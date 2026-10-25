<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Inactive\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
$steps   = ['setup' => 'COM_USERTOOLS_STEP_PERIOD', 'review' => 'COM_USERTOOLS_STEP_SELECT', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'result', 'label' => 'COM_USERTOOLS_INACTIVE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_INACTIVE_RESULT_HEADING') ?></h2>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_STAT_SUSPENDED' => count($result['blocked']), 'COM_USERTOOLS_STAT_SKIPPED' => (int) $result['skipped'], 'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($result['blocked']) : ?>
  <section class="card ut-card"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_SUSPENDED_HEADING') ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_users&view=users&filter[state]=1') ?>"><?= Text::_('COM_USERTOOLS_OPEN_BLOCKED_USERS') ?></a></div>
    <p class="form-text"><?= Text::_('COM_USERTOOLS_BLOCKED_UNDO_HELP') ?></p>
    <ul class="mb-0"><?php foreach ($result['blocked'] as $row) : ?><li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_SUSPEND_ERRORS_HEADING') ?></h3>
    <ul class="mb-0"><?php foreach ($result['errors'] as $row) : ?><li><strong><?= $this->escape($row['username']) ?></strong>: <?= Text::_($row['message']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=inactive') ?>"><?= Text::_('COM_USERTOOLS_NEW_REVIEW') ?></a>
  </div>
</div>
