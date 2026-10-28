<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use PHPUnit\Framework\TestCase;

final class AccessMatrixTest extends TestCase
{
    /** @return array<int, object> Joomla's default tree plus "Socios" under Registered and "Junta" under Socios. */
    private function groupTree(): array
    {
        $rows = [
            [1, 0, 'Public', 0], [9, 1, 'Guest', 1], [6, 1, 'Manager', 1], [7, 6, 'Administrator', 2], [2, 1, 'Registered', 1],
            [3, 2, 'Author', 2], [4, 3, 'Editor', 3], [10, 2, 'Socios', 2], [11, 10, 'Junta', 3], [8, 1, 'Super Users', 1],
        ];
        $groups = [];
        foreach ($rows as [$id, $parent, $title, $level]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title, 'level' => $level, 'superUser' => $id === 8, 'backend' => in_array($id, [6, 7, 8], true)];
        }
        return $groups;
    }

    /** @return list<array{id: int, title: string, rules: list<int>}> Joomla's default levels plus two. */
    private function levels(): array
    {
        return [
            ['id' => 1, 'title' => 'Public', 'rules' => [1]],
            ['id' => 2, 'title' => 'Registered', 'rules' => [6, 2, 8]],
            ['id' => 3, 'title' => 'Special', 'rules' => [6, 3, 8]],
            ['id' => 5, 'title' => 'Guest', 'rules' => [9]],
            ['id' => 6, 'title' => 'Super Users', 'rules' => [8]],
            ['id' => 7, 'title' => 'Socios', 'rules' => [10, -42, -43, 999]],
            ['id' => 8, 'title' => 'Vacío', 'rules' => []],
        ];
    }

    /** @param array{rows: list<array<string, mixed>>} $matrix */
    private function cell(array $matrix, int $group, int $level): string
    {
        foreach ($matrix['rows'] as $row) {
            if ($row['id'] === $group) {
                $cell = $row['cells'][$level];
                return $cell['state'] . ($cell['via'] ? ':' . $cell['via'] : '');
            }
        }
        $this->fail("group $group missing");
    }

    public function testDirectAndInheritedLevels(): void
    {
        $m = AccessMatrix::build($this->groupTree(), $this->levels(), [2 => 5, 11 => 1], 9);
        $this->assertSame('direct', $this->cell($m, 2, 2));
        $this->assertSame('inherited:2', $this->cell($m, 4, 2), 'Editor inherits Registered through Registered');
        $this->assertSame('inherited:3', $this->cell($m, 4, 3), 'the nearest assigned parent is reported');
        $this->assertSame('direct', $this->cell($m, 3, 3));
        $this->assertSame('inherited:6', $this->cell($m, 7, 2), 'Administrator inherits from Manager');
        $this->assertSame('none', $this->cell($m, 9, 2), 'Guest is not Registered');
        $this->assertSame('inherited:10', $this->cell($m, 11, 7));
        $this->assertSame('none', $this->cell($m, 3, 7), 'siblings do not inherit');
        $this->assertSame('none', $this->cell($m, 2, 6));
        $this->assertSame('inherited:1', $this->cell($m, 11, 1), 'every group sits under Public');
    }

    public function testPublicIsAlwaysGranted(): void
    {
        $levels = $this->levels();
        $levels[0]['rules'] = [];
        $m = AccessMatrix::build($this->groupTree(), $levels);
        $this->assertSame('public', $this->cell($m, 4, 1), 'Joomla grants level 1 to every account');
        $this->assertFalse($m['levels'][0]['empty'], 'Public is never reported as empty');
    }

    public function testLevelSummaryAndRowFlags(): void
    {
        $m      = AccessMatrix::build($this->groupTree(), $this->levels(), [2 => 5, 11 => 1], 9);
        $levels = array_column($m['levels'], null, 'id');
        $this->assertSame([6, 2, 8], $levels[2]['groups'], 'assigned groups in tree order; unknown group ids ignored');
        $this->assertSame([10], $levels[7]['groups']);
        $this->assertSame(2, $levels[7]['users'], 'negative ids are individual accounts');
        $this->assertSame(2, $levels[7]['reach'], 'Socios and Junta');
        $this->assertSame(8, $levels[2]['reach']);
        $this->assertTrue($levels[8]['empty']);
        $this->assertFalse($levels[7]['empty']);
        $this->assertSame([1, 9, 6, 7, 2, 3, 4, 10, 11, 8], array_column($m['rows'], 'id'), 'rows keep the tree order');
        $rows = array_column($m['rows'], null, 'id');
        $this->assertTrue($rows[9]['guest']);
        $this->assertTrue($rows[8]['superUser']);
        $this->assertSame(5, $rows[2]['members']);
        $this->assertSame(0, $rows[3]['members']);
    }

    public function testExportAsTable(): void
    {
        $m     = AccessMatrix::build($this->groupTree(), $this->levels(), [2 => 5], 9);
        $text  = fn (string $key, string $group = '') => $group === '' ? $key : "$key($group)";
        $table = AccessMatrix::export($m, AccessMatrix::EXPORT_MATRIX, $text);
        $this->assertSame(['COM_USERTOOLS_ACCESS_CSV_GROUP_ID', 'COM_USERTOOLS_ACCESS_GROUP', 'COM_USERTOOLS_ACCESS_CSV_PATH', 'COM_USERTOOLS_ACCESS_MEMBERS',
            'Public', 'Registered', 'Special', 'Guest', 'Super Users', 'Socios', 'Vacío'], $table['header'], 'one column per level, in display order');
        $this->assertCount(10, $table['rows'], 'one row per group');
        $editor = array_column($table['rows'], null, 0)[4];
        $this->assertSame([4, 'Editor', 'Public / Registered / Author / Editor', 0,
            'COM_USERTOOLS_ACCESS_CSV_INHERITED_VIA(Public)', 'COM_USERTOOLS_ACCESS_CSV_INHERITED_VIA(Registered)', 'COM_USERTOOLS_ACCESS_CSV_INHERITED_VIA(Author)', '', '', '', ''], $editor);
        $this->assertSame([2, 'Registered', 'Public / Registered', 5, 'COM_USERTOOLS_ACCESS_CSV_INHERITED_VIA(Public)', 'COM_USERTOOLS_ACCESS_DIRECT', '', '', '', '', ''],
            array_column($table['rows'], null, 0)[2]);

        $levels = $this->levels();
        $levels[0]['rules'] = [];
        $always = AccessMatrix::export(AccessMatrix::build($this->groupTree(), $levels), AccessMatrix::EXPORT_MATRIX, $text);
        $this->assertSame('COM_USERTOOLS_ACCESS_ALWAYS', $always['rows'][0][4]);
    }

    public function testExportOneRowPerLevel(): void
    {
        $m     = AccessMatrix::build($this->groupTree(), $this->levels(), [], 9);
        $pairs = AccessMatrix::export($m, AccessMatrix::EXPORT_PAIRS, fn (string $key) => $key);
        $this->assertSame(['COM_USERTOOLS_ACCESS_CSV_GROUP_ID', 'COM_USERTOOLS_ACCESS_GROUP', 'COM_USERTOOLS_ACCESS_CSV_PATH', 'COM_USERTOOLS_ACCESS_CSV_LEVEL_ID',
            'COM_USERTOOLS_ACCESS_LEVEL', 'COM_USERTOOLS_ACCESS_CSV_STATE', 'COM_USERTOOLS_ACCESS_CSV_VIA'], $pairs['header']);
        $junta = array_values(array_filter($pairs['rows'], fn ($r) => $r[0] === 11));
        $this->assertSame([
            [11, 'Junta', 'Public / Registered / Socios / Junta', 1, 'Public', 'COM_USERTOOLS_ACCESS_INHERITED', 'Public'],
            [11, 'Junta', 'Public / Registered / Socios / Junta', 2, 'Registered', 'COM_USERTOOLS_ACCESS_INHERITED', 'Registered'],
            [11, 'Junta', 'Public / Registered / Socios / Junta', 7, 'Socios', 'COM_USERTOOLS_ACCESS_INHERITED', 'Socios'],
        ], $junta, 'only the levels the group has, with the parent it comes from');
        $this->assertSame([], array_filter($pairs['rows'], fn ($r) => $r[3] === 8), 'a level nobody has gives no rows');
    }

    public function testPathsAndIndividualAccounts(): void
    {
        $rows = [['id' => 1, 'title' => 'Public', 'level' => 0], ['id' => 2, 'title' => 'A', 'level' => 1], ['id' => 3, 'title' => 'B', 'level' => 2],
            ['id' => 4, 'title' => 'C', 'level' => 1]];
        $this->assertSame([1 => 'Public', 2 => 'Public / A', 3 => 'Public / A / B', 4 => 'Public / C'], AccessMatrix::paths($rows), 'a sibling replaces the previous branch');
        $this->assertTrue(AccessMatrix::hasUserRules(AccessMatrix::build($this->groupTree(), $this->levels())['levels']));
        $levels = $this->levels();
        $levels[5]['rules'] = [10];
        $this->assertFalse(AccessMatrix::hasUserRules(AccessMatrix::build($this->groupTree(), $levels)['levels']), 'no level assigned to single accounts');
    }

    public function testRulesDecoding(): void
    {
        $this->assertSame([6, 2, -8], AccessMatrix::rules('[6,2,-8]'));
        $this->assertSame([], AccessMatrix::rules(''));
        $this->assertSame([], AccessMatrix::rules('not json'));
        $this->assertSame([3], AccessMatrix::rules('["3","x"]'));
    }
}
