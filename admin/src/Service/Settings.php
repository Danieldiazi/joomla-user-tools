<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Component options. Every value is declared in config.xml, so saving the Options screen keeps
 * the exclusions saved from the tools. Keys from 2026.10.2 and earlier are still read.
 */
final class Settings
{
    public const COMPARE  = 'compare_';
    public const INACTIVE = 'inactive_';
    public const DELETE   = 'delete_';

    /** Which accounts the deletion tool lists: only blocked (default), active and blocked, or only active. */
    public const STATUSES = ['blocked', 'all', 'active'];
    public const DEFAULT_STATUS = 'blocked';

    /** Well below PHP's usual 30 s limit and proxy timeouts, including slow mail servers. */
    public const BATCH_SECONDS = 8;

    /** @param array<string, mixed> $params */
    public function __construct(private array $params)
    {
    }

    public function exclusions(string $tool): Exclusions
    {
        return Exclusions::fromArray($this->toolValues($tool), $tool);
    }

    public function inactiveDays(): int
    {
        $days = (int) ($this->toolValues(self::INACTIVE)[self::INACTIVE . 'days'] ?? 365);
        return max(InactivityPolicy::MIN_DAYS, min(InactivityPolicy::MAX_DAYS, $days ?: 365));
    }

    /** Deletion is irreversible, so its default period is longer than the inactivity review's. */
    public function deleteDays(): int
    {
        $days = (int) ($this->toolValues(self::DELETE)[self::DELETE . 'days'] ?? 730);
        return max(InactivityPolicy::MIN_DAYS, min(InactivityPolicy::MAX_DAYS, $days ?: 730));
    }

    public function deleteIncludeNever(): bool
    {
        return (int) ($this->toolValues(self::DELETE)[self::DELETE . 'include_never'] ?? 0) === 1;
    }

    public function deleteStatus(): string
    {
        $status = (string) ($this->toolValues(self::DELETE)[self::DELETE . 'status'] ?? self::DEFAULT_STATUS);
        return \in_array($status, self::STATUSES, true) ? $status : self::DEFAULT_STATUS;
    }

    public function inactiveIncludeNever(): bool
    {
        return (int) ($this->toolValues(self::INACTIVE)[self::INACTIVE . 'include_never'] ?? 0) === 1;
    }

    /** Manual blocks from this number of accounts on require typing the number. 0 disables it. */
    public function confirmThreshold(): int
    {
        return max(0, (int) ($this->params['confirm_threshold'] ?? 10));
    }

    /**
     * Seconds of work per request when applying a comparison; the rest continues in the next request.
     * Not shown in Options (hidden field): 0 processes one account per request, which the tests use.
     */
    public function batchSeconds(): int
    {
        return max(0, min(60, (int) ($this->params['batch_seconds'] ?? self::BATCH_SECONDS)));
    }

    /** Days without signing in after which the access diagnosis reports an account with administrator access. */
    public function diagnoseDays(): int
    {
        $days = (int) ($this->params['diagnose_admin_days'] ?? AccessHealth::DEFAULT_ADMIN_DAYS);
        return max(InactivityPolicy::MIN_DAYS, min(InactivityPolicy::MAX_DAYS, $days ?: AccessHealth::DEFAULT_ADMIN_DAYS));
    }

    public function creationMode(): string
    {
        return Registration::mode($this->params['creation_mode'] ?? Registration::MODE_EMAIL);
    }

    public function retentionDays(): int
    {
        return max(0, (int) ($this->params['report_retention_days'] ?? 90));
    }

    /** @return array<string, mixed> */
    private function toolValues(string $tool): array
    {
        $values = [];
        foreach (['protect_admins', 'excluded_names', 'excluded_groups', 'days', 'include_never', 'status'] as $key) {
            if (\array_key_exists($tool . $key, $this->params)) {
                $values[$tool . $key] = $this->params[$tool . $key];
            }
        }
        if ($values) {
            return $values;
        }
        if ($tool === self::DELETE) {
            return [];
        }
        // Legacy layout: compare settings at the top level, inactivity settings in "manual_inactivity".
        $legacy = $tool === self::COMPARE ? $this->params : (array) ($this->params['manual_inactivity'] ?? []);
        foreach ($legacy as $key => $value) {
            if (\in_array($key, ['protect_admins', 'excluded_names', 'excluded_groups', 'days', 'include_never'], true)) {
                $values[$tool . $key] = \is_object($value) ? (array) $value : $value;
            }
        }
        return $values;
    }

    /**
     * Values in the current layout, used by the installer to migrate older options.
     *
     * @return array<string, mixed>
     */
    public function migrated(): array
    {
        $out = [];
        foreach ([self::COMPARE, self::INACTIVE] as $tool) {
            $out += $this->exclusions($tool)->toArray($tool);
        }
        $out[self::INACTIVE . 'days']          = $this->inactiveDays();
        $out[self::INACTIVE . 'include_never'] = (int) $this->inactiveIncludeNever();
        return $out;
    }
}
