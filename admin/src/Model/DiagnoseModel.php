<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessHealth;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Access diagnosis: situations worth reviewing in accounts, groups and access levels. Read only. */
class DiagnoseModel extends BaseDatabaseModel
{
    /** @var array<string, mixed>|null */
    private ?array $health = null;

    /** @return array{days: int, checks: array<string, array{severity: string, found: bool, items: list<array<string, mixed>>, count: int}>} */
    public function getHealth(): array
    {
        return $this->health ??= Toolkit::accessHealth(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    /** @return array<int, object> */
    public function getGroups(): array
    {
        return Toolkit::directory()->groups();
    }

    /** Every finding as a CSV file, in the interface language. */
    public function csv(): string
    {
        $table = AccessHealth::export($this->getHealth(), $this->getGroups(),
            fn (string $key, string|int $argument = '') => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument));
        return CsvExport::build($table['header'], $table['rows']);
    }
}
