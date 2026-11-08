<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Reach;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ReachModel;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Reach of each user group (default layout) and the members of one group branch (members layout). Read only. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, string> */
    public array $levelTitles = [];

    /** @var array<int, string> */
    public array $paths = [];

    /** @var array{group: ?object, accounts: list<array<string, mixed>>, total: int, blocked: int, page: int} */
    public array $members = ['group' => null, 'accounts' => [], 'total' => 0, 'blocked' => 0, 'page' => 1];

    /** @var array<int, object> */
    public array $groups = [];

    public int $few = GroupReach::DEFAULT_FEW;
    public string $error = '';

    public function display($tpl = null): void
    {
        $input     = Factory::getApplication()->getInput();
        $this->few = GroupReach::threshold($input->getInt('few', GroupReach::DEFAULT_FEW));
        /** @var ReachModel $model */
        $model = $this->getModel();
        try {
            if ($this->getLayout() === 'members') {
                // Names and usernames are shown only to who could list them in Joomla's user manager.
                if (!$this->canUsers()) {
                    throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }
                $this->groups  = Toolkit::directory()->groups();
                $this->paths   = $model->getPaths();
                $this->members = $model->getMembers($input->getInt('group', 0), max(1, $input->getInt('page', 1)));
            } else {
                $this->rows        = $model->getRows($this->few);
                $this->levelTitles = $model->getLevelTitles();
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=reach');
        } catch (NotAllowed $e) {
            throw $e;
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_REACH_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_REACH_TITLE');
        parent::display($tpl);
    }

    /** The member list needs the same permission as Joomla's user manager. */
    public function canUsers(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users');
    }

    /** Account names link to the profile tool for users who have it. */
    public function canProfile(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('usertools.profile', 'com_usertools');
    }
}
