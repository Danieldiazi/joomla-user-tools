<?php

/**
 * Confirmation block of the manual tools.
 * $displayData: label (string key), count (int, accounts to block), threshold (int), dynamic (bool: count follows the selection)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$count     = (int) $displayData['count'];
$threshold = (int) $displayData['threshold'];
$dynamic   = !empty($displayData['dynamic']);
$typed     = $threshold > 0 && ($dynamic || $count >= $threshold);
?>
<div class="card ut-card ut-confirm" data-ut-confirm data-ut-threshold="<?= $threshold ?>">
  <div class="card-body">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="confirm" value="1" id="ut-confirm" required>
      <label class="form-check-label" for="ut-confirm"><?= Text::_($displayData['label']) ?></label>
    </div>
    <?php if ($typed) : ?>
    <div class="mt-3" data-ut-typed<?= (!$dynamic || $count >= $threshold) ? '' : ' hidden' ?>>
      <label class="form-label" for="ut-confirm-count" data-ut-typed-label><?= Text::sprintf('COM_USERTOOLS_TYPE_COUNT', $count) ?></label>
      <input class="form-control ut-count-input" type="text" inputmode="numeric" autocomplete="off" name="confirm_count" id="ut-confirm-count" data-ut-typed-input<?= (!$dynamic || $count >= $threshold) ? ' required' : '' ?>>
      <div class="form-text"><?= Text::sprintf('COM_USERTOOLS_TYPE_COUNT_HELP', $threshold) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
