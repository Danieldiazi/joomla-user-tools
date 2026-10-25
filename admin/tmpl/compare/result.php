<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Compare\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$result  = $this->result;
$mailed  = array_filter($result['created'], fn ($row) => ($row['mode'] ?? '') === 'email');
$shown   = array_filter($result['created'], fn ($row) => ($row['mode'] ?? '') === 'password');
?>
<div class="ut-app">
  <?= LayoutHelper::render('usertools.steps', ['steps' => ['edit' => 'COM_USERTOOLS_STEP_LIST', 'review' => 'COM_USERTOOLS_STEP_REVIEW', 'result' => 'COM_USERTOOLS_STEP_RESULT'], 'current' => 'result', 'label' => 'COM_USERTOOLS_COMPARE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_RESULT_HEADING') ?></h2>
  <div class="ut-stats">
    <?php foreach (['COM_USERTOOLS_STAT_BLOCKED' => count($result['blocked']), 'COM_USERTOOLS_STAT_CREATED' => count($result['created']), 'COM_USERTOOLS_STAT_SKIPPED' => (int) ($result['skipped'] ?? 0), 'COM_USERTOOLS_STAT_ERRORS' => count($result['errors'])] as $label => $number) : ?>
    <div class="card"><div class="card-body"><strong><?= (int) $number ?></strong><span><?= Text::_($label) ?></span></div></div>
    <?php endforeach; ?>
  </div>

  <?php if ($shown) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_PASSWORDS_HEADING') ?></h3>
    <div class="alert alert-warning"><?= Text::_('COM_USERTOOLS_PASSWORDS_ONCE') ?></div>
    <div class="ut-table-scroll"><table class="table table-sm"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_USERNAME') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_EMAIL') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_TEMP_PASSWORD') ?></th></tr></thead><tbody>
    <?php foreach ($shown as $row) : ?>
      <tr><td><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></td><td><?= $this->escape($row['email']) ?></td>
        <td><?php if (isset($row['password'])) : ?><code class="ut-secret"><?= $this->escape($row['password']) ?></code> <button type="button" class="btn btn-sm btn-outline-secondary" data-ut-copy="<?= $this->escape($row['password']) ?>"><?= Text::_('COM_USERTOOLS_COPY') ?></button><?php else : ?><?= Text::_('COM_USERTOOLS_PASSWORD_ALREADY_SHOWN') ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div></section>
  <?php endif; ?>

  <?php if ($mailed) : ?>
  <section class="card ut-card"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_MAILED_HEADING') ?></h3>
    <ul class="mb-0">
    <?php foreach ($mailed as $row) : ?>
      <li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a> · <?= $this->escape($row['email']) ?> ·
        <?= !empty($row['mailed']) ? '<span class="text-success">' . Text::_('COM_USERTOOLS_MAIL_SENT') . '</span>' : '<span class="text-danger">' . Text::_('COM_USERTOOLS_MAIL_FAILED') . '</span>' ?></li>
    <?php endforeach; ?>
    </ul>
  </div></section>
  <?php endif; ?>

  <?php if ($result['blocked']) : ?>
  <section class="card ut-card"><div class="card-body">
    <div class="ut-section-heading"><h3 class="h5 mb-0"><?= Text::_('COM_USERTOOLS_BLOCKED_HEADING') ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_users&view=users&filter[state]=1') ?>"><?= Text::_('COM_USERTOOLS_OPEN_BLOCKED_USERS') ?></a></div>
    <p class="form-text"><?= Text::_('COM_USERTOOLS_BLOCKED_UNDO_HELP') ?></p>
    <ul class="mb-0"><?php foreach ($result['blocked'] as $row) : ?><li><a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row['id']) ?>"><?= $this->escape($row['username']) ?></a></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>

  <?php if ($result['errors']) : ?>
  <section class="card ut-card border-danger"><div class="card-body">
    <h3 class="h5"><?= Text::_('COM_USERTOOLS_ERRORS_HEADING') ?></h3>
    <ul class="mb-0"><?php foreach ($result['errors'] as $row) : ?><li><strong><?= $this->escape($row['username']) ?></strong>: <?= Text::_($row['message']) ?></li><?php endforeach; ?></ul>
  </div></section>
  <?php endif; ?>

  <div class="ut-actions">
    <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools') ?>"><?= Text::_('COM_USERTOOLS_BACK_HOME') ?></a>
    <a class="btn btn-primary" href="<?= Route::_('index.php?option=com_usertools&view=compare') ?>"><?= Text::_('COM_USERTOOLS_NEW_COMPARISON') ?></a>
  </div>
</div>
