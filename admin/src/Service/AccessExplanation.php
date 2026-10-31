<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Explains why an account can or cannot view, edit or publish one article or article category.
 * Pure logic over facts read from Joomla (AccessExplanationReader): permissions come from Joomla's
 * own authorisation (User::authorise), never from a second ACL, and every condition this class does
 * not model is listed as "not checked". It never changes anything and never acts as the account.
 *
 * Mirrors com_content as of Joomla 6.2: on the site, an article needs a published (or archived)
 * state, the publishing dates and a category with published > 0, unless the account may edit it or
 * change its state; its own access level and its category's level must both be granted.
 */
final class AccessExplanation
{
    public const PASS    = 'pass';
    public const FAIL    = 'fail';
    public const UNKNOWN = 'unknown';
    public const INFO    = 'info';

    public const ACTIONS  = ['view', 'edit', 'publish'];
    public const TYPES    = ['article', 'category'];
    public const CONTEXTS = ['site', 'administrator'];

    private const PUBLISHED = 1;
    private const ARCHIVED  = 2;

    /**
     * Contexts each action can be checked in, by item type (Joomla edits categories only in the administrator).
     *
     * @return list<string>
     */
    public static function contexts(string $type, string $action): array
    {
        if ($action === 'view') {
            return ['site'];
        }
        return $type === 'category' ? ['administrator'] : ['administrator', 'site'];
    }

    /** @throws UserFacingException for any value outside the supported choices */
    public static function validate(string $type, string $action, string $context): void
    {
        if (!\in_array($type, self::TYPES, true) || !\in_array($action, self::ACTIONS, true)
            || !\in_array($context, self::contexts($type, $action), true)) {
            throw new UserFacingException('COM_USERTOOLS_EXPLAIN_ERROR_CHOICE');
        }
    }

    /**
     * @param array{
     *   account: array{id: int, blocked: bool, levels: list<int>},
 *   guest_levels: list<int>,
     *   item: array{type: string, state: int, access: int, publish_up: ?string, publish_down: ?string,
     *     author: int, checked_out: int, category: ?array{published: int, access: int, title: string},
     *     ancestors: list<array{published: int, title: string}>},
     *   levels: array<int, string>,
     *   can: array{login_site: bool, login_admin: bool, manage: bool, edit: bool, edit_own: bool, edit_state: bool},
     *   denials: list<array{action: string, asset: string, group: string}>|null,
     *   workflow: null|array{stage: string, transitions: list<array{title: string, allowed: bool, publishes: bool}>},
     *   menus: list<array{title: string, access: int, published: int}>,
     *   now: string
     * } $facts
     * @return array{verdict: string, checks: list<array{key: string, status: string, text: string, args: list<string|int>}>,
     *   menus: list<array{title: string, status: string, text: string, args: list<string|int>}>, notes: list<string>}
     */
    public static function explain(string $action, string $context, array $facts): array
    {
        self::validate($facts['item']['type'], $action, $context);
        $checks = [self::account($facts)];
        $checks[] = self::login($context, $facts);
        // An account that cannot sign in still browses the site as a visitor: evaluate what a visitor sees.
        $guest = $action === 'view' && ($facts['account']['blocked'] || !$facts['can']['login_site']);
        if ($guest) {
            $checks = array_map(static fn (array $c): array => $c['status'] === self::FAIL ? ['status' => self::INFO] + $c : $c, $checks);
            $checks[] = self::check('guest', self::INFO, 'COM_USERTOOLS_EXPLAIN_AS_GUEST');
            $facts['account']['levels'] = $facts['guest_levels'];
            $facts['can'] = ['edit' => false, 'edit_state' => false] + $facts['can'];
        }
        if ($context === 'administrator') {
            $checks[] = $facts['can']['manage']
                ? self::check('manage', self::PASS, 'COM_USERTOOLS_EXPLAIN_MANAGE_PASS')
                : self::check('manage', self::FAIL, 'COM_USERTOOLS_EXPLAIN_MANAGE_FAIL');
        }
        $notes = ['COM_USERTOOLS_EXPLAIN_NOTE_PLUGINS'];
        $menus = [];
        if ($action === 'view') {
            array_push($checks, ...self::view($facts));
            $menus = self::menus($facts);
            $notes[] = 'COM_USERTOOLS_EXPLAIN_NOTE_LANGUAGE';
            $notes[] = $facts['item']['type'] === 'article' ? 'COM_USERTOOLS_EXPLAIN_NOTE_NOAUTH' : 'COM_USERTOOLS_EXPLAIN_NOTE_CATEGORY_PATH';
        } elseif ($action === 'edit') {
            $checks[] = self::edit($facts);
            $checks[] = self::checkedOut($facts);
            if ($context === 'site') {
                $notes[] = 'COM_USERTOOLS_EXPLAIN_NOTE_SITE_EDIT';
            }
        } else {
            array_push($checks, ...self::publish($facts));
            $checks[] = self::checkedOut($facts);
            if ($facts['workflow'] !== null) {
                $notes[] = 'COM_USERTOOLS_EXPLAIN_NOTE_WORKFLOW';
            }
        }
        $failed = array_filter($checks, static fn (array $c): bool => $c['status'] === self::FAIL);
        return ['verdict' => $failed ? 'no' : 'yes', 'checks' => $checks, 'menus' => $menus, 'notes' => $notes];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function account(array $facts): array
    {
        return $facts['account']['blocked']
            ? self::check('account', self::FAIL, 'COM_USERTOOLS_EXPLAIN_ACCOUNT_BLOCKED')
            : self::check('account', self::PASS, 'COM_USERTOOLS_EXPLAIN_ACCOUNT_ACTIVE');
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function login(string $context, array $facts): array
    {
        $allowed = $context === 'site' ? $facts['can']['login_site'] : $facts['can']['login_admin'];
        $prefix  = $context === 'site' ? 'COM_USERTOOLS_EXPLAIN_LOGIN_SITE_' : 'COM_USERTOOLS_EXPLAIN_LOGIN_ADMIN_';
        return self::check('login', $allowed ? self::PASS : self::FAIL, $prefix . ($allowed ? 'PASS' : 'FAIL'));
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<array{key: string, status: string, text: string, args: list<string|int>}>
     */
    private static function view(array $facts): array
    {
        $item   = $facts['item'];
        $levels = $facts['account']['levels'];
        $checks = [];
        if ($item['type'] === 'article') {
            // Joomla shows unpublished or out-of-date articles to accounts that may edit them or change their state.
            $privileged = $facts['can']['edit'] || $facts['can']['edit_state'];
            if (\in_array($item['state'], [self::PUBLISHED, self::ARCHIVED], true)) {
                $checks[] = self::check('state', self::PASS, $item['state'] === self::ARCHIVED ? 'COM_USERTOOLS_EXPLAIN_STATE_ARCHIVED' : 'COM_USERTOOLS_EXPLAIN_STATE_PUBLISHED');
            } else {
                $checks[] = $privileged
                    ? self::check('state', self::PASS, 'COM_USERTOOLS_EXPLAIN_STATE_EDITOR', [self::stateKey($item['state'])])
                    : self::check('state', self::FAIL, 'COM_USERTOOLS_EXPLAIN_STATE_FAIL', [self::stateKey($item['state'])]);
            }
            $checks[] = self::dates($item, $facts['now'], $privileged);
            $category = $item['category'];
            if ($category === null) {
                $checks[] = self::check('category', self::FAIL, 'COM_USERTOOLS_EXPLAIN_CATEGORY_MISSING');
            } else {
                $checks[] = $category['published'] > 0
                    ? self::check('category', self::PASS, 'COM_USERTOOLS_EXPLAIN_CATEGORY_PUBLISHED', [$category['title']])
                    : self::check('category', self::FAIL, 'COM_USERTOOLS_EXPLAIN_CATEGORY_UNPUBLISHED', [$category['title']]);
            }
            $checks[] = self::level('access', $item['access'], $levels, $facts['levels'], 'ARTICLE');
            if ($category !== null) {
                $checks[] = self::level('category_access', $category['access'], $levels, $facts['levels'], 'CATEGORY');
            }
            return $checks;
        }
        // A category page needs the category and every parent category published.
        $checks[] = $item['state'] === self::PUBLISHED
            ? self::check('state', self::PASS, 'COM_USERTOOLS_EXPLAIN_STATE_PUBLISHED')
            : self::check('state', self::FAIL, 'COM_USERTOOLS_EXPLAIN_STATE_FAIL', [self::stateKey($item['state'])]);
        $hidden = array_values(array_filter($item['ancestors'], static fn (array $a): bool => $a['published'] !== self::PUBLISHED));
        $checks[] = $hidden
            ? self::check('parents', self::FAIL, 'COM_USERTOOLS_EXPLAIN_PARENTS_FAIL', [implode(', ', array_column($hidden, 'title'))])
            : self::check('parents', self::PASS, 'COM_USERTOOLS_EXPLAIN_PARENTS_PASS');
        $checks[] = self::level('access', $item['access'], $levels, $facts['levels'], 'CATEGORY_SELF');
        return $checks;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function dates(array $item, string $now, bool $privileged): array
    {
        $up   = self::date($item['publish_up']);
        $down = self::date($item['publish_down']);
        if ($privileged && (($up !== null && $up > $now) || ($down !== null && $down < $now))) {
            return self::check('dates', self::PASS, 'COM_USERTOOLS_EXPLAIN_DATES_EDITOR');
        }
        if ($up !== null && $up > $now) {
            return self::check('dates', self::FAIL, 'COM_USERTOOLS_EXPLAIN_DATES_FUTURE', [$up]);
        }
        if ($down !== null && $down < $now) {
            return self::check('dates', self::FAIL, 'COM_USERTOOLS_EXPLAIN_DATES_EXPIRED', [$down]);
        }
        return self::check('dates', self::PASS, 'COM_USERTOOLS_EXPLAIN_DATES_PASS');
    }

    /**
     * @param list<int>          $granted
     * @param array<int, string> $titles
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function level(string $key, int $level, array $granted, array $titles, string $what): array
    {
        $title = $titles[$level] ?? '#' . $level;
        return \in_array($level, $granted, true)
            ? self::check($key, self::PASS, 'COM_USERTOOLS_EXPLAIN_LEVEL_' . $what . '_PASS', [$title])
            : self::check($key, self::FAIL, 'COM_USERTOOLS_EXPLAIN_LEVEL_' . $what . '_FAIL', [$title]);
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function edit(array $facts): array
    {
        $can = $facts['can'];
        if ($can['edit']) {
            return self::check('permission', self::PASS, 'COM_USERTOOLS_EXPLAIN_EDIT_PASS');
        }
        if ($can['edit_own']) {
            return $facts['item']['author'] === $facts['account']['id']
                ? self::check('permission', self::PASS, 'COM_USERTOOLS_EXPLAIN_EDIT_OWN_PASS')
                : self::check('permission', self::FAIL, 'COM_USERTOOLS_EXPLAIN_EDIT_OWN_FAIL');
        }
        return self::check('permission', self::FAIL, 'COM_USERTOOLS_EXPLAIN_EDIT_FAIL');
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<array{key: string, status: string, text: string, args: list<string|int>}>
     */
    private static function publish(array $facts): array
    {
        $checks = [];
        if ($facts['item']['state'] === self::PUBLISHED) {
            $checks[] = self::check('current', self::INFO, 'COM_USERTOOLS_EXPLAIN_PUBLISH_ALREADY');
        }
        $workflow = $facts['workflow'];
        if ($workflow === null) {
            $checks[] = $facts['can']['edit_state']
                ? self::check('permission', self::PASS, 'COM_USERTOOLS_EXPLAIN_PUBLISH_PASS')
                : self::check('permission', self::FAIL, 'COM_USERTOOLS_EXPLAIN_PUBLISH_FAIL');
            return $checks;
        }
        // With workflows, publishing is a transition the account must be allowed to execute; core.edit.state is not enough.
        $allowed    = array_values(array_filter($workflow['transitions'], static fn (array $t): bool => $t['allowed']));
        $publishing = array_values(array_filter($allowed, static fn (array $t): bool => $t['publishes']));
        if ($publishing) {
            $checks[] = self::check('permission', self::PASS, 'COM_USERTOOLS_EXPLAIN_TRANSITION_PASS', [$workflow['stage'], implode(', ', array_column($publishing, 'title'))]);
        } elseif ($allowed) {
            $checks[] = self::check('permission', self::FAIL, 'COM_USERTOOLS_EXPLAIN_TRANSITION_OTHER', [$workflow['stage'], implode(', ', array_column($allowed, 'title'))]);
        } else {
            $checks[] = self::check('permission', self::FAIL, $workflow['transitions'] ? 'COM_USERTOOLS_EXPLAIN_TRANSITION_DENIED' : 'COM_USERTOOLS_EXPLAIN_TRANSITION_NONE', [$workflow['stage']]);
        }
        return $checks;
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function checkedOut(array $facts): array
    {
        $by = $facts['item']['checked_out'];
        return $by > 0 && $by !== $facts['account']['id']
            ? self::check('checked_out', self::FAIL, 'COM_USERTOOLS_EXPLAIN_CHECKED_OUT')
            : self::check('checked_out', self::PASS, 'COM_USERTOOLS_EXPLAIN_NOT_CHECKED_OUT');
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<array{title: string, status: string, text: string, args: list<string|int>}>
     */
    private static function menus(array $facts): array
    {
        $rows = [];
        foreach ($facts['menus'] as $menu) {
            $title = $facts['levels'][$menu['access']] ?? '#' . $menu['access'];
            if ($menu['published'] !== self::PUBLISHED) {
                $rows[] = ['title' => $menu['title'], 'status' => self::FAIL, 'text' => 'COM_USERTOOLS_EXPLAIN_MENU_UNPUBLISHED', 'args' => []];
            } elseif (!\in_array($menu['access'], $facts['account']['levels'], true)) {
                $rows[] = ['title' => $menu['title'], 'status' => self::FAIL, 'text' => 'COM_USERTOOLS_EXPLAIN_MENU_LEVEL_FAIL', 'args' => [$title]];
            } else {
                $rows[] = ['title' => $menu['title'], 'status' => self::PASS, 'text' => 'COM_USERTOOLS_EXPLAIN_MENU_PASS', 'args' => [$title]];
            }
        }
        return $rows;
    }

    private static function stateKey(int $state): string
    {
        return match ($state) {
            0       => 'JUNPUBLISHED',
            -2      => 'JTRASHED',
            self::ARCHIVED => 'JARCHIVED',
            default => 'JPUBLISHED',
        };
    }

    private static function date(?string $value): ?string
    {
        $value = $value === null ? '' : substr($value, 0, 19);
        return $value === '' || str_starts_with($value, '0000-00-00') ? null : $value;
    }

    /**
     * @param list<string|int> $args
     * @return array{key: string, status: string, text: string, args: list<string|int>}
     */
    private static function check(string $key, string $status, string $text, array $args = []): array
    {
        return ['key' => $key, 'status' => $status, 'text' => $text, 'args' => $args];
    }
}
