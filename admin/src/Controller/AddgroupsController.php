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
            $this->go('view=addgroups');
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
            $this->go($model->getPreview() ? 'view=addgroups&layout=review' : 'view=addgroups');
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

    private function model(): AddgroupsModel
    {
        /** @var AddgroupsModel $model */
        $model = $this->getModel('Addgroups', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
