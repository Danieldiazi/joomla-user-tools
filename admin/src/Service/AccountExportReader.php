<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/** Bulk reads only IDs / method names, never MFA credentials or recovery codes. */
final class AccountExportReader
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return array<int, bool> IDs with an activation token; an account is pending only if also blocked. */
    public function pendingActivation(): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__users'))
            ->where($db->quoteName('activation') . ' IS NOT NULL')
            ->where($db->quoteName('activation') . ' <> ' . $db->quote(''));
        return array_fill_keys(array_map('intval', $db->setQuery($query)->loadColumn()), true);
    }

    /** @return array<int, bool> IDs that have at least one MFA method (excluding recovery codes). */
    public function mfaConfigured(): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select('DISTINCT ' . $db->quoteName('user_id'))
            ->from($db->quoteName('#__user_mfa'))
            ->where($db->quoteName('method') . ' NOT IN (' . $db->quote('backupcodes') . ', ' . $db->quote('emergencycodes') . ')');
        return array_fill_keys(array_map('intval', $db->setQuery($query)->loadColumn()), true);
    }
}
