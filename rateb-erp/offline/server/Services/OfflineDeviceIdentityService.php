<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Thin device-identity helpers for cold unlock — delegates to DeviceTrustService.
 * No SQL duplication.
 */
final class OfflineDeviceIdentityService
{
    public function bindFingerprint(int $companyId, string $deviceId, string $fingerprint): bool
    {
        return (new DeviceTrustService())->updateFingerprint(
            $companyId,
            $this->normalizeDeviceId($deviceId),
            $fingerprint
        );
    }

    /**
     * Fail-closed trust gate for cold unlock (same surface as replay allow).
     *
     * @return array{ok: bool, error?: string, device_id?: string}
     */
    public function assertTrustedForCold(int $companyId, string $deviceId): array
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($companyId < 1 || $deviceId === '') {
            return ['ok' => false, 'error' => 'device_unknown', 'device_id' => $deviceId];
        }

        $trust = new DeviceTrustService();
        if (!$trust->isReplayAllowed($companyId, $deviceId)) {
            return ['ok' => false, 'error' => 'device_not_trusted', 'device_id' => $deviceId];
        }

        return ['ok' => true, 'device_id' => $deviceId];
    }

    public function normalizeDeviceId(string $raw): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($raw)) ?? '';

        return substr($id, 0, 64);
    }
}
