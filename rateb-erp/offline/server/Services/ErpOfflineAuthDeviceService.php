<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;

/**
 * Phase 11 — ERP shell device register/heartbeat (additive).
 * Uses rateb_offline_devices; does not modify POS device services.
 */
final class ErpOfflineAuthDeviceService
{
    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string}
     */
    public function register(int $companyId, int $userId, int $branchId, array $input): array
    {
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($policy['error'] ?? 'denied'), 'code' => 'POLICY'];
        }

        $deviceId = $this->normalizeDeviceId((string) ($input['device_id'] ?? ''));
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required', 'code' => 'DEVICE_ID_REQUIRED'];
        }

        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $existing = $model->findByDeviceId($companyId, $deviceId);
        $now = date('Y-m-d H:i:s');
        $label = trim((string) ($input['label'] ?? 'ERP shell'));
        if (mb_strlen($label) > 150) {
            $label = mb_substr($label, 0, 150);
        }
        $meta = json_encode([
            'purpose' => 'erp_shell_auth',
            'ua' => substr((string) ($input['ua'] ?? ''), 0, 250),
        ], JSON_UNESCAPED_UNICODE);

        if ($existing === null) {
            $rowId = $model->create([
                'company_id' => $companyId,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'device_id' => $deviceId,
                'user_id' => $userId,
                'label' => $label,
                'meta_json' => $meta !== false ? $meta : null,
                'last_seen_at' => $now,
                'status' => OfflineDevice::STATUS_PENDING,
            ]);
            $device = $model->find($rowId);

            return ['ok' => true, 'device' => $this->publicDevice($device ?? []), 'created' => true];
        }

        $id = (int) ($existing['id'] ?? 0);
        $patch = [
            'user_id' => $userId,
            'last_seen_at' => $now,
            'label' => $label,
        ];
        if ($branchId > 0) {
            $patch['branch_id'] = $branchId;
        }
        if ($meta !== false) {
            $patch['meta_json'] = $meta;
        }
        // Never auto-activate; keep revoked as revoked.
        $status = strtolower(trim((string) ($existing['status'] ?? '')));
        if ($status === OfflineDevice::STATUS_REVOKED) {
            return [
                'ok' => false,
                'error' => 'device_revoked',
                'code' => 'REVOKED',
                'device' => $this->publicDevice($existing),
            ];
        }
        $model->update($id, $patch);
        $device = $model->find($id);

        return ['ok' => true, 'device' => $this->publicDevice($device ?? $existing), 'created' => false];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string}
     */
    public function heartbeat(int $companyId, int $userId, array $input): array
    {
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($policy['error'] ?? 'denied'), 'code' => 'POLICY'];
        }

        $deviceId = $this->normalizeDeviceId((string) ($input['device_id'] ?? ''));
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required', 'code' => 'DEVICE_ID_REQUIRED'];
        }

        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $existing = $model->findByDeviceId($companyId, $deviceId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'device_unknown', 'code' => 'UNKNOWN'];
        }

        $id = (int) ($existing['id'] ?? 0);
        $model->update($id, [
            'last_seen_at' => date('Y-m-d H:i:s'),
            'user_id' => $userId > 0 ? $userId : ($existing['user_id'] ?? null),
        ]);
        $device = $model->find($id) ?? $existing;

        return ['ok' => true, 'device' => $this->publicDevice($device)];
    }

    private function normalizeDeviceId(string $raw): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($raw)) ?? '';

        return substr($id, 0, 64);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicDevice(array $row): array
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));

        return [
            'device_id' => (string) ($row['device_id'] ?? ''),
            'status' => $status,
            'is_active' => $status === OfflineDevice::STATUS_ACTIVE,
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
        ];
    }
}
