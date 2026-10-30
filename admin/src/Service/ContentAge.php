<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Content by age: how many items of each kind were last modified in each year, newest year first.
 * The year comes from the last modification date (UTC, as stored by Joomla), or the creation date
 * when there is none. Pure logic; ContentAgeReader reads the counts and the items.
 */
final class ContentAge
{
    /** Items per page in the list of one year. */
    public const PAGE_SIZE = 50;

    /** Years accepted in requests. */
    public const MIN_YEAR = 1900;
    public const MAX_YEAR = 9999;

    /**
     * @param  list<array{key: string, label: string, counts: array<int, int>}> $kinds counts per year
     * @return array{years: list<int>, kinds: list<array{key: string, label: string, counts: array<int, int>, total: int}>, totals: array<int, int>, total: int}
     */
    public static function build(array $kinds): array
    {
        $totals = [];
        $rows   = [];
        foreach ($kinds as $kind) {
            $counts = [];
            foreach ($kind['counts'] as $year => $count) {
                $year  = (int) $year;
                $count = (int) $count;
                if ($count <= 0 || $year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                    continue;
                }
                $counts[$year] = ($counts[$year] ?? 0) + $count;
                $totals[$year] = ($totals[$year] ?? 0) + $count;
            }
            krsort($counts);
            $rows[] = ['key' => $kind['key'], 'label' => $kind['label'], 'counts' => $counts, 'total' => array_sum($counts)];
        }
        krsort($totals);
        return ['years' => array_keys($totals), 'kinds' => $rows, 'totals' => $totals, 'total' => array_sum($totals)];
    }

    /** A valid year from a request, or 0. */
    public static function year(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        return $year !== false && $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $year : 0;
    }

    /**
     * UTC bounds of a year, for an index-friendly query: start inclusive, end exclusive.
     *
     * @return array{0: string, 1: string}
     */
    public static function bounds(int $year): array
    {
        return [\sprintf('%04d-01-01 00:00:00', $year), \sprintf('%04d-01-01 00:00:00', $year + 1)];
    }

    /** Last page for a number of items (at least 1), and a requested page kept within it. */
    public static function page(int $requested, int $total): int
    {
        return max(1, min($requested, (int) ceil(max(1, $total) / self::PAGE_SIZE)));
    }

    /**
     * The table as rows for a CSV file: one row per year, one column per kind, as on screen.
     *
     * @param  array{years: list<int>, kinds: list<array<string, mixed>>, totals: array<int, int>, total: int} $age see build()
     * @param  callable(string): string $text
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public static function export(array $age, callable $text): array
    {
        $header = [$text('COM_USERTOOLS_AGE_YEAR')];
        foreach ($age['kinds'] as $kind) {
            $header[] = (string) $kind['label'];
        }
        $header[] = $text('COM_USERTOOLS_USAGE_TOTAL');
        $rows     = [];
        foreach ($age['years'] as $year) {
            $line = [$year];
            foreach ($age['kinds'] as $kind) {
                $line[] = (int) ($kind['counts'][$year] ?? 0);
            }
            $line[] = (int) $age['totals'][$year];
            $rows[] = $line;
        }
        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * The items of one year as rows for a CSV file.
     *
     * @param  list<array{id: int, title: string, category: string, level: string, access: int, author: string, state: int, created: ?string, modified: ?string}> $items
     * @param  callable(string, string=): string $text
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public static function exportItems(array $items, callable $text): array
    {
        $header = [$text('COM_USERTOOLS_AGE_ID'), $text('COM_USERTOOLS_AGE_TITLE_COL'), $text('COM_USERTOOLS_AGE_CATEGORY'), $text('COM_USERTOOLS_ACCESS_LEVEL'),
            $text('COM_USERTOOLS_AGE_AUTHOR'), $text('COM_USERTOOLS_AGE_STATE'), $text('COM_USERTOOLS_AGE_CREATED'), $text('COM_USERTOOLS_AGE_MODIFIED')];
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [$item['id'], $item['title'], $item['category'], self::levelName($item, $text), $item['author'], $text(self::stateKey($item['state'])),
                (string) $item['created'], (string) $item['modified']];
        }
        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Level title, or "ID n (missing)" when the level does not exist.
     *
     * @param array{level: string, access: int} $item
     * @param callable(string, string=): string $text
     */
    public static function levelName(array $item, callable $text): string
    {
        return $item['level'] !== '' ? $item['level'] : $text('COM_USERTOOLS_USAGE_MISSING_LEVEL', (string) $item['access']);
    }

    /** Joomla's own language key for a publishing state. */
    public static function stateKey(int $state): string
    {
        return match ($state) {
            1       => 'JPUBLISHED',
            0       => 'JUNPUBLISHED',
            2       => 'JARCHIVED',
            default => 'JTRASHED',
        };
    }
}
