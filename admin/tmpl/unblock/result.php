<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Unblock\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_UNBLOCK_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_UNBLOCK_RESULT_HEADING') ?></h2>
  <p class="lead"><?= $this->escape($result['label']) ?></p>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_UNBLOCK_STAT_UNBLOCKED' => count($result['unblocked']), 'COM_USERTOOLS_STAT_SKIPPED' => count($result['skipped']), 'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($result['unblocked']) : ?>
  <section class="card ut-card" data-ut-unblocked="<?= count($result['unblocked']) ?>"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_UNBLOCKED_HEADING') ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_actionlogs&view=actionlogs&filter[extension]=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_ACTION_LOG') ?></a></div>
    <ul class="mb-0"><?php foreach ($result['unblocked'] as $row) : ?><li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['skipped']) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_UNBLOCK_SKIPPED_RESULT') ?></h3>
    <ul class="mb-0"><?php foreach ($result['skipped'] as $row) : ?><li><strong><?= $this->escape($row['username']) ?></strong>: <?= Text::_($row['message']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_UNBLOCK_ERRORS_HEADING') ?></h3>
    <ul class="mb-0"><?php foreach ($result['errors'] as $row) : ?><li><strong><?= $this->escape($row['username']) ?></strong>: <?= Text::_($row['message']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=unblock') ?>"><?= Text::_('COM_USERTOOLS_UNBLOCK_OTHER') ?></a>
  </div>
</div>
