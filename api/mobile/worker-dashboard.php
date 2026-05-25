<?php
/**
 * Worker dashboard summary — profile + task stats.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';

try {
    $claims = rateb_mobile_require_auth('worker');
    $pdo = rateb_mobile_pdo();
    $profile = rateb_mobile_staff_profile($pdo, $claims);
    $worker = rateb_mobile_resolve_worker($pdo, $claims);

    $pendingTasks = 0;
    $dueToday = 0;

    if ($worker !== null) {
        $workerId = (int) $worker['id'];
        $status = strtolower((string) ($worker['status'] ?? 'pending'));
        if (in_array($status, ['pending', 'approved'], true)) {
            $pendingTasks++;
            $dueToday++;
        }
        if ($status === 'pending') {
            $pendingTasks++;
        }

        $docStmt = $pdo->prepare(
            "SELECT COUNT(*) AS missing
             FROM (
                 SELECT 'passport' AS doc_key
                 UNION SELECT 'visa'
                 UNION SELECT 'medical'
             ) required_docs
             LEFT JOIN worker_documents wd
               ON wd.worker_id = ? AND wd.document_type = required_docs.doc_key
             WHERE wd.id IS NULL"
        );
        try {
            $docStmt->execute([$workerId]);
            $missing = (int) ($docStmt->fetchColumn() ?: 0);
            $pendingTasks += $missing;
            if ($missing > 0) {
                $dueToday = max($dueToday, 1);
            }
        } catch (Throwable $docErr) {
            // worker_documents table may not exist on all installs.
            if ($status === 'pending') {
                $pendingTasks = max($pendingTasks, 2);
                $dueToday = max($dueToday, 1);
            }
        }

        $deployStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM worker_deployments
             WHERE worker_id = ? AND status IN ('processing', 'issue')"
        );
        try {
            $deployStmt->execute([$workerId]);
            $openDeployments = (int) ($deployStmt->fetchColumn() ?: 0);
            $pendingTasks += $openDeployments;
        } catch (Throwable $deployErr) {
            // ignore
        }
    } else {
        $pendingTasks = 2;
        $dueToday = 1;
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'profile' => $profile,
            'worker' => $worker ? [
                'id' => (int) $worker['id'],
                'name' => (string) ($worker['worker_name'] ?? ''),
                'status' => (string) ($worker['status'] ?? ''),
                'passport_number' => (string) ($worker['passport_number'] ?? ''),
            ] : null,
            'stats' => [
                'pending_tasks' => $pendingTasks,
                'due_today' => $dueToday,
                'has_worker_record' => $worker !== null,
                'documents_pending' => $worker !== null && $pendingTasks > 0,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Dashboard unavailable'], 500);
}
