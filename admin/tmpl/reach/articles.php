<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\GroupReach;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Reach\HtmlView $this */
$list    = $this->articles;
$group   = $list['group'];
$user    = Factory::getApplication()->getIdentity();
$pages   = max(1, (int) ceil($list['total'] / GroupReach::PAGE_SIZE));
$base    = 'index.php?option=com_usertools&view=reach&layout=articles&group=' . (int) ($group->id ?? 0) . ($list['exclusive'] ? '&only=1' : '');
$path    = $group !== null ? $this->escape($this->paths[$group->id] ?? $group->title) : '';
$heading = Text::sprintf($list['exclusive'] ? 'COM_USERTOOLS_REACH_EXCLUSIVE_HEADING' : 'COM_USERTOOLS_REACH_ARTICLES_HEADING', $path);
$level   = fn (string $title, int $id): string => $title !== '' ? $this->escape($title) : '<span class="badge text-bg-warning">' . Text::sprintf('COM_USERTOOLS_USAGE_MISSING_LEVEL', $id) . '</span>';
?>
<div class="ut-app ut-wide" data-ut-reach-articles-list="<?= (int) ($group->id ?? 0) ?>" data-ut-reach-articles-only="<?= (int) $list['exclusive'] ?>">
  <p><a href="<?= Route::_('index.php?option=com_usertools&view=reach') ?>"><span aria-hidden="true">←</span> <?= Text::_('COM_USERTOOLS_REACH_BACK') ?></a></p>
  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php elseif ($group !== null) : ?>
  <h2 class="h4"><?= $heading ?></h2>
  <p data-ut-reach-articles-total="<?= (int) $list['total'] ?>"><?= Text::plural('COM_USERTOOLS_REACH_ARTICLES_TOTAL', (int) $list['total']) ?></p>
  <?php if ($list['items']) : ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <caption class="visually-hidden"><?= $heading ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_TITLE_COL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_CATEGORY') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_REACH_CATEGORY_LEVEL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_STATE') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_MODIFIED') ?></th>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_AGE_HITS') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list['items'] as $item) :
          $asset = 'com_content.article.' . (int) $item['id'];
          ?>
        <tr data-ut-reach-article="<?= (int) $item['id'] ?>">
          <th scope="row">
            <?php if ($user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && (int) $item['created_by'] === (int) $user->id)) : ?>
            <a href="<?= Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $item['id']) ?>"><?= $this->escape($item['title']) ?></a>
            <?php else : ?><?= $this->escape($item['title']) ?><?php endif; ?>
            <small class="d-block text-body-secondary fw-normal">ID <?= (int) $item['id'] ?></small>
          </th>
          <td><?= $this->escape($item['category']) ?></td>
          <td><?= $level($item['level'], $item['access']) ?></td>
          <td><?= $level($item['categoryLevel'], $item['categoryAccess']) ?></td>
          <td><?= Text::_(ContentAge::stateKey((int) $item['state'])) ?></td>
          <td><?= $this->date($item['modified']) ?></td>
          <td class="text-end"><?= (int) $item['hits'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1) : ?>
  <nav class="ut-actions" aria-label="<?= Text::_('COM_USERTOOLS_AGE_PAGES') ?>">
    <span><?= Text::sprintf('COM_USERTOOLS_AGE_PAGE_OF', (int) $list['page'], $pages) ?></span>
    <div class="d-flex gap-2">
      <?php if ($list['page'] > 1) : ?><a class="btn btn-outline-secondary" href="<?= Route::_($base . '&page=' . ($list['page'] - 1)) ?>"><?= Text::_('JPREV') ?></a><?php endif; ?>
      <?php if ($list['page'] < $pages) : ?><a class="btn btn-outline-secondary" href="<?= Route::_($base . '&page=' . ($list['page'] + 1)) ?>"><?= Text::_('JNEXT') ?></a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
  <p class="text-body-secondary mb-0"><?= Text::_($list['exclusive'] ? 'COM_USERTOOLS_REACH_EXCLUSIVE_NOTE' : 'COM_USERTOOLS_REACH_ARTICLES_NOTE') ?>
    <?php if ($list['limited']) : ?> <?= Text::_('COM_USERTOOLS_REACH_ARTICLES_LIMITED') ?><?php endif; ?></p>
  <?php endif; ?>
</div>
