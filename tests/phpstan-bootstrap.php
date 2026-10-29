<?php

// PHPStan runs against a Joomla 6 tree (JOOMLA_PATH) to resolve the CMS classes.
$joomla = getenv('JOOMLA_PATH') ?: '';
if ($joomla === '' || !is_dir($joomla . '/libraries')) {
    fwrite(STDERR, "Set JOOMLA_PATH to an unpacked Joomla 6 package.\n");
    exit(1);
}
foreach (['_JEXEC' => 1, 'JPATH_BASE' => $joomla . '/administrator', 'JPATH_ROOT' => $joomla, 'JPATH_SITE' => $joomla,
    'JPATH_ADMINISTRATOR' => $joomla . '/administrator', 'JPATH_PLUGINS' => $joomla . '/plugins', 'JPATH_LIBRARIES' => $joomla . '/libraries',
    'JPATH_COMPONENT' => $joomla . '/administrator/components/com_usertools', 'JVERSION' => '6.2.0'] as $name => $value) {
    if (!\defined($name)) {
        \define($name, $value);
    }
}
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    $map = [
        'Joomla\\CMS\\' => $joomla . '/libraries/src/',
        'Joomla\\Component\\Scheduler\\Administrator\\' => $joomla . '/administrator/components/com_scheduler/src/',
        'Joomla\\Component\\Actionlogs\\Administrator\\' => $joomla . '/administrator/components/com_actionlogs/src/',
        'Joomla\\Component\\Mails\\Administrator\\' => $joomla . '/administrator/components/com_mails/src/',
        'Joomla\\Component\\Users\\Administrator\\' => $joomla . '/administrator/components/com_users/src/',
        'Danieldiazi\\Component\\Usertools\\Administrator\\' => __DIR__ . '/../admin/src/',
        'Danieldiazi\\Plugin\\Task\\Usertools\\' => __DIR__ . '/../plugins/task/usertools/src/',
        'Danieldiazi\\Module\\Usertools\\Administrator\\' => __DIR__ . '/../modules/mod_usertools/src/',
        'Danieldiazi\\Module\\UsertoolsGroups\\Site\\' => __DIR__ . '/../modules/mod_usertools_groups/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});
