<?php

namespace Danieldiazi\Component\Usertools\Administrator\View;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

/** Toolbar, assets and helpers shared by every screen. */
trait ViewTrait
{
    protected function prepareDocument(string $titleKey): void
    {
        $user = Factory::getApplication()->getIdentity();
        // The home screen is titled with the extension name alone.
        ToolbarHelper::title(Text::_('COM_USERTOOLS') . ($titleKey === '' ? '' : ' · ' . Text::_($titleKey)), 'users');
        if ($user->authorise('core.admin', 'com_usertools') || $user->authorise('core.options', 'com_usertools')) {
            ToolbarHelper::preferences('com_usertools');
        }
        ToolbarHelper::help('', false, 'https://github.com/Danieldiazi/joomla-user-tools#readme');
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_usertools');
        $wa->useStyle('com_usertools.admin')->useScript('com_usertools.admin');
        foreach (['COM_USERTOOLS_JS_SELECTED', 'COM_USERTOOLS_JS_NO_RESULTS', 'COM_USERTOOLS_JS_TYPE_COUNT'] as $key) {
            Text::script($key);
        }
    }

    /** Leaves the screen with a message when its state expired (for example a review after 15 minutes). */
    protected function leave(UserFacingException $e, string $query): void
    {
        $app = Toolkit::app();
        $app->enqueueMessage(Text::sprintf($e->key(), ...array_map(fn ($a) => \is_string($a) ? $this->escape($a) : $a, $e->arguments())), 'warning');
        $app->redirect('index.php?option=com_usertools&' . $query);
    }

    public function can(string $action): bool
    {
        return Factory::getApplication()->getIdentity()->authorise($action, 'com_usertools');
    }

    /** UTC date for tables, or "Never". */
    public function date(?string $value): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return Text::_('COM_USERTOOLS_NEVER');
        }
        return $this->escape($value);
    }

    /**
     * Group titles of an account, for the review tables.
     *
     * @param list<int>          $ids
     * @param array<int, object> $groups
     */
    public function groupTitles(array $ids, array $groups): string
    {
        $titles = [];
        foreach ($ids as $id) {
            if (isset($groups[$id])) {
                $titles[] = $groups[$id]->title;
            }
        }
        return $this->escape(implode(', ', $titles));
    }
}
