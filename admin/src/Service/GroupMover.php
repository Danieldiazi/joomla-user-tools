<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\Table\Usergroup;
use Joomla\Component\Users\Administrator\Model\GroupModel;
use Joomla\Database\DatabaseInterface;

/**
 * Moves one group under another parent through Joomla's own group model, the one behind Users > Groups,
 * so its checks run, the nested tree is rebuilt and the user plugins receive onUserBeforeSaveGroup and
 * onUserAfterSaveGroup. The group is reloaded first: if its parent is no longer the reviewed one, it is
 * left alone. Each move is written to the User Actions Log.
 */
final class GroupMover
{
    public const MOVED   = 'moved';
    public const CHANGED = 'changed';
    public const ERROR   = 'error';

    /** @var \Closure(array<string, mixed>): bool */
    private \Closure $save;

    /** @param (callable(array<string, mixed>): bool)|null $save */
    public function __construct(private DatabaseInterface $db, private ActionLogger $logger, ?callable $save = null)
    {
        $this->save = $save !== null ? \Closure::fromCallable($save) : static function (array $data): bool {
            $component = Factory::getApplication()->bootComponent('com_users');
            if (!$component instanceof MVCFactoryServiceInterface) {
                return false;
            }
            $model = $component->getMVCFactory()->createModel('Group', 'Administrator', ['ignore_request' => true]);
            return $model instanceof GroupModel && $model->save($data);
        };
    }

    /** @return string one of MOVED, CHANGED, ERROR */
    public function move(int $id, int $from, int $to, Operator $operator, string $fromTitle, string $toTitle): string
    {
        $table = new Usergroup($this->db);
        if (!$table->load($id) || (int) $table->parent_id !== $from) {
            return self::CHANGED;
        }
        try {
            $saved = ($this->save)(['id' => $id, 'parent_id' => $to, 'title' => (string) $table->title]);
        } catch (\Throwable) {
            $saved = false;
        }
        // Joomla keeps the group paths and authorised levels in static caches.
        Access::clearStatics();
        if (!$saved) {
            return self::ERROR;
        }
        $this->logger->groupMoved($id, (string) $table->title, $fromTitle, $toTitle, $operator);
        return self::MOVED;
    }
}
