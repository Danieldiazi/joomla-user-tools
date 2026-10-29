<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupMove;
use PHPUnit\Framework\TestCase;

final class GroupMoveTest extends TestCase
{
    /**
     * Public > (Guest, Manager > Administrator, Registered > (Author > Editor > Publisher, Socios > Junta > Presidencia), Super Users).
     * Manager allows administrator login; Super Users has core.admin.
     *
     * @return array<int, object>
     */
    private function tree(): array
    {
        $rows = [
            [1, 0, 1, 24, 'Public'], [9, 1, 2, 3, 'Guest'], [6, 1, 4, 7, 'Manager'], [7, 6, 5, 6, 'Administrator'],
            [2, 1, 8, 21, 'Registered'], [3, 2, 9, 14, 'Author'], [4, 3, 10, 13, 'Editor'], [5, 4, 11, 12, 'Publisher'],
            [10, 2, 15, 20, 'Socios'], [11, 10, 16, 19, 'Junta'], [12, 11, 17, 18, 'Presidencia'], [8, 1, 22, 23, 'Super Users'],
        ];
        $groups = [];
        foreach ($rows as [$id, $parent, $lft, $rgt, $title]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'lft' => $lft, 'rgt' => $rgt, 'title' => $title,
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
            ['id' => 7, 'title' => 'Socios', 'rules' => [10]],
        ];
    }

    /**
     * @param list<int> $selected
     * @param array<int, array{username: string, groups: list<int>}> $accounts
     */
    private function plan(array $selected, int $target, ?array $groups = null, array $accounts = [], ?callable $allow = null): array
    {
        $allow ??= fn (string $action, array $path): bool => $action === 'core.admin' ? in_array(8, $path, true)
            : (in_array(6, $path, true) || in_array(8, $path, true));
        return array_column(GroupMove::plan($groups ?? $this->tree(), $this->levels(), $selected, $target, $allow, $accounts), null, 'id');
    }

    public function testMovesWithSubgroupsAndReportsLevels(): void
    {
        $row = $this->plan([11], 3)[11];
        $this->assertSame(GroupMove::MOVE, $row['status']);
        $this->assertSame(10, $row['from']);
        $this->assertSame([11, 12], $row['subtree'], 'the group moves with its subgroups');
        $this->assertSame([3], $row['gained'], 'under Author it gets Special');
        $this->assertSame([7], $row['lost'], 'and leaves Socios behind');
        $this->assertSame([], $row['access']);
    }

    public function testChangesInAdministratorAccessAreRefused(): void
    {
        $row = $this->plan([4], 6)[4];
        $this->assertSame(GroupMove::REFUSED, $row['status']);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_BACKEND', $row['reason']);
        $this->assertSame([['id' => 4, 'before' => 'none', 'after' => 'backend'], ['id' => 5, 'before' => 'none', 'after' => 'backend']], $row['access'],
            'every subgroup that would gain administrator access is named');
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_BACKEND', $this->plan([7], 2)[7]['reason'], 'losing administrator access is refused too');
    }

    public function testRefusals(): void
    {
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_SUPER', $this->plan([8], 2)[8]['reason']);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_ROOT', $this->plan([1], 2)[1]['reason']);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_CYCLE', $this->plan([2], 4)[2]['reason'], 'not inside its own subgroups');
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_CYCLE', $this->plan([3], 3)[3]['reason'], 'not under itself');
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_TARGET_SUPER', $this->plan([3], 8)[3]['reason']);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_TARGET', $this->plan([3], 99)[3]['reason']);
        $this->assertSame(GroupMove::SAME, $this->plan([10], 2)[10]['status'], 'already there: nothing to do');
        $groups = $this->tree();
        $groups[5]->title = 'SOCIOS';
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_TITLE', $this->plan([5], 2, $groups)[5]['reason'], 'Joomla does not allow two groups with the same name under one parent');
    }

    public function testSelectedSubgroupsMoveWithTheirParent(): void
    {
        $plan = $this->plan([12, 10], 3);
        $this->assertSame(GroupMove::MOVE, $plan[10]['status']);
        $this->assertSame(GroupMove::COVERED, $plan[12]['status']);
        $this->assertSame(10, $plan[12]['via']);
        $this->assertSame([10, 12], array_keys($plan), 'rows in tree order');
        // When the parent cannot move, the subgroup is decided on its own.
        $plan = $this->plan([2, 4], 3);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_CYCLE', $plan[2]['reason']);
        $this->assertSame(GroupMove::SAME, $plan[4]['status']);
        // Manager keeps its own administrator access wherever it goes, so it can move with Administrator.
        $plan = $this->plan([6, 7], 2);
        $this->assertSame(GroupMove::MOVE, $plan[6]['status']);
        $this->assertSame(GroupMove::COVERED, $plan[7]['status']);
    }

    public function testTwoGroupsWithTheSameNameCannotMeetUnderTheNewParent(): void
    {
        $groups = $this->tree();
        $groups[12]->title = 'Publisher';
        $plan = $this->plan([5, 12], 2, $groups);
        $this->assertSame(GroupMove::MOVE, $plan[5]['status']);
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_TITLE', $plan[12]['reason']);
    }

    public function testAccountsAreJudgedOnAllTheirGroups(): void
    {
        // Socios denies administrator login. An account in Manager and in Junta (below Socios) has no
        // administrator access today; moving Junta out of Socios would give it access through Manager.
        $allow = fn (string $action, array $ids): bool => $action === 'core.admin' ? in_array(8, $ids, true)
            : (!in_array(10, $ids, true) && (in_array(6, $ids, true) || in_array(8, $ids, true)));
        $groups = $this->tree();
        $groups[6]->backend = $groups[7]->backend = true;
        $accounts = [50 => ['username' => 'ana', 'groups' => [6, 11]], 51 => ['username' => 'luis', 'groups' => [12]]];
        $row = $this->plan([11], 3, $groups, $accounts, $allow)[11];
        $this->assertSame('COM_USERTOOLS_MOVE_REASON_ACCOUNTS', $row['reason']);
        $this->assertSame([['username' => 'ana', 'before' => 'none', 'after' => 'backend']], $row['accounts'], 'only the account whose access changes is named');
        $this->assertSame([], $row['access'], 'the groups themselves do not change');
        $this->assertSame(GroupMove::MOVE, $this->plan([11], 3, $groups, [51 => $accounts[51]], $allow)[11]['status'], 'without that account the move is fine');
    }

    public function testPathFollowsTheMoves(): void
    {
        $this->assertSame([12, 11, 10, 2, 1], GroupMove::path($this->tree(), 12));
        $this->assertSame([12, 11, 3, 2, 1], GroupMove::path($this->tree(), 12, [11 => 3]));
    }
}
