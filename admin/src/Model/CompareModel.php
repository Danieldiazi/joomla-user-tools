<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountMailer;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
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
     * Applies the reviewed changes. The returned result may contain one-time passwords:
     * they are never stored, the session keeps a redacted copy.
     *
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apply(array $input): array
    {
        $preview = $this->requirePreview($input);
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
        $operator   = $this->operator();
        $exclusions = $draft['exclusions'];
        $result     = ['blocked' => [], 'created' => [], 'errors' => [], 'skipped' => 0];
        $fresh      = $this->calculate($preview['names'], $exclusions);
        $offered    = array_fill_keys($preview['ids'], true);
        $blocker    = Toolkit::blocker();
        foreach ($fresh['candidates'] as $candidate) {
            if (!isset($offered[(int) $candidate->id])) {
                continue;
            }
            $outcome = $blocker->block((int) $candidate->id, $exclusions, $operator,
                fn (object $user) => !Comparison::contains($preview['names'], (string) $user->username), 'COM_USERTOOLS_TOOL_COMPARE');
            $this->collect($result, $outcome, (int) $candidate->id);
        }
        $result['skipped'] = \count($preview['ids']) - \count($result['blocked']) - \count($result['errors']);
        if ($selection) {
            $created = $this->create($selection, $mode, $operator);
            $result['created'] = $created['created'];
            $result['errors']  = array_merge($result['errors'], $created['errors']);
        }
        $result['mode'] = $mode;
        $this->storeResult(self::RESULT, $result);
        $this->session()->set(self::DRAFT, []);
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
     * @param  list<array{username: string, email: string}> $selection
     * @return array{created: list<array<string, mixed>>, errors: list<array{username: string, message: string}>}
     */
    private function create(array $selection, string $mode, Operator $operator): array
    {
        $app     = Toolkit::app();
        $factory = Toolkit::users();
        $mailer  = new AccountMailer();
        // The core user plugin would email the generated password; this tool sends its own link instead.
        PluginHelper::importPlugin('user');
        $core = $app->bootPlugin('joomla', 'user');
        if (!$core instanceof CMSPlugin) {
            throw new \LogicException('The core user plugin is not available.');
        }
        $before = $core->params->get('mail_to_user', 1);
        $core->params->set('mail_to_user', 0);
        try {
            $result = Registration::create($selection, fn () => $factory->loadUserById(0), self::REGISTERED_GROUP, $mode,
                Toolkit::passwords(), fn (object $user) => $mailer->send($user));
        } finally {
            $core->params->set('mail_to_user', $before);
        }
        $log = new ActionLogger();
        foreach ($result['created'] as $row) {
            $log->created((object) ['id' => $row['id'], 'username' => $row['username']], $operator, $mode);
        }
        return $result;
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
