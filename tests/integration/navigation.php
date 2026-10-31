<?php

// Included by run.php after login, against the disposable Joomla site only.

/** @return array<string, array{label: string, active: bool}> */
function utMenuLinks(string $html): array
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $links = [];
    foreach ((new DOMXPath($document))->query('//ul//a[@href]') as $element) {
        parse_str((string) parse_url(html_entity_decode($element->getAttribute('href'), ENT_QUOTES), PHP_URL_QUERY), $query);
        if (($query['option'] ?? '') === 'com_usertools' && ($query['view'] ?? '') === 'home') {
            $links[(string) ($query['section'] ?? '')] = [
                'label' => trim($element->textContent),
                'active' => $element->getAttribute('aria-current') === 'page',
            ];
        }
    }
    return $links;
}

/** Assert the native active leaf after real MVC redirects, not just the landing pages. */
function utCheckCategory(string $html, string $category, string $context): void
{
    $active = array_keys(array_filter(utMenuLinks($html), static fn (array $link): bool => $link['active']));
    Test::check($active === [$category], $context . ' keeps exactly its category active');
    Test::check(str_contains($html, 'Back to ') && str_contains($html, 'User Tools home'),
        $context . ' keeps both category and complete-home navigation');
}

Test::section('Category menu: install, upgrade, permissions and native active path');
$menuComponent = (int) $site->q("SELECT extension_id FROM #__extensions WHERE element = 'com_usertools' AND type = 'component'")[0]['extension_id'];
$menuRows = fn (): array => $site->q('SELECT id, parent_id, title, link FROM #__menu WHERE component_id = ? AND client_id = 1 ORDER BY lft', [$menuComponent]);
$menuPlugin = fn (): array => $site->q("SELECT extension_id, enabled, custom_data FROM #__extensions WHERE element = 'usertools' AND type = 'plugin' AND folder = 'system'");
Test::check(count($menuRows()) === 6 && (int) ($menuPlugin()[0]['enabled'] ?? 0) === 1,
    'fresh package installs one root, five submenu entries and enables the navigation plugin');
$menuPackage = glob(__DIR__ . '/../../dist/pkg_usertools-*.zip')[0] ?? '';
Test::check($menuPackage !== '', 'the package is available for update tests');
if ($menuPackage !== '') {
    // Model the previous release's registry: a single component link and no system plugin.
    // All changes are confined to this disposable Joomla database.
    $site->q('DELETE FROM #__menu WHERE component_id = ? AND client_id = 1 AND parent_id <> 1', [$menuComponent]);
    $site->q("DELETE FROM #__extensions WHERE element = 'usertools' AND type = 'plugin' AND folder = 'system'");
    $upgrade = $site->cli('extension:install --path=' . escapeshellarg(realpath($menuPackage)));
    Test::check(count($menuRows()) === 6 && count($menuPlugin()) === 1 && (int) $menuPlugin()[0]['enabled'] === 1,
        'upgrading the previous single-link registry creates the categories and enables the new plugin');
    $upgrade = $site->cli('extension:install --path=' . escapeshellarg(realpath($menuPackage)));
    Test::check(count($menuRows()) === 6 && count($menuPlugin()) === 1, 'repeating the package update does not duplicate navigation');
    $site->q("UPDATE #__extensions SET enabled = 0 WHERE element = 'usertools' AND type = 'plugin' AND folder = 'system'");
    $site->cli('extension:install --path=' . escapeshellarg(realpath($menuPackage)));
    Test::check((int) $menuPlugin()[0]['enabled'] === 0, 'later updates respect an explicit disable of the system plugin');
    $site->q("UPDATE #__extensions SET enabled = 1 WHERE element = 'usertools' AND type = 'plugin' AND folder = 'system'");
}
$menuHome = $admin->get('index.php?option=com_usertools&view=home');
Test::check(array_keys(utMenuLinks($menuHome)) === ['', 'accounts', 'groups', 'automation', 'delete'],
    'the real Joomla administrator menu renders Home and the four categories in order');
foreach (['accounts' => ['profile', 'compare', 'export', 'inactive', 'unblock'],
    'groups' => ['addgroups', 'ungroup', 'move', 'access', 'usage', 'age', 'diagnose'],
    'automation' => ['schedule', 'reports'], 'delete' => ['delete', 'anonymize']] as $category => $expected) {
    [$categoryCode, $categoryPage, $categoryHeaders] = $admin->request('index.php?option=com_usertools&view=home&section=' . $category);
    preg_match_all('/data-ut-home-card="([a-z]+)"/', $categoryPage, $categoryCards);
    Test::check($categoryCode === 200 && $categoryCards[1] === $expected,
        'category ' . $category . ' includes only its tools, in catalogue order');
    Test::check((utMenuLinks($categoryPage)[$category]['active'] ?? false)
        && !(utMenuLinks($categoryPage)['']['active'] ?? true)
        && str_contains($categoryHeaders['cache-control'] ?? '', 'no-store'),
        'category ' . $category . ' has one native active leaf and private response headers');
    Test::noPhpErrors($categoryPage, 'category ' . $category);
}
foreach (['profile' => 'accounts', 'access' => 'groups', 'reports' => 'automation', 'delete' => 'delete'] as $view => $category) {
    $toolPage = $admin->get('index.php?option=com_usertools&view=' . $view);
    Test::check(utMenuLinks($toolPage)[$category]['active'] ?? false, 'tool ' . $view . ' keeps its category active');
    Test::check(str_contains($toolPage, 'Back to '), 'tool ' . $view . ' has a toolbar link back to its category');
}
[$invalidCategory] = $admin->request('index.php?option=com_usertools&view=home&section=invented');
Test::check($invalidCategory === 404, 'unknown category is rejected instead of opening all tools');
[$invalidCategoryArray] = $admin->request('index.php?option=com_usertools&view=home&section[]=accounts');
Test::check($invalidCategoryArray === 404, 'a category array is rejected as an invalid category');

$menuSchedulerRules = $site->q("SELECT rules FROM #__assets WHERE name = 'com_scheduler'")[0]['rules'];
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_scheduler'", [json_encode(['core.manage' => ['6' => 0]])]);
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'",
    [json_encode(['core.manage' => ['6' => 1], 'usertools.profile' => ['6' => 1]])]);
$menuDelegate = new Client($url);
$menuDelegate->login('delegate', $delegatePassword);
$limitedHome = $menuDelegate->get('index.php?option=com_usertools&view=home');
preg_match_all('/data-ut-home-card="([a-z]+)"/', $limitedHome, $limitedCards);
Test::check(array_keys(utMenuLinks($limitedHome)) === ['', 'accounts'] && $limitedCards[1] === ['profile'],
    'one permitted tool yields only Home, its category and that tool');
[$limitedCategory] = $menuDelegate->request('index.php?option=com_usertools&view=home&section=groups');
[$limitedTool] = $menuDelegate->request('index.php?option=com_usertools&view=move');
[$limitedSchedule] = $menuDelegate->request('index.php?option=com_usertools&view=schedule');
Test::check($limitedCategory === 403 && $limitedTool === 403 && $limitedSchedule === 403,
    'category URL, Super User tool and native scheduler permission are enforced on direct requests');
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_usertools'", [json_encode(['core.manage' => ['6' => 1], 'usertools.access' => ['6' => 1]])]);
$changedHome = $menuDelegate->get('index.php?option=com_usertools&view=home');
Test::check(array_keys(utMenuLinks($changedHome)) === ['', 'groups'] && !str_contains($changedHome, 'data-ut-home-card="profile"'),
    'changing permissions changes the menu and home on the next request');
[$withdrawnCategory] = $menuDelegate->request('index.php?option=com_usertools&view=home&section=accounts');
Test::check($withdrawnCategory === 403, 'withdrawing the last tool permission withdraws category access');
$site->q("UPDATE #__assets SET rules = '{}' WHERE name = 'com_usertools'");
$site->q("UPDATE #__assets SET rules = ? WHERE name = 'com_scheduler'", [$menuSchedulerRules]);
$outsidePage = $admin->get('index.php?option=com_users&view=users');
Test::check(array_keys(utMenuLinks($outsidePage)) === ['', 'accounts', 'groups', 'automation', 'delete'],
    'the navigation integration also runs outside the component');
$usersDashboard = $admin->get('index.php?option=com_cpanel&view=cpanel&dashboard=users');
preg_match_all('/data-ut-dashboard-links-category="([a-z]+)"/', $usersDashboard, $dashboardCategories);
Test::check($dashboardCategories[1] === ['accounts', 'groups', 'automation', 'delete'],
    'Users dashboard shortcuts use the same four catalogue categories');
