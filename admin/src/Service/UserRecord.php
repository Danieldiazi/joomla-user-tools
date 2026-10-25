<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Read-only account snapshot loaded in bulk. It answers the same questions as a Joomla User
 * for the exclusion rules. Changes are always revalidated with a fresh Joomla User before saving.
 */
final class UserRecord
{
    /**
     * @param list<int> $groups           direct groups
     * @param list<int> $authorisedGroups direct groups plus their ancestors
     */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $name,
        public readonly string $email,
        public readonly int $block,
        public readonly ?string $lastvisitDate,
        public readonly ?string $registerDate,
        public readonly array $groups,
        public readonly array $authorisedGroups,
        public readonly bool $isSuperUser,
        public readonly bool $hasBackendAccess,
    ) {
    }

    public function authorise(string $action): bool
    {
        return match ($action) {
            'core.admin'       => $this->isSuperUser,
            'core.login.admin' => $this->isSuperUser || $this->hasBackendAccess,
            default            => throw new \LogicException('Unsupported action for a bulk record: ' . $action),
        };
    }

    /** @return list<int> */
    public function getAuthorisedGroups(): array
    {
        return $this->authorisedGroups;
    }

    public function neverVisited(): bool
    {
        return $this->lastvisitDate === null || $this->lastvisitDate === '' || str_starts_with($this->lastvisitDate, '0000-00-00');
    }
}
