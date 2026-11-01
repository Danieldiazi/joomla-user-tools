<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Privacy\CanRemoveDataEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Anonymizes one account the way a completed removal request in Users > Privacy does: Joomla's privacy
 * plugins are asked whether the data can be removed, then remove it. The core "Privacy - User Accounts"
 * plugin replaces the name, username and email, blocks the account and closes its sessions through the
 * User API; privacy plugins of other extensions clean their own data. No request is stored in Privacy:
 * nobody asked for it, the administrator did it, and the User Actions Log records it.
 *
 * Joomla's plugin leaves the profile fields, the user notes and the custom field values, so they are
 * removed here too. The account itself is kept, so content keeps its author ID.
 *
 * The outcome keeps apart what is verified from what is not: the Joomla identity (name, username, email
 * and block, checked on a freshly loaded account), the additional cleanup that this class does and then
 * checks source by source, and the privacy plugins that ran, whose effect cannot be verified because a
 * plugin returning without an error proves nothing. Content, messages, action logs, media, files,
 * backups and external data are outside its scope.
 */
final class AccountAnonymizer
{
    public const ANONYMIZED = 'anonymized';
    public const PARTIAL = 'partial';
    public const SKIPPED    = 'skipped';
    public const PROTECTED  = 'protected';
    public const REFUSED    = 'refused';
    public const ERROR      = 'error';

    public const VERIFIED = 'verified';
    public const PENDING = 'pending';

    /** Additional data removed and then checked here, source by source. */
    public const SOURCES = ['profile', 'notes', 'fields'];

    /** @var \Closure(User): array{0: bool, 1: string, 2?: list<string>} */
    private \Closure $remove;
    /** @var \Closure(int): array{deleted: int, remaining: array<string, int>} */
    private \Closure $cleanup;

    /**
     * @param (callable(User): array{0: bool, 1: string, 2?: list<string>})|null $remove asks the privacy plugins and removes;
     *        [removed, refusal, names of the privacy plugins that ran]
     * @param (callable(int): array{deleted: int, remaining: array<string, int>})|null $cleanup removes the additional data and
     *        counts what is left of each source
     */
    public function __construct(private UserFactoryInterface $users, private ?DatabaseInterface $db, private ActionLogger $log, ?callable $remove = null, ?callable $cleanup = null)
    {
        $this->cleanup = $cleanup !== null ? \Closure::fromCallable($cleanup) : $this->removeExtraData(...);
        $this->remove = $remove !== null ? \Closure::fromCallable($remove) : static function (User $user): array {
            $app        = Factory::getApplication();
            $dispatcher = $app->getDispatcher();
            $component  = $app->bootComponent('com_privacy');
            if (!$component instanceof MVCFactoryServiceInterface) {
                return [false, ''];
            }
            $request = $component->getMVCFactory()->createTable('Request', 'Administrator');
            if (!$request instanceof RequestTable) {
                return [false, ''];
            }
            // An unsaved removal request: the plugins need one, but nothing is stored in Privacy.
            $request->email        = (string) $user->email;
            $request->request_type = 'remove';
            $request->status       = 1;
            PluginHelper::importPlugin('privacy', null, true, $dispatcher);
            // Only the names of the enabled plugins are kept: never their messages, which may hold personal data.
            $plugins = array_values(array_map(static fn (object $plugin): string => (string) $plugin->name, PluginHelper::getPlugin('privacy')));
            $results = $dispatcher->dispatch('onPrivacyCanRemoveData', new CanRemoveDataEvent('onPrivacyCanRemoveData', [
                'subject' => $request,
                'user'    => $user,
            ]))->getArgument('result', []);
            foreach ((array) $results as $status) {
                if (\is_object($status) && empty($status->canRemove)) {
                    return [false, (string) ($status->reason ?? ''), $plugins];
                }
            }
            $dispatcher->dispatch('onPrivacyRemoveData', new RemoveDataEvent('onPrivacyRemoveData', ['subject' => $request, 'user' => $user]));
            return [true, '', $plugins];
        };
    }

    /**
     * @param  callable(object): bool $stillEligible revalidates the review on the fresh User
     * @return array{status: string, username: string, reason: string, newName?: string, newUsername?: string,
     *     identity?: string, cleanup?: array<string, string>, plugins?: list<string>}
     */
    public function anonymize(int $id, Exclusions $exclusions, Operator $operator, callable $stillEligible): array
    {
        $user = $this->users->loadUserById($id);
        $name = (string) $user->username;
        if (empty($user->id)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => 'COM_USERTOOLS_REASON_MISSING'];
        }
        // Exclusions include the operator and Super Users, which are always protected.
        $reason = $exclusions->reason($user, $operator);
        if ($reason !== '') {
            return ['status' => self::PROTECTED, 'username' => $name, 'reason' => $reason];
        }
        if (!$stillEligible($user)) {
            return ['status' => self::SKIPPED, 'username' => $name, 'reason' => 'COM_USERTOOLS_REASON_NO_LONGER_ELIGIBLE'];
        }
        $already = AnonymizeSelection::isAnonymized($user) && (int) $user->block === 1;
        $plugins = [];
        try {
            $answer = $already ? [true, ''] : ($this->remove)($user);
            [$removed, $refusal] = [(bool) $answer[0], (string) $answer[1]];
            $plugins = array_values(array_map('strval', (array) ($answer[2] ?? [])));
        } catch (\Throwable) {
            [$removed, $refusal] = [false, ''];
        }
        if (!$removed) {
            return $refusal !== ''
                ? ['status' => self::REFUSED, 'username' => $name, 'reason' => $refusal]
                : ['status' => self::ERROR, 'username' => $name, 'reason' => 'COM_USERTOOLS_ANONYMIZE_ERROR_FAILED'];
        }
        // Check the result on a fresh object: a disabled or replaced privacy plugin must not pass silently.
        $after = $this->users->loadUserById($id);
        if (!AnonymizeSelection::isAnonymized($after) || (int) $after->block !== 1) {
            return ['status' => self::ERROR, 'username' => $name, 'reason' => 'COM_USERTOOLS_ANONYMIZE_ERROR_FAILED'];
        }
        if (!$already) {
            $this->log->anonymized($id, $operator);
        }
        $done = ['username' => $name, 'newName' => (string) $after->name, 'newUsername' => (string) $after->username,
            'identity' => self::VERIFIED, 'plugins' => $plugins];
        $cleanup = array_fill_keys(self::SOURCES, self::PENDING);
        try {
            $result = ($this->cleanup)($id);
            foreach (self::SOURCES as $source) {
                // Verified only when the check after deleting found nothing left of that source.
                $cleanup[$source] = isset($result['remaining'][$source]) && (int) $result['remaining'][$source] === 0 ? self::VERIFIED : self::PENDING;
            }
            $deleted = (int) $result['deleted'];
        } catch (\Throwable) {
            $deleted = 0;
        }
        if (\in_array(self::PENDING, $cleanup, true)) {
            try {
                Log::add('Additional anonymization cleanup is pending for user ID ' . $id, Log::ERROR, 'com_usertools');
            } catch (\Throwable) {
                // The pending cleanup is still returned to the caller.
            }
            return ['status' => self::PARTIAL, 'reason' => 'COM_USERTOOLS_ANONYMIZE_CLEANUP_INCOMPLETE', 'cleanup' => $cleanup] + $done;
        }
        // A successful no-op retry must not create a misleading cleanup action log entry.
        if ($already && $deleted > 0) {
            $this->log->cleanupCompleted($id, $operator);
        }
        return ['status' => self::ANONYMIZED, 'reason' => '', 'cleanup' => $cleanup] + $done;
    }

    /**
     * Deletes the additional data and then counts what is left of each source, so that "verified" means
     * checked after deleting, not merely "no exception".
     *
     * @return array{deleted: int, remaining: array<string, int>}
     */
    private function removeExtraData(int $id): array
    {
        $db      = $this->db ?? throw new \LogicException('Database required for cleanup.');
        $deleted = 0;
        foreach (['#__user_profiles' => 'user_id', '#__user_notes' => 'user_id'] as $table => $column) {
            $db->setQuery($db->createQuery()->delete($db->quoteName($table))
                ->where($db->quoteName($column) . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->execute();
            $deleted += $db->getAffectedRows();
        }
        $item    = (string) $id;
        $context = 'com_users.user';
        $fields  = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context');
        $db->setQuery($db->createQuery()->delete($db->quoteName('#__fields_values'))
            ->where($db->quoteName('item_id') . ' = :item')
            ->where($db->quoteName('field_id') . ' IN (' . $fields . ')')
            ->bind(':item', $item)->bind(':context', $context))->execute();
        $deleted += $db->getAffectedRows();
        $remaining = [];
        foreach (['profile' => '#__user_profiles', 'notes' => '#__user_notes'] as $source => $table) {
            $remaining[$source] = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName($table))
                ->where($db->quoteName('user_id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();
        }
        $fields = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context');
        $remaining['fields'] = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__fields_values'))
            ->where($db->quoteName('item_id') . ' = :item')->where($db->quoteName('field_id') . ' IN (' . $fields . ')')
            ->bind(':item', $item)->bind(':context', $context))->loadResult();
        return ['deleted' => $deleted, 'remaining' => $remaining];
    }
}
