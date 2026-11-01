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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Handover\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$inventory = $this->inventory;
$source = $inventory['source'];
$selected = array_fill_keys($this->draft['selected'], true);
$fresh = !$this->draft['selected'];
$editable = array_filter($inventory['articles'], static fn (array $a): bool => $a['editable']);
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'select', 'label' => 'COM_USERTOOLS_HANDOVER_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_HANDOVER_STEP_SELECT') ?></h2>
  <p data-ut-handover-account="<?= (int) $source->id ?>"><?= Text::sprintf('COM_USERTOOLS_HANDOVER_SOURCE_IS', Text::sprintf('COM_USERTOOLS_ACCOUNT_LABEL', $this->escape($source->username), $this->escape($source->name), $this->escape($source->email), (int) $source->id)) ?>
    <a href="<?= Route::_('index.php?option=com_usertools&view=handover') ?>"><?= Text::_('COM_USERTOOLS_HANDOVER_OTHER_ACCOUNT') ?></a></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=handover') ?>" data-ut-handover-select>
    <section class="card ut-card"><div class="card-body">
      <h3 class="h5"><?= Text::plural('COM_USERTOOLS_HANDOVER_ARTICLES', (int) $inventory['total']) ?></h3>
      <?php if ($inventory['articles']) : ?>
      <p><?= Text::_('COM_USERTOOLS_HANDOVER_ARTICLES_HELP') ?> <a href="<?= $this->escape($inventory['listUrl']) ?>" target="_blank" rel="noopener"><?= Text::_('COM_USERTOOLS_HANDOVER_LIST_LINK') ?></a></p>
      <?php if ($inventory['total'] > count($inventory['articles'])) : ?><p class="alert alert-info" data-ut-handover-more><?= Text::sprintf('COM_USERTOOLS_HANDOVER_MORE', count($inventory['articles']), (int) $inventory['total']) ?></p><?php endif; ?>
      <div class="table-responsive ut-table-scroll">
        <table class="table table-striped table-sm">
          <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_HANDOVER_ARTICLES_TABLE') ?></caption>
          <thead><tr>
            <th scope="col"><?= Text::_('COM_USERTOOLS_HANDOVER_COL_TRANSFER') ?></th>
            <th scope="col"><?= Text::_('JGLOBAL_TITLE') ?></th>
            <th scope="col"><?= Text::_('JSTATUS') ?></th>
            <th scope="col"><?= Text::_('JCATEGORY') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_HANDOVER_COL_ALIAS') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($inventory['articles'] as $article) : $cid = 'ut-handover-article-' . (int) $article['id']; ?>
          <tr data-ut-handover-article="<?= (int) $article['id'] ?>" data-ut-handover-editable="<?= $article['editable'] ? '1' : '0' ?>">
            <td>
              <?php if ($article['editable']) : ?>
              <input class="form-check-input" type="checkbox" name="articles[]" value="<?= (int) $article['id'] ?>" id="<?= $cid ?>"<?= ($fresh || isset($selected[(int) $article['id']])) ? ' checked' : '' ?>>
              <label class="visually-hidden" for="<?= $cid ?>"><?= Text::sprintf('COM_USERTOOLS_HANDOVER_TRANSFER_ONE', $this->escape($article['title'])) ?></label>
              <?php else : ?>
              <span><?= Text::_($article['lockedBy'] ? 'COM_USERTOOLS_HANDOVER_LOCKED' : 'COM_USERTOOLS_HANDOVER_NOT_EDITABLE') ?></span>
              <?php endif; ?>
            </td>
            <td><?= $this->escape($article['title']) ?> <small class="text-muted">(ID <?= (int) $article['id'] ?>)</small></td>
            <td><?= $this->stateLabel((int) $article['state']) ?></td>
            <td><?= $this->escape($article['category']) ?></td>
            <td><?= $article['alias'] !== '' ? $this->escape($article['alias']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else : ?>
      <p data-ut-handover-empty><?= Text::_('COM_USERTOOLS_HANDOVER_NO_ARTICLES') ?></p>
      <?php endif; ?>
      <?php if ($inventory['others']) : ?>
      <h4 class="h6 mt-3"><?= Text::_('COM_USERTOOLS_HANDOVER_OTHERS') ?></h4>
      <ul data-ut-handover-others>
        <?php foreach ($inventory['others'] as $other) : ?>
        <li><?= Text::sprintf('COM_USERTOOLS_HANDOVER_OTHER_' . strtoupper($other['kind']), (int) $other['count'], $this->escape($other['component'])) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="form-text"><?= Text::_('COM_USERTOOLS_HANDOVER_OTHERS_HELP') ?></p>
      <?php endif; ?>
    </div></section>
    <?php if ($editable) : ?>
    <section class="card ut-card"><div class="card-body">
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_HANDOVER_TARGET_TITLE') ?></h3>
      <?= LayoutHelper::render('usertools.account', ['id' => 'ut-handover-target', 'tool' => 'handover', 'login' => $this->draft['targetLogin'],
          'account' => $this->draft['target'], 'candidates' => $this->draft['targetCandidates'], 'label' => 'COM_USERTOOLS_HANDOVER_TARGET',
          'help' => 'COM_USERTOOLS_HANDOVER_TARGET_HELP', 'required' => true], $layouts) ?>
    </div></section>
    <div class="ut-actions"><button class="btn btn-primary" name="task" value="handover.preview"><?= Text::_('COM_USERTOOLS_HANDOVER_PREVIEW') ?></button></div>
    <?php else : ?>
    <p class="text-muted" data-ut-handover-nothing><?= Text::_('COM_USERTOOLS_HANDOVER_NOTHING_TO_TRANSFER') ?></p>
    <?php endif; ?>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
