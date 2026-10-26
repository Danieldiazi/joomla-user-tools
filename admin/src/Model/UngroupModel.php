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
 * Removes one account from a group and every group below it: choose, review, apply.
 * Nothing changes without the reviewed, one-time preview and its confirmation.
 */
class UngroupModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.ungroup.draft';
    private const PREVIEW = 'com_usertools.ungroup.preview';
    private const RESULT  = 'com_usertools.ungroup.result';

    /** @return array{login: string, group: int} */
    public function getDraft(): array
    {
        $draft = (array) $this->session()->get(self::DRAFT, []);
        return ['login' => (string) ($draft['login'] ?? ''), 'group' => (int) ($draft['group'] ?? 0)];
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        $login   = trim((string) ($input['login'] ?? ''));
        $groupId = (int) ($input['group'] ?? 0);
        $this->session()->set(self::DRAFT, ['login' => $login, 'group' => $groupId]);
        $groups = Toolkit::directory()->groups();
        if (!isset($groups[$groupId]) || (int) $groups[$groupId]->parent_id === 0) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_GROUP');
        }
        $account = $this->account(Toolkit::directory()->findId($login));
        if ($account === null) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_ACCOUNT', [$login]);
        }
        $plan = $this->plan($account, $groupId);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'account' => $account->id,
            'group'   => $groupId,
            'before'  => $account->groups,
            'plan'    => $plan,
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * The review, recomputed now so it never shows stale data.
     *
     * @return array{account: UserRecord, group: int, plan: array<string, mixed>, groups: array<int, object>, defaultGroup: int, nonce: string}
     */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        $account = $preview ? $this->account((int) $preview['account']) : null;
        if (!$preview || $account === null) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $plan = $this->plan($account, (int) $preview['group']);
        if (!$this->same($account->groups, $preview['before']) || $plan !== $preview['plan']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        return ['account' => $account, 'group' => (int) $preview['group'], 'plan' => $plan, 'groups' => Toolkit::directory()->groups(),
            'defaultGroup' => $this->defaultGroup(), 'nonce' => (string) $preview['nonce']];
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
        $plan = $this->plan($account, (int) $preview['group']);
        if ($plan !== $preview['plan']) {
            throw new UserFacingException('COM_USERTOOLS_UNGROUP_ERROR_CHANGED');
        }
        $groups  = Toolkit::directory()->groups();
        $title   = fn (int $id) => isset($groups[$id]) ? (string) $groups[$id]->title : '#' . $id;
        if ($plan['reason'] !== '') {
            throw new UserFacingException($plan['reason'], [$title((int) $preview['group']), $title($this->defaultGroup())]);
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
            'group'    => $title((int) $preview['group']),
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

    /** @return array{remove: list<int>, keep: list<int>, added: int, result: list<int>, reason: string, losesSuper: bool, losesBackend: bool} */
    private function plan(UserRecord $account, int $groupId): array
    {
        $directory = Toolkit::directory();
        return GroupRemoval::plan(
            $directory->groups(),
            $account->groups,
            $groupId,
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
