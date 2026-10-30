<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountUnblocker;
use Danieldiazi\Component\Usertools\Administrator\Service\BlockBatch;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * "Undo blocks": choose a batch (a scheduled task run or a manual review), review which of its
 * accounts are still blocked and unchanged, and unblock the ticked ones.
 */
class UnblockModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    public const LIST_SIZE = 20;

    /** Reports read per page while looking for batches that blocked accounts. */
    private const PAGE_SIZE = 100;

    private const PREVIEW = 'com_usertools.unblock.preview';
    private const RESULT  = 'com_usertools.unblock.result';

    /**
     * Recent batches that blocked at least one account, newest first.
     *
     * @return list<array{ref: string, kind: string, label: string, started_at: string, blocked: int}>
     */
    public function getBatches(): array
    {
        $reports = Toolkit::reports();
        $manual  = Toolkit::batches();
        $batches = [];
        foreach (BlockBatch::withBlocks(fn (int $n): array => $reports->page($n, self::PAGE_SIZE, ['mode' => 'real']), self::PAGE_SIZE, self::LIST_SIZE) as $report) {
            $batches[] = $this->summary(BlockBatch::KIND_TASK, (string) $report['id'], $report);
        }
        foreach (BlockBatch::withBlocks(fn (int $n): array => $manual->page($n, self::PAGE_SIZE), self::PAGE_SIZE, self::LIST_SIZE) as $batch) {
            $batches[] = $this->summary(BlockBatch::KIND_MANUAL, (string) $batch['id'], $batch);
        }
        usort($batches, static fn (array $a, array $b): int => strcmp($b['started_at'], $a['started_at']));
        return \array_slice($batches, 0, self::LIST_SIZE);
    }

    /** Opens the review of one batch, with every account that can still be unblocked ticked. */
    public function select(string $reference): void
    {
        ['kind' => $kind, 'id' => $id] = BlockBatch::parse($reference);
        $batch = $kind === BlockBatch::KIND_TASK ? Toolkit::reports()->get($id) : Toolkit::batches()->get($id);
        if ($batch === null) {
            throw new UserFacingException('COM_USERTOOLS_UNBLOCK_ERROR_BATCH');
        }
        $rows = BlockBatch::blockedRows($batch);
        if (!$rows) {
            throw new UserFacingException('COM_USERTOOLS_UNBLOCK_ERROR_EMPTY');
        }
        $summary  = $this->summary($kind, $id, $batch);
        $eligible = array_keys($this->classify($rows)['candidates']);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'ref'        => $summary['ref'],
            'label'      => $summary['label'],
            'started_at' => $summary['started_at'],
            'rows'       => $rows,
            'ids'        => array_map(static fn (array $row): int => $row['id'], $rows),
            'selected'   => $eligible,
        ]));
    }

    /**
     * @return array{label: string, started_at: string, candidates: list<array{row: array<string, mixed>, user: UserRecord}>,
     *               skipped: list<array{row: array<string, mixed>, reason: string}>, selected: list<int>, threshold: int, nonce: string}
     */
    public function getReview(): array
    {
        $preview = $this->validPreview(self::PREVIEW);
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $classified = $this->classify($preview['rows']);
        return [
            'label'      => (string) $preview['label'],
            'started_at' => (string) $preview['started_at'],
            'candidates' => array_values($classified['candidates']),
            'skipped'    => $classified['skipped'],
            'selected'   => array_values(array_intersect(array_map('intval', (array) $preview['selected']), array_keys($classified['candidates']))),
            'threshold'  => Toolkit::settings()->confirmThreshold(),
            'nonce'      => (string) $preview['nonce'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function back(array $input): void
    {
        $this->session()->set(self::PREVIEW, []);
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apply(array $input): array
    {
        $preview = $this->validPreview(self::PREVIEW);
        if (!$preview || !hash_equals((string) $preview['nonce'], (string) ($input['preview'] ?? ''))) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        if ((int) ($input['selection_ready'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_JAVASCRIPT');
        }
        $selected            = InactiveSelection::decode((string) ($input['selected'] ?? '[]'), $preview['ids']);
        $preview['selected'] = $selected;
        $this->session()->set(self::PREVIEW, $preview);
        if (!$selected || (int) ($input['confirm'] ?? 0) !== 1) {
            throw new UserFacingException('COM_USERTOOLS_UNBLOCK_ERROR_CONFIRM');
        }
        $this->checkTypedCount(\count($selected), $input);
        // The review is consumed before the first write, so it can never be applied twice.
        $this->session()->set(self::PREVIEW, []);
        $rows      = array_column($preview['rows'], null, 'id');
        $operator  = $this->operator();
        $unblocker = Toolkit::unblocker();
        $result    = ['label' => $preview['label'], 'unblocked' => [], 'skipped' => [], 'errors' => []];
        foreach ($selected as $id) {
            $outcome = $unblocker->unblock($rows[$id], $operator, (string) $preview['label']);
            if ($outcome['status'] === AccountUnblocker::UNBLOCKED) {
                $result['unblocked'][] = ['id' => $id, 'username' => $outcome['username']];
            } elseif ($outcome['status'] === AccountUnblocker::ERROR) {
                $result['errors'][] = ['username' => $outcome['username'], 'message' => $outcome['reason']];
            } else {
                $result['skipped'][] = ['username' => $outcome['username'], 'message' => $outcome['reason']];
            }
        }
        $this->storeResult(self::RESULT, $result);
        Toolkit::clearDashboardCache();
        return $result;
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->validResult(self::RESULT);
    }

    /**
     * @param  list<array{id: int, username: string, last_visit: ?string}> $rows
     * @return array{candidates: array<int, array{row: array<string, mixed>, user: UserRecord}>, skipped: list<array{row: array<string, mixed>, reason: string}>}
     */
    private function classify(array $rows): array
    {
        $records    = Toolkit::directory()->users(array_map(static fn (array $row): int => $row['id'], $rows));
        $operator   = $this->operator();
        $candidates = [];
        $skipped    = [];
        foreach ($rows as $row) {
            $user   = $records[$row['id']] ?? null;
            $reason = $user === null ? BlockBatch::REASON_MISSING : BlockBatch::reason($user, $row, $operator);
            if ($reason === '' && $user !== null) {
                $candidates[$row['id']] = ['row' => $row, 'user' => $user];
            } else {
                $skipped[] = ['row' => $row, 'reason' => $reason];
            }
        }
        return ['candidates' => $candidates, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed> $batch
     * @return array{ref: string, kind: string, label: string, started_at: string, blocked: int}
     */
    private function summary(string $kind, string $id, array $batch): array
    {
        $started = (string) ($batch['started_at'] ?? '');
        $label   = $kind === BlockBatch::KIND_TASK
            ? Text::sprintf('COM_USERTOOLS_UNBLOCK_SOURCE_TASK', (string) ($batch['task_title'] ?? ''), $started)
            : Text::sprintf('COM_USERTOOLS_UNBLOCK_SOURCE_MANUAL', Text::_((string) ($batch['tool'] ?? '')), (string) ($batch['operator_name'] ?? ''), $started);
        return ['ref' => BlockBatch::reference($kind, $id), 'kind' => $kind, 'label' => $label, 'started_at' => $started, 'blocked' => (int) ($batch['blocked'] ?? 0)];
    }
}
