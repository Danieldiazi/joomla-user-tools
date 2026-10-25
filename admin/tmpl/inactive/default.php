<?php

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Inactive\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$steps   = ['setup' => 'COM_USERTOOLS_STEP_PERIOD', 'review' => 'COM_USERTOOLS_STEP_SELECT', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'setup', 'label' => 'COM_USERTOOLS_INACTIVE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_INACTIVE_SETUP_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_INACTIVE_SETUP_INTRO') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=inactive') ?>">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card ut-card"><div class="card-body">
          <h3 class="h5"><?= Text::_('COM_USERTOOLS_PERIOD_HEADING') ?></h3>
          <label class="form-label" for="ut-days"><?= Text::_('COM_USERTOOLS_DAYS_LABEL') ?></label>
          <input class="form-control ut-count-input" id="ut-days" type="number" name="days" value="<?= (int) $this->draft['days'] ?>" min="<?= InactivityPolicy::MIN_DAYS ?>" max="<?= InactivityPolicy::MAX_DAYS ?>" required>
          <div class="form-text mb-3"><?= Text::_('COM_USERTOOLS_DAYS_HELP') ?></div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_never" value="1" id="ut-include-never"<?= $this->draft['includeNever'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-include-never"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER') ?></label>
            <div class="form-text"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER_HELP') ?></div>
          </div>
          <div class="alert alert-info mt-3 mb-0"><?= Text::_('COM_USERTOOLS_SEARCH_NOTE') ?></div>
        </div></div>
      </div>
      <div class="col-lg-6">
        <?= LayoutHelper::render('usertools.exclusions', ['exclusions' => $this->draft['exclusions'], 'groups' => $this->groups, 'idPrefix' => 'ut-inactive', 'saveNote' => 'COM_USERTOOLS_EXCLUSIONS_SAVED_ON_SEARCH'], $layouts) ?>
      </div>
    </div>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('JCANCEL') ?></a>
      <button class="btn btn-primary" name="task" value="inactive.search"><?= Text::_('COM_USERTOOLS_SEARCH_INACTIVE') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
