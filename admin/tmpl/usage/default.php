<?php

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessUsage;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Usage\HtmlView $this */
$levels  = $this->usage['levels'];
$rows    = $this->usage['rows'];
$canEdit = Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_users');
$unused  = array_values(array_filter($levels, fn ($level) => !$level['missing'] && $level['total'] === 0));
$missing = array_values(array_filter($levels, fn ($level) => $level['missing']));
?>
<div class="ut-app ut-wide">
  <p class="lead"><?= Text::_('COM_USERTOOLS_USAGE_INTRO') ?></p>

  <?php if ($this->error) : ?>
    <div class="alert alert-danger"><?= Text::_($this->error) ?></div>
  <?php else : ?>
  <?php if ($missing) : ?>
  <div class="alert alert-warning" role="alert" data-ut-usage-missing>
    <?= Text::sprintf('COM_USERTOOLS_USAGE_MISSING_NOTE', implode(', ', array_map(fn ($level) => (int) $level['id'], $missing))) ?>
  </div>
  <?php endif; ?>

  <div class="ut-section-heading">
    <h2 class="h4 mb-0"><?= Text::_('COM_USERTOOLS_USAGE_MATRIX') ?></h2>
    <div class="ut-access-tools">
      <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_USAGE_FILTER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_USAGE_FILTER') ?>" data-ut-filter="ut-usage-table">
      <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=access') ?>"><?= Text::_('COM_USERTOOLS_USAGE_TO_GROUPS') ?></a>
      <a class="btn btn-outline-secondary" data-ut-export="usage"
        href="<?= Route::_('index.php?option=com_usertools&task=usage.export&' . Session::getFormToken() . '=1') ?>"><?= Text::_('COM_USERTOOLS_EXPORT_CSV') ?></a>
    </div>
  </div>
  <?php if (!$rows) : ?>
    <p class="text-muted mt-3"><?= Text::_('COM_USERTOOLS_USAGE_NOTHING') ?></p>
  <?php else : ?>
  <div class="table-responsive ut-access-scroll mt-3">
    <table class="table table-sm table-bordered align-middle ut-access ut-usage" id="ut-usage-table">
      <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_USAGE_MATRIX') ?></caption>
      <thead><tr>
        <th scope="col" class="ut-access-group"><?= Text::_('COM_USERTOOLS_ACCESS_LEVEL') ?></th>
        <?php foreach ($rows as $row) : ?>
        <th scope="col" class="text-center" data-ut-usage-kind="<?= $this->escape($row['key']) ?>"><?= $this->escape($row['label']) ?>
          <?php if (str_starts_with($row['key'], 'table:')) : ?><small class="d-block fw-normal text-body-secondary"><?= Text::_('COM_USERTOOLS_USAGE_OTHER_TABLE_NOTE') ?></small><?php endif; ?></th>
        <?php endforeach; ?>
        <th scope="col" class="text-end"><?= Text::_('COM_USERTOOLS_USAGE_TOTAL') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($levels as $level) : ?>
        <tr data-ut-usage-level="<?= (int) $level['id'] ?>"<?= $level['total'] === 0 ? ' class="text-body-secondary"' : '' ?>>
          <th scope="row" class="ut-access-group">
            <?php if ($level['missing']) : ?>
              <span class="badge bg-warning text-dark"><?= Text::sprintf('COM_USERTOOLS_USAGE_MISSING_LEVEL', (int) $level['id']) ?></span>
            <?php elseif ($canEdit) : ?>
              <a href="<?= Route::_('index.php?option=com_users&task=level.edit&id=' . (int) $level['id']) ?>"><?= $this->escape($level['title']) ?></a>
            <?php else : ?>
              <?= $this->escape($level['title']) ?>
            <?php endif; ?>
          </th>
          <?php foreach ($rows as $row) :
              $count = (int) ($row['counts'][$level['id']] ?? 0);
              $link  = AccessUsage::link($row['link'], (int) $level['id']);
              ?>
          <td class="text-center" data-ut-usage-cell="<?= $this->escape($row['key']) ?>" data-ut-usage-count="<?= $count ?>">
            <?php if ($count === 0) : ?><span class="text-body-secondary" aria-hidden="true">—</span><span class="visually-hidden">0</span>
            <?php elseif ($link !== '' && !$level['missing']) : ?><a href="<?= Route::_($link) ?>"><?= $count ?></a>
            <?php else : ?><?= $count ?><?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="text-end fw-semibold" data-ut-usage-total="<?= (int) $level['total'] ?>"><?= (int) $level['total'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr>
        <th scope="row"><?= Text::_('COM_USERTOOLS_USAGE_TOTAL') ?></th>
        <?php foreach ($rows as $row) : ?><td class="text-center fw-semibold"><?= (int) $row['total'] ?></td><?php endforeach; ?>
        <td class="text-end fw-semibold"><?= array_sum(array_column($rows, 'total')) ?></td>
      </tr></tfoot>
    </table>
    <p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p>
  </div>
  <?php endif; ?>

  <?php if ($unused) : ?>
  <p class="mt-3" data-ut-usage-unused><?= Text::sprintf('COM_USERTOOLS_USAGE_UNUSED', $this->escape(implode(', ', array_column($unused, 'title')))) ?></p>
  <?php endif; ?>
  <div class="alert alert-info mb-0"><?= Text::_('COM_USERTOOLS_USAGE_NOTE') ?></div>
  <?php endif; ?>
</div>
