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
        /** Account id whose block throws, as a request cut off half-way would. */
        public static int $failOn = 0;
        public static function app() { return self::$application; }
        public static function directory() { return self::$directory; }
        public static function settings() { return self::$options; }
        public static function settingsStore() { return self::$store; }
        public static function clearDashboardCache(): void {}
        public static function blocker() {
            return new class {
                public function block($id, ...$args) {
                    if ($id === Toolkit::$failOn) { throw new \RuntimeException('time limit'); }
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
        public int $batch = 8;
        public function batchSeconds() { return $this->batch; }
    };
    Toolkit::$store = new class { public function save($values) {} };

    foreach (['Compare', 'Inactive', 'Delete', 'Reports', 'Ungroup', 'Access', 'Usage', 'Diagnose', 'Age'] as $controllerName) {
        foreach (['compare' => 'usertools.compare', 'inactive' => 'usertools.inactive', 'delete' => 'usertools.delete', 'access' => 'usertools.access', 'usage' => 'usertools.access', 'ungroup' => 'usertools.ungroup', 'diagnose' => 'usertools.diagnose', 'age' => 'usertools.content',
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
    check($result['done'] && $model->getJob() === null && count($model->getResult()['blocked']) === 10, 'A small job ends in the same request');

    // Large jobs run in batches: one account per request when the budget is 0.
    Toolkit::$options->batch = 0;
    $writes = Toolkit::$writes;
    $p = preview($model);
    $first = $model->apply(applyInput($p));
    check(!$first['done'] && $first['processed'] === 1 && $first['total'] === 10 && Toolkit::$writes === $writes + 1, 'The first batch processes at least one account');
    check($model->getPreview() === null && $model->getJob() !== null, 'The preview is consumed and the job is kept');
    $p2 = preview($model);
    try { $model->apply(applyInput($p2)); throw new \RuntimeException('A second job was started'); }
    catch (UserFacingException $e) { check($e->key() === 'COM_USERTOOLS_ERROR_JOB_PENDING', 'Only one job at a time'); }
    $steps = 1;
    do { $step = $model->step(); $steps++; } while (!$step['done'] && $steps < 50);
    check($steps === 10 && Toolkit::$writes === $writes + 10, 'Every account is processed exactly once, one per request');
    check(count($step['result']['blocked']) === 10 && $step['result']['skipped'] === 0 && $model->getJob() === null, 'The last batch stores the whole result');
    try { $model->step(); throw new \RuntimeException('Step without a job'); }
    catch (UserFacingException $e) { check($e->key() === 'COM_USERTOOLS_ERROR_JOB_MISSING', 'No job, nothing to do'); }

    $p = preview($model);
    $model->apply(applyInput($p));
    $model->step();
    $model->discard();
    $discarded = $model->getResult();
    check($model->getJob() === null && count($discarded['blocked']) === 2 && $discarded['discarded'] === 8 && $discarded['skipped'] === 0, 'Discarding keeps what was done and counts the rest');
    $identity->id = 2;
    $model->apply(applyInput(preview($model)));
    $identity->id = 1;
    check($model->getJob() === null, 'A job belongs to the operator who started it');

    // A request cut off half-way keeps what it did: continuing neither repeats nor loses an account.
    Toolkit::$options->batch = 8;
    $writes = Toolkit::$writes;
    $p = preview($model);
    Toolkit::$failOn = 6;
    try { $model->apply(applyInput($p)); throw new \RuntimeException('The cut was not simulated'); }
    catch (\RuntimeException $e) { check($e->getMessage() === 'time limit', 'The batch stops at the sixth account'); }
    $job = $model->getJob();
    check($job !== null && $job['processed'] === 4 && count($job['blocked']) === 4 && $job['block'][0] === 6, 'The four accounts done before the cut are saved in the job');
    Toolkit::$failOn = 0;
    $step = $model->step();
    check($step['done'] && Toolkit::$writes === $writes + 10 && count($step['result']['blocked']) === 10, 'Continuing does the other six, once each');
    echo "OK: controller view permissions and changed comparison previews\n";
}
