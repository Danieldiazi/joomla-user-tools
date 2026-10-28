<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'edit', 'label' => 'COM_USERTOOLS_COMPARE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_COMPARE_EDIT_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_COMPARE_EDIT_INTRO') ?></p>
  <?php if ($this->job) : ?>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=compare') ?>" class="alert alert-warning" role="alert" data-ut-job-pending>
    <strong class="d-block"><?= Text::_('COM_USERTOOLS_JOB_PENDING_HEADING') ?></strong>
    <p><?= Text::sprintf('COM_USERTOOLS_JOB_PENDING_TEXT', (int) $this->job['processed'], (int) $this->job['total']) ?></p>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=compare&layout=progress') ?>"><?= Text::_('COM_USERTOOLS_PROGRESS_CONTINUE') ?></a>
    <button class="btn btn-outline-danger" name="task" value="compare.discard"><?= Text::_('COM_USERTOOLS_PROGRESS_DISCARD') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
  <?php endif; ?>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=compare') ?>">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card ut-card"><div class="card-body">
          <label class="form-label fw-bold" for="ut-names"><?= Text::_('COM_USERTOOLS_NAMES_LABEL') ?></label>
          <p class="form-text" id="ut-names-help"><?= Text::_('COM_USERTOOLS_NAMES_HELP') ?></p>
          <textarea class="form-control ut-names" id="ut-names" name="names" rows="14" required aria-describedby="ut-names-help" placeholder="usuario1&#10;usuario2&#10;usuario3"><?= $this->escape($this->draft['text']) ?></textarea>
          <div class="alert alert-info mt-3 mb-0"><?= Text::_('COM_USERTOOLS_NAMES_NOTE') ?></div>
        </div></div>
      </div>
      <div class="col-lg-5">
        <?= LayoutHelper::render('usertools.exclusions', ['exclusions' => $this->draft['exclusions'], 'groups' => $this->groups, 'idPrefix' => 'ut-compare', 'saveNote' => 'COM_USERTOOLS_EXCLUSIONS_SAVED_ON_CONTINUE'], $layouts) ?>
      </div>
    </div>
    <div class="ut-actions">
      <button class="btn btn-outline-secondary" name="task" value="compare.saveExclusions" formnovalidate><?= Text::_('COM_USERTOOLS_SAVE_EXCLUSIONS') ?></button>
      <button class="btn btn-primary" name="task" value="compare.preview"><?= Text::_('COM_USERTOOLS_NEXT_REVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
