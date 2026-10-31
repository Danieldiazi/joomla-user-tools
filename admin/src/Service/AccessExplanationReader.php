<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Reads the facts AccessExplanation needs for one account and one com_content article or category.
 * Permissions are resolved by Joomla (User::authorise on the item's asset) for a freshly loaded
 * User object of the account: nothing is written, no session is touched and the account is never
 * signed in. Queries are bounded to one item, its category path, its workflow stage and its menus.
 */
final class AccessExplanationReader
{
    /** Menu items listed for one item, at most. */
    public const MENU_LIMIT = 20;

    public function __construct(private DatabaseInterface $db, private UserFactoryInterface $users)
    {
    }

    /**
     * The item as the operator may see it, or null if it does not exist or the operator may not see
     * it (both look the same, so the screen never reveals that a hidden item exists).
     *
     * @param  list<int>|null $operatorLevels null only for a global Super User
     * @return array{type: string, id: int, title: string, state: int, access: int, publish_up: ?string, publish_down: ?string,
     *   author: int, checked_out: int, category: ?array{id: int, published: int, access: int, title: string},
     *   ancestors: list<array{published: int, title: string}>}|null
     */
    public function item(string $type, int $id, ?array $operatorLevels): ?array
    {
        if ($id < 1) {
            return null;
        }
        $db = $this->db;
        if ($type === 'article') {
            $row = $db->setQuery($db->createQuery()
                ->select([$db->quoteName('a.id'), $db->quoteName('a.title'), $db->quoteName('a.state'), $db->quoteName('a.access'), $db->quoteName('a.publish_up'),
                    $db->quoteName('a.publish_down'), $db->quoteName('a.created_by'), $db->quoteName('a.checked_out'), $db->quoteName('a.catid'),
                    $db->quoteName('c.published', 'category_published'), $db->quoteName('c.access', 'category_access'), $db->quoteName('c.title', 'category_title')])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
                ->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER), 0, 1)->loadObject();
            if (!$row || !$this->visible([(int) $row->access, $row->category_access === null ? null : (int) $row->category_access], $operatorLevels)) {
                return null;
            }
            return [
                'type' => 'article', 'id' => (int) $row->id, 'title' => (string) $row->title, 'state' => (int) $row->state, 'access' => (int) $row->access,
                'publish_up' => $row->publish_up === null ? null : (string) $row->publish_up, 'publish_down' => $row->publish_down === null ? null : (string) $row->publish_down,
                'author' => (int) $row->created_by, 'checked_out' => (int) ($row->checked_out ?? 0),
                'category' => $row->category_title === null ? null
                    : ['id' => (int) $row->catid, 'published' => (int) $row->category_published, 'access' => (int) $row->category_access, 'title' => (string) $row->category_title],
                'ancestors' => [],
            ];
        }
        $extension = 'com_content';
        $row = $db->setQuery($db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('published'), $db->quoteName('access'), $db->quoteName('created_user_id'),
                $db->quoteName('checked_out'), $db->quoteName('lft'), $db->quoteName('rgt')])
            ->from($db->quoteName('#__categories'))
            ->where([$db->quoteName('id') . ' = :id', $db->quoteName('extension') . ' = :extension'])
            ->bind(':id', $id, ParameterType::INTEGER)->bind(':extension', $extension), 0, 1)->loadObject();
        if (!$row || !$this->visible([(int) $row->access], $operatorLevels)) {
            return null;
        }
        $lft = (int) $row->lft;
        $rgt = (int) $row->rgt;
        $ancestors = [];
        foreach ($db->setQuery($db->createQuery()
            ->select([$db->quoteName('title'), $db->quoteName('published'), $db->quoteName('access')])
            ->from($db->quoteName('#__categories'))
            ->where([$db->quoteName('extension') . ' = :parentExtension', $db->quoteName('lft') . ' < :lft', $db->quoteName('rgt') . ' > :rgt', $db->quoteName('parent_id') . ' > 0'])
            ->bind(':parentExtension', $extension)->bind(':lft', $lft, ParameterType::INTEGER)->bind(':rgt', $rgt, ParameterType::INTEGER)
            ->order($db->quoteName('lft')))->loadObjectList() as $parent) {
            // A parent the operator may not see keeps its state, never its title.
            $ancestors[] = ['published' => (int) $parent->published, 'title' => $this->visible([(int) $parent->access], $operatorLevels)
                ? (string) $parent->title : Text::_('COM_USERTOOLS_EXPLAIN_HIDDEN_PARENT')];
        }
        return [
            'type' => 'category', 'id' => (int) $row->id, 'title' => (string) $row->title, 'state' => (int) $row->published, 'access' => (int) $row->access,
            'publish_up' => null, 'publish_down' => null, 'author' => (int) $row->created_user_id, 'checked_out' => (int) ($row->checked_out ?? 0),
            'category' => null, 'ancestors' => $ancestors,
        ];
    }

    /**
     * Facts for AccessExplanation::explain().
     *
     * @param array<string, mixed> $item from item()
     * @param bool                 $withRules whether to list explicit denials (only for operators who may see permissions)
     * @return array<string, mixed>
     */
    public function facts(int $accountId, array $item, bool $withRules, string $now): array
    {
        $user = $this->users->loadUserById($accountId);
        if ((int) $user->id !== $accountId || $accountId < 1) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_NOT_FOUND');
        }
        $asset = 'com_content.' . $item['type'] . '.' . (int) $item['id'];
        $can = [
            'login_site'  => (bool) $user->authorise('core.login.site'),
            'login_admin' => (bool) $user->authorise('core.login.admin'),
            'manage'      => (bool) $user->authorise('core.manage', 'com_content'),
            'edit'        => (bool) $user->authorise('core.edit', $asset),
            'edit_own'    => (bool) $user->authorise('core.edit.own', $asset),
            'edit_state'  => (bool) $user->authorise('core.edit.state', $asset),
        ];
        return [
            'account'      => ['id' => $accountId, 'blocked' => (int) $user->block === 1, 'levels' => array_values(array_map('intval', $user->getAuthorisedViewLevels()))],
            'guest_levels' => array_values(array_map('intval', Access::getAuthorisedViewLevels(0))),
            'item'         => $item,
            'levels'       => $this->levelTitles(),
            'can'          => $can,
            'denials'      => $withRules ? $this->denials($user, $asset) : null,
            'workflow'     => $item['type'] === 'article' ? $this->workflow($user, (int) $item['id']) : null,
            'menus'        => $this->menus($item['type'], (int) $item['id']),
            'now'          => $now,
        ];
    }

    /** @return array<int, string> */
    private function levelTitles(): array
    {
        $db = $this->db;
        $titles = [];
        foreach ($db->setQuery($db->createQuery()->select([$db->quoteName('id'), $db->quoteName('title')])->from($db->quoteName('#__viewlevels')))->loadObjectList() as $row) {
            $titles[(int) $row->id] = (string) $row->title;
        }
        return $titles;
    }

    /**
     * Explicit "Denied" rules for the account's groups (including inherited parent groups) along the
     * item's asset chain, for the actions the explanation uses. Joomla resolves the result; this only
     * says where a denial is configured.
     *
     * @return list<array{action: string, asset: string, group: string}>
     */
    private function denials(User $user, string $asset): array
    {
        $db = $this->db;
        $groups = array_map('intval', Access::getGroupsByUser((int) $user->id, true));
        if (!$groups) {
            return [];
        }
        $names = [];
        foreach ($db->setQuery($db->createQuery()->select([$db->quoteName('id'), $db->quoteName('title')])->from($db->quoteName('#__usergroups'))
            ->whereIn($db->quoteName('id'), $groups))->loadObjectList() as $row) {
            $names[(int) $row->id] = (string) $row->title;
        }
        $name = $asset;
        $chain = [];
        foreach ([$asset, 'com_content'] as $candidate) {
            $name  = $candidate;
            $chain = $db->setQuery($db->createQuery()
                ->select([$db->quoteName('p.name'), $db->quoteName('p.title'), $db->quoteName('p.rules')])
                ->from($db->quoteName('#__assets', 'a'))
                ->join('INNER', $db->quoteName('#__assets', 'p'), $db->quoteName('p.lft') . ' <= ' . $db->quoteName('a.lft') . ' AND ' . $db->quoteName('p.rgt') . ' >= ' . $db->quoteName('a.rgt'))
                ->where($db->quoteName('a.name') . ' = :name')->bind(':name', $name)
                ->order($db->quoteName('p.lft')))->loadObjectList();
            if ($chain) {
                break;
            }
        }
        $denials = [];
        foreach ($chain as $node) {
            $rules = json_decode((string) $node->rules, true);
            foreach (['core.login.site', 'core.login.admin', 'core.manage', 'core.edit', 'core.edit.own', 'core.edit.state'] as $action) {
                foreach ((array) ($rules[$action] ?? []) as $group => $value) {
                    if ((int) $value === 0 && isset($names[(int) $group])) {
                        $denials[] = ['action' => $action, 'asset' => (string) ($node->title ?: $node->name), 'group' => $names[(int) $group]];
                    }
                }
            }
        }
        return $denials;
    }

    /**
     * The article's workflow stage and the transitions Joomla offers from it, or null when workflows
     * are disabled in com_content (publishing then depends on core.edit.state).
     *
     * @return null|array{stage: string, transitions: list<array{title: string, allowed: bool, publishes: bool}>}
     */
    private function workflow(User $user, int $articleId): ?array
    {
        if (!ComponentHelper::getParams('com_content')->get('workflow_enabled', 0)) {
            return null;
        }
        $db = $this->db;
        $extension = 'com_content.article';
        $stage = $db->setQuery($db->createQuery()
            ->select([$db->quoteName('s.id'), $db->quoteName('s.title'), $db->quoteName('s.workflow_id')])
            ->from($db->quoteName('#__workflow_associations', 'w'))
            ->join('INNER', $db->quoteName('#__workflow_stages', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('w.stage_id'))
            ->where([$db->quoteName('w.item_id') . ' = :id', $db->quoteName('w.extension') . ' = :extension'])
            ->bind(':id', $articleId, ParameterType::INTEGER)->bind(':extension', $extension), 0, 1)->loadObject();
        if (!$stage) {
            return ['stage' => '', 'transitions' => []];
        }
        // Workflow titles are often language keys of com_workflow (for example the basic workflow).
        Factory::getApplication()->getLanguage()->load('com_workflow', JPATH_ADMINISTRATOR);
        $stageId    = (int) $stage->id;
        $workflowId = (int) $stage->workflow_id;
        $any        = -1;
        $transitions = [];
        foreach ($db->setQuery($db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('options')])
            ->from($db->quoteName('#__workflow_transitions'))
            ->where([$db->quoteName('workflow_id') . ' = :workflow', $db->quoteName('published') . ' = 1'])
            ->whereIn($db->quoteName('from_stage_id'), [$stageId, $any])
            ->bind(':workflow', $workflowId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering')))->loadObjectList() as $row) {
            $options = new Registry((string) $row->options);
            $transitions[] = [
                'title'     => Text::_((string) $row->title),
                'allowed'   => (bool) $user->authorise('core.execute.transition', 'com_content.transition.' . (int) $row->id),
                'publishes' => (string) $options->get('publishing', '') === '1',
            ];
        }
        return ['stage' => Text::_((string) $stage->title), 'transitions' => $transitions];
    }

    /** @return list<array{title: string, access: int, published: int}> */
    private function menus(string $type, int $id): array
    {
        $db = $this->db;
        $client = 0;
        $trashed = -2;
        $links = $type === 'article'
            ? ['index.php?option=com_content&view=article&id=' . $id]
            : ['index.php?option=com_content&view=category&id=' . $id, 'index.php?option=com_content&view=category&layout=blog&id=' . $id];
        $rows = [];
        foreach ($db->setQuery($db->createQuery()
            ->select([$db->quoteName('title'), $db->quoteName('access'), $db->quoteName('published')])
            ->from($db->quoteName('#__menu'))
            ->where([$db->quoteName('client_id') . ' = :client', $db->quoteName('published') . ' <> :trashed'])
            ->whereIn($db->quoteName('link'), $links, ParameterType::STRING)
            ->bind(':client', $client, ParameterType::INTEGER)->bind(':trashed', $trashed, ParameterType::INTEGER)
            ->order($db->quoteName('lft')), 0, self::MENU_LIMIT)->loadObjectList() as $row) {
            $rows[] = ['title' => (string) $row->title, 'access' => (int) $row->access, 'published' => (int) $row->published];
        }
        return $rows;
    }

    /**
     * @param list<int|null> $levels
     * @param list<int>|null $allowed
     */
    private function visible(array $levels, ?array $allowed): bool
    {
        if ($allowed === null) {
            return true;
        }
        foreach ($levels as $level) {
            if ($level !== null && !\in_array($level, $allowed, true)) {
                return false;
            }
        }
        return true;
    }
}
