<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Profile;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ProfileModel;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;
    public string $login = '';
    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ProfileModel $model */
        $model = $this->getModel();
        $input = Toolkit::app()->getInput();
        $this->login = trim($input->getString('login', ''));
        $id = $input->getInt('account', 0);
        try {
            if ($this->login !== '') {
                $id = $model->findAccount($this->login);
            }
            $this->profile = $model->getProfile($id);
        } catch (UserFacingException $e) {
            $this->error = Text::_($e->key());
        }
        $this->setLayout('default');
        $this->prepareDocument('COM_USERTOOLS_PROFILE_TITLE');
        parent::display($tpl);
    }

    public function componentTitle(string $context): string
    {
        $component = explode('.', $context)[0];
        if ($component === 'com_usertools') {
            return Text::_('COM_USERTOOLS');
        }
        if (!preg_match('/^com_[a-z0-9_]+$/D', $component)) {
            return Text::_('COM_USERTOOLS_PROFILE_OTHER_SOURCE');
        }
        $language = Toolkit::app()->getLanguage();
        $language->load($component . '.sys', JPATH_ADMINISTRATOR);
        $key = strtoupper($component);
        return $language->hasKey($key) ? Text::_($key) : Text::_('COM_USERTOOLS_PROFILE_OTHER_SOURCE');
    }

    public function groupTitle(int $id): string
    {
        return (string) ($this->profile['groups'][$id]->title ?? '#' . $id);
    }
}
