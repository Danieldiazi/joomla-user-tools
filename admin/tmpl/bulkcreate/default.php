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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Bulkcreate\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'edit', 'label' => 'COM_USERTOOLS_BULKCREATE_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_BULKCREATE_EDIT') ?></h2>
  <p class="lead" data-ut-tour="rules"><?= Text::_('COM_USERTOOLS_BULKCREATE_INTRO') ?></p>
  <?php if ($this->job) : ?>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=bulkcreate') ?>" class="alert alert-warning" role="alert" data-ut-job-pending>
    <strong class="d-block"><?= Text::_('COM_USERTOOLS_JOB_PENDING_HEADING') ?></strong>
    <p><?= Text::sprintf('COM_USERTOOLS_JOB_PENDING_TEXT', (int) $this->job['processed'], (int) $this->job['total']) ?></p>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=bulkcreate&layout=progress') ?>"><?= Text::_('COM_USERTOOLS_PROGRESS_CONTINUE') ?></a>
    <button class="btn btn-outline-danger" name="task" value="bulkcreate.discard"><?= Text::_('COM_USERTOOLS_PROGRESS_DISCARD') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
  <?php endif; ?>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=bulkcreate') ?>">
    <div class="card ut-card" data-ut-tour="list"><div class="card-body">
      <label class="form-label fw-bold" for="ut-bulkcreate-lines"><?= Text::_('COM_USERTOOLS_BULKCREATE_INPUT') ?></label>
      <p class="form-text" id="ut-bulkcreate-help"><?= Text::_('COM_USERTOOLS_BULKCREATE_INPUT_HELP') ?></p>
      <textarea class="form-control ut-names" id="ut-bulkcreate-lines" name="lines" rows="14" required
        aria-describedby="ut-bulkcreate-help" placeholder="jperez&#10;mlopez;María López&#10;agarcia;;;12"><?= $this->escape($this->draft['text']) ?></textarea>
    </div></div>
    <div class="alert alert-info mt-3"><?= Text::_('COM_USERTOOLS_BULKCREATE_NOTICE') ?></div>
    <div class="ut-actions" data-ut-tour="actions">
      <button class="btn btn-primary" name="task" value="bulkcreate.preview"><?= Text::_('COM_USERTOOLS_NEXT_REVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
