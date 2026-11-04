<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Parses a roster that creates accounts only. No comparison or removal semantics are allowed.
 * All group IDs come from the server-side group tree, never from a submitted review field.
 */
final class BulkRegistrationList
{
    public const MAX_LINES = 2000;
    public const READY = 'ready';

    /**
     * @param array<int, object> $groups Directory groups, including privilege flags.
     * @param iterable<UserRecord> $users Existing Joomla accounts.
     * @return list<array{line: int, username: string, name: string, email: string, group: int, fingerprint: string, status: string}>
     */
    public static function parse(string $text, array $groups, iterable $users, bool $canAssignGroups): array
    {
        if (\strlen($text) > Comparison::MAX_INPUT_BYTES || !preg_match('//u', $text)) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_LIST_INVALID');
        }
        $existing = [];
        foreach ($users as $user) {
            $existing[Comparison::normalise((string) $user->username)] = true;
        }
        $rows = [];
        $duplicates = [];
        foreach (GroupSync::lines($text) as [$line, $cells]) {
            if (\count($rows) >= self::MAX_LINES) {
                throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_TOO_LONG', [self::MAX_LINES]);
            }
            $login = trim((string) ($cells[0] ?? ''));
            $name = trim((string) ($cells[1] ?? '')) ?: $login;
            $email = trim((string) ($cells[2] ?? ''));
            $group = 0;
            $status = self::READY;
            // Joomla's username/name database lengths; reject controls and markup,
            // rather than silently deleting characters that could alter identity.
            if (\count($cells) > 4 || $login === '' || mb_strlen($login) > 150
                || mb_strlen($name) > 400 || !preg_match('/^[\pL\pN_.@-]+$/u', $login)
                || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                $status = 'invalid';
            }
            if ($status === self::READY && isset($existing[Comparison::normalise($login)])) {
                $status = 'existing';
            }
            $token = trim((string) ($cells[3] ?? ''));
            if ($token !== '' && $status === self::READY) {
                $group = BulkGroupAssignments::resolveGroup($groups, $token);
                if ($group < 0) {
                    $status = 'ambiguous_group';
                } elseif ($group < 1 || !isset($groups[$group])) {
                    $status = 'unknown_group';
                } elseif (!self::safeGroup($groups, $group) || !$canAssignGroups) {
                    $status = 'protected_group';
                }
            }
            $rows[] = [
                'line' => (int) $line, 'username' => $login, 'name' => $name,
                'email' => $email, 'group' => $group,
                'fingerprint' => $group > 0 && isset($groups[$group]) ? self::fingerprint($groups[$group]) : '',
                'status' => $status,
            ];
            if ($login !== '') {
                $duplicates[Comparison::normalise($login)][] = \count($rows) - 1;
            }
        }
        foreach ($duplicates as $positions) {
            if (\count($positions) > 1) {
                foreach ($positions as $position) {
                    $rows[$position]['status'] = 'duplicate';
                }
            }
        }
        if (!$rows) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_LIST_EMPTY');
        }
        return $rows;
    }

    /** Never assign a group that inherits backend or global admin privileges. */
    /** @param array<int, object> $groups */
    public static function safeGroup(array $groups, int $id): bool
    {
        $group = $groups[$id] ?? null;
        return $group !== null && (int) $group->parent_id > 0
            && !$group->backend && !$group->superUser;
    }

    public static function fingerprint(object $group): string
    {
        return hash('sha256', json_encode([
            (int) $group->id, (int) $group->parent_id, (int) $group->lft,
            (int) $group->rgt, (string) $group->title,
            (bool) $group->backend, (bool) $group->superUser,
        ], JSON_THROW_ON_ERROR));
    }

    /** Account and email collisions are checked again at the moment of creation. */
    public static function validEmail(string $email): bool
    {
        return \strlen($email) <= 100 && (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
