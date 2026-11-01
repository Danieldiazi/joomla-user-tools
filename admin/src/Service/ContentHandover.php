<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Pure rules of the handover assistant: who can leave content and who can receive it, which reviewed
 * articles can be transferred, and the fingerprint that tells whether an article changed since the
 * review. Only "created_by" changes; the visible author alias and the modification history are kept
 * as Joomla records them.
 */
final class ContentHandover
{
    /** The inventory lists up to this many articles; the rest are counted and can be handled in a later round. */
    public const LIMIT = 500;

    public const TRANSFERRED = 'transferred';
    public const CHANGED = 'changed';
    public const ERROR = 'error';

    /**
     * Super Users, accounts with administrator access (for anyone who is not a Super User) and the
     * operator's own account are protected by the closing tools, so the assistant does not start for them.
     */
    public static function sourceError(?UserRecord $source, Operator $operator): string
    {
        if ($source === null) {
            return 'COM_USERTOOLS_HANDOVER_ERROR_SOURCE_MISSING';
        }
        return (new Exclusions(false))->protects($source, $operator) ? 'COM_USERTOOLS_HANDOVER_ERROR_SOURCE_PROTECTED' : '';
    }

    /** The account receiving the content must be another existing, active account. */
    public static function targetError(UserRecord $source, ?UserRecord $target): string
    {
        return match (true) {
            $target === null => 'COM_USERTOOLS_HANDOVER_ERROR_TARGET_MISSING',
            $target->id === $source->id => 'COM_USERTOOLS_HANDOVER_ERROR_TARGET_SAME',
            $target->block !== 0 => 'COM_USERTOOLS_HANDOVER_ERROR_TARGET_BLOCKED',
            default => '',
        };
    }

    /**
     * What identifies the reviewed state of an article: if any of these change, it is skipped. Being checked
     * out is checked apart, so that opening it does not invalidate the review for the operator.
     *
     * @param array<string, mixed> $article id, created_by, modified, state, catid
     */
    public static function fingerprint(array $article): string
    {
        return hash('sha256', json_encode([(int) $article['id'], (int) $article['created_by'], (string) ($article['modified'] ?? ''),
            (int) $article['state'], (int) $article['catid']], JSON_THROW_ON_ERROR));
    }

    /**
     * The chosen articles, in inventory order. Each must be in the inventory and editable by the operator;
     * anything else means the screen is out of date.
     *
     * @param  list<array<string, mixed>> $inventory
     * @param  list<int>                  $ids
     * @return list<array<string, mixed>>
     */
    public static function selection(array $inventory, array $ids): array
    {
        $wanted = array_fill_keys(array_map('intval', $ids), true);
        if (!$wanted) {
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_NONE');
        }
        $chosen = [];
        foreach ($inventory as $article) {
            if (!isset($wanted[(int) $article['id']])) {
                continue;
            }
            if (empty($article['editable'])) {
                throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_SELECTION');
            }
            unset($wanted[(int) $article['id']]);
            $chosen[] = $article;
        }
        if ($wanted) {
            throw new UserFacingException('COM_USERTOOLS_HANDOVER_ERROR_SELECTION');
        }
        return $chosen;
    }

    /**
     * Articles the account still authored when the result is shown, split into those that were part of
     * the reviewed selection (not transferred) and new ones that appeared afterwards.
     *
     * @param  list<int> $remaining article IDs the source still authors now
     * @param  list<int> $inventory article IDs listed in the reviewed inventory
     * @param  list<int> $selected  article IDs chosen in the review
     * @return array{pending: list<int>, new: list<int>, left: list<int>}
     */
    public static function closing(array $remaining, array $inventory, array $selected): array
    {
        $inInventory = array_fill_keys($inventory, true);
        $chosen = array_fill_keys($selected, true);
        $result = ['pending' => [], 'new' => [], 'left' => []];
        foreach ($remaining as $id) {
            $key = isset($chosen[$id]) ? 'pending' : (isset($inInventory[$id]) ? 'left' : 'new');
            $result[$key][] = $id;
        }
        return $result;
    }
}
