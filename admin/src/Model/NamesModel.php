<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountNameUpdater;
use Danieldiazi\Component\Usertools\Administrator\Service\BulkNameUpdates as Plan;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Review and apply one-use batches that update only account display names. */
class NamesModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.names.draft';
    private const PREVIEW = 'com_usertools.names.preview';
    private const JOB = 'com_usertools.names.job';
    private const RESULT = 'com_usertools.names.result';

    public function getDraft(): string
    {
        return (string) $this->session()->get(self::DRAFT, '');
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $lines = is_string($input['lines'] ?? null) ? $input['lines'] : '';
        $this->session()->set(self::DRAFT, $lines);
        $this->session()->set(self::PREVIEW, []);
        if (trim($lines) === '' || count(preg_split('/\R/u', $lines) ?: []) > 1000) {
            throw new UserFacingException('COM_USERTOOLS_NAMES_INVALID_INPUT');
        }
        $rows = $this->plan($lines);
        if (!$rows) {
            throw new UserFacingException('COM_USERTOOLS_NAMES_INVALID_INPUT');
        }
        $selected = [];
        foreach ($rows as $index => $row) {
            if ($row['status'] === Plan::UPDATE) {
                $selected[] = $index;
            }
        }
        $this->session()->set(self::PREVIEW, $this->newPreview(['rows' => $rows, 'selected' => $selected]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /** @return array<string, mixed> */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($this->plan($this->getDraft()) !== $preview['rows']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_NAMES_CHANGED');
        }
        return $preview;
    }

    /** @param array<string, mixed> $input */
    public function apply(array $input): bool
    {
        $preview = $this->getReview();
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        if (!hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))
            || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }

        // A failed/missing script must never submit the initially checked rows as consent.
        if ((int) ($input['selection_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }

        $eligible = [];
        foreach ($preview['rows'] as $index => $row) {
            if ($row['status'] === Plan::UPDATE) {
                $eligible[(string) $index] = $index;
            }
        }
        $selected = [];
        $rawSelected = $input['selected'] ?? '[]';
        $submitted = is_array($rawSelected) ? $rawSelected : json_decode((string) $rawSelected, true);
        foreach (is_array($submitted) ? $submitted : [] as $value) {
            if (!is_int($value) && !is_string($value)) {
                continue;
            }
            $key = (string) $value;
            if (isset($eligible[$key])) {
                $selected[] = $eligible[$key];
            }
        }
        $selected = array_values(array_unique($selected));
        $preview['selected'] = $selected;
        $this->session()->set(self::PREVIEW, $preview);
        if (!$selected) {
            throw new UserFacingException('COM_USERTOOLS_NAMES_NONE_SELECTED');
        }
        $pending = [];
        foreach ($selected as $index) {
            $pending[] = $preview['rows'][$index];
        }
        $this->session()->set(self::PREVIEW, []);
        $this->session()->set(self::JOB, ['actor' => (int) $this->identity()->id, 'pending' => $pending,
            'updated' => [], 'changed' => [], 'errors' => []]);
        return $this->step();
    }

    /** Apply at most 20 rows per request and persist each result before moving on. */
    public function step(): bool
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $writer = new AccountNameUpdater(Toolkit::users(), new \Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger());
        for ($processed = 0; $processed < 20 && $job['pending']; $processed++) {
            $row = array_shift($job['pending']);
            $outcome = $writer->update($row['id'], $row['username'], $row['before'], $row['after'], $this->operator());
            $status = match ($outcome['status']) {
                AccountNameUpdater::UPDATED => 'updated',
                AccountNameUpdater::CHANGED => 'changed',
                default => 'errors',
            };
            $job[$status][] = $row;
            $this->session()->set(self::JOB, $job);
        }
        if ($job['pending']) {
            return false;
        }
        $this->session()->set(self::JOB, []);
        Toolkit::clearDashboardCache();
        $this->storeResult(self::RESULT, ['updated' => $job['updated'], 'changed' => $job['changed'], 'errors' => $job['errors']]);
        $this->session()->set(self::DRAFT, '');
        return true;
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

    /** @return list<array{line: int, username: string, id: int, before: string, after: string, status: string}> */
    private function plan(string $lines): array
    {
        $usernames = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $separator = strpos($line, ';');
            if ($separator !== false) {
                $username = trim(substr($line, 0, $separator));
                if ($username !== '') {
                    $usernames[] = $username;
                }
            }
        }
        $directory = Toolkit::directory();
        return Plan::plan($lines, $directory->usersByUsername($usernames), $this->operator());
    }
}
