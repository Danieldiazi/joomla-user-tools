<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Joomla guided tours (com_guidedtours) for the screens that change accounts or groups. The package
 * installer writes them from these definitions, and each screen offers its tour with a button that
 * only appears where the tool is allowed. Joomla can only restrict a tour by viewing level, so the
 * tours never start on their own and are not offered as context tours in Joomla's tours menu.
 *
 * Each step points to a stable `data-ut-tour` attribute of the screen. Texts are language keys in
 * one file per tour (`com_usertools.tour_<view>.ini`), the file Joomla loads for the tour's uid.
 */
final class GuidedTours
{
    public const UID_PREFIX = 'com_usertools.tour-';

    /** Not an extension: keeps the tours out of the "tours for this page" part of Joomla's menu. */
    public const EXTENSIONS = '["com_usertools.tours"]';

    /** After Joomla's own tours, so they never push them out of the short lists of Joomla's menu. */
    public const ORDERING = 9000;

    /** Joomla's "next" step type: the step only explains, it never clicks or submits anything. */
    public const STEP_NEXT = 0;

    /**
     * Tours by view: the steps in order, each with the `data-ut-tour` target and where the tip goes.
     *
     * @var array<string, array<string, string>>
     */
    public const TOURS = [
        'compare'   => ['steps' => 'bottom', 'list' => 'right', 'exclusions' => 'left', 'actions' => 'top'],
        'inactive'  => ['steps' => 'bottom', 'period' => 'right', 'exclusions' => 'left', 'actions' => 'top'],
        'unblock'   => ['steps' => 'bottom', 'rules' => 'bottom', 'batches' => 'top'],
        'delete'    => ['warning' => 'bottom', 'period' => 'right', 'exclusions' => 'left', 'actions' => 'top'],
        'anonymize' => ['warning' => 'bottom', 'source' => 'right', 'exclusions' => 'left', 'actions' => 'top'],
        'addgroups' => ['rules' => 'bottom', 'accounts' => 'top', 'actions' => 'top'],
        'ungroup'   => ['steps' => 'bottom', 'account' => 'bottom', 'actions' => 'top'],
        'move'      => ['rules' => 'bottom', 'groups' => 'top', 'target' => 'top', 'actions' => 'top'],
        'schedule'  => ['state' => 'bottom', 'instructions' => 'top'],
    ];

    public static function uid(string $view): string
    {
        return self::UID_PREFIX . $view;
    }

    /** The screen the tour explains, relative to the site root as Joomla stores it. */
    public static function url(string $view): string
    {
        return 'administrator/index.php?option=com_usertools&view=' . $view;
    }

    /** Language file Joomla loads for the tour, without the language folder. */
    public static function languageFile(string $view): string
    {
        return 'com_usertools.' . str_replace('-', '_', substr(self::uid($view), \strlen('com_usertools.'))) . '.ini';
    }

    public static function key(string $view, string $part): string
    {
        return 'COM_USERTOOLS_TOUR_' . strtoupper($view) . '_' . strtoupper($part);
    }

    public static function target(string $name): string
    {
        return '[data-ut-tour="' . $name . '"]';
    }

    /**
     * The tour row, without dates and authors.
     *
     * @return array{title: string, uid: string, description: string, extensions: string, url: string, ordering: int, autostart: int}
     */
    public static function tour(string $view, int $position): array
    {
        return [
            'title'       => self::key($view, 'TITLE'),
            'uid'         => self::uid($view),
            'description' => self::key($view, 'DESC'),
            'extensions'  => self::EXTENSIONS,
            'url'         => self::url($view),
            'ordering'    => self::ORDERING + $position,
            'autostart'   => 0,
        ];
    }

    /**
     * The steps of a tour, in order.
     *
     * @return list<array{title: string, description: string, ordering: int, position: string, target: string, type: int, url: string}>
     */
    public static function steps(string $view): array
    {
        $steps = [];
        foreach (self::TOURS[$view] as $name => $position) {
            $steps[] = [
                'title'       => self::key($view, $name . '_TITLE'),
                'description' => self::key($view, $name . '_DESC'),
                'ordering'    => \count($steps) + 1,
                'position'    => $position,
                'target'      => self::target($name),
                'type'        => self::STEP_NEXT,
                'url'         => self::url($view),
            ];
        }
        return $steps;
    }

    /**
     * ID of the published tour of a screen that the user may view, or 0.
     *
     * @param list<int> $levels the user's authorised viewing levels
     */
    public static function find(DatabaseInterface $db, string $view, array $levels): int
    {
        if (!isset(self::TOURS[$view]) || !$levels) {
            return 0;
        }
        $uid   = self::uid($view);
        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__guidedtours'))
            ->where($db->quoteName('uid') . ' = :uid')
            ->where($db->quoteName('published') . ' = 1')
            ->whereIn($db->quoteName('access'), array_values(array_map('intval', $levels)))
            ->bind(':uid', $uid, ParameterType::STRING);
        try {
            return (int) $db->setQuery($query, 0, 1)->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }
}
