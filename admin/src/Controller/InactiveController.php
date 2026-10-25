<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\InactiveModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Controller\BaseController;

/** POST actions of the manual inactivity review. */
class InactiveController extends BaseController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.inactive';

    public function search(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->search($this->postData());
            $this->go('view=inactive&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=inactive');
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
        $this->go('view=inactive');
    }

    public function suspend(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $model->suspend($this->postData());
            $this->go('view=inactive&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=inactive&layout=review' : 'view=inactive');
        }
    }

    private function model(): InactiveModel
    {
        /** @var InactiveModel $model */
        $model = $this->getModel('Inactive', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
