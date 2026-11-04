<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\PasswordGenerator;
use Danieldiazi\Component\Usertools\Administrator\Service\SsoAccountConverter;
use Danieldiazi\Component\Usertools\Administrator\Service\SsoConversionPlan as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class SsoConversionTest extends TestCase
{
    private function groups(): array
    {
        return [
            1 => (object) ['id' => 1, 'lft' => 1, 'rgt' => 12],
            2 => (object) ['id' => 2, 'lft' => 2, 'rgt' => 9],
            3 => (object) ['id' => 3, 'lft' => 3, 'rgt' => 4],
            5 => (object) ['id' => 5, 'lft' => 5, 'rgt' => 6],
            8 => (object) ['id' => 8, 'lft' => 10, 'rgt' => 11],
        ];
    }

    public function testFiltersAndSubgroupScope(): void
    {
        $groups = $this->groups();
        self::assertSame([2], Plan::groupIds($groups, 2, false));
        self::assertSame([2, 3, 5], Plan::groupIds($groups, 2, true));
        self::assertSame([], Plan::groupIds($groups, 0, true));
        self::assertSame(['group' => 2, 'subgroups' => true, 'status' => 'active', 'visits' => 'no'],
            Plan::filters(['group' => '2', 'subgroups' => 1, 'status' => 'active', 'visits' => 'no'], $groups));
        $this->expectException(UserFacingException::class);
        Plan::filters(['group' => 999], $groups);
    }

    public function testProtectedAccountsAndStatusVisitFilters(): void
    {
        $groups = $this->groups();
        $users = [
            Fixtures::user(11, 'first', [3], block: 0),
            Fixtures::user(12, 'blocked', [3], block: 1),
            Fixtures::user(13, 'root', [8], super: true, backend: true),
            Fixtures::user(14, 'manager', [3], backend: true),
            Fixtures::user(15, 'elsewhere', [8]),
            Fixtures::user(9, 'self', [3]),
        ];
        $operator = new Operator(9, false);
        $filters = Plan::filters(['group' => 2, 'subgroups' => 1, 'status' => 'active'], $groups);
        self::assertSame(['first'], array_column(Plan::candidates($users, $groups, $filters, $operator), 'username'));
        $filters['status'] = 'all';
        self::assertSame(['first', 'blocked'], array_column(Plan::candidates($users, $groups, $filters, $operator), 'username'));
        $filters['visits'] = 'yes';
        self::assertSame([], Plan::candidates($users, $groups, $filters, $operator));
        $filters['visits'] = 'no';
        self::assertCount(2, Plan::candidates($users, $groups, $filters, $operator));
        $filters['group'] = 0;
        self::assertContains('manager', array_column(Plan::candidates($users, $groups, $filters, new Operator(9, true)), 'username'));
    }

    public function testConversionRotatesPasswordClearsResetAuditsAndTerminatesSessions(): void
    {
        $account = $this->account();
        $reviewed = $this->reviewed($account);
        $oldHash = $account->password;
        $events = [];
        $sessions = [];
        $converter = $this->converter($account, $events, $sessions);
        $result = $converter->convert($reviewed, new Operator(9, true, 'operator'));
        self::assertSame('converted', $result['status']);
        self::assertTrue($result['sessionsClosed']);
        self::assertSame(0, $account->requireReset);
        self::assertSame(1, $account->saves);
        self::assertNotSame($oldHash, $account->password);
        self::assertSame([11], $sessions);
        self::assertSame([2], $account->groups);
        self::assertSame(0, $account->block);
        self::assertSame('alice@example.test', $account->email);
        self::assertSame('COM_USERTOOLS_ACTION_LOG_CONVERTED_SSO', $events[0][0]);
        self::assertStringNotContainsString($account->password, json_encode($events));
        self::assertSame('', $account->password_clear);
        self::assertSame('changed', $converter->convert($reviewed, new Operator(9, true))['status']);
        self::assertCount(1, $events);
    }

    public function testStaleAndPrivilegedAccountsAreSkipped(): void
    {
        foreach (['reset', 'username', 'group', 'block', 'visit', 'super', 'backend', 'self'] as $change) {
            $account = $this->account();
            $reviewed = $this->reviewed($account);
            $events = [];
            $sessions = [];
            $operator = new Operator($change === 'self' ? 11 : 9, $change !== 'backend');
            if ($change === 'reset') $account->requireReset = 0;
            if ($change === 'username') $account->username = 'someone';
            if ($change === 'group') $account->groups = [5];
            if ($change === 'block') $account->block = 1;
            if ($change === 'visit') $account->lastvisitDate = '2026-11-04 19:00:00';
            if ($change === 'super') $account->super = true;
            if ($change === 'backend') $account->backend = true;
            $result = $this->converter($account, $events, $sessions)->convert($reviewed, $operator);
            self::assertSame('changed', $result['status'], $change);
            self::assertSame(0, $account->saves, $change);
            self::assertSame([], $events, $change);
        }
    }

    private function account(): object
    {
        return new class {
            public int $id = 11;
            public string $username = 'alice';
            public string $name = 'Alice';
            public string $email = 'alice@example.test';
            public int $block = 0;
            public int $requireReset = 1;
            public ?string $lastvisitDate = null;
            public ?string $registerDate = '2026-01-01 00:00:00';
            public array $groups = [2];
            public bool $super = false;
            public bool $backend = false;
            public string $password;
            public string $password_clear = '';
            public int $saves = 0;
            public function __construct() { $this->password = password_hash('Old-Pass-123Aa!', PASSWORD_BCRYPT); }
            public function authorise(string $action): bool {
                return $action === 'core.admin' && $this->super
                    || $action === 'core.login.admin' && ($this->backend || $this->super);
            }
            public function bind(array $data): bool {
                $this->password = password_hash($data['password'], PASSWORD_BCRYPT);
                $this->password_clear = $data['password'];
                $this->requireReset = $data['requireReset'];
                return true;
            }
            public function save(bool $updateOnly): bool {
                $this->saves++;
                return $updateOnly;
            }
        };
    }

    /** @return array<string, mixed> */
    private function reviewed(object $account): array
    {
        return [
            'id' => $account->id, 'username' => $account->username,
            'fingerprint' => \Danieldiazi\Component\Usertools\Administrator\Service\AccountProfile::fingerprint($account),
        ];
    }

    /** @param list<array{0: string, 1: array<mixed>}> $events
     * @param list<int> $sessions */
    private function converter(object $account, array &$events, array &$sessions): SsoAccountConverter
    {
        $factory = new class ($account) implements UserFactoryInterface {
            public function __construct(private object $account) {}
            public function loadUserById(int $id) { return $this->account; }
            public function loadUserByUsername(string $username) { return $this->account; }
        };
        $logger = new ActionLogger(static function (array $messages, string $key) use (&$events): void {
            $events[] = [$key, $messages];
        });
        return new SsoAccountConverter($factory, new PasswordGenerator(), $logger,
            static function (int $id) use (&$sessions): bool {
                $sessions[] = $id;
                return true;
            });
    }
}
