<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use PHPUnit\Framework\TestCase;

final class GroupReachTest extends TestCase
{
    /**
     * Joomla's default tree plus "Socios" under Registered and "Junta" under Socios, as a nested set.
     *
     * @return array<int, object>
     */
    private function groupTree(): array
    {
        // id, parent, title, depth, lft, rgt
        $rows = [
            [1, 0, 'Public', 0, 1, 22], [9, 1, 'Guest', 1, 2, 3], [6, 1, 'Manager', 1, 4, 7], [7, 6, 'Administrator', 2, 5, 6],
            [2, 1, 'Registered', 1, 8, 17], [3, 2, 'Author', 2, 9, 12], [4, 3, 'Editor', 3, 10, 11], [10, 2, 'Socios', 2, 13, 16],
            [11, 10, 'Junta', 3, 14, 15], [8, 1, 'Super Users', 1, 18, 19],
        ];
        $groups = [];
        foreach ($rows as [$id, $parent, $title, $depth, $lft, $rgt]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title, 'level' => $depth, 'lft' => $lft, 'rgt' => $rgt,
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
            ['id' => 3, 'title' => 'Special', 'rules' => [6, 3, 8]],
            ['id' => 5, 'title' => 'Guest', 'rules' => [9]],
            ['id' => 7, 'title' => 'Socios', 'rules' => [10]],
            ['id' => 8, 'title' => 'Junta', 'rules' => [11]],
            ['id' => 9, 'title' => 'Nadie', 'rules' => []],
        ];
    }

    /**
     * @param  array<int, int> $members
     * @param  list<array{access: int, category: int, total: int}> $articles
     * @return array<int, array<string, mixed>> rows by group id
     */
    private function reach(array $members, array $articles, int $few = GroupReach::DEFAULT_FEW): array
    {
        $groups = $this->groupTree();
        $matrix = AccessMatrix::build($groups, $this->levels(), [2 => 40, 10 => 2, 11 => 1], 9);
        return array_column(GroupReach::build($matrix, $groups, $members, $articles, $few), null, 'id');
    }

    /** Articles grouped by their level and their category's level. */
    private function articles(): array
    {
        return [
            ['access' => 1, 'category' => 1, 'total' => 10],  // public
            ['access' => 2, 'category' => 1, 'total' => 4],   // registered, in a public category
            ['access' => 1, 'category' => 2, 'total' => 3],   // public, but in a registered category
            ['access' => 7, 'category' => 1, 'total' => 5],   // Socios
            ['access' => 8, 'category' => 7, 'total' => 2],   // Junta, in a Socios category
            ['access' => 9, 'category' => 1, 'total' => 6],   // nobody's level
            ['access' => 5, 'category' => 1, 'total' => 1],   // guests only
        ];
    }

    public function testArticlesNeedTheArticleAndCategoryLevels(): void
    {
        $rows = $this->reach([], $this->articles());
        $this->assertSame(10, $rows[1]['articles'], 'Public views only public articles in public categories');
        $this->assertSame(11, $rows[9]['articles'], 'Guest views public ones and its own level');
        $this->assertSame(17, $rows[2]['articles'], 'Registered also views articles in a registered category');
        $this->assertSame(22, $rows[10]['articles'], 'Socios adds its own level to what it inherits');
        $this->assertSame(24, $rows[11]['articles'], 'Junta needs its level and the Socios level of the category, which it inherits');
        $this->assertSame(17, $rows[7]['articles'], 'Administrator inherits Registered from Manager');
        $this->assertSame(31, $rows[8]['articles'], 'Super Users view every article, even those of a level nobody has');
        $this->assertSame([1, 2, 7], $rows[10]['levels']);
    }

    public function testArticlesOnlyABranchCanView(): void
    {
        $rows = $this->reach([], $this->articles());
        $this->assertSame(5, $rows[10]['exclusive'], 'the Socios branch alone views its 5 articles; those of Junta need a level Socios lacks');
        $this->assertSame(2, $rows[11]['exclusive'], 'Junta alone views its own 2');
        $this->assertSame(0, $rows[2]['exclusive'], 'Manager also views what Registered views');
        $this->assertSame(1, $rows[9]['exclusive'], 'only guests view the guest article');
        $this->assertSame(6, $rows[8]['exclusive'], 'for Super Users: articles no other group can view');
        $this->assertSame($rows[1]['articles'], $rows[1]['exclusive'], 'every group hangs from Public');
    }

    public function testMembersAndFewFlag(): void
    {
        $rows = $this->reach([1 => 50, 2 => 45, 10 => 3, 11 => 1], [], 3);
        $this->assertSame(40, $rows[2]['direct'], 'direct members come from the matrix');
        $this->assertSame(45, $rows[2]['members'], 'members count the branch');
        $this->assertTrue($rows[11]['few'], '1 < 3');
        $this->assertFalse($rows[10]['few'], '3 is not fewer than 3');
        $this->assertTrue($rows[3]['empty'] && !$rows[3]['few'], 'a group with no account is empty, not "few"');
        $this->assertSame(0, $rows[3]['articles'], 'no articles at all');
    }

    public function testThresholdPageAndExport(): void
    {
        $this->assertSame(GroupReach::DEFAULT_FEW, GroupReach::threshold('x'));
        $this->assertSame(1, GroupReach::threshold('-4'));
        $this->assertSame(GroupReach::MAX_FEW, GroupReach::threshold('999999'));
        $this->assertSame(12, GroupReach::threshold('12'));
        $this->assertSame(1, GroupReach::page(3, 0));
        $this->assertSame(2, GroupReach::page(9, GroupReach::PAGE_SIZE + 1));

        $groups = $this->groupTree();
        $matrix = AccessMatrix::build($groups, $this->levels(), [], 9);
        $rows   = GroupReach::build($matrix, $groups, [10 => 3], $this->articles());
        $table  = GroupReach::export($rows, AccessMatrix::paths($matrix['rows']), array_column($matrix['levels'], 'title', 'id'), fn (string $k) => $k);
        $this->assertCount(8, $table['header']);
        $socios = current(array_filter($table['rows'], fn (array $r) => $r[0] === 10));
        $this->assertSame([10, 'Socios', 'Public / Registered / Socios', 0, 3, 22, 5, 'Public, Registered, Socios'], $socios);
        $super = current(array_filter($table['rows'], fn (array $r) => $r[0] === 8));
        $this->assertSame('COM_USERTOOLS_REACH_ALL_LEVELS', $super[7]);
    }
}
