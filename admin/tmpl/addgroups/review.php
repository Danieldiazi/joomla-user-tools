<?php

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\BulkGroupAssignments;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Addgroups\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review = $this->review;
$counts = array_count_values(array_column($review['rows'], 'status'));
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_ADDGROUPS_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ADDGROUPS_REVIEW') ?></h2>
  <p><?= Text::sprintf('COM_USERTOOLS_ADDGROUPS_REVIEW_HELP', (int) $review['count'], (int) ($counts[BulkGroupAssignments::EXISTING] ?? 0)) ?></p>
  <section class="card ut-card"><div class="card-body"><div class="table-responsive">
    <table class="table table-striped table-sm" id="ut-addgroups-table">
      <thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ADDGROUPS_GROUP') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th></tr></thead>
      <tbody>
      <?php foreach ($review['rows'] as $row) : ?>
      <tr data-ut-addgroups-row="<?= (int) $row['id'] ?>" data-ut-addgroups-status="<?= $this->escape($row['status']) ?>">
        <td><?= $this->escape($row['username']) ?></td>
        <td><?= $this->escape($this->groupTitle((int) $row['group'])) ?></td>
        <td><?= Text::_('COM_USERTOOLS_ADDGROUPS_STATUS_' . strtoupper($row['status'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></section>
  <?php if ($review['count'] > 0) : ?>
    <div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ADDGROUPS_CONFIRM_HELP') ?></div>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>" data-ut-addgroups-apply>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_ADDGROUPS_CONFIRM', 'count' => $review['count'], 'threshold' => 1], $layouts) ?>
      <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
      <div class="ut-actions">
        <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
        <button class="btn btn-primary" name="task" value="addgroups.apply"><?= Text::_('COM_USERTOOLS_ADDGROUPS_APPLY') ?></button>
      </div>
      <?= HTMLHelper::_('form.token') ?>
    </form>
  <?php else : ?>
    <p class="alert alert-info" data-ut-addgroups-none><?= Text::_('COM_USERTOOLS_ADDGROUPS_NO_CHANGES') ?></p>
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
  <?php endif; ?>
</div>
