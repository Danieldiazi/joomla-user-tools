<?php

namespace Danieldiazi\Module\UsertoolsGroups\Site\Helper;

\defined('_JEXEC') or die;

use Danieldiazi\Module\UsertoolsGroups\Site\Service\GroupTree;
use Joomla\CMS\Access\Access;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Reads group membership with a fixed number of queries, whatever the size of the group. Read only:
 * it never changes accounts or groups.
 */
class MembershipHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    private const MAX_LIMIT = 1000;

    private ?GroupTree $tree = null;

    /** @var (\Closure(string, list<int>): bool)|null */
    private ?\Closure $allow = null;

    /** Replaces Joomla's rule engine (tests). @param callable(string, list<int>): bool $allow */
    public function setAllow(callable $allow): void
    {
        $this->allow = \Closure::fromCallable($allow);
    }

    /**
     * Groups of one account.
     *
     * @return array{groups: list<array{id: int, title: string, level: int, direct: bool}>}
     */
    public function userGroups(int $userId, Registry $params): array
    {
        $db     = $this->getDatabase();
        $query  = $db->createQuery()
            ->select($db->quoteName('group_id'))
            ->from($db->quoteName('#__user_usergroup_map'))
            ->where($db->quoteName('user_id') . ' = :user')
            ->bind(':user', $userId, ParameterType::INTEGER);
        $direct = array_map('intval', $db->setQuery($query)->loadColumn());
        $hidden = array_map('intval', (array) $params->get('hidden_groups', []));
        return ['groups' => $this->tree()->forUser($direct, (bool) $params->get('inherited', 0), $hidden)];
    }

    /**
     * Active members of a group (and, when asked, of its subgroups), sorted by what is shown.
     *
     * @return array{group: string, members: list<string>, total: int}
     */
    public function groupMembers(Registry $params): array
    {
        $tree    = $this->tree();
        $groupId = (int) $params->get('group_id', 0);
        $empty   = ['group' => $tree->title($groupId), 'members' => [], 'total' => 0];
        $groups  = $tree->descendants($groupId, (bool) $params->get('subgroups', 0));
        if (!$groups) {
            return $empty;
        }
        $display = (string) $params->get('display', 'name');
        $limit   = max(1, min(self::MAX_LIMIT, (int) $params->get('limit', 50)));
        $hidden  = $params->get('hide_backend', 1) ? $tree->backendGroups($this->allow ?? self::rootRules(...)) : [];

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->from($db->quoteName('#__users', 'u'))
            ->where($db->quoteName('u.block') . ' = 0');
        // Subqueries are added as strings, so their values are bound on the outer query.
        $in = $query->bindArray($groups);
        $query->where('EXISTS (' . $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__user_usergroup_map', 'm'))
            ->where($db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('m.group_id') . ' IN (' . implode(',', $in) . ')') . ')');
        if ($hidden) {
            $out = $query->bindArray($hidden);
            $query->where('NOT EXISTS (' . $db->createQuery()
                ->select('1')
                ->from($db->quoteName('#__user_usergroup_map', 'h'))
                ->where($db->quoteName('h.user_id') . ' = ' . $db->quoteName('u.id'))
                ->where($db->quoteName('h.group_id') . ' IN (' . implode(',', $out) . ')') . ')');
        }

        $count = clone $query;
        $count->select('COUNT(*)');
        $total = (int) $db->setQuery($count)->loadResult();
        if ($total === 0) {
            return $empty;
        }

        $order = $display === 'username' ? ['u.username', 'u.id'] : ['u.name', 'u.username', 'u.id'];
        $query->select($db->quoteName(['u.id', 'u.name', 'u.username']))
            ->order(array_map([$db, 'quoteName'], $order))
            ->setLimit($limit);
        $members = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $members[] = GroupTree::label((string) $row->name, (string) $row->username, $display);
        }
        return ['group' => $empty['group'], 'members' => $members, 'total' => $total];
    }

    private function tree(): GroupTree
    {
        if ($this->tree === null) {
            $db         = $this->getDatabase();
            $query      = $db->createQuery()
                ->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'title']))
                ->from($db->quoteName('#__usergroups'))
                ->order($db->quoteName('lft'));
            $this->tree = new GroupTree($db->setQuery($query)->loadObjectList());
        }
        return $this->tree;
    }

    /** @param list<int> $identities */
    private static function rootRules(string $action, array $identities): bool
    {
        return (bool) Access::getAssetRules(1)->allow($action, $identities);
    }
}
