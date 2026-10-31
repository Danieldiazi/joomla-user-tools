<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessExplanation;
use Danieldiazi\Component\Usertools\Administrator\Service\AccountIdentity;
use Danieldiazi\Component\Usertools\Administrator\Service\AccessExplanationReader;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;

/**
 * "Explain access": why one account can or cannot view, edit or publish one com_content article or
 * category. Read only. The operator needs this tool's permission, the account profile permission and
 * access to the content: the explanation never shows an item the operator could not see in Joomla.
 */
class ExplainModel extends BaseDatabaseModel
{
    /**
     * The account typed or chosen. Several matches are returned as candidates, never resolved to the first.
     *
     * @return array{status: string, id: int, candidates: list<array{id: int, username: string, name: string, email: string, blocked: bool}>}
     */
    public function account(string $login, int $id): array
    {
        $match = Toolkit::directory()->pick($login, $id);
        if ($match['status'] === AccountIdentity::NONE) {
            throw new UserFacingException('COM_USERTOOLS_PROFILE_NOT_FOUND');
        }
        return $match;
    }

    /** @return array<string, mixed> the explanation, plus the item it refers to */
    public function explain(int $account, string $type, int $item, string $action, string $context): array
    {
        AccessExplanation::validate($type, $action, $context);
        $operator = $this->operator();
        if (!$operator->authorise('core.manage', 'com_content')) {
            throw new UserFacingException('COM_USERTOOLS_EXPLAIN_ERROR_CONTENT');
        }
        $superUser = (bool) $operator->authorise('core.admin');
        $reader = new AccessExplanationReader(Toolkit::db(), Toolkit::users());
        $row = $reader->item($type, $item, $superUser ? null : array_values(array_map('intval', $operator->getAuthorisedViewLevels())));
        if ($row === null) {
            // Missing and hidden items give the same answer, so the screen never confirms that a hidden item exists.
            throw new UserFacingException('COM_USERTOOLS_EXPLAIN_ERROR_ITEM');
        }
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        // Where a denial is configured is shown only to whoever may see Joomla's permission settings.
        $facts = $reader->facts($account, $row, $superUser, $now);
        return AccessExplanation::explain($action, $context, $facts) + ['item' => $row, 'denials' => $facts['denials'], 'now' => $now];
    }

    private function operator(): User
    {
        return Toolkit::app()->getIdentity() ?? throw new \LogicException('No user.');
    }
}
