<?php
declare(strict_types=1);

/**
 * One-shot Global AI: workflow record + tracking device (no App\Core autoloader).
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function ratib_global_ai_run(array $payload): array
{
    $root = dirname(__DIR__);
    require_once $root . '/includes/worker_onboarding_workflow_legacy.php';
    require_once $root . '/api/core/Database.php';
    require_once $root . '/api/core/ensure-worker-tracking-schema.php';
    require_once $root . '/admin/core/EventBus.php';

    $workerPayload = is_array($payload['worker'] ?? null) ? $payload['worker'] : [];
    $name = trim((string) ($workerPayload['name'] ?? $workerPayload['worker_name'] ?? $workerPayload['full_name'] ?? ''));
    $passport = trim((string) ($workerPayload['passport_number'] ?? ''));
    $workerId = (int) ($payload['worker_id'] ?? $workerPayload['worker_id'] ?? $workerPayload['id'] ?? 0);

    if ($name === '' || $passport === '') {
        throw new InvalidArgumentException('worker.name and worker.passport_number are required.');
    }
    if ($workerId <= 0) {
        throw new InvalidArgumentException('worker_id is required.');
    }

    $payload['worker'] = array_merge($workerPayload, [
        'worker_id' => $workerId,
        'id' => $workerId,
        'name' => $name,
        'passport_number' => $passport,
    ]);

    $agencyPdo = Database::getInstance()->getConnection();
    $chk = $agencyPdo->prepare("SELECT id FROM workers WHERE id = :id AND COALESCE(status, '') <> 'deleted' LIMIT 1");
    $chk->execute([':id' => $workerId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Worker not found in agency database.', 404);
    }

    $workflowId = ratib_global_ai_record_workflow($agencyPdo, $workerId, $payload);
    if ($workflowId === null || $workflowId <= 0) {
        throw new RuntimeException('Could not record workflow.', 503);
    }

    $tracking = ['success' => false, 'message' => 'Tracking not provisioned'];
    $tenantId = (int) ($payload['tenant_id'] ?? 0);
    $controlPdo = getControlDB();

    if ($tenantId <= 0) {
        $agencyId = (int) ($_SESSION['agency_id'] ?? $_SESSION['control_agency_id'] ?? 0);
        if ($agencyId > 0) {
            $st = $controlPdo->prepare('SELECT tenant_id FROM control_agencies WHERE id = ? LIMIT 1');
            $st->execute([$agencyId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $tenantId = (int) ($row['tenant_id'] ?? 0);
        }
    }
    if ($tenantId <= 0) {
        $resolved = ratib_global_ai_resolve_tenant_for_worker($controlPdo, $workerId);
        $tenantId = (int) ($resolved['tenant_id'] ?? 0);
    }

    if ($tenantId > 0) {
        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        if ($deviceId === '') {
            $deviceId = 'dev-' . bin2hex(random_bytes(8));
        }
        $token = trim((string) ($payload['api_token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
        }
        ratibEnsureWorkerTrackingSchema($controlPdo);
        $st2 = $controlPdo->prepare(
            "INSERT INTO worker_tracking_devices
             (worker_id, tenant_id, device_id, worker_identity, worker_password_hash, api_token, is_active, last_seen, created_at, updated_at)
             VALUES (?, ?, ?, NULL, NULL, ?, 1, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                api_token = VALUES(api_token),
                is_active = 1,
                updated_at = NOW()"
        );
        $st2->execute([$workerId, $tenantId, $deviceId, $token]);
        $tracking = [
            'success' => true,
            'data' => [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'device_id' => $deviceId,
                'api_token' => $token,
                'workflow_id' => (string) $workflowId,
            ],
        ];
    }

    $trackingOk = !empty($tracking['success']);
    return [
        'success' => true,
        'tracking_ok' => $trackingOk,
        'workflow_ok' => true,
        'workflow_id' => (string) $workflowId,
        'worker_id' => $workerId,
        'tenant_id' => $trackingOk ? (string) ($tracking['data']['tenant_id'] ?? '') : '',
        'device_id' => $trackingOk ? (string) ($tracking['data']['device_id'] ?? '') : '',
        'tracking_message' => $trackingOk ? 'Tracking onboarding completed.' : (string) ($tracking['message'] ?? 'Tracking skipped'),
        'workflow_message' => 'Worker onboarding workflow completed.',
        'handler' => 'global_ai_run',
        'build' => 'global-ai-run-20260516-v5',
    ];
}

/**
 * @return array{tenant_id?: int, agency_id?: int}|null
 */
function ratib_global_ai_resolve_tenant_for_worker(PDO $controlPdo, int $workerId): ?array
{
    $st = $controlPdo->prepare(
        "SELECT id, tenant_id, db_host, db_port, db_user, db_pass, db_name
         FROM control_agencies
         WHERE is_active = 1 AND tenant_id IS NOT NULL AND tenant_id > 0 AND db_name IS NOT NULL AND db_name <> ''
         ORDER BY id ASC LIMIT 300"
    );
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
        $tenantId = (int) ($a['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            continue;
        }
        $agencyPdo = ratib_global_ai_agency_pdo_for_tenant($controlPdo, $tenantId);
        if (!$agencyPdo instanceof PDO) {
            continue;
        }
        $w = $agencyPdo->prepare('SELECT id FROM workers WHERE id = :id AND COALESCE(status, \'\') <> \'deleted\' LIMIT 1');
        $w->execute([':id' => $workerId]);
        if ($w->fetch(PDO::FETCH_ASSOC)) {
            return ['tenant_id' => $tenantId, 'agency_id' => (int) ($a['id'] ?? 0)];
        }
    }
    return null;
}
