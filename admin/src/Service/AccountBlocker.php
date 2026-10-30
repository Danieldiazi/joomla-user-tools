<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;

/**
 * Blocks one account through Joomla's User API (save events run), after revalidating it with a
 * fresh User object. Open sessions are destroyed, as the core Users manager does, and the action
 * is written to the User Actions Log.
 */
final class AccountBlocker
{
    public const BLOCKED   = 'blocked';
    public const SKIPPED   = 'skipped';
    public const PROTECTED = 'protected';
    public const ERROR     = 'error';

    /** @var \Closure(int): void */
    private \Closure $destroySessions;

    /** @param (callable(int): void)|null $destroySessions */
    public function __construct(private UserFactoryInterface $users, private ActionLogger $log, ?callable $destroySessions = null)
    {
        $this->destroySessions = $destroySessions !== null ? \Closure::fromCallable($destroySessions)
            : static function (int $id): void {
                UserHelper::destroyUserSessions($id);
            };
    }

    /**
     * @param  callable(object): bool $stillEligible revalidates the tool's own criteria on the fresh User
     * @return array{status: string, username: string, reason: string}
     */
    public function block(int $id, Exclusions $exclusions, Operator $operator, callable $stillEligible, string $tool): array
    {
        $user = $this->users->loadUserById($id);
        $name = (string) $user->username;
        if (empty($user->id)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => 'COM_USERTOOLS_REASON_MISSING'];
        }
        if ((int) $user->block === 1) {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => 'COM_USERTOOLS_REASON_ALREADY_BLOCKED'];
        }
        $reason = $exclusions->reason($user, $operator);
        if ($reason !== '') {
            return ['status' => self::PROTECTED, 'username' => $name, 'reason' => $reason];
        }
        if (!$stillEligible($user)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => 'COM_USERTOOLS_REASON_NO_LONGER_ELIGIBLE'];
        }
        $user->block = 1;
        try {
            if (!$user->save()) {
                throw new \RuntimeException('save');
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'username' => $name, 'reason' => 'COM_USERTOOLS_ERROR_BLOCK_FAILED'];
        }
        try {
            ($this->destroySessions)($id);
        } catch (\Throwable) {
            // The account is blocked; Joomla refuses new logins even if a session row survives.
        }
        $this->log->blocked($user, $operator, $tool);
        return ['status' => self::BLOCKED, 'username' => $name, 'reason' => ''];
    }
}
