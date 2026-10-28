<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Counts, per viewing access level, the items of each kind that use it. Joomla's own content is read
 * with its meaning (state, client, component); any other table with an "access" column is counted as
 * a whole, the same tables Joomla checks before deleting a level (Users LevelModel::delete()).
 * Items in the trash are not counted. Read only.
 */
final class AccessUsageReader
{
    /** Trashed state in every Joomla table that has one. */
    private const TRASHED = -2;

    /**
     * Joomla's content with an access level: table, state column, extra conditions, grouping column
     * (one row per component or field context) and the filtered list to open.
     */
    private const CORE = [
        ['key' => 'articles', 'table' => '#__content', 'state' => 'state', 'label' => 'COM_USERTOOLS_USAGE_ARTICLES', 'asset' => 'com_content',
            'link' => 'index.php?option=com_content&view=articles&filter[access]={access}'],
        ['key' => 'categories', 'table' => '#__categories', 'state' => 'published', 'group' => 'extension', 'notRoot' => true, 'label' => 'COM_USERTOOLS_USAGE_CATEGORIES',
            'link' => 'index.php?option=com_categories&view=categories&extension={group}&filter[access]={access}'],
        ['key' => 'fields', 'table' => '#__fields', 'state' => 'state', 'group' => 'context', 'label' => 'COM_USERTOOLS_USAGE_FIELDS',
            'link' => 'index.php?option=com_fields&view=fields&context={group}&filter[access]={access}'],
        ['key' => 'field_groups', 'table' => '#__fields_groups', 'state' => 'state', 'group' => 'context', 'label' => 'COM_USERTOOLS_USAGE_FIELD_GROUPS',
            'link' => 'index.php?option=com_fields&view=groups&context={group}&filter[access]={access}'],
        ['key' => 'menu', 'table' => '#__menu', 'state' => 'published', 'client' => 0, 'notRoot' => true, 'label' => 'COM_USERTOOLS_USAGE_MENU_ITEMS', 'asset' => 'com_menus',
            'link' => 'index.php?option=com_menus&view=items&client_id=0&menutype=&filter[access]={access}'],
        ['key' => 'modules', 'table' => '#__modules', 'state' => 'published', 'client' => 0, 'label' => 'COM_USERTOOLS_USAGE_MODULES_SITE', 'asset' => 'com_modules',
            'link' => 'index.php?option=com_modules&view=modules&client_id=0&filter[access]={access}'],
        ['key' => 'modules_admin', 'table' => '#__modules', 'state' => 'published', 'client' => 1, 'label' => 'COM_USERTOOLS_USAGE_MODULES_ADMIN', 'asset' => 'com_modules',
            'link' => 'index.php?option=com_modules&view=modules&client_id=1&filter[access]={access}'],
        ['key' => 'plugins', 'table' => '#__extensions', 'type' => 'plugin', 'label' => 'COM_USERTOOLS_USAGE_PLUGINS', 'asset' => 'com_plugins',
            'link' => 'index.php?option=com_plugins&view=plugins&filter[access]={access}'],
        ['key' => 'contacts', 'table' => '#__contact_details', 'state' => 'published', 'label' => 'COM_USERTOOLS_USAGE_CONTACTS', 'asset' => 'com_contact',
            'link' => 'index.php?option=com_contact&view=contacts&filter[access]={access}'],
        ['key' => 'newsfeeds', 'table' => '#__newsfeeds', 'state' => 'published', 'label' => 'COM_USERTOOLS_USAGE_NEWSFEEDS', 'asset' => 'com_newsfeeds',
            'link' => 'index.php?option=com_newsfeeds&view=newsfeeds&filter[access]={access}'],
        ['key' => 'tags', 'table' => '#__tags', 'state' => 'published', 'notRoot' => true, 'label' => 'COM_USERTOOLS_USAGE_TAGS', 'asset' => 'com_tags',
            'link' => 'index.php?option=com_tags&view=tags&filter[access]={access}'],
        ['key' => 'languages', 'table' => '#__languages', 'state' => 'published', 'label' => 'COM_USERTOOLS_USAGE_LANGUAGES', 'asset' => 'com_languages',
            'link' => 'index.php?option=com_languages&view=languages&filter[access]={access}'],
        ['key' => 'tours', 'table' => '#__guidedtours', 'state' => 'published', 'label' => 'COM_USERTOOLS_USAGE_TOURS', 'asset' => 'com_guidedtours',
            'link' => 'index.php?option=com_guidedtours&view=tours&filter[access]={access}'],
    ];

    /** Tables with an access column that are not content: the Smart Search index repeats the items above. */
    private const IGNORED = ['#__finder_links', '#__finder_taxonomy', '#__viewlevels'];

    /**
     * @param callable(string, string): string $label translates a language key with one argument (component or context name)
     * @param callable(string): string         $component readable name of a component ("com_content" → "Articles")
     * @param callable(string): bool           $canOpen   whether the current user may open that component's lists
     */
    public function __construct(private DatabaseInterface $db, private $label, private $component, private $canOpen)
    {
    }

    /**
     * @return list<array{key: string, label: string, table: string, link: string, counts: array<int, int>}>
     */
    public function sources(): array
    {
        $db       = $this->db;
        $prefix   = $db->getPrefix();
        $existing = [];
        foreach ($db->getTableList() as $table) {
            $existing[(string) $table] = true;
        }
        $sources = [];
        $handled = [];
        foreach (self::CORE as $source) {
            $table = str_replace('#__', $prefix, $source['table']);
            $handled[$table] = true;
            if (!isset($existing[$table])) {
                continue;
            }
            foreach ($this->coreCounts($source) as $group => $counts) {
                $asset  = (string) ($source['asset'] ?? $group);
                $name   = $group === '' ? '' : $this->groupName((string) $group);
                $sources[] = [
                    'key'    => $source['key'] . ($group === '' ? '' : ':' . $group),
                    'label'  => ($this->label)($source['label'], $name),
                    'table'  => $source['table'],
                    'link'   => ($this->canOpen)(explode('.', $asset)[0]) ? str_replace('{group}', rawurlencode((string) $group), $source['link']) : '',
                    'counts' => $counts,
                ];
            }
        }
        foreach (self::IGNORED as $table) {
            $handled[str_replace('#__', $prefix, $table)] = true;
        }
        // Other extensions (attachments, galleries…): any table with an "access" column, counted as a whole.
        foreach (array_keys($existing) as $table) {
            if (isset($handled[$table]) || !str_starts_with($table, $prefix)) {
                continue;
            }
            try {
                $columns = $db->getTableColumns($table);
            } catch (\Throwable) {
                continue;
            }
            if (!isset($columns['access'])) {
                continue;
            }
            $short     = '#__' . substr($table, \strlen($prefix));
            $sources[] = ['key' => 'table:' . $short, 'label' => ($this->label)('COM_USERTOOLS_USAGE_OTHER_TABLE', $short), 'table' => $short,
                'link' => '', 'counts' => $this->counts($db->createQuery()->from($db->quoteName($table)))];
        }
        return $sources;
    }

    /**
     * @param  array<string, mixed> $source
     * @return array<string, array<int, int>> counts per group ('' when the source is not grouped)
     */
    private function coreCounts(array $source): array
    {
        $db    = $this->db;
        $query = $db->createQuery()->from($db->quoteName($source['table']));
        if (isset($source['state'])) {
            $trashed = self::TRASHED;
            $query->where($db->quoteName($source['state']) . ' <> :trashed')->bind(':trashed', $trashed, ParameterType::INTEGER);
        }
        if (isset($source['client'])) {
            $client = (int) $source['client'];
            $query->where($db->quoteName('client_id') . ' = :client')->bind(':client', $client, ParameterType::INTEGER);
        }
        if (isset($source['type'])) {
            $type = (string) $source['type'];
            $query->where($db->quoteName('type') . ' = :type')->bind(':type', $type);
        }
        if (!empty($source['notRoot'])) {
            // The root of the category, menu and tag trees is not an item.
            $query->where($db->quoteName('parent_id') . ' <> 0');
        }
        if (!isset($source['group'])) {
            return ['' => $this->counts($query)];
        }
        $column = $db->quoteName($source['group']);
        $query->select([$column . ' AS ' . $db->quoteName('grp'), $db->quoteName('access'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->group([$column, $db->quoteName('access')])
            ->order($column);
        $groups = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $groups[(string) $row->grp][(int) $row->access] = (int) $row->total;
        }
        return $groups;
    }

    /**
     * @return array<int, int> items per access level
     */
    private function counts(\Joomla\Database\QueryInterface $query): array
    {
        $db = $this->db;
        $query->select([$db->quoteName('access'), 'COUNT(*) AS ' . $db->quoteName('total')])->group($db->quoteName('access'));
        $counts = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $counts[(int) $row->access] = (int) $row->total;
        }
        return $counts;
    }

    /** "com_content" → "Articles"; a field context "com_content.article" → "Articles (article)". */
    private function groupName(string $group): string
    {
        [$component, $section] = array_pad(explode('.', $group, 2), 2, '');
        $name = ($this->component)($component);
        return $section === '' ? $name : $name . ' (' . $section . ')';
    }
}
