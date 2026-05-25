<?php
/**
 * Company recruitment / case requests for mobile portal.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';

try {
    $claims = rateb_mobile_require_auth('company');
    $pdo = rateb_mobile_pdo();

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 50;
    $offset = ($page - 1) * $limit;

    $requests = [];
    $total = 0;
    $openCount = 0;

    try {
        $caseWhere = ['1=1'];
        $caseParams = [];
        rateb_mobile_apply_cases_tenant_scope($pdo, $claims, 'c', $caseWhere, $caseParams);
        $caseWhereSql = implode(' AND ', $caseWhere);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cases c WHERE {$caseWhereSql}");
        $countStmt->execute($caseParams);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $openStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM cases c
             WHERE {$caseWhereSql}
             AND c.status IN ('open', 'pending', 'in_progress', 'new')"
        );
        $openStmt->execute($caseParams);
        $openCount = (int) ($openStmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare(
            "SELECT c.id, c.case_number, c.case_title, c.status, c.priority, c.case_type,
                    c.updated_at, c.created_at, w.worker_name
             FROM cases c
             LEFT JOIN workers w ON c.worker_id = w.id
             WHERE {$caseWhereSql}
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT ? OFFSET ?"
        );
        $listParams = array_merge($caseParams, [$limit, $offset]);
        $stmt->execute($listParams);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = rateb_mobile_humanize_status((string) ($row['status'] ?? 'open'));
            $priority = trim((string) ($row['priority'] ?? ''));
            $subtitle = $status;
            if ($priority !== '') {
                $subtitle = $priority . ' · ' . $status;
            }
            $workerName = trim((string) ($row['worker_name'] ?? ''));
            if ($workerName !== '') {
                $subtitle .= ' · ' . $workerName;
            }

            $title = trim((string) ($row['case_title'] ?? ''));
            if ($title === '') {
                $title = 'Case #' . (string) ($row['case_number'] ?? $row['id'] ?? '');
            }

            $requests[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'status' => (string) ($row['status'] ?? ''),
                'status_label' => $status,
                'priority' => $priority,
                'case_type' => (string) ($row['case_type'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'updated_label' => rateb_mobile_relative_time((string) ($row['updated_at'] ?? '')),
            ];
        }
    } catch (Throwable $casesErr) {
        // cases table may not exist — return empty list gracefully.
        $total = 0;
        $openCount = 0;
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'requests' => $requests,
            'stats' => [
                'total' => $total,
                'open' => $openCount,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Requests unavailable'], 500);
}
