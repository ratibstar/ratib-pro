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
    // No task source carries a due date, so nothing is reported as due today.
    $dueToday = 0;
    $missingDocs = 0;

    if ($worker !== null) {
        $workerId = (int) $worker['id'];

        try {
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
            $docStmt->execute([$workerId]);
            $missingDocs = (int) ($docStmt->fetchColumn() ?: 0);
            $pendingTasks += $missingDocs;
        } catch (Throwable $docErr) {
            // worker_documents table may not exist on all installs.
        }

        try {
            $deployStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM worker_deployments
                 WHERE worker_id = ? AND status IN ('processing', 'issue')"
            );
            $deployStmt->execute([$workerId]);
            $openDeployments = (int) ($deployStmt->fetchColumn() ?: 0);
            $pendingTasks += $openDeployments;
        } catch (Throwable $deployErr) {
            // ignore
        }
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
                'documents_pending' => $missingDocs > 0,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Dashboard unavailable'], 500);
}
