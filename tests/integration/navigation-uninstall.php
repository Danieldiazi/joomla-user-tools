<?php

// Run last, after browser captures, on the disposable CI Joomla site only.
declare(strict_types=1);

$path = getenv('UT_JOOMLA') ?: die("UT_JOOMLA is required\n");
require $path . '/configuration.php';
$configuration = new JConfig();
$dsn = str_contains($configuration->dbtype, 'pgsql')
    ? "pgsql:host={$configuration->host};dbname={$configuration->db}"
    : "mysql:host={$configuration->host};dbname={$configuration->db};charset=utf8mb4";
$database = new PDO($dsn, $configuration->user, $configuration->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = $configuration->dbprefix;
$package = (int) $database->query("SELECT extension_id FROM {$prefix}extensions WHERE type = 'package' AND element = 'pkg_usertools'")->fetchColumn();
if (!$package) {
    throw new RuntimeException('The disposable test package is not installed.');
}
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path . '/cli/joomla.php') . ' extension:remove ' . $package . ' -n 2>&1';
exec($command, $lines, $code);
if ($code !== 0) {
    throw new RuntimeException('Package uninstall failed: ' . implode("\n", $lines));
}
$menuCount = (int) $database->query("SELECT COUNT(*) FROM {$prefix}menu WHERE client_id = 1 AND link LIKE 'index.php?option=com_usertools%'")->fetchColumn();
$pluginCount = (int) $database->query("SELECT COUNT(*) FROM {$prefix}extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'usertools'")->fetchColumn();
$tourCount = (int) $database->query("SELECT COUNT(*) FROM {$prefix}guidedtours WHERE uid LIKE 'com_usertools.tour-%'")->fetchColumn();
if ($menuCount !== 0 || $pluginCount !== 0 || $tourCount !== 0) {
    throw new RuntimeException('Uninstall left User Tools navigation links or its system plugin or guided tours behind.');
}
echo "Navigation uninstall verified: no orphan menu links, system plugin or guided tours.\n";
