<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\BulkRegistrationList;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Bulkcreate\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$rows = $this->review['rows'];
$valid = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === BulkRegistrationList::READY));
$mode = $this->review['mode'];
$mailOnline = $this->review['mailOnline'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'review', 'label' => 'COM_USERTOOLS_BULKCREATE_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_BULKCREATE_REVIEW') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_BULKCREATE_REVIEW_HELP') ?></p>
  <div class="ut-stats">
    <div class="card"><div class="card-body"><strong><?= count($rows) ?></strong><span><?= Text::_('COM_USERTOOLS_BULKCREATE_LINES') ?></span></div></div>
    <div class="card"><div class="card-body"><strong><?= count($valid) ?></strong><span><?= Text::_('COM_USERTOOLS_BULKCREATE_READY') ?></span></div></div>
    <div class="card"><div class="card-body"><strong><?= count($rows) - count($valid) ?></strong><span><?= Text::_('COM_USERTOOLS_BULKCREATE_INVALID') ?></span></div></div>
  </div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=bulkcreate') ?>" data-ut-review data-ut-bulkcreate-review>
    <input type="hidden" name="preview" value="<?= $this->escape($this->preview['nonce']) ?>">
    <input type="hidden" name="registrations" data-ut-registrations value="{}">
    <input type="hidden" name="review_ready" data-ut-ready value="0">
    <section class="card ut-card"><div class="card-body">
      <fieldset>
        <legend class="form-label fw-bold fs-6"><?= Text::_('COM_USERTOOLS_CREATION_MODE') ?></legend>
        <?php foreach (Registration::MODES as $choice) :
            $disabled = $choice === Registration::MODE_EMAIL && !$mailOnline;
            $checked = $mode === $choice || ($mode === Registration::MODE_EMAIL && !$mailOnline && $choice === Registration::MODE_PASSWORD);
            ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" id="ut-bulkcreate-mode-<?= $this->escape($choice) ?>" name="creation_mode" value="<?= $this->escape($choice) ?>"<?= $checked ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
            <label class="form-check-label" for="ut-bulkcreate-mode-<?= $this->escape($choice) ?>"><?= Text::_('COM_USERTOOLS_CREATION_MODE_' . strtoupper($choice)) ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>
      <?php if (!$mailOnline) : ?><p class="form-text"><?= Text::_('COM_USERTOOLS_CREATION_MODE_EMAIL_OFFLINE') ?></p><?php endif; ?>
      <div class="alert alert-warning mt-3 mb-0"><?= Text::_('COM_USERTOOLS_BULKCREATE_SSO_NOTICE') ?></div>
    </div></section>
    <section class="card ut-card"><div class="card-body">
      <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_BULKCREATE_EMAILS') ?></h3></div>
      <div class="ut-email-auto mb-3" data-ut-email-auto>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ut-bulkcreate-auto" data-ut-email-auto-on>
          <label class="form-check-label" for="ut-bulkcreate-auto"><?= Text::_('COM_USERTOOLS_EMAIL_AUTO') ?></label>
        </div>
        <div class="input-group input-group-sm ut-email-domain">
          <span class="input-group-text" aria-hidden="true">@</span>
          <input class="form-control" type="text" id="ut-bulkcreate-domain" data-ut-email-domain inputmode="url" autocomplete="off"
            spellcheck="false" placeholder="<?= Text::_('COM_USERTOOLS_EMAIL_DOMAIN_PLACEHOLDER') ?>"
            aria-label="<?= Text::_('COM_USERTOOLS_EMAIL_DOMAIN') ?>" aria-describedby="ut-bulkcreate-email-help" disabled>
        </div>
        <div class="form-text" id="ut-bulkcreate-email-help"><?= Text::_('COM_USERTOOLS_EMAIL_AUTO_HELP') ?></div>
        <div class="form-text text-warning-emphasis" data-ut-email-skipped hidden></div>
      </div>
      <?php if ($valid) : ?>
      <p aria-live="polite"><strong data-ut-bulkcreate-selected>0</strong> <?= Text::_('COM_USERTOOLS_BULKCREATE_SELECTED') ?></p>
      <?php endif; ?>
      <div class="ut-table-scroll"><table class="table table-sm table-striped">
        <thead><tr>
          <th scope="col"><input class="form-check-input me-1" type="checkbox" data-ut-add-all aria-label="<?= Text::_('COM_USERTOOLS_ADD_ALL') ?>"><?= Text::_('COM_USERTOOLS_ADD') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_BULKCREATE_LINE') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_NAME') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_GROUPS') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $row) :
              $ready = $row['status'] === BulkRegistrationList::READY;
              $group = (int) $row['group'];
              $groupName = $group && isset($this->groups[$group]) ? $this->groups[$group]->title : '';
              ?>
          <tr<?= $ready ? ' data-ut-user="' . $this->escape(Registration::key($row['username'])) . '" data-ut-name="' . $this->escape($row['username']) . '"' : '' ?>>
            <td><?php if ($ready) : ?><input class="form-check-input" type="checkbox" data-ut-add
              aria-label="<?= Text::sprintf('COM_USERTOOLS_ADD_USER', $this->escape($row['username'])) ?>"><?php endif; ?></td>
            <td><?= (int) $row['line'] ?></td>
            <td><?= $this->escape($row['username']) ?></td>
            <td><?= $this->escape($row['name']) ?></td>
            <td>
              <?php if ($ready) : ?>
                <input class="form-control form-control-sm" type="email" data-ut-email
                  aria-label="<?= Text::sprintf('COM_USERTOOLS_EMAIL_OF', $this->escape($row['username'])) ?>"
                  value="<?= $this->escape($row['email']) ?>" placeholder="<?= Text::_('COM_USERTOOLS_EMAIL_PLACEHOLDER') ?>" disabled>
              <?php else : ?><?= $this->escape($row['email']) ?><?php endif; ?>
            </td>
            <td><?= Text::_('COM_USERTOOLS_BULKCREATE_REGISTERED') ?><?= $group > 0 ? ' + ' . $this->escape($groupName !== '' ? $groupName . ' (#' . $group . ')' : '#' . $group) : '' ?></td>
            <td><?= Text::_('COM_USERTOOLS_BULKCREATE_STATUS_' . strtoupper($row['status'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div></section>
    <noscript><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_BULKCREATE_CONFIRM',
        'count' => 0, 'threshold' => $this->review['threshold'], 'dynamic' => true], $layouts) ?>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=bulkcreate') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
      <button class="btn btn-primary" name="task" value="bulkcreate.apply" data-ut-bulkcreate-submit disabled><?= Text::_('COM_USERTOOLS_BULKCREATE_APPLY') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
