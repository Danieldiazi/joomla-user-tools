<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\BulkcreateModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\Bulkcreate\HtmlView as BulkcreateView;
use Joomla\CMS\Language\Text;

/** Isolated create-only POST endpoint; all actions require the create permission and CSRF. */
class BulkcreateController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.create';

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->preview(['lines' => $this->input->post->get('lines', '', 'RAW')]);
            $this->go('view=bulkcreate&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($this->model()->getJob() ? 'view=bulkcreate&layout=progress' : 'view=bulkcreate');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $step = $model->apply($this->postData());
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=bulkcreate&layout=review' : 'view=bulkcreate');
            return;
        }
        if (!array_filter($step['created'], static fn (array $row): bool => isset($row['password']))) {
            $this->go($step['done'] ? 'view=bulkcreate&layout=result' : 'view=bulkcreate&layout=progress');
            return;
        }
        $view = $this->view($model, $step['done'] ? 'result' : 'progress');
        if ($step['done']) {
            $view->oneTimeResult = $step['result'];
        } else {
            $view->oneTimeRows = $step['created'];
        }
        $view->display();
    }

    public function step(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $step = $this->model()->step();
            $data = [
                'done' => $step['done'], 'total' => $step['total'], 'processed' => $step['processed'],
                'created' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'], 'username' => (string) $row['username'],
                    'email' => (string) $row['email'],
                    'password' => isset($row['password']) ? (string) $row['password'] : null,
                    'mailed' => $row['mailed'] ?? null,
                ], $step['created']),
                'errors' => \count($step['errors']),
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

    public function discard(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $this->model()->discard();
            $this->go('view=bulkcreate&layout=result');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=bulkcreate');
        }
    }

    private function view(BulkcreateModel $model, string $layout): BulkcreateView
    {
        $view = $this->getView('Bulkcreate', 'html', 'Administrator');
        if (!$view instanceof BulkcreateView) {
            throw new \LogicException('Unexpected view.');
        }
        $view->setModel($model, true);
        $view->setDocument(Toolkit::app()->getDocument());
        $view->setLayout($layout);
        return $view;
    }

    private function model(): BulkcreateModel
    {
        /** @var BulkcreateModel $model */
        $model = $this->getModel('Bulkcreate', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
