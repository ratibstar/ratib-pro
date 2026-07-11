<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase P2 — online renew of warm offline identity for a trusted device.
 */
final class ErpOfflineIdentityRenewService
{
    /**
     * @return array<string, mixed>
     */
    public function renew(int $companyId, int $userId, int $branchId, string $deviceId): array
    {
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($policy['error'] ?? 'denied'), 'code' => 'POLICY'];
        }

        $deviceId = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($deviceId)) ?? '';
        $deviceId = substr($deviceId, 0, 64);
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required', 'code' => 'DEVICE_ID_REQUIRED'];
        }

        $trust = new DeviceTrustService();
        if (!$trust->isReplayAllowed($companyId, $deviceId)) {
            return ['ok' => false, 'error' => 'device_revoked', 'code' => 'DEVICE_REVOKED'];
        }

        $cold = new OfflineColdIdentityService();
        if ($cold->isEnabled()) {
            $issued = $cold->renewColdPackage(
                $companyId,
                $branchId,
                $userId,
                $deviceId
            );
        } else {
            $issued = (new ErpOfflineIdentityService())->renew(
                $companyId,
                $branchId,
                $userId,
                $deviceId
            );
        }
        if (!($issued['ok'] ?? false)) {
            return $issued;
        }

        $claims = is_array($issued['identity']['claims'] ?? null)
            ? $issued['identity']['claims']
            : [];

        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_IDENTITY_RENEWED,
            $companyId,
            [
                'branch_id' => $branchId,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'detail' => [
                    'jti' => (string) ($claims['jti'] ?? ''),
                    'identity_version' => (int) ($claims['identity_version'] ?? 0),
                    'expires_at' => (int) ($claims['expires_at'] ?? 0),
                ],
            ]
        );

        return [
            'ok' => true,
            'identity' => $issued['identity'] ?? null,
            'logout_destroys_identity' => true,
            'session_policy' => (new ErpOfflineIdentitySessionPolicy())->snapshot(),
        ];
    }
}
