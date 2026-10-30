<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Export\HtmlView $this */
?>
<div class="ut-app ut-narrow">
  <p class="lead"><?= Text::_('COM_USERTOOLS_EXPORT_INTRO') ?></p>
  <div class="alert alert-info"><?= Text::_('COM_USERTOOLS_EXPORT_PRIVACY') ?></div>
  <form method="get" action="<?= Route::_('index.php') ?>" data-ut-accounts-export>
    <input type="hidden" name="option" value="com_usertools">
    <input type="hidden" name="task" value="export.download">
    <input type="hidden" name="<?= Session::getFormToken() ?>" value="1">
    <section class="card ut-card"><div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="ut-export-group"><?= Text::_('COM_USERTOOLS_EXPORT_FILTER_GROUP') ?></label>
        <select class="form-select" id="ut-export-group" name="group">
          <option value="0"><?= Text::_('COM_USERTOOLS_EXPORT_ALL_GROUPS') ?></option>
          <?php foreach ($this->groups as $group) : ?>
          <option value="<?= (int) $group->id ?>"><?= $this->escape(str_repeat('— ', max(0, (int) $group->level)) . $group->title . ' (#' . $group->id . ')') ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="ut-export-subgroups" name="subgroups" value="1">
          <label class="form-check-label" for="ut-export-subgroups"><?= Text::_('COM_USERTOOLS_EXPORT_SUBGROUPS') ?></label>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="ut-export-days"><?= Text::_('COM_USERTOOLS_EXPORT_DAYS') ?></label>
          <input class="form-control" id="ut-export-days" type="number" min="0" max="36500" step="1" name="days" value="0">
          <div class="form-text"><?= Text::_('COM_USERTOOLS_EXPORT_DAYS_HELP') ?></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ut-export-status"><?= Text::_('COM_USERTOOLS_EXPORT_FILTER_STATUS') ?></label>
          <select class="form-select" id="ut-export-status" name="status">
            <option value="all"><?= Text::_('COM_USERTOOLS_EXPORT_ALL_STATES') ?></option>
            <option value="active"><?= Text::_('COM_USERTOOLS_EXPORT_ACTIVE') ?></option>
            <option value="blocked"><?= Text::_('COM_USERTOOLS_EXPORT_BLOCKED') ?></option>
          </select>
        </div>
      </div>
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="ut-export-never" name="include_never" value="1">
        <label class="form-check-label" for="ut-export-never"><?= Text::_('COM_USERTOOLS_EXPORT_INCLUDE_NEVER') ?></label>
      </div>
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="ut-export-email" name="email" value="1">
        <label class="form-check-label" for="ut-export-email"><?= Text::_('COM_USERTOOLS_EXPORT_EMAIL') ?></label>
        <div class="form-text"><?= Text::_('COM_USERTOOLS_EXPORT_EMAIL_HELP') ?></div>
      </div>
    </div></section>
    <p class="form-text mt-3"><?= Text::_('COM_USERTOOLS_EXPORT_MFA_HELP') ?></p>
    <div class="ut-actions">
      <button type="submit" class="btn btn-primary" data-ut-export="accounts"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></button>
    </div>
  </form>
</div>
