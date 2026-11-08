<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Reach\HtmlView $this */
$canUsers = $this->canUsers();
$fewQ     = $this->few !== GroupReach::DEFAULT_FEW ? '&few=' . $this->few : '';
$flagged  = count(array_filter($this->rows, fn (array $row): bool => $row['few']));
?>
<div class="ut-app ut-wide">
  <p class="lead"><?= Text::_('COM_USERTOOLS_REACH_INTRO') ?></p>

  <form method="get" action="<?= Route::_('index.php') ?>" class="row g-2 align-items-end mb-3" data-ut-reach-filter>
    <input type="hidden" name="option" value="com_usertools"><input type="hidden" name="view" value="reach">
    <div class="col-sm-6 col-lg-4">
      <label class="form-label" for="ut-reach-few"><?= Text::_('COM_USERTOOLS_REACH_FEW_LABEL') ?></label>
      <input type="number" class="form-control" id="ut-reach-few" name="few" min="1" max="<?= GroupReach::MAX_FEW ?>" value="<?= (int) $this->few ?>"
        aria-describedby="ut-reach-few-desc">
      <small class="form-text" id="ut-reach-few-desc"><?= Text::_('COM_USERTOOLS_REACH_FEW_DESC') ?></small>
    </div>
    <div class="col-sm-6 col-lg-4 d-flex gap-2 mb-sm-4">
      <button class="btn btn-primary"><?= Text::_('COM_USERTOOLS_FILTER_APPLY') ?></button>
    </div>
  </form>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= Text::_('COM_USERTOOLS_REACH_TABLE') ?></h2>
    <div class="ut-access-tools">
      <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_ACCESS_FILTER') ?>" data-ut-filter="ut-reach-table">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=access') ?>"><?= Text::_('COM_USERTOOLS_USAGE_TO_GROUPS') ?></a>
      <a class="btn btn-outline-secondary" data-ut-export="reach"
        href="<?= Route::_('index.php?option=com_usertools&task=reach.export' . $fewQ . '&' . Session::getFormToken() . '=1') ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a>
    </div>
  </div>
  <p class="mt-2" data-ut-reach-flagged="<?= (int) $flagged ?>"><?= Text::plural('COM_USERTOOLS_REACH_FLAGGED', $flagged, (int) $this->few) ?></p>
  <div class="table-responsive ut-access-scroll">
    <table class="table table-sm table-bordered align-middle ut-access" id="ut-reach-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_REACH_TABLE') ?></caption>
      <thead><tr>
        <th scope="col" class="ut-access-group"><?= Text::_('COM_USERTOOLS_ACCESS_GROUP') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_REACH_DIRECT') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_REACH_MEMBERS') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_LEVELS') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_REACH_ARTICLES') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_REACH_EXCLUSIVE') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($this->rows as $row) : ?>
        <tr data-ut-reach-group="<?= (int) $row['id'] ?>">
          <th scope="row" class="ut-access-group">
            <span class="ut-group-option" style="--ut-level: <?= (int) $row['level'] ?>"><?= $this->escape($row['title']) ?></span>
            <?php if ($row['superUser']) : ?><span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_SUPER') ?></span>
            <?php elseif ($row['backend']) : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_BACKEND') ?></span><?php endif; ?>
            <?php if ($row['guest']) : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_ACCESS_BADGE_GUEST') ?></span><?php endif; ?>
          </th>
          <td class="text-end" data-ut-reach-direct="<?= (int) $row['direct'] ?>"><?= (int) $row['direct'] ?></td>
          <td class="text-end" data-ut-reach-members="<?= (int) $row['members'] ?>">
            <?php if ($canUsers && $row['members'] > 0) : ?><a href="<?= Route::_('index.php?option=com_usertools&view=reach&layout=members&group=' . (int) $row['id']) ?>"
              aria-label="<?= $this->escape(Text::sprintf('COM_USERTOOLS_REACH_MEMBERS_LABEL', (int) $row['members'], $row['title'])) ?>"><?= (int) $row['members'] ?></a>
            <?php else : ?><?= (int) $row['members'] ?><?php endif; ?>
            <?php if ($row['few']) : ?><span class="badge text-bg-warning d-block mt-1" data-ut-reach-few><?= Text::_('COM_USERTOOLS_REACH_FEW') ?></span>
            <?php elseif ($row['empty'] && !$row['guest']) : ?><span class="badge text-bg-secondary d-block mt-1"><?= Text::_('COM_USERTOOLS_REACH_EMPTY') ?></span><?php endif; ?>
          </td>
          <td><?php if ($row['superUser']) : ?><?= Text::_('COM_USERTOOLS_REACH_ALL_LEVELS') ?>
            <?php else : ?><?= $this->escape(implode(', ', array_map(fn (int $id): string => $this->levelTitles[$id] ?? '#' . $id, $row['levels']))) ?><?php endif; ?></td>
          <td class="text-end" data-ut-reach-articles="<?= (int) $row['articles'] ?>"><?= (int) $row['articles'] ?></td>
          <td class="text-end" data-ut-reach-exclusive="<?= (int) $row['exclusive'] ?>"><?= (int) $row['exclusive'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p>
  </div>
  <div class="alert alert-info mb-0">
    <p><?= Text::_('COM_USERTOOLS_REACH_NOTE_MEMBERS') ?></p>
    <p><?= Text::_('COM_USERTOOLS_REACH_NOTE_ARTICLES') ?></p>
    <p class="mb-0"><?= Text::_('COM_USERTOOLS_REACH_NOTE_EXCLUSIVE') ?><?php if (!$canUsers) : ?> <?= Text::_('COM_USERTOOLS_REACH_NO_LIST_NOTE') ?><?php endif; ?></p>
  </div>
  <?php endif; ?>
</div>
