<?php

namespace Danieldiazi\Plugin\Task\Usertools\Extension;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountBlocker;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\MassBlockGuard;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\ReportStore;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserDirectory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Scheduled task "User Tools: block inactive users". It acts without a user identity, even when
 * the lazy scheduler is triggered by a logged-in visitor, and never blocks Super Users.
 */
final class Usertools extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /** Report checkpoint: rewrite the report after this many account changes. */
    private const CHECKPOINT = 50;

    protected $autoloadLanguage = true;

    protected const TASKS_MAP = [
        'usertools.block_inactive' => [
            'langConstPrefix' => 'PLG_TASK_USERTOOLS_BLOCK',
            'method'          => 'blockInactive',
            'form'            => 'inactive',
        ],
    ];

    /** @var (\Closure(): array{directory: UserDirectory, blocker: AccountBlocker, reports: ReportStore, settings: Settings, window: int})|null */
    private ?\Closure $services = null;

    /** Tests replace Joomla's services. */
    public function setServices(\Closure $factory): void
    {
        $this->services = $factory;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    protected function blockInactive(ExecuteTaskEvent $event): int
    {
        $values  = (array) $event->getArgument('params');
        $subject = $event->getArgument('subject');
        $title   = \is_object($subject) && method_exists($subject, 'get') ? (string) $subject->get('title') : 'User Tools';
        $guard   = MassBlockGuard::fromParams($values);
        $report  = [
            'version'       => 2,
            'task_id'       => \is_object($subject) && method_exists($subject, 'get') ? (int) $subject->get('id') : 0,
            'task_title'    => $title,
            'started_at'    => gmdate('Y-m-d H:i:s'), 'finished_at' => null, 'status' => 'running',
            'dry_run'       => (int) ($values['dry_run'] ?? 1) !== 0,
            'days'          => $values['days'] ?? 365,
            'include_never' => (int) ($values['include_never'] ?? 0) === 1,
            'guard'         => ['percent' => $guard->percent, 'minimum' => $guard->minimum],
            'cutoff'        => null, 'active_accounts' => 0,
            'candidates'    => 0, 'blocked' => 0, 'errors' => 0, 'protected' => 0, 'omitted' => 0,
            'omitted_reasons' => [], 'users' => [], 'message' => '',
        ];
        $store    = null;
        $reportId = '';
        $exit     = Status::OK;
        try {
            if (!ComponentHelper::isEnabled('com_usertools')) {
                throw new \RuntimeException('COM_USERTOOLS_REPORT_ERROR_COMPONENT');
            }
            $this->getApplication()->bootComponent('com_usertools');
            $services = $this->services ? ($this->services)() : [
                'directory' => Toolkit::directory(),
                'blocker'   => Toolkit::blocker(),
                'reports'   => Toolkit::reports(),
                'settings'  => Toolkit::settings(),
                'window'    => Toolkit::sessionWindow(),
            ];
            $store = $services['reports'];
            // Abort before any account change if the report cannot be written.
            $reportId   = $store->start($report);
            $exclusions = Exclusions::fromArray($values);
            $cutoff     = InactivityPolicy::cutoff($values['days'] ?? 365, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $operator   = Operator::system($title);
            $report['cutoff'] = $cutoff->format('Y-m-d H:i:s');
            $this->logTask(\sprintf('Start. UTC cutoff %s; dry run: %s', $report['cutoff'], $report['dry_run'] ? 'yes' : 'no'));

            // 1. Classify every account with bulk queries.
            $directory  = $services['directory'];
            $candidates = [];
            foreach ($directory->users() as $user) {
                if ($user->block === 1) {
                    continue;
                }
                $report['active_accounts']++;
                $reason = $exclusions->reason($user, $operator);
                if ($reason !== '') {
                    $report['protected']++;
                    $report['users'][] = $this->row($user, 'protected', $reason);
                } elseif (!InactivityPolicy::eligible($user, $cutoff, $report['include_never'])) {
                    $this->omit($report, InactivityPolicy::omissionReason($user, $report['include_never']));
                } else {
                    $candidates[$user->id] = $user;
                }
            }
            foreach (array_keys($directory->withRecentSession(array_keys($candidates), time() - $services['window'])) as $id) {
                unset($candidates[$id]);
                $this->omit($report, 'COM_USERTOOLS_REASON_ACTIVE_SESSION');
            }
            $report['candidates'] = \count($candidates);

            // 2. Safety net against mass blocking.
            if ($guard->exceeded($report['candidates'], $report['active_accounts'])) {
                $share   = MassBlockGuard::share($report['candidates'], $report['active_accounts']);
                $report['guard_triggered'] = true;
                $report['message']         = 'COM_USERTOOLS_REPORT_GUARD_' . ($report['dry_run'] ? 'DRY' : 'STOPPED');
                $report['guard_share']     = $share;
                foreach ($candidates as $user) {
                    $report['users'][] = $this->row($user, $report['dry_run'] ? 'simulated' : 'guarded', 'COM_USERTOOLS_REASON_INACTIVE');
                }
                $this->logTask(\sprintf('Mass block guard: %d candidates (%s%% of %d active accounts) exceed %d%%. Nothing was blocked.',
                    $report['candidates'], $share, $report['active_accounts'], $guard->percent), 'warning');
                $candidates = [];
            }

            // 3. Block (or simulate), revalidating each account with a fresh Joomla User.
            $changes = 0;
            foreach ($candidates as $user) {
                if ($report['dry_run']) {
                    $report['users'][] = $this->row($user, 'simulated', 'COM_USERTOOLS_REASON_INACTIVE');
                    $this->logTask('Dry run: would block user ID ' . $user->id);
                    continue;
                }
                $outcome = $services['blocker']->block($user->id, $exclusions, $operator,
                    fn (object $fresh) => InactivityPolicy::eligible($fresh, $cutoff, $report['include_never'])
                        && !$directory->withRecentSession([$user->id], time() - $services['window']), 'COM_USERTOOLS_TOOL_TASK');
                if ($outcome['status'] === AccountBlocker::BLOCKED) {
                    $report['blocked']++;
                    $report['users'][] = $this->row($user, 'blocked', 'COM_USERTOOLS_REASON_INACTIVE');
                    $this->logTask('Blocked for inactivity: user ID ' . $user->id);
                } elseif ($outcome['status'] === AccountBlocker::ERROR) {
                    $report['errors']++;
                    $report['users'][] = $this->row($user, 'error', $outcome['reason']);
                    $this->logTask('Could not block user ID ' . $user->id, 'error');
                } elseif ($outcome['status'] === AccountBlocker::PROTECTED) {
                    $report['protected']++;
                    $report['users'][] = $this->row($user, 'protected', $outcome['reason']);
                } else {
                    $this->omit($report, $outcome['reason']);
                }
                if (++$changes % self::CHECKPOINT === 0) {
                    $store->save($reportId, $report);
                }
            }
            $stopped = !empty($report['guard_triggered']) && !$report['dry_run'];
            $exit    = $report['errors'] || $stopped ? Status::KNOCKOUT : Status::OK;
            $this->logTask(\sprintf('Summary: candidates=%d; blocked=%d; errors=%d.', $report['candidates'], $report['blocked'], $report['errors']));
        } catch (\Throwable $e) {
            $report['errors']++;
            $report['message'] = str_starts_with($e->getMessage(), 'COM_USERTOOLS_') ? $e->getMessage() : 'COM_USERTOOLS_REPORT_ERROR_GENERIC';
            $exit              = Status::KNOCKOUT;
            $this->logTask('The task could not be completed: ' . $e->getMessage(), 'error');
        } finally {
            $report['status']      = !empty($report['guard_triggered']) && !$report['dry_run'] ? 'guarded' : ($exit === Status::OK ? 'completed' : 'failed');
            $report['finished_at'] = gmdate('Y-m-d H:i:s');
            if ($store && $reportId !== '') {
                try {
                    $store->save($reportId, $report);
                    $retention = isset($services['settings']) ? $services['settings']->retentionDays() : 0;
                    $store->prune($retention);
                } catch (\Throwable) {
                    $this->logTask('The report could not be updated. It may appear as unfinished.', 'error');
                    $exit = Status::KNOCKOUT;
                }
            }
        }
        return $exit;
    }

    /** @return array{id: int, username: string, last_visit: ?string, action: string, reason: string} */
    private function row(object $user, string $action, string $reason): array
    {
        return ['id' => (int) $user->id, 'username' => (string) $user->username, 'last_visit' => $user->lastvisitDate, 'action' => $action, 'reason' => $reason];
    }

    /**
     * Omitted accounts are only counted by reason, so reports stay small on large sites.
     *
     * @param array<string, mixed> $report
     */
    private function omit(array &$report, string $reason): void
    {
        $report['omitted']++;
        $report['omitted_reasons'][$reason] = ($report['omitted_reasons'][$reason] ?? 0) + 1;
    }
}
