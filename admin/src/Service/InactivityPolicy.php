<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;

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

    public static function omissionReason(object $user, bool $includeNever): string
    {
        $last = $user->lastvisitDate ?? '';
        $never = !$last || str_starts_with((string) $last, '0000-00-00');
        if ($never && !$includeNever) { return 'Nunca ha accedido y esta opción está desactivada.'; }
        $value = $never ? ($user->registerDate ?? '') : $last;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return 'No tiene una fecha válida para calcular la inactividad.';
        }
        return $never ? 'Nunca ha accedido, pero su registro todavía está dentro del plazo.' : 'Su último acceso está dentro del plazo.';
    }
}
