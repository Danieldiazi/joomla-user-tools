<?php
// Isolated Joomla stand-ins exercise the real controllers and model without a CMS installation.
namespace Joomla\CMS\Access\Exception { class NotAllowed extends \RuntimeException {} }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\User {
    class User {
        public int $id = 1;
        public string $username = 'operator';
        public array $permissions = ['core.manage' => true, 'core.admin' => true];
        public function authorise($permission, $asset = null) { return $this->permissions[$permission] ?? false; }
    }
}
namespace Joomla\Session { interface SessionInterface {} }
namespace Joomla\CMS\MVC\Model { class BaseDatabaseModel {} }
namespace Joomla\CMS\MVC\Controller {
    class BaseController {
        protected $app;
        protected $input;
        public bool $rendered = false;
        public function __construct($app, $input) { $this->app = $app; $this->input = $input; }
        public function display($cachable = false, $urlparams = []) { $this->rendered = true; return $this; }
    }
}
namespace Danieldiazi\Component\Usertools\Administrator\Service {
    final class Toolkit {
        public static object $application;
        public static object $directory;
        public static object $options;
        public static object $store;
        public static int $writes = 0;
        public static function app() { return self::$application; }
        public static function directory() { return self::$directory; }
        public static function settings() { return self::$options; }
        public static function settingsStore() { return self::$store; }
        public static function clearDashboardCache(): void {}
        public static function blocker() {
            return new class {
                public function block($id, ...$args) {
                    Toolkit::$writes++;
                    return ['status' => 'blocked', 'username' => 'user' . $id, 'reason' => ''];
                }
            };
        }
    }
    final class AccountMailer { public static function canSend(): bool { return false; } }
}
namespace {
    require __DIR__ . '/../bootstrap.php';
    use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
    use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
    use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
    use Danieldiazi\Component\Usertools\Administrator\Model\CompareModel;
    use Danieldiazi\Tests\Fixtures;

    function check(bool $ok, string $message): void {
        if (!$ok) { throw new \RuntimeException($message); }
    }
    $session = new class implements \Joomla\Session\SessionInterface {
        public array $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
    };
    $identity = new \Joomla\CMS\User\User();
    $app = new class($session, $identity) {
        public array $headers = [];
        public function __construct(private $session, private $identity) {}
        public function getSession() { return $this->session; }
        public function getIdentity() { return $this->identity; }
        public function setHeader($name, $value, $replace) { $this->headers[$name] = $value; }
    };
    Toolkit::$application = $app;
    Toolkit::$directory = new class {
        public array $records = [];
        public function users() { return $this->records; }
        public function exists($field, $value) { return false; }
    };
    Toolkit::$options = new class {
        public function exclusions($tool) { return new Exclusions(); }
        public function creationMode() { return 'password'; }
        public function confirmThreshold() { return 10; }
    };
    Toolkit::$store = new class { public function save($values) {} };

    foreach (['Compare', 'Inactive', 'Delete', 'Reports', 'Ungroup'] as $controllerName) {
        foreach (['compare' => 'usertools.compare', 'inactive' => 'usertools.inactive', 'delete' => 'usertools.delete', 'access' => 'usertools.access', 'ungroup' => 'usertools.ungroup',
                  'reports' => 'usertools.reports', 'report' => 'usertools.reports'] as $view => $permission) {
            $input = new class($view) {
                public function __construct(private string $view) {}
                public function getCmd($name, $default = '') { return $name === 'view' ? $this->view : $default; }
                public function set($name, $value) { $this->view = $value; }
            };
            $class = 'Danieldiazi\\Component\\Usertools\\Administrator\\Controller\\' . $controllerName . 'Controller';
            $identity->permissions[$permission] = false;
            $controller = new $class($app, $input);
            try { $controller->display(); throw new \RuntimeException('Unauthorized view rendered'); }
            catch (\Joomla\CMS\Access\Exception\NotAllowed $e) { check($e->getCode() === 403, 'Expected 403'); }
            check(!$controller->rendered, 'Denied request must not render');
            $identity->permissions[$permission] = true;
            $controller = new $class($app, $input);
            $controller->display();
            check($controller->rendered, 'Authorized request must render');
            check($app->headers['Cache-Control'] === 'no-store, private', 'Views must not be cached');
            $identity->permissions[$permission] = false;
        }
    }

    $model = new CompareModel();
    function preview(CompareModel $model, int $count = 10): array {
        Toolkit::$directory->records = [Fixtures::user(1, 'keep', [8], true, true)];
        for ($id = 2; $id <= $count + 1; $id++) {
            Toolkit::$directory->records[$id] = Fixtures::user($id, 'user' . $id);
        }
        $model->preview(['names' => 'keep']);
        return $model->getPreview();
    }
    function expectChanged(callable $action): void {
        $before = Toolkit::$writes;
        try { $action(); throw new \RuntimeException('Changed review was accepted'); }
        catch (UserFacingException $e) { check($e->key() === 'COM_USERTOOLS_ERROR_REVIEW_CHANGED', 'Expected changed review message'); }
        check(Toolkit::$writes === $before, 'No writes allowed for a changed review');
    }
    function applyInput(array $preview, string $count = '10'): array {
        return ['preview' => $preview['nonce'], 'review_ready' => 1, 'confirm' => 1,
                'confirm_count' => $count, 'registrations' => '{}', 'creation_mode' => 'password'];
    }

    $p = preview($model);
    check(count($model->getReview()['candidates']) === 10, 'Ten candidates expected');
    Toolkit::$directory->records[2] = Fixtures::user(2, 'user2', block: 1);
    expectChanged(fn () => $model->getReview());
    check($model->getPreview() === null, 'Changed preview must be invalidated');
    check($model->getDraft()['text'] === 'keep', 'Draft must survive invalidation');

    // A fresh comparison shows nine and can be applied with no typed count below threshold.
    $model->preview(['names' => 'keep']);
    $p = $model->getPreview();
    check(count($model->getReview()['candidates']) === 9, 'New review must show nine');
    $result = $model->apply(applyInput($p, ''));
    check(count($result['blocked']) === 9, 'New nine-account review can be applied');

    // The set can also change after render, before the POST: reject before count validation/writes.
    $p = preview($model);
    $model->getReview();
    unset(Toolkit::$directory->records[2]);
    expectChanged(fn () => $model->apply(applyInput($p)));
    check($model->getPreview() === null, 'POST must invalidate a changed preview');

    $p = preview($model);
    Toolkit::$directory->records[2] = Fixtures::user(2, 'keep');
    expectChanged(fn () => $model->apply(applyInput($p)));

    $p = preview($model);
    Toolkit::$directory->records = array_reverse(Toolkit::$directory->records, true);
    check(count($model->getReview()['candidates']) === 10, 'Ordering changes do not invalidate');
    Toolkit::$directory->records[99] = Fixtures::user(99, 'new');
    check(count($model->getReview()['candidates']) === 10, 'New accounts cannot expand the reviewed set');
    $result = $model->apply(applyInput($p));
    check(count($result['blocked']) === 10, 'Unchanged review still applies');
    check($model->getPreview() === null, 'Successful preview is consumed');
    echo "OK: controller view permissions and changed comparison previews\n";
}
