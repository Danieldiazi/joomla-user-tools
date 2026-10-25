<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountBlocker;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\MassBlockGuard;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\PasswordGenerator;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class SafetyTest extends TestCase
{
    public function testMassBlockGuard(): void
    {
        $guard = new MassBlockGuard(20, 10);
        $this->assertFalse($guard->exceeded(200, 1000), 'exactly 20 % is allowed');
        $this->assertTrue($guard->exceeded(201, 1000));
        $this->assertFalse($guard->exceeded(9, 10), 'below the minimum number of candidates');
        $this->assertTrue($guard->exceeded(10, 30));
        $this->assertFalse((new MassBlockGuard(0, 1))->exceeded(1000, 1000), '0 disables it');
        $this->assertFalse((new MassBlockGuard(100, 1))->exceeded(1000, 1000), '100 disables it');
        $this->assertSame(20.1, MassBlockGuard::share(201, 1000));
    }

    public function testGuardParamsAreClamped(): void
    {
        $this->assertSame([20, 10], [MassBlockGuard::fromParams([])->percent, MassBlockGuard::fromParams([])->minimum]);
        $guard = MassBlockGuard::fromParams(['guard_percent' => '250', 'guard_minimum' => '-3']);
        $this->assertSame([100, 1], [$guard->percent, $guard->minimum]);
        $this->assertSame(20, MassBlockGuard::fromParams(['guard_percent' => 'x'])->percent);
    }

    public function testPasswordsFollowThePolicy(): void
    {
        $policy = ['minimum_length' => 40, 'minimum_integers' => 6, 'minimum_symbols' => 5, 'minimum_uppercase' => 7, 'minimum_lowercase' => 3];
        $seen   = [];
        for ($i = 0; $i < 200; $i++) {
            $password = (new PasswordGenerator($policy))->generate();
            $this->assertSame(40, strlen($password));
            $this->assertGreaterThanOrEqual(6, preg_match_all('/[0-9]/', $password));
            $this->assertGreaterThanOrEqual(5, preg_match_all('/[^A-Za-z0-9]/', $password));
            $this->assertGreaterThanOrEqual(7, preg_match_all('/[A-Z]/', $password));
            $this->assertGreaterThanOrEqual(3, preg_match_all('/[a-z]/', $password));
            $seen[$password] = true;
        }
        $this->assertCount(200, $seen);
        $this->assertSame(24, strlen((new PasswordGenerator([]))->generate()), 'never shorter than 24');
        $this->assertDoesNotMatchRegularExpression('/aA1!$/', (new PasswordGenerator([]))->generate() . (new PasswordGenerator([]))->generate());
    }

    public function testBlockerRevalidatesClosesSessionsAndLogs(): void
    {
        $accounts = [
            10 => Fixtures::joomlaUser(10, 'baja'),
            11 => Fixtures::joomlaUser(11, 'ya', 1),
            12 => Fixtures::joomlaUser(12, 'falla', 0, false),
            13 => Fixtures::joomlaUser(13, 'cambiada'),
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
        $logged    = [];
        $destroyed = [];
        $blocker   = new AccountBlocker($factory, new ActionLogger(function (array $m, string $key) use (&$logged) {
            $logged[] = [$key, $m[0]['title'], $m[0]['tool']];
        }), function (int $id) use (&$destroyed) {
            $destroyed[] = $id;
        });
        $operator = new Operator(1, true, 'admin');
        $eligible = fn (object $u) => $u->username !== 'cambiada';
        $results  = [];
        foreach ([10, 11, 12, 13, 99] as $id) {
            $results[$id] = $blocker->block($id, new Exclusions(), $operator, $eligible, '')['status'];
        }
        $this->assertSame([10 => 'blocked', 11 => 'skipped', 12 => 'error', 13 => 'skipped', 99 => 'skipped'], $results);
        $this->assertSame(1, $accounts[10]->block);
        $this->assertSame(0, $accounts[13]->block);
        $this->assertSame([10], $destroyed, 'sessions are closed only for blocked accounts');
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_BLOCKED', 'baja', '']], $logged);
        $task = $blocker->block(10, new Exclusions(), Operator::system('Nightly'), fn () => true, '');
        $this->assertSame('skipped', $task['status'], 'already blocked by the first call');
    }
}
