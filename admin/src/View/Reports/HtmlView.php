<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Reports;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ReportsModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array{items: list<array<string, mixed>>, total: int, tasks: array<int, string>} */
    public array $page = ['items' => [], 'total' => 0, 'tasks' => []];
    /** @var array{task: int, mode: string, status: string} */
    public array $filters = ['task' => 0, 'mode' => '', 'status' => ''];
    public int $pageNumber = 1;
    public string $error = '';
    public string $directory = '';

    public function display($tpl = null): void
    {
        $input = Factory::getApplication()->getInput();
        $this->filters = [
            'task'   => max(0, $input->getInt('filter_task', 0)),
            'mode'   => \in_array($input->getCmd('filter_mode', ''), ['real', 'dry'], true) ? $input->getCmd('filter_mode') : '',
            'status' => \in_array($input->getCmd('filter_status', ''), ['completed', 'failed', 'guarded', 'running'], true) ? $input->getCmd('filter_status') : '',
        ];
        $this->pageNumber = max(1, min(100000, $input->getInt('page', 1)));
        /** @var ReportsModel $model */
        $model = $this->getModel();
        try {
            $this->page = $model->getPage($this->pageNumber, $this->filters);
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_REPORTS_UNREADABLE';
        }
        $this->directory = Toolkit::reports()->directory();
        $this->prepareDocument('COM_USERTOOLS_REPORTS');
        parent::display($tpl);
    }
}
