<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\View;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GuidedTours;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

/** Toolbar, assets and helpers shared by every screen. */
trait ViewTrait
{
    protected function prepareDocument(string $titleKey): void
    {
        $user = Factory::getApplication()->getIdentity();
        // The home screen is titled with the extension name alone.
        ToolbarHelper::title(Text::_('COM_USERTOOLS') . ($titleKey === '' ? '' : ' · ' . Text::_($titleKey)), 'users');
        if ($titleKey !== '') {
            // Every screen but the home screen leads back to it from the same place in Joomla's toolbar.
            ToolbarHelper::link(Route::_('index.php?option=com_usertools'), Text::_('COM_USERTOOLS_TOOLBAR_HOME'), 'home');
        }
        if ($user->authorise('core.admin', 'com_usertools') || $user->authorise('core.options', 'com_usertools')) {
            ToolbarHelper::preferences('com_usertools');
        }
        $this->tourButton();
        ToolbarHelper::help('', false, 'https://github.com/Danieldiazi/joomla-user-tools#readme');
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_usertools');
        $wa->useStyle('com_usertools.admin')->useScript('com_usertools.admin');
        foreach (['COM_USERTOOLS_JS_SELECTED', 'COM_USERTOOLS_JS_NO_RESULTS', 'COM_USERTOOLS_JS_TYPE_COUNT'] as $key) {
            Text::script($key);
        }
    }

    /**
     * Next to Help, the button that starts this screen's guided tour. Only on the first screen of a
     * tool (the tour explains it), and only if Joomla's guided tours are enabled and the tour is
     * published for the user: the screen itself already required the tool's permission.
     */
    private function tourButton(): void
    {
        $view = $this->getName();
        if ($this->getLayout() !== 'default' || !isset(GuidedTours::TOURS[$view])
            || !ComponentHelper::isEnabled('com_guidedtours') || !PluginHelper::isEnabled('system', 'guidedtours')) {
            return;
        }
        $document = $this->getDocument();
        $user     = Factory::getApplication()->getIdentity();
        $id       = GuidedTours::find(Toolkit::db(), $view, array_map('intval', $user->getAuthorisedViewLevels()));
        if ($id < 1 || !$document instanceof HtmlDocument) {
            return;
        }
        // Joomla's tours script starts the tour on a click whose target has this class and data-id,
        // so the icon inside the button lets clicks through to it (see admin.css).
        $document->getToolbar()->customButton('ut-tour')->html(
            '<button type="button" class="btn btn-info button-start-guidedtour" data-id="' . $id . '" data-ut-tour-start="' . $this->escape($view) . '">'
            . '<span class="icon-map-signs" aria-hidden="true"></span> ' . Text::_('COM_USERTOOLS_TOUR_BUTTON') . '</button>'
        );
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
        return \Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController::allowed(Factory::getApplication()->getIdentity(), $action);
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
