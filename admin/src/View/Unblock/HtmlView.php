<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Unblock;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UnblockModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = ['setup' => 'COM_USERTOOLS_UNBLOCK_STEP_BATCH', 'review' => 'COM_USERTOOLS_STEP_SELECT', 'result' => 'COM_USERTOOLS_STEP_RESULT'];

    /** @var list<array<string, mixed>> */
    public array $batches = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    /** @var array<int, object> */
    public array $groups = [];

    public function display($tpl = null): void
    {
        /** @var UnblockModel $model */
        $model  = $this->getModel();
        $layout = $this->getLayout();
        if ($layout === 'review') {
            try {
                $this->review = $model->getReview();
            } catch (UserFacingException $e) {
                $this->leave($e, 'view=unblock');
                return;
            }
            $this->groups = Toolkit::directory()->groups();
        } elseif ($layout === 'result') {
            $this->result = $model->getResult();
            if (!$this->result) {
                Toolkit::app()->redirect('index.php?option=com_usertools&view=unblock');
                return;
            }
        } else {
            $this->setLayout('default');
            $this->batches = $model->getBatches();
        }
        $this->prepareDocument('COM_USERTOOLS_UNBLOCK_TITLE');
        parent::display($tpl);
    }
}
