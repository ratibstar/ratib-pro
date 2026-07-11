<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosBranchBridgeService;

/** Company-scoped offline POS device register / heartbeat / activate / revoke. */
final class PosOfflineDeviceService
{
    public function __construct(
        private PosBranchBridgeService $branch = new PosBranchBridgeService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /**
     * Register or refresh a device as pending (or keep active if already approved).
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string}
     */
    public function register(int $companyId, int $userId, array $input): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => __('select_company_ops'), 'code' => 'NO_COMPANY'];
        }
        $deviceId = $this->normalizeDeviceId((string) ($input['device_id'] ?? ''));
        if ($deviceId === '') {
            return ['ok' => false, 'error' => __('invalid_request'), 'code' => 'DEVICE_ID_REQUIRED'];
        }

        $branchId = isset($input['branch_id']) ? (int) $input['branch_id'] : 0;
        if ($branchId < 1) {
            $branchId = (int) ((new PosSessionService())->current()['branch_id'] ?? 0);
        }
        if ($branchId > 0) {
            try {
                $this->branch->assertCanAccess($branchId);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'code' => 'BRANCH_DENIED'];
            }
        } else {
            $branchId = null;
        }

        $label = trim((string) ($input['label'] ?? ''));
        if (mb_strlen($label) > 150) {
            $label = mb_substr($label, 0, 150);
        }
        $metaJson = $this->encodeMeta($input['meta'] ?? $input['meta_json'] ?? null, $input);

        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $existing = $model->findByDeviceId($companyId, $deviceId);
        $now = date('Y-m-d H:i:s');

        if ($existing === null) {
            $rowId = $model->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'device_id' => $deviceId,
                'user_id' => $userId > 0 ? $userId : null,
                'label' => $label !== '' ? $label : null,
                'meta_json' => $metaJson,
                'last_seen_at' => $now,
                'status' => OfflineDevice::STATUS_PENDING,
            ]);
            $this->audit->log('pos_device_register', 'offline_device', $rowId, [
                'device_id' => $deviceId,
                'branch_id' => $branchId,
                'status' => OfflineDevice::STATUS_PENDING,
            ]);
            $device = $model->find($rowId);

            return [
                'ok' => true,
                'device' => $this->publicDevice($device ?? []),
                'created' => true,
            ];
        }

        $id = (int) ($existing['id'] ?? 0);
        $status = (string) ($existing['status'] ?? OfflineDevice::STATUS_PENDING);
        $patch = [
            'user_id' => $userId > 0 ? $userId : ($existing['user_id'] ?? null),
            'last_seen_at' => $now,
        ];
        if ($branchId !== null) {
            $patch['branch_id'] = $branchId;
        }
        if ($label !== '') {
            $patch['label'] = $label;
        }
        if ($metaJson !== null) {
            $patch['meta_json'] = $metaJson;
        }

        // Revoked / inactive devices must wait for admin again.
        if (in_array($status, [OfflineDevice::STATUS_REVOKED, OfflineDevice::STATUS_INACTIVE], true)) {
            $patch['status'] = OfflineDevice::STATUS_PENDING;
            $patch['activated_by'] = null;
            $patch['activated_at'] = null;
            $patch['approved_by'] = null;
            $status = OfflineDevice::STATUS_PENDING;
        } elseif ($status !== OfflineDevice::STATUS_ACTIVE) {
            $patch['status'] = OfflineDevice::STATUS_PENDING;
            $status = OfflineDevice::STATUS_PENDING;
        }

        $model->update($id, $patch);
        $this->audit->log('pos_device_register', 'offline_device', $id, [
            'device_id' => $deviceId,
            'branch_id' => $branchId,
            'status' => $status,
            'refreshed' => true,
        ]);
        $device = $model->find($id);

        return [
            'ok' => true,
            'device' => $this->publicDevice($device ?? $existing),
            'created' => false,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string}
     */
    public function heartbeat(int $companyId, int $userId, array $input): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => __('select_company_ops'), 'code' => 'NO_COMPANY'];
        }
        $deviceId = $this->normalizeDeviceId((string) ($input['device_id'] ?? ''));
        if ($deviceId === '') {
            return ['ok' => false, 'error' => __('invalid_request'), 'code' => 'DEVICE_ID_REQUIRED'];
        }

        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $existing = $model->findByDeviceId($companyId, $deviceId);
        if ($existing === null) {
            return ['ok' => false, 'error' => __('pos_device_not_found'), 'code' => 'NOT_FOUND'];
        }

        $id = (int) ($existing['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $patch = [
            'last_seen_at' => $now,
        ];
        if ($userId > 0) {
            $patch['user_id'] = $userId;
        }
        $metaJson = $this->encodeMeta($input['meta'] ?? $input['meta_json'] ?? null, $input);
        if ($metaJson !== null) {
            $patch['meta_json'] = $metaJson;
        }

        $model->update($id, $patch);
        $device = $model->find($id);

        return [
            'ok' => true,
            'device' => $this->publicDevice($device ?? $existing),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(int $companyId, ?int $branchId = null, ?string $status = null, int $limit = 200): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT d.*,
                       b.name AS branch_name,
                       b.name_ar AS branch_name_ar,
                       u.name AS user_display,
                       u.email AS user_email,
                       act.name AS activated_by_name,
                       appr.name AS approved_by_name
                FROM rateb_offline_devices d
                LEFT JOIN rateb_branches b ON b.id = d.branch_id AND b.company_id = d.company_id
                LEFT JOIN rateb_users u ON u.id = d.user_id
                LEFT JOIN rateb_users act ON act.id = d.activated_by
                LEFT JOIN rateb_users appr ON appr.id = d.approved_by
                WHERE d.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND d.branch_id = :bid';
            $params['bid'] = $branchId;
        }
        if ($status !== null && $status !== '' && in_array($status, [
            OfflineDevice::STATUS_PENDING,
            OfflineDevice::STATUS_ACTIVE,
            OfflineDevice::STATUS_INACTIVE,
            OfflineDevice::STATUS_REVOKED,
        ], true)) {
            $sql .= ' AND d.status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY
            CASE d.status
                WHEN \'pending\' THEN 0
                WHEN \'active\' THEN 1
                WHEN \'inactive\' THEN 2
                ELSE 3
            END,
            d.last_seen_at DESC,
            d.id DESC
            LIMIT ' . $limit;

        $rows = (new OfflineDevice())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->adminDevice($row);
        }

        return $out;
    }

    /** @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string} */
    public function activate(int $id, int $companyId, int $adminUserId): array
    {
        if ($id < 1 || $companyId < 1 || $adminUserId < 1) {
            return ['ok' => false, 'error' => __('invalid_request'), 'code' => 'INVALID'];
        }
        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $row = $model->find($id);
        if ($row === null || (int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => __('pos_device_not_found'), 'code' => 'NOT_FOUND'];
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === OfflineDevice::STATUS_ACTIVE) {
            return ['ok' => true, 'device' => $this->adminDevice($row)];
        }
        if ($status === OfflineDevice::STATUS_REVOKED) {
            // Allow re-activate from revoked after admin review.
        }

        $now = date('Y-m-d H:i:s');
        $model->update($id, [
            'status' => OfflineDevice::STATUS_ACTIVE,
            'activated_by' => $adminUserId,
            'activated_at' => $now,
            'approved_by' => $adminUserId,
        ]);
        $this->audit->log('pos_device_activate', 'offline_device', $id, [
            'device_id' => (string) ($row['device_id'] ?? ''),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
        ]);
        $fresh = $model->find($id);

        return ['ok' => true, 'device' => $this->adminDevice($fresh ?? $row)];
    }

    /** @return array{ok: bool, device?: array<string, mixed>, error?: string, code?: string} */
    public function revoke(int $id, int $companyId, int $adminUserId): array
    {
        if ($id < 1 || $companyId < 1 || $adminUserId < 1) {
            return ['ok' => false, 'error' => __('invalid_request'), 'code' => 'INVALID'];
        }
        TenantContext::setCompanyId($companyId);
        $model = new OfflineDevice();
        $row = $model->find($id);
        if ($row === null || (int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => __('pos_device_not_found'), 'code' => 'NOT_FOUND'];
        }
        if ((string) ($row['status'] ?? '') === OfflineDevice::STATUS_REVOKED) {
            return ['ok' => true, 'device' => $this->adminDevice($row)];
        }

        $model->update($id, [
            'status' => OfflineDevice::STATUS_REVOKED,
        ]);
        $this->audit->log('pos_device_revoke', 'offline_device', $id, [
            'device_id' => (string) ($row['device_id'] ?? ''),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'revoked_by' => $adminUserId,
        ]);
        $fresh = $model->find($id);

        return ['ok' => true, 'device' => $this->adminDevice($fresh ?? $row)];
    }

    /** @return list<array{value: int, label: string}> */
    public function branchFilterOptions(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new OfflineDevice())->query(
            'SELECT DISTINCT d.branch_id, b.name, b.name_ar, b.code
             FROM rateb_offline_devices d
             LEFT JOIN rateb_branches b ON b.id = d.branch_id AND b.company_id = d.company_id
             WHERE d.company_id = :cid AND d.branch_id IS NOT NULL AND d.branch_id > 0
             ORDER BY b.name, d.branch_id',
            ['cid' => $companyId]
        );
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['branch_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['name_ar'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            $label = $name !== '' ? $name : ('#' . $id);
            if ($code !== '') {
                $label = $code . ' — ' . $label;
            }
            $out[] = ['value' => $id, 'label' => $label];
        }

        return $out;
    }

    private function normalizeDeviceId(string $raw): string
    {
        $id = trim($raw);
        if ($id === '' || strlen($id) > 64) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9._:-]+$/', $id)) {
            return '';
        }

        return $id;
    }

    /**
     * @param mixed $meta
     * @param array<string, mixed> $input
     */
    private function encodeMeta(mixed $meta, array $input): ?string
    {
        $payload = [];
        if (is_array($meta)) {
            $payload = $meta;
        } elseif (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $platform = trim((string) ($input['platform'] ?? $payload['platform'] ?? ''));
        $ua = trim((string) ($input['user_agent'] ?? $payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        if ($platform !== '') {
            $payload['platform'] = mb_substr($platform, 0, 120);
        }
        if ($ua !== '') {
            $payload['user_agent'] = mb_substr($ua, 0, 500);
        }
        if ($payload === []) {
            return null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicDevice(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== ''
                ? (int) $row['branch_id'] : null,
            'device_id' => (string) ($row['device_id'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'activated_at' => (string) ($row['activated_at'] ?? ''),
            'is_active' => ((string) ($row['status'] ?? '')) === OfflineDevice::STATUS_ACTIVE,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function adminDevice(array $row): array
    {
        $public = $this->publicDevice($row);
        $branchName = trim((string) ($row['branch_name'] ?? $row['branch_name_ar'] ?? ''));
        $userLabel = trim((string) ($row['user_display'] ?? ''));
        if ($userLabel === '') {
            $userLabel = trim((string) ($row['user_email'] ?? ''));
        }
        $meta = null;
        if (!empty($row['meta_json'])) {
            $decoded = is_string($row['meta_json'])
                ? json_decode($row['meta_json'], true)
                : $row['meta_json'];
            $meta = is_array($decoded) ? $decoded : null;
        }

        return array_merge($public, [
            'user_id' => isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'user_label' => $userLabel,
            'branch_name' => $branchName,
            'activated_by' => isset($row['activated_by']) && $row['activated_by'] !== null
                ? (int) $row['activated_by'] : null,
            'activated_by_name' => trim((string) ($row['activated_by_name'] ?? '')),
            'approved_by' => isset($row['approved_by']) && $row['approved_by'] !== null
                ? (int) $row['approved_by'] : null,
            'approved_by_name' => trim((string) ($row['approved_by_name'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'meta' => $meta,
        ]);
    }
}
