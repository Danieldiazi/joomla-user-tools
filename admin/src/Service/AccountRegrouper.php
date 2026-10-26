<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;

/**
 * Replaces the groups of one account through Joomla's User API (save events run), after checking
 * with a fresh User object that its groups are still the reviewed ones. Open sessions are closed so
 * the lost permissions apply at once, and the change is written to the User Actions Log.
 */
final class AccountRegrouper
{
    public const CHANGED = 'changed';
    public const SAVED   = 'saved';
    public const ERROR   = 'error';

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
     * @param  list<int> $before  the groups shown in the review
     * @param  list<int> $after   the groups the account keeps (plus the default group if added)
     * @param  list<string> $removedTitles for the log
     * @return array{status: string, username: string}
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
            return ['status' => self::CHANGED, 'username' => $name];
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
            return ['status' => self::ERROR, 'username' => $name];
        }
        try {
            ($this->destroySessions)($id);
        } catch (\Throwable) {
            // The groups are saved; the next login uses them.
        }
        $this->log->ungrouped($user, $operator, $removedTitles, $addedTitle);
        return ['status' => self::SAVED, 'username' => $name];
    }
}
