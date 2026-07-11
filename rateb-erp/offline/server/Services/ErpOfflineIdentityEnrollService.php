<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;

/**
 * Phase P1 — Online enroll of warm offline identity + device activation.
 * Additive; does not create PHP sessions.
 */
final class ErpOfflineIdentityEnrollService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function enroll(int $companyId, int $userId, int $branchId, array $input): array
    {
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($policy['error'] ?? 'denied'), 'code' => 'POLICY'];
        }

        $deviceId = trim((string) ($input['device_id'] ?? ''));
        $deviceSvc = new ErpOfflineAuthDeviceService();
        $reg = $deviceSvc->register($companyId, $userId, $branchId, [
            'device_id' => $deviceId,
            'label' => (string) ($input['label'] ?? 'ERP shell'),
            'ua' => (string) ($input['ua'] ?? ''),
        ]);
        if (!($reg['ok'] ?? false)) {
            return $reg;
        }

        $activated = $this->activateErpShellDevice($companyId, $deviceId, $userId, $branchId);
        if (!($activated['ok'] ?? false)) {
            return $activated;
        }

        $issued = (new ErpOfflineIdentityService())->issue(
            $companyId,
            $branchId,
            $userId,
            $deviceId
        );
        if (!($issued['ok'] ?? false)) {
            return $issued;
        }

        return [
            'ok' => true,
            'device' => $activated['device'] ?? ($reg['device'] ?? null),
            'identity' => $issued['identity'] ?? null,
            'logout_destroys_identity' => true,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, device?: array<string, mixed>}
     */
    private function activateErpShellDevice(int $companyId, string $deviceId, int $userId, int $branchId): array
    {
        $deviceId = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($deviceId)) ?? '';
        $deviceId = substr($deviceId, 0, 64);
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required'];
        }

        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $existing = $model->findByDeviceId($companyId, $deviceId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'device_unknown'];
        }

        $status = strtolower(trim((string) ($existing['status'] ?? '')));
        if ($status === OfflineDevice::STATUS_REVOKED) {
            return ['ok' => false, 'error' => 'device_revoked'];
        }

        $existingUser = (int) ($existing['user_id'] ?? 0);
        if ($existingUser > 0 && $existingUser !== $userId) {
            return ['ok' => false, 'error' => 'device_user_mismatch'];
        }

        $id = (int) ($existing['id'] ?? 0);
        $patch = [
            'status' => OfflineDevice::STATUS_ACTIVE,
            'user_id' => $userId,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ];
        if ($branchId > 0) {
            $patch['branch_id'] = $branchId;
        }
        $model->update($id, $patch);
        $device = $model->find($id) ?? $existing;
        $statusOut = strtolower(trim((string) ($device['status'] ?? OfflineDevice::STATUS_ACTIVE)));

        return [
            'ok' => true,
            'device' => [
                'device_id' => (string) ($device['device_id'] ?? $deviceId),
                'status' => $statusOut,
                'is_active' => $statusOut === OfflineDevice::STATUS_ACTIVE,
                'company_id' => (int) ($device['company_id'] ?? $companyId),
                'branch_id' => (int) ($device['branch_id'] ?? $branchId),
                'user_id' => (int) ($device['user_id'] ?? $userId),
                'label' => (string) ($device['label'] ?? ''),
                'last_seen_at' => (string) ($device['last_seen_at'] ?? ''),
            ],
        ];
    }
}
