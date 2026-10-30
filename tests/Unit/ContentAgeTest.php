<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAgeReader;
use PHPUnit\Framework\TestCase;

final class ContentAgeTest extends TestCase
{
    public function testTableByYearNewestFirst(): void
    {
        $age = ContentAge::build([
            ['key' => 'articles', 'label' => 'Articles', 'counts' => [2019 => 4, 2024 => 1, 2012 => 7, 0 => 3]],
            ['key' => 'contacts', 'label' => 'Contacts', 'counts' => [2019 => 2, 2030 => 0]],
        ]);
        $this->assertSame([2024, 2019, 2012], $age['years'], 'newest first; invalid years and empty cells are dropped');
        $this->assertSame([2024 => 1, 2019 => 6, 2012 => 7], $age['totals']);
        $this->assertSame(14, $age['total']);
        $this->assertSame(12, $age['kinds'][0]['total']);
        $this->assertSame([2019 => 2], $age['kinds'][1]['counts']);
        $this->assertSame(['header' => ['COM_USERTOOLS_AGE_YEAR', 'Articles', 'Contacts', 'COM_USERTOOLS_USAGE_TOTAL'],
            'rows' => [[2024, 1, 0, 1], [2019, 4, 2, 6], [2012, 7, 0, 7]]], ContentAge::export($age, fn (string $k) => $k), 'one row per year, as on screen');
        $this->assertSame(['years' => [], 'kinds' => [], 'totals' => [], 'total' => 0], ContentAge::build([]));
    }

    public function testRequestValuesAndBounds(): void
    {
        $this->assertSame(2019, ContentAge::year('2019'));
        $this->assertSame(0, ContentAge::year('19x'));
        $this->assertSame(0, ContentAge::year(1800));
        $this->assertSame(['2019-01-01 00:00:00', '2020-01-01 00:00:00'], ContentAge::bounds(2019), 'start inclusive, end exclusive');
        $this->assertSame(1, ContentAge::page(5, 0));
        $this->assertSame(2, ContentAge::page(7, ContentAge::PAGE_SIZE + 1), 'a page beyond the end shows the last one');
        $this->assertSame(1, ContentAge::page(-3, 500));
    }

    public function testListExportAndLabels(): void
    {
        $items = [
            ['id' => 5, 'title' => '=cmd', 'category' => 'News', 'level' => 'Public', 'access' => 1, 'author' => 'Ana', 'state' => 2, 'created' => '2010-01-01 00:00:00', 'modified' => '2012-01-01 00:00:00'],
            ['id' => 6, 'title' => 'Lost', 'category' => '', 'level' => '', 'access' => 77, 'author' => '', 'state' => 0, 'created' => '2011-01-01 00:00:00', 'modified' => null],
        ];
        $text  = fn (string $key, string $arg = '') => $arg === '' ? $key : "$key($arg)";
        $table = ContentAge::exportItems($items, $text);
        $this->assertCount(8, $table['header']);
        $this->assertSame([5, '=cmd', 'News', 'Public', 'Ana', 'JARCHIVED', '2010-01-01 00:00:00', '2012-01-01 00:00:00'], $table['rows'][0],
            'formula protection is left to CsvExport');
        $this->assertSame('COM_USERTOOLS_USAGE_MISSING_LEVEL(77)', $table['rows'][1][3], 'a missing level is named by its ID');
        $this->assertSame(['JPUBLISHED', 'JUNPUBLISHED', 'JARCHIVED', 'JTRASHED'], array_map([ContentAge::class, 'stateKey'], [1, 0, 2, -2]));
    }

    public function testEveryKindDeclaresItsColumnsAndScreens(): void
    {
        $this->assertSame(['articles', 'categories', 'contacts', 'newsfeeds'], array_keys(ContentAgeReader::KINDS));
        foreach (ContentAgeReader::KINDS as $key => $kind) {
            foreach (['table', 'title', 'state', 'category', 'created', 'modified', 'author', 'label', 'asset', 'item', 'edit'] as $field) {
                $this->assertNotSame('', (string) ($kind[$field] ?? ''), $key . ' needs ' . $field);
            }
            $this->assertStringContainsString('{id}', $kind['item'], $key);
            $this->assertStringContainsString('{id}', $kind['edit'], $key);
        }
        $this->assertSame(['extension', 'com_content'], ContentAgeReader::KINDS['categories']['scope'], 'only article categories, not those of every extension');
        $this->assertFalse(ContentAgeReader::KINDS['categories']['categoryAccess'], 'like Joomla, a category is checked by its own level, not its parent\'s');
        $this->assertArrayNotHasKey('categoryAccess', ContentAgeReader::KINDS['articles'], 'articles keep the category access check');
    }

    public function testItemsCsvCanNameTheParentCategory(): void
    {
        $text = fn (string $key, string $argument = '') => $key;
        $rows = ContentAge::exportItems([['id' => 3, 'title' => 'News', 'category' => '', 'level' => 'Public', 'access' => 1, 'author' => 'A', 'state' => 1,
            'created' => '2012-01-01 00:00:00', 'modified' => '2012-02-01 00:00:00']], $text, 'COM_USERTOOLS_AGE_PARENT_CATEGORY');
        $this->assertSame('COM_USERTOOLS_AGE_PARENT_CATEGORY', $rows['header'][2]);
        $this->assertSame('', $rows['rows'][0][2], 'a top-level category has no parent to show');
    }
}
