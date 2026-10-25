<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;

/** A separate instance is created from the manual settings or each task's params. */
final class Exclusions
{
    public function __construct(
        public readonly bool $protectAdmins = true,
        public readonly array $names = [],
        public readonly array $groups = [],
    ) {}

    public static function fromArray(array $values): self
    {
        $groups = [];
        foreach ((array) ($values['excluded_groups'] ?? []) as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) {
                throw new \InvalidArgumentException('Selecciona grupos válidos.');
            }
            $groups[] = (int) $id;
        }
        $groups = array_values(array_unique($groups));
        sort($groups);
        return new self(
            (string) ($values['protect_admins'] ?? '1') !== '0',
            Comparison::parse((string) ($values['excluded_names'] ?? '')),
            $groups,
        );
    }

    public function toArray(): array
    {
        return ['protect_admins' => (int) $this->protectAdmins,
            'excluded_names' => implode("\n", $this->names), 'excluded_groups' => $this->groups];
    }

    public function protects(object $user, int $operatorId = 0): bool
    {
        if ($operatorId > 0 && (int) $user->id === $operatorId) {
            return true;
        }
        if (in_array((string) $user->username, $this->names, true)) {
            return true;
        }
        if ($this->protectAdmins
            && ($user->authorise('core.admin') || $user->authorise('core.login.admin'))) {
            return true;
        }
        // Authorised groups include ancestors: selecting a parent protects its children.
        return (bool) array_intersect($this->groups, $user->getAuthorisedGroups());
    }
}
