<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsage;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsageReader;
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
        $app      = Toolkit::app();
        $identity = $app->getIdentity();
        $language = $app->getLanguage();
        $reader   = new AccessUsageReader(
            Toolkit::db(),
            fn (string $key, string $argument) => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument),
            function (string $component) use ($language): string {
                // Component names come from each component's own language file.
                $language->load($component . '.sys', JPATH_ADMINISTRATOR) || $language->load($component . '.sys', JPATH_ADMINISTRATOR . '/components/' . $component);
                $key = strtoupper($component);
                return $language->hasKey($key) ? Text::_($key) : $component;
            },
            fn (string $component) => (bool) $identity?->authorise('core.manage', $component),
        );
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
