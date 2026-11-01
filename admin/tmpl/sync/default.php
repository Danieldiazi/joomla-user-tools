<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupSync;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sync\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$draft = $this->draft;
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_SYNC_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SYNC_STEP_LIST') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_SYNC_INTRO') ?></p>
  <div class="alert alert-warning" data-ut-tour="rules"><?= Text::_('COM_USERTOOLS_SYNC_RULES') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=sync') ?>" data-ut-sync-form>
    <section class="card ut-card" data-ut-tour="branch"><div class="card-body">
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_SYNC_BRANCH') ?></h3>
      <label class="form-label" for="ut-sync-root"><?= Text::_('COM_USERTOOLS_SYNC_ROOT') ?></label>
      <select class="form-select" id="ut-sync-root" name="root" required aria-describedby="ut-sync-root-help">
        <option value=""><?= Text::_('COM_USERTOOLS_SYNC_ROOT_CHOOSE') ?></option>
        <?php foreach ($this->groups as $group) : ?>
        <?php if ((int) $group->parent_id !== 0 && (int) $group->id !== GroupSync::DEFAULT_GROUP && !$group->backend && !$group->superUser) : ?>
        <option value="<?= (int) $group->id ?>"<?= $draft['root'] === (int) $group->id ? ' selected' : '' ?>><?= str_repeat('– ', max(0, (int) $group->level - 1)) . $this->escape($group->title) ?> (#<?= (int) $group->id ?>)</option>
        <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <p class="form-text" id="ut-sync-root-help"><?= Text::_('COM_USERTOOLS_SYNC_ROOT_HELP') ?></p>
      <fieldset class="mt-3">
        <legend class="h6"><?= Text::_('COM_USERTOOLS_SYNC_MODE') ?></legend>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" value="partial" id="ut-sync-partial"<?= $draft['mode'] !== GroupSync::FULL ? ' checked' : '' ?> aria-describedby="ut-sync-partial-help">
          <label class="form-check-label" for="ut-sync-partial"><?= Text::_('COM_USERTOOLS_SYNC_MODE_PARTIAL') ?></label>
          <p class="form-text mt-0" id="ut-sync-partial-help"><?= Text::_('COM_USERTOOLS_SYNC_MODE_PARTIAL_HELP') ?></p>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" value="full" id="ut-sync-full"<?= $draft['mode'] === GroupSync::FULL ? ' checked' : '' ?> aria-describedby="ut-sync-full-help">
          <label class="form-check-label" for="ut-sync-full"><?= Text::_('COM_USERTOOLS_SYNC_MODE_FULL') ?></label>
          <p class="form-text mt-0" id="ut-sync-full-help"><?= Text::_('COM_USERTOOLS_SYNC_MODE_FULL_HELP') ?></p>
        </div>
        <div class="form-check ms-4">
          <input class="form-check-input" type="checkbox" name="complete" value="1" id="ut-sync-complete"<?= $draft['complete'] ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-sync-complete"><?= Text::_('COM_USERTOOLS_SYNC_COMPLETE') ?></label>
        </div>
      </fieldset>
    </div></section>
    <section class="card ut-card" data-ut-tour="list"><div class="card-body">
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_SYNC_LIST') ?></h3>
      <fieldset>
        <legend class="h6"><?= Text::_('COM_USERTOOLS_SYNC_BY') ?></legend>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="by" value="username" id="ut-sync-by-username"<?= $draft['by'] !== GroupSync::BY_ID ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-sync-by-username"><?= Text::_('COM_USERTOOLS_SYNC_BY_USERNAME') ?></label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="radio" name="by" value="id" id="ut-sync-by-id"<?= $draft['by'] === GroupSync::BY_ID ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-sync-by-id"><?= Text::_('COM_USERTOOLS_SYNC_BY_ID') ?></label>
        </div>
      </fieldset>
      <label class="form-label" for="ut-sync-lines"><?= Text::_('COM_USERTOOLS_SYNC_LINES') ?></label>
      <textarea class="form-control font-monospace" id="ut-sync-lines" name="lines" rows="12" required spellcheck="false" aria-describedby="ut-sync-lines-help"><?= $this->escape($draft['lines']) ?></textarea>
      <div class="form-text" id="ut-sync-lines-help">
        <p class="mb-1"><?= Text::_('COM_USERTOOLS_SYNC_LINES_HELP') ?></p>
        <pre class="mb-1 p-2 border rounded"><code><?= $this->escape(Text::_('COM_USERTOOLS_SYNC_LINES_EXAMPLE')) ?></code></pre>
        <p class="mb-0"><?= Text::_($this->canCreate ? 'COM_USERTOOLS_SYNC_CREATE_HELP' : 'COM_USERTOOLS_SYNC_CREATE_NOT_ALLOWED_HELP') ?></p>
      </div>
    </div></section>
    <div class="ut-actions" data-ut-tour="actions"><button class="btn btn-primary" name="task" value="sync.preview"><?= Text::_('COM_USERTOOLS_SYNC_PREVIEW') ?></button></div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
