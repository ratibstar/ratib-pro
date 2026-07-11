<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Push acknowledgement contract — ok only when at least one item was accepted or duplicated.
 *
 * @phpstan-type PushResult array{
 *   accepted?: int,
 *   duplicate?: int,
 *   conflict?: int,
 *   rejected?: int,
 *   accepted_keys?: list<string>,
 *   duplicate_keys?: list<string>,
 *   conflict_keys?: list<string>,
 *   rejected_keys?: list<string>,
 *   errors?: array<string, mixed>
 * }
 */
final class OfflinePushAckContract
{
    /**
     * Keys the client may safely remove from its local queue.
     *
     * @param array<string, mixed> $result
     * @return list<string>
     */
    public function clearableKeys(array $result): array
    {
        $accepted = is_array($result['accepted_keys'] ?? null) ? $result['accepted_keys'] : [];
        $duplicate = is_array($result['duplicate_keys'] ?? null) ? $result['duplicate_keys'] : [];
        $out = [];
        foreach (array_merge($accepted, $duplicate) as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $out[$key] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param array<string, mixed> $result
     * @return array{ok: bool, http_status: int, clearable_keys: list<string>}
     */
    public function evaluate(array $result): array
    {
        $accepted = (int) ($result['accepted'] ?? 0);
        $duplicate = (int) ($result['duplicate'] ?? 0);
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];

        if (!empty($errors['offline_disabled'])) {
            return ['ok' => false, 'http_status' => 403, 'clearable_keys' => []];
        }
        if (!empty($errors['branch_denied'])) {
            return ['ok' => false, 'http_status' => 403, 'clearable_keys' => []];
        }
        if (!empty($errors['device_denied']) || !empty($errors['device_unknown'])) {
            return ['ok' => false, 'http_status' => 403, 'clearable_keys' => []];
        }
        if (!empty($errors['company_required'])) {
            return ['ok' => false, 'http_status' => 403, 'clearable_keys' => []];
        }
        if (!empty($errors['migration_required']) && ($accepted + $duplicate) < 1) {
            return ['ok' => false, 'http_status' => 503, 'clearable_keys' => []];
        }

        $ok = ($accepted + $duplicate) > 0;
        $clearable = $ok ? $this->clearableKeys($result) : [];

        return [
            'ok' => $ok,
            'http_status' => $ok ? 200 : 422,
            'clearable_keys' => $clearable,
        ];
    }
}
