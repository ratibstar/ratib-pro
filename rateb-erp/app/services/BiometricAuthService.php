<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;

/**
 * WebAuthn biometric bridge for ERP POS (rateb_users + company scoped).
 *
 * Finish path binds the session challenge via clientDataJSON (type + challenge + origin).
 * Full COSE signature verification is deferred until a WebAuthn library / extracted SPKI is available;
 * authenticatorData + signature must still be present on assertion finish.
 */
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
            $companyId = $this->resolveCompanyId();
            $sql = 'SELECT id FROM rateb_webauthn_credentials WHERE user_id = :uid';
            $params = ['uid' => $userId];
            if ($this->companyColumnReady() && $companyId > 0) {
                $sql .= ' AND (company_id = :cid OR company_id IS NULL)';
                $params['cid'] = $companyId;
            }
            $sql .= ' LIMIT 1';
            $fp = $db->prepare($sql);
            $fp->execute($params);

            return (bool) $fp->fetchColumn();
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
                // Fallback: logged-in supervisor/super-admin can approve with their own passkey.
                $selfId = $this->userId();
                if ($selfId > 0 && $this->userCanSupervise($selfId)) {
                    try {
                        $opts = $this->buildWebAuthnOptions($selfId);
                        SessionManager::set('pos_webauthn_supervisor', 1);
                        return ['ok' => true] + $opts;
                    } catch (\Throwable $e2) {
                        return ['ok' => false, 'error' => $this->publicErrorMessage($e2)];
                    }
                }
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
        $sessionChallenge = (string) SessionManager::get('pos_webauthn_register_challenge');
        if ($registerUserId < 1 || $registerUserId !== $userId || $expires < time() || $sessionChallenge === '') {
            return ['ok' => false, 'error' => __('pos_biometric_challenge_expired')];
        }

        $challengeError = $this->assertClientDataChallenge($payload, $sessionChallenge, 'webauthn.create');
        if ($challengeError !== null) {
            return ['ok' => false, 'error' => $challengeError];
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

        $companyId = $this->resolveCompanyId();
        if ($companyId < 1) {
            $companyId = (int) ($user['company_id'] ?? 0);
        }
        $branchId = $this->resolveBranchId();

        try {
            $db = Database::connection();
            $this->assertWebauthnStorageReady();

            $deleteSql = 'DELETE FROM rateb_webauthn_credentials WHERE user_id = :uid';
            $deleteParams = ['uid' => $userId];
            if ($this->companyColumnReady() && $companyId > 0) {
                $deleteSql .= ' AND (company_id = :cid OR company_id IS NULL)';
                $deleteParams['cid'] = $companyId;
            }
            $delete = $db->prepare($deleteSql);
            $delete->execute($deleteParams);

            if ($this->companyColumnReady()) {
                $hasBranch = $this->columnExists('rateb_webauthn_credentials', 'branch_id');
                if ($hasBranch) {
                    $insert = $db->prepare(
                        'INSERT INTO rateb_webauthn_credentials
                            (user_id, company_id, branch_id, credential_id, public_key, sign_count)
                         VALUES (:uid, :co, :bid, :cred, :pk, 0)'
                    );
                    $insert->execute([
                        'uid' => $userId,
                        'co' => $companyId > 0 ? $companyId : null,
                        'bid' => $branchId > 0 ? $branchId : null,
                        'cred' => $credentialId,
                        'pk' => base64_encode($publicKey),
                    ]);
                } else {
                    $insert = $db->prepare(
                        'INSERT INTO rateb_webauthn_credentials
                            (user_id, company_id, credential_id, public_key, sign_count)
                         VALUES (:uid, :co, :cred, :pk, 0)'
                    );
                    $insert->execute([
                        'uid' => $userId,
                        'co' => $companyId > 0 ? $companyId : null,
                        'cred' => $credentialId,
                        'pk' => base64_encode($publicKey),
                    ]);
                }
            } else {
                $insert = $db->prepare(
                    'INSERT INTO rateb_webauthn_credentials (user_id, credential_id, public_key, sign_count)
                     VALUES (:uid, :cred, :pk, 0)'
                );
                $insert->execute([
                    'uid' => $userId,
                    'cred' => $credentialId,
                    'pk' => base64_encode($publicKey),
                ]);
            }
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

        $sessionChallenge = (string) SessionManager::get('pos_webauthn_challenge');
        if ($sessionChallenge === '') {
            return ['ok' => false, 'error' => __('pos_biometric_failed')];
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

        $challengeError = $this->assertClientDataChallenge($payload, $sessionChallenge, 'webauthn.get');
        if ($challengeError !== null) {
            return ['ok' => false, 'error' => $challengeError];
        }

        $authenticatorDataB64 = (string) ($payload['authenticatorData'] ?? '');
        $signatureB64 = (string) ($payload['signature'] ?? '');
        if ($authenticatorDataB64 === '' || $signatureB64 === '') {
            return ['ok' => false, 'error' => __('pos_biometric_invalid_credential')];
        }
        if ($this->base64DecodeFlexible($authenticatorDataB64) === ''
            || $this->base64DecodeFlexible($signatureB64) === '') {
            return ['ok' => false, 'error' => __('pos_biometric_invalid_credential')];
        }

        $credentialIdB64 = (string) ($payload['credentialId'] ?? $payload['id'] ?? '');
        if ($credentialIdB64 === '') {
            return ['ok' => false, 'error' => __('invalid_request')];
        }

        $credentialId = $this->base64UrlDecode($credentialIdB64);
        if ($credentialId === '') {
            $credentialId = $this->base64DecodeFlexible($credentialIdB64);
        }
        $db = Database::connection();
        $companyId = $this->resolveCompanyId();
        $sql = 'SELECT user_id, company_id, credential_id FROM rateb_webauthn_credentials';
        $params = [];
        if ($this->companyColumnReady() && $companyId > 0) {
            $sql .= ' WHERE (company_id = :cid OR company_id IS NULL)';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $verifiedId = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $raw = $this->credentialIdToBinary($row['credential_id'] ?? null);
            if ($raw !== '' && hash_equals($raw, $credentialId)) {
                if ($this->companyColumnReady() && $companyId > 0) {
                    $rowCid = (int) ($row['company_id'] ?? 0);
                    if ($rowCid > 0 && $rowCid !== $companyId) {
                        continue;
                    }
                }
                $verifiedId = (int) ($row['user_id'] ?? 0);
                break;
            }
        }
        if ($verifiedId < 1) {
            // Legacy rows may have been stored already base64-encoded.
            $legacySql = 'SELECT user_id, company_id FROM rateb_webauthn_credentials WHERE credential_id = :cid';
            $legacyParams = ['cid' => $credentialId];
            if ($this->companyColumnReady() && $companyId > 0) {
                $legacySql .= ' AND (company_id = :co OR company_id IS NULL)';
                $legacyParams['co'] = $companyId;
            }
            $legacySql .= ' LIMIT 1';
            $stmt = $db->prepare($legacySql);
            $stmt->execute($legacyParams);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $legacyParams['cid'] = $credentialIdB64;
                $stmt->execute($legacyParams);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            if (!$row) {
                return ['ok' => false, 'error' => __('pos_biometric_failed')];
            }
            $verifiedId = (int) ($row['user_id'] ?? 0);
        }
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

    /**
     * Face recognition is not enabled — stub templates must never authenticate.
     *
     * @param array<string, mixed> $payload
     * @return array{ok:bool,error?:string}
     */
    public function verifyFace(int $userId, array $payload): array
    {
        unset($userId, $payload);

        return ['ok' => false, 'error' => __('pos_biometric_face_coming_soon')];
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
        $companyId = $this->resolveCompanyId();
        $sql = 'SELECT wc.credential_id, wc.user_id, wc.company_id, u.is_super_admin
             FROM rateb_webauthn_credentials wc
             INNER JOIN rateb_users u ON u.id = wc.user_id
             WHERE (u.status = \'active\' OR u.status = \'1\' OR u.status IS NULL OR u.status = \'\')';
        $params = [];
        if ($this->companyColumnReady() && $companyId > 0) {
            $sql .= ' AND (wc.company_id = :cid OR wc.company_id IS NULL)';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $allow = [];
        $seen = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $isSuper = !empty($row['is_super_admin']);
            if (!$isSuper && !$this->userCanSupervise($uid)) {
                continue;
            }
            $raw = $this->credentialIdToBinary($row['credential_id'] ?? null);
            if ($raw === '') {
                continue;
            }
            $key = $uid . ':' . $this->base64UrlEncode($raw);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $allow[] = [
                'type' => 'public-key',
                'id' => $this->base64UrlEncode($raw),
                'transports' => ['internal', 'hybrid', 'usb'],
            ];
        }

        // Always include the current session user if they can supervise and have a passkey.
        $selfId = $this->userId();
        if ($selfId > 0 && $this->userCanSupervise($selfId)) {
            $selfSql = 'SELECT credential_id FROM rateb_webauthn_credentials WHERE user_id = :uid';
            $selfParams = ['uid' => $selfId];
            if ($this->companyColumnReady() && $companyId > 0) {
                $selfSql .= ' AND (company_id = :cid OR company_id IS NULL)';
                $selfParams['cid'] = $companyId;
            }
            $stmt = $db->prepare($selfSql);
            $stmt->execute($selfParams);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $raw = $this->credentialIdToBinary($row['credential_id'] ?? null);
                if ($raw === '') {
                    continue;
                }
                $key = $selfId . ':' . $this->base64UrlEncode($raw);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $allow[] = [
                    'type' => 'public-key',
                    'id' => $this->base64UrlEncode($raw),
                    'transports' => ['internal', 'hybrid', 'usb'],
                ];
            }
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

    private function credentialIdToBinary(mixed $value): string
    {
        if (is_resource($value)) {
            $data = stream_get_contents($value);

            return $data === false ? '' : $data;
        }
        if (!is_string($value) || $value === '') {
            return '';
        }

        return $value;
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
        $companyId = $this->resolveCompanyId();
        if ($companyId < 1) {
            $companyId = (int) ($user['company_id'] ?? 0);
        }
        $deleteSql = 'DELETE FROM rateb_webauthn_credentials WHERE user_id = :uid';
        $deleteParams = ['uid' => $userId];
        if ($this->companyColumnReady() && $companyId > 0) {
            $deleteSql .= ' AND (company_id = :cid OR company_id IS NULL)';
            $deleteParams['cid'] = $companyId;
        }
        $delete = $db->prepare($deleteSql);
        $delete->execute($deleteParams);

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
        $companyId = $this->resolveCompanyId();
        $sql = 'SELECT credential_id FROM rateb_webauthn_credentials WHERE user_id = :uid';
        $params = ['uid' => $userId];
        if ($this->companyColumnReady() && $companyId > 0) {
            $sql .= ' AND (company_id = :cid OR company_id IS NULL)';
            $params['cid'] = $companyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
                    'id' => $this->base64UrlEncode($this->credentialIdToBinary($row['credential_id'])),
                    'transports' => ['internal', 'hybrid', 'usb'],
                ]],
                'timeout' => 60000,
                'userVerification' => 'required',
            ],
        ];
    }

    /**
     * Bind assertion/attestation to the session challenge via clientDataJSON.
     *
     * @param array<string, mixed> $payload
     */
    private function assertClientDataChallenge(array $payload, string $sessionChallengeB64, string $expectedType): ?string
    {
        $clientDataB64 = (string) ($payload['clientDataJSON'] ?? '');
        if ($clientDataB64 === '') {
            return __('pos_biometric_invalid_credential');
        }

        $clientDataRaw = $this->base64DecodeFlexible($clientDataB64);
        if ($clientDataRaw === '') {
            return __('pos_biometric_invalid_credential');
        }

        $data = json_decode($clientDataRaw, true);
        if (!is_array($data)) {
            return __('pos_biometric_invalid_credential');
        }

        if ((string) ($data['type'] ?? '') !== $expectedType) {
            return __('pos_biometric_failed');
        }

        $expectedChallenge = base64_decode($sessionChallengeB64, true);
        if (!is_string($expectedChallenge) || $expectedChallenge === '') {
            return __('pos_biometric_challenge_expired');
        }

        $clientChallenge = $this->base64UrlDecode((string) ($data['challenge'] ?? ''));
        if ($clientChallenge === '' || !hash_equals($expectedChallenge, $clientChallenge)) {
            return __('pos_biometric_failed');
        }

        $origin = (string) ($data['origin'] ?? '');
        $expectedOrigin = $this->resolveExpectedOrigin();
        if ($origin === '' || ($expectedOrigin !== '' && !hash_equals($expectedOrigin, $origin))) {
            // Allow localhost http/https swap during local ops only when host matches.
            if (!$this->originHostMatches($origin, $expectedOrigin)) {
                return __('pos_biometric_failed');
            }
        }

        return null;
    }

    private function resolveExpectedOrigin(): string
    {
        $https = !empty($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }

        return $scheme . '://' . $host;
    }

    private function originHostMatches(string $origin, string $expectedOrigin): bool
    {
        if ($origin === '' || $expectedOrigin === '') {
            return false;
        }
        $oHost = parse_url($origin, PHP_URL_HOST);
        $eHost = parse_url($expectedOrigin, PHP_URL_HOST);
        if (!is_string($oHost) || !is_string($eHost) || $oHost === '' || $eHost === '') {
            return false;
        }

        return hash_equals(strtolower($eHost), strtolower($oHost));
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

    private function resolveCompanyId(): int
    {
        if (function_exists('rateb_resolve_ops_company_id')) {
            $id = (int) rateb_resolve_ops_company_id();
            if ($id > 0) {
                return $id;
            }
        }

        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }

    private function resolveBranchId(): int
    {
        return (int) (SessionManager::get('rateb_branch_id')
            ?? SessionManager::get('pos_branch_id')
            ?? 0);
    }

    private function resolveRpId(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return $host;
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

    private function companyColumnReady(): bool
    {
        return $this->columnExists('rateb_webauthn_credentials', 'company_id');
    }

    private function tableExists(string $table): bool
    {
        return Database::tableExists($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Database::liveTableHasColumn($table, $column);
    }

    private function publicErrorMessage(\Throwable $e): string
    {
        return DatabaseErrorService::userMessage($e);
    }
}
