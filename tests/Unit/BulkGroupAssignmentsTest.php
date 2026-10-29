<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\BulkGroupAssignments;
use PHPUnit\Framework\TestCase;

final class BulkGroupAssignmentsTest extends TestCase
{
    public function testPairInputDeduplicatesCaseInsensitively(): void
    {
        $this->assertSame([
            ['login' => 'ANA', 'group' => 'Autores'],
            ['login' => 'luis', 'group' => 'Editores'],
        ], BulkGroupAssignments::parse("ANA;Autores\nana;autores\nluis;Editores", 'pairs'));
    }

    public function testOneGroupInput(): void
    {
        $this->assertSame([
            ['login' => 'ana', 'group' => 'Autores'],
            ['login' => 'luis', 'group' => 'Autores'],
        ], BulkGroupAssignments::parse("ana\nluis", 'single', 'Autores'));
    }

    public function testMalformedRowsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BulkGroupAssignments::parse('ana;Autores;extra', 'pairs');
    }

    public function testAdditivePlanNeverRemovesMemberships(): void
    {
        $this->assertSame(['add' => [7], 'result' => [2, 5, 7]], BulkGroupAssignments::plan([5, 2], [7, 2, 7]));
    }
}
