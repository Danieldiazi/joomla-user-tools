<?php

\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
/** @var \Danieldiazi\Component\Usertools\Administrator\View\Addgroups\HtmlView $this */
?>
<div class="ut-app ut-narrow">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ADDGROUPS_PROGRESS') ?></h2>
  <p><?= Text::sprintf('COM_USERTOOLS_ADDGROUPS_PENDING', count($this->job['pending'])) ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>" data-ut-addgroups-next>
    <button class="btn btn-primary" name="task" value="addgroups.next"><?= Text::_('COM_USERTOOLS_ADDGROUPS_NEXT') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
