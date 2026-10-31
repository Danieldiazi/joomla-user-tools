<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View\Explain;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ExplainModel;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessExplanation;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Explain access. Read only: a GET form and its explanation. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    public string $login = '';
    public string $type = 'article';
    public int $item = 0;
    public string $action = 'view';
    public string $context = 'site';
    /** @var array{id: int, username: string, name: string, email: string, blocked: bool}|null */
    public ?array $account = null;
    /** @var list<array<string, mixed>> */
    public array $candidates = [];
    /** @var array<string, mixed>|null */
    public ?array $result = null;
    public string $error = '';
    public bool $canProfile = false;

    public function display($tpl = null): void
    {
        /** @var ExplainModel $model */
        $model = $this->getModel();
        $input = Toolkit::app()->getInput();
        $this->canProfile = $this->can('usertools.profile');
        $this->login  = trim($input->getString('login', ''));
        $this->type   = \in_array($input->getCmd('type', 'article'), AccessExplanation::TYPES, true) ? $input->getCmd('type', 'article') : 'article';
        $this->item   = max(0, $input->getInt('item', 0));
        $this->action = \in_array($input->getCmd('do', 'view'), AccessExplanation::ACTIONS, true) ? $input->getCmd('do', 'view') : 'view';
        $contexts     = AccessExplanation::contexts($this->type, $this->action);
        $this->context = \in_array($input->getCmd('context', ''), $contexts, true) ? $input->getCmd('context', '') : $contexts[0];
        $accountId = $input->getInt('account', 0);
        try {
            if (!$this->canProfile) {
                throw new UserFacingException('COM_USERTOOLS_EXPLAIN_ERROR_PROFILE');
            }
            if ($this->login !== '' || $accountId > 0) {
                $match = $model->account($this->login, $accountId);
                if ($match['status'] === AccountIdentity::AMBIGUOUS) {
                    $this->candidates = $match['candidates'];
                    throw new UserFacingException('COM_USERTOOLS_ACCOUNT_AMBIGUOUS', [$this->login]);
                }
                $this->account = $match['candidates'][0];
                $this->login   = $this->account['username'];
                if ($this->item > 0) {
                    $this->result = $model->explain($this->account['id'], $this->type, $this->item, $this->action, $this->context);
                }
            }
        } catch (UserFacingException $e) {
            $this->error = Text::sprintf($e->key(), ...$e->arguments());
        }
        $this->setLayout('default');
        $this->prepareDocument('COM_USERTOOLS_EXPLAIN_TITLE');
        parent::display($tpl);
    }

    /** Link to the item in Joomla, only if the operator may open it there. */
    public function itemLink(): string
    {
        $item = $this->result['item'] ?? null;
        if (!$item) {
            return '';
        }
        $user  = Factory::getApplication()->getIdentity();
        $asset = 'com_content.' . $item['type'] . '.' . (int) $item['id'];
        $own   = $user->authorise('core.edit.own', $asset) && (int) $item['author'] === (int) $user->id;
        if (!$user->authorise('core.edit', $asset) && !$own) {
            return '';
        }
        return $item['type'] === 'article'
            ? 'index.php?option=com_content&task=article.edit&id=' . (int) $item['id']
            : 'index.php?option=com_categories&task=category.edit&id=' . (int) $item['id'] . '&extension=com_content';
    }

    /** Joomla's permission settings for articles open with core.admin on com_content. */
    public function canPermissions(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_content');
    }

    /** Access levels open with core.admin on com_users. */
    public function canLevels(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_users');
    }

    public function statusLabel(string $status): string
    {
        return Text::_('COM_USERTOOLS_EXPLAIN_STATUS_' . strtoupper($status));
    }

    /** @param array{text: string, args: list<string|int>} $check */
    public function checkText(array $check): string
    {
        $args = array_map(fn ($arg) => \is_string($arg) && preg_match('/^J[A-Z_]+$/D', $arg) ? Text::_($arg) : $this->escape((string) $arg), $check['args']);
        return Text::sprintf($check['text'], ...$args);
    }
}
