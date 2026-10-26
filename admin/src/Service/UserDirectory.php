<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Loads accounts, groups and recent sessions with a handful of queries instead of one or more
 * queries per account. Permissions are evaluated with Joomla's own rule engine on the root asset,
 * exactly as Access::check() does: authorised groups plus the negative user id as identities.
 */
final class UserDirectory
{
    private const CHUNK = 500;

    /** @var \Closure(string, list<int>): bool */
    private \Closure $allow;

    /** @var array<int, object>|null */
    private ?array $groups = null;

    /** @var array<int, list<int>> */
    private array $ancestorCache = [];

    /** @param (callable(string, list<int>): bool)|null $allow */
    public function __construct(private DatabaseInterface $db, ?callable $allow = null, private string $rootUser = '')
    {
        $this->allow = $allow !== null ? \Closure::fromCallable($allow)
            : static fn (string $action, array $identities): bool => (bool) Access::getAssetRules(1)->allow($action, $identities);
    }

    /**
     * Groups ordered as in the tree, with the flags used by the exclusion screens.
     *
     * @return array<int, \stdClass> id, title, parent_id, lft, rgt, level, superUser, backend
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }
        $db    = $this->db;
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'title']))
            ->from($db->quoteName('#__usergroups'))
            ->order($db->quoteName('lft'));
        $rows   = $db->setQuery($query)->loadObjectList();
        $groups = [];
        $stack  = [];
        foreach ($rows as $row) {
            while ($stack && $stack[\count($stack) - 1] < (int) $row->lft) {
                array_pop($stack);
            }
            $group = (object) [
                'id'        => (int) $row->id,
                'parent_id' => (int) $row->parent_id,
                'lft'       => (int) $row->lft,
                'rgt'       => (int) $row->rgt,
                'title'     => (string) $row->title,
                'level'     => \count($stack),
                'superUser' => false,
                'backend'   => false,
            ];
            $stack[] = $group->rgt;
            $groups[$group->id] = $group;
        }
        foreach ($groups as $group) {
            $identities       = $this->ancestors($groups, $group->id);
            $group->superUser = ($this->allow)('core.admin', $identities);
            $group->backend   = $group->superUser || ($this->allow)('core.login.admin', $identities);
        }
        return $this->groups = $groups;
    }

    /**
     * @param  list<int>|null $ids null loads every account
     * @return array<int, UserRecord>
     */
    public function users(?array $ids = null): array
    {
        $db      = $this->db;
        $records = [];
        $chunks  = $ids === null ? [null] : array_chunk(array_values(array_unique(array_map('intval', $ids))), self::CHUNK);
        foreach ($chunks as $chunk) {
            $query = $db->createQuery()
                ->select($db->quoteName(['id', 'name', 'username', 'email', 'block', 'lastvisitDate', 'registerDate']))
                ->from($db->quoteName('#__users'))
                ->order($db->quoteName('id'));
            if ($chunk !== null) {
                $query->whereIn($db->quoteName('id'), $chunk ?: [0]);
            }
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $records[(int) $row->id] = $row;
            }
        }
        if (!$records) {
            return [];
        }
        $membership = $this->membership(array_keys($records));
        $groups     = $this->groups();
        $users      = [];
        foreach ($records as $id => $row) {
            $direct     = $membership[$id] ?? [];
            $authorised = [];
            foreach ($direct as $groupId) {
                foreach ($this->ancestors($groups, $groupId) as $ancestor) {
                    $authorised[$ancestor] = $ancestor;
                }
            }
            $authorised = array_values($authorised);
            sort($authorised);
            $identities = array_merge([-$id], $authorised);
            $super      = $this->isRootUser($id, (string) $row->username) || ($authorised && ($this->allow)('core.admin', $identities));
            $backend    = $super || ($authorised && ($this->allow)('core.login.admin', $identities));
            $users[$id] = new UserRecord(
                $id,
                (string) $row->username,
                (string) $row->name,
                (string) $row->email,
                (int) $row->block,
                $row->lastvisitDate !== null ? (string) $row->lastvisitDate : null,
                $row->registerDate !== null ? (string) $row->registerDate : null,
                $direct,
                $authorised,
                $super,
                $backend,
            );
        }
        return $users;
    }

    /**
     * Viewing access levels in their configured order.
     *
     * @return list<array{id: int, title: string, rules: list<int>}>
     */
    public function viewLevels(): array
    {
        $db     = $this->db;
        $query  = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'rules']))
            ->from($db->quoteName('#__viewlevels'))
            ->order([$db->quoteName('ordering'), $db->quoteName('title')]);
        $levels = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $levels[] = ['id' => (int) $row->id, 'title' => (string) $row->title, 'rules' => AccessMatrix::rules($row->rules)];
        }
        return $levels;
    }

    /**
     * Accounts that are not blocked, per group they are directly assigned to.
     *
     * @return array<int, int>
     */
    public function activeMembersPerGroup(): array
    {
        $db    = $this->db;
        $query = $db->createQuery()
            ->select([$db->quoteName('m.group_id'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->from($db->quoteName('#__user_usergroup_map', 'm'))
            ->join('INNER', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id'))
            ->where($db->quoteName('u.block') . ' = 0')
            ->group($db->quoteName('m.group_id'));
        $counts = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $counts[(int) $row->group_id] = (int) $row->total;
        }
        return $counts;
    }

    /** Account id by username or email (case-insensitive on every database), or 0. */
    public function findId(string $login): int
    {
        $login = trim($login);
        if ($login === '') {
            return 0;
        }
        $db    = $this->db;
        $query = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__users'))
            ->where('(LOWER(' . $db->quoteName('username') . ') = LOWER(:login) OR LOWER(' . $db->quoteName('email') . ') = LOWER(:email))')
            ->bind(':login', $login)
            ->bind(':email', $login)
            ->order($db->quoteName('id'));
        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }

    /** Active Super Users other than the given account (members of a group with core.admin, or the root user). */
    public function otherActiveSuperUsers(int $exceptId): int
    {
        $superGroups = array_keys(array_filter($this->groups(), fn (object $g) => $g->superUser));
        $db          = $this->db;
        $ids         = [];
        if ($superGroups) {
            $query = $db->createQuery()
                ->select('DISTINCT ' . $db->quoteName('m.user_id'))
                ->from($db->quoteName('#__user_usergroup_map', 'm'))
                ->join('INNER', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id'))
                ->whereIn($db->quoteName('m.group_id'), $superGroups)
                ->where($db->quoteName('u.block') . ' = 0');
            $ids = array_map('intval', $db->setQuery($query)->loadColumn());
        }
        if ($this->rootUser !== '') {
            $root = is_numeric($this->rootUser) ? (int) $this->rootUser : $this->findId($this->rootUser);
            foreach ($root > 0 ? $this->users([$root]) : [] as $user) {
                if ($user->block === 0) {
                    $ids[] = $user->id;
                }
            }
        }
        return \count(array_diff(array_unique($ids), [$exceptId]));
    }

    /** Number of accounts that are not blocked. */
    public function activeCount(): int
    {
        $db = $this->db;
        return (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__users'))
            ->where($db->quoteName('block') . ' = 0'))->loadResult();
    }

    /**
     * Accounts with a session row newer than the given UNIX time (requires Joomla session metadata).
     *
     * @param  list<int> $ids
     * @return array<int, true>
     */
    public function withRecentSession(array $ids, int $since): array
    {
        $db     = $this->db;
        $active = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), self::CHUNK) as $chunk) {
            $query = $db->createQuery()
                ->select('DISTINCT ' . $db->quoteName('userid'))
                ->from($db->quoteName('#__session'))
                ->whereIn($db->quoteName('userid'), $chunk)
                ->where($db->quoteName('time') . ' >= :since')
                ->bind(':since', $since, ParameterType::INTEGER);
            foreach ($db->setQuery($query)->loadColumn() as $id) {
                $active[(int) $id] = true;
            }
        }
        return $active;
    }

    /** Existence checks used before creating accounts (case-insensitive on every database). */
    public function exists(string $field, string $value): bool
    {
        if (!\in_array($field, ['username', 'email'], true)) {
            throw new \InvalidArgumentException('Unsupported field');
        }
        $db    = $this->db;
        $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__users'))
            ->where('LOWER(' . $db->quoteName($field) . ') = LOWER(:value)')
            ->bind(':value', $value);
        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    /**
     * @param  list<int> $ids
     * @return array<int, list<int>>
     */
    private function membership(array $ids): array
    {
        $db  = $this->db;
        $map = [];
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $query = $db->createQuery()
                ->select($db->quoteName(['user_id', 'group_id']))
                ->from($db->quoteName('#__user_usergroup_map'))
                ->whereIn($db->quoteName('user_id'), $chunk);
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $map[(int) $row->user_id][] = (int) $row->group_id;
            }
        }
        return $map;
    }

    /**
     * @param  array<int, object> $groups
     * @return list<int> the group and all its ancestors
     */
    private function ancestors(array $groups, int $groupId): array
    {
        if (isset($this->ancestorCache[$groupId])) {
            return $this->ancestorCache[$groupId];
        }
        if (!isset($groups[$groupId])) {
            return [];
        }
        $target = $groups[$groupId];
        $result = [];
        foreach ($groups as $group) {
            if ($group->lft <= $target->lft && $group->rgt >= $target->rgt) {
                $result[] = $group->id;
            }
        }
        return $this->ancestorCache[$groupId] = $result;
    }

    private function isRootUser(int $id, string $username): bool
    {
        if ($this->rootUser === '') {
            return false;
        }
        return is_numeric($this->rootUser) ? (int) $this->rootUser === $id : $this->rootUser === $username;
    }
}
