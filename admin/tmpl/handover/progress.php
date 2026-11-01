<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Handover\HtmlView $this */
$done = (int) $this->job['total'] - count($this->job['pending']);
?>
<div class="ut-app ut-narrow">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_HANDOVER_PROGRESS') ?></h2>
  <p role="status" data-ut-handover-pending="<?= count($this->job['pending']) ?>"><?= Text::sprintf('COM_USERTOOLS_HANDOVER_PROGRESS_TEXT', $done, (int) $this->job['total']) ?></p>
  <progress class="w-100" max="<?= max(1, (int) $this->job['total']) ?>" value="<?= $done ?>" aria-label="<?= Text::_('COM_USERTOOLS_HANDOVER_PROGRESS') ?>"></progress>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=handover') ?>" class="ut-actions" data-ut-handover-next>
    <button class="btn btn-primary" name="task" value="handover.next"><?= Text::_('COM_USERTOOLS_HANDOVER_NEXT') ?></button>
    <button class="btn btn-outline-danger" name="task" value="handover.discard"><?= Text::_('COM_USERTOOLS_HANDOVER_DISCARD') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
