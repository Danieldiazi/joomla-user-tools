<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class ComparisonTest extends TestCase
{
    private function users(): array
    {
        return [
            (object) ['id' => 1, 'username' => 'admin', 'block' => 0],
            (object) ['id' => 2, 'username' => 'ana', 'block' => 0],
            (object) ['id' => 3, 'username' => 'baja', 'block' => 0],
            (object) ['id' => 4, 'username' => 'servicio', 'block' => 0],
            (object) ['id' => 5, 'username' => 'bloqueado', 'block' => 1],
            (object) ['id' => 6, 'username' => 'OTRO', 'block' => 0],
        ];
    }

    public function testParseCleansInput(): void
    {
        $this->assertSame(['ana', '0', 'Ñandú'], Comparison::parse("\xEF\xBB\xBFana\r\n ANA \n\n0\nÑandú\nñANDÚ"));
    }

    public function testParseRejectsInvalidUtf8AndHugeInput(): void
    {
        foreach (["\xFF", str_repeat('a', Comparison::MAX_INPUT_BYTES + 1)] as $input) {
            try {
                Comparison::parse($input);
                $this->fail('Accepted invalid input');
            } catch (UserFacingException $e) {
                $this->assertSame('COM_USERTOOLS_ERROR_LIST_INVALID', $e->key());
            }
        }
    }

    public function testBlocksOnlyMissingUnprotectedAccounts(): void
    {
        $r = Comparison::compare($this->users(), ['ana', 'otro', 'desconocido'], ['servicio'], [1]);
        $this->assertSame([3], array_column($r['candidates'], 'id'));
        $this->assertSame(2, $r['matched'], 'otro matches OTRO');
        $this->assertSame(['desconocido'], $r['unknown']);
        $this->assertSame(2, $r['protected']);
        $this->assertSame(1, $r['blocked']);
    }

    public function testNoMatchesStillProposesUnprotectedAccounts(): void
    {
        $r = Comparison::compare($this->users(), ['pepito'], [], [1]);
        $this->assertSame(0, $r['matched']);
        $this->assertSame([2, 3, 4, 6], array_column($r['candidates'], 'id'));
        $this->assertSame(['pepito'], $r['unknown']);
    }

    public function testEmptyRosterNeverBlocks(): void
    {
        $this->assertSame([], Comparison::compare($this->users(), [], [], [])['candidates']);
    }

    public function testLargeRoster(): void
    {
        $names = array_map(fn ($i) => 'usuario' . $i, range(1, 5000));
        $users = array_map(fn ($i) => (object) ['id' => $i, 'username' => 'USUARIO' . $i, 'block' => 0], range(1, 5001));
        $r     = Comparison::compare($users, $names, [], []);
        $this->assertSame(5000, $r['matched']);
        $this->assertSame([5001], array_column($r['candidates'], 'id'));
    }
}
