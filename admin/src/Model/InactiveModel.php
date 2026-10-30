<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountProfile;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Manual inactivity review: search, select and suspend. */
class InactiveModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.inactive.draft';
    private const PREVIEW = 'com_usertools.inactive.preview';
    private const RESULT  = 'com_usertools.inactive.result';

    /** Explicit single-account review from a profile, independent of inactivity filters. */
    public function reviewAccount(int $id): void
    {
        if (!$this->identity()->authorise('usertools.profile', 'com_usertools')) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_ACTION_UNAVAILABLE');
        }
        $account = Toolkit::directory()->users([$id])[$id] ?? null;
        $exclusions = Toolkit::settings()->exclusions(Settings::INACTIVE);
        if (!$account || $account->block !== 0 || $exclusions->protects($account, $this->operator())) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_ACTION_UNAVAILABLE');
        }
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'account' => $id, 'accountState' => AccountProfile::fingerprint($account),
            'cutoff' => '', 'includeNever' => false, 'exclusions' => $exclusions->toArray(),
            'signature' => $this->signature($exclusions, ['account', $id]), 'ids' => [$id], 'selected' => [$id],
        ]));
    }

    /** @return array{days: int, includeNever: bool, exclusions: Exclusions} */
    public function getDraft(): array
    {
        $settings = Toolkit::settings();
        $draft    = (array) $this->session()->get(self::DRAFT, []);
        return [
            'days'         => (int) ($draft['days'] ?? $settings->inactiveDays()),
            'includeNever' => (bool) ($draft['includeNever'] ?? $settings->inactiveIncludeNever()),
            'exclusions'   => isset($draft['exclusions']) ? Exclusions::fromArray($draft['exclusions']) : $settings->exclusions(Settings::INACTIVE),
        ];
    }

    /** @param array<string, mixed> $input */
    public function search(array $input): void
    {
        $days         = (int) ($input['days'] ?? 365);
        $includeNever = (int) ($input['include_never'] ?? 0) === 1;
        $exclusions   = $this->exclusionsFromInput($input);
        $this->session()->set(self::DRAFT, ['days' => $days, 'includeNever' => $includeNever, 'exclusions' => $exclusions->toArray()]);
        $cutoff = InactivityPolicy::cutoff($days, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        Toolkit::settingsStore()->save($exclusions->toArray(Settings::INACTIVE)
            + [Settings::INACTIVE . 'days' => $days, Settings::INACTIVE . 'include_never' => (int) $includeNever]);
        $previous   = (array) $this->session()->get(self::PREVIEW, []);
        $candidates = $this->candidates($exclusions, $cutoff, $includeNever);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'cutoff'       => $cutoff->format('Y-m-d H:i:s'),
            'includeNever' => $includeNever,
            'exclusions'   => $exclusions->toArray(),
            'signature'    => $this->signature($exclusions, [$days, $includeNever]),
            'ids'          => array_map(fn (UserRecord $u) => $u->id, $candidates),
            'selected'     => array_values(array_intersect((array) ($previous['selected'] ?? []), array_map(fn (UserRecord $u) => $u->id, $candidates))),
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        $preview = $this->validPreview(self::PREVIEW);
        $settings = Toolkit::settings();
        $single = (int) ($preview['account'] ?? 0) > 0;
        $current = $this->signature($settings->exclusions(Settings::INACTIVE), $single
            ? ['account', (int) $preview['account']] : [$settings->inactiveDays(), $settings->inactiveIncludeNever()]);
        if ($preview && (($preview['signature'] ?? '') !== $current
            || ($single && !$this->identity()->authorise('usertools.profile', 'com_usertools')))) {
            $this->session()->set(self::PREVIEW, []);
            return null;
        }
        return $preview;
    }

    /** @return array{candidates: list<UserRecord>, cutoff: string, selected: list<int>, threshold: int, nonce: string, singleAccount: int} */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $cutoff  = new \DateTimeImmutable($preview['cutoff'] ?: 'now', new \DateTimeZone('UTC'));
        $offered = array_fill_keys($preview['ids'], true);
        $fresh = !empty($preview['account']) ? $this->accountCandidates($preview)
            : $this->candidates(Exclusions::fromArray($preview['exclusions']), $cutoff, (bool) $preview['includeNever']);
        return [
            'candidates' => array_values(array_filter($fresh, fn (UserRecord $u) => isset($offered[$u->id]))),
            'cutoff'     => $preview['cutoff'],
            'selected'   => array_map('intval', (array) $preview['selected']),
            'threshold'  => Toolkit::settings()->confirmThreshold(),
            'nonce'      => $preview['nonce'],
            'singleAccount' => (int) ($preview['account'] ?? 0),
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
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function suspend(array $input): array
    {
        $preview             = $this->requirePreview($input);
        $selected            = InactiveSelection::decode((string) ($input['selected'] ?? '[]'), $preview['ids']);
        $preview['selected'] = $selected;
        $this->session()->set(self::PREVIEW, $preview);
        if (!$selected || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_SUSPEND_CONFIRM');
        }
        $this->checkTypedCount(\count($selected), $input);
        $this->session()->set(self::PREVIEW, []);
        $exclusions   = Exclusions::fromArray($preview['exclusions']);
        $cutoff       = new \DateTimeImmutable($preview['cutoff'] ?: 'now', new \DateTimeZone('UTC'));
        $includeNever = (bool) $preview['includeNever'];
        $operator     = $this->operator();
        $single = !empty($preview['account']);
        $fresh = array_map(fn (UserRecord $u) => $u->id, $single ? $this->accountCandidates($preview)
            : $this->candidates($exclusions, $cutoff, $includeNever));
        $eligible     = InactiveSelection::eligibleIds($selected, $preview['ids'], $fresh);
        $result       = ['blocked' => [], 'errors' => [], 'skipped' => 0];
        $blocker      = Toolkit::blocker();
        $directory    = Toolkit::directory();
        $window       = Toolkit::sessionWindow();
        foreach ($eligible as $id) {
            $outcome = $blocker->block($id, $exclusions, $operator,
                fn (object $user) => $single ? AccountProfile::fingerprint($user) === $preview['accountState']
                    : InactivityPolicy::eligible($user, $cutoff, $includeNever)
                        && !$directory->withRecentSession([$id], time() - $window),
                $single ? 'COM_USERTOOLS_PROFILE_TITLE' : 'COM_USERTOOLS_TOOL_INACTIVE');
            $this->collect($result, $outcome, $id);
        }
        $result['skipped'] = \count($selected) - \count($result['blocked']) - \count($result['errors']);
        $result['batch']   = $this->recordBatch('COM_USERTOOLS_TOOL_INACTIVE', 'COM_USERTOOLS_REASON_INACTIVE', $result['blocked']);
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
    private function candidates(Exclusions $exclusions, \DateTimeImmutable $cutoff, bool $includeNever): array
    {
        $directory = Toolkit::directory();
        $operator  = $this->operator();
        $matches   = [];
        foreach ($directory->users() as $user) {
            if (InactivityPolicy::eligible($user, $cutoff, $includeNever) && !$exclusions->protects($user, $operator)) {
                $matches[$user->id] = $user;
            }
        }
        $active = $directory->withRecentSession(array_keys($matches), time() - Toolkit::sessionWindow());
        return array_values(array_diff_key($matches, $active));
    }

    /**
     * @param array<string, mixed> $preview
     * @return list<UserRecord>
     */
    private function accountCandidates(array $preview): array
    {
        $id = (int) $preview['account'];
        $account = Toolkit::directory()->users([$id])[$id] ?? null;
        return $account && $account->block === 0
            && !Exclusions::fromArray($preview['exclusions'])->protects($account, $this->operator())
            && AccountProfile::fingerprint($account) === $preview['accountState'] ? [$account] : [];
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
