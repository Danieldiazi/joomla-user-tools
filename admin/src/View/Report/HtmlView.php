<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Report;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ReportsModel;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed>|null */
    public ?array $report = null;
    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ReportsModel $model */
        $model = $this->getModel();
        try {
            $this->report = $model->getReport(Factory::getApplication()->getInput()->getString('report_id', ''));
            if (!$this->report) {
                $this->error = 'COM_USERTOOLS_REPORT_NOT_FOUND';
            }
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_REPORTS_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_REPORT');
        parent::display($tpl);
    }
}
