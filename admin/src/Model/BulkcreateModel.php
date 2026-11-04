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
use Danieldiazi\Component\Usertools\Administrator\Service\BulkRegistrationList;
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Standalone bulk creation, without any block/update path.
 * The review binds the roster, names, groups, operator, nonce and expiry.
 * The resumable job stores no passwords, and checkpoints immediately after each creation.
 */
class BulkcreateModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.bulkcreate.draft';
    private const PREVIEW = 'com_usertools.bulkcreate.preview';
    private const JOB = 'com_usertools.bulkcreate.job';
    private const RESULT = 'com_usertools.bulkcreate.result';
    private const REGISTERED = 2;

    /** @return array{text: string, mode: string} */
    public function getDraft(): array
    {
        $draft = (array) $this->session()->get(self::DRAFT, []);
        return ['text' => (string) ($draft['text'] ?? ''),
            'mode' => Registration::mode($draft['mode'] ?? Toolkit::settings()->creationMode())];
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input): void
    {
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $text = (string) ($input['lines'] ?? '');
        $this->assertRegistered();
        $rows = BulkRegistrationList::parse($text, Toolkit::directory()->groups(),
            Toolkit::directory()->users(),
            (bool) $this->identity()->authorise('usertools.addgroups', 'com_usertools'));
        $this->session()->set(self::DRAFT, ['text' => $text, 'mode' => Toolkit::settings()->creationMode()]);
        $this->session()->set(self::PREVIEW, $this->newPreview(['rows' => $rows]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /** @return array{rows: list<array<string, mixed>>, mode: string, threshold: int, mailOnline: bool} */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        return [
            'rows' => $preview['rows'],
            'mode' => $this->getDraft()['mode'],
            'threshold' => Toolkit::settings()->confirmThreshold(),
            'mailOnline' => AccountMailer::canSend(),
        ];
    }

    /**
     * Only offered/valid rows can be selected; the requested names/groups always come
     * from the server-owned preview. The client can only choose rows and enter emails.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apply(array $input): array
    {
        $preview = $this->getPreview();
        if (!$preview || !hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        if ((int) ($input['review_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }
        if ((int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_CONFIRM');
        }
        $mode = Registration::mode($input['creation_mode'] ?? null);
        if ($mode === Registration::MODE_EMAIL && !AccountMailer::canSend()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_MAIL_OFFLINE');
        }
        $this->assertRegistered();
        $ready = array_values(array_filter($preview['rows'],
            static fn (array $row): bool => $row['status'] === BulkRegistrationList::READY));
        $allowed = array_column($ready, 'username');
        $draft = Registration::draft((string) ($input['registrations'] ?? '{}'), $allowed);
        $chosen = [];
        foreach ($ready as $row) {
            $key = Registration::key($row['username']);
            if (!isset($draft['checked'][$key])) {
                continue;
            }
            $chosen[] = $row + ['email' => trim((string) ($draft['emails'][$key] ?? ''))];
        }
        if (!$chosen) {
            throw new UserFacingException('COM_USERTOOLS_BULKCREATE_ERROR_NO_SELECTION');
        }
        $this->checkTypedCount(\count($chosen), $input);
        $used = [];
        $directory = Toolkit::directory();
        $groups = $directory->groups();
        foreach ($chosen as $row) {
            $email = $row['email'];
            if (!BulkRegistrationList::validEmail($email)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_EMAIL_INVALID', [$row['username']]);
            }
            $key = Comparison::normalise($email);
            if (isset($used[$key]) || $directory->exists('email', $email)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_EMAIL_IN_USE', [$row['username']]);
            }
            $used[$key] = true;
            if ($row['group'] && (!$this->identity()->authorise('usertools.addgroups', 'com_usertools')
                || !BulkRegistrationList::safeGroup($groups, (int) $row['group'])
                || BulkRegistrationList::fingerprint($groups[(int) $row['group']]) !== $row['fingerprint'])) {
                throw new UserFacingException('COM_USERTOOLS_BULKCREATE_ERROR_GROUP_CHANGED', [$row['username']]);
            }
        }
        // The irreversible one-use decision is consumed before the first write.
        $this->session()->set(self::PREVIEW, []);
        $job = [
            'actor' => (int) $this->identity()->id,
            'mode' => $mode,
            'create' => $chosen,
            'total' => \count($chosen),
            'processed' => 0,
            'created' => [],
            'errors' => [],
            'skipped' => [],
        ];
        $this->session()->set(self::JOB, $job);
        return $this->step();
    }

    /** @return array<string, mixed>|null */
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
     * One bounded batch; calls the existing Joomla registration service.
     * @return array{done: bool, total: int, processed: int, created: list<array<string, mixed>>, errors: list<array<string, mixed>>, result: ?array}
     */
    public function step(): array
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        if (!$this->identity()->authorise('usertools.create', 'com_usertools')) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_CREATE_REVOKED');
        }
        $this->assertRegistered();
        $deadline = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + Toolkit::settings()->batchSeconds();
        $factory = Toolkit::users();
        $mailer = new AccountMailer();
        $logger = new ActionLogger();
        $operator = $this->operator();
        $mode = (string) $job['mode'];
        $step = ['created' => [], 'errors' => []];
        $count = 0;
        $core = $this->silenceCoreMail();
        try {
            while ($job['create'] && ($count === 0 || microtime(true) < $deadline)) {
                $row = $job['create'][0];
                $directory = Toolkit::directory();
                $groups = $directory->groups();
                $reason = '';
                if ($directory->exists('username', (string) $row['username'])) {
                    $reason = 'COM_USERTOOLS_BULKCREATE_SKIP_EXISTS';
                } elseif ($directory->exists('email', (string) $row['email'])) {
                    $reason = 'COM_USERTOOLS_BULKCREATE_SKIP_EMAIL';
                } elseif ($row['group'] && (!$this->identity()->authorise('usertools.addgroups', 'com_usertools')
                    || !BulkRegistrationList::safeGroup($groups, (int) $row['group'])
                    || BulkRegistrationList::fingerprint($groups[(int) $row['group']]) !== $row['fingerprint'])) {
                    $reason = 'COM_USERTOOLS_BULKCREATE_SKIP_GROUP';
                }
                if ($reason !== '') {
                    array_shift($job['create']);
                    $job['processed']++;
                    $job['skipped'][] = ['username' => $row['username'], 'message' => $reason];
                    $this->session()->set(self::JOB, $job);
                    $count++;
                    continue;
                }
                $selection = [[
                    'username' => $row['username'], 'name' => $row['name'],
                    'email' => $row['email'],
                    'groups' => $row['group'] > 0 ? [(int) $row['group']] : [],
                ]];
                $outcome = Registration::create($selection, fn () => $factory->loadUserById(0),
                    self::REGISTERED, $mode, Toolkit::passwords(), fn (object $user) => $mailer->send($user),
                    function (object $user) use (&$job, $logger, $operator, $mode): void {
                        // Checkpoint before email sending. Never save temporary passwords.
                        array_shift($job['create']);
                        $job['processed']++;
                        $job['created'][] = ['id' => (int) $user->id, 'username' => (string) $user->username,
                            'email' => (string) $user->email, 'mode' => $mode, 'mailed' => false];
                        $this->session()->set(self::JOB, $job);
                        $logger->created($user, $operator, $mode);
                    });
                if (!$outcome['created']) {
                    array_shift($job['create']);
                    $job['processed']++;
                } else {
                    $stored = $outcome['created'][0];
                    unset($stored['password']);
                    $job['created'][\count($job['created']) - 1] = $stored;
                }
                $job['errors'] = array_merge($job['errors'], $outcome['errors']);
                $step['created'] = array_merge($step['created'], $outcome['created']);
                $step['errors'] = array_merge($step['errors'], $outcome['errors']);
                $this->session()->set(self::JOB, $job);
                $count++;
            }
        } finally {
            $core[0]->params->set('mail_to_user', $core[1]);
        }
        $done = !$job['create'];
        $result = null;
        if ($done) {
            $result = $this->finish($job, 0);
            $withPasswords = [];
            foreach ($step['created'] as $row) {
                $withPasswords[(int) $row['id']] = $row;
            }
            $result['created'] = array_map(static fn (array $row): array =>
                $withPasswords[(int) $row['id']] ?? $row, $result['created']);
        }
        Toolkit::clearDashboardCache();
        return $step + ['done' => $done, 'total' => (int) $job['total'],
            'processed' => (int) $job['processed'], 'result' => $result];
    }

    public function discard(): void
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $this->finish($job, \count($job['create']));
        Toolkit::clearDashboardCache();
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->validResult(self::RESULT);
    }

    private function finish(array $job, int $discarded): array
    {
        $result = [
            'created' => $job['created'],
            'errors' => $job['errors'],
            'skipped' => $job['skipped'],
            'discarded' => $discarded,
            'mode' => $job['mode'],
        ];
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, $result);
        return $result;
    }

    private function assertRegistered(): void
    {
        $group = Toolkit::directory()->groups()[self::REGISTERED] ?? null;
        if (!$group || $group->backend || $group->superUser) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_REGISTERED_GROUP');
        }
    }
}
