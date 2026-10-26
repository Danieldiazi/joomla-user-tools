<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** User groups, viewing access levels and the relation between them. Read only. */
class AccessModel extends BaseDatabaseModel
{
    /** @return array<string, mixed> see AccessMatrix::build() */
    public function getMatrix(): array
    {
        $directory = Toolkit::directory();
        $guest     = (int) ComponentHelper::getParams('com_users')->get('guest_usergroup', 1);
        return AccessMatrix::build($directory->groups(), $directory->viewLevels(), $directory->activeMembersPerGroup(), $guest);
    }
}
