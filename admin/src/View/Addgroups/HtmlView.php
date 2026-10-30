<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Addgroups;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AddgroupsModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = ['setup' => 'COM_USERTOOLS_ADDGROUPS_CHOOSE', 'review' => 'COM_USERTOOLS_ADDGROUPS_REVIEW',
        'result' => 'COM_USERTOOLS_STEP_RESULT'];

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

    public function display($tpl = null): void
    {
        /** @var AddgroupsModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        $this->groups = $model->getGroups();
        try {
            if ($this->getLayout() === 'review') {
                $this->review = $model->getReview();
            } elseif ($this->getLayout() === 'progress') {
                $this->job = $model->getJob();
                if (!$this->job) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=addgroups&layout=result');
                    return;
                }
            } elseif ($this->getLayout() === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=addgroups');
                    return;
                }
            } else {
                $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=addgroups');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_ADDGROUPS_TITLE');
        parent::display($tpl);
    }

    public function groupTitle(int $id): string
    {
        return isset($this->groups[$id]) ? (string) $this->groups[$id]->title . ' (#' . $id . ')' : '#' . $id;
    }
}
