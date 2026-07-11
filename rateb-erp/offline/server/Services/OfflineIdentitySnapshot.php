<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;

/**
 * Cold offline identity — session RBAC/permission snapshot for sealed claims.
 * Fail-closed: does not invent permissions; reuses Phase 12 RBAC services.
 */
final class OfflineIdentitySnapshot
{
    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   snapshot?: array<string, mixed>
     * }
     */
    public function buildForSession(): array
    {
        if (!class_exists(ErpOfflineRbacManifestService::class)
            || !class_exists(ErpOfflineRbacVersionService::class)) {
            return ['ok' => false, 'error' => 'rbac_unavailable'];
        }

        $built = (new ErpOfflineRbacManifestService())->buildForSession();
        if (!($built['ok'] ?? false) || !is_array($built['manifest'] ?? null)) {
            return ['ok' => false, 'error' => (string) ($built['error'] ?? 'rbac_denied')];
        }

        $manifest = $built['manifest'];
        $companyId = (int) ($manifest['company_id'] ?? 0);
        $branchId = (int) ($manifest['branch_id'] ?? 0);
        $userId = (int) ($manifest['user_id'] ?? 0);
        if ($companyId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'online_session_required'];
        }

        $permissionSlugs = is_array($manifest['permission_slugs'] ?? null)
            ? array_values(array_map('strval', $manifest['permission_slugs']))
            : [];
        $planModules = is_array($manifest['plan_modules'] ?? null)
            ? array_values(array_map('strval', $manifest['plan_modules']))
            : [];

        $fp = (new ErpOfflineRbacVersionService())->fingerprint($companyId, $userId, $branchId);
        $roles = $this->rolesFromSessionOrFingerprint($fp);

        $userUuid = '';
        $sessionUuid = SessionManager::get('rateb_user_uuid')
            ?? SessionManager::get('user_uuid')
            ?? null;
        if (is_string($sessionUuid) && trim($sessionUuid) !== '') {
            $userUuid = trim($sessionUuid);
        } else {
            $userUuid = (string) $userId;
        }

        $snapshot = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'user_uuid' => $userUuid,
            'device_uuid' => '',
            'roles' => $roles,
            'permissions' => $permissionSlugs,
            'plan_modules' => $planModules,
            'offline_policy' => [
                'ui_only' => true,
                'server_authz_bypass' => false,
                'cold_capable' => true,
            ],
            'captured_at' => time(),
        ];

        $locale = SessionManager::get('rateb_locale') ?? SessionManager::get('locale');
        if (is_string($locale) && trim($locale) !== '') {
            $snapshot['locale'] = trim($locale);
        }
        $theme = SessionManager::get('rateb_theme') ?? SessionManager::get('theme');
        if (is_string($theme) && trim($theme) !== '') {
            $snapshot['theme'] = trim($theme);
        }

        return ['ok' => true, 'snapshot' => $snapshot];
    }

    /**
     * @param array{role_ids?: list<int>} $fp
     * @return list<int|string>
     */
    private function rolesFromSessionOrFingerprint(array $fp): array
    {
        $fromSession = SessionManager::get('rateb_roles');
        if (is_array($fromSession) && $fromSession !== []) {
            $out = [];
            foreach ($fromSession as $role) {
                if (is_int($role) || is_string($role)) {
                    $out[] = $role;
                } elseif (is_array($role) && isset($role['id'])) {
                    $out[] = (int) $role['id'];
                } elseif (is_array($role) && isset($role['slug'])) {
                    $out[] = (string) $role['slug'];
                }
            }

            return array_values($out);
        }

        $roleIds = is_array($fp['role_ids'] ?? null) ? $fp['role_ids'] : [];

        return array_values(array_map('intval', $roleIds));
    }
}
