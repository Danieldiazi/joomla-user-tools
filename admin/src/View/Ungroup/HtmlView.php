<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Ungroup;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UngroupModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** "Remove from a group": choose, review, result. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array{login: string, group: int} */
    public array $draft = ['login' => '', 'group' => 0];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        /** @var UngroupModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->review = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=ungroup');
                return;
            }
        } elseif ($layout === 'result') {
            $this->result = $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=ungroup');
                return;
            }
        } else {
            $this->setLayout('default');
        }
        $this->prepareDocument('COM_USERTOOLS_UNGROUP_TITLE');
        parent::display($tpl);
    }

    public function groupTitle(int $id): string
    {
        return isset($this->groups[$id]) ? (string) $this->groups[$id]->title : '#' . $id;
    }
}
