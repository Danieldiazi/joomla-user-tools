<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Home\HtmlView $this */
$states = [
    'enabled'  => ['success', 'COM_USERTOOLS_PLUGIN_ENABLED'],
    'disabled' => ['warning', 'COM_USERTOOLS_PLUGIN_DISABLED'],
    'missing'  => ['danger', 'COM_USERTOOLS_PLUGIN_MISSING'],
];
[$stateColour, $stateKey] = $states[$this->pluginState];

// Sections in the order of a typical task; each card is shown only with its permission, and a
// section with no card is left out. Badges carry text, never colour alone.
$sections = [
    'accounts' => ['COM_USERTOOLS_HOME_SECTION_ACCOUNTS', 'COM_USERTOOLS_HOME_SECTION_ACCOUNTS_DESC', [
        ['usertools.compare', 'compare', 'COM_USERTOOLS_COMPARE_TITLE', 'COM_USERTOOLS_COMPARE_HOME_DESC', 'COM_USERTOOLS_COMPARE_START', '', 'list'],
        ['usertools.export', 'export', 'COM_USERTOOLS_EXPORT_TITLE', 'COM_USERTOOLS_EXPORT_HOME_DESC', 'COM_USERTOOLS_EXPORT_START', 'read', 'download'],
        ['usertools.inactive', 'inactive', 'COM_USERTOOLS_INACTIVE_TITLE', 'COM_USERTOOLS_INACTIVE_HOME_DESC', 'COM_USERTOOLS_INACTIVE_START', '', 'clock'],
    ]],
    'groups' => ['COM_USERTOOLS_HOME_SECTION_GROUPS', 'COM_USERTOOLS_HOME_SECTION_GROUPS_DESC', [
        ['usertools.addgroups', 'addgroups', 'COM_USERTOOLS_ADDGROUPS_TITLE', 'COM_USERTOOLS_ADDGROUPS_HOME_DESC', 'COM_USERTOOLS_ADDGROUPS_START', '', 'users'],
        ['usertools.ungroup', 'ungroup', 'COM_USERTOOLS_UNGROUP_TITLE', 'COM_USERTOOLS_UNGROUP_HOME_DESC', 'COM_USERTOOLS_UNGROUP_START', '', 'users-cog'],
        ['core.admin', 'move', 'COM_USERTOOLS_MOVE_TITLE', 'COM_USERTOOLS_MOVE_HOME_DESC', 'COM_USERTOOLS_MOVE_START', 'super', 'folder-open'],
        ['usertools.access', 'access', 'COM_USERTOOLS_ACCESS_TITLE', 'COM_USERTOOLS_ACCESS_HOME_DESC', 'COM_USERTOOLS_ACCESS_START', 'read', 'user-lock'],
        ['usertools.access', 'usage', 'COM_USERTOOLS_USAGE_TITLE', 'COM_USERTOOLS_USAGE_HOME_DESC', 'COM_USERTOOLS_USAGE_START', 'read', 'file-alt'],
        ['usertools.content', 'age', 'COM_USERTOOLS_AGE_TITLE', 'COM_USERTOOLS_AGE_HOME_DESC', 'COM_USERTOOLS_AGE_START', 'read', 'calendar'],
        ['usertools.diagnose', 'diagnose', 'COM_USERTOOLS_DIAGNOSE_TITLE', 'COM_USERTOOLS_DIAGNOSE_HOME_DESC', 'COM_USERTOOLS_DIAGNOSE_START', 'read', 'search'],
    ]],
    'automation' => ['COM_USERTOOLS_HOME_SECTION_AUTOMATION', 'COM_USERTOOLS_HOME_SECTION_AUTOMATION_DESC', [
        ['core.manage', 'schedule', 'COM_USERTOOLS_SCHEDULE_HOME_TITLE', 'COM_USERTOOLS_SCHEDULE_HOME_DESC', 'COM_USERTOOLS_SCHEDULE_START', 'plugin', 'calendar-check'],
        ['usertools.reports', 'reports', 'COM_USERTOOLS_REPORTS', 'COM_USERTOOLS_REPORTS_HOME_DESC', 'COM_USERTOOLS_REPORTS_START', '', 'tasks'],
    ]],
];
foreach ($sections as $key => $section) {
    $sections[$key][2] = array_values(array_filter($section[2], fn (array $card) => $this->can($card[0])));
}
$sections = array_filter($sections, fn (array $section) => (bool) $section[2]);
?>
<div class="ut-app ut-home">
  <p class="lead"><?= Text::_('COM_USERTOOLS_HOME_INTRO') ?></p>
  <div class="alert alert-info d-flex gap-2 ut-home-note">
    <span class="icon-shield" aria-hidden="true"></span>
    <div><?= Text::_('COM_USERTOOLS_HOME_NOTE') ?></div>
  </div>

  <?php foreach ($sections as $key => [$title, $description, $cards]) : ?>
  <section class="ut-home-section" aria-labelledby="ut-home-<?= $key ?>" data-ut-home-section="<?= $key ?>">
    <h2 class="ut-home-heading" id="ut-home-<?= $key ?>"><?= Text::_($title) ?></h2>
    <p class="ut-home-section-desc"><?= Text::_($description) ?></p>
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <?php foreach ($cards as $card) :
          [, $view, $cardTitle, $cardText, $action] = $card;
          $kind = $card[5] ?? '';
          $icon = $card[6] ?? '';
          ?>
      <div class="col">
        <article class="card h-100" data-ut-home-card="<?= $view ?>">
          <div class="card-body d-flex flex-column">
            <div class="ut-card-head">
              <h3 class="card-title ut-card-title" id="ut-card-<?= $view ?>"><span class="icon-<?= $icon ?>" aria-hidden="true"></span> <?= Text::_($cardTitle) ?></h3>
              <?php if ($kind === 'read') : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_READ_ONLY') ?></span><?php endif; ?>
              <?php if ($kind === 'super') : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_SUPER_ONLY') ?></span><?php endif; ?>
            </div>
            <p class="card-text"><?= Text::_($cardText) ?></p>
            <?php if ($kind === 'plugin') : ?>
            <p class="card-text"><?= Text::_('COM_USERTOOLS_PLUGIN_STATE') ?> <span class="badge text-bg-<?= $stateColour ?>"><?= Text::_($stateKey) ?></span></p>
            <?php endif; ?>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=' . $view) ?>" aria-describedby="ut-card-<?= $view ?>"><?= Text::_($action) ?></a>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($this->can('usertools.delete') || $this->can('usertools.anonymize')) : ?>
  <section class="ut-home-section" aria-labelledby="ut-home-delete" data-ut-home-section="delete">
    <h2 class="ut-home-heading" id="ut-home-delete"><?= Text::_('COM_USERTOOLS_HOME_SECTION_DELETE') ?></h2>
    <p class="ut-home-section-desc"><?= Text::_('COM_USERTOOLS_HOME_SECTION_DELETE_DESC') ?></p>
    <div class="row row-cols-1 row-cols-md-2 g-3">
    <?php if ($this->can('usertools.delete')) : ?>
    <div class="col">
    <article class="card border-danger h-100" data-ut-home-card="delete">
      <div class="card-body">
        <div class="ut-card-head">
          <h3 class="card-title ut-card-title" id="ut-card-delete"><span class="icon-trash" aria-hidden="true"></span> <?= Text::_('COM_USERTOOLS_DELETE_TITLE') ?></h3>
          <span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_PERMANENT') ?></span>
        </div>
        <p class="card-text"><?= Text::_('COM_USERTOOLS_DELETE_HOME_DESC') ?></p>
        <p class="card-text fw-semibold ut-home-warning"><?= Text::_('COM_USERTOOLS_DELETE_HOME_WARNING') ?></p>
        <a class="btn btn-sm btn-outline-danger" href="<?= Route::_('index.php?option=com_usertools&view=delete') ?>" aria-describedby="ut-card-delete"><?= Text::_('COM_USERTOOLS_DELETE_START') ?></a>
      </div>
    </article>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.anonymize')) : ?>
    <div class="col">
    <article class="card border-danger h-100" data-ut-home-card="anonymize">
      <div class="card-body">
        <div class="ut-card-head">
          <h3 class="card-title ut-card-title" id="ut-card-anonymize"><span class="icon-eye-slash" aria-hidden="true"></span> <?= Text::_('COM_USERTOOLS_ANONYMIZE_TITLE') ?></h3>
          <span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_PERMANENT') ?></span>
        </div>
        <p class="card-text"><?= Text::_('COM_USERTOOLS_ANONYMIZE_HOME_DESC') ?></p>
        <p class="card-text fw-semibold ut-home-warning"><?= Text::_('COM_USERTOOLS_ANONYMIZE_HOME_WARNING') ?></p>
        <a class="btn btn-sm btn-outline-danger" href="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" aria-describedby="ut-card-anonymize"><?= Text::_('COM_USERTOOLS_ANONYMIZE_START') ?></a>
      </div>
    </article>
    </div>
    <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
