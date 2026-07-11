<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase P1 — Enterprise Offline Warm Identity (server-issued, cryptographically signed).
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
        ?int $ttlSeconds = null
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
        $identityKey = random_bytes(32);
        $claims = [
            'v' => 1,
            'purpose' => self::PURPOSE,
            'company_id' => $companyId,
            'branch_id' => max(0, $branchId),
            'user_id' => $userId,
            'device_id' => $deviceId,
            'issued_at' => $now,
            'expires_at' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $canonical = $this->canonical($claims);
        $signature = hash_hmac('sha256', $canonical, $identityKey, true);
        $serverAttestation = hash_hmac('sha256', $canonical . '|' . base64_encode($signature), $this->masterSecret(), true);

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
     * Verify a decrypted identity package (client or reconnect path).
     *
     * @param array<string, mixed> $package
     * @param array{company_id?: int, branch_id?: int, user_id?: int, device_id?: string} $expect
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

    private function normalizeDeviceId(string $raw): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._:-]/', '', trim($raw)) ?? '';

        return substr($id, 0, 64);
    }
}
