<?php

namespace Danieldiazi\Component\Usertools\Administrator\Model;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\Session\SessionInterface;
use Joomla\CMS\User\User;

/** Session state shared by the manual tools: one-time previews and short-lived results. */
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
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected function newPreview(array $values): array
    {
        return $values + [
            'nonce'   => bin2hex(random_bytes(24)),
            'expires' => time() + $this->lifetime,
            'actor'   => (int) $this->identity()->id,
        ];
    }

    /** @return array<string, mixed>|null */
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

    /** @param array<string, mixed> $result */
    protected function storeResult(string $key, array $result): void
    {
        foreach ($result['created'] ?? [] as $i => $row) {
            unset($result['created'][$i]['password']);
        }
        $this->session()->set($key, $result + ['actor' => (int) $this->identity()->id, 'expires' => time() + $this->lifetime]);
    }

    /** @return array<string, mixed>|null */
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
    protected function checkTypedCount(int $count, array $input): void
    {
        $threshold = Toolkit::settings()->confirmThreshold();
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

    /** @param array<string, mixed> $input */
    protected function exclusionsFromInput(array $input): Exclusions
    {
        return Exclusions::fromArray([
            'protect_admins'  => (int) ($input['protect_admins'] ?? 1),
            'excluded_names'  => (string) ($input['excluded'] ?? ''),
            'excluded_groups' => (array) ($input['excluded_groups'] ?? []),
        ]);
    }
}
