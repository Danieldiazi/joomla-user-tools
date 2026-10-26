<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountRegrouper;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupRemoval;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class GroupRemovalTest extends TestCase
{
    /**
     * Public > (Guest, Manager > Administrator, Registered > (Author > Editor > Publisher, Socios > Junta), Super Users).
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

    /**
     * @param list<int> $direct
     * @param list<int> $selected
     */
    private function plan(array $direct, array $selected, bool $recursive = false, ?Operator $operator = null, int $account = 50, bool $super = false, bool $backend = false, int $others = 1, int $default = 2): array
    {
        return GroupRemoval::plan($this->tree(), $direct, $selected, $recursive, $default, $operator ?? new Operator(1, true, 'admin'), $account, $super, $backend, $others);
    }

    public function testRemovesOnlyTheUntickedGroups(): void
    {
        $plan = $this->plan([2, 10, 12, 4], [10]);
        $this->assertSame([10], $plan['remove'], 'without the recursive option only Socios goes');
        $this->assertSame([2, 4, 12], $plan['keep']);
        $this->assertSame([], $plan['cascaded']);
        $this->assertSame('', $plan['reason']);
        $this->assertSame([4, 10], $this->plan([2, 4, 10], [10, 4, 99])['remove'], 'several groups, in tree order; groups it is not in are ignored');
    }

    public function testRecursiveRemovesEverythingBelow(): void
    {
        $plan = $this->plan([2, 10, 12, 4], [10], true);
        $this->assertSame([10, 12], $plan['remove'], 'Socios and its grandchild Presidencia (Junta itself is not a membership)');
        $this->assertSame([12 => 10], $plan['cascaded']);
        $this->assertSame([2, 4], $plan['keep'], 'parents and siblings stay');
        $all = $this->plan([2, 4, 5, 10, 11, 9], [2], true);
        $this->assertSame([2, 4, 5, 10, 11], $all['remove'], 'everything under Registered, at any depth');
        $this->assertSame([9], $all['keep']);
        $this->assertSame([3, 4, 5], GroupRemoval::subtree($this->tree(), 3));
        $this->assertSame([1, 2, 3], GroupRemoval::ancestors($this->tree(), 4));
    }

    public function testAccountLeftWithoutGroupsGetsTheDefaultGroup(): void
    {
        $plan = $this->plan([11, 12], [11], true);
        $this->assertSame([11, 12], $plan['remove']);
        $this->assertSame(2, $plan['added']);
        $this->assertSame([2], $plan['result']);

        $below = $this->plan([2], [2], true, default: 3);
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_DEFAULT_REMOVED', $below['reason'], 'a default group below a removed one would give its permissions back');
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_DEFAULT_REMOVED', $this->plan([10], [10], false, default: 12)['reason'], 'also without the recursive option');
        $this->assertSame(9, $this->plan([10], [10], true, default: 9)['added'], 'a default group elsewhere in the tree is fine');

        $blocked = $this->plan([2, 10], [2], true);
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_DEFAULT_REMOVED', $blocked['reason'], 'the default group (Registered) is being removed');
        $this->assertSame(0, $blocked['added']);
    }

    public function testNothingToRemove(): void
    {
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_NOTHING', $this->plan([2, 3], [])['reason']);
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_NOTHING', $this->plan([2, 3], [10], true)['reason'], 'not a member of the chosen group');
    }

    public function testSafetyRules(): void
    {
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_SELF', $this->plan([2, 10], [10], false, new Operator(50, true), 50)['reason'], 'never your own account');
        $delegate = new Operator(60, false, 'delegate');
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_BACKEND', $this->plan([6, 10], [10], false, $delegate, backend: true)['reason'], 'non Super Users never touch backend accounts');
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_BACKEND', $this->plan([8, 10], [10], false, $delegate, super: true, backend: true)['reason']);
        $this->assertSame('', $this->plan([2, 10], [10], false, $delegate)['reason'], 'a delegate can change ordinary accounts');

        $lastSuper = $this->plan([8, 2], [8], super: true, backend: true, others: 0);
        $this->assertSame('COM_USERTOOLS_UNGROUP_REASON_LAST_SUPER', $lastSuper['reason']);
        $this->assertTrue($lastSuper['losesSuper']);
        $ok = $this->plan([8, 2], [8], super: true, backend: true, others: 1);
        $this->assertSame('', $ok['reason']);
        $this->assertTrue($ok['losesSuper'] && $ok['losesBackend']);
        $this->assertFalse($this->plan([7, 10], [10], backend: true)['losesBackend'], 'Administrator still gives backend access');
        $this->assertTrue($this->plan([6, 7, 2], [6], true, backend: true)['losesBackend'], 'Manager and, recursively, Administrator');
    }

    public function testRegrouperRevalidatesSavesClosesSessionsAndLogs(): void
    {
        $user = new class {
            public int $id = 50;
            public string $username = 'ana';
            /** @var array<int, int> */
            public array $groups = [2 => 2, 10 => 10, 12 => 12];
            public int $saved = 0;
            public bool $updateOnly = false;

            public function save(bool $updateOnly = false): bool
            {
                $this->saved++;
                $this->updateOnly = $updateOnly;
                return true;
            }
        };
        $factory = new class ($user) implements UserFactoryInterface {
            public function __construct(private object $user)
            {
            }

            public function loadUserById(int $id)
            {
                return $this->user;
            }

            public function loadUserByUsername(string $username)
            {
                return $this->user;
            }
        };
        $logged   = [];
        $sessions = [];
        $logger   = new ActionLogger(function (array $messages, string $key) use (&$logged) {
            $logged[] = [$key, $messages[0]['groups'], $messages[0]['added']];
        });
        $left      = 0;
        $regrouper = new AccountRegrouper($factory, $logger, function (int $id) use (&$sessions) {
            $sessions[] = $id;
        }, function () use (&$left) {
            return $left;
        });
        $operator = new Operator(1, true, 'admin');

        $changed = $regrouper->regroup(50, [2, 10], [2], $operator, ['Socios'], '');
        $this->assertSame(AccountRegrouper::CHANGED, $changed['status'], 'groups differ from the review');
        $this->assertSame(0, $user->saved);

        $done = $regrouper->regroup(50, [12, 2, 10], [2], $operator, ['Socios', 'Presidencia'], '');
        $this->assertSame(AccountRegrouper::SAVED, $done['status']);
        $this->assertSame(AccountRegrouper::SESSIONS_CLOSED, $done['sessions']);
        $this->assertSame([2 => 2], $user->groups);
        $this->assertTrue($user->updateOnly);
        $this->assertSame([50], $sessions);
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_UNGROUPED', 'Socios, Presidencia', '']], $logged);

        $user->groups = [11 => 11];
        $regrouper->regroup(50, [11], [2], $operator, ['Junta'], 'Registered');
        $this->assertSame('COM_USERTOOLS_ACTION_LOG_UNGROUPED_DEFAULT', $logged[1][0]);
        $this->assertSame('Registered', $logged[1][2]);
    }

    public function testSurvivingSessionsAreReported(): void
    {
        $user = new class {
            public int $id = 50;
            public string $username = 'ana';
            /** @var array<int, int> */
            public array $groups = [2 => 2, 10 => 10];

            public function save(bool $updateOnly = false): bool
            {
                return true;
            }
        };
        $factory = new class ($user) implements UserFactoryInterface {
            public function __construct(private object $user)
            {
            }

            public function loadUserById(int $id)
            {
                return $this->user;
            }

            public function loadUserByUsername(string $username)
            {
                return $this->user;
            }
        };
        $logged  = 0;
        $logger  = new ActionLogger(function () use (&$logged) {
            $logged++;
        });
        $failing = new AccountRegrouper($factory, $logger, function () {
            throw new \RuntimeException('session backend down');
        }, fn () => 1);
        $outcome = $failing->regroup(50, [2, 10], [2], new Operator(1, true), ['Socios'], '');
        $this->assertSame(AccountRegrouper::SAVED, $outcome['status'], 'the saved groups are not undone');
        $this->assertSame(AccountRegrouper::SESSIONS_OPEN, $outcome['sessions'], 'a surviving session is reported, not hidden');
        $this->assertSame(1, $logged, 'the saved change is still logged');

        $this->assertSame(AccountRegrouper::SESSIONS_OPEN, (new AccountRegrouper($factory, $logger, fn () => null, function () {
            throw new \RuntimeException('db down');
        }))->closeSessions(50), 'an unverifiable count counts as open');
        $this->assertSame(AccountRegrouper::SESSIONS_UNKNOWN, (new AccountRegrouper($factory, $logger, fn () => null, fn () => null))->closeSessions(50), 'session metadata off');
        $this->assertSame(AccountRegrouper::SESSIONS_CLOSED, (new AccountRegrouper($factory, $logger, fn () => null, fn () => 0))->closeSessions(50));
    }
}
