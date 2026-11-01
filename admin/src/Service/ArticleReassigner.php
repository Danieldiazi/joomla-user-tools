<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;

/**
 * Transfers the authorship of one reviewed article through com_content's own Article table, the same
 * table Joomla's batch processing uses: its check and store run, with their table events, versioning
 * and asset handling. Only "created_by" is changed. As in any edit through Joomla, "modified" and
 * "modified_by" then record when and by whom the transfer was made; the visible author alias is kept.
 *
 * Right before writing, the article must still be authored by the source account, match the reviewed
 * fingerprint, not be checked out by someone else and be editable by the operator. Otherwise it is
 * skipped, never replaced by another article.
 */
final class ArticleReassigner
{
    /** @var \Closure(): object */
    private \Closure $table;

    /** @param (callable(): object)|null $table returns a new com_content Article table (injectable for tests) */
    public function __construct(private ActionLogger $log, ?callable $table = null)
    {
        $this->table = $table !== null ? \Closure::fromCallable($table) : static function (): object {
            $component = Factory::getApplication()->bootComponent('com_content');
            if (!$component instanceof MVCFactoryServiceInterface) {
                throw new \RuntimeException('com_content is not available.');
            }
            return $component->getMVCFactory()->createTable('Article', 'Administrator');
        };
    }

    /**
     * @param callable(int): bool $canEdit whether the operator may edit this article (core.edit on its asset)
     */
    public function reassign(int $id, string $fingerprint, int $source, int $target, Operator $operator, callable $canEdit): string
    {
        try {
            $table = ($this->table)();
            if (!$table->load($id)) {
                return ContentHandover::CHANGED;
            }
        } catch (\Throwable) {
            return ContentHandover::ERROR;
        }
        $current = ['id' => (int) $table->id, 'created_by' => (int) $table->created_by, 'modified' => (string) ($table->modified ?? ''),
            'checked_out' => (int) ($table->checked_out ?? 0), 'state' => (int) $table->state, 'catid' => (int) $table->catid];
        if ($current['id'] !== $id || $current['created_by'] !== $source || !hash_equals($fingerprint, ContentHandover::fingerprint($current))
            || !\in_array($current['checked_out'], [0, $operator->id], true) || !$canEdit($id) || $source === $target) {
            return ContentHandover::CHANGED;
        }
        $table->created_by = $target;
        try {
            if (!$table->check() || !$table->store()) {
                return ContentHandover::ERROR;
            }
        } catch (\Throwable) {
            return ContentHandover::ERROR;
        }
        $this->log->articleReassigned($id, (string) $table->title, $source, $target, $operator);
        return ContentHandover::TRANSFERRED;
    }
}
