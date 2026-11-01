<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Pure plan for synchronising the memberships of one branch of groups from a list.
 *
 * Only the managed groups (the chosen group and its subgroups, without groups that give administrator
 * access) are ever added or removed; every other membership is kept. A partial list only touches the
 * accounts it names. A complete list also proposes removing the managed groups of members who are not
 * listed, never blocking them. Nothing is guessed: an unknown or ambiguous account or group, a group
 * outside the branch and a repeated account make the row invalid, and the account is left untouched.
 *
 * Each line is "account;groups;email;name": groups separated by "|", each one by ID or full path;
 * email and name are only used to create a new account (identification by username).
 */
final class GroupSync
{
    public const PARTIAL = 'partial';
    public const FULL = 'full';

    public const BY_ID = 'id';
    public const BY_USERNAME = 'username';

    /** Joomla's default group for new accounts; it is added when an account would be left without groups. */
    public const DEFAULT_GROUP = 2;

    /** Up to this many lines are read; a longer list is refused. */
    public const MAX_LINES = 2000;

    /** Removing every managed group from more than this share of the current members asks for an extra confirmation. */
    public const MASS_SHARE = 0.5;

    public const CHANGE = 'change';
    public const UNCHANGED = 'unchanged';
    public const CREATE = 'create';
    public const LEAVE = 'leave';
    public const INVALID_LINE = 'invalid_line';
    public const UNKNOWN_ACCOUNT = 'unknown_account';
    public const AMBIGUOUS_ACCOUNT = 'ambiguous_account';
    public const DUPLICATE = 'duplicate';
    public const UNKNOWN_GROUP = 'unknown_group';
    public const AMBIGUOUS_GROUP = 'ambiguous_group';
    public const OUTSIDE = 'outside';
    public const PROTECTED_GROUP = 'protected_group';
    public const PROTECTED_ACCOUNT = 'protected_account';
    public const EMAIL_INVALID = 'email_invalid';
    public const EMAIL_IN_USE = 'email_in_use';
    public const CREATE_NOT_ALLOWED = 'create_not_allowed';

    /** Statuses that change something when applied. */
    public const WRITES = [self::CHANGE, self::CREATE, self::LEAVE];

    /** Statuses that mean the row could not be used. Protected accounts are not errors: they are left alone on purpose. */
    public const ERRORS = [self::INVALID_LINE, self::UNKNOWN_ACCOUNT, self::AMBIGUOUS_ACCOUNT, self::DUPLICATE, self::UNKNOWN_GROUP,
        self::AMBIGUOUS_GROUP, self::OUTSIDE, self::PROTECTED_GROUP, self::EMAIL_INVALID, self::EMAIL_IN_USE, self::CREATE_NOT_ALLOWED];

    /**
     * The groups the branch manages, and those inside it that are never managed because they give
     * administrator access. The branch cannot be the root group nor contain the default group.
     *
     * @param  array<int, object> $groups from UserDirectory::groups()
     * @return array{managed: list<int>, protected: list<int>}
     */
    public static function branch(array $groups, int $root): array
    {
        $top = $groups[$root] ?? null;
        if ($top === null || (int) $top->parent_id === 0 || $top->backend || $top->superUser) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_BRANCH');
        }
        $managed = [];
        $protected = [];
        foreach ($groups as $group) {
            if ((int) $group->lft < (int) $top->lft || (int) $group->rgt > (int) $top->rgt) {
                continue;
            }
            if ((int) $group->id === self::DEFAULT_GROUP) {
                throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_BRANCH_DEFAULT');
            }
            if ($group->backend || $group->superUser) {
                $protected[] = (int) $group->id;
            } else {
                $managed[] = (int) $group->id;
            }
        }
        return ['managed' => $managed, 'protected' => $protected];
    }

    /**
     * @param  array<int, object>     $groups
     * @param  array<int, UserRecord> $users
     * @param  array<int, UserRecord> $users every account: their usernames and emails are checked in memory
     * @return array{rows: list<array<string, mixed>>, managed: list<int>, protected: list<int>, members: int}
     */
    public static function plan(array $groups, array $users, int $root, string $mode, string $by, string $text,
        Operator $operator, bool $canCreate): array
    {
        if (!\in_array($mode, [self::PARTIAL, self::FULL], true) || !\in_array($by, [self::BY_ID, self::BY_USERNAME], true)) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_INPUT');
        }
        $branch = self::branch($groups, $root);
        $managed = array_fill_keys($branch['managed'], true);
        $lines = self::lines($text);
        $byName = [];
        $usedEmails = [];
        foreach ($users as $user) {
            $byName[mb_strtolower($user->username)][] = $user->id;
            $usedEmails[mb_strtolower($user->email)] = true;
        }
        $rows = [];
        foreach ($lines as [$number, $cells]) {
            $rows[] = self::row($number, $cells, $groups, $users, $byName, $managed, $by);
        }
        self::markDuplicates($rows);
        $emails = [];
        foreach ($rows as &$row) {
            if ($row['status'] !== '') {
                continue;
            }
            if ($row['id'] > 0) {
                $row = self::existing($row, $users[$row['id']], $managed, $operator);
                continue;
            }
            $email = mb_strtolower($row['email']);
            if (!$canCreate) {
                $row['status'] = self::CREATE_NOT_ALLOWED;
            } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $row['status'] = self::EMAIL_INVALID;
            } elseif (isset($emails[$email]) || isset($usedEmails[$email])) {
                $row['status'] = self::EMAIL_IN_USE;
            } else {
                $emails[$email] = true;
                $row['status'] = self::CREATE;
                $row['add'] = $row['targets'];
            }
        }
        unset($row);
        $listed = [];
        foreach ($rows as $row) {
            if ($row['id'] > 0) {
                $listed[$row['id']] = true;
            }
        }
        $members = 0;
        foreach ($users as $user) {
            $current = array_values(array_filter($user->groups, static fn (int $g): bool => isset($managed[$g])));
            if (!$current) {
                continue;
            }
            $members++;
            if ($mode !== self::FULL || isset($listed[$user->id])) {
                continue;
            }
            $leave = ['line' => 0, 'login' => $user->username, 'id' => $user->id, 'username' => $user->username, 'email' => '', 'name' => '',
                'targets' => [], 'before' => $user->groups, 'add' => [], 'remove' => [], 'kept' => [], 'default' => false, 'emailDiffers' => false, 'status' => ''];
            $leave = self::existing($leave, $user, $managed, $operator);
            $leave['status'] = $leave['status'] === self::CHANGE ? self::LEAVE : $leave['status'];
            $rows[] = $leave;
        }
        return ['rows' => $rows, 'managed' => $branch['managed'], 'protected' => $branch['protected'], 'members' => $members];
    }

    /**
     * Accounts that the plan changes or creates.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function writes(array $rows): int
    {
        return \count(array_filter($rows, static fn (array $row): bool => \in_array($row['status'], self::WRITES, true)));
    }

    /** @param list<array<string, mixed>> $rows */
    public static function errors(array $rows): int
    {
        return \count(array_filter($rows, static fn (array $row): bool => \in_array($row['status'], self::ERRORS, true)));
    }

    /**
     * A complete list with an unusable row could hide someone who would then lose the branch, so it
     * cannot be applied until the list is corrected. A partial list only touches the valid rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function blocked(string $mode, array $rows): bool
    {
        return $mode === self::FULL && self::errors($rows) > 0;
    }

    /**
     * Current members who lose every managed group: leavers of a complete list and listed accounts
     * whose row leaves them without any group of the branch.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function leaving(array $rows): int
    {
        return \count(array_filter($rows, static fn (array $row): bool => \in_array($row['status'], [self::CHANGE, self::LEAVE], true)
            && $row['remove'] && !$row['add'] && !$row['kept']));
    }

    /**
     * Whether the list removes the branch from so many current members that it needs an extra confirmation,
     * in either mode.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function massive(array $rows, int $members): bool
    {
        $leaving = self::leaving($rows);
        return $members > 0 && $leaving > 0 && $leaving > $members * self::MASS_SHARE;
    }

    /**
     * Non-empty lines, without "#" comments, split as CSV with ";" and double quotes.
     *
     * @return list<array{0: int, 1: list<string>}>
     */
    public static function lines(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $all = preg_split('/\R/u', $text);
        if ($all === false) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_INPUT');
        }
        $lines = [];
        foreach ($all as $index => $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (\count($lines) >= self::MAX_LINES) {
                throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_TOO_LONG', [self::MAX_LINES]);
            }
            $lines[] = [$index + 1, array_map(static fn ($cell): string => trim((string) $cell), str_getcsv($line, ';', '"', ''))];
        }
        return $lines;
    }

    /**
     * @param  list<string>                $cells
     * @param  array<int, object>          $groups
     * @param  array<int, UserRecord>      $users
     * @param  array<string, list<int>>    $byName
     * @param  array<int, true>            $managed
     * @return array<string, mixed>
     */
    private static function row(int $number, array $cells, array $groups, array $users, array $byName, array $managed, string $by): array
    {
        $row = ['line' => $number, 'login' => $cells[0] ?? '', 'id' => 0, 'username' => $cells[0] ?? '', 'email' => $cells[2] ?? '',
            'name' => $cells[3] ?? '', 'targets' => [], 'before' => [], 'add' => [], 'remove' => [], 'kept' => [], 'default' => false,
            'emailDiffers' => false, 'status' => ''];
        if ($row['login'] === '' || \count($cells) < 2 || \count($cells) > 4) {
            $row['status'] = self::INVALID_LINE;
            return $row;
        }
        if ($by === self::BY_ID) {
            if (!preg_match('/^[1-9]\d*$/D', $row['login'])) {
                $row['status'] = self::INVALID_LINE;
                return $row;
            }
            $id = (int) $row['login'];
            if (!isset($users[$id])) {
                $row['status'] = self::UNKNOWN_ACCOUNT;
                return $row;
            }
            $row['id'] = $id;
        } else {
            $ids = $byName[mb_strtolower($row['login'])] ?? [];
            if (\count($ids) > 1) {
                $row['status'] = self::AMBIGUOUS_ACCOUNT;
                return $row;
            }
            $row['id'] = $ids ? $ids[0] : 0;
        }
        if ($row['id'] > 0) {
            $row['username'] = $users[$row['id']]->username;
            $row['emailDiffers'] = $row['email'] !== '' && mb_strtolower($row['email']) !== mb_strtolower($users[$row['id']]->email);
        }
        foreach (explode('|', $cells[1]) as $token) {
            if (trim($token) === '') {
                continue;
            }
            $group = BulkGroupAssignments::resolveGroup($groups, $token);
            $status = match (true) {
                $group < 0 => self::AMBIGUOUS_GROUP,
                $group === 0 => self::UNKNOWN_GROUP,
                isset($managed[$group]) => '',
                (bool) $groups[$group]->backend || (bool) $groups[$group]->superUser => self::PROTECTED_GROUP,
                default => self::OUTSIDE,
            };
            if ($status !== '') {
                $row['status'] = $status;
                $row['group'] = trim($token);
                return $row;
            }
            $row['targets'][$group] = $group;
        }
        $row['targets'] = array_values($row['targets']);
        return $row;
    }

    /**
     * An account named twice is contradictory or redundant: all its rows are invalid and it is not touched.
     *
     * @param list<array<string, mixed>> $rows
     */
    private static function markDuplicates(array &$rows): void
    {
        $seen = [];
        foreach ($rows as $index => $row) {
            if (\in_array($row['status'], [self::INVALID_LINE, self::AMBIGUOUS_ACCOUNT], true)) {
                continue;
            }
            $key = $row['id'] > 0 ? '#' . $row['id'] : 'u:' . mb_strtolower($row['login']);
            $seen[$key][] = $index;
        }
        foreach ($seen as $indexes) {
            if (\count($indexes) > 1) {
                foreach ($indexes as $index) {
                    $rows[$index]['status'] = self::DUPLICATE;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed> $row
     * @param  array<int, true>     $managed
     * @return array<string, mixed>
     */
    private static function existing(array $row, UserRecord $user, array $managed, Operator $operator): array
    {
        $row['before'] = $user->groups;
        if ($user->id === $operator->id || $user->isSuperUser || $user->hasBackendAccess) {
            $row['status'] = self::PROTECTED_ACCOUNT;
            return $row;
        }
        $current = array_values(array_filter($user->groups, static fn (int $g): bool => isset($managed[$g])));
        $row['add'] = array_values(array_diff($row['targets'], $current));
        $row['remove'] = array_values(array_diff($current, $row['targets']));
        $row['kept'] = array_values(array_intersect($current, $row['targets']));
        $after = array_diff(array_merge($user->groups, $row['add']), $row['remove']);
        $row['default'] = !$after;
        $row['status'] = $row['add'] || $row['remove'] ? self::CHANGE : self::UNCHANGED;
        return $row;
    }
}
