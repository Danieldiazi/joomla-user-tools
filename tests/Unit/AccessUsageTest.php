<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsage;
use PHPUnit\Framework\TestCase;

final class AccessUsageTest extends TestCase
{
    /** @return list<array{id: int, title: string}> */
    private function levels(): array
    {
        return [['id' => 1, 'title' => 'Public'], ['id' => 2, 'title' => 'Registered'], ['id' => 3, 'title' => 'Special'], ['id' => 7, 'title' => 'Socios']];
    }

    /** @return list<array{key: string, label: string, table: string, link: string, counts: array<int, int>}> */
    private function sources(): array
    {
        return [
            ['key' => 'articles', 'label' => 'Articles', 'table' => '#__content', 'link' => 'index.php?option=com_content&filter[access]={access}', 'counts' => [1 => 40, 2 => 5, 7 => 3]],
            ['key' => 'categories:com_content', 'label' => 'Categories: Articles', 'table' => '#__categories', 'link' => 'x={access}', 'counts' => [1 => 6]],
            ['key' => 'contacts', 'label' => 'Contacts', 'table' => '#__contact_details', 'link' => '', 'counts' => []],
            ['key' => 'modules', 'label' => 'Site modules', 'table' => '#__modules', 'link' => 'm={access}', 'counts' => [1 => 12, 3 => 0, 9 => 2]],
            ['key' => 'table:#__attachments', 'label' => 'Table #__attachments', 'table' => '#__attachments', 'link' => '', 'counts' => [2 => 8, 0 => 1]],
        ];
    }

    public function testRowsPerKindAndTotalsPerLevel(): void
    {
        $usage = AccessUsage::build($this->levels(), $this->sources());
        $this->assertSame(['articles', 'categories:com_content', 'modules', 'table:#__attachments'], array_column($usage['rows'], 'key'), 'kinds with no items are left out');
        $rows = array_column($usage['rows'], null, 'key');
        $this->assertSame([1 => 40, 2 => 5, 7 => 3], $rows['articles']['counts']);
        $this->assertSame(48, $rows['articles']['total']);
        $this->assertSame([1 => 12, 9 => 2], $rows['modules']['counts'], 'zero counts are dropped');
        $levels = array_column($usage['levels'], null, 'id');
        $this->assertSame(58, $levels[1]['total']);
        $this->assertSame(13, $levels[2]['total']);
        $this->assertSame(0, $levels[3]['total'], 'Special is unused');
        $this->assertFalse($levels[3]['missing']);
    }

    public function testItemsWithAMissingLevelAreShownApart(): void
    {
        $usage = AccessUsage::build($this->levels(), $this->sources());
        $this->assertSame([1, 2, 3, 7, 0, 9], array_column($usage['levels'], 'id'), 'existing levels in their order, then missing ones by id');
        $missing = array_values(array_filter($usage['levels'], fn ($l) => $l['missing']));
        $this->assertSame([['id' => 0, 'title' => '', 'missing' => true, 'total' => 1], ['id' => 9, 'title' => '', 'missing' => true, 'total' => 2]], $missing);
    }

    public function testLinksAndExport(): void
    {
        $this->assertSame('index.php?option=com_content&filter[access]=7', AccessUsage::link('index.php?option=com_content&filter[access]={access}', 7));
        $this->assertSame('', AccessUsage::link('', 7), 'other extensions have no link');

        $table = AccessUsage::export(AccessUsage::build($this->levels(), $this->sources()), fn (string $key, string $arg = '') => $arg === '' ? $key : "$key($arg)");
        $this->assertSame(['COM_USERTOOLS_USAGE_TYPE', 'COM_USERTOOLS_USAGE_TABLE', 'Public', 'Registered', 'Special', 'Socios',
            'COM_USERTOOLS_USAGE_MISSING_LEVEL(0)', 'COM_USERTOOLS_USAGE_MISSING_LEVEL(9)', 'COM_USERTOOLS_USAGE_TOTAL'], $table['header']);
        $this->assertSame(['Articles', '#__content', 40, 5, 0, 3, 0, 0, 48], $table['rows'][0]);
        $this->assertSame(['Table #__attachments', '#__attachments', 0, 8, 0, 0, 1, 0, 9], $table['rows'][3]);
    }
}
