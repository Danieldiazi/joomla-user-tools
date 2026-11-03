<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;

/** Saves a reviewed display-name change through Joomla's User API after fresh safety checks. */
final class AccountNameUpdater
{
    public const UPDATED = 'updated';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    public function __construct(private UserFactoryInterface $users, private ActionLogger $log)
    {
    }

    /**
     * Change only the reviewed name. A stale account or protected target is skipped.
     *
     * @return array{status: string}
     */
    public function update(int $id, string $username, string $before, string $after, Operator $operator): array
    {
        if ($id < 1 || $id === $operator->id || $after === '') {
            return ['status' => self::CHANGED];
        }
        $user = $this->users->loadUserById($id);
        if (empty($user->id) || (string) $user->username !== $username || (string) $user->name !== $before
            || $user->authorise('core.admin') || $user->authorise('core.login.admin')) {
            return ['status' => self::CHANGED];
        }

        $user->name = $after;
        try {
            if (!$user->save(true)) {
                return ['status' => self::ERROR];
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR];
        }

        $this->log->nameChanged($user, $operator);
        return ['status' => self::UPDATED];
    }
}
