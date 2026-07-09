<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;

/** Supervisor biometric approval — request/grant/consume single-use tokens (60s TTL). */
final class PosSupervisorApprovalService
{
    private const TOKEN_TTL_SECONDS = 60;

    /** @param array<string, mixed> $payload */
    public function createRequest(
        int $companyId,
        int $requestedBy,
        string $actionType,
        array $payload,
        ?int $registerSessionId = null
    ): int {
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

    public function grantRequest(int $requestId, int $supervisorUserId, string $method = 'webauthn'): ?string
    {
        $authz = new \Rateb\App\Services\AuthorizationService();
        $canSupervise = $authz->userHasPermission($supervisorUserId, 'pos.supervisor.approve')
            || $authz->userHasPermission($supervisorUserId, 'pos.discount.manage')
            || $authz->userHasPermission($supervisorUserId, 'pos.returns.manage');
        if (!$canSupervise) {
            return null;
        }

        $db = Database::connection();
        $req = $db->prepare('SELECT id, status FROM rateb_pos_approval_requests WHERE id = :id LIMIT 1');
        $req->execute(['id' => $requestId]);
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

        $upd = $db->prepare('UPDATE rateb_pos_approval_requests SET status = :st WHERE id = :id');
        $upd->execute(['st' => 'granted', 'id' => $requestId]);

        return $token;
    }

    public function consumeToken(string $token, string $actionType): bool
    {
        if ($token === '') {
            return false;
        }
        $hash = hash('sha256', $token);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT g.id AS grant_id, g.request_id, g.expires_at, g.consumed_at, r.action_type, r.status
             FROM rateb_pos_approval_grants g
             INNER JOIN rateb_pos_approval_requests r ON r.id = g.request_id
             WHERE g.token_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
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

        $db->prepare('UPDATE rateb_pos_approval_grants SET consumed_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $row['grant_id']]);
        $db->prepare('UPDATE rateb_pos_approval_requests SET status = :st, consumed_at = NOW() WHERE id = :id')
            ->execute(['st' => 'consumed', 'id' => (int) $row['request_id']]);

        return true;
    }

    public function approvalTokenFromRequest(): string
    {
        $header = (string) ($_SERVER['HTTP_X_POS_APPROVAL_TOKEN'] ?? '');

        return trim($header);
    }

    public function requireApprovalOrAbort(string $actionType): void
    {
        $token = $this->approvalTokenFromRequest();
        if ($token === '' || !$this->consumeToken($token, $actionType)) {
            \Rateb\App\Core\Response::json([
                'ok' => false,
                'error' => __('pos_supervisor_approval_required'),
                'approval_required' => true,
            ], 403);
            exit;
        }
    }
}
