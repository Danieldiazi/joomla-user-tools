<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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

    public static function regrouper(): AccountRegrouper
    {
        return new AccountRegrouper(self::users(), new ActionLogger());
    }

    public static function groupMover(): GroupMover
    {
        return new GroupMover(self::db(), new ActionLogger());
    }

    public static function deleter(): AccountDeleter
    {
        return new AccountDeleter(self::users(), new ActionLogger());
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

    /** Reads how many items use each access level, with names in the interface language. */
    public static function usageReader(): AccessUsageReader
    {
        $identity = self::app()->getIdentity();
        $language = self::app()->getLanguage();
        return new AccessUsageReader(
            self::db(),
            fn (string $key, string $argument) => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument),
            function (string $component) use ($language): string {
                // Component names come from each component's own language file.
                $language->load($component . '.sys', JPATH_ADMINISTRATOR) || $language->load($component . '.sys', JPATH_ADMINISTRATOR . '/components/' . $component);
                $key = strtoupper($component);
                return $language->hasKey($key) ? Text::_($key) : $component;
            },
            fn (string $component) => (bool) $identity?->authorise('core.manage', $component),
        );
    }

    /**
     * Access diagnosis (see AccessHealth::build()).
     *
     * @return array{days: int, checks: array<string, array{severity: string, found: bool, items: list<array<string, mixed>>, count: int}>}
     */
    public static function accessHealth(\DateTimeImmutable $now): array
    {
        $directory = self::directory();
        $guest     = (int) ComponentHelper::getParams('com_users')->get('guest_usergroup', 1);
        $groups    = $directory->groups();
        $levels    = $directory->viewLevels();
        $matrix    = AccessMatrix::build($groups, $levels, [], $guest);
        $usage     = AccessUsage::build(array_map(fn (array $level) => ['id' => $level['id'], 'title' => $level['title']], $levels), self::usageReader()->sources());
        return AccessHealth::build($directory->users(), $groups, $directory->membersPerGroup(), $matrix['levels'], $usage, $guest, $now, self::settings()->diagnoseDays());
    }

    /** Cache group of the dashboard module. */
    public const DASHBOARD_CACHE = 'mod_usertools';

    /** Minutes the dashboard figures are kept when nothing changes. */
    public const DASHBOARD_MINUTES = 10;

    /**
     * Dashboard figures for one user, cached for a few minutes. Each part is only computed when the
     * user may use the matching tool.
     *
     * @return array{inactive: ?int, pendingDeletion: ?int, tasks: ?list<array<string, mixed>>, diagnosis: ?int, alerts: list<array<string, mixed>>, generated: string}
     */
    public static function dashboard(\Joomla\CMS\User\User $user): array
    {
        $can = [
            'inactive' => $user->authorise('usertools.inactive', 'com_usertools'),
            'delete'   => $user->authorise('usertools.delete', 'com_usertools'),
            'reports'  => $user->authorise('usertools.reports', 'com_usertools'),
            'diagnose' => $user->authorise('usertools.diagnose', 'com_usertools'),
        ];
        $key   = 'dashboard-' . (int) $user->id . '-' . implode('', array_map('intval', $can));
        $cache = self::dashboardCache();
        $json  = $cache->get($key);
        if (\is_string($json) && \is_array($data = json_decode($json, true))) {
            return $data;
        }
        $now  = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $data = ['inactive' => null, 'pendingDeletion' => null, 'tasks' => null, 'diagnosis' => null, 'alerts' => [], 'generated' => $now->format('Y-m-d H:i:s')];
        if ($can['inactive'] || $can['delete']) {
            $directory = self::directory();
            $counts    = DashboardSummary::counts($directory->users(), self::settings(), Operator::fromUser($user),
                fn (array $ids) => $directory->withRecentSession($ids, time() - self::sessionWindow()), $now);
            $data['inactive']        = $can['inactive'] ? $counts['inactive'] : null;
            $data['pendingDeletion'] = $can['delete'] ? $counts['pendingDeletion'] : null;
        }
        $pluginState = self::pluginState();
        if ($can['reports']) {
            $db    = self::db();
            $query = $db->createQuery()
                ->select($db->quoteName(['id', 'title', 'state', 'last_execution', 'next_execution', 'last_exit_code', 'params', 'created']))
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('usertools.block_inactive'))
                ->where($db->quoteName('state') . ' IN (0, 1)')
                ->order($db->quoteName('id'));
            $rows = $db->setQuery($query)->loadAssocList();
            try {
                $latest = self::reports()->latestByTask(array_map(fn ($r) => (int) $r['id'], $rows));
            } catch (\Throwable) {
                $latest = [];
            }
            $data['tasks']  = DashboardSummary::tasks($rows, $latest);
            $data['alerts'] = DashboardSummary::alerts($pluginState, $data['tasks'], $now);
        } elseif ($pluginState !== 'enabled') {
            $data['alerts'] = DashboardSummary::alerts($pluginState, [], $now);
        }
        if ($can['diagnose']) {
            // The same checks as the diagnosis screen; a failure there does not hide the rest of the summary.
            try {
                $data['diagnosis'] = AccessHealth::reviewCount(self::accessHealth($now));
                $data['alerts']    = DashboardSummary::withDiagnosis($data['alerts'], $data['diagnosis']);
            } catch (\Throwable) {
                $data['diagnosis'] = null;
            }
        }
        $cache->store((string) json_encode($data), $key);
        return $data;
    }

    /** Called after any change to accounts or tasks, so the dashboard never shows stale figures. */
    public static function clearDashboardCache(): void
    {
        try {
            self::dashboardCache()->cache->clean(self::DASHBOARD_CACHE);
        } catch (\Throwable) {
        }
    }

    private static function dashboardCache(): OutputController
    {
        /** @var OutputController $controller */
        $controller = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
            'defaultgroup' => self::DASHBOARD_CACHE,
            // Same folder for the administrator, the site (lazy scheduler) and the CLI.
            'cachebase'    => JPATH_ADMINISTRATOR . '/cache',
            'caching'      => true,
            'lifetime'     => self::DASHBOARD_MINUTES,
        ]);
        return $controller;
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
