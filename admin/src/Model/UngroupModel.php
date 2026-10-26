<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountRegrouper;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupRemoval;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Removes one account from some of its groups: choose the account, untick its groups (optionally with
 * every group below them), review, apply. Nothing changes without the reviewed, one-time preview.
 */
class UngroupModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.ungroup.draft';
    private const PREVIEW = 'com_usertools.ungroup.preview';
    private const RESULT  = 'com_usertools.ungroup.result';

    /** Accounts offered while typing. */
    public const SUGGESTIONS = 10;

    /** @return array{login: string, account: int, removed: list<int>, recursive: bool} */
    public function getDraft(): array
    {
        $draft = (array) $this->session()->get(self::DRAFT, []);
        return [
            'login'     => (string) ($draft['login'] ?? ''),
            'account'   => (int) ($draft['account'] ?? 0),
            'removed'   => array_map('intval', (array) ($draft['removed'] ?? [])),
            'recursive' => (bool) ($draft['recursive'] ?? false),
        ];
    }

    /** Forgets the chosen account and selection (for example when the account no longer exists). */
    public function resetDraft(): void
    {
        $this->session()->set(self::DRAFT, []);
        $this->session()->set(self::PREVIEW, []);
    }

    /**
     * Accounts whose username, name or email contains the text, for the account field.
     *
     * @return list<array{username: string, name: string, email: string, blocked: bool}>
     */
    public function suggest(string $text): array
    {
        $text = trim($text);
        if (mb_strlen($text) < 2) {
            return [];
        }
        return Toolkit::directory()->search($text, self::SUGGESTIONS);
    }

    /**
     * Step 1: the account. Refusals that do not depend on the groups are shown at once.
     *
     * @param array<string, mixed> $input
     */
    public function choose(array $input): void
    {
        $login = trim((string) ($input['login'] ?? ''));
        $this->session()->set(self::DRAFT, ['login' => $login]);
        $this->session()->set(self::PREVIEW, []);
        $account = $this->account(Toolkit::directory()->findId($login));
        if ($account === null) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_ACCOUNT', [$login]);
        }
        $operator = $this->operator();
        if ($account->id === $operator->id) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_REASON_SELF');
        }
        if (($account->isSuperUser || $account->hasBackendAccess) && !$operator->isSuperUser) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_REASON_BACKEND');
        }
        $this->session()->set(self::DRAFT, ['login' => $account->username, 'account' => $account->id]);
    }

    /**
     * Step 2: the account's groups, in tree order, with their parents so the screen can apply the
     * recursive option while ticking.
     *
     * @return array{account: UserRecord, groups: list<array{id: int, title: string, level: int, parents: list<int>, superUser: bool, backend: bool}>, removed: list<int>, recursive: bool, all: array<int, object>}
     */
    public function getSelection(): array
    {
        $draft   = $this->getDraft();
        $account = $this->account($draft['account']);
        if ($account === null) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHOOSE');
        }
        $all  = Toolkit::directory()->groups();
        $mine = array_fill_keys($account->groups, true);
        $rows = [];
        foreach ($all as $group) {
            if (isset($mine[(int) $group->id])) {
                $rows[] = [
                    'id'        => (int) $group->id,
                    'title'     => (string) $group->title,
                    'level'     => (int) $group->level,
                    'parents'   => GroupRemoval::ancestors($all, (int) $group->id),
                    'superUser' => (bool) $group->superUser,
                    'backend'   => (bool) $group->backend,
                ];
            }
        }
        return ['account' => $account, 'groups' => $rows, 'removed' => $draft['removed'], 'recursive' => $draft['recursive'], 'all' => $all];
    }

    /**
     * Step 2 → 3: the groups the account keeps (ticked) become a one-time review.
     *
     * @param array<string, mixed> $input
     */
    public function select(array $input): void
    {
        $selection = $this->getSelection();
        $account   = $selection['account'];
        $keep      = array_map('intval', (array) ($input['keep'] ?? []));
        $removed   = array_values(array_diff($account->groups, $keep));
        $recursive = (int) ($input['recursive'] ?? 0) === 1;
        $draft     = $this->getDraft();
        $this->session()->set(self::DRAFT, ['login' => $draft['login'], 'account' => $account->id, 'removed' => $removed, 'recursive' => $recursive]);
        if (!$removed) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_REASON_NOTHING');
        }
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'account'   => $account->id,
            'before'    => $account->groups,
            'selected'  => $removed,
            'recursive' => $recursive,
            'plan'      => $this->plan($account, $removed, $recursive),
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * Step 3: the review, recomputed now so it never shows stale data.
     *
     * @return array{account: UserRecord, plan: array<string, mixed>, groups: array<int, object>, defaultGroup: int, recursive: bool, nonce: string}
     */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        $account = $preview ? $this->account((int) $preview['account']) : null;
        if (!$preview || $account === null) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $plan = $this->plan($account, $preview['selected'], (bool) $preview['recursive']);
        if (!$this->same($account->groups, $preview['before']) || $plan !== $preview['plan']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        return ['account' => $account, 'plan' => $plan, 'groups' => Toolkit::directory()->groups(), 'defaultGroup' => $this->defaultGroup(),
            'recursive' => (bool) $preview['recursive'], 'nonce' => (string) $preview['nonce']];
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
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CONFIRM');
        }
        // Consume the preview before writing: a refresh or replay cannot repeat the change.
        $this->session()->set(self::PREVIEW, []);
        $account = $this->account((int) $preview['account']);
        if ($account === null || !$this->same($account->groups, $preview['before'])) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        $plan = $this->plan($account, $preview['selected'], (bool) $preview['recursive']);
        if ($plan !== $preview['plan']) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        $groups = Toolkit::directory()->groups();
        $title  = fn (int $id) => isset($groups[$id]) ? (string) $groups[$id]->title : '#' . $id;
        if ($plan['reason'] !== '') {
            throw new UserFacingException($plan['reason'], [$title($this->defaultGroup())]);
        }
        $removed = array_map($title, $plan['remove']);
        $added   = $plan['added'] ? $title($plan['added']) : '';
        $outcome = Toolkit::regrouper()->regroup($account->id, $preview['before'], $plan['result'], $this->operator(), $removed, $added);
        if ($outcome['status'] === AccountRegrouper::CHANGED) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        if ($outcome['status'] === AccountRegrouper::ERROR) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_SAVE', [$account->username]);
        }
        $result = [
            'id'       => $account->id,
            'username' => $account->username,
            'removed'  => $removed,
            'added'    => $added,
            'groups'   => array_map($title, $plan['result']),
            'sessions' => $outcome['sessions'],
        ];
        $this->storeResult(self::RESULT, $result);
        $this->session()->set(self::DRAFT, []);
        Toolkit::clearDashboardCache();
        return $result;
    }

    /**
     * Tries again to close the sessions of the account just changed (from the result screen).
     *
     * @param array<string, mixed> $input
     */
    public function retrySessions(array $input): string
    {
        $result = $this->getResult();
        if (!$result || (int) ($input['account'] ?? 0) !== (int) $result['id']) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $result['sessions'] = Toolkit::regrouper()->closeSessions((int) $result['id']);
        unset($result['actor'], $result['expires']);
        $this->storeResult(self::RESULT, $result);
        return $result['sessions'];
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->validResult(self::RESULT);
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** The group Joomla gives new accounts (Users Options). */
    private function defaultGroup(): int
    {
        return (int) ComponentHelper::getParams('com_users')->get('new_usertype', 2);
    }

    private function account(int $id): ?UserRecord
    {
        return $id > 0 ? (Toolkit::directory()->users([$id])[$id] ?? null) : null;
    }

    /**
     * @param  array<int, mixed> $selected
     * @return array{remove: list<int>, keep: list<int>, added: int, result: list<int>, reason: string, losesSuper: bool, losesBackend: bool, cascaded: array<int, int>}
     */
    private function plan(UserRecord $account, array $selected, bool $recursive): array
    {
        $directory = Toolkit::directory();
        return GroupRemoval::plan(
            $directory->groups(),
            $account->groups,
            array_values(array_map('intval', $selected)),
            $recursive,
            $this->defaultGroup(),
            $this->operator(),
            $account->id,
            $account->isSuperUser,
            $account->hasBackendAccess,
            $account->isSuperUser ? $directory->otherActiveSuperUsers($account->id) : 0,
        );
    }

    /**
     * @param list<int> $a
     * @param array<int, mixed> $b
     */
    private function same(array $a, array $b): bool
    {
        $a = array_map('intval', $a);
        $b = array_map('intval', $b);
        sort($a);
        sort($b);
        return $a === $b;
    }
}
