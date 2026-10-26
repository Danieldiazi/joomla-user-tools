<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Private task reports, readable only through the component. Stored next to Joomla's logs. */
final class ReportStore
{
    private const HEADER  = "<?php die('Forbidden.'); ?>\n";
    private const PATTERN = '/^report-([0-9]{14})-([a-f0-9]{24})\.php$/D';

    public function __construct(private string $directory)
    {
    }

    public static function forLogPath(string $logPath): self
    {
        return new self(rtrim($logPath, '/\\') . '/usertools-reports');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** @param array<string, mixed> $report */
    public function start(array $report): string
    {
        $id = gmdate('YmdHis') . '-' . bin2hex(random_bytes(12));
        $this->save($id, $report);
        return $id;
    }

    /** @param array<string, mixed> $report */
    public function save(string $id, array $report): void
    {
        $target = $this->path($id);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cannot create the reports folder.');
        }
        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $data      = self::HEADER . json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        try {
            if (file_put_contents($temporary, $data, LOCK_EX) === false || !rename($temporary, $target)) {
                throw new \RuntimeException('Cannot save the task report.');
            }
            @chmod($target, 0640);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if ($content === false || !str_starts_with($content, self::HEADER)) {
            throw new \RuntimeException('Unreadable report.');
        }
        $report = json_decode(substr($content, \strlen(self::HEADER)), true, 64, JSON_THROW_ON_ERROR);
        if (!\is_array($report)) {
            throw new \RuntimeException('Unexpected report format.');
        }
        return $report + ['id' => $id];
    }

    /**
     * Newest first. Filters: task (task ID), mode ('real' or 'dry'), status.
     *
     * @param  array{task?: int, mode?: string, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, tasks: array<int, string>}
     */
    public function page(int $page = 1, int $size = 20, array $filters = []): array
    {
        $items = [];
        $tasks = [];
        foreach ($this->ids() as $id) {
            try {
                $report = $this->get($id);
            } catch (\Throwable) {
                $report = ['id' => $id, 'status' => 'unreadable', 'task_title' => '', 'task_id' => 0];
            }
            if ($report === null) {
                continue;
            }
            unset($report['users']);
            $taskId = (int) ($report['task_id'] ?? 0);
            if ($taskId > 0) {
                $tasks[$taskId] ??= (string) ($report['task_title'] ?? '');
            }
            if (!empty($filters['task']) && $taskId !== (int) $filters['task']) {
                continue;
            }
            if (($filters['mode'] ?? '') === 'real' && !empty($report['dry_run'])) {
                continue;
            }
            if (($filters['mode'] ?? '') === 'dry' && empty($report['dry_run'])) {
                continue;
            }
            if (!empty($filters['status']) && ($report['status'] ?? '') !== $filters['status']) {
                continue;
            }
            $items[] = $report;
        }
        ksort($tasks);
        $page = max(1, $page);
        return ['items' => \array_slice($items, ($page - 1) * $size, $size), 'total' => \count($items), 'tasks' => $tasks];
    }

    /**
     * Newest report of each task, reading reports from newest to oldest until every task is found.
     *
     * @param  list<int>                        $taskIds
     * @return array<int, array<string, mixed>> without per-user rows
     */
    public function latestByTask(array $taskIds): array
    {
        $wanted = array_fill_keys(array_map('intval', $taskIds), true);
        $latest = [];
        foreach ($this->ids() as $id) {
            if (\count($latest) === \count($wanted)) {
                break;
            }
            try {
                $report = $this->get($id);
            } catch (\Throwable) {
                continue;
            }
            $taskId = (int) ($report['task_id'] ?? 0);
            if ($report !== null && isset($wanted[$taskId]) && !isset($latest[$taskId])) {
                unset($report['users']);
                $latest[$taskId] = $report;
            }
        }
        return $latest;
    }

    /** Delete reports older than the given number of days (0 keeps everything). Returns how many were deleted. */
    public function prune(int $days, ?\DateTimeImmutable $now = null): int
    {
        if ($days < 1 || !is_dir($this->directory)) {
            return 0;
        }
        $now    = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $limit  = $now->sub(new \DateInterval('P' . $days . 'D'))->format('YmdHis');
        $pruned = 0;
        foreach ($this->ids() as $id) {
            if (substr($id, 0, 14) < $limit && @unlink($this->path($id))) {
                $pruned++;
            }
        }
        return $pruned;
    }

    /** @return list<string> report IDs, newest first */
    private function ids(): array
    {
        $ids = [];
        foreach (glob($this->directory . '/report-*.php') ?: [] as $file) {
            if (preg_match(self::PATTERN, basename($file), $m)) {
                $ids[] = $m[1] . '-' . $m[2];
            }
        }
        // Timestamp plus random suffix keeps several runs within one second in a stable order.
        rsort($ids, SORT_STRING);
        return $ids;
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[0-9]{14}-[a-f0-9]{24}$/D', $id)) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_REPORT_ID');
        }
        return $this->directory . '/report-' . $id . '.php';
    }
}
