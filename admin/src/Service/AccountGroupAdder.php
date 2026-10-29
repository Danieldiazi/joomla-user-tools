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
     * @param list<int> $targets
     * @return string
     */
    public function add(int $id, string $username, array $before, array $targets, Operator $operator, bool $safeTarget): string
    {
        if (!$safeTarget || !$targets || $id === $operator->id) {
            return self::CHANGED;
        }
        $user = $this->users->loadUserById($id);
        $current = array_values(array_unique(array_map('intval', (array) $user->groups)));
        $expected = array_values(array_unique(array_map('intval', $before)));
        sort($current);
        sort($expected);
        if (empty($user->id) || (string) $user->username !== $username || $current !== $expected
            || $user->authorise('core.admin') || $user->authorise('core.login.admin') || array_intersect($targets, $current)) {
            return self::CHANGED;
        }
        $all = array_values(array_unique(array_merge($current, $targets)));
        $user->groups = array_combine($all, $all);
        try {
            if (!$user->save(true)) {
                return self::ERROR;
            }
        } catch (\Throwable) {
            return self::ERROR;
        }
        foreach ($targets as $group) {
            $this->log->groupAdded($user, $operator, $group);
        }
        try {
            UserHelper::destroyUserSessions($id);
        } catch (\Throwable) {
            // The account was saved and logged; do not retry the write.
        }
        return self::ADDED;
    }
}
