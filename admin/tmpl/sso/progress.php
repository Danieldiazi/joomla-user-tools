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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sso\HtmlView $this */
?>
<div class="ut-app ut-narrow">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SSO_PROGRESS') ?></h2>
  <p aria-live="polite"><?= Text::sprintf('COM_USERTOOLS_SSO_PROGRESS_COUNT', (int) $this->job['processed'], (int) $this->job['total']) ?></p>
  <p class="alert alert-info"><?= Text::_('COM_USERTOOLS_SSO_PROGRESS_HELP') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=sso') ?>" class="ut-actions">
    <button class="btn btn-primary" name="task" value="sso.next"><?= Text::_('COM_USERTOOLS_SSO_NEXT') ?></button>
    <button class="btn btn-outline-danger" name="task" value="sso.discard"><?= Text::_('COM_USERTOOLS_PROGRESS_DISCARD') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
