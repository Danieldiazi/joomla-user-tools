<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Danieldiazi\Component\Usertools\Administrator\View\Anonymize\HtmlView $this */
$layouts = JPATH_ADMINISTRATOR . '/components/com_usertools/layouts';
$job     = $this->job;
$done    = (int) $job['total'] - count($job['pending']);
?>
<div class="ut-app ut-narrow">
  <?= LayoutHelper::render('usertools.steps', ['steps' => $this::STEPS, 'current' => 'result', 'label' => 'COM_USERTOOLS_ANONYMIZE_PROGRESS'], $layouts) ?>
  <h2 class="h3"><?= Text::_('COM_USERTOOLS_ANONYMIZE_PROGRESS_HEADING') ?></h2>
  <p data-ut-anonymize-progress="<?= $done ?>/<?= (int) $job['total'] ?>"><?= Text::sprintf('COM_USERTOOLS_ANONYMIZE_PROGRESS_TEXT', $done, (int) $job['total']) ?></p>
  <progress class="w-100 mb-3" max="<?= (int) $job['total'] ?>" value="<?= $done ?>"><?= $done ?> / <?= (int) $job['total'] ?></progress>
  <p class="form-text"><?= Text::_('COM_USERTOOLS_ANONYMIZE_PROGRESS_HELP') ?></p>
  <form method="post" action="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" data-ut-anonymize-next>
    <div class="ut-actions">
      <button class="btn btn-primary" name="task" value="anonymize.next"><?= Text::_('COM_USERTOOLS_ANONYMIZE_CONTINUE') ?></button>
    </div>
    <?= HTMLHelper::_('form.token') ?>
  </form>
</div>
