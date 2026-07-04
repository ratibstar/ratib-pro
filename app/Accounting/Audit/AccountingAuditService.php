<?php
declare(strict_types=1);

namespace App\Accounting\Audit;

use App\Accounting\Infrastructure\AccountingConnectionFactory;
use App\Accounting\Support\AccountingConfig;

final class AccountingAuditService
{
    public function __construct(
        private readonly ?\PDO $pdo = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::auditEnabled();
    }

    private function connection(): ?\PDO
    {
        return $this->pdo ?? AccountingConnectionFactory::pdo();
    }

    public function tableExists(): bool
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'accounting_audit_logs'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        string $system,
        string $status,
        ?string $eventUuid = null,
        array $metadata = []
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO accounting_audit_logs (event_uuid, action, system, status, metadata, created_at)
                 VALUES (:uuid, :action, :system, :status, :metadata, NOW())'
            );
            $stmt->execute([
                'uuid' => $eventUuid,
                'action' => $action,
                'system' => $system,
                'status' => $status,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('AccountingAuditService::log failed: ' . $e->getMessage());
        }
    }
}
