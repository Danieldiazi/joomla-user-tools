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
$removed   = array_flip($this->selection['removed']);
$recursive = array_flip($this->selection['recursive']);
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'groups', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::sprintf('COM_USERTOOLS_UNGROUP_GROUPS_HEADING', $this->escape($account->username)) ?></h2>
  <p class="text-body-secondary"><?= $this->escape($account->name) ?> · <?= $this->escape($account->email) ?>
    <?php if ($account->block) : ?> · <span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_UNGROUP_BLOCKED') ?></span><?php endif; ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-groups>
    <section class="card ut-card"><div class="card-body">
      <p><?= Text::_('COM_USERTOOLS_UNGROUP_GROUPS_HELP') ?></p>
      <fieldset class="mb-3">
        <legend class="visually-hidden"><?= Text::_('COM_USERTOOLS_UNGROUP_CHANGES') ?></legend>
        <?php foreach ($this->selection['groups'] as $group) : ?>
        <?php $depth = (int) $group['depth']; ?>
        <?php if (!$group['member']) : ?>
        <div class="ut-tree-row ut-tree-context text-body-secondary" style="--ut-level: <?= $depth ?>" data-ut-ungroup-context="<?= (int) $group['id'] ?>">
          <?php if ($depth > 0) : ?><span class="ut-tree-branch" aria-hidden="true">└</span><?php endif; ?><?= $this->escape($group['title']) ?>
          <small class="ms-1">(<?= Text::_('COM_USERTOOLS_UNGROUP_NOT_MEMBER') ?>)</small>
        </div>
        <?php continue; endif; ?>
        <div class="form-check ut-tree-row" style="--ut-level: <?= $depth ?>" data-ut-ungroup-row data-ut-depth="<?= $depth ?>">
          <input class="form-check-input" type="checkbox" name="keep[]" value="<?= (int) $group['id'] ?>" id="ut-keep-<?= (int) $group['id'] ?>"
            data-ut-keep="<?= (int) $group['id'] ?>" data-ut-parents="<?= $this->escape(implode(',', $group['parents'])) ?>"<?= isset($removed[$group['id']]) ? '' : ' checked' ?>>
          <label class="form-check-label" for="ut-keep-<?= (int) $group['id'] ?>"><?php if ($depth > 0) : ?><span class="ut-tree-branch" aria-hidden="true">└</span><?php endif; ?><?= $this->escape($group['title']) ?>
            <?php if ($group['superUser']) : ?><span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_SUPER') ?></span>
            <?php elseif ($group['backend']) : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?></label>
          <?php if ($group['hasChildren']) : ?>
          <div class="form-check form-check-inline ms-2 ut-subgroups" data-ut-subgroups-wrap>
            <input class="form-check-input" type="checkbox" name="recursive[]" value="<?= (int) $group['id'] ?>" id="ut-sub-<?= (int) $group['id'] ?>" data-ut-subgroups="<?= (int) $group['id'] ?>"<?= isset($recursive[$group['id']]) ? ' checked' : '' ?>>
            <label class="form-check-label small" for="ut-sub-<?= (int) $group['id'] ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_AND_SUBGROUPS') ?></label>
          </div>
          <?php endif; ?>
          <small class="d-block text-body-secondary" data-ut-cascade-note hidden></small>
        </div>
        <?php endforeach; ?>
      </fieldset>
      <p class="form-text mb-0"><?= Text::_('COM_USERTOOLS_UNGROUP_RECURSIVE_HELP') ?></p>
      <p class="mt-3 mb-0 fw-bold" data-ut-ungroup-count aria-live="polite"></p>
    </div></section>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_OTHER_ACCOUNT') ?></a>
      <button class="btn btn-primary" name="task" value="ungroup.select" data-ut-ungroup-next><?= Text::_('COM_USERTOOLS_UNGROUP_PREVIEW') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
