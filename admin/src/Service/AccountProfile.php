<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Pure presentation rules for a read-only account profile. */
final class AccountProfile
{
    /**
     * Joomla's actual authorised levels are authoritative, including recovery-mode accounts.
     * AccessMatrix explains each grant through directly assigned groups and their ancestors.
     *
     * @param array<int, object> $groups
     * @param list<array{id: int, title: string, rules: list<int>}> $levels
     * @param list<int> $authorised
     * @return list<array{id: int, title: string, sources: list<array{state: string, group: int, via: int}>}>
     */
    public static function levels(array $groups, array $levels, UserRecord $account, array $authorised): array
    {
        $matrix = AccessMatrix::build($groups, $levels);
        $members = array_fill_keys($account->groups, true);
        $allowed = array_fill_keys($authorised, true);
        $result = [];
        foreach ($levels as $level) {
            $id = $level['id'];
            if (!isset($allowed[$id])) {
                continue;
            }
            $sources = [];
            if ($id === AccessMatrix::PUBLIC_LEVEL) {
                $sources[] = ['state' => 'public', 'group' => 0, 'via' => 0];
            } else {
                if (in_array(-$account->id, $level['rules'], true)) {
                    $sources[] = ['state' => 'account', 'group' => 0, 'via' => 0];
                }
                foreach ($matrix['rows'] as $row) {
                    $cell = $row['cells'][$id];
                    if (isset($members[$row['id']]) && $cell['state'] !== 'none') {
                        $sources[] = ['state' => $cell['state'], 'group' => $row['id'], 'via' => $cell['via']];
                    }
                }
                if (!$sources) {
                    $sources[] = ['state' => 'joomla', 'group' => 0, 'via' => 0];
                }
            }
            $result[] = ['id' => $id, 'title' => $level['title'], 'sources' => $sources];
        }
        return $result;
    }

    /**
     * @param array<string, bool> $permissions
     * @return array<string, bool>
     */
    public static function actions(UserRecord $account, Operator $operator, array $permissions): array
    {
        $safe = !(new Exclusions(false))->protects($account, $operator);
        return [
            'ungroup' => $safe && (bool) $account->groups && ($permissions['usertools.ungroup'] ?? false),
            'block' => $safe && $account->block === 0 && ($permissions['usertools.inactive'] ?? false),
            'anonymize' => $safe && ($permissions['usertools.anonymize'] ?? false),
        ];
    }

    /** The fields reviewed before a single-account block; never includes passwords or MFA data. */
    public static function fingerprint(object $account): string
    {
        $groups = array_values(array_unique(array_map('intval', (array) $account->groups)));
        sort($groups);
        return hash('sha256', json_encode([(int) $account->id, (string) $account->username,
            (string) $account->name, (string) $account->email, (int) $account->block,
            (string) $account->lastvisitDate, (string) $account->registerDate, $groups], JSON_THROW_ON_ERROR));
    }

    /**
     * Match the account as actor and/or as the user object, not an article/group with the same ID.
     * Only the role is exposed; arbitrary third-party message payloads never reach the profile.
     */
    public static function logRole(int $account, int $actor, string $message): string
    {
        $data = json_decode($message, true);
        $object = false;
        if (is_array($data)) {
            $type = $data['type'] ?? '';
            if (in_array($type, ['COM_USERTOOLS_ACTION_LOG_TYPE_USER', 'PLG_ACTIONLOG_JOOMLA_TYPE_USER'], true)) {
                $object = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) === $account;
            }
            if (is_string($data['itemlink'] ?? null)) {
                $query = parse_url(html_entity_decode($data['itemlink'], ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY);
                $params = [];
                parse_str(is_string($query) ? $query : '', $params);
                if (($params['option'] ?? '') === 'com_users' && ($params['task'] ?? '') === 'user.edit') {
                    $object = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT) === $account;
                }
            }
        }
        return $actor === $account ? ($object ? 'both' : 'actor') : ($object ? 'object' : '');
    }

    public static function eventKey(string $key): string
    {
        return match ($key) {
            'COM_USERTOOLS_ACTION_LOG_BLOCKED', 'COM_USERTOOLS_ACTION_LOG_TASK_BLOCKED' => 'COM_USERTOOLS_PROFILE_EVENT_BLOCKED',
            'COM_USERTOOLS_ACTION_LOG_DELETED' => 'COM_USERTOOLS_PROFILE_EVENT_DELETED',
            'COM_USERTOOLS_ACTION_LOG_ANONYMIZED' => 'COM_USERTOOLS_PROFILE_EVENT_ANONYMIZED',
            'COM_USERTOOLS_ACTION_LOG_CLEANUP_COMPLETED' => 'COM_USERTOOLS_PROFILE_EVENT_CLEANUP',
            'COM_USERTOOLS_ACTION_LOG_GROUP_ADDED', 'COM_USERTOOLS_ACTION_LOG_UNGROUPED',
                'COM_USERTOOLS_ACTION_LOG_UNGROUPED_DEFAULT' => 'COM_USERTOOLS_PROFILE_EVENT_GROUPS',
            'COM_USERTOOLS_ACTION_LOG_CREATED_EMAIL', 'COM_USERTOOLS_ACTION_LOG_CREATED_PASSWORD' => 'COM_USERTOOLS_PROFILE_EVENT_CREATED',
            default => 'COM_USERTOOLS_PROFILE_EVENT_RECORDED',
        };
    }

    public static function articleUrl(int $account): string
    {
        return 'index.php?' . http_build_query(['option' => 'com_content', 'view' => 'articles',
            'filter' => ['author_id' => [$account], 'published' => '*', 'search' => '', 'category_id' => '',
                'access' => '', 'language' => '', 'tag' => '', 'level' => '', 'featured' => '', 'stage' => '']]);
    }
}
