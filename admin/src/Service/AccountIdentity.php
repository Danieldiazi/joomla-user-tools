<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Unambiguous account identity for the single-account tools. A typed login (username, email or
 * ID) resolves to one account only when exactly one account matches; when the username of one
 * account is the email of another, or an all-digit text is both a username and an ID, the answer
 * is "ambiguous" with every candidate, never the first row SQL happens to return. Pure logic.
 */
final class AccountIdentity
{
    public const NONE      = 'none';
    public const ONE       = 'one';
    public const AMBIGUOUS = 'ambiguous';

    /** Shortest search text that queries accounts, and the most suggestions returned. */
    public const MIN_SEARCH  = 2;
    public const MAX_SEARCH  = 100;
    public const SUGGESTIONS = 10;

    /**
     * @param  list<array{id: int, username: string, name: string, email: string, blocked: bool}> $matches
     * @return array{status: string, id: int, candidates: list<array{id: int, username: string, name: string, email: string, blocked: bool}>}
     */
    public static function decide(array $matches): array
    {
        $unique = [];
        foreach ($matches as $row) {
            $unique[(int) $row['id']] = $row;
        }
        ksort($unique);
        $candidates = array_values($unique);
        return match (\count($candidates)) {
            0       => ['status' => self::NONE, 'id' => 0, 'candidates' => []],
            1       => ['status' => self::ONE, 'id' => (int) $candidates[0]['id'], 'candidates' => $candidates],
            default => ['status' => self::AMBIGUOUS, 'id' => 0, 'candidates' => $candidates],
        };
    }

    /**
     * Whether the visible text still names the chosen account: empty, its username or email, its ID,
     * or a suggestion label, which always ends with "ID <id>".
     */
    public static function describes(string $text, int $id, string $username, string $email): bool
    {
        $text = trim($text);
        return $text === '' || $text === (string) $id || mb_strtolower($text) === mb_strtolower($username)
            || mb_strtolower($text) === mb_strtolower($email) || preg_match('/\bID ' . $id . '$/uD', $text) === 1;
    }

    /** The search text, or null when it is too short to query personal data. */
    public static function searchTerm(string $text): ?string
    {
        $text = trim($text);
        if (mb_strlen($text) < self::MIN_SEARCH) {
            return null;
        }
        return mb_substr($text, 0, self::MAX_SEARCH);
    }

    /**
     * The values of the visible label "username — Name (email) · ID 245", in that order. The format
     * itself is a language string; the values are plain text and are escaped where they are shown.
     *
     * @param  array{id: int, username: string, name: string, email: string} $row
     * @return array{0: string, 1: string, 2: string, 3: int}
     */
    public static function labelParts(array $row): array
    {
        return [(string) $row['username'], (string) $row['name'], (string) $row['email'], (int) $row['id']];
    }
}
