<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Move\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
$list    = fn (array $rows): string => implode(', ', array_map(fn (array $r) => $this->escape($r['title']), $rows));
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_MOVE_PROGRESS'], $layouts) ?>
  <?php if ($result['moved']) : ?>
  <div class="alert alert-success" data-ut-move-done="<?= count($result['moved']) ?>"><?= Text::sprintf('COM_USERTOOLS_MOVE_DONE', $this->escape($result['target']), $list($result['moved'])) ?></div>
  <?php endif; ?>
  <?php if ($result['changed']) : ?>
  <div class="alert alert-warning" data-ut-move-changed><?= Text::sprintf('COM_USERTOOLS_MOVE_SKIPPED_CHANGED', $list($result['changed'])) ?></div>
  <?php endif; ?>
  <?php if ($result['errors']) : ?>
  <div class="alert alert-danger" data-ut-move-errors><?= Text::sprintf('COM_USERTOOLS_MOVE_ERRORS', $list($result['errors'])) ?></div>
  <?php endif; ?>
  <?php $sessions = $result['sessions']; ?>
  <?php if ($sessions['open']) : ?>
  <div class="alert alert-danger" data-ut-move-sessions="open"><?= Text::sprintf('COM_USERTOOLS_MOVE_SESSIONS_OPEN', $this->escape(implode(', ', $sessions['open']))) ?></div>
  <?php endif; ?>
  <?php if ($sessions['unknown']) : ?>
  <div class="alert alert-warning" data-ut-move-sessions="unknown"><?= Text::plural('COM_USERTOOLS_MOVE_SESSIONS_UNKNOWN', $sessions['unknown']) ?></div>
  <?php endif; ?>
  <?php if ($sessions['closed']) : ?>
  <p data-ut-move-sessions="closed"><?= Text::plural('COM_USERTOOLS_MOVE_SESSIONS_CLOSED', $sessions['closed']) ?></p>
  <?php endif; ?>
  <?php if ($sessions['self']) : ?><p><?= Text::_('COM_USERTOOLS_MOVE_SESSIONS_SELF') ?></p><?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_users&view=groups') ?>"><?= Text::_('COM_USERTOOLS_MOVE_OPEN_GROUPS') ?></a>
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_actionlogs&view=actionlogs&filter[extension]=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_ACTION_LOG') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=move') ?>"><?= Text::_('COM_USERTOOLS_MOVE_AGAIN') ?></a>
  </div>
</div>
