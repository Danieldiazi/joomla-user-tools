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
    'enabled' => ['success', 'COM_USERTOOLS_PLUGIN_ENABLED'],
    'disabled' => ['warning', 'COM_USERTOOLS_PLUGIN_DISABLED'],
    'missing' => ['danger', 'COM_USERTOOLS_PLUGIN_MISSING'],
];
[$stateColour, $stateKey] = $states[$this->pluginState];
?>
<div class="ut-app ut-home" data-ut-category="<?= $this->escape($this->section) ?>">
  <p class="lead"><?= Text::_('COM_USERTOOLS_HOME_INTRO') ?></p>
  <div class="alert alert-info d-flex gap-2 ut-home-note">
    <span class="icon-shield" aria-hidden="true"></span>
    <div><?= Text::_('COM_USERTOOLS_HOME_NOTE') ?></div>
  </div>
  <?php if (!$this->navigationEnabled) : ?>
  <p class="alert alert-warning"><?= Text::_('COM_USERTOOLS_NAV_DISABLED') ?></p>
  <?php endif; ?>

  <?php foreach ($this->sections as $category => $section) : ?>
  <section class="ut-home-section" aria-labelledby="ut-home-<?= $category ?>" data-ut-home-section="<?= $category ?>">
    <h2 class="ut-home-heading" id="ut-home-<?= $category ?>"><?= Text::_($section['title']) ?></h2>
    <p class="ut-home-section-desc"><?= Text::_($section['description']) ?></p>
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <?php foreach ($section['tools'] as $view => $card) :
          [, $cardTitle, $cardText, $action, $kind, $icon] = $card;
          $permanent = $kind === 'permanent';
          ?>
      <div class="col">
        <article class="card h-100<?= $permanent ? ' border-danger' : '' ?>" data-ut-home-card="<?= $view ?>">
          <div class="card-body d-flex flex-column">
            <div class="ut-card-head">
              <h3 class="card-title ut-card-title" id="ut-card-<?= $view ?>"><span class="icon-<?= $icon ?>" aria-hidden="true"></span> <?= Text::_($cardTitle) ?></h3>
              <?php if ($kind === 'read') : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_READ_ONLY') ?></span><?php endif; ?>
              <?php if ($kind === 'super') : ?><span class="badge text-bg-warning"><?= Text::_('COM_USERTOOLS_SUPER_ONLY') ?></span><?php endif; ?>
              <?php if ($permanent) : ?><span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_PERMANENT') ?></span><?php endif; ?>
            </div>
            <p class="card-text"><?= Text::_($cardText) ?></p>
            <?php if (isset($card[6])) : ?><p class="card-text fw-semibold ut-home-warning"><?= Text::_($card[6]) ?></p><?php endif; ?>
            <?php if ($kind === 'plugin') : ?>
            <p class="card-text"><?= Text::_('COM_USERTOOLS_PLUGIN_STATE') ?> <span class="badge text-bg-<?= $stateColour ?>"><?= Text::_($stateKey) ?></span></p>
            <?php endif; ?>
            <a class="btn btn-sm <?= $permanent ? 'btn-outline-danger' : 'btn-primary' ?> mt-auto align-self-start" href="<?= Route::_('index.php?option=com_usertools&view=' . $view) ?>" aria-describedby="ut-card-<?= $view ?>"><?= Text::_($action) ?></a>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>
