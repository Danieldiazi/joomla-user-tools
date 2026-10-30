<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountGroupAdder;
use Danieldiazi\Component\Usertools\Administrator\Service\BulkGroupAssignments as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Additive group membership: one-time preview, typed confirmation, per-account checkpoints. */
class AddgroupsModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.addgroups.draft';
    private const PREVIEW = 'com_usertools.addgroups.preview';
    private const JOB = 'com_usertools.addgroups.job';
    private const RESULT = 'com_usertools.addgroups.result';

    /** @return array{mode: string, lines: string, group: string} */
    public function getDraft(): array
    {
        $d = (array) $this->session()->get(self::DRAFT, []);
        return ['mode' => (string) ($d['mode'] ?? 'single'), 'lines' => (string) ($d['lines'] ?? ''), 'group' => (string) ($d['group'] ?? '')];
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $mode = (string) ($input['mode'] ?? 'single');
        $lines = trim((string) ($input['lines'] ?? ''));
        $group = trim((string) ($input['group'] ?? ''));
        $this->session()->set(self::DRAFT, compact('mode', 'lines', 'group'));
        $this->session()->set(self::PREVIEW, []);
        if (!in_array($mode, ['single', 'pairs'], true) || $lines === '' || count(preg_split('/\R/u', $lines) ?: []) > 1000) {
            throw new UserFacingException('COM_USERTOOLS_ADDGROUPS_INVALID_INPUT');
        }
        $rows = $this->plan($mode, $lines, $group);
        if (!$rows) {
            throw new UserFacingException('COM_USERTOOLS_ADDGROUPS_INVALID_INPUT');
        }
        $this->session()->set(self::PREVIEW, $this->newPreview(['rows' => $rows, 'mode' => $mode, 'lines' => $lines, 'group' => $group]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /** @return array<string, mixed> */
    public function getReview(): array
    {
        $p = $this->getPreview();
        if (!$p) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($this->plan($p['mode'], $p['lines'], $p['group']) !== $p['rows']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_ADDGROUPS_CHANGED');
        }
        $accounts = [];
        foreach ($p['rows'] as $row) {
            if ($row['status'] === Plan::ADD) {
                $accounts[$row['id']] = true;
            }
        }
        return $p + ['count' => count($accounts)];
    }

    /** @param array<string, mixed> $input */
    public function apply(array $input): bool
    {
        $p = $this->getReview();
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        if (!hash_equals((string) $p['nonce'], (string) ($input['preview'] ?? ''))
            || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $this->checkTypedCount((int) $p['count'], $input, 1);
        $this->session()->set(self::PREVIEW, []);
        $pending = [];
        foreach ($p['rows'] as $row) {
            if ($row['status'] !== Plan::ADD) {
                continue;
            }
            $id = $row['id'];
            if (!isset($pending[$id])) {
                $pending[$id] = ['username' => $row['username'], 'before' => $row['before'], 'targets' => []];
            }
            $pending[$id]['targets'][] = $row['group'];
        }
        $this->session()->set(self::JOB, ['actor' => (int) $this->identity()->id, 'pending' => $pending,
            'added' => [], 'changed' => [], 'errors' => [], 'rows' => $p['rows']]);
        return $this->step();
    }

    /** Works in resumable bounded batches. Stores progress after each account. */
    public function step(): bool
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $groups = Toolkit::directory()->groups();
        $writer = new AccountGroupAdder(Toolkit::users(), new \Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger());
        $processed = 0;
        foreach ($job['pending'] as $id => $entry) {
            if ($processed++ >= 20) {
                break;
            }
            unset($job['pending'][$id]);
            $safe = true;
            foreach ($entry['targets'] as $target) {
                if (!isset($groups[$target]) || $groups[$target]->backend || $groups[$target]->superUser || (int) $groups[$target]->parent_id === 0) {
                    $safe = false;
                }
            }
            $outcome = $writer->add((int) $id, $entry['username'], $entry['before'], $entry['targets'], $this->operator(), $safe);
            $category = match ($outcome['status']) {
                AccountGroupAdder::ADDED => 'added',
                AccountGroupAdder::CHANGED => 'changed',
                default => 'errors',
            };
            $row = ['id' => (int) $id, 'username' => $entry['username'], 'targets' => $entry['targets']];
            if ($outcome['status'] === AccountGroupAdder::ADDED) {
                $row['sessions'] = $outcome['sessions'];
            }
            $job[$category][] = $row;
            $this->session()->set(self::JOB, $job);
        }
        Toolkit::clearDashboardCache();
        if ($job['pending']) {
            return false;
        }
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, ['added' => $job['added'], 'changed' => $job['changed'],
            'errors' => $job['errors'], 'rows' => $job['rows']]);
        $this->session()->set(self::DRAFT, []);
        return true;
    }

    /**
     * Retry only session closure for an account whose memberships were saved in this result.
     * Never repeats User::save() or its action-log entries.
     *
     * @param array<string, mixed> $input
     */
    public function retrySessions(array $input): string
    {
        $result = $this->getResult();
        $id = (int) ($input['account'] ?? 0);
        if (!$result || $id < 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        foreach ($result['added'] as &$row) {
            if ((int) $row['id'] !== $id || ($row['sessions'] ?? '') === 'closed') {
                continue;
            }
            $state = Toolkit::regrouper()->closeSessions($id);
            $row['sessions'] = $state;
            unset($row);
            unset($result['actor'], $result['expires']);
            $this->storeResult(self::RESULT, $result);
            return $state;
        }
        unset($row);
        throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
    }

    /** @return array<string, mixed>|null */
    public function getJob(): ?array
    {
        $job = (array) $this->session()->get(self::JOB, []);
        return $job && ($job['actor'] ?? 0) === (int) $this->identity()->id ? $job : null;
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->validResult(self::RESULT);
    }

    /** @return list<array<string, mixed>> */
    private function plan(string $mode, string $lines, string $group): array
    {
        $directory = Toolkit::directory();
        return Plan::plan($directory->groups(), $directory->users(), $mode, $lines, $group, $this->operator());
    }
}
