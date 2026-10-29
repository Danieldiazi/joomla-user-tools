<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Anonymize;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AnonymizeModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** "Anonymize accounts": choose, review and select, progress, result. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

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
    public bool $pluginReady = true;

    public const STEPS = ['setup' => 'COM_USERTOOLS_ANONYMIZE_STEP_CHOOSE', 'review' => 'COM_USERTOOLS_ANONYMIZE_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];

    public function display($tpl = null): void
    {
        /** @var AnonymizeModel $model */
        $model             = $this->getModel();
        $this->draft       = $model->getDraft();
        $this->groups      = $model->getGroups();
        $this->pluginReady = $model->pluginReady();
        $layout            = $this->getLayout();
        try {
            if ($layout === 'review') {
                $this->review = $model->getReview();
            } elseif ($layout === 'progress') {
                $this->job = $model->getJob();
                if (!$this->job) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=anonymize&layout=result');
                    return;
                }
            } elseif ($layout === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=anonymize');
                    return;
                }
            } else {
                $this->job = $model->getJob();
                $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=anonymize');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_ANONYMIZE_TITLE');
        parent::display($tpl);
    }
}
