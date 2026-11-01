<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\SyncModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Language\Text;

/** Synchronise a branch of groups: every task checks the form token and the tool's permission. */
class SyncController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.sync';

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        $post = $this->input->post;
        try {
            $this->model()->preview(['root' => $post->getInt('root', 0), 'mode' => $post->getCmd('mode', ''), 'by' => $post->getCmd('by', ''),
                'lines' => $post->get('lines', '', 'RAW'), 'complete' => $post->getInt('complete', 0)]);
            $this->go('view=sync&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($this->model()->getJob() ? 'view=sync&layout=progress' : 'view=sync');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        $post = $this->input->post;
        try {
            $done = $model->apply(['preview' => $post->getString('preview', ''), 'confirm' => $post->getInt('confirm', 0),
                'confirm_count' => trim($post->getString('confirm_count', '')), 'mass' => $post->getInt('mass', 0)]);
            $this->go($done ? 'view=sync&layout=result' : 'view=sync&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getJob() ? 'view=sync&layout=progress' : ($model->getPreview() ? 'view=sync&layout=review' : 'view=sync'));
        }
    }

    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $done = $this->model()->step();
            $this->go($done ? 'view=sync&layout=result' : 'view=sync&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=sync');
        }
    }

    public function discard(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->discard();
            $this->go('view=sync&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=sync');
        }
    }

    /** Retries the session closing only; memberships and their log entries are not written again. */
    public function sessions(): void
    {
        $this->guard(self::PERMISSION);
        try {
            if ($this->model()->retrySessions(['account' => $this->input->post->getInt('account', 0)]) === 'closed') {
                $this->app->enqueueMessage(Text::_('COM_USERTOOLS_SYNC_SESSIONS_CLOSED_NOW'), 'success');
            }
        } catch (UserFacingException $e) {
            $this->warn($e);
        }
        $this->go('view=sync&layout=result');
    }

    private function model(): SyncModel
    {
        /** @var SyncModel $model */
        $model = $this->getModel('Sync', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
