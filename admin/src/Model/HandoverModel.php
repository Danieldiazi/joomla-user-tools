<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountProfileReader;
use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\ArticleReassigner;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentHandover;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentHandoverReader;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Handover assistant: inventory of the articles an account leaves, transfer of the chosen ones to
 * another account after a one-time review, resumable batches with a checkpoint per article, and a
 * closing step that inventories again and only then offers the block or anonymisation review.
 * Transferring never blocks, deletes or anonymises the account.
 */
class HandoverModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT = 'com_usertools.handover.draft';
    private const PREVIEW = 'com_usertools.handover.preview';
    private const JOB = 'com_usertools.handover.job';
    private const RESULT = 'com_usertools.handover.result';

    /** @return array{source: int, login: string, candidates: list<array<string, mixed>>, target: int, targetLogin: string, targetCandidates: list<array<string, mixed>>, selected: list<int>, page: int} */
    public function getDraft(): array
    {
        $d = (array) $this->session()->get(self::DRAFT, []);
        return ['source' => (int) ($d['source'] ?? 0), 'login' => (string) ($d['login'] ?? ''), 'candidates' => (array) ($d['candidates'] ?? []),
            'target' => (int) ($d['target'] ?? 0), 'targetLogin' => (string) ($d['targetLogin'] ?? ''), 'targetCandidates' => (array) ($d['targetCandidates'] ?? []),
            'selected' => array_map('intval', (array) ($d['selected'] ?? [])), 'page' => (int) ($d['page'] ?? 0)];
    }

    /** The account profile permission and managing articles are needed besides the tool's own permission. */
    public function assertAllowed(): void
    {
        $identity = $this->identity();
        if (!$identity->authorise('usertools.profile', 'com_usertools')) {
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_PROFILE');
        }
        if (!$identity->authorise('core.manage', 'com_content')) {
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_CONTENT');
        }
    }

    /** @param array<string, mixed> $input login and the chosen account ID */
    public function chooseSource(array $input): void
    {
        $this->assertAllowed();
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $login = trim((string) ($input['login'] ?? ''));
        $match = Toolkit::directory()->pick($login, (int) ($input['account'] ?? 0));
        $this->session()->set(self::PREVIEW, []);
        $this->session()->set(self::DRAFT, ['login' => $login]);
        if ($match['status'] === AccountIdentity::NONE) {
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_SOURCE_MISSING');
        }
        if ($match['status'] === AccountIdentity::AMBIGUOUS) {
            $this->session()->set(self::DRAFT, ['login' => $login, 'candidates' => $match['candidates']]);
            throw new UserFacingException('COM_USERTOOLS_ACCOUNT_AMBIGUOUS', [$login]);
        }
        $id = (int) $match['candidates'][0]['id'];
        $error = ContentHandover::sourceError(Toolkit::directory()->users([$id])[$id] ?? null, $this->operator());
        if ($error !== '') {
            throw new UserFacingException($error);
        }
        $this->session()->set(self::DRAFT, ['login' => $login, 'source' => $id]);
    }

    /**
     * What the account leaves: its articles (each editable or not by the operator, with its fingerprint),
     * how many there are in all, and other content types that the assistant does not transfer yet.
     *
     * One page of up to ContentHandover::LIMIT articles, so articles beyond the first ones (for example
     * after many that cannot be edited) can always be reached.
     *
     * @return array{source: UserRecord, articles: list<array<string, mixed>>, total: int, others: list<array<string, mixed>>, listUrl: string, page: int, pages: int}
     */
    public function getInventory(int $page = 0): array
    {
        $this->assertAllowed();
        $source = $this->source();
        $identity = $this->identity();
        $levels = $identity->authorise('core.admin') ? null : array_values(array_map('intval', $identity->getAuthorisedViewLevels()));
        $reader = new ContentHandoverReader(Toolkit::db());
        $operator = $this->operator();
        $articles = [];
        $total = $reader->count($source->id, $levels);
        $pages = max(1, (int) ceil($total / ContentHandover::LIMIT));
        $page = min(max(0, $page), $pages - 1);
        foreach ($reader->articles($source->id, $levels, ContentHandover::LIMIT, $page * ContentHandover::LIMIT) as $article) {
            $article['fingerprint'] = ContentHandover::fingerprint($article);
            $article['lockedBy'] = \in_array($article['checked_out'], [0, $operator->id], true) ? 0 : $article['checked_out'];
            $article['editable'] = $article['lockedBy'] === 0 && $identity->authorise('core.edit', 'com_content.article.' . $article['id']);
            $articles[] = $article;
        }
        $others = array_values(array_filter((new AccountProfileReader(Toolkit::db()))->content($source->id,
            fn (string $component): bool => (bool) $identity->authorise('core.manage', $component), $levels),
            static fn (array $row): bool => $row['kind'] !== 'articles' && $row['count'] > 0));
        return ['source' => $source, 'articles' => $articles, 'total' => $total, 'others' => $others, 'page' => $page, 'pages' => $pages,
            'listUrl' => \Danieldiazi\Component\Usertools\Administrator\Service\AccountProfile::articleUrl($source->id)];
    }

    /** @param array<string, mixed> $input target login and account ID, chosen article IDs */
    public function preview(array $input): void
    {
        $this->assertAllowed();
        if ($this->getJob()) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_PENDING');
        }
        $draft = $this->getDraft();
        $login = trim((string) ($input['login'] ?? ''));
        $selected = array_values(array_unique(array_map('intval', (array) ($input['articles'] ?? []))));
        $this->session()->set(self::PREVIEW, []);
        $page = max(0, (int) ($input['page'] ?? 0));
        $this->session()->set(self::DRAFT, ['targetLogin' => $login, 'selected' => $selected, 'target' => 0, 'targetCandidates' => [], 'page' => $page] + $draft);
        $inventory = $this->getInventory($page);
        $match = Toolkit::directory()->pick($login, (int) ($input['account'] ?? 0));
        if ($match['status'] === AccountIdentity::AMBIGUOUS) {
            $this->session()->set(self::DRAFT, ['targetLogin' => $login, 'selected' => $selected, 'target' => 0, 'targetCandidates' => $match['candidates'], 'page' => $page] + $draft);
            throw new UserFacingException('COM_USERTOOLS_ACCOUNT_AMBIGUOUS', [$login]);
        }
        $targetId = $match['status'] === AccountIdentity::ONE ? (int) $match['candidates'][0]['id'] : 0;
        $error = ContentHandover::targetError($inventory['source'], Toolkit::directory()->users([$targetId])[$targetId] ?? null);
        if ($error !== '') {
            throw new UserFacingException($error);
        }
        $chosen = ContentHandover::selection($inventory['articles'], $selected);
        $this->session()->set(self::DRAFT, ['targetLogin' => $login, 'selected' => $selected, 'target' => $targetId, 'targetCandidates' => [], 'page' => $page] + $draft);
        $this->session()->set(self::PREVIEW, $this->newPreview(['source' => $inventory['source']->id, 'target' => $targetId, 'page' => $inventory['page'],
            'articles' => $this->reviewed($chosen), 'inventory' => array_column($inventory['articles'], 'id')]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        return $this->validPreview(self::PREVIEW);
    }

    /**
     * Computed again from fresh data: if the accounts or a reviewed article changed, or the operator can
     * no longer edit one, the review is withdrawn and has to be prepared again.
     *
     * @return array<string, mixed>
     */
    public function getReview(): array
    {
        $this->assertAllowed();
        $p = $this->getPreview();
        if (!$p) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $inventory = $this->getInventory((int) ($p['page'] ?? 0));
        $records = Toolkit::directory()->users([(int) $p['target']]);
        $target = $records[(int) $p['target']] ?? null;
        try {
            if ($inventory['source']->id !== (int) $p['source'] || ContentHandover::targetError($inventory['source'], $target) !== ''
                || $this->reviewed(ContentHandover::selection($inventory['articles'], array_column($p['articles'], 'id'))) !== $p['articles']) {
                throw new UserFacingException('COM_USERTOOLS_HANDOVER_CHANGED');
            }
        } catch (UserFacingException) {
            $this->session()->set(self::PREVIEW, []);
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_CHANGED');
        }
        /** @var UserRecord $target */
        $fresh = Toolkit::users()->loadUserById($target->id);
        $groups = Toolkit::directory()->groups();
        return $p + [
            'sourceRecord' => $inventory['source'],
            'targetRecord' => $target,
            'targetGroups' => array_map(static fn (int $id): string => (string) ($groups[$id]->title ?? ('#' . $id)), $target->groups),
            'editOwn'      => (bool) $fresh->authorise('core.edit.own', 'com_content'),
            'count'        => \count($p['articles']),
            'threshold'    => Toolkit::settings()->confirmThreshold(),
            'listUrl'      => $inventory['listUrl'],
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
        $this->checkTypedCount((int) $review['count'], $input);
        // Consumed before any write: a refresh or a replay never repeats the transfer.
        $this->session()->set(self::PREVIEW, []);
        $this->session()->set(self::JOB, ['actor' => (int) $this->identity()->id, 'source' => (int) $review['source'], 'target' => (int) $review['target'],
            'pending' => $review['articles'], 'total' => \count($review['articles']), 'transferred' => [], 'changed' => [], 'errors' => [],
            'selected' => array_column($review['articles'], 'id'), 'inventory' => $review['inventory']]);
        return $this->step();
    }

    /**
     * Works for a few seconds (at least one article) and stores the job after each article. The target
     * account is checked again in every step: if it disappeared or was blocked, nothing else is transferred.
     */
    public function step(): bool
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        // Every batch needs the same permissions as the review: a withdrawn one stops the job until it is granted again or discarded.
        $this->assertAllowed();
        $deadline = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + Toolkit::settings()->batchSeconds();
        $identity = $this->identity();
        $operator = $this->operator();
        $records = Toolkit::directory()->users([(int) $job['source'], (int) $job['target']]);
        $source = $records[(int) $job['source']] ?? null;
        $targetOk = $source !== null && ContentHandover::targetError($source, $records[(int) $job['target']] ?? null) === ''
            && $identity->authorise('usertools.reassign', 'com_usertools') && $identity->authorise('core.manage', 'com_content');
        $writer = new ArticleReassigner(new ActionLogger());
        $count = 0;
        while ($job['pending'] && ($count === 0 || microtime(true) < $deadline || !$targetOk)) {
            $article = array_shift($job['pending']);
            $count++;
            $status = $targetOk ? $writer->reassign((int) $article['id'], (string) $article['fingerprint'], (int) $job['source'], (int) $job['target'], $operator,
                fn (int $id): bool => (bool) $identity->authorise('core.edit', 'com_content.article.' . $id)) : ContentHandover::CHANGED;
            $key = match ($status) {
                ContentHandover::TRANSFERRED => 'transferred',
                ContentHandover::CHANGED => 'changed',
                default => 'errors',
            };
            $job[$key][] = ['id' => (int) $article['id'], 'title' => (string) $article['title']];
            $this->checkpoint($job);
        }
        if ($job['pending']) {
            return false;
        }
        $this->finish($job, 0);
        return true;
    }

    /** Stops a pending job: what was transferred stays transferred; the rest is left with the source account. */
    public function discard(): void
    {
        $job = $this->getJob();
        if (!$job) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JOB_MISSING');
        }
        $this->finish($job, \count($job['pending']));
    }

    /** @return array<string, mixed>|null */
    public function getJob(): ?array
    {
        $job = (array) $this->session()->get(self::JOB, []);
        return $job && ($job['actor'] ?? 0) === (int) $this->identity()->id ? $job : null;
    }

    /**
     * The stored result plus a fresh inventory of what the account still authors: articles of the
     * selection that were not transferred keep the handover pending, and articles created after the
     * review are reported as new. The closing tools are only offered when nothing chosen is pending.
     *
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        $result = $this->validResult(self::RESULT);
        if (!$result) {
            return null;
        }
        $closing = ContentHandover::closing((new ContentHandoverReader(Toolkit::db()))->authored((int) $result['source']),
            array_map('intval', $result['inventory']), array_map('intval', $result['selected']));
        $records = Toolkit::directory()->users([(int) $result['source'], (int) $result['target']]);
        $source = $records[(int) $result['source']] ?? null;
        $actions = ['block' => false, 'anonymize' => false];
        if ($source !== null && !$closing['pending']) {
            $actions = array_intersect_key(ProfileModel::actionsFor($source, $this->identity()), $actions);
        }
        return $result + ['closing' => $closing, 'sourceRecord' => $source, 'targetRecord' => $records[(int) $result['target']] ?? null,
            'actions' => $actions, 'canDelete' => (bool) $this->identity()->authorise('usertools.delete', 'com_usertools')];
    }

    /** @param array<string, mixed> $job */
    private function finish(array $job, int $discarded): void
    {
        $this->session()->set(self::JOB, []);
        $this->storeResult(self::RESULT, ['source' => $job['source'], 'target' => $job['target'], 'transferred' => $job['transferred'],
            'changed' => $job['changed'], 'errors' => $job['errors'], 'discarded' => $discarded, 'selected' => $job['selected'], 'inventory' => $job['inventory']]);
        $this->session()->set(self::DRAFT, []);
    }

    private function source(): UserRecord
    {
        $id = $this->getDraft()['source'];
        $source = $id > 0 ? (Toolkit::directory()->users([$id])[$id] ?? null) : null;
        $error = ContentHandover::sourceError($source, $this->operator());
        if ($error !== '') {
            throw new UserFacingException($error);
        }
        /** @var UserRecord $source */
        return $source;
    }

    /**
     * The fields kept for the review and the job: no content, only what identifies and shows each article.
     *
     * @param  list<array<string, mixed>> $articles
     * @return list<array{id: int, title: string, state: int, category: string, alias: string, fingerprint: string}>
     */
    private function reviewed(array $articles): array
    {
        return array_map(static fn (array $a): array => ['id' => (int) $a['id'], 'title' => (string) $a['title'], 'state' => (int) $a['state'],
            'category' => (string) $a['category'], 'alias' => (string) $a['alias'], 'fingerprint' => (string) $a['fingerprint']], $articles);
    }

    /** @param array<string, mixed> $job */
    private function checkpoint(array $job): void
    {
        $this->session()->set(self::JOB, $job);
    }
}
