<?php

// Included by run.php; all accounts and edits belong to the disposable Joomla fixture.
Test::section('Bulk name updates: permission, selection, stale reviews and resumable batches');
$namesUrl = 'index.php?option=com_usertools&view=names';
$namesRules = $site->q("SELECT rules FROM #__assets WHERE name = 'com_usertools'")[0]['rules'];
$site->addUser('names_one', 'Registered');
$site->addUser('names_two', 'Registered');
$site->addUser('names_manager', 'Manager');
$nameRow = fn (string $username): array => $site->q('SELECT * FROM #__users WHERE username = ?', [$username])[0];
$beforeOne = $nameRow('names_one');
$beforeTwo = $nameRow('names_two');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1]])]);
$namesDelegate = new Client($url);
$namesDelegate->login('delegate', $delegatePassword);
[$denied] = $namesDelegate->request($namesUrl);
Test::check($denied === 403, 'name screen requires its own permission');
$delegatePage = $namesDelegate->get('index.php?option=com_usertools');
foreach (['names.preview', 'names.apply', 'names.next'] as $task) {
    [$denied] = $namesDelegate->request('index.php?option=com_usertools', ['task' => $task, Client::token($delegatePage) => 1]);
    Test::check($denied === 403, $task . ' denies an operator without permission');
}
$setup = $admin->get($namesUrl);
foreach (['names.preview', 'names.apply', 'names.next'] as $task) {
    [, $csrfDenied] = $admin->request('index.php?option=com_usertools', ['task' => $task]);
    Test::check(!str_contains($csrfDenied, 'data-ut-names-result='), $task . ' without token does not execute');
}
Test::check($nameRow('names_one') === $beforeOne, 'missing CSRF never changes account data');
$review = $admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => "names_one;Ángela Flores\nnames_two;Second Name\nnames_manager;Protected\nadmin;Protected\nmissing_name;Unknown\nbad"], 'data-ut-names-form');
utCheckCategory($review, 'accounts', 'name review');
Test::check(substr_count($review, 'data-ut-names-status="update"') === 2
    && substr_count($review, 'data-ut-names-status="protected"') === 2
    && str_contains($review, 'data-ut-names-status="unknown"')
    && str_contains($review, 'data-ut-names-status="invalid_line"')
    && str_contains($review, 'Ángela Flores') && str_contains($review, htmlspecialchars($beforeOne['name'], ENT_QUOTES)),
    'review shows current/proposed names and classifies protected, unknown and invalid rows');
$noJs = $admin->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1, 'selected' => '[0,1]', 'selection_ready' => 0], 'data-ut-names-review');
Test::check($nameRow('names_one') === $beforeOne && $nameRow('names_two') === $beforeTwo
    && str_contains($noJs, 'data-ut-names-review'), 'unsynchronised selection cannot update initially selected accounts');
$noSelection = $admin->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1, 'selected' => '[]', 'selection_ready' => 1], 'data-ut-names-review');
Test::check($nameRow('names_one') === $beforeOne && str_contains($noSelection, 'data-ut-names-review'), 'empty selection preserves review without writes');
// Save the exact token/nonce for a replay after the successful POST.
preg_match('/name="preview" value="([^"]+)"/', $noSelection, $nonce);
$replay = ['task' => 'names.apply', 'preview' => $nonce[1], 'confirm' => 1, 'selection_ready' => 1,
    'selected' => '[0]', Client::token($noSelection) => 1];
$done = $admin->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1, 'selection_ready' => 1,
    'selected' => '[0,0,2,999,{"forged":1}]'], 'data-ut-names-review');
$afterOne = $nameRow('names_one');
Test::check($afterOne['name'] === 'Ángela Flores' && $nameRow('names_two') === $beforeTwo
    && substr_count($done, 'data-ut-names-result="updated"') === 1
    && substr_count($done, 'data-ut-names-result="skipped"') === 5, 'only selected eligible row is updated, once; protected and forged indexes are ignored, and the other rows are reported');
$beforeWithoutName = $beforeOne;
$afterWithoutName = $afterOne;
unset($beforeWithoutName['name'], $afterWithoutName['name']);
Test::check($beforeWithoutName === $afterWithoutName, 'all account fields except name are preserved');
$admin->request('index.php?option=com_usertools', $replay);
Test::check($nameRow('names_one') === $afterOne, 'consumed name preview cannot be replayed');
$stale = $admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => 'names_two;Proposed'], 'data-ut-names-form');
$site->q('UPDATE #__users SET name = ? WHERE username = ?', ['Concurrent Name', 'names_two']);
$staleResult = $admin->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1, 'selection_ready' => 1, 'selected' => '[0]'], 'data-ut-names-review');
Test::check($nameRow('names_two')['name'] === 'Concurrent Name' && substr_count($staleResult, 'data-ut-names-result="changed"') === 1,
    'a concurrent name edit is not overwritten and the row is reported as skipped');
$anyCase = $admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => 'NAMES_TWO;Any Case'], 'data-ut-names-form');
Test::check(substr_count($anyCase, 'data-ut-names-status="update"') === 1, 'usernames match regardless of case on every database');
$duplicates = $admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => "names_one;First\nNAMES_ONE;Second"], 'data-ut-names-form');
Test::check(substr_count($duplicates, 'data-ut-names-status="duplicate"') === 2, 'neither duplicate wins by input order');
$badName = $admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => 'names_one;' . str_repeat('x', 401)], 'data-ut-names-form');
Test::check(str_contains($badName, 'data-ut-names-status="invalid_name"'), 'a name that does not fit the column is rejected in the review');
// Delegated permission grants this tool without granting edits to protected accounts.
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'",
    [json_encode(['core.manage' => ['6' => 1], 'usertools.names' => ['6' => 1]])]);
$allowed = $namesDelegate->submit($namesUrl, ['task' => 'names.preview', 'lines' => "names_two;Delegated Name\nnames_manager;Protected\ndelegate;Protected"], 'data-ut-names-form');
Test::check(substr_count($allowed, 'data-ut-names-status="protected"') === 2, 'delegated tool protects backend accounts and the operator');
$namesDelegate->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1, 'selection_ready' => 1, 'selected' => '[0,1,2]'], 'data-ut-names-review');
Test::check($nameRow('names_two')['name'] === 'Delegated Name', 'delegated permission can update a regular account');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [$namesRules]);
$batchLines = [];
for ($i = 1; $i <= 21; $i++) {
    $site->addUser('names_batch_' . $i, 'Registered');
    $batchLines[] = 'names_batch_' . $i . ';Batch ' . $i;
}
$admin->submit($namesUrl, ['task' => 'names.preview', 'lines' => implode("\n", $batchLines)], 'data-ut-names-form');
$progress = $admin->submit($namesUrl . '&layout=review', ['task' => 'names.apply', 'confirm' => 1,
    'selection_ready' => 1, 'selected' => json_encode(range(0, 20))], 'data-ut-names-review');
Test::check(str_contains($progress, 'data-ut-names-next') && $nameRow('names_batch_20')['name'] === 'Batch 20'
    && $nameRow('names_batch_21')['name'] !== 'Batch 21', 'a request processes at most 20 accounts');
$site->q('UPDATE #__users SET name = ? WHERE username = ?', ['Edited Between Batches', 'names_batch_21']);
$resumed = $admin->get($namesUrl);
Test::check(str_contains($resumed, 'data-ut-names-next'), 'reopening tool resumes the pending batch');
$finished = $admin->submit($namesUrl . '&layout=progress', ['task' => 'names.next'], 'data-ut-names-next');
Test::check(substr_count($finished, 'data-ut-names-result="updated"') === 20
    && substr_count($finished, 'data-ut-names-result="changed"') === 1
    && $nameRow('names_batch_21')['name'] === 'Edited Between Batches', 'continuation skips stale row and keeps earlier results');
Test::noPhpErrors($finished, 'name batch result');
