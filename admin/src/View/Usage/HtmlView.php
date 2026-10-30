<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Usage;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UsageModel;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Content per viewing access level. Read only. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed> */
    public array $usage = ['levels' => [], 'rows' => []];
    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var UsageModel $model */
        $model = $this->getModel();
        try {
            $this->usage = $model->getUsage();
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_USAGE_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_USAGE_TITLE');
        parent::display($tpl);
    }
}
