<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * POS sync push acknowledgement — ok only when accepted+duplicate > 0.
 * Client may clear only clearable_keys (accepted ∪ duplicate).
 */
final class PosPushAckContract
{
    /**
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
