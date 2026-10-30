<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/** Progress indicator. $displayData: steps (array<string, string key>), current (string), label (string key) */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<nav aria-label="<?= Text::_($displayData['label']) ?>">
  <ol class="ut-steps">
  <?php foreach ($displayData['steps'] as $key => $label) : ?>
    <li<?= $displayData['current'] === $key ? ' aria-current="step" class="ut-current"' : '' ?>><?= Text::_($label) ?></li>
  <?php endforeach; ?>
  </ol>
</nav>
