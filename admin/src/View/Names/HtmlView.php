<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Names;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\NamesModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = ['setup' => 'COM_USERTOOLS_NAMES_CHOOSE', 'review' => 'COM_USERTOOLS_NAMES_REVIEW',
        'result' => 'COM_USERTOOLS_STEP_RESULT'];

    public string $draft = '';
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $job = null;
    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function display($tpl = null): void
    {
        /** @var NamesModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        try {
            if ($this->getLayout() === 'review') {
                $this->review = $model->getReview();
            } elseif ($this->getLayout() === 'progress') {
                $this->job = $model->getJob();
                if (!$this->job) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=names&layout=result');
                    return;
                }
            } elseif ($this->getLayout() === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=names');
                    return;
                }
            } else {
                $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=names');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_NAMES_TITLE');
        parent::display($tpl);
    }
}
