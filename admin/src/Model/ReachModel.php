<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReachReader;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Reach of each user group: accounts and visible articles, and the members of one branch. Read only. */
class ReachModel extends BaseDatabaseModel
{
    private ?GroupReachReader $reader = null;

    /** @var array<string, mixed>|null */
    private ?array $matrix = null;

    /**
     * @return list<array<string, mixed>> see GroupReach::build()
     */
    public function getRows(int $few): array
    {
        return GroupReach::build($this->matrix(), Toolkit::directory()->groups(), $this->reader()->activeMembersPerBranch(),
            $this->reader()->articlesByLevels(gmdate('Y-m-d H:i:s')), $few);
    }

    /** @return array<int, string> level id => title, in their order */
    public function getLevelTitles(): array
    {
        return array_column($this->matrix()['levels'], 'title', 'id');
    }

    /** @return array<int, string> group id => full path */
    public function getPaths(): array
    {
        return AccessMatrix::paths($this->matrix()['rows']);
    }

    /**
     * One page of the active accounts of a group and its subgroups.
     *
     * @return array{group: object, accounts: list<array<string, mixed>>, total: int, blocked: int, page: int}
     */
    public function getMembers(int $groupId, int $page): array
    {
        $group = Toolkit::directory()->groups()[$groupId] ?? null;
        if ($group === null) {
            throw new UserFacingException('COM_USERTOOLS_REACH_UNKNOWN_GROUP');
        }
        $total = $this->reader()->countMembers($group->lft, $group->rgt);
        $page  = GroupReach::page($page, $total);
        return [
            'group'    => $group,
            'accounts' => $this->reader()->listMembers($group->lft, $group->rgt, ($page - 1) * GroupReach::PAGE_SIZE, GroupReach::PAGE_SIZE),
            'total'    => $total,
            'blocked'  => $this->reader()->countMembers($group->lft, $group->rgt, true),
            'page'     => $page,
        ];
    }

    /** The table as a CSV file, in the interface language. */
    public function csv(int $few): string
    {
        $table = GroupReach::export($this->getRows($few), $this->getPaths(), $this->getLevelTitles(), fn (string $key): string => Text::_($key));
        return CsvExport::build($table['header'], $table['rows']);
    }

    /** @return array<string, mixed> see AccessMatrix::build() */
    private function matrix(): array
    {
        if ($this->matrix === null) {
            $directory    = Toolkit::directory();
            $guest        = (int) ComponentHelper::getParams('com_users')->get('guest_usergroup', 1);
            $this->matrix = AccessMatrix::build($directory->groups(), $directory->viewLevels(), $directory->activeMembersPerGroup(), $guest);
        }
        return $this->matrix;
    }

    private function reader(): GroupReachReader
    {
        return $this->reader ??= new GroupReachReader(Toolkit::db());
    }
}
