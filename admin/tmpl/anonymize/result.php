<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Anonymize\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
$retry   = !empty($result['retryCleanup']);
$completed = $retry ? ($result['cleaned'] ?? []) : $result['anonymized'];
$rows      = array_merge($completed, $result['partial'] ?? []);
$plugins   = array_values(array_unique(array_merge(...array_map(static fn (array $r): array => (array) ($r['plugins'] ?? []), $rows ?: [[]]))));
sort($plugins);
$cleanupState = static fn (array $row): string => in_array('pending', (array) ($row['cleanup'] ?? []), true) || empty($row['cleanup']) ? 'pending' : 'verified';
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_ANONYMIZE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_($retry ? 'COM_USERTOOLS_ANONYMIZE_RETRY_RESULT_HEADING' : 'COM_USERTOOLS_ANONYMIZE_RESULT_HEADING') ?></h2>
  <div class="ut-stats">
    <?php foreach ([$retry ? 'COM_USERTOOLS_ANONYMIZE_STAT_CLEANED' : 'COM_USERTOOLS_STAT_ANONYMIZED' => count($completed), 'COM_USERTOOLS_ANONYMIZE_STAT_PARTIAL' => count($result['partial'] ?? []), 'COM_USERTOOLS_STAT_SKIPPED' => (int) $result['skipped'], 'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($completed) : ?>
  <section class="card ut-card" <?= $retry ? 'data-ut-cleaned="' . count($completed) . '"' : 'data-ut-anonymized="' . count($completed) . '"' ?>><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_($retry ? 'COM_USERTOOLS_ANONYMIZE_CLEANED_HEADING' : 'COM_USERTOOLS_ANONYMIZED_HEADING') ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_actionlogs&view=actionlogs&filter[extension]=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_ACTION_LOG') ?></a></div>
    <p class="form-text"><?= Text::_($retry ? 'COM_USERTOOLS_ANONYMIZE_CLEANED_HELP' : 'COM_USERTOOLS_ANONYMIZED_HELP') ?></p>
    <div class="ut-table-scroll"><table class="table table-sm"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_ID') ?></th><th scope="col"><?= Text::_($retry ? 'COM_USERTOOLS_USERNAME' : 'COM_USERTOOLS_ANONYMIZE_OLD_USERNAME') ?></th>
      <?php if (!$retry) : ?><th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NEW_NAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NEW_USERNAME') ?></th><?php endif; ?>
      <th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_COL_IDENTITY') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_COL_CLEANUP') ?></th></tr></thead><tbody>
    <?php foreach ($completed as $row) : ?><tr data-ut-<?= $retry ? 'cleaned' : 'anonymized' ?>-row="<?= (int) $row['id'] ?>">
      <td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>" target="_blank" rel="noopener"
        aria-label="<?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_OPEN_ACCOUNT', (int) $row['id']) ?>"><?= (int) $row['id'] ?></a></td>
      <td><?= $this->escape($row['username']) ?></td><?php if (!$retry) : ?><td><?= $this->escape($row['newName'] ?? '') ?></td><td><code><?= $this->escape($row['newUsername'] ?? '') ?></code></td><?php endif; ?>
      <td data-ut-anonymize-identity="<?= ($row['identity'] ?? '') === 'verified' ? 'verified' : 'unverified' ?>"><?= Text::_(($row['identity'] ?? '') === 'verified' ? 'COM_USERTOOLS_ANONYMIZE_IDENTITY_VERIFIED' : 'COM_USERTOOLS_ANONYMIZE_IDENTITY_UNVERIFIED') ?></td>
      <td data-ut-anonymize-cleanup="<?= $cleanupState($row) ?>"><?= Text::_($cleanupState($row) === 'verified' ? 'COM_USERTOOLS_ANONYMIZE_CLEANUP_VERIFIED' : 'COM_USERTOOLS_ANONYMIZE_CLEANUP_PENDING') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></section>
  <?php endif; ?>
  <?php if (!empty($result['partial'])) : ?>
  <section class="alert alert-warning" data-ut-anonymize-partial>
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ANONYMIZE_PARTIAL_HEADING') ?></h3>
    <p><?= Text::_('COM_USERTOOLS_ANONYMIZE_CLEANUP_INCOMPLETE') ?></p>
    <ul><?php foreach ($result['partial'] as $row) : ?>
      <li data-ut-anonymize-partial-row="<?= (int) $row['id'] ?>">ID <?= (int) $row['id'] ?>: <?= $this->escape($row['newUsername']) ?> — <?= Text::_('COM_USERTOOLS_ANONYMIZE_IDENTITY_VERIFIED') ?>.
        <?php $pending = array_keys(array_filter((array) ($row['cleanup'] ?? []), static fn (string $s): bool => $s !== 'verified')); ?>
        <?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_PENDING_SOURCES', implode(', ', array_map(static fn (string $s): string => Text::_('COM_USERTOOLS_ANONYMIZE_SOURCE_' . strtoupper($s)), $pending ?: ['profile', 'notes', 'fields']))) ?></li>
    <?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
  <?php if ($rows) : ?>
  <section class="card ut-card" aria-labelledby="ut-anonymize-scope" data-ut-anonymize-scope><div class="card-body">
    <h3 class="h5" id="ut-anonymize-scope"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE') ?></h3>
    <p class="fw-bold" data-ut-anonymize-limits><?= Text::_('COM_USERTOOLS_ANONYMIZE_LIMITS') ?></p>
    <dl class="mb-0">
      <dt><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE_CORE') ?></dt>
      <dd><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE_CORE_DESC') ?></dd>
      <dt><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE_PLUGINS') ?></dt>
      <dd data-ut-anonymize-plugins="<?= count($plugins) ?>"><?php if ($plugins) : ?><?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_SCOPE_PLUGINS_RAN', $this->escape(implode(', ', $plugins))) ?><?php else : ?><?= Text::_($retry ? 'COM_USERTOOLS_ANONYMIZE_SCOPE_PLUGINS_RETRY' : 'COM_USERTOOLS_ANONYMIZE_SCOPE_PLUGINS_NONE') ?><?php endif; ?></dd>
      <dt><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE_OTHER') ?></dt>
      <dd class="mb-0"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SCOPE_OTHER_DESC') ?></dd>
    </dl>
  </div></section>
  <?php endif; ?>
  <?php if ((int) $result['skipped'] > 0) : ?><div class="alert alert-info"><?= Text::_('COM_USERTOOLS_ANONYMIZE_SKIPPED_HELP') ?></div><?php endif; ?>
  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ANONYMIZE_ERRORS_HEADING') ?></h3>
    <ul class="mb-0"><?php foreach ($result['errors'] as $row) : ?><li><strong><?= $this->escape($row['username']) ?></strong>: <?= $this->escape(Text::_($row['message'])) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>
  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>"><?= Text::_('COM_USERTOOLS_ANONYMIZE_AGAIN') ?></a>
  </div>
</div>
