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

    public function testRulesDecoding(): void
    {
        $this->assertSame([6, 2, -8], AccessMatrix::rules('[6,2,-8]'));
        $this->assertSame([], AccessMatrix::rules(''));
        $this->assertSame([], AccessMatrix::rules('not json'));
        $this->assertSame([3], AccessMatrix::rules('["3","x"]'));
    }
}
