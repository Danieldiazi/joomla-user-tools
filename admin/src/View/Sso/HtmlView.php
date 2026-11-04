<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Sso;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\SsoModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = [
        'setup' => 'COM_USERTOOLS_SSO_SEARCH_HEADING',
        'review' => 'COM_USERTOOLS_SSO_REVIEW_HEADING',
        'result' => 'COM_USERTOOLS_STEP_RESULT',
    ];

    /** @var array<string, mixed> */
    public array $draft = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<int, object> */
    public array $groups = [];
    /** @var array<string, mixed>|null */
    public ?array $job = null;
    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function display($tpl = null): void
    {
        /** @var SsoModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        $this->groups = $model->getGroups();
        try {
            switch ($this->getLayout()) {
                case 'review':
                    $this->review = $model->getReview();
                    break;
                case 'progress':
                    $this->job = $model->getJob();
                    if (!$this->job) {
                        Toolkit::app()->redirect('index.php?option=com_usertools&view=sso&layout=result');
                        return;
                    }
                    break;
                case 'result':
                    $this->result = $model->getResult();
                    if (!$this->result) {
                        Toolkit::app()->redirect('index.php?option=com_usertools&view=sso');
                        return;
                    }
                    break;
                default:
                    if ($model->getJob()) {
                        Toolkit::app()->redirect('index.php?option=com_usertools&view=sso&layout=progress');
                        return;
                    }
                    $this->setLayout('default');
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=sso');
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_SSO_TITLE');
        parent::display($tpl);
    }
}
