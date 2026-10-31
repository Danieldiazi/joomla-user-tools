<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Profile;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ProfileModel;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
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
    public int $accountId = 0;
    /** @var list<array<string, mixed>> */
    public array $candidates = [];
    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ProfileModel $model */
        $model = $this->getModel();
        $input = Toolkit::app()->getInput();
        $this->login = trim($input->getString('login', ''));
        $id = $input->getInt('account', 0);
        try {
            if ($this->login !== '' || $id > 0) {
                $match = $model->pickAccount($this->login, $id);
                $this->candidates = $match['candidates'];
                $id = $match['id'];
                if ($match['status'] === AccountIdentity::AMBIGUOUS) {
                    throw new UserFacingException('COM_USERTOOLS_ACCOUNT_AMBIGUOUS', [$this->login]);
                }
                $this->candidates = [];
            }
            $this->profile = $model->getProfile($id);
            if ($this->profile) {
                $this->accountId = (int) $this->profile['account']->id;
                $this->login     = (string) $this->profile['account']->username;
            }
        } catch (UserFacingException $e) {
            $this->error = Text::sprintf($e->key(), ...$e->arguments());
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
