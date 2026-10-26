<?php

// Unit tests run without Joomla: domain services only need the _JEXEC guard and the autoloader.
\define('_JEXEC', 1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $map = [
            'Danieldiazi\\Component\\Usertools\\Administrator\\' => __DIR__ . '/../admin/src/',
            'Danieldiazi\\Tests\\'                              => __DIR__ . '/Unit/',
        ];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
}

// Minimal stand-ins for the Joomla interfaces some services type-hint.
if (!class_exists(\Joomla\CMS\Language\Text::class)) {
    eval('namespace Joomla\CMS\Language; class Text { public static function _($key) { return $key; } }');
}
if (!interface_exists(\Joomla\CMS\User\UserFactoryInterface::class)) {
    eval('namespace Joomla\CMS\User; interface UserFactoryInterface { public function loadUserById(int $id); public function loadUserByUsername(string $username); }');
}
