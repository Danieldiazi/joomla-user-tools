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
use Joomla\Database\QueryInterface;

/**
 * Reads, per kind of item, how many items were last modified in each year, and the items of one year.
 * Items in the trash are left out; archived ones are included. Read only.
 */
final class ContentAgeReader
{
    /** Trashed state in Joomla's tables. */
    private const TRASHED = -2;

    /**
     * Kinds of item with a creation and a modification date: table, columns, the component that
     * manages them and the screens to open. A new kind is declared here and nowhere else. Modules,
     * menu items and plugins are left out on purpose: Joomla stores no modification date for them.
     * `category` is the column joined to #__categories (the parent, for categories themselves) and
     * `scope` limits the rows of a shared table.
     */
    public const KINDS = [
        'articles' => ['table' => '#__content', 'title' => 'title', 'state' => 'state', 'category' => 'catid', 'created' => 'created', 'modified' => 'modified',
            'author' => 'created_by', 'label' => 'COM_USERTOOLS_USAGE_ARTICLES', 'asset' => 'com_content',
            'item' => 'com_content.article.{id}', 'edit' => 'index.php?option=com_content&task=article.edit&id={id}'],
        'categories' => ['table' => '#__categories', 'title' => 'title', 'state' => 'published', 'category' => 'parent_id', 'created' => 'created_time', 'modified' => 'modified_time',
            'author' => 'created_user_id', 'scope' => ['extension', 'com_content'], 'label' => 'COM_USERTOOLS_AGE_KIND_CATEGORIES', 'categoryLabel' => 'COM_USERTOOLS_AGE_PARENT_CATEGORY', 'asset' => 'com_content',
            'item' => 'com_content.category.{id}', 'edit' => 'index.php?option=com_categories&task=category.edit&id={id}&extension=com_content'],
        'contacts' => ['table' => '#__contact_details', 'title' => 'name', 'state' => 'published', 'category' => 'catid', 'created' => 'created', 'modified' => 'modified',
            'author' => 'created_by', 'label' => 'COM_USERTOOLS_USAGE_CONTACTS', 'asset' => 'com_contact',
            'item' => 'com_contact.contact.{id}', 'edit' => 'index.php?option=com_contact&task=contact.edit&id={id}'],
        'newsfeeds' => ['table' => '#__newsfeeds', 'title' => 'name', 'state' => 'published', 'category' => 'catid', 'created' => 'created', 'modified' => 'modified',
            'author' => 'created_by', 'label' => 'COM_USERTOOLS_USAGE_NEWSFEEDS', 'asset' => 'com_newsfeeds',
            'item' => 'com_newsfeeds.newsfeed.{id}', 'edit' => 'index.php?option=com_newsfeeds&task=newsfeed.edit&id={id}'],
    ];

    /** @param list<int>|null $viewLevels allowed viewing levels; null only for a global Super User */
    public function __construct(private DatabaseInterface $db, private ?array $viewLevels = null)
    {
    }

    /** @return list<string> kinds whose table exists */
    public function kinds(): array
    {
        $prefix   = $this->db->getPrefix();
        $existing = array_flip(array_map('strval', $this->db->getTableList()));
        return array_values(array_filter(array_keys(self::KINDS), fn (string $kind) => isset($existing[str_replace('#__', $prefix, self::KINDS[$kind]['table'])])));
    }

    /**
     * Items per year of last modification.
     *
     * @return array<int, int>
     */
    public function counts(string $kind, int $level = 0): array
    {
        $db    = $this->db;
        $year  = 'EXTRACT(YEAR FROM ' . $this->dateColumn($kind) . ')';
        $query = $this->base($kind, $level)
            ->select([$year . ' AS ' . $db->quoteName('yr'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->group($year);
        $counts = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $counts[(int) $row->yr] = (int) $row->total;
        }
        return $counts;
    }

    /** Number of items last modified in a year. */
    public function count(string $kind, int $year, int $level = 0): int
    {
        $query = $this->inYear($this->base($kind, $level), $kind, $year)->select('COUNT(*)');
        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Items last modified in a year, oldest first.
     *
     * @return list<array{id: int, title: string, category: string, level: string, access: int, author: string, created_by: int, state: int, created: ?string, modified: ?string}>
     */
    public function items(string $kind, int $year, int $level = 0, int $offset = 0, int $limit = 0): array
    {
        $db     = $this->db;
        $source = self::KINDS[$kind];
        $query  = $this->inYear($this->base($kind, $level), $kind, $year)
            ->select([
                $db->quoteName('a.id'), $db->quoteName('a.' . $source['author'], 'created_by'), $db->quoteName('a.' . $source['title'], 'title'), $db->quoteName('a.access'),
                $db->quoteName('a.' . $source['created'], 'created'), $db->quoteName('a.' . $source['modified'], 'modified'),
                $db->quoteName('a.' . $source['state'], 'state'), $db->quoteName('c.title', 'category'), $db->quoteName('c.parent_id', 'category_parent'),
                $db->quoteName('u.name', 'author'), $db->quoteName('l.title', 'level'),
            ])
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.' . $source['author']))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'l'), $db->quoteName('l.id') . ' = ' . $db->quoteName('a.access'))
            ->order([$this->dateColumn($kind), $db->quoteName('a.id')]);
        $items = [];
        foreach ($db->setQuery($query, $offset, $limit)->loadObjectList() as $row) {
            $items[] = [
                'id'       => (int) $row->id,
                'title'    => (string) $row->title,
                // A top-level category hangs from Joomla's hidden root: it has no parent to show.
                'category' => (int) ($row->category_parent ?? 0) === 0 ? '' : (string) ($row->category ?? ''),
                'level'    => (string) ($row->level ?? ''),
                'access'   => (int) $row->access,
                'author'   => (string) ($row->author ?? ''),
                'created_by' => (int) $row->created_by,
                'state'    => (int) $row->state,
                'created'  => $row->created !== null ? substr((string) $row->created, 0, 19) : null,
                'modified' => $row->modified !== null ? substr((string) $row->modified, 0, 19) : null,
            ];
        }
        return $items;
    }

    private function dateColumn(string $kind): string
    {
        $db     = $this->db;
        $source = self::KINDS[$kind];
        return 'COALESCE(' . $db->quoteName('a.' . $source['modified']) . ', ' . $db->quoteName('a.' . $source['created']) . ')';
    }

    private function base(string $kind, int $level): QueryInterface
    {
        if (!isset(self::KINDS[$kind])) {
            throw new UserFacingException('COM_USERTOOLS_AGE_UNKNOWN_KIND');
        }
        $db      = $this->db;
        $source  = self::KINDS[$kind];
        $trashed = self::TRASHED;
        $query   = $db->createQuery()
            ->from($db->quoteName($source['table'], 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.' . $source['category']))
            ->where($db->quoteName('a.' . $source['state']) . ' <> :trashed')
            ->bind(':trashed', $trashed, ParameterType::INTEGER);
        if (isset($source['scope'])) {
            [$column, $value] = $source['scope'];
            $query->where($db->quoteName('a.' . $column) . ' = :scope')->bind(':scope', $value);
        }
        // Match Joomla's administrator article list: component access does not grant access to
        // every article or category. Keep the same scope for counts, pages and CSV exports.
        if ($this->viewLevels !== null) {
            $allowed = array_values(array_unique(array_filter(array_map('intval', $this->viewLevels), fn (int $id): bool => $id > 0)));
            if (!$allowed) {
                $query->where('1 = 0');
            } else {
                $query->whereIn($db->quoteName('a.access'), $allowed);
                $query->whereIn($db->quoteName('c.access'), $allowed);
            }
        }
        if ($level > 0) {
            $query->where($db->quoteName('a.access') . ' = :level')->bind(':level', $level, ParameterType::INTEGER);
        }
        return $query;
    }

    private function inYear(QueryInterface $query, string $kind, int $year): QueryInterface
    {
        [$start, $end] = ContentAge::bounds($year);
        return $query->where($this->dateColumn($kind) . ' >= :start')->where($this->dateColumn($kind) . ' < :end')
            ->bind(':start', $start)->bind(':end', $end);
    }
}
