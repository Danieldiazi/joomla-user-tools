<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * A pure, deterministic plan for additive membership changes. An unknown or ambiguous group
 * (including duplicate sibling paths) is never silently resolved.
 */
final class BulkGroupAssignments
{
    public const ADD = 'add';
    public const EXISTING = 'existing';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const PROTECTED = 'protected';
    public const INVALID_GROUP = 'invalid_group';
    public const DUPLICATE = 'duplicate';
    public const INVALID_LINE = 'invalid_line';

    /** @param array<int, object> $groups */
    public static function resolveGroup(array $groups, string $token): int
    {
        $token = trim($token);
        if (preg_match('/^#?([1-9]\d*)$/D', $token, $m)) {
            $id = (int) $m[1];
            return isset($groups[$id]) ? $id : 0;
        }
        $segments = array_map('trim', explode('/', $token));
        if (count($segments) < 2 || in_array('', $segments, true)) {
            return 0;
        }
        $parents = [0];
        foreach ($segments as $segment) {
            $next = [];
            foreach ($groups as $group) {
                if (in_array((int) $group->parent_id, $parents, true) && mb_strtolower((string) $group->title) === mb_strtolower($segment)) {
                    $next[] = (int) $group->id;
                }
            }
            if (!$next) {
                return 0;
            }
            $parents = $next;
        }
        // -1 means an ambiguous full path; it is not an alternative ID.
        return count($parents) === 1 ? $parents[0] : -1;
    }

    /**
     * @param array<int, object> $groups
     * @param array<int, UserRecord> $users
     * @return list<array{login: string, id: int, username: string, group: int, before: list<int>, status: string}>
     */
    public static function plan(array $groups, array $users, string $mode, string $lines, string $oneGroup, Operator $operator): array
    {
        $lookups = [];
        foreach ($users as $user) {
            foreach ([$user->username, $user->email] as $value) {
                $lookups[mb_strtolower($value)][] = $user;
            }
        }
        $rows = [];
        $seen = [];
        $fixed = $mode === 'single' ? self::resolveGroup($groups, $oneGroup) : 0;
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = $mode === 'single' ? [$line, $oneGroup] : array_map('trim', explode(';', $line));
            $login = trim($parts[0] ?? '');
            $group = $mode === 'single' ? $fixed : (count($parts) === 2 ? self::resolveGroup($groups, $parts[1]) : 0);
            $status = '';
            $matches = array_unique(array_map(static fn (UserRecord $u): int => $u->id, $lookups[mb_strtolower($login)] ?? []));
            $user = count($matches) === 1 ? $users[(int) reset($matches)] : null;
            if (!$login || ($mode !== 'single' && count($parts) !== 2)) {
                $status = self::INVALID_LINE;
            } elseif ($group < 0 || count($matches) > 1) {
                $status = self::AMBIGUOUS;
            } elseif (!$group || !isset($groups[$group])) {
                $status = self::INVALID_GROUP;
            } elseif (!$user) {
                $status = self::UNKNOWN;
            } elseif ((int) $user->id === $operator->id || $user->isSuperUser || $user->hasBackendAccess
                || (bool) $groups[$group]->superUser || (bool) $groups[$group]->backend || (int) $groups[$group]->parent_id === 0) {
                $status = self::PROTECTED;
            } else {
                $key = $user->id . ':' . $group;
                if (isset($seen[$key])) {
                    $status = self::DUPLICATE;
                } elseif (in_array($group, $user->groups, true)) {
                    $status = self::EXISTING;
                } else {
                    $status = self::ADD;
                }
                $seen[$key] = true;
            }
            $rows[] = ['login' => $login, 'id' => $user === null ? 0 : $user->id, 'username' => $user === null ? $login : $user->username,
                'group' => $group, 'before' => $user === null ? [] : $user->groups, 'status' => $status];
        }
        return $rows;
    }
}
