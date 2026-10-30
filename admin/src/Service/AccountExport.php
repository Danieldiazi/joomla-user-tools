<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Filters preloaded account snapshots and produces CSV data without database access. */
final class AccountExport
{
    /**
     * @param array<int, UserRecord> $users
     * @param array<int, object> $groups  UserDirectory::groups(), with lft/rgt
     * @return list<UserRecord>
     */
    public static function select(array $users, array $groups, int $group, bool $subgroups, int $days, bool $includeNever, string $status, \DateTimeImmutable $now): array
    {
        if ($group !== 0 && !isset($groups[$group])) {
            throw new UserFacingException('COM_USERTOOLS_EXPORT_BAD_GROUP');
        }
        if (!\in_array($status, ['all', 'active', 'blocked'], true)) {
            throw new UserFacingException('COM_USERTOOLS_EXPORT_BAD_STATUS');
        }
        $cutoff = $days > 0 ? InactivityPolicy::cutoff($days, $now) : null;
        $allowed = [];
        if ($group > 0) {
            $allowed[$group] = true;
            if ($subgroups) {
                $root = $groups[$group];
                foreach ($groups as $child) {
                    if ((int) $child->lft >= (int) $root->lft && (int) $child->rgt <= (int) $root->rgt) {
                        $allowed[(int) $child->id] = true;
                    }
                }
            }
        }
        $selected = [];
        foreach ($users as $user) {
            if ($status !== 'all' && !InactivityPolicy::matchesStatus($user, $status)) {
                continue;
            }
            if ($group && !array_intersect_key(array_fill_keys($user->groups, true), $allowed)) {
                continue;
            }
            if ($cutoff !== null && !InactivityPolicy::eligible($user, $cutoff, $includeNever, true)) {
                continue;
            }
            $selected[] = $user;
        }
        usort($selected, static fn (UserRecord $a, UserRecord $b): int => $a->id <=> $b->id);
        return $selected;
    }

    /**
     * @param list<UserRecord> $users
     * @param array<int, object> $groups
     * @param array<int, bool> $pending  IDs with non-empty activation token and blocked account
     * @param array<int, bool> $mfa      IDs with at least one configured non-recovery method
     * @param callable(string): string $text
     * @return iterable<list<int|string>>
     */
    public static function rows(array $users, array $groups, array $pending, array $mfa, bool $email, callable $text): iterable
    {
        foreach ($users as $user) {
            $names = [];
            foreach ($user->groups as $id) {
                $title = isset($groups[$id]) ? (string) $groups[$id]->title : $text('COM_USERTOOLS_EXPORT_MISSING_GROUP');
                $names[] = $title . ' (#' . $id . ')';
            }
            $row = [$user->id, $user->username, $user->name];
            if ($email) {
                $row[] = $user->email;
            }
            array_push($row, implode(' | ', $names), (string) $user->lastvisitDate, (string) $user->registerDate,
                $text($user->block ? 'COM_USERTOOLS_EXPORT_BLOCKED' : 'COM_USERTOOLS_EXPORT_ACTIVE'),
                $text(isset($pending[$user->id]) && $user->block ? 'JYES' : 'JNO'),
                $text(isset($mfa[$user->id]) ? 'JYES' : 'JNO'));
            yield $row;
        }
    }

    /** @return list<string> */
    public static function header(bool $email, callable $text): array
    {
        $head = [
            $text('COM_USERTOOLS_ID'), $text('COM_USERTOOLS_USERNAME'), $text('COM_USERTOOLS_NAME'),
        ];
        if ($email) {
            $head[] = $text('COM_USERTOOLS_EMAIL');
        }
        return array_merge($head, [
            $text('COM_USERTOOLS_EXPORT_GROUPS'), $text('COM_USERTOOLS_LAST_VISIT_UTC'),
            $text('COM_USERTOOLS_EXPORT_REGISTERED'), $text('COM_USERTOOLS_EXPORT_STATE'),
            $text('COM_USERTOOLS_EXPORT_PENDING_ACTIVATION'), $text('COM_USERTOOLS_EXPORT_MFA'),
        ]);
    }
}
