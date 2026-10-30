<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessHealth;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Diagnose\HtmlView $this */
$checks    = $this->health['checks'];
$days      = (int) $this->health['days'];
$canUsers  = $this->canUsers();
$canGroups = $this->canEditGroups();
// Status of a check in text and colour: never colour alone.
$status = function (array $check): array {
    if (!$check['found']) {
        return ['success', 'COM_USERTOOLS_DIAGNOSE_OK'];
    }
    return $check['severity'] === AccessHealth::REVIEW ? ['warning', 'COM_USERTOOLS_DIAGNOSE_REVIEW'] : ['info', 'COM_USERTOOLS_DIAGNOSE_INFO'];
};
// One sentence saying what was found, singular or plural.
$found = function (string $key, array $check) use ($days): string {
    $prefix = 'COM_USERTOOLS_DIAGNOSE_' . strtoupper($key);
    if ($key === 'superusers') {
        return $check['found'] ? Text::_($check['count'] === 0 ? $prefix . '_FOUND_0' : $prefix . '_FOUND') : Text::sprintf($prefix . '_NONE', $check['count']);
    }
    if (!$check['found']) {
        return Text::sprintf($prefix . '_NONE', $days);
    }
    return Text::sprintf($check['count'] === 1 ? $prefix . '_FOUND_1' : $prefix . '_FOUND', $check['count'], $days);
};
$usageLink = Route::_('index.php?option=com_usertools&view=usage');
?>
<div class="ut-app ut-wide ut-diagnose">
  <p class="lead"><?= Text::_('COM_USERTOOLS_DIAGNOSE_INTRO') ?></p>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= Text::_('COM_USERTOOLS_DIAGNOSE_SUMMARY') ?></h2>
    <div class="ut-access-tools">
      <a class="btn btn-outline-secondary" data-ut-export="diagnose"
        href="<?= Route::_('index.php?option=com_usertools&task=diagnose.export&' . Session::getFormToken() . '=1') ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a>
    </div>
  </div>
  <ul class="list-unstyled ut-diagnose-summary mt-3" data-ut-diagnose-summary>
    <?php foreach ($checks as $key => $check) :
        [$colour, $label] = $status($check);
        ?>
    <li class="d-flex align-items-baseline gap-2 mb-2">
      <span class="badge text-bg-<?= $colour ?> flex-shrink-0"><?= Text::_($label) ?></span>
      <a href="#ut-check-<?= $this->escape($key) ?>"><?= Text::_('COM_USERTOOLS_DIAGNOSE_' . strtoupper($key) . '_TITLE') ?></a>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php foreach ($checks as $key => $check) :
      [$colour, $label] = $status($check);
      $prefix = 'COM_USERTOOLS_DIAGNOSE_' . strtoupper($key);
      ?>
  <section class="card mb-3 ut-diagnose-check" id="ut-check-<?= $this->escape($key) ?>" aria-labelledby="ut-check-<?= $this->escape($key) ?>-title"
    data-ut-check="<?= $this->escape($key) ?>" data-ut-check-found="<?= (int) $check['found'] ?>" data-ut-check-severity="<?= $this->escape($check['severity']) ?>" data-ut-check-count="<?= (int) $check['count'] ?>">
    <div class="card-body">
      <div class="ut-card-head">
        <h2 class="card-title h5 ut-card-title" id="ut-check-<?= $this->escape($key) ?>-title"><?= Text::_($prefix . '_TITLE') ?></h2>
        <span class="badge text-bg-<?= $colour ?>"><?= Text::_($label) ?></span>
      </div>
      <p class="fw-semibold"><?= $found($key, $check) ?></p>
      <?php if ($check['found']) : ?>
      <p><strong><?= Text::_('COM_USERTOOLS_DIAGNOSE_WHY') ?></strong> <?= Text::_($prefix . '_WHY') ?></p>
      <p><strong><?= Text::_('COM_USERTOOLS_DIAGNOSE_HOW') ?></strong> <?= Text::_($prefix . '_HOW') ?></p>
      <?php endif; ?>

      <?php if ($key === 'admins' && $check['items']) : ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <caption class="visually-hidden"><?= Text::_($prefix . '_TITLE') ?></caption>
          <thead><tr>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_ACCOUNT') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_NAME') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_LAST_VISIT') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_GROUPS') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($check['items'] as $item) : ?>
            <tr data-ut-diagnose-admin="<?= (int) $item['id'] ?>">
              <th scope="row">
                <?php if ($canUsers) : ?><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item['id']) ?>"><?= $this->escape($item['username']) ?></a>
                <?php else : ?><?= $this->escape($item['username']) ?><?php endif; ?>
                <?php if ($item['superUser']) : ?><span class="badge text-bg-secondary ms-1"><?= Text::_('COM_USERTOOLS_DIAGNOSE_SUPER_BADGE') ?></span><?php endif; ?>
              </th>
              <td><?= $this->escape($item['name']) ?></td>
              <td><?= $item['never'] ? Text::sprintf('COM_USERTOOLS_DIAGNOSE_NEVER_SINCE', $this->date($item['registered'])) : $this->date($item['lastVisit']) ?></td>
              <td><?= $this->groupTitles($item['groups'], $this->groups) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($key === 'empty_levels' && $check['items']) : ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <caption class="visually-hidden"><?= Text::_($prefix . '_TITLE') ?></caption>
          <thead><tr>
            <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
            <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_ITEMS') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($check['items'] as $item) : ?>
            <tr data-ut-diagnose-level="<?= (int) $item['id'] ?>">
              <th scope="row">
                <?php if ($canGroups) : ?><a href="<?= Route::_('index.php?option=com_users&task=level.edit&id=' . (int) $item['id']) ?>"><?= $this->escape($item['title']) ?></a>
                <?php else : ?><?= $this->escape($item['title']) ?><?php endif; ?>
              </th>
              <td class="text-end"><?= $item['items'] ? '<a href="' . $usageLink . '">' . (int) $item['items'] . '</a>' : '0' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($key === 'missing_levels' && $check['items']) : ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <caption class="visually-hidden"><?= Text::_($prefix . '_TITLE') ?></caption>
          <thead><tr>
            <th scope="col"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_KINDS') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($check['items'] as $item) : ?>
            <tr data-ut-diagnose-missing="<?= (int) $item['id'] ?>">
              <th scope="row"><?= Text::sprintf('COM_USERTOOLS_USAGE_MISSING_LEVEL', (int) $item['id']) ?></th>
              <td>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($item['kinds'] as $kind) : ?>
                  <li data-ut-diagnose-kind="<?= $this->escape($kind['key']) ?>"><?= $this->escape($kind['label']) ?>:
                    <?php if ($kind['link'] !== '') : ?><a href="<?= Route::_($kind['link']) ?>"><?= (int) $kind['count'] ?></a>
                    <?php else : ?><?= (int) $kind['count'] ?><?php endif; ?></li>
                  <?php endforeach; ?>
                </ul>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($key === 'empty_groups' && $check['items']) : ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <caption class="visually-hidden"><?= Text::_($prefix . '_TITLE') ?></caption>
          <thead><tr>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_GROUP') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_CHILDREN') ?></th>
            <th scope="col"><?= Text::_('COM_USERTOOLS_DIAGNOSE_COL_LEVELS') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($check['items'] as $item) : ?>
            <tr data-ut-diagnose-group="<?= (int) $item['id'] ?>">
              <th scope="row">
                <?php if ($canGroups) : ?><a href="<?= Route::_('index.php?option=com_users&task=group.edit&id=' . (int) $item['id']) ?>"><?= $this->escape($item['title']) ?></a>
                <?php else : ?><?= $this->escape($item['title']) ?><?php endif; ?>
                <small class="d-block text-body-secondary fw-normal"><?= $this->escape($this->paths[$item['id']] ?? '') ?></small>
              </th>
              <td><?= $item['children'] ? (int) $item['children'] : Text::_('JNO') ?></td>
              <td><?= $item['levels'] ? $this->escape(implode(', ', $item['levels'])) : Text::_('COM_USERTOOLS_DIAGNOSE_NO_LEVELS') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
