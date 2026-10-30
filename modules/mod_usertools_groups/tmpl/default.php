<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var string                                                              $mode
 * @var bool                                                                $showCount
 * @var list<array{id: int, title: string, level: int, direct: bool}>|null  $groups
 * @var string|null                                                         $group
 * @var list<string>|null                                                   $members
 * @var int|null                                                            $total
 */
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="mod-usertools-groups">
<?php if ($mode === 'user') : ?>
  <?php if (!$groups) : ?>
  <p class="mb-0"><?= Text::_('MOD_USERTOOLS_GROUPS_NO_GROUPS') ?></p>
  <?php else : ?>
  <ul class="mod-list mb-0">
    <?php foreach ($groups as $item) : ?>
    <li<?= $item['direct'] ? '' : ' class="text-body-secondary"' ?>><?= $e($item['title']) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
<?php else : ?>
  <?php if (!$members) : ?>
  <p class="mb-0"><?= Text::sprintf('MOD_USERTOOLS_GROUPS_NO_MEMBERS', $e($group)) ?></p>
  <?php else : ?>
    <?php if ($showCount) : ?>
  <p class="mb-2"><?= Text::plural('MOD_USERTOOLS_GROUPS_N_MEMBERS', (int) $total, $e($group)) ?></p>
    <?php endif; ?>
  <ul class="mod-list mb-0">
    <?php foreach ($members as $member) : ?>
    <li><?= $e($member) ?></li>
    <?php endforeach; ?>
  </ul>
    <?php if ($total > count($members)) : ?>
  <p class="mt-2 mb-0 text-body-secondary"><?= Text::plural('MOD_USERTOOLS_GROUPS_N_MORE', (int) $total - count($members)) ?></p>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</div>
