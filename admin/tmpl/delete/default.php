<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Delete\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$steps   = ['setup' => 'COM_USERTOOLS_STEP_PERIOD', 'review' => 'COM_USERTOOLS_STEP_SELECT_DELETE', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'setup', 'label' => 'COM_USERTOOLS_DELETE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_DELETE_SETUP_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_DELETE_SETUP_INTRO') ?></p>
  <div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_DELETE_IRREVERSIBLE') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=delete') ?>">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card ut-card"><div class="card-body">
          <h3 class="h5"><?= Text::_('COM_USERTOOLS_PERIOD_HEADING') ?></h3>
          <label class="form-label" for="ut-days"><?= Text::_('COM_USERTOOLS_DAYS_LABEL') ?></label>
          <input class="form-control ut-count-input" id="ut-days" type="number" name="days" value="<?= (int) $this->draft['days'] ?>" min="<?= InactivityPolicy::MIN_DAYS ?>" max="<?= InactivityPolicy::MAX_DAYS ?>" required>
          <div class="form-text mb-3"><?= Text::_('COM_USERTOOLS_DELETE_DAYS_HELP') ?></div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="include_never" value="1" id="ut-include-never"<?= $this->draft['includeNever'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-include-never"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER') ?></label>
            <div class="form-text"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER_HELP') ?></div>
          </div>
          <fieldset>
            <legend class="form-label fw-bold fs-6"><?= Text::_('COM_USERTOOLS_DELETE_STATUS') ?></legend>
            <?php foreach (['blocked' => 'COM_USERTOOLS_DELETE_STATUS_BLOCKED', 'all' => 'COM_USERTOOLS_DELETE_STATUS_ALL', 'active' => 'COM_USERTOOLS_DELETE_STATUS_ACTIVE'] as $value => $label) : ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="status" value="<?= $value ?>" id="ut-status-<?= $value ?>"<?= $this->draft['status'] === $value ? ' checked' : '' ?>>
              <label class="form-check-label" for="ut-status-<?= $value ?>"><?= Text::_($label) ?></label>
            </div>
            <?php endforeach; ?>
            <div class="form-text"><?= Text::_('COM_USERTOOLS_DELETE_STATUS_HELP') ?></div>
          </fieldset>
          <div class="alert alert-info mt-3 mb-0"><?= Text::_('COM_USERTOOLS_DELETE_SEARCH_NOTE') ?></div>
        </div></div>
      </div>
      <div class="col-lg-6">
        <?= LayoutHelper::render('usertools.exclusions', ['exclusions' => $this->draft['exclusions'], 'groups' => $this->groups, 'idPrefix' => 'ut-delete', 'saveNote' => 'COM_USERTOOLS_EXCLUSIONS_SAVED_ON_SEARCH', 'title' => 'COM_USERTOOLS_EXCLUSIONS_DELETE_TITLE'], $layouts) ?>
      </div>
    </div>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('JCANCEL') ?></a>
      <button class="btn btn-primary" name="task" value="delete.search"><?= Text::_('COM_USERTOOLS_SEARCH_INACTIVE') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
