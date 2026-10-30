<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Decides which groups can be moved under a new parent and what their members gain or lose. Pure logic:
 * the caller passes the group tree, the viewing levels and the check of the root asset rules.
 *
 * A group moves with all its subgroups. A move that would give or take away administrator access
 * (core.login.admin) or Super User rights (core.admin) for the group or any of its subgroups is refused:
 * those changes are made in Joomla, group by group, never in bulk.
 */
final class GroupMove
{
    public const MOVE    = 'move';
    public const SAME    = 'same';
    public const COVERED = 'covered';
    public const REFUSED = 'refused';

    /**
     * @param  array<int, object>                                     $groups   UserDirectory::groups(), in tree order
     * @param  list<array{id: int, title: string, rules: list<int>}>   $levels   viewing access levels
     * @param  list<int>                                              $selected groups the operator ticked
     * @param  callable(string, list<int>): bool                      $allow    root asset rule for a list of identities
     * @param  array<int, array{username: string, groups: list<int>}> $accounts accounts in any selected group or below,
     *         with all their groups: an account's access depends on every group it is in, not just the moved one
     * @return list<array{id: int, from: int, status: string, reason: string, via: int, subtree: list<int>,
     *     gained: list<int>, lost: list<int>, access: list<array{id: int, before: string, after: string}>,
     *     accounts: list<array{username: string, before: string, after: string}>}>
     *     in tree order; via is the selected group a covered one moves with
     */
    public static function plan(array $groups, array $levels, array $selected, int $target, callable $allow, array $accounts = []): array
    {
        $wanted = array_flip(array_map('intval', $selected));
        $moves  = [];
        $titles = [];
        foreach ($groups as $group) {
            if ((int) $group->parent_id === $target) {
                $titles[mb_strtolower(trim((string) $group->title))] = true;
            }
        }
        $rows = [];
        foreach ($groups as $group) {
            $id = (int) $group->id;
            if (!isset($wanted[$id])) {
                continue;
            }
            $row = ['id' => $id, 'from' => (int) $group->parent_id, 'status' => self::REFUSED, 'reason' => '', 'via' => 0,
                'subtree' => GroupRemoval::subtree($groups, $id), 'gained' => [], 'lost' => [], 'access' => [], 'accounts' => []];
            $via   = self::movingAncestor($groups, $id, $moves);
            $title = mb_strtolower(trim((string) $group->title));
            if ($via > 0) {
                $row['status'] = self::COVERED;
                $row['via']    = $via;
            } elseif (!isset($groups[$target])) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TARGET';
            } elseif ((int) $group->parent_id === 0) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_ROOT';
            } elseif ((bool) $group->superUser) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_SUPER';
            } elseif (\in_array($target, $row['subtree'], true)) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_CYCLE';
            } elseif ((bool) $groups[$target]->superUser) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TARGET_SUPER';
            } elseif ((int) $group->parent_id === $target) {
                $row['status'] = self::SAME;
            } elseif (isset($titles[$title])) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TITLE';
            } else {
                // Checked together with the moves already accepted: an account in two moved groups is
                // judged on the final tree.
                $tentative = $moves + [$id => $target];
                $row       = self::changes($groups, $levels, $row, $tentative, $allow);
                $row['accounts'] = $row['access'] ? [] : self::accountChanges($groups, $accounts, $tentative, $allow);
                if ($row['access']) {
                    $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_BACKEND';
                } elseif ($row['accounts']) {
                    $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_ACCOUNTS';
                } else {
                    $row['status'] = self::MOVE;
                    $moves         = $tentative;
                    // Two groups with the same name cannot end up under the same parent.
                    $titles[$title] = true;
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Levels gained and lost by the group and its subgroups, and any change in their administrator access.
     *
     * @param  array<int, object>                                   $groups
     * @param  list<array{id: int, title: string, rules: list<int>}> $levels
     * @param  array<string, mixed>                                 $row
     * @param  array<int, int>                                      $moves  group id => new parent
     * @param  callable(string, list<int>): bool                    $allow
     * @return array<string, mixed>
     */
    private static function changes(array $groups, array $levels, array $row, array $moves, callable $allow): array
    {
        $gained = [];
        $lost   = [];
        foreach ($row['subtree'] as $id) {
            $before = self::path($groups, $id);
            $after  = self::path($groups, $id, $moves);
            $was    = self::levelsOf($levels, $before);
            $will   = self::levelsOf($levels, $after);
            $gained += array_fill_keys(array_diff($will, $was), true);
            $lost   += array_fill_keys(array_diff($was, $will), true);
            $old     = self::accessOf($groups[$id]);
            $new     = self::access($allow, $after);
            if ($old !== $new) {
                $row['access'][] = ['id' => $id, 'before' => $old, 'after' => $new];
            }
        }
        $order = array_column($levels, 'id');
        $row['gained'] = array_values(array_filter($order, fn ($id) => isset($gained[$id])));
        $row['lost']   = array_values(array_filter($order, fn ($id) => isset($lost[$id])));
        return $row;
    }

    /**
     * Accounts whose administrator access would change: Joomla decides on all the groups of the account
     * together, so a deny inherited through one group can block the access another group gives.
     *
     * @param  array<int, object>                                     $groups
     * @param  array<int, array{username: string, groups: list<int>}> $accounts
     * @param  array<int, int>                                        $moves
     * @param  callable(string, list<int>): bool                      $allow
     * @return list<array{username: string, before: string, after: string}>
     */
    private static function accountChanges(array $groups, array $accounts, array $moves, callable $allow): array
    {
        $moved = [];
        foreach (array_keys($moves) as $root) {
            $moved += array_fill_keys(GroupRemoval::subtree($groups, $root), true);
        }
        $changes = [];
        foreach ($accounts as $id => $account) {
            $mine = array_map('intval', $account['groups']);
            if (!array_intersect_key(array_flip($mine), $moved)) {
                continue;
            }
            $before = [-(int) $id];
            $after  = [-(int) $id];
            foreach ($mine as $group) {
                $before = array_merge($before, self::path($groups, $group));
                $after  = array_merge($after, self::path($groups, $group, $moves));
            }
            $was  = self::access($allow, array_values(array_unique($before)));
            $will = self::access($allow, array_values(array_unique($after)));
            if ($was !== $will) {
                $changes[] = ['username' => (string) $account['username'], 'before' => $was, 'after' => $will];
            }
        }
        return $changes;
    }

    /**
     * @param callable(string, list<int>): bool $allow
     * @param list<int>                          $identities
     */
    private static function access(callable $allow, array $identities): string
    {
        return $allow('core.admin', $identities) ? 'super' : ($allow('core.login.admin', $identities) ? 'backend' : 'none');
    }

    private static function accessOf(object $group): string
    {
        return (bool) $group->superUser ? 'super' : ((bool) $group->backend ? 'backend' : 'none');
    }

    /**
     * @param  list<array{id: int, title: string, rules: list<int>}> $levels
     * @param  list<int>                                            $path
     * @return list<int>
     */
    private static function levelsOf(array $levels, array $path): array
    {
        $result = [AccessMatrix::PUBLIC_LEVEL];
        foreach ($levels as $level) {
            if (array_intersect(array_map('intval', $level['rules']), $path)) {
                $result[] = (int) $level['id'];
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * The group and its ancestors, optionally with some groups under a new parent.
     *
     * @param  array<int, object> $groups
     * @param  array<int, int>    $moves group id => new parent id
     * @return list<int>
     */
    public static function path(array $groups, int $id, array $moves = []): array
    {
        $path = [];
        while ($id > 0 && isset($groups[$id]) && !isset($path[$id])) {
            $path[$id] = $id;
            $id        = $moves[$id] ?? (int) $groups[$id]->parent_id;
        }
        return array_values($path);
    }

    /**
     * @param array<int, object> $groups
     * @param array<int, int>    $moves
     */
    private static function movingAncestor(array $groups, int $id, array $moves): int
    {
        foreach (self::path($groups, $id) as $ancestor) {
            if ($ancestor !== $id && isset($moves[$ancestor])) {
                return $ancestor;
            }
        }
        return 0;
    }
}
