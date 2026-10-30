<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Diagnose;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\DiagnoseModel;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Access diagnosis. Read only. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed> */
    public array $health = ['days' => 0, 'checks' => []];

    /** @var array<int, object> */
    public array $groups = [];

    /** @var array<int, string> group id => "Public / Registered / …" */
    public array $paths = [];

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var DiagnoseModel $model */
        $model = $this->getModel();
        try {
            $this->health = $model->getHealth();
            $this->groups = $model->getGroups();
            $this->paths  = AccessMatrix::paths(array_map(fn (object $g) => ['id' => (int) $g->id, 'title' => (string) $g->title, 'level' => (int) $g->level], array_values($this->groups)));
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_DIAGNOSE_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_DIAGNOSE_TITLE');
        parent::display($tpl);
    }

    /** Joomla opens account screens with core.manage on com_users. */
    public function canUsers(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users');
    }

    /** Joomla opens the group and level screens only with core.admin on com_users (Users DisplayController::canView()). */
    public function canEditGroups(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_users');
    }
}
