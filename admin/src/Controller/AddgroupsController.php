<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AddgroupsModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

class AddgroupsController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.addgroups';

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        $post = $this->input->post;
        try {
            $this->model()->preview(['mode' => $post->getCmd('mode', 'single'),
                'lines' => $post->get('lines', '', 'RAW'), 'group' => $post->getString('group', '')]);
            $this->go('view=addgroups&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($this->model()->getJob() ? 'view=addgroups&layout=progress' : 'view=addgroups');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $done = $model->apply($this->postData());
            $this->go($done ? 'view=addgroups&layout=result' : 'view=addgroups&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getJob() ? 'view=addgroups&layout=progress' : ($model->getPreview() ? 'view=addgroups&layout=review' : 'view=addgroups'));
        }
    }

    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $done = $this->model()->step();
            $this->go($done ? 'view=addgroups&layout=result' : 'view=addgroups&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=addgroups');
        }
    }

    /** Retry session closure only; memberships and action logs are not written again. */
    public function sessions(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $state = $this->model()->retrySessions(['account' => $this->input->post->getInt('account', 0)]);
            if ($state === 'closed') {
                $this->app->enqueueMessage(\Joomla\CMS\Language\Text::_('COM_USERTOOLS_ADDGROUPS_SESSIONS_CLOSED_NOW'), 'success');
            }
            $this->go('view=addgroups&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=addgroups&layout=result');
        }
    }

    private function model(): AddgroupsModel
    {
        /** @var AddgroupsModel $model */
        $model = $this->getModel('Addgroups', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
