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
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Handover\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result = $this->result;
$closing = $result['closing'];
$source = $result['sourceRecord'];
$sourceLabel = $source ? Text::sprintf('COM_USERTOOLS_ACCOUNT_LABEL', $this->escape($source->username), $this->escape($source->name), $this->escape($source->email), (int) $source->id) : '#' . (int) $result['source'];
$target = $result['targetRecord'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_HANDOVER_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_HANDOVER_RESULT') ?></h2>
  <p><?= Text::sprintf('COM_USERTOOLS_HANDOVER_RESULT_ACCOUNTS', $sourceLabel, $target ? $this->escape($target->username) . ' · ID ' . (int) $target->id : '#' . (int) $result['target']) ?></p>
  <div class="ut-stats">
    <?php foreach (['transferred' => 'COM_USERTOOLS_HANDOVER_TRANSFERRED', 'changed' => 'COM_USERTOOLS_HANDOVER_SKIPPED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $key => $label) : ?>
    <div class="card"><div class="card-body"><strong data-ut-handover-total="<?= $key ?>"><?= count($result[$key]) ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($result['discarded'])) : ?><p class="alert alert-info" data-ut-handover-discarded><?= Text::plural('COM_USERTOOLS_HANDOVER_DISCARDED', (int) $result['discarded']) ?></p><?php endif; ?>
  <?php foreach (['transferred' => 'COM_USERTOOLS_HANDOVER_TRANSFERRED', 'changed' => 'COM_USERTOOLS_HANDOVER_SKIPPED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $status => $label) : ?>
  <?php if ($result[$status]) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_($label) ?></h3>
    <?php if ($status === 'changed') : ?><p><?= Text::_('COM_USERTOOLS_HANDOVER_SKIPPED_HELP') ?></p><?php endif; ?>
    <ul><?php foreach ($result[$status] as $row) : ?>
      <li data-ut-handover-result="<?= $status ?>" data-ut-handover-id="<?= (int) $row['id'] ?>"><?= $this->escape($row['title']) ?> (ID <?= (int) $row['id'] ?>)</li>
    <?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php endforeach; ?>
  <section class="card ut-card" aria-labelledby="ut-handover-closing"><div class="card-body">
    <h3 class="h5" id="ut-handover-closing"><?= Text::_('COM_USERTOOLS_HANDOVER_CLOSING') ?></h3>
    <?php if ($closing['pending']) : ?>
    <div class="alert alert-warning" data-ut-handover-closing="pending"><?= Text::plural('COM_USERTOOLS_HANDOVER_CLOSING_PENDING', count($closing['pending'])) ?></div>
    <?php else : ?>
    <p data-ut-handover-closing="ready"><?= Text::_('COM_USERTOOLS_HANDOVER_CLOSING_READY') ?></p>
    <?php endif; ?>
    <?php if ($closing['new']) : ?><div class="alert alert-info" data-ut-handover-new><?= Text::plural('COM_USERTOOLS_HANDOVER_CLOSING_NEW', count($closing['new'])) ?></div><?php endif; ?>
    <?php if ($closing['left']) : ?><p data-ut-handover-left><?= Text::plural('COM_USERTOOLS_HANDOVER_CLOSING_LEFT', count($closing['left'])) ?></p><?php endif; ?>
    <?php if (!$closing['pending'] && $source) : ?>
    <p><?= Text::_('COM_USERTOOLS_HANDOVER_CLOSING_NEXT') ?></p>
    <div class="ut-actions">
      <?php if ($result['actions']['block']) : ?>
      <a class="btn btn-outline-secondary" data-ut-handover-action="block" href="<?= Route::_('index.php?option=com_usertools&task=inactive.account&' . Session::getFormToken() . '=1&account=' . (int) $source->id) ?>"><?= Text::_('COM_USERTOOLS_PROFILE_ACTION_BLOCK') ?></a>
      <?php endif; ?>
      <?php if ($result['actions']['anonymize']) : ?>
      <a class="btn btn-outline-secondary" data-ut-handover-action="anonymize" href="<?= Route::_('index.php?option=com_usertools&view=anonymize&account=' . (int) $source->id) ?>"><?= Text::_('COM_USERTOOLS_PROFILE_ACTION_ANONYMIZE') ?></a>
      <?php endif; ?>
      <?php if ($result['canDelete']) : ?>
      <a class="btn btn-outline-danger" data-ut-handover-action="delete" href="<?= Route::_('index.php?option=com_usertools&view=delete') ?>"><?= Text::_('COM_USERTOOLS_HANDOVER_DELETE_TOOL') ?></a>
      <?php endif; ?>
    </div>
    <?php if (!$result['actions']['block'] && !$result['actions']['anonymize']) : ?><p class="form-text" data-ut-handover-no-actions><?= Text::_('COM_USERTOOLS_HANDOVER_NO_ACTIONS') ?></p><?php endif; ?>
    <?php endif; ?>
  </div></section>
  <div class="ut-actions">
    <?php if ($source) : ?><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=profile&account=' . (int) $source->id) ?>"><?= Text::_('COM_USERTOOLS_HANDOVER_OPEN_PROFILE') ?></a><?php endif; ?>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=handover' . ($source ? '&account=' . (int) $source->id : '')) ?>"><?= Text::_('COM_USERTOOLS_HANDOVER_AGAIN') ?></a>
  </div>
</div>
