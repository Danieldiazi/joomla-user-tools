<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountExport;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use PHPUnit\Framework\TestCase;

final class AccountExportTest extends TestCase
{
    /** @return array<int, object> */
    private function sampleGroups(): array
    {
        return [
            1 => (object) ['id' => 1, 'title' => 'Public', 'lft' => 1, 'rgt' => 12],
            2 => (object) ['id' => 2, 'title' => 'Registered', 'lft' => 2, 'rgt' => 9],
            3 => (object) ['id' => 3, 'title' => 'Members', 'lft' => 3, 'rgt' => 4],
            4 => (object) ['id' => 4, 'title' => 'Outside', 'lft' => 10, 'rgt' => 11],
        ];
    }

    private function record(int $id, string $login, int $block, ?string $last, string $registered, array $groups): UserRecord
    {
        return new UserRecord($id, $login, $login, $login . '@example.test', $block, $last,
            $registered, $groups, $groups, false, false);
    }

    /** @return array<int, UserRecord> */
    private function sampleUsers(): array
    {
        return [
            1 => $this->record(1, '=HYPERLINK(1)', 0, '2025-01-01 00:00:00', '2024-01-01 00:00:00', [2]),
            2 => $this->record(2, 'blocked', 1, null, '2022-01-01 00:00:00', [3]),
            3 => $this->record(3, 'recent', 0, '2026-10-20 00:00:00', '2024-01-01 00:00:00', [4]),
            4 => $this->record(4, 'newNever', 0, null, '2026-10-22 00:00:00', [3]),
        ];
    }

    public function testGroupsDirectDescendantsAndStatus(): void
    {
        $now = new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('UTC'));
        $all = $this->sampleUsers();
        $g = $this->sampleGroups();
        $this->assertSame([1], array_column(AccountExport::select($all, $g, 2, false, 0, false, 'all', $now), 'id'));
        $this->assertSame([1, 2, 4], array_column(AccountExport::select($all, $g, 2, true, 0, false, 'all', $now), 'id'));
        $this->assertSame([1, 4], array_column(AccountExport::select($all, $g, 2, true, 0, false, 'active', $now), 'id'));
        $this->assertSame([2], array_column(AccountExport::select($all, $g, 2, true, 0, false, 'blocked', $now), 'id'));
    }

    public function testInactivityUsesSameNeverVisitedRules(): void
    {
        $now = new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('UTC'));
        $u = $this->sampleUsers();
        $g = $this->sampleGroups();
        $this->assertSame([1], array_column(AccountExport::select($u, $g, 0, false, 365, false, 'all', $now), 'id'));
        $this->assertSame([1, 2], array_column(AccountExport::select($u, $g, 0, false, 365, true, 'all', $now), 'id'));
    }

    public function testCsvEmailDefaultMfaAndFormulaProtection(): void
    {
        $u = $this->sampleUsers();
        $g = $this->sampleGroups();
        $text = static fn (string $k): string => $k;
        $without = CsvExport::build(AccountExport::header(false, $text),
            AccountExport::rows(array_values($u), $g, [2 => true], [1 => true], false, $text));
        $with = CsvExport::build(AccountExport::header(true, $text),
            AccountExport::rows(array_values($u), $g, [2 => true], [1 => true], true, $text));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $without);
        $this->assertStringContainsString("'=HYPERLINK(1)", $without);
        $this->assertStringNotContainsString('example.test', $without, 'email is opt-in');
        $this->assertStringContainsString('=HYPERLINK(1)@example.test', $with);
        $this->assertStringContainsString('Registered (#2)', $with);
        $this->assertStringContainsString('COM_USERTOOLS_EXPORT_PENDING_ACTIVATION', $with);
        $this->assertStringContainsString('JYES', $with);
        $this->assertSame(9, count(AccountExport::header(false, $text)));
        $this->assertSame(10, count(AccountExport::header(true, $text)));
    }

    public function testUnknownGroupIsNotSilentlyConvertedToAll(): void
    {
        $this->expectException(UserFacingException::class);
        AccountExport::select($this->sampleUsers(), $this->sampleGroups(), 999, false, 0, false, 'all',
            new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('UTC')));
    }

    public function testLargeExportsAreStableAndUnique(): void
    {
        $users = [];
        for ($i = 1805; $i > 0; $i--) {
            $users[$i] = $this->record($i, 'test' . $i, 0, null, '2020-01-01 00:00:00', [2]);
        }
        $out = AccountExport::select($users, $this->sampleGroups(), 0, false, 0, false, 'all',
            new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('UTC')));
        $this->assertCount(1805, $out);
        $this->assertSame(1, $out[0]->id);
        $this->assertSame(1805, $out[1804]->id);
        $this->assertCount(1805, iterator_to_array(AccountExport::rows($out, $this->sampleGroups(), [], [], false, static fn (string $k) => $k)));
    }
}
