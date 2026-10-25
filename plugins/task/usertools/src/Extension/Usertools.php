<?php
namespace Danieldiazi\Plugin\Task\Usertools\Extension;

defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\ReportStore;
use Danieldiazi\Plugin\Task\Usertools\Service\InactivityPolicy;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class Usertools extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;
    private UserFactoryInterface $userFactory;
    private ?ReportStore $reportStore = null;
    protected $autoloadLanguage = true;

    private const TASKS_MAP = [
        'usertools.block_inactive' => [
            'langConstPrefix' => 'PLG_TASK_USERTOOLS_BLOCK',
            'method' => 'blockInactive',
            'form' => 'inactive',
        ],
    ];

    public function setUserFactory(UserFactoryInterface $factory): void { $this->userFactory = $factory; }
    public function setReportStore(ReportStore $store): void { $this->reportStore = $store; }

    public static function getSubscribedEvents(): array
    {
        return ['onTaskOptionsList' => 'advertiseRoutines', 'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm'];
    }

    private function blockInactive(ExecuteTaskEvent $event): int
    {
        $values = (array) $event->getArgument('params');
        $subject = $event->getArgument('subject');
        $report = [
            'task_id' => is_object($subject) && method_exists($subject, 'get') ? (int) $subject->get('id') : 0,
            'task_title' => is_object($subject) && method_exists($subject, 'get') ? (string) $subject->get('title') : 'User Tools',
            'started_at' => gmdate('Y-m-d H:i:s'), 'finished_at' => null, 'status' => 'running',
            'dry_run' => (int) ($values['dry_run'] ?? 1) !== 0, 'days' => $values['days'] ?? 365,
            'include_never' => (int) ($values['include_never'] ?? 0) === 1,
            'cutoff' => null, 'candidates' => 0, 'blocked' => 0, 'errors' => 0,
            'protected' => 0, 'omitted' => 0, 'users' => [], 'message' => '',
        ];
        $store = null;
        $reportId = '';
        $exit = Status::OK;
        try {
            if (!ComponentHelper::isEnabled('com_usertools')) {
                throw new \RuntimeException('Instala y habilita el componente User Tools 2026.10.0 o posterior.');
            }
            $this->getApplication()->bootComponent('com_usertools');
            $store = $this->reportStore ?? new ReportStore(
                rtrim((string) $this->getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\') . '/usertools-reports'
            );
            // Abort before account changes if the report cannot be persisted.
            $reportId = $store->start($report);
            $settings = Exclusions::fromArray($values);
            $cutoff = InactivityPolicy::cutoff($values['days'] ?? 365, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $report['cutoff'] = $cutoff->format('Y-m-d H:i:s');
            $actorId = (int) ($this->getApplication()->getIdentity()?->id ?? 0);
            $db = $this->getDatabase();
            $records = $db->setQuery($db->createQuery()
                ->select($db->quoteName(['id', 'username', 'block', 'lastvisitDate', 'registerDate']))
                ->from($db->quoteName('#__users'))->order($db->quoteName('id')))->loadObjectList();
            $this->logTask('Inicio. Corte UTC: ' . $report['cutoff'] . '; simulación: ' . ($report['dry_run'] ? 'sí' : 'no'));
            foreach ($records as $record) {
                $user = $this->userFactory->loadUserById((int) $record->id);
                $row = ['id' => (int) $record->id, 'username' => (string) ($user->username ?? $record->username ?? ''),
                    'last_visit' => $user->lastvisitDate ?? null, 'action' => 'omitted', 'reason' => ''];
                if (!$user->id) {
                    $row['reason'] = 'La cuenta ya no existe.';
                } elseif ((int) $user->block === 1) {
                    $row['reason'] = 'La cuenta ya estaba bloqueada.';
                } elseif (($reason = $settings->reason($user, $actorId)) !== '') {
                    $row['action'] = 'protected';
                    $row['reason'] = $reason;
                } elseif (!InactivityPolicy::eligible($user, $cutoff, $report['include_never'])) {
                    $row['reason'] = InactivityPolicy::omissionReason($user, $report['include_never']);
                } elseif ($this->hasActiveSession((int) $user->id)) {
                    $row['reason'] = 'Tiene una sesión reciente registrada en Joomla.';
                } else {
                    $report['candidates']++;
                    $row['reason'] = 'Ha superado el plazo de inactividad.';
                    if ($report['dry_run']) {
                        $row['action'] = 'simulated';
                        $this->logTask('Simulación: se bloquearía el usuario ID ' . (int) $user->id);
                    } else {
                        $user->block = 1;
                        try {
                            if (!$user->save()) { throw new \RuntimeException('Guardado rechazado.'); }
                            $report['blocked']++;
                            $row['action'] = 'blocked';
                            $this->logTask('Bloqueado por inactividad: usuario ID ' . (int) $user->id);
                        } catch (\Throwable $e) {
                            $report['errors']++;
                            $row['action'] = 'error';
                            $row['reason'] = 'Joomla no pudo guardar el bloqueo.';
                            $this->logTask('No se pudo bloquear el usuario ID ' . (int) $user->id, 'error');
                        }
                    }
                }
                if ($row['action'] === 'protected') { $report['protected']++; }
                if ($row['action'] === 'omitted') { $report['omitted']++; }
                $report['users'][] = $row;
                if (count($report['users']) % 50 === 0) { $store->save($reportId, $report); }
            }
            $exit = $report['errors'] ? Status::KNOCKOUT : Status::OK;
            $this->logTask("Resumen: candidatos={$report['candidates']}; bloqueados={$report['blocked']}; errores={$report['errors']}.");
        } catch (\Throwable $e) {
            $report['errors']++;
            $report['message'] = 'No se pudo completar la tarea. Revisa la configuración y el registro técnico de Joomla.';
            $exit = Status::KNOCKOUT;
            $this->logTask('No se pudo completar la tarea: ' . $e->getMessage(), 'error');
        } finally {
            $report['status'] = $exit === Status::OK ? 'completed' : 'failed';
            $report['finished_at'] = gmdate('Y-m-d H:i:s');
            if ($store && $reportId !== '') {
                try { $store->save($reportId, $report); }
                catch (\Throwable $e) {
                    $this->logTask('No se pudo actualizar el informe. Puede aparecer como sin finalizar.', 'error');
                    $exit = Status::KNOCKOUT;
                }
            }
        }
        return $exit;
    }

    private function hasActiveSession(int $userId): bool
    {
        $db = $this->getDatabase();
        $lifetime = max(1, (int) $this->getApplication()->get('lifetime', 15)) * 60;
        $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__session'))
            ->where($db->quoteName('userid') . ' = ' . $userId)
            ->where($db->quoteName('time') . ' >= ' . $db->quote((string) (time() - $lifetime)));
        return (int) $db->setQuery($query)->loadResult() > 0;
    }
}
