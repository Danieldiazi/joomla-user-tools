<?php
namespace Danieldiazi\Plugin\Task\Usertools\Extension;

defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
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
    protected $autoloadLanguage = true;

    private const TASKS_MAP = [
        'usertools.block_inactive' => [
            'langConstPrefix' => 'PLG_TASK_USERTOOLS_BLOCK',
            'method' => 'blockInactive',
            'form' => 'inactive',
        ],
    ];

    public function setUserFactory(UserFactoryInterface $factory): void
    {
        $this->userFactory = $factory;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function blockInactive(ExecuteTaskEvent $event): int
    {
        $candidates = 0;
        $blocked = 0;
        $failed = 0;
        try {
            if (!ComponentHelper::isEnabled('com_usertools')) {
                throw new \RuntimeException('Instala y habilita el componente User Tools 0.2.0 o posterior.');
            }
            $this->getApplication()->bootComponent('com_usertools');
            $values = (array) $event->getArgument('params');
            // The task's own params are the only source of its exclusion settings.
            $settings = Exclusions::fromArray($values);
            $cutoff = InactivityPolicy::cutoff($values['days'] ?? 365, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $includeNever = (int) ($values['include_never'] ?? 0) === 1;
            $dryRun = (int) ($values['dry_run'] ?? 1) !== 0;
            $actorId = (int) ($this->getApplication()->getIdentity()?->id ?? 0);
            $db = $this->getDatabase();
            $query = $db->createQuery()->select($db->quoteName(['id', 'block', 'lastvisitDate', 'registerDate']))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('block') . ' = 0')->order($db->quoteName('id'));
            $records = $db->setQuery($query)->loadObjectList();
            $this->logTask('Inicio. Corte UTC: ' . $cutoff->format('Y-m-d H:i:s') . '; simulación: ' . ($dryRun ? 'sí' : 'no'));
            foreach ($records as $record) {
                if (!InactivityPolicy::eligible($record, $cutoff, $includeNever)) {
                    continue;
                }
                // A fresh user object rechecks dates, block status, groups and ACL before saving.
                $user = $this->userFactory->loadUserById((int) $record->id);
                if (!InactivityPolicy::eligible($user, $cutoff, $includeNever)
                    || $settings->protects($user, $actorId) || $this->hasActiveSession((int) $user->id)) {
                    continue;
                }
                $candidates++;
                if ($dryRun) {
                    $this->logTask('Simulación: se bloquearía el usuario ID ' . (int) $user->id);
                    continue;
                }
                $user->block = 1;
                try {
                    if (!$user->save()) {
                        throw new \RuntimeException('Guardado rechazado.');
                    }
                    $blocked++;
                    $this->logTask('Bloqueado por inactividad: usuario ID ' . (int) $user->id);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->logTask('No se pudo bloquear el usuario ID ' . (int) $user->id, 'error');
                }
            }
        } catch (\Throwable $e) {
            $this->logTask('No se pudo completar la tarea: ' . $e->getMessage(), 'error');
            return Status::KNOCKOUT;
        }
        $this->logTask("Resumen: candidatos=$candidates; bloqueados=$blocked; errores=$failed.");
        return $failed ? Status::KNOCKOUT : Status::OK;
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
