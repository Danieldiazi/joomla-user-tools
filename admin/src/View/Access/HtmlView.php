<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Access;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AccessModel;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** User groups × viewing access levels. Read only. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed> */
    public array $matrix = ['levels' => [], 'rows' => []];
    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var AccessModel $model */
        $model = $this->getModel();
        try {
            $this->matrix = $model->getMatrix();
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_ACCESS_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_ACCESS_TITLE');
        parent::display($tpl);
    }

    /** Links to the user manager only for users who can open it. */
    public function canUsers(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users');
    }

    /** Joomla opens the group and level screens only with core.admin on com_users (Users DisplayController::canView()). */
    public function canEditGroups(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_users');
    }

    /** Title of a group by id, for "inherited from" notes. */
    public function groupTitle(int $id): string
    {
        foreach ($this->matrix['rows'] as $row) {
            if ($row['id'] === $id) {
                return $row['title'];
            }
        }
        return '#' . $id;
    }
}
