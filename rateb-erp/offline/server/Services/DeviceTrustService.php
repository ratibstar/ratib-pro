<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Offline\Models\OfflineIdentityNonce;

/**
 * Phase P2 — device trust lifecycle for warm offline identity.
 */
final class DeviceTrustService
{
    public const TRUSTED = 'trusted';
    public const REVOKED = 'revoked';
    public const LOST = 'lost';
    public const DISABLED = 'disabled';

    /**
     * @param array{branch_id?: int|null, user_id?: int|null, status?: string|null} $filters
     * @return list<array<string, mixed>>
     */
    public function listDevices(int $companyId, array $filters = []): array
    {
        if ($companyId < 1 || !OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
            return [];
        }

        TenantContext::setCompanyId($companyId);
        $sql = 'SELECT * FROM rateb_offline_devices WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : 0;
        if ($branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        }
        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : 0;
        if ($userId > 0) {
            $sql .= ' AND user_id = :uid';
            $params['uid'] = $userId;
        }
        $status = isset($filters['status']) ? strtolower(trim((string) $filters['status'])) : '';
        if ($status !== '') {
            if ($status === 'active') {
                $status = self::TRUSTED;
            }
            if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
                $sql .= ' AND trust_status = :tst';
                $params['tst'] = $status;
            } else {
                $sql .= ' AND status = :st';
                $params['st'] = $status === self::TRUSTED ? OfflineDevice::STATUS_ACTIVE : $status;
            }
        }

        $sql .= ' ORDER BY last_seen_at DESC, id DESC LIMIT 500';
        $rows = (new OfflineDevice())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->publicRow($row);
            }
        }

        return $out;
    }

    /**
     * @return array{ok: bool, error?: string, device?: array<string, mixed>}
     */
    public function rename(int $companyId, string $deviceId, string $nickname, int $actorUserId): array
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'device_unknown'];
        }
        if (!OfflineSchema::hasColumn('rateb_offline_devices', 'nickname')) {
            return ['ok' => false, 'error' => 'migration_required'];
        }

        $nickname = trim($nickname);
        if (mb_strlen($nickname) > 120) {
            $nickname = mb_substr($nickname, 0, 120);
        }
        $id = (int) ($row['id'] ?? 0);
        (new OfflineDevice())->update($id, ['nickname' => $nickname !== '' ? $nickname : null]);
        $fresh = (new OfflineDevice())->find($id) ?? $row;

        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_DEVICE_RENAMED,
            $companyId,
            [
                'branch_id' => (int) ($fresh['branch_id'] ?? 0),
                'user_id' => $actorUserId > 0 ? $actorUserId : (int) ($fresh['user_id'] ?? 0),
                'device_id' => (string) ($fresh['device_id'] ?? $deviceId),
                'detail' => ['nickname' => $nickname, 'actor_user_id' => $actorUserId],
            ]
        );

        return ['ok' => true, 'device' => $this->publicRow($fresh)];
    }

    /**
     * @return array{ok: bool, error?: string, device?: array<string, mixed>}
     */
    public function revoke(int $companyId, string $deviceId, int $actorUserId, ?string $reason = null): array
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'device_unknown'];
        }

        $patch = ['status' => OfflineDevice::STATUS_REVOKED];
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
            $patch['trust_status'] = self::REVOKED;
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $patch['force_logout_at'] = date('Y-m-d H:i:s');
        }
        $id = (int) ($row['id'] ?? 0);
        (new OfflineDevice())->update($id, $patch);

        if (OfflineSchema::hasColumn('rateb_offline_identity_nonces', 'id')) {
            (new OfflineIdentityNonce())->invalidateDevice($companyId, $deviceId);
        }

        $fresh = (new OfflineDevice())->find($id) ?? $row;
        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_DEVICE_REVOKED,
            $companyId,
            [
                'branch_id' => (int) ($fresh['branch_id'] ?? 0),
                'user_id' => $actorUserId > 0 ? $actorUserId : (int) ($fresh['user_id'] ?? 0),
                'device_id' => $deviceId,
                'detail' => [
                    'reason' => $reason,
                    'actor_user_id' => $actorUserId,
                ],
            ]
        );
        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_IDENTITY_REVOKED,
            $companyId,
            [
                'branch_id' => (int) ($fresh['branch_id'] ?? 0),
                'user_id' => (int) ($fresh['user_id'] ?? 0),
                'device_id' => $deviceId,
                'detail' => ['via' => 'device_revoke', 'actor_user_id' => $actorUserId],
            ]
        );

        return ['ok' => true, 'device' => $this->publicRow($fresh)];
    }

    /**
     * @return array{ok: bool, error?: string, revoked?: int}
     */
    public function revokeAll(int $companyId, int $actorUserId, ?int $branchId = null, ?int $userId = null): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $filters = [];
        if ($branchId !== null && $branchId > 0) {
            $filters['branch_id'] = $branchId;
        }
        if ($userId !== null && $userId > 0) {
            $filters['user_id'] = $userId;
        }
        $devices = $this->listDevices($companyId, $filters);
        $n = 0;
        foreach ($devices as $d) {
            $did = (string) ($d['device_id'] ?? '');
            if ($did === '') {
                continue;
            }
            $trust = strtolower((string) ($d['trust_status'] ?? ''));
            if ($trust === self::REVOKED) {
                continue;
            }
            $r = $this->revoke($companyId, $did, $actorUserId, 'revoke_all');
            if (!empty($r['ok'])) {
                $n++;
            }
        }

        return ['ok' => true, 'revoked' => $n];
    }

    /**
     * @return array{ok: bool, error?: string, device?: array<string, mixed>}
     */
    public function forceLogout(int $companyId, string $deviceId, int $actorUserId): array
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'device_unknown'];
        }

        $patch = [];
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $patch['force_logout_at'] = date('Y-m-d H:i:s');
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'last_logout_at')) {
            $patch['last_logout_at'] = date('Y-m-d H:i:s');
        }
        $id = (int) ($row['id'] ?? 0);
        if ($patch !== []) {
            (new OfflineDevice())->update($id, $patch);
        }
        if (OfflineSchema::hasColumn('rateb_offline_identity_nonces', 'id')) {
            (new OfflineIdentityNonce())->invalidateDevice($companyId, $deviceId);
        }

        $fresh = (new OfflineDevice())->find($id) ?? $row;
        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_DEVICE_FORCE_LOGOUT,
            $companyId,
            [
                'branch_id' => (int) ($fresh['branch_id'] ?? 0),
                'user_id' => $actorUserId > 0 ? $actorUserId : (int) ($fresh['user_id'] ?? 0),
                'device_id' => $deviceId,
                'detail' => ['actor_user_id' => $actorUserId],
            ]
        );

        return ['ok' => true, 'device' => $this->publicRow($fresh)];
    }

    /**
     * @return array{ok: bool, error?: string, device?: array<string, mixed>}
     */
    public function restore(int $companyId, string $deviceId): array
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'device_unknown'];
        }

        $patch = [
            'status' => OfflineDevice::STATUS_ACTIVE,
        ];
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
            $patch['trust_status'] = self::TRUSTED;
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $patch['force_logout_at'] = null;
        }
        $id = (int) ($row['id'] ?? 0);
        (new OfflineDevice())->update($id, $patch);
        $fresh = (new OfflineDevice())->find($id) ?? $row;

        (new ErpOfflineIdentityAuditService())->log(
            ErpOfflineIdentityAuditService::EVENT_DEVICE_RESTORED,
            $companyId,
            [
                'branch_id' => (int) ($fresh['branch_id'] ?? 0),
                'user_id' => (int) ($fresh['user_id'] ?? 0),
                'device_id' => $deviceId,
                'detail' => [],
            ]
        );

        return ['ok' => true, 'device' => $this->publicRow($fresh)];
    }

    public function touchOnline(int $companyId, string $deviceId): void
    {
        $this->touchField($companyId, $deviceId, 'last_online_at');
    }

    public function touchReplay(int $companyId, string $deviceId): void
    {
        $this->touchField($companyId, $deviceId, 'last_replay_at');
    }

    public function touchUnlock(int $companyId, string $deviceId): void
    {
        $this->touchField($companyId, $deviceId, 'last_unlock_at');
    }

    public function touchLogout(int $companyId, string $deviceId): void
    {
        $this->touchField($companyId, $deviceId, 'last_logout_at');
    }

    public function updateFingerprint(int $companyId, string $deviceId, string $fingerprint): bool
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null || !OfflineSchema::hasColumn('rateb_offline_devices', 'fingerprint')) {
            return false;
        }
        $fp = substr(trim($fingerprint), 0, 128);
        $id = (int) ($row['id'] ?? 0);

        return (new OfflineDevice())->update($id, ['fingerprint' => $fp !== '' ? $fp : null]);
    }

    /**
     * Apply trust + identity fields after successful enroll.
     *
     * @param array{identity_expires_at?: int|null, identity_version?: int|null, identity_jti?: string|null, fingerprint?: string|null} $identityMeta
     */
    public function markEnrolledTrusted(int $companyId, string $deviceId, array $identityMeta = []): bool
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return false;
        }
        $patch = ['status' => OfflineDevice::STATUS_ACTIVE];
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
            $patch['trust_status'] = self::TRUSTED;
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $patch['force_logout_at'] = null;
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'identity_expires_at')
            && array_key_exists('identity_expires_at', $identityMeta)) {
            $patch['identity_expires_at'] = $identityMeta['identity_expires_at'];
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'identity_version')
            && isset($identityMeta['identity_version'])) {
            $patch['identity_version'] = (int) $identityMeta['identity_version'];
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'identity_jti')
            && array_key_exists('identity_jti', $identityMeta)) {
            $jti = trim((string) ($identityMeta['identity_jti'] ?? ''));
            $patch['identity_jti'] = $jti !== '' ? substr($jti, 0, 64) : null;
        }
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'fingerprint')
            && isset($identityMeta['fingerprint'])) {
            $fp = substr(trim((string) $identityMeta['fingerprint']), 0, 128);
            $patch['fingerprint'] = $fp !== '' ? $fp : null;
        }
        $id = (int) ($row['id'] ?? 0);

        return (new OfflineDevice())->update($id, $patch);
    }

    public function isReplayAllowed(int $companyId, string $deviceId): bool
    {
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return false;
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === OfflineDevice::STATUS_REVOKED
            || $status === OfflineDevice::STATUS_INACTIVE
            || $status === OfflineDevice::STATUS_PENDING
            || $status === self::LOST
            || $status === self::DISABLED) {
            return false;
        }

        if (OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')) {
            $trust = strtolower(trim((string) ($row['trust_status'] ?? '')));
            if ($trust === '' || $trust === 'active') {
                $trust = self::TRUSTED;
            }
            if ($trust !== self::TRUSTED) {
                return false;
            }
        }

        if (OfflineSchema::hasColumn('rateb_offline_devices', 'force_logout_at')) {
            $fl = $row['force_logout_at'] ?? null;
            if ($fl !== null && trim((string) $fl) !== '') {
                return false;
            }
        }

        return $status === OfflineDevice::STATUS_ACTIVE || $status === OfflineDevice::STATUS_TRUSTED;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function publicRow(array $row): array
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $trust = OfflineSchema::hasColumn('rateb_offline_devices', 'trust_status')
            ? strtolower(trim((string) ($row['trust_status'] ?? '')))
            : '';
        if ($trust === '' || $trust === 'active') {
            $trust = $status === OfflineDevice::STATUS_ACTIVE || $status === OfflineDevice::STATUS_TRUSTED
                ? self::TRUSTED
                : ($status !== '' ? $status : self::TRUSTED);
        }

        return [
            'device_id' => (string) ($row['device_id'] ?? ''),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'fingerprint' => (string) ($row['fingerprint'] ?? ''),
            'status' => $status,
            'trust_status' => $trust,
            'display_trust' => $trust,
            'is_active' => $status === OfflineDevice::STATUS_ACTIVE || $status === OfflineDevice::STATUS_TRUSTED,
            'is_trusted' => $trust === self::TRUSTED,
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'last_online_at' => (string) ($row['last_online_at'] ?? ''),
            'last_replay_at' => (string) ($row['last_replay_at'] ?? ''),
            'last_unlock_at' => (string) ($row['last_unlock_at'] ?? ''),
            'last_logout_at' => (string) ($row['last_logout_at'] ?? ''),
            'identity_expires_at' => isset($row['identity_expires_at']) ? (int) $row['identity_expires_at'] : null,
            'identity_version' => isset($row['identity_version']) ? (int) $row['identity_version'] : 1,
            'identity_jti' => (string) ($row['identity_jti'] ?? ''),
            'force_logout_at' => (string) ($row['force_logout_at'] ?? ''),
            'vault_integrity' => (string) ($row['vault_integrity'] ?? ''),
        ];
    }

    private function touchField(int $companyId, string $deviceId, string $column): void
    {
        if (!OfflineSchema::hasColumn('rateb_offline_devices', $column)) {
            return;
        }
        $row = $this->requireDevice($companyId, $deviceId);
        if ($row === null) {
            return;
        }
        $id = (int) ($row['id'] ?? 0);
        $patch = [$column => date('Y-m-d H:i:s')];
        if ($column !== 'last_seen_at' && OfflineSchema::hasColumn('rateb_offline_devices', 'last_seen_at')) {
            $patch['last_seen_at'] = date('Y-m-d H:i:s');
        }
        (new OfflineDevice())->update($id, $patch);
    }

    /** @return array<string, mixed>|null */
    private function requireDevice(int $companyId, string $deviceId): ?array
    {
        $deviceId = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($deviceId)) ?? '';
        $deviceId = substr($deviceId, 0, 64);
        if ($companyId < 1 || $deviceId === '' || !OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
            return null;
        }
        TenantContext::setCompanyId($companyId);

        return (new OfflineDevice())->findByDeviceId($companyId, $deviceId);
    }
}
