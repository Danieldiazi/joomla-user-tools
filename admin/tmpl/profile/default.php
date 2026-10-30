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

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Profile\HtmlView $this */
$profile = $this->profile;
?>
<div class="ut-app">
  <p class="lead"><?= Text::_('COM_USERTOOLS_PROFILE_INTRO') ?></p>
  <form method="get" action="<?= Route::_('index.php') ?>" class="card ut-card" data-ut-profile-search>
    <div class="card-body">
      <input type="hidden" name="option" value="com_usertools"><input type="hidden" name="view" value="profile">
      <label class="form-label" for="ut-profile-login"><?= Text::_('COM_USERTOOLS_PROFILE_LOGIN') ?></label>
      <div class="d-flex flex-wrap gap-2">
        <input class="form-control ut-filter" type="search" name="login" id="ut-profile-login" required maxlength="255" autocomplete="off" value="<?= $this->escape($this->login) ?>">
        <button class="btn btn-primary" type="submit"><?= Text::_('COM_USERTOOLS_PROFILE_OPEN') ?></button>
      </div>
    </div>
  </form>
  <?php if ($this->error !== '') : ?><div class="alert alert-warning" role="alert" data-ut-profile-error><?= $this->escape($this->error) ?></div><?php endif; ?>
  <?php if ($profile) : $account = $profile['account']; ?>
  <section class="card ut-card" aria-labelledby="ut-profile-identity" data-ut-profile-account="<?= (int) $account->id ?>">
    <div class="card-body">
      <h2 class="h3" id="ut-profile-identity"><?= $this->escape($account->name) ?> <small class="text-body-secondary">(<?= $this->escape($account->username) ?> · #<?= (int) $account->id ?>)</small></h2>
      <dl class="row mb-0">
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_EMAIL') ?></dt><dd class="col-sm-8"><?= $this->escape($account->email) ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_PROFILE_STATUS') ?></dt><dd class="col-sm-8" data-ut-profile-status><?= Text::_($account->block ? 'COM_USERTOOLS_PROFILE_BLOCKED' : 'COM_USERTOOLS_PROFILE_ACTIVE') ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_LAST_VISIT_UTC') ?></dt><dd class="col-sm-8"><?= $this->date($account->lastvisitDate) ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_REGISTERED_UTC') ?></dt><dd class="col-sm-8"><?= $this->date($account->registerDate) ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_PROFILE_BACKEND') ?></dt><dd class="col-sm-8"><?= Text::_($account->hasBackendAccess ? 'JYES' : 'JNO') ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_PROFILE_SUPER') ?></dt><dd class="col-sm-8"><?= Text::_($account->isSuperUser ? 'JYES' : 'JNO') ?></dd>
        <dt class="col-sm-4"><?= Text::_('COM_USERTOOLS_PROFILE_MFA') ?></dt><dd class="col-sm-8" data-ut-profile-mfa="<?= (int) $profile['mfa'] ?>"><?= Text::_($profile['mfa'] ? 'JYES' : 'JNO') ?></dd>
      </dl>
    </div>
  </section>
  <div class="row g-3 mb-3">
    <section class="col-lg-5" aria-labelledby="ut-profile-groups"><div class="card h-100"><div class="card-body">
      <h2 class="h4" id="ut-profile-groups"><?= Text::_('COM_USERTOOLS_GROUPS') ?></h2>
      <ul class="list-unstyled mb-0">
      <?php foreach ($profile['tree'] as $row) : ?>
        <li class="mb-2<?= $row['member'] ? '' : ' text-body-secondary' ?>" style="padding-inline-start: <?= (int) $row['depth'] ?>em" data-ut-profile-group="<?= (int) $row['id'] ?>" data-ut-profile-direct="<?= (int) $row['member'] ?>">
          <?= $this->escape($this->groupTitle($row['id'])) ?> <small>— <?= Text::_($row['member'] ? 'COM_USERTOOLS_PROFILE_ASSIGNED' : 'COM_USERTOOLS_PROFILE_PATH_GROUP') ?></small>
        </li>
      <?php endforeach; ?>
      </ul>
    </div></div></section>
    <section class="col-lg-7" aria-labelledby="ut-profile-levels"><div class="card h-100"><div class="card-body">
      <h2 class="h4" id="ut-profile-levels"><?= Text::_('COM_USERTOOLS_PROFILE_LEVELS') ?></h2>
      <ul class="list-unstyled mb-0">
      <?php foreach ($profile['levels'] as $level) : ?>
        <li class="mb-3" data-ut-profile-level="<?= (int) $level['id'] ?>"><strong><?= $this->escape($level['title']) ?></strong>
          <ul>
          <?php foreach ($level['sources'] as $source) : ?>
            <li><?php if ($source['state'] === 'inherited') : ?><?= Text::sprintf('COM_USERTOOLS_PROFILE_LEVEL_INHERITED', $this->escape($this->groupTitle($source['group'])), $this->escape($this->groupTitle($source['via']))) ?>
              <?php elseif ($source['state'] === 'direct') : ?><?= Text::sprintf('COM_USERTOOLS_PROFILE_LEVEL_DIRECT', $this->escape($this->groupTitle($source['group']))) ?>
              <?php else : ?><?= Text::_('COM_USERTOOLS_PROFILE_LEVEL_' . strtoupper($source['state'])) ?><?php endif; ?></li>
          <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
      </ul>
    </div></div></section>
  </div>
  <section class="card ut-card" aria-labelledby="ut-profile-content"><div class="card-body">
    <h2 class="h4" id="ut-profile-content"><?= Text::_('COM_USERTOOLS_PROFILE_CONTENT') ?></h2>
    <p><?= Text::_('COM_USERTOOLS_PROFILE_CONTENT_NOTE') ?></p>
    <?php if ($profile['content']) : ?>
    <div class="ut-table-scroll"><table class="table table-striped"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_TYPE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_COUNT') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_REVIEW') ?></th></tr></thead><tbody>
    <?php foreach ($profile['content'] as $row) : ?>
      <tr data-ut-profile-content="<?= $this->escape($row['kind']) ?>" data-ut-profile-component="<?= $this->escape($row['component']) ?>" data-ut-profile-count="<?= (int) $row['count'] ?>">
        <th scope="row"><?= $row['kind'] === 'categories' ? Text::sprintf('COM_USERTOOLS_PROFILE_CATEGORIES_OF', $this->escape($this->componentTitle($row['component']))) : Text::_('COM_USERTOOLS_PROFILE_' . strtoupper($row['kind'])) ?></th>
        <td><?= (int) $row['count'] ?></td><td><?php if ($row['url'] !== '') : ?><a href="<?= Route::_($row['url']) ?>"><?= Text::_('COM_USERTOOLS_PROFILE_OPEN_LIST') ?></a><?php else : ?><?= Text::_('COM_USERTOOLS_PROFILE_NO_AUTHOR_FILTER') ?><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else : ?><p><?= Text::_('COM_USERTOOLS_PROFILE_CONTENT_DENIED') ?></p><?php endif; ?>
  </div></section>
  <section class="card ut-card" aria-labelledby="ut-profile-logs"><div class="card-body">
    <h2 class="h4" id="ut-profile-logs"><?= Text::_('COM_USERTOOLS_PROFILE_LOGS') ?></h2>
    <?php if (!$profile['logsAllowed']) : ?><p><?= Text::_('COM_USERTOOLS_PROFILE_LOGS_DENIED') ?></p>
    <?php else : ?>
      <p><?= Text::_('COM_USERTOOLS_PROFILE_LOGS_NOTE') ?></p>
      <?php if ($profile['logs']) : ?>
      <div class="ut-table-scroll"><table class="table table-sm table-striped"><thead><tr><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_DATE') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_RELATION') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_EVENT') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_ACTOR') ?></th><th scope="col"><?= Text::_('COM_USERTOOLS_PROFILE_SOURCE') ?></th></tr></thead><tbody>
      <?php foreach ($profile['logs'] as $row) : ?>
        <tr data-ut-profile-log="<?= (int) $row['id'] ?>" data-ut-profile-role="<?= $this->escape($row['role']) ?>"><td><a href="<?= Route::_($row['url']) ?>"><?= $this->date($row['date']) ?></a></td><td><?= Text::_('COM_USERTOOLS_PROFILE_ROLE_' . strtoupper($row['role'])) ?></td><td><?= Text::_($row['event']) ?></td><td><?= $row['actor'] !== '' ? $this->escape($row['actor']) : Text::_('COM_USERTOOLS_PROFILE_SYSTEM') ?></td><td><?= $this->escape($this->componentTitle($row['extension'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <?php else : ?><p><?= Text::_('COM_USERTOOLS_PROFILE_LOGS_NONE') ?></p><?php endif; ?>
    <?php endif; ?>
  </div></section>
  <?php if (in_array(true, $profile['actions'], true)) : ?>
  <section class="card ut-card" aria-labelledby="ut-profile-actions"><div class="card-body">
    <h2 class="h4" id="ut-profile-actions"><?= Text::_('COM_USERTOOLS_PROFILE_ACTIONS') ?></h2>
    <p><?= Text::_('COM_USERTOOLS_PROFILE_ACTIONS_NOTE') ?></p><div class="ut-actions">
    <?php foreach (['ungroup', 'block', 'anonymize'] as $action) : ?>
      <?php if ($profile['actions'][$action]) : $query = $action === 'block' ? 'task=inactive.account&' . Session::getFormToken() . '=1' : 'view=' . $action; ?>
      <a class="btn btn-outline-secondary" data-ut-profile-action="<?= $action ?>" href="<?= Route::_('index.php?option=com_usertools&' . $query . '&account=' . (int) $account->id) ?>"><?= Text::_('COM_USERTOOLS_PROFILE_ACTION_' . strtoupper($action)) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>
  </div></section>
  <?php endif; ?>
  <?php endif; ?>
</div>
