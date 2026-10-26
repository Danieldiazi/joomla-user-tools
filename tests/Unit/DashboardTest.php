<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\DashboardSummary;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\ReportStore;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-26 12:00:00', new \DateTimeZone('UTC'));
    }

    public function testCountsFollowTheToolRules(): void
    {
        $old  = '2024-01-01 00:00:00';
        $users = [
            Fixtures::user(1, 'root', [8], super: true, backend: true, lastVisit: $old),
            Fixtures::user(2, 'ana', lastVisit: $old),
            Fixtures::user(3, 'servicio', lastVisit: $old),
            Fixtures::user(4, 'reciente', lastVisit: '2026-10-01 00:00:00'),
            Fixtures::user(5, 'bloqueada', block: 1, lastVisit: $old),
            Fixtures::user(6, 'bloqueada2', block: 1, lastVisit: $old),
            Fixtures::user(7, 'conectada', lastVisit: $old),
            Fixtures::user(8, 'nunca', lastVisit: null, registered: '2020-01-01 00:00:00'),
        ];
        $settings = new Settings(['inactive_days' => 365, 'inactive_excluded_names' => 'servicio', 'delete_days' => 365, 'delete_excluded_names' => 'bloqueada2']);
        $counts = DashboardSummary::counts($users, $settings, new Operator(99, true), fn (array $ids) => in_array(7, $ids, true) ? [7 => true] : [], $this->now);
        $this->assertSame(['inactive' => 1, 'pendingDeletion' => 1], $counts, 'ana is inactive; bloqueada is pending deletion');

        $withNever = new Settings(['inactive_days' => 365, 'inactive_include_never' => 1, 'delete_days' => 365]);
        $counts = DashboardSummary::counts($users, $withNever, new Operator(99, true), fn () => [], $this->now);
        $this->assertSame(['inactive' => 4, 'pendingDeletion' => 2], $counts, 'never-visited accounts follow the tool option; Super Users never count');
    }

    public function testTaskStatusesAndAlerts(): void
    {
        $rows = [
            ['id' => 1, 'title' => 'Noche', 'state' => 1, 'last_execution' => '2026-10-26 03:00:00', 'next_execution' => '2026-10-27 03:00:00', 'last_exit_code' => 5, 'params' => '{"dry_run":0}', 'created' => '2026-01-01 00:00:00'],
            ['id' => 2, 'title' => 'Prueba', 'state' => 1, 'last_execution' => null, 'next_execution' => null, 'last_exit_code' => 0, 'params' => '{"dry_run":1}', 'created' => '2026-09-01 00:00:00'],
            ['id' => 3, 'title' => 'Vieja', 'state' => 0, 'last_execution' => '2026-01-01 00:00:00', 'next_execution' => null, 'last_exit_code' => 5, 'params' => '{}', 'created' => '2025-01-01 00:00:00'],
            ['id' => 4, 'title' => 'Colgada', 'state' => 1, 'last_execution' => '2026-10-26 09:00:00', 'next_execution' => null, 'last_exit_code' => 0, 'params' => '{"dry_run":0}', 'created' => '2026-10-20 00:00:00'],
        ];
        $reports = [
            1 => ['id' => '20261026030000-' . str_repeat('a', 24), 'status' => 'guarded', 'started_at' => '2026-10-26 03:00:00'],
            4 => ['id' => '20261026090000-' . str_repeat('b', 24), 'status' => 'running', 'started_at' => '2026-10-26 09:00:00'],
        ];
        $tasks = DashboardSummary::tasks($rows, $reports);
        $this->assertSame(['guarded', 'never', 'failed', 'running'], array_column($tasks, 'status'));
        $this->assertSame([false, true, true, false], array_column($tasks, 'dryRun'));
        $this->assertNull($tasks[1]['lastRun']);

        $alerts = DashboardSummary::alerts('enabled', $tasks, $this->now);
        $this->assertSame([
            ['danger', 'MOD_USERTOOLS_ALERT_GUARDED', ['Noche']],
            ['warning', 'MOD_USERTOOLS_ALERT_UNFINISHED', ['Colgada']],
            ['info', 'MOD_USERTOOLS_ALERT_DRY_RUN', ['Prueba', 14]],
        ], array_map(fn ($a) => [$a['level'], $a['key'], $a['args']], $alerts), 'disabled tasks do not alert; danger comes first');
        $this->assertStringContainsString('view=report&report_id=20261026030000', $alerts[0]['link']);

        $none = DashboardSummary::alerts('disabled', [], $this->now);
        $this->assertSame(['MOD_USERTOOLS_ALERT_PLUGIN_DISABLED'], array_column($none, 'key'));
        $this->assertSame(['MOD_USERTOOLS_ALERT_NO_TASKS'], array_column(DashboardSummary::alerts('enabled', [], $this->now), 'key'));
        $recent = DashboardSummary::tasks([['id' => 9, 'title' => 'Nueva', 'state' => 1, 'params' => '{"dry_run":1}', 'created' => '2026-10-20 00:00:00']], []);
        $this->assertSame([], DashboardSummary::alerts('enabled', $recent, $this->now), 'a recent dry run is not an alert');
    }

    public function testLatestReportPerTask(): void
    {
        $dir   = sys_get_temp_dir() . '/ut-latest-' . bin2hex(random_bytes(4));
        $store = new ReportStore($dir);
        $store->save('20261001000000-' . str_repeat('a', 24), ['task_id' => 1, 'status' => 'completed', 'users' => [['id' => 1]]]);
        $store->save('20261002000000-' . str_repeat('b', 24), ['task_id' => 1, 'status' => 'guarded']);
        $store->save('20261003000000-' . str_repeat('c', 24), ['task_id' => 2, 'status' => 'completed']);
        $latest = $store->latestByTask([1, 2, 3]);
        $this->assertSame(['guarded', 'completed'], [$latest[1]['status'], $latest[2]['status']]);
        $this->assertArrayNotHasKey(3, $latest);
        $this->assertArrayNotHasKey('users', $latest[1]);
        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }
}
