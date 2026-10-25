<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class ExclusionsTest extends TestCase
{
    public function testSuperUsersAreAlwaysProtected(): void
    {
        $super = Fixtures::user(10, 'root', [8], super: true, backend: true);
        foreach ([new Exclusions(false), new Exclusions(true)] as $exclusions) {
            $this->assertSame(Exclusions::REASON_SUPER_USER, $exclusions->reason($super, new Operator(1, true)));
            $this->assertSame(Exclusions::REASON_SUPER_USER, $exclusions->reason($super, Operator::system()));
        }
    }

    public function testOperatorIsProtectedFirst(): void
    {
        $this->assertSame(Exclusions::REASON_OPERATOR, (new Exclusions(false))->reason(Fixtures::user(7, 'me'), new Operator(7, false)));
    }

    public function testBackendAccounts(): void
    {
        $manager = Fixtures::user(20, 'mgr', [6], backend: true);
        $this->assertSame(Exclusions::REASON_ADMIN, (new Exclusions(true))->reason($manager, new Operator(1, true)));
        $this->assertSame('', (new Exclusions(false))->reason($manager, new Operator(1, true)), 'a Super User may block a manager when administrators are not excluded');
        $this->assertSame(Exclusions::REASON_PRIVILEGED, (new Exclusions(false))->reason($manager, new Operator(2, false)), 'a delegate may never block backend accounts');
    }

    public function testNamesAndGroupsIncludingSubgroups(): void
    {
        $exclusions = Exclusions::fromArray(['protect_admins' => '0', 'excluded_names' => "Servicio\n", 'excluded_groups' => ['3', '']]);
        $op         = new Operator(1, true);
        $this->assertSame(Exclusions::REASON_NAME, $exclusions->reason(Fixtures::user(3, 'SERVICIO'), $op));
        // authorisedGroups contains ancestors: a member of a subgroup of 3 is protected.
        $child = new \Danieldiazi\Component\Usertools\Administrator\Service\UserRecord(4, 'autor', 'Autor', 'a@x.test', 0, null, null, [9], [1, 2, 3, 9], false, false);
        $this->assertSame(Exclusions::REASON_GROUP, $exclusions->reason($child, $op));
        $this->assertSame('', $exclusions->reason(Fixtures::user(5, 'ana'), $op));
    }

    public function testRoundTripWithPrefix(): void
    {
        $original = new Exclusions(false, ['b', 'a'], [5, 3]);
        $copy     = Exclusions::fromArray($original->toArray('compare_'), 'compare_');
        $this->assertFalse($copy->protectAdmins);
        $this->assertSame(['b', 'a'], $copy->names);
        $this->assertSame([3, 5], $copy->groups, 'groups are stored sorted');
        $this->assertSame([3, 5], Exclusions::fromArray(['excluded_groups' => [5, '3', 3]])->groups);
    }

    public function testInvalidGroups(): void
    {
        $this->expectException(UserFacingException::class);
        Exclusions::fromArray(['excluded_groups' => ['abc']]);
    }
}
