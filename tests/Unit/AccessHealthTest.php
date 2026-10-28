<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessHealth;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsage;
use Danieldiazi\Component\Usertools\Administrator\Service\DashboardSummary;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use PHPUnit\Framework\TestCase;

final class AccessHealthTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-28 12:00:00', new \DateTimeZone('UTC'));
    }

    /** @return array<int, object> Public > Guest, Registered > Author > Editor, Manager > Administrator, Super Users, and an empty "Club" > "Board". */
    private function groupTree(): array
    {
        $rows = [[1, 0, 'Public', 1, 22, 0], [9, 1, 'Guest', 2, 3, 1], [2, 1, 'Registered', 4, 9, 1], [3, 2, 'Author', 5, 8, 2], [4, 3, 'Editor', 6, 7, 3],
            [6, 1, 'Manager', 10, 13, 1], [7, 6, 'Administrator', 11, 12, 2], [10, 1, 'Club', 14, 17, 1], [11, 10, 'Board', 15, 16, 2], [8, 1, 'Super Users', 18, 19, 1],
            [12, 1, 'Unused', 20, 21, 1]];
        $groups = [];
        foreach ($rows as [$id, $parent, $title, $lft, $rgt, $level]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title, 'lft' => $lft, 'rgt' => $rgt, 'level' => $level,
                'superUser' => $id === 8, 'backend' => in_array($id, [6, 7, 8], true)];
        }
        return $groups;
    }

    /** @return list<array{id: int, title: string, rules: list<int>}> */
    private function levels(): array
    {
        return [
            ['id' => 1, 'title' => 'Public', 'rules' => [1]],
            ['id' => 2, 'title' => 'Registered', 'rules' => [6, 2, 8]],
            ['id' => 3, 'title' => 'Club', 'rules' => [10]],
            ['id' => 4, 'title' => 'Nobody', 'rules' => [999]],
            ['id' => 5, 'title' => 'One account', 'rules' => [-7]],
        ];
    }

    public function testStaleAdministratorsIncludeSuperUsersAndNeverVisited(): void
    {
        $users = [
            1 => Fixtures::user(1, 'recent', [8], true, true, 0, '2026-10-01 00:00:00'),
            2 => Fixtures::user(2, 'oldsuper', [8], true, true, 0, '2025-01-01 00:00:00'),
            3 => Fixtures::user(3, 'oldmanager', [6], false, true, 0, '2026-06-01 00:00:00'),
            4 => Fixtures::user(4, 'blockedmgr', [6], false, true, 1, '2020-01-01 00:00:00'),
            5 => Fixtures::user(5, 'oldauthor', [3], false, false, 0, '2020-01-01 00:00:00'),
            6 => Fixtures::user(6, 'nevermgr', [6], false, true, 0, null, '2026-01-01 00:00:00'),
            7 => Fixtures::user(7, 'newmgr', [6], false, true, 0, null, '2026-10-20 00:00:00'),
        ];
        $rows = AccessHealth::staleAdmins($users, $this->now->sub(new \DateInterval('P90D')));
        $this->assertSame(['oldsuper', 'nevermgr', 'oldmanager'], array_column($rows, 'username'),
            'active backend accounts only, longest without activity first; a recent registration is not reported yet');
        $this->assertTrue($rows[0]['superUser'], 'Super Users are reported: no tool protects them here');
        $this->assertTrue($rows[1]['never']);
        $this->assertNull($rows[1]['lastVisit']);
        $this->assertSame([6], $rows[2]['groups']);
        $this->assertSame(2, AccessHealth::activeSuperUsers($users));
        $this->assertSame(0, AccessHealth::activeSuperUsers([Fixtures::user(1, 'gone', [8], true, true, 1)]), 'blocked Super Users do not count');
    }

    public function testLevelsNobodyHasAndMissingLevels(): void
    {
        $matrix = AccessMatrix::build($this->groupTree(), $this->levels(), [], 9);
        $usage  = AccessUsage::build(array_map(fn ($l) => ['id' => $l['id'], 'title' => $l['title']], $this->levels()), [
            ['key' => 'articles', 'label' => 'Articles', 'table' => '#__content', 'link' => 'index.php?option=com_content&view=articles&filter[access]={access}',
                'counts' => [1 => 10, 4 => 3, 77 => 2]],
            ['key' => 'table:#__gallery', 'label' => 'Table #__gallery', 'table' => '#__gallery', 'link' => '', 'counts' => [77 => 1, 88 => 4]],
        ]);
        $this->assertSame([['id' => 4, 'title' => 'Nobody', 'items' => 3]], AccessHealth::emptyLevels($matrix['levels'], $usage['levels']),
            'a level whose only group does not exist is empty; a level with one account is not; Public never is');
        $missing = AccessHealth::missingLevels($usage);
        $this->assertSame([77, 88], array_column($missing, 'id'));
        $this->assertSame(3, $missing[0]['total']);
        $this->assertSame(['index.php?option=com_content&view=articles&filter[access]=77', ''], array_column($missing[0]['kinds'], 'link'),
            'core lists open filtered by the missing level; other tables have no link');
    }

    public function testGroupsWithoutMembersAnywhereInTheirTree(): void
    {
        $matrix = AccessMatrix::build($this->groupTree(), $this->levels(), [], 9);
        $rows   = AccessHealth::memberlessGroups($this->groupTree(), [2 => 4, 4 => 1, 7 => 1, 8 => 1], $matrix['levels'], 9);
        $this->assertSame(['Club' => 1, 'Board' => 0, 'Unused' => 0], array_column($rows, 'children', 'title'),
            'Author has a member below it; Manager has none directly but Administrator has one; Public and Guest are left out');
        $this->assertSame(['Club'], $rows[0]['levels']);
    }

    public function testBuildCountsAndCsv(): void
    {
        // Manager has a member through Administrator, so only Club, Board and Unused are reported.
        $users  = [1 => Fixtures::user(1, 'onlysuper', [8], true, true, 0, '2026-10-27 00:00:00'), 2 => Fixtures::user(2, 'mgr', [7], false, true, 0, '2024-02-03 04:05:06')];
        $matrix = AccessMatrix::build($this->groupTree(), $this->levels(), [], 9);
        $usage  = AccessUsage::build(array_map(fn ($l) => ['id' => $l['id'], 'title' => $l['title']], $this->levels()),
            [['key' => 'articles', 'label' => 'Articles', 'table' => '#__content', 'link' => '', 'counts' => [77 => 2]]]);
        $health = AccessHealth::build($users, $this->groupTree(), [7 => 1, 8 => 1, 2 => 1, 4 => 1], $matrix['levels'], $usage, 9, $this->now, 90);
        $this->assertSame(array_keys(AccessHealth::CHECKS), array_keys($health['checks']), 'every check is reported, in order, with or without findings');
        $this->assertSame(90, $health['days']);
        $this->assertSame([true, true, true, true, true], array_column($health['checks'], 'found'));
        $this->assertSame(1, $health['checks']['superusers']['count']);
        $this->assertSame(2, $health['checks']['missing_levels']['count'], 'the missing-level figure counts items');
        $this->assertSame(['Club', 'Board', 'Unused'], array_column($health['checks']['empty_groups']['items'], 'title'));
        $this->assertSame(4, AccessHealth::reviewCount($health), 'groups without members are information, not a check to review');

        $csv = AccessHealth::export($health, $this->groupTree(), fn (string $key, string|int $arg = '') => $arg === '' ? $key : "$key($arg)");
        $this->assertSame(['COM_USERTOOLS_DIAGNOSE_CSV_CHECK', 'COM_USERTOOLS_DIAGNOSE_CSV_SEVERITY', 'COM_USERTOOLS_DIAGNOSE_CSV_ID', 'COM_USERTOOLS_DIAGNOSE_CSV_ITEM',
            'COM_USERTOOLS_DIAGNOSE_CSV_DETAIL'], $csv['header']);
        $this->assertContains(['COM_USERTOOLS_DIAGNOSE_ADMINS_TITLE', 'COM_USERTOOLS_DIAGNOSE_REVIEW', 2, 'mgr',
            'COM_USERTOOLS_DIAGNOSE_CSV_LAST_VISIT(2024-02-03 04:05:06) · Administrator'], $csv['rows']);
        $this->assertContains(['COM_USERTOOLS_DIAGNOSE_SUPERUSERS_TITLE', 'COM_USERTOOLS_DIAGNOSE_REVIEW', '', '', 'COM_USERTOOLS_DIAGNOSE_CSV_SUPERUSERS(1)'], $csv['rows']);
        $this->assertContains(['COM_USERTOOLS_DIAGNOSE_MISSING_LEVELS_TITLE', 'COM_USERTOOLS_DIAGNOSE_REVIEW', 77, 'COM_USERTOOLS_USAGE_MISSING_LEVEL(77)', 'Articles: 2'], $csv['rows']);
        $this->assertContains(['COM_USERTOOLS_DIAGNOSE_EMPTY_GROUPS_TITLE', 'COM_USERTOOLS_DIAGNOSE_INFO', 10, 'Club',
            'COM_USERTOOLS_DIAGNOSE_CSV_CHILDREN(1) · COM_USERTOOLS_DIAGNOSE_CSV_LEVELS(Club)'], $csv['rows']);

        $healthy = AccessHealth::build($users + [3 => Fixtures::user(3, 'second', [8], true, true, 0, '2026-10-27 00:00:00')], $this->groupTree(),
            [7 => 1, 8 => 2, 2 => 1, 11 => 1, 12 => 1, 4 => 1], AccessMatrix::build($this->groupTree(), array_slice($this->levels(), 0, 3), [], 9)['levels'],
            AccessUsage::build([], []), 9, $this->now, 1000);
        $this->assertSame([false, false, false, false, false], array_column($healthy['checks'], 'found'), 'nothing to report on a tidy site');
        $this->assertSame(0, AccessHealth::reviewCount($healthy));
        $this->assertSame([], AccessHealth::export($healthy, $this->groupTree(), fn ($k) => $k)['rows']);
    }

    public function testDashboardAlertAndSettings(): void
    {
        $alerts = [['level' => 'danger', 'key' => 'X', 'args' => [], 'link' => ''], ['level' => 'info', 'key' => 'Y', 'args' => [], 'link' => '']];
        $this->assertSame($alerts, DashboardSummary::withDiagnosis($alerts, 0));
        $with = DashboardSummary::withDiagnosis($alerts, 3);
        $this->assertSame(['X', 'MOD_USERTOOLS_ALERT_DIAGNOSIS', 'Y'], array_column($with, 'key'), 'a warning between danger and information');
        $this->assertSame('index.php?option=com_usertools&view=diagnose', $with[1]['link']);
        $this->assertSame('MOD_USERTOOLS_ALERT_DIAGNOSIS_1', DashboardSummary::withDiagnosis([], 1)[0]['key']);
        $this->assertSame(90, (new Settings([]))->diagnoseDays());
        $this->assertSame(30, (new Settings(['diagnose_admin_days' => '30']))->diagnoseDays());
        $this->assertSame(90, (new Settings(['diagnose_admin_days' => 0]))->diagnoseDays());
    }
}
