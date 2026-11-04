<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Pure, read-only selection and review fingerprint for accounts that require a password reset.
 * The reset flag itself is checked by the database query and again on the fresh Joomla User.
 */
final class SsoConversionPlan
{
    public const MAX_ACCOUNTS = 5000;

    /**
     * @param array<string, mixed> $input
     * @param array<int, object> $groups
     * @return array{group: int, subgroups: bool, status: string, visits: string}
     */
    public static function filters(array $input, array $groups): array
    {
        $group = (int) ($input['group'] ?? 0);
        $status = (string) ($input['status'] ?? 'all');
        $visits = (string) ($input['visits'] ?? 'all');
        if ($group < 0 || ($group > 0 && !isset($groups[$group]))
            || !\in_array($status, ['active', 'blocked', 'all'], true)
            || !\in_array($visits, ['yes', 'no', 'all'], true)) {
            throw new UserFacingException('COM_USERTOOLS_SSO_INVALID_FILTER');
        }
        return ['group' => $group, 'subgroups' => (bool) ($input['subgroups'] ?? false),
            'status' => $status, 'visits' => $visits];
    }

    /** @param array<int, object> $groups
     * @return list<int> */
    public static function groupIds(array $groups, int $id, bool $subgroups): array
    {
        if ($id === 0) {
            return [];
        }
        if (!isset($groups[$id])) {
            throw new UserFacingException('COM_USERTOOLS_SSO_INVALID_FILTER');
        }
        if (!$subgroups) {
            return [$id];
        }
        $root = $groups[$id];
        $ids = [];
        foreach ($groups as $group) {
            if ($group->lft >= $root->lft && $group->rgt <= $root->rgt) {
                $ids[] = (int) $group->id;
            }
        }
        return $ids;
    }

    /**
     * @param array<int, UserRecord> $users Only accounts with requireReset=1.
     * @param array<int, object> $groups
     * @param array{group: int, subgroups: bool, status: string, visits: string} $filters
     * @return list<array{id: int, username: string, name: string, email: string, block: int, lastvisitDate: ?string, registerDate: ?string, groups: list<int>, fingerprint: string}>
     */
    public static function candidates(array $users, array $groups, array $filters, Operator $operator): array
    {
        $allowedGroups = self::groupIds($groups, $filters['group'], $filters['subgroups']);
        $membership = array_fill_keys($allowedGroups, true);
        $rows = [];
        foreach ($users as $user) {
            if ($user->id === $operator->id || $user->isSuperUser || (!$operator->isSuperUser && $user->hasBackendAccess)) {
                continue;
            }
            if ($filters['status'] === 'active' && $user->block !== 0
                || $filters['status'] === 'blocked' && $user->block !== 1
                || $filters['visits'] === 'yes' && $user->neverVisited()
                || $filters['visits'] === 'no' && !$user->neverVisited()) {
                continue;
            }
            if ($membership && !array_intersect_key(array_fill_keys($user->groups, true), $membership)) {
                continue;
            }
            $rows[] = [
                'id' => $user->id, 'username' => $user->username, 'name' => $user->name,
                'email' => $user->email, 'block' => $user->block,
                'lastvisitDate' => $user->lastvisitDate, 'registerDate' => $user->registerDate,
                'groups' => $user->groups, 'fingerprint' => AccountProfile::fingerprint($user),
            ];
        }
        return $rows;
    }

    /** @param array<string, mixed> $row */
    public static function stillEligible(object $user, array $row, Operator $operator): bool
    {
        if (empty($user->id) || (int) $user->id !== (int) $row['id']
            || (int) ($user->requireReset ?? 0) !== 1 || (int) $user->id === $operator->id
            || $user->authorise('core.admin')
            || (!$operator->isSuperUser && $user->authorise('core.login.admin'))) {
            return false;
        }
        // The snapshot includes identity, groups, block state and login/registration dates.
        // A changed account is skipped, never replaced by one matching the old filter.
        return hash_equals((string) $row['fingerprint'], AccountProfile::fingerprint($user));
    }
}
