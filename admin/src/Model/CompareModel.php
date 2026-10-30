<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountMailer;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;

/** Manual comparison of a roster of usernames: preview, review and apply. */
class CompareModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.compare.draft';
    private const PREVIEW = 'com_usertools.compare.preview';
    private const RESULT  = 'com_usertools.compare.result';
    private const JOB     = 'com_usertools.compare.job';
    private const REGISTERED_GROUP = 2;

    /** @return array{text: string, checked: array<string, string>, emails: array<string, string>, mode: string, exclusions: Exclusions} */
    public function getDraft(): array
    {
        $settings = Toolkit::settings();
        $draft    = (array) $this->session()->get(self::DRAFT, []);
        return [
            'text'       => (string) ($draft['text'] ?? ''),
            'checked'    => (array) ($draft['checked'] ?? []),
            'emails'     => (array) ($draft['emails'] ?? []),
            'mode'       => Registration::mode($draft['mode'] ?? $settings->creationMode()),
            'exclusions' => isset($draft['exclusions']) ? Exclusions::fromArray($draft['exclusions']) : $settings->exclusions(Settings::COMPARE),
        ];
    }

    /** @param array<string, mixed> $input */
    public function saveExclusions(array $input): Exclusions
    {
        $exclusions = $this->exclusionsFromInput($input);
        $this->storeDraft(['text' => (string) ($input['names'] ?? ''), 'exclusions' => $exclusions->toArray()]);
        Toolkit::settingsStore()->save($exclusions->toArray(Settings::COMPARE));
        $this->session()->set(self::PREVIEW, []);
        return $exclusions;
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        $exclusions = $this->saveExclusions($input);
        $names      = Comparison::parse((string) ($input['names'] ?? ''));
        if (!$names) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_LIST_EMPTY');
        }
        $comparison = $this->calculate($names, $exclusions);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'names'      => $names,
            'ids'        => array_map(fn ($u) => (int) $u->id, $comparison['candidates']),
            'unknown'    => array_map('strval', $comparison['unknown']),
            'signature'  => $this->signature($exclusions),
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        $preview = $this->validPreview(self::PREVIEW);
        if ($preview && ($preview['signature'] ?? '') !== $this->signature(Toolkit::settings()->exclusions(Settings::COMPARE))) {
            $this->session()->set(self::PREVIEW, []);
            return null;
        }
        return $preview;
    }

    /**
     * Fresh data for the review screen, limited to what the preview offered.
     *
     * @return array{candidates: list<UserRecord>, unknown: list<string>, matched: int, protected: int, blocked: int, threshold: int, mode: string, mailOnline: bool, canCreate: bool}
     */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $draft      = $this->getDraft();
        $comparison = $this->calculate($preview['names'], $draft['exclusions']);
        $offered    = array_fill_keys($preview['ids'], true);
        $comparison['candidates'] = array_values(array_filter($comparison['candidates'], fn ($u) => isset($offered[(int) $u->id])));
        $this->assertReviewedCandidates($preview, $comparison['candidates']);
        $comparison['unknown']    = array_values(array_intersect($preview['unknown'], array_map('strval', $comparison['unknown'])));
        return $comparison + [
            'threshold'  => Toolkit::settings()->confirmThreshold(),
            'mode'       => $draft['mode'],
            'mailOnline' => AccountMailer::canSend(),
            'canCreate'  => $this->identity()->authorise('usertools.create', 'com_usertools'),
        ];
    }

    /** @param array<string, mixed> $input */
    public function back(array $input): void
    {
        $preview = $this->requirePreview($input);
        $this->storeDraft(Registration::draft((string) ($input['registrations'] ?? '{}'), $preview['unknown'])
            + ['mode' => Registration::mode($input['creation_mode'] ?? null)]);
        $this->session()->set(self::PREVIEW, []);
    }

    /**
     * Applies the reviewed changes. The preview is consumed and the accounts to block and create
     * become a job in the session; the first batch runs in this request (see step()).
     *
     * @param  array<string, mixed> $input
     * @return array<string, mixed> see step()
     */
    public function apply(array $input): array
    {
        $preview = $this->requirePreview($input);
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $mode    = Registration::mode($input['creation_mode'] ?? null);
        $this->storeDraft(Registration::draft((string) ($input['registrations'] ?? '{}'), $preview['unknown']) + ['mode' => $mode]);
        if ((int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_CONFIRM');
        }
        // Revalidate the offered set before validating the count or performing any write.
        $review = $this->getReview();
        $this->checkTypedCount(\count($review['candidates']), $input);
        $draft      = $this->getDraft();
        $directory  = Toolkit::directory();
        $selection  = Registration::select(
            $preview['unknown'],
            $draft['checked'],
            $draft['emails'],
            fn (string $email) => $directory->exists('email', $email),
            fn (string $name) => $directory->exists('username', $name),
        );
        if ($selection) {
            if (!$this->identity()->authorise('usertools.create', 'com_usertools')) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_CREATE_NOT_ALLOWED');
            }
            if ($mode === Registration::MODE_EMAIL && !AccountMailer::canSend()) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_MAIL_OFFLINE');
            }
            $this->assertRegisteredGroup();
        }
        // Consume the preview before any write: a refresh or replay cannot repeat the batch.
        $this->session()->set(self::PREVIEW, []);
        $exclusions = $draft['exclusions'];
        $fresh      = $this->calculate($preview['names'], $exclusions);
        $offered    = array_fill_keys($preview['ids'], true);
        $block      = [];
        foreach ($fresh['candidates'] as $candidate) {
            if (isset($offered[(int) $candidate->id])) {
                $block[] = (int) $candidate->id;
            }
        }
        // Only usernames and emails are kept: no password is ever stored with the job.
        $this->session()->set(self::JOB, [
            'actor'      => (int) $this->identity()->id,
            'mode'       => $mode,
            'names'      => $preview['names'],
            'exclusions' => $exclusions->toArray(),
            'offered'    => \count($preview['ids']),
            'block'      => $block,
            'create'     => $selection,
            'total'      => \count($block) + \count($selection),
            'processed'  => 0,
            'blocked'    => [],
            'created'    => [],
            'errors'     => [],
        ]);
        $this->session()->set(self::DRAFT, []);
        return $this->step();
    }

    /**
     * The pending job of this operator, if a comparison is being applied.
     *
     * @return array<string, mixed>|null
     */
    public function getJob(): ?array
    {
        $job = (array) $this->session()->get(self::JOB, []);
        if (!$job) {
            return null;
        }
        if (($job['actor'] ?? 0) !== (int) $this->identity()->id) {
            $this->session()->set(self::JOB, []);
            return null;
        }
        return $job;
    }

    /**
     * Processes the job for a few seconds (at least one account), so no request runs into PHP's or
     * the web server's time limit. Each account is revalidated, changed and logged on its own, so an
     * interrupted request loses nothing already done and the rest can still be continued or discarded.
     * One-time passwords appear only in the return value of the step that created them.
     *
     * @return array{done: bool, total: int, processed: int, blocked: list<array<string, mixed>>, created: list<array<string, mixed>>,
     *     errors: list<array<string, mixed>>, result: array<string, mixed>|null}
     */
    public function step(): array
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        // Counted from the start of the request: in apply() the checks before the first batch use part of it.
        $deadline   = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + Toolkit::settings()->batchSeconds();
        $operator   = $this->operator();
        $exclusions = Exclusions::fromArray($job['exclusions']);
        $names      = $job['names'];
        $step       = ['blocked' => [], 'created' => [], 'errors' => []];
        $count      = 0;

        if ($job['block']) {
            $blocker = Toolkit::blocker();
            while ($job['block'] && $this->more($count, $deadline)) {
                $id      = (int) $job['block'][0];
                $outcome = $blocker->block($id, $exclusions, $operator,
                    fn (object $user) => !Comparison::contains($names, (string) $user->username), 'COM_USERTOOLS_TOOL_COMPARE');
                $one = ['blocked' => [], 'errors' => []];
                $this->collect($one, $outcome, $id);
                array_shift($job['block']);
                $job['blocked']     = array_merge($job['blocked'], $one['blocked']);
                $job['errors']      = array_merge($job['errors'], $one['errors']);
                $job['blockErrors'] = (int) ($job['blockErrors'] ?? 0) + \count($one['errors']);
                $job['processed']++;
                $this->checkpoint($job);
                $step['blocked'] = array_merge($step['blocked'], $one['blocked']);
                $step['errors']  = array_merge($step['errors'], $one['errors']);
                $count++;
            }
        }
        if ($job['create'] && $this->more($count, $deadline)) {
            // The permission may have been withdrawn while the job was paused: what is left waits, or is discarded.
            if (!$this->identity()->authorise('usertools.create', 'com_usertools')) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_CREATE_REVOKED');
            }
            // The same for the target group: it must still exist and give no administrator access.
            $this->assertRegisteredGroup();
            $factory = Toolkit::users();
            $mailer  = new AccountMailer();
            $log     = new ActionLogger();
            $mode    = (string) $job['mode'];
            $core    = $this->silenceCoreMail();
            try {
                while ($job['create'] && $this->more($count, $deadline)) {
                    $row     = $job['create'][0];
                    $created = Registration::create([$row], fn () => $factory->loadUserById(0), self::REGISTERED_GROUP, $mode,
                        Toolkit::passwords(), fn (object $user) => $mailer->send($user),
                        function (object $user) use ($log, $operator, $mode, &$job): void {
                            // The account exists: take it off the queue at once, so a request cut off now
                            // (a slow mail server, a time limit) never creates it twice when continuing.
                            array_shift($job['create']);
                            $job['processed']++;
                            $job['created'][] = ['username' => (string) $user->username, 'email' => (string) $user->email, 'id' => (int) $user->id,
                                'mode' => $mode, 'mailed' => false];
                            $this->checkpoint($job);
                            $log->created($user, $operator, $mode);
                        });
                    if (!$created['created']) {
                        // Not created: nothing exists to repeat, so it leaves the queue as a failure.
                        array_shift($job['create']);
                        $job['processed']++;
                    } else {
                        // Now the email outcome is known; the stored row never has the password.
                        $stored = $created['created'][0];
                        unset($stored['password']);
                        $job['created'][\count($job['created']) - 1] = $stored;
                    }
                    $job['errors'] = array_merge($job['errors'], $created['errors']);
                    $this->checkpoint($job);
                    $step['created'] = array_merge($step['created'], $created['created']);
                    $step['errors']  = array_merge($step['errors'], $created['errors']);
                    $count++;
                }
            } finally {
                $core[0]->params->set('mail_to_user', $core[1]);
            }
        }

        $done   = !$job['block'] && !$job['create'];
        $result = null;
        if ($done) {
            $result = $this->finish($job, 0);
            // The response that created them may show this step's passwords; the stored copy never has them.
            $withPasswords = [];
            foreach ($step['created'] as $row) {
                $withPasswords[(int) $row['id']] = $row;
            }
            $result['created'] = array_map(fn (array $row) => $withPasswords[(int) $row['id']] ?? $row, $result['created']);
        }
        Toolkit::clearDashboardCache();
        return $step + ['done' => $done, 'total' => (int) $job['total'], 'processed' => (int) $job['processed'], 'result' => $result];
    }

    /**
     * Stops a pending job: what was done stays done (and logged); the accounts not reached yet are
     * left as they are and counted in the result.
     */
    public function discard(): void
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $this->finish($job, \count($job['block']) + \count($job['create']));
        Toolkit::clearDashboardCache();
    }

    /**
     * @param  array<string, mixed> $job
     * @return array<string, mixed> the result, also stored (without passwords) for the result screen
     */
    private function finish(array $job, int $discarded): array
    {
        $result = [
            'blocked'   => $job['blocked'],
            'created'   => $job['created'],
            'errors'    => $job['errors'],
            // Offered accounts that were not blocked nor failed: protected or changed since the review.
            'skipped'   => max(0, (int) $job['offered'] - \count($job['blocked']) - (int) ($job['blockErrors'] ?? 0) - \count($job['block'])),
            'discarded' => $discarded,
            'mode'      => $job['mode'],
            'batch'     => $this->recordBatch('COM_USERTOOLS_TOOL_COMPARE', 'COM_USERTOOLS_UNBLOCK_REASON_NOT_LISTED', $job['blocked']),
        ];
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, $result);
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

    /**
     * @param  list<string> $names
     * @return array{candidates: list<UserRecord>, unknown: list<string>, matched: int, protected: int, blocked: int}
     */
    private function calculate(array $names, Exclusions $exclusions): array
    {
        $users     = Toolkit::directory()->users();
        $operator  = $this->operator();
        $protected = [];
        foreach ($users as $user) {
            if ($exclusions->protects($user, $operator)) {
                $protected[] = $user->id;
            }
        }
        return Comparison::compare($users, $names, $exclusions->names, $protected);
    }

    /**
     * Saves the job after each account, so a request that is cut off half-way (a time limit, a slow mail
     * server) keeps everything done so far and continuing never repeats an account.
     *
     * @param array<string, mixed> $job
     */
    private function checkpoint(array $job): void
    {
        $this->session()->set(self::JOB, $job);
    }

    /** At least one account per request, so a job always moves forward; then until the time budget runs out. */
    private function more(int $done, float $deadline): bool
    {
        return $done === 0 || microtime(true) < $deadline;
    }

    /**
     * The core user plugin would email the generated password; this tool sends its own link instead.
     *
     * @return array{0: CMSPlugin, 1: mixed} the plugin and its setting, to restore afterwards
     */
    private function silenceCoreMail(): array
    {
        PluginHelper::importPlugin('user');
        $core = Toolkit::app()->bootPlugin('joomla', 'user');
        if (!$core instanceof CMSPlugin) {
            throw new \LogicException('The core user plugin is not available.');
        }
        $before = $core->params->get('mail_to_user', 1);
        $core->params->set('mail_to_user', 0);
        return [$core, $before];
    }

    /**
     * A changed offered set requires a new comparison, so the typed count always matches the review.
     * Newly eligible accounts are not added to an existing preview.
     *
     * @param array<string, mixed> $preview
     * @param list<UserRecord> $candidates
     */
    private function assertReviewedCandidates(array $preview, array $candidates): void
    {
        $offered = array_map('intval', $preview['ids']);
        $current = array_map(static fn (UserRecord $user): int => $user->id, $candidates);
        sort($offered);
        sort($current);
        if ($offered !== $current) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_ERROR_REVIEW_CHANGED');
        }
    }

    private function assertRegisteredGroup(): void
    {
        $group = Toolkit::directory()->groups()[self::REGISTERED_GROUP] ?? null;
        if (!$group || $group->backend) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTERED_GROUP');
        }
    }

    /** @param array<string, mixed> $values */
    private function storeDraft(array $values): void
    {
        $this->session()->set(self::DRAFT, array_replace((array) $this->session()->get(self::DRAFT, []), $values));
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
        if ((int) ($input['review_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }
        return $preview;
    }
}
