<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\AuditLog;
use Rateb\App\Models\LoginActivity;

final class AuditService
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $payload = null): void
    {
        $userId = $_SESSION['rateb_user_id'] ?? null;
        $companyId = $_SESSION['rateb_company_id'] ?? null;

        (new AuditLog())->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /** HQ-reviewable audit for inter-branch transfers (old/new data, session, IP). */
    public function logTransfer(
        string $action,
        array $transfer,
        ?array $oldData,
        ?array $newData,
        ?int $userId = null,
        ?array $extra = null
    ): void {
        $companyId = (int) ($transfer['company_id'] ?? ($_SESSION['rateb_company_id'] ?? 0));
        $payload = [
            'transfer_no' => $transfer['transfer_no'] ?? null,
            'transfer_type' => $transfer['transfer_type'] ?? null,
            'source_branch_id' => (int) ($transfer['source_branch_id'] ?? 0),
            'dest_branch_id' => (int) ($transfer['dest_branch_id'] ?? 0),
            'old' => $oldData,
            'new' => $newData,
            'session_id' => session_id() ?: null,
            'executed_at' => date('c'),
        ];
        if ($extra !== null) {
            $payload = array_merge($payload, $extra);
        }
        (new AuditLog())->create([
            'company_id' => $companyId > 0 ? $companyId : null,
            'user_id' => $userId ?? ($_SESSION['rateb_user_id'] ?? null),
            'action' => $action,
            'entity_type' => 'branch_transfer',
            'entity_id' => (int) ($transfer['id'] ?? 0),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

final class LoginActivityService
{
    public function record(?int $userId, string $email, bool $success): void
    {
        (new LoginActivity())->create([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'success' => $success ? 1 : 0,
        ]);
    }
}
