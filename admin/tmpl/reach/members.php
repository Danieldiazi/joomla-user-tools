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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Reach\HtmlView $this */
$members    = $this->members;
$group      = $members['group'];
$canProfile = $this->canProfile();
$pages      = max(1, (int) ceil($members['total'] / GroupReach::PAGE_SIZE));
$base       = 'index.php?option=com_usertools&view=reach&layout=members&group=' . (int) ($group->id ?? 0);
$heading    = $group !== null ? Text::sprintf('COM_USERTOOLS_REACH_MEMBERS_HEADING', $this->escape($this->paths[$group->id] ?? $group->title)) : '';
?>
<div class="ut-app ut-wide" data-ut-reach-list="<?= (int) ($group->id ?? 0) ?>">
  <p><a href="<?= Route::_('index.php?option=com_usertools&view=reach') ?>"><span aria-hidden="true">←</span> <?= Text::_('COM_USERTOOLS_REACH_BACK') ?></a></p>
  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php elseif ($group !== null) : ?>
  <h2 class="h4"><?= $heading ?></h2>
  <p data-ut-reach-list-total="<?= (int) $members['total'] ?>"><?= Text::plural('COM_USERTOOLS_REACH_MEMBERS_TOTAL', (int) $members['total']) ?>
    <?php if ($members['blocked'] > 0) : ?><?= Text::plural('COM_USERTOOLS_REACH_MEMBERS_BLOCKED', (int) $members['blocked']) ?><?php endif; ?></p>
  <?php if ($members['accounts']) : ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <caption class="visually-hidden"><?= $heading ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_ACCOUNT') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_ACCOUNT_GROUPS') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_LASTVISIT') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_REGISTERED') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($members['accounts'] as $account) : ?>
        <tr data-ut-reach-account="<?= (int) $account['id'] ?>">
          <th scope="row">
            <?php if ($canProfile) : ?><a href="<?= Route::_('index.php?option=com_usertools&view=profile&account=' . (int) $account['id']) ?>"><?= $this->escape($account['name']) ?></a>
            <?php else : ?><?= $this->escape($account['name']) ?><?php endif; ?>
            <small class="d-block text-body-secondary fw-normal"><?= $this->escape($account['username']) ?></small>
          </th>
          <td><?= $this->groupTitles($account['groups'], $this->groups) ?></td>
          <td><?= $this->date($account['lastvisit']) ?></td>
          <td><?= $this->date($account['registered']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1) : ?>
  <nav class="ut-actions" aria-label="<?= Text::_('COM_USERTOOLS_AGE_PAGES') ?>">
    <span><?= Text::sprintf('COM_USERTOOLS_AGE_PAGE_OF', (int) $members['page'], $pages) ?></span>
    <div class="d-flex gap-2">
      <?php if ($members['page'] > 1) : ?><a class="btn btn-outline-secondary" href="<?= Route::_($base . '&page=' . ($members['page'] - 1)) ?>"><?= Text::_('JPREV') ?></a><?php endif; ?>
      <?php if ($members['page'] < $pages) : ?><a class="btn btn-outline-secondary" href="<?= Route::_($base . '&page=' . ($members['page'] + 1)) ?>"><?= Text::_('JNEXT') ?></a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
  <p class="text-body-secondary mb-0"><?= Text::_('COM_USERTOOLS_REACH_MEMBERS_NOTE') ?></p>
  <?php endif; ?>
</div>
