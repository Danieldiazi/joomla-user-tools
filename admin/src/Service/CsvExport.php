<?php

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

    /** Usernames such as "=HYPERLINK(...)" must not run as formulas when the file is opened. */
    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
    }
}
