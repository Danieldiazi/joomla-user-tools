<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Settings;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\Service\UserRecord;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Manual inactivity review: search, select and suspend. */
class InactiveModel extends BaseDatabaseModel
{
    use ToolStateTrait;

    private const DRAFT   = 'com_usertools.inactive.draft';
    private const PREVIEW = 'com_usertools.inactive.preview';
    private const RESULT  = 'com_usertools.inactive.result';

    /** @return array{days: int, includeNever: bool, exclusions: Exclusions} */
    public function getDraft(): array
    {
        $settings = Toolkit::settings();
        $draft    = (array) $this->session()->get(self::DRAFT, []);
        return [
            'days'         => (int) ($draft['days'] ?? $settings->inactiveDays()),
            'includeNever' => (bool) ($draft['includeNever'] ?? $settings->inactiveIncludeNever()),
            'exclusions'   => isset($draft['exclusions']) ? Exclusions::fromArray($draft['exclusions']) : $settings->exclusions(Settings::INACTIVE),
        ];
    }

    /** @param array<string, mixed> $input */
    public function search(array $input): void
    {
        $days         = (int) ($input['days'] ?? 365);
        $includeNever = (int) ($input['include_never'] ?? 0) === 1;
        $exclusions   = $this->exclusionsFromInput($input);
        $this->session()->set(self::DRAFT, ['days' => $days, 'includeNever' => $includeNever, 'exclusions' => $exclusions->toArray()]);
        $cutoff = InactivityPolicy::cutoff($days, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        Toolkit::settingsStore()->save($exclusions->toArray(Settings::INACTIVE)
            + [Settings::INACTIVE . 'days' => $days, Settings::INACTIVE . 'include_never' => (int) $includeNever]);
        $previous   = (array) $this->session()->get(self::PREVIEW, []);
        $candidates = $this->candidates($exclusions, $cutoff, $includeNever);
        $this->session()->set(self::PREVIEW, $this->newPreview([
            'cutoff'       => $cutoff->format('Y-m-d H:i:s'),
            'includeNever' => $includeNever,
            'exclusions'   => $exclusions->toArray(),
            'signature'    => $this->signature($exclusions, [$days, $includeNever]),
            'ids'          => array_map(fn (UserRecord $u) => $u->id, $candidates),
            'selected'     => array_values(array_intersect((array) ($previous['selected'] ?? []), array_map(fn (UserRecord $u) => $u->id, $candidates))),
        ]));
    }

    /** @return array<string, mixed>|null */
    public function getPreview(): ?array
    {
        $preview = $this->validPreview(self::PREVIEW);
        $settings = Toolkit::settings();
        $current  = $this->signature($settings->exclusions(Settings::INACTIVE), [$settings->inactiveDays(), $settings->inactiveIncludeNever()]);
        if ($preview && ($preview['signature'] ?? '') !== $current) {
            $this->session()->set(self::PREVIEW, []);
            return null;
        }
        return $preview;
    }

    /** @return array{candidates: list<UserRecord>, cutoff: string, selected: list<int>, threshold: int, nonce: string} */
    public function getReview(): array
    {
        $preview = $this->getPreview();
        if (!$preview) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_PREVIEW_EXPIRED');
        }
        $cutoff  = new \DateTimeImmutable($preview['cutoff'], new \DateTimeZone('UTC'));
        $offered = array_fill_keys($preview['ids'], true);
        $fresh   = $this->candidates(Exclusions::fromArray($preview['exclusions']), $cutoff, (bool) $preview['includeNever']);
        return [
            'candidates' => array_values(array_filter($fresh, fn (UserRecord $u) => isset($offered[$u->id]))),
            'cutoff'     => $preview['cutoff'],
            'selected'   => array_map('intval', (arra…87962 tokens truncated…Text::_('COM_USERTOOLS_DELETE_HOME_WARNING') ?></p>
        <a class="btn btn-sm btn-outline-danger" href="<?= Route::_('index.php?option=com_usertools&view=delete') ?>" aria-describedby="ut-card-delete"><?= Text::_('COM_USERTOOLS_DELETE_START') ?></a>
      </div>
    </article>
    </div>
    <?php endif; ?>
    <?php if ($this->can('usertools.anonymize')) : ?>
    <div class="col">
    <article class="card border-danger h-100" data-ut-home-card="anonymize">
      <div class="card-body">
        <div class="ut-card-head">
          <h3 class="card-title ut-card-title" id="ut-card-anonymize"><span class="icon-eye-slash" aria-hidden="true"></span> <?= Text::_('COM_USERTOOLS_ANONYMIZE_TITLE') ?></h3>
          <span class="badge text-bg-danger"><?= Text::_('COM_USERTOOLS_PERMANENT') ?></span>
        </div>
        <p class="card-text"><?= Text::_('COM_USERTOOLS_ANONYMIZE_HOME_DESC') ?></p>
        <p class="card-text fw-semibold ut-home-warning"><?= Text::_('COM_USERTOOLS_ANONYMIZE_HOME_WARNING') ?></p>
        <a class="btn btn-sm btn-outline-danger" href="<?= Route::_('index.php?option=com_usertools&view=anonymize') ?>" aria-describedby="ut-card-anonymize"><?= Text::_('COM_USERTOOLS_ANONYMIZE_START') ?></a>
      </div>
    </article>
    </div>
    <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
