<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Export;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        $this->groups = Toolkit::directory()->groups();
        $this->prepareDocument('COM_USERTOOLS_EXPORT_TITLE');
        parent::display($tpl);
    }
}
