<?php

// Verify the actual published package before upgrading the disposable CI Joomla site.
declare(strict_types=1);

$path = getenv('UT_JOOMLA') ?: die("UT_JOOMLA is required\n");
require $path . '/configuration.php';
$configuration = new JConfig();
$dsn = str_contains($configuration->dbtype, 'pgsql')
    ? "pgsql:host={$configuration->host};dbname={$configuration->db}"
    : "mysql:host={$configuration->host};dbname={$configuration->db};charset=utf8mb4";
$database = new PDO($dsn, $configuration->user, $configuration->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = $configuration->dbprefix;
$component = (int) $database->query("SELECT extension_id FROM {$prefix}extensions WHERE type = 'component' AND element = 'com_usertools'")->fetchColumn();
$menuCount = (int) $database->query("SELECT COUNT(*) FROM {$prefix}menu WHERE component_id = $component AND client_id = 1")->fetchColumn();
$pluginCount = (int) $database->query("SELECT COUNT(*) FROM {$prefix}extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'usertools'")->fetchColumn();
if ($component < 1 || $menuCount !== 1 || $pluginCount !== 0) {
    throw new RuntimeException('The published baseline must have one component link and no navigation plugin.');
}
echo "Published 2026.10.20 baseline verified before upgrade.\n";
