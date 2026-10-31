<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Shared account selector of the single-account tools. The visible field accepts a username, an
 * email or an ID and offers suggestions labelled "username — Name (email) · ID n"; choosing one sends
 * its ID in the hidden "account" field, which is what the server uses. When a typed text names
 * several accounts, the candidates are listed and one must be chosen explicitly.
 * $displayData: id (string), tool (profile|ungroup|explain), login (string), account (int),
 * candidates (list of id/username/name/email/blocked), label and help (string keys), required (bool).
 */

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$id         = $displayData['id'];
$candidates = $displayData['candidates'] ?? [];
$suggest    = Route::_('index.php?option=com_usertools&task=accounts.suggest&tool=' . $displayData['tool'] . '&' . Session::getFormToken() . '=1', false);
$e          = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div data-ut-account-picker>
  <label class="form-label" for="<?= $id ?>"><?= Text::_($displayData['label']) ?></label>
  <input class="form-control" type="text" name="login" id="<?= $id ?>" maxlength="255" autocomplete="off" list="<?= $id ?>-list"
    value="<?= $e($displayData['login']) ?>" aria-describedby="<?= $id ?>-help"<?= !empty($displayData['required']) ? ' required' : '' ?>
    data-ut-account-search="<?= $e($suggest) ?>" data-ut-account-min="<?= AccountIdentity::MIN_SEARCH ?>">
  <datalist id="<?= $id ?>-list"></datalist>
  <input type="hidden" name="account" value="<?= (int) ($displayData['account'] ?? 0) ?>" data-ut-account-id>
  <div class="form-text" id="<?= $id ?>-help"><?= Text::_($displayData['help']) ?></div>
  <?php if ($candidates) : ?>
  <fieldset class="mt-3" data-ut-account-candidates>
    <legend class="form-label fs-6 fw-bold"><?= Text::sprintf('COM_USERTOOLS_ACCOUNT_CHOOSE', $e($displayData['login'])) ?></legend>
    <?php foreach ($candidates as $candidate) : $cid = $id . '-candidate-' . (int) $candidate['id']; ?>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="account" value="<?= (int) $candidate['id'] ?>" id="<?= $cid ?>" data-ut-account-candidate>
      <label class="form-check-label" for="<?= $cid ?>"><?= Text::sprintf('COM_USERTOOLS_ACCOUNT_LABEL', ...array_map($e, AccountIdentity::labelParts($candidate))) ?><?php if (!empty($candidate['blocked'])) : ?> · <?= Text::_('COM_USERTOOLS_ACCOUNT_BLOCKED') ?><?php endif; ?></label>
    </div>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>
</div>
