<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\MoveModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** Actions of "Move groups": groups and parent → review → result. Super Users only. */
class MoveController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = DisplayController::SUPER_USERS;

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->preview([
                'groups' => $this->input->post->get('groups', [], 'ARRAY'),
                'target' => $this->input->post->getInt('target', 0),
            ]);
            $this->go('view=move&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=move');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $model->apply($this->postData());
            $this->go('view=move&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=move&layout=review' : 'view=move');
        }
    }

    private function model(): MoveModel
    {
        /** @var MoveModel $model */
        $model = $this->getModel('Move', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
