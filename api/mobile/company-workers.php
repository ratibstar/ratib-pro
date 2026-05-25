<?php
/**
 * Company workers roster for mobile portal.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';

try {
    $claims = rateb_mobile_require_auth('company');
    $pdo = rateb_mobile_pdo();

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 50;
    $offset = ($page - 1) * $limit;
    $search = trim((string) ($_GET['search'] ?? ''));

    $where = ["w.status != 'deleted'"];
    $params = [];

    if ($search !== '') {
        $where[] = '(w.worker_name LIKE ? OR w.email LIKE ? OR w.passport_number LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM workers w WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetchColumn() ?: 0);

    $sql = "SELECT w.id, w.worker_name, w.email, w.status, w.passport_number,
                   w.contact_number, w.updated_at,
                   a.agent_name
            FROM workers w
            LEFT JOIN agents a ON w.agent_id = a.id
            WHERE $whereSql
            ORDER BY w.updated_at DESC, w.id DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $workers = [];
    foreach ($rows as $row) {
        $status = rateb_mobile_humanize_status((string) ($row['status'] ?? 'pending'));
        $agent = trim((string) ($row['agent_name'] ?? ''));
        $subtitle = $status;
        if ($agent !== '') {
            $subtitle .= ' · ' . $agent;
        }

        $workers[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['worker_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'status_label' => $status,
            'subtitle' => $subtitle,
            'passport_number' => (string) ($row['passport_number'] ?? ''),
            'contact_number' => (string) ($row['contact_number'] ?? ''),
            'agent_name' => $agent,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    $active = 0;
    $pending = 0;
    foreach ($workers as $w) {
        $st = strtolower((string) ($w['status'] ?? ''));
        if (in_array($st, ['approved', 'deployed', 'active'], true)) {
            $active++;
        } elseif (in_array($st, ['pending'], true)) {
            $pending++;
        }
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'workers' => $workers,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'pending' => $pending,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Workers unavailable'], 500);
}
