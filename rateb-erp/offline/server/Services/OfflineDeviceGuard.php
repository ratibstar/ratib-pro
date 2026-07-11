<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Models\OfflineDevice;

/**
 * Enterprise push device gate (Phase 7.1 / H-DEVICE-001).
 * ACTIVE (or trusted) devices only — pending / inactive / revoked / unknown rejected.
 * Phase P2: also deny trust_status revoked/lost/disabled and force_logout_at.
 */
final class OfflineDeviceGuard
{
    /**
     * @return array{ok: bool, device_id?: string, status?: string|null, error?: string}
     */
    public function assertActive(int $companyId, string $deviceId): array
    {
        $deviceId = trim($deviceId);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        if ($deviceId === '') {
            return ['ok' => false, 'device_id' => '', 'error' => 'device_unknown'];
        }
        if (!OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
            return ['ok' => false, 'device_id' => $deviceId, 'error' => 'device_unknown'];
        }

        $row = (new OfflineDevice())->findByDeviceId($companyId, $deviceId);
        if ($row === null) {
            return ['ok' => false, 'device_id' => $deviceId, 'status' => null, 'error' => 'device_unknown'];
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $statusOk = $status === OfflineDevice::STATUS_ACTIVE
            || $status === OfflineDevice::STATUS_TRUSTED;
        if (!$statusOk) {
            return [
                'ok' => false,
                'device_id' => $deviceId,
                'status' => $status !== '' ? $status : 'unknown',
                'error' => 'device_denied',
            ];
        }

        if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
            $trust = strtolower(trim((string) ($row['trust_status'] ?? '')));
            if ($trust === '' || $trust === 'active') {
                $trust = OfflineDevice::TRUST_TRUSTED;
            }
            if ($trust === OfflineDevice::TRUST_REVOKED) {
                return [
                    'ok' => false,
                    'device_id' => $deviceId,
                    'status' => $status,
                    'error' => 'device_revoked',
                ];
            }
            if ($trust === OfflineDevice::TRUST_LOST) {
                return [
                    'ok' => false,
                    'device_id' => $deviceId,
                    'status' => $status,
                    'error' => 'device_lost',
                ];
            }
            if ($trust === OfflineDevice::TRUST_DISABLED) {
                return [
                    'ok' => false,
                    'device_id' => $deviceId,
                    'status' => $status,
                    'error' => 'device_disabled',
                ];
            }
        }

        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $forceLogout = $row['force_logout_at'] ?? null;
            if ($forceLogout !== null && trim((string) $forceLogout) !== '') {
                return [
                    'ok' => false,
                    'device_id' => $deviceId,
                    'status' => $status,
                    'error' => 'device_force_logout',
                ];
            }
        }

        return [
            'ok' => true,
            'device_id' => $deviceId,
            'status' => $status === OfflineDevice::STATUS_TRUSTED
                ? OfflineDevice::STATUS_TRUSTED
                : OfflineDevice::STATUS_ACTIVE,
        ];
    }
}
