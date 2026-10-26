<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
/** @var \Danieldiazi\Component\Usertools\Administrator\Service\UserRecord $account */
$account = $this->selection['account'];
$removed = array_flip($this->selection['removed']);
$minLevel = $this->selection['groups'] ? min(array_column($this->selection['groups'], 'level')) : 0;
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'groups', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::sprintf('COM_USERTOOLS_UNGROUP_GROUPS_HEADING', $this->escape($account->username)) ?></h2>
  <p class="text-body-secondary"><?= $this->escape($account->name) ?> · <?= $this->escape($account->email) ?>
    <?php if ($account->block) : ?> · <span class="badge bg-secondary"><?= Text::_('COM_USERTOOLS_UNGROUP_BLOCKED') ?></span><?php endif; ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-groups>
    <section class="card ut-card"><div class="card-body">
      <p><?= Text::_('COM_USERTOOLS_UNGROUP_GROUPS_HELP') ?></p>
      <fieldset class="mb-3">
        <legend class="visually-hidden"><?= Text::_('COM_USERTOOLS_UNGROUP_CHANGES') ?></legend>
        <?php foreach ($this->selection['groups'] as $group) : ?>
        <?php $depth = (int) $group['level'] - $minLevel; ?>
        <div class="form-check ut-tree-row" style="--ut-level: <?= $depth ?>" data-ut-ungroup-row data-ut-depth="<?= $depth ?>">
          <input class="form-check-input" type="checkbox" name="keep[]" value="<?= (int) $group['id'] ?>" id="ut-keep-<?= (int) $group['id'] ?>"
            data-ut-keep="<?= (int) $group['id'] ?>" data-ut-parents="<?= $this->escape(implode(',', $group['parents'])) ?>"<?= isset($removed[$group['id']]) ? '' : ' checked' ?>>
          <label class="form-check-label" for="ut-keep-<?= (int) $group['id'] ?>"><?php if ($depth > 0) : ?><span class="ut-tree-branch" aria-hidden="true">└</span><?php endif; ?><?= $this->escape($group['title']) ?>
            <?php if ($group['superUser']) : ?><span class="badge bg-danger"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_SUPER') ?></span>
            <?php elseif ($group['backend']) : ?><span class="badge bg-warning text-dark"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?></label>
          <small class="d-block text-body-secondary" data-ut-cascade-note hidden></small>
        </div>
        <?php endforeach; ?>
      </fieldset>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="recursive" value="1" id="ut-ungroup-recursive" data-ut-recursive<?= $this->selection['recursive'] ? ' checked' : '' ?>>
        <label class="form-check-label" for="ut-ungroup-recursive"><?= Text::_('COM_USERTOOLS_UNGROUP_RECURSIVE') ?></label>
        <div class="form-text"><?= Text::_('COM_USERTOOLS_UNGROUP_RECURSIVE_HELP') ?></div>
      </div>
      <p class="mt-3 mb-0 fw-bold" data-ut-ungroup-count aria-live="polite"></p>
    </div></section>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_OTHER_ACCOUNT') ?></a>
      <button class="btn btn-primary" name="task" value="ungroup.select" data-ut-ungroup-next><?= Text::_('COM_USERTOOLS_UNGROUP_PREVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
