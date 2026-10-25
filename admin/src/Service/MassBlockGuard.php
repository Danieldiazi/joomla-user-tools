<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Safety net for scheduled tasks: if a run would block more than the configured share of the
 * active accounts, nothing is blocked. It protects against misconfiguration or stale access dates.
 */
final class MassBlockGuard
{
    public const DEFAULT_PERCENT = 20;
    public const DEFAULT_MINIMUM = 10;

    public function __construct(public readonly int $percent = self::DEFAULT_PERCENT, public readonly int $minimum = self::DEFAULT_MINIMUM)
    {
    }

    /** @param array<string, mixed> $params task parameters */
    public static function fromParams(array $params): self
    {
        $percent = filter_var($params['guard_percent'] ?? self::DEFAULT_PERCENT, FILTER_VALIDATE_INT);
        $minimum = filter_var($params['guard_minimum'] ?? self::DEFAULT_MINIMUM, FILTER_VALIDATE_INT);
        return new self(
            $percent === false ? self::DEFAULT_PERCENT : max(0, min(100, $percent)),
            $minimum === false ? self::DEFAULT_MINIMUM : max(1, $minimum),
        );
    }

    public function enabled(): bool
    {
        return $this->percent > 0 && $this->percent < 100;
    }

    /** True when the run must stop: at least $minimum candidates and more than $percent % of active accounts. */
    public function exceeded(int $candidates, int $activeAccounts): bool
    {
        if (!$this->enabled() || $candidates < $this->minimum || $activeAccounts < 1) {
            return false;
        }
        return $candidates * 100 > $this->percent * $activeAccounts;
    }

    public static function share(int $candidates, int $activeAccounts): float
    {
        return $activeAccounts > 0 ? round($candidates * 100 / $activeAccounts, 1) : 0.0;
    }
}
