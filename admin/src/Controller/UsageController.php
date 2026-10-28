<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UsageModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** CSV download of the content per access level. Read only. */
class UsageController extends DisplayController
{
    public function export(): void
    {
        $this->checkToken('get');
        if (!$this->app->getIdentity()->authorise('usertools.access', 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        /** @var UsageModel $model */
        $model = $this->getModel('Usage', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv();
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_USAGE_UNREADABLE'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=usage');
            return;
        }
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="usertools-level-usage-' . gmdate('Ymd') . '.csv"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
