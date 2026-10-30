<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Compare;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\CompareModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
    /** Pending job of a comparison being applied (progress screen and the notice on the first screen). */
    /** @var array<string, mixed>|null */
    public ?array $job = null;
    /** Accounts created, with their one-time passwords, by the request that rendered the progress screen. */
    /** @var list<array<string, mixed>> */
    public array $oneTimeRows = [];

    public function display($tpl = null): void
    {
        /** @var CompareModel $model */
        $model        = $this->getModel();
        $this->draft  = $model->getDraft();
        $this->groups = $model->getGroups();
        $layout       = $this->getLayout();
        $this->job    = $model->getJob();
        if ($layout === 'progress') {
            if (!$this->job) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=compare' . ($model->getResult() ? '&layout=result' : ''));
                return;
            }
        } elseif ($layout === 'review') {
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
        Text::script('COM_USERTOOLS_JS_EMAIL_SKIPPED');
        Text::script('COM_USERTOOLS_JS_EMAIL_SKIPPED_1');
        Text::script('COM_USERTOOLS_JS_EMAIL_DOMAIN_INVALID');
        foreach (['PROGRESS', 'PROGRESS_RETRY', 'PROGRESS_FAILED', 'PROGRESS_DONE_PASSWORDS', 'COPY_ALL', 'COPIED'] as $key) {
            Text::script('COM_USERTOOLS_JS_' . $key);
        }
        parent::display($tpl);
    }
}
