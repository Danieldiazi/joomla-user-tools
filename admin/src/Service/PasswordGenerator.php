<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Random passwords that satisfy the password policy of com_users (length and minimum number of
 * digits, symbols, upper and lower case letters). Every character comes from random_int().
 */
final class PasswordGenerator
{
    private const LOWER   = 'abcdefghijkmnopqrstuvwxyz';
    private const UPPER   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGITS  = '23456789';
    private const SYMBOLS = '!#$%&*+-=?@^_~';
    private const MIN_LENGTH = 24;
    private const MAX_LENGTH = 128;

    /** @param array<string, mixed> $policy com_users parameters */
    public function __construct(private array $policy = [])
    {
    }

    public function generate(): string
    {
        $required = [
            self::DIGITS  => max(1, (int) ($this->policy['minimum_integers'] ?? 0)),
            self::SYMBOLS => max(1, (int) ($this->policy['minimum_symbols'] ?? 0)),
            self::UPPER   => max(1, (int) ($this->policy['minimum_uppercase'] ?? 0)),
            self::LOWER   => max(1, (int) ($this->policy['minimum_lowercase'] ?? 0)),
        ];
        $length = max(self::MIN_LENGTH, (int) ($this->policy['minimum_length'] ?? 0), array_sum($required));
        $length = min(self::MAX_LENGTH, $length);
        $chars  = [];
        foreach ($required as $alphabet => $count) {
            for ($i = 0; $i < $count; $i++) {
                $chars[] = $this->pick($alphabet);
            }
        }
        $all = self::LOWER . self::UPPER . self::DIGITS . self::SYMBOLS;
        while (\count($chars) < $length) {
            $chars[] = $this->pick($all);
        }
        // Fisher-Yates shuffle so the required characters do not sit at fixed positions.
        for ($i = \count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', \array_slice($chars, 0, max($length, array_sum($required))));
    }

    private function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, \strlen($alphabet) - 1)];
    }
}
