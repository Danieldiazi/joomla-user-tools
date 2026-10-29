<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AnonymizeSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use PHPUnit\Framework\TestCase;

final class AnonymizeSelectionTest extends TestCase
{
    /** Public > Registered > (Socios > Junta), Manager. */
    private function tree(): array
    {
        $groups = [];
        foreach ([[1, 0, 1, 10], [2, 1, 2, 7], [10, 2, 3, 6], [11, 10, 4, 5], [6, 1, 8, 9]] as [$id, $parent, $lft, $rgt]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'lft' => $lft, 'rgt' => $rgt];
        }
        return $groups;
    }

    /** @return list<UserRecord> */
    private function users(): array
    {
        return [
            Fixtures::user(1, 'admin', [8], true, true, 0, '2026-10-01 00:00:00'),
            Fixtures::user(2, 'operator', [6], false, true, 0, '2026-10-01 00:00:00'),
            Fixtures::user(3, 'old', [2], false, false, 1, '2019-01-01 00:00:00'),
            Fixtures::user(4, 'socio', [10], false, false, 0, '2026-10-20 00:00:00'),
            Fixtures::user(5, 'junta', [11], false, false, 0, null, '2018-01-01 00:00:00'),
            Fixtures::user(6, 'manager', [6], false, true, 0, '2018-01-01 00:00:00'),
            new UserRecord(7, 'f0e1d2', 'User ID 7', 'UserID7removed@email.invalid', 1, '2018-01-01 00:00:00', '2018-01-01 00:00:00', [2], [1, 2], false, false),
        ];
    }

    private function select(array $criteria, ?Exclusions $exclusions = null): array
    {
        $criteria += ['names' => [], 'cutoff' => null, 'includeNever' => false, 'group' => 0, 'subgroups' => false];
        $result = AnonymizeSelection::select($this->users(), $criteria, $exclusions ?? new Exclusions(), new Operator(2, true, 'operator'), $this->tree());
        return [array_keys($result['candidates']), array_map(fn ($s) => $s['label'] . ':' . $s['reason'], $result['skipped'])];
    }

    public function testListMatchesUsernamesAndEmails(): void
    {
        [$ids, $skipped] = $this->select(['source' => 'list', 'names' => ['OLD', 'socio@example.test', 'nadie', 'admin', 'operator', 'f0e1d2']]);
        $this->assertSame([3, 4], $ids, 'usernames and emails, case-insensitive');
        $this->assertSame(['nadie:COM_USERTOOLS_ANONYMIZE_REASON_NOT_FOUND', 'admin:COM_USERTOOLS_REASON_SUPER_USER', 'operator:COM_USERTOOLS_REASON_OPERATOR',
            'ID 7:COM_USERTOOLS_ANONYMIZE_REASON_DONE'], $skipped, 'unknown names, protected and already anonymized accounts are reported');
    }

    public function testProtectionReasons(): void
    {
        [, $skipped] = $this->select(['source' => 'list', 'names' => ['admin', 'operator', 'manager']]);
        $this->assertSame(['admin:COM_USERTOOLS_REASON_SUPER_USER', 'operator:COM_USERTOOLS_REASON_OPERATOR', 'manager:COM_USERTOOLS_REASON_ADMIN'], $skipped,
            'Super Users, the operator and, by default, accounts with administrator access are never offered');
        [$ids] = $this->select(['source' => 'list', 'names' => ['manager']], new Exclusions(false));
        $this->assertSame([6], $ids, 'a Super User operator can include administrator accounts by turning the exclusion off');
    }

    public function testInactiveAccounts(): void
    {
        $cutoff = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $this->assertSame([3], $this->select(['source' => 'inactive', 'cutoff' => $cutoff])[0], 'blocked or not, by last visit');
        $this->assertSame([3, 5], $this->select(['source' => 'inactive', 'cutoff' => $cutoff, 'includeNever' => true])[0], 'never visited counts from registration');
    }

    public function testGroupWithOrWithoutSubgroups(): void
    {
        $this->assertSame([4], $this->select(['source' => 'group', 'group' => 10])[0]);
        $this->assertSame([4, 5], $this->select(['source' => 'group', 'group' => 10, 'subgroups' => true])[0]);
        $this->assertSame([], $this->select(['source' => 'group', 'group' => 99])[0], 'unknown group: nothing');
    }

    public function testFingerprintChangesWithWhatMatters(): void
    {
        $a = Fixtures::user(3, 'old', [2, 10], false, false, 1, '2019-01-01 00:00:00');
        $this->assertSame(AnonymizeSelection::fingerprint($a), AnonymizeSelection::fingerprint(Fixtures::user(3, 'old', [10, 2], false, false, 1, '2019-01-01 00:00:00')));
        $this->assertNotSame(AnonymizeSelection::fingerprint($a), AnonymizeSelection::fingerprint(Fixtures::user(3, 'old', [2, 10], false, false, 1, '2026-10-29 00:00:00')), 'a new visit');
        $this->assertNotSame(AnonymizeSelection::fingerprint($a), AnonymizeSelection::fingerprint(Fixtures::user(3, 'old', [2], false, false, 1, '2019-01-01 00:00:00')), 'other groups');
        $this->assertTrue(AnonymizeSelection::isAnonymized((object) ['email' => 'UserID7removed@email.invalid']));
        $this->assertFalse(AnonymizeSelection::isAnonymized((object) ['email' => 'user7@example.test']));
    }
}
