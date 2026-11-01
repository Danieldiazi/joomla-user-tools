<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\BlockBatch;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Session\SessionInterface;
use Joomla\CMS\User\User;

/**
 * Common session lifecycle for manual operations:
 * - Drafts preserve operator input and have no separate preview expiry.
 * - Previews contain the tool's reviewed selection plus nonce, owner and expiry. A tool checks
 *   its nonce and any business signature before consuming it, prior to the first account write.
 * - Resumable jobs (when supported) have their own owner and checkpoint/queue contract.
 * - Results expire after 900 seconds; they are not an audit history or a transaction log.
 *
 * Tool-specific payloads are documented in the owning model. This trait never authorizes
 * writes: controllers and services must revalidate permissions and account state themselves.
 */
trait ToolStateTrait
{
    private int $lifetime = 900;

    protected function session(): SessionInterface
    {
        return Toolkit::app()->getSession();
    }

    protected function identity(): User
    {
        return Toolkit::app()->getIdentity() ?? throw new \LogicException('No user.');
    }

    protected function operator(): Operator
    {
        return Operator::fromUser($this->identity());
    }

    /**
     * Add an unpredictable one-use token, the current operator ID and a Unix expiry.
     * Payload fields are defined by the tool; the caller must check the nonce and consume
     * the preview before writing. The array union intentionally cannot overwrite metadata
     * when the payload happens to carry a metadata key.
     *
     * @param array<string, mixed> $values Tool-specific reviewed fields.
     * @return array<string, mixed> Payload with nonce (hex string), actor (user ID), expires (Unix seconds).
     */
    protected function newPreview(array $values): array
    {
        return $values + [
            'nonce'   => bin2hex(random_bytes(24)),
            'expires' => time() + $this->lifetime,
            'actor'   => (int) $this->identity()->id,
        ];
    }

    /**
     * Validate expiry and owner. An invalid preview is cleared from session; this is not
     * a substitute for verifying the submitted nonce or re-checking the reviewed accounts.
     *
     * @return array<string, mixed>|null The original tool payload, or null when unavailable.
     */
    protected function validPreview(string $key): ?array
    {
        $preview = (array) $this->session()->get($key, []);
        if (!$preview || ($preview['expires'] ?? 0) < time() || ($preview['actor'] ?? 0) !== (int) $this->identity()->id) {
            if ($preview) {
                $this->session()->set($key, []);
            }
            return null;
        }
        return $preview;
    }

    /**
     * Store a short-lived, operator-owned display result, NOT a persistent audit record.
     * Remove temporary passwords from created rows before any session write; only the
     * immediate HTTP response from the creating request may show those credentials.
     *
     * @param array<string, mixed> $result Tool-specific result rows and counters.
     */
    protected function storeResult(string $key, array $result): void
    {
        foreach ($result['created'] ?? [] as $i => $row) {
            unset($result['created'][$i]['password']);
        }
        $this->session()->set($key, $result + ['actor' => (int) $this->identity()->id, 'expires' => time() + $this->lifetime]);
    }

    /**
     * Results may outlive the job that produced them, but expire after 900 seconds.
     * They carry actor and expires metadata and must never be treated as rollback data.
     *
     * @return array<string, mixed>|null Tool-specific result or null if expired/wrong operator.
     */
    protected function validResult(string $key): ?array
    {
        $result = (array) $this->session()->get($key, []);
        if (!$result || ($result['actor'] ?? 0) !== (int) $this->identity()->id || ($result['expires'] ?? 0) < time()) {
            return null;
        }
        return $result;
    }

    /** @param array<mixed> $extra */
    protected function signature(Exclusions $exclusions, array $extra = []): string
    {
        return hash('sha256', json_encode([$exclusions->toArray(), $extra], JSON_THROW_ON_ERROR));
    }

    /**
     * Reinforced confirmation: from the configured number of accounts on, the operator types the number.
     *
     * @param array<string, mixed> $input
     */
    protected function checkTypedCount(int $count, array $input, ?int $threshold = null): void
    {
        $threshold ??= Toolkit::settings()->confirmThreshold();
        if ($threshold > 0 && $count >= $threshold && (string) ($input['confirm_count'] ?? '') !== (string) $count) {
            throw new UserFacingException('COM_USERTOOLS_ERROR_CONFIRM_COUNT', [$count]);
        }
    }

    /**
     * @param array<string, mixed>                                  $result
     * @param array{status: string, username: string, reason: string} $outcome
     */
    protected function collect(array &$result, array $outcome, int $id): void
    {
        if ($outcome['status'] === 'blocked') {
            $result['blocked'][] = ['id' => $id, 'username' => $outcome['username']];
        } elseif ($outcome['status'] === 'error') {
            $result['errors'][] = ['username' => $outcome['username'], 'message' => $outcome['reason']];
        }
    }

    /**
     * Saves the accounts a manual review has just blocked, so "Undo blocks" can offer them later.
     * Never stops the operation: without a batch, the accounts can still be unblocked in Users.
     *
     * @param  list<array{id: int, username: string}> $blocked
     * @return string the batch reference, or '' if nothing was saved
     */
    protected function recordBatch(string $tool, string $reason, array $blocked): string
    {
        if (!$blocked) {
            return '';
        }
        try {
            $operator = $this->operator();
            $records  = Toolkit::directory()->users(array_map(static fn (array $row): int => (int) $row['id'], $blocked));
            $users    = [];
            foreach ($blocked as $row) {
                if (isset($records[(int) $row['id']])) {
                    $users[] = BlockBatch::row($records[(int) $row['id']], $reason);
                }
            }
            $store = Toolkit::batches();
            $id    = $store->start([
                'version'       => 1,
                'kind'          => BlockBatch::KIND_MANUAL,
                'tool'          => $tool,
                'operator_id'   => $operator->id,
                'operator_name' => $operator->name,
                'started_at'    => gmdate('Y-m-d H:i:s'),
                'blocked'       => \count($users),
                'users'         => $users,
            ]);
            $store->prune(Toolkit::settings()->retentionDays());
            return BlockBatch::reference(BlockBatch::KIND_MANUAL, $id);
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array<string, mixed> $input */
    protected function exclusionsFromInput(array $input): Exclusions
    {
        return Exclusions::fromArray([
            'protect_admins'  => (int) ($input['protect_admins'] ?? 1),
            'excluded_names'  => (string) ($input['excluded'] ?? ''),
            'excluded_groups' => (array) ($input['excluded_groups'] ?? []),
        ]);
    }

    /**
     * The core user plugin would email the generated password; this tool sends its own link instead.
     *
     * @return array{0: CMSPlugin, 1: mixed} the plugin and its setting, to restore afterwards
     */
    protected function silenceCoreMail(): array
    {
        PluginHelper::importPlugin('user');
        $core = Toolkit::app()->bootPlugin('joomla', 'user');
        if (!$core instanceof CMSPlugin) {
            throw new \LogicException('The core user plugin is not available.');
        }
        $before = $core->params->get('mail_to_user', 1);
        $core->params->set('mail_to_user', 0);
        return [$core, $before];
    }
}
