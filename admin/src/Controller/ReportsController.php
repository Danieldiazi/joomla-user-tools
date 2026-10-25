<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Model\ReportsModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/** CSV download of one task report. */
class ReportsController extends BaseController
{
    public function export(): void
    {
        if (!$this->app->getIdentity()->authorise('usertools.reports', 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        /** @var ReportsModel $model */
        $model = $this->getModel('Reports', 'Administrator', ['ignore_request' => true]);
        $id    = $this->input->getString('report_id', '');
        try {
            $report = $model->getReport($id);
        } catch (UserFacingException | \RuntimeException | \JsonException) {
            $report = null;
        }
        if (!$report) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_REPORT_NOT_FOUND'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=reports');
            return;
        }
        $csv = $model->csv($report);
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="usertools-report-' . $id . '.csv"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
