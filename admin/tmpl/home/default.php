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
[$stateColour, $stateKey] = $states[$this->pluginState];

// Sections in the order of a typical task; each card is shown only with its permission, and a
// section with no card is left out. Badges carry text, never colour alone.
$sections = [
    'accounts' => ['COM_USERTOOLS_HOME_SECTION_ACCOUNTS', 'COM_USERTOOLS_HOME_SECTION_ACCOUNTS_DESC', [
        ['usertools.compare', 'compare', 'COM_USERTOOLS_COMPARE_TITLE', 'COM_USERTOOLS_COMPARE_HOME_DESC', 'COM_USERTOOLS_COMPARE_START'],
        ['usertools.inactive', 'inactive', 'COM_USERTOOLS_INACTIVE_TITLE', 'COM_USERTOOLS_INACTIVE_HOME_DESC', 'COM_USERTOOLS_INACTIVE_START'],
    ]],
    'groups' => ['COM_USERTOOLS_HOME_SECTION_GROUPS', 'COM_USERTOOLS_HOME_SECTION_GROUPS_DESC', [
        ['usertools.ungroup', 'ungroup', 'COM_USERTOOLS_UNGROUP_TITLE', 'COM_USERTOOLS_UNGROUP_HOME_DESC', 'COM_USERTOOLS_UNGROUP_START'],
        ['usertools.access', 'access', 'COM_USERTOOLS_ACCESS_TITLE', 'COM_USERTOOLS_ACCESS_HOME_DESC', 'COM_USERTOOLS_ACCESS_START', 'read'],
        ['usertools.access', 'usage', 'COM_USERTOOLS_USAGE_TITLE', 'COM_USERTOOLS_USAGE_HOME_DESC', 'COM_USERTOOLS_USAGE_START', 'read'],
    ]],
    'automation' => ['COM_USERTOOLS_HOME_SECTION_AUTOMATION', 'COM_USERTOOLS_HOME_SECTION_AUTOMATION_DESC', [
        ['core.manage', 'schedule', 'COM_USERTOOLS_SCHEDULE_HOME_TITLE', 'COM_USERTOOLS_SCHEDULE_HOME_DESC', 'COM_USERTOOLS_SCHEDULE_START', 'plugin'],
        ['usertools.reports', 'reports', 'COM_USERTOOLS_REPORTS', 'COM_USERTOOLS_REPORTS_HOME_DESC', 'COM_USERTOOLS_REPORTS_START'],
    ]],
];
foreach ($sections as $key => $section) {
    $sections[$key][2] = array_values(array_filter($section[2], fn (array $card) => $this->can($card[0])));
}
$sections = array_filter($sections, fn (array $section) => (bool) $section[2]);
?>
<div class="ut-app ut-home">
  <p class="lead"><?= Text::_('COM_USERTOOLS_HOME_INTRO') ?></p>
  <p class="ut-home-note"><?= Text::_('COM_USERTOOLS_HOME_NOTE') ?></p>

  <?php foreach ($sections as $key => [$title, $description, $cards]) : ?>
  <section class="ut-home-section" aria-labelledby="ut-home-<?= $key ?>" data-ut-home-section="<?= $key ?>">
    <h2 class="h4" id="ut-home-<?= $key ?>"><?= Text::_($title) ?></h2>
    <p class="ut-home-section-desc"><?= Text::_($description) ?></p>
    <div class="row row-cols-1 row-cols-md-2<?= count($cards) > 2 ? ' row-cols-xl-3' : '' ?> g-3">
      <?php foreach ($cards as $card) :
          [, $view, $cardTitle, $cardText, $action] = $card;
          $kind = $card[5] ?? '';
          ?>
      <div class="col">
        <article class="card h-100" data-ut-home-card="<?= $view ?>">
          <div class="card-body">
            <h3 class="h5 card-title" id="ut-card-<?= $view ?>"><?= Text::_($cardTitle) ?>
              <?php if ($kind === 'read') : ?><span class="badge text-bg-secondary ms-1"><?= Text::_('COM_USERTOOLS_READ_ONLY') ?></span><?php endif; ?></h3>
            <p class="card-text"><?= Text::_($cardText) ?></p>
            <?php if ($kind === 'plugin') : ?>
            <p class="card-text"><?= Text::_('COM_USERTOOLS_PLUGIN_STATE') ?> <span class="badge text-bg-<?= $stateColour ?>"><?= Text::_($stateKey) ?></span></p>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=' . $view) ?>" aria-describedby="ut-card-<?= $view ?>"><?= Text::_($action) ?></a>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($this->can('usertools.delete')) : ?>
  <section class="ut-home-section" aria-labelledby="ut-home-delete" data-ut-home-section="delete">
    <h2 class="h4" id="ut-home-delete"><?= Text::_('COM_USERTOOLS_HOME_SECTION_DELETE') ?></h2>
    <p class="ut-home-section-desc"><?= Text::_('COM_USERTOOLS_HOME_SECTION_DELETE_DESC') ?></p>
    <article class="card border-danger" data-ut-home-card="delete">
      <div class="card-body">
        <h3 class="h5 card-title" id="ut-card-delete"><?= Text::_('COM_USERTOOLS_DELETE_TITLE') ?>
          <span class="badge text-bg-danger ms-1"><?= Text::_('COM_USERTOOLS_PERMANENT') ?></span></h3>
        <p class="card-text"><?= Text::_('COM_USERTOOLS_DELETE_HOME_DESC') ?></p>
        <p class="card-text fw-semibold"><?= Text::_('COM_USERTOOLS_DELETE_HOME_WARNING') ?></p>
        <a class="btn btn-outline-danger" href="<?= Route::_('index.php?option=com_usertools&view=delete') ?>" aria-describedby="ut-card-delete"><?= Text::_('COM_USERTOOLS_DELETE_START') ?></a>
      </div>
    </article>
  </section>
  <?php endif; ?>
</div>
