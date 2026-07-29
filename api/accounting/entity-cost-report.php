<?php
/**
 * Entity cost report API.
 *
 * Returns expenses grouped by entity type and by individual entity.
 * Supports: agent, subagent, worker, partner_agency.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    enforceApiPermission('accounts', 'view');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$entityType = isset($_GET['entity_type']) ? strtolower(trim($_GET['entity_type'])) : null;
$entityId = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : null;
$from = isset($_GET['from']) ? trim($_GET['from']) : null;
$to = isset($_GET['to']) ? trim($_GET['to']) : null;

$allowedTypes = ['agent', 'subagent', 'worker', 'partner_agency'];
if ($entityType !== null && $entityType !== '' && !in_array($entityType, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid entity_type']);
    exit;
}

if (!$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$conditions = ["ft.transaction_type = 'Expense'", "ft.status IN ('Approved', 'Posted')"];
$params = [];
$types = '';

if (!empty($entityType)) {
    $conditions[] = "et.entity_type = ?";
    $params[] = $entityType;
    $types .= 's';
}
if (!empty($entityId)) {
    $conditions[] = "et.entity_id = ?";
    $params[] = $entityId;
    $types .= 'i';
}
if (!empty($from)) {
    $conditions[] = "ft.transaction_date >= ?";
    $params[] = $from;
    $types .= 's';
}
if (!empty($to)) {
    $conditions[] = "ft.transaction_date <= ?";
    $params[] = $to;
    $types .= 's';
}

$where = implode(' AND ', $conditions);

$summarySql = "
    SELECT 
        et.entity_type,
        COUNT(DISTINCT et.entity_id) as entity_count,
        COUNT(et.id) as transaction_count,
        COALESCE(SUM(ft.total_amount), 0) as total_expenses
    FROM entity_transactions et
    INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
    WHERE {$where}
    GROUP BY et.entity_type
";

$summaryStmt = $conn->prepare($summarySql);
if (!empty($params)) {
    $summaryStmt->bind_param($types, ...$params);
}
$summaryStmt->execute();
$summaryRes = $summaryStmt->get_result();
$byType = [];
while ($row = $summaryRes->fetch_assoc()) {
    $byType[$row['entity_type']] = [
        'entity_count' => (int) $row['entity_count'],
        'transaction_count' => (int) $row['transaction_count'],
        'total_expenses' => (float) $row['total_expenses'],
    ];
}
$summaryStmt->close();

$detailSql = "
    SELECT 
        et.entity_type,
        et.entity_id,
        COALESCE(SUM(ft.total_amount), 0) as total_expenses,
        COUNT(et.id) as transaction_count,
        CASE 
            WHEN et.entity_type = 'agent' THEN (SELECT COALESCE(NULLIF(TRIM(agent_name), ''), full_name) FROM agents WHERE id = et.entity_id LIMIT 1)
            WHEN et.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = et.entity_id LIMIT 1)
            WHEN et.entity_type = 'worker' THEN (SELECT COALESCE(NULLIF(TRIM(worker_name), ''), full_name) FROM workers WHERE id = et.entity_id LIMIT 1)
            WHEN et.entity_type = 'partner_agency' THEN (SELECT name FROM partner_agencies WHERE id = et.entity_id LIMIT 1)
            ELSE NULL
        END as entity_name
    FROM entity_transactions et
    INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
    WHERE {$where}
    GROUP BY et.entity_type, et.entity_id
    ORDER BY total_expenses DESC
";

$detailStmt = $conn->prepare($detailSql);
if (!empty($params)) {
    $detailStmt->bind_param($types, ...$params);
}
$detailStmt->execute();
$detailRes = $detailStmt->get_result();
$entities = [];
while ($row = $detailRes->fetch_assoc()) {
    $entities[] = [
        'entity_type' => $row['entity_type'],
        'entity_id' => (int) $row['entity_id'],
        'entity_name' => $row['entity_name'] ?: (ucfirst($row['entity_type']) . ' #' . $row['entity_id']),
        'transaction_count' => (int) $row['transaction_count'],
        'total_expenses' => (float) $row['total_expenses'],
    ];
}
$detailStmt->close();

$total = array_sum(array_column($byType, 'total_expenses'));

echo json_encode([
    'success' => true,
    'from' => $from,
    'to' => $to,
    'total_expenses' => $total,
    'by_type' => $byType,
    'entities' => $entities,
], JSON_UNESCAPED_UNICODE);
