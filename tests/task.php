<?php
// Adapter-level tests: doubles isolate Joomla services, no real Joomla installation.
namespace Joomla\CMS\Plugin {
    class CMSPlugin {
        public $app;
        public function getApplication() { return $this->app; }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static bool $enabled = true; public static function isEnabled($name) { return self::$enabled; } }
}
namespace Joomla\CMS\User {
    interface UserFactoryInterface { public function loadUserById(int $id); }
}
namespace Joomla\Database {
    trait DatabaseAwareTrait {
        private $db;
        public function setDatabase($db) { $this->db = $db; }
        public function getDatabase() { return $this->db; }
    }
}
namespace Joomla\Event { interface SubscriberInterface { public static function getSubscribedEvents(): array; } }
namespace Joomla\Component\Scheduler\Administrator\Task {
    class Status { public const OK = 0; public const KNOCKOUT = 5; }
}
namespace Joomla\Component\Scheduler\Administrator\Event {
    class ExecuteTaskEvent {
        public function __construct(private array $params) {}
        public function getArgument($key) { return (object) $this->params; }
    }
}
namespace Joomla\Component\Scheduler\Administrator\Traits {
    trait TaskPluginTrait {
        public array $logs = [];
        public function logTask($text, $priority = 'info') { $this->logs[] = $text; }
    }
}
namespace {
    define('_JEXEC', 1);
    require __DIR__ . '/../admin/src/Service/Comparison.php';
    require __DIR__ . '/../admin/src/Service/Exclusions.php';
    require __DIR__ . '/../plugins/task/usertools/src/Service/InactivityPolicy.php';
    require __DIR__ . '/../plugins/task/usertools/src/Extension/Usertools.php';

    use Danieldiazi\Plugin\Task\Usertools\Extension\Usertools;
    use Joomla\CMS\User\UserFactoryInterface;
    use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;

    function verify($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    class TaskUser {
        public int $block = 0;
        public string $lastvisitDate = '2020-01-01 00:00:00';
        public string $registerDate = '2019-01-01 00:00:00';
        public array $groups = [2];
        public bool $admin = false;
        public bool $fail = false;
        public int $saved = 0;
        public function __construct(public int $id, public string $username) {}
        public function authorise($permission) { return $this->admin; }
        public function getAuthorisedGroups() { return $this->groups; }
        public function save() { $this->saved++; return !$this->fail; }
    }
    class TaskFactory implements UserFactoryInterface {
        public function __construct(public array $users) {}
        public function loadUserById(int $id) { return $this->users[$id]; }
    }
    class TaskQuery {
        public bool $sessions = false;
        public int $userId = 0;
        public function select($x) { return $this; }
        public function from($table) { $this->sessions = $table === '#__session'; return $this; }
        public function where($clause) {
            if (str_starts_with($clause, 'userid = ')) { $this->userId = (int) substr($clause, 9); }
            return $this;
        }
        public function order($x) { return $this; }
    }
    class TaskDb {
        public $query;
        public function __construct(public array $records, public array $active = []) {}
        public function createQuery() { return new TaskQuery(); }
        public function quoteName($x) { return $x; }
        public function quote($x) { return "'" . $x . "'"; }
        public function setQuery($query) { $this->query = $query; return $this; }
        public function loadObjectList() { return $this->records; }
        public function loadResult() { return in_array($this->query->userId, $this->active, true) ? 1 : 0; }
    }
    class TaskApp {
        public function bootComponent($name) {}
        public function getIdentity() { return (object) ['id' => 0]; }
        public function get($key, $default) { return $default; }
    }
    function runTask(array $params, array $users, ?array $snapshot = null, array $active = []): array {
        $plugin = new Usertools();
        $plugin->app = new TaskApp();
        $plugin->setDatabase(new TaskDb($snapshot ?? array_values($users), $active));
        $plugin->setUserFactory(new TaskFactory($users));
        $method = new \ReflectionMethod($plugin, 'blockInactive');
        $status = $method->invoke($plugin, new ExecuteTaskEvent($params));
        return [$status, $plugin->logs];
    }

    $normal = new TaskUser(1, 'normal');
    [$status, $logs] = runTask([], [1 => $normal]);
    verify($status === 0 && $normal->saved === 0 && str_contains(implode('', $logs), 'se bloquearía'), 'Simulación por defecto');
    runTask(['dry_run' => 0], [1 => $normal]);
    verify($normal->saved === 1 && $normal->block === 1, 'Bloqueo real mediante API');

    $admin = new TaskUser(2, 'admin'); $admin->admin = true;
    $named = new TaskUser(3, 'servicio');
    $grouped = new TaskUser(4, 'grupo'); $grouped->groups = [7, 8];
    $active = new TaskUser(5, 'sesion');
    runTask(['dry_run' => 0, 'excluded_names' => 'servicio', 'excluded_groups' => [7]],
        [2=>$admin, 3=>$named, 4=>$grouped, 5=>$active], active: [5]);
    foreach ([$admin, $named, $grouped, $active] as $user) {
        verify($user->saved === 0, 'Administrador, nombre, grupo y sesión protegidos');
    }
    runTask(['dry_run' => 0, 'protect_admins' => 0], [2=>$admin]);
    verify($admin->saved === 1, 'Opción de administradores independiente y configurable');

    $fresh = new TaskUser(6, 'reciente');
    $snapshot = clone $fresh;
    $fresh->lastvisitDate = gmdate('Y-m-d H:i:s');
    runTask(['dry_run'=>0], [6=>$fresh], [$snapshot]);
    verify($fresh->saved === 0, 'Revalidar acceso reciente antes de guardar');
    $bad = new TaskUser(7, 'fallo'); $bad->fail = true;
    [$status, $logs] = runTask(['dry_run'=>0], [7=>$bad]);
    verify($status === 5 && str_contains(implode('', $logs), 'errores=1'), 'Fallo de guardado reportado');
    $untouched = new TaskUser(8, 'intacto');
    [$status] = runTask(['dry_run'=>0, 'days'=>0], [8=>$untouched]);
    verify($status === 5 && !$untouched->saved, 'Configuración inválida no modifica usuarios');
    \Joomla\CMS\Component\ComponentHelper::$enabled = false;
    [$status] = runTask(['dry_run'=>0], [8=>$untouched]);
    verify($status === 5 && !$untouched->saved, 'Dependencia ausente no modifica usuarios');
    echo "OK: ejecución simulada y real, protecciones, revalidación y errores (servicios simulados)\n";
}
