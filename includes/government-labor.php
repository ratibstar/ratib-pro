<?php
/**
 * Government Labor Monitoring — shared checks (deploy block, profile flags, dashboard counts).
 */
require_once __DIR__ . '/../api/core/ensure-government-labor-schema.php';

/**
 * @return string|null Block reason, or null if deployment is allowed
 */
function ratib_government_deploy_block_reason_pdo(PDO $conn, int $workerId): ?string
{
    if ($workerId <= 0) {
        return null;
    }
    ratibEnsureGovernmentLaborSchema($conn);
    $st = $conn->prepare(
        "SELECT reason FROM gov_blacklist
         WHERE entity_type = 'worker' AND entity_id = ? AND status = 'active' LIMIT 1"
    );
    $st->execute([$workerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['reason'])) {
        return 'Worker is on government blacklist: ' . trim((string) $row['reason']);
    }
    return null;
}

/**
 * @return array<int, array{type:string,message:string,severity:string}>
 */
function ratib_government_worker_alerts_pdo(PDO $conn, int $workerId): array
{
    if ($workerId <= 0) {
        return [];
    }
    ratibEnsureGovernmentLaborSchema($conn);
    $alerts = [];

    $st = $conn->prepare(
        "SELECT reason FROM gov_blacklist
         WHERE entity_type = 'worker' AND entity_id = ? AND status = 'active' LIMIT 1"
    );
    $st->execute([$workerId]);
    $bl = $st->fetch(PDO::FETCH_ASSOC);
    if ($bl && trim((string) ($bl['reason'] ?? '')) !== '') {
        $alerts[] = [
            'type' => 'blacklist',
            'message' => 'Government blacklist: ' . trim((string) $bl['reason']),
            'severity' => 'high',
        ];
    }

    $st2 = $conn->prepare(
        "SELECT id, inspection_date, notes FROM gov_inspections
         WHERE worker_id = ? AND status = 'failed'
         ORDER BY inspection_date DESC, id DESC LIMIT 3"
    );
    $st2->execute([$workerId]);
    while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
        $alerts[] = [
            'type' => 'inspection_failed',
            'message' => 'Failed inspection on ' . ($r['inspection_date'] ?? '') . (isset($r['notes']) && $r['notes'] !== '' ? (' — ' . substr((string) $r['notes'], 0, 200)) : ''),
            'severity' => 'high',
        ];
    }

    $st3 = $conn->prepare(
        "SELECT status, location_text, last_checkin FROM gov_worker_tracking WHERE worker_id = ? LIMIT 1"
    );
    $st3->execute([$workerId]);
    $tr = $st3->fetch(PDO::FETCH_ASSOC);
    if ($tr && ($tr['status'] ?? '') === 'alert') {
        $alerts[] = [
            'type' => 'tracking_alert',
            'message' => 'Worker monitoring status: ALERT' . (!empty($tr['location_text']) ? (' (' . $tr['location_text'] . ')') : ''),
            'severity' => 'high',
        ];
    }

    return $alerts;
}

/**
 * Summary counts + lightweight alert lines for control dashboard (same PDO / tenant as workers).
 *
 * @return array{totals:array,alerts:array<int,array<string,mixed>>}
 */
function ratib_government_dashboard_summary_pdo(PDO $conn): array
{
    ratibEnsureGovernmentLaborSchema($conn);
    $totals = [
        'violations' => 0,
        'blacklist_active' => 0,
        'workers_alert' => 0,
        'inspections_failed_pending' => 0,
    ];
    try {
        $totals['violations'] = (int) $conn->query('SELECT COUNT(*) FROM gov_violations')->fetchColumn();
        $totals['blacklist_active'] = (int) $conn->query(
            "SELECT COUNT(*) FROM gov_blacklist WHERE status = 'active'"
        )->fetchColumn();
        $totals['workers_alert'] = (int) $conn->query(
            "SELECT COUNT(*) FROM gov_worker_tracking WHERE status = 'alert'"
        )->fetchColumn();
        $totals['inspections_failed_pending'] = (int) $conn->query(
            "SELECT COUNT(*) FROM gov_inspections WHERE status IN ('failed','pending')"
        )->fetchColumn();
    } catch (Throwable $e) {
        // leave zeros
    }

    $alerts = [];
    try {
        $sql = "
            SELECT 'worker_blacklist' AS kind, b.entity_id AS worker_id, b.reason AS msg, w.worker_name AS name
            FROM gov_blacklist b
            LEFT JOIN workers w ON w.id = b.entity_id AND b.entity_type = 'worker'
            WHERE b.status = 'active' AND b.entity_type = 'worker'
            ORDER BY b.id DESC LIMIT 8
        ";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $alerts[] = [
                'kind' => 'blacklist',
                'title' => 'Blacklisted worker',
                'detail' => trim(($row['name'] ?? '') . ' — ' . ($row['msg'] ?? '')),
                'worker_id' => (int) ($row['worker_id'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $sql = "
            SELECT t.worker_id, t.location_text, t.last_checkin, w.worker_name
            FROM gov_worker_tracking t
            LEFT JOIN workers w ON w.id = t.worker_id
            WHERE t.status = 'alert'
            ORDER BY t.last_checkin IS NULL, t.last_checkin DESC
            LIMIT 8
        ";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $alerts[] = [
                'kind' => 'tracking',
                'title' => 'Worker monitoring alert',
                'detail' => trim(($row['worker_name'] ?? ('Worker #' . $row['worker_id'])) . ' — ' . ($row['location_text'] ?? '')),
                'worker_id' => (int) ($row['worker_id'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $sql = "
            SELECT i.worker_id, i.inspection_date, i.inspector_name, w.worker_name
            FROM gov_inspections i
            LEFT JOIN workers w ON w.id = i.worker_id
            WHERE i.status = 'failed'
            ORDER BY i.inspection_date DESC, i.id DESC
            LIMIT 8
        ";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $alerts[] = [
                'kind' => 'inspection',
                'title' => 'Failed inspection',
                'detail' => trim(($row['worker_name'] ?? ('Worker #' . $row['worker_id'])) . ' — ' . ($row['inspection_date'] ?? '') . ' (' . ($row['inspector_name'] ?? '') . ')'),
                'worker_id' => (int) ($row['worker_id'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        // ignore
    }

    return ['totals' => $totals, 'alerts' => $alerts];
}
