<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Home\HtmlView $this */
$states = [
    'enabled'  => ['success', 'COM_USERTOOLS_PLUGIN_ENABLED'],
    'disabled' => ['warning', 'COM_USERTOOLS_PLUGIN_DISABLED'],
    'missing'  => ['danger', 'COM_USERTOOLS_PLUGIN_MISSING'],
];
[$badge, $stateKey] = $states[$this->pluginState];
?>
<div class="ut-app">
  <p class="lead"><?= Text::_('COM_USERTOOLS_HOME_INTRO') ?></p>
  <div class="row g-4">
    <?php if ($this->can('usertools.compare')) : ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-primary align-self-start mb-2"><?= Text::_('COM_USERTOOLS_MANUAL') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_COMPARE_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_COMPARE_HOME_DESC') ?></p>
        <ol class="mb-4"><li><?= Text::_('COM_USERTOOLS_COMPARE_HOME_STEP1') ?></li><li><?= Text::_('COM_USERTOOLS_COMPARE_HOME_STEP2') ?></li><li><?= Text::_('COM_USERTOOLS_COMPARE_HOME_STEP3') ?></li></ol>
        <a class="btn btn-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=compare') ?>"><?= Text::_('COM_USERTOOLS_COMPARE_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.inactive')) : ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-primary align-self-start mb-2"><?= Text::_('COM_USERTOOLS_MANUAL') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_INACTIVE_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_INACTIVE_HOME_DESC') ?></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=inactive') ?>"><?= Text::_('COM_USERTOOLS_INACTIVE_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.delete')) : ?>
    <div class="col-lg-6">
      <section class="card h-100 border-danger-subtle"><div class="card-body d-flex flex-column">
        <span class="badge bg-danger align-self-start mb-2"><?= Text::_('COM_USERTOOLS_MANUAL_IRREVERSIBLE') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_DELETE_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_DELETE_HOME_DESC') ?></p>
        <a class="btn btn-outline-danger mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=delete') ?>"><?= Text::_('COM_USERTOOLS_DELETE_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.ungroup')) : ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-primary align-self-start mb-2"><?= Text::_('COM_USERTOOLS_MANUAL') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_UNGROUP_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_UNGROUP_HOME_DESC') ?></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=ungroup') ?>"><?= Text::_('COM_USERTOOLS_UNGROUP_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-secondary align-self-start mb-2"><?= Text::_('COM_USERTOOLS_SCHEDULED') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_SCHEDULE_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_SCHEDULE_HOME_DESC') ?></p>
        <p><span class="badge bg-<?= $badge ?>"><?= Text::_($stateKey) ?></span></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=schedule') ?>"><?= Text::_('COM_USERTOOLS_SCHEDULE_START') ?></a>
      </div></section>
    </div>
    <?php if ($this->can('usertools.reports')) : ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-secondary align-self-start mb-2"><?= Text::_('COM_USERTOOLS_SCHEDULED') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_REPORTS') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_REPORTS_HOME_DESC') ?></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=reports') ?>"><?= Text::_('COM_USERTOOLS_REPORTS_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.access')) : ?>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-info align-self-start mb-2"><?= Text::_('COM_USERTOOLS_READ_ONLY') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_ACCESS_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_ACCESS_HOME_DESC') ?></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=access') ?>"><?= Text::_('COM_USERTOOLS_ACCESS_START') ?></a>
      </div></section>
    </div>
    <div class="col-lg-6">
      <section class="card h-100"><div class="card-body d-flex flex-column">
        <span class="badge bg-info align-self-start mb-2"><?= Text::_('COM_USERTOOLS_READ_ONLY') ?></span>
        <h2 class="h4"><?= Text::_('COM_USERTOOLS_USAGE_TITLE') ?></h2>
        <p><?= Text::_('COM_USERTOOLS_USAGE_HOME_DESC') ?></p>
        <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=usage') ?>"><?= Text::_('COM_USERTOOLS_USAGE_START') ?></a>
      </div></section>
    </div>
    <?php endif; ?>
  </div>
  <div class="alert alert-info mt-4 mb-0"><?= Text::_('COM_USERTOOLS_HOME_NOTE') ?></div>
</div>
