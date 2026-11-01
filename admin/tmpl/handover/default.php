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
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'account', 'label' => 'COM_USERTOOLS_HANDOVER_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_HANDOVER_STEP_ACCOUNT') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_HANDOVER_INTRO') ?></p>
  <div class="alert alert-info"><?= Text::_('COM_USERTOOLS_HANDOVER_RULES') ?></div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=handover') ?>" data-ut-handover-source>
    <section class="card ut-card"><div class="card-body">
      <?= LayoutHelper::render('usertools.account', ['id' => 'ut-handover-login', 'tool' => 'handover', 'login' => $this->draft['login'],
          'account' => $this->draft['source'], 'candidates' => $this->draft['candidates'], 'label' => 'COM_USERTOOLS_HANDOVER_SOURCE',
          'help' => 'COM_USERTOOLS_ACCOUNT_HELP', 'required' => true], $layouts) ?>
    </div></section>
    <div class="ut-actions"><button class="btn btn-primary" name="task" value="handover.source"><?= Text::_('COM_USERTOOLS_HANDOVER_CONTINUE') ?></button></div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
