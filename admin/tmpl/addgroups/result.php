<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Addgroups\HtmlView $this */
$result = $this->result;
?>
<div class="ut-app">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ADDGROUPS_RESULT') ?></h2>
  <div class="ut-stats">
    <?php foreach (['added' => 'COM_USERTOOLS_ADDGROUPS_STATUS_ADDED', 'changed' => 'COM_USERTOOLS_ADDGROUPS_STATUS_CHANGED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $key => $label) : ?>
    <div class="card"><div class="card-body"><strong><?= count($result[$key]) ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php foreach (['added', 'changed', 'errors'] as $status) : ?>
  <?php if ($result[$status]) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ADDGROUPS_STATUS_' . strtoupper($status)) ?></h3>
    <ul><?php foreach ($result[$status] as $row) : ?>
      <li data-ut-addgroups-result="<?= $status ?>" data-ut-addgroups-id="<?= (int) $row['id'] ?>">
        <?= $this->escape($row['username']) ?> —
        <?php foreach ($row['targets'] as $id) : ?><?= $this->escape($this->groupTitle((int) $id)) ?> <?php endforeach; ?>
      </li>
    <?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <?php endforeach; ?>
  <div class="ut-actions"><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>"><?= Text::_('COM_USERTOOLS_ADDGROUPS_AGAIN') ?></a></div>
</div>
