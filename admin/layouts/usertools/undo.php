<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * How to undo the blocks just shown: a button to "Undo blocks" for this batch when the user may use
 * it, otherwise the manual way in Users. $displayData: batch (string reference or ''), can (bool)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$batch = (string) ($displayData['batch'] ?? '');
?>
<?php if ($batch !== '' && !empty($displayData['can'])) : ?>
<form method="post" action="<?= Route::_('index.php?option=com_usertools&view=unblock') ?>" class="d-flex flex-wrap gap-2 align-items-center mb-2" data-ut-undo-batch>
  <input type="hidden" name="batch" value="<?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8') ?>">
  <button class="btn btn-sm btn-outline-primary" name="task" value="unblock.select"><?= Text::_('COM_USERTOOLS_UNBLOCK_THESE') ?></button>
  <span class="form-text m-0"><?= Text::_('COM_USERTOOLS_UNBLOCK_THESE_HELP') ?></span>
  <?= HTMLHelper::_('form.token') ?>
</form>
<?php else : ?>
<p class="form-text"><?= Text::_('COM_USERTOOLS_BLOCKED_UNDO_HELP') ?></p>
<?php endif; ?>
