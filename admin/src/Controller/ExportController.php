<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ExportModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** Permission-checked, token-checked private CSV export (no writes to accounts). */
class ExportController extends DisplayController
{
    public function download(): void
    {
        $this->checkToken('get');
        if (!self::allowed($this->app->getIdentity(), 'usertools.export')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $filters = [
            'group' => $this->input->getInt('group', 0),
            'subgroups' => $this->input->getInt('subgroups', 0),
            'days' => $this->input->getInt('days', 0),
            'include_never' => $this->input->getInt('include_never', 0),
            'status' => $this->input->getCmd('status', 'all'),
            'email' => $this->input->getInt('email', 0),
        ];
        /** @var ExportModel $model */
        $model = $this->getModel('Export', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv($filters);
        } catch (UserFacingException $e) {
            $this->app->enqueueMessage(Text::sprintf($e->key(), ...$e->arguments()), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=export');
            return;
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_EXPORT_ERROR'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=export');
            return;
        }
        // Log sensitive exports without writing individual account data or addresses into the log.
        if ($filters['email']) {
            (new \Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger())->exportedWithEmails(
                $this->app->getIdentity(), $filters['group'], $filters['days']);
        }
        $app = Toolkit::app();
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="usertools-accounts-' . gmdate('Ymd') . '.csv"', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
