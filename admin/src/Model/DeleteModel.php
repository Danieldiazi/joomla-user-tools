<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Manual deletion of inactive accounts: search, select and delete.
 * Deletion cannot be undone, so the operator always types the number of accounts.
 */
class DeleteModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.delete.draft';
    private const PREVIEW = 'com_usertools.delete.preview';
    private const RESULT  = 'com_usertools.delete.result';

    /** Typed confirmation from the first account. */
    public const CONFIRM_THRESHOLD = 1;

    /** @return array{days: int, includeNever: bool, status: string, exclusions: Exclusions} */
    public function getDraft(): array
    {
        $settings = Toolkit::settings();
        $draft    = (array) $this->session()->get(self::DRAFT, []);
        return [
            'days'         => (int) ($draft['days'] ?? $settings->deleteDays()),
            'includeNever' => (bool) ($draft['includeNever'] ?? $settings->deleteIncludeNever()),
            'status'       => (string) ($draft['status'] ?? $settings->deleteStatus()),
            'exclusions'   => isset($draft['exclusions']) ? Exclusions::fromArray($draft['exclusions']) : $settings->exclusions(Settings::DELETE),
        ];
    }

    /** @param array<string, mixed> $input */
    public function search(array $input): void
    {
        $days         = (int) ($input['days'] ?? 730);
        $includeNever = (int) ($input['include_never'] ?? 0) === 1;
        $status       = \in_array($input['status'] ?? '', Settings::STATUSES, true) ? (string) $input['status'] : Settings::DEFAULT_STATUS;
        $exclusions   = $this->exclusionsFromInput($input);
        $this->session()->set(self::DRAFT, ['days' => $days, 'includeNever' => $includeNever, 'status' => $status, 'exclusions' => $exclusions->toArray()]);
        $cutoff = InactivityPolicy::cutoff($days, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        Toolkit::settingsStore()->save($exclusions->toArray(Settings::DELETE) + [
            Settings::DELETE . 'days'          => $days,
            Settings::DELETE . 'include_never' => (int) $includeNever,
            Settings::DELETE . 'status'        => $status,
        ]);
        $previous   = (array) $this->session()->get(self::PREVIEW, []);
        $candidates = $this->candidates($exclusions, $cutoff, $includeNever, $status);
        $ids        = array_map(fn (UserRecord $u) => $u->id, $candidates);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'cutoff'       => $cutoff->format('Y-m-d H:i:s'),
            'includeNever' => $includeNever,
            'status'       => $status,
            'exclusions'   => $exclusions->toArray(),
            'signature'    => $this->signature($exclusions, [$days, $includeNever, $status]),
            'ids'          => $ids,
            'selected'     => array_values(array_intersect((array) ($previous['selected'] ?? []), $ids)),
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        $preview  = $this->validPreview(self::PREVIEW);
        $settings = Toolkit::settings();
        $current  = $this->signature($settings->exclusions(Settings::DELETE), [$settings->deleteDays(), $settings->deleteIncludeNever(), $settings->deleteStatus()]);
        if ($preview && ($preview['signature'] ?? '') !== $current) {
            $this->session()->set(self::PREVIEW, []);
            return null;
        }
        return $preview;
    }

    /** @return array{candidates: list<UserRecord>, cutoff: string, status: string, selected: list<int>, threshold: int, nonce: string} */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $offered = array_fill_keys($preview['ids'], true);
        $fresh   = $this->candidates(Exclusions::fromArray($preview['exclusions']), new \DateTimeImmutable($preview['cutoff'], new \DateTimeZone('UTC')),
            (bool) $preview['includeNever'], (string) $preview['status']);
        return [
            'candidates' => array_values(array_filter($fresh, fn (UserRecord $u) => isset($offered[$u->id]))),
            'cutoff'     => $preview['cutoff'],
            'status'     => $preview['status'],
            'selected'   => array_map('intval', (array) $preview['selected']),
            'threshold'  => self::CONFIRM_THRESHOLD,
            'nonce'      => $preview['nonce'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function back(array $input): void
    {
        $preview             = $this->requirePreview($input);
        $preview['selected'] = InactiveSelection::decode((string) ($input['selected'] ?? '[]'), $preview['ids']);
        $this->session()->set(self::PREVIEW, $preview);
    }

    /**
     * Deletes the selected accounts that still meet every criterion. Accounts that changed after the
     * review (a new visit, a new exclusion...) are skipped, never replaced by others.
     *
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function delete(array $input): array
    {
        $preview             = $this->requirePreview($input);
        $selected            = InactiveSelection::decode((string) ($input['selected'] ?? '[]'), $preview['ids']);
        $preview['selected'] = $selected;
        $this->session()->set(self::PREVIEW, $preview);
        if (!$selected || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_DELETE_CONFIRM');
        }
        $this->checkTypedCount(\count($selected), $input, self::CONFIRM_THRESHOLD);
        // Consume the preview before any write: a refresh or replay cannot repeat the batch.
        $this->session()->set(self::PREVIEW, []);
        $exclusions   = Exclusions::fromArray($preview['exclusions']);
        $cutoff       = new \DateTimeImmutable($preview['cutoff'], new \DateTimeZone('UTC'));
        $includeNever = (bool) $preview['includeNever'];
        $status       = (string) $preview['status'];
        $operator     = $this->operator();
        $fresh        = array_map(fn (UserRecord $u) => $u->id, $this->candidates($exclusions, $cutoff, $includeNever, $status));
        $eligible     = InactiveSelection::eligibleIds($selected, $preview['ids'], $fresh);
        $result       = ['deleted' => [], 'errors' => [], 'skipped' => 0];
        $deleter      = Toolkit::deleter();
        $directory    = Toolkit::directory();
        $window       = Toolkit::sessionWindow();
        foreach ($eligible as $id) {
            $outcome = $deleter->delete($id, $exclusions, $operator, fn (object $user) => InactivityPolicy::matchesStatus($user, $status)
                && InactivityPolicy::eligible($user, $cutoff, $includeNever, true)
                && !$directory->withRecentSession([$id], time() - $window));
            if ($outcome['status'] === 'deleted') {
                $result['deleted'][] = ['id' => $id, 'username' => $outcome['username'], 'name' => $outcome['name']];
            } elseif ($outcome['status'] === 'error') {
                $result['errors'][] = ['username' => $outcome['username'], 'message' => $outcome['reason']];
            }
        }
        $result['skipped'] = \count($selected) - \count($result['deleted']) - \count($result['errors']);
        $this->storeResult(self::RESULT, $result);
        Toolkit::clearDashboardCache();
        return $result;
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

    /** @return list<UserRecord> */
    private function candidates(Exclusions $exclusions, \DateTimeImmutable $cutoff, bool $includeNever, string $status): array
    {
        $directory = Toolkit::directory();
        $operator  = $this->operator();
        $matches   = [];
        foreach ($directory->users() as $user) {
            if (InactivityPolicy::matchesStatus($user, $status) && InactivityPolicy::eligible($user, $cutoff, $includeNever, true)
                && !$exclusions->protects($user, $operator)) {
                $matches[$user->id] = $user;
            }
        }
        $active = $directory->withRecentSession(array_keys($matches), time() - Toolkit::sessionWindow());
        return array_values(array_diff_key($matches, $active));
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function requirePreview(array $input): array
    {
        $preview = $this->getPreview();
        if (!$preview || !hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ((int) ($input['selection_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }
        return $preview;
    }
}
