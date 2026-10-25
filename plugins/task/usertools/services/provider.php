<?php
defined('_JEXEC') or die;

use Danieldiazi\Plugin\Task\Usertools\Extension\Usertools;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new Usertools((array) PluginHelper::getPlugin('task', 'usertools'));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get(DatabaseInterface::class));
            $plugin->setUserFactory($container->get(UserFactoryInterface::class));
            return $plugin;
        });
    }
};
