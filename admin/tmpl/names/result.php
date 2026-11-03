<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
/** @var \Danieldiazi\Component\Usertools\Administrator\View\Names\HtmlView $this */
$result = $this->result;
?>
<div class="ut-app">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_NAMES_RESULT') ?></h2>
  <div class="ut-stats">
    <?php foreach (['updated' => 'COM_USERTOOLS_NAMES_STATUS_UPDATED', 'changed' => 'COM_USERTOOLS_NAMES_STATUS_CHANGED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $key => $label) : ?>
    <div class="card"><div class="card-body"><strong><?= count($result[$key]) ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php foreach (['updated', 'changed', 'errors'] as $status) : ?>
  <?php if ($result[$status]) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_NAMES_STATUS_' . strtoupper($status)) ?></h3>
    <ul><?php foreach ($result[$status] as $row) : ?>
      <li data-ut-names-result="<?= $status ?>" data-ut-names-id="<?= (int) $row['id'] ?>">
        <?= $this->escape($row['username']) ?> — <?= $this->escape($row['before']) ?> → <?= $this->escape($row['after']) ?>
        <?php if ($status === 'changed') : ?> (<?= Text::_('COM_USERTOOLS_NAMES_RESULT_STALE') ?>)<?php elseif ($status === 'errors') : ?> (<?= Text::_('COM_USERTOOLS_NAMES_RESULT_ERROR') ?>)<?php endif; ?>
      </li>
    <?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php endforeach; ?>
  <div class="ut-actions"><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=names') ?>"><?= Text::_('COM_USERTOOLS_NAMES_AGAIN') ?></a></div>
</div>
