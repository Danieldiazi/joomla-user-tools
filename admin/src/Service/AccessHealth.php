<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Access diagnosis: situations worth reviewing in accounts, groups and access levels. Each check
 * only reports; nothing is changed. Pure logic: the data comes from UserDirectory, AccessMatrix and
 * AccessUsage.
 */
final class AccessHealth
{
    /** Something that should be reviewed. */
    public const REVIEW = 'review';

    /** Useful to know; usually not a problem. */
    public const INFO = 'info';

    /** Days without signing in after which an account with administrator access is reported. */
    public const DEFAULT_ADMIN_DAYS = 90;

    /** Checks in display order: key => severity. */
    public const CHECKS = [
        'admins'         => self::REVIEW,
        'superusers'     => self::REVIEW,
        'empty_levels'   => self::REVIEW,
        'missing_levels' => self::REVIEW,
        'empty_groups'   => self::INFO,
    ];

    /**
     * @param  array<int, UserRecord>                    $users        every account
     * @param  array<int, object>                        $groups       UserDirectory::groups(), in tree order
     * @param  array<int, int>                           $members      accounts (blocked too) per group, direct
     * @param  list<array<string, mixed>>                $matrixLevels AccessMatrix::build()['levels']
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $usage AccessUsage::build()
     * @return array{days: int, checks: array<string, array{severity: string, found: bool, items: list<array<string, mixed>>, count: int}>}
     */
    public static function build(array $users, array $groups, array $members, array $matrixLevels, array $usage, int $guestGroup, \DateTimeImmutable $now, int $days): array
    {
        $days    = max(InactivityPolicy::MIN_DAYS, min(InactivityPolicy::MAX_DAYS, $days));
        $admins  = self::staleAdmins($users, InactivityPolicy::cutoff($days, $now));
        $supers  = self::activeSuperUsers($users);
        $empty   = self::emptyLevels($matrixLevels, $usage['levels']);
        $missing = self::missingLevels($usage);
        $unused  = self::memberlessGroups($groups, $members, $matrixLevels, $guestGroup);
        $check   = fn (string $key, array $items, bool $found, int $count) => ['severity' => self::CHECKS[$key], 'found' => $found, 'items' => $items, 'count' => $count];
        return ['days' => $days, 'checks' => [
            'admins'         => $check('admins', $admins, (bool) $admins, \count($admins)),
            'superusers'     => $check('superusers', [], $supers <= 1, $supers),
            'empty_levels'   => $check('empty_levels', $empty, (bool) $empty, \count($empty)),
            'missing_levels' => $check('missing_levels', $missing, (bool) $missing, array_sum(array_column($missing, 'total'))),
            'empty_groups'   => $check('empty_groups', $unused, (bool) $unused, \count($unused)),
        ]];
    }

    /**
     * Checks to review that found something (the figure shown on the dashboard).
     *
     * @param array{checks: array<string, array{severity: string, found: bool}>} $health
     */
    public static function reviewCount(array $health): int
    {
        return \count(array_filter($health['checks'], fn (array $check) => $check['found'] && $check['severity'] === self::REVIEW));
    }

    /**
     * Accounts that are not blocked, have administrator access and have not signed in since the cutoff
     * (or never did and were created before it). Super Users included: no tool protects them here.
     *
     * @param  array<int, UserRecord> $users
     * @return list<array{id: int, username: string, name: string, superUser: bool, never: bool, lastVisit: ?string, registered: ?string, groups: list<int>}>
     */
    public static function staleAdmins(array $users, \DateTimeImmutable $cutoff): array
    {
        $rows = [];
        foreach ($users as $user) {
            if (!$user->hasBackendAccess || !InactivityPolicy::eligible($user, $cutoff, true)) {
                continue;
            }
            $never  = $user->neverVisited();
            $rows[] = [
                'id'         => $user->id,
                'username'   => $user->username,
                'name'       => $user->name,
                'superUser'  => $user->isSuperUser,
                'never'      => $never,
                'lastVisit'  => $never ? null : $user->lastvisitDate,
                'registered' => $user->registerDate,
                'groups'     => $user->groups,
            ];
        }
        // Longest without activity first.
        usort($rows, fn (array $a, array $b) => [$a['lastVisit'] ?? $a['registered'], $a['id']] <=> [$b['lastVisit'] ?? $b['registered'], $b['id']]);
        return $rows;
    }

    /** @param array<int, UserRecord> $users */
    public static function activeSuperUsers(array $users): int
    {
        return \count(array_filter($users, fn (UserRecord $user) => $user->block === 0 && $user->isSuperUser));
    }

    /**
     * Levels (other than Public) with no group and no account assigned, with how many items use them.
     *
     * @param  list<array<string, mixed>> $matrixLevels AccessMatrix::build()['levels']
     * @param  list<array<string, mixed>> $usageLevels  AccessUsage::build()['levels']
     * @return list<array{id: int, title: string, items: int}>
     */
    public static function emptyLevels(array $matrixLevels, array $usageLevels): array
    {
        $items = array_column($usageLevels, 'total', 'id');
        $rows  = [];
        foreach ($matrixLevels as $level) {
            if (!empty($level['empty'])) {
                $rows[] = ['id' => (int) $level['id'], 'title' => (string) $level['title'], 'items' => (int) ($items[$level['id']] ?? 0)];
            }
        }
        return $rows;
    }

    /**
     * Items whose access level does not exist, per level and kind of item.
     *
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $usage AccessUsage::build()
     * @return list<array{id: int, total: int, kinds: list<array{key: string, label: string, count: int, link: string}>}>
     */
    public static function missingLevels(array $usage): array
    {
        $rows = [];
        foreach ($usage['levels'] as $level) {
            if (empty($level['missing'])) {
                continue;
            }
            $kinds = [];
            foreach ($usage['rows'] as $row) {
                $count = (int) ($row['counts'][$level['id']] ?? 0);
                if ($count > 0) {
                    $kinds[] = ['key' => (string) $row['key'], 'label' => (string) $row['label'], 'count' => $count,
                        'link' => AccessUsage::link((string) $row['link'], (int) $level['id'])];
                }
            }
            $rows[] = ['id' => (int) $level['id'], 'total' => (int) $level['total'], 'kinds' => $kinds];
        }
        return $rows;
    }

    /**
     * Groups with no account in them or in any group below them. The root (Public) and the guest group
     * are left out: they never have members by design.
     *
     * @param  array<int, object>         $groups       UserDirectory::groups(), in tree order
     * @param  array<int, int>            $members      accounts per group, direct
     * @param  list<array<string, mixed>> $matrixLevels AccessMatrix::build()['levels']
     * @return list<array{id: int, title: string, level: int, children: int, levels: list<string>}>
     */
    public static function memberlessGroups(array $groups, array $members, array $matrixLevels, int $guestGroup): array
    {
        $levelsOf = [];
        foreach ($matrixLevels as $level) {
            foreach ($level['groups'] ?? [] as $groupId) {
                $levelsOf[(int) $groupId][] = (string) $level['title'];
            }
        }
        $rows = [];
        foreach ($groups as $group) {
            if ((int) $group->parent_id === 0 || (int) $group->id === $guestGroup) {
                continue;
            }
            $inTree   = 0;
            $children = 0;
            foreach ($groups as $other) {
                if ($other->lft >= $group->lft && $other->rgt <= $group->rgt) {
                    $inTree += (int) ($members[(int) $other->id] ?? 0);
                    $children += (int) ($other->id !== $group->id);
                }
            }
            if ($inTree === 0) {
                $rows[] = ['id' => (int) $group->id, 'title' => (string) $group->title, 'level' => (int) $group->level,
                    'children' => $children, 'levels' => $levelsOf[(int) $group->id] ?? []];
            }
        }
        return $rows;
    }

    /**
     * Every finding as table rows for a CSV file. Texts come through $text so this stays free of Joomla.
     *
     * @param  array{days: int, checks: array<string, array<string, mixed>>} $health see build()
     * @param  array<int, object>                                            $groups group titles for the admin rows
     * @param  callable(string, string|int=): string                         $text   translates a language key with an optional argument
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public static function export(array $health, array $groups, callable $text): array
    {
        $header = [$text('COM_USERTOOLS_DIAGNOSE_CSV_CHECK'), $text('COM_USERTOOLS_DIAGNOSE_CSV_SEVERITY'), $text('COM_USERTOOLS_DIAGNOSE_CSV_ID'),
            $text('COM_USERTOOLS_DIAGNOSE_CSV_ITEM'), $text('COM_USERTOOLS_DIAGNOSE_CSV_DETAIL')];
        $rows   = [];
        foreach ($health['checks'] as $key => $check) {
            if (!$check['found']) {
                continue;
            }
            $name     = $text('COM_USERTOOLS_DIAGNOSE_' . strtoupper($key) . '_TITLE');
            $severity = $text($check['severity'] === self::REVIEW ? 'COM_USERTOOLS_DIAGNOSE_REVIEW' : 'COM_USERTOOLS_DIAGNOSE_INFO');
            foreach ($check['items'] as $item) {
                $rows[] = match ($key) {
                    'admins' => [$name, $severity, $item['id'], $item['username'],
                        ($item['never'] ? $text('COM_USERTOOLS_DIAGNOSE_CSV_NEVER', (string) $item['registered']) : $text('COM_USERTOOLS_DIAGNOSE_CSV_LAST_VISIT', (string) $item['lastVisit']))
                        . ' · ' . implode(', ', array_map(fn (int $id) => (string) ($groups[$id]->title ?? '#' . $id), $item['groups']))],
                    'empty_levels' => [$name, $severity, $item['id'], $item['title'], $text('COM_USERTOOLS_DIAGNOSE_CSV_ITEMS', $item['items'])],
                    'missing_levels' => [$name, $severity, $item['id'], $text('COM_USERTOOLS_USAGE_MISSING_LEVEL', (string) $item['id']),
                        implode(', ', array_map(fn (array $kind) => $kind['label'] . ': ' . $kind['count'], $item['kinds']))],
                    default => [$name, $severity, $item['id'], $item['title'],
                        $text($item['children'] ? 'COM_USERTOOLS_DIAGNOSE_CSV_CHILDREN' : 'COM_USERTOOLS_DIAGNOSE_CSV_NO_CHILDREN', $item['children'])
                        . ($item['levels'] ? ' · ' . $text('COM_USERTOOLS_DIAGNOSE_CSV_LEVELS', implode(', ', $item['levels'])) : '')],
                };
            }
            if ($key === 'superusers') {
                $rows[] = [$name, $severity, '', '', $text('COM_USERTOOLS_DIAGNOSE_CSV_SUPERUSERS', $check['count'])];
            }
        }
        return ['header' => $header, 'rows' => $rows];
    }
}
