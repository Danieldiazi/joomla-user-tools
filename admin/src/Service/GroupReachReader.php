<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Reads what GroupReach needs from Joomla's own tables: active accounts per group branch, the
 * articles the site would show grouped by their levels, and the members of one branch. Read only,
 * no table of its own; each figure is one aggregate query, never one query per account.
 */
final class GroupReachReader
{
    /** Published and archived articles can be opened on the site (com_content's filter.published and filter.archived). */
    private const VISIBLE_STATES = [1, 2];

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Distinct active accounts in each group or any of its subgroups (nested set of #__usergroups).
     *
     * @return array<int, int>
     */
    public function activeMembersPerBranch(): array
    {
        $db    = $this->db;
        $query = $db->createQuery()
            ->select([$db->quoteName('g.id'), 'COUNT(DISTINCT ' . $db->quoteName('m.user_id') . ') AS ' . $db->quoteName('total')])
            ->from($db->quoteName('#__usergroups', 'g'))
            ->join('INNER', $db->quoteName('#__usergroups', 's'),
                $db->quoteName('s.lft') . ' >= ' . $db->quoteName('g.lft') . ' AND ' . $db->quoteName('s.rgt') . ' <= ' . $db->quoteName('g.rgt'))
            ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), $db->quoteName('m.group_id') . ' = ' . $db->quoteName('s.id'))
            ->join('INNER', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id'))
            ->where($db->quoteName('u.block') . ' = 0')
            ->group($db->quoteName('g.id'));
        $counts = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $counts[(int) $row->id] = (int) $row->total;
        }
        return $counts;
    }

    /**
     * Articles the site can show now, grouped by their level and their category's level: published
     * or archived, inside their publishing dates, in a published or archived category. Joomla's
     * article view refuses the rest to anyone without edit permissions.
     *
     * @param  string $now UTC date in SQL format
     * @return list<array{access: int, category: int, total: int}>
     */
    public function articlesByLevels(string $now): array
    {
        $db = $this->db;
        if (!$this->tableExists('#__content')) {
            return [];
        }
        $query = $db->createQuery()
            ->select([$db->quoteName('a.access'), $db->quoteName('c.access', 'category_access'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->from($db->quoteName('#__content', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->whereIn($db->quoteName('a.state'), self::VISIBLE_STATES)
            ->where($db->quoteName('c.published') . ' > 0')
            ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' <= :up)')
            ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' >= :down)')
            ->bind(':up', $now)
            ->bind(':down', $now)
            ->group([$db->quoteName('a.access'), $db->quoteName('c.access')]);
        $pairs = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $pairs[] = ['access' => (int) $row->access, 'category' => (int) $row->category_access, 'total' => (int) $row->total];
        }
        return $pairs;
    }

    /**
     * Number of articles behind one figure of the table: those the site can show now (the same
     * conditions as articlesByLevels()) whose level and category level are one of $pairs.
     *
     * @param list<array{access: int, category: int}> $pairs      GroupReach::selection()
     * @param list<int>|null                          $viewLevels levels the operator may see; null only for a global Super User
     */
    public function countArticles(array $pairs, string $now, ?array $viewLevels): int
    {
        $query = $this->articles($pairs, $now, $viewLevels)->select('COUNT(*)');
        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * One page of those articles, by category and title, with what is needed to decide on them.
     *
     * @param  list<array{access: int, category: int}> $pairs
     * @param  list<int>|null                          $viewLevels
     * @return list<array{id: int, title: string, category: string, level: string, access: int, categoryLevel: string, categoryAccess: int,
     *     state: int, created_by: int, modified: ?string, hits: int}>
     */
    public function listArticles(array $pairs, string $now, ?array $viewLevels, int $offset, int $limit): array
    {
        $db    = $this->db;
        $query = $this->articles($pairs, $now, $viewLevels)
            ->select([
                $db->quoteName('a.id'), $db->quoteName('a.title'), $db->quoteName('a.access'), $db->quoteName('a.state'), $db->quoteName('a.created_by'),
                $db->quoteName('a.modified'), $db->quoteName('a.created'), $db->quoteName('a.hits'), $db->quoteName('c.title', 'category'),
                $db->quoteName('c.access', 'category_access'), $db->quoteName('l.title', 'level'), $db->quoteName('cl.title', 'category_level'),
            ])
            ->join('LEFT', $db->quoteName('#__viewlevels', 'l'), $db->quoteName('l.id') . ' = ' . $db->quoteName('a.access'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'cl'), $db->quoteName('cl.id') . ' = ' . $db->quoteName('c.access'))
            ->order([$db->quoteName('c.title'), $db->quoteName('a.title'), $db->quoteName('a.id')]);
        $items = [];
        foreach ($db->setQuery($query, $offset, $limit)->loadObjectList() as $row) {
            $modified = $row->modified ?? $row->created;
            $items[]  = [
                'id'             => (int) $row->id,
                'title'          => (string) $row->title,
                'category'       => (string) $row->category,
                'level'          => (string) ($row->level ?? ''),
                'access'         => (int) $row->access,
                'categoryLevel'  => (string) ($row->category_level ?? ''),
                'categoryAccess' => (int) $row->category_access,
                'state'          => (int) $row->state,
                'created_by'     => (int) $row->created_by,
                'modified'       => $modified !== null ? substr((string) $modified, 0, 19) : null,
                'hits'           => (int) $row->hits,
            ];
        }
        return $items;
    }

    /**
     * The articles of articlesByLevels() limited to some level combinations and, for an operator
     * who is not a global Super User, to the levels Joomla lets them see (article and category),
     * as the administrator's article list does: the reach screen counts every article, but titles
     * are only listed within the operator's own access.
     *
     * @param list<array{access: int, category: int}> $pairs
     * @param list<int>|null                          $viewLevels
     */
    private function articles(array $pairs, string $now, ?array $viewLevels): QueryInterface
    {
        $db    = $this->db;
        $query = $db->createQuery()
            ->from($db->quoteName('#__content', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->whereIn($db->quoteName('a.state'), self::VISIBLE_STATES)
            ->where($db->quoteName('c.published') . ' > 0')
            ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' <= :up)')
            ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' >= :down)')
            ->bind(':up', $now)
            ->bind(':down', $now);
        if (!$pairs) {
            return $query->where('1 = 0');
        }
        // Joomla binds by reference: each placeholder needs its own variable, not a reused loop one.
        $access     = array_map(fn (array $pair): int => (int) $pair['access'], array_values($pairs));
        $category   = array_map(fn (array $pair): int => (int) $pair['category'], array_values($pairs));
        $conditions = [];
        foreach (array_keys($access) as $index) {
            $conditions[] = '(' . $db->quoteName('a.access') . ' = :pa' . $index . ' AND ' . $db->quoteName('c.access') . ' = :pc' . $index . ')';
            $query->bind(':pa' . $index, $access[$index], ParameterType::INTEGER)->bind(':pc' . $index, $category[$index], ParameterType::INTEGER);
        }
        $query->where('(' . implode(' OR ', $conditions) . ')');
        if ($viewLevels !== null) {
            $allowed = array_values(array_unique(array_filter(array_map('intval', $viewLevels), fn (int $id): bool => $id > 0)));
            if (!$allowed) {
                return $query->where('1 = 0');
            }
            $query->whereIn($db->quoteName('a.access'), $allowed)->whereIn($db->quoteName('c.access'), $allowed);
        }
        return $query;
    }

    /** Active accounts in the branch (the group with lft/rgt and its subgroups). */
    public function countMembers(int $lft, int $rgt, bool $blocked = false): int
    {
        $query = $this->members($lft, $rgt, $blocked)->select('COUNT(DISTINCT ' . $this->db->quoteName('u.id') . ')');
        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * One page of the active accounts in a branch, by username, with the groups of the branch
     * each one belongs to. No email or other contact data: the list is meant to decide whether
     * the group is worth keeping, and the profile tool is one click away.
     *
     * @return list<array{id: int, name: string, username: string, lastvisit: ?string, registered: ?string, groups: list<int>}>
     */
    public function listMembers(int $lft, int $rgt, int $offset, int $limit): array
    {
        $db    = $this->db;
        $query = $this->members($lft, $rgt, false)
            ->select('DISTINCT ' . implode(', ', $db->quoteName(['u.id', 'u.name', 'u.username', 'u.lastvisitDate', 'u.registerDate'])))
            ->order([$db->quoteName('u.username'), $db->quoteName('u.id')]);
        $accounts = [];
        foreach ($db->setQuery($query, $offset, $limit)->loadObjectList() as $row) {
            $accounts[(int) $row->id] = [
                'id'         => (int) $row->id,
                'name'       => (string) $row->name,
                'username'   => (string) $row->username,
                'lastvisit'  => $row->lastvisitDate !== null ? substr((string) $row->lastvisitDate, 0, 19) : null,
                'registered' => $row->registerDate !== null ? substr((string) $row->registerDate, 0, 19) : null,
                'groups'     => [],
            ];
        }
        if (!$accounts) {
            return [];
        }
        // The groups of the page's accounts inside the branch, in one query.
        $ids   = array_keys($accounts);
        $query = $db->createQuery()
            ->select([$db->quoteName('m.user_id'), $db->quoteName('m.group_id')])
            ->from($db->quoteName('#__user_usergroup_map', 'm'))
            ->join('INNER', $db->quoteName('#__usergroups', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('m.group_id'))
            ->whereIn($db->quoteName('m.user_id'), $ids)
            ->where($db->quoteName('s.lft') . ' >= :lft')
            ->where($db->quoteName('s.rgt') . ' <= :rgt')
            ->bind(':lft', $lft, ParameterType::INTEGER)
            ->bind(':rgt', $rgt, ParameterType::INTEGER)
            ->order($db->quoteName('s.lft'));
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $accounts[(int) $row->user_id]['groups'][] = (int) $row->group_id;
        }
        return array_values($accounts);
    }

    private function members(int $lft, int $rgt, bool $blocked): QueryInterface
    {
        $db    = $this->db;
        $state = $blocked ? 1 : 0;
        return $db->createQuery()
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id'))
            ->join('INNER', $db->quoteName('#__usergroups', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('m.group_id'))
            ->where($db->quoteName('s.lft') . ' >= :lft')
            ->where($db->quoteName('s.rgt') . ' <= :rgt')
            ->where($db->quoteName('u.block') . ' = :block')
            ->bind(':lft', $lft, ParameterType::INTEGER)
            ->bind(':rgt', $rgt, ParameterType::INTEGER)
            ->bind(':block', $state, ParameterType::INTEGER);
    }

    private function tableExists(string $table): bool
    {
        $name = str_replace('#__', $this->db->getPrefix(), $table);
        return \in_array($name, array_map('strval', $this->db->getTableList()), true);
    }
}
