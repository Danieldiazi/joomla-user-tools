<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$steps   = ['setup' => 'COM_USERTOOLS_UNGROUP_STEP_CHOOSE', 'review' => 'COM_USERTOOLS_UNGROUP_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'];
/** @var \Danieldiazi\Component\Usertools\Administrator\Service\UserRecord $account */
$account = $this->review['account'];
$plan    = $this->review['plan'];
$remove  = array_flip($plan['remove']);
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $steps, 'current' => 'review', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::sprintf('COM_USERTOOLS_UNGROUP_REVIEW_HEADING', $this->escape($this->groupTitle($this->review['group']))) ?></h2>

  <section class="card ut-card"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_USERNAME') ?></dt><dd class="col-sm-8" data-ut-account><?= $this->escape($account->username) ?> (ID <?= (int) $account->id ?>)</dd>
      <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_NAME') ?></dt><dd class="col-sm-8"><?= $this->escape($account->name) ?></dd>
      <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_EMAIL') ?></dt><dd class="col-sm-8"><?= $this->escape($account->email) ?></dd>
      <?php if ($account->block) : ?><dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_UNGROUP_STATE') ?></dt><dd class="col-sm-8"><span class="badge bg-secondary"><?= Text::_('COM_USERTOOLS_UNGROUP_BLOCKED') ?></span></dd><?php endif; ?>
    </dl>
  </div></section>

  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_UNGROUP_CHANGES') ?></h3>
    <table class="table table-sm mb-0" id="ut-ungroup-table">
      <thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_UNGROUP_CURRENT_GROUP') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_UNGROUP_CHANGE') ?></th></tr></thead>
      <tbody>
      <?php foreach (array_merge($plan['remove'], $plan['keep']) as $id) : ?>
        <tr data-ut-change="<?= isset($remove[$id]) ? 'remove' : 'keep' ?>">
          <td><?= $this->escape($this->groupTitle($id)) ?></td>
          <td><?php if (isset($remove[$id])) : ?><span class="badge bg-danger"><?= Text::_('COM_USERTOOLS_UNGROUP_REMOVED') ?></span><?php else : ?><span class="badge bg-success"><?= Text::_('COM_USERTOOLS_UNGROUP_KEPT') ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($plan['added']) : ?>
        <tr data-ut-change="add"><td><?= $this->escape($this->groupTitle($plan['added'])) ?></td><td><span class="badge bg-info"><?= Text::_('COM_USERTOOLS_UNGROUP_ADDED') ?></span></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <?php if ($plan['added']) : ?><p class="form-text mb-0"><?= Text::sprintf('COM_USERTOOLS_UNGROUP_ADDED_HELP', $this->escape($this->groupTitle($plan['added']))) ?></p><?php endif; ?>
  </div></section>

  <?php if ($plan['reason'] !== '') : ?>
    <div class="alert alert-<?= $plan['reason'] === 'COM_USERTOOLS_UNGROUP_REASON_NOT_MEMBER' ? 'info' : 'warning' ?>" data-ut-reason="<?= $this->escape($plan['reason']) ?>">
      <?= Text::sprintf($plan['reason'], $this->escape($this->groupTitle($this->review['group'])), $this->escape($this->groupTitle((int) $this->review['defaultGroup']))) ?>
    </div>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
    </div>
  <?php else : ?>
    <?php if ($plan['losesSuper']) : ?><div class="alert alert-danger"><?= Text::_('COM_USERTOOLS_UNGROUP_LOSES_SUPER') ?></div>
    <?php elseif ($plan['losesBackend']) : ?><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_UNGROUP_LOSES_BACKEND') ?></div><?php endif; ?>
    <div class="alert alert-info"><?= Text::_('COM_USERTOOLS_UNGROUP_SESSIONS') ?></div>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-form>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_UNGROUP_CONFIRM', 'count' => 1, 'threshold' => 0], $layouts) ?>
      <input type="hidden" name="preview" value="<?= $this->escape($this->review['nonce']) ?>">
      <div class="ut-actions">
        <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
        <button class="btn btn-danger" name="task" value="ungroup.apply"><?= Text::_('COM_USERTOOLS_UNGROUP_APPLY') ?></button>
      </div>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  <?php endif; ?>
</div>
