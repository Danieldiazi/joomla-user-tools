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
    public const INVALID_NAME = 'invalid_name';

    /** Length of #__users.name: longer values could be truncated by a non-strict database. */
    private const MAX_NAME_LENGTH = 400;

    /**
     * Resolve input by username and classify every submitted line without changing any account.
     * The first semicolon is the only delimiter, so proposed names may contain semicolons.
     * Usernames match case-insensitively, like the other bulk tools: on a database where two
     * accounts differ only by case the row is ambiguous, never applied to the first match.
     * Trailing line breaks are not entries; a blank line between entries is an invalid row.
     *
     * @param array<int, UserRecord> $users
     * @return list<array{line: int, username: string, id: int, before: string, after: string, status: string}>
     */
    public static function plan(string $lines, array $users, Operator $operator): array
    {
        $lookups = [];
        foreach ($users as $user) {
            $lookups[mb_strtolower($user->username)][] = $user;
        }

        $parsed = [];
        $repeats = [];
        foreach (preg_split('/\R/u', rtrim($lines)) ?: [] as $index => $line) {
            $separator = strpos($line, ';');
            $username  = $separator === false ? '' : trim(substr($line, 0, $separator));
            $after     = $separator === false ? '' : trim(substr($line, $separator + 1));
            $key       = mb_strtolower($username);
            if ($username !== '') {
                $repeats[$key] = ($repeats[$key] ?? 0) + 1;
            }
            $parsed[] = [$index + 1, $username, $key, $after, $separator !== false];
        }

        $rows = [];
        foreach ($parsed as [$number, $username, $key, $after, $hasSeparator]) {
            $matches = $username === '' ? [] : ($lookups[$key] ?? []);
            $user    = count($matches) === 1 ? $matches[0] : null;

            if (!$hasSeparator || $username === '' || $after === '') {
                $status = self::INVALID_LINE;
            } elseif (mb_strlen($after) > self::MAX_NAME_LENGTH || preg_match('/\p{Cc}/u', $after) === 1) {
                $status = self::INVALID_NAME;
            } elseif (($repeats[$key] ?? 0) > 1) {
                // Every repeated row is rejected: the first line must not win by input order.
                $status = self::DUPLICATE;
            } elseif (count($matches) > 1) {
                $status = self::AMBIGUOUS;
            } elseif ($user === null) {
                $status = self::UNKNOWN;
            } elseif ($user->id === $operator->id || $user->isSuperUser || $user->hasBackendAccess) {
                $status = self::PROTECTED;
            } elseif ($user->name === $after) {
                $status = self::UNCHANGED;
            } else {
                $status = self::UPDATE;
            }

            $rows[] = [
                'line' => $number,
                'username' => $user->username ?? $username,
                'id' => $user->id ?? 0,
                'before' => $user->name ?? '',
                'after' => $after,
                'status' => $status,
            ];
        }

        return $rows;
    }
}
