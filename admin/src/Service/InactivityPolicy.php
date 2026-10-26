<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Shared by the manual review and the scheduled task. All dates are UTC, as stored by Joomla. */
final class InactivityPolicy
{
    public const MIN_DAYS = 1;
    public const MAX_DAYS = 36500;

    public static function cutoff(mixed $days, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $days = filter_var($days, FILTER_VALIDATE_INT);
        if ($days === false || $days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_DAYS', [self::MIN_DAYS, self::MAX_DAYS]);
        }
        return $now->setTimezone(new \DateTimeZone('UTC'))->sub(new \DateInterval('P' . $days . 'D'));
    }

    /** Blocked accounts are only eligible when $allowBlocked is set (account deletion). */
    public static function eligible(object $user, \DateTimeImmutable $cutoff, bool $includeNever, bool $allowBlocked = false): bool
    {
        if (empty($user->id) || (!$allowBlocked && (int) $user->block !== 0)) {
            return false;
        }
        $date = self::reference($user, $includeNever);
        return $date !== null && $date < $cutoff;
    }

    /** Account status filter of the deletion tool: 'all', 'blocked' or 'active'. */
    public static function matchesStatus(object $user, string $status): bool
    {
        return match ($status) {
            'blocked' => (int) $user->block === 1,
            'active'  => (int) $user->block === 0,
            default   => true,
        };
    }

    /** Language key explaining why an active account is not a candidate. */
    public static function omissionReason(object $user, bool $includeNever): string
    {
        $never = self::never($user);
        if ($never && !$includeNever) {
            return 'COM_USERTOOLS_REASON_NEVER_VISITED';
        }
        if (self::reference($user, $includeNever) === null) {
            return 'COM_USERTOOLS_REASON_INVALID_DATE';
        }
        return $never ? 'COM_USERTOOLS_REASON_RECENT_REGISTRATION' : 'COM_USERTOOLS_REASON_RECENT_VISIT';
    }

    public static function never(object $user): bool
    {
        $last = $user->lastvisitDate ?? null;
        return $last === null || $last === '' || str_starts_with((string) $last, '0000-00-00');
    }

    private static function reference(object $user, bool $includeNever): ?\DateTimeImmutable
    {
        if (self::never($user)) {
            if (!$includeNever) {
                return null;
            }
            $value = (string) ($user->registerDate ?? '');
        } else {
            $value = (string) $user->lastvisitDate;
        }
        $date   = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }
        return $date;
    }
}
