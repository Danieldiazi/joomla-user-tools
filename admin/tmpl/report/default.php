<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\ReportsModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Report\HtmlView $this */
$states  = ['running' => 'COM_USERTOOLS_STATUS_RUNNING', 'completed' => 'COM_USERTOOLS_STATUS_COMPLETED', 'failed' => 'COM_USERTOOLS_STATUS_FAILED', 'guarded' => 'COM_USERTOOLS_STATUS_GUARDED'];
$report  = $this->report;
?>
<div class="ut-app">
  <?php if ($this->error) : ?>
    <div class="alert alert-warning"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <section class="card ut-card"><div class="card-body">
    <div class="ut-section-heading">
      <h2 class="h4 mb-0"><?= $this->escape($report['task_title'] ?? '') ?> <small class="text-muted">· <?= Text::sprintf('COM_USERTOOLS_TASK_N', (int) ($report['task_id'] ?? 0)) ?></small></h2>
      <span class="badge <?= !empty($report['dry_run']) ? 'bg-info' : 'bg-danger' ?>"><?= Text::_(!empty($report['dry_run']) ? 'COM_USERTOOLS_MODE_DRY' : 'COM_USERTOOLS_MODE_REAL') ?></span>
    </div>
    <p class="mt-3"><strong><?= Text::_($states[$report['status'] ?? ''] ?? 'COM_USERTOOLS_STATUS_UNREADABLE') ?></strong>
      · <?= Text::sprintf('COM_USERTOOLS_REPORT_TIMES', $this->escape($report['started_at'] ?? ''), $this->escape($report['finished_at'] ?? Text::_('COM_USERTOOLS_NOT_FINISHED'))) ?></p>
    <p><?= Text::sprintf('COM_USERTOOLS_REPORT_SETTINGS', $this->escape((string) ($report['days'] ?? '')), $this->escape($report['cutoff'] ?? '—'), Text::_(!empty($report['include_never']) ? 'JYES' : 'JNO')) ?></p>
    <?php if (isset($report['guard'])) :
        $percent = (int) $report['guard']['percent'];
        ?>
      <p><?= $percent > 0 && $percent < 100
          ? Text::sprintf('COM_USERTOOLS_REPORT_GUARD', $percent, (int) $report['guard']['minimum'], (int) ($report['active_accounts'] ?? 0))
          : Text::sprintf('COM_USERTOOLS_REPORT_GUARD_OFF', (int) ($report['active_accounts'] ?? 0)) ?></p>
    <?php endif; ?>
    <?php if (($report['status'] ?? '') === 'running') : ?><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_REPORT_RUNNING') ?></div><?php endif; ?>
    <?php if (!empty($report['guard_triggered'])) : ?>
      <div class="alert alert-danger mb-0"><?= Text::sprintf(!empty($report['dry_run']) ? 'COM_USERTOOLS_REPORT_GUARD_DRY' : 'COM_USERTOOLS_REPORT_GUARD_STOPPED', (int) $report['candidates'], $this->escape((string) ($report['guard_share'] ?? '')), (int) ($report['active_accounts'] ?? 0), (int) $report['guard']['percent']) ?></div>
    <?php elseif (!empty($report['message'])) : ?><div class="alert alert-warning mb-0"><?= $this->escape(ReportsModel::translate((string) $report['message'])) ?></div><?php endif; ?>
  </div></section>
  <div class="ut-stats">
  <?php foreach (['COM_USERTOOLS_STAT_CANDIDATES' => 'candidates', 'COM_USERTOOLS_STAT_BLOCKED' => 'blocked', 'COM_USERTOOLS_STAT_PROTECTED' => 'protected', 'COM_USERTOOLS_STAT_OMITTED' => 'omitted', 'COM_USERTOOLS_STAT_ERRORS' => 'errors'] as $label => $key) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) ($report[$key] ?? 0) ?></strong><span><?= Text::_($label) ?></span></div></div>
  <?php endforeach; ?>
  </div>
  <?php if (empty($report['dry_run']) && (int) ($report['blocked'] ?? 0) > 0 && $this->can('usertools.unblock')) : ?>
  <section class="card ut-card"><div class="card-body">
    <?= LayoutHelper::render('usertools.undo', ['batch' => 'task:' . $report['id'], 'can' => true], JPATH_ADMINISTRATOR . '/components/com_usertools/layouts') ?>
  </div></section>
  <?php endif; ?>
  <?php if (!empty($report['omitted_reasons'])) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_OMITTED_SUMMARY') ?></h3>
    <ul class="mb-0"><?php foreach ($report['omitted_reasons'] as $reason => $count) : ?><li><strong><?= (int) $count ?></strong> — <?= $this->escape(ReportsModel::translate((string) $reason)) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <section class="card ut-card"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_REPORT_DETAIL') ?></h3>
      <?php if (!empty($report['users'])) : ?><a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&task=reports.export&report_id=' . $this->escape($report['id'])) ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a><?php endif; ?></div>
    <?php if (empty($report['users'])) : ?><p class="text-muted mt-3 mb-0"><?= Text::_('COM_USERTOOLS_REPORT_NO_USERS') ?></p>
    <?php else : ?>
      <?php if (count($report['users']) > 8) : ?><input type="search" class="form-control mt-3" placeholder="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" data-ut-filter="ut-report-table"><?php endif; ?>
      <div class="ut-table-scroll"><table class="table table-sm table-striped" id="ut-report-table"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ID') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_RESULT') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_REASON') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></th></tr></thead><tbody>
      <?php foreach ($report['users'] as $row) : ?>
        <tr><td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></td><td><?= (int) $row['id'] ?></td>
          <td><?= Text::_('COM_USERTOOLS_REPORT_ACTION_' . strtoupper((string) $row['action'])) ?></td><td><?= $this->escape(ReportsModel::translate((string) $row['reason'])) ?></td><td><?= $this->date($row['last_visit'] ?? null) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table><p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p></div>
    <?php endif; ?>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=reports') ?>"><?= Text::_('COM_USERTOOLS_BACK_REPORTS') ?></a>
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_scheduler&view=tasks') ?>"><?= Text::_('COM_USERTOOLS_OPEN_TASKS') ?></a>
  </div>
</div>
