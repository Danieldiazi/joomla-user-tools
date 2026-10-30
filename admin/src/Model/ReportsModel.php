<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Task reports stored by the scheduled task. */
class ReportsModel extends BaseDatabaseModel
{
    public const PAGE_SIZE = 20;

    /**
     * @param  array{task?: int, mode?: string, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, tasks: array<int, string>}
     */
    public function getPage(int $page, array $filters): array
    {
        return Toolkit::reports()->page($page, self::PAGE_SIZE, $filters);
    }

    /** @return array<string, mixed>|null */
    public function getReport(string $id): ?array
    {
        return Toolkit::reports()->get($id);
    }

    /** @param array<string, mixed> $report */
    public function csv(array $report): string
    {
        $rows = [];
        foreach ((array) ($report['users'] ?? []) as $row) {
            $rows[] = [
                (int) ($row['id'] ?? 0),
                (string) ($row['username'] ?? ''),
                Text::_('COM_USERTOOLS_REPORT_ACTION_' . strtoupper((string) ($row['action'] ?? 'unknown'))),
                self::translate((string) ($row['reason'] ?? '')),
                (string) ($row['last_visit'] ?? ''),
            ];
        }
        return CsvExport::build([
            Text::_('COM_USERTOOLS_ID'), Text::_('COM_USERTOOLS_USERNAME'), Text::_('COM_USERTOOLS_RESULT'),
            Text::_('COM_USERTOOLS_REASON'), Text::_('COM_USERTOOLS_LAST_VISIT_UTC'),
        ], $rows);
    }

    /** Reports from 2026.10.2 and earlier store Spanish text; newer ones store language keys. */
    public static function translate(string $value): string
    {
        return str_starts_with($value, 'COM_USERTOOLS_') ? Text::_($value) : $value;
    }
}
