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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Move\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$chosen  = array_flip($this->draft['groups']);
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_MOVE_PROGRESS'], $layouts) ?>
  <p class="lead"><?= Text::_('COM_USERTOOLS_MOVE_INTRO') ?></p>
  <div class="alert alert-warning" data-ut-move-rules data-ut-tour="rules">
    <p class="mb-1"><strong><?= Text::_('COM_USERTOOLS_MOVE_RULES_HEADING') ?></strong></p>
    <ul class="mb-0">
      <li><?= Text::_('COM_USERTOOLS_MOVE_RULE_INHERIT') ?></li>
      <li><?= Text::_('COM_USERTOOLS_MOVE_RULE_BACKEND') ?></li>
      <li><?= Text::_('COM_USERTOOLS_MOVE_RULE_FIXED') ?></li>
    </ul>
  </div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=move') ?>" data-ut-move-form>
    <section class="card ut-card" data-ut-tour="groups"><div class="card-body">
      <fieldset class="mb-0">
        <legend class="h5"><?= Text::_('COM_USERTOOLS_MOVE_GROUPS_LEGEND') ?></legend>
        <p class="form-text"><?= Text::_('COM_USERTOOLS_MOVE_GROUPS_HELP') ?></p>
        <?php foreach ($this->groups as $group) : ?>
        <?php $id = (int) $group->id; $depth = (int) $group->level; ?>
        <?php if ((int) $group->parent_id === 0 || $group->superUser) : ?>
        <div class="ut-tree-row ut-tree-context text-body-secondary" style="--ut-level: <?= $depth ?>" data-ut-move-fixed="<?= $id ?>">
          <?php if ($depth > 0) : ?><span class="ut-tree-branch" aria-hidden="true">└</span><?php endif; ?><?= $this->escape($group->title) ?>
          <small class="ms-1">(<?= Text::_((int) $group->parent_id === 0 ? 'COM_USERTOOLS_MOVE_FIXED_ROOT' : 'COM_USERTOOLS_MOVE_FIXED_SUPER') ?>)</small>
        </div>
        <?php else : ?>
        <div class="form-check ut-tree-row" style="--ut-level: <?= $depth ?>">
          <input class="form-check-input" type="checkbox" name="groups[]" value="<?= $id ?>" id="ut-move-<?= $id ?>" data-ut-move-group="<?= $id ?>"<?= isset($chosen[$id]) ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-move-<?= $id ?>"><?php if ($depth > 0) : ?><span class="ut-tree-branch" aria-hidden="true">└</span><?php endif; ?><?= $this->escape($group->title) ?>
            <?php if ($group->backend) : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?></label>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </fieldset>
    </div></section>
    <section class="card ut-card" data-ut-tour="target"><div class="card-body">
      <label class="form-label h5" for="ut-move-target"><?= Text::_('COM_USERTOOLS_MOVE_TARGET') ?></label>
      <select class="form-select" name="target" id="ut-move-target" aria-describedby="ut-move-target-help" required>
        <option value=""><?= Text::_('COM_USERTOOLS_MOVE_TARGET_CHOOSE') ?></option>
        <?php foreach ($this->groups as $group) : ?>
        <?php if ($group->superUser) { continue; } ?>
        <option value="<?= (int) $group->id ?>"<?= (int) $group->id === $this->draft['target'] ? ' selected' : '' ?>><?= str_repeat('— ', (int) $group->level) . $this->escape($group->title) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="form-text mb-0" id="ut-move-target-help"><?= Text::_('COM_USERTOOLS_MOVE_TARGET_HELP') ?></p>
    </div></section>
    <div class="ut-actions" data-ut-tour="actions">
      <button class="btn btn-primary" name="task" value="move.preview"><?= Text::_('COM_USERTOOLS_MOVE_PREVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
