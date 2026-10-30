<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Plugin\System\Usertools\Extension;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController;
use Danieldiazi\Component\Usertools\Administrator\Service\Navigation;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

/** Filters only User Tools links through Joomla's public administrator menu event. */
final class Usertools extends CMSPlugin implements SubscriberInterface
{
    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return ['onPreprocessMenuItems' => 'onPreprocessMenuItems'];
    }

    public function onPreprocessMenuItems(PreprocessMenuItemsEvent $event): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator') || $event->getContext() !== 'com_menus.administrator.module'
            || !ComponentHelper::isEnabled('com_usertools')) {
            return;
        }
        $user = $app->getIdentity();
        if ($user === null) {
            return;
        }
        // Booting registers the component namespace on every administrator screen.
        $app->bootComponent('com_usertools');
        $allowed = static fn (string $permission): bool => DisplayController::allowed($user, $permission);
        $sections = Navigation::sections($allowed);
        $input = $app->getInput();
        $view = $input->getCmd('view', 'home');
        $active = $input->getCmd('option') === 'com_usertools'
            ? ($view === 'home' ? $input->getCmd('section', '') : Navigation::categoryForView($view)) : null;

        foreach ($event->getItems() as $item) {
            $uri = new Uri(html_entity_decode((string) $item->link, ENT_QUOTES, 'UTF-8'));
            if ($uri->getVar('option') !== 'com_usertools') {
                continue;
            }
            $itemView = (string) $uri->getVar('view', 'home');
            $section = (string) $uri->getVar('section', '');
            $permission = Navigation::PERMISSIONS[$itemView] ?? 'core.manage';
            if (!$allowed('core.manage') || !$allowed($permission)
                || ($itemView === 'home' && $section !== '' && !isset($sections[$section]))) {
                $item->getParent()?->removeChild($item);
                continue;
            }
            // Keep category links pointing to their landing pages while marking their native path.
            if ($itemView === 'home' && !$item->hasChildren() && $active !== null && $section === $active) {
                for ($node = $item; $node instanceof MenuItem; $node = $node->getParent()) {
                    $node->active = true;
                }
            }
        }
    }
}
