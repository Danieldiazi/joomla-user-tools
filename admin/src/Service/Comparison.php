<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Case-insensitive comparison of a roster of usernames with the site accounts. */
final class Comparison
{
    public const MAX_INPUT_BYTES = 2000000;

    public static function normalise(string $name): string
    {
        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Index by normalised name, preserving the first original spelling for display and account creation.
     *
     * @param  iterable<string|int> $names
     * @return array<string, string>
     */
    public static function index(iterable $names): array
    {
        $indexed = [];
        foreach ($names as $name) {
            $key = self::normalise((string) $name);
            if (!isset($indexed[$key])) {
                $indexed[$key] = (string) $name;
            }
        }
        return $indexed;
    }

    /** @param list<string> $names */
    public static function contains(array $names, string $name): bool
    {
        return isset(self::index($names)[self::normalise($name)]);
    }

    /** @return list<string> */
    public static function parse(string $text): array
    {
        if (\strlen($text) > self::MAX_INPUT_BYTES || !preg_match('//u', $text)) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_LIST_INVALID');
        }
        $text  = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split('/\R/u', $text) ?: [];
        $names = array_filter(array_map('trim', $lines), fn ($s) => $s !== '');
        return array_values(self::index($names));
    }

    /**
     * @param  iterable<object> $users        objects with id, username and block
     * @param  list<string>     $names        roster of usernames that keep access
     * @param  list<string>     $excluded     usernames excluded from blocking
     * @param  list<int>        $protectedIds accounts protected by other rules
     * @return array{candidates: list<object>, matched: int, protected: int, blocked: int, unknown: list<string>}
     */
    public static function compare(iterable $users, array $names, array $excluded, array $protectedIds): array
    {
        $valid      = self::index($names);
        $exceptions = self::index($excluded);
        $protected  = array_fill_keys($protectedIds, true);
        $remaining  = $valid;
        $result     = ['candidates' => [], 'matched' => 0, 'protected' => 0, 'blocked' => 0, 'unknown' => []];
        foreach ($users as $user) {
            $key = self::normalise((string) $user->username);
            if (isset($valid[$key])) {
                $result['matched']++;
                unset($remaining[$key]);
            }
            if (isset($exceptions[$key]) || isset($protected[(int) $user->id])) {
                $result['protected']++;
            } elseif ((int) $user->block === 1) {
                $result['blocked']++;
            } elseif (!isset($valid[$key])) {
                $result['candidates'][] = $user;
            }
        }
        $result['unknown'] = array_values($remaining);
        // An empty list is never a valid roster; without matches the review warns instead.
        if (!$valid) {
            $result['candidates'] = [];
        }
        return $result;
    }
}
