<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\BulkNameUpdates;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Names\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$selected = array_map('intval', $this->review['selected'] ?? []);
$candidates = count(array_filter($this->review['rows'], static fn (array $row): bool => $row['status'] === BulkNameUpdates::UPDATE));

?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_NAMES_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_NAMES_REVIEW') ?></h2>
  <p><?= Text::sprintf('COM_USERTOOLS_NAMES_REVIEW_COUNT', count($selected)) ?> <?= Text::_('COM_USERTOOLS_NAMES_REVIEW_HELP') ?></p>
  <section class="card ut-card"><div class="card-body"><div class="table-responsive">
    <table class="table table-striped table-sm" id="ut-names-table">
      <thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_NAMES_SELECT') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_NAMES_BEFORE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_NAMES_AFTER') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th></tr></thead>
      <tbody>
      <?php foreach ($this->review['rows'] as $index => $row) : ?>
      <tr data-ut-names-row="<?= (int) $row['line'] ?>" data-ut-names-status="<?= $this->escape($row['status']) ?>">
        <td><?php if ($row['status'] === BulkNameUpdates::UPDATE) : ?>
          <input class="form-check-input" type="checkbox" data-ut-name-choice value="<?= (int) $index ?>" form="ut-names-apply" aria-label="<?= Text::sprintf('COM_USERTOOLS_NAMES_SELECT_ROW', $this->escape($row['username'])) ?>"<?= in_array($index, $selected, true) ? ' checked' : '' ?>>
          <?php else : ?>—<?php endif; ?></td>
        <td><?= $this->escape($row['username'] !== '' ? $row['username'] : '—') ?></td>
        <td><?= $row['id'] > 0 ? $this->escape($row['before']) : '—' ?></td>
        <td><?= $this->escape($row['after'] !== '' ? $row['after'] : '—') ?></td>
        <td><?= Text::_('COM_USERTOOLS_NAMES_STATUS_' . strtoupper($row['status'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></section>
  <?php if ($candidates > 0) : ?>
  <?php if (!$selected) : ?><p class="alert alert-info" data-ut-names-none-selected><?= Text::_('COM_USERTOOLS_NAMES_NONE_SELECTED') ?></p><?php endif; ?>
  <form id="ut-names-apply" method="post" action="<?= Route::_('index.php?option=com_usertools&view=names') ?>" data-ut-names-review>
    <input type="hidden" name="selected" data-ut-names-selected value="[]">
    <input type="hidden" name="selection_ready" data-ut-names-ready value="0">
    <div class="form-check my-3">
      <input class="form-check-input" type="checkbox" name="confirm" value="1" id="ut-names-confirm" required>
      <label class="form-check-label" for="ut-names-confirm"><?= Text::_('COM_USERTOOLS_NAMES_CONFIRM') ?></label>
    </div>
    <input type="hidden" name="preview" value="<?= $this->escape($this->review['nonce']) ?>">
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=names') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
      <button class="btn btn-primary" name="task" value="names.apply" data-ut-names-submit disabled><?= Text::_('COM_USERTOOLS_NAMES_APPLY') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
  <?php else : ?>
  <p class="alert alert-info" data-ut-names-none><?= Text::_('COM_USERTOOLS_NAMES_NO_CHANGES') ?></p>
  <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=names') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
  <?php endif; ?>
</div>
