<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Who performs an operation: a person in the backend or the scheduler (no identity). */
final class Operator
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isSuperUser,
        public readonly string $name = '',
    ) {
    }

    /** Scheduled tasks act without a user identity, whoever triggered the scheduler. */
    public static function system(string $name = ''): self
    {
        return new self(0, true, $name);
    }

    public static function fromUser(object $user): self
    {
        return new self((int) $user->id, (bool) $user->authorise('core.admin'), (string) ($user->username ?? ''));
    }
}
