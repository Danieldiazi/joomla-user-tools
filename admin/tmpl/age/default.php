<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Age\HtmlView $this */
$table   = $this->table;
$levelQ  = $this->level > 0 ? '&level=' . $this->level : '';
$listUrl = fn (string $kind, int $year) => Route::_('index.php?option=com_usertools&view=age&layout=list&kind=' . $kind . '&year=' . $year . $levelQ);
$listed  = array_filter(array_column($table['kinds'], 'key'), fn (string $kind) => $this->canList($kind));
?>
<div class="ut-app ut-wide">
  <p class="lead"><?= Text::_('COM_USERTOOLS_AGE_INTRO') ?></p>

  <form method="get" action="<?= Route::_('index.php') ?>" class="row g-2 align-items-end mb-3" data-ut-age-filter>
    <input type="hidden" name="option" value="com_usertools"><input type="hidden" name="view" value="age">
    <div class="col-sm-6 col-lg-4">
      <label class="form-label" for="ut-age-level"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></label>
      <select class="form-select" id="ut-age-level" name="level">
        <option value="0"><?= Text::_('COM_USERTOOLS_AGE_ALL_LEVELS') ?></option>
        <?php foreach ($this->levels as $level) : ?>
        <option value="<?= (int) $level['id'] ?>"<?= $this->level === $level['id'] ? ' selected' : '' ?>><?= $this->escape($level['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-6 col-lg-4 d-flex gap-2">
      <button class="btn btn-primary"><?= Text::_('COM_USERTOOLS_FILTER_APPLY') ?></button>
      <?php if ($this->level > 0) : ?><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=age') ?>"><?= Text::_('JCLEAR') ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= $this->level > 0 ? Text::sprintf('COM_USERTOOLS_AGE_MATRIX_LEVEL', $this->escape($this->levelTitle())) : Text::_('COM_USERTOOLS_AGE_MATRIX') ?></h2>
    <div class="ut-access-tools">
      <a class="btn btn-outline-secondary" data-ut-export="age"
        href="<?= Route::_('index.php?option=com_usertools&task=age.export' . $levelQ . '&' . Session::getFormToken() . '=1') ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a>
    </div>
  </div>
  <?php if (!$table['years']) : ?>
    <p class="text-muted mt-3" data-ut-age-empty><?= Text::_('COM_USERTOOLS_AGE_NOTHING') ?></p>
  <?php else : ?>
  <div class="table-responsive ut-access-scroll mt-3">
    <table class="table table-sm table-bordered align-middle ut-access ut-age" id="ut-age-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_AGE_MATRIX') ?></caption>
      <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_AGE_YEAR') ?></th>
        <?php foreach ($table['kinds'] as $kind) : ?>
        <th scope="col" class="text-center" data-ut-age-kind="<?= $this->escape($kind['key']) ?>"><?= $this->escape($kind['label']) ?></th>
        <?php endforeach; ?>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_USAGE_TOTAL') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($table['years'] as $year) : ?>
        <tr data-ut-age-year="<?= (int) $year ?>">
          <th scope="row"><?= (int) $year ?></th>
          <?php foreach ($table['kinds'] as $kind) :
              $count = (int) ($kind['counts'][$year] ?? 0);
              ?>
          <td class="text-center" data-ut-age-cell="<?= $this->escape($kind['key']) ?>" data-ut-age-count="<?= $count ?>">
            <?php if ($count === 0) : ?><span class="text-body-secondary" aria-hidden="true">—</span><span class="visually-hidden">0</span>
            <?php elseif (in_array($kind['key'], $listed, true)) : ?><a href="<?= $listUrl($kind['key'], (int) $year) ?>"
              aria-label="<?= $this->escape(Text::sprintf('COM_USERTOOLS_AGE_CELL_LABEL', $count, $kind['label'], (int) $year)) ?>"><?= $count ?></a>
            <?php else : ?><?= $count ?><?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="text-end fw-semibold" data-ut-age-total="<?= (int) $table['totals'][$year] ?>"><?= (int) $table['totals'][$year] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr>
        <th scope="row"><?= Text::_('COM_USERTOOLS_USAGE_TOTAL') ?></th>
        <?php foreach ($table['kinds'] as $kind) : ?><td class="text-center fw-semibold"><?= (int) $kind['total'] ?></td><?php endforeach; ?>
        <td class="text-end fw-semibold"><?= (int) $table['total'] ?></td>
      </tr></tfoot>
    </table>
  </div>
  <?php endif; ?>
  <div class="alert alert-info mb-0"><?= Text::_('COM_USERTOOLS_AGE_NOTE') ?><?php if (count($listed) < count($table['kinds'])) : ?> <?= Text::_('COM_USERTOOLS_AGE_NO_LIST_NOTE') ?><?php endif; ?></div>
  <?php endif; ?>
</div>
