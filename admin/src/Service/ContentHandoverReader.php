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

/** Read-only, bounded queries for the handover assistant: the articles an account authored. */
final class ContentHandoverReader
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Articles authored by the account in every state, within the operator's viewing levels (null only
     * for a global Super User), oldest first, up to $limit.
     *
     * @param  list<int>|null $levels
     * @return list<array{id: int, title: string, state: int, catid: int, category: string, alias: string, modified: string, checked_out: int, created_by: int}>
     */
    public function articles(int $source, ?array $levels, int $limit): array
    {
        $db = $this->db;
        $query = $this->base($source, $levels)
            ->select([$db->quoteName('a.id'), $db->quoteName('a.title'), $db->quoteName('a.state'), $db->quoteName('a.catid'),
                $db->quoteName('c.title', 'category'), $db->quoteName('a.created_by_alias', 'alias'), $db->quoteName('a.modified'),
                $db->quoteName('a.checked_out'), $db->quoteName('a.created_by')])
            ->order($db->quoteName('a.id'));
        $rows = [];
        foreach ($db->setQuery($query, 0, $limit)->loadAssocList() as $row) {
            $rows[] = ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'state' => (int) $row['state'], 'catid' => (int) $row['catid'],
                'category' => (string) ($row['category'] ?? ''), 'alias' => (string) ($row['alias'] ?? ''), 'modified' => (string) ($row['modified'] ?? ''),
                'checked_out' => (int) ($row['checked_out'] ?? 0), 'created_by' => (int) $row['created_by']];
        }
        return $rows;
    }

    /** @param list<int>|null $levels */
    public function count(int $source, ?array $levels): int
    {
        return (int) $this->db->setQuery($this->base($source, $levels)->select('COUNT(*)'))->loadResult();
    }

    /**
     * IDs of every article the account authors now, without the viewing-level filter, for the closing check.
     *
     * @return list<int>
     */
    public function authored(int $source): array
    {
        $db = $this->db;
        return array_map('intval', $db->setQuery($db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__content'))
            ->where($db->quoteName('created_by') . ' = :id')->bind(':id', $source, ParameterType::INTEGER)->order($db->quoteName('id')))->loadColumn());
    }

    /** @param list<int>|null $levels */
    private function base(int $source, ?array $levels): \Joomla\Database\QueryInterface
    {
        $db = $this->db;
        $query = $db->createQuery()->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.created_by') . ' = :source')->bind(':source', $source, ParameterType::INTEGER);
        if ($levels !== null) {
            $query->whereIn($db->quoteName('a.access'), $levels ?: [0]);
            $query->whereIn($db->quoteName('c.access'), $levels ?: [0]);
        }
        return $query;
    }
}
