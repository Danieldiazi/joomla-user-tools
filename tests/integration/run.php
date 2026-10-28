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
preg_match_all('/data-ut-home-section="([a-z]+)"/', $home, $sections);
Test::check($sections[1] === ['accounts', 'groups', 'automation', 'delete'], 'home groups the tools in four sections, deletion last (' . implode(',', $sections[1]) . ')');
preg_match_all('/data-ut-home-card="([a-z]+)"/', $home, $cards);
Test::check($cards[1] === ['compare', 'inactive', 'ungroup', 'access', 'usage', 'age', 'diagnose', 'schedule', 'reports', 'delete'], 'every tool has its card, in order');
$deleteCard = substr($home, (int) strpos($home, 'data-ut-home-card="delete"'), 1500);
Test::check(str_contains($deleteCard, 'Permanent') && str_contains($deleteCard, 'Deletion cannot be undone.') && str_contains($deleteCard, 'btn-outline-danger'),
    'deletion says «Permanent» and its consequence in text, not only in red');
Test::check(substr_count($home, '<h2 class="ut-home-heading"') === 4 && substr_count($home, '<h3 class="card-title ut-card-title"') === 10 && str_contains($home, 'aria-describedby="ut-card-ungroup"'),
    'sections are h2 and cards h3; each button is described by its card title');
Test::check(str_contains($home, 'Task plugin:') && !str_contains($home, 'MANUAL'), 'the plugin state sits in the task card; no repeated «Manual» labels');
Test::check(!str_contains($home, 'User Tools home'), 'the home screen has no link to itself in the toolbar');
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
Test::check(str_contains($review, 'data-ut-add-all') && str_contains($review, 'data-ut-email-domain') && str_contains($review, 'data-ut-name="nuevo1"'),
    'new accounts offer "tick all" and emails built from the username');
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

Test::section('Comparison in batches: progress, continue, discard, one-time passwords');
// One account per request, as if each one hit the time budget.
$site->options(['batch_seconds' => 0]);
// Every current username except $without, plus new names: only $without can be proposed for blocking.
$roster   = fn (array $without, array $new): string => implode("\n", array_merge(array_diff(array_column($site->q('SELECT username FROM #__users'), 'username'), $without), $new));
$registrationsFor = function (array $names): string {
    $rows = [];
    foreach ($names as $name) {
        $rows[hash('sha256', $name)] = ['checked' => true, 'email' => $name . '@example.test'];
    }
    return json_encode($rows);
};
$step = function (Client $client, string $page): array {
    [$code, $body] = $client->request('index.php?option=com_usertools', ['task' => 'compare.step', Client::token($page) => 1]);
    return json_decode($body, true) ?: ['http' => $code, 'body' => substr($body, 0, 200)];
};
$logCount = fn (string $key): int => (int) $site->q('SELECT COUNT(*) AS n FROM #__action_logs WHERE message_language_key = ?', [$key])[0]['n'];
$createdBefore = $logCount('COM_USERTOOLS_ACTION_LOG_CREATED_EMAIL');
$blockedBefore = $logCount('COM_USERTOOLS_ACTION_LOG_BLOCKED');

$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster(['baja', 'servicio'], ['lote1', 'lote2', 'lote3']),
    'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$progress = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'email', 'registrations' => $registrationsFor(['lote1', 'lote2', 'lote3'])], 'data-ut-review');
Test::check(str_contains($progress, 'data-ut-progress') && str_contains($progress, '1 of 5 accounts processed'), 'a large job continues on the progress screen after the first batch');
Test::check($site->blocked('baja') && !$site->blocked('servicio') && $site->userId('lote1') === 0, 'the first batch did one account only');
[, $noToken] = $admin->request('index.php?option=com_usertools', ['task' => 'compare.step']);
Test::check(!str_contains($noToken, '"processed"') && !$site->blocked('servicio'), 'a batch without the form token does nothing');
$data = $step($admin, $progress);
Test::check(($data['done'] ?? null) === false && ($data['processed'] ?? 0) === 2 && $site->blocked('servicio'), 'each request processes the next account (' . json_encode($data) . ')');
// The operator leaves: the first screen offers to continue or discard, and nothing else can be applied meanwhile.
$first = $admin->get('index.php?option=com_usertools&view=compare');
Test::check(str_contains($first, 'data-ut-job-pending') && str_contains($first, '2 of 5 accounts have been processed'), 'a job left half-way is offered to continue or discard');
$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster([], []), 'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$refused = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'email', 'registrations' => '{}'], 'data-ut-review');
Test::check(str_contains($refused, 'A comparison is partly applied'), 'another comparison cannot be applied while one is pending');
$progress = $admin->get('index.php?option=com_usertools&view=compare&layout=progress');
for ($i = 0, $data = []; $i < 10 && empty($data['done']); $i++) {
    $data = $step($admin, $progress);
}
Test::check(($data['done'] ?? false) === true && ($data['processed'] ?? 0) === 5 && $i === 3, 'continuing finishes the job, one account per request');
Test::check($site->userId('lote1') && $site->userId('lote2') && $site->userId('lote3') && array_key_exists('password', $data['created'][0] ?? []) && $data['created'][0]['password'] === null, 'the three accounts were created; email mode sends no password');
Test::check($logCount('COM_USERTOOLS_ACTION_LOG_CREATED_EMAIL') === $createdBefore + 3 && $logCount('COM_USERTOOLS_ACTION_LOG_BLOCKED') === $blockedBefore + 2,
    'every block and creation is in the User Actions Log');
$summary = $admin->get('index.php?option=com_usertools&view=compare&layout=result');
Test::check(str_contains($summary, 'Operation finished') && str_contains($summary, 'lote3@example.test') && str_contains($summary, '>baja<'), 'the summary covers every batch');
Test::check(!str_contains($admin->get('index.php?option=com_usertools&view=compare'), 'data-ut-job-pending'), 'nothing is pending afterwards');

// Discarding keeps what was done and leaves the rest untouched.
$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster(['baja', 'servicio', 'lote1', 'lote2'], ['lote3', 'lote4', 'lote5']),
    'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'email', 'registrations' => $registrationsFor(['lote4', 'lote5'])], 'data-ut-review');
$discarded = $admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.discard'], 'data-ut-job-pending');
Test::check($site->blocked('lote1') && !$site->blocked('lote2') && $site->userId('lote4') === 0 && str_contains($discarded, '3 pending accounts were neither blocked nor created'),
    'discarding keeps the first account and leaves the other three untouched');

// Password mode: each batch shows the passwords it created, and none is stored.
$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster(['baja', 'servicio', 'lote1'], ['lote6', 'lote7']),
    'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$progress = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'password', 'registrations' => $registrationsFor(['lote6', 'lote7'])], 'data-ut-review');
preg_match('#<script type="application/json" data-ut-progress-initial>(.*?)</script>#s', $progress, $initial);
$firstRows = json_decode($initial[1] ?? '[]', true) ?: [];
$data = $step($admin, $progress);
$passwords = array_filter([$firstRows[0]['password'] ?? '', $data['created'][0]['password'] ?? '']);
Test::check(count($passwords) === 2 && ($firstRows[0]['username'] ?? '') === 'lote6' && ($data['done'] ?? false) === true, 'each batch returns the passwords it created, once');
$stored = implode('', array_column($site->q('SELECT data FROM #__session'), 'data'));
Test::check(!array_filter($passwords, fn ($p) => str_contains($stored, $p)), 'no batch password is stored in any session');
$summary = $admin->get('index.php?option=com_usertools&view=compare&layout=result');
Test::check(!array_filter($passwords, fn ($p) => str_contains($summary, $p)) && str_contains($summary, 'Already shown'), 'the summary never shows them again');
$site->options(['batch_seconds' => 8]);
$site->q('UPDATE #__users SET block = 0 WHERE username IN (?, ?)', ['baja', 'servicio']);

Test::section('Delegated permission: no Super User, no backend accounts');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1]])]);
$site->q('UPDATE #__users SET block = 0 WHERE username = ?', ['mgr']);
$delegate = new Client($url);
$delegate->login('delegate', $delegatePassword);
$dhome = $delegate->get('index.php?option=com_usertools');
Test::check(str_contains($dhome, 'Compare a list of users') && !str_contains($dhome, 'Review inactive users'), 'the delegate sees only the tools it is allowed to use');
Test::check(!str_contains($dhome, 'data-ut-home-section="groups"') && !str_contains($dhome, 'data-ut-home-section="delete"') && str_contains($dhome, 'data-ut-home-section="accounts"'),
    'sections with no allowed tool are left out');
[$code] = $delegate->request('index.php?option=com_usertools&view=inactive');
Test::check($code === 403, 'the delegate cannot open the inactivity review (got ' . $code . ')');
$review = $delegate->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => "ana\nluis", 'protect_admins' => 0, 'excluded' => '', 'excluded_groups' => []]);
$block = substr($review, (int) strpos($review, 'id="ut-block-table"'));
$block = substr($block, 0, (int) strpos($block, '</table>'));
Test::check(!str_contains($block, '>mgr<') && !str_contains($block, '>delegate<') && str_contains($block, '>baja<'), 'a non Super User cannot block backend accounts or itself');
Test::check(str_contains($review, 'you are not allowed to create accounts') || !str_contains($review, 'data-ut-add'), 'a delegate without "Create accounts" cannot create users');
// "Create accounts" withdrawn while a job is paused: continuing creates nothing more.
$site->options(['batch_seconds' => 0]);
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1], 'usertools.create' => ['6' => 1]])]);
$delegate->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster([], ['permiso1', 'permiso2']),
    'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$progress = $delegate->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'email', 'registrations' => $registrationsFor(['permiso1', 'permiso2'])], 'data-ut-review');
Test::check(str_contains($progress, '1 of 2 accounts processed') && $site->userId('permiso1') > 0, 'the delegate starts a job that creates accounts');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.compare' => ['6' => 1]])]);
$data = $step($delegate, $progress);
Test::check(str_contains($data['error'] ?? '', 'no longer allowed to create accounts') && $site->userId('permiso2') === 0, 'without "Create accounts" the rest is not created (' . json_encode($data) . ')');
$delegate->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.discard'], 'data-ut-job-pending');
// Registered gains administrator access while a job is paused: continuing creates nothing more there.
$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.preview', 'names' => $roster([], ['grupo1', 'grupo2']),
    'protect_admins' => 1, 'excluded' => '', 'excluded_groups' => []]);
$progress = $admin->submit('index.php?option=com_usertools&view=compare&layout=review', ['task' => 'compare.apply', 'review_ready' => 1, 'confirm' => 1,
    'creation_mode' => 'email', 'registrations' => $registrationsFor(['grupo1', 'grupo2'])], 'data-ut-review');
$rootRules = (string) $site->q("SELECT rules FROM #__assets WHERE name = 'root.1'")[0]['rules'];
$withAdmin = json_decode($rootRules, true) ?: [];
$withAdmin['core.login.admin'][(string) $site->q("SELECT id FROM #__usergroups WHERE title = 'Registered'")[0]['id']] = 1;
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'root.1'", [json_encode($withAdmin)]);
$data = $step($admin, $progress);
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'root.1'", [$rootRules]);
Test::check($site->userId('grupo1') > 0 && $site->userId('grupo2') === 0 && isset($data['error']), 'a target group that gained administrator access stops the pending creations (' . json_encode($data) . ')');
$admin->submit('index.php?option=com_usertools&view=compare', ['task' => 'compare.discard'], 'data-ut-job-pending');
$site->options(['batch_seconds' => 8]);
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
Test::check(str_contains($dashboard, 'mod-usertools') && str_contains($dashboard, 'Inactive accounts to review'), 'the summary module is published on the Users dashboard');
Test::check(str_contains($dashboard, 'UT integration'), 'the module lists the User Tools tasks');
Test::check(str_contains($dashboard, 'data-ut-dashboard-task=') && preg_match('/<dl\b[^>]*>\s*<div\b[^>]*>\s*<dt\b/', $dashboard) === 1,
    'tasks expose labelled dates without squeezing five table columns');
Test::check(str_contains($dashboard, 'They are not deleted automatically.') && str_contains($dashboard, 'Review accounts to delete'),
    'the deletion count describes candidates and leads to a review, not an immediate deletion');
Test::check(str_contains($dashboard, 'Data updated:'), 'the module labels the data timestamp');
Test::check(str_contains($dashboard, 'Dry runs do not change accounts.') === str_contains($dashboard, 'data-ut-task-mode="dry"'),
    'dry runs are explained only when some task is a dry run');
Test::check(str_contains($dashboard, 'data-ut-task-mode="real"') && str_contains($dashboard, 'text-bg-warning" data-ut-task-mode="real"'),
    'live blocking is told apart from the disabled badge');

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
Test::check(str_contains($dashboard, 'Stopped for safety'), 'the task list shows the result of the last run');
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

Test::section('Groups module on the site');
foreach ($site->q("SELECT id FROM #__modules WHERE title LIKE 'UT groups%'") as $row) {
    $site->q('DELETE FROM #__modules_menu WHERE moduleid = ?', [$row['id']]);
    $site->q('DELETE FROM #__modules WHERE id = ?', [$row['id']]);
}
$site->addUser('gm_author', 'Author');
$site->addUser('gm_editor', 'Editor');
$site->addUser('gm_mgr', 'Manager,Author');
$site->addUser('gm_blocked', 'Author');
$site->q('UPDATE #__users SET block = 1 WHERE username = ?', ['gm_blocked']);
$site->q('UPDATE #__users SET name = ? WHERE username = ?', ['<b>Gm</b> Author', 'gm_author']);
$author = (int) $site->q("SELECT id FROM #__usergroups WHERE title = 'Author'")[0]['id'];
$guest  = (int) $site->q("SELECT id FROM #__usergroups WHERE title = 'Guest'")[0]['id'];
$public = (int) $site->q("SELECT id FROM #__usergroups WHERE title = 'Public'")[0]['id'];
$groupModules = [
    'UT groups members' => ['mode' => 'members', 'group_id' => $author, 'subgroups' => 1, 'display' => 'both', 'limit' => 50],
    'UT groups admins'  => ['mode' => 'members', 'group_id' => $author, 'subgroups' => 0, 'display' => 'username', 'hide_backend' => 0],
    'UT groups limited' => ['mode' => 'members', 'group_id' => $author, 'subgroups' => 1, 'display' => 'username', 'limit' => 1],
    'UT groups empty'   => ['mode' => 'members', 'group_id' => $guest, 'show_empty' => 0],
    'UT groups mine'    => ['mode' => 'user', 'user_source' => 'current'],
    'UT groups editor'  => ['mode' => 'user', 'user_source' => 'specific', 'user_id' => $site->userId('gm_editor'), 'inherited' => 1, 'hidden_groups' => [$public]],
];
foreach ($groupModules as $title => $params) {
    $site->q("INSERT INTO #__modules (title, note, content, ordering, position, published, module, access, showtitle, params, client_id, language) VALUES (?, '', '', 0, 'sidebar-right', 1, 'mod_usertools_groups', 1, 1, ?, 0, '*')",
        [$title, json_encode($params)]);
    $site->q('INSERT INTO #__modules_menu (moduleid, menuid) SELECT id, 0 FROM #__modules WHERE title = ?', [$title]);
}
$page = (string) file_get_contents($url . '/index.php');
Test::noPhpErrors($page, 'site home');
$block = function (string $title) use ($page): ?string {
    $start = strpos($page, '>' . $title . '<');
    if ($start === false) {
        return null;
    }
    $next = strpos($page, 'card-header', $start + 1);
    return substr($page, $start, ($next === false ? strlen($page) : $next) - $start);
};
$members = (string) $block('UT groups members');
Test::check(str_contains($members, '&lt;b&gt;Gm&lt;/b&gt; Author (gm_author)') && str_contains($members, 'Gm_editor (gm_editor)'), 'members of a group and its subgroups are listed, escaped');
Test::check(!str_contains($members, 'gm_mgr') && !str_contains($members, 'gm_blocked') && !str_contains($members, '@example.test'), 'administrator accounts, blocked accounts and emails are never listed by default');
$admins = (string) $block('UT groups admins');
Test::check(str_contains($admins, 'gm_mgr') && !str_contains($admins, 'gm_editor'), 'administrator accounts can be shown on request; subgroups only when asked');
$limited = (string) $block('UT groups limited');
Test::check(substr_count($limited, '<li') === 1 && str_contains($limited, 'and 1 more'), 'the limit is applied and the rest is counted');
Test::check($block('UT groups empty') === null, 'an empty list is hidden when asked');
Test::check($block('UT groups mine') === null, 'guests do not see a list of their own groups');
$editor = (string) $block('UT groups editor');
Test::check(str_contains($editor, 'Editor') && str_contains($editor, 'class="text-body-secondary">Author') && !str_contains($editor, '>Public<'), 'a chosen user shows inherited groups dimmed, without hidden ones');

Test::section('Groups and access levels');
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT level%'");
$registeredId = (int) $site->q("SELECT id FROM #__usergroups WHERE title = 'Registered'")[0]['id'];
$editorId     = (int) $site->q("SELECT id FROM #__usergroups WHERE title = 'Editor'")[0]['id'];
$site->q("INSERT INTO #__viewlevels (title, ordering, rules) VALUES ('UT level empty', 90, '[]')");
$site->q('INSERT INTO #__viewlevels (title, ordering, rules) VALUES (?, 91, ?)', ['UT level authors', json_encode([$author, -$site->userId('gm_mgr')])]);
$accessPage = $admin->get('index.php?option=com_usertools&view=access');
Test::check(str_contains($accessPage, 'User Tools home') && (bool) preg_match('#href="[^"]*index\.php\?option=com_usertools"[^>]*>\s*<span class="icon-home#', $accessPage),
    'each screen has a toolbar button back to the User Tools home');
$row = function (string $html, string $attribute, int $id): string {
    $start = strpos($html, $attribute . '="' . $id . '"');
    return $start === false ? '' : substr($html, $start, (int) strpos($html, '</tr>', $start) - $start);
};
$editorRow = $row($accessPage, 'data-ut-group', $editorId);
Test::check(substr_count($editorRow, 'data-ut-cell="inherited"') >= 2 && str_contains($editorRow, 'from group «Author»'), 'Editor inherits Special and the new level from its parent Author');
Test::check(str_contains($row($accessPage, 'data-ut-group', $registeredId), 'data-ut-cell="direct"'), 'Registered is assigned directly to the Registered level');
Test::check(str_contains($row($accessPage, 'data-ut-group', $guest), 'Guests'), 'the guest group is marked');
$emptyLevel   = (int) $site->q("SELECT id FROM #__viewlevels WHERE title = 'UT level empty'")[0]['id'];
$authorsLevel = (int) $site->q("SELECT id FROM #__viewlevels WHERE title = 'UT level authors'")[0]['id'];
Test::check(str_contains($row($accessPage, 'data-ut-level', $emptyLevel), 'No groups'), 'a level with no groups or accounts is flagged');
$authorsRow = $row($accessPage, 'data-ut-level', $authorsLevel);
Test::check(str_contains($authorsRow, '>1</td>') && !str_contains($authorsRow, 'No groups'), 'accounts assigned individually are counted');
Test::check(str_contains($accessPage, 'Accounts assigned individually'), 'the individual accounts column is shown when some level has one');

// CSV export: the links carry the form token; without it nothing is sent.
preg_match_all('/href="([^"]*task=access\.export[^"]*)"/', $accessPage, $links);
Test::check(count($links[1]) === 2, 'two export links (table and one row per level)');
$exportUrl = fn (string $shape): string => html_entity_decode((string) current(array_filter($links[1], fn ($l) => str_contains($l, 'shape=' . $shape))));
[$code, $csv, $headers] = $admin->request(preg_replace('#^.*/administrator/#', '', $exportUrl('matrix')));
Test::check($code === 200 && str_starts_with($headers['content-type'] ?? '', 'text/csv') && str_contains($headers['content-disposition'] ?? '', 'usertools-access-'), 'the table downloads as CSV');
Test::check(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, ';"UT level authors"') && str_contains($csv, '"Public / Registered / Author / Editor"'), 'with BOM, a column per level and the group path');
$editorLine = (string) current(array_filter(explode("\n", $csv), fn ($l) => str_starts_with($l, $editorId . ';')));
Test::check(str_contains($editorLine, 'Inherited from group «Author»'), 'cells say where an inherited level comes from');
[$code, $pairs] = $admin->request(preg_replace('#^.*/administrator/#', '', $exportUrl('pairs')));
Test::check($code === 200 && str_contains($pairs, $editorId . ';Editor;"Public / Registered / Author / Editor";' . $authorsLevel . ';"UT level authors";Inherited;Author'), 'one row per group and level, with the parent it comes from');
Test::check(!str_contains($pairs, ';"UT level empty";'), 'levels a group does not have are left out');
$tokenless = preg_replace('/&(amp;)?[0-9a-f]{32}=1/', '', $exportUrl('matrix'));
[, $refused, $refusedHeaders] = $admin->request(preg_replace('#^.*/administrator/#', '', $tokenless));
Test::check(!str_starts_with($refusedHeaders['content-type'] ?? '', 'text/csv') && !str_contains($refused, '"UT level authors"'), 'without the token there is no CSV');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1]])]);
[$code] = $delegate->request('index.php?option=com_usertools&view=access');
Test::check($code === 403 && !str_contains($delegate->get('index.php?option=com_usertools'), 'view=access'), 'the view needs its own permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.access' => ['6' => 1]])]);
// The delegate may manage users (user list) but is not Admin of Users (groups and levels screens).
$usersRules = (string) $site->q("SELECT rules FROM #__assets WHERE name = 'com_users'")[0]['rules'];
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_users'", [json_encode((json_decode($usersRules, true) ?: []) + ['core.manage' => ['6' => 1]])]);
$delegateAccess = $delegate->get('index.php?option=com_usertools&view=access');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_users'", [$usersRules]);
Test::check(str_contains($delegateAccess, 'id="ut-access-table"'), 'a delegate with the permission can open it');
Test::check(!str_contains($delegateAccess, 'task=group.edit') && !str_contains($delegateAccess, 'task=level.edit') && str_contains($delegateAccess, 'filter[group_id]='),
    'without core.admin on Users, group and level titles are not links (Joomla would refuse them); member counts still link');
Test::check(str_contains($accessPage, 'task=group.edit') && str_contains($accessPage, 'task=level.edit'), 'a Super User gets the edit links');
[$code, $delegateCsv, $delegateHeaders] = $delegate->request('index.php?option=com_usertools&task=access.export&shape=matrix');
Test::check(!str_starts_with($delegateHeaders['content-type'] ?? '', 'text/csv'), 'a request without the token gets no CSV, even with the permission');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT level%'");
$plainLevels = $admin->get('index.php?option=com_usertools&view=access');
Test::check(!str_contains($plainLevels, 'Accounts assigned individually'), 'with no level assigned to single accounts, that column is hidden');

Test::section('Content by access level');
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT usage%'");
$site->q("INSERT INTO #__viewlevels (title, ordering, rules) VALUES ('UT usage level', 95, '[2]')");
$site->q("INSERT INTO #__viewlevels (title, ordering, rules) VALUES ('UT usage empty', 96, '[2]')");
$usageLevel = (int) $site->q("SELECT id FROM #__viewlevels WHERE title = 'UT usage level'")[0]['id'];
$category   = $site->q("SELECT id, access FROM #__categories WHERE extension = 'com_content' AND parent_id <> 0 ORDER BY id")[0];
$modules    = $site->q('SELECT id, access FROM #__modules WHERE client_id = 0 ORDER BY id');
$site->q('UPDATE #__categories SET access = ? WHERE id = ?', [$usageLevel, $category['id']]);
$site->q('UPDATE #__modules SET access = ? WHERE id = ?', [$usageLevel, $modules[0]['id']]);
$site->q('UPDATE #__modules SET access = 999 WHERE id = ?', [$modules[1]['id']]);
$site->q('UPDATE #__modules SET access = ?, published = -2 WHERE id = ?', [$usageLevel, $modules[2]['id']]);
// A table of another extension with an "access" column, as an attachments extension would have.
$site->db->exec('CREATE TABLE ' . $site->prefix . 'ut_gallery (id INT, access INT)');
$site->q('INSERT INTO #__ut_gallery (id, access) VALUES (1, ?), (2, ?), (3, ?), (4, 1)', [$usageLevel, $usageLevel, $usageLevel]);
$usage = $admin->get('index.php?option=com_usertools&view=usage');
// One row per level, one column per kind of item.
$levelRow = function (int $id) use ($usage): string {
    $start = strpos($usage, 'data-ut-usage-level="' . $id . '"');
    return $start === false ? '' : substr($usage, $start, (int) strpos($usage, '</tr>', $start) - $start);
};
$cell = function (int $id, string $kind) use ($levelRow): string {
    $row   = $levelRow($id);
    $start = strpos($row, 'data-ut-usage-cell="' . $kind . '"');
    return $start === false ? '' : substr($row, $start, (int) strpos($row, '</td>', $start) - $start);
};
$count = fn (int $id, string $kind): int => preg_match('/data-ut-usage-count="(\d+)"/', $cell($id, $kind), $c) ? (int) $c[1] : -1;
$hasKind = fn (string $kind): bool => str_contains($usage, 'data-ut-usage-kind="' . $kind . '"');
Test::check($count($usageLevel, 'categories:com_content') === 1, 'the category moved to the new level is counted in its row');
Test::check(str_contains($cell($usageLevel, 'categories:com_content'), 'option=com_categories&amp;view=categories&amp;extension=com_content&amp;filter[access]=' . $usageLevel),
    'the count links to the categories list filtered by that level');
Test::check($count($usageLevel, 'modules') === 1, 'a trashed module is not counted');
Test::check(str_contains($levelRow($usageLevel), 'data-ut-usage-total="5"'), 'each level row ends with its total (1 category, 1 module, 3 gallery rows)');
Test::check(str_contains($usage, 'data-ut-usage-missing') && str_contains($levelRow(999), 'ID 999 (missing)') && $count(999, 'modules') === 1,
    'items with a level that does not exist get their own row and a warning');
Test::check($count($usageLevel, 'table:#__ut_gallery') === 3 && !str_contains($cell($usageLevel, 'table:#__ut_gallery'), '<a ') && str_contains($usage, 'From another extension'),
    'a table of another extension with an access column is counted, without a link');
Test::check(str_contains($usage, 'Levels with no items: ') && str_contains($usage, 'UT usage empty') && !$hasKind('table:#__finder_links'),
    'unused levels are listed; the Smart Search index is not counted');
Test::check($hasKind('plugins') && $hasKind('menu') && str_contains($usage, 'data-ut-filter="ut-usage-table"'), 'plugins and site menu items are counted, and levels can be filtered');
preg_match('/href="([^"]*task=usage\.export[^"]*)"/', $usage, $export);
[$code, $csv, $headers] = $admin->request(preg_replace('#^.*/administrator/#', '', html_entity_decode($export[1] ?? '')));
Test::check($code === 200 && str_starts_with($headers['content-type'] ?? '', 'text/csv') && str_contains($csv, $usageLevel . ';"UT usage level";') && str_contains($csv, ';"Table #__ut_gallery";'),
    'the usage downloads as CSV');
[, , $tokenless] = $admin->request('index.php?option=com_usertools&task=usage.export');
Test::check(!str_starts_with($tokenless['content-type'] ?? '', 'text/csv'), 'without the token there is no CSV');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1]])]);
[$code] = $delegate->request('index.php?option=com_usertools&view=usage');
Test::check($code === 403, 'the screen needs the "View groups, levels and content by level" permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q('UPDATE #__categories SET access = ? WHERE id = ?', [$category['access'], $category['id']]);
foreach (array_slice($modules, 0, 3) as $module) {
    $site->q('UPDATE #__modules SET access = ? WHERE id = ?', [$module['access'], $module['id']]);
}
$site->q('UPDATE #__modules SET published = 1 WHERE id = ?', [$modules[2]['id']]);
$site->db->exec('DROP TABLE ' . $site->prefix . 'ut_gallery');
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT usage%'");

Test::section('Access diagnosis');
$diagUrl = 'index.php?option=com_usertools&view=diagnose';
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT diag%'");
$site->q("INSERT INTO #__viewlevels (title, ordering, rules) VALUES ('UT diag nobody', 97, '[99999]')");
$nobodyLevel = (int) $site->q("SELECT id FROM #__viewlevels WHERE title = 'UT diag nobody'")[0]['id'];
$diagOld   = gmdate('Y-m-d H:i:s', time() - 200 * 86400);
$diagAdmin = $site->addUser('diag_admin', 'Manager', '', $diagOld);
$diagNever = $site->addUser('diag_never', 'Administrator', '', $diagOld);
$site->q('UPDATE #__users SET "lastvisitDate" = NULL WHERE id = ?', [$diagNever]);
$diagRecent = $site->addUser('diag_recent', 'Manager', '', gmdate('Y-m-d H:i:s', time() - 5 * 86400));
$diagModule = $site->q('SELECT id, title, access FROM #__modules WHERE client_id = 0 AND published = 1 ORDER BY id')[0];
$site->q('UPDATE #__modules SET access = 998 WHERE id = ?', [$diagModule['id']]);
$diag = $admin->get($diagUrl);
Test::noPhpErrors($diag, 'the access diagnosis');
$check = function (string $html, string $key): string {
    $start = strpos($html, 'data-ut-check="' . $key . '"');
    return $start === false ? '' : substr($html, $start, (int) strpos($html, '</section>', $start) - $start);
};
preg_match_all('/data-ut-check="([a-z_]+)"/', $diag, $checks);
Test::check($checks[1] === ['admins', 'superusers', 'empty_levels', 'missing_levels', 'empty_groups'], 'every check is shown, in order, found or not');
$admins = $check($diag, 'admins');
Test::check(str_contains($admins, 'data-ut-diagnose-admin="' . $diagAdmin . '"') && str_contains($admins, 'data-ut-diagnose-admin="' . $diagNever . '"')
    && !str_contains($admins, 'data-ut-diagnose-admin="' . $diagRecent . '"'), 'backend accounts without activity for 90 days are listed, never-visited ones by registration date');
Test::check(str_contains($admins, 'Never (registered: ') && str_contains($admins, 'Why it matters:') && str_contains($admins, 'How to review it:') && str_contains($admins, '>Review<'),
    'each notice says what, why and how, with its type in text');
$site->options(['diagnose_admin_days' => 365]);
Test::check(!str_contains($check($admin->get($diagUrl), 'admins'), 'data-ut-diagnose-admin="' . $diagAdmin . '"'), 'the period comes from Options');
$site->options(['diagnose_admin_days' => 90]);
$supers = (int) $site->q("SELECT COUNT(DISTINCT u.id) AS n FROM #__users u JOIN #__user_usergroup_map m ON m.user_id = u.id WHERE m.group_id = 8 AND u.block = 0")[0]['n'];
Test::check(str_contains($check($diag, 'superusers'), 'data-ut-check-found="' . (int) ($supers <= 1) . '"') && str_contains($check($diag, 'superusers'), 'data-ut-check-count="' . $supers . '"'),
    'the number of active Super Users is reported (' . $supers . ')');
Test::check(str_contains($check($diag, 'empty_levels'), 'data-ut-diagnose-level="' . $nobodyLevel . '"') && !str_contains($check($diag, 'empty_levels'), 'data-ut-diagnose-level="1"'),
    'a level whose only group does not exist is reported; Public never is');
$missing = $check($diag, 'missing_levels');
preg_match('/data-ut-diagnose-kind="modules">[^<]*<a href="([^"]+)"/', $missing, $modulesLink);
Test::check(str_contains($missing, 'data-ut-diagnose-missing="998"') && isset($modulesLink[1]), 'content with a missing level is listed with a link to the filtered list');
$filtered = $admin->get(preg_replace('#^.*/administrator/#', '', html_entity_decode($modulesLink[1] ?? '')));
Test::check(str_contains($filtered, htmlspecialchars($diagModule['title'])), 'Joomla lists the item when filtered by the missing level');
$emptyGroups = $check($diag, 'empty_groups');
$publisherMembers = (int) $site->q("SELECT COUNT(*) AS n FROM #__user_usergroup_map m JOIN #__usergroups g ON g.id = m.group_id WHERE g.title = 'Publisher'")[0]['n'];
Test::check(str_contains($emptyGroups, '>Information<') && ($publisherMembers > 0 || str_contains($emptyGroups, '>Publisher</a>')) && !str_contains($emptyGroups, '>Public</a>')
    && !str_contains($emptyGroups, '>Guest</a>'), 'groups without members are information; Public and Guest are left out');
preg_match('/href="([^"]*task=diagnose\.export[^"]*)"/', $diag, $export);
[$code, $csv, $headers] = $admin->request(preg_replace('#^.*/administrator/#', '', html_entity_decode($export[1] ?? '')));
Test::check($code === 200 && str_starts_with($headers['content-type'] ?? '', 'text/csv') && str_contains($csv, ';' . $diagAdmin . ';diag_admin;') && str_contains($csv, '"ID 998 (missing)"'),
    'the findings download as CSV');
[, , $tokenless] = $admin->request('index.php?option=com_usertools&task=diagnose.export');
Test::check(!str_starts_with($tokenless['content-type'] ?? '', 'text/csv'), 'without the token there is no CSV');
// The dashboard module points to the diagnosis with the same count.
foreach (glob($site->path . '/administrator/cache/' . 'mod_usertools/*') ?: [] as $file) {
    @unlink($file);
}
$reviewChecks = substr_count($diag, 'data-ut-check-found="1" data-ut-check-severity="review"');
$dashboardDiag = $admin->get('index.php?option=com_cpanel&view=cpanel&dashboard=users');
Test::check(str_contains($dashboardDiag, 'The access diagnosis has ' . $reviewChecks . ' checks to review.') && str_contains($dashboardDiag, 'view=diagnose'),
    'the dashboard module warns with the same number of checks (' . $reviewChecks . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.access' => ['6' => 1]])]);
[$code] = $delegate->request($diagUrl);
Test::check($code === 403, 'the diagnosis needs its own permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q('UPDATE #__modules SET access = ? WHERE id = ?', [$diagModule['access'], $diagModule['id']]);
$site->q("DELETE FROM #__viewlevels WHERE title LIKE 'UT diag%'");
foreach (['diag_admin', 'diag_never', 'diag_recent'] as $name) {
    $site->cli('user:delete --username=' . $name);
}

Test::section('Content by age');
$ageUrl = 'index.php?option=com_usertools&view=age';
$site->q("DELETE FROM #__content WHERE alias LIKE 'ut-age-%'");
$ageCategory = (int) $site->q("SELECT id FROM #__categories WHERE extension = 'com_content' AND parent_id <> 0 ORDER BY id")[0]['id'];
$addArticle = function (string $alias, string $modified, int $state = 1, int $access = 1) use ($site, $ageCategory): int {
    $site->q('INSERT INTO #__content (title, alias, introtext, "fulltext", state, catid, created, created_by, modified, images, urls, attribs, access, metadesc, metadata, language)'
        . " VALUES (?, ?, '', '', ?, ?, ?, ?, ?, '{}', '{}', '{}', ?, '', '{}', '*')",
        ['UT ' . $alias, 'ut-age-' . $alias, $state, $ageCategory, '2010-05-05 10:00:00', $site->userId(getenv('UT_ADMIN_USER') ?: 'admin'), $modified, $access]);
    return (int) $site->q('SELECT id FROM #__content WHERE alias = ?', ['ut-age-' . $alias])[0]['id'];
};
$a2012 = $addArticle('old-a', '2012-03-01 00:00:00');
$addArticle('old-b', '2012-12-31 23:59:59', 2);
$addArticle('old-trash', '2012-06-01 00:00:00', -2);
$addArticle('old-registered', '2013-01-01 00:00:00', 0, 2);
$ageCount = function (string $html, int $year): int {
    return preg_match('/data-ut-age-year="' . $year . '".*?data-ut-age-cell="articles" data-ut-age-count="(\d+)"/s', $html, $m) ? (int) $m[1] : -1;
};
$age = $admin->get($ageUrl);
Test::noPhpErrors($age, 'content by age');
Test::check($ageCount($age, 2012) === 2 && $ageCount($age, 2013) === 1, 'articles are counted by year of last modification; the trash is not counted, archived ones are');
preg_match_all('/data-ut-age-year="(\d+)"/', $age, $years);
$sorted = $years[1];
rsort($sorted);
Test::check($years[1] === $sorted, 'years run from the newest to the oldest');
Test::check(str_contains($age, 'layout=list&amp;kind=articles&amp;year=2012') && str_contains($age, 'data-ut-age-total='), 'each number links to its list and each row has its total');
$filtered = $admin->get($ageUrl . '&level=2');
Test::check($ageCount($filtered, 2013) === 1 && !str_contains($filtered, 'data-ut-age-year="2012"'), 'the access level filter counts only that level');
$list = $admin->get($ageUrl . '&layout=list&kind=articles&year=2012');
Test::noPhpErrors($list, 'the list of one year');
Test::check(str_contains($list, 'data-ut-age-list-total="2"') && strpos($list, 'UT old-a') < strpos($list, 'UT old-b') && !str_contains($list, 'UT old-trash'),
    'the list shows the items of that year, oldest first, without the trash');
Test::check(str_contains($list, 'option=com_content&amp;task=article.edit&amp;id=' . $a2012) && str_contains($list, 'Archived'), 'each item links to its edit screen and shows its status');
preg_match('/href="([^"]*task=age\.export[^"]*year=2012[^"]*)"/', $list, $export);
[$code, $csv, $headers] = $admin->request(preg_replace('#^.*/administrator/#', '', html_entity_decode($export[1] ?? '')));
Test::check($code === 200 && str_starts_with($headers['content-type'] ?? '', 'text/csv') && str_contains($csv, $a2012 . ';"UT old-a";') && substr_count($csv, "\n") === 3,
    'the list of one year downloads as CSV');
preg_match('/href="([^"]*task=age\.export[^"]*)"/', $age, $export);
[$code, $csv] = $admin->request(preg_replace('#^.*/administrator/#', '', html_entity_decode($export[1] ?? '')));
Test::check($code === 200 && preg_match('/^2012;2;2\r?$/m', $csv) === 1, 'the table downloads as CSV, one row per year');
[$code] = $admin->request($ageUrl . '&layout=list&kind=nothing&year=2012');
Test::check($code === 403, 'an unknown kind is refused (got ' . $code . ')');
$contentRules = $site->q("SELECT rules FROM #__assets WHERE name = 'com_content'")[0]['rules'];
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1]])]);
[$code] = $delegate->request($ageUrl);
Test::check($code === 403, 'the screen needs the "View content by age" permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.content' => ['6' => 1]])]);
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_content'", [json_encode(['core.manage' => ['6' => 0]])]);
[$code, $delegateAge] = $delegate->request($ageUrl);
[$listCode] = $delegate->request($ageUrl . '&layout=list&kind=articles&year=2012');
Test::check($code === 200 && $ageCount($delegateAge, 2012) === 2 && !str_contains($delegateAge, 'layout=list') && $listCode === 403,
    'without managing articles in Joomla, the numbers are shown but the titles are not (got ' . $listCode . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_content'", [$contentRules]);
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q("DELETE FROM #__content WHERE alias LIKE 'ut-age-%'");

Test::section('Remove from groups');
$gid = fn (string $title): int => (int) $site->q('SELECT id FROM #__usergroups WHERE title = ?', [$title])[0]['id'];
$groupsOf = function (string $username) use ($site): array {
    $rows = $site->q('SELECT g.title FROM #__user_usergroup_map m JOIN #__usergroups g ON g.id = m.group_id WHERE m.user_id = ? ORDER BY g.lft', [$site->userId($username)]);
    return array_column($rows, 'title');
};
$site->addUser('ug_one', 'Registered,Author,Editor');
$site->addUser('ug_two', 'Editor');
$site->addUser('ug_three', 'Author');
$site->addUser('ug_back', 'Manager,Author');
$ungroupUrl = 'index.php?option=com_usertools&view=ungroup';
$choose = fn (Client $client, string $login): string => $client->submit($ungroupUrl, ['task' => 'ungroup.choose', 'login' => $login], 'data-ut-ungroup-choose');
// $keep: titles left ticked (the operator's own ticks, as the browser sends them); $subgroups: unticked
// groups with "and its subgroups" ticked.
$select = fn (Client $client, array $keep, array $subgroups = []): string => $client->submit($ungroupUrl . '&layout=groups',
    ['task' => 'ungroup.select', 'keep' => array_map($gid, $keep), 'recursive' => array_map($gid, $subgroups)], 'data-ut-ungroup-groups');
$apply = fn (Client $client): string => $client->submit($ungroupUrl . '&layout=review', ['task' => 'ungroup.apply', 'confirm' => 1], 'data-ut-ungroup-form');

// Suggestions while typing: token and permission required, no change.
$choosePage = $admin->get($ungroupUrl);
preg_match('/data-ut-account-search="([^"]+)"/', $choosePage, $m);
$suggestUrl = html_entity_decode($m[1] ?? '', ENT_QUOTES);
[$code, $json] = $admin->request(preg_replace('#^.*?/administrator/#', '', $suggestUrl) . '&q=UG_O');
Test::check($code === 200 && in_array('ug_one', array_column((array) json_decode($json, true), 'username'), true), 'typing part of a username suggests the account');
[, $mine] = $admin->request(preg_replace('#^.*?/administrator/#', '', $suggestUrl) . '&q=' . rawurlencode(getenv('UT_ADMIN_USER') ?: 'admin'));
Test::check(!in_array(getenv('UT_ADMIN_USER') ?: 'admin', array_column((array) json_decode($mine, true), 'username'), true), 'the operator\'s own account is never suggested');
[$code] = $admin->request('index.php?option=com_usertools&task=ungroup.suggest&q=ug_');
Test::check($code === 403, 'suggestions need the form token (got ' . $code . ')');

$groupsPage = $choose($admin, 'UG_ONE');
Test::check(substr_count($groupsPage, 'data-ut-keep=') === 3 && str_contains($groupsPage, 'data-ut-subgroups="' . $gid('Author') . '"') && !str_contains($groupsPage, 'data-ut-subgroups="' . $gid('Editor') . '"'), 'the second step lists only the groups of the account, with "and its subgroups" only where there are subgroups (login is case-insensitive)');
$review = $select($admin, ['Registered', 'Editor']);
Test::check(substr_count($review, 'data-ut-change="remove"') === 1 && substr_count($review, 'data-ut-change="keep"') === 2, 'without the recursive option only the unticked group is removed');
$groupsPage = $admin->get($ungroupUrl . '&layout=groups');
// Editor stays ticked: the server still removes it because it is below the unticked Author.
$review = $select($admin, ['Registered', 'Editor'], ['Author']);
Test::check(substr_count($review, 'data-ut-change="remove"') === 2 && str_contains($review, 'data-ut-cascaded') && substr_count($review, 'data-ut-change="keep"') === 1,
    'with the recursive option the groups below an unticked one go too, and the review says why');
Test::check($groupsOf('ug_one') === ['Registered', 'Author', 'Editor'], 'nothing changes before confirming');
$site->fakeSession($site->userId('ug_one'));
$done = $apply($admin);
Test::check(str_contains($done, 'data-ut-ungroup-done'), 'the change is applied after confirming');
Test::check($groupsOf('ug_one') === ['Registered'], 'the account left Author and Editor');
Test::check($site->sessions($site->userId('ug_one')) === 0 && !str_contains($done, 'data-ut-sessions='), 'the open sessions of the account are closed');
$site->fakeSession($site->userId('ug_one'));
$resultPage = $admin->get($ungroupUrl . '&layout=result');
$retried = $admin->request('index.php?option=com_usertools', ['task' => 'ungroup.sessions', 'account' => $site->userId('ug_one'), Client::token($resultPage) => 1])[1];
Test::check($site->sessions($site->userId('ug_one')) === 0 && str_contains($retried, 'sessions were closed'), 'the result screen can close the sessions again');
Test::check(count($site->q("SELECT id FROM #__action_logs WHERE message_language_key = 'COM_USERTOOLS_ACTION_LOG_UNGROUPED' AND item_id = ?", [$site->userId('ug_one')])) === 1, 'the change is in the User Actions Log');
$admin->request('index.php?option=com_usertools', ['task' => 'ungroup.apply', 'confirm' => 1, 'preview' => 'stale']);
Test::check($groupsOf('ug_one') === ['Registered'], 'a replayed confirmation changes nothing');

$choose($admin, 'ug_two');
$select($admin, []);
$done = $apply($admin);
Test::check($groupsOf('ug_two') === ['Registered'] && str_contains($done, 'Registered'), 'an account left without groups gets the group for new users');
Test::check(count($site->q("SELECT id FROM #__action_logs WHERE message_language_key = 'COM_USERTOOLS_ACTION_LOG_UNGROUPED_DEFAULT' AND item_id = ?", [$site->userId('ug_two')])) === 1, 'the log says the default group was added');

$choose($admin, 'ug_one');
$review = $select($admin, [], ['Registered']);
Test::check(str_contains($review, 'data-ut-reason="COM_USERTOOLS_UNGROUP_REASON_DEFAULT_REMOVED"') && !str_contains($review, 'data-ut-ungroup-form'), 'removing the group for new users from its last member is refused');
$back = $select($admin, ['Registered']);
Test::check(str_contains($back, 'data-ut-ungroup-groups') && str_contains($back, 'nothing to remove'), 'nothing unticked is explained on the groups step');
// An account deleted between steps sends the operator back to choosing one, without a redirect loop.
$site->addUser('ug_gone', 'Author');
$choose($admin, 'ug_gone');
$site->cli('user:delete --username=ug_gone');
[$code, $gone] = $admin->request($ungroupUrl . '&layout=groups');
Test::check($code === 200 && str_contains($gone, 'data-ut-ungroup-choose') && !str_contains($gone, 'data-ut-keep='), 'a vanished account goes back to the account step (got ' . $code . ')');
Test::check(str_contains($admin->get($ungroupUrl . '&layout=groups'), 'data-ut-ungroup-choose'), 'and the draft is cleared, so it does not happen again');
$self = $choose($admin, getenv('UT_ADMIN_USER') ?: 'admin');
Test::check(str_contains($self, 'own account') && str_contains($self, 'data-ut-ungroup-choose'), 'the operator cannot choose its own account');

$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1]])]);
[$code] = $delegate->request($ungroupUrl);
Test::check($code === 403, 'the tool needs its own permission (got ' . $code . ')');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.ungroup' => ['6' => 1]])]);
$refused = $choose($delegate, 'ug_back');
Test::check(str_contains($refused, 'Only a Super User') && !str_contains($refused, 'data-ut-keep='), 'a non Super User cannot choose accounts with administrator access');
Test::check(str_contains($choose($delegate, 'ug_three'), 'data-ut-keep='), 'a delegate can change ordinary accounts');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
Test::check(in_array('Manager', $groupsOf('ug_back'), true) && in_array('Author', $groupsOf('ug_back'), true), 'refused changes leave the groups untouched');

echo "\n" . Test::$passed . ' checks passed, ' . count(Test::$failed) . " failed\n";
exit(Test::$failed ? 1 : 0);
