<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
/** @var \Danieldiazi\Component\Usertools\Administrator\View\Names\HtmlView $this */
$pending = count($this->job['pending']);
$processed = count($this->job['updated']) + count($this->job['changed']) + count($this->job['errors']);
$total = $processed + $pending;
$percentage = $total > 0 ? (int) floor(100 * $processed / $total) : 100;
// An error redirects here with autostart=0; a deliberate click can still resume the saved job.
$autostart = Factory::getApplication()->getInput()->getInt('autostart', 1) === 1;
?>
<div class="ut-app ut-narrow" data-ut-names-progress data-ut-names-auto="<?= $autostart ? '1' : '0' ?>">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_NAMES_PROGRESS') ?></h2>
  <p><?= Text::sprintf('COM_USERTOOLS_JS_PROGRESS', $processed, $total) ?></p>
  <div class="progress mb-3" role="progressbar" aria-label="<?= $this->escape(Text::_('COM_USERTOOLS_NAMES_PROGRESS')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percentage ?>">
    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: <?= $percentage ?>%"><?= $percentage ?>%</div>
  </div>
  <p><?= Text::sprintf('COM_USERTOOLS_NAMES_PENDING', $pending) ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=names') ?>" data-ut-names-next>
    <button class="btn btn-primary" name="task" value="names.next"><?= Text::_('COM_USERTOOLS_NAMES_NEXT') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
