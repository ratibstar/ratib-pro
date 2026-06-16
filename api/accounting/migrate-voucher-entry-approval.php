<?php
/**
 * One-time backfill: mirror posted payment/receipt vouchers into entry_approval
 * (same semantics as ReceiptPaymentVoucherManager::syncEntryApprovalForPostedVoucher).
 *
 * Run once while logged in (same auth as other accounting migrations):
 *   GET/POST https://your-site/api/accounting/migrate-voucher-entry-approval.php
 *
 * Safe to re-run: skips rows where entry_approval.journal_entry_id already exists.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    enforceApiPermission('journal-entries', 'update');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied: journal-entries update required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

function ratebTableExists(mysqli $conn, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $ok = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    return $ok;
}

function ratebColumnExists(mysqli $conn, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
    $ok = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    return $ok;
}

/**
 * @return array{inserted:int, skipped:int, errors:string[]}
 */
function ratebBackfillVoucherTable(mysqli $conn, string $table, string $entityType, int $userId): array
{
    $out = ['inserted' => 0, 'skipped' => 0, 'errors' => []];
    $allowed = ['payment_vouchers', 'receipt_vouchers', 'payment_receipts'];
    if (!in_array($table, $allowed, true) || !ratebTableExists($conn, $table) || !ratebTableExists($conn, 'entry_approval')) {
        return $out;
    }
    if (!ratebColumnExists($conn, $table, 'journal_entry_id')) {
        return $out;
    }

    $numCol = ratebColumnExists($conn, $table, 'voucher_number') ? 'voucher_number' : (ratebColumnExists($conn, $table, 'receipt_number') ? 'receipt_number' : null);
    $dateCol = ratebColumnExists($conn, $table, 'voucher_date') ? 'voucher_date' : (ratebColumnExists($conn, $table, 'payment_date') ? 'payment_date' : null);
    if (!$numCol || !$dateCol) {
        $out['errors'][] = "Table {$table}: missing voucher number / date column";
        return $out;
    }

    $hasStatus = ratebColumnExists($conn, $table, 'status');
    $hasPosting = ratebColumnExists($conn, $table, 'posting_status');
    $hasIsPosted = ratebColumnExists($conn, $table, 'is_posted');

    if ($table === 'payment_receipts' && $hasStatus) {
        $postedSql = "v.status IN ('Cleared','Deposited','Posted')";
    } elseif ($hasStatus || $hasPosting || $hasIsPosted) {
        $parts = [];
        if ($hasStatus) {
            $parts[] = "v.status = 'Posted'";
        }
        if ($hasPosting) {
            $parts[] = "LOWER(TRIM(COALESCE(v.posting_status,''))) = 'posted'";
        }
        if ($hasIsPosted) {
            $parts[] = 'COALESCE(v.is_posted,0) = 1';
        }
        $postedSql = '(' . implode(' OR ', $parts) . ')';
    } else {
        $postedSql = '1=1';
    }

    $hasEntityType = ratebColumnExists($conn, 'entry_approval', 'entity_type');
    $hasEntityId = ratebColumnExists($conn, 'entry_approval', 'entity_id');
    $hasCc = ratebColumnExists($conn, 'entry_approval', 'cost_center_id');
    $hasDebit = ratebColumnExists($conn, 'entry_approval', 'debit_amount');
    $hasCredit = ratebColumnExists($conn, 'entry_approval', 'credit_amount');
    $hasApprBy = ratebColumnExists($conn, 'entry_approval', 'approved_by');
    $hasApprAt = ratebColumnExists($conn, 'entry_approval', 'approved_at');

    $amtExpr = '0';
    if (ratebColumnExists($conn, $table, 'amount')) {
        $amtExpr = 'v.amount';
    } elseif (ratebColumnExists($conn, $table, 'total_amount')) {
        $amtExpr = 'v.total_amount';
    }
    $createdBySql = ratebColumnExists($conn, $table, 'created_by')
        ? 'COALESCE(v.created_by, ' . (int)$userId . ')'
        : (string)(int)$userId;

    $sql = "
        SELECT v.id, v.`{$numCol}` AS vnum, v.`{$dateCol}` AS vdate, {$amtExpr} AS vamt,
               COALESCE(NULLIF(TRIM(v.currency), ''), 'SAR') AS vcur,
               " . (ratebColumnExists($conn, $table, 'cost_center_id') ? 'v.cost_center_id' : 'NULL') . " AS cost_center_id,
               {$createdBySql} AS created_by_u,
               v.journal_entry_id AS je_id,
               je.entry_number AS je_entry_number,
               je.description AS je_desc,
               COALESCE(je.total_debit, 0) AS je_td,
               COALESCE(je.total_credit, 0) AS je_tc,
               COALESCE(NULLIF(TRIM(je.currency), ''), 'SAR') AS je_cur
        FROM `{$table}` v
        INNER JOIN journal_entries je ON je.id = v.journal_entry_id
        WHERE v.journal_entry_id IS NOT NULL AND v.journal_entry_id > 0
          AND {$postedSql}
          AND NOT EXISTS (SELECT 1 FROM entry_approval ea WHERE ea.journal_entry_id = v.journal_entry_id)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $out['errors'][] = "Prepare failed ({$table}): " . $conn->error;
        return $out;
    }
    if (!$stmt->execute()) {
        $out['errors'][] = "Execute failed ({$table}): " . $stmt->error;
        $stmt->close();
        return $out;
    }
    $res = $stmt->get_result();
    if (!$res) {
        $out['errors'][] = "No result ({$table}): " . $stmt->error;
        $stmt->close();
        return $out;
    }

    $baseFields = ['entry_number', 'entry_date', 'description', 'amount', 'currency', 'status', 'journal_entry_id', 'created_by'];

    while ($row = $res->fetch_assoc()) {
        $vnum = trim((string)($row['vnum'] ?? ''));
        $jeNumRaw = trim((string)($row['je_entry_number'] ?? ''));
        $entryNumber = $vnum !== ''
            ? ('APP-' . $vnum)
            : ('APP-' . preg_replace('/^APP-/i', '', $jeNumRaw !== '' ? $jeNumRaw : 'JE'));
        $entryDate = $row['vdate'];
        if ($entryDate && strpos((string)$entryDate, ' ') !== false) {
            $entryDate = substr((string)$entryDate, 0, 10);
        }
        $desc = (string)($row['je_desc'] ?? '');
        $amt = max((float)($row['je_td']), (float)($row['je_tc']), (float)($row['vamt'] ?? 0));
        $vc = trim((string)($row['vcur'] ?? ''));
        $jc = trim((string)($row['je_cur'] ?? ''));
        $cur = strtoupper(trim($vc !== '' ? $vc : ($jc !== '' ? $jc : 'SAR')));
        if (strpos($cur, ' - ') !== false) {
            $cur = trim(explode(' - ', $cur)[0]);
        }
        $jeId = (int)$row['je_id'];
        $createdBy = (int)$row['created_by_u'];
        if ($createdBy <= 0) {
            $createdBy = $userId;
        }

        $fields = $baseFields;
        $place = ['?', '?', '?', '?', '?', "'approved'", '?', '?'];
        $vals = [$entryNumber, $entryDate, $desc, $amt, $cur, $jeId, $createdBy];
        $types = 'sssdsii';

        if ($hasApprBy) {
            $fields[] = 'approved_by';
            $place[] = '?';
            $types .= 'i';
            $vals[] = $userId;
        }
        if ($hasApprAt) {
            $fields[] = 'approved_at';
            $place[] = 'NOW()';
        }
        if ($hasCc && $row['cost_center_id'] !== null && (int)$row['cost_center_id'] > 0) {
            $fields[] = 'cost_center_id';
            $place[] = '?';
            $types .= 'i';
            $vals[] = (int)$row['cost_center_id'];
        }
        if ($hasDebit) {
            $fields[] = 'debit_amount';
            $place[] = '?';
            $types .= 'd';
            $vals[] = (float)$row['je_td'];
        }
        if ($hasCredit) {
            $fields[] = 'credit_amount';
            $place[] = '?';
            $types .= 'd';
            $vals[] = (float)$row['je_tc'];
        }
        if ($hasEntityType) {
            $fields[] = 'entity_type';
            $place[] = '?';
            $types .= 's';
            $vals[] = $entityType;
        }
        if ($hasEntityId && (int)$row['id'] > 0) {
            $fields[] = 'entity_id';
            $place[] = '?';
            $types .= 'i';
            $vals[] = (int)$row['id'];
        }

        $sqlIns = 'INSERT INTO entry_approval (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $place) . ')';

        $ins = $conn->prepare($sqlIns);
        if (!$ins) {
            $out['errors'][] = "Insert prepare failed ({$table} id={$row['id']}): " . $conn->error;
            $out['skipped']++;
            continue;
        }
        $typesStr = $types;
        $bindArgs = [&$typesStr];
        foreach ($vals as $k => $_v) {
            $bindArgs[] = &$vals[$k];
        }
        if (!call_user_func_array([$ins, 'bind_param'], $bindArgs)) {
            $out['errors'][] = "bind_param failed ({$table} id={$row['id']})";
            $ins->close();
            $out['skipped']++;
            continue;
        }
        if ($ins->execute()) {
            $out['inserted']++;
        } else {
            $out['errors'][] = "Insert failed ({$table} id={$row['id']}): " . $ins->error;
            $out['skipped']++;
        }
        $ins->close();
    }
    $res->free();
    $stmt->close();

    return $out;
}

$summary = [
    'success' => true,
    'payment_vouchers' => ratebBackfillVoucherTable($conn, 'payment_vouchers', 'payment_voucher', $userId),
    'receipt_vouchers' => ratebBackfillVoucherTable($conn, 'receipt_vouchers', 'receipt_voucher', $userId),
    'payment_receipts' => ratebBackfillVoucherTable($conn, 'payment_receipts', 'receipt_voucher', $userId),
];

$summary['total_inserted'] = $summary['payment_vouchers']['inserted'] + $summary['receipt_vouchers']['inserted'] + $summary['payment_receipts']['inserted'];
$summary['total_errors'] = count($summary['payment_vouchers']['errors']) + count($summary['receipt_vouchers']['errors']) + count($summary['payment_receipts']['errors']);

echo json_encode($summary, JSON_UNESCAPED_UNICODE);
