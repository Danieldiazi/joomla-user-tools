<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Module\UsertoolsGroups\Site\Dispatcher;

\defined('_JEXEC') or die;

use Danieldiazi\Module\UsertoolsGroups\Site\Helper\MembershipHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

/**
 * Lists the groups of an account (the visitor or a chosen one) or the members of a group. Shows
 * nothing to guests when it lists the visitor's groups.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /** @return array<string, mixed>|false */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        /** @var Registry $params */
        $params = $data['params'];
        /** @var MembershipHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('MembershipHelper');
        $mode   = $params->get('mode', 'user') === 'members' ? 'members' : 'user';

        try {
            if ($mode === 'members') {
                $result = $helper->groupMembers($params);
                $empty  = $result['members'] === [];
            } else {
                $identity = $this->getApplication()->getIdentity();
                $userId   = $params->get('user_source', 'current') === 'specific'
                    ? (int) $params->get('user_id', 0)
                    : ($identity ? (int) $identity->id : 0);
                if ($userId <= 0) {
                    return false;
                }
                $result = $helper->userGroups($userId, $params);
                $empty  = $result['groups'] === [];
            }
        } catch (\RuntimeException $e) {
            Log::add('mod_usertools_groups: ' . $e->getMessage(), Log::WARNING, 'mod_usertools_groups');
            return false;
        }
        if ($empty && !$params->get('show_empty', 1)) {
            return false;
        }
        return $data + ['mode' => $mode, 'showCount' => (bool) $params->get('show_count', 1)] + $result;
    }
}
