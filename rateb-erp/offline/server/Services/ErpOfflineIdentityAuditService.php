<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineIdentityAudit;

/**
 * Phase P2 — append-only offline identity / device trust audit log.
 */
final class ErpOfflineIdentityAuditService
{
    public const EVENT_IDENTITY_ENROLLED = 'identity_enrolled';
    public const EVENT_IDENTITY_RENEWED = 'identity_renewed';
    public const EVENT_UNLOCK_SUCCESS = 'unlock_success';
    public const EVENT_UNLOCK_FAILED = 'unlock_failed';
    public const EVENT_IDENTITY_EXPIRED = 'identity_expired';
    public const EVENT_IDENTITY_REVOKED = 'identity_revoked';
    public const EVENT_LOGOUT_WIPE = 'logout_wipe';
    public const EVENT_DEVICE_REVOKED = 'device_revoked';
    public const EVENT_DEVICE_RENAMED = 'device_renamed';
    public const EVENT_DEVICE_RESTORED = 'device_restored';
    public const EVENT_DEVICE_FORCE_LOGOUT = 'device_force_logout';
    public const EVENT_COLD_UNLOCK_SUCCESS = 'cold_unlock_success';
    public const EVENT_COLD_UNLOCK_FAILED = 'cold_unlock_failed';
    public const EVENT_COLD_SESSION_DESTROYED = 'cold_session_destroyed';

    /** @var list<string> */
    private const KNOWN_EVENTS = [
        self::EVENT_IDENTITY_ENROLLED,
        self::EVENT_IDENTITY_RENEWED,
        self::EVENT_UNLOCK_SUCCESS,
        self::EVENT_UNLOCK_FAILED,
        self::EVENT_IDENTITY_EXPIRED,
        self::EVENT_IDENTITY_REVOKED,
        self::EVENT_LOGOUT_WIPE,
        self::EVENT_DEVICE_REVOKED,
        self::EVENT_DEVICE_RENAMED,
        self::EVENT_DEVICE_RESTORED,
        self::EVENT_DEVICE_FORCE_LOGOUT,
        self::EVENT_COLD_UNLOCK_SUCCESS,
        self::EVENT_COLD_UNLOCK_FAILED,
        self::EVENT_COLD_SESSION_DESTROYED,
    ];

    /**
     * @param array{
     *   branch_id?: int|null,
     *   user_id?: int|null,
     *   device_id?: string|null,
     *   detail?: array<string, mixed>|string|null
     * } $opts
     */
    public function log(string $event, int $companyId, array $opts = []): bool
    {
        $event = strtolower(trim($event));
        if ($companyId < 1 || $event === '') {
            return false;
        }
        if (!in_array($event, self::KNOWN_EVENTS, true)) {
            return false;
        }
        if (!OfflineSchema::hasColumn('rateb_offline_identity_audit', 'id')) {
            return false;
        }

        $detail = $opts['detail'] ?? null;
        $detailJson = null;
        if (is_array($detail)) {
            $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $detailJson = is_string($encoded) ? $encoded : null;
        } elseif (is_string($detail) && $detail !== '') {
            $detailJson = $detail;
        }

        $deviceId = isset($opts['device_id']) ? trim((string) $opts['device_id']) : '';
        if ($deviceId !== '') {
            $deviceId = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $deviceId) ?? '', 0, 64);
        }

        try {
            TenantContext::setCompanyId($companyId);
            (new OfflineIdentityAudit())->create([
                'company_id' => $companyId,
                'branch_id' => isset($opts['branch_id']) && (int) $opts['branch_id'] > 0
                    ? (int) $opts['branch_id']
                    : null,
                'user_id' => isset($opts['user_id']) && (int) $opts['user_id'] > 0
                    ? (int) $opts['user_id']
                    : null,
                'device_id' => $deviceId !== '' ? $deviceId : null,
                'event_type' => $event,
                'detail_json' => $detailJson,
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
