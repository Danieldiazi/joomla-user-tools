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
 * Saves the reviewed membership change of one account of a synchronised branch, through Joomla's
 * User API, after checking with a fresh User that its username and groups are still the reviewed ones
 * and that it has no administrator access. Only the reviewed managed groups are added or removed; an
 * account left without groups gets Joomla's default group. Each change is logged and the account's
 * sessions are closed and verified, reported apart from the save so that only the closing is retried.
 */
final class AccountGroupSyncer
{
    public const SAVED = 'saved';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    /** @var \Closure(int): string */
    private \Closure $closeSessions;

    /** @param (callable(int): string)|null $closeSessions injectable for tests */
    public function __construct(private UserFactoryInterface $users, private ActionLogger $log, ?callable $closeSessions = null)
    {
        $this->closeSessions = $closeSessions !== null ? \Closure::fromCallable($closeSessions)
            : static fn (int $id): string => (new AccountRegrouper($users, $log))->closeSessions($id);
    }

    /**
     * @param  list<int>         $before   the account's groups in the review
     * @param  list<int>         $add      managed groups to add
     * @param  list<int>         $remove   managed groups to remove
     * @param  array<int, true>  $managed  the branch's managed groups now
     * @param  array<int, string> $titles  group titles for the log
     * @return array{status: string, sessions: string, default: bool}
     */
    public function sync(int $id, string $username, array $before, array $add, array $remove, array $managed, array $titles, Operator $operator): array
    {
        $refused = ['status' => self::CHANGED, 'sessions' => '', 'default' => false];
        foreach (array_merge($add, $remove) as $group) {
            if (!isset($managed[$group])) {
                return $refused;
            }
        }
        if ((!$add && !$remove) || $id === $operator->id) {
            return $refused;
        }
        $user = $this->users->loadUserById($id);
        $current = array_values(array_unique(array_map('intval', (array) $user->groups)));
        $expected = array_values(array_unique(array_map('intval', $before)));
        sort($current);
        sort($expected);
        if (empty($user->id) || (string) $user->username !== $username || $current !== $expected
            || $user->authorise('core.admin') || $user->authorise('core.login.admin')) {
            return $refused;
        }
        $after = array_values(array_diff(array_unique(array_merge($current, $add)), $remove));
        $default = !$after;
        if ($default) {
            $after = [GroupSync::DEFAULT_GROUP];
        }
        $user->groups = array_combine($after, $after);
        try {
            if (!$user->save(true)) {
                return ['status' => self::ERROR, 'sessions' => '', 'default' => false];
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'sessions' => '', 'default' => false];
        }
        foreach ($add as $group) {
            $this->log->groupAdded($user, $operator, $group);
        }
        if ($remove) {
            $this->log->ungrouped($user, $operator, array_map(static fn (int $g): string => $titles[$g] ?? ('#' . $g), $remove),
                $default ? ($titles[GroupSync::DEFAULT_GROUP] ?? ('#' . GroupSync::DEFAULT_GROUP)) : '');
        }
        // Saved and logged: a session problem is reported on its own and never repeats the save.
        try {
            $sessions = ($this->closeSessions)($id);
        } catch (\Throwable) {
            $sessions = AccountRegrouper::SESSIONS_OPEN;
        }
        return ['status' => self::SAVED, 'sessions' => $sessions, 'default' => $default];
    }
}
