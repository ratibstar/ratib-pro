<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Models\OfflineDevice;

/**
 * Enterprise push device gate (Phase 7.1 / H-DEVICE-001).
 * ACTIVE devices only — pending / inactive / revoked / unknown rejected.
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
        if ($status !== OfflineDevice::STATUS_ACTIVE) {
            return [
                'ok' => false,
                'device_id' => $deviceId,
                'status' => $status !== '' ? $status : 'unknown',
                'error' => 'device_denied',
            ];
        }

        return [
            'ok' => true,
            'device_id' => $deviceId,
            'status' => OfflineDevice::STATUS_ACTIVE,
        ];
    }
}
