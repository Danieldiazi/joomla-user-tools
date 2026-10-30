<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupMove;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Move\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review  = $this->review;
$target  = $this->escape($this->groupTitle($review['target']));
$access  = ['none' => 'COM_USERTOOLS_MOVE_ACCESS_NONE', 'backend' => 'COM_USERTOOLS_MOVE_ACCESS_BACKEND', 'super' => 'COM_USERTOOLS_MOVE_ACCESS_SUPER'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_MOVE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::sprintf('COM_USERTOOLS_MOVE_REVIEW_HEADING', $target) ?></h2>
  <p><?= Text::_('COM_USERTOOLS_MOVE_REVIEW_HELP') ?></p>

  <section class="card ut-card"><div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" id="ut-move-table">
        <caption class="visually-hidden"><?= Text::sprintf('COM_USERTOOLS_MOVE_REVIEW_HEADING', $target) ?></caption>
        <thead><tr>
          <th scope="col"><?= Text::_('COM_USERTOOLS_MOVE_COL_GROUP') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_MOVE_COL_FROM') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_MOVE_COL_RESULT') ?></th>
          <th scope="col" class="text-end pe-3"><?= Text::_('COM_USERTOOLS_MOVE_COL_ACCOUNTS') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_MOVE_COL_GAINED') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_MOVE_COL_LOST') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($review['rows'] as $row) : ?>
          <tr data-ut-move-row="<?= (int) $row['id'] ?>" data-ut-move-status="<?= $this->escape($row['status']) ?>">
            <th scope="row"><?= $this->escape($this->groupTitle($row['id'])) ?>
              <?php if (count($row['subtree']) > 1) : ?><small class="d-block text-body-secondary fw-normal"><?= Text::plural('COM_USERTOOLS_MOVE_WITH_SUBGROUPS', count($row['subtree']) - 1) ?></small><?php endif; ?></th>
            <td><?= $this->escape($this->groupTitle($row['from'])) ?></td>
            <td>
              <?php if ($row['status'] === GroupMove::MOVE) : ?>
              <span class="badge text-bg-primary"><?= Text::_('COM_USERTOOLS_MOVE_STATUS_MOVE') ?></span>
              <?php elseif ($row['status'] === GroupMove::COVERED) : ?>
              <span class="badge text-bg-info"><?= Text::_('COM_USERTOOLS_MOVE_STATUS_COVERED') ?></span>
              <small class="d-block text-body-secondary"><?= Text::sprintf('COM_USERTOOLS_MOVE_COVERED', $this->escape($this->groupTitle($row['via']))) ?></small>
              <?php elseif ($row['status'] === GroupMove::SAME) : ?>
              <span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_MOVE_STATUS_SAME') ?></span>
              <?php else : ?>
              <span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_MOVE_STATUS_REFUSED') ?></span>
              <small class="d-block" data-ut-move-reason="<?= $this->escape($row['reason']) ?>"><?= Text::sprintf($row['reason'], $target) ?></small>
              <?php if ($row['accounts']) : ?>
              <ul class="small mb-0 ps-3" data-ut-move-accounts>
                <?php foreach (array_slice($row['accounts'], 0, 10) as $change) : ?>
                <li><?= Text::sprintf('COM_USERTOOLS_MOVE_ACCESS_CHANGE', $this->escape($change['username']), Text::_($access[$change['before']]), Text::_($access[$change['after']])) ?></li>
                <?php endforeach; ?>
                <?php if (count($row['accounts']) > 10) : ?><li><?= Text::sprintf('COM_USERTOOLS_MOVE_ACCOUNTS_MORE', count($row['accounts']) - 10) ?></li><?php endif; ?>
              </ul>
              <?php endif; ?>
              <?php if ($row['access']) : ?>
              <ul class="small mb-0 ps-3">
                <?php foreach ($row['access'] as $change) : ?>
                <li><?= Text::sprintf('COM_USERTOOLS_MOVE_ACCESS_CHANGE', $this->escape($this->groupTitle($change['id'])), Text::_($access[$change['before']]), Text::_($access[$change['after']])) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="text-end pe-3"><?= isset($review['accounts'][$row['id']]) ? (int) $review['accounts'][$row['id']] : '—' ?></td>
            <?php $moves = $row['status'] === GroupMove::MOVE; ?>
            <td><?= $moves && $row['gained'] ? $this->escape($this->levelTitles($row['gained'])) : '—' ?></td>
            <td><?= $moves && $row['lost'] ? $this->escape($this->levelTitles($row['lost'])) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="form-text mb-0"><?= Text::_('COM_USERTOOLS_MOVE_ACCOUNTS_HELP') ?></p>
  </div></section>

  <?php if ($review['moving'] === 0) : ?>
    <div class="alert alert-info" data-ut-move-nothing><?= Text::_('COM_USERTOOLS_MOVE_NOTHING') ?></div>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=move') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
    </div>
  <?php else : ?>
    <div class="alert alert-info" data-ut-move-sessions><?= Text::plural('COM_USERTOOLS_MOVE_SESSIONS', $review['sessions']) ?>
      <?php if ($review['self']) : ?> <?= Text::_('COM_USERTOOLS_MOVE_SESSIONS_SELF') ?><?php endif; ?></div>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=move') ?>" data-ut-move-apply>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_MOVE_CONFIRM', 'count' => $review['moving'], 'threshold' => 0], $layouts) ?>
      <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
      <div class="ut-actions">
        <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=move') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
        <button class="btn btn-primary" name="task" value="move.apply"><?= Text::plural('COM_USERTOOLS_MOVE_APPLY', $review['moving']) ?></button>
      </div>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  <?php endif; ?>
</div>
