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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <div class="alert alert-success" data-ut-ungroup-done><?= Text::sprintf('COM_USERTOOLS_UNGROUP_DONE', $this->escape($result['username']) . ' (ID ' . (int) $result['id'] . ')', $this->escape(implode(', ', $result['removed']))) ?></div>
  <?php if (($result['sessions'] ?? '') === 'open') : ?>
  <div class="alert alert-danger" data-ut-sessions="open">
    <p><?= Text::_('COM_USERTOOLS_UNGROUP_SESSIONS_OPEN') ?></p>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" class="mb-0">
      <input type="hidden" name="account" value="<?= (int) $result['id'] ?>">
      <button class="btn btn-danger" name="task" value="ungroup.sessions"><?= Text::_('COM_USERTOOLS_UNGROUP_SESSIONS_RETRY') ?></button>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  </div>
  <?php elseif (($result['sessions'] ?? '') === 'unknown') : ?>
  <div class="alert alert-warning" data-ut-sessions="unknown"><?= Text::_('COM_USERTOOLS_UNGROUP_SESSIONS_UNKNOWN') ?></div>
  <?php endif; ?>
  <section class="card ut-card"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_UNGROUP_NOW_IN') ?></dt><dd class="col-sm-8" data-ut-now-in><?= $this->escape(implode(', ', $result['groups'])) ?></dd>
      <?php if ($result['added'] !== '') : ?><dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_UNGROUP_ADDED') ?></dt><dd class="col-sm-8"><?= $this->escape($result['added']) ?></dd><?php endif; ?>
    </dl>
  </div></section>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $result['id']) ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_OPEN_USER') ?></a>
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_actionlogs&view=actionlogs&filter[extension]=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_ACTION_LOG') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_AGAIN') ?></a>
  </div>
</div>
