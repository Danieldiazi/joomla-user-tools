<?php
// Isolated Joomla stand-ins exercise the real controllers and model without a CMS installation.
namespace Joomla\CMS\Access\Exception { class NotAllowed extends \RuntimeException {} }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\User {
    class User {
        public int $id = 1;
        public string $username = 'operator';
        public array $permissions = ['core.manage' => true, 'core.admin' => true];
        public function authorise($permission, $asset = null) { return $this->permissions[($asset ?? 'global') . ':' . $permission] ?? $this->permissions[$permission] ?? false; }
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
        public static object $factory;
        public static function users() { return self::$factory; }
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
        public function usersByUsername($names) { return array_filter($this->records, fn ($row) => in_array($row->username, $names, true)); }
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

    foreach (['Compare', 'Bulkcreate', 'Sso', 'Export', 'Inactive', 'Delete', 'Reports', 'Ungroup', 'Access', 'Reach', 'Usage', 'Diagnose', 'Age', 'Move', 'Anonymize', 'Addgroups', 'Names', 'Sync', 'Unblock', 'Profile', 'Accounts', 'Handover'] as $controllerName) {
        foreach (['compare' => 'usertools.compare', 'bulkcreate' => 'usertools.create', 'sso' => 'usertools.sso', 'export' => 'usertools.export', 'inactive' => 'usertools.inactive', 'delete' => 'usertools.delete', 'access' => 'usertools.access', 'usage' => 'usertools.access', 'reach' => 'usertools.access', 'ungroup' => 'usertools.ungroup', 'diagnose' => 'usertools.diagnose', 'age' => 'usertools.content', 'move' => 'core.admin', 'anonymize' => 'usertools.anonymize', 'addgroups' => 'usertools.addgroups', 'names' => 'usertools.names', 'sync' => 'usertools.sync', 'unblock' => 'usertools.unblock', 'handover' => 'usertools.reassign', 'profile' => 'usertools.profile', 'explain' => 'usertools.explain',
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
    // Category pages cannot expand a single tool permission into access to other categories.
    $savedPermissions = $identity->permissions;
    $identity->permissions = ['core.manage' => true, 'usertools.profile' => true,
        'com_scheduler:core.manage' => false, 'core.admin' => false];
    foreach (['accounts' => 0, 'groups' => 403, 'automation' => 403, 'delete' => 403, 'invented' => 404] as $section => $code) {
        $input = new class($section) {
            public function __construct(private string $section) {}
            public function getCmd($name, $default = '') { return ['view' => 'home', 'section' => $this->section][$name] ?? $default; }
            public function set($name, $value) {}
        };
        $controller = new \Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController($app, $input);
        try {
            $controller->display();
            check($code === 0 && $controller->rendered, 'Only the permitted category renders');
        } catch (\RuntimeException $e) {
            check($code !== 0 && $e->getCode() === $code && !$controller->rendered, 'Invalid or forbidden category denied');
        }
    }
    check(!\Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController::allowed(
        $identity, \Danieldiazi\Component\Usertools\Administrator\Service\Navigation::SCHEDULER_MANAGE
    ), 'Component manage permission does not grant scheduler manage permission');
    $identity->permissions = $savedPermissions;
    // The stand-in identity starts as a Super User; the loop above took that away for "move".
    $identity->permissions['core.admin'] = true;

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
        return ['preview' => $preview['nonce'], 'review_ready' => 1, 'selection_ready' => 1,
                'selected' => json_encode(array_map('intval', $preview['ids'])), 'confirm' => 1,
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
    // Name reviews cannot write without synchronised, explicit selection and a current nonce.
    $names = new \Danieldiazi\Component\Usertools\Administrator\Model\NamesModel();
    Toolkit::$directory->records = [Fixtures::user(2, 'alice'), Fixtures::user(3, 'bob')];
    $names->preview(['lines' => "alice;New Alice\nbob;New Bob"]);
    $np = $names->getPreview();
    foreach ([['selection_ready' => 0], ['preview' => 'forged'], ['confirm' => 0], ['selected' => '[]']] as $override) {
        try {
            $names->apply($override + ['preview' => $np['nonce'], 'confirm' => 1, 'selection_ready' => 1, 'selected' => '[0]']);
            throw new \RuntimeException('Unsafe name submission accepted');
        } catch (UserFacingException $e) {
            check($names->getJob() === null && $names->getPreview() !== null, 'Rejected name selection writes nothing and preserves review');
        }
    }
    $session->data['com_usertools.names.preview']['expires'] = time() - 1;
    check($names->getPreview() === null, 'Name preview expires');
    $names->preview(['lines' => 'alice;New Alice']);
    Toolkit::$directory->records[0] = Fixtures::user(2, 'renamed');
    check($names->getReview()['rows'][0]['username'] === 'alice', 'A changed account does not discard the rest of the review');
    // Real name writer with mutable stand-in accounts exercises selection and one-use jobs.
    Toolkit::$directory->records = [Fixtures::user(2, 'alice'), Fixtures::user(3, 'bob')];
    Toolkit::$factory = new class implements \Joomla\CMS\User\UserFactoryInterface {
        public array $accounts = [];
        public function __construct() {
            foreach ([2 => 'alice', 3 => 'bob'] as $id => $username) {
                $this->accounts[$id] = new class ($id, $username) {
                    public string $name;
                    public int $saves = 0;
                    public function __construct(public int $id, public string $username) { $this->name = ucfirst($username); }
                    public function authorise($permission) { return false; }
                    public function save(bool $updateOnly): bool { $this->saves++; return true; }
                };
            }
        }
        public function loadUserById(int $id) { return $this->accounts[$id]; }
        public function loadUserByUsername(string $username) { throw new \LogicException('Names use reviewed IDs'); }
    };
    $names->preview(['lines' => "alice;New Alice\nbob;New Bob"]);
    $np = $names->getPreview();
    $nameInput = ['preview' => $np['nonce'], 'confirm' => 1, 'selection_ready' => 1, 'selected' => '[0,0,999,{},false]'];
    check($names->apply($nameInput), 'Selected name job completes');
    check(Toolkit::$factory->accounts[2]->name === 'New Alice' && Toolkit::$factory->accounts[2]->saves === 1
        && Toolkit::$factory->accounts[3]->name === 'Bob', 'Only selected valid row saves, once');
    check(count($names->getResult()['updated']) === 1 && $names->getPreview() === null && $names->getJob() === null,
        'Completed name review is consumed and result stored');
    try { $names->apply($nameInput); throw new \RuntimeException('Name review replay accepted'); }
    catch (UserFacingException $e) { check(Toolkit::$factory->accounts[2]->saves === 1, 'Replay cannot save twice'); }
    // A concurrent edit skips only that account; unselected and invalid rows are reported as well.
    Toolkit::$factory->accounts[2]->name = 'Alice';
    $names->preview(['lines' => "alice;Again\nbob;New Bob\nbad"]);
    $np = $names->getPreview();
    Toolkit::$factory->accounts[3]->name = 'Concurrent';
    check($names->apply(['preview' => $np['nonce'], 'confirm' => 1, 'selection_ready' => 1, 'selected' => '[0,1]']), 'Name job with a stale row completes');
    $nr = $names->getResult();
    check(Toolkit::$factory->accounts[2]->name === 'Again' && Toolkit::$factory->accounts[3]->name === 'Concurrent' && Toolkit::$factory->accounts[3]->saves === 0,
        'Stale row is not overwritten while the rest are saved');
    check(array_column($nr['updated'], 'username') === ['alice'] && array_column($nr['changed'], 'username') === ['bob']
        && array_column($nr['skipped'], 'status') === ['invalid_line'], 'Result reports each row: updated, changed and skipped');
    echo "OK: controller view permissions and changed comparison previews\n";
}
