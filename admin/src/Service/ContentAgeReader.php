<?php

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
     * Kinds of item: table, columns and the screens to open. Only articles for now; more kinds with a
     * modification date (categories, contacts, news feeds) can be added here.
     */
    public const KINDS = [
        'articles' => ['table' => '#__content', 'state' => 'state', 'category' => 'catid', 'label' => 'COM_USERTOOLS_USAGE_ARTICLES', 'asset' => 'com_content',
            'item' => 'com_content.article.{id}', 'edit' => 'index.php?option=com_content&task=article.edit&id={id}'],
    ];

    public function __construct(private DatabaseInterface $db)
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
        $year  = 'EXTRACT(YEAR FROM ' . $this->dateColumn() . ')';
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
        $query = $this->inYear($this->base($kind, $level), $year)->select('COUNT(*)');
        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Items last modified in a year, oldest first.
     *
     * @return list<array{id: int, title: string, category: string, level: string, access: int, author: string, state: int, created: ?string, modified: ?string}>
     */
    public function items(string $kind, int $year, int $level = 0, int $offset = 0, int $limit = 0): array
    {
        $db     = $this->db;
        $source = self::KINDS[$kind];
        $query  = $this->inYear($this->base($kind, $level), $year)
            ->select([
                $db->quoteName('a.id'), $db->quoteName('a.title'), $db->quoteName('a.access'), $db->quoteName('a.created'), $db->quoteName('a.modified'),
                $db->quoteName('a.' . $source['state'], 'state'), $db->quoteName('c.title', 'category'), $db->quoteName('u.name', 'author'),
                $db->quoteName('l.title', 'level'),
            ])
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.' . $source['category']))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'l'), $db->quoteName('l.id') . ' = ' . $db->quoteName('a.access'))
            ->order([$this->dateColumn(), $db->quoteName('a.id')]);
        $items = [];
        foreach ($db->setQuery($query, $offset, $limit)->loadObjectList() as $row) {
            $items[] = [
                'id'       => (int) $row->id,
                'title'    => (string) $row->title,
                'category' => (string) ($row->category ?? ''),
                'level'    => (string) ($row->level ?? ''),
                'access'   => (int) $row->access,
                'author'   => (string) ($row->author ?? ''),
                'state'    => (int) $row->state,
                'created'  => $row->created !== null ? substr((string) $row->created, 0, 19) : null,
                'modified' => $row->modified !== null ? substr((string) $row->modified, 0, 19) : null,
            ];
        }
        return $items;
    }

    private function dateColumn(): string
    {
        $db = $this->db;
        return 'COALESCE(' . $db->quoteName('a.modified') . ', ' . $db->quoteName('a.created') . ')';
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
            ->where($db->quoteName('a.' . $source['state']) . ' <> :trashed')
            ->bind(':trashed', $trashed, ParameterType::INTEGER);
        if ($level > 0) {
            $query->where($db->quoteName('a.access') . ' = :level')->bind(':level', $level, ParameterType::INTEGER);
        }
        return $query;
    }

    private function inYear(QueryInterface $query, int $year): QueryInterface
    {
        [$start, $end] = ContentAge::bounds($year);
        return $query->where($this->dateColumn() . ' >= :start')->where($this->dateColumn() . ' < :end')
            ->bind(':start', $start)->bind(':end', $end);
    }
}
