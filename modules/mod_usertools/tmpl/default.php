<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @var array<string, mixed>|null $summary
 * @var bool                      $canDelete
 */
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
  <div class="row g-3 mb-3">
    <?php if ($summary['inactive'] !== null) : ?>
    <div class="col-sm-6">
      <a class="d-block h-100 p-3 border rounded text-decoration-none" href="<?= Route::_('index.php?option=com_usertools&view=inactive') ?>">
        <span class="d-block">
          <strong class="d-block fs-2 lh-1 mb-1" data-ut-count="inactive"><?= (int) $summary['inactive'] ?></strong>
          <span class="d-block"><?= Text::_('MOD_USERTOOLS_INACTIVE') ?></span>
          <small class="d-block text-body-secondary"><?= Text::_('MOD_USERTOOLS_INACTIVE_HELP') ?></small>
        </span>
      </a>
    </div>
    <?php endif; ?>
    <?php if ($summary['pendingDeletion'] !== null) : ?>
    <div class="col-sm-6">
      <a class="d-block h-100 p-3 border rounded text-decoration-none" href="<?= Route::_('index.php?option=com_usertools&view=delete&status=blocked') ?>">
        <span class="d-block">
          <strong class="d-block fs-2 lh-1 mb-1" data-ut-count="pending"><?= (int) $summary['pendingDeletion'] ?></strong>
          <span class="d-block"><?= Text::_('MOD_USERTOOLS_PENDING_DELETION') ?></span>
          <small class="d-block text-body-secondary"><?= Text::_('MOD_USERTOOLS_PENDING_DELETION_HELP') ?></small>
        </span>
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($summary['tasks'] !== null) : ?>
    <?php if ($summary['tasks']) : ?>
    <div class="table-responsive">
      <table class="table table-sm mb-2">
        <caption class="visually-hidden"><?= Text::_('MOD_USERTOOLS_TASKS') ?></caption>
        <thead><tr>
          <th scope="col"><?= Text::_('MOD_USERTOOLS_TASK') ?></th>
          <th scope="col"><?= Text::_('MOD_USERTOOLS_MODE') ?></th>
          <th scope="col"><?= Text::_('MOD_USERTOOLS_LAST_RUN') ?></th>
          <th scope="col"><?= Text::_('MOD_USERTOOLS_RESULT') ?></th>
          <th scope="col"><?= Text::_('MOD_USERTOOLS_NEXT_RUN') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($summary['tasks'] as $task) :
            [$badge, $label] = $statusBadge[$task['status']] ?? $statusBadge['unknown'];
            ?>
          <tr>
            <td><a href="<?= Route::_('index.php?option=com_scheduler&task=task.edit&id=' . (int) $task['id']) ?>"><?= $e($task['title']) ?></a>
              <?php if (!$task['enabled']) : ?><span class="badge bg-secondary"><?= Text::_('MOD_USERTOOLS_DISABLED') ?></span><?php endif; ?></td>
            <td><?= Text::_($task['dryRun'] ? 'MOD_USERTOOLS_MODE_DRY' : 'MOD_USERTOOLS_MODE_REAL') ?></td>
            <td><?= $date($task['lastRun']) ?></td>
            <td><?php if ($task['reportId'] !== '') : ?><a href="<?= Route::_('index.php?option=com_usertools&view=report&report_id=' . $e($task['reportId'])) ?>"><?php endif; ?>
              <span class="badge bg-<?= $badge ?>"><?= Text::_($label) ?></span><?php if ($task['reportId'] !== '') : ?></a><?php endif; ?></td>
            <td><?= $task['enabled'] && $task['nextRun'] ? $date($task['nextRun']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small class="text-body-secondary"><?= Text::sprintf('MOD_USERTOOLS_GENERATED', $date($summary['generated'])) ?></small>
    <a class="btn btn-sm btn-primary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('MOD_USERTOOLS_OPEN') ?></a>
  </div>
<?php endif; ?>
</div>
