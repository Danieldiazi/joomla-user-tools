<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Module\Usertools\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController;
use Danieldiazi\Component\Usertools\Administrator\Service\Navigation;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

/**
 * Dashboard summary of User Tools. It is shown only to users who can manage the component, and each
 * figure only to users allowed to use the matching tool.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        $app  = $this->getApplication();
        $user = $app->getIdentity();
        if (!$user || !ComponentHelper::isEnabled('com_usertools') || !$user->authorise('core.manage', 'com_usertools')) {
            return;
        }
        // Registers the component namespace so its services can be used here.
        $app->bootComponent('com_usertools');
        $app->getLanguage()->load('com_usertools', JPATH_ADMINISTRATOR . '/components/com_usertools');
        parent::dispatch();
    }

    /** @return array<string, mixed> */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $user = $this->getApplication()->getIdentity();
        try {
            $data['summary'] = Toolkit::dashboard($user);
        } catch (\Throwable) {
            $data['summary'] = null;
        }
        $data['sections'] = Navigation::sections(static fn (string $permission): bool => DisplayController::allowed($user, $permission));
        return $data;
    }
}
