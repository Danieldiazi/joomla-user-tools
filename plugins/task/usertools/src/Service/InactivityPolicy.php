<?php
namespace Danieldiazi\Plugin\Task\Usertools\Service;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy as SharedPolicy;
/** Compatibility wrapper; manual and scheduled checks use the same policy. */
final class InactivityPolicy
{
    public static function cutoff(mixed $days,\DateTimeImmutable $now): \DateTimeImmutable { return SharedPolicy::cutoff($days,$now); }
    public static function eligible(object $user,\DateTimeImmutable $cutoff,bool $includeNever): bool { return SharedPolicy::eligible($user,$cutoff,$includeNever); }
    public static function omissionReason(object $user,bool $includeNever): string { return SharedPolicy::omissionReason($user,$includeNever); }
}
