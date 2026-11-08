<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ReachModel;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** CSV download of the reach of each group. Read only; it has counts, not accounts. */
class ReachController extends DisplayController
{
    public function export(): void
    {
        $this->checkToken('get');
        if (!$this->app->getIdentity()->authorise('usertools.access', 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        /** @var ReachModel $model */
        $model = $this->getModel('Reach', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv(GroupReach::threshold($this->input->getInt('few', GroupReach::DEFAULT_FEW)));
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_REACH_UNREADABLE'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=reach');
            return;
        }
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="usertools-group-reach-' . gmdate('Ymd') . '.csv"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
