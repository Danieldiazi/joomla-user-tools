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
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <p class="lead"><?= Text::_('COM_USERTOOLS_UNGROUP_INTRO') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-choose>
    <section class="card ut-card" data-ut-tour="account"><div class="card-body">
      <?= LayoutHelper::render('usertools.account', ['id' => 'ut-ungroup-login', 'tool' => 'ungroup', 'login' => $this->draft['login'], 'account' => $this->draft['account'],
        'candidates' => $this->draft['candidates'], 'label' => 'COM_USERTOOLS_UNGROUP_LOGIN', 'help' => 'COM_USERTOOLS_ACCOUNT_HELP', 'required' => true], $layouts) ?>
    </div></section>
    <div class="ut-actions" data-ut-tour="actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
      <button class="btn btn-primary" name="task" value="ungroup.choose"><?= Text::_('COM_USERTOOLS_UNGROUP_NEXT_GROUPS') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
