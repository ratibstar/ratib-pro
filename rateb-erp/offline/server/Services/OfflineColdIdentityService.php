<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Enterprise cold offline identity — sealed RBAC snapshot inside warm-purpose package.
 * Does not create PHP sessions. Does not bypass server authorization.
 */
final class OfflineColdIdentityService
{
    public function isEnabled(): bool
    {
        return (new OfflineFeatureFlagService())->isColdIdentityEnabled();
    }

    /**
     * @return array{ok: bool, error?: string, identity?: array<string, mixed>}
     */
    public function issueColdPackage(
        int $companyId,
        int $branchId,
        int $userId,
        string $deviceId,
        ?int $ttlSeconds = null
    ): array {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'cold_identity_disabled'];
        }

        $snap = (new OfflineIdentitySnapshot())->buildForSession();
        if (!($snap['ok'] ?? false) || !is_array($snap['snapshot'] ?? null)) {
            return ['ok' => false, 'error' => (string) ($snap['error'] ?? 'snapshot_failed')];
        }

        $snapshot = $snap['snapshot'];
        if ((int) ($snapshot['company_id'] ?? 0) !== $companyId
            || (int) ($snapshot['user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'snapshot_session_mismatch'];
        }

        $permissions = is_array($snapshot['permissions'] ?? null)
            ? array_values(array_map('strval', $snapshot['permissions']))
            : [];
        $roles = is_array($snapshot['roles'] ?? null)
            ? array_values($snapshot['roles'])
            : [];
        $planModules = is_array($snapshot['plan_modules'] ?? null)
            ? array_values(array_map('strval', $snapshot['plan_modules']))
            : [];
        $offlinePolicy = is_array($snapshot['offline_policy'] ?? null)
            ? $snapshot['offline_policy']
            : [
                'ui_only' => true,
                'server_authz_bypass' => false,
                'cold_capable' => true,
            ];

        $extraClaims = [
            'cold_capable' => true,
            'user_uuid' => (string) ($snapshot['user_uuid'] ?? (string) $userId),
            'roles' => $roles,
            'permissions' => $permissions,
            'plan_modules' => $planModules,
            'offline_policy' => $offlinePolicy,
        ];
        if (isset($snapshot['locale']) && is_string($snapshot['locale'])) {
            $extraClaims['locale'] = $snapshot['locale'];
        }
        if (isset($snapshot['theme']) && is_string($snapshot['theme'])) {
            $extraClaims['theme'] = $snapshot['theme'];
        }

        return (new ErpOfflineIdentityService())->issue(
            $companyId,
            $branchId,
            $userId,
            $deviceId,
            $ttlSeconds,
            1,
            $extraClaims
        );
    }

    /**
     * Renew cold package (online) — preserves RBAC snapshot claims.
     *
     * @return array{ok: bool, error?: string, identity?: array<string, mixed>}
     */
    public function renewColdPackage(
        int $companyId,
        int $branchId,
        int $userId,
        string $deviceId,
        ?array $previousClaims = null,
        ?int $ttlSeconds = null
    ): array {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'cold_identity_disabled'];
        }

        $snap = (new OfflineIdentitySnapshot())->buildForSession();
        if (!($snap['ok'] ?? false) || !is_array($snap['snapshot'] ?? null)) {
            return ['ok' => false, 'error' => (string) ($snap['error'] ?? 'snapshot_failed')];
        }

        $snapshot = $snap['snapshot'];
        if ((int) ($snapshot['company_id'] ?? 0) !== $companyId
            || (int) ($snapshot['user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'snapshot_session_mismatch'];
        }

        $extraClaims = [
            'cold_capable' => true,
            'user_uuid' => (string) ($snapshot['user_uuid'] ?? (string) $userId),
            'roles' => is_array($snapshot['roles'] ?? null) ? array_values($snapshot['roles']) : [],
            'permissions' => is_array($snapshot['permissions'] ?? null)
                ? array_values(array_map('strval', $snapshot['permissions']))
                : [],
            'plan_modules' => is_array($snapshot['plan_modules'] ?? null)
                ? array_values(array_map('strval', $snapshot['plan_modules']))
                : [],
            'offline_policy' => is_array($snapshot['offline_policy'] ?? null)
                ? $snapshot['offline_policy']
                : [
                    'ui_only' => true,
                    'server_authz_bypass' => false,
                    'cold_capable' => true,
                ],
        ];
        if (isset($snapshot['locale']) && is_string($snapshot['locale'])) {
            $extraClaims['locale'] = $snapshot['locale'];
        }
        if (isset($snapshot['theme']) && is_string($snapshot['theme'])) {
            $extraClaims['theme'] = $snapshot['theme'];
        }

        return (new ErpOfflineIdentityService())->renew(
            $companyId,
            $branchId,
            $userId,
            $deviceId,
            $previousClaims,
            $ttlSeconds,
            $extraClaims
        );
    }

    /**
     * @param array<string, mixed> $package
     * @param array<string, mixed> $expect
     * @return array{ok: bool, error?: string, claims?: array<string, mixed>}
     */
    public function validateColdPackage(array $package, array $expect = []): array
    {
        return (new OfflineIdentityValidator())->validate($package, $expect);
    }
}
