<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Bounded, read-only queries for a single account. No credential or MFA payload is selected. */
final class AccountProfileReader
{
    public function __construct(private DatabaseInterface $db) {}

    public function mfaConfigured(int $id): bool
    {
        $db = $this->db;
        return (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__user_mfa'))
            ->where($db->quoteName('user_id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER)
            ->where($db->quoteName('method') . ' NOT IN (' . $db->quote('backupcodes') . ', ' . $db->quote('emergencycodes') . ')'))->loadResult() > 0;
    }

    /**
     * Counts all states, including the trash, within the operator's content access scope.
     * Joomla has an author filter for articles, but not for categories or contacts.
     *
     * @param callable(string): bool $canManage component management permission
     * @param list<int>|null $levels null only for a global Super User
     * @return list<array{kind: string, component: string, count: int, url: string}>
     */
    public function content(int $id, callable $canManage, ?array $levels): array
    {
        $db = $this->db;
        $result = [];
        foreach (['articles' => ['#__content', 'com_content'], 'contacts' => ['#__contact_details', 'com_contact']] as $kind => [$table, $component]) {
            if (!$canManage($component)) {
                continue;
            }
            $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName($table, 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
                ->where($db->quoteName('a.created_by') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            if ($levels !== null) {
                $query->whereIn($db->quoteName('a.access'), $levels ?: [0]);
                $query->whereIn($db->quoteName('c.access'), $levels ?: [0]);
            }
            $result[] = ['kind' => $kind, 'component' => $component, 'count' => (int) $db->setQuery($query)->loadResult(),
                'url' => $kind === 'articles' ? AccountProfile::articleUrl($id) : ''];
        }
        $query = $db->createQuery()->select([$db->quoteName('extension'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->from($db->quoteName('#__categories'))->where($db->quoteName('created_user_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)->where($db->quoteName('parent_id') . ' > 0')
            ->group($db->quoteName('extension'))->order($db->quoteName('extension'));
        if ($levels !== null) {
            $query->whereIn($db->quoteName('access'), $levels ?: [0]);
        }
        foreach ($db->setQuery($query)->loadAssocList() as $row) {
            $component = (string) $row['extension'];
            if ($canManage($component)) {
                $result[] = ['kind' => 'categories', 'component' => $component, 'count' => (int) $row['total'], 'url' => ''];
            }
        }
        return $result;
    }

    /**
     * Up to 50 recent matches, scanning at most 1000 indexed actor/item candidates.
     * The caller must check Joomla's global core.admin permission for com_actionlogs.
     *
     * @return list<array{id: int, date: string, actor: string, role: string, event: string, extension: string, url: string}>
     */
    public function logs(int $id): array
    {
        $db = $this->db;
        $result = [];
        $last = 0;
        for ($batch = 0; $batch < 10 && count($result) < 50; $batch++) {
            $query = $db->createQuery()->select($db->quoteName(['a.id', 'a.log_date', 'a.user_id', 'a.message', 'a.message_language_key', 'a.extension', 'u.username']))
                ->from($db->quoteName('#__action_logs', 'a'))
                ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'))
                ->where('(' . $db->quoteName('a.user_id') . ' = :actor OR ' . $db->quoteName('a.item_id') . ' = :object)')
                ->bind(':actor', $id, ParameterType::INTEGER)->bind(':object', $id, ParameterType::INTEGER)
                ->order($db->quoteName('a.id') . ' DESC');
            if ($last > 0) {
                $query->where($db->quoteName('a.id') . ' < :last')->bind(':last', $last, ParameterType::INTEGER);
            }
            $rows = $db->setQuery($query, 0, 100)->loadAssocList();
            foreach ($rows as $row) {
                $role = AccountProfile::logRole($id, (int) $row['user_id'], (string) $row['message']);
                if ($role !== '') {
                    $entry = (int) $row['id'];
                    $result[] = ['id' => $entry, 'date' => (string) $row['log_date'], 'actor' => (string) ($row['username'] ?? ''),
                        'role' => $role, 'event' => AccountProfile::eventKey((string) $row['message_language_key']),
                        'extension' => (string) $row['extension'], 'url' => 'index.php?' . http_build_query([
                            'option' => 'com_actionlogs', 'view' => 'actionlogs',
                            'filter' => ['search' => 'id:' . $entry, 'user' => '', 'extension' => '', 'dateRange' => '']])];
                }
                $last = (int) $row['id'];
                if (count($result) === 50) {
                    break;
                }
            }
            if (count($rows) < 100) {
                break;
            }
        }
        return $result;
    }
}
