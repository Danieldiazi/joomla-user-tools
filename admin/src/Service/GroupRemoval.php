<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Plans the removal of one account from the groups the operator unticked. For each unticked group the
 * operator can also choose "and its subgroups": the account then leaves every group below that one too
 * (children, grandchildren…).
 * Pure logic: the model loads the data and the applier writes through Joomla's User API.
 *
 * Rules:
 * - The operator never changes its own account.
 * - Non Super Users never change accounts with administrator access (or Super Users).
 * - A Super User may lose that status only if another active Super User remains.
 * - An account left without groups gets the default group for new users; if that group is one of
 *   those being removed or lies below one of them (it would inherit its permissions), nothing is changed.
 */
final class GroupRemoval
{
    /**
     * @param  array<int, object> $groups      UserDirectory::groups(): id, parent_id, lft, rgt, title, superUser, backend
     * @param  list<int>          $direct      groups the account is assigned to
     * @param  list<int>          $selected    groups chosen for removal (ids the account is not in are ignored)
     * @param  list<int>          $recursive   chosen groups whose subgroups go too (others are ignored)
     * @param  int                $otherSupers active Super Users other than this account
     * @return array{remove: list<int>, keep: list<int>, added: int, result: list<int>, reason: string, losesSuper: bool, losesBackend: bool, cascaded: array<int, int>}
     *         reason is a language key when the change is not allowed, '' otherwise; cascaded maps a group
     *         removed only because of "and its subgroups" to the chosen group above it
     */
    public static function plan(
        array $groups,
        array $direct,
        array $selected,
        array $recursive,
        int $defaultGroup,
        Operator $operator,
        int $accountId,
        bool $isSuper,
        bool $isBackend,
        int $otherSupers,
    ): array {
        $direct   = array_values(array_unique(array_map('intval', $direct)));
        $chosen   = array_fill_keys(array_intersect(array_map('intval', $selected), $direct), true);
        $cascaded = [];
        // In tree order, so a group below two chosen ones is attributed to the higher one.
        foreach (self::treeOrder($groups, array_values(array_unique(array_map('intval', $recursive)))) as $root) {
            if (isset($chosen[$root])) {
                foreach (self::subtree($groups, $root) as $below) {
                    if ($below !== $root && !isset($chosen[$below]) && \in_array($below, $direct, true) && !isset($cascaded[$below])) {
                        $cascaded[$below] = $root;
                    }
                }
            }
        }
        $remove = $keep = [];
        foreach (self::treeOrder($groups, $direct) as $id) {
            if (isset($chosen[$id]) || isset($cascaded[$id])) {
                $remove[] = $id;
            } else {
                $keep[] = $id;
            }
        }
        // A group below a removed one inherits its permissions, so the default group cannot be one of
        // those either: adding it would give back what is being taken away (recursive or not).
        $removedTrees = [];
        foreach ($remove as $id) {
            foreach (self::subtree($groups, $id) as $below) {
                $removedTrees[$below] = true;
            }
        }
        $added  = 0;
        $result = $keep;
        $reason = '';
        if (!$keep && $remove) {
            if (isset($removedTrees[$defaultGroup]) || !isset($groups[$defaultGroup])) {
                $reason = 'COM_USERTOOLS_UNGROUP_REASON_DEFAULT_REMOVED';
            } else {
                $added  = $defaultGroup;
                $result = [$defaultGroup];
            }
        }
        $staysSuper   = self::grants($groups, $result, 'superUser');
        $staysBackend = $staysSuper || self::grants($groups, $result, 'backend');
        $losesSuper   = $isSuper && !$staysSuper;
        $losesBackend = $isBackend && !$staysBackend;

        if ($accountId === $operator->id && $operator->id > 0) {
            $reason = 'COM_USERTOOLS_UNGROUP_REASON_SELF';
        } elseif (($isBackend || $isSuper) && !$operator->isSuperUser) {
            $reason = 'COM_USERTOOLS_UNGROUP_REASON_BACKEND';
        } elseif ($losesSuper && $otherSupers < 1) {
            $reason = 'COM_USERTOOLS_UNGROUP_REASON_LAST_SUPER';
        } elseif (!$remove) {
            $reason = 'COM_USERTOOLS_UNGROUP_REASON_NOTHING';
        }

        return [
            'remove'       => $remove,
            'keep'         => $keep,
            'added'        => $reason === '' ? $added : 0,
            'result'       => $result,
            'reason'       => $reason,
            'losesSuper'   => $losesSuper,
            'losesBackend' => $losesBackend,
            'cascaded'     => $cascaded,
        ];
    }

    /**
     * Rows of the selection tree: the account's groups plus the groups between them that the account is
     * not in, so every row hangs from its real parent. Ancestors shared by all the account's groups (the
     * common trunk, such as Public) are left out unless the account is in them.
     *
     * @param  array<int, object> $groups  UserDirectory::groups(), in tree order
     * @param  list<int>          $members the account's groups
     * @return list<array{id: int, member: bool, depth: int}> in tree order; depth counts the shown ancestors
     */
    public static function treeRows(array $groups, array $members): array
    {
        $members   = array_values(array_filter(array_unique(array_map('intval', $members)), fn (int $id): bool => isset($groups[$id])));
        $ancestors = [];
        foreach ($members as $id) {
            $ancestors[$id] = self::ancestors($groups, $id);
        }
        if (!$ancestors) {
            return [];
        }
        $common = \count($ancestors) === 1 ? reset($ancestors) : array_intersect(...array_values($ancestors));
        $shown  = array_fill_keys($members, true);
        foreach ($ancestors as $list) {
            foreach (array_diff($list, $common) as $id) {
                $shown[$id] = true;
            }
        }
        $rows = [];
        foreach ($groups as $group) {
            $id = (int) $group->id;
            if (isset($shown[$id])) {
                $depth = 0;
                foreach (self::ancestors($groups, $id) as $ancestor) {
                    $depth += isset($shown[$ancestor]) ? 1 : 0;
                }
                $rows[] = ['id' => $id, 'member' => \in_array($id, $members, true), 'depth' => $depth];
            }
        }
        return $rows;
    }

    /**
     * Ancestors of each group (nearest last), for the selection screen.
     *
     * @param  array<int, object> $groups
     * @return list<int>
     */
    public static function ancestors(array $groups, int $groupId): array
    {
        if (!isset($groups[$groupId])) {
            return [];
        }
        $target = $groups[$groupId];
        $result = [];
        foreach ($groups as $group) {
            if ((int) $group->id !== $groupId && (int) $group->lft < (int) $target->lft && (int) $group->rgt > (int) $target->rgt) {
                $result[] = (int) $group->id;
            }
        }
        return $result;
    }

    /**
     * The group and every group below it, in tree order.
     *
     * @param  array<int, object> $groups
     * @return list<int>
     */
    public static function subtree(array $groups, int $groupId): array
    {
        if (!isset($groups[$groupId])) {
            return [];
        }
        $root   = $groups[$groupId];
        $result = [];
        foreach ($groups as $group) {
            if ((int) $group->lft >= (int) $root->lft && (int) $group->rgt <= (int) $root->rgt) {
                $result[] = (int) $group->id;
            }
        }
        return $result;
    }

    /**
     * @param  array<int, object> $groups
     * @param  list<int>          $ids
     * @return list<int> the ids in tree order; unknown ids at the end
     */
    private static function treeOrder(array $groups, array $ids): array
    {
        $wanted = array_fill_keys($ids, true);
        $result = [];
        foreach ($groups as $group) {
            if (isset($wanted[(int) $group->id])) {
                $result[] = (int) $group->id;
                unset($wanted[(int) $group->id]);
            }
        }
        return array_merge($result, array_keys($wanted));
    }

    /**
     * @param array<int, object> $groups
     * @param list<int>          $ids
     */
    private static function grants(array $groups, array $ids, string $flag): bool
    {
        foreach ($ids as $id) {
            if (!empty($groups[$id]->{$flag})) {
                return true;
            }
        }
        return false;
    }
}
