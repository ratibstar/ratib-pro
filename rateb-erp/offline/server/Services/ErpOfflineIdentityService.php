<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Offline\Models\OfflineIdentityNonce;

/**
 * Phase P1 — Enterprise Offline Warm Identity (server-issued, cryptographically signed).
 * Phase P2 — renew, nonce registry, clock skew / anti-rollback checks (additive).
 *
 * Does not create PHP sessions. Does not bypass Auth/RBAC.
 * Client seals the issued package under a PIN-derived AES key; PIN only decrypts locally.
 */
final class ErpOfflineIdentityService
{
    public const PURPOSE = 'erp_offline_warm';
    public const ALG = 'HS256-IDENTITY';
    public const DEFAULT_TTL_SECONDS = 30 * 24 * 60 * 60;

    /**
     * @param array<string, mixed> $extraClaims Scalar / JSON-safe array extras merged before sign.
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   identity?: array<string, mixed>
     * }
     */
    public function issue(
        int $companyId,
        int $branchId,
        int $userId,
        string $deviceId,
        ?int $ttlSeconds = null,
        int $identityVersion = 1,
        array $extraClaims = []
    ): array {
        if (!(new OfflineFeatureFlagService())->isAuthUnlockEnabled()) {
            return ['ok' => false, 'error' => 'auth_unlock_disabled'];
        }
        if ($companyId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'online_session_required'];
        }
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required'];
        }

        $ttl = $ttlSeconds ?? $this->ttlSeconds();
        $ttl = max(3600, min($ttl, 90 * 24 * 60 * 60));
        $now = time();
        $identityVersion = max(1, $identityVersion);
        $identityKey = random_bytes(32);
        $claims = [
            'v' => 1,
            'purpose' => self::PURPOSE,
            'company_id' => $companyId,
            'branch_id' => max(0, $branchId),
            'user_id' => $userId,
            'device_id' => $deviceId,
            'issued_at' => $now,
            'not_before' => $now,
            'expires_at' => $now + $ttl,
            'identity_version' => $identityVersion,
            'anti_rollback' => $now,
            'jti' => bin2hex(random_bytes(16)),
        ];
        foreach ($this->filterJsonSafeClaims($extraClaims) as $key => $value) {
            if (!is_string($key) || $key === '' || array_key_exists($key, $claims)) {
                continue;
            }
            $claims[$key] = $value;
        }
        $canonical = $this->canonical($claims);
        $signature = hash_hmac('sha256', $canonical, $identityKey, true);
        $serverAttestation = hash_hmac('sha256', $canonical . '|' . base64_encode($signature), $this->masterSecret(), true);

        $this->registerNonce($companyId, $deviceId, $claims);

        return [
            'ok' => true,
            'identity' => [
                'alg' => self::ALG,
                'kid' => 'offline-identity-v1',
                'claims' => $claims,
                'canonical' => $canonical,
                'signature' => base64_encode($signature),
                'identity_key' => base64_encode($identityKey),
                'server_attestation' => base64_encode($serverAttestation),
                'ttl_seconds' => $ttl,
            ],
        ];
    }

    /**
     * Invalidate previous JTI and issue a new identity package (version + 1).
     *
     * @param array<string, mixed>|null $previousClaims
     * @param array<string, mixed> $extraClaims Merged into renewed claims (cold snapshot extras).
     * @return array{ok: bool, error?: string, identity?: array<string, mixed>}
     */
    public function renew(
        int $companyId,
        int $branchId,
        int $userId,
        string $deviceId,
        ?array $previousClaims = null,
        ?int $ttlSeconds = null,
        array $extraClaims = []
    ): array {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($deviceId === '') {
            return ['ok' => false, 'error' => 'device_id_required'];
        }

        $prevVersion = 1;
        $prevJti = '';
        if (is_array($previousClaims)) {
            $prevVersion = max(1, (int) ($previousClaims['identity_version'] ?? 1));
            $prevJti = trim((string) ($previousClaims['jti'] ?? ''));
        } else {
            $device = null;
            if (OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
                TenantContext::setCompanyId($companyId);
                $device = (new OfflineDevice())->findByDeviceId($companyId, $deviceId);
            }
            if ($device !== null) {
                $prevVersion = max(1, (int) ($device['identity_version'] ?? 1));
                $prevJti = trim((string) ($device['identity_jti'] ?? ''));
            }
        }

        if ($prevJti !== '' && OfflineSchema::hasColumn('rateb_offline_identity_nonces', 'id')) {
            (new OfflineIdentityNonce())->invalidateJti($companyId, $prevJti);
        }

        $issued = $this->issue(
            $companyId,
            $branchId,
            $userId,
            $deviceId,
            $ttlSeconds,
            $prevVersion + 1,
            $extraClaims
        );
        if (!($issued['ok'] ?? false)) {
            return $issued;
        }

        $claims = is_array($issued['identity']['claims'] ?? null)
            ? $issued['identity']['claims']
            : [];
        if (OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
            (new DeviceTrustService())->markEnrolledTrusted($companyId, $deviceId, [
                'identity_expires_at' => (int) ($claims['expires_at'] ?? 0) ?: null,
                'identity_version' => (int) ($claims['identity_version'] ?? ($prevVersion + 1)),
                'identity_jti' => (string) ($claims['jti'] ?? ''),
            ]);
        }

        return $issued;
    }

    /**
     * Verify a decrypted identity package (client or reconnect path).
     *
     * @param array<string, mixed> $package
     * @param array{
     *   company_id?: int,
     *   branch_id?: int,
     *   user_id?: int,
     *   device_id?: string,
     *   identity_version?: int,
     *   previous_issued_at?: int,
     *   check_nonce?: bool
     * } $expect
     * @return array{ok: bool, error?: string, claims?: array<string, mixed>}
     */
    public function verifyPackage(array $package, array $expect = []): array
    {
        $claims = is_array($package['claims'] ?? null) ? $package['claims'] : null;
        if ($claims === null) {
            return ['ok' => false, 'error' => 'identity_missing'];
        }
        if ((string) ($claims['purpose'] ?? '') !== self::PURPOSE) {
            return ['ok' => false, 'error' => 'identity_purpose'];
        }
        $expiresAt = (int) ($claims['expires_at'] ?? 0);
        if ($expiresAt < 1 || $expiresAt <= time()) {
            return ['ok' => false, 'error' => 'identity_expired'];
        }

        $now = time();
        $issuedAt = (int) ($claims['issued_at'] ?? 0);
        $skew = (new ErpOfflineIdentitySessionPolicy())->clockSkewSeconds();
        if ($issuedAt > 0 && $issuedAt > ($now + $skew)) {
            return ['ok' => false, 'error' => 'identity_clock_skew'];
        }
        $notBefore = (int) ($claims['not_before'] ?? $issuedAt);
        if ($notBefore > 0 && $now + $skew < $notBefore) {
            return ['ok' => false, 'error' => 'identity_not_before'];
        }

        if (isset($expect['identity_version'])) {
            $expectedVer = (int) $expect['identity_version'];
            $gotVer = (int) ($claims['identity_version'] ?? 1);
            if ($expectedVer > 0 && $gotVer !== $expectedVer) {
                return ['ok' => false, 'error' => 'identity_version_mismatch'];
            }
        }

        if (isset($expect['previous_issued_at'])) {
            $prevIssued = (int) $expect['previous_issued_at'];
            $anti = (int) ($claims['anti_rollback'] ?? $issuedAt);
            if ($prevIssued > 0 && $anti < $prevIssued) {
                return ['ok' => false, 'error' => 'identity_anti_rollback'];
            }
        }

        $identityKeyB64 = (string) ($package['identity_key'] ?? '');
        $sigB64 = (string) ($package['signature'] ?? '');
        if ($identityKeyB64 === '' || $sigB64 === '') {
            return ['ok' => false, 'error' => 'identity_incomplete'];
        }
        $identityKey = base64_decode($identityKeyB64, true);
        $signature = base64_decode($sigB64, true);
        if ($identityKey === false || $signature === false) {
            return ['ok' => false, 'error' => 'identity_encoding'];
        }
        $canonical = $this->canonical($claims);
        $expected = hash_hmac('sha256', $canonical, $identityKey, true);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'error' => 'identity_signature'];
        }

        $attB64 = (string) ($package['server_attestation'] ?? '');
        if ($attB64 !== '') {
            $att = base64_decode($attB64, true);
            if ($att === false) {
                return ['ok' => false, 'error' => 'identity_attestation'];
            }
            $expectedAtt = hash_hmac('sha256', $canonical . '|' . base64_encode($signature), $this->masterSecret(), true);
            if (!hash_equals($expectedAtt, $att)) {
                return ['ok' => false, 'error' => 'identity_attestation'];
            }
        }

        if (isset($expect['company_id']) && (int) $expect['company_id'] !== (int) ($claims['company_id'] ?? 0)) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }
        if (array_key_exists('branch_id', $expect)
            && (int) $expect['branch_id'] !== (int) ($claims['branch_id'] ?? 0)) {
            return ['ok' => false, 'error' => 'branch_mismatch'];
        }
        if (isset($expect['user_id']) && (int) $expect['user_id'] !== (int) ($claims['user_id'] ?? 0)) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }
        if (isset($expect['device_id']) && (string) $expect['device_id'] !== (string) ($claims['device_id'] ?? '')) {
            return ['ok' => false, 'error' => 'device_mismatch'];
        }

        $checkNonce = !array_key_exists('check_nonce', $expect) || !empty($expect['check_nonce']);
        if ($checkNonce && OfflineSchema::hasColumn('rateb_offline_identity_nonces', 'id')) {
            $jti = trim((string) ($claims['jti'] ?? ''));
            $cid = (int) ($claims['company_id'] ?? 0);
            if ($jti !== '' && $cid > 0) {
                $active = (new OfflineIdentityNonce())->findActiveJti($cid, $jti);
                if ($active === null) {
                    // Allow packages issued before nonce table existed (no row at all vs invalidated).
                    $any = (new OfflineIdentityNonce())->queryOne(
                        'SELECT id, status FROM rateb_offline_identity_nonces
                         WHERE company_id = :cid AND jti = :jti LIMIT 1',
                        ['cid' => $cid, 'jti' => $jti]
                    );
                    if ($any !== null) {
                        return ['ok' => false, 'error' => 'identity_jti_invalid'];
                    }
                }
            }
        }

        return ['ok' => true, 'claims' => $claims];
    }

    public function ttlSeconds(): int
    {
        $env = getenv('RATEB_OFFLINE_IDENTITY_TTL_SECONDS');
        if ($env === false || $env === '') {
            $env = (string) ($_ENV['RATEB_OFFLINE_IDENTITY_TTL_SECONDS'] ?? '');
        }
        $n = (int) $env;

        return $n > 0 ? $n : self::DEFAULT_TTL_SECONDS;
    }

    /** @param array<string, mixed> $claims */
    public function canonical(array $claims): string
    {
        ksort($claims);
        $json = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '';
    }

    public function masterSecret(): string
    {
        $env = getenv('RATEB_OFFLINE_IDENTITY_SECRET');
        if ($env === false || $env === '') {
            $env = (string) ($_ENV['RATEB_OFFLINE_IDENTITY_SECRET'] ?? '');
        }
        $env = trim($env);
        if ($env !== '') {
            return hash('sha256', 'rateb-offline-identity|' . $env, true);
        }
        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 3);
        // Fail-safe local secret (production must set RATEB_OFFLINE_IDENTITY_SECRET).
        return hash('sha256', 'rateb-offline-identity|local|' . $root, true);
    }

    /** @param array<string, mixed> $claims */
    private function registerNonce(int $companyId, string $deviceId, array $claims): void
    {
        if (!OfflineSchema::hasColumn('rateb_offline_identity_nonces', 'id')) {
            return;
        }
        $jti = trim((string) ($claims['jti'] ?? ''));
        if ($jti === '') {
            return;
        }
        try {
            TenantContext::setCompanyId($companyId);
            (new OfflineIdentityNonce())->create([
                'company_id' => $companyId,
                'device_id' => $deviceId,
                'jti' => $jti,
                'identity_version' => max(1, (int) ($claims['identity_version'] ?? 1)),
                'status' => OfflineIdentityNonce::STATUS_ACTIVE,
                'issued_at' => (int) ($claims['issued_at'] ?? time()),
                'expires_at' => (int) ($claims['expires_at'] ?? (time() + $this->ttlSeconds())),
            ]);
        } catch (\Throwable $e) {
            // Nonce registry is best-effort additive; issue still succeeds.
        }
    }

    private function normalizeDeviceId(string $raw): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($raw)) ?? '';

        return substr($id, 0, 64);
    }

    /**
     * Keep only scalar / nested-array values that json_encode can round-trip.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function filterJsonSafeClaims(array $extra): array
    {
        $out = [];
        foreach ($extra as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            $key = (string) $key;
            if ($key === '' || !$this->isJsonSafeValue($value)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private function isJsonSafeValue(mixed $value): bool
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $child) {
            if (!$this->isJsonSafeValue($child)) {
                return false;
            }
        }

        return true;
    }
}
