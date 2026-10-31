<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Home;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\Navigation;
use Joomla\CMS\Plugin\PluginHelper;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public string $pluginState = 'missing';
    public bool $navigationEnabled = false;
    public string $section = '';
    /** @var array<string, array<string, mixed>> */
    public array $sections = [];

    public function display($tpl = null): void
    {
        $this->pluginState = Toolkit::pluginState();
        $this->navigationEnabled = PluginHelper::isEnabled('system', 'usertools');
        $this->section = Toolkit::app()->getInput()->getCmd('section', '');
        $sections = Navigation::sections(fn (string $permission): bool => $this->can($permission));
        $this->sections = $this->section === '' ? $sections
            : (isset($sections[$this->section]) ? [$this->section => $sections[$this->section]] : []);
        $this->prepareDocument(Navigation::CATEGORIES[$this->section]['title'] ?? '');
        parent::display($tpl);
    }
}
