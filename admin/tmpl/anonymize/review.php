<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Anonymize\HtmlView $this */
$layouts    = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$review     = $this->review;
$candidates = $review['candidates'];
$skipped    = $review['skipped'];
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'review', 'label' => 'COM_USERTOOLS_ANONYMIZE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ANONYMIZE_REVIEW_HEADING') ?></h2>
  <p class="lead"><?= Text::plural('COM_USERTOOLS_ANONYMIZE_FOUND', count($candidates)) ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" data-ut-suspension-form>
    <input type="hidden" name="preview" value="<?= $this->escape($review['nonce']) ?>">
    <input type="hidden" name="selected" value="[]" data-ut-selected>
    <input type="hidden" name="selection_ready" value="0" data-ut-selection-ready>
    <section class="card ut-card"><div class="card-body">
    <?php if ($candidates) : ?>
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="ut-anonymize-all" data-ut-suspend-all><label class="form-check-label" for="ut-anonymize-all"><?= Text::_('COM_USERTOOLS_SELECT_ALL') ?></label></div>
        <?php if (count($candidates) > 8) : ?>
        <input type="search" class="form-control ut-filter" placeholder="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" aria-label="<?= Text::_('COM_USERTOOLS_FILTER_PLACEHOLDER') ?>" data-ut-filter="ut-anonymize-table">
        <?php endif; ?>
      </div>
      <div class="ut-table-scroll"><table class="table table-sm table-striped" id="ut-anonymize-table">
        <caption class="visually-hidden"><?= Text::_('COM_USERTOOLS_ANONYMIZE_REVIEW_HEADING') ?></caption>
        <thead><tr>
        <th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_COLUMN') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_NAME') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_STATE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></th>
        <th scope="col"><?= Text::_('COM_USERTOOLS_GROUPS') ?></th></tr></thead><tbody>
      <?php foreach ($candidates as $user) : ?>
        <tr data-ut-anonymize-row="<?= (int) $user->id ?>"><td><input class="form-check-input" type="checkbox" data-ut-suspend-id="<?= (int) $user->id ?>" aria-label="<?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_USER', $this->escape($user->username)) ?>"<?= in_array($user->id, $review['selected'], true) ? ' checked' : '' ?>></td>
          <td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $user->id) ?>" target="_blank" rel="noopener"><?= $this->escape($user->username) ?></a></td>
          <td><?= $this->escape($user->name) ?></td><td><?= $this->escape($user->email) ?></td>
          <td><?php if ($user->block) : ?><span class="badge text-bg-secondary"><?= Text::_('COM_USERTOOLS_STATE_BLOCKED') ?></span>
            <?php else : ?><span class="badge text-bg-warning" data-ut-anonymize-active><?= Text::_('COM_USERTOOLS_ANONYMIZE_STATE_ACTIVE') ?></span><?php endif; ?></td>
          <td><?= $this->date($user->lastvisitDate) ?></td><td><?= $this->groupTitles($user->groups, $this->groups) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table><p class="text-muted" data-ut-filter-empty hidden><?= Text::_('COM_USERTOOLS_JS_NO_RESULTS') ?></p></div>
    <?php else : ?>
      <p class="mb-0"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NONE') ?></p>
    <?php endif; ?>
      <p class="text-muted mt-2 mb-0" data-ut-suspension-count aria-live="polite"></p>
    </div></section>

    <?php if ($skipped) : ?>
    <section class="card ut-card" data-ut-anonymize-skipped="<?= count($skipped) ?>"><div class="card-body">
      <h3 class="h5"><?= Text::plural('COM_USERTOOLS_ANONYMIZE_SKIPPED_HEADING', count($skipped)) ?></h3>
      <ul class="mb-0">
        <?php foreach (array_slice($skipped, 0, 50) as $row) : ?>
        <li><strong><?= $this->escape($row['label']) ?></strong>: <?= Text::_($row['reason']) ?></li>
        <?php endforeach; ?>
        <?php if (count($skipped) > 50) : ?><li><?= Text::sprintf('COM_USERTOOLS_MOVE_ACCOUNTS_MORE', count($skipped) - 50) ?></li><?php endif; ?>
      </ul>
    </div></section>
    <?php endif; ?>

    <?php if ($candidates) : ?>
      <div class="alert alert-danger"><strong><?= Text::_('COM_USERTOOLS_PERMANENT') ?>.</strong> <?= Text::_('COM_USERTOOLS_ANONYMIZE_WARNING') ?></div>
      <?= LayoutHelper::render('usertools.confirm', ['label' => 'COM_USERTOOLS_ANONYMIZE_CONFIRM', 'count' => count($review['selected']), 'threshold' => $review['threshold'], 'dynamic' => true, 'help' => 'COM_USERTOOLS_ANONYMIZE_TYPE_HELP'], $layouts) ?>
    <?php endif; ?>
    <noscript><div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_ERROR_JAVASCRIPT') ?></div></noscript>
    <div class="ut-actions">
      <button class="btn btn-outline-secondary" name="task" value="anonymize.back" formnovalidate><?= Text::_('COM_USERTOOLS_BACK_EDIT') ?></button>
      <?php if ($candidates) : ?><button class="btn btn-danger" name="task" value="anonymize.apply" data-ut-suspend-submit disabled><?= Text::_('COM_USERTOOLS_ANONYMIZE_APPLY') ?></button><?php endif; ?>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
