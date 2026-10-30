<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Exclusions of one tool: the manual comparison, the manual inactivity review or each scheduled task.
 * Super Users are always protected, whatever the configuration, so a site can never lose all of them.
 */
final class Exclusions
{
    public const REASON_OPERATOR    = 'COM_USERTOOLS_REASON_OPERATOR';
    public const REASON_SUPER_USER  = 'COM_USERTOOLS_REASON_SUPER_USER';
    public const REASON_NAME        = 'COM_USERTOOLS_REASON_NAME';
    public const REASON_ADMIN       = 'COM_USERTOOLS_REASON_ADMIN';
    public const REASON_PRIVILEGED  = 'COM_USERTOOLS_REASON_PRIVILEGED';
    public const REASON_GROUP       = 'COM_USERTOOLS_REASON_GROUP';

    /**
     * @param list<string> $names
     * @param list<int>    $groups
     */
    public function __construct(
        public readonly bool $protectAdmins = true,
        public readonly array $names = [],
        public readonly array $groups = [],
    ) {
    }

    /** @param array<string, mixed> $values keys protect_admins, excluded_names, excluded_groups (optionally prefixed) */
    public static function fromArray(array $values, string $prefix = ''): self
    {
        $groups = [];
        foreach ((array) ($values[$prefix . 'excluded_groups'] ?? []) as $id) {
            if ($id === '' || $id === null) {
                continue;
            }
            if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_GROUPS_INVALID');
            }
            $groups[] = (int) $id;
        }
        $groups = array_values(array_unique($groups));
        sort($groups);
        $names = $values[$prefix . 'excluded_names'] ?? '';
        if (\is_array($names)) {
            $names = implode("\n", array_map('strval', $names));
        }
        return new self(
            (string) ($values[$prefix . 'protect_admins'] ?? '1') !== '0',
            Comparison::parse((string) $names),
            $groups,
        );
    }

    /** @return array<string, int|string|list<int>> */
    public function toArray(string $prefix = ''): array
    {
        return [
            $prefix . 'protect_admins'  => (int) $this->protectAdmins,
            $prefix . 'excluded_names'  => implode("\n", $this->names),
            $prefix . 'excluded_groups' => $this->groups,
        ];
    }

    /** @param object $user Joomla User or UserRecord: authorise() and getAuthorisedGroups() */
    public function protects(object $user, Operator $operator): bool
    {
        return $this->reason($user, $operator) !== '';
    }

    /** Language key explaining why the account is protected, or an empty string. */
    public function reason(object $user, Operator $operator): string
    {
        if ($operator->id > 0 && (int) $user->id === $operator->id) {
            return self::REASON_OPERATOR;
        }
        if ($user->authorise('core.admin')) {
            return self::REASON_SUPER_USER;
        }
        if (Comparison::contains($this->names, (string) $user->username)) {
            return self::REASON_NAME;
        }
        $backend = (bool) $user->authorise('core.login.admin');
        if ($this->protectAdmins && $backend) {
            return self::REASON_ADMIN;
        }
        if ($backend && !$operator->isSuperUser) {
            return self::REASON_PRIVILEGED;
        }
        if (array_intersect($this->groups, $user->getAuthorisedGroups())) {
            return self::REASON_GROUP;
        }
        return '';
    }
}
