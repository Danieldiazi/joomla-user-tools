<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Delete\HtmlView $this */
$layouts    = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review     = $this->review;
$candidates = $review['candidates'];
$steps      = ['setup' => 'COM_USERTOOLS_STEP_PERIOD', 'review' => 'COM_USERTOOLS_STEP_SELECT_DELETE', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
$statusKey  = ['all' => 'COM_USERTOOLS_DELETE_STATUS_ALL', 'blocked' => 'COM_USERTOOLS_DELETE_STATUS_BLOCKED', 'active' => 'COM_USERTOOLS_DELETE_STATUS_ACTIVE'][$review['status']] ?? 'COM_USERTOOLS_DELETE_STATUS_ALL';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'review', 'label' => 'COM_USERTOOLS_DELETE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_DELETE_REVIEW_HEADING') ?></h2>
  <p class="lead"><?= Text::plural('COM_USERTOOLS_DELETE_FOUND', count($candidates)) ?></p>
  <div class="alert alert-info"><?= Text::sprintf('COM_USERTOOLS_DELETE_CUTOFF_NOTE', $this->escape($review['cutoff']), Text::_($statusKey)) ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=delete') ?>" data-ut-suspension-form>
    <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
    <input type="hidden" name="selected" value="[]" data-ut-selected>
    <input type="hidden" name="selection_ready" value="0" data-ut-selection-ready>
    <section class="card ut-card"><div class="card-body">
    <?php if ($candidates) : ?>
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="ut-delete-all" data-ut-suspend-all><label class="form-check-label" for="ut-delete-all"><?= Text::_('COM_USERTOOLS_SELECT_ALL') ?></label></div>
        <?php if (count($candidates) > 8) : ?>
        <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" data-ut-filter="ut-delete-table">
        <?php endif; ?>
      </div>
      <div class="ut-table-scroll"><table class="table table-sm table-striped" id="ut-delete-table"><thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_DELETE_COLUMN') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_NAME') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REGISTERED_UTC') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_GROUPS') ?></th></tr></thead><tbody>
      <?php foreach ($candidates as $user) : ?>
        <tr><td><input class="form-check-input" type="checkbox" data-ut-suspend-id="<?= (int) $user->id ?>" aria-label="<?= Text::sprintf('COM_USERTOOLS_DELETE_USER', $this->escape($user->username)) ?>"<?= in_array($user->id, $review['selected'], true) ? ' checked' : '' ?>></td>
          <td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $user->id) ?>" target="_blank" rel="noopener"><?= $this->escape($user->username) ?></a></td>
          <td><?= $this->escape($user->name) ?></td><td><?= $this->escape($user->email) ?></td>
          <td><span class="badge <?= $user->block ? 'bg-secondary' : 'bg-success' ?>"><?= Text::_($user->block ? 'COM_USERTOOLS_STATE_BLOCKED' : 'COM_USERTOOLS_STATE_ACTIVE') ?></span></td>
          <td><?= $this->date($user->lastvisitDate) ?></td><td><?= $this->date($user->registerDate) ?></td><td><?= $this->groupTitles($user->groups, $this->groups) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table><p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p></div>
    <?php else : ?>
      <p class="mb-0"><?= Text::_('COM_USERTOOLS_INACTIVE_NONE') ?></p>
    <?php endif; ?>
      <p class="text-muted mt-2 mb-0" data-ut-suspension-count aria-live="polite"></p>
    </div></section>
    <?php if ($candidates) : ?>
      <div class="alert alert-danger"><?= Text::_('COM_USERTOOLS_DELETE_WARNING') ?></div>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_CONFIRM_DELETE', 'count' => count($review['selected']), 'threshold' => $review['threshold'], 'dynamic' => true, 'help' => 'COM_USERTOOLS_TYPE_COUNT_DELETE_HELP'], $layouts) ?>
    <?php endif; ?>
    <noscript><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <div class="ut-actions">
      <button class="btn btn-outline-secondary" name="task" value="delete.back" formnovalidate><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></button>
      <?php if ($candidates) : ?><button class="btn btn-danger" name="task" value="delete.delete" data-ut-suspend-submit disabled><?= Text::_('COM_USERTOOLS_DELETE_SELECTED') ?></button><?php endif; ?>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
