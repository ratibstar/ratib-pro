<?php
declare(strict_types=1);

/**
 * Record Global AI onboarding in agency DB without App\ autoloader (fallback when app/ deploy lags).
 *
 * @return int|null workflow id
 */
function ratib_global_ai_record_workflow(PDO $agencyPdo, int $workerId, array $payload): ?int
{
    if ($workerId <= 0) {
        return null;
    }

    ratib_global_ai_ensure_workflow_schema($agencyPdo);

    $context = [
        'worker_id' => $workerId,
        'onboarding_source' => 'global_ai_tracking_fallback',
        'tracking' => $payload['tracking'] ?? null,
        'notify_to' => $payload['notify_to'] ?? null,
    ];
    $worker = $payload['worker'] ?? null;
    if (is_array($worker)) {
        $context['worker'] = $worker;
    }

    $stmt = $agencyPdo->prepare(
        'INSERT INTO workflows (name, context_json, status, created_at, updated_at)
         VALUES (:name, :context_json, :status, NOW(), NOW())'
    );
    $stmt->execute([
        ':name' => 'WorkerOnboardingWorkflow',
        ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => 'completed',
    ]);
    $workflowId = (int) $agencyPdo->lastInsertId();
    if ($workflowId <= 0) {
        return null;
    }

    $context['workflow_id'] = $workflowId;
    $upd = $agencyPdo->prepare(
        'UPDATE workflows SET context_json = :context_json, status = :status, updated_at = NOW() WHERE id = :id'
    );
    $upd->execute([
        ':id' => $workflowId,
        ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => 'completed',
    ]);

    return $workflowId;
}

function ratib_global_ai_ensure_workflow_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            context_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            failed_step VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_workflows_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Open agency PDO for tenant_id via control_agencies (used by tracking onboarding fallback).
 */
function ratib_global_ai_agency_pdo_for_tenant(PDO $controlPdo, int $tenantId): ?PDO
{
    if ($tenantId <= 0) {
        return null;
    }
    $st = $controlPdo->prepare(
        "SELECT db_host, db_port, db_user, db_pass, db_name
         FROM control_agencies
         WHERE is_active = 1 AND tenant_id = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $st->execute([$tenantId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || trim((string) ($row['db_name'] ?? '')) === '') {
        return null;
    }
    $dbHost = trim((string) ($row['db_host'] ?? ''));
    $dbPort = (int) ($row['db_port'] ?? 3306);
    $dbUser = (string) ($row['db_user'] ?? '');
    $dbPass = (string) ($row['db_pass'] ?? '');
    $dbName = trim((string) ($row['db_name'] ?? ''));
    if ($dbHost === '') {
        $dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    }
    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort > 0 ? $dbPort : 3306, $dbName),
            $dbUser !== '' ? $dbUser : (defined('DB_USER') ? (string) DB_USER : ''),
            $dbPass !== '' ? $dbPass : (defined('DB_PASS') ? (string) DB_PASS : ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $e) {
        error_log('ratib_global_ai_agency_pdo_for_tenant: ' . $e->getMessage());
        return null;
    }
}
