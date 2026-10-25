<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Compare;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\CompareModel;
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
    /** @var array<string, mixed>|null */
    public ?array $preview = null;
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** Result with one-time passwords, set by the controller for the response that applies the changes. */
    /** @var array<string, mixed>|null */
    public ?array $oneTimeResult = null;
    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        /** @var CompareModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->preview = $model->getPreview();
                $this->review  = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=compare');
                return;
            }
        } elseif ($layout === 'result') {
            $this->result = $this->oneTimeResult ?? $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools');
                return;
            }
        } else {
            $this->setLayout('default');
        }
        $this->prepareDocument('COM_USERTOOLS_COMPARE');
        parent::display($tpl);
    }
}
