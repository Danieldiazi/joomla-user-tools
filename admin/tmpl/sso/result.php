<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sso\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result = $this->result;
$openSessions = array_filter($result['converted'], static fn (array $row): bool => !$row['sessionsClosed']);
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_SSO_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SSO_RESULT_HEADING') ?></h2>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_SSO_CONVERTED' => count($result['converted']),
        'COM_USERTOOLS_SSO_CHANGED' => count($result['changed']),
        'COM_USERTOOLS_STAT_ERRORS' => count($result['errors']),
        'COM_USERTOOLS_SSO_UNSELECTED' => (int) $result['unselected']] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>

  <?php if ($openSessions) : ?>
  <div class="alert alert-warning" role="alert"><?= Text::sprintf('COM_USERTOOLS_SSO_SESSIONS_FAILED', count($openSessions)) ?></div>
  <?php endif; ?>
  <?php if ($result['discarded']) : ?>
  <div class="alert alert-warning"><?= Text::sprintf('COM_USERTOOLS_RESULT_DISCARDED', (int) $result['discarded']) ?></div>
  <?php endif; ?>
  <?php if ($result['converted']) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_SSO_CONVERTED') ?></h3>
    <ul><?php foreach ($result['converted'] as $row) : ?>
      <li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a>
      <?php if (!$row['sessionsClosed']) : ?> · <?= Text::_('COM_USERTOOLS_SSO_SESSIONS_NOTE') ?><?php endif; ?></li>
    <?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['changed']) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_SSO_CHANGED_HEADING') ?></h3>
    <p><?= Text::_('COM_USERTOOLS_SSO_CHANGED_EXPLANATION') ?></p>
    <ul><?php foreach ($result['changed'] as $row) : ?><li><?= $this->escape($row['username']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ERRORS_HEADING') ?></h3>
    <ul><?php foreach ($result['errors'] as $row) : ?><li><?= $this->escape($row['username']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=sso') ?>"><?= Text::_('COM_USERTOOLS_SSO_NEW') ?></a>
  </div>
</div>
