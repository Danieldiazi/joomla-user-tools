<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Access\HtmlView $this */
$levels = $this->matrix['levels'];
$rows   = $this->matrix['rows'];
$canUsers = $this->canUsers();
?>
<div class="ut-app ut-wide">
  <p class="lead"><?= Text::_('COM_USERTOOLS_ACCESS_INTRO') ?></p>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="card ut-card"><div class="card-body">
    <h2 class="h5"><?= Text::_('COM_USERTOOLS_ACCESS_LEGEND') ?></h2>
    <ul class="list-unstyled mb-0 ut-access-legend">
      <li><span class="badge bg-success"><?= Text::_('COM_USERTOOLS_ACCESS_DIRECT') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_DIRECT_DESC') ?></li>
      <li><span class="badge bg-info"><?= Text::_('COM_USERTOOLS_ACCESS_INHERITED') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_INHERITED_DESC') ?></li>
      <li><span class="badge bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS_DESC') ?></li>
      <li><span class="text-body-secondary" aria-hidden="true">—</span> <?= Text::_('COM_USERTOOLS_ACCESS_NONE_DESC') ?></li>
    </ul>
  </div></div>

  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= Text::_('COM_USERTOOLS_ACCESS_MATRIX') ?></h2>
    <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" data-ut-filter="ut-access-table">
  </div>
  <div class="table-responsive ut-access-scroll mt-3">
    <table class="table table-sm table-bordered align-middle ut-access" id="ut-access-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_ACCESS_MATRIX') ?></caption>
      <thead><tr>
        <th scope="col" class="ut-access-group"><?= Text::_('COM_USERTOOLS_ACCESS_GROUP') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_ACCESS_MEMBERS') ?></th>
        <?php foreach ($levels as $level) : ?>
        <th scope="col" class="text-center">
          <?php if ($canUsers) : ?><a href="<?= Route::_('index.php?option=com_users&task=level.edit&id=' . (int) $level['id']) ?>"><?= $this->escape($level['title']) ?></a><?php else : ?><?= $this->escape($level['title']) ?><?php endif; ?>
        </th>
        <?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $row) : ?>
        <tr data-ut-group="<?= (int) $row['id'] ?>">
          <th scope="row" class="ut-access-group">
            <span class="ut-group-option" style="--ut-level: <?= (int) $row['level'] ?>">
              <?php if ($canUsers) : ?><a href="<?= Route::_('index.php?option=com_users&task=group.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['title']) ?></a><?php else : ?><?= $this->escape($row['title']) ?><?php endif; ?>
            </span>
            <?php if ($row['superUser']) : ?><span class="badge bg-danger"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_SUPER') ?></span>
            <?php elseif ($row['backend']) : ?><span class="badge bg-warning text-dark"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?>
            <?php if ($row['guest']) : ?><span class="badge bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_GUEST') ?></span><?php endif; ?>
          </th>
          <td class="text-end">
            <?php if ($canUsers && $row['members'] > 0) : ?><a href="<?= Route::_('index.php?option=com_users&view=users&filter[group_id]=' . (int) $row['id']) ?>"><?= (int) $row['members'] ?></a><?php else : ?><?= (int) $row['members'] ?><?php endif; ?>
          </td>
          <?php foreach ($levels as $level) :
              $cell = $row['cells'][$level['id']];
              ?>
          <td class="text-center" data-ut-cell="<?= $this->escape($cell['state']) ?>">
            <?php if ($cell['state'] === 'direct') : ?><span class="badge bg-success"><?= Text::_('COM_USERTOOLS_ACCESS_DIRECT') ?></span>
            <?php elseif ($cell['state'] === 'inherited') : ?><span class="badge bg-info"><?= Text::_('COM_USERTOOLS_ACCESS_INHERITED') ?></span>
              <small class="d-block text-body-secondary"><?= Text::sprintf('COM_USERTOOLS_ACCESS_VIA', $this->escape($this->groupTitle($cell['via']))) ?></small>
            <?php elseif ($cell['state'] === 'public') : ?><span class="badge bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS') ?></span>
            <?php else : ?><span class="text-body-secondary" aria-hidden="true">—</span><span class="visually-hidden"><?= Text::_('COM_USERTOOLS_ACCESS_NONE') ?></span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p>
  </div>

  <h2 class="h4 mt-4"><?= Text::_('COM_USERTOOLS_ACCESS_LEVELS') ?></h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle" id="ut-levels-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_ACCESS_LEVELS') ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_ASSIGNED_GROUPS') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_ACCESS_REACH') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_ACCESS_USERS') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($levels as $level) : ?>
        <tr data-ut-level="<?= (int) $level['id'] ?>">
          <th scope="row"><?= $this->escape($level['title']) ?>
            <?php if ($level['empty']) : ?><span class="badge bg-warning text-dark"><?= Text::_('COM_USERTOOLS_ACCESS_EMPTY') ?></span><?php endif; ?></th>
          <td><?php if ($level['groups']) : ?><?= $this->escape(implode(', ', array_map(fn ($id) => $this->groupTitle((int) $id), $level['groups']))) ?>
            <?php elseif ((int) $level['id'] === 1) : ?><span class="text-body-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS_DESC') ?></span>
            <?php else : ?><span class="text-body-secondary">—</span><?php endif; ?></td>
          <td class="text-end"><?= Text::sprintf('COM_USERTOOLS_ACCESS_REACH_COUNT', (int) $level['reach'], count($rows)) ?></td>
          <td class="text-end"><?= (int) $level['users'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-info mb-0"><?= Text::_('COM_USERTOOLS_ACCESS_NOTE') ?></div>
  <?php endif; ?>
</div>
