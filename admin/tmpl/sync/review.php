<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupSync;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sync\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review = $this->review;
$plan = $review['plan'];
$draft = $review['draft'];
$rows = $plan['rows'];
$counts = array_count_values(array_column($rows, 'status'));
$back = Route::_('index.php?option=com_usertools&view=sync');
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_SYNC_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SYNC_STEP_REVIEW') ?></h2>
  <p data-ut-sync-summary data-ut-sync-count="<?= (int) $review['count'] ?>">
    <?= Text::sprintf($draft['mode'] === GroupSync::FULL ? 'COM_USERTOOLS_SYNC_SUMMARY_FULL' : 'COM_USERTOOLS_SYNC_SUMMARY_PARTIAL', $this->escape($this->groupTitle((int) $draft['root']))) ?>
    <?= Text::_('COM_USERTOOLS_SYNC_NOTHING_YET') ?>
  </p>
  <div class="ut-stats">
    <?php foreach ([GroupSync::CREATE, GroupSync::CHANGE, GroupSync::LEAVE, GroupSync::UNCHANGED, GroupSync::PROTECTED_ACCOUNT] as $status) : ?>
    <div class="card"><div class="card-body"><strong data-ut-sync-total="<?= $status ?>"><?= (int) ($counts[$status] ?? 0) ?></strong><span><?= Text::_('COM_USERTOOLS_SYNC_STATUS_' . strtoupper($status)) ?></span></div></div>
    <?php endforeach; ?>
    <div class="card"><div class="card-body"><strong data-ut-sync-total="errors"><?= (int) $review['errors'] ?></strong><span><?= Text::_('COM_USERTOOLS_SYNC_STAT_ERRORS') ?></span></div></div>
  </div>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_SYNC_MANAGED') ?></h3>
    <p><?= Text::_('COM_USERTOOLS_SYNC_MANAGED_HELP') ?></p>
    <ul data-ut-sync-managed><?php foreach ($plan['managed'] as $id) : ?><li><?= $this->escape($this->groupPath((int) $id)) ?> (#<?= (int) $id ?>)</li><?php endforeach; ?></ul>
    <?php if ($plan['protected']) : ?>
    <p class="mb-1"><?= Text::_('COM_USERTOOLS_SYNC_PROTECTED_GROUPS') ?></p>
    <ul data-ut-sync-protected><?php foreach ($plan['protected'] as $id) : ?><li><?= $this->escape($this->groupPath((int) $id)) ?> (#<?= (int) $id ?>)</li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div></section>
  <section class="card ut-card"><div class="card-body"><div class="table-responsive ut-table-scroll">
    <table class="table table-striped table-sm" id="ut-sync-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_SYNC_TABLE') ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_SYNC_COL_LINE') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_SYNC_COL_ACCOUNT') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_SYNC_COL_ADD') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_SYNC_COL_REMOVE') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_SYNC_COL_KEEP') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $row) : ?>
      <tr data-ut-sync-row="<?= (int) $row['id'] ?>" data-ut-sync-status="<?= $this->escape($row['status']) ?>">
        <td><?= $row['line'] > 0 ? (int) $row['line'] : Text::_('COM_USERTOOLS_SYNC_NOT_LISTED') ?></td>
        <td><?= $this->escape($row['username']) ?><?= $row['id'] > 0 ? ' · ID ' . (int) $row['id'] : '' ?>
          <?php if ($row['status'] === GroupSync::CREATE) : ?><br><small><?= $this->escape($row['email']) ?></small><?php endif; ?></td>
        <td><?= Text::_('COM_USERTOOLS_SYNC_STATUS_' . strtoupper($row['status'])) ?>
          <?php if (!empty($row['group'])) : ?><br><small><?= Text::sprintf('COM_USERTOOLS_SYNC_GROUP_REFERENCE', $this->escape($row['group'])) ?></small><?php endif; ?>
          <?php if ($row['default']) : ?><br><small data-ut-sync-default><?= Text::sprintf('COM_USERTOOLS_SYNC_DEFAULT_ADDED', $this->escape($this->groupTitle(GroupSync::DEFAULT_GROUP))) ?></small><?php endif; ?>
          <?php if ($row['emailDiffers']) : ?><br><small data-ut-sync-email-differs><?= Text::_('COM_USERTOOLS_SYNC_EMAIL_DIFFERS') ?></small><?php endif; ?></td>
        <td><?= $this->escape($this->groupList($row['add'])) ?></td>
        <td><?= $this->escape($this->groupList($row['remove'])) ?></td>
        <td><?= $this->escape($this->groupList($row['kept'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></section>
  <?php if ($review['blocked']) : ?>
    <div class="alert alert-danger" data-ut-sync-blocked><?= Text::_('COM_USERTOOLS_SYNC_BLOCKED') ?></div>
    <a class="btn btn-outline-secondary" href="<?= $back ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
  <?php elseif ($review['count'] === 0) : ?>
    <p class="alert alert-info" data-ut-sync-none><?= Text::_('COM_USERTOOLS_SYNC_NO_CHANGES') ?></p>
    <a class="btn btn-outline-secondary" href="<?= $back ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
  <?php else : ?>
    <?php if ($review['errors'] > 0) : ?><div class="alert alert-warning" data-ut-sync-skipped><?= Text::plural('COM_USERTOOLS_SYNC_ERRORS_SKIPPED', (int) $review['errors']) ?></div><?php endif; ?>
    <?php if ($review['creates'] > 0 && !$review['mailOnline']) : ?><div class="alert alert-danger"><?= Text::_('COM_USERTOOLS_ERROR_MAIL_OFFLINE') ?></div><?php endif; ?>
    <div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_SYNC_CONFIRM_HELP') ?></div>
    <form method="post" action="<?= $back ?>" data-ut-sync-apply>
      <?php if ($review['massive']) : ?>
      <div class="card ut-card border-danger"><div class="card-body">
        <p class="mb-2" id="ut-sync-mass-help"><?= Text::sprintf('COM_USERTOOLS_SYNC_MASS', (int) $review['leaving'], (int) $plan['members']) ?></p>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="mass" value="1" id="ut-sync-mass" required aria-describedby="ut-sync-mass-help">
          <label class="form-check-label" for="ut-sync-mass"><?= Text::_('COM_USERTOOLS_SYNC_MASS_CONFIRM') ?></label>
        </div>
      </div></div>
      <?php endif; ?>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_SYNC_CONFIRM', 'count' => $review['count'], 'threshold' => $review['threshold']], $layouts) ?>
      <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
      <div class="ut-actions">
        <a class="btn btn-outline-secondary" href="<?= $back ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
        <button class="btn btn-primary" name="task" value="sync.apply"><?= Text::plural('COM_USERTOOLS_SYNC_APPLY', (int) $review['count']) ?></button>
      </div>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  <?php endif; ?>
</div>
