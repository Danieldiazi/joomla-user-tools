<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Inactive;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\InactiveModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed> */
    public array $draft = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        /** @var InactiveModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->review = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=inactive');
                return;
            }
        } elseif ($layout === 'result') {
            $this->result = $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=inactive');
                return;
            }
        } else {
            $this->setLayout('default');
        }
        $this->prepareDocument('COM_USERTOOLS_INACTIVE');
        parent::display($tpl);
    }
}
