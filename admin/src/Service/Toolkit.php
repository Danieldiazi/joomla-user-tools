<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

/** Builds the services shared by the component and the task plugin from Joomla's container. */
final class Toolkit
{
    /** The web application (administrator or site). Screens never run in the CLI. */
    public static function app(): CMSApplication
    {
        $app = Factory::getApplication();
        if (!$app instanceof CMSApplication) {
            throw new \LogicException('User Tools screens need a web application.');
        }
        return $app;
    }

    public static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    public static function users(): UserFactoryInterface
    {
        return Factory::getContainer()->get(UserFactoryInterface::class);
    }

    public static function directory(): UserDirectory
    {
        return new UserDirectory(self::db(), null, (string) Factory::getApplication()->get('root_user', ''));
    }

    public static function settings(): Settings
    {
        return new Settings(ComponentHelper::getParams('com_usertools')->toArray());
    }

    public static function settingsStore(): SettingsStore
    {
        return new SettingsStore(self::db());
    }

    public static function blocker(): AccountBlocker
    {
        return new AccountBlocker(self::users(), new ActionLogger());
    }

    public static function reports(): ReportStore
    {
        $app = Factory::getApplication();
        return ReportStore::forLogPath((string) $app->get('log_path', JPATH_ADMINISTRATOR . '/logs'));
    }

    public static function passwords(): PasswordGenerator
    {
        return new PasswordGenerator(ComponentHelper::getParams('com_users')->toArray());
    }

    /** Session lifetime in seconds; sessions newer than this are treated as active. */
    public static function sessionWindow(): int
    {
        return max(1, (int) Factory::getApplication()->get('lifetime', 15)) * 60;
    }

    /** Status of the task plugin for the home and schedule screens. */
    public static function pluginState(): string
    {
        $db    = self::db();
        $query = $db->createQuery()->select($db->quoteName('enabled'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('usertools'));
        $enabled = $db->setQuery($query)->loadResult();
        return $enabled === null ? 'missing' : ((int) $enabled === 1 ? 'enabled' : 'disabled');
    }
}
