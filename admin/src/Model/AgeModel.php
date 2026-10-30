<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAgeReader;
use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Content by age: items per year of last modification, and the items of one year. Read only. */
class AgeModel extends BaseDatabaseModel
{
    private ?ContentAgeReader $reader = null;

    /** @return array{years: list<int>, kinds: list<array{key: string, label: string, counts: array<int, int>, total: int}>, totals: array<int, int>, total: int} */
    public function getYears(int $level): array
    {
        $kinds = [];
        foreach ($this->reader()->kinds() as $kind) {
            $kinds[] = ['key' => $kind, 'label' => Text::_(ContentAgeReader::KINDS[$kind]['label']), 'counts' => $this->reader()->counts($kind, $level)];
        }
        return ContentAge::build($kinds);
    }

    /** @return list<array{id: int, title: string}> levels for the filter, in their order */
    public function getLevels(): array
    {
        $user = Toolkit::app()->getIdentity();
        $levels = Toolkit::directory()->viewLevels();
        if (!$user->authorise('core.admin')) {
            $allowed = array_fill_keys(array_map('intval', $user->getAuthorisedViewLevels()), true);
            $levels = array_filter($levels, fn (array $level): bool => isset($allowed[$level['id']]));
        }
        return array_values(array_map(fn (array $level) => ['id' => $level['id'], 'title' => $level['title']], $levels));
    }

    /**
     * One page of the items of a kind last modified in a year.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int}
     */
    public function getList(string $kind, int $year, int $level, int $page): array
    {
        $this->assertKind($kind);
        $total = $this->reader()->count($kind, $year, $level);
        $page  = ContentAge::page($page, $total);
        return ['items' => $this->reader()->items($kind, $year, $level, ($page - 1) * ContentAge::PAGE_SIZE, ContentAge::PAGE_SIZE), 'total' => $total, 'page' => $page];
    }

    /** The table, or the items of one year when a kind and a year are given, as a CSV file. */
    public function csv(string $kind, int $year, int $level): string
    {
        $text = fn (string $key, string $argument = '') => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument);
        if ($kind !== '' && $year > 0) {
            $this->assertKind($kind);
            $table = ContentAge::exportItems($this->reader()->items($kind, $year, $level), $text);
        } else {
            $table = ContentAge::export($this->getYears($level), $text);
        }
        return CsvExport::build($table['header'], $table['rows']);
    }

    private function assertKind(string $kind): void
    {
        if (!\in_array($kind, $this->reader()->kinds(), true)) {
            throw new UserFacingException('COM_USERTOOLS_AGE_UNKNOWN_KIND');
        }
    }

    private function reader(): ContentAgeReader
    {
        if ($this->reader === null) {
            $user = Toolkit::app()->getIdentity();
            $this->reader = new ContentAgeReader(Toolkit::db(), $user->authorise('core.admin') ? null : array_values(array_map('intval', $user->getAuthorisedViewLevels())));
        }
        return $this->reader;
    }
}
