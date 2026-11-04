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
 * Rotates only the local password and disables requireReset on a fresh Joomla User.
 * Joomla User::bind() hashes the generated password and User::save(true) runs plugins.
 */
final class SsoAccountConverter
{
    public const CONVERTED = 'converted';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    /** @var \Closure(int): bool */
    private \Closure $destroySessions;

    /** @param (callable(int): bool)|null $destroySessions */
    public function __construct(
        private UserFactoryInterface $users,
        private PasswordGenerator $passwords,
        private ActionLogger $log,
        ?callable $destroySessions = null,
    ) {
        $this->destroySessions = $destroySessions !== null ? \Closure::fromCallable($destroySessions)
            : static fn (int $id): bool => (bool) UserHelper::destroyUserSessions($id);
    }

    /**
     * @param array<string, mixed> $reviewed Account fingerprint, no passwords or MFA information.
     * @return array{status: string, id: int, username: string, sessionsClosed: bool}
     */
    public function convert(array $reviewed, Operator $operator): array
    {
        $id = (int) $reviewed['id'];
        $user = $this->users->loadUserById($id);
        $name = (string) ($user->username ?? $reviewed['username']);
        if (!SsoConversionPlan::stillEligible($user, $reviewed, $operator)) {
            return ['status' => self::CHANGED, 'id' => $id, 'username' => $name, 'sessionsClosed' => false];
        }

        $secret = $this->passwords->generate();
        // Never write a raw password to logs or session. Joomla's bind hashes it in memory.
        $data = ['password' => $secret, 'password2' => $secret, 'requireReset' => 0];
        try {
            if (!$user->bind($data) || !$user->save(true)) {
                return ['status' => self::ERROR, 'id' => $id, 'username' => $name, 'sessionsClosed' => false];
            }
        } catch (\Throwable) {
            return ['status' => self::ERROR, 'id' => $id, 'username' => $name, 'sessionsClosed' => false];
        } finally {
            unset($secret, $data);
            // Remove the transient plaintext left on the in-memory Joomla User object.
            if (isset($user->password_clear)) {
                $user->password_clear = '';
            }
        }

        try {
            $closed = ($this->destroySessions)($id);
        } catch (\Throwable) {
            $closed = false;
        }
        $this->log->convertedToSso($user, $operator);
        return ['status' => self::CONVERTED, 'id' => $id, 'username' => $name, 'sessionsClosed' => (bool) $closed];
    }
}
