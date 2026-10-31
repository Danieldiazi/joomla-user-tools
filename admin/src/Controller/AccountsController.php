<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/**
 * Account suggestions for the shared account selector (GET, JSON, read only). Each tool asks for
 * its own suggestions and needs that tool's permission, the form token and at least two characters;
 * the response is never cached. Suggestions carry the account ID the form sends back.
 */
class AccountsController extends DisplayController
{
    /** Tools with a single-account selector, and the permissions each one needs besides its own view. */
    public const TOOLS = ['profile' => [], 'ungroup' => [], 'explain' => ['usertools.profile']];

    public function suggest(): void
    {
        $tool     = $this->input->getCmd('tool', '');
        $identity = $this->app->getIdentity();
        $allowed  = isset(self::TOOLS[$tool]) && $this->checkToken('get', false) && $identity
            && self::allowed($identity, self::PERMISSIONS[$tool]);
        foreach (self::TOOLS[$tool] ?? [] as $permission) {
            $allowed = $allowed && self::allowed($identity, $permission);
        }
        if (!$allowed) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $term = AccountIdentity::searchTerm($this->input->getString('q', ''));
        $rows = $term === null ? [] : Toolkit::directory()->search($term, AccountIdentity::SUGGESTIONS + 1);
        // "Remove from groups" never works on the operator's own account, so it is not offered there.
        if ($tool === 'ungroup') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] !== (int) $identity->id));
        }
        $suggestions = [];
        foreach (\array_slice($rows, 0, AccountIdentity::SUGGESTIONS) as $row) {
            $suggestions[] = ['id' => $row['id'], 'label' => Text::sprintf('COM_USERTOOLS_ACCOUNT_LABEL', ...AccountIdentity::labelParts($row)), 'blocked' => $row['blocked']];
        }
        $app = Toolkit::app();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->sendHeaders();
        echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
