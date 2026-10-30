<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\UnblockModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST actions of "Undo blocks". */
class UnblockController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.unblock';

    public function select(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->select($this->input->post->getString('batch', ''));
            $this->go('view=unblock&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=unblock');
        }
    }

    public function back(): void
    {
        $this->guard(self::PERMISSION);
        $this->model()->back($this->postData());
        $this->go('view=unblock');
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->apply($this->postData());
            $this->go('view=unblock&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($e->key() === 'COM_USERTOOLS_ERROR_PREVIEW_EXPIRED' ? 'view=unblock' : 'view=unblock&layout=review');
        }
    }

    private function model(): UnblockModel
    {
        /** @var UnblockModel $model */
        $model = $this->getModel('Unblock', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
