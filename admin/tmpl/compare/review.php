<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView $this */
$layouts    = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review     = $this->review;
$candidates = $review['candidates'];
$unknown    = $review['unknown'];
$draft      = $this->draft;
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'review', 'label' => 'COM_USERTOOLS_COMPARE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_REVIEW_HEADING') ?></h2>
  <p class="lead"><?= Text::_('COM_USERTOOLS_REVIEW_INTRO') ?></p>
  <?php if (!$review['matched']) : ?>
  <div class="alert <?= $candidates ? 'alert-danger' : 'alert-warning' ?>" role="alert" data-ut-no-matches>
    <strong class="d-block"><?= Text::_('COM_USERTOOLS_NO_MATCHES') ?></strong>
    <?= Text::_($candidates ? 'COM_USERTOOLS_NO_MATCHES_BLOCK' : 'COM_USERTOOLS_NO_MATCHES_NOTHING') ?>
  </div>
  <?php endif; ?>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_STAT_MATCHED' => $review['matched'], 'COM_USERTOOLS_STAT_PROTECTED' => $review['protected'], 'COM_USERTOOLS_STAT_TO_BLOCK' => count($candidates), 'COM_USERTOOLS_STAT_NEW_NAMES' => count($unknown)] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=compare') ?>" data-ut-review>
    <input type="hidden" name="preview" value="<?= $this->escape($this->preview['nonce']) ?>">
    <input type="hidden" name="registrations" data-ut-registrations value="{}">
    <input type="hidden" name="review_ready" data-ut-ready value="0">

    <section class="card ut-card"><div class="card-body">
      <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_TO_BLOCK_HEADING') ?></h3><span class="badge text-bg-danger"><?= count($candidates) ?></span></div>
      <?php if ($candidates) : ?>
        <?php if (count($candidates) > 8) : ?>
        <input type="search" class="form-control mt-3" placeholder="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" data-ut-filter="ut-block-table">
        <?php endif; ?>
        <div class="ut-table-scroll"><table class="table table-sm table-striped" id="ut-block-table"><thead><tr>
          <th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_NAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_GROUPS') ?></th></tr></thead><tbody>
        <?php foreach ($candidates as $user) : ?>
          <tr><td><?= $this->escape($user->username) ?></td><td><?= $this->escape($user->name) ?></td><td><?= $this->escape($user->email) ?></td>
            <td><?= $this->date($user->lastvisitDate) ?></td><td><?= $this->groupTitles($user->groups, $this->groups) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table><p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p></div>
      <?php else : ?>
        <p class="text-muted mt-3 mb-0"><?= Text::_('COM_USERTOOLS_NOTHING_TO_BLOCK') ?></p>
      <?php endif; ?>
    </div></section>

    <section class="card ut-card"><div class="card-body">
      <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_NEW_USERS_HEADING') ?></h3><span class="badge text-bg-secondary"><?= count($unknown) ?></span></div>
      <?php if (!$unknown) : ?>
        <p class="text-muted mt-3 mb-0"><?= Text::_('COM_USERTOOLS_ALL_NAMES_EXIST') ?></p>
      <?php elseif (!$review['canCreate']) : ?>
        <p class="mt-3"><?= Text::_('COM_USERTOOLS_CREATE_NOT_ALLOWED_NOTE') ?></p>
        <ul class="mb-0"><?php foreach ($unknown as $name) : ?><li><?= $this->escape($name) ?></li><?php endforeach; ?></ul>
      <?php else : ?>
        <p class="mt-3"><?= Text::_('COM_USERTOOLS_NEW_USERS_INTRO') ?></p>
        <fieldset class="mb-3">
          <legend class="form-label fw-bold fs-6"><?= Text::_('COM_USERTOOLS_CREATION_MODE') ?></legend>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="creation_mode" id="ut-mode-email" value="<?= Registration::MODE_EMAIL ?>"<?= $review['mode'] === Registration::MODE_EMAIL ? ' checked' : '' ?><?= $review['mailOnline'] ? '' : ' disabled' ?>>
            <label class="form-check-label" for="ut-mode-email"><?= Text::_('COM_USERTOOLS_CREATION_MODE_EMAIL') ?></label>
            <div class="form-text"><?= Text::_($review['mailOnline'] ? 'COM_USERTOOLS_CREATION_MODE_EMAIL_HELP' : 'COM_USERTOOLS_CREATION_MODE_EMAIL_OFFLINE') ?></div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="creation_mode" id="ut-mode-password" value="<?= Registration::MODE_PASSWORD ?>"<?= ($review['mode'] === Registration::MODE_PASSWORD || !$review['mailOnline']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-mode-password"><?= Text::_('COM_USERTOOLS_CREATION_MODE_PASSWORD') ?></label>
            <div class="form-text"><?= Text::_('COM_USERTOOLS_CREATION_MODE_PASSWORD_HELP') ?></div>
          </div>
        </fieldset>
        <div class="ut-email-auto mb-3" data-ut-email-auto>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ut-email-auto" data-ut-email-auto-on>
            <label class="form-check-label" for="ut-email-auto"><?= Text::_('COM_USERTOOLS_EMAIL_AUTO') ?></label>
          </div>
          <div class="input-group input-group-sm ut-email-domain">
            <span class="input-group-text" aria-hidden="true">@</span>
            <input class="form-control" type="text" id="ut-email-domain" data-ut-email-domain inputmode="url" autocomplete="off" spellcheck="false"
              placeholder="<?= Text::_('COM_USERTOOLS_EMAIL_DOMAIN_PLACEHOLDER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_EMAIL_DOMAIN') ?>" aria-describedby="ut-email-auto-help" disabled>
          </div>
          <div class="form-text" id="ut-email-auto-help"><?= Text::_('COM_USERTOOLS_EMAIL_AUTO_HELP') ?></div>
          <div class="form-text text-warning-emphasis" data-ut-email-skipped hidden></div>
        </div>
        <div class="ut-table-scroll"><table class="table table-sm"><thead><tr>
          <th scope="col"><input class="form-check-input me-1" type="checkbox" data-ut-add-all aria-label="<?= Text::_('COM_USERTOOLS_ADD_ALL') ?>" title="<?= Text::_('COM_USERTOOLS_ADD_ALL') ?>"><?= Text::_('COM_USERTOOLS_ADD') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th></tr></thead><tbody>
        <?php foreach ($unknown as $name) :
            $key     = Registration::key($name);
            $checked = (string) ($draft['checked'][$key] ?? '0') === '1';
            ?>
          <tr data-ut-user="<?= $this->escape($key) ?>" data-ut-name="<?= $this->escape($name) ?>">
            <td><input class="form-check-input" type="checkbox" data-ut-add aria-label="<?= Text::sprintf('COM_USERTOOLS_ADD_USER', $this->escape($name)) ?>"<?= $checked ? ' checked' : '' ?>></td>
            <td><?= $this->escape($name) ?></td>
            <td><input class="form-control form-control-sm" type="email" data-ut-email aria-label="<?= Text::sprintf('COM_USERTOOLS_EMAIL_OF', $this->escape($name)) ?>" value="<?= $this->escape($draft['emails'][$key] ?? '') ?>" placeholder="<?= Text::_('COM_USERTOOLS_EMAIL_PLACEHOLDER') ?>"<?= $checked ? ' required' : ' disabled' ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <p class="form-text mb-0"><?= Text::_('COM_USERTOOLS_NEW_USERS_GROUP_NOTE') ?></p>
      <?php endif; ?>
    </div></section>

    <noscript><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_CONFIRM_COMPARE', 'count' => count($candidates), 'threshold' => $review['threshold'], 'dynamic' => false], $layouts) ?>
    <div class="ut-actions">
      <button class="btn btn-outline-secondary" name="task" value="compare.back" formnovalidate><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></button>
      <button class="btn btn-danger" name="task" value="compare.apply"><?= Text::_('COM_USERTOOLS_APPLY_CHANGES') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
