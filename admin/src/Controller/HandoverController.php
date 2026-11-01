<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\HandoverModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** Handover assistant: every task checks the form token and the tool's permission. */
class HandoverController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.reassign';

    public function source(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->chooseSource(['login' => $this->input->post->getString('login', ''), 'account' => $this->input->post->getInt('account', 0)]);
            $this->go('view=handover&layout=select');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($this->model()->getJob() ? 'view=handover&layout=progress' : 'view=handover');
        }
    }

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        $post = $this->input->post;
        try {
            $this->model()->preview(['login' => $post->getString('login', ''), 'account' => $post->getInt('account', 0),
                'articles' => $post->get('articles', [], 'ARRAY'), 'page' => $post->getInt('page', 0)]);
            $this->go('view=handover&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $model = $this->model();
            $this->go($model->getJob() ? 'view=handover&layout=progress' : ($model->getDraft()['source'] > 0 ? 'view=handover&layout=select' : 'view=handover'));
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        $post = $this->input->post;
        try {
            $done = $model->apply(['preview' => $post->getString('preview', ''), 'confirm' => $post->getInt('confirm', 0),
                'confirm_count' => trim($post->getString('confirm_count', ''))]);
            $this->go($done ? 'view=handover&layout=result' : 'view=handover&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getJob() ? 'view=handover&layout=progress' : ($model->getPreview() ? 'view=handover&layout=review' : 'view=handover&layout=select'));
        }
    }

    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->go($this->model()->step() ? 'view=handover&layout=result' : 'view=handover&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=handover');
        }
    }

    public function discard(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->discard();
            $this->go('view=handover&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=handover');
        }
    }

    private function model(): HandoverModel
    {
        /** @var HandoverModel $model */
        $model = $this->getModel('Handover', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
