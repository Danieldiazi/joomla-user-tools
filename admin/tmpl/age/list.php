<?php

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAgeReader;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Age\HtmlView $this */
$list    = $this->list;
$source  = ContentAgeReader::KINDS[$this->kind];
$levelQ  = $this->level > 0 ? '&level=' . $this->level : '';
$base    = 'index.php?option=com_usertools&view=age&layout=list&kind=' . $this->kind . '&year=' . $this->year . $levelQ;
$pages   = max(1, (int) ceil($list['total'] / ContentAge::PAGE_SIZE));
$user    = Factory::getApplication()->getIdentity();
$text    = fn (string $key, string $argument = '') => $argument === '' ? Text::_($key) : Text::sprintf($key, $argument);
$heading = Text::sprintf('COM_USERTOOLS_AGE_LIST_HEADING', Text::_($source['label']), $this->year);
?>
<div class="ut-app ut-wide" data-ut-age-list="<?= $this->escape($this->kind) ?>" data-ut-age-list-year="<?= (int) $this->year ?>">
  <p><a href="<?= Route::_('index.php?option=com_usertools&view=age' . $levelQ) ?>"><span aria-hidden="true">←</span> <?= Text::_('COM_USERTOOLS_AGE_BACK') ?></a></p>
  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= $this->escape($heading) ?><?php if ($this->level > 0) : ?> · <?= $this->escape($this->levelTitle()) ?><?php endif; ?></h2>
    <div class="ut-access-tools">
      <a class="btn btn-outline-secondary" data-ut-export="age-list"
        href="<?= Route::_('index.php?option=com_usertools&task=age.export&kind=' . $this->kind . '&year=' . $this->year . $levelQ . '&' . Session::getFormToken() . '=1') ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a>
    </div>
  </div>
  <p class="mt-2" data-ut-age-list-total="<?= (int) $list['total'] ?>"><?= Text::sprintf($list['total'] === 1 ? 'COM_USERTOOLS_AGE_LIST_TOTAL_1' : 'COM_USERTOOLS_AGE_LIST_TOTAL', (int) $list['total']) ?>
    <?= Text::_('COM_USERTOOLS_AGE_LIST_ORDER') ?></p>
  <?php if (!$list['items']) : ?>
    <p class="text-muted"><?= Text::_('COM_USERTOOLS_AGE_LIST_EMPTY') ?></p>
  <?php else : ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <caption class="visually-hidden"><?= $this->escape($heading) ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_TITLE_COL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_CATEGORY') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_AUTHOR') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_STATE') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_CREATED') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_MODIFIED') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list['items'] as $item) : ?>
        <tr data-ut-age-item="<?= (int) $item['id'] ?>">
          <th scope="row">
            <?php $asset = str_replace('{id}', (string) (int) $item['id'], $source['item']); ?>
            <?php if ($user->authorise('core.edit', $asset) || $user->authorise('core.edit.own', $asset)) : ?>
            <a href="<?= Route::_(str_replace('{id}', (string) (int) $item['id'], $source['edit'])) ?>"><?= $this->escape($item['title']) ?></a>
            <?php else : ?><?= $this->escape($item['title']) ?><?php endif; ?>
            <small class="d-block text-body-secondary fw-normal">ID <?= (int) $item['id'] ?></small>
          </th>
          <td><?= $this->escape($item['category']) ?></td>
          <td><?= $item['level'] === '' ? '<span class="badge text-bg-warning">' . $this->escape(ContentAge::levelName($item, $text)) . '</span>' : $this->escape($item['level']) ?></td>
          <td><?= $this->escape($item['author']) ?></td>
          <td><?= Text::_(ContentAge::stateKey((int) $item['state'])) ?></td>
          <td><?= $this->date($item['created']) ?></td>
          <td><?= $this->date($item['modified']) ?></td>
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
  <?php endif; ?>
</div>
