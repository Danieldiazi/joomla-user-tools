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
 * Deletes one account through Joomla's User API, after revalidating it with a fresh User object.
 * User::delete() runs onUserBeforeDelete/onUserAfterDelete, so the core user plugin removes the
 * account's sessions, messages and profile data, and other extensions can clean up their own data.
 * The deletion is written to the User Actions Log with the tool that performed it.
 */
final class AccountDeleter
{
    public const DELETED   = 'deleted';
    public const SKIPPED   = 'skipped';
    public const PROTECTED = 'protected';
    public const ERROR     = 'error';

    public function __construct(private UserFactoryInterface $users, private ActionLogger $log)
    {
    }

    /**
     * @param  callable(object): bool $stillEligible revalidates the tool's own criteria on the fresh User
     * @return array{status: string, username: string, name: string, reason: string}
     */
    public function delete(int $id, Exclusions $exclusions, Operator $operator, callable $stillEligible): array
    {
        $user = $this->users->loadUserById($id);
        $name = (string) $user->username;
        $full = (string) $user->name;
        if (empty($user->id)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'name' => $full, 'reason' => 'COM_USERTOOLS_REASON_MISSING'];
        }
        // Exclusions include the operator and Super Users, which are always protected.
        $reason = $exclusions->reason($user, $operator);
        if ($reason !== '') {
            return ['status' => self::PROTECTED, 'username' => $name, 'name' => $full, 'reason' => $reason];
        }
        if (!$stillEligible($user)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'name' => $full, 'reason' => 'COM_USERTOOLS_REASON_NO_LONGER_ELIGIBLE'];
        }
        try {
            if (!$user->delete()) {
                throw new \RuntimeException('delete');
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'username' => $name, 'name' => $full, 'reason' => 'COM_USERTOOLS_ERROR_DELETE_FAILED'];
        }
        $this->log->deleted((object) ['id' => $id, 'username' => $name, 'name' => $full], $operator);
        return ['status' => self::DELETED, 'username' => $name, 'name' => $full, 'reason' => ''];
    }
}
