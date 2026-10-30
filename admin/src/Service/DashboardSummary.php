<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Figures and alerts of the administrator dashboard module. The counts use exactly the same rules
 * as the tools (period, exclusions, protected accounts and recent sessions), so a number on the
 * dashboard matches what the tool lists when it is opened.
 */
final class DashboardSummary
{
    /** A task left in dry run for longer than this is reported. */
    public const DRY_RUN_WARNING_DAYS = 14;

    /** A report that has not finished after this many hours is reported as unfinished. */
    public const UNFINISHED_HOURS = 1;

    /**
     * @param  iterable<UserRecord>                        $users
     * @param  callable(list<int>): array<int, true>       $recentSessions accounts with a recent session
     * @return array{inactive: int, pendingDeletion: int}
     */
    public static function counts(iterable $users, Settings $settings, Operator $operator, callable $recentSessions, \DateTimeImmutable $now): array
    {
        $inactiveCutoff = InactivityPolicy::cutoff($settings->inactiveDays(), $now);
        $deleteCutoff   = InactivityPolicy::cutoff($settings->deleteDays(), $now);
        $inactiveRules  = $settings->exclusions(Settings::INACTIVE);
        $deleteRules    = $settings->exclusions(Settings::DELETE);
        $inactive       = [];
        $pending        = [];
        foreach ($users as $user) {
            if (InactivityPolicy::eligible($user, $inactiveCutoff, $settings->inactiveIncludeNever())
                && !$inactiveRules->protects($user, $operator)) {
                $inactive[] = $user->id;
            }
            // Pending deletion: blocked accounts that the deletion tool would offer with "Only blocked".
            if ($user->block === 1 && InactivityPolicy::eligible($user, $deleteCutoff, $settings->deleteIncludeNever(), true)
                && !$deleteRules->protects($user, $operator)) {
                $pending[] = $user->id;
            }
        }
        $active = $recentSessions(array_values(array_unique(array_merge($inactive, $pending))));
        return [
            'inactive'        => \count(array_diff($inactive, array_keys($active))),
            'pendingDeletion' => \count(array_diff($pending, array_keys($active))),
        ];
    }

    /**
     * Scheduled tasks of User Tools with the outcome of their latest report.
     *
     * @param  list<array<string, mixed>>          $rows          #__scheduler_tasks rows
     * @param  array<int, array<string, mixed>>    $latestReports latest report per task ID
     * @return list<array{id: int, title: string, enabled: bool, dryRun: bool, lastRun: ?string, nextRun: ?string, created: ?string, status: string, reportId: string, reportStarted: ?string}>
     */
    public static function tasks(array $rows, array $latestReports): array
    {
        $tasks = [];
        foreach ($rows as $row) {
            $id     = (int) $row['id'];
            $params = \is_array($row['params'] ?? null) ? $row['params'] : (json_decode((string) ($row['params'] ?? ''), true) ?: []);
            $report = $latestReports[$id] ?? null;
            $status = $report ? (string) ($report['status'] ?? 'unreadable') : (empty($row['last_execution']) ? 'never' : 'unknown');
            if ($status === 'unknown' && (int) ($row['last_exit_code'] ?? 0) !== 0) {
                $status = 'failed';
            }
            $tasks[] = [
                'id'            => $id,
                'title'         => (string) ($row['title'] ?? ''),
                'enabled'       => (int) ($row['state'] ?? 0) === 1,
                'dryRun'        => (int) ($params['dry_run'] ?? 1) !== 0,
                'lastRun'       => self::date($row['last_execution'] ?? null),
                'nextRun'       => self::date($row['next_execution'] ?? null),
                'created'       => self::date($row['created'] ?? null),
                'status'        => $status,
                'reportId'      => $report ? (string) ($report['id'] ?? '') : '',
                'reportStarted' => $report ? self::date($report['started_at'] ?? null) : null,
            ];
        }
        return $tasks;
    }

    /**
     * Alerts in order of importance: danger first.
     *
     * @param  list<array<string, mixed>> $tasks from tasks()
     * @return list<array{level: string, key: string, args: list<string|int>, link: string}>
     */
    public static function alerts(string $pluginState, array $tasks, \DateTimeImmutable $now): array
    {
        $alerts = [];
        $schedule = 'index.php?option=com_usertools&view=schedule';
        if ($pluginState !== 'enabled') {
            $alerts[] = ['level' => 'warning', 'key' => $pluginState === 'missing' ? 'MOD_USERTOOLS_ALERT_PLUGIN_MISSING' : 'MOD_USERTOOLS_ALERT_PLUGIN_DISABLED', 'args' => [], 'link' => $schedule];
        }
        $enabled = 0;
        foreach ($tasks as $task) {
            if (!$task['enabled']) {
                continue;
            }
            $enabled++;
            $report = $task['reportId'] !== '' ? 'index.php?option=com_usertools&view=report&report_id=' . $task['reportId'] : 'index.php?option=com_scheduler&task=task.edit&id=' . $task['id'];
            if ($task['status'] === 'guarded') {
                $alerts[] = ['level' => 'danger', 'key' => 'MOD_USERTOOLS_ALERT_GUARDED', 'args' => [$task['title']], 'link' => $report];
            } elseif ($task['status'] === 'failed') {
                $alerts[] = ['level' => 'danger', 'key' => 'MOD_USERTOOLS_ALERT_FAILED', 'args' => [$task['title']], 'link' => $report];
            } elseif ($task['status'] === 'running' && $task['reportStarted'] !== null
                && self::olderThan($task['reportStarted'], $now, 'PT' . self::UNFINISHED_HOURS . 'H')) {
                $alerts[] = ['level' => 'warning', 'key' => 'MOD_USERTOOLS_ALERT_UNFINISHED', 'args' => [$task['title']], 'link' => $report];
            }
            if ($task['dryRun'] && $task['created'] !== null && self::olderThan($task['created'], $now, 'P' . self::DRY_RUN_WARNING_DAYS . 'D')) {
                $alerts[] = ['level' => 'info', 'key' => 'MOD_USERTOOLS_ALERT_DRY_RUN', 'args' => [$task['title'], self::DRY_RUN_WARNING_DAYS],
                    'link' => 'index.php?option=com_scheduler&task=task.edit&id=' . $task['id']];
            }
        }
        if ($pluginState === 'enabled' && $enabled === 0) {
            $alerts[] = ['level' => 'info', 'key' => 'MOD_USERTOOLS_ALERT_NO_TASKS', 'args' => [], 'link' => $schedule];
        }
        $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        return $alerts;
    }

    /**
     * Adds the access diagnosis to the alerts when some check needs reviewing, keeping the order.
     *
     * @param  list<array{level: string, key: string, args: list<string|int>, link: string}> $alerts
     * @return list<array{level: string, key: string, args: list<string|int>, link: string}>
     */
    public static function withDiagnosis(array $alerts, int $count): array
    {
        if ($count <= 0) {
            return $alerts;
        }
        $alerts[] = ['level' => 'warning', 'key' => $count === 1 ? 'MOD_USERTOOLS_ALERT_DIAGNOSIS_1' : 'MOD_USERTOOLS_ALERT_DIAGNOSIS', 'args' => [$count],
            'link' => 'index.php?option=com_usertools&view=diagnose'];
        $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        return $alerts;
    }

    private static function date(mixed $value): ?string
    {
        $value = (string) ($value ?? '');
        return $value === '' || str_starts_with($value, '0000-00-00') ? null : substr($value, 0, 19);
    }

    private static function olderThan(string $date, \DateTimeImmutable $now, string $interval): bool
    {
        $when = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date, new \DateTimeZone('UTC'));
        return $when !== false && $when < $now->setTimezone(new \DateTimeZone('UTC'))->sub(new \DateInterval($interval));
    }
}
