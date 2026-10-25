<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    public function testCutoffBounds(): void
    {
        $now = new \DateTimeImmutable('2026-10-25 12:00:00', new \DateTimeZone('Europe/Madrid'));
        $this->assertSame('2025-10-25 11:00:00', InactivityPolicy::cutoff('365', $now)->format('Y-m-d H:i:s'));
        foreach ([0, 36501, 'x', '1.5'] as $bad) {
            try {
                InactivityPolicy::cutoff($bad, $now);
                $this->fail('Accepted ' . var_export($bad, true));
            } catch (UserFacingException $e) {
                $this->assertSame([1, 36500], $e->arguments());
            }
        }
    }

    public function testEligibility(): void
    {
        $cutoff = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $old    = Fixtures::user(1, 'old', lastVisit: '2024-12-31 23:59:59');
        $exact  = Fixtures::user(2, 'exact', lastVisit: '2025-01-01 00:00:00');
        $never  = Fixtures::user(3, 'never', lastVisit: null, registered: '2020-01-01 00:00:00');
        $fresh  = Fixtures::user(4, 'fresh', lastVisit: null, registered: '2025-06-01 00:00:00');
        $bad    = Fixtures::user(5, 'bad', lastVisit: '2024-02-30 10:00:00');
        $this->assertTrue(InactivityPolicy::eligible($old, $cutoff, false));
        $this->assertFalse(InactivityPolicy::eligible($exact, $cutoff, false), 'strictly before the cut-off');
        $this->assertFalse(InactivityPolicy::eligible($never, $cutoff, false));
        $this->assertTrue(InactivityPolicy::eligible($never, $cutoff, true));
        $this->assertFalse(InactivityPolicy::eligible($fresh, $cutoff, true));
        $this->assertFalse(InactivityPolicy::eligible($bad, $cutoff, false));
        $this->assertFalse(InactivityPolicy::eligible(Fixtures::user(6, 'blocked', block: 1, lastVisit: '2000-01-01 00:00:00'), $cutoff, false));
        $this->assertSame('COM_USERTOOLS_REASON_NEVER_VISITED', InactivityPolicy::omissionReason($never, false));
        $this->assertSame('COM_USERTOOLS_REASON_RECENT_REGISTRATION', InactivityPolicy::omissionReason($fresh, true));
        $this->assertSame('COM_USERTOOLS_REASON_INVALID_DATE', InactivityPolicy::omissionReason($bad, false));
        $this->assertSame('COM_USERTOOLS_REASON_RECENT_VISIT', InactivityPolicy::omissionReason($exact, false));
    }

    public function testInactiveSelection(): void
    {
        $this->assertSame([3, 1], InactiveSelection::decode('[3,1,3]', [1, 2, 3]));
        foreach (['{"a":1}', '[4]', '["1"]', 'nope', '[0]'] as $bad) {
            try {
                InactiveSelection::decode($bad, [1, 2, 3]);
                $this->fail('Accepted ' . $bad);
            } catch (UserFacingException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([1], InactiveSelection::eligibleIds([1, 2], [1, 2, 3], [1, 3]));
    }

    public function testSettingsReadCurrentAndLegacyLayouts(): void
    {
        $legacy = new Settings(['protect_admins' => 0, 'excluded_names' => 'servicio', 'excluded_groups' => [4],
            'manual_inactivity' => ['days' => 200, 'include_never' => 1, 'excluded_names' => 'luis', 'protect_admins' => 1]]);
        $this->assertFalse($legacy->exclusions(Settings::COMPARE)->protectAdmins);
        $this->assertSame(['servicio'], $legacy->exclusions(Settings::COMPARE)->names);
        $this->assertSame(['luis'], $legacy->exclusions(Settings::INACTIVE)->names);
        $this->assertSame(200, $legacy->inactiveDays());
        $this->assertTrue($legacy->inactiveIncludeNever());
        $migrated = $legacy->migrated();
        $this->assertSame([4], $migrated['compare_excluded_groups']);
        $this->assertSame(200, $migrated['inactive_days']);

        $current = new Settings($migrated + ['confirm_threshold' => '0', 'creation_mode' => 'password', 'report_retention_days' => -5]);
        $this->assertSame(['servicio'], $current->exclusions(Settings::COMPARE)->names);
        $this->assertSame(0, $current->confirmThreshold());
        $this->assertSame('password', $current->creationMode());
        $this->assertSame(0, $current->retentionDays());
        $defaults = new Settings([]);
        $this->assertSame([10, 'email', 90, 365], [$defaults->confirmThreshold(), $defaults->creationMode(), $defaults->retentionDays(), $defaults->inactiveDays()]);
        $this->assertTrue($defaults->exclusions(Settings::COMPARE)->protectAdmins);
    }
}
