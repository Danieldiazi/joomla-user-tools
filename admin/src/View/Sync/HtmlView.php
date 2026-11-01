<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Sync;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\SyncModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = ['setup' => 'COM_USERTOOLS_SYNC_STEP_LIST', 'review' => 'COM_USERTOOLS_SYNC_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];

    /** @var array<string, mixed> */
    public array $draft = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $job = null;
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<int, object> */
    public array $groups = [];
    public bool $canCreate = false;

    public function display($tpl = null): void
    {
        /** @var SyncModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        $this->groups = $model->getGroups();
        $this->canCreate = $model->canCreate();
        try {
            if ($this->getLayout() === 'review') {
                $this->review = $model->getReview();
            } elseif ($this->getLayout() === 'progress') {
                $this->job = $model->getJob();
                if (!$this->job) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=sync&layout=result');
                    return;
                }
            } elseif ($this->getLayout() === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=sync');
                    return;
                }
            } else {
                $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=sync');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_SYNC_TITLE');
        parent::display($tpl);
    }

    public function groupTitle(int $id): string
    {
        return isset($this->groups[$id]) ? (string) $this->groups[$id]->title . ' (#' . $id . ')' : '#' . $id;
    }

    /** Full path of a group, as it can be written in the list. */
    public function groupPath(int $id): string
    {
        $parts = [];
        while (isset($this->groups[$id])) {
            array_unshift($parts, (string) $this->groups[$id]->title);
            $id = (int) $this->groups[$id]->parent_id;
        }
        return implode('/', $parts);
    }

    /** @param list<int> $ids */
    public function groupList(array $ids): string
    {
        return implode(', ', array_map(fn (int $id): string => $this->groupTitle($id), $ids));
    }
}
