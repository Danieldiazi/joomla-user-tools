<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * A batch of blocks that can be undone: the accounts a scheduled task run blocked (its report) or
 * the accounts a manual review blocked (a batch saved when blocking). Pure logic: it decides which
 * accounts may be unblocked, on a bulk UserRecord for the review and on a fresh Joomla User right
 * before saving. Only accounts still blocked and unchanged since the batch are offered.
 */
final class BlockBatch
{
    public const KIND_TASK   = 'task';
    public const KIND_MANUAL = 'manual';

    public const REASON_NOT_BLOCKED = 'COM_USERTOOLS_UNBLOCK_REASON_NOT_BLOCKED';
    public const REASON_CHANGED     = 'COM_USERTOOLS_UNBLOCK_REASON_CHANGED';
    public const REASON_ANONYMIZED  = 'COM_USERTOOLS_UNBLOCK_REASON_ANONYMIZED';
    public const REASON_PRIVILEGED  = 'COM_USERTOOLS_UNBLOCK_REASON_PRIVILEGED';
    public const REASON_MISSING     = 'COM_USERTOOLS_REASON_MISSING';

    private const ID = '/^(task|manual):([0-9]{14}-[a-f0-9]{24})$/D';

    /**
     * @return array{kind: string, id: string}
     * @throws UserFacingException for anything but a well-formed reference
     */
    public static function parse(string $reference): array
    {
        if (!preg_match(self::ID, $reference, $m)) {
            throw new UserFacingException('COM_USERTOOLS_UNBLOCK_ERROR_BATCH');
        }
        return ['kind' => $m[1], 'id' => $m[2]];
    }

    public static function reference(string $kind, string $id): string
    {
        return $kind . ':' . $id;
    }

    /**
     * Accounts the batch blocked for real. Dry runs and runs stopped by the safety limit block nothing.
     *
     * @param  array<string, mixed> $batch a task report or a manual batch
     * @return list<array{id: int, username: string, last_visit: ?string}>
     */
    public static function blockedRows(array $batch): array
    {
        if (!empty($batch['dry_run'])) {
            return [];
        }
        $rows = [];
        $seen = [];
        foreach ((array) ($batch['users'] ?? []) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (($row['action'] ?? '') !== 'blocked' || $id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[]    = ['id' => $id, 'username' => (string) ($row['username'] ?? ''), 'last_visit' => self::visit($row['last_visit'] ?? null)];
        }
        return $rows;
    }

    /**
     * Up to $limit batches that blocked at least one account, newest first. Reads page after page,
     * so runs that blocked nothing never push older undoable batches out of the list.
     *
     * @param  callable(int): array{items: list<array<string, mixed>>, total: int} $page reads one page (1-based)
     * @return list<array<string, mixed>>
     */
    public static function withBlocks(callable $page, int $size, int $limit): array
    {
        $found = [];
        for ($number = 1; \count($found) < $limit; $number++) {
            $result = $page($number);
            foreach ($result['items'] as $batch) {
                if ((int) ($batch['blocked'] ?? 0) > 0 && empty($batch['dry_run'])) {
                    $found[] = $batch;
                    if (\count($found) === $limit) {
                        break;
                    }
                }
            }
            if ($number * $size >= (int) $result['total'] || !$result['items']) {
                break;
            }
        }
        return $found;
    }

    /**
     * The row a manual batch stores for a blocked account, the same shape the task report uses.
     *
     * @return array{id: int, username: string, last_visit: ?string, action: string, reason: string}
     */
    public static function row(UserRecord $user, string $reason): array
    {
        return ['id' => $user->id, 'username' => $user->username, 'last_visit' => $user->lastvisitDate, 'action' => 'blocked', 'reason' => $reason];
    }

    /**
     * Why an account of the batch cannot be unblocked, or an empty string if it can. Works on a
     * UserRecord and on a fresh Joomla User alike.
     *
     * @param array{id: int, username: string, last_visit: ?string} $row what the batch recorded
     */
    public static function reason(object $user, array $row, Operator $operator): string
    {
        if (empty($user->id) || (int) $user->id !== (int) $row['id']) {
            return self::REASON_MISSING;
        }
        if ($operator->id > 0 && (int) $user->id === $operator->id) {
            return Exclusions::REASON_OPERATOR;
        }
        if ((int) $user->block !== 1) {
            return self::REASON_NOT_BLOCKED;
        }
        if (AnonymizeSelection::isAnonymized($user)) {
            return self::REASON_ANONYMIZED;
        }
        // A blocked account cannot sign in, so its last visit only changes if someone edits it.
        if ((string) $user->username !== $row['username'] || self::visit($user->lastvisitDate ?? null) !== self::visit($row['last_visit'])) {
            return self::REASON_CHANGED;
        }
        if (!$operator->isSuperUser && $user->authorise('core.login.admin')) {
            return self::REASON_PRIVILEGED;
        }
        return '';
    }

    private static function visit(mixed $value): ?string
    {
        $value = $value === null ? '' : substr((string) $value, 0, 19);
        return $value === '' || str_starts_with($value, '0000-00-00') ? null : $value;
    }
}
