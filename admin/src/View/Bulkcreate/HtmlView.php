<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Bulkcreate;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\BulkcreateModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Presentation state is fetched from the model; all review rows are server-owned. */
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
    public ?array $job = null;
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<string, mixed>|null */
    public ?array $oneTimeResult = null;
    /** @var list<array<string, mixed>> */
    public array $oneTimeRows = [];
    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        /** @var BulkcreateModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        $this->job = $model->getJob();
        $this->groups = Toolkit::directory()->groups();
        $layout = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->preview = $model->getPreview();
                $this->review = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=bulkcreate');
                return;
            }
        } elseif ($layout === 'progress') {
            if (!$this->job) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=bulkcreate' . ($model->getResult() ? '&layout=result' : ''));
                return;
            }
        } elseif ($layout === 'result') {
            $this->result = $this->oneTimeResult ?? $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=bulkcreate');
                return;
            }
        } else {
            $this->setLayout('default');
        }
        $this->prepareDocument('COM_USERTOOLS_BULKCREATE_TITLE');
        foreach (['EMAIL_SKIPPED', 'EMAIL_SKIPPED_1', 'EMAIL_DOMAIN_INVALID', 'PROGRESS', 'PROGRESS_RETRY', 'PROGRESS_FAILED',
            'PROGRESS_DONE_PASSWORDS', 'COPY_ALL', 'COPIED'] as $key) {
            Text::script('COM_USERTOOLS_JS_' . $key);
        }
        parent::display($tpl);
    }
}
