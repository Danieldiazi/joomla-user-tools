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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Bulkcreate\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result = $this->result;
$byMode = [];
foreach ($result['created'] as $row) {
    $byMode[(string) ($row['mode'] ?? '')][] = $row;
}
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'result', 'label' => 'COM_USERTOOLS_BULKCREATE_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_BULKCREATE_RESULT') ?></h2>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_STAT_CREATED' => count($result['created']), 'COM_USERTOOLS_STAT_SKIPPED' => count($result['skipped']),
        'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($result['discarded'])) : ?>
    <div class="alert alert-warning"><?= Text::sprintf('COM_USERTOOLS_RESULT_DISCARDED', (int) $result['discarded']) ?></div>
  <?php endif; ?>

  <?php if (!empty($byMode['password'])) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_PASSWORDS_HEADING') ?></h3>
    <p class="alert alert-warning"><?= Text::_('COM_USERTOOLS_PASSWORDS_ONCE') ?></p>
    <div class="ut-table-scroll"><table class="table table-sm">
      <thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_TEMP_PASSWORD') ?></th></tr></thead><tbody>
      <?php foreach ($byMode['password'] as $row) : ?>
      <tr><td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></td>
        <td><?= $this->escape($row['email']) ?></td>
        <td><?php if (isset($row['password'])) : ?>
          <code class="ut-secret"><?= $this->escape($row['password']) ?></code>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-ut-copy="<?= $this->escape($row['password']) ?>"><?= Text::_('COM_USERTOOLS_COPY') ?></button>
        <?php else : ?><?= Text::_('COM_USERTOOLS_PASSWORD_ALREADY_SHOWN') ?><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div></section>
  <?php endif; ?>

  <?php if (!empty($byMode['email'])) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_MAILED_HEADING') ?></h3>
    <ul class="mb-0">
      <?php foreach ($byMode['email'] as $row) : ?>
      <li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a>
        · <?= $this->escape($row['email']) ?> ·
        <?= !empty($row['mailed']) ? '<span class="text-success">' . Text::_('COM_USERTOOLS_MAIL_SENT') . '</span>'
            : '<span class="text-danger">' . Text::_('COM_USERTOOLS_MAIL_FAILED') . '</span>' ?></li>
      <?php endforeach; ?>
    </ul>
  </div></section>
  <?php endif; ?>

  <?php if (!empty($byMode['sso'])) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_SSO_HEADING') ?></h3>
    <p><?= Text::_('COM_USERTOOLS_SSO_RESULT') ?></p>
    <ul class="mb-0">
      <?php foreach ($byMode['sso'] as $row) : ?>
      <li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a>
        · <?= $this->escape($row['email']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div></section>
  <?php endif; ?>

  <?php if ($result['skipped']) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_BULKCREATE_SKIPPED') ?></h3>
    <ul class="mb-0">
      <?php foreach ($result['skipped'] as $row) : ?>
      <li><?= $this->escape($row['username']) ?>: <?= Text::_($row['message']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div></section>
  <?php endif; ?>
  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ERRORS_HEADING') ?></h3>
    <ul class="mb-0">
      <?php foreach ($result['errors'] as $row) : ?>
      <li><?= $this->escape($row['username']) ?>: <?= Text::_($row['message']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=bulkcreate') ?>"><?= Text::_('COM_USERTOOLS_BULKCREATE_NEW') ?></a>
  </div>
</div>
