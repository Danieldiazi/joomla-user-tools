<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessMatrix;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Access\HtmlView $this */
$levels = $this->matrix['levels'];
$rows   = $this->matrix['rows'];
$canUsers = $this->canUsers();
$canEdit  = $this->canEditGroups();
$hasUsers = AccessMatrix::hasUserRules($levels);
$export   = fn (string $shape) => Route::_('index.php?option=com_usertools&task=access.export&shape=' . $shape . '&' . Session::getFormToken() . '=1');
?>
<div class="ut-app ut-wide">
  <p class="lead"><?= Text::_('COM_USERTOOLS_ACCESS_INTRO') ?></p>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="card ut-card"><div class="card-body">
    <h2 class="h5"><?= Text::_('COM_USERTOOLS_ACCESS_LEGEND') ?></h2>
    <ul class="list-unstyled mb-0 ut-access-legend">
      <li><span class="badge text-bg-success"><?= Text::_('COM_USERTOOLS_ACCESS_DIRECT') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_DIRECT_DESC') ?></li>
      <li><span class="badge text-bg-info"><?= Text::_('COM_USERTOOLS_ACCESS_INHERITED') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_INHERITED_DESC') ?></li>
      <li><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS') ?></span> <?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS_DESC') ?></li>
      <li><span class="text-body-secondary" aria-hidden="true">—</span> <?= Text::_('COM_USERTOOLS_ACCESS_NONE_DESC') ?></li>
    </ul>
  </div></div>

  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= Text::_('COM_USERTOOLS_ACCESS_MATRIX') ?></h2>
    <div class="ut-access-tools">
      <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" data-ut-filter="ut-access-table">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=usage') ?>"><?= Text::_('COM_USERTOOLS_ACCESS_TO_USAGE') ?></a>
      <div class="btn-group" role="group" aria-label="<?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?>">
        <a class="btn btn-outline-secondary" href="<?= $export(AccessMatrix::EXPORT_MATRIX) ?>" data-ut-export="matrix" title="<?= Text::_('COM_USERTOOLS_ACCESS_EXPORT_MATRIX_DESC') ?>"><?= Text::_('COM_USERTOOLS_ACCESS_EXPORT_MATRIX') ?></a>
        <a class="btn btn-outline-secondary" href="<?= $export(AccessMatrix::EXPORT_PAIRS) ?>" data-ut-export="pairs" title="<?= Text::_('COM_USERTOOLS_ACCESS_EXPORT_PAIRS_DESC') ?>"><?= Text::_('COM_USERTOOLS_ACCESS_EXPORT_PAIRS') ?></a>
      </div>
    </div>
  </div>
  <div class="table-responsive ut-access-scroll mt-3">
    <table class="table table-sm table-bordered align-middle ut-access" id="ut-access-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_ACCESS_MATRIX') ?></caption>
      <thead><tr>
        <th scope="col" class="ut-access-group"><?= Text::_('COM_USERTOOLS_ACCESS_GROUP') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_ACCESS_MEMBERS') ?></th>
        <?php foreach ($levels as $level) : ?>
        <th scope="col" class="text-center">
          <?php if ($canEdit) : ?><a href="<?= Route::_('index.php?option=com_users&task=level.edit&id=' . (int) $level['id']) ?>"><?= $this->escape($level['title']) ?></a><?php else : ?><?= $this->escape($level['title']) ?><?php endif; ?>
        </th>
        <?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $row) : ?>
        <tr data-ut-group="<?= (int) $row['id'] ?>">
          <th scope="row" class="ut-access-group">
            <span class="ut-group-option" style="--ut-level: <?= (int) $row['level'] ?>">
              <?php if ($canEdit) : ?><a href="<?= Route::_('index.php?option=com_users&task=group.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['title']) ?></a><?php else : ?><?= $this->escape($row['title']) ?><?php endif; ?>
            </span>
            <?php if ($row['superUser']) : ?><span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_SUPER') ?></span>
            <?php elseif ($row['backend']) : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?>
            <?php if ($row['guest']) : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_GUEST') ?></span><?php endif; ?>
          </th>
          <td class="text-end">
            <?php if ($canUsers && $row['members'] > 0) : ?><a href="<?= Route::_('index.php?option=com_users&view=users&filter[group_id]=' . (int) $row['id']) ?>"><?= (int) $row['members'] ?></a><?php else : ?><?= (int) $row['members'] ?><?php endif; ?>
          </td>
          <?php foreach ($levels as $level) :
              $cell = $row['cells'][$level['id']];
              ?>
          <td class="text-center" data-ut-cell="<?= $this->escape($cell['state']) ?>">
            <?php if ($cell['state'] === 'direct') : ?><span class="badge text-bg-success"><?= Text::_('COM_USERTOOLS_ACCESS_DIRECT') ?></span>
            <?php elseif ($cell['state'] === 'inherited') : ?><span class="badge text-bg-info"><?= Text::_('COM_USERTOOLS_ACCESS_INHERITED') ?></span>
              <small class="d-block text-body-secondary"><?= Text::sprintf('COM_USERTOOLS_ACCESS_VIA', $this->escape($this->groupTitle($cell['via']))) ?></small>
            <?php elseif ($cell['state'] === 'public') : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS') ?></span>
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
        <?php if ($hasUsers) : ?><th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_ACCESS_USERS') ?></th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($levels as $level) : ?>
        <tr data-ut-level="<?= (int) $level['id'] ?>">
          <th scope="row"><?= $this->escape($level['title']) ?>
            <?php if ($level['empty']) : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_ACCESS_EMPTY') ?></span><?php endif; ?></th>
          <td><?php if ($level['groups']) : ?><?= $this->escape(implode(', ', array_map(fn ($id) => $this->groupTitle((int) $id), $level['groups']))) ?>
            <?php elseif ((int) $level['id'] === 1) : ?><span class="text-body-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_ALWAYS_DESC') ?></span>
            <?php else : ?><span class="text-body-secondary">—</span><?php endif; ?></td>
          <td class="text-end"><?= Text::sprintf('COM_USERTOOLS_ACCESS_REACH_COUNT', (int) $level['reach'], count($rows)) ?></td>
          <?php if ($hasUsers) : ?><td class="text-end"><?= (int) $level['users'] ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-info mb-0"><?= Text::_('COM_USERTOOLS_ACCESS_NOTE') ?><?php if ($hasUsers) : ?> <?= Text::_('COM_USERTOOLS_ACCESS_NOTE_USERS') ?><?php endif; ?></div>
  <?php endif; ?>
</div>
