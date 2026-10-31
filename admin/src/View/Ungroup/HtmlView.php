<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Ungroup;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UngroupModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** "Remove from groups": account, groups, review, result. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array{login: string, account: int, removed: list<int>, recursive: list<int>, candidates: list<array<string, mixed>>} */
    public array $draft = ['login' => '', 'account' => 0, 'removed' => [], 'recursive' => [], 'candidates' => []];
    /** @var array<string, mixed> */
    public array $selection = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<int, object> */
    public array $groups = [];

    /** Steps shown at the top of every screen. */
    public const STEPS = ['setup' => 'COM_USERTOOLS_UNGROUP_STEP_CHOOSE', 'groups' => 'COM_USERTOOLS_UNGROUP_STEP_GROUPS',
        'review' => 'COM_USERTOOLS_UNGROUP_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];

    public function display($tpl = null): void
    {
        /** @var UngroupModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        // Where to go when a step cannot be shown: the groups step needs a valid account, so its
        // failure goes back to choosing one (with the draft cleared), never to itself.
        $fallback = $layout === 'groups' || !$this->draft['account'] ? 'view=ungroup' : 'view=ungroup&layout=groups';
        try {
            if ($layout === 'groups') {
                $this->selection = $model->getSelection();
            } elseif ($layout === 'review') {
                $this->review = $model->getReview();
            } elseif ($layout === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=ungroup');
                    return;
                }
            } else {
                $this->setLayout('default');
                $requested = Toolkit::app()->getInput()->getInt('account', 0);
                if ($requested > 0) {
                    $account = Toolkit::directory()->users([$requested])[$requested] ?? null;
                    $this->draft['login']   = $account ? $account->username : '';
                    $this->draft['account'] = $account ? $account->id : 0;
                }
            }
        } catch (UserFacingException $e) {
            if ($fallback === 'view=ungroup') {
                $model->resetDraft();
            }
            $this->leave($e, $fallback);
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_UNGROUP_TITLE');
        Text::script('COM_USERTOOLS_JS_UNGROUP_COUNT');
        Text::script('COM_USERTOOLS_JS_UNGROUP_COUNT_1');
        Text::script('COM_USERTOOLS_JS_UNGROUP_CASCADED');
        parent::display($tpl);
    }

    /**
     * @param  list<int> $ids
     * @return list<int> the ids in the order of the group tree
     */
    public function treeOrder(array $ids): array
    {
        $wanted = array_flip($ids);
        return array_values(array_filter(array_map('intval', array_keys($this->groups)), fn (int $id) => isset($wanted[$id])));
    }

    public function groupTitle(int $id): string
    {
        return isset($this->groups[$id]) ? (string) $this->groups[$id]->title : '#' . $id;
    }
}
