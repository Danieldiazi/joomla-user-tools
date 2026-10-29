<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Which accounts the anonymization offers: a pasted list of usernames or emails, every inactive account,
 * or the accounts of one group (optionally with its subgroups). Pure logic: the caller passes the
 * accounts and the group tree. Protected and already anonymized accounts are reported, never offered.
 */
final class AnonymizeSelection
{
    public const SOURCE_LIST     = 'list';
    public const SOURCE_INACTIVE = 'inactive';
    public const SOURCE_GROUP    = 'group';
    public const SOURCES         = [self::SOURCE_LIST, self::SOURCE_INACTIVE, self::SOURCE_GROUP];

    public const REASON_DONE      = 'COM_USERTOOLS_ANONYMIZE_REASON_DONE';
    public const REASON_NOT_FOUND = 'COM_USERTOOLS_ANONYMIZE_REASON_NOT_FOUND';

    /** The email Joomla's "Privacy - User Accounts" plugin leaves on an anonymized account. */
    public static function isAnonymized(object $user): bool
    {
        return (bool) preg_match('/^UserID\d+removed@email\.invalid$/i', (string) $user->email);
    }

    /**
     * @param  iterable<UserRecord>                                                                               $users
     * @param  array{source: string, names: list<string>, cutoff: ?\DateTimeImmutable, includeNever: bool, group: int, subgroups: bool} $criteria
     * @param  array<int, object>                                                                                 $groups UserDirectory::groups()
     * @return array{candidates: array<int, UserRecord>, skipped: list<array{label: string, reason: string}>}
     */
    public static function select(iterable $users, array $criteria, Exclusions $exclusions, Operator $operator, array $groups): array
    {
        $matched = [];
        $skipped = [];
        if ($criteria['source'] === self::SOURCE_LIST) {
            $byName = [];
            foreach ($users as $user) {
                $byName[Comparison::normalise($user->username)] = $user;
                $byName[Comparison::normalise($user->email)]    = $user;
            }
            foreach ($criteria['names'] as $name) {
                $user = $byName[Comparison::normalise($name)] ?? null;
                if ($user === null) {
                    $skipped[] = ['label' => $name, 'reason' => self::REASON_NOT_FOUND];
                } else {
                    $matched[$user->id] = $user;
                }
            }
        } elseif ($criteria['source'] === self::SOURCE_INACTIVE && $criteria['cutoff'] !== null) {
            foreach ($users as $user) {
                if (InactivityPolicy::eligible($user, $criteria['cutoff'], $criteria['includeNever'], true)) {
                    $matched[$user->id] = $user;
                }
            }
        } elseif ($criteria['source'] === self::SOURCE_GROUP && isset($groups[$criteria['group']])) {
            $wanted = array_flip($criteria['subgroups'] ? GroupRemoval::subtree($groups, $criteria['group']) : [$criteria['group']]);
            foreach ($users as $user) {
                if (array_intersect_key(array_flip($user->groups), $wanted)) {
                    $matched[$user->id] = $user;
                }
            }
        }
        $candidates = [];
        foreach ($matched as $id => $user) {
            $done   = self::isAnonymized($user);
            $reason = $done ? self::REASON_DONE : $exclusions->reason($user, $operator);
            if ($reason !== '') {
                // An anonymized account's username is random: its ID says more.
                $skipped[] = ['label' => $done ? 'ID ' . $user->id : $user->username, 'reason' => $reason];
            } else {
                $candidates[$id] = $user;
            }
        }
        ksort($candidates);
        return ['candidates' => $candidates, 'skipped' => $skipped];
    }

    /**
     * What must not change between the review and the anonymization of an account. It works on a bulk
     * UserRecord and on a freshly loaded Joomla User alike, so the check right before writing is the same.
     */
    public static function fingerprint(object $user): string
    {
        $groups = array_values(array_unique(array_map('intval', (array) $user->groups)));
        sort($groups);
        $visit = $user->lastvisitDate === null || $user->lastvisitDate === '' ? null : substr((string) $user->lastvisitDate, 0, 19);
        return hash('sha256', json_encode([(string) $user->username, (string) $user->email, (int) $user->block, $visit, $groups], JSON_THROW_ON_ERROR));
    }
}
