<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountProfile;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountProfileReader;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupRemoval;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Stateless account lookup. Reading a profile never saves an account or changes a tool draft. */
class ProfileModel extends BaseDatabaseModel
{
    public function findAccount(string $login): int
    {
        $id = Toolkit::directory()->findId($login);
        if ($id < 1) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_NOT_FOUND');
        }
        return $id;
    }

    /** @return array<string, mixed>|null */
    public function getProfile(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $directory = Toolkit::directory();
        $account = $directory->users([$id])[$id] ?? null;
        if (!$account) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_NOT_FOUND');
        }
        $identity = Toolkit::app()->getIdentity() ?? throw new \LogicException('No user.');
        $operator = Operator::fromUser($identity);
        $permissions = [];
        foreach (['usertools.ungroup', 'usertools.inactive', 'usertools.anonymize'] as $permission) {
            $permissions[$permission] = (bool) $identity->authorise($permission, 'com_usertools');
        }
        $actions = AccountProfile::actions($account, $operator, $permissions);
        $settings = Toolkit::settings();
        $actions['block'] = $actions['block'] && !$settings->exclusions(Settings::INACTIVE)->protects($account, $operator);
        $actions['anonymize'] = $actions['anonymize'] && !$settings->exclusions(Settings::ANONYMIZE)->protects($account, $operator);
        $groups = $directory->groups();
        $reader = new AccountProfileReader(Toolkit::db());
        $logsAllowed = (bool) $identity->authorise('core.admin');
        return [
            'account' => $account, 'groups' => $groups, 'tree' => GroupRemoval::treeRows($groups, $account->groups),
            'levels' => AccountProfile::levels($groups, $directory->viewLevels(), $account,
                array_values(array_map('intval', Toolkit::users()->loadUserById($id)->getAuthorisedViewLevels()))),
            'mfa' => $reader->mfaConfigured($id), 'actions' => $actions,
            'content' => $reader->content($id, fn (string $component): bool => (bool) $identity->authorise('core.manage', $component),
                $logsAllowed ? null : array_values(array_map('intval', $identity->getAuthorisedViewLevels()))),
            'logsAllowed' => $logsAllowed, 'logs' => $logsAllowed ? $reader->logs($id) : [],
        ];
    }
}
