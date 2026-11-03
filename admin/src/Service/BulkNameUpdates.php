<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Builds a reviewable plan for changing only the display name of existing accounts. */
final class BulkNameUpdates
{
    public const UPDATE = 'update';
    public const UNCHANGED = 'unchanged';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const DUPLICATE = 'duplicate';
    public const PROTECTED = 'protected';
    public const INVALID_LINE = 'invalid_line';

    /**
     * Resolve input by username and classify every submitted line without changing any account.
     * The first semicolon is the only delimiter, so proposed names may contain semicolons.
     *
     * @param array<int, UserRecord> $users
     * @return list<array{line: int, username: string, id: int, before: string, after: string, status: string}>
     */
    public static function plan(string $lines, array $users, Operator $operator): array
    {
        $lookups = [];
        foreach ($users as $user) {
            $lookups[$user->username][] = $user;
        }

        $rows = [];
        $seen = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $index => $line) {
            $separator = strpos($line, ';');
            $username  = $separator === false ? '' : trim(substr($line, 0, $separator));
            $after     = $separator === false ? '' : trim(substr($line, $separator + 1));
            $matches   = $username === '' ? [] : ($lookups[$username] ?? []);
            $user      = count($matches) === 1 ? $matches[0] : null;
            $status    = self::UPDATE;

            if ($separator === false || $username === '' || $after === '') {
                $status = self::INVALID_LINE;
            } elseif (count($matches) > 1) {
                $status = self::AMBIGUOUS;
            } elseif ($user === null) {
                $status = self::UNKNOWN;
            } elseif (isset($seen[$username])) {
                $status = self::DUPLICATE;
            } elseif ($user->id === $operator->id || $user->isSuperUser || $user->hasBackendAccess) {
                $status = self::PROTECTED;
            } elseif ($user->name === $after) {
                $status = self::UNCHANGED;
            }

            if ($username !== '') {
                $seen[$username] = true;
            }
            $rows[] = [
                'line' => $index + 1,
                'username' => $username,
                'id' => $user?->id ?? 0,
                'before' => $user?->name ?? '',
                'after' => $after,
                'status' => $status,
            ];
        }

        // Duplicate rows are all rejected; the first line must not win by input order.
        $counts = [];
        foreach ($rows as $row) {
            if ($row['username'] !== '' && $row['id'] > 0) {
                $key = $row['username'];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        foreach ($rows as &$row) {
            if ($row['id'] > 0 && ($counts[$row['username']] ?? 0) > 1) {
                $row['status'] = self::DUPLICATE;
            }
        }
        unset($row);

        return $rows;
    }
}
