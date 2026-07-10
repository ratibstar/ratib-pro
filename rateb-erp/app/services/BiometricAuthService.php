<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;

/** WebAuthn + face biometric bridge for ERP POS (rateb_users scoped). */
final class BiometricAuthService
{
    private const CHALLENGE_TTL = 120;
    private const POS_VERIFY_TTL = 28800; // 8 hours

    public function hasEnrollment(int $userId): bool
    {
        if ($userId < 1 || !$this->webauthnTablesReady()) {
            return false;
        }
        try {
            $db = Database::connection();
            $fp = $db->prepare('SELECT id FROM rateb_webauthn_credentials WHERE user_id = :uid LIMIT 1');
            $fp->execute(['uid' => $userId]);
            if ($fp->fetchColumn()) {
                return true;
            }
            if (!$this->faceTemplatesTableReady()) {
                return false;
            }
            $face = $db->prepare('SELECT id FROM rateb_face_templates WHERE user_id = :uid LIMIT 1');
            $face->execute(['uid' => $userId]);

            return (bool) $face->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPosVerified(int $userId): bool
    {
        return $this->isPosBiometricVerified($userId);
    }

    public function isPosBiometricVerified(int $userId): bool
    {
        $verifiedUser = (int) SessionManager::get('pos_biometric_user_id');
        $verifiedAt = (int) SessionManager::get('pos_biometric_verified_at');
        if ($verifiedUser !== $userId || $verifiedAt < 1) {
            return false;
        }

        return (time() - $verifiedAt) < self::POS_VERIFY_TTL;
    }

    public function markPosVerified(int $userId): void
    {
        $this->bindPosBiometricSession($userId, 'webauthn');
    }

    public function clearPosBiometricSession(): void
    {
        SessionManager::forget('pos_biometric_user_id');
        SessionManager::forget('pos_biometric_verified_at');
        SessionManager::forget('pos_biometric_method');
    }

    /** @return array<string, mixed> */
    public function startWebAuthn(int $userId, bool $supervisor = false): array
    {
        if ($supervisor) {
            SessionManager::set('pos_webauthn_supervisor', 1);
            try {
                return ['ok' => true] + $this->buildSupervisorWebAuthnOptions();
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $this->publicErrorMessage($e)];
            }
        }

        SessionManager::forget('pos_webauthn_supervisor');
        if ($userId < 1) {
            $userId = $this->userId();
        }

        try {
            return ['ok' => true] + $this->buildWebAuthnOptions($userId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->publicErrorMessage($e)];
        }
    }

    /** @return array<string, mixed> */
    public function registerStartWebAuthn(int $userId): array
    {
        if ($userId < 1) {
            $userId = $this->userId();
        }

        $sessionUserId = $this->userId();
        if ($sessionUserId < 1 || $sessionUserId !== $userId) {
            return ['ok' => false, 'error' => __('access_denied')];
        }

        try {
            return ['ok' => true] + $this->buildRegisterWebAuthnOptions($userId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->publicErrorMessage($e)];
        }
    }

    /** @param array<string, mixed> $payload */
    public function registerFinishWebAuthn(array $payload, int $userId): array
    {
        if ($userId < 1) {
            $userId = $this->userId();
        }

        $sessionUserId = $this->userId();
        if ($sessionUserId < 1 || $sessionUserId !== $userId) {
            return ['ok' => false, 'error' => __('access_denied')];
        }

        $registerUserId = (int) SessionManager::get('pos_webauthn_register_user_id');
        $expires = (int) SessionManager::get('pos_webauthn_register_expires');
        if ($registerUserId < 1 || $registerUserId !== $userId || $expires < time()) {
            return ['ok' => false, 'error' => __('pos_biometric_challenge_expired')];
        }

        $credentialIdB64 = (string) ($payload['credentialId'] ?? '');
        $publicKeyB64 = (string) ($payload['publicKey'] ?? $payload['attestationObject'] ?? '');
        if ($credentialIdB64 === '' || $publicKeyB64 === '') {
            return ['ok' => false, 'error' => __('invalid_request')];
        }

        $credentialId = $this->base64DecodeFlexible($credentialIdB64);
        $publicKey = $this->base64DecodeFlexible($publicKeyB64);
        if ($credentialId === '' || $publicKey === '') {
            return ['ok' => false, 'error' => __('pos_biometric_invalid_credential')];
        }

        $user = (new User())->find($userId);
        if ($user === null || (string) ($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => __('access_denied')];
        }

        try {
            $db = Database::connection();
            $this->assertWebauthnStorageReady();

            $delete = $db->prepare('DELETE FROM rateb_webauthn_credentials WHERE user_id = :uid');
            $delete->execute(['uid' => $userId]);

            $insert = $db->prepare(
                'INSERT INTO rateb_webauthn_credentials (user_id, credential_id, public_key, sign_count)
                 VALUES (:uid, :cid, :pk, 0)'
            );
            // Attestation payloads are binary; base64 keeps TEXT/MEDIUMBLOB columns safe on all MySQL configs.
            $insert->execute([
                'uid' => $userId,
                'cid' => $credentialId,
                'pk' => base64_encode($publicKey),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->publicErrorMessage($e)];
        }

        SessionManager::forget('pos_webauthn_register_challenge');
        SessionManager::forget('pos_webauthn_register_user_id');
        SessionManager::forget('pos_webauthn_register_expires');

        return ['ok' => true, 'user_id' => $userId];
    }

    /** @param array<string, mixed> $payload */
    public function finishWebAuthn(array $payload, int $userId, bool $supervisor = false): array
    {
        if ($userId < 1 && !$supervisor) {
            $userId = $this->userId();
        }

        if ($supervisor) {
            $expires = (int) SessionManager::get('pos_webauthn_expires');
            if ($expires < time()) {
                return ['ok' => false, 'error' => __('pos_biometric_failed')];
            }
        } else {
            $sessionUserId = (int) SessionManager::get('pos_webauthn_user_id');
            $expires = (int) SessionManager::get('pos_webauthn_expires');
            if ($sessionUserId < 1 || $expires < time()) {
                return ['ok' => false, 'error' => __('pos_biometric_failed')];
            }

            if ($sessionUserId !== $userId) {
                return ['ok' => false, 'error' => __('pos_biometric_failed')];
            }
        }

        $credentialIdB64 = (string) ($payload['credentialId'] ?? $payload['id'] ?? '');
        if ($credentialIdB64 === '') {
            return ['ok' => false, 'error' => __('invalid_request')];
        }

        $credentialId = $this->base64UrlDecode($credentialIdB64);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT user_id FROM rateb_webauthn_credentials WHERE credential_id = :cid LIMIT 1'
        );
        $stmt->execute(['cid' => $credentialId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => __('pos_biometric_failed')];
        }

        $verifiedId = (int) ($row['user_id'] ?? 0);
        if ($supervisor) {
            if (!$this->userCanSupervise($verifiedId)) {
                return ['ok' => false, 'error' => __('pos_supervisor_required')];
            }
        } elseif ($verifiedId !== $userId) {
            return ['ok' => false, 'error' => __('pos_biometric_failed')];
        }

        SessionManager::forget('pos_webauthn_challenge');
        SessionManager::forget('pos_webauthn_user_id');
        SessionManager::forget('pos_webauthn_expires');
        SessionManager::forget('pos_webauthn_supervisor');

        return ['ok' => true, 'user_id' => $verifiedId];
    }

    /** @param array<string, mixed> $payload @return array{ok:bool,error?:string} */
    public function verifyFace(int $userId, array $payload): array
    {
        if ($userId < 1) {
            $userId = $this->userId();
        }

        $template = (string) ($payload['faceTemplate'] ?? '');
        if ($template === '') {
            return ['ok' => false, 'error' => __('invalid_request')];
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT template_data, confidence_threshold FROM rateb_face_templates WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['template_data'])) {
            return ['ok' => false, 'error' => __('pos_biometric_not_enrolled')];
        }

        $similarity = $this->compareTemplates($template, (string) $row['template_data']);
        $threshold = (float) ($row['confidence_threshold'] ?? 0.75);
        if ($similarity < $threshold) {
            return ['ok' => false, 'error' => __('pos_biometric_failed')];
        }

        return ['ok' => true];
    }

    private function userCanSupervise(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $authz = new AuthorizationService();
        if ($authz->userHasPermission($userId, 'pos.supervisor.approve')
            || $authz->userHasPermission($userId, 'pos.discount.manage')
            || $authz->userHasPermission($userId, 'pos.returns.manage')) {
            return true;
        }

        // Defensive: is_super_admin users must always be able to approve POS overrides.
        $user = (new User())->find($userId);

        return $user !== null && !empty($user['is_super_admin']);
    }

    /** @return array<string, mixed> */
    private function buildSupervisorWebAuthnOptions(): array
    {
        $this->assertWebauthnStorageReady();

        $db = Database::connection();
        $rows = $db->query(
            'SELECT wc.credential_id, wc.user_id
             FROM rateb_webauthn_credentials wc
             INNER JOIN rateb_users u ON u.id = wc.user_id
             WHERE u.status = \'active\''
        )->fetchAll(\PDO::FETCH_ASSOC);

        $allow = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1 || !$this->userCanSupervise($uid)) {
                continue;
            }
            $credentialId = $row['credential_id'] ?? null;
            if ($credentialId === null || $credentialId === '') {
                continue;
            }
            // credential_id is stored as raw binary bytes.
            $raw = is_string($credentialId) ? $credentialId : (string) $credentialId;
            $allow[] = [
                'type' => 'public-key',
                'id' => $this->base64UrlEncode($raw),
                'transports' => ['internal', 'hybrid', 'usb'],
            ];
        }

        if ($allow === []) {
            throw new \RuntimeException(__('pos_supervisor_biometric_missing'));
        }

        $challenge = random_bytes(32);
        SessionManager::set('pos_webauthn_challenge', base64_encode($challenge));
        SessionManager::set('pos_webauthn_user_id', 0);
        SessionManager::set('pos_webauthn_expires', time() + self::CHALLENGE_TTL);

        return [
            'publicKey' => [
                'challenge' => $this->base64UrlEncode($challenge),
                'rpId' => $this->resolveRpId(),
                'allowCredentials' => $allow,
                'timeout' => 60000,
                'userVerification' => 'required',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildRegisterWebAuthnOptions(int $userId): array
    {
        $this->assertWebauthnStorageReady();

        $user = (new User())->find($userId);
        if ($user === null || (string) ($user['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('access_denied'));
        }

        $displayName = trim((string) ($user['name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($user['email'] ?? 'user'));
        }
        $loginName = trim((string) ($user['email'] ?? $displayName));

        $db = Database::connection();
        $delete = $db->prepare('DELETE FROM rateb_webauthn_credentials WHERE user_id = :uid');
        $delete->execute(['uid' => $userId]);

        $challenge = random_bytes(32);
        SessionManager::set('pos_webauthn_register_challenge', base64_encode($challenge));
        SessionManager::set('pos_webauthn_register_user_id', $userId);
        SessionManager::set('pos_webauthn_register_expires', time() + self::CHALLENGE_TTL);

        $rpId = $this->resolveRpId();

        return [
            'publicKey' => [
                'challenge' => $this->base64UrlEncode($challenge),
                'rp' => [
                    'name' => 'RATEB ERP',
                    'id' => $rpId,
                ],
                'user' => [
                    'id' => $this->base64UrlEncode(pack('N', $userId)),
                    'name' => $loginName,
                    'displayName' => $displayName,
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout' => 120000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'userVerification' => 'preferred',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildWebAuthnOptions(int $userId): array
    {
        $this->assertWebauthnStorageReady();

        $user = (new User())->find($userId);
        if ($user === null || (string) ($user['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('access_denied'));
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT credential_id FROM rateb_webauthn_credentials WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['credential_id'])) {
            throw new \RuntimeException(__('pos_biometric_not_enrolled'));
        }

        $challenge = random_bytes(32);
        SessionManager::set('pos_webauthn_challenge', base64_encode($challenge));
        SessionManager::set('pos_webauthn_user_id', $userId);
        SessionManager::set('pos_webauthn_expires', time() + self::CHALLENGE_TTL);

        return [
            'publicKey' => [
                'challenge' => $this->base64UrlEncode($challenge),
                'rpId' => $this->resolveRpId(),
                'allowCredentials' => [[
                    'type' => 'public-key',
                    'id' => $this->base64UrlEncode($row['credential_id']),
                    'transports' => ['internal'],
                ]],
                'timeout' => 60000,
                'userVerification' => 'required',
            ],
        ];
    }

    private function bindPosBiometricSession(int $userId, string $method): void
    {
        SessionManager::set('pos_biometric_user_id', $userId);
        SessionManager::set('pos_biometric_verified_at', time());
        SessionManager::set('pos_biometric_method', $method);
    }

    private function userId(): int
    {
        return (int) (SessionManager::get('rateb_user_id') ?? 0);
    }

    private function resolveRpId(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return $host;
    }

    private function compareTemplates(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        similar_text($a, $b, $pct);

        return max(0.0, min(1.0, $pct / 100));
    }

    private function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $b64): string
    {
        $pad = 4 - (strlen($b64) % 4);
        if ($pad < 4) {
            $b64 .= str_repeat('=', $pad);
        }
        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }

    private function base64DecodeFlexible(string $b64): string
    {
        $decoded = base64_decode($b64, true);
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        return $this->base64UrlDecode($b64);
    }

    private function assertWebauthnStorageReady(): void
    {
        if ($this->webauthnTablesReady()) {
            return;
        }

        throw new \RuntimeException(__('db_schema_outdated'));
    }

    private function webauthnTablesReady(): bool
    {
        return $this->tableExists('rateb_webauthn_credentials');
    }

    private function faceTemplatesTableReady(): bool
    {
        return $this->tableExists('rateb_face_templates');
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $db = Database::connection();
            $safeTable = str_replace('`', '', $table);
            $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($safeTable));
            $cache[$table] = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    private function publicErrorMessage(\Throwable $e): string
    {
        return DatabaseErrorService::userMessage($e);
    }
}
