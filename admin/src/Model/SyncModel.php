<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountGroupSyncer;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountMailer;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupSync;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Synchronises the memberships of one branch of groups from a list: one-time review, typed
 * confirmation, resumable batches with a checkpoint after each account, and a retry of the session
 * closing alone. New accounts get a link to choose their password; no password is ever kept.
 */
class SyncModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.sync.draft';
    private const PREVIEW = 'com_usertools.sync.preview';
    private const JOB = 'com_usertools.sync.job';
    private const RESULT = 'com_usertools.sync.result';

    /** @return array{root: int, mode: string, by: string, lines: string, complete: bool} */
    public function getDraft(): array
    {
        $d = (array) $this->session()->get(self::DRAFT, []);
        return ['root' => (int) ($d['root'] ?? 0), 'mode' => (string) ($d['mode'] ?? GroupSync::PARTIAL), 'by' => (string) ($d['by'] ?? GroupSync::BY_USERNAME),
            'lines' => (string) ($d['lines'] ?? ''), 'complete' => (bool) ($d['complete'] ?? false)];
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    public function canCreate(): bool
    {
        return (bool) $this->identity()->authorise('usertools.create', 'com_usertools');
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $draft = ['root' => (int) ($input['root'] ?? 0), 'mode' => (string) ($input['mode'] ?? ''), 'by' => (string) ($input['by'] ?? ''),
            'lines' => trim((string) ($input['lines'] ?? '')), 'complete' => (int) ($input['complete'] ?? 0) === 1];
        $this->session()->set(self::DRAFT, $draft);
        $this->session()->set(self::PREVIEW, []);
        if ($draft['lines'] === '' || \strlen($draft['lines']) > 1048576) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_EMPTY');
        }
        if ($draft['mode'] === GroupSync::FULL && !$draft['complete']) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_COMPLETE');
        }
        $plan = $this->plan($draft);
        $listed = array_filter($plan['rows'], static fn (array $row): bool => $row['line'] > 0);
        if (!$listed) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_EMPTY');
        }
        if ($draft['mode'] === GroupSync::FULL && !array_filter($listed, static fn (array $row): bool => !\in_array($row['status'], GroupSync::ERRORS, true))) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_EMPTY_FULL');
        }
        $this->session()->set(self::PREVIEW, $this->newPreview(['draft' => $draft, 'plan' => $plan]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * The plan is computed again: if the list, the branch, the groups, the accounts or the permission
     * to create changed since the preview, the review is withdrawn and has to be prepared again.
     *
     * @return array<string, mixed>
     */
    public function getReview(): array
    {
        $p = $this->getPreview();
        if (!$p) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($this->plan($p['draft']) !== $p['plan']) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_SYNC_CHANGED');
        }
        $rows = $p['plan']['rows'];
        $mode = $p['draft']['mode'];
        $creates = \count(array_filter($rows, static fn (array $row): bool => $row['status'] === GroupSync::CREATE));
        return $p + [
            'count'      => GroupSync::writes($rows),
            'errors'     => GroupSync::errors($rows),
            'blocked'    => GroupSync::blocked($mode, $rows),
            'massive'    => GroupSync::massive($rows, (int) $p['plan']['members']),
            'leaving'    => GroupSync::leaving($rows),
            'creates'    => $creates,
            'mailOnline' => AccountMailer::canSend(),
            'threshold'  => Toolkit::settings()->confirmThreshold(),
        ];
    }

    /** @param array<string, mixed> $input */
    public function apply(array $input): bool
    {
        $review = $this->getReview();
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        if (!hash_equals((string) $review['nonce'], (string) ($input['preview'] ?? '')) || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($review['blocked'] || $review['count'] === 0) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_BLOCKED');
        }
        if ($review['massive'] && (int) ($input['mass'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_SYNC_ERROR_MASS');
        }
        if ($review['creates'] > 0 && (!$this->canCreate() || !AccountMailer::canSend())) {
            throw new UserFacingException($this->canCreate() ? 'COM_USERTOOLS_ERROR_MAIL_OFFLINE' : 'COM_USERTOOLS_ERROR_CREATE_NOT_ALLOWED');
        }
        $this->checkTypedCount((int) $review['count'], $input);
        // Consumed before any write: a refresh or a replay never repeats the synchronisation.
        $this->session()->set(self::PREVIEW, []);
        $pending = [];
        foreach ($review['plan']['rows'] as $row) {
            if (\in_array($row['status'], GroupSync::WRITES, true)) {
                // No password, and for new accounts only what Joomla needs to create them.
                $pending[] = ['status' => $row['status'], 'id' => (int) $row['id'], 'username' => (string) $row['username'],
                    'email' => $row['status'] === GroupSync::CREATE ? (string) $row['email'] : '', 'name' => $row['status'] === GroupSync::CREATE ? (string) $row['name'] : '',
                    'before' => $row['before'], 'add' => $row['add'], 'remove' => $row['remove']];
            }
        }
        $this->session()->set(self::JOB, ['actor' => (int) $this->identity()->id, 'root' => (int) $review['draft']['root'], 'mode' => $review['draft']['mode'],
            'pending' => $pending, 'total' => \count($pending), 'saved' => [], 'created' => [], 'changed' => [], 'errors' => [],
            'unchanged' => \count(array_filter($review['plan']['rows'], static fn (array $r): bool => $r['status'] === GroupSync::UNCHANGED))]);
        $this->session()->set(self::DRAFT, []);
        return $this->step();
    }

    /**
     * Works for a few seconds (at least one account) and stores the job after each account, so a request
     * that is cut off never repeats an account when continuing. The branch is read again in each step:
     * a group that left it or now gives administrator access is not touched.
     */
    public function step(): bool
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $deadline = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + Toolkit::settings()->batchSeconds();
        $groups = Toolkit::directory()->groups();
        try {
            $managed = array_fill_keys(GroupSync::branch($groups, (int) $job['root'])['managed'], true);
        } catch (UserFacingException) {
            $managed = [];
        }
        $titles = array_map(static fn (object $g): string => (string) $g->title, $groups);
        $operator = $this->operator();
        $writer = new AccountGroupSyncer(Toolkit::users(), new ActionLogger());
        $count = 0;
        while ($job['pending'] && ($count === 0 || microtime(true) < $deadline)) {
            $entry = array_shift($job['pending']);
            $count++;
            if ($entry['status'] === GroupSync::CREATE) {
                $this->checkpoint($job);
                $this->create($job, $entry, $managed, $operator);
                continue;
            }
            $outcome = $writer->sync((int) $entry['id'], $entry['username'], $entry['before'], $entry['add'], $entry['remove'], $managed, $titles, $operator);
            $row = ['id' => (int) $entry['id'], 'username' => $entry['username'], 'add' => $entry['add'], 'remove' => $entry['remove'], 'leave' => $entry['status'] === GroupSync::LEAVE];
            if ($outcome['status'] === AccountGroupSyncer::SAVED) {
                $job['saved'][] = $row + ['sessions' => $outcome['sessions'], 'default' => $outcome['default']];
            } else {
                $job[$outcome['status'] === AccountGroupSyncer::CHANGED ? 'changed' : 'errors'][] = $row;
            }
            $this->checkpoint($job);
        }
        Toolkit::clearDashboardCache();
        if ($job['pending']) {
            return false;
        }
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, ['saved' => $job['saved'], 'created' => $job['created'], 'changed' => $job['changed'],
            'errors' => $job['errors'], 'unchanged' => $job['unchanged'], 'mode' => $job['mode'], 'root' => $job['root']]);
        return true;
    }

    /** Stops a pending job: what was done stays done; the accounts not reached yet are counted as not processed. */
    public function discard(): void
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, ['saved' => $job['saved'], 'created' => $job['created'], 'changed' => $job['changed'],
            'errors' => $job['errors'], 'unchanged' => $job['unchanged'], 'mode' => $job['mode'], 'root' => $job['root'], 'discarded' => \count($job['pending'])]);
        Toolkit::clearDashboardCache();
    }

    /**
     * Retries only the session closing of an account saved in this result; the save and its log are never repeated.
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
        foreach ($result['saved'] as $index => $row) {
            if ((int) $row['id'] === $id && ($row['sessions'] ?? '') !== 'closed') {
                $state = Toolkit::regrouper()->closeSessions($id);
                $result['saved'][$index]['sessions'] = $state;
                unset($result['actor'], $result['expires']);
                $this->storeResult(self::RESULT, $result);
                return $state;
            }
        }
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

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $entry
     * @param array<int, true>     $managed
     */
    private function create(array &$job, array $entry, array $managed, \Danieldiazi\Component\Usertools\Administrator\Service\Operator $operator): void
    {
        $row = ['id' => 0, 'username' => $entry['username'], 'add' => $entry['add'], 'remove' => [], 'leave' => false];
        $directory = Toolkit::directory();
        if (!$this->canCreate() || !AccountMailer::canSend() || array_diff($entry['add'], array_keys($managed))
            || $directory->exists('username', $entry['username']) || $directory->exists('email', $entry['email'])) {
            $job['changed'][] = $row;
            $this->checkpoint($job);
            return;
        }
        $factory = Toolkit::users();
        $mailer = new AccountMailer();
        $log = new ActionLogger();
        $core = $this->silenceCoreMail();
        try {
            $created = Registration::create([['username' => $entry['username'], 'email' => $entry['email'], 'name' => $entry['name'], 'groups' => $entry['add']]],
                fn () => $factory->loadUserById(0), GroupSync::DEFAULT_GROUP, Registration::MODE_EMAIL, Toolkit::passwords(),
                fn (object $user) => $mailer->send($user),
                function (object $user) use ($log, $operator, $entry, &$job, $row): void {
                    // The account exists: record it at once, so continuing after a cut-off request never creates it twice.
                    $job['created'][] = ['id' => (int) $user->id, 'mailed' => false] + $row;
                    $this->checkpoint($job);
                    $log->created($user, $operator, Registration::MODE_EMAIL);
                    foreach ($entry['add'] as $group) {
                        $log->groupAdded($user, $operator, (int) $group);
                    }
                });
        } finally {
            $core[0]->params->set('mail_to_user', $core[1]);
        }
        if ($created['created']) {
            $job['created'][\count($job['created']) - 1]['mailed'] = (bool) ($created['created'][0]['mailed'] ?? false);
        } else {
            $job['errors'][] = $row;
        }
        $this->checkpoint($job);
    }

    /** @param array<string, mixed> $job */
    private function checkpoint(array $job): void
    {
        $this->session()->set(self::JOB, $job);
    }

    /**
     * @param  array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private function plan(array $draft): array
    {
        $directory = Toolkit::directory();
        return GroupSync::plan($directory->groups(), $directory->users(), (int) $draft['root'], (string) $draft['mode'], (string) $draft['by'],
            (string) $draft['lines'], $this->operator(), $this->canCreate());
    }
}
