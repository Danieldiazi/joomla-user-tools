<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\NamesModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST actions for bulk account-name updates. */
class NamesController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.names';

    public function preview(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $lines = $this->input->post->get('lines', '', 'RAW');
            $this->model()->preview(['lines' => is_string($lines) ? $lines : '']);
            $this->go('view=names&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=names');
        }
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $done = $model->apply([
                'preview' => $this->input->post->getString('preview', ''),
                'confirm' => $this->input->post->getInt('confirm', 0),
                'selection_ready' => $this->input->post->getInt('selection_ready', 0),
                'selected' => $this->input->post->get('selected', '[]', 'RAW'),
            ]);
            $this->go($done ? 'view=names&layout=result' : 'view=names&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getJob() ? 'view=names&layout=progress' : ($model->getPreview() ? 'view=names&layout=review' : 'view=names'));
        }
    }

    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $done = $this->model()->step();
            $this->go($done ? 'view=names&layout=result' : 'view=names&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=names');
        }
    }

    private function model(): NamesModel
    {
        /** @var NamesModel $model */
        $model = $this->getModel('Names', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
