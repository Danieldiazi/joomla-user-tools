<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** One catalogue for the home, category pages, administrator menu and dashboard links. */
final class Navigation
{
    /** These two markers are resolved against their native Joomla assets, never the component. */
    public const SUPER_USERS = 'core.admin';
    public const SCHEDULER_MANAGE = 'com_scheduler:core.manage';

    public const PERMISSIONS = [
        'home' => 'core.manage',
        'profile' => 'usertools.profile',
        'compare' => 'usertools.compare',
        'export' => 'usertools.export',
        'inactive' => 'usertools.inactive',
        'unblock' => 'usertools.unblock',
        'delete' => 'usertools.delete',
        'schedule' => self::SCHEDULER_MANAGE,
        'reports' => 'usertools.reports',
        'report' => 'usertools.reports',
        'access' => 'usertools.access',
        'usage' => 'usertools.access',
        'ungroup' => 'usertools.ungroup',
        'diagnose' => 'usertools.diagnose',
        'age' => 'usertools.content',
        'move' => self::SUPER_USERS,
        'addgroups' => 'usertools.addgroups',
        'anonymize' => 'usertools.anonymize',
    ];

    /** @var array<string, array{title: string, description: string}> */
    public const CATEGORIES = [
        'accounts' => ['title' => 'COM_USERTOOLS_HOME_SECTION_ACCOUNTS', 'description' => 'COM_USERTOOLS_HOME_SECTION_ACCOUNTS_DESC'],
        'groups' => ['title' => 'COM_USERTOOLS_HOME_SECTION_GROUPS', 'description' => 'COM_USERTOOLS_HOME_SECTION_GROUPS_DESC'],
        'automation' => ['title' => 'COM_USERTOOLS_HOME_SECTION_AUTOMATION', 'description' => 'COM_USERTOOLS_HOME_SECTION_AUTOMATION_DESC'],
        'delete' => ['title' => 'COM_USERTOOLS_HOME_SECTION_DELETE', 'description' => 'COM_USERTOOLS_HOME_SECTION_DELETE_DESC'],
    ];

    /**
     * view => category, title, explanation, action, badge, icon, optional permanent warning.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6?: string}>
     */
    public const TOOLS = [
        'profile' => ['accounts', 'COM_USERTOOLS_PROFILE_TITLE', 'COM_USERTOOLS_PROFILE_HOME_DESC', 'COM_USERTOOLS_PROFILE_OPEN', 'read', 'vcard'],
        'compare' => ['accounts', 'COM_USERTOOLS_COMPARE_TITLE', 'COM_USERTOOLS_COMPARE_HOME_DESC', 'COM_USERTOOLS_COMPARE_START', '', 'list'],
        'export' => ['accounts', 'COM_USERTOOLS_EXPORT_TITLE', 'COM_USERTOOLS_EXPORT_HOME_DESC', 'COM_USERTOOLS_EXPORT_START', 'read', 'download'],
        'inactive' => ['accounts', 'COM_USERTOOLS_INACTIVE_TITLE', 'COM_USERTOOLS_INACTIVE_HOME_DESC', 'COM_USERTOOLS_INACTIVE_START', '', 'clock'],
        'unblock' => ['accounts', 'COM_USERTOOLS_UNBLOCK_TITLE', 'COM_USERTOOLS_UNBLOCK_HOME_DESC', 'COM_USERTOOLS_UNBLOCK_START', '', 'undo'],
        'addgroups' => ['groups', 'COM_USERTOOLS_ADDGROUPS_TITLE', 'COM_USERTOOLS_ADDGROUPS_HOME_DESC', 'COM_USERTOOLS_ADDGROUPS_START', '', 'users'],
        'ungroup' => ['groups', 'COM_USERTOOLS_UNGROUP_TITLE', 'COM_USERTOOLS_UNGROUP_HOME_DESC', 'COM_USERTOOLS_UNGROUP_START', '', 'users-cog'],
        'move' => ['groups', 'COM_USERTOOLS_MOVE_TITLE', 'COM_USERTOOLS_MOVE_HOME_DESC', 'COM_USERTOOLS_MOVE_START', 'super', 'folder-open'],
        'access' => ['groups', 'COM_USERTOOLS_ACCESS_TITLE', 'COM_USERTOOLS_ACCESS_HOME_DESC', 'COM_USERTOOLS_ACCESS_START', 'read', 'user-lock'],
        'usage' => ['groups', 'COM_USERTOOLS_USAGE_TITLE', 'COM_USERTOOLS_USAGE_HOME_DESC', 'COM_USERTOOLS_USAGE_START', 'read', 'file-alt'],
        'age' => ['groups', 'COM_USERTOOLS_AGE_TITLE', 'COM_USERTOOLS_AGE_HOME_DESC', 'COM_USERTOOLS_AGE_START', 'read', 'calendar'],
        'diagnose' => ['groups', 'COM_USERTOOLS_DIAGNOSE_TITLE', 'COM_USERTOOLS_DIAGNOSE_HOME_DESC', 'COM_USERTOOLS_DIAGNOSE_START', 'read', 'search'],
        'schedule' => ['automation', 'COM_USERTOOLS_SCHEDULE_HOME_TITLE', 'COM_USERTOOLS_SCHEDULE_HOME_DESC', 'COM_USERTOOLS_SCHEDULE_START', 'plugin', 'calendar-check'],
        'reports' => ['automation', 'COM_USERTOOLS_REPORTS', 'COM_USERTOOLS_REPORTS_HOME_DESC', 'COM_USERTOOLS_REPORTS_START', '', 'tasks'],
        'delete' => ['delete', 'COM_USERTOOLS_DELETE_TITLE', 'COM_USERTOOLS_DELETE_HOME_DESC', 'COM_USERTOOLS_DELETE_START', 'permanent', 'trash', 'COM_USERTOOLS_DELETE_HOME_WARNING'],
        'anonymize' => ['delete', 'COM_USERTOOLS_ANONYMIZE_TITLE', 'COM_USERTOOLS_ANONYMIZE_HOME_DESC', 'COM_USERTOOLS_ANONYMIZE_START', 'permanent', 'eye-slash', 'COM_USERTOOLS_ANONYMIZE_HOME_WARNING'],
    ];

    /**
     * No permission or menu result is cached: withdrawing access affects the next request.
     *
     * @param callable(string): bool $allowed
     * @return array<string, array{title: string, description: string, tools: array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6?: string}>}>
     */
    public static function sections(callable $allowed): array
    {
        if (!$allowed('core.manage')) {
            return [];
        }
        $sections = [];
        foreach (self::CATEGORIES as $category => $details) {
            $tools = [];
            foreach (self::TOOLS as $view => $tool) {
                if ($tool[0] === $category && $allowed(self::PERMISSIONS[$view])) {
                    $tools[$view] = $tool;
                }
            }
            if ($tools) {
                $sections[$category] = $details + ['tools' => $tools];
            }
        }
        return $sections;
    }

    public static function categoryForView(string $view): ?string
    {
        return self::TOOLS[$view === 'report' ? 'reports' : $view][0] ?? null;
    }

    public static function categoryLink(string $category): string
    {
        if (!isset(self::CATEGORIES[$category])) {
            throw new \InvalidArgumentException('Unknown User Tools category.');
        }
        return 'index.php?option=com_usertools&view=home&section=' . $category;
    }
}
