<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Model\CompareModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView as CompareView;
use Joomla\CMS\Language\Text;

/** POST actions of the manual comparison. */
class CompareController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.compare';

    public function saveExclusions(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->saveExclusions($this->postData());
            $this->app->enqueueMessage(Text::_('COM_USERTOOLS_COMPARE_EXCLUSIONS_SAVED'));
        } catch (UserFacingException $e) {
            $this->warn($e);
        }
        $this->go('view=compare');
    }

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->preview($this->postData());
            $this->go('view=compare&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=compare');
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
        $this->go('view=compare');
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $step = $model->apply($this->postData());
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=compare&layout=review' : 'view=compare');
            return;
        }
        $hasPasswords = (bool) array_filter($step['created'], fn ($row) => isset($row['password']));
        if (!$hasPasswords) {
            $this->go($step['done'] ? 'view=compare&layout=result' : 'view=compare&layout=progress');
            return;
        }
        // One-time passwords are rendered in this response and never stored anywhere.
        $view = $this->view($model, $step['done'] ? 'result' : 'progress');
        if ($step['done']) {
            $view->oneTimeResult = $step['result'];
        } else {
            $view->oneTimeRows = $step['created'];
        }
        $view->display();
    }

    /** One batch of a comparison being applied, as JSON for the progress screen. */
    public function step(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $step = $this->model()->step();
            $data = [
                'done'      => $step['done'],
                'total'     => $step['total'],
                'processed' => $step['processed'],
                'blocked'   => \count($step['blocked']),
                // One-time passwords travel only in the response of the batch that created them.
                'created'   => array_map(fn ($row) => [
                    'id' => (int) $row['id'], 'username' => (string) $row['username'], 'email' => (string) $row['email'],
                    'password' => isset($row['password']) ? (string) $row['password'] : null, 'mailed' => $row['mailed'] ?? null,
                ], $step['created']),
                'errors'    => \count($step['errors']),
            ];
        } catch (UserFacingException $e) {
            $data = ['error' => Text::sprintf($e->key(), ...$e->arguments())];
        }
        Toolkit::app()->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        Toolkit::app()->setHeader('X-Content-Type-Options', 'nosniff', true);
        Toolkit::app()->sendHeaders();
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }

    /** Stops a pending job: what was done stays, the rest is left untouched. */
    public function discard(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->discard();
            $this->go('view=compare&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=compare');
        }
    }

    private function view(CompareModel $model, string $layout): CompareView
    {
        $view = $this->getView('Compare', 'html', 'Administrator');
        if (!$view instanceof CompareView) {
            throw new \LogicException('Unexpected view.');
        }
        $view->setModel($model, true);
        $view->setDocument(Toolkit::app()->getDocument());
        $view->setLayout($layout);
        return $view;
    }

    private function model(): CompareModel
    {
        /** @var CompareModel $model */
        $model = $this->getModel('Compare', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
