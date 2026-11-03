<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountNameUpdater;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\BulkNameUpdates as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class BulkNameUpdatesTest extends TestCase
{
    public function testOnlyExactUsernamesResolveAndProtectedOrInvalidRowsCannotUpdate(): void
    {
        $users = [Fixtures::user(2, 'alice'), Fixtures::user(3, 'manager', backend: true),
            Fixtures::user(4, 'root', super: true), Fixtures::user(9, 'operator'),
            Fixtures::user(5, 'same')];
        $rows = Plan::plan("alice;Ángela; Flores\nALICE;Other\nmanager;New\nroot;New\noperator;New\nsame;Same\nmissing;New\nbad\n;Name\nalice2;", $users, new Operator(9, true));
        self::assertSame(['update', 'unknown', 'protected', 'protected', 'protected', 'unchanged',
            'unknown', 'invalid_line', 'invalid_line', 'invalid_line'], array_column($rows, 'status'));
        self::assertSame('Alice', $rows[0]['before']);
        self::assertSame('Ángela; Flores', $rows[0]['after']);
        self::assertSame(2, $rows[0]['id']);
    }

    public function testEveryDuplicateIsRejectedAndAmbiguityNeverChoosesFirstAccount(): void
    {
        $rows = Plan::plan("alice;First\nalice;Second\ndouble;Name", [Fixtures::user(2, 'alice'),
            Fixtures::user(3, 'double'), Fixtures::user(4, 'double')], new Operator(9, true));
        self::assertSame(['duplicate', 'duplicate', 'ambiguous'], array_column($rows, 'status'));
        self::assertSame(0, $rows[2]['id']);
    }

    public function testWriterChangesOnlyNameAndAuditsOnceWithoutCopyingNames(): void
    {
        $user = $this->account();
        $keys = [];
        $writer = $this->writer($user, $keys);
        self::assertSame(['status' => 'updated'], $writer->update(2, 'alice', 'Alice', 'New Name', new Operator(9, true)));
        self::assertSame('New Name', $user->name);
        self::assertSame('alice', $user->username);
        self::assertSame('alice@example.test', $user->email);
        self::assertSame([2], $user->groups);
        self::assertSame(1, $user->saves);
        self::assertSame(['COM_USERTOOLS_ACTION_LOG_NAME_CHANGED'], array_column($keys, 0));
        self::assertStringNotContainsString('New Name', json_encode($keys));
        self::assertSame(['status' => 'changed'], $writer->update(2, 'alice', 'Alice', 'Other', new Operator(9, true)));
        self::assertSame(1, $user->saves);
        self::assertCount(1, $keys);
    }

    public function testWriterRejectsChangedAndPrivilegedAccountsAndReportsSaveFailures(): void
    {
        foreach (['operator', 'username', 'name', 'admin', 'backend', 'missing', 'false', 'throw'] as $case) {
            $user = $this->account();
            $keys = [];
            $operator = new Operator($case === 'operator' ? 2 : 9, true);
            if ($case === 'username') $user->username = 'renamed';
            if ($case === 'name') $user->name = 'Concurrent edit';
            if ($case === 'missing') $user->id = 0;
            $user->permission = match ($case) { 'admin' => 'core.admin', 'backend' => 'core.login.admin', default => '' };
            $user->failure = $case;
            $result = $this->writer($user, $keys)->update(2, 'alice', 'Alice', 'New Name', $operator);
            self::assertSame(in_array($case, ['false', 'throw'], true) ? 'error' : 'changed', $result['status'], $case);
            self::assertSame(0, $user->saves, $case);
            self::assertSame([], $keys, $case);
        }
    }

    private function account(): object
    {
        return new class {
            public int $id = 2;
            public string $username = 'alice';
            public string $name = 'Alice';
            public string $email = 'alice@example.test';
            public array $groups = [2];
            public int $saves = 0;
            public string $permission = '';
            public string $failure = '';
            public function authorise(string $permission): bool { return $permission === $this->permission; }
            public function save(bool $updateOnly): bool {
                if ($this->failure === 'throw') throw new \RuntimeException('save failed');
                if ($this->failure === 'false') return false;
                $this->saves++;
                return $updateOnly;
            }
        };
    }

    private function writer(object $user, array &$keys): AccountNameUpdater
    {
        $factory = new class ($user) implements UserFactoryInterface {
            public function __construct(private object $user) {}
            public function loadUserById(int $id) { return $this->user; }
            public function loadUserByUsername(string $username) { return $this->user; }
        };
        return new AccountNameUpdater($factory, new ActionLogger(static function (array $messages, string $key) use (&$keys): void {
            $keys[] = [$key, $messages];
        }));
    }
}
