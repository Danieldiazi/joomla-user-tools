<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * A validation problem that is shown to the operator. It carries a language key and
 * its sprintf arguments so the domain services stay independent from translations.
 */
final class UserFacingException extends \InvalidArgumentException
{
    /** @param list<string|int> $arguments */
    public function __construct(private string $key, private array $arguments = [])
    {
        parent::__construct($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return list<string|int> */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
