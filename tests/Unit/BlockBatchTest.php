<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountUnblocker;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\BlockBatch;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class BlockBatchTest extends TestCase
{
    private const VISIT = '2024-05-01 10:00:00';

    public function testReferencesAreStrict(): void
    {
        $this->assertSame(['kind' => 'task', 'id' => '20261030120000-0123456789abcdef01234567'], BlockBatch::parse('task:20261030120000-0123456789abcdef01234567'));
        $this->assertSame('manual:20261030120000-0123456789abcdef01234567', BlockBatch::reference('manual', '20261030120000-0123456789abcdef01234567'));
        foreach (['', 'task:../x', 'other:20261030120000-0123456789abcdef01234567', 'task:20261030120000-0123456789ABCDEF01234567'] as $bad) {
            try {
                BlockBatch::parse($bad);
                $this->fail('Accepted ' . $bad);
            } catch (UserFacingException $e) {
                $this->assertSame('COM_USERTOOLS_UNBLOCK_ERROR_BATCH', $e->key());
            }
        }
    }

    public function testOnlyRealBlocksAreUndoable(): void
    {
        $batch = ['users' => [
            ['id' => 5, 'username' => 'ana', 'last_visit' => self::VISIT, 'action' => 'blocked'],
            ['id' => 6, 'username' => 'luis', 'last_visit' => null, 'action' => 'protected'],
            ['id' => 7, 'username' => 'eva', 'last_visit' => '0000-00-00 00:00:00', 'action' => 'blocked'],
            ['id' => 5, 'username' => 'ana', 'last_visit' => self::VISIT, 'action' => 'blocked'],
        ]];
        $this->assertSame([
            ['id' => 5, 'username' => 'ana', 'last_visit' => self::VISIT],
            ['id' => 7, 'username' => 'eva', 'last_visit' => null],
        ], BlockBatch::blockedRows($batch));
        $this->assertSame([], BlockBatch::blockedRows($batch + ['dry_run' => true]), 'a dry run blocked nothing');
    }

    public function testRunsThatBlockedNothingDoNotHideOlderBatches(): void
    {
        // 25 newest runs blocked nothing; the older ones did. Pages of 10, newest first.
        $all = [];
        for ($i = 0; $i < 40; $i++) {
            $all[] = ['id' => 'r' . $i, 'blocked' => $i < 25 ? 0 : 1];
        }
        $all[30]['dry_run'] = true;
        $reads = 0;
        $page  = function (int $n) use ($all, &$reads): array {
            $reads++;
            return ['items' => \array_slice($all, ($n - 1) * 10, 10), 'total' => \count($all)];
        };
        $found = BlockBatch::withBlocks($page, 10, 3);
        $this->assertSame(['r25', 'r26', 'r27'], array_column($found, 'id'));
        $this->assertSame(3, $reads, 'stops once enough batches are found');
        $this->assertCount(14, BlockBatch::withBlocks($page, 10, 20), 'all older batches with blocks, without the dry run');
        $this->assertSame([], BlockBatch::withBlocks(fn (int $n): array => ['items' => [], 'total' => 0], 10, 20));
    }

    public function testOnlyStillBlockedUnchangedAccountsAreOffered(): void
    {
        $row      = ['id' => 5, 'username' => 'ana', 'last_visit' => self::VISIT];
        $super    = new Operator(1, true, 'root');
        $delegate = new Operator(2, false, 'delegate');
        $this->assertSame('', BlockBatch::reason(Fixtures::user(5, 'ana', block: 1, lastVisit: self::VISIT), $row, $super));
        $this->assertSame(BlockBatch::REASON_NOT_BLOCKED, BlockBatch::reason(Fixtures::user(5, 'ana', lastVisit: self::VISIT), $row, $super));
        $this->assertSame(BlockBatch::REASON_CHANGED, BlockBatch::reason(Fixtures::user(5, 'ana.renamed', block: 1, lastVisit: self::VISIT), $row, $super));
        $this->assertSame(BlockBatch::REASON_CHANGED, BlockBatch::reason(Fixtures::user(5, 'ana', block: 1, lastVisit: '2026-10-30 08:00:00'), $row, $super));
        $this->assertSame(BlockBatch::REASON_MISSING, BlockBatch::reason(Fixtures::user(9, 'ana', block: 1, lastVisit: self::VISIT), $row, $super));
        $this->assertSame(Exclusions::REASON_OPERATOR, BlockBatch::reason(Fixtures::user(5, 'ana', block: 1, lastVisit: self::VISIT), $row, new Operator(5, true)));
        $backend = Fixtures::user(5, 'ana', [6], backend: true, block: 1, lastVisit: self::VISIT);
        $this->assertSame(BlockBatch::REASON_PRIVILEGED, BlockBatch::reason($backend, $row, $delegate), 'rule 3: only a Super User changes administrators');
        $this->assertSame('', BlockBatch::reason($backend, $row, $super));
        $anonymized = new \Danieldiazi\Component\Usertools\Administrator\Service\UserRecord(5, 'ana', 'User ID 5', 'UserID5removed@email.invalid', 1, self::VISIT, null, [2], [1, 2], false, false);
        $this->assertSame(BlockBatch::REASON_ANONYMIZED, BlockBatch::reason($anonymized, $row, $super));
        $never = ['id' => 7, 'username' => 'eva', 'last_visit' => null];
        $this->assertSame('', BlockBatch::reason(Fixtures::user(7, 'eva', block: 1, lastVisit: null), $never, $super), 'never visited stays never visited');
    }

    public function testUnblockerRevalidatesWithAFreshUserAndLogs(): void
    {
        $accounts = [
            10 => Fixtures::joomlaUser(10, 'back', 1),
            11 => Fixtures::joomlaUser(11, 'already', 0),
            12 => Fixtures::joomlaUser(12, 'fails', 1, false),
            13 => Fixtures::joomlaUser(13, 'renamed', 1),
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
        $unblocker = new AccountUnblocker($factory, new ActionLogger(function (array $m, string $key) use (&$logged) {
            $logged[] = [$key, $m[0]['title'], $m[0]['batch']];
        }));
        $visit    = '2000-01-01 00:00:00';
        $operator = new Operator(1, true, 'admin');
        $status   = [];
        foreach ([10 => 'back', 11 => 'already', 12 => 'fails', 13 => 'before-rename', 99 => 'gone'] as $id => $name) {
            $status[$id] = $unblocker->unblock(['id' => $id, 'username' => $name, 'last_visit' => $visit], $operator, 'run X')['status'];
        }
        $this->assertSame([10 => 'unblocked', 11 => 'skipped', 12 => 'error', 13 => 'skipped', 99 => 'skipped'], $status);
        $this->assertSame(0, $accounts[10]->block);
        $this->assertSame(1, $accounts[13]->block, 'a changed account is never unblocked');
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_UNBLOCKED', 'back', 'run X']], $logged, 'only real unblocks are logged');
    }
}
