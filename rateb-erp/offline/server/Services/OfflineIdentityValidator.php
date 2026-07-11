<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Validates warm / cold offline identity packages.
 * Cold-capable claims require permissions + fail-closed offline_policy.
 */
final class OfflineIdentityValidator
{
    /**
     * @param array<string, mixed> $package
     * @param array{
     *   company_id?: int,
     *   branch_id?: int,
     *   user_id?: int,
     *   device_id?: string,
     *   identity_version?: int,
     *   previous_issued_at?: int,
     *   check_nonce?: bool
     * } $expect
     * @return array{ok: bool, error?: string, claims?: array<string, mixed>}
     */
    public function validate(array $package, array $expect = []): array
    {
        $verified = (new ErpOfflineIdentityService())->verifyPackage($package, $expect);
        if (!($verified['ok'] ?? false)) {
            return $verified;
        }

        $claims = is_array($verified['claims'] ?? null) ? $verified['claims'] : [];
        $coldCapable = !empty($claims['cold_capable']);
        if (!$coldCapable) {
            return $verified;
        }

        $permissions = $claims['permissions'] ?? null;
        if (!is_array($permissions) || !$this->isList($permissions)) {
            return ['ok' => false, 'error' => 'cold_permissions_required'];
        }

        $policy = is_array($claims['offline_policy'] ?? null) ? $claims['offline_policy'] : [];
        if (($policy['server_authz_bypass'] ?? null) !== false) {
            return ['ok' => false, 'error' => 'cold_policy_bypass_forbidden'];
        }

        if ((int) ($claims['company_id'] ?? 0) < 1
            || (int) ($claims['user_id'] ?? 0) < 1
            || trim((string) ($claims['device_id'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'cold_identity_scope_required'];
        }

        return $verified;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
