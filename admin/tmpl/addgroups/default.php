<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Addgroups\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_ADDGROUPS_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ADDGROUPS_CHOOSE') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_ADDGROUPS_INTRO') ?></p>
  <div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ADDGROUPS_RULES') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>" data-ut-addgroups-form>
    <section class="card ut-card"><div class="card-body">
      <fieldset>
        <legend class="h5"><?= Text::_('COM_USERTOOLS_ADDGROUPS_FORMAT') ?></legend>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" value="single" id="ut-ag-single"<?= $this->draft['mode'] === 'single' ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-ag-single"><?= Text::_('COM_USERTOOLS_ADDGROUPS_SINGLE') ?></label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="radio" name="mode" value="pairs" id="ut-ag-pairs"<?= $this->draft['mode'] === 'pairs' ? ' checked' : '' ?>>
          <label class="form-check-label" for="ut-ag-pairs"><?= Text::_('COM_USERTOOLS_ADDGROUPS_PAIRS') ?></label>
        </div>
      </fieldset>
      <label class="form-label" for="ut-ag-group"><?= Text::_('COM_USERTOOLS_ADDGROUPS_GROUP') ?></label>
      <input class="form-control" id="ut-ag-group" name="group" value="<?= $this->escape($this->draft['group']) ?>" list="ut-ag-groups" autocomplete="off">
      <datalist id="ut-ag-groups">
        <?php foreach ($this->groups as $group) : ?>
        <?php if (!$group->backend && !$group->superUser && (int) $group->parent_id !== 0) : ?>
        <option value="#<?= (int) $group->id ?>"><?= $this->escape($group->title) ?></option>
        <?php endif; ?>
        <?php endforeach; ?>
      </datalist>
      <p class="form-text"><?= Text::_('COM_USERTOOLS_ADDGROUPS_GROUP_HELP') ?></p>
      <label class="form-label" for="ut-ag-lines"><?= Text::_('COM_USERTOOLS_ADDGROUPS_ACCOUNTS') ?></label>
      <textarea class="form-control" id="ut-ag-lines" name="lines" rows="10" required><?= $this->escape($this->draft['lines']) ?></textarea>
      <p class="form-text"><?= Text::_('COM_USERTOOLS_ADDGROUPS_ACCOUNTS_HELP') ?></p>
    </div></section>
    <div class="ut-actions"><button class="btn btn-primary" name="task" value="addgroups.preview"><?= Text::_('COM_USERTOOLS_ADDGROUPS_PREVIEW') ?></button></div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
