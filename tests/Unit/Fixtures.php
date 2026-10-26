<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;

/** Builders shared by the unit tests. */
final class Fixtures
{
    /** @param list<int> $groups */
    public static function user(int $id, string $username, array $groups = [2], bool $super = false, bool $backend = false, int $block = 0, ?string $lastVisit = null, ?string $registered = '2020-01-01 00:00:00'): UserRecord
    {
        return new UserRecord($id, $username, ucfirst($username), $username . '@example.test', $block, $lastVisit, $registered, $groups, array_merge([1], $groups), $super, $backend);
    }

    /** Mutable stand-in for a Joomla User (bind/save/authorise). */
    public static function joomlaUser(int $id = 0, string $username = '', int $block = 0, bool $saves = true): object
    {
        return new class ($id, $username, $block, $saves) {
            public array $bound = [];
            public int $saved = 0;
            public string $name = '';
            public ?string $lastvisitDate = '2000-01-01 00:00:00';
            public ?string $registerDate = '2000-01-01 00:00:00';

            public function __construct(public int $id, public string $username, public int $block, private bool $saves)
            {
            }

            public function bind(array $data): bool
            {
                $this->bound = $data;
                $this->username = $data['username'];
                return true;
            }

            public function save(): bool
            {
                if (!$this->saves) {
                    return false;
                }
                $this->saved++;
                $this->id = $this->id ?: random_int(1000, 9999);
                return true;
            }

            public function authorise(string $action): bool
            {
                return false;
            }

            public function getAuthorisedGroups(): array
            {
                return [1, 2];
            }
        };
    }
}
