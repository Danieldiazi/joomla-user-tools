<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AnonymizeModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;

/** POST actions of the anonymization of accounts. */
class AnonymizeController extends DisplayController
{
    use ToolControllerTrait;

    private const PERMISSION = 'usertools.anonymize';

    public function search(): void
    {
        $this->guard(self::PERMISSION);
        $post = $this->input->post;
        try {
            $this->model()->search($this->postData() + [
                'source'    => $post->getCmd('source', ''),
                'group'     => $post->getInt('group', 0),
                'subgroups' => $post->getInt('subgroups', 0),
                'retry_cleanup' => $post->getInt('retry_cleanup', 0),
            ]);
            $this->go('view=anonymize&layout=review');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=anonymize');
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
        $this->go('view=anonymize');
    }

    public function apply(): void
    {
        $this->guard(self::PERMISSION);
        $model = $this->model();
        try {
            $done = $model->apply($this->postData());
            $this->go($done ? 'view=anonymize&layout=result' : 'view=anonymize&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=anonymize&layout=review' : 'view=anonymize');
        }
    }

    /** Next batch of a long anonymization (a plain form: works without JavaScript). */
    public function next(): void
    {
        $this->guard(self::PERMISSION);
        try {
            $done = $this->model()->step();
            $this->go($done ? 'view=anonymize&layout=result' : 'view=anonymize&layout=progress');
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go('view=anonymize');
        }
    }

    private function model(): AnonymizeModel
    {
        /** @var AnonymizeModel $model */
        $model = $this->getModel('Anonymize', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
