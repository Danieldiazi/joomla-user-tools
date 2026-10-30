<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Danieldiazi\Component\Usertools\Administrator\Controller\DisplayController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @var array<string, mixed>|null $summary
 * @var bool                      $canDelete
 */
$identity = Factory::getApplication()->getIdentity();
$links = [
    ['usertools.compare', 'compare', 'MOD_USERTOOLS_LINK_COMPARE'],
    ['usertools.inactive', 'inactive', 'MOD_USERTOOLS_LINK_INACTIVE'],
    ['usertools.ungroup', 'ungroup', 'MOD_USERTOOLS_LINK_UNGROUP'],
    ['usertools.access', 'access', 'MOD_USERTOOLS_LINK_ACCESS'],
    ['usertools.access', 'usage', 'MOD_USERTOOLS_LINK_USAGE'],
    ['usertools.content', 'age', 'MOD_USERTOOLS_LINK_AGE'],
    ['usertools.diagnose', 'diagnose', 'MOD_USERTOOLS_LINK_DIAGNOSE'],
    ['usertools.reports', 'reports', 'MOD_USERTOOLS_LINK_REPORTS'],
    ['usertools.delete', 'delete', 'MOD_USERTOOLS_LINK_DELETE'],
    ['usertools.anonymize', 'anonymize', 'MOD_USERTOOLS_LINK_ANONYMIZE'],
];
$links[] = ['core.admin', 'move', 'MOD_USERTOOLS_LINK_MOVE'];
$links = array_values(array_filter($links, static fn (array $link): bool =>
    $identity !== null && DisplayController::allowed($identity, $link[0])
));
$e    = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$date = fn (?string $utc) => $utc ? HTMLHelper::_('date', $utc, Text::_('DATE_FORMAT_LC5')) : Text::_('MOD_USERTOOLS_NEVER');
$statusBadge = [
    'completed'  => ['success', 'MOD_USERTOOLS_STATUS_COMPLETED'],
    'failed'     => ['danger', 'MOD_USERTOOLS_STATUS_FAILED'],
    'guarded'    => ['danger', 'MOD_USERTOOLS_STATUS_GUARDED'],
    'running'    => ['warning', 'MOD_USERTOOLS_STATUS_RUNNING'],
    'never'      => ['secondary', 'MOD_USERTOOLS_STATUS_NEVER'],
    'unknown'    => ['secondary', 'MOD_USERTOOLS_STATUS_UNKNOWN'],
    'unreadable' => ['secondary', 'MOD_USERTOOLS_STATUS_UNKNOWN'],
];
?>
<div class="mod-usertools p-3">
<?php if ($summary === null) : ?>
  <div class="alert alert-warning mb-0"><?= Text::_('MOD_USERTOOLS_UNAVAILABLE') ?></div>
<?php else : ?>
  <?php if ($summary['alerts']) : ?>
  <ul class="list-unstyled mb-3">
    <?php foreach ($summary['alerts'] as $alert) : ?>
    <li class="alert alert-<?= $e($alert['level']) ?> py-2 mb-2">
      <?= Text::sprintf($alert['key'], ...array_map($e, $alert['args'])) ?>
      <a class="alert-link ms-1" href="<?= Route::_($alert['link']) ?>"><?= Text::_('MOD_USERTOOLS_REVIEW') ?></a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($summary['inactive'] !== null || $summary['pendingDeletion'] !== null) : ?>
  <div class="row g-3 mb-3" data-ut-dashboard-counts>
    <?php if ($summary['inactive'] !== null) : ?>
    <div class="col-12 col-sm-6">
      <a class="d-block h-100 p-3 border rounded text-decoration-none" href="<?= Route::_('index.php?option=com_usertools&view=inactive') ?>">
        <span class="d-block">
          <strong class="d-block fs-2 lh-1 mb-1" data-ut-count="inactive"><?= (int) $summary['inactive'] ?></strong>
          <span class="d-block"><?= Text::_('MOD_USERTOOLS_INACTIVE') ?></span>
          <small class="d-block text-body-secondary"><?= Text::_('MOD_USERTOOLS_INACTIVE_HELP') ?></small>
          <span class="d-block small fw-semibold mt-2"><?= Text::_('MOD_USERTOOLS_REVIEW_INACTIVE') ?> <span aria-hidden="true">→</span></span>
        </span>
      </a>
    </div>
    <?php endif; ?>
    <?php if ($summary['pendingDeletion'] !== null) : ?>
    <div class="col-12 col-sm-6">
      <a class="d-block h-100 p-3 border rounded text-decoration-none" href="<?= Route::_('index.php?option=com_usertools&view=delete&status=blocked') ?>">
        <span class="d-block">
          <strong class="d-block fs-2 lh-1 mb-1" data-ut-count="pending"><?= (int) $summary['pendingDeletion'] ?></strong>
          <span class="d-block"><?= Text::_('MOD_USERTOOLS_PENDING_DELETION') ?></span>
          <small class="d-block text-body-secondary"><?= Text::_('MOD_USERTOOLS_PENDING_DELETION_HELP') ?></small>
          <span class="d-block small fw-semibold mt-2"><?= Text::_('MOD_USERTOOLS_REVIEW_DELETION') ?> <span aria-hidden="true">→</span></span>
        </span>
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($summary['tasks'] !== null && $summary['tasks']) : ?>
  <div class="mb-3" data-ut-dashboard-tasks>
    <h3 class="fs-6 fw-semibold mb-2"><?= Text::_('MOD_USERTOOLS_TASKS') ?></h3>
    <ul class="list-unstyled mb-0">
      <?php foreach ($summary['tasks'] as $task) :
          [$badge, $label] = $statusBadge[$task['status']] ?? $statusBadge['unknown'];
          ?>
      <li class="border rounded p-3 mb-2" data-ut-dashboard-task="<?= (int) $task['id'] ?>">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
          <a class="fw-semibold text-break" href="<?= Route::_('index.php?option=com_scheduler&task=task.edit&id=' . (int) $task['id']) ?>"><?= $e($task['title']) ?></a>
          <span class="badge text-bg-<?= $task['dryRun'] ? 'info' : 'warning' ?>" data-ut-task-mode="<?= $task['dryRun'] ? 'dry' : 'real' ?>"><?= Text::_($task['dryRun'] ? 'MOD_USERTOOLS_MODE_DRY' : 'MOD_USERTOOLS_MODE_REAL') ?></span>
          <?php if (!$task['enabled']) : ?><span class="badge text-bg-secondary"><?= Text::_('MOD_USERTOOLS_DISABLED') ?></span><?php endif; ?>
        </div>
        <dl class="row g-2 small mb-0">
          <div class="col-12 col-sm-6">
            <dt class="text-body-secondary fw-normal"><?= Text::_('MOD_USERTOOLS_LAST_RUN') ?></dt>
            <dd class="mb-0">
              <span class="d-block"><?= $task['lastRun'] ? $date($task['lastRun']) : Text::_('MOD_USERTOOLS_NOT_RUN') ?></span>
              <?php if ($task['lastRun'] || $task['reportId'] !== '') : ?>
              <span class="badge text-bg-<?= $badge ?> mt-1"><?= Text::_($label) ?></span>
              <?php endif; ?>
            </dd>
          </div>
          <div class="col-12 col-sm-6">
            <dt class="text-body-secondary fw-normal"><?= Text::_('MOD_USERTOOLS_NEXT_RUN') ?></dt>
            <dd class="mb-0"><?= !$task['enabled'] ? Text::_('MOD_USERTOOLS_NEXT_DISABLED') : ($task['nextRun'] ? $date($task['nextRun']) : Text::_('MOD_USERTOOLS_NEXT_NONE')) ?></dd>
          </div>
        </dl>
        <?php if ($task['reportId'] !== '') : ?>
        <a class="d-inline-block small mt-2" href="<?= Route::_('index.php?option=com_usertools&view=report&report_id=' . $e($task['reportId'])) ?>"><?= Text::sprintf('MOD_USERTOOLS_VIEW_REPORT', $e($task['title'])) ?></a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if (array_filter(array_column($summary['tasks'], 'dryRun'))) : ?>
    <p class="small text-body-secondary mt-2 mb-0"><?= Text::_('MOD_USERTOOLS_DRY_HELP') ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($links) : ?>
  <nav class="mb-3" aria-label="<?= Text::_('MOD_USERTOOLS_QUICK_LINKS') ?>">
    <h3 class="fs-6 fw-semibold mb-2"><?= Text::_('MOD_USERTOOLS_QUICK_LINKS') ?></h3>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($links as [, $view, $label]) : ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=' . $view) ?>"><?= Text::_($label) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-body-secondary">
      <p class="mb-0"><?= Text::sprintf('MOD_USERTOOLS_UPDATED', $date($summary['generated'])) ?></p>
      <p class="mb-0"><?= Text::_('MOD_USERTOOLS_REFRESH_HELP') ?></p>
    </div>
    <a class="btn btn-sm btn-primary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('MOD_USERTOOLS_OPEN') ?></a>
  </div>
<?php endif; ?>
</div>
