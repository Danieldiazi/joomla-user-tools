<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Reports\HtmlView $this */
$states  = ['running' => 'COM_USERTOOLS_STATUS_RUNNING', 'completed' => 'COM_USERTOOLS_STATUS_COMPLETED', 'failed' => 'COM_USERTOOLS_STATUS_FAILED', 'guarded' => 'COM_USERTOOLS_STATUS_GUARDED', 'unreadable' => 'COM_USERTOOLS_STATUS_UNREADABLE'];
$filters = $this->filters;
$query   = http_build_query(array_filter(['filter_task' => $filters['task'] ?: null, 'filter_mode' => $filters['mode'] ?: null, 'filter_status' => $filters['status'] ?: null]));
$pageUrl = fn (int $p) => Route::_('index.php?option=com_usertools&view=reports&page=' . $p . ($query ? '&' . $query : ''));
?>
<div class="ut-app">
  <p class="lead"><?= Text::_('COM_USERTOOLS_REPORTS_INTRO') ?></p>
  <form method="get" action="<?= Route::_('index.php') ?>" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="option" value="com_usertools"><input type="hidden" name="view" value="reports">
    <div class="col-sm-4"><label class="form-label" for="ut-filter-task"><?= Text::_('COM_USERTOOLS_FILTER_TASK') ?></label>
      <select class="form-select" id="ut-filter-task" name="filter_task"><option value="0"><?= Text::_('COM_USERTOOLS_FILTER_ALL') ?></option>
      <?php foreach ($this->page['tasks'] as $id => $title) : ?><option value="<?= (int) $id ?>"<?= $filters['task'] === $id ? ' selected' : '' ?>>#<?= (int) $id ?> · <?= $this->escape($title) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-3"><label class="form-label" for="ut-filter-mode"><?= Text::_('COM_USERTOOLS_FILTER_MODE') ?></label>
      <select class="form-select" id="ut-filter-mode" name="filter_mode"><option value=""><?= Text::_('COM_USERTOOLS_FILTER_ALL') ?></option>
        <option value="real"<?= $filters['mode'] === 'real' ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_MODE_REAL') ?></option>
        <option value="dry"<?= $filters['mode'] === 'dry' ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_MODE_DRY') ?></option></select></div>
    <div class="col-sm-3"><label class="form-label" for="ut-filter-status"><?= Text::_('COM_USERTOOLS_FILTER_STATUS') ?></label>
      <select class="form-select" id="ut-filter-status" name="filter_status"><option value=""><?= Text::_('COM_USERTOOLS_FILTER_ALL') ?></option>
      <?php foreach (['completed', 'failed', 'guarded', 'running'] as $status) : ?><option value="<?= $status ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= Text::_($states[$status]) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-2 d-flex gap-2"><button class="btn btn-primary"><?= Text::_('COM_USERTOOLS_FILTER_APPLY') ?></button>
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=reports') ?>"><?= Text::_('JCLEAR') ?></a></div>
  </form>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php elseif (!$this->page['items']) : ?>
    <section class="card"><div class="card-body">
      <h2 class="h5"><?= Text::_('COM_USERTOOLS_REPORTS_EMPTY') ?></h2>
      <p><?= Text::_('COM_USERTOOLS_REPORTS_EMPTY_DESC') ?></p>
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_scheduler&view=tasks') ?>"><?= Text::_('COM_USERTOOLS_OPEN_TASKS') ?></a>
    </div></section>
  <?php else : ?>
    <div class="table-responsive"><table class="table table-striped">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_REPORTS') ?></caption>
      <thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_DATE_UTC') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_TASK') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_FILTER_MODE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_FILTER_STATUS') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_STAT_CANDIDATES') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STAT_BLOCKED') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STAT_ERRORS') ?></th><th scope="col"><span class="visually-hidden"><?= Text::_('COM_USERTOOLS_DETAIL') ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($this->page['items'] as $item) :
          $status = (string) ($item['status'] ?? 'unreadable');
          $badge  = ['completed' => 'success', 'failed' => 'danger', 'guarded' => 'warning', 'running' => 'secondary'][$status] ?? 'secondary';
          ?>
        <tr><td><?= $this->escape($item['started_at'] ?? '—') ?></td>
          <td><?= $this->escape($item['task_title'] ?? '') ?> <small class="text-muted">#<?= (int) ($item['task_id'] ?? 0) ?></small></td>
          <td><?= Text::_(!empty($item['dry_run']) ? 'COM_USERTOOLS_MODE_DRY' : 'COM_USERTOOLS_MODE_REAL') ?></td>
          <td><span class="badge text-bg-<?= $badge ?>"><?= Text::_($states[$status] ?? 'COM_USERTOOLS_STATUS_UNREADABLE') ?></span></td>
          <td><?= (int) ($item['candidates'] ?? 0) ?></td><td><?= (int) ($item['blocked'] ?? 0) ?></td><td><?= (int) ($item['errors'] ?? 0) ?></td>
          <td><a href="<?= Route::_('index.php?option=com_usertools&view=report&report_id=' . $this->escape($item['id'])) ?>"><?= Text::_('COM_USERTOOLS_VIEW_REPORT') ?></a></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <div class="ut-actions">
      <span><?= Text::plural('COM_USERTOOLS_REPORTS_TOTAL', (int) $this->page['total']) ?> · <?= Text::sprintf('COM_USERTOOLS_PAGE', $this->pageNumber) ?></span>
      <div class="d-flex gap-2">
        <?php if ($this->pageNumber > 1) : ?><a class="btn btn-outline-secondary" href="<?= $pageUrl($this->pageNumber - 1) ?>"><?= Text::_('JPREV') ?></a><?php endif; ?>
        <?php if ($this->pageNumber * 20 < $this->page['total']) : ?><a class="btn btn-outline-secondary" href="<?= $pageUrl($this->pageNumber + 1) ?>"><?= Text::_('JNEXT') ?></a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
  <p class="form-text"><?= Text::sprintf('COM_USERTOOLS_REPORTS_STORAGE', $this->escape($this->directory)) ?></p>
</div>
