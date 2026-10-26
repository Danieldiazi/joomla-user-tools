<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Ungroup\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$suggest = Route::_('index.php?option=com_usertools&task=ungroup.suggest&' . Session::getFormToken() . '=1', false);
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'setup', 'label' => 'COM_USERTOOLS_UNGROUP_PROGRESS'], $layouts) ?>
  <p class="lead"><?= Text::_('COM_USERTOOLS_UNGROUP_INTRO') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>" data-ut-ungroup-choose>
    <section class="card ut-card"><div class="card-body">
      <label class="form-label" for="ut-ungroup-login"><?= Text::_('COM_USERTOOLS_UNGROUP_LOGIN') ?></label>
      <input class="form-control" type="text" name="login" id="ut-ungroup-login" required autocomplete="off" list="ut-ungroup-accounts"
        value="<?= $this->escape($this->draft['login']) ?>" aria-describedby="ut-ungroup-login-help" data-ut-account-search="<?= $this->escape($suggest) ?>">
      <datalist id="ut-ungroup-accounts"></datalist>
      <div class="form-text" id="ut-ungroup-login-help"><?= Text::_('COM_USERTOOLS_UNGROUP_LOGIN_HELP') ?></div>
    </div></section>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
      <button class="btn btn-primary" name="task" value="ungroup.choose"><?= Text::_('COM_USERTOOLS_UNGROUP_NEXT_GROUPS') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
