<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Parse input for additive group membership imports.
 * This class does not change any Joomla data. Unknown accounts/groups are resolved during review.
 */
final class BulkGroupAssignments
{
    /**
     * @return list<array{login: string, group: string}>
     */
    public static function parse(string $input, string $mode, string $singleGroup = ''): array
    {
        if (!\in_array($mode, ['pairs', 'single'], true)) {
            throw new \InvalidArgumentException('Unknown input mode.');
        }
        $singleGroup = trim($singleGroup);
        $pairs = [];
        foreach (preg_split('/\r\n|\r|\n/', $input) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($mode === 'single') {
                if (str_contains($line, ';')) {
                    throw new \InvalidArgumentException('Single-group mode expects one username per line.');
                }
                $login = $line;
                $group = $singleGroup;
            } else {
                $parts = explode(';', $line);
                if (\count($parts) !== 2) {
                    throw new \InvalidArgumentException('Each row must use usuario;grupo.');
                }
                [$login, $group] = array_map('trim', $parts);
            }
            if ($login === '' || $group === '') {
                throw new \InvalidArgumentException('Username and group are required.');
            }
            $key = mb_strtolower($login) . "\0" . mb_strtolower($group);
            $pairs[$key] = ['login' => $login, 'group' => $group];
            if (\count($pairs) > 5000) {
                throw new \InvalidArgumentException('Maximum 5000 distinct assignments.');
            }
        }
        if (!$pairs) {
            throw new \InvalidArgumentException('At least one assignment is required.');
        }
        return array_values($pairs);
    }

    /**
     * Additive only: existing groups are preserved, and duplicate assignments are ignored.
     * @param list<int> $current
     * @param list<int> $requested
     * @return array{add: list<int>, result: list<int>}
     */
    public static function plan(array $current, array $requested): array
    {
        $before = array_values(array_unique(array_map('intval', $current)));
        $after = array_values(array_unique(array_merge($before, array_map('intval', $requested))));
        sort($after);
        $add = array_values(array_diff($after, $before));
        sort($add);
        return ['add' => $add, 'result' => $after];
    }
}
