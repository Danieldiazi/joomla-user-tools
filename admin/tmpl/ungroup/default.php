<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$steps   = ['setup' => 'COM_USERTOOLS_UNGROUP_STEP_CHOOSE', 'review' => 'COM_USERTOOLS_UNGROUP_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'setup', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <p class="lead"><?= Text::_('COM_USERTOOLS_UNGROUP_INTRO') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-choose>
    <section class="card ut-card"><div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="ut-ungroup-login"><?= Text::_('COM_USERTOOLS_UNGROUP_LOGIN') ?></label>
        <input class="form-control" type="text" name="login" id="ut-ungroup-login" required autocomplete="off" value="<?= $this->escape($this->draft['login']) ?>" aria-describedby="ut-ungroup-login-help">
        <div class="form-text" id="ut-ungroup-login-help"><?= Text::_('COM_USERTOOLS_UNGROUP_LOGIN_HELP') ?></div>
      </div>
      <div class="mb-0">
        <label class="form-label" for="ut-ungroup-group"><?= Text::_('COM_USERTOOLS_UNGROUP_GROUP') ?></label>
        <select class="form-select" name="group" id="ut-ungroup-group" required aria-describedby="ut-ungroup-group-help">
          <option value=""><?= Text::_('COM_USERTOOLS_UNGROUP_GROUP_SELECT') ?></option>
          <?php foreach ($this->groups as $group) :
              if ((int) $group->parent_id === 0) {
                  continue;
              } ?>
          <option value="<?= (int) $group->id ?>"<?= $this->draft['group'] === (int) $group->id ? ' selected' : '' ?>><?= str_repeat('– ', max(0, (int) $group->level - 1)) . $this->escape($group->title) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text" id="ut-ungroup-group-help"><?= Text::_('COM_USERTOOLS_UNGROUP_GROUP_HELP') ?></div>
      </div>
    </div></section>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
      <button class="btn btn-primary" name="task" value="ungroup.preview"><?= Text::_('COM_USERTOOLS_UNGROUP_PREVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
