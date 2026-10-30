<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel;
use Joomla\CMS\Language\Text;

/**
 * Writes to Joomla's User Actions Log (Users > User Actions Log). Logging must never stop an
 * operation, so failures are swallowed; the task reports keep their own record.
 */
final class ActionLogger
{
    public const CONTEXT = 'com_usertools.users';

    /** @var \Closure(list<array<string, mixed>>, string, string, int): void */
    private \Closure $writer;

    /** @param (callable(list<array<string, mixed>>, string, string, int): void)|null $writer */
    public function __construct(?callable $writer = null)
    {
        $this->writer = $writer !== null ? \Closure::fromCallable($writer) : static function (array $messages, string $key, string $context, int $userId): void {
            $component = Factory::getApplication()->bootComponent('com_actionlogs');
            if (!$component instanceof MVCFactoryServiceInterface) {
                return;
            }
            $model = $component->getMVCFactory()->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);
            if ($model instanceof ActionlogModel) {
                $model->addLog($messages, $key, $context, $userId);
            }
        };
    }

    public function blocked(object $account, Operator $operator, string $tool): void
    {
        $this->write($operator->id > 0 ? 'COM_USERTOOLS_ACTION_LOG_BLOCKED' : 'COM_USERTOOLS_ACTION_LOG_TASK_BLOCKED', 'block', $account, $operator, $tool);
    }

    /** The account no longer exists afterwards, so the message keeps its ID, username and name. */
    public function deleted(object $account, Operator $operator): void
    {
        $this->write('COM_USERTOOLS_ACTION_LOG_DELETED', 'delete', $account, $operator, 'COM_USERTOOLS_TOOL_DELETE', ['name' => (string) ($account->name ?? '')]);
    }

    /**
     * @param list<string> $removed titles of the groups removed
     */
    public function ungrouped(object $account, Operator $operator, array $removed, string $added): void
    {
        $this->write($added !== '' ? 'COM_USERTOOLS_ACTION_LOG_UNGROUPED_DEFAULT' : 'COM_USERTOOLS_ACTION_LOG_UNGROUPED', 'update', $account, $operator, 'COM_USERTOOLS_TOOL_UNGROUP',
            ['groups' => implode(', ', $removed), 'added' => $added]);
    }

    /** A group moved under another parent with "Move groups". */
    public function groupMoved(int $id, string $title, string $from, string $to, Operator $operator): void
    {
        $message = [
            'action'      => 'update',
            'type'        => 'COM_USERTOOLS_ACTION_LOG_TYPE_GROUP',
            'id'          => $id,
            'title'       => $title,
            'itemlink'    => 'index.php?option=com_users&task=group.edit&id=' . $id,
            'userid'      => $operator->id,
            'username'    => $operator->name,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $operator->id,
            'from'        => $from,
            'to'          => $to,
        ];
        try {
            ($this->writer)([$message], 'COM_USERTOOLS_ACTION_LOG_GROUP_MOVED', self::CONTEXT, $operator->id);
        } catch (\Throwable) {
        }
    }

    /** The account's name, username and email no longer exist, so the message keeps only its ID. */
    public function anonymized(int $id, Operator $operator): void
    {
        $message = [
            'action'      => 'update',
            'type'        => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER',
            'id'          => $id,
            'title'       => 'ID ' . $id,
            'itemlink'    => 'index.php?option=com_users&task=user.edit&id=' . $id,
            'userid'      => $operator->id,
            'username'    => $operator->name,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $operator->id,
        ];
        try {
            ($this->writer)([$message], 'COM_USERTOOLS_ACTION_LOG_ANONYMIZED', self::CONTEXT, $operator->id);
        } catch (\Throwable) {
        }
    }

    /** Records successful auxiliary-data cleanup for an already anonymized account. */
    public function cleanupCompleted(int $id, Operator $operator): void
    {
        $message = [
            'action' => 'update',
            'type' => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER',
            'id' => $id,
            'title' => 'ID ' . $id,
            'itemlink' => 'index.php?option=com_users&task=user.edit&id=' . $id,
            'userid' => $operator->id,
            'username' => $operator->name,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $operator->id,
        ];
        try {
            ($this->writer)([$message], 'COM_USERTOOLS_ACTION_LOG_CLEANUP_COMPLETED', self::CONTEXT, $operator->id);
        } catch (\Throwable) {
        }
    }

    /** A successful additive group assignment; saves only the group ID in the audit trail. */
    public function groupAdded(object $account, Operator $operator, int $group): void
    {
        $this->write('COM_USERTOOLS_ACTION_LOG_GROUP_ADDED', 'update', $account, $operator,
            '', ['group' => '#' . $group]);
    }

    public function created(object $account, Operator $operator, string $mode): void
    {
        $this->write($mode === Registration::MODE_PASSWORD ? 'COM_USERTOOLS_ACTION_LOG_CREATED_PASSWORD' : 'COM_USERTOOLS_ACTION_LOG_CREATED_EMAIL', 'create', $account, $operator, '');
    }

    /** An account unblocked with "Undo blocks"; $batch says which run or review had blocked it. */
    public function unblocked(object $account, Operator $operator, string $batch): void
    {
        $this->write('COM_USERTOOLS_ACTION_LOG_UNBLOCKED', 'update', $account, $operator, '', ['batch' => $batch]);
    }

    /** @param array<string, string> $extra */
    private function write(string $key, string $action, object $account, Operator $operator, string $tool, array $extra = []): void
    {
        $message = [
            'action'      => $action,
            'type'        => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER',
            'id'          => (int) $account->id,
            'title'       => (string) $account->username,
            'itemlink'    => 'index.php?option=com_users&task=user.edit&id=' . (int) $account->id,
            'userid'      => $operator->id,
            'username'    => $operator->name,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $operator->id,
            'tool'        => $tool !== '' ? Text::_($tool) : '',
            'task'        => $operator->name,
        ] + $extra;
        try {
            ($this->writer)([$message], $key, self::CONTEXT, $operator->id);
        } catch (\Throwable) {
        }
    }
}
