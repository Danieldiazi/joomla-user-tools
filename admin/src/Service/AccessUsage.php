<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Which content uses each viewing access level: per kind of item (articles, categories of a component,
 * menu items…), how many items have each level. The screen shows one row per level and one column per
 * kind. Pure logic; AccessUsageReader reads the counts.
 */
final class AccessUsage
{
    /**
     * @param  list<array{id: int, title: string}>                                                  $levels  in display order
     * @param  list<array{key: string, label: string, table: string, link: string, counts: array<int, int>}> $sources counts per level id
     * @return array{
     *     levels: list<array{id: int, title: string, missing: bool, total: int}>,
     *     rows: list<array{key: string, label: string, table: string, link: string, counts: array<int, int>, total: int}>
     * }
     */
    public static function build(array $levels, array $sources): array
    {
        $columns = [];
        foreach ($levels as $level) {
            $columns[(int) $level['id']] = ['id' => (int) $level['id'], 'title' => (string) $level['title'], 'missing' => false, 'total' => 0];
        }
        $rows = [];
        foreach ($sources as $source) {
            $counts = [];
            foreach ($source['counts'] as $levelId => $count) {
                if ((int) $count <= 0) {
                    continue;
                }
                $levelId          = (int) $levelId;
                $counts[$levelId] = ($counts[$levelId] ?? 0) + (int) $count;
                // Items pointing to a level that does not exist (deleted level, imported data): nobody but Super Users sees them.
                $columns[$levelId] ??= ['id' => $levelId, 'title' => '', 'missing' => true, 'total' => 0];
                $columns[$levelId]['total'] += (int) $count;
            }
            if (!$counts) {
                continue;
            }
            $rows[] = ['key' => $source['key'], 'label' => $source['label'], 'table' => $source['table'], 'link' => $source['link'],
                'counts' => $counts, 'total' => array_sum($counts)];
        }
        // Existing levels keep their order; missing ones follow, by id.
        $known   = array_values(array_filter($columns, fn ($c) => !$c['missing']));
        $missing = array_values(array_filter($columns, fn ($c) => $c['missing']));
        usort($missing, fn ($a, $b) => $a['id'] <=> $b['id']);
        return ['levels' => array_merge($known, $missing), 'rows' => $rows];
    }

    /**
     * Link to a Joomla list filtered by one level: the source link has an "{access}" placeholder.
     */
    public static function link(string $link, int $levelId): string
    {
        return $link === '' ? '' : str_replace('{access}', (string) $levelId, $link);
    }

    /**
     * The usage as table rows for a CSV file: one row per level, one column per kind of item, as on screen.
     *
     * @param  array{levels: list<array<string, mixed>>, rows: list<array<string, mixed>>} $usage see build()
     * @param  callable(string, string=): string $text translates a language key, with an optional argument
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public static function export(array $usage, callable $text): array
    {
        $header = [$text('COM_USERTOOLS_USAGE_LEVEL_ID'), $text('COM_USERTOOLS_ACCESS_LEVEL')];
        foreach ($usage['rows'] as $row) {
            $header[] = (string) $row['label'];
        }
        $header[] = $text('COM_USERTOOLS_USAGE_TOTAL');
        $rows     = [];
        foreach ($usage['levels'] as $level) {
            $line = [(int) $level['id'], $level['missing'] ? $text('COM_USERTOOLS_USAGE_MISSING_LEVEL', (string) $level['id']) : (string) $level['title']];
            foreach ($usage['rows'] as $row) {
                $line[] = (int) ($row['counts'][$level['id']] ?? 0);
            }
            $line[] = (int) $level['total'];
            $rows[] = $line;
        }
        return ['header' => $header, 'rows' => $rows];
    }
}
