<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AgeModel;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAgeReader;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** CSV download of the content by age: the table, or the items of one year. Read only. */
class AgeController extends DisplayController
{
    public function export(): void
    {
        $this->checkToken('get');
        $identity = $this->app->getIdentity();
        $kind     = $this->input->getCmd('kind', '');
        $year     = ContentAge::year($this->input->getInt('year', 0));
        $level    = max(0, $this->input->getInt('level', 0));
        // Titles of items are only listed to users who can manage that kind of item in Joomla.
        $listing  = $kind !== '' && $year > 0;
        if (!$identity->authorise('usertools.content', 'com_usertools')
            || ($listing && !$identity->authorise('core.manage', ContentAgeReader::KINDS[$kind]['asset'] ?? 'com_content'))) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        /** @var AgeModel $model */
        $model = $this->getModel('Age', 'Administrator', ['ignore_request' => true]);
        try {
            $csv = $model->csv($listing ? $kind : '', $year, $level);
        } catch (\Throwable) {
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_AGE_UNREADABLE'), 'warning');
            $this->setRedirect('index.php?option=com_usertools&view=age');
            return;
        }
        $name = 'usertools-content-age-' . ($listing ? $kind . '-' . $year . '-' : '') . gmdate('Ymd') . '.csv';
        Toolkit::app()->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        Toolkit::app()->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
