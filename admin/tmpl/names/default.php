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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Names\HtmlView $this */
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_NAMES_TITLE'], JPATH_ADMINISTRATOR . '/components/com_usertools/layouts') ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_NAMES_CHOOSE') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_NAMES_INTRO') ?></p>
  <div class="alert alert-warning" data-ut-tour="format"><?= Text::_('COM_USERTOOLS_NAMES_RULES') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=names') ?>" data-ut-names-form>
    <label class="form-label" for="ut-names-lines"><?= Text::_('COM_USERTOOLS_NAMES_LINES') ?></label>
    <textarea class="form-control" id="ut-names-lines" name="lines" rows="12" required data-ut-tour="input"><?= $this->escape($this->draft) ?></textarea>
    <p class="form-text"><?= Text::_('COM_USERTOOLS_NAMES_LINES_HELP') ?></p>
    <div class="ut-actions" data-ut-tour="actions"><button class="btn btn-primary" name="task" value="names.preview"><?= Text::_('COM_USERTOOLS_NAMES_PREVIEW') ?></button></div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
