<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Replaces the groups of one account through Joomla's User API (save events run), after checking
 * with a fresh User object that its groups are still the reviewed ones. Open sessions are closed so
 * the lost permissions apply at once, and the change is written to the User Actions Log.
 *
 * Unlike a blocked account, an account whose groups changed can keep working with its old
 * permissions in a session that survives, so the closing is verified and reported.
 */
final class AccountRegrouper
{
    public const CHANGED = 'changed';
    public const SAVED   = 'saved';
    public const ERROR   = 'error';

    /** Sessions after the change: none left, some still open, or not verifiable (session metadata off). */
    public const SESSIONS_CLOSED  = 'closed';
    public const SESSIONS_OPEN    = 'open';
    public const SESSIONS_UNKNOWN = 'unknown';

    /** @var \Closure(int): void */
    private \Closure $destroySessions;

    /** @var \Closure(int): ?int */
    private \Closure $openSessions;

    /**
     * @param (callable(int): void)|null $destroySessions
     * @param (callable(int): ?int)|null $openSessions sessions left for the account, null when they cannot be counted
     */
    public function __construct(private UserFactoryInterface $users, private ActionLogger $log, ?callable $destroySessions = null, ?callable $openSessions = null)
    {
        $this->destroySessions = $destroySessions !== null ? \Closure::fromCallable($destroySessions)
            : static function (int $id): void {
                UserHelper::destroyUserSessions($id);
            };
        $this->openSessions = $openSessions !== null ? \Closure::fromCallable($openSessions)
            : static function (int $id): ?int {
                if (!Factory::getApplication()->get('session_metadata', true)) {
                    return null;
                }
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                return (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__session'))
                    ->where($db->quoteName('userid') . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();
            };
    }

    /** Closes the account's sessions and checks that none is left. */
    public function closeSessions(int $id): string
    {
        try {
            ($this->destroySessions)($id);
        } catch (\Throwable) {
            // Checked below: what matters is whether a session survived.
        }
        try {
            $left = ($this->openSessions)($id);
        } catch (\Throwable) {
            return self::SESSIONS_OPEN;
        }
        return $left === null ? self::SESSIONS_UNKNOWN : ($left > 0 ? self::SESSIONS_OPEN : self::SESSIONS_CLOSED);
    }

    /**
     * @param  list<int> $before  the groups shown in the review
     * @param  list<int> $after   the groups the account keeps (plus the default group if added)
     * @param  list<string> $removedTitles for the log
     * @return array{status: string, username: string, sessions: string}
     */
    public function regroup(int $id, array $before, array $after, Operator $operator, array $removedTitles, string $addedTitle): array
    {
        $user = $this->users->loadUserById($id);
        $name = (string) $user->username;
        $current = array_map('intval', array_values((array) $user->groups));
        sort($current);
        $expected = array_map('intval', $before);
        sort($expected);
        if (empty($user->id) || $current !== $expected) {
            return ['status' => self::CHANGED, 'username' => $name, 'sessions' => ''];
        }
        $groups = [];
        foreach ($after as $groupId) {
            $groups[(int) $groupId] = (int) $groupId;
        }
        $user->groups = $groups;
        try {
            if (!$groups || !$user->save(true)) {
                throw new \RuntimeException('save');
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'username' => $name, 'sessions' => ''];
        }
        // The groups are saved and logged even if a session survives; the result reports it.
        $this->log->ungrouped($user, $operator, $removedTitles, $addedTitle);
        return ['status' => self::SAVED, 'username' => $name, 'sessions' => $this->closeSessions($id)];
    }
}
