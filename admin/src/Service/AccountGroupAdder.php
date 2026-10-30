<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;

/** Saves only additions, through the User API, after fresh checks on the account. */
final class AccountGroupAdder
{
    public const ADDED = 'added';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    /** @var \Closure(int): string */
    private \Closure $closeSessions;

    /** @param (callable(int): string)|null $closeSessions Injectable for testing failed/unknown session closure. */
    public function __construct(private UserFactoryInterface $users, private ActionLogger $log, ?callable $closeSessions = null)
    {
        $this->closeSessions = $closeSessions !== null ? \Closure::fromCallable($closeSessions)
            : static fn (int $id): string => (new AccountRegrouper($users, $log))->closeSessions($id);
    }

    /**
     * @param list<int> $before
     * @param list<int> $targets
     * @return array{status: string, sessions: string}
     */
    public function add(int $id, string $username, array $before, array $targets, Operator $operator, bool $safeTarget): array
    {
        if (!$safeTarget || !$targets || $id === $operator->id) {
            return ['status' => self::CHANGED, 'sessions' => ''];
        }
        $user = $this->users->loadUserById($id);
        $current = array_values(array_unique(array_map('intval', (array) $user->groups)));
        $expected = array_values(array_unique(array_map('intval', $before)));
        sort($current);
        sort($expected);
        if (empty($user->id) || (string) $user->username !== $username || $current !== $expected
            || $user->authorise('core.admin') || $user->authorise('core.login.admin') || array_intersect($targets, $current)) {
            return ['status' => self::CHANGED, 'sessions' => ''];
        }
        $all = array_values(array_unique(array_merge($current, $targets)));
        $user->groups = array_combine($all, $all);
        try {
            if (!$user->save(true)) {
                return ['status' => self::ERROR, 'sessions' => ''];
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'sessions' => ''];
        }
        foreach ($targets as $group) {
            $this->log->groupAdded($user, $operator, $group);
        }
        // Memberships are already saved and audited: report a session problem separately
        // and never repeat the membership write to retry the session closure.
        try {
            $sessions = ($this->closeSessions)($id);
        } catch (\Throwable) {
            $sessions = AccountRegrouper::SESSIONS_OPEN;
        }
        return ['status' => self::ADDED, 'sessions' => $sessions];
    }
}
