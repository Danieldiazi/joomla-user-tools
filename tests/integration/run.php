<?php

/**
 * End-to-end test against a real Joomla 6 site with User Tools installed.
 *
 *   UT_URL=http://127.0.0.1:8090 UT_JOOMLA=/path/to/site UT_ADMIN_USER=admin UT_ADMIN_PASS=... \
 *   UT_MAILS=/tmp/mails php tests/integration/run.php
 *
 * The site must be disposable: the test creates, blocks and deletes accounts and changes options.
 * Mail must go to tests/integration/smtp-sink.php (127.0.0.1:2525 by default).
 */

declare(strict_types=1);

final class Client
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'ut-jar');
    }

    /** @return array{0: int, 1: string, 2: array<string, string>} */
    public function request(string $path, ?array $post = null): array
    {
        $headers = [];
        $ch      = curl_init($this->base . '/administrator/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = (string) curl_exec($ch);
        return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body, $headers];
    }

    public function get(string $path): string
    {
        [$code, $body] = $this->request($path);
        Test::check($code === 200, "GET $path returns 200 (got $code)");
        Test::noPhpErrors($body, $path);
        return $body;
    }

    /** Posts a form of the page at $page, adding its CSRF token and hidden fields. */
    public function submit(string $page, array $fields, string $formMarker = '<form'): string
    {
        $html = $this->get($page);
        $form = substr($html, (int) strpos($html, $formMarker));
        $form = substr($form, 0, (int) strpos($form, '</form>'));
        preg_match_all('/<input type="hidden" name="([^"\[]+)" (?:[^>]*?)value="([^"]*)"/', $form, $m, PREG_SET_ORDER);
        $data = [];
        foreach ($m as $hidden) {
            $data[$hidden[1]] = html_entity_decode($hidden[2], ENT_QUOTES);
        }
        $data[self::token($html)] = 1;
        [$code, $body] = $this->request('index.php?option=com_usertools', $fields + $data);
        Test::check($code === 200, "POST $page returns 200 (got $code)");
        Test::noPhpErrors($body, 'POST ' . $page);
        return $body;
    }

    public function login(string $user, string $password): void
    {
        $html = $this->request('index.php')[1];
        $this->request('index.php', ['username' => $user, 'passwd' => $password, 'option' => 'com_login', 'task' => 'login', 'return' => base64_encode('index.php'), self::token($html) => 1]);
        Test::check(!str_contains($this->request('index.php')[1], 'name="passwd"'), "login as $user");
    }

    public static function token(string $html): string
    {
        if (preg_match('/<input type="hidden" name="([a-f0-9]{32})" value="1"/', $html, $m) || preg_match('/"csrf\.token":"([a-f0-9]{32})"/', $html, $m)) {
            return $m[1];
        }
        throw new RuntimeException('No CSRF token found');
    }
}

final class Test
{
    public static int $passed = 0;
    public static array $failed = [];

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        self::$failed[] = $message;
        echo "  FAIL: $message\n";
    }

    public static function noPhpErrors(string $html, string $where): void
    {
        self::check(!preg_match('#(Fatal error|Warning|Notice|Deprecated)</b>:|<b>(Fatal error|Warning|Notice|Deprecated)#', $html), "no PHP errors in $where");
        $visible = strip_tags((string) preg_replace('#<script\b[^>]*>.*?</script>#si', '', $html));
        self::check(!preg_match('/\bCOM_USERTOOLS_[A-Z0-9_]+\b/', $visible, $m), "no untranslated strings in $where" . (isset($m[0]) ? " ({$m[0]})" : ''));
    }

    public static function section(string $title): void
    {
        echo "\n== $title\n";
    }
}

final class Site
{
    public PDO $db;
    public string $prefix;

    public function __construct(public string $path)
    {
        require_once $path . '/configuration.php';
        $c            = new JConfig();
        $this->prefix = $c->dbprefix;
        $dsn          = str_contains($c->dbtype, 'pgsql') ? "pgsql:host={$c->host};dbname={$c->db}" : "mysql:host={$c->host};dbname={$c->db};charset=utf8mb4";
        $this->db     = new PDO($dsn, $c->user, $c->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function cli(string $args): string
    {
        return (string) shell_exec(PHP_BINARY . ' ' . escapeshellarg($this->path . '/cli/joomla.php') . ' ' . $args . ' -n 2>&1');
    }

    public function q(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare(str_replace('#__', $this->prefix, $sql));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userId(string $username): int
    {
        return (int) ($this->q('SELECT id FROM #__users WHERE username = ?', [$username])[0]['id'] ?? 0);
    }

    public function blocked(string $username): bool
    {
        return (int) ($this->q('SELECT block FROM #__users WHERE username = ?', [$username])[0]['block'] ?? -1) === 1;
    }

    public function addUser(string $username, string $group, string $password = '', ?string $lastVisit = null): int
    {
        $password = $password ?: 'Test-' . bin2hex(random_bytes(8)) . 'Aa1!';
        $this->cli('user:add --username=' . escapeshellarg($username) . ' --name=' . escapeshellarg(ucfirst($username)) . ' --email=' . escapeshellarg($username . '@example.test')
            . ' --password=' . escapeshellarg($password) . ' --usergroup=' . escapeshellarg($group));
        $id = $this->userId($username);
        if ($lastVisit !== null) {
            $this->q('UPDATE #__users SET "lastvisitDate" = ?, "registerDate" = ? WHERE id = ?', [$lastVisit, $lastVisit, $id]);
        }
        return $id;
    }

    public function options(array $values): void
    {
        $row    = $this->q("SELECT extension_id, params FROM #__extensions WHERE type = 'component' AND element = 'com_usertools'")[0];
        $params = array_replace(json_decode($row['params'] ?: '{}', true) ?: [], $values);
        $this->q('UPDATE #__extensions SET params = ? WHERE extension_id = ?', [json_encode($params), $row['extension_id']]);
    }

    public function fakeSession(int $userId): void
    {
        $this->q('INSERT INTO #__session (session_id, client_id, guest, time, data, userid, username) VALUES (?, 0, 0, ?, ?, ?, ?)',
            [bin2hex(random_bytes(16)), time(), '', $userId, 'fake']);
    }

    public function sessions(int $userId): int
    {
        return count($this->q('SELECT session_id FROM #__session WHERE userid = ?', [$userId]));
    }
}

$url     = rtrim(getenv('UT_URL') ?: 'http://127.0.0.1:8090', '/');
$site    = new Site(getenv('UT_JOOMLA') ?: die("UT_JOOMLA is required\n"));
$mails   = getenv('UT_MAILS') ?: sys_get_temp_dir() . '/usertools-mails';
$admin   = new Client($url);
$quoted  = str_contains($site->db->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql');
if (!$quoted) {
    // MySQL does not accept double-quoted identifiers in the default mode.
    $site->db->exec("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ANSI_QUOTES')");
}

Test::section('Setup');
$site->cli('config:set live_site=' . escapeshellarg($url) . ' error_reporting=maximum mailer=smtp smtphost=127.0.0.1 smtpport=2525 smtpauth=false smtpsecure=none mailonline=true session_metadata=true');
// Remove what a previous run left behind.
$previous = array_column($site->q("SELECT id FROM #__users WHERE email LIKE '%@example.test' AND username <> ?", [getenv('UT_ADMIN_USER') ?: 'admin']), 'id');
foreach ($previous as $id) {
    $site->q('DELETE FROM #__user_usergroup_map WHERE user_id = ?', [$id]);
    $site->q('DELETE FROM #__users WHERE id = ?', [$id]);
}
$site->q("DELETE FROM #__scheduler_tasks WHERE title = 'UT integration'");
$site->q("DELETE FROM #__action_logs WHERE extension = 'com_usertools.users'");
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$old = gmdate('Y-m-d H:i:s', time() - 400 * 86400);
foreach (['ana', 'luis', 'baja', 'servicio'] as $name) {
    $site->addUser($name, 'Registered');
}
$site->addUser('mgr', 'Manager');
$site->addUser('super2', 'Super Users');
$delegatePassword = 'Delegate-' . bin2hex(random_bytes(6)) . 'Aa1!';
$site->addUser('delegate', 'Manager', $delegatePassword);
$site->options(['confirm_threshold' => 3, 'creation_mode' => 'email', 'compare_protect_admins' => 0, 'compare_excluded_names' => '', 'compare_excluded_groups' => []]);
$admin->login(getenv('UT_ADMIN_USER') ?: 'admin', getenv('UT_ADMIN_PASS') ?: die("UT_ADMIN_PASS is required\n"));
$home = $admin->get('index.php?option=com_usertools');
Test::check(str_contains($home, 'Compare a list of users') && str_contains($home, 'Task results'), 'home lists every tool for a Super User');
$admin->get('index.php?option=com_config&view=component&component=com_usertools');

Test::section('Comparison: preview, Super Users always protected, reinforced confirmation');
$review = $admin->submit('index.php?option=com_usertools&view=compare', [
    'task' => 'compare.preview', 'names' => "ana\nLUIS\ndelegate\nnuevo1\nnuevo2", 'protect_admins' => 0, 'excluded' => '', 'excluded_groups' => [],
]);
$block = substr($review, (int) strpos($review, 'id="ut-block-table"'));
$block = substr($block, 0, (int) strpos($block, '</table>'));
Test::check(str_contains($block, '>baja<') && str_contains($block, '>servicio<') && str_contains($block, '>mgr<'), 'review proposes baja, servicio and mgr');
$toBlock = substr_count($block, '<tr>') - 1;
Test::check($toBlock === 3, "exactly three accounts proposed (got $toBlock)");
Test::check(!str_contains($block, '>admin<') && !str_contains($block, '>super2<'), 'Super Users are never proposed, even with "Exclude administrators" off');
Test::check(!str_contains($block, '>luis<'), 'LUIS matches luis (case-insensitive)');
Test::check(str_contains($review, 'baja@example.test') && str_contains($review, 'Registered'), 'review shows email and groups');
Test::check(str_contains($review, 'name="confirm_count"'), 'typed confirmation is required from the threshold');
$site->fakeSession($site->userId('baja'));
$registrations = [];
foreach (['nuevo1', 'nuevo2'] as $name) {
    $registrations[hash('sha256', $name)] = ['checked' => true, 'email' => $name . '@example.test'];
}
$fields = ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1, 'creation_mode' => 'email', 'registrations' => json_encode($registrations)];
$denied = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', $fields + ['confirm_count' => '1'], 'data-ut-review');
Test::check(str_contains($denied, 'type the number of affected accounts: ' . $toBlock) && !$site->blocked('baja'), 'a wrong typed number blocks nothing');
array_map('unlink', glob($mails . '/*.eml') ?: []);
$result = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', $fields + ['confirm_count' => (string) $toBlock], 'data-ut-review');
Test::check(str_contains($result, 'Operation finished'), 'comparison applied');
Test::check($site->blocked('baja') && $site->blocked('servicio') && $site->blocked('mgr') && !$site->blocked('admin') && !$site->blocked('super2'), 'only the reviewed accounts were blocked');
Test::check($site->sessions($site->userId('baja')) === 0, 'blocking closed the open sessions of the account');
$new1 = $site->q('SELECT block, activation, "requireReset" FROM #__users WHERE username = ?', ['nuevo1'])[0] ?? null;
Test::check($new1 !== null && (int) $new1['block'] === 0 && $new1['activation'] !== '' && (int) $new1['requireReset'] === 0, 'nuevo1 created, active, with a password setup token');
Test::check(!preg_match('#<code class="ut-secret">#', $result), 'email mode shows no password');
usleep(300000);
$sent = implode("\n", array_map('file_get_contents', glob($mails . '/*.eml') ?: []));
Test::check(str_contains($sent, 'nuevo1@example.test') && str_contains($sent, $url . '/index.php?option=com_users&view=reset&layout=confirm&token='), 'nuevo1 received the "choose your password" link to the public site');
Test::check(str_contains($result, 'email sent'), 'result reports the email as sent');
// Follow the emailed link on the public site, choose a password and log in.
preg_match('#(' . preg_quote($url, '#') . '/index\.php\?option=com_users&view=reset&layout=confirm&token=([a-f0-9]+))#', $sent, $link);
$siteClient = new Client($url);
[, $confirm] = $siteClient->request('../' . substr($link[1] ?? '', strlen($url) + 1));
$chosen = 'Chosen-' . bin2hex(random_bytes(6)) . 'Aa1!';
[, $complete] = $siteClient->request('../index.php?option=com_users&task=reset.confirm', ['jform' => ['username' => 'nuevo1', 'token' => $link[2] ?? ''], Client::token($confirm) => 1]);
[, $done] = $siteClient->request('../index.php?option=com_users&task=reset.complete', ['jform' => ['password1' => $chosen, 'password2' => $chosen], Client::token($complete) => 1]);
[, $loginPage] = $siteClient->request('../index.php?option=com_users&view=login');
[, $loggedIn] = $siteClient->request('../index.php?option=com_users&task=user.login', ['username' => 'nuevo1', 'password' => $chosen, 'return' => base64_encode('index.php?option=com_users&view=profile'), Client::token($loginPage) => 1]);
Test::check(str_contains($loggedIn, 'Hi nuevo1') && str_contains($done, 'successful'), 'the new user chose a password with the emailed link and logged in');
$logs = $site->q("SELECT message_language_key FROM #__action_logs WHERE extension = 'com_usertools.users'");
$keys = array_count_values(array_column($logs, 'message_language_key'));
Test::check(($keys['COM_USERTOOLS_ACTION_LOG_BLOCKED'] ?? 0) === 3 && ($keys['COM_USERTOOLS_ACTION_LOG_CREATED_EMAIL'] ?? 0) === 2, 'blocks and creations are in the User Actions Log (' . json_encode($keys) . ')');
$actionLog = $admin->get('index.php?option=com_actionlogs&view=actionlogs');
Test::check(str_contains($actionLog, 'with User Tools'), 'the User Actions Log shows readable User Tools entries');

Test::section('Comparison: one-time passwords never reach the session');
$review = $admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => "ana\nluis\ndelegate\nnuevo1\nnuevo2\nnuevo3", 'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$result = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1, 'creation_mode' => 'password',
    'registrations' => json_encode([hash('sha256', 'nuevo3') => ['checked' => true, 'email' => 'nuevo3@example.test']])], 'data-ut-review');
preg_match('#<code class="ut-secret">([^<]+)</code>#', $result, $pw);
$password = html_entity_decode($pw[1] ?? '', ENT_QUOTES);
Test::check(strlen($password) >= 24 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/[0-9]/', $password) && preg_match('/[^A-Za-z0-9]/', $password), 'password mode shows a strong temporary password once');
$stored = implode('', array_column($site->q('SELECT data FROM #__session'), 'data'));
Test::check($password !== '' && !str_contains($stored, $password), 'the password is not stored in any session');
Test::check((int) ($site->q('SELECT "requireReset" FROM #__users WHERE username = ?', ['nuevo3'])[0]['requireReset'] ?? 0) === 1, 'password mode forces a change at first login');
$again = $admin->get('index.php?option=com_usertools&view=compare&layout=result');
Test::check(!str_contains($again, $password) && str_contains($again, 'Already shown'), 'reloading the result does not show the password again');

Test::section('Comparison: no matching names');
$site->q('UPDATE #__users SET block = 0 WHERE username IN (?, ?)', ['baja', 'servicio']);
$review = $admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => 'pepito', 'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
Test::check(str_contains($review, 'alert-danger') && str_contains($review, 'No name in the list matches a Joomla user'), 'red warning when nothing matches and accounts would be blocked');

Test::section('Delegated permission: no Super User, no backend accounts');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1]])]);
$site->q('UPDATE #__users SET block = 0 WHERE username = ?', ['mgr']);
$delegate = new Client($url);
$delegate->login('delegate', $delegatePassword);
$dhome = $delegate->get('index.php?option=com_usertools');
Test::check(str_contains($dhome, 'Compare a list of users') && !str_contains($dhome, 'Review inactive users'), 'the delegate sees only the tools it is allowed to use');
[$code] = $delegate->request('index.php?option=com_usertools&view=inactive');
Test::check($code === 403, 'the delegate cannot open the inactivity review (got ' . $code . ')');
$review = $delegate->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => "ana\nluis", 'protect_admins' => 0, 'excluded' => '', 'excluded_groups' => []]);
$block = substr($review, (int) strpos($review, 'id="ut-block-table"'));
$block = substr($block, 0, (int) strpos($block, '</table>'));
Test::check(!str_contains($block, '>mgr<') && !str_contains($block, '>delegate<') && str_contains($block, '>baja<'), 'a non Super User cannot block backend accounts or itself');
Test::check(str_contains($review, 'you are not allowed to create accounts') || !str_contains($review, 'data-ut-add'), 'a delegate without "Create accounts" cannot create users');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");

Test::section('Inactivity review');
foreach (['old1', 'old2'] as $name) {
    $site->addUser($name, 'Registered', '', $old);
}
$review = $admin->submit('index.php?option=com_usertools&view=inactive', ['task' => 'inactive.search', 'days' => 365, 'include_never' => 0, 'protect_admins' => 1, 'excluded' => 'old2', 'excluded_groups' => []]);
Test::check(str_contains($review, '>old1<') && !str_contains($review, '>old2<'), 'old1 is a candidate, the excluded old2 is not');
$result = $admin->submit('index.php?option=com_usertools&view=inactive&layout=review', ['task' => 'inactive.suspend', 'selection_ready' => 1, 'confirm' => 1,
    'selected' => json_encode([$site->userId('old1')])], 'data-ut-suspension-form');
Test::check(str_contains($result, 'Review finished') && $site->blocked('old1') && !$site->blocked('old2'), 'only the selected account was suspended');

Test::section('Scheduled task: safety limit, dry run, real run, reports');
foreach (range(1, 6) as $i) {
    $site->addUser('stale' . $i, 'Registered', '', $old);
}
$taskParams = ['dry_run' => 0, 'days' => 365, 'include_never' => 0, 'protect_admins' => 1, 'excluded_names' => '', 'excluded_groups' => [], 'guard_percent' => 20, 'guard_minimum' => 3];
$site->q('INSERT INTO #__scheduler_tasks (asset_id, title, type, execution_rules, cron_rules, state, last_exit_code, times_executed, times_failed, locked, priority, ordering, params, note, created, created_by) VALUES (0, ?, ?, ?, ?, 1, 0, 0, 0, NULL, 0, 0, ?, ?, ?, ?)',
    ['UT integration', 'usertools.block_inactive', json_encode(['rule-type' => 'manual']), json_encode(['type' => 'manual']), json_encode($taskParams), '', gmdate('Y-m-d H:i:s'), $site->userId('admin')]);
$taskId = (int) $site->q("SELECT id FROM #__scheduler_tasks WHERE title = 'UT integration' ORDER BY id DESC")[0]['id'];
echo $site->cli('scheduler:run --id=' . $taskId) !== '' ? '' : '';
$reports = $admin->get('index.php?option=com_usertools&view=reports');
Test::check(str_contains($reports, 'Stopped for safety'), 'the safety limit stopped the real run');
Test::check(!$site->blocked('stale1'), 'nothing was blocked by the stopped run');
$site->q('UPDATE #__scheduler_tasks SET params = ? WHERE id = ?', [json_encode(['guard_percent' => 0] + $taskParams), $taskId]);
$site->cli('scheduler:run --id=' . $taskId);
Test::check($site->blocked('stale1') && $site->blocked('stale6') && !$site->blocked('admin') && !$site->blocked('super2'), 'the real run blocked the stale accounts and no Super User');
$taskLogs = $site->q("SELECT COUNT(*) AS n FROM #__action_logs WHERE message_language_key = 'COM_USERTOOLS_ACTION_LOG_TASK_BLOCKED'")[0]['n'];
Test::check((int) $taskLogs >= 6, 'task blocks are in the User Actions Log');
$list = $admin->get('index.php?option=com_usertools&view=reports&filter_mode=real&filter_status=completed');
preg_match('/report_id=([0-9]{14}-[a-f0-9]{24})/', $list, $rid);
Test::check(isset($rid[1]), 'filters find the completed real run');
$detail = $admin->get('index.php?option=com_usertools&view=report&report_id=' . ($rid[1] ?? ''));
Test::check(str_contains($detail, '>stale1<') && str_contains($detail, 'Skipped accounts by reason'), 'report lists blocked accounts and summarises skipped ones');
[$code, $csv, $headers] = $admin->request('index.php?option=com_usertools&task=reports.export&report_id=' . ($rid[1] ?? ''));
Test::check($code === 200 && str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, 'stale1') && str_contains($headers['content-type'] ?? '', 'text/csv'), 'CSV export works');

Test::section('Delete inactive users: selection, protections, typed confirmation and logs');
$recent = gmdate('Y-m-d H:i:s');
$site->q('UPDATE #__users SET "lastvisitDate" = ?, block = 0 WHERE username IN (?, ?)', [$old, 'super2', 'mgr']);
// The scheduled task above blocked old2; it is the active inactive account of this section.
$site->q('UPDATE #__users SET "lastvisitDate" = ?, block = 0 WHERE username = ?', [$old, 'old2']);
$site->options(['delete_status' => null]);
$deletePage = $admin->get('index.php?option=com_usertools&view=delete');
Test::check((bool) preg_match('/id="ut-status-blocked"[^>]* checked/', $deletePage) && !preg_match('/id="ut-status-all"[^>]* checked/', $deletePage), '"Only blocked" is selected by default');
Test::check(str_contains($deletePage, 'Deletion is permanent'), 'the deletion screen warns that it cannot be undone');
$search = fn (Client $c, array $extra) => $c->submit('index.php?option=com_usertools&view=delete', $extra + ['task' => 'delete.search', 'days' => 365, 'include_never' => 0, 'protect_admins' => 0, 'excluded' => '', 'excluded_groups' => []]);
$rows = function (string $html): array {
    $table = substr($html, (int) strpos($html, 'id="ut-delete-table"'));
    $table = substr($table, 0, (int) strpos($table, '</table>'));
    preg_match_all('/data-ut-suspend-id="(\d+)"/', $table, $m);
    return array_map('intval', $m[1]);
};
$id = fn (string $name) => $site->userId($name);

$review = $search($admin, ['status' => 'blocked', 'excluded' => 'stale6']);
$listed = $rows($review);
Test::check(in_array($id('stale1'), $listed, true) && in_array($id('stale5'), $listed, true) && in_array($id('old1'), $listed, true), '"Only blocked" lists the blocked inactive accounts');
Test::check(!in_array($id('stale6'), $listed, true), 'excluded usernames are not listed');
Test::check(!in_array($id('old2'), $listed, true) && !in_array($id('mgr'), $listed, true), '"Only blocked" leaves active accounts out');
Test::check(str_contains($review, 'I confirm the permanent deletion'), 'the review asks for explicit confirmation');

$review = $search($admin, ['status' => 'all']);
$listed = $rows($review);
Test::check(in_array($id('old2'), $listed, true) && in_array($id('mgr'), $listed, true), 'a Super User may delete inactive backend accounts when administrators are not excluded');
Test::check(!in_array($id('super2'), $listed, true) && !in_array($id('admin'), $listed, true), 'Super Users are never listed for deletion');
$review = $search($admin, ['status' => 'all', 'protect_admins' => 1, 'excluded_groups' => [2]]);
$listed = $rows($review);
Test::check(!in_array($id('mgr'), $listed, true) && !in_array($id('old2'), $listed, true) && !in_array($id('stale1'), $listed, true), 'administrator and group exclusions apply');

$review = $search($admin, ['status' => 'blocked']);
$staleIds = [$id('stale1'), $id('stale2')];
$stale1   = $staleIds[0];
$site->q('INSERT INTO #__session (session_id, client_id, guest, time, data, userid, username) VALUES (?, 0, 0, ?, ?, ?, ?)', [bin2hex(random_bytes(16)), time() - 3 * 86400, '', $stale1, 'stale1']);
$fields = ['task' => 'delete.delete', 'selection_ready' => 1, 'confirm' => 1, 'selected' => json_encode([$stale1])];
$denied = $admin->submit('index.php?option=com_usertools&view=delete&layout=review', $fields, 'data-ut-suspension-form');
Test::check(str_contains($denied, 'type the number of affected accounts: 1') && $id('stale1') === $stale1, 'deletion always requires typing the number, even for one account');
$result = $admin->submit('index.php?option=com_usertools&view=delete&layout=review', ['selected' => json_encode($staleIds), 'confirm_count' => '2'] + $fields, 'data-ut-suspension-form');
Test::check(str_contains($result, 'Deletion finished'), 'deletion applied');
Test::check($id('stale1') === 0 && $id('stale2') === 0 && $id('stale3') > 0, 'only the selected accounts were deleted');
Test::check(count($site->q('SELECT user_id FROM #__user_usergroup_map WHERE user_id IN (?, ?)', $staleIds)) === 0, 'group memberships of deleted accounts are removed');
Test::check($site->sessions($stale1) === 0, 'sessions of deleted accounts are removed');
$logs = $site->q("SELECT message FROM #__action_logs WHERE message_language_key = 'COM_USERTOOLS_ACTION_LOG_DELETED'");
$logged = array_map(fn ($r) => json_decode($r['message'], true)['title'] ?? '', $logs);
sort($logged);
Test::check($logged === ['stale1', 'stale2'], 'each deletion is in the User Actions Log (' . json_encode($logged) . ')');
$actionLog = $admin->get('index.php?option=com_actionlogs&view=actionlogs');
Test::check(str_contains($actionLog, 'deleted the inactive account stale1'), 'the User Actions Log shows a readable deletion entry');
[$code, $replay] = $admin->request('index.php?option=com_usertools', ['selected' => json_encode([$id('stale3')]), 'confirm_count' => '1'] + $fields + [Client::token($result) => 1]);
Test::check($id('stale3') > 0, 'a replayed deletion request deletes nothing');

$review = $search($admin, ['status' => 'blocked']);
$site->q('UPDATE #__users SET "lastvisitDate" = ? WHERE username = ?', [$recent, 'stale3']);
$result = $admin->submit('index.php?option=com_usertools&view=delete&layout=review', ['selected' => json_encode([$id('stale3')]), 'confirm_count' => '1'] + $fields, 'data-ut-suspension-form');
Test::check($id('stale3') > 0 && str_contains($result, 'changed after the review'), 'an account that logged in after the review is skipped');

$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1]])]);
[$code] = $delegate->request('index.php?option=com_usertools&view=delete');
Test::check($code === 403, 'deletion needs its own permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.delete' => ['6' => 1]])]);
$review = $search($delegate, ['status' => 'all']);
$listed = $rows($review);
Test::check(in_array($id('old2'), $listed, true) && !in_array($id('mgr'), $listed, true) && !in_array($id('delegate'), $listed, true), 'a delegate can delete registered accounts but never backend accounts or itself');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");

Test::section('Dashboard module on the Users dashboard');
$dashboardUrl = 'index.php?option=com_cpanel&view=cpanel&dashboard=users';
$moduleCount = function (string $html, string $name): ?int {
    return preg_match('/data-ut-count="' . $name . '">(\d+)</', $html, $m) ? (int) $m[1] : null;
};
$dashboard = $admin->get($dashboardUrl);
Test::check(str_contains($dashboard, 'mod-usertools') && str_contains($dashboard, 'Inactive accounts not blocked'), 'the summary module is published on the Users dashboard');
Test::check(str_contains($dashboard, 'UT integration'), 'the module lists the User Tools tasks');
// Figures match what the tools list with the same options.
$review = $admin->submit('index.php?option=com_usertools&view=inactive', ['task' => 'inactive.search', 'days' => 365, 'include_never' => 0, 'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
preg_match_all('/data-ut-suspend-id="\d+"/', $review, $inactiveRows);
$review = $search($admin, ['status' => 'blocked', 'protect_admins' => 1, 'days' => 365]);
$pendingRows = count($rows($review));
$dashboard = $admin->get($dashboardUrl);
Test::check($moduleCount($dashboard, 'inactive') === count($inactiveRows[0]), 'inactive count matches the inactivity review (' . $moduleCount($dashboard, 'inactive') . ' / ' . count($inactiveRows[0]) . ')');
Test::check($moduleCount($dashboard, 'pending') === $pendingRows, 'pending deletion count matches the deletion review with "Only blocked" (' . $moduleCount($dashboard, 'pending') . ' / ' . $pendingRows . ')');
Test::check(str_contains($dashboard, 'view=delete&amp;status=blocked') || str_contains($dashboard, 'view=delete&status=blocked'), 'the pending deletion figure opens the deletion tool with "Only blocked"');
$deleteFromDashboard = $admin->get('index.php?option=com_usertools&view=delete&status=blocked');
Test::check((bool) preg_match('/id="ut-status-blocked"[^>]* checked/', $deleteFromDashboard), 'the link preselects "Only blocked"');
// A change made with a tool refreshes the figures immediately.
$before = $moduleCount($dashboard, 'inactive');
$firstInactive = (int) ($inactiveRows[0] ? preg_replace('/\D/', '', $inactiveRows[0][0]) : 0);
if ($before > 0) {
    $admin->submit('index.php?option=com_usertools&view=inactive', ['task' => 'inactive.search', 'days' => 365, 'include_never' => 0, 'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
    $admin->submit('index.php?option=com_usertools&view=inactive&layout=review', ['task' => 'inactive.suspend', 'selection_ready' => 1, 'confirm' => 1, 'confirm_count' => '1', 'selected' => json_encode([$firstInactive])], 'data-ut-suspension-form');
    Test::check($moduleCount($admin->get($dashboardUrl), 'inactive') === $before - 1, 'the dashboard is refreshed after a tool changes accounts');
}
// A task stopped by the safety limit is reported.
$guardParams = ['dry_run' => 0, 'days' => 1, 'include_never' => 1, 'protect_admins' => 1, 'excluded_names' => '', 'excluded_groups' => [], 'guard_percent' => 1, 'guard_minimum' => 1];
$site->q("DELETE FROM #__scheduler_tasks WHERE title = 'UT guard'");
$site->q('INSERT INTO #__scheduler_tasks (asset_id, title, type, execution_rules, cron_rules, state, last_exit_code, times_executed, times_failed, locked, priority, ordering, params, note, created, created_by) VALUES (0, ?, ?, ?, ?, 1, 0, 0, 0, NULL, 0, 0, ?, ?, ?, ?)',
    ['UT guard', 'usertools.block_inactive', json_encode(['rule-type' => 'manual']), json_encode(['type' => 'manual']), json_encode($guardParams), '', gmdate('Y-m-d H:i:s'), $site->userId('admin')]);
$guardId = (int) $site->q("SELECT id FROM #__scheduler_tasks WHERE title = 'UT guard'")[0]['id'];
// Two inactive active accounts out of the active ones exceed the 1 % limit.
$site->q('UPDATE #__users SET "lastvisitDate" = ?, block = 0 WHERE username IN (?, ?)', [$old, 'ana', 'luis']);
$site->cli('scheduler:run --id=' . $guardId);
Test::check(!$site->blocked('ana') && !$site->blocked('luis'), 'the stopped run blocked nobody');
$dashboard = $admin->get($dashboardUrl);
Test::check(str_contains($dashboard, 'The task «UT guard» was stopped by the safety limit'), 'a task stopped for safety raises a danger alert');
Test::check(str_contains($dashboard, 'Stopped for safety'), 'the task table shows the result of the last run');
$site->q('UPDATE #__scheduler_tasks SET state = 0 WHERE id = ?', [$guardId]);
// Permissions: each figure only for users allowed to use its tool.
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1]])]);
$usersRules = (string) $site->q("SELECT rules FROM #__assets WHERE name = 'com_users'")[0]['rules'];
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_users'", [json_encode((json_decode($usersRules, true) ?: []) + ['core.manage' => ['6' => 1]])]);
$delegateDashboard = $delegate->get($dashboardUrl);
Test::check(str_contains($delegateDashboard, 'mod-usertools') && $moduleCount($delegateDashboard, 'inactive') === null && $moduleCount($delegateDashboard, 'pending') === null && !str_contains($delegateDashboard, 'UT integration'),
    'a user without the inactivity, deletion or results permissions sees no figures or tasks');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['usertools.compare' => ['6' => 1]])]);
Test::check(!str_contains($delegate->get($dashboardUrl), 'mod-usertools'), 'the module is hidden from users who cannot manage User Tools');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_users'", [$usersRules]);

echo "\n" . Test::$passed . ' checks passed, ' . count(Test::$failed) . " failed\n";
exit(Test::$failed ? 1 : 0);
