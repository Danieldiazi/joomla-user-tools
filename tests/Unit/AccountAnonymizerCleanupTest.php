<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountAnonymizer;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\TestCase;

final class AccountAnonymizerCleanupTest extends TestCase
{
    private function runScenario(bool $already, bool $fail, int $deleted = 1, array $remaining = ['profile' => 0, 'notes' => 0, 'fields' => 0], array $plugins = ['user', 'consents']): array
    {
        $account = new class ($already) {
            public int $id = 42;
            public string $username = 'original';
            public string $name = 'Original';
            public string $email = 'original@example.test';
            public int $block = 0;
            public function __construct(bool $already)
            {
                if ($already) {
                    $this->anonymize();
                }
            }
            public function anonymize(): void
            {
                $this->username = 'random42';
                $this->name = 'User ID 42';
                $this->email = 'UserID42removed@email.invalid';
                $this->block = 1;
            }
            public function authorise(string $permission): bool { return false; }
            public function getAuthorisedGroups(): array { return [1, 2]; }
        };
        $factory = new class ($account) implements UserFactoryInterface {
            public function __construct(private object $account) {}
            public function loadUserById(int $id) { return $this->account; }
            public function loadUserByUsername(string $username) { return $this->account; }
        };
        $logged = [];
        $removed = 0;
        $cleaned = 0;
        $anonymizer = new AccountAnonymizer(
            $factory,
            null,
            new ActionLogger(function (array $messages, string $key) use (&$logged): void {
                $logged[] = [$key, $messages[0]['id']];
            }),
            function (object $user) use (&$removed, $plugins): array {
                $removed++;
                $user->anonymize();
                return [true, '', $plugins];
            },
            function (int $id) use ($fail, $deleted, $remaining, &$cleaned): array {
                $cleaned++;
                if ($fail) {
                    throw new \RuntimeException('test failure');
                }
                return ['deleted' => $deleted, 'remaining' => $remaining];
            },
        );
        $outcome = $anonymizer->anonymize(42, new Exclusions(), new Operator(1, true), static fn (): bool => true);
        return [$outcome, $logged, $removed, $cleaned];
    }

    public function testPartialCleanupStillRecordsSuccessfulAnonymization(): void
    {
        [$result, $logged, $removed, $cleaned] = $this->runScenario(false, true);
        $this->assertSame(AccountAnonymizer::PARTIAL, $result['status']);
        $this->assertSame('COM_USERTOOLS_ANONYMIZE_CLEANUP_INCOMPLETE', $result['reason']);
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_ANONYMIZED', 42]], $logged);
        $this->assertSame([1, 1], [$removed, $cleaned]);
    }

    public function testRetryOnlyCleansAndDoesNotRepeatPrivacyEventsOrAudit(): void
    {
        [$result, $logged, $removed, $cleaned] = $this->runScenario(true, false);
        $this->assertSame(AccountAnonymizer::ANONYMIZED, $result['status']);
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_CLEANUP_COMPLETED', 42]], $logged);
        $this->assertSame([0, 1], [$removed, $cleaned]);
    }
    public function testRetryOfAlreadyCleanAccountDoesNotLogCleanup(): void
    {
        [$result, $logged, $removed, $cleaned] = $this->runScenario(true, false, 0);
        $this->assertSame(AccountAnonymizer::ANONYMIZED, $result['status']);
        $this->assertSame([], $logged, 'a no-op retry is successful but must not claim cleanup took place');
        $this->assertSame([0, 1], [$removed, $cleaned]);
    }


    public function testIdentityAndCleanupAreReportedApartAndPluginsAreNeverCalledVerified(): void
    {
        [$result] = $this->runScenario(false, false);
        $this->assertSame(AccountAnonymizer::ANONYMIZED, $result['status']);
        $this->assertSame(AccountAnonymizer::VERIFIED, $result['identity']);
        $this->assertSame(['profile' => 'verified', 'notes' => 'verified', 'fields' => 'verified'], $result['cleanup']);
        $this->assertSame(['user', 'consents'], $result['plugins'], 'the plugins that ran are listed by name only');
        $this->assertArrayNotHasKey('email', $result, 'no old personal data in the outcome');
    }

    public function testCleanupIsVerifiedOnlyWhenNothingIsLeftAfterDeleting(): void
    {
        [$result, $logged] = $this->runScenario(false, false, 3, ['profile' => 0, 'notes' => 2, 'fields' => 0]);
        $this->assertSame(AccountAnonymizer::PARTIAL, $result['status'], 'deleting without an error is not enough');
        $this->assertSame(['profile' => 'verified', 'notes' => 'pending', 'fields' => 'verified'], $result['cleanup']);
        $this->assertSame(AccountAnonymizer::VERIFIED, $result['identity'], 'the identity is still reported as done');
        $this->assertSame([['COM_USERTOOLS_ACTION_LOG_ANONYMIZED', 42]], $logged);
        [$missing] = $this->runScenario(false, false, 1, ['profile' => 0]);
        $this->assertSame(['profile' => 'verified', 'notes' => 'pending', 'fields' => 'pending'], $missing['cleanup'], 'a source that was not checked is pending');
        [$failed] = $this->runScenario(false, true);
        $this->assertSame(['profile' => 'pending', 'notes' => 'pending', 'fields' => 'pending'], $failed['cleanup']);
    }

    public function testARetryDoesNotRunThePrivacyPluginsAgain(): void
    {
        [$result] = $this->runScenario(true, false);
        $this->assertSame([], $result['plugins']);
    }
}
