<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** New accounts selected in the comparison review. They are always created in the Registered group. */
final class Registration
{
    /** The account receives an email with a link to choose its password. Nobody sees a password. */
    public const MODE_EMAIL = 'email';
    /** A temporary password is shown once to the operator and must be changed at first login. */
    public const MODE_PASSWORD = 'password';

    public static function key(string $name): string
    {
        return hash('sha256', Comparison::normalise($name));
    }

    public static function mode(mixed $value): string
    {
        return $value === self::MODE_PASSWORD ? self::MODE_PASSWORD : self::MODE_EMAIL;
    }

    /**
     * @param  list<string> $unknown names offered for registration
     * @return array{checked: array<string, string>, emails: array<string, string>}
     */
    public static function draft(string $json, array $unknown): array
    {
        if (\strlen($json) > Comparison::MAX_INPUT_BYTES) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_SELECTION_TOO_LARGE');
        }
        try {
            $rows = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTRATIONS_UNREADABLE');
        }
        if (!\is_array($rows)) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTRATIONS_INVALID');
        }
        $allowed = array_fill_keys(array_map([self::class, 'key'], $unknown), true);
        $draft   = ['checked' => [], 'emails' => []];
        foreach ($rows as $key => $row) {
            if (!isset($allowed[$key]) || !\is_array($row) || !\is_string($row['email'] ?? null)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTRATIONS_MISMATCH');
            }
            if (($row['checked'] ?? false) === true) {
                $draft['checked'][$key] = '1';
            }
            $draft['emails'][$key] = $row['email'];
        }
        return $draft;
    }

    /**
     * Validate the whole selection before either blocking or creating accounts.
     *
     * @param  list<string>            $unknown
     * @param  array<string, string>   $checked
     * @param  array<string, string>   $emails
     * @param  callable(string): bool  $emailExists
     * @param  callable(string): bool  $usernameExists
     * @return list<array{username: string, email: string}>
     */
    public static function select(array $unknown, array $checked, array $emails, callable $emailExists, callable $usernameExists): array
    {
        $allowed = [];
        foreach ($unknown as $name) {
            $allowed[self::key((string) $name)] = (string) $name;
        }
        $selection = [];
        $used      = [];
        foreach ($checked as $key => $value) {
            if ((string) $value !== '1') {
                continue;
            }
            if (!isset($allowed[$key])) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTRATIONS_CHANGED');
            }
            $name  = $allowed[$key];
            $email = trim((string) ($emails[$key] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_EMAIL_INVALID', [$name]);
            }
            $normalised = mb_strtolower($email, 'UTF-8');
            if (isset($used[$normalised]) || $emailExists($email)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_EMAIL_IN_USE', [$name]);
            }
            if ($usernameExists($name)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_USERNAME_EXISTS', [$name]);
            }
            $used[$normalised] = true;
            $selection[]       = ['username' => $name, 'email' => $email];
        }
        return $selection;
    }

    /**
     * @param  list<array{username: string, email: string}> $selection
     * @param  callable(): object                           $newUser  returns an empty Joomla User
     * @param  callable(object): bool                       $sendLink sends the "choose your password" email (email mode)
     * @param  (callable(object): void)|null                $saved    runs as soon as each account exists (the action log),
     *                                                                so an interrupted request never leaves an unlogged account
     * @return array{created: list<array<string, mixed>>, errors: list<array{username: string, message: string}>}
     */
    public static function create(array $selection, callable $newUser, int $groupId, string $mode, PasswordGenerator $passwords, callable $sendLink, ?callable $saved = null): array
    {
        $mode   = self::mode($mode);
        $result = ['created' => [], 'errors' => []];
        foreach ($selection as $row) {
            $password = $passwords->generate();
            try {
                $user = $newUser();
                $data = [
                    'id' => 0, 'username' => $row['username'], 'name' => $row['username'],
                    'email' => $row['email'], 'password' => $password, 'password2' => $password,
                    'groups' => [$groupId], 'block' => 0, 'activation' => '',
                    'requireReset' => $mode === self::MODE_PASSWORD ? 1 : 0, 'sendEmail' => 0,
                ];
                if (!$user->bind($data) || !$user->save()) {
                    throw new \RuntimeException('save');
                }
            } catch (\Throwable) {
                // Never put generated passwords or third-party plugin diagnostics in results or logs.
                $result['errors'][] = ['username' => $row['username'], 'message' => 'COM_USERTOOLS_ERROR_CREATE_FAILED'];
                continue;
            }
            if ($saved !== null) {
                try {
                    $saved($user);
                } catch (\Throwable) {
                    // The account exists; a failing log writer must not hide it from the result.
                }
            }
            $created = $row + ['id' => (int) $user->id, 'mode' => $mode];
            if ($mode === self::MODE_PASSWORD) {
                $created['password'] = $password;
            } else {
                try {
                    $created['mailed'] = (bool) $sendLink($user);
                } catch (\Throwable) {
                    $created['mailed'] = false;
                }
            }
            $result['created'][] = $created;
        }
        return $result;
    }
}
