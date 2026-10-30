<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Schedule\HtmlView $this */
$state = [
    'enabled'  => ['success', 'COM_USERTOOLS_SCHEDULE_STATE_ENABLED'],
    'disabled' => ['warning', 'COM_USERTOOLS_SCHEDULE_STATE_DISABLED'],
    'missing'  => ['danger', 'COM_USERTOOLS_SCHEDULE_STATE_MISSING'],
][$this->pluginState];
?>
<div class="ut-app ut-narrow">
  <div class="alert alert-<?= $state[0] ?>"><?= Text::_($state[1]) ?></div>
  <section class="card"><div class="card-body">
    <h2 class="h4"><?= Text::_('COM_USERTOOLS_SCHEDULE_HEADING') ?></h2>
    <ol class="ut-instructions">
      <li><strong><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP1') ?></strong><p><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP1_DESC') ?></p>
        <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_plugins&view=plugins&filter[folder]=task&filter[search]=usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_PLUGINS') ?></a></li>
      <li><strong><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP2') ?></strong><p><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP2_DESC') ?></p>
        <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_scheduler&view=tasks') ?>"><?= Text::_('COM_USERTOOLS_OPEN_TASKS') ?></a></li>
      <li><strong><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP3') ?></strong><p><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP3_DESC') ?></p></li>
      <li><strong><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP4') ?></strong><p><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP4_DESC') ?></p></li>
      <li><strong><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP5') ?></strong><p><?= Text::_('COM_USERTOOLS_SCHEDULE_STEP5_DESC') ?></p></li>
    </ol>
  </div></section>
</div>
