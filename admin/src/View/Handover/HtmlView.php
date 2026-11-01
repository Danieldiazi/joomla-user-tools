<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Handover;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\HandoverModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Handover assistant: account, articles and receiver, review, result with the closing step. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public const STEPS = ['account' => 'COM_USERTOOLS_HANDOVER_STEP_ACCOUNT', 'select' => 'COM_USERTOOLS_HANDOVER_STEP_SELECT',
        'review' => 'COM_USERTOOLS_HANDOVER_STEP_REVIEW', 'result' => 'COM_USERTOOLS_HANDOVER_STEP_RESULT'];

    /** @var array<string, mixed> */
    public array $draft = [];
    /** @var array<string, mixed> */
    public array $inventory = [];
    /** @var array<string, mixed> */
    public array $review = [];
    /** @var array<string, mixed>|null */
    public ?array $job = null;
    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function display($tpl = null): void
    {
        /** @var HandoverModel $model */
        $model = $this->getModel();
        $this->draft = $model->getDraft();
        $layout = $this->getLayout();
        // A review that can no longer be shown goes back to the selection, which explains what changed.
        $fallback = $layout === 'review' && $this->draft['source'] > 0 ? 'view=handover&layout=select' : 'view=handover';
        try {
            $model->assertAllowed();
        } catch (UserFacingException $e) {
            // Without the profile permission or access to articles, the whole assistant is unavailable.
            $this->leave($e, 'view=home');
            return;
        }
        try {
            if ($layout === 'select') {
                // Other pages of a long inventory are opened with ?page=N; reading them changes nothing.
                $this->inventory = $model->getInventory(Toolkit::app()->getInput()->getInt('page', $this->draft['page']));
            } elseif ($layout === 'review') {
                $this->review = $model->getReview();
            } elseif ($layout === 'progress') {
                $this->job = $model->getJob();
                if (!$this->job) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=handover&layout=result');
                    return;
                }
            } elseif ($layout === 'result') {
                $this->result = $model->getResult();
                if (!$this->result) {
                    Toolkit::app()->redirect('index.php?option=com_usertools&view=handover');
                    return;
                }
            } else {
                $this->setLayout('default');
                // Opened from an account profile: the account is filled in, nothing is chosen until it is sent.
                $requested = Toolkit::app()->getInput()->getInt('account', 0);
                if ($requested > 0) {
                    $account = Toolkit::directory()->users([$requested])[$requested] ?? null;
                    $this->draft['login'] = $account ? $account->username : '';
                    $this->draft['source'] = $account ? $account->id : 0;
                    $this->draft['candidates'] = [];
                }
            }
        } catch (UserFacingException $e) {
            $this->leave($e, $fallback);
            return;
        }
        $this->prepareDocument('COM_USERTOOLS_HANDOVER_TITLE');
        parent::display($tpl);
    }

    public function stateLabel(int $state): string
    {
        return Text::_(match ($state) {
            1 => 'JPUBLISHED',
            0 => 'JUNPUBLISHED',
            2 => 'JARCHIVED',
            -2 => 'JTRASHED',
            default => 'JUNDEFINED',
        });
    }
}
