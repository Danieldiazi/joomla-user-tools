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
use Joomla\CMS\Session\Session;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView $this */
$layouts   = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$job       = $this->job;
$total     = max(1, (int) $job['total']);
$processed = (int) $job['processed'];
$passwords = $job['mode'] === Registration::MODE_PASSWORD;
// Rows created by the request that rendered this page, with their one-time passwords (never stored).
$rows = array_map(fn ($row) => ['id' => (int) $row['id'], 'username' => (string) $row['username'], 'email' => (string) $row['email'],
    'password' => isset($row['password']) ? (string) $row['password'] : null], $this->oneTimeRows);
$percent = (int) floor($processed * 100 / $total);
?>
<div class="ut-app" data-ut-progress
  data-ut-step-url="<?= $this->escape(Route::_('index.php?option=com_usertools&task=compare.step&format=raw', false)) ?>"
  data-ut-result-url="<?= $this->escape(Route::_('index.php?option=com_usertools&view=compare&layout=result', false)) ?>"
  data-ut-token="<?= $this->escape(Session::getFormToken()) ?>" data-ut-total="<?= (int) $job['total'] ?>" data-ut-processed="<?= $processed ?>"
  data-ut-passwords="<?= $passwords ? '1' : '0' ?>">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'result', 'label' => 'COM_USERTOOLS_COMPARE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_PROGRESS_HEADING') ?></h2>
  <div class="alert alert-info"><?= Text::_('COM_USERTOOLS_PROGRESS_INTRO') ?></div>

  <section class="card ut-card"><div class="card-body">
    <div class="progress" role="progressbar" aria-label="<?= Text::_('COM_USERTOOLS_PROGRESS_HEADING') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>" data-ut-progress-bar>
      <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: <?= $percent ?>%"></div>
    </div>
    <p class="mt-2 mb-0" aria-live="polite" data-ut-progress-text><?= Text::sprintf('COM_USERTOOLS_JS_PROGRESS', $processed, (int) $job['total']) ?></p>
    <noscript><div class="alert alert-warning mt-3 mb-0"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <div class="alert alert-danger mt-3 mb-0" role="alert" data-ut-progress-error hidden>
      <p class="mb-2" data-ut-progress-error-text></p>
      <button type="button" class="btn btn-sm btn-primary" data-ut-progress-retry><?= Text::_('COM_USERTOOLS_PROGRESS_CONTINUE') ?></button>
    </div>
  </div></section>

  <?php if ($passwords) : ?>
  <section class="card ut-card"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_PASSWORDS_HEADING') ?></h3>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-ut-progress-copy><?= Text::_('COM_USERTOOLS_JS_COPY_ALL') ?></button></div>
    <div class="alert alert-warning mt-3"><?= Text::_('COM_USERTOOLS_PROGRESS_PASSWORDS') ?></div>
    <div class="ut-table-scroll"><table class="table table-sm"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_TEMP_PASSWORD') ?></th></tr></thead>
      <tbody data-ut-progress-rows></tbody></table></div>
    <div class="alert alert-success mb-0" data-ut-progress-finished hidden>
      <?= Text::_('COM_USERTOOLS_JS_PROGRESS_DONE_PASSWORDS') ?>
      <a class="btn btn-sm btn-primary ms-2" href="<?= Route::_('index.php?option=com_usertools&view=compare&layout=result') ?>"><?= Text::_('COM_USERTOOLS_PROGRESS_SEE_RESULT') ?></a>
    </div>
  </div></section>
  <?php endif; ?>
  <script type="application/json" data-ut-progress-initial><?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=compare') ?>" class="ut-actions" data-ut-progress-discard>
    <button class="btn btn-outline-danger" name="task" value="compare.discard"><?= Text::_('COM_USERTOOLS_PROGRESS_DISCARD') ?></button>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
