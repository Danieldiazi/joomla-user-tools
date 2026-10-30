<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountExport;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountExportReader;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Read-only export. UserDirectory batches users and memberships; MFA and activation need two queries. */
class ExportModel extends BaseDatabaseModel
{
    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** @param array<string, mixed> $filters */
    public function csv(array $filters): string
    {
        $group = (int) ($filters['group'] ?? 0);
        $subgroups = (bool) ($filters['subgroups'] ?? false);
        $days = (int) ($filters['days'] ?? 0);
        $includeNever = (bool) ($filters['include_never'] ?? false);
        $status = (string) ($filters['status'] ?? 'all');
        $email = (bool) ($filters['email'] ?? false);
        if ($days < 0 || $days > InactivityPolicy::MAX_DAYS) {
            throw new UserFacingException('COM_USERTOOLS_EXPORT_BAD_DAYS');
        }
        $directory = Toolkit::directory();
        $groups = $directory->groups();
        $selected = AccountExport::select($directory->users(), $groups, $group, $subgroups, $days, $includeNever, $status,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $reader = new AccountExportReader(Toolkit::db());
        $pending = $reader->pendingActivation();
        $mfa = $reader->mfaConfigured();
        $text = static fn (string $key): string => Text::_($key);
        return CsvExport::build(AccountExport::header($email, $text), AccountExport::rows($selected, $groups, $pending, $mfa, $email, $text));
    }
}
