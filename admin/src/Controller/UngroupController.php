<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UngroupModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** Actions of "Remove from groups": account → groups → review → result. */
class UngroupController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.ungroup';

    /** Account suggestions while typing (GET, JSON). Needs the form token and the tool permission. */
    public function suggest(): void
    {
        if (!$this->checkToken('get', false) || !$this->app->getIdentity()->authorise(self::PERMISSION, 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $app = Toolkit::app();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->sendHeaders();
        echo json_encode($this->model()->suggest($this->input->getString('q', '')), JSON_UNESCAPED_UNICODE);
        $app->close();
    }

    public function choose(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->choose(['login' => $this->input->post->getString('login', '')]);
            $this->go('view=ungroup&layout=groups');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=ungroup');
        }
    }

    public function select(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->select([
                'keep'      => $this->input->post->get('keep', [], 'ARRAY'),
                'recursive' => $this->input->post->get('recursive', [], 'ARRAY'),
            ]);
            $this->go('view=ungroup&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=ungroup&layout=groups');
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
            $this->go($model->getPreview() ? 'view=ungroup&layout=review' : 'view=ungroup&layout=groups');
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
