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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Anonymize\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$draft   = $this->draft;
$source  = $draft['source'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_ANONYMIZE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SETUP_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_ANONYMIZE_INTRO') ?></p>
  <div class="alert alert-warning" data-ut-anonymize-what data-ut-tour="warning">
    <p class="mb-1"><strong><?= Text::_('COM_USERTOOLS_PERMANENT') ?>.</strong> <?= Text::_('COM_USERTOOLS_ANONYMIZE_IRREVERSIBLE') ?></p>
    <ul class="mb-0">
      <li><?= Text::_('COM_USERTOOLS_ANONYMIZE_WHAT_CHANGES') ?></li>
      <li><?= Text::_('COM_USERTOOLS_ANONYMIZE_WHAT_STAYS') ?></li>
      <li><?= Text::_('COM_USERTOOLS_ANONYMIZE_WHAT_OTHERS') ?></li>
    </ul>
  </div>
  <?php if (!$this->pluginReady && empty($draft['retryCleanup'])) : ?>
  <div class="alert alert-danger" data-ut-anonymize-plugin><?= Text::_('COM_USERTOOLS_ANONYMIZE_ERROR_PLUGIN') ?></div>
  <?php endif; ?>
  <?php if ($this->job) : ?>
  <div class="alert alert-info" data-ut-anonymize-pending>
    <p><?= Text::plural('COM_USERTOOLS_ANONYMIZE_PENDING', count($this->job['pending'])) ?></p>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" class="mb-0">
      <button class="btn btn-primary" name="task" value="anonymize.next"><?= Text::_('COM_USERTOOLS_ANONYMIZE_CONTINUE') ?></button>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  </div>
  <?php endif; ?>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" data-ut-anonymize-form>
    <div class="row g-4">
      <div class="col-lg-6">
        <fieldset class="card ut-card" data-ut-tour="source"><div class="card-body">
          <legend class="h5"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SOURCE') ?></legend>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="retry_cleanup" value="1" id="ut-retry-cleanup"<?= !empty($draft['retryCleanup']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-retry-cleanup"><?= Text::_('COM_USERTOOLS_ANONYMIZE_RETRY_CLEANUP') ?></label>
            <div class="form-text"><?= Text::_('COM_USERTOOLS_ANONYMIZE_RETRY_CLEANUP_HELP') ?></div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="source" value="list" id="ut-source-list"<?= $source === 'list' ? ' checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="ut-source-list"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SOURCE_LIST') ?></label>
          </div>
          <div class="ms-4 mb-3">
            <label class="form-label" for="ut-anonymize-names"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NAMES') ?></label>
            <textarea class="form-control" id="ut-anonymize-names" name="names" rows="5" aria-describedby="ut-anonymize-names-help"><?= $this->escape($draft['names']) ?></textarea>
            <div class="form-text" id="ut-anonymize-names-help"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NAMES_HELP') ?></div>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="radio" name="source" value="inactive" id="ut-source-inactive"<?= $source === 'inactive' ? ' checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="ut-source-inactive"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SOURCE_INACTIVE') ?></label>
          </div>
          <div class="ms-4 mb-3">
            <label class="form-label" for="ut-days"><?= Text::_('COM_USERTOOLS_DAYS_LABEL') ?></label>
            <input class="form-control ut-count-input" id="ut-days" type="number" name="days" value="<?= (int) $draft['days'] ?>" min="<?= InactivityPolicy::MIN_DAYS ?>" max="<?= InactivityPolicy::MAX_DAYS ?>" aria-describedby="ut-days-help">
            <div class="form-text" id="ut-days-help"><?= Text::_('COM_USERTOOLS_ANONYMIZE_DAYS_HELP') ?></div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="include_never" value="1" id="ut-include-never"<?= $draft['includeNever'] ? ' checked' : '' ?>>
              <label class="form-check-label" for="ut-include-never"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER') ?></label>
              <div class="form-text"><?= Text::_('COM_USERTOOLS_INCLUDE_NEVER_HELP') ?></div>
            </div>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="radio" name="source" value="group" id="ut-source-group"<?= $source === 'group' ? ' checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="ut-source-group"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SOURCE_GROUP') ?></label>
          </div>
          <div class="ms-4">
            <label class="form-label" for="ut-anonymize-group"><?= Text::_('COM_USERTOOLS_ANONYMIZE_GROUP') ?></label>
            <select class="form-select" name="group" id="ut-anonymize-group">
              <option value="0"><?= Text::_('COM_USERTOOLS_MOVE_TARGET_CHOOSE') ?></option>
              <?php foreach ($this->groups as $group) : ?>
              <option value="<?= (int) $group->id ?>"<?= (int) $group->id === $draft['group'] ? ' selected' : '' ?>><?= str_repeat('— ', (int) $group->level) . $this->escape($group->title) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="subgroups" value="1" id="ut-anonymize-subgroups"<?= $draft['subgroups'] ? ' checked' : '' ?>>
              <label class="form-check-label" for="ut-anonymize-subgroups"><?= Text::_('COM_USERTOOLS_UNGROUP_AND_SUBGROUPS') ?></label>
              <div class="form-text"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SUBGROUPS_HELP') ?></div>
            </div>
          </div>
        </div></fieldset>
      </div>
      <div class="col-lg-6">
        <?= LayoutHelper::render('usertools.exclusions', ['exclusions' => $draft['exclusions'], 'groups' => $this->groups, 'idPrefix' => 'ut-anonymize', 'saveNote' => 'COM_USERTOOLS_EXCLUSIONS_SAVED_ON_SEARCH', 'title' => 'COM_USERTOOLS_EXCLUSIONS_ANONYMIZE_TITLE'], $layouts) ?>
      </div>
    </div>
    <div class="ut-actions" data-ut-tour="actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('JCANCEL') ?></a>
      <button class="btn btn-primary" name="task" value="anonymize.search"<?= $this->pluginReady ? '' : ' disabled' ?>><?= Text::_('COM_USERTOOLS_ANONYMIZE_SEARCH') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
