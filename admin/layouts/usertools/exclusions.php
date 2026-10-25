<?php

/**
 * Exclusion fields shared by the manual tools.
 * $displayData: exclusions (Exclusions), groups (array<int, object>), idPrefix (string), saveNote (string key)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$exclusions = $displayData['exclusions'];
$groups     = $displayData['groups'];
$prefix     = $displayData['idPrefix'];
$e          = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="card ut-card" data-ut-exclusions>
  <div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_EXCLUSIONS_TITLE') ?></h3>
    <input type="hidden" name="protect_admins" value="0">
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="protect_admins" value="1" id="<?= $prefix ?>-protect" data-ut-protect-admins<?= $exclusions->protectAdmins ? ' checked' : '' ?>>
      <label class="form-check-label" for="<?= $prefix ?>-protect"><?= Text::_('COM_USERTOOLS_EXCLUDE_ADMINS') ?></label>
      <div class="form-text"><?= Text::_('COM_USERTOOLS_EXCLUDE_ADMINS_HELP') ?></div>
    </div>
    <label class="form-label fw-bold" for="<?= $prefix ?>-excluded"><?= Text::_('COM_USERTOOLS_EXCLUDED_NAMES') ?></label>
    <textarea class="form-control mb-3" id="<?= $prefix ?>-excluded" name="excluded" rows="3" placeholder="<?= Text::_('COM_USERTOOLS_ONE_PER_LINE') ?>"><?= $e(implode("\n", $exclusions->names)) ?></textarea>
    <fieldset>
      <legend class="form-label fw-bold fs-6"><?= Text::_('COM_USERTOOLS_EXCLUDED_GROUPS') ?></legend>
      <div class="ut-group-list">
      <?php foreach ($groups as $group) :
          $manual = in_array((int) $group->id, $exclusions->groups, true);
          $kind   = $group->superUser ? 'super' : ($group->backend ? 'backend' : '');
          $locked = $kind === 'super' || ($kind === 'backend' && $exclusions->protectAdmins);
          $id     = $prefix . '-group-' . (int) $group->id;
          ?>
        <div class="ut-group-option<?= $locked ? ' ut-is-protected' : '' ?>" data-ut-group-option style="--ut-level: <?= (int) $group->level ?>">
          <div class="form-check mb-0">
            <input class="form-check-input" id="<?= $id ?>" type="checkbox" name="excluded_groups[]" value="<?= (int) $group->id ?>" data-ut-group data-ut-group-kind="<?= $kind ?>" data-ut-manual="<?= $manual ? '1' : '0' ?>" aria-describedby="<?= $id ?>-note"<?= ($manual || $locked) ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
            <label class="form-check-label" for="<?= $id ?>"><?= $e($group->title) ?></label>
          </div>
          <input type="hidden" name="excluded_groups[]" value="<?= (int) $group->id ?>" data-ut-group-preserve<?= ($manual && $locked) ? '' : ' disabled' ?>>
          <small class="form-text d-block" id="<?= $id ?>-note" data-ut-group-note data-ut-note-super="<?= Text::_('COM_USERTOOLS_GROUP_SUPER_NOTE') ?>" data-ut-note-backend="<?= Text::_('COM_USERTOOLS_GROUP_ADMIN_NOTE') ?>"<?= $locked ? '' : ' hidden' ?>><?= Text::_($kind === 'super' ? 'COM_USERTOOLS_GROUP_SUPER_NOTE' : 'COM_USERTOOLS_GROUP_ADMIN_NOTE') ?></small>
        </div>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <p class="form-text mb-0"><?= Text::_($displayData['saveNote']) ?></p>
  </div>
</div>
