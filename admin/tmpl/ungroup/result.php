<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$steps   = ['setup' => 'COM_USERTOOLS_UNGROUP_STEP_CHOOSE', 'review' => 'COM_USERTOOLS_UNGROUP_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
$result  = $this->result;
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'result', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <div class="alert alert-success" data-ut-ungroup-done><?= Text::sprintf('COM_USERTOOLS_UNGROUP_DONE', $this->escape($result['username']), $this->escape(implode(', ', $result['removed']))) ?></div>
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
