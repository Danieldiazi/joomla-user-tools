<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\DiagnoseModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** CSV download of the access diagnosis. Read only. */
class DiagnoseController extends DisplayController
{
    public function export(): void
    {
        $this->checkToken('get');
        if (!$this->app->getIdentity()->authorise('usertools.diagnose', 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        /** @var DiagnoseModel $model */
        $model = $this->getModel('Diagnose', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv();
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_DIAGNOSE_UNREADABLE'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=diagnose');
            return;
        }
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="usertools-access-diagnosis-' . gmdate('Ymd') . '.csv"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
