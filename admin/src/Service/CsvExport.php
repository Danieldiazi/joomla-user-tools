<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** CSV for spreadsheets (UTF-8 BOM, semicolons) with protection against formula injection. */
final class CsvExport
{
    /**
     * @param list<string>            $header
     * @param iterable<list<scalar|null>> $rows
     */
    public static function build(array $header, iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new \RuntimeException('Cannot create the CSV buffer.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map([self::class, 'cell'], $header), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'cell'], $row), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /**
     * Characters a spreadsheet may skip, trim or normalise before deciding whether a cell is a formula:
     * whitespace and line breaks, control characters, Unicode spaces and invisible format characters
     * (zero-width spaces, joiners, bidi marks, BOM).
     */
    private const IGNORABLE = '[\s\p{Z}\p{Cc}\p{Cf}]';

    /** Characters that start a formula in Excel, LibreOffice Calc or Google Sheets, with their full-width forms. */
    private const TRIGGERS = '[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]';

    /**
     * Neutralises cells that a spreadsheet could run as a formula, such as a username "=HYPERLINK(...)",
     * also when ignorable characters come first (" =1+1" is evaluated by LibreOffice when it trims spaces).
     * The cell is prefixed with an apostrophe, which keeps the whole original value visible as text; nothing
     * is removed. Plain numbers ("-5", "+34", "1.5") are left as they are: they cannot be formulas.
     * Cells starting with a tab or a line break are prefixed too. Invalid UTF-8 is always prefixed.
     * Separators, quotes and line breaks inside a cell are quoted by fputcsv(), so they never add columns or rows.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '' || preg_match('/^[+-]?(\d+([.,]\d+)?|[.,]\d+)$/', $value) === 1) {
            return $value;
        }
        $risky = preg_match('/^(' . self::IGNORABLE . '*' . self::TRIGGERS . '|[\t\r\n])/u', $value);
        return $risky === 0 ? $value : "'" . $value;
    }
}
