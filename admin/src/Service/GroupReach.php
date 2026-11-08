<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Reach of each user group: how many active accounts belong to it (directly and through its
 * subgroups) and how many articles a member of only that group can open on the site. Pure logic:
 * GroupReachReader reads the counts, AccessMatrix decides which levels each group has.
 *
 * An article is visible to a group when the group has the article's level and the level of its
 * category, as com_content's ArticleModel checks before showing it. Super User groups see every
 * article whatever its levels, as Access::getAuthorisedViewLevels() grants them every level.
 *
 * What this does not model: accounts assigned to a level one by one (negative rules of
 * #__viewlevels), the parent categories' levels (Joomla only checks the article's own category),
 * language filters and per-article permissions to edit unpublished items. An account in several
 * groups sees the union of what its groups see, so the figures describe one group, not one person.
 */
final class GroupReach
{
    /** Groups with fewer active accounts than this (and at least one) are flagged, unless the request says otherwise. */
    public const DEFAULT_FEW = 5;
    public const MAX_FEW     = 1000;

    /** Accounts per page in the list of members. */
    public const PAGE_SIZE = 50;

    /** A threshold from a request, kept within 1..MAX_FEW. */
    public static function threshold(mixed $value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value === false ? self::DEFAULT_FEW : max(1, min(self::MAX_FEW, $value));
    }

    /**
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $matrix  AccessMatrix::build()
     * @param  array<int, object>                                                           $groups  UserDirectory::groups(), with lft and rgt
     * @param  array<int, int>                                                              $members active accounts per group, counting its subgroups
     * @param  list<array{access: int, category: int, total: int}>                          $articles visible articles grouped by their level and their category's level
     * @return list<array{id: int, title: string, level: int, superUser: bool, backend: bool, guest: bool, direct: int, members: int,
     *     few: bool, empty: bool, levels: list<int>, articles: int, exclusive: int}>
     */
    public static function build(array $matrix, array $groups, array $members, array $articles, int $few = self::DEFAULT_FEW): array
    {
        [$levelsOf, $super, $viewers] = self::analyse($matrix, $articles);
        $rows = [];
        foreach ($matrix['rows'] as $row) {
            $id      = (int) $row['id'];
            $visible = 0;
            $only    = 0;
            foreach ($articles as $index => $pair) {
                [$sees, $alone] = self::reach($id, $index, $groups, $super, $viewers);
                $visible += $sees ? (int) $pair['total'] : 0;
                $only    += $alone ? (int) $pair['total'] : 0;
            }
            $count  = (int) ($members[$id] ?? 0);
            $rows[] = [
                'id'        => $id,
                'title'     => (string) $row['title'],
                'level'     => (int) $row['level'],
                'superUser' => $super[$id],
                'backend'   => (bool) $row['backend'],
                'guest'     => (bool) $row['guest'],
                'direct'    => (int) $row['members'],
                'members'   => $count,
                'few'       => $count > 0 && $count < $few,
                'empty'     => $count === 0,
                'levels'    => array_keys($levelsOf[$id]),
                'articles'  => $visible,
                'exclusive' => $only,
            ];
        }
        return $rows;
    }

    /**
     * The combinations of article level and category level behind one group's figure, so the list
     * of those articles uses exactly the rule of the table: every article it can view, or only
     * those its branch alone can view ($exclusive).
     *
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $matrix
     * @param  array<int, object>                                                           $groups
     * @param  list<array{access: int, category: int, total: int}>                          $articles
     * @return list<array{access: int, category: int}>
     */
    public static function selection(array $matrix, array $groups, array $articles, int $groupId, bool $exclusive): array
    {
        [, $super, $viewers] = self::analyse($matrix, $articles);
        if (!isset($super[$groupId])) {
            return [];
        }
        $pairs = [];
        foreach ($articles as $index => $pair) {
            [$sees, $alone] = self::reach($groupId, $index, $groups, $super, $viewers);
            if ($exclusive ? $alone : $sees) {
                $pairs[] = ['access' => (int) $pair['access'], 'category' => (int) $pair['category']];
            }
        }
        return $pairs;
    }

    /**
     * Levels of each group, which groups are Super Users and which ordinary groups can open each
     * combination of article level and category level. Super User groups are left out of the
     * viewers: they see everything, so they never make an article "shared".
     *
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $matrix
     * @param  list<array{access: int, category: int, total: int}>                          $articles
     * @return array{0: array<int, array<int, true>>, 1: array<int, bool>, 2: array<int, list<int>>}
     */
    private static function analyse(array $matrix, array $articles): array
    {
        $levelsOf = [];
        $super    = [];
        foreach ($matrix['rows'] as $row) {
            $id    = (int) $row['id'];
            $owned = [];
            foreach ($row['cells'] as $levelId => $cell) {
                if (($cell['state'] ?? 'none') !== 'none') {
                    $owned[(int) $levelId] = true;
                }
            }
            $levelsOf[$id] = $owned;
            $super[$id]    = (bool) $row['superUser'];
        }
        $viewers = [];
        foreach ($articles as $index => $pair) {
            $viewers[$index] = [];
            foreach ($levelsOf as $groupId => $owned) {
                if (!$super[$groupId] && isset($owned[(int) $pair['access']], $owned[(int) $pair['category']])) {
                    $viewers[$index][] = $groupId;
                }
            }
        }
        return [$levelsOf, $super, $viewers];
    }

    /**
     * Whether a group can open the articles of one combination, and whether its branch alone can.
     * For a Super User group, "alone" means that no ordinary group has both levels.
     *
     * @param  array<int, object>     $groups
     * @param  array<int, bool>       $super
     * @param  array<int, list<int>>  $viewers
     * @return array{0: bool, 1: bool}
     */
    private static function reach(int $id, int $index, array $groups, array $super, array $viewers): array
    {
        if ($super[$id]) {
            return [true, !$viewers[$index]];
        }
        if (!\in_array($id, $viewers[$index], true)) {
            return [false, false];
        }
        $group = $groups[$id] ?? null;
        return [true, $group !== null && self::insideBranch($viewers[$index], $groups, $group)];
    }

    /**
     * True when every group that can open an article hangs from $branch (or is $branch): removing
     * the branch would leave the article visible to nobody but Super Users and accounts assigned
     * to its level one by one.
     *
     * @param list<int>          $viewers
     * @param array<int, object> $groups
     */
    private static function insideBranch(array $viewers, array $groups, object $branch): bool
    {
        foreach ($viewers as $viewer) {
            $group = $groups[$viewer] ?? null;
            if ($group === null || (int) $group->lft < (int) $branch->lft || (int) $group->rgt > (int) $branch->rgt) {
                return false;
            }
        }
        return true;
    }

    /**
     * The reach as table rows for a CSV file. Texts come through $text so this stays free of Joomla.
     *
     * @param  list<array<string, mixed>>       $rows   see build()
     * @param  array<int, string>               $paths  AccessMatrix::paths()
     * @param  array<int, string>               $titles level id => title
     * @param  callable(string): string          $text
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public static function export(array $rows, array $paths, array $titles, callable $text): array
    {
        $header = [$text('COM_USERTOOLS_ACCESS_CSV_GROUP_ID'), $text('COM_USERTOOLS_ACCESS_GROUP'), $text('COM_USERTOOLS_ACCESS_CSV_PATH'),
            $text('COM_USERTOOLS_REACH_DIRECT'), $text('COM_USERTOOLS_REACH_MEMBERS'), $text('COM_USERTOOLS_REACH_ARTICLES'),
            $text('COM_USERTOOLS_REACH_EXCLUSIVE'), $text('COM_USERTOOLS_ACCESS_LEVELS')];
        $lines = [];
        foreach ($rows as $row) {
            $levels  = $row['superUser'] ? $text('COM_USERTOOLS_REACH_ALL_LEVELS')
                : implode(', ', array_map(fn (int $id): string => $titles[$id] ?? '#' . $id, $row['levels']));
            $lines[] = [(int) $row['id'], (string) $row['title'], $paths[$row['id']] ?? (string) $row['title'], (int) $row['direct'], (int) $row['members'],
                (int) $row['articles'], (int) $row['exclusive'], $levels];
        }
        return ['header' => $header, 'rows' => $lines];
    }

    /** Last page for a number of accounts (at least 1), and a requested page kept within it. */
    public static function page(int $requested, int $total): int
    {
        return max(1, min($requested, (int) ceil(max(1, $total) / self::PAGE_SIZE)));
    }
}
