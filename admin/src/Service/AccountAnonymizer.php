<?php

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
 */
final class AccountAnonymizer
{
    public const ANONYMIZED = 'anonymized';
    public const PARTIAL = 'partial';
    public const SKIPPED    = 'skipped';
    public const PROTECTED  = 'protected';
    public const REFUSED    = 'refused';
    public const ERROR      = 'error';

    /** @var \Closure(User): array{0: bool, 1: string} */
    private \Closure $remove;
    /** @var \Closure(int): void */
    private \Closure $cleanup;

    /** @param (callable(User): array{0: bool, 1: string})|null $remove asks the privacy plugins and removes; [removed, refusal] */
    public function __construct(private UserFactoryInterface $users, private DatabaseInterface $db, private ActionLogger $log, ?callable $remove = null, ?callable $cleanup = null)
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
            $results = $dispatcher->dispatch('onPrivacyCanRemoveData', new CanRemoveDataEvent('onPrivacyCanRemoveData', [
                'subject' => $request,
                'user'    => $user,
            ]))->getArgument('result', []);
            foreach ((array) $results as $status) {
                if (\is_object($status) && empty($status->canRemove)) {
                    return [false, (string) ($status->reason ?? '')];
                }
            }
            $dispatcher->dispatch('onPrivacyRemoveData', new RemoveDataEvent('onPrivacyRemoveData', ['subject' => $request, 'user' => $user]));
            return [true, ''];
        };
    }

    /**
     * @param  callable(object): bool $stillEligible revalidates the review on the fresh User
     * @return array{status: string, username: string, reason: string, newName?: string, newUsername?: string}
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
        try {
            [$removed, $refusal] = $already ? [true, ''] : ($this->remove)($user);
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
        try {
            ($this->cleanup)($id);
        } catch (\Throwable $e) {
            try {
                Log::add('Additional anonymization cleanup failed for user ID ' . $id, Log::ERROR, 'com_usertools');
            } catch (\Throwable) {
                // The cleanup failure is still returned to the caller.
            }
            return ['status' => self::PARTIAL, 'username' => $name, 'reason' => 'COM_USERTOOLS_ANONYMIZE_CLEANUP_INCOMPLETE', 'newName' => (string) $after->name, 'newUsername' => (string) $after->username];
        }
        return ['status' => self::ANONYMIZED, 'username' => $name, 'reason' => '', 'newName' => (string) $after->name, 'newUsername' => (string) $after->username];
    }

    /** Profile fields, user notes and custom field values of the account. */
    private function removeExtraData(int $id): void
    {
        $db = $this->db;
        foreach (['#__user_profiles' => 'user_id', '#__user_notes' => 'user_id'] as $table => $column) {
            $db->setQuery($db->createQuery()->delete($db->quoteName($table))
                ->where($db->quoteName($column) . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->execute();
        }
        $item    = (string) $id;
        $context = 'com_users.user';
        $fields  = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context');
        $db->setQuery($db->createQuery()->delete($db->quoteName('#__fields_values'))
            ->where($db->quoteName('item_id') . ' = :item')
            ->where($db->quoteName('field_id') . ' IN (' . $fields . ')')
            ->bind(':item', $item)->bind(':context', $context))->execute();
    }
}
