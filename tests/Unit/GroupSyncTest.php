<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountGroupSyncer;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountRegrouper;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupSync;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class GroupSyncTest extends TestCase
{
    private const OPERATOR = 105;

    /** @return array<int, object> */
    private function fixtureGroups(): array
    {
        // id, parent, title, lft, rgt, backend, superUser
        $list = [
            [1, 0, 'Public', 1, 26, false, false],
            [2, 1, 'Registered', 2, 3, false, false],
            [10, 1, 'Departamentos', 4, 17, false, false],
            [11, 10, 'Ventas', 5, 14, false, false],
            [12, 11, 'Norte', 6, 7, false, false],
            [13, 11, 'Admins ventas', 8, 9, true, false],
            [15, 11, 'Sur', 10, 11, false, false],
            [16, 11, 'Sur', 12, 13, false, false],
            [14, 10, 'Web', 15, 16, false, false],
            [8, 1, 'Super Users', 18, 19, true, true],
            [20, 1, 'Proyectos', 20, 23, false, false],
            [21, 20, 'Web', 21, 22, false, false],
        ];
        $groups = [];
        foreach ($list as [$id, $parent, $title, $lft, $rgt, $backend, $super]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title, 'lft' => $lft, 'rgt' => $rgt,
                'level' => 0, 'backend' => $backend, 'superUser' => $super];
        }
        return $groups;
    }

    /** @return array<int, UserRecord> */
    private function users(): array
    {
        $make = static fn (int $id, string $name, array $groups, bool $backend = false, bool $super = false): UserRecord =>
            new UserRecord($id, $name, ucfirst($name), $name . '@example.test', 0, null, null, $groups, $groups, $super, $backend || $super);
        return [
            100 => $make(100, 'ana', [2, 11]),
            101 => $make(101, 'luis', [2, 12, 21]),
            102 => $make(102, 'marta', [11]),
            103 => $make(103, 'boss', [2, 11, 13], true),
            104 => $make(104, 'Dup', [2]),
            106 => $make(106, 'dup', [2]),
            105 => $make(105, 'operator', [2, 11]),
            107 => $make(107, 'outsider', [2, 14]),
        ];
    }

    /** @return array<string, mixed> */
    private function plan(string $text, string $mode = GroupSync::PARTIAL, string $by = GroupSync::BY_USERNAME, bool $canCreate = true, array $emails = [], int $root = 11): array
    {
        $users = $this->users();
        foreach ($emails as $index => $email) {
            $users[900 + $index] = new UserRecord(900 + $index, 'holder' . $index, 'Holder', $email, 0, null, null, [2], [2], false, false);
        }
        return GroupSync::plan($this->fixtureGroups(), $users, $root, $mode, $by, $text, new Operator(self::OPERATOR, true, 'operator'), $canCreate);
    }

    /** @return array<string, array<string, mixed>> rows keyed by username */
    private function byName(array $plan): array
    {
        $rows = [];
        foreach ($plan['rows'] as $row) {
            $rows[$row['username']] = $row;
        }
        return $rows;
    }

    public function testTheBranchListsManagedGroupsAndLeavesOutAdministratorGroups(): void
    {
        self::assertSame(['managed' => [11, 12, 15, 16], 'protected' => [13]], GroupSync::branch($this->fixtureGroups(), 11));
        foreach ([1, 8, 13, 999] as $root) {
            try {
                GroupSync::branch($this->fixtureGroups(), $root);
                self::fail('Branch ' . $root . ' should be refused');
            } catch (UserFacingException $e) {
                self::assertSame('COM_USERTOOLS_SYNC_ERROR_BRANCH', $e->key());
            }
        }
        $groups = $this->fixtureGroups();
        $groups[2]->lft = 6;
        $groups[2]->rgt = 7;
        $groups[12]->lft = 2;
        $groups[12]->rgt = 3;
        $this->expectExceptionObject(new UserFacingException('COM_USERTOOLS_SYNC_ERROR_BRANCH_DEFAULT'));
        GroupSync::branch($groups, 11);
    }

    public function testAPartialListOnlyTouchesListedAccountsAndKeepsOtherGroups(): void
    {
        $rows = $this->byName($this->plan("ana;#12\nluis;Public/Departamentos/Ventas"));
        self::assertCount(2, $rows, 'accounts not on a partial list are not part of the plan');
        self::assertSame([GroupSync::CHANGE, [12], [11], []], [$rows['ana']['status'], $rows['ana']['add'], $rows['ana']['remove'], $rows['ana']['kept']]);
        self::assertSame([[11], [12]], [$rows['luis']['add'], $rows['luis']['remove']], 'Proyectos/Web (21) is outside the branch and never listed');
        self::assertFalse($rows['luis']['default']);
    }

    public function testACompleteListProposesLeaversWithoutTouchingProtectedAccounts(): void
    {
        $plan = $this->plan("ana;#11\nluis;#12", GroupSync::FULL);
        $rows = $this->byName($plan);
        self::assertSame(GroupSync::UNCHANGED, $rows['ana']['status']);
        self::assertSame(GroupSync::UNCHANGED, $rows['luis']['status']);
        self::assertSame([GroupSync::LEAVE, [11], 0, true], [$rows['marta']['status'], $rows['marta']['remove'], $rows['marta']['line'], $rows['marta']['default']],
            'marta leaves the branch; she had no other group, so the default group is added');
        self::assertSame(GroupSync::PROTECTED_ACCOUNT, $rows['boss']['status'], 'an administrator account is never a leaver');
        self::assertSame(GroupSync::PROTECTED_ACCOUNT, $rows['operator']['status'], 'the operator is never a leaver');
        self::assertArrayNotHasKey('outsider', $rows, 'members of other branches are not part of the plan');
        self::assertSame(5, $plan['members']);
        self::assertSame(1, GroupSync::writes($plan['rows']));
    }

    public function testInvalidRowsAreNeverGuessed(): void
    {
        $rows = $this->plan("ana;#14\nluis;#13\nmarta;#999\nana2;Public/Departamentos/Ventas/Sur\nDUP;#11\nnobody\n;#11\na;b;c;d;e")['rows'];
        self::assertSame([GroupSync::OUTSIDE, GroupSync::PROTECTED_GROUP, GroupSync::UNKNOWN_GROUP, GroupSync::AMBIGUOUS_GROUP, GroupSync::AMBIGUOUS_ACCOUNT,
            GroupSync::INVALID_LINE, GroupSync::INVALID_LINE, GroupSync::INVALID_LINE], array_column($rows, 'status'));
        self::assertSame('#14', $rows[0]['group']);
        self::assertSame([], $rows[0]['add'], 'an invalid row changes nothing');
    }

    public function testAnAccountListedTwiceIsLeftUntouched(): void
    {
        $rows = $this->plan("ana;#11\nANA;#12\nluis;#12")['rows'];
        self::assertSame([GroupSync::DUPLICATE, GroupSync::DUPLICATE, GroupSync::UNCHANGED], array_column($rows, 'status'));
        $byId = $this->plan("100;#11\n100;#11", GroupSync::PARTIAL, GroupSync::BY_ID)['rows'];
        self::assertSame([GroupSync::DUPLICATE, GroupSync::DUPLICATE], array_column($byId, 'status'), 'even identical rows');
    }

    public function testIdentificationByIdOnlyAcceptsExistingIds(): void
    {
        $rows = $this->plan("100;#12\n999;#12\nana;#12\n0;#12", GroupSync::PARTIAL, GroupSync::BY_ID)['rows'];
        self::assertSame([GroupSync::CHANGE, GroupSync::UNKNOWN_ACCOUNT, GroupSync::INVALID_LINE, GroupSync::INVALID_LINE], array_column($rows, 'status'));
        self::assertSame('ana', $rows[0]['username']);
    }

    public function testNewAccountsNeedPermissionAndAnUnusedValidEmail(): void
    {
        $rows = $this->plan("nuevo;#12;nuevo@example.test;Nuevo Socio\nsin;#12;no-es-correo\nusado;#12;used@example.test\notro;#12;NUEVO@example.test\nvacio;#12\nana2;#12;ANA@example.test")['rows'];
        self::assertSame(GroupSync::EMAIL_IN_USE, $rows[5]['status'], 'an existing account email, compared in memory without case');
        self::assertSame([GroupSync::CREATE, GroupSync::EMAIL_INVALID, GroupSync::EMAIL_IN_USE, GroupSync::EMAIL_IN_USE, GroupSync::EMAIL_INVALID],
            array_column($this->plan("nuevo;#12;nuevo@example.test;Nuevo Socio\nsin;#12;no-es-correo\nusado;#12;used@example.test\notro;#12;NUEVO@example.test\nvacio;#12", GroupSync::PARTIAL, GroupSync::BY_USERNAME, true, ['used@example.test'])['rows'], 'status'));
        self::assertSame([12], $rows[0]['add']);
        self::assertSame('Nuevo Socio', $rows[0]['name']);
        self::assertSame([GroupSync::CREATE_NOT_ALLOWED], array_column($this->plan('nuevo;#12;nuevo@example.test', GroupSync::PARTIAL, GroupSync::BY_USERNAME, false)['rows'], 'status'));
    }

    public function testANewAccountNeedsAUsernameJoomlaAccepts(): void
    {
        self::assertSame([GroupSync::USERNAME_TOO_SHORT], array_column($this->plan('x;#12;x@example.test')['rows'], 'status'));
    }

    public function testAnEmailThatDiffersIsShownButNeverUsedToMatchAnotherPerson(): void
    {
        $rows = $this->byName($this->plan("ana;#11;otra@example.test\nluis;#12;LUIS@example.test"));
        self::assertTrue($rows['ana']['emailDiffers']);
        self::assertFalse($rows['luis']['emailDiffers']);
        self::assertSame(100, $rows['ana']['id']);
    }

    public function testEmptyGroupsRemoveTheBranchAndOnlyThenAddTheDefaultGroup(): void
    {
        $rows = $this->byName($this->plan("ana;\nmarta;"));
        self::assertSame([[11], false], [$rows['ana']['remove'], $rows['ana']['default']], 'ana keeps Registered');
        self::assertSame([[11], true], [$rows['marta']['remove'], $rows['marta']['default']]);
    }

    public function testACompleteListWithAnInvalidRowCannotBeApplied(): void
    {
        $rows = $this->plan("ana;#11\nluis;#14", GroupSync::FULL)['rows'];
        self::assertTrue(GroupSync::blocked(GroupSync::FULL, $rows));
        self::assertFalse(GroupSync::blocked(GroupSync::PARTIAL, $rows), 'a partial list applies only its valid rows');
        self::assertSame(1, GroupSync::errors($rows));
    }

    public function testRemovingMostMembersNeedsAnExtraConfirmation(): void
    {
        $plan = $this->plan('ana;#11', GroupSync::FULL);
        self::assertSame([GroupSync::LEAVE, GroupSync::LEAVE], array_values(array_column(array_filter($plan['rows'], static fn ($r) => $r['line'] === 0 && $r['status'] === GroupSync::LEAVE), 'status')));
        self::assertTrue(GroupSync::massive($plan['rows'], 3), '2 of 3 removable members');
        self::assertFalse(GroupSync::massive($plan['rows'], 5));
        // Listed accounts with empty groups lose the whole branch too, in either mode.
        foreach ([GroupSync::FULL, GroupSync::PARTIAL] as $mode) {
            $emptied = $this->plan("ana;\nluis;\nmarta;", $mode);
            self::assertSame(3, GroupSync::leaving($emptied['rows']), $mode);
            self::assertTrue(GroupSync::massive($emptied['rows'], $emptied['members']), $mode);
        }
        self::assertSame(0, GroupSync::leaving($this->plan('ana;#12')['rows']), 'moving within the branch is not leaving it');
    }

    public function testLinesAcceptCommentsBomQuotesAndRefuseHugeLists(): void
    {
        $lines = GroupSync::lines("\xEF\xBB\xBF# comment\n\n  ana ; #11|#12 \r\n\"x;y\";#12;x@example.test;\"Pérez; Ana\"");
        self::assertSame([[3, ['ana', '#11|#12']], [4, ['x;y', '#12', 'x@example.test', 'Pérez; Ana']]], $lines);
        $this->expectExceptionObject(new UserFacingException('COM_USERTOOLS_SYNC_ERROR_TOO_LONG', [GroupSync::MAX_LINES]));
        GroupSync::lines(str_repeat("a;#1\n", GroupSync::MAX_LINES + 1));
    }

    public function testUnknownModesAreRefused(): void
    {
        $this->expectExceptionObject(new UserFacingException('COM_USERTOOLS_SYNC_ERROR_INPUT'));
        $this->plan('ana;#11', 'everything');
    }

    /** @return array{0: object, 1: UserFactoryInterface} */
    private function account(array $groups, bool $admin = false): array
    {
        $user = new class ($groups, $admin) {
            public int $id = 100;
            public string $username = 'ana';
            public int $saves = 0;
            public function __construct(public array $groups, private bool $admin) {}
            public function authorise(string $permission): bool { return $this->admin; }
            public function save(bool $updateOnly): bool { $this->saves++; return true; }
        };
        $factory = new class ($user) implements UserFactoryInterface {
            public function __construct(private object $user) {}
            public function loadUserById(int $id) { return $this->user; }
            public function loadUserByUsername(string $name) { return $this->user; }
        };
        return [$user, $factory];
    }

    public function testTheWriterChangesOnlyReviewedManagedGroupsAndLogsEachChange(): void
    {
        [$user, $factory] = $this->account([2 => 2, 11 => 11, 21 => 21]);
        $logs = [];
        $logger = new ActionLogger(static function (array $messages, string $key) use (&$logs): void {
            $logs[] = [$key, $messages[0]['group'] ?? ($messages[0]['groups'] ?? '')];
        });
        $closed = [];
        $writer = new AccountGroupSyncer($factory, $logger, static function (int $id) use (&$closed): string {
            $closed[] = $id;
            return AccountRegrouper::SESSIONS_CLOSED;
        });
        $managed = [11 => true, 12 => true];
        $operator = new Operator(self::OPERATOR, true, 'operator');
        $outcome = $writer->sync(100, 'ana', [2, 11, 21], [12], [11], $managed, [11 => 'Ventas', 12 => 'Norte'], $operator);
        self::assertSame(['status' => AccountGroupSyncer::SAVED, 'sessions' => AccountRegrouper::SESSIONS_CLOSED, 'default' => false], $outcome);
        self::assertSame([2, 21, 12], array_values($user->groups), 'groups outside the branch are kept');
        self::assertSame([['COM_USERTOOLS_ACTION_LOG_GROUP_ADDED', '#12'], ['COM_USERTOOLS_ACTION_LOG_UNGROUPED', 'Ventas']], $logs);
        self::assertSame([100], $closed);
        // The same entry again: the account no longer matches the review, so nothing is written twice.
        self::assertSame(AccountGroupSyncer::CHANGED, $writer->sync(100, 'ana', [2, 11, 21], [12], [11], $managed, [], $operator)['status']);
        self::assertSame(1, $user->saves);
        self::assertCount(2, $logs);
    }

    public function testTheWriterRefusesStaleOrUnsafeChanges(): void
    {
        $operator = new Operator(self::OPERATOR, true, 'operator');
        [$user, $factory] = $this->account([2 => 2, 11 => 11]);
        $writer = new AccountGroupSyncer($factory, new ActionLogger(static function (): void {}), static fn (): string => 'closed');
        self::assertSame(AccountGroupSyncer::CHANGED, $writer->sync(100, 'ana', [2, 11], [13], [], [11 => true], [], $operator)['status'], 'a group that left the branch');
        self::assertSame(AccountGroupSyncer::CHANGED, $writer->sync(100, 'other', [2, 11], [], [11], [11 => true], [], $operator)['status'], 'another username');
        self::assertSame(AccountGroupSyncer::CHANGED, $writer->sync(self::OPERATOR, 'ana', [2, 11], [], [11], [11 => true], [], $operator)['status'], 'the operator');
        [$admin, $adminFactory] = $this->account([2 => 2, 11 => 11], true);
        $adminWriter = new AccountGroupSyncer($adminFactory, new ActionLogger(static function (): void {}), static fn (): string => 'closed');
        self::assertSame(AccountGroupSyncer::CHANGED, $adminWriter->sync(100, 'ana', [2, 11], [], [11], [11 => true], [], $operator)['status'], 'an account that gained administrator access');
        self::assertSame(0, $user->saves + $admin->saves);
    }

    public function testAnAccountLeftWithoutGroupsGetsTheDefaultGroupAndSessionProblemsAreReportedApart(): void
    {
        [$user, $factory] = $this->account([11 => 11]);
        $logs = [];
        $writer = new AccountGroupSyncer($factory, new ActionLogger(static function (array $messages, string $key) use (&$logs): void {
            $logs[] = [$key, $messages[0]['added'] ?? ''];
        }), static function (): string {
            throw new \RuntimeException('session store down');
        });
        $outcome = $writer->sync(100, 'ana', [11], [], [11], [11 => true], [11 => 'Ventas', 2 => 'Registered'], new Operator(self::OPERATOR, true, 'operator'));
        self::assertSame(['status' => AccountGroupSyncer::SAVED, 'sessions' => AccountRegrouper::SESSIONS_OPEN, 'default' => true], $outcome);
        self::assertSame([2 => 2], $user->groups);
        self::assertSame([['COM_USERTOOLS_ACTION_LOG_UNGROUPED_DEFAULT', 'Registered']], $logs);
    }
}
