<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Which viewing access level each user group gets, following the rules of
 * Joomla's Access::getAuthorisedViewLevels(): a group gets a level when the group itself or any of
 * its parents is assigned to it, and every account gets the Public level (id 1). Pure logic.
 */
final class AccessMatrix
{
    public const PUBLIC_LEVEL = 1;

    /**
     * @param  array<int, object>                                      $groups  UserDirectory::groups(), in tree order
     * @param  list<array{id: int, title: string, rules: list<int>}>   $levels  in display order
     * @param  array<int, int>                                         $members active accounts per group (direct)
     * @return array{
     *     levels: list<array{id: int, title: string, groups: list<int>, users: int, reach: int, empty: bool}>,
     *     rows: list<array{id: int, title: string, level: int, superUser: bool, backend: bool, guest: bool, members: int,
     *         cells: array<int, array{state: string, via: int}>}>
     * }
     */
    public static function build(array $groups, array $levels, array $members = [], int $guestGroup = 0): array
    {
        $parents = [];
        foreach ($groups as $group) {
            $parents[(int) $group->id] = (int) $group->parent_id;
        }

        $levelRows = [];
        $assigned  = [];
        foreach ($levels as $level) {
            $direct = [];
            $users  = 0;
            foreach ($level['rules'] as $rule) {
                $rule = (int) $rule;
                if ($rule < 0) {
                    $users++;
                } elseif (isset($parents[$rule])) {
                    $direct[$rule] = $rule;
                }
            }
            $assigned[$level['id']] = $direct;
            $levelRows[$level['id']] = [
                'id'     => (int) $level['id'],
                'title'  => (string) $level['title'],
                'groups' => array_values(array_filter(array_keys($groups), fn ($id) => isset($direct[$id]))),
                'users'  => $users,
                'reach'  => 0,
                'empty'  => false,
            ];
        }

        $rows = [];
        foreach ($groups as $group) {
            $id    = (int) $group->id;
            $cells = [];
            foreach ($levelRows as $levelId => $row) {
                $cells[$levelId] = self::cell($id, $levelId, $assigned[$levelId], $parents);
                if ($cells[$levelId]['state'] !== 'none') {
                    $levelRows[$levelId]['reach']++;
                }
            }
            $rows[] = [
                'id'        => $id,
                'title'     => (string) $group->title,
                'level'     => (int) ($group->level ?? 0),
                'superUser' => (bool) ($group->superUser ?? false),
                'backend'   => (bool) ($group->backend ?? false),
                'guest'     => $id === $guestGroup,
                'members'   => (int) ($members[$id] ?? 0),
                'cells'     => $cells,
            ];
        }
        foreach ($levelRows as $levelId => $row) {
            // A level with no group and no account assigned is visible to nobody (except Public, always granted).
            $levelRows[$levelId]['empty'] = $levelId !== self::PUBLIC_LEVEL && !$row['groups'] && $row['users'] === 0;
        }

        return ['levels' => array_values($levelRows), 'rows' => $rows];
    }

    /**
     * @param  array<int, int> $assigned groups assigned to the level
     * @param  array<int, int> $parents  group id => parent id
     * @return array{state: string, via: int} state: direct, inherited (via = nearest assigned parent), public or none
     */
    private static function cell(int $groupId, int $levelId, array $assigned, array $parents): array
    {
        if (isset($assigned[$groupId])) {
            return ['state' => 'direct', 'via' => 0];
        }
        $seen   = [$groupId => true];
        $parent = $parents[$groupId] ?? 0;
        while ($parent > 0 && isset($parents[$parent]) && !isset($seen[$parent])) {
            if (isset($assigned[$parent])) {
                return ['state' => 'inherited', 'via' => $parent];
            }
            $seen[$parent] = true;
            $parent        = $parents[$parent];
        }
        if ($levelId === self::PUBLIC_LEVEL) {
            return ['state' => 'public', 'via' => 0];
        }
        return ['state' => 'none', 'via' => 0];
    }

    /**
     * Decodes #__viewlevels.rules ("[1,6,-42]").
     *
     * @return list<int>
     */
    public static function rules(?string $json): array
    {
        $rules = json_decode((string) $json, true);
        return \is_array($rules) ? array_values(array_map('intval', array_filter($rules, 'is_numeric'))) : [];
    }
}
