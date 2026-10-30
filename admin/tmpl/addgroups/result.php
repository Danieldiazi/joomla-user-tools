<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Addgroups\HtmlView $this */
$result = $this->result;
$open = array_values(array_filter($result['added'], static fn (array $r): bool => ($r['sessions'] ?? '') === 'open'));
$unknown = array_values(array_filter($result['added'], static fn (array $r): bool => ($r['sessions'] ?? '') === 'unknown'));
?>
<div class="ut-app">
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ADDGROUPS_RESULT') ?></h2>
  <div class="ut-stats">
    <?php foreach (['added' => 'COM_USERTOOLS_ADDGROUPS_STATUS_ADDED', 'changed' => 'COM_USERTOOLS_ADDGROUPS_STATUS_CHANGED', 'errors' => 'COM_USERTOOLS_STAT_ERRORS'] as $key => $label) : ?>
    <div class="card"><div class="card-body"><strong><?= count($result[$key]) ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($open) : ?>
  <div class="alert alert-danger" data-ut-addgroups-sessions="open">
    <p><?= Text::sprintf('COM_USERTOOLS_ADDGROUPS_SESSIONS_OPEN', $this->escape(implode(', ', array_column($open, 'username')))) ?></p>
    <?php foreach ($open as $row) : ?>
    <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=addgroups') ?>" class="mb-2" data-ut-addgroups-retry="<?= (int) $row['id'] ?>">
      <input type="hidden" name="account" value="<?= (int) $row['id'] ?>">
      <button class="btn btn-outline-danger btn-sm" name="task" value="addgroups.sessions"><?= Text::sprintf('COM_USERTOOLS_ADDGROUPS_SESSIONS_RETRY', $this->escape($row['username'])) ?></button>
      <?= HTMLHelper::_('form.token') ?>
    </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($unknown) : ?>
  <div class="alert alert-warning" data-ut-addgroups-sessions="unknown"><?= Text::sprintf('COM_USERTOOLS_ADDGROUPS_SESSIONS_UNKNOWN', $this->escape(implode(', ', array_column($unknown, 'username')))) ?></div>
  <?php endif; ?>
  <?php foreach (['added', 'changed', 'errors'] as $status) : ?>
  <?php if ($result[$status]) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ADDGROUPS_STATUS_' . strtoupper($status)) ?></h3>
    <ul><?php foreach ($result[$status] as $row) : ?>
      <li data-ut-addgroups-result="<?= $status ?>" data-ut-addgroups-id="<?= (int) $row['id'] ?>"<?= $status === 'added' ? ' data-ut-addgroups-session="' . $this->escape($row['sessions'] ?? '') . '"' : '' ?>>
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
