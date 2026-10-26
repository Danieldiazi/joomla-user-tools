<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Plans the removal of one account from a group and every group below it (children, grandchildren…).
 * Pure logic: the model loads the data and the applier writes through Joomla's User API.
 *
 * Rules:
 * - The operator never changes its own account.
 * - Non Super Users never change accounts with administrator access (or Super Users).
 * - A Super User may lose that status only if another active Super User remains.
 * - An account left without groups gets the default group for new users; if that group is one of
 *   those being removed, nothing is changed.
 */
final class GroupRemoval
{
    /**
     * @param  array<int, object> $groups      UserDirectory::groups(): id, parent_id, lft, rgt, title, superUser, backend
     * @param  list<int>          $direct      groups the account is assigned to
     * @param  int                $otherSupers active Super Users other than this account
     * @return array{remove: list<int>, keep: list<int>, added: int, result: list<int>, reason: string, losesSuper: bool, losesBackend: bool}
     *         reason is a language key when the change is not allowed, '' otherwise
     */
    public static function plan(
        array $groups,
        array $direct,
        int $groupId,
        int $defaultGroup,
        Operator $operator,
        int $accountId,
        bool $isSuper,
        bool $isBackend,
        int $otherSupers,
    ): array {
        $direct  = array_values(array_unique(array_map('intval', $direct)));
        $subtree = array_fill_keys(self::subtree($groups, $groupId), true);
        $remove  = $keep = [];
        foreach (self::treeOrder($groups, $direct) as $id) {
            if (isset($subtree[$id])) {
                $remove[] = $id;
            } else {
                $keep[] = $id;
            }
        }
        $added  = 0;
        $result = $keep;
        $reason = '';
        if (!$keep && $remove) {
            if (isset($subtree[$defaultGroup]) || !isset($groups[$defaultGroup])) {
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
            $reason = 'COM_USERTOOLS_UNGROUP_REASON_NOT_MEMBER';
        }

        return [
            'remove'       => $remove,
            'keep'         => $keep,
            'added'        => $reason === '' ? $added : 0,
            'result'       => $result,
            'reason'       => $reason,
            'losesSuper'   => $losesSuper,
            'losesBackend' => $losesBackend,
        ];
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
