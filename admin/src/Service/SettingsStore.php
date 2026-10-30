<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/** Saves some component options through Joomla's extension table, keeping all other values. */
final class SettingsStore
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $component = ComponentHelper::getComponent('com_usertools');
        $table     = new Extension($this->db);
        if (!$table->load((int) $component->id)) {
            throw new \RuntimeException('Component record not found.');
        }
        $params = new Registry($table->params);
        foreach ($values as $key => $value) {
            $params->set($key, $value);
        }
        $table->params = $params->toString();
        if (!$table->check() || !$table->store()) {
            throw new \RuntimeException('Cannot save the component options.');
        }
        // The dashboard figures depend on these options.
        Toolkit::clearDashboardCache();
        // Keep the cached options of this request in sync.
        foreach ($values as $key => $value) {
            $component->getParams()->set($key, $value);
        }
    }
}
