<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsage;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Which content uses each viewing access level. Read only. */
class UsageModel extends BaseDatabaseModel
{
    /** @return array<string, mixed> see AccessUsage::build() */
    public function getUsage(): array
    {
        $reader = Toolkit::usageReader();
        $levels = array_map(fn ($level) => ['id' => $level['id'], 'title' => $level['title']], Toolkit::directory()->viewLevels());
        return AccessUsage::build($levels, $reader->sources());
    }

    /** The usage as a CSV file, in the interface language. */
    public function csv(): string
    {
        $table = AccessUsage::export($this->getUsage(), fn (string $key, string $argument = '') => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument));
        return CsvExport::build($table['header'], $table['rows']);
    }
}
