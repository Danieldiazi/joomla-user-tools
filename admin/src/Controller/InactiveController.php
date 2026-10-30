<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\InactiveModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST actions of the manual inactivity review. */
class InactiveController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.inactive';

    /** A profile link opens a review of exactly one account; it never blocks on GET. */
    public function account(): void
    {
        $identity = $this->app->getIdentity();
        if (!$this->checkToken('get', false) || !$identity->authorise(self::PERMISSION, 'com_usertools')
            || !$identity->authorise('usertools.profile', 'com_usertools')) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->app->setHeader('Cache-Control', 'no-store, private', true);
        try {
            $this->model()->reviewAccount($this->input->getInt('account', 0));
            $this->go('view=inactive&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=profile&account=' . $this->input->getInt('account', 0));
        }
    }

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
