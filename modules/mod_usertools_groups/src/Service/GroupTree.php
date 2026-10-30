<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Module\UsertoolsGroups\Site\Service;

\defined('_JEXEC') or die;

/**
 * The user group tree, built from #__usergroups rows (nested set). Pure logic, no database access,
 * so it can be unit tested without Joomla.
 */
final class GroupTree
{
    /** @var array<int, array{id: int, parent_id: int, lft: int, rgt: int, title: string, level: int}> */
    private array $groups = [];

    /** @param iterable<object|array<string, mixed>> $rows id, parent_id, lft, rgt, title */
    public function __construct(iterable $rows)
    {
        $list = [];
        foreach ($rows as $row) {
            $row    = (array) $row;
            $list[] = [
                'id'        => (int) $row['id'],
                'parent_id' => (int) $row['parent_id'],
                'lft'       => (int) $row['lft'],
                'rgt'       => (int) $row['rgt'],
                'title'     => (string) $row['title'],
                'level'     => 0,
            ];
        }
        usort($list, fn (array $a, array $b): int => $a['lft'] <=> $b['lft']);
        $stack = [];
        foreach ($list as $group) {
            while ($stack && $stack[\count($stack) - 1] < $group['lft']) {
                array_pop($stack);
            }
            $group['level']               = \count($stack);
            $stack[]                      = $group['rgt'];
            $this->groups[$group['id']]   = $group;
        }
    }

    public function has(int $id): bool
    {
        return isset($this->groups[$id]);
    }

    public function title(int $id): string
    {
        return $this->groups[$id]['title'] ?? '';
    }

    /** @return list<int> the group and all its ancestors, root first */
    public function ancestors(int $id): array
    {
        if (!isset($this->groups[$id])) {
            return [];
        }
        $target = $this->groups[$id];
        $result = [];
        foreach ($this->groups as $group) {
            if ($group['lft'] <= $target['lft'] && $group['rgt'] >= $target['rgt']) {
                $result[] = $group['id'];
            }
        }
        return $result;
    }

    /** @return list<int> the group and, when asked, every group below it, in tree order */
    public function descendants(int $id, bool $recursive = true): array
    {
        if (!isset($this->groups[$id])) {
            return [];
        }
        if (!$recursive) {
            return [$id];
        }
        $target = $this->groups[$id];
        $result = [];
        foreach ($this->groups as $group) {
            if ($group['lft'] >= $target['lft'] && $group['rgt'] <= $target['rgt']) {
                $result[] = $group['id'];
            }
        }
        return $result;
    }

    /**
     * Groups to show for an account, in tree order.
     *
     * @param  list<int> $direct    groups the account is assigned to
     * @param  bool      $inherited also show the groups it inherits from (its parents)
     * @param  list<int> $hidden    groups never shown
     * @return list<array{id: int, title: string, level: int, direct: bool}>
     */
    public function forUser(array $direct, bool $inherited, array $hidden = []): array
    {
        $direct = array_flip(array_map('intval', $direct));
        $shown  = [];
        foreach (array_keys($direct) as $id) {
            foreach ($inherited ? $this->ancestors($id) : ($this->has($id) ? [$id] : []) as $groupId) {
                $shown[$groupId] = true;
            }
        }
        $hidden = array_flip(array_map('intval', $hidden));
        $result = [];
        foreach ($this->groups as $group) {
            if (isset($shown[$group['id']]) && !isset($hidden[$group['id']])) {
                $result[] = ['id' => $group['id'], 'title' => $group['title'], 'level' => $group['level'], 'direct' => isset($direct[$group['id']])];
            }
        }
        return $result;
    }

    /**
     * Groups whose members can log in to the administrator (or are Super Users), evaluated on the
     * root asset with the group and its ancestors as identities, as Joomla's Access::check() does.
     *
     * @param  callable(string, list<int>): bool $allow
     * @return list<int>
     */
    public function backendGroups(callable $allow): array
    {
        $result = [];
        foreach ($this->groups as $group) {
            $identities = $this->ancestors($group['id']);
            if ($allow('core.admin', $identities) || $allow('core.login.admin', $identities)) {
                $result[] = $group['id'];
            }
        }
        return $result;
    }

    /** How a member is shown: real name, username, or both. */
    public static function label(string $name, string $username, string $display): string
    {
        return match ($display) {
            'username' => $username,
            'both'     => $name !== '' && $name !== $username ? $name . ' (' . $username . ')' : $username,
            default    => $name !== '' ? $name : $username,
        };
    }
}
