<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Sso\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$rows = $this->review['rows'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_SSO_TITLE'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_SSO_REVIEW_HEADING') ?></h2>
  <p class="lead"><?= Text::sprintf('COM_USERTOOLS_SSO_FOUND', count($rows)) ?></p>
  <p class="alert alert-warning"><?= Text::_('COM_USERTOOLS_SSO_REVIEW_WARNING') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=sso') ?>" data-ut-suspension-form data-ut-sso-review>
    <input type="hidden" name="preview" value="<?= $this->escape($this->review['nonce']) ?>">
    <input type="hidden" name="selected" value="[]" data-ut-selected>
    <input type="hidden" name="selection_ready" value="0" data-ut-selection-ready>
    <section class="card ut-card"><div class="card-body">
      <?php if ($rows) : ?>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="ut-sso-all" data-ut-suspend-all>
          <label class="form-check-label" for="ut-sso-all"><?= Text::_('COM_USERTOOLS_SELECT_ALL') ?></label></div>
        <?php if (count($rows) > 8) : ?>
        <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>"
          aria-label="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" data-ut-filter="ut-sso-table">
        <?php endif; ?>
      </div>
      <div class="ut-table-scroll"><table class="table table-sm table-striped" id="ut-sso-table">
        <thead><tr>
          <th scope="col"><?= Text::_('COM_USERTOOLS_SSO_SELECT') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_NAME') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_SSO_STATUS') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_REGISTERED_UTC') ?></th>
          <th scope="col"><?= Text::_('COM_USERTOOLS_GROUPS') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
        <tr><td><input class="form-check-input" type="checkbox" data-ut-suspend-id="<?= (int) $row['id'] ?>"
          aria-label="<?= Text::sprintf('COM_USERTOOLS_SSO_SELECT_USER', $this->escape($row['username'])) ?>"></td>
          <td><?= $this->escape($row['username']) ?></td>
          <td><?= $this->escape($row['name']) ?></td>
          <td><?= $this->escape($row['email']) ?></td>
          <td><?= Text::_((int) $row['block'] === 0 ? 'COM_USERTOOLS_SSO_STATUS_ACTIVE' : 'COM_USERTOOLS_SSO_STATUS_BLOCKED') ?></td>
          <td><?= $this->date($row['lastvisitDate']) ?></td>
          <td><?= $this->date($row['registerDate']) ?></td>
          <td><?= $this->groupTitles($row['groups'], $this->groups) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table><p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p></div>
      <?php else : ?>
      <p class="text-muted"><?= Text::_('COM_USERTOOLS_SSO_NONE') ?></p>
      <?php endif; ?>
      <p class="text-muted mt-2 mb-0" aria-live="polite" data-ut-suspension-count></p>
    </div></section>
    <?php if ($rows) : ?>
    <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_SSO_CONFIRM', 'count' => 0,
        'threshold' => $this->review['threshold'], 'dynamic' => true], $layouts) ?>
    <?php endif; ?>
    <noscript><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <div class="ut-actions">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=sso') ?>"><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></a>
      <?php if ($rows) : ?>
      <button class="btn btn-danger" name="task" value="sso.apply" data-ut-suspend-submit disabled><?= Text::_('COM_USERTOOLS_SSO_APPLY') ?></button>
      <?php endif; ?>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
