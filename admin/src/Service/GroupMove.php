<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Decides which groups can be moved under a new parent and what their members gain or lose. Pure logic:
 * the caller passes the group tree, the viewing levels and the check of the root asset rules.
 *
 * A group moves with all its subgroups. A move that would give or take away administrator access
 * (core.login.admin) or Super User rights (core.admin) for the group or any of its subgroups is refused:
 * those changes are made in Joomla, group by group, never in bulk.
 */
final class GroupMove
{
    public const MOVE    = 'move';
    public const SAME    = 'same';
    public const COVERED = 'covered';
    public const REFUSED = 'refused';

    /**
     * @param  array<int, object>                                     $groups   UserDirectory::groups(), in tree order
     * @param  list<array{id: int, title: string, rules: list<int>}>   $levels   viewing access levels
     * @param  list<int>                                              $selected groups the operator ticked
     * @param  callable(string, list<int>): bool                      $allow    root asset rule for a group path
     * @return list<array{id: int, from: int, status: string, reason: string, via: int, subtree: list<int>,
     *     gained: list<int>, lost: list<int>, access: list<array{id: int, before: string, after: string}>}>
     *     in tree order; via is the selected group a covered one moves with
     */
    public static function plan(array $groups, array $levels, array $selected, int $target, callable $allow): array
    {
        $wanted = array_flip(array_map('intval', $selected));
        $movers = [];
        $titles = [];
        foreach ($groups as $group) {
            if ((int) $group->parent_id === $target) {
                $titles[mb_strtolower(trim((string) $group->title))] = true;
            }
        }
        $rows = [];
        foreach ($groups as $group) {
            $id = (int) $group->id;
            if (!isset($wanted[$id])) {
                continue;
            }
            $row = ['id' => $id, 'from' => (int) $group->parent_id, 'status' => self::REFUSED, 'reason' => '', 'via' => 0,
                'subtree' => self::subtree($groups, $id), 'gained' => [], 'lost' => [], 'access' => []];
            $via = self::movingAncestor($groups, $id, $movers);
            $title = mb_strtolower(trim((string) $group->title));
            if ($via > 0) {
                $row['status'] = self::COVERED;
                $row['via']    = $via;
            } elseif (!isset($groups[$target])) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TARGET';
            } elseif ((int) $group->parent_id === 0) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_ROOT';
            } elseif ((bool) $group->superUser) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_SUPER';
            } elseif (\in_array($target, $row['subtree'], true)) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_CYCLE';
            } elseif ((bool) $groups[$target]->superUser) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TARGET_SUPER';
            } elseif ((int) $group->parent_id === $target) {
                $row['status'] = self::SAME;
            } elseif (isset($titles[$title])) {
                $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_TITLE';
            } else {
                $row = self::changes($groups, $levels, $row, $target, $allow);
                if ($row['access']) {
                    $row['reason'] = 'COM_USERTOOLS_MOVE_REASON_BACKEND';
                } else {
                    $row['status'] = self::MOVE;
                    $movers[$id]   = true;
                    // Two groups with the same name cannot end up under the same parent.
                    $titles[$title] = true;
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Levels gained and lost by the group and its subgroups, and any change in administrator access.
     *
     * @param  array<int, object>                                   $groups
     * @param  list<array{id: int, title: string, rules: list<int>}> $levels
     * @param  array<string, mixed>                                 $row
     * @param  callable(string, list<int>): bool                    $allow
     * @return array<string, mixed>
     */
    private static function changes(array $groups, array $levels, array $row, int $target, callable $allow): array
    {
        $above  = self::path($groups, $target);
        $inside = array_flip($row['subtree']);
        $gained = [];
        $lost   = [];
        foreach ($row['subtree'] as $id) {
            $before = self::path($groups, $id);
            $after  = array_values(array_unique(array_merge($above, array_values(array_filter($before, fn (int $g) => isset($inside[$g]))))));
            $was    = self::levelsOf($levels, $before);
            $will   = self::levelsOf($levels, $after);
            $gained += array_fill_keys(array_diff($will, $was), true);
            $lost   += array_fill_keys(array_diff($was, $will), true);
            $super   = $allow('core.admin', $after);
            $backend = $super || $allow('core.login.admin', $after);
            $old     = self::accessOf($groups[$id]);
            $new     = $super ? 'super' : ($backend ? 'backend' : 'none');
            if ($old !== $new) {
                $row['access'][] = ['id' => $id, 'before' => $old, 'after' => $new];
            }
        }
        $order = array_column($levels, 'id');
        $row['gained'] = array_values(array_filter($order, fn ($id) => isset($gained[$id])));
        $row['lost']   = array_values(array_filter($order, fn ($id) => isset($lost[$id])));
        return $row;
    }

    private static function accessOf(object $group): string
    {
        return (bool) $group->superUser ? 'super' : ((bool) $group->backend ? 'backend' : 'none');
    }

    /**
     * @param  list<array{id: int, title: string, rules: list<int>}> $levels
     * @param  list<int>                                            $path
     * @return list<int>
     */
    private static function levelsOf(array $levels, array $path): array
    {
        $result = [AccessMatrix::PUBLIC_LEVEL];
        foreach ($levels as $level) {
            if (array_intersect(array_map('intval', $level['rules']), $path)) {
                $result[] = (int) $level['id'];
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * The group and its ancestors.
     *
     * @param  array<int, object> $groups
     * @return list<int>
     */
    public static function path(array $groups, int $id): array
    {
        if (!isset($groups[$id])) {
            return [];
        }
        $target = $groups[$id];
        $result = [];
        foreach ($groups as $group) {
            if ((int) $group->lft <= (int) $target->lft && (int) $group->rgt >= (int) $target->rgt) {
                $result[] = (int) $group->id;
            }
        }
        return $result;
    }

    /**
     * @param  array<int, object> $groups
     * @return list<int>
     */
    private static function subtree(array $groups, int $id): array
    {
        return GroupRemoval::subtree($groups, $id);
    }

    /**
     * @param array<int, object> $groups
     * @param array<int, bool>   $movers
     */
    private static function movingAncestor(array $groups, int $id, array $movers): int
    {
        foreach (array_reverse(self::path($groups, $id)) as $ancestor) {
            if ($ancestor !== $id && isset($movers[$ancestor])) {
                return $ancestor;
            }
        }
        return 0;
    }
}
