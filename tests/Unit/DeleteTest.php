<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountDeleter;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteTest extends TestCase
{
    public function testBlockedAccountsAreEligibleOnlyForDeletion(): void
    {
        $cutoff  = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $blocked = Fixtures::user(1, 'old', block: 1, lastVisit: '2020-01-01 00:00:00');
        $this->assertFalse(InactivityPolicy::eligible($blocked, $cutoff, false), 'blocking tools ignore blocked accounts');
        $this->assertTrue(InactivityPolicy::eligible($blocked, $cutoff, false, true));
        $this->assertFalse(InactivityPolicy::eligible(Fixtures::user(2, 'recent', block: 1, lastVisit: '2025-06-01 00:00:00'), $cutoff, false, true));
    }

    public function testStatusFilter(): void
    {
        $active  = Fixtures::user(1, 'a');
        $blocked = Fixtures::user(2, 'b', block: 1);
        $this->assertSame([true, true], [InactivityPolicy::matchesStatus($active, 'all'), InactivityPolicy::matchesStatus($blocked, 'all')]);
        $this->assertSame([false, true], [InactivityPolicy::matchesStatus($active, 'blocked'), InactivityPolicy::matchesStatus($blocked, 'blocked')]);
        $this->assertSame([true, false], [InactivityPolicy::matchesStatus($active, 'active'), InactivityPolicy::matchesStatus($blocked, 'active')]);
    }

    public function testDeleteSettings(): void
    {
        $defaults = new Settings([]);
        $this->assertSame([730, false, 'blocked'], [$defaults->deleteDays(), $defaults->deleteIncludeNever(), $defaults->deleteStatus()]);
        $this->assertTrue($defaults->exclusions(Settings::DELETE)->protectAdmins);
        $legacy = new Settings(['excluded_names' => 'servicio', 'manual_inactivity' => ['excluded_names' => 'luis']]);
        $this->assertSame([], $legacy->exclusions(Settings::DELETE)->names, 'deletion never inherits the exclusions of other tools');
        $saved = new Settings(['delete_days' => 400, 'delete_include_never' => 1, 'delete_status' => 'blocked', 'delete_excluded_names' => "a\nB", 'delete_protect_admins' => 0]);
        $this->assertSame([400, true, 'blocked'], [$saved->deleteDays(), $saved->deleteIncludeNever(), $saved->deleteStatus()]);
        $this->assertSame(['a', 'B'], $saved->exclusions(Settings::DELETE)->names);
        $this->assertSame('blocked', (new Settings(['delete_status' => 'nope']))->deleteStatus(), 'unknown values fall back to only blocked');
        $this->assertSame('all', (new Settings(['delete_status' => 'all']))->deleteStatus());
    }

    public function testDeleterRevalidatesProtectsAndLogs(): void
    {
        $accounts = [
            10 => $this->account(10, 'baja'),
            11 => $this->account(11, 'falla', false),
            12 => $this->account(12, 'cambiada'),
            13 => $this->account(13, 'root', true, true),
            14 => $this->account(14, 'servicio'),
        ];
        $factory = new class ($accounts) implements UserFactoryInterface {
            public function __construct(private array $accounts)
            {
            }

            public function loadUserById(int $id)
            {
                return $this->accounts[$id] ?? Fixtures::joomlaUser();
            }

            public function loadUserByUsername(string $username)
            {
                return Fixtures::joomlaUser();
            }
        };
        $logged  = [];
        $deleter = new AccountDeleter($factory, new ActionLogger(function (array $m, string $key) use (&$logged) {
            $logged[] = [$key, $m[0]['id'], $m[0]['title'], $m[0]['name']];
        }));
        $exclusions = new Exclusions(true, ['servicio']);
        $operator   = new Operator(1, true, 'admin');
        $statuses   = [];
        foreach ([10, 11, 12, 13, 14, 99] as $id) {
            $statuses[$id] = $deleter->delete($id, $exclusions, $operator, fn (object $u) => $u->username !== 'cambiada')['status'];
        }
        $this->assertSame([10 => 'deleted', 11 => 'error', 12 => 'skipped', 13 => 'protected', 14 => 'protected', 99 => 'skipped'], $statuses);
        $this->assertSame([1, 1, 0, 0, 0], [$accounts[10]->deletes, $accounts[11]->deletes, $accounts[12]->deletes, $accounts[13]->deletes, $accounts[14]->deletes]);
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_DELETED', 10, 'baja', 'Baja']], $logged, 'only real deletions are logged');
    }

    private function account(int $id, string $username, bool $succeeds = true, bool $super = false): object
    {
        return new class ($id, $username, $succeeds, $super) {
            public int $deletes = 0;
            public int $block = 1;
            public string $name;

            public function __construct(public int $id, public string $username, private bool $succeeds, private bool $super)
            {
                $this->name = ucfirst($username);
            }

            public function delete(): bool
            {
                $this->deletes++;
                return $this->succeeds;
            }

            public function authorise(string $action): bool
            {
                return $this->super && \in_array($action, ['core.admin', 'core.login.admin'], true);
            }

            public function getAuthorisedGroups(): array
            {
                return $this->super ? [1, 8] : [1, 2];
            }
        };
    }
}
