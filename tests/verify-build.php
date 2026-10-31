<?php

/**
 * Checks the packages built by scripts/build.php:
 *   php tests/verify-build.php 2026.10.3 dist
 */

declare(strict_types=1);

[$version, $dir] = [ltrim($argv[1] ?? '', 'v'), rtrim($argv[2] ?? 'dist', '/')];
$errors = [];
$check  = function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};

$read = function (string $zipFile, string $entry): ?string {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return null;
    }
    $content = $zip->getFromName($entry);
    $zip->close();
    return $content === false ? null : $content;
};
$names = function (string $zipFile): array {
    $zip = new ZipArchive();
    $zip->open($zipFile);
    $list = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $list[] = $zip->getNameIndex($i);
    }
    $zip->close();
    return $list;
};

$com = "$dir/com_usertools-$version.zip";
$plg = "$dir/plg_task_usertools-$version.zip";
$nav = "$dir/plg_system_usertools-$version.zip";
$mod = "$dir/mod_usertools-$version.zip";
$grp = "$dir/mod_usertools_groups-$version.zip";
$pkg = "$dir/pkg_usertools-$version.zip";
foreach ([$com, $plg, $nav, $mod, $grp, $pkg, "$dir/SHA256SUMS.txt", "$dir/updates.xml"] as $file) {
    $check(is_file($file), "missing $file");
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

foreach ([[$com, 'com_usertools.xml'], [$plg, 'usertools.xml'], [$nav, 'usertools.xml'], [$mod, 'mod_usertools.xml'], [$grp, 'mod_usertools_groups.xml'], [$pkg, 'pkg_usertools.xml']] as [$zip, $manifest]) {
    $xml = simplexml_load_string((string) $read($zip, $manifest));
    $check($xml !== false && (string) $xml->version === $version, "$manifest has version $version");
}
$assets = json_decode((string) $read($com, 'media/joomla.asset.json'), true);
$check(($assets['version'] ?? '') === $version, 'joomla.asset.json has the version');

$comFiles = $names($com);
foreach (['admin/access.xml', 'admin/config.xml', 'admin/services/provider.php', 'admin/language/es-ES/com_usertools.ini', 'admin/language/en-GB/com_usertools.ini',
    'admin/language/es-ES/com_usertools.sys.ini', 'admin/language/en-GB/com_usertools.sys.ini', 'admin/layouts/usertools/exclusions.php',
    'admin/tmpl/compare/review.php', 'admin/tmpl/access/default.php', 'media/css/admin.css', 'media/js/admin.js'] as $file) {
    $check(in_array($file, $comFiles, true), "component contains $file");
}
foreach ($comFiles as $file) {
    $check(!preg_match('#(^|/)(tests|vendor|node_modules|\.git)/#', $file), "component does not ship $file");
}
$plgFiles = $names($plg);
foreach (['usertools.xml', 'services/provider.php', 'src/Extension/Usertools.php', 'forms/inactive.xml', 'language/es-ES/plg_task_usertools.ini', 'language/en-GB/plg_task_usertools.sys.ini'] as $file) {
    $check(in_array($file, $plgFiles, true), "plugin contains $file");
}
// Older releases installed translations in Joomla's shared language folder. Those files
// take precedence over plugin-local files, so upgrades must refresh both locations.
$pluginManifest = simplexml_load_string((string) $read($plg, 'usertools.xml'));
$declaredLanguages = [];
foreach ($pluginManifest->languages->language as $language) {
    $declaredLanguages[(string) $language['tag']][] = (string) $language;
}
preg_match_all('/PLG_TASK_USERTOOLS_[A-Z0-9_]+/', (string) $read($plg, 'forms/inactive.xml'), $formKeys);
foreach (['es-ES', 'en-GB'] as $tag) {
    foreach (['ini', 'sys.ini'] as $suffix) {
        $entry = "language/$tag/plg_task_usertools.$suffix";
        $check(in_array($entry, $declaredLanguages[$tag] ?? [], true), "manifest refreshes shared $tag $suffix translations");
        $check($read($plg, $entry) !== null, "plugin ships $entry");
    }
    $strings = parse_ini_string((string) $read($plg, "language/$tag/plg_task_usertools.ini"), false, INI_SCANNER_RAW) ?: [];
    foreach (array_unique($formKeys[0]) as $key) {
        $check(isset($strings[$key]) && $strings[$key] !== '', "$tag translates task form key $key");
    }
}

$navFiles = $names($nav);
foreach (['usertools.xml', 'services/provider.php', 'src/Extension/Usertools.php', 'language/es-ES/plg_system_usertools.sys.ini',
    'language/en-GB/plg_system_usertools.sys.ini', 'LICENSE'] as $file) {
    $check(in_array($file, $navFiles, true), "navigation plugin contains $file");
}
$menuManifest = simplexml_load_string((string) $read($com, 'com_usertools.xml'));
$check(count($menuManifest->administration->submenu->menu) === 5, 'component declares Home and four category links');
foreach (['es-ES', 'en-GB'] as $tag) {
    $menuStrings = parse_ini_string((string) $read($com, "admin/language/$tag/com_usertools.sys.ini"), false, INI_SCANNER_RAW) ?: [];
    $mainStrings = parse_ini_string((string) $read($com, "admin/language/$tag/com_usertools.ini"), false, INI_SCANNER_RAW) ?: [];
    foreach ($menuManifest->administration->submenu->menu as $item) {
        $key = (string) $item;
        $check(isset($menuStrings[$key]) && $menuStrings[$key] === ($mainStrings[$key] ?? null), "$tag menu and page agree on $key");
    }
}

$pkgFiles = $names($pkg);
$modFiles = $names($mod);
foreach (['mod_usertools.xml', 'services/provider.php', 'src/Dispatcher/Dispatcher.php', 'tmpl/default.php', 'language/es-ES/mod_usertools.ini', 'language/en-GB/mod_usertools.sys.ini'] as $file) {
    $check(in_array($file, $modFiles, true), "module contains $file");
}
$grpFiles = $names($grp);
foreach (['mod_usertools_groups.xml', 'services/provider.php', 'src/Dispatcher/Dispatcher.php', 'src/Helper/MembershipHelper.php', 'src/Service/GroupTree.php',
    'tmpl/default.php', 'language/es-ES/mod_usertools_groups.ini', 'language/en-GB/mod_usertools_groups.sys.ini'] as $file) {
    $check(in_array($file, $grpFiles, true), "groups module contains $file");
}
$groupStrings = [];
foreach (['es-ES', 'en-GB'] as $tag) {
    foreach (['ini', 'sys.ini'] as $suffix) {
        $groupStrings[$tag][$suffix] = parse_ini_string((string) $read($grp, "language/$tag/mod_usertools_groups.$suffix"), false, INI_SCANNER_RAW) ?: [];
    }
}
foreach (['ini', 'sys.ini'] as $suffix) {
    $check(array_keys($groupStrings['es-ES'][$suffix]) === array_keys($groupStrings['en-GB'][$suffix]), "groups module es-ES and en-GB $suffix have the same keys");
}
preg_match_all('/MOD_USERTOOLS_GROUPS_[A-Z0-9_]+/', implode("\n", array_map(fn ($f) => (string) $read($grp, $f), ['mod_usertools_groups.xml', 'tmpl/default.php'])), $groupKeys);
foreach (array_unique($groupKeys[0]) as $key) {
    $check(isset($groupStrings['es-ES']['ini'][$key]), "groups module translates $key");
}
foreach (['script.php', 'packages/com_usertools.zip', 'packages/plg_task_usertools.zip', 'packages/plg_system_usertools.zip', 'packages/mod_usertools.zip', 'packages/mod_usertools_groups.zip', 'language/es-ES/pkg_usertools.sys.ini'] as $file) {
    $check(in_array($file, $pkgFiles, true), "package contains $file");
}
$pkgXml = simplexml_load_string((string) $read($pkg, 'pkg_usertools.xml'));
$check(str_contains((string) $pkgXml->updateservers->server, 'releases/latest/download/updates.xml'), 'package declares the update server');

foreach (file("$dir/SHA256SUMS.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$hash, $file] = preg_split('/\s+/', $line);
    $check(hash_file('sha256', "$dir/$file") === $hash, "checksum of $file");
}
$updates = simplexml_load_file("$dir/updates.xml");
$check((string) $updates->update->version === $version, 'updates.xml version');
$check((string) $updates->update->element === 'pkg_usertools', 'updates.xml element');
$check((string) $updates->update->sha256 === hash_file('sha256', $pkg), 'updates.xml checksum matches the package');
$check(str_ends_with((string) $updates->update->downloads->downloadurl, "/v$version/pkg_usertools-$version.zip"), 'updates.xml download URL');

// Every language key used by the code exists in both languages.
$languages = [];
foreach (['es-ES', 'en-GB'] as $tag) {
    $languages[$tag] = parse_ini_string((string) $read($com, "admin/language/$tag/com_usertools.ini"), false, INI_SCANNER_RAW) ?: [];
    $check(count($languages[$tag]) > 200, "$tag language file parses");
}
$check(array_keys($languages['es-ES']) == array_keys($languages['en-GB']) || !array_diff_key($languages['es-ES'], $languages['en-GB']) && !array_diff_key($languages['en-GB'], $languages['es-ES']), 'es-ES and en-GB have the same keys');

// Guided tours: one language file per tour, named after the tour uid, in both languages.
foreach (['compare', 'inactive', 'unblock', 'delete', 'anonymize', 'addgroups', 'ungroup', 'move', 'schedule'] as $tour) {
    $tourStrings = [];
    foreach (['es-ES', 'en-GB'] as $tag) {
        $tourStrings[$tag] = parse_ini_string((string) $read($com, "admin/language/$tag/com_usertools.tour_$tour.ini"), false, INI_SCANNER_RAW) ?: [];
        $check(isset($tourStrings[$tag]['COM_USERTOOLS_TOUR_' . strtoupper($tour) . '_TITLE']), "$tag guided tour $tour is packaged");
    }
    $check(array_keys($tourStrings['es-ES']) === array_keys($tourStrings['en-GB']), "guided tour $tour has the same keys in both languages");
}

if ($errors) {
    fwrite(STDERR, "Build verification failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "Build $version verified\n";
