<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;

/** Saves only additions, through the User API, after fresh checks on the account. */
final class AccountGroupAdder
{
    public const ADDED = 'added';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    public function __construct(private UserFactoryInterface $users, private ActionLogger $log)
    {
    }

    /**
     * @param list<int> $before
     * @return string
     */
    public function add(int $id, string $username, array $before, int $group, Operator $operator, bool $safeTarget): string
    {
        if (!$safeTarget || $group < 1 || $id === $operator->id) {
            return self::CHANGED;
        }
        $user = $this->users->loadUserById($id);
        $current = array_values(array_unique(array_map('intval', (array) ($user->groups ?? []))));
        $expected = array_values(array_unique(array_map('intval', $before)));
        sort($current);
        sort($expected);
        if (empty($user->id) || (string) $user->username !== $username || $current !== $expected
            || $user->authorise('core.admin') || $user->authorise('core.login.admin') || in_array($group, $current, true)) {
            return self::CHANGED;
        }
        $user->groups = array_fill_keys(array_merge($current, [$group]), true);
        // Joomla's User::save expects group IDs as values, not booleans.
        $user->groups = array_combine(array_keys($user->groups), array_keys($user->groups));
        try {
            if (!$user->save(true)) {
                return self::ERROR;
            }
        } catch (\Throwable) {
            return self::ERROR;
        }
        $this->log->groupAdded($user, $operator, $group);
        try {
            UserHelper::destroyUserSessions($id);
        } catch (\Throwable) {
            // The account was saved and logged; do not retry the write.
        }
        return self::ADDED;
    }
}
