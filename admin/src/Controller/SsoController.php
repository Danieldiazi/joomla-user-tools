<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\SsoModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST endpoints for one-use, reviewed SSO credential rotation. */
class SsoController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.sso';

    public function search(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->search([
                'group' => $this->input->post->getInt('group', 0),
                'subgroups' => $this->input->post->getInt('subgroups', 0),
                'status' => $this->input->post->getCmd('status', 'all'),
                'visits' => $this->input->post->getCmd('visits', 'all'),
            ]);
            $this->go('view=sso&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=sso');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $done = $model->apply($this->postData());
            $this->go($done ? 'view=sso&layout=result' : 'view=sso&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getJob() ? 'view=sso&layout=progress'
                : ($model->getPreview() ? 'view=sso&layout=review' : 'view=sso'));
        }
    }

    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $done = $this->model()->step();
            $this->go($done ? 'view=sso&layout=result' : 'view=sso&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=sso&layout=progress');
        }
    }

    public function discard(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->discard();
            $this->go('view=sso&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=sso');
        }
    }

    private function model(): SsoModel
    {
        /** @var SsoModel $model */
        $model = $this->getModel('Sso', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
