<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function formulas(): array
    {
        return [
            'equals'               => ['=HYPERLINK("http://x";"c")'],
            'plus'                 => ['+1+1'],
            'minus'                => ['-1+cmd'],
            'at'                   => ['@SUM(1;1)'],
            'space'                => [' =1+1'],
            'several spaces'       => ['   +A1'],
            'tab'                  => ["\t=1+1"],
            'carriage return'      => ["\r=1+1"],
            'line feed'            => ["\n=1+1"],
            'vertical tab'         => ["\x0B=1+1"],
            'control character'    => ["\x01=1+1"],
            'no-break space'       => ["\u{00A0}=1+1"],
            'ideographic space'    => ["\u{3000}=1+1"],
            'zero-width space'     => ["\u{200B}=1+1"],
            'byte order mark'      => ["\u{FEFF}=1+1"],
            'bidi mark'            => ["\u{200E}@x"],
            'mixed invisibles'     => [" \u{200B}\u{00A0}\t-2+3"],
            'full-width equals'    => ["\u{FF1D}1+1"],
            'full-width plus'      => ["\u{FF0B}1"],
            'full-width at'        => ["\u{FF20}x"],
            'leading tab only'     => ["\tb"],
            'leading line break'   => ["\nb"],
        ];
    }

    #[DataProvider('formulas')]
    public function testCellsThatMayRunAsFormulasArePrefixedAndKeptWhole(string $value): void
    {
        self::assertSame("'" . $value, CsvExport::cell($value), 'nothing is removed: the original follows the apostrophe');
    }

    /** @return array<string, array{mixed}> */
    public static function harmless(): array
    {
        return [
            'username'          => ['ana.garcia'],
            'email'             => ['ana@example.test'],
            'apostrophe'        => ["O'Neill"],
            'accents'           => ['José Ñúñez'],
            'multibyte'         => ['李小龍 · 🙂'],
            'formula later'     => ['ana=1+1'],
            'hyphenated'        => ['Jean-Luc'],
            'negative number'   => ['-5'],
            'signed number'     => ['+34'],
            'decimal comma'     => ['-1,5'],
            'decimal point'     => ['0.25'],
            'empty'             => [''],
            'trailing space'    => ['ana '],
            'inner line break'  => ["linea 1\n=2"],
        ];
    }

    #[DataProvider('harmless')]
    public function testLegitimateValuesAreLeftUnchanged(string $value): void
    {
        self::assertSame($value, CsvExport::cell($value));
    }

    public function testNullAndScalarsBecomeText(): void
    {
        self::assertSame('', CsvExport::cell(null));
        self::assertSame('42', CsvExport::cell(42));
        self::assertSame('-3', CsvExport::cell(-3));
        self::assertSame('1', CsvExport::cell(true));
    }

    public function testInvalidUtf8IsAlwaysPrefixed(): void
    {
        self::assertSame("'\xC3=1", CsvExport::cell("\xC3=1"));
    }

    public function testSeparatorsQuotesAndLineBreaksNeverAddColumnsOrRows(): void
    {
        $rows = [
            ['a;b', 'say "hi"', "two\nlines"],
            ['";=1+1;"', "x\r\n=2;y", '=3'],
            [null, '', 'ok'],
        ];
        $csv = CsvExport::build(['one', 'two', 'three'], $rows);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://temp', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $parsed = [];
        while (($line = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $parsed[] = $line;
        }
        fclose($stream);
        self::assertCount(4, $parsed, 'header plus three rows');
        foreach ($parsed as $line) {
            self::assertCount(3, $line, 'every row keeps three columns');
        }
        self::assertSame(['a;b', 'say "hi"', "two\nlines"], $parsed[1]);
        self::assertSame(['";=1+1;"', "x\r\n=2;y", "'=3"], $parsed[2]);
        self::assertSame(['', '', 'ok'], $parsed[3]);
    }
}
