<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuthorizationService;

/** Supervisor biometric approval — request/grant/consume single-use tokens (60s TTL). */
final class PosSupervisorApprovalService
{
    private const TOKEN_TTL_SECONDS = 60;
    private static bool $schemaReady = false;

    /** @param array<string, mixed> $payload */
    public function createRequest(
        int $companyId,
        int $requestedBy,
        string $actionType,
        array $payload,
        ?int $registerSessionId = null
    ): int {
        $this->ensureSchema();
        if ($companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_pos_approval_requests (company_id, register_session_id, action_type, payload_json, requested_by, status)
             VALUES (:cid, :sid, :action, :payload, :uid, :status)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'sid' => $registerSessionId,
            'action' => $actionType,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'uid' => $requestedBy,
            'status' => 'pending',
        ]);

        return (int) $db->lastInsertId();
    }

    public function grantRequest(
        int $requestId,
        int $supervisorUserId,
        int $companyId,
        string $method = 'webauthn'
    ): ?string
    {
        $this->ensureSchema();
        if ($companyId < 1 || !$this->userCanSupervise($supervisorUserId, $companyId)) {
            return null;
        }

        $db = Database::connection();
        $req = $db->prepare(
            'SELECT id, status FROM rateb_pos_approval_requests
             WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $req->execute(['id' => $requestId, 'cid' => $companyId]);
        $row = $req->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'pending') {
            return null;
        }

        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $ins = $db->prepare(
            'INSERT INTO rateb_pos_approval_grants (request_id, supervisor_user_id, biometric_method, token_hash, expires_at)
             VALUES (:rid, :sup, :method, :hash, :exp)'
        );
        $ins->execute([
            'rid' => $requestId,
            'sup' => $supervisorUserId,
            'method' => $method,
            'hash' => $hash,
            'exp' => $expires,
        ]);

        $upd = $db->prepare(
            'UPDATE rateb_pos_approval_requests SET status = :st
             WHERE id = :id AND company_id = :cid'
        );
        $upd->execute(['st' => 'granted', 'id' => $requestId, 'cid' => $companyId]);

        return $token;
    }

    public function consumeToken(string $token, string $actionType, int $companyId): bool
    {
        if ($token === '' || $companyId < 1) {
            return false;
        }
        $this->ensureSchema();
        $hash = hash('sha256', $token);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT g.id AS grant_id, g.request_id, g.expires_at, g.consumed_at, r.action_type, r.status
             FROM rateb_pos_approval_grants g
             INNER JOIN rateb_pos_approval_requests r ON r.id = g.request_id
             WHERE g.token_hash = :hash AND r.company_id = :cid LIMIT 1'
        );
        $stmt->execute(['hash' => $hash, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ((string) ($row['action_type'] ?? '') !== $actionType) {
            return false;
        }
        if (!empty($row['consumed_at'])) {
            return false;
        }
        if (strtotime((string) ($row['expires_at'] ?? '')) < time()) {
            return false;
        }
        if ((string) ($row['status'] ?? '') !== 'granted') {
            return false;
        }

        $consume = $db->prepare(
            'UPDATE rateb_pos_approval_grants SET consumed_at = NOW()
             WHERE id = :id AND consumed_at IS NULL AND expires_at >= NOW()'
        );
        $consume->execute(['id' => (int) $row['grant_id']]);
        if ($consume->rowCount() !== 1) {
            return false;
        }
        $db->prepare(
            'UPDATE rateb_pos_approval_requests SET status = :st, consumed_at = NOW()
             WHERE id = :id AND company_id = :cid AND status = :granted'
        )->execute([
            'st' => 'consumed',
            'id' => (int) $row['request_id'],
            'cid' => $companyId,
            'granted' => 'granted',
        ]);

        return true;
    }

    public function approvalTokenFromRequest(): string
    {
        $header = (string) ($_SERVER['HTTP_X_POS_APPROVAL_TOKEN'] ?? '');

        return trim($header);
    }

    public function requireApprovalOrAbort(string $actionType, int $companyId): void
    {
        $token = $this->approvalTokenFromRequest();
        if ($token === '' || !$this->consumeToken($token, $actionType, $companyId)) {
            \Rateb\App\Core\Response::json([
                'ok' => false,
                'error' => __('pos_supervisor_approval_required'),
                'approval_required' => true,
            ], 403);
            exit;
        }
    }

    private function userCanSupervise(int $userId, int $companyId): bool
    {
        if ($userId < 1 || $companyId < 1) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            'SELECT company_id, is_super_admin FROM rateb_users WHERE id = :uid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        if (!empty($user['is_super_admin'])) {
            return true;
        }
        if ((int) ($user['company_id'] ?? 0) !== $companyId) {
            return false;
        }
        $authz = new AuthorizationService();
        if ($authz->userHasPermission($userId, 'pos.supervisor.approve')
            || $authz->userHasPermission($userId, 'pos.discount.manage')
            || $authz->userHasPermission($userId, 'pos.returns.manage')) {
            return true;
        }

        return false;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $db = Database::connection();
        $db->exec(
            'CREATE TABLE IF NOT EXISTS rateb_pos_approval_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_id INT UNSIGNED NOT NULL,
                register_session_id BIGINT UNSIGNED NULL,
                action_type VARCHAR(64) NOT NULL,
                payload_json JSON NOT NULL,
                requested_by INT UNSIGNED NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                consumed_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_pos_approval_company (company_id, status, created_at),
                KEY idx_pos_approval_requester (requested_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS rateb_pos_approval_grants (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id BIGINT UNSIGNED NOT NULL,
                supervisor_user_id INT UNSIGNED NOT NULL,
                biometric_method VARCHAR(32) NOT NULL DEFAULT \'webauthn\',
                token_hash CHAR(64) NOT NULL,
                verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pos_approval_token_hash (token_hash),
                KEY idx_pos_approval_grant_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        // Legacy column from an older schema; must not block token_hash-only inserts.
        try {
            $col = $db->query("SHOW COLUMNS FROM rateb_pos_approval_grants LIKE 'approval_token'");
            if ($col !== false && $col->fetch() !== false) {
                $db->exec('ALTER TABLE rateb_pos_approval_grants MODIFY approval_token CHAR(64) NULL');
            }
            if ($col instanceof \PDOStatement) {
                $col->closeCursor();
            }
        } catch (\Throwable) {
            // Best-effort; grant insert still uses token_hash only.
        }
        self::$schemaReady = true;
    }
}
