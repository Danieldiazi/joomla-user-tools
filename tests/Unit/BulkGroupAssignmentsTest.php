<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountGroupAdder;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\BulkGroupAssignments as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class BulkGroupAssignmentsTest extends TestCase
{
    /** @return array<int, object> */
    private function groups(): array
    {
        $list = [
            [1, 0, 'Public', false],
            [2, 1, 'Registered', false],
            [8, 1, 'Super Users', true],
            [11, 2, 'Socios', false],
            [12, 11, 'Directiva', false],
            [13, 2, 'Directiva', false],
            [14, 2, 'Doble', false],
            [15, 2, 'Doble', false],
            [16, 1, 'Manager', true],
        ];
        $groups = [];
        foreach ($list as [$id, $parent, $title, $privileged]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title,
                'backend' => $privileged, 'superUser' => $id === 8];
        }
        return $groups;
    }

    /** @return array<int, UserRecord> */
    private function users(): array
    {
        $rows = [];
        foreach ([[41, 'alice', [2], false], [42, 'bob', [2, 11], false], [43, 'boss', [16], true]] as [$id, $name, $groups, $backend]) {
            $rows[$id] = new UserRecord($id, $name, ucfirst($name), $name . '@example.test', 0,
                null, null, $groups, $groups, false, $backend);
        }
        return $rows;
    }

    public function testResolvesOnlyIdsAndUniqueFullPaths(): void
    {
        $g = $this->groups();
        $this->assertSame(11, Plan::resolveGroup($g, '#11'));
        $this->assertSame(11, Plan::resolveGroup($g, '11'));
        $this->assertSame(12, Plan::resolveGroup($g, 'Public/Registered/Socios/Directiva'));
        $this->assertSame(13, Plan::resolveGroup($g, 'Public/Registered/Directiva'));
        $this->assertSame(0, Plan::resolveGroup($g, 'Directiva'));
        $this->assertSame(-1, Plan::resolveGroup($g, 'Public/Registered/Doble'));
        $this->assertSame(0, Plan::resolveGroup($g, '#999'));
    }

    public function testClassifiesDuplicatesExistingMembersProtectedAndUnknown(): void
    {
        $plan = Plan::plan($this->groups(), $this->users(), 'pairs',
            "ALICE;#11\nalice;#11\nbob;#11\nboss;#11\nnobody;#11\nalice;#8\nalice;Directiva\nalice;Public/Registered/Doble",
            new Operator(9, true, 'admin'));
        $this->assertSame([
            Plan::ADD, Plan::DUPLICATE, Plan::EXISTING, Plan::PROTECTED,
            Plan::UNKNOWN, Plan::PROTECTED, Plan::INVALID_GROUP, Plan::AMBIGUOUS,
        ], array_column($plan, 'status'));
        $this->assertSame(41, $plan[0]['id']);
    }

    public function testSingleGroupAndOperatorProtection(): void
    {
        $plan = Plan::plan($this->groups(), $this->users(), 'single', "alice\nbob", '#12',
            new Operator(41, true, 'alice'));
        $this->assertSame([Plan::PROTECTED, Plan::ADD], array_column($plan, 'status'));
    }

    public function testWriterPreservesMembershipAndAuditsEachAddedGroup(): void
    {
        $user = new class () {
            public int $id = 41;
            public string $username = 'alice';
            public array $groups = [2 => 2];
            public bool $saved = false;
            public function authorise(string $permission): bool { return false; }
            public function save(bool $updateOnly): bool { $this->saved = $updateOnly; return true; }
        };
        $factory = new class ($user) implements UserFactoryInterface {
            public function __construct(private object $user) {}
            public function loadUserById(int $id) { return $this->user; }
            public function loadUserByUsername(string $name) { return $this->user; }
        };
        $keys = [];
        $logger = new ActionLogger(static function (array $messages, string $key) use (&$keys): void {
            $keys[] = [$key, $messages[0]['group'] ?? ''];
        });
        $writer = new AccountGroupAdder($factory, $logger);
        $operator = new Operator(9, true, 'admin');
        $this->assertSame(AccountGroupAdder::ADDED, $writer->add(41, 'alice', [2], [11, 12], $operator, true));
        $this->assertSame([2, 11, 12], array_values($user->groups));
        $this->assertTrue($user->saved);
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_GROUP_ADDED', '#11'],
            ['COM_USERTOOLS_ACTION_LOG_GROUP_ADDED', '#12']], $keys);
        $this->assertSame(AccountGroupAdder::CHANGED, $writer->add(41, 'alice', [2], [11], $operator, true));
        $this->assertCount(2, $keys, 'stale preview never writes a duplicate audit record');
    }
}
