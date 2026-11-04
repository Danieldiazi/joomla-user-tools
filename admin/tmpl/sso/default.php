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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sso\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_SSO_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SSO_SEARCH_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_SSO_INTRO') ?></p>
  <div class="alert alert-warning" data-ut-tour="rules"><?= Text::_('COM_USERTOOLS_SSO_RULES') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=sso') ?>" data-ut-sso-search>
    <section class="card ut-card" data-ut-tour="filters"><div class="card-body">
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_SSO_FILTERS') ?></h3>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="ut-sso-group" class="form-label"><?= Text::_('COM_USERTOOLS_GROUP') ?></label>
          <select id="ut-sso-group" name="group" class="form-select">
            <option value="0"><?= Text::_('COM_USERTOOLS_SSO_ALL_GROUPS') ?></option>
            <?php foreach ($this->groups as $group) : ?>
            <option value="<?= (int) $group->id ?>"<?= (int) $this->draft['group'] === (int) $group->id ? ' selected' : '' ?>>
              <?= str_repeat('— ', max(0, (int) $group->level)) . $this->escape($group->title) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="ut-sso-subgroups" name="subgroups" value="1"<?= $this->draft['subgroups'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-sso-subgroups"><?= Text::_('COM_USERTOOLS_SSO_SUBGROUPS') ?></label>
          </div>
        </div>
        <div class="col-md-3">
          <label for="ut-sso-status" class="form-label"><?= Text::_('COM_USERTOOLS_SSO_STATUS') ?></label>
          <select id="ut-sso-status" name="status" class="form-select">
            <?php foreach (['all', 'active', 'blocked'] as $value) : ?>
            <option value="<?= $value ?>"<?= $this->draft['status'] === $value ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_SSO_STATUS_' . strtoupper($value)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="ut-sso-visits" class="form-label"><?= Text::_('COM_USERTOOLS_SSO_VISITS') ?></label>
          <select id="ut-sso-visits" name="visits" class="form-select">
            <?php foreach (['all', 'yes', 'no'] as $value) : ?>
            <option value="<?= $value ?>"<?= $this->draft['visits'] === $value ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_SSO_VISITS_' . strtoupper($value)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div></section>
    <div class="ut-actions" data-ut-tour="actions">
      <button class="btn btn-primary" name="task" value="sso.search"><?= Text::_('COM_USERTOOLS_SSO_SEARCH') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
