<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Model\CompareModel;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView as CompareView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/** POST actions of the manual comparison. */
class CompareController extends BaseController
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
            $result = $model->apply($this->postData());
        } catch (UserFacingException $e) {
            $this->warn($e);
            $this->go($model->getPreview() ? 'view=compare&layout=review' : 'view=compare');
            return;
        }
        $hasPasswords = (bool) array_filter($result['created'], fn ($row) => isset($row['password']));
        if (!$hasPasswords) {
            $this->go('view=compare&layout=result');
            return;
        }
        // One-time passwords are rendered in this response and never stored anywhere.
        $view = $this->getView('Compare', 'html', 'Administrator');
        if (!$view instanceof CompareView) {
            throw new \LogicException('Unexpected view.');
        }
        $view->setModel($model, true);
        $view->setDocument(Toolkit::app()->getDocument());
        $view->setLayout('result');
        $view->oneTimeResult = $result;
        $view->display();
    }

    private function model(): CompareModel
    {
        /** @var CompareModel $model */
        $model = $this->getModel('Compare', 'Administrator', ['ignore_request' => true]);
        return $model;
    }
}
