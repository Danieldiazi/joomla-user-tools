<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\DeleteModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST actions of the deletion of inactive accounts. */
class DeleteController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.delete';

    public function search(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->search($this->postData() + ['status' => $this->input->post->getCmd('status', '')]);
            $this->go('view=delete&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=delete');
        }
    }

    public function back(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->back($this->postData());
        } catch (UserFacingException $e) {
            $this->warn($e);
        }
        $this->go('view=delete');
    }

    public function delete(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $model->delete($this->postData());
            $this->go('view=delete&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=delete&layout=review' : 'view=delete');
        }
    }

    private function model(): DeleteModel
    {
        /** @var DeleteModel $model */
        $model = $this->getModel('Delete', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
