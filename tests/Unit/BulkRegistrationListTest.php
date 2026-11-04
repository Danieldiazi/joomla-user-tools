<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\BulkRegistrationList as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use PHPUnit\Framework\TestCase;

final class BulkRegistrationListTest extends TestCase
{
    private function fixtureGroups(): array
    {
        return [
            1 => (object) ['id' => 1, 'parent_id' => 0, 'lft' => 1, 'rgt' => 10,
                'title' => 'Public', 'backend' => false, 'superUser' => false],
            2 => (object) ['id' => 2, 'parent_id' => 1, 'lft' => 2, 'rgt' => 7,
                'title' => 'Registered', 'backend' => false, 'superUser' => false],
            12 => (object) ['id' => 12, 'parent_id' => 2, 'lft' => 3, 'rgt' => 4,
                'title' => 'Contributors', 'backend' => false, 'superUser' => false],
            8 => (object) ['id' => 8, 'parent_id' => 1, 'lft' => 8, 'rgt' => 9,
                'title' => 'Super Users', 'backend' => true, 'superUser' => true],
        ];
    }

    private function users(): array
    {
        return [new UserRecord(44, 'existing', 'Existing', 'existing@example.test', 0,
            null, null, [2], [2], false, false)];
    }

    public function testOptionalFieldsAndDefaultName(): void
    {
        $rows = Plan::parse("jperez\nmlopez;María López\nagarcia;;;12\nrfernandez;Rosa Fernández;rosa@example.test;12",
            $this->fixtureGroups(), $this->users(), true);
        $this->assertSame(['jperez', 'María López', 'agarcia', 'Rosa Fernández'], array_column($rows, 'name'));
        $this->assertSame(['', '', '', 'rosa@example.test'], array_column($rows, 'email'));
        $this->assertSame([0, 0, 12, 12], array_column($rows, 'group'));
        $this->assertSame(array_fill(0, 4, Plan::READY), array_column($rows, 'status'));
    }

    public function testDuplicateExistingInvalidAndPrivilegedAccountsCannotBeCreated(): void
    {
        $rows = Plan::parse("existing\nJPEREZ\njperez\nbad;too;many;columns;here\nadmin;;;8\nmissing;;;999\nstaff;;;12",
            $this->fixtureGroups(), $this->users(), false);
        $this->assertSame(['existing', 'duplicate', 'duplicate', 'invalid',
            'protected_group', 'unknown_group', 'protected_group'], array_column($rows, 'status'));
    }

    public function testEmailIsRequiredOnlyAtCreationAndMustBeValid(): void
    {
        $this->assertFalse(Plan::validEmail(''));
        $this->assertFalse(Plan::validEmail('not-an-email'));
        $this->assertTrue(Plan::validEmail('jperez@example.test'));
    }

    public function testChangesInGroupFingerprintAreDetected(): void
    {
        $groups = $this->fixtureGroups();
        $before = Plan::fingerprint($groups[12]);
        $groups[12]->backend = true;
        $this->assertNotSame($before, Plan::fingerprint($groups[12]));
        $this->assertFalse(Plan::safeGroup($groups, 12));
    }

    public function testEmptyAndOversizedInputsAreRejected(): void
    {
        $this->expectException(UserFacingException::class);
        Plan::parse(" \n ", $this->fixtureGroups(), [], true);
    }
}
