<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AccessModel;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** CSV download of the groups × access levels matrix. Read only. */
class AccessController extends DisplayController
{
    public function export(): void
    {
        $this->checkToken('get');
        if (!$this->app->getIdentity()->authorise('usertools.access', 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $shape = $this->input->getCmd('shape', AccessMatrix::EXPORT_MATRIX) === AccessMatrix::EXPORT_PAIRS
            ? AccessMatrix::EXPORT_PAIRS : AccessMatrix::EXPORT_MATRIX;
        /** @var AccessModel $model */
        $model = $this->getModel('Access', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv($shape);
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_ACCESS_UNREADABLE'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=access');
            return;
        }
        $name = 'usertools-access' . ($shape === AccessMatrix::EXPORT_PAIRS ? '-pairs' : '') . '-' . gmdate('Ymd') . '.csv';
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
