<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Anonymize\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_ANONYMIZE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ANONYMIZE_RESULT_HEADING') ?></h2>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_STAT_ANONYMIZED' => count($result['anonymized']), 'COM_USERTOOLS_STAT_SKIPPED' => (int) $result['skipped'], 'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($result['anonymized']) : ?>
  <section class="card ut-card" data-ut-anonymized="<?= count($result['anonymized']) ?>"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_ANONYMIZED_HEADING') ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_actionlogs&view=actionlogs&filter[extension]=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_OPEN_ACTION_LOG') ?></a></div>
    <p class="form-text"><?= Text::_('COM_USERTOOLS_ANONYMIZED_HELP') ?></p>
    <div class="ut-table-scroll"><table class="table table-sm"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_ID') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_OLD_USERNAME') ?></th>
      <th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NEW_NAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_ANONYMIZE_NEW_USERNAME') ?></th></tr></thead><tbody>
    <?php foreach ($result['anonymized'] as $row) : ?><tr data-ut-anonymized-row="<?= (int) $row['id'] ?>">
      <td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>" target="_blank" rel="noopener"
        aria-label="<?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_OPEN_ACCOUNT', (int) $row['id']) ?>"><?= (int) $row['id'] ?></a></td>
      <td><?= $this->escape($row['username']) ?></td><td><?= $this->escape($row['newName'] ?? '') ?></td><td><code><?= $this->escape($row['newUsername'] ?? '') ?></code></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></section>
  <?php endif; ?>
  <?php if (!empty($result['partial'])) : ?>
  <section class="alert alert-warning" data-ut-anonymize-partial>
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ANONYMIZE_PARTIAL_HEADING') ?></h3>
    <p><?= Text::_('COM_USERTOOLS_ANONYMIZE_CLEANUP_INCOMPLETE') ?></p>
    <ul><?php foreach ($result['partial'] as $row) : ?><li>ID <?= (int) $row['id'] ?>: <?= $this->escape($row['newUsername']) ?></li><?php endforeach; ?></ul>
  </section>
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
