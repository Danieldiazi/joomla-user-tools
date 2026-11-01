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
$review = $this->review;
$label = fn (object $u): string => Text::sprintf('COM_USERTOOLS_ACCOUNT_LABEL', $this->escape($u->username), $this->escape($u->name), $this->escape($u->email), (int) $u->id);
$back = Route::_('index.php?option=com_usertools&view=handover&layout=select');
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_HANDOVER_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_HANDOVER_STEP_REVIEW') ?></h2>
  <dl class="row" data-ut-handover-summary data-ut-handover-count="<?= (int) $review['count'] ?>">
    <dt class="col-sm-3"><?= Text::_('COM_USERTOOLS_HANDOVER_FROM') ?></dt><dd class="col-sm-9" data-ut-handover-from="<?= (int) $review['source'] ?>"><?= $label($review['sourceRecord']) ?></dd>
    <dt class="col-sm-3"><?= Text::_('COM_USERTOOLS_HANDOVER_TO') ?></dt><dd class="col-sm-9" data-ut-handover-to="<?= (int) $review['target'] ?>"><?= $label($review['targetRecord']) ?>
      <br><small><?= Text::sprintf('COM_USERTOOLS_HANDOVER_TARGET_GROUPS', $this->escape(implode(', ', $review['targetGroups']))) ?></small></dd>
    <dt class="col-sm-3"><?= Text::_('COM_USERTOOLS_HANDOVER_HOW_MANY') ?></dt><dd class="col-sm-9"><?= Text::plural('COM_USERTOOLS_HANDOVER_COUNT', (int) $review['count']) ?></dd>
  </dl>
  <div class="alert alert-info" data-ut-handover-policy><?= Text::_('COM_USERTOOLS_HANDOVER_POLICY') ?></div>
  <?php if ($review['editOwn']) : ?><div class="alert alert-warning" data-ut-handover-edit-own><?= Text::_('COM_USERTOOLS_HANDOVER_EDIT_OWN') ?></div><?php endif; ?>
  <section class="card ut-card"><div class="card-body"><div class="table-responsive ut-table-scroll">
    <table class="table table-striped table-sm">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_HANDOVER_ARTICLES_TABLE') ?></caption>
      <thead><tr><th scope="col"><?= Text::_('JGLOBAL_TITLE') ?></th><th scope="col"><?= Text::_('JSTATUS') ?></th><th scope="col"><?= Text::_('JCATEGORY') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_HANDOVER_COL_ALIAS') ?></th></tr></thead>
      <tbody>
      <?php foreach ($review['articles'] as $article) : ?>
      <tr data-ut-handover-reviewed="<?= (int) $article['id'] ?>"><td><?= $this->escape($article['title']) ?> <small class="text-muted">(ID <?= (int) $article['id'] ?>)</small></td>
        <td><?= $this->stateLabel((int) $article['state']) ?></td><td><?= $this->escape($article['category']) ?></td>
        <td><?= $article['alias'] !== '' ? Text::sprintf('COM_USERTOOLS_HANDOVER_ALIAS_KEPT', $this->escape($article['alias'])) : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="mb-0"><a href="<?= $this->escape($review['listUrl']) ?>" target="_blank" rel="noopener"><?= Text::_('COM_USERTOOLS_HANDOVER_LIST_LINK') ?></a></p>
  </div></section>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=handover') ?>" data-ut-handover-apply>
    <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_HANDOVER_CONFIRM', 'count' => $review['count'], 'threshold' => $review['threshold'],
        'help' => 'COM_USERTOOLS_HANDOVER_TYPE_COUNT_HELP', 'typed' => 'COM_USERTOOLS_HANDOVER_TYPE_COUNT'], $layouts) ?>
    <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= $back ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
      <button class="btn btn-primary" name="task" value="handover.apply"><?= Text::plural('COM_USERTOOLS_HANDOVER_APPLY', (int) $review['count']) ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
