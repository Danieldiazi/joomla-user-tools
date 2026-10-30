<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Plugin\System\Usertools\Extension\Usertools;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new Usertools($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('system', 'usertools'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
