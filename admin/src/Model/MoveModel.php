<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupMove;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupMover;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupRemoval;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Moves several groups under a new parent: choose the groups and the parent, review what their members
 * gain or lose, apply. Super Users only. Moves that change administrator access are refused.
 */
class MoveModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.move.draft';
    private const PREVIEW = 'com_usertools.move.preview';
    private const RESULT  = 'com_usertools.move.result';

    /** @return array{groups: list<int>, target: int} */
    public function getDraft(): array
    {
        $draft = (array) $this->session()->get(self::DRAFT, []);
        return ['groups' => array_values(array_map('intval', (array) ($draft['groups'] ?? []))), 'target' => (int) ($draft['target'] ?? 0)];
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** @return list<array{id: int, title: string, rules: list<int>}> */
    public function getLevels(): array
    {
        return Toolkit::directory()->viewLevels();
    }

    /**
     * Step 1 → 2: the chosen groups and parent become a one-time review.
     *
     * @param array<string, mixed> $input
     */
    public function preview(array $input): void
    {
        $groups = array_values(array_unique(array_filter(array_map('intval', (array) ($input['groups'] ?? [])))));
        $target = (int) ($input['target'] ?? 0);
        $this->session()->set(self::DRAFT, ['groups' => $groups, 'target' => $target]);
        $this->session()->set(self::PREVIEW, []);
        if (!$groups) {
            throw new UserFacingException('COM_USERTOOLS_MOVE_ERROR_NO_GROUPS');
        }
        if (!isset($this->getGroups()[$target])) {
            throw new UserFacingException('COM_USERTOOLS_MOVE_ERROR_TARGET');
        }
        $this->session()->set(self::PREVIEW, $this->newPreview(['groups' => $groups, 'target' => $target, 'plan' => $this->plan($groups, $target)]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * Step 2: the review, recomputed now so it never shows stale data.
     *
     * @return array{target: int, rows: list<array<string, mixed>>, accounts: array<int, int>, moving: int, sessions: int, self: bool, nonce: string}
     */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $plan = $this->plan($preview['groups'], (int) $preview['target']);
        if ($plan !== $preview['plan']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_MOVE_ERROR_CHANGED');
        }
        $directory = Toolkit::directory();
        $accounts  = [];
        $moved     = [];
        foreach ($plan as $row) {
            if ($row['status'] === GroupMove::MOVE) {
                $accounts[$row['id']] = $directory->accountsIn($row['subtree']);
                $moved = array_merge($moved, $row['subtree']);
            }
        }
        $affected = $directory->accountsWithGroups($moved);
        return ['target' => (int) $preview['target'], 'rows' => $plan, 'accounts' => $accounts,
            'moving' => \count($accounts), 'sessions' => \count($affected), 'self' => isset($affected[(int) $this->identity()->id]),
            'nonce' => (string) $preview['nonce']];
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apply(array $input): array
    {
        $preview = $this->getPreview();
        if (!$preview || !hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ((int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_MOVE_ERROR_CONFIRM');
        }
        // Consume the preview before writing: a refresh or replay cannot repeat the change.
        $this->session()->set(self::PREVIEW, []);
        $target = (int) $preview['target'];
        $plan   = $this->plan($preview['groups'], $target);
        if ($plan !== $preview['plan']) {
            throw new UserFacingException('COM_USERTOOLS_MOVE_ERROR_CHANGED');
        }
        $groups = $this->getGroups();
        $title  = fn (int $id): string => isset($groups[$id]) ? (string) $groups[$id]->title : '#' . $id;
        $mover  = Toolkit::groupMover();
        $result = ['target' => $title($target), 'moved' => [], 'changed' => [], 'errors' => [],
            'sessions' => ['closed' => 0, 'unknown' => 0, 'open' => [], 'self' => false]];
        $moved  = [];
        foreach ($plan as $row) {
            if ($row['status'] !== GroupMove::MOVE) {
                continue;
            }
            $status = $mover->move($row['id'], $row['from'], $target, $this->operator(), $title($row['from']), $title($target));
            $key    = match ($status) {
                GroupMover::MOVED   => 'moved',
                GroupMover::CHANGED => 'changed',
                default             => 'errors',
            };
            $result[$key][] = ['id' => $row['id'], 'title' => $title($row['id']), 'from' => $title($row['from'])];
            if ($status === GroupMover::MOVED) {
                $moved = array_merge($moved, $row['subtree']);
            }
        }
        // Accounts in the moved groups may keep levels they lost in an open session, as with
        // "Remove from groups": close those sessions and report any that survives. The operator's own
        // session stays open; its administrator access cannot change here.
        $regrouper = Toolkit::regrouper();
        $self      = (int) $this->identity()->id;
        foreach (Toolkit::directory()->accountsWithGroups($moved) as $id => $account) {
            if ($id === $self) {
                $result['sessions']['self'] = true;
                continue;
            }
            $state = $regrouper->closeSessions($id);
            if ($state === 'open') {
                $result['sessions']['open'][] = $account['username'];
            } elseif ($state === 'unknown') {
                $result['sessions']['unknown']++;
            } else {
                $result['sessions']['closed']++;
            }
        }
        $this->storeResult(self::RESULT, $result);
        $this->session()->set(self::DRAFT, []);
        Toolkit::clearDashboardCache();
        return $result;
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->validResult(self::RESULT);
    }

    /**
     * @param  array<int, mixed> $groups
     * @return list<array<string, mixed>>
     */
    private function plan(array $groups, int $target): array
    {
        $directory = Toolkit::directory();
        $all     = $directory->groups();
        $ids     = array_values(array_map('intval', $groups));
        $subtree = [];
        foreach ($ids as $id) {
            $subtree = array_merge($subtree, GroupRemoval::subtree($all, $id));
        }
        return GroupMove::plan($all, $directory->viewLevels(), $ids, $target,
            fn (string $action, array $path): bool => $directory->allows($action, $path), $directory->accountsWithGroups($subtree));
    }
}
