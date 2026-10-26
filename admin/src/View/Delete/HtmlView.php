<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Delete;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\DeleteModel;
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
        /** @var DeleteModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->review = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=delete');
                return;
            }
        } elseif ($layout === 'result') {
            $this->result = $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=delete');
                return;
            }
        } else {
            $this->setLayout('default');
        }
        $this->prepareDocument('COM_USERTOOLS_DELETE');
        parent::display($tpl);
    }
}
