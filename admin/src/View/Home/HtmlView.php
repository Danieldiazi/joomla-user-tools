<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Home;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public string $pluginState = 'missing';

    public function display($tpl = null): void
    {
        $this->pluginState = Toolkit::pluginState();
        $this->prepareDocument('');
        parent::display($tpl);
    }
}
