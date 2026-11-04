<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\SsoAccountConverter;
use Danieldiazi\Component\Usertools\Administrator\Service\SsoConversionPlan;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Manual conversion of accounts with requireReset=1 to SSO credentials.
 * The job contains only reviewed account fingerprints, no password/hash or MFA data.
 */
class SsoModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.sso.draft';
    private const PREVIEW = 'com_usertools.sso.preview';
    private const JOB = 'com_usertools.sso.job';
    private const RESULT = 'com_usertools.sso.result';

    /** @return array{group: int, subgroups: bool, status: string, visits: string} */
    public function getDraft(): array
    {
        $data = (array) $this->session()->get(self::DRAFT, []);
        return SsoConversionPlan::filters($data, Toolkit::directory()->groups());
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** @param array<string, mixed> $input */
    public function search(array $input): void
    {
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $directory = Toolkit::directory();
        $groups = $directory->groups();
        $filters = SsoConversionPlan::filters($input, $groups);
        $db = Toolkit::db();
        $query = $db->getQuery(true)->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('requireReset') . ' = 1')
            ->order($db->quoteName('id'));
        $ids = array_map('intval', $db->setQuery($query)->loadColumn());
        if (\count($ids) > SsoConversionPlan::MAX_ACCOUNTS) {
            throw new UserFacingException('COM_USERTOOLS_SSO_TOO_MANY', [SsoConversionPlan::MAX_ACCOUNTS]);
        }
        $rows = SsoConversionPlan::candidates($directory->users($ids), $groups, $filters, $this->operator());
        $this->session()->set(self::DRAFT, $filters);
        $this->session()->set(self::PREVIEW, $this->newPreview(['rows' => $rows, 'filters' => $filters]));
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
        return $preview + ['threshold' => Toolkit::settings()->confirmThreshold()];
    }

    /** @param array<string, mixed> $input */
    public function apply(array $input): bool
    {
        $preview = $this->getPreview();
        if (!$preview || !hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        if ((int) ($input['selection_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }
        $selected = InactiveSelection::decode((string) ($input['selected'] ?? '[]'),
            array_column($preview['rows'], 'id'));
        if ((int) ($input['confirm'] ?? 0) !== 1 || !$selected) {
            throw new UserFacingException('COM_USERTOOLS_SSO_CONFIRM_REQUIRED');
        }
        $this->checkTypedCount(\count($selected), $input);
        $chosen = array_fill_keys($selected, true);
        $pending = [];
        foreach ($preview['rows'] as $row) {
            if (isset($chosen[$row['id']])) {
                $pending[] = $row;
            }
        }
        // The preview is consumed before the first credential change, preventing replay.
        $this->session()->set(self::PREVIEW, []);
        $this->session()->set(self::JOB, [
            'actor' => (int) $this->identity()->id,
            'pending' => $pending,
            'total' => \count($pending),
            'processed' => 0,
            'unselected' => \count($preview['rows']) - \count($pending),
            'converted' => [], 'changed' => [], 'errors' => [],
        ]);
        return $this->step();
    }

    public function step(): bool
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        if (!$this->identity()->authorise('usertools.sso', 'com_usertools')) {
            throw new UserFacingException('COM_USERTOOLS_SSO_PERMISSION_REVOKED');
        }
        $converter = new SsoAccountConverter(Toolkit::users(), Toolkit::passwords(), new ActionLogger());
        $operator = $this->operator();
        // Small, bounded and re-entrant batches. Every completed account is checkpointed.
        for ($done = 0; $done < 20 && $job['pending']; $done++) {
            $row = $job['pending'][0];
            $result = $converter->convert($row, $operator);
            array_shift($job['pending']);
            $job['processed']++;
            $key = match ($result['status']) {
                SsoAccountConverter::CONVERTED => 'converted',
                SsoAccountConverter::CHANGED => 'changed',
                default => 'errors',
            };
            $job[$key][] = $result;
            $this->session()->set(self::JOB, $job);
        }
        Toolkit::clearDashboardCache();
        if ($job['pending']) {
            return false;
        }
        $this->finish($job);
        return true;
    }

    /** Stop without changing any account still pending. */
    public function discard(): void
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $job['discarded'] = \count($job['pending']);
        $this->finish($job);
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

    /** @param array<string, mixed> $job */
    private function finish(array $job): void
    {
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, [
            'total' => $job['total'], 'processed' => $job['processed'],
            'converted' => $job['converted'], 'changed' => $job['changed'],
            'errors' => $job['errors'], 'unselected' => $job['unselected'],
            'discarded' => (int) ($job['discarded'] ?? 0),
        ]);
        Toolkit::clearDashboardCache();
    }
}
