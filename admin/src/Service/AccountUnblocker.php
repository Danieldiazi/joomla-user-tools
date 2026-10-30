<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;

/**
 * Unblocks one account of a block batch through Joomla's User API (save events run), after
 * revalidating it with a fresh User object: an account that changed since the batch is skipped,
 * never replaced. The action is written to the User Actions Log.
 */
final class AccountUnblocker
{
    public const UNBLOCKED = 'unblocked';
    public const SKIPPED   = 'skipped';
    public const ERROR     = 'error';

    public function __construct(private UserFactoryInterface $users, private ActionLogger $log)
    {
    }

    /**
     * @param  array{id: int, username: string, last_visit: ?string} $row what the batch recorded
     * @return array{status: string, username: string, reason: string}
     */
    public function unblock(array $row, Operator $operator, string $source): array
    {
        $user   = $this->users->loadUserById((int) $row['id']);
        $name   = (string) ($user->username ?: $row['username']);
        $reason = BlockBatch::reason($user, $row, $operator);
        if ($reason !== '') {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => $reason];
        }
        $user->block = 0;
        try {
            if (!$user->save()) {
                throw new \RuntimeException('save');
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'username' => $name, 'reason' => 'COM_USERTOOLS_UNBLOCK_ERROR_FAILED'];
        }
        $this->log->unblocked($user, $operator, $source);
        return ['status' => self::UNBLOCKED, 'username' => $name, 'reason' => ''];
    }
}
