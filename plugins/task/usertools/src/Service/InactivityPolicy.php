<?php
namespace Danieldiazi\Plugin\Task\Usertools\Service;

final class InactivityPolicy
{
    public static function cutoff(mixed $days, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $days = filter_var($days, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 36500) {
            throw new \InvalidArgumentException('El plazo debe estar entre 1 y 36500 días.');
        }
        return $now->setTimezone(new \DateTimeZone('UTC'))->sub(new \DateInterval('P' . $days . 'D'));
    }

    public static function eligible(object $user, \DateTimeImmutable $cutoff, bool $includeNever): bool
    {
        if (empty($user->id) || (int) $user->block !== 0) {
            return false;
        }
        $last = $user->lastvisitDate ?? '';
        if ($last === '' || $last === null || str_starts_with((string) $last, '0000-00-00')) {
            if (!$includeNever) {
                return false;
            }
            $last = $user->registerDate ?? '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $last, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return false;
        }
        return $date < $cutoff;
    }
}
