<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountAnonymizer;
use Danieldiazi\Component\Usertools\Administrator\Service\AnonymizeSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Anonymization of accounts chosen from a list, from inactivity or from a group: search, review and
 * select, anonymize. It cannot be undone, so the operator always types the number of accounts. Large
 * batches continue over several requests; every account is revalidated right before it is anonymized.
 */
class AnonymizeModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.anonymize.draft';
    private const PREVIEW = 'com_usertools.anonymize.preview';
    private const JOB     = 'com_usertools.anonymize.job';
    private const RESULT  = 'com_usertools.anonymize.result';

    /** Typed confirmation from the first account. */
    public const CONFIRM_THRESHOLD = 1;

    /** @return array{source: string, names: string, days: int, includeNever: bool, group: int, subgroups: bool, exclusions: Exclusions} */
    public function getDraft(): array
    {
        $settings = Toolkit::settings();
        $draft    = (array) $this->session()->get(self::DRAFT, []);
        $source   = (string) ($draft['source'] ?? AnonymizeSelection::SOURCE_LIST);
        return [
            'source'       => \in_array($source, AnonymizeSelection::SOURCES, true) ? $source : AnonymizeSelection::SOURCE_LIST,
            'names'        => (string) ($draft['names'] ?? ''),
            'days'         => (int) ($draft['days'] ?? $settings->anonymizeDays()),
            'includeNever' => (bool) ($draft['includeNever'] ?? $settings->anonymizeIncludeNever()),
            'group'        => (int) ($draft['group'] ?? 0),
            'subgroups'    => (bool) ($draft['subgroups'] ?? false),
            'retryCleanup' => (bool) ($draft['retryCleanup'] ?? false),
            'exclusions'   => isset($draft['exclusions']) ? Exclusions::fromArray($draft['exclusions']) : $settings->exclusions(Settings::ANONYMIZE),
        ];
    }

    /** Joomla's "Privacy - User Accounts" plugin does the anonymization; without it nothing would change. */
    public function pluginReady(): bool
    {
        return PluginHelper::isEnabled('privacy', 'user');
    }

    /** @param array<string, mixed> $input */
    public function search(array $input): void
    {
        $source = \in_array($input['source'] ?? '', AnonymizeSelection::SOURCES, true) ? (string) $input['source'] : AnonymizeSelection::SOURCE_LIST;
        $names  = (string) ($input['names'] ?? '');
        $days         = (int) ($input['days'] ?? 730);
        $includeNever = (int) ($input['include_never'] ?? 0) === 1;
        $group        = (int) ($input['group'] ?? 0);
        $subgroups    = (int) ($input['subgroups'] ?? 0) === 1;
        $retryCleanup = (int) ($input['retry_cleanup'] ?? 0) === 1;
        $exclusions   = $this->exclusionsFromInput($input);
        $this->session()->set(self::DRAFT, ['source' => $source, 'names' => $names, 'days' => $days, 'includeNever' => $includeNever,
            'group' => $group, 'subgroups' => $subgroups, 'retryCleanup' => $retryCleanup, 'exclusions' => $exclusions->toArray()]);
        $this->session()->set(self::PREVIEW, []);
        if (!$retryCleanup && !$this->pluginReady()) {
            throw new UserFacingException('COM_USERTOOLS_ANONYMIZE_ERROR_PLUGIN');
        }
        $cutoff = null;
        if ($source === AnonymizeSelection::SOURCE_INACTIVE) {
            $cutoff = InactivityPolicy::cutoff($days, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        } elseif ($source === AnonymizeSelection::SOURCE_LIST && !Comparison::parse($names)) {
            throw new UserFacingException('COM_USERTOOLS_ANONYMIZE_ERROR_NO_NAMES');
        } elseif ($source === AnonymizeSelection::SOURCE_GROUP && !isset(Toolkit::directory()->groups()[$group])) {
            throw new UserFacingException('COM_USERTOOLS_ANONYMIZE_ERROR_GROUP');
        }
        Toolkit::settingsStore()->save($exclusions->toArray(Settings::ANONYMIZE) + [
            Settings::ANONYMIZE . 'days'          => $days,
            Settings::ANONYMIZE . 'include_never' => (int) $includeNever,
        ]);
        $criteria = ['source' => $source, 'names' => Comparison::parse($names), 'cutoff' => $cutoff?->format('Y-m-d H:i:s'),
            'includeNever' => $includeNever, 'group' => $group, 'subgroups' => $subgroups, 'retryCleanup' => $retryCleanup];
        $found = $this->select($criteria, $exclusions);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'criteria'     => $criteria,
            'exclusions'   => $exclusions->toArray(),
            'fingerprints' => array_map(fn (UserRecord $u) => AnonymizeSelection::fingerprint($u), $found['candidates']),
            'ids'          => array_keys($found['candidates']),
            'selected'     => [],
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * @return array{candidates: list<UserRecord>, skipped: list<array{label: string, reason: string}>, criteria: array<string, mixed>,
     *     selected: list<int>, threshold: int, nonce: string}
     */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $found = $this->select($preview['criteria'], Exclusions::fromArray($preview['exclusions']));
        // Only the accounts of the search that have not changed since: never new ones.
        $fresh = array_filter($found['candidates'], fn (UserRecord $u) => ($preview['fingerprints'][$u->id] ?? '') === AnonymizeSelection::fingerprint($u));
        return [
            'candidates' => array_values($fresh),
            'skipped'    => $found['skipped'],
            'criteria'   => $preview['criteria'],
            'selected'   => array_map('intval', (array) $preview['selected']),
            'threshold'  => self::CONFIRM_THRESHOLD,
            'nonce'      => (string) $preview['nonce'],
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
     * Starts the anonymization of the selected accounts and runs the first batch.
     *
     * @param  array<string, mixed> $input
     */
    public function apply(array $input): bool
    {
        $preview             = $this->requirePreview($input);
        $selected            = InactiveSelection::decode((string) ($input['selected'] ?? '[]'), $preview['ids']);
        $preview['selected'] = $selected;
        $this->session()->set(self::PREVIEW, $preview);
        if (!$selected || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ANONYMIZE_ERROR_CONFIRM');
        }
        $this->checkTypedCount(\count($selected), $input, self::CONFIRM_THRESHOLD);
        // Consume the preview before any write: a refresh or replay cannot repeat the batch.
        $this->session()->set(self::PREVIEW, []);
        $this->session()->set(self::JOB, [
            'actor'      => (int) $this->identity()->id,
            'criteria'   => $preview['criteria'],
            'exclusions' => $preview['exclusions'],
            'pending'    => array_intersect_key($preview['fingerprints'], array_flip($selected)),
            'total'      => \count($selected),
            'done'       => [],
            'cleaned'    => [],
            'errors'     => [],
            'partial'    => [],
            'skipped'    => 0,
        ]);
        return $this->step();
    }

    /** Anonymizes the next accounts until the time budget runs out. Returns true when everything is done. */
    public function step(): bool
    {
        $job = $this->getJob();
        if ($job === null) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $exclusions = Exclusions::fromArray($job['exclusions']);
        $operator   = $this->operator();
        $current    = $this->select($job['criteria'], $exclusions)['candidates'];
        $anonymizer = Toolkit::anonymizer();
        $deadline   = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + Toolkit::settings()->batchSeconds();
        $count      = 0;
        foreach ($job['pending'] as $id => $fingerprint) {
            if ($count > 0 && microtime(true) >= $deadline) {
                break;
            }
            $count++;
            unset($job['pending'][$id]);
            $record = $current[(int) $id] ?? null;
            // An account that changed after the review (a new visit, other groups...) is skipped, never replaced.
            if ($record === null || AnonymizeSelection::fingerprint($record) !== $fingerprint) {
                $job['skipped']++;
            } else {
                // The same fingerprint again, on the User loaded right before writing: a change between this
                // batch's snapshot and the account's turn is caught too.
                $outcome = $anonymizer->anonymize((int) $id, $exclusions, $operator,
                    fn (object $user): bool => (empty($job['criteria']['retryCleanup']) ? !AnonymizeSelection::isAnonymized($user) : true) && AnonymizeSelection::fingerprint($user) === $fingerprint);
                if ($outcome['status'] === AccountAnonymizer::ANONYMIZED && !empty($job['criteria']['retryCleanup'])) {
                    $job['cleaned'][] = ['id' => (int) $id, 'username' => $outcome['username'],
                        'newName' => $outcome['newName'] ?? '', 'newUsername' => $outcome['newUsername'] ?? ''];
                } elseif ($outcome['status'] === AccountAnonymizer::ANONYMIZED) {
                    $job['done'][] = ['id' => (int) $id, 'username' => $outcome['username'],
                        'newName' => $outcome['newName'] ?? '', 'newUsername' => $outcome['newUsername'] ?? ''];
                } elseif ($outcome['status'] === AccountAnonymizer::PARTIAL) {
                    $job['partial'][] = ['id' => (int) $id, 'username' => $outcome['username'],
                        'newName' => $outcome['newName'] ?? '', 'newUsername' => $outcome['newUsername'] ?? '',
                        'message' => $outcome['reason']];
                } elseif (\in_array($outcome['status'], [AccountAnonymizer::ERROR, AccountAnonymizer::REFUSED], true)) {
                    $job['errors'][] = ['username' => $outcome['username'], 'message' => $outcome['reason']];
                } else {
                    $job['skipped']++;
                }
            }
            // Checkpoint after each account: an interrupted request never repeats or loses one.
            $this->session()->set(self::JOB, $job);
        }
        Toolkit::clearDashboardCache();
        if ($job['pending']) {
            return false;
        }
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, ['anonymized' => $job['done'], 'cleaned' => $job['cleaned'], 'retryCleanup' => (bool) ($job['criteria']['retryCleanup'] ?? false), 'errors' => $job['errors'], 'partial' => $job['partial'], 'skipped' => $job['skipped'], 'total' => $job['total']]);
        $this->session()->set(self::DRAFT, []);
        return true;
    }

    /** @return array<string, mixed>|null the batch in progress of the current user */
    public function getJob(): ?array
    {
        $job = (array) $this->session()->get(self::JOB, []);
        if (!$job || ($job['actor'] ?? 0) !== (int) $this->identity()->id) {
            return null;
        }
        return $job;
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

    /**
     * @param  array<string, mixed> $criteria
     * @return array{candidates: array<int, UserRecord>, skipped: list<array{label: string, reason: string}>}
     */
    private function select(array $criteria, Exclusions $exclusions): array
    {
        $directory = Toolkit::directory();
        return AnonymizeSelection::select($directory->users(), [
            'source'       => (string) $criteria['source'],
            'names'        => array_values(array_map('strval', (array) $criteria['names'])),
            'cutoff'       => $criteria['cutoff'] !== null ? new \DateTimeImmutable((string) $criteria['cutoff'], new \DateTimeZone('UTC')) : null,
            'includeNever' => (bool) $criteria['includeNever'],
            'group'        => (int) $criteria['group'],
            'subgroups'    => (bool) $criteria['subgroups'],
            'retryCleanup' => (bool) ($criteria['retryCleanup'] ?? false),
        ], $exclusions, $this->operator(), $directory->groups());
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
