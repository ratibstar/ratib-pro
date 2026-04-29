<?php
/**
 * EN: Resolve worker by passport/identity for global AI flow, then include related case/order context.
 * AR: يبحث عن العامل برقم الهوية/الجواز لتدفق الذكاء الاصطناعي ثم يرجع ملخص الحالات/الطلبات المرتبطة.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../../control-panel/includes/control-permissions.php';

try {
    try {
        enforceApiPermission('workers', 'get');
    } catch (Throwable $authError) {
        $hasControlAccess = !empty($_SESSION['control_logged_in'])
            && (
                hasControlPermission(CONTROL_PERM_GOVERNMENT)
                || hasControlPermission('manage_control_government')
                || hasControlPermission('gov_admin')
                || hasControlPermission(CONTROL_PERM_ADMINS)
            );
        if (!$hasControlAccess) {
            throw $authError;
        }
    }

    $passportRaw = trim((string) ($_GET['passport_number'] ?? ''));
    $identityRaw = trim((string) ($_GET['identity_number'] ?? ''));
    $passport = preg_replace('/\D+/', '', substr($passportRaw, 0, 120)) ?? '';
    $identity = preg_replace('/\D+/', '', substr($identityRaw, 0, 120)) ?? '';

    if ($passport === '' && $identity === '') {
        echo ApiResponse::error('Passport number or identity number is required.', 422);
        exit;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $where = [];
    $params = [];
    if ($passport !== '') {
        $where[] = 'w.passport_number = ?';
        $params[] = $passport;
    }
    if ($identity !== '') {
        $where[] = 'w.identity_number = ?';
        $params[] = $identity;
    }

    $workerSql = "
        SELECT w.*, a.agent_name, s.subagent_name
        FROM workers w
        LEFT JOIN agents a ON a.id = w.agent_id
        LEFT JOIN subagents s ON s.id = w.subagent_id
        WHERE (" . implode(' OR ', $where) . ")
          AND COALESCE(w.status, '') <> 'deleted'
        ORDER BY w.id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($workerSql);
    $stmt->execute($params);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$worker) {
        echo ApiResponse::error('Worker not found for provided passport/identity.', 404);
        exit;
    }

    $workerId = (int) ($worker['id'] ?? 0);
    if ($workerId < 1) {
        echo ApiResponse::error('Worker record is invalid.', 500);
        exit;
    }

    $cases = [];
    $casesCount = 0;
    $casesTableCheck = $conn->query("SHOW TABLES LIKE 'cases'");
    $casesTableRow = $casesTableCheck ? $casesTableCheck->fetch(PDO::FETCH_NUM) : false;
    if ($casesTableRow) {
        $casesStmt = $conn->prepare(
            "SELECT id, case_number, case_title, status, created_at
             FROM cases
             WHERE worker_id = ?
             ORDER BY id DESC
             LIMIT 5"
        );
        $casesStmt->execute([$workerId]);
        $cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $casesCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM cases WHERE worker_id = ?");
        $casesCountStmt->execute([$workerId]);
        $casesCount = (int) (($casesCountStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
    }

    $orders = [];
    $ordersCount = 0;

    $ordersTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
    $ordersTableRow = $ordersTableCheck ? $ordersTableCheck->fetch(PDO::FETCH_NUM) : false;
    if ($ordersTableRow) {
        $ordersSql = "
            SELECT id, status, created_at
            FROM orders
            WHERE worker_id = ?
            ORDER BY id DESC
            LIMIT 5
        ";
        $ordersStmt = $conn->prepare($ordersSql);
        $ordersStmt->execute([$workerId]);
        $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ordersCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE worker_id = ?");
        $ordersCountStmt->execute([$workerId]);
        $ordersCount = (int) (($ordersCountStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
    }

    echo ApiResponse::success([
        'worker' => $worker,
        'cases_count' => $casesCount,
        'orders_count' => $ordersCount,
        'cases' => $cases,
        'orders' => $orders,
    ], 'Worker context loaded.');
} catch (Throwable $e) {
    error_log('AI lookup error: ' . $e->getMessage());
    $msg = $e->getMessage();
    $isAuthError = stripos($msg, 'access denied') !== false
        || stripos($msg, 'permission') !== false
        || stripos($msg, 'unauthorized') !== false;
    $status = $isAuthError ? 403 : 500;
    echo ApiResponse::error('AI worker lookup failed: ' . $msg, $status);
}

