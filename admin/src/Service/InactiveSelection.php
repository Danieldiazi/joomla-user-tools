<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/** Decodes the accounts ticked in the manual inactivity review. */
final class InactiveSelection
{
    /**
     * @param  list<int> $allowed IDs offered in the review
     * @return list<int>
     */
    public static function decode(string $json, array $allowed): array
    {
        if (\strlen($json) > Comparison::MAX_INPUT_BYTES) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_SELECTION_TOO_LARGE');
        }
        try {
            $values = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_SELECTION_UNREADABLE');
        }
        if (!\is_array($values) || !array_is_list($values)) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_SELECTION_INVALID');
        }
        $ids = [];
        foreach ($values as $id) {
            if (!\is_int($id) || $id < 1 || !\in_array($id, $allowed, true)) {
                throw new UserFacingException('COM_USERTOOLS_ERROR_SELECTION_FOREIGN');
            }
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int> $selected
     * @param  list<int> $preview
     * @param  list<int> $fresh
     * @return list<int>
     */
    public static function eligibleIds(array $selected, array $preview, array $fresh): array
    {
        return array_values(array_intersect($selected, $preview, $fresh));
    }
}
