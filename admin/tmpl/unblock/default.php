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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Unblock\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_UNBLOCK_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_UNBLOCK_CHOOSE') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_UNBLOCK_INTRO') ?></p>
  <div class="alert alert-info" data-ut-tour="rules"><?= Text::_('COM_USERTOOLS_UNBLOCK_RULES') ?></div>
  <section class="card ut-card" data-ut-tour="batches"><div class="card-body">
  <?php if ($this->batches) : ?>
    <div class="ut-table-scroll"><table class="table table-sm table-striped" data-ut-unblock-batches><thead><tr>
      <th scope="col"><?= Text::_('COM_USERTOOLS_UNBLOCK_BATCH') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_UNBLOCK_BLOCKED_COUNT') ?></th><th scope="col"><span class="visually-hidden"><?= Text::_('COM_USERTOOLS_UNBLOCK_ACTION') ?></span></th></tr></thead><tbody>
    <?php foreach ($this->batches as $batch) : ?>
      <tr data-ut-unblock-batch="<?= $this->escape($batch['ref']) ?>"><td><?= $this->escape($batch['label']) ?></td><td><?= (int) $batch['blocked'] ?></td>
        <td class="text-end"><form method="post" action="<?= Route::_('index.php?option=com_usertools&view=unblock') ?>" class="d-inline">
          <input type="hidden" name="batch" value="<?= $this->escape($batch['ref']) ?>">
          <button class="btn btn-sm btn-primary" name="task" value="unblock.select" aria-label="<?= Text::sprintf('COM_USERTOOLS_UNBLOCK_REVIEW_BATCH', $this->escape($batch['label'])) ?>"><?= Text::_('COM_USERTOOLS_UNBLOCK_REVIEW') ?></button>
          <?= HTMLHelper::_('form.token') ?>
        </form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php else : ?>
    <p class="mb-0" data-ut-unblock-none><?= Text::_('COM_USERTOOLS_UNBLOCK_NONE') ?></p>
  <?php endif; ?>
  </div></section>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
  </div>
</div>
