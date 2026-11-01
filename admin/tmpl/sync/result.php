<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sync\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result = $this->result;
$open = array_values(array_filter($result['saved'], static fn (array $r): bool => ($r['sessions'] ?? '') === 'open'));
$unknown = array_values(array_filter($result['saved'], static fn (array $r): bool => ($r['sessions'] ?? '') === 'unknown'));
$unmailed = array_values(array_filter($result['created'], static fn (array $r): bool => empty($r['mailed'])));
$action = Route::_('index.php?option=com_usertools&view=sync');
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_SYNC_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SYNC_RESULT') ?></h2>
  <div class="ut-stats">
    <?php foreach (['saved' => 'COM_USERTOOLS_SYNC_RESULT_SAVED', 'created' => 'COM_USERTOOLS_SYNC_RESULT_CREATED', 'changed' => 'COM_USERTOOLS_SYNC_RESULT_CHANGED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $key => $label) : ?>
    <div class="card"><div class="card-body"><strong data-ut-sync-result-total="<?= $key ?>"><?= count($result[$key]) ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($result['discarded'])) : ?><p class="alert alert-info" data-ut-sync-discarded><?= Text::plural('COM_USERTOOLS_SYNC_DISCARDED', (int) $result['discarded']) ?></p><?php endif; ?>
  <?php if ($open) : ?>
  <div class="alert alert-danger" data-ut-sync-sessions="open">
    <p><?= Text::sprintf('COM_USERTOOLS_SYNC_SESSIONS_OPEN', $this->escape(implode(', ', array_column($open, 'username')))) ?></p>
    <?php foreach ($open as $row) : ?>
    <form method="post" action="<?= $action ?>" class="mb-2" data-ut-sync-retry="<?= (int) $row['id'] ?>">
      <input type="hidden" name="account" value="<?= (int) $row['id'] ?>">
      <button class="btn btn-outline-danger btn-sm" name="task" value="sync.sessions"><?= Text::sprintf('COM_USERTOOLS_SYNC_SESSIONS_RETRY', $this->escape($row['username'])) ?></button>
      <?= HTMLHelper::_('form.token') ?>
    </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($unknown) : ?><div class="alert alert-warning" data-ut-sync-sessions="unknown"><?= Text::sprintf('COM_USERTOOLS_SYNC_SESSIONS_UNKNOWN', $this->escape(implode(', ', array_column($unknown, 'username')))) ?></div><?php endif; ?>
  <?php if ($unmailed) : ?><div class="alert alert-warning" data-ut-sync-unmailed><?= Text::sprintf('COM_USERTOOLS_SYNC_UNMAILED', $this->escape(implode(', ', array_column($unmailed, 'username')))) ?></div><?php endif; ?>
  <?php foreach (['saved' => 'COM_USERTOOLS_SYNC_RESULT_SAVED', 'created' => 'COM_USERTOOLS_SYNC_RESULT_CREATED', 'changed' => 'COM_USERTOOLS_SYNC_RESULT_CHANGED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $status => $label) : ?>
  <?php if ($result[$status]) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_($label) ?></h3>
    <ul><?php foreach ($result[$status] as $row) : ?>
      <li data-ut-sync-result="<?= $status ?>" data-ut-sync-id="<?= (int) $row['id'] ?>"<?= $status === 'saved' ? ' data-ut-sync-session="' . $this->escape($row['sessions'] ?? '') . '"' : '' ?>>
        <?= $this->escape($row['username']) ?><?= $row['id'] > 0 ? ' · ID ' . (int) $row['id'] : '' ?>
        <?php if ($row['add']) : ?> — <?= Text::sprintf('COM_USERTOOLS_SYNC_ADDED_TO', $this->escape($this->groupList($row['add']))) ?><?php endif; ?>
        <?php if ($row['remove']) : ?> — <?= Text::sprintf('COM_USERTOOLS_SYNC_REMOVED_FROM', $this->escape($this->groupList($row['remove']))) ?><?php endif; ?>
        <?php if (!empty($row['default'])) : ?> — <?= Text::sprintf('COM_USERTOOLS_SYNC_DEFAULT_ADDED', $this->escape($this->groupTitle(\Danieldiazi\Component\Usertools\Administrator\Service\GroupSync::DEFAULT_GROUP))) ?><?php endif; ?>
      </li>
    <?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php endforeach; ?>
  <p class="text-muted"><?= Text::plural('COM_USERTOOLS_SYNC_RESULT_UNCHANGED', (int) $result['unchanged']) ?></p>
  <div class="ut-actions"><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= $action ?>"><?= Text::_('COM_USERTOOLS_SYNC_AGAIN') ?></a></div>
</div>
