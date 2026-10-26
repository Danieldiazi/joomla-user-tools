<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UngroupModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Language\Text;

/** POST actions of "Remove from a group". */
class UngroupController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.ungroup';

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->preview(['login' => $this->input->post->getString('login', ''), 'group' => $this->input->post->getInt('group', 0)]);
            $this->go('view=ungroup&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=ungroup');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $model->apply($this->postData());
            $this->go('view=ungroup&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=ungroup&layout=review' : 'view=ungroup');
        }
    }

    public function sessions(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $state = $this->model()->retrySessions(['account' => $this->input->post->getInt('account', 0)]);
            if ($state === 'closed') {
                $this->app->enqueueMessage(Text::_('COM_USERTOOLS_UNGROUP_SESSIONS_CLOSED_NOW'), 'success');
            }
            $this->go('view=ungroup&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=ungroup');
        }
    }

    private function model(): UngroupModel
    {
        /** @var UngroupModel $model */
        $model = $this->getModel('Ungroup', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
