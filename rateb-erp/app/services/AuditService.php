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
