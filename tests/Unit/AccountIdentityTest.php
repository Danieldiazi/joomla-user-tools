<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use PHPUnit\Framework\TestCase;

final class AccountIdentityTest extends TestCase
{
    private function row(int $id, string $username, string $name = 'Ana Pérez', string $email = ''): array
    {
        return ['id' => $id, 'username' => $username, 'name' => $name, 'email' => $email ?: $username . '@example.test', 'blocked' => false];
    }

    public function testUsernameOfOneAndEmailOfAnotherIsNeverResolvedSilently(): void
    {
        $a = $this->row(7, 'ana@example.test', 'Ana A', 'ana.a@example.test');
        $b = $this->row(3, 'anab', 'Ana B', 'ana@example.test');
        foreach ([[$a, $b], [$b, $a]] as $order) {
            $match = AccountIdentity::decide($order);
            self::assertSame(AccountIdentity::AMBIGUOUS, $match['status'], 'whatever order SQL returns');
            self::assertSame(0, $match['id'], 'no ID is chosen');
            self::assertSame([3, 7], array_column($match['candidates'], 'id'), 'every candidate is offered, in a stable order');
        }
    }

    public function testOneMatchAndNoMatch(): void
    {
        $match = AccountIdentity::decide([$this->row(5, 'luis'), $this->row(5, 'luis')]);
        self::assertSame(AccountIdentity::ONE, $match['status'], 'the same account matched twice (username and ID) is one account');
        self::assertSame(5, $match['id']);
        self::assertSame(['status' => AccountIdentity::NONE, 'id' => 0, 'candidates' => []], AccountIdentity::decide([]));
    }

    public function testHomonymsAreToldApartByUsernameEmailAndId(): void
    {
        $one = AccountIdentity::labelParts($this->row(12, 'ana', 'Ana Pérez', 'ana@example.test'));
        $two = AccountIdentity::labelParts($this->row(31, 'ana2', 'Ana Pérez', 'ana2@example.test'));
        self::assertSame(['ana', 'Ana Pérez', 'ana@example.test', 12], $one);
        self::assertSame($one[1], $two[1]);
        self::assertNotSame($one, $two);
        self::assertSame('<b>x</b>', AccountIdentity::labelParts($this->row(1, 'x', '<b>x</b>'))[1], 'values stay plain text: escaping is done where they are shown');
    }

    public function testAChosenIdIsTrustedOnlyWhileTheTextStillNamesIt(): void
    {
        self::assertTrue(AccountIdentity::describes('', 40, '12', 'a@example.test'));
        self::assertTrue(AccountIdentity::describes('12', 40, '12', 'a@example.test'), 'an all-digit username keeps its account');
        self::assertTrue(AccountIdentity::describes('A@Example.test', 40, '12', 'a@example.test'));
        self::assertTrue(AccountIdentity::describes('40', 40, '12', 'a@example.test'));
        self::assertTrue(AccountIdentity::describes('12 — Ana (a@example.test) · ID 40', 40, '12', 'a@example.test'), 'a suggestion label');
        self::assertFalse(AccountIdentity::describes('luis', 40, '12', 'a@example.test'), 'another account typed over a stale hidden ID');
        self::assertFalse(AccountIdentity::describes('x · ID 400', 40, '12', 'a@example.test'));
    }

    public function testSearchNeedsTwoCharactersAndIsBounded(): void
    {
        self::assertNull(AccountIdentity::searchTerm(' a '));
        self::assertSame('an', AccountIdentity::searchTerm(' an '));
        self::assertSame(AccountIdentity::MAX_SEARCH, mb_strlen((string) AccountIdentity::searchTerm(str_repeat('é', 300))));
    }
}
