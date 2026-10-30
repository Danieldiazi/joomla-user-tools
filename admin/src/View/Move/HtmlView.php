<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Move;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\MoveModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** "Move groups": choose, review, result. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array{groups: list<int>, target: int} */
    public array $draft = ['groups' => [], 'target' => 0];
    /** @var array<int, object> */
    public array $groups = [];
    /** @var array<int, string> level id => title */
    public array $levels = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /** Steps shown at the top of every screen. */
    public const STEPS = ['setup' => 'COM_USERTOOLS_MOVE_STEP_CHOOSE', 'review' => 'COM_USERTOOLS_MOVE_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];

    public function display($tpl = null): void
    {
        /** @var MoveModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $this->levels = array_column($model->getLevels(), 'title', 'id');
        $layout       = $this->getLayout();
        try {
            if ($layout === 'review') {
                $this->review = $model->getReview();
            } elseif ($layout === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=move');
                    return;
                }
            } else {
                $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=move');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_MOVE_TITLE');
        parent::display($tpl);
    }

    public function groupTitle(int $id): string
    {
        return isset($this->groups[$id]) ? (string) $this->groups[$id]->title : '#' . $id;
    }

    /** @param list<int> $ids */
    public function levelTitles(array $ids): string
    {
        return implode(', ', array_map(fn (int $id) => $this->levels[$id] ?? '#' . $id, $ids));
    }
}
