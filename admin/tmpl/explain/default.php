<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessExplanation;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Explain\HtmlView $this */
$result  = $this->result;
$badge   = [AccessExplanation::PASS => 'success', AccessExplanation::FAIL => 'danger', AccessExplanation::UNKNOWN => 'warning', AccessExplanation::INFO => 'secondary'];
$actions = ['view' => 'COM_USERTOOLS_EXPLAIN_ACTION_VIEW', 'edit' => 'COM_USERTOOLS_EXPLAIN_ACTION_EDIT', 'publish' => 'COM_USERTOOLS_EXPLAIN_ACTION_PUBLISH'];
?>
<div class="ut-app ut-narrow ut-explain">
  <p class="lead"><?= Text::_('COM_USERTOOLS_EXPLAIN_INTRO') ?></p>
  <form method="get" action="<?= Route::_('index.php') ?>" class="card ut-card" data-ut-explain-form>
    <div class="card-body">
      <input type="hidden" name="option" value="com_usertools"><input type="hidden" name="view" value="explain">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="ut-explain-login"><?= Text::_('COM_USERTOOLS_EXPLAIN_ACCOUNT') ?></label>
          <input class="form-control" type="text" name="login" id="ut-explain-login" required maxlength="255" autocomplete="off" value="<?= $this->escape($this->login) ?>" aria-describedby="ut-explain-login-help">
          <div class="form-text" id="ut-explain-login-help"><?= Text::_('COM_USERTOOLS_EXPLAIN_ACCOUNT_HELP') ?></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ut-explain-do"><?= Text::_('COM_USERTOOLS_EXPLAIN_ACTION') ?></label>
          <select class="form-select" name="do" id="ut-explain-do">
            <?php foreach ($actions as $value => $label) : ?>
            <option value="<?= $value ?>"<?= $this->action === $value ? ' selected' : '' ?>><?= Text::_($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ut-explain-type"><?= Text::_('COM_USERTOOLS_EXPLAIN_TYPE') ?></label>
          <select class="form-select" name="type" id="ut-explain-type">
            <option value="article"<?= $this->type === 'article' ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_EXPLAIN_TYPE_ARTICLE') ?></option>
            <option value="category"<?= $this->type === 'category' ? ' selected' : '' ?>><?= Text::_('COM_USERTOOLS_EXPLAIN_TYPE_CATEGORY') ?></option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ut-explain-item"><?= Text::_('COM_USERTOOLS_EXPLAIN_ITEM') ?></label>
          <input class="form-control" type="number" min="1" name="item" id="ut-explain-item" required value="<?= $this->item ?: '' ?>" aria-describedby="ut-explain-item-help">
          <div class="form-text" id="ut-explain-item-help"><?= Text::_('COM_USERTOOLS_EXPLAIN_ITEM_HELP') ?></div>
        </div>
        <fieldset class="col-12">
          <legend class="form-label fs-6"><?= Text::_('COM_USERTOOLS_EXPLAIN_CONTEXT') ?></legend>
          <?php foreach (['site' => 'COM_USERTOOLS_EXPLAIN_CONTEXT_SITE', 'administrator' => 'COM_USERTOOLS_EXPLAIN_CONTEXT_ADMIN'] as $value => $label) : ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="context" value="<?= $value ?>" id="ut-explain-context-<?= $value ?>"<?= $this->context === $value ? ' checked' : '' ?>>
            <label class="form-check-label" for="ut-explain-context-<?= $value ?>"><?= Text::_($label) ?></label>
          </div>
          <?php endforeach; ?>
          <div class="form-text"><?= Text::_('COM_USERTOOLS_EXPLAIN_CONTEXT_HELP') ?></div>
        </fieldset>
      </div>
      <div class="ut-actions mb-0"><button class="btn btn-primary" type="submit"><?= Text::_('COM_USERTOOLS_EXPLAIN_SUBMIT') ?></button></div>
    </div>
  </form>
  <?php if ($this->error !== '') : ?><div class="alert alert-warning" role="alert" data-ut-explain-error><?= $this->escape($this->error) ?></div><?php endif; ?>
  <?php if ($result) : $item = $result['item']; $yes = $result['verdict'] === 'yes'; ?>
  <section class="card ut-card" aria-labelledby="ut-explain-heading" data-ut-explain-result data-ut-explain-verdict="<?= $this->escape($result['verdict']) ?>">
    <div class="card-body">
      <h2 class="h4" id="ut-explain-heading"><?= Text::sprintf('COM_USERTOOLS_EXPLAIN_HEADING', $this->escape($this->account['username']), Text::_('COM_USERTOOLS_EXPLAIN_VERB_' . strtoupper($this->action)), $this->escape($item['title']), (int) $item['id']) ?></h2>
      <div class="alert alert-<?= $yes ? 'success' : 'danger' ?>" role="status">
        <strong><?= Text::_($yes ? 'COM_USERTOOLS_EXPLAIN_VERDICT_YES' : 'COM_USERTOOLS_EXPLAIN_VERDICT_NO') ?></strong>
        <?= Text::_($yes ? 'COM_USERTOOLS_EXPLAIN_VERDICT_YES_DESC' : 'COM_USERTOOLS_EXPLAIN_VERDICT_NO_DESC') ?>
      </div>
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_EXPLAIN_CHECKS') ?></h3>
      <ul class="list-unstyled ut-explain-checks">
        <?php foreach ($result['checks'] as $check) : ?>
        <li class="mb-2" data-ut-explain-check="<?= $this->escape($check['key']) ?>" data-ut-explain-status="<?= $this->escape($check['status']) ?>">
          <span class="badge text-bg-<?= $badge[$check['status']] ?>"><?= $this->statusLabel($check['status']) ?></span>
          <?= $this->checkText($check) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$yes && $result['denials'] !== null && $result['denials']) : ?>
      <h3 class="h6"><?= Text::_('COM_USERTOOLS_EXPLAIN_DENIALS') ?></h3>
      <ul data-ut-explain-denials>
        <?php foreach ($result['denials'] as $denial) : ?>
        <li><?= Text::sprintf('COM_USERTOOLS_EXPLAIN_DENIAL', $this->escape($denial['action']), $this->escape($denial['asset']), $this->escape($denial['group'])) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php elseif (!$yes && $result['denials'] === null) : ?>
      <p class="form-text"><?= Text::_('COM_USERTOOLS_EXPLAIN_DENIALS_HIDDEN') ?></p>
      <?php endif; ?>
      <?php if ($this->action === 'view') : ?>
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_EXPLAIN_MENUS') ?></h3>
      <?php if ($result['menus']) : ?>
      <ul class="list-unstyled" data-ut-explain-menus>
        <?php foreach ($result['menus'] as $menu) : ?>
        <li class="mb-1"><span class="badge text-bg-<?= $badge[$menu['status']] ?>"><?= $this->statusLabel($menu['status']) ?></span>
          <?= $this->escape($menu['title']) ?>: <?= $this->checkText($menu) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php else : ?>
      <p data-ut-explain-menus="0"><?= Text::_('COM_USERTOOLS_EXPLAIN_MENUS_NONE') ?></p>
      <?php endif; ?>
      <?php endif; ?>
      <h3 class="h5"><?= Text::_('COM_USERTOOLS_EXPLAIN_NOT_CHECKED') ?></h3>
      <ul data-ut-explain-notes>
        <?php foreach ($result['notes'] as $note) : ?><li><?= Text::_($note) ?></li><?php endforeach; ?>
      </ul>
      <p class="form-text"><?= Text::sprintf('COM_USERTOOLS_EXPLAIN_TIME', $this->escape($result['now'])) ?></p>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_usertools&view=profile&account=' . (int) $this->account['id']) ?>"><?= Text::_('COM_USERTOOLS_EXPLAIN_OPEN_PROFILE') ?></a>
        <?php if ($this->itemLink() !== '') : ?><a class="btn btn-sm btn-outline-secondary" href="<?= Route::_($this->itemLink()) ?>"><?= Text::_('COM_USERTOOLS_EXPLAIN_OPEN_ITEM') ?></a><?php endif; ?>
        <?php if ($this->canPermissions()) : ?><a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_config&view=component&component=com_content') ?>"><?= Text::_('COM_USERTOOLS_EXPLAIN_OPEN_PERMISSIONS') ?></a><?php endif; ?>
        <?php if ($this->canLevels()) : ?><a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_users&view=levels') ?>"><?= Text::_('COM_USERTOOLS_EXPLAIN_OPEN_LEVELS') ?></a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
  <p class="text-body-secondary"><?= Text::_('COM_USERTOOLS_EXPLAIN_READ_ONLY') ?></p>
</div>
