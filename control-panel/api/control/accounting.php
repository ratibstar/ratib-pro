<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/accounting.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/accounting.php`.
 */
/**
 * Control Panel API: Accounting (between control admin and countries/agencies)
 * Requires control panel session.
 */
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/config.php';
error_reporting(0);

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}
$canManageAccounting = hasControlPermission(CONTROL_PERM_ACCOUNTING) || hasControlPermission('manage_control_accounting');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    jsonOut(['success' => false, 'message' => 'Database unavailable']);
}

/** Normalize Eastern/Western Arabic numerals to ASCII 0-9 (dates/amounts pasted from Arabic locales). */
function control_normalize_ascii_digits(string $s): string {
    $from = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $to = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($from, $to, $s);
}

/** Whether $table in the current schema has $column (for optional receipt/expense line JSON). */
function control_table_has_column(mysqli $ctrl, string $table, string $column): bool {
    $t = $ctrl->real_escape_string($table);
    $c = $ctrl->real_escape_string($column);
    $q = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}' LIMIT 1";
    $r = $ctrl->query($q);
    return $r && $r->num_rows > 0;
}

/**
 * Encode debit/credit line arrays for control_receipts / control_expenses / control_support_payments lines_json.
 * @param mixed $raw Expects ['debit' => [...], 'credit' => [...]] from JSON body.
 */
function control_encode_receipt_expense_lines_payload($raw): ?string {
    if ($raw === null) {
        return null;
    }
    if (!is_array($raw)) {
        jsonOut(['success' => false, 'message' => 'Invalid lines: expected an object with debit/credit arrays']);
    }
    $debit = isset($raw['debit']) && is_array($raw['debit']) ? $raw['debit'] : [];
    $credit = isset($raw['credit']) && is_array($raw['credit']) ? $raw['credit'] : [];
    if (count($debit) > 80 || count($credit) > 80) {
        jsonOut(['success' => false, 'message' => 'Too many debit/credit lines']);
    }
    $payload = json_encode(['debit' => $debit, 'credit' => $credit], JSON_UNESCAPED_UNICODE);
    if ($payload === false || strlen($payload) > 65535) {
        jsonOut(['success' => false, 'message' => 'Lines data too large']);
    }
    return $payload;
}

$chk = $ctrl->query("SHOW TABLES LIKE 'control_accounting_transactions'");
if (!$chk || $chk->num_rows === 0) {
    jsonOut(['success' => false, 'message' => 'Run config/control_accounting.sql and control_accounting_full.sql first']);
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);

/**
 * Next journal reference for General Ledger manual entries: GL-00001 (5-digit global sequence).
 */
function control_next_gl_journal_reference(mysqli $ctrl): string {
    $prefix = 'GL-';
    $maxSeq = 0;
    $chkJ = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
    if ($chkJ && $chkJ->num_rows > 0) {
        $like = 'GL-%';
        $st = $ctrl->prepare('SELECT reference FROM control_journal_entries WHERE reference LIKE ?');
        if ($st) {
            $st->bind_param('s', $like);
            $st->execute();
            $res = $st->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $ref = trim((string) ($row['reference'] ?? ''));
                    // Backward-compatible: accept both GL-00001 and legacy GL-YYYY-00001.
                    if ($ref !== '' && preg_match('/^GL-(?:\d{4}-)?(\d+)$/i', $ref, $m)) {
                        $maxSeq = max($maxSeq, (int) $m[1]);
                    }
                }
            }
            $st->close();
        }
    }

    return $prefix . sprintf('%05d', $maxSeq + 1);
}

/**
 * Restrict journal access by allowed country IDs when scoped permissions apply.
 */
function control_journal_country_allowed(array $entry, ?array $allowedCountryIds): bool {
    if ($allowedCountryIds === null) {
        return true;
    }
    $entryCountryId = isset($entry['country_id']) ? (int) $entry['country_id'] : 0;
    if ($entryCountryId === 0) {
        // Global/system entries are visible across country-scoped operators.
        return true;
    }
    if ($allowedCountryIds === []) {
        return false;
    }
    return in_array($entryCountryId, array_map('intval', $allowedCountryIds), true);
}

/**
 * Keep only approval IDs mapped to journals in allowed countries.
 *
 * @param mysqli $ctrl
 * @param array<int> $ids
 * @param array<int>|null $allowedCountryIds
 * @return array<int>
 */
function control_filter_approval_ids_by_country(mysqli $ctrl, array $ids, ?array $allowedCountryIds): array {
    if (empty($ids)) {
        return [];
    }
    if ($allowedCountryIds === null) {
        return $ids;
    }
    if ($allowedCountryIds === []) {
        return [];
    }
    $idList = implode(',', array_map('intval', $ids));
    $countryList = implode(',', array_map('intval', $allowedCountryIds));
    $out = [];
    $sql = "SELECT a.id
            FROM control_entry_approvals a
            INNER JOIN control_journal_entries j ON j.id = a.journal_entry_id
            WHERE a.id IN ($idList) AND (j.country_id IN ($countryList) OR j.country_id = 0)";
    $res = $ctrl->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = (int) ($row['id'] ?? 0);
        }
    }
    return array_values(array_unique(array_filter($out)));
}

function control_csrf_session_token(): string {
    if (empty($_SESSION['control_csrf_token']) || !is_string($_SESSION['control_csrf_token'])) {
        try {
            $_SESSION['control_csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['control_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return (string) $_SESSION['control_csrf_token'];
}

function control_validate_csrf_token(array $input): bool {
    $sessionToken = control_csrf_session_token();
    $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $bodyToken = trim((string) ($input['csrf_token'] ?? ''));
    $provided = $headerToken !== '' ? $headerToken : $bodyToken;
    if ($provided === '') {
        return false;
    }
    return hash_equals($sessionToken, $provided);
}

function control_acquire_mysql_lock(mysqli $ctrl, string $lockName, int $timeoutSeconds = 5): bool {
    $st = $ctrl->prepare('SELECT GET_LOCK(?, ?) AS lck');
    if (!$st) {
        return false;
    }
    $st->bind_param('si', $lockName, $timeoutSeconds);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return isset($row['lck']) && (int) $row['lck'] === 1;
}

function control_release_mysql_lock(mysqli $ctrl, string $lockName): void {
    $st = $ctrl->prepare('SELECT RELEASE_LOCK(?)');
    if (!$st) {
        return;
    }
    $st->bind_param('s', $lockName);
    $st->execute();
    $st->close();
}

function control_rejection_reason_choices(): array {
    return [
        'Missing supporting documents',
        'Incorrect account mapping',
        'Unbalanced journal entry',
        'Amount mismatch',
        'Duplicate entry',
        'Wrong period/date',
        'Policy compliance issue',
        'Other',
    ];
}

/**
 * Normalize numbering formats to GL-00001 and RC-00001.
 *
 * @return array{journals_total:int,receipts_total:int,journals_changed:int,receipts_changed:int}
 */
function control_normalize_gl_rc_numbers(mysqli $ctrl): array {
    $out = [
        'journals_total' => 0,
        'receipts_total' => 0,
        'journals_changed' => 0,
        'receipts_changed' => 0,
    ];
    $jr = $ctrl->query("SELECT COUNT(*) AS n FROM control_journal_entries");
    if ($jr) {
        $out['journals_total'] = (int) (($jr->fetch_assoc()['n'] ?? 0));
    }
    $rr = $ctrl->query("SELECT COUNT(*) AS n FROM control_receipts");
    if ($rr) {
        $out['receipts_total'] = (int) (($rr->fetch_assoc()['n'] ?? 0));
    }

    // Journals: assign GL-00001.. by id order for GL/legacy-registration references.
    $ctrl->query("SET @gl_seq := 0");
    $ctrl->query("
        UPDATE control_journal_entries j
        JOIN (
            SELECT id, (@gl_seq := @gl_seq + 1) AS seq
            FROM control_journal_entries
            WHERE reference IS NOT NULL
              AND TRIM(reference) <> ''
              AND (UPPER(reference) LIKE 'GL-%' OR UPPER(reference) LIKE 'JE-REG-%')
            ORDER BY id ASC
        ) x ON x.id = j.id
        SET j.reference = CONCAT('GL-', LPAD(x.seq, 5, '0'))
    ");
    $out['journals_changed'] = max(0, (int) $ctrl->affected_rows);

    // Receipts: assign RC-00001.. by id order, including empty numbers.
    $ctrl->query("SET @rc_seq := 0");
    $ctrl->query("
        UPDATE control_receipts r
        JOIN (
            SELECT id, (@rc_seq := @rc_seq + 1) AS seq
            FROM control_receipts
            ORDER BY id ASC
        ) x ON x.id = r.id
        SET r.receipt_number = CONCAT('RC-', LPAD(x.seq, 5, '0'))
    ");
    $out['receipts_changed'] = max(0, (int) $ctrl->affected_rows);

    return $out;
}

function control_next_receipt_number(mysqli $ctrl): string {
    $maxSeq = 0;
    $chkR = $ctrl->query("SHOW TABLES LIKE 'control_receipts'");
    if ($chkR && $chkR->num_rows > 0) {
        $st = $ctrl->prepare("SELECT receipt_number FROM control_receipts WHERE receipt_number IS NOT NULL AND TRIM(receipt_number) <> ''");
        if ($st) {
            $st->execute();
            $res = $st->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $num = trim((string) ($row['receipt_number'] ?? ''));
                    if ($num !== '' && preg_match('/^(?:RC|REG|RECEIPT|RCP)-?(\d+)$/i', $num, $m)) {
                        $maxSeq = max($maxSeq, (int) $m[1]);
                    }
                }
            }
            $st->close();
        }
    }
    return 'RC-' . sprintf('%05d', $maxSeq + 1);
}

function control_next_expense_voucher(mysqli $ctrl): string {
    $maxSeq = 0;
    $chkE = $ctrl->query("SHOW TABLES LIKE 'control_expenses'");
    if ($chkE && $chkE->num_rows > 0) {
        $st = $ctrl->prepare("SELECT voucher_number FROM control_expenses WHERE voucher_number IS NOT NULL AND TRIM(voucher_number) <> ''");
        if ($st) {
            $st->execute();
            $res = $st->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $num = trim((string) ($row['voucher_number'] ?? ''));
                    if ($num !== '' && preg_match('/^(?:EX|EXP|EXPENSE|VOUCHER)-?(\d+)$/i', $num, $m)) {
                        $maxSeq = max($maxSeq, (int) $m[1]);
                    }
                }
            }
            $st->close();
        }
    }
    return 'EX-' . sprintf('%05d', $maxSeq + 1);
}

function control_next_support_payment_number(mysqli $ctrl): string {
    $maxSeq = 0;
    $chkS = $ctrl->query("SHOW TABLES LIKE 'control_support_payments'");
    if ($chkS && $chkS->num_rows > 0 && control_table_has_column($ctrl, 'control_support_payments', 'payment_number')) {
        $st = $ctrl->prepare("SELECT payment_number FROM control_support_payments WHERE payment_number IS NOT NULL AND TRIM(payment_number) <> ''");
        if ($st) {
            $st->execute();
            $res = $st->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $num = trim((string) ($row['payment_number'] ?? ''));
                    if ($num !== '' && preg_match('/^(?:SP|SUP|SPP|SUPPORT)-?(\d+)$/i', $num, $m)) {
                        $maxSeq = max($maxSeq, (int) $m[1]);
                    }
                }
            }
            $st->close();
        }
    }
    return 'SP-' . sprintf('%05d', $maxSeq + 1);
}

// Module tables for list/add
$MODULES = [
    'chart_accounts' => 'control_chart_accounts',
    'cost_centers' => 'control_cost_centers',
    'bank_guarantees' => 'control_bank_guarantees',
    'support_payments' => 'control_support_payments',
    'journal_entries' => 'control_journal_entries',
    'expenses' => 'control_expenses',
    'receipts' => 'control_receipts',
    'disbursement_vouchers' => 'control_disbursement_vouchers',
    'electronic_invoices' => 'control_electronic_invoices',
    'entry_approvals' => 'control_entry_approvals',
    'bank_reconciliations' => 'control_bank_reconciliations',
];

$method = $_SERVER['REQUEST_METHOD'];

// GET - overview, by country, by agency, transactions list
if ($method === 'GET') {
    $action = trim($_GET['action'] ?? 'overview');
    $countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
    $agencyId = isset($_GET['agency_id']) && ctype_digit($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    if ($action === 'overview') {
        $totals = ['revenue' => 0, 'commission' => 0, 'settlement_in' => 0, 'settlement_out' => 0, 'balance' => 0];
        $txWhere = '';
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $idsStr = implode(',', array_map('intval', $allowedCountryIds));
            $txWhere = " WHERE country_id IN ($idsStr)";
        } elseif ($allowedCountryIds === []) {
            $txWhere = " WHERE 1=0";
        }
        $r = $ctrl->query("SELECT type, SUM(amount) as total FROM control_accounting_transactions" . $txWhere . " GROUP BY type");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $t = $row['type'];
                $totals[$t] = (float) $row['total'];
            }
        }
        $totals['balance'] = $totals['revenue'] + $totals['commission'] + $totals['settlement_in'] - $totals['settlement_out'];
        $totals['pending_commission'] = $totals['commission'];

        $countryCount = 0;
        $agencyCount = 0;
        $countryFilter = '';
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $countryFilter = " AND id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
        } elseif ($allowedCountryIds === []) {
            $countryFilter = " AND 1=0";
        }
        $chkC = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
        if ($chkC && $chkC->num_rows > 0) {
            $rc = $ctrl->query("SELECT COUNT(*) as n FROM control_countries WHERE is_active = 1" . $countryFilter);
            if ($rc) $countryCount = (int) $rc->fetch_assoc()['n'];
        }
        $agencyFilter = '';
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $agencyFilter = " AND country_id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
        } elseif ($allowedCountryIds === []) {
            $agencyFilter = " AND 1=0";
        }
        $chkA = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
        if ($chkA && $chkA->num_rows > 0) {
            $ra = $ctrl->query("SELECT COUNT(*) as n FROM control_agencies WHERE is_active = 1" . $agencyFilter);
            if ($ra) $agencyCount = (int) $ra->fetch_assoc()['n'];
        }

        jsonOut(['success' => true, 'totals' => $totals, 'countryCount' => $countryCount, 'agencyCount' => $agencyCount]);
        exit;
    }

    if ($action === 'by_country') {
        $countries = [];
        $countryWhere = "c.is_active = 1";
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $countryWhere .= " AND c.id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
        } elseif ($allowedCountryIds === []) {
            $countryWhere .= " AND 1=0";
        }
        $chkC = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
        if ($chkC && $chkC->num_rows > 0) {
            $sql = "SELECT c.id, c.name, c.slug,
                COALESCE(SUM(CASE WHEN t.type IN ('revenue','commission','settlement_in') THEN t.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN t.type = 'settlement_out' THEN t.amount ELSE 0 END), 0) as balance,
                COALESCE(SUM(CASE WHEN t.type = 'revenue' THEN t.amount ELSE 0 END), 0) as revenue,
                COALESCE(SUM(CASE WHEN t.type = 'commission' THEN t.amount ELSE 0 END), 0) as commission,
                COALESCE(SUM(CASE WHEN t.type = 'settlement_out' THEN t.amount ELSE 0 END), 0) as settled
                FROM control_countries c
                LEFT JOIN control_accounting_transactions t ON t.country_id = c.id
                WHERE $countryWhere
                GROUP BY c.id, c.name, c.slug
                ORDER BY c.sort_order, c.name";
            $res = $ctrl->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $countries[] = [
                        'id' => (int) $row['id'],
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'revenue' => (float) $row['revenue'],
                        'commission' => (float) $row['commission'],
                        'settled' => (float) $row['settled'],
                        'balance' => (float) $row['balance'],
                    ];
                }
            }
        }
        jsonOut(['success' => true, 'countries' => $countries]);
        exit;
    }

    if ($action === 'by_agency') {
        $agencies = [];
        if ($countryId > 0 && $allowedCountryIds !== null && !in_array($countryId, $allowedCountryIds, true)) {
            jsonOut(['success' => true, 'agencies' => []]);
            exit;
        }
        $where = $params = $types = '';
        if ($countryId > 0) {
            $where = 'WHERE a.country_id = ? AND a.is_active = 1';
            $params = [$countryId];
            $types = 'i';
        } else {
            $countryClause = '';
            if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
                $countryClause = " AND a.country_id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
            } elseif ($allowedCountryIds === []) {
                $countryClause = " AND 1=0";
            }
            $where = "WHERE a.is_active = 1" . $countryClause;
        }
        $sql = "SELECT a.id, a.name, a.slug, a.country_id,
                COALESCE(SUM(CASE WHEN t.type IN ('revenue','commission','settlement_in') THEN t.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN t.type = 'settlement_out' THEN t.amount ELSE 0 END), 0) as balance,
                COALESCE(SUM(CASE WHEN t.type = 'revenue' THEN t.amount ELSE 0 END), 0) as revenue,
                COALESCE(SUM(CASE WHEN t.type = 'commission' THEN t.amount ELSE 0 END), 0) as commission,
                COALESCE(SUM(CASE WHEN t.type = 'settlement_out' THEN t.amount ELSE 0 END), 0) as settled
                FROM control_agencies a
                LEFT JOIN control_accounting_transactions t ON t.agency_id = a.id
                $where
                GROUP BY a.id, a.name, a.slug, a.country_id
                ORDER BY a.sort_order, a.name";
        $stmt = $params ? $ctrl->prepare($sql) : null;
        if ($stmt && $params) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $ctrl->query($sql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $agencies[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'country_id' => (int) $row['country_id'],
                    'revenue' => (float) $row['revenue'],
                    'commission' => (float) $row['commission'],
                    'settled' => (float) $row['settled'],
                    'balance' => (float) $row['balance'],
                ];
            }
        }
        jsonOut(['success' => true, 'agencies' => $agencies]);
        exit;
    }

    if ($action === 'transactions') {
        $where = ['1=1'];
        $params = [];
        $types = '';
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $where[] = 't.country_id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
        } elseif ($allowedCountryIds === []) {
            $where[] = '1=0';
        }
        if ($countryId > 0) {
            $where[] = 't.country_id = ?';
            $params[] = $countryId;
            $types .= 'i';
        }
        if ($agencyId > 0) {
            $where[] = 't.agency_id = ?';
            $params[] = $agencyId;
            $types .= 'i';
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = 't.created_at';
        $orderDir = 'DESC';
        $sortCol = trim($_GET['sort'] ?? '');
        $orderParam = strtoupper(trim($_GET['order'] ?? 'desc'));
        if (in_array($orderParam, ['ASC', 'DESC'])) $orderDir = $orderParam;
        $allowedSort = ['created_at' => 't.created_at', 'amount' => 't.amount', 'type' => 't.type'];
        if ($sortCol && isset($allowedSort[$sortCol])) $orderBy = $allowedSort[$sortCol];

        $countSql = "SELECT COUNT(*) as total FROM control_accounting_transactions t WHERE $whereClause";
        $total = 0;
        if ($params) {
            $st = $ctrl->prepare($countSql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $tr = $st->get_result();
            if ($tr) $total = (int) $tr->fetch_assoc()['total'];
        } else {
            $tr = $ctrl->query($countSql);
            if ($tr) $total = (int) $tr->fetch_assoc()['total'];
        }

        $hasDebitCredit = false;
        $chkCol = $ctrl->query("SHOW COLUMNS FROM control_accounting_transactions LIKE 'debit_account_id'");
        if ($chkCol && $chkCol->num_rows > 0) $hasDebitCredit = true;

        if ($hasDebitCredit) {
            $listSql = "SELECT t.id, t.agency_id, t.country_id, t.type, t.amount, t.currency_code, t.description, t.reference, t.created_at,
                t.debit_account_id, t.credit_account_id,
                a.name as agency_name, c.name as country_name,
                da.account_code as debit_account_code, da.account_name as debit_account_name,
                ca.account_code as credit_account_code, ca.account_name as credit_account_name
                FROM control_accounting_transactions t
                LEFT JOIN control_agencies a ON a.id = t.agency_id
                LEFT JOIN control_countries c ON c.id = t.country_id
                LEFT JOIN control_chart_accounts da ON da.id = t.debit_account_id
                LEFT JOIN control_chart_accounts ca ON ca.id = t.credit_account_id
                WHERE $whereClause
                ORDER BY $orderBy $orderDir
                LIMIT $limit OFFSET $offset";
        } else {
            $listSql = "SELECT t.id, t.agency_id, t.country_id, t.type, t.amount, t.currency_code, t.description, t.reference, t.created_at,
                    a.name as agency_name, c.name as country_name
                    FROM control_accounting_transactions t
                    LEFT JOIN control_agencies a ON a.id = t.agency_id
                    LEFT JOIN control_countries c ON c.id = t.country_id
                    WHERE $whereClause
                    ORDER BY $orderBy $orderDir
                    LIMIT $limit OFFSET $offset";
        }
        $list = [];
        if ($params) {
            $st = $ctrl->prepare($listSql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $res = $st->get_result();
        } else {
            $res = $ctrl->query($listSql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $item = [
                    'id' => (int) $row['id'],
                    'agency_id' => $row['agency_id'] ? (int) $row['agency_id'] : null,
                    'country_id' => $row['country_id'] ? (int) $row['country_id'] : null,
                    'type' => $row['type'],
                    'amount' => (float) $row['amount'],
                    'currency_code' => $row['currency_code'],
                    'description' => $row['description'],
                    'reference' => $row['reference'],
                    'created_at' => $row['created_at'],
                    'agency_name' => $row['agency_name'],
                    'country_name' => $row['country_name'],
                ];
                if ($hasDebitCredit) {
                    $item['debit_account_id'] = $row['debit_account_id'] ? (int) $row['debit_account_id'] : null;
                    $item['credit_account_id'] = $row['credit_account_id'] ? (int) $row['credit_account_id'] : null;
                    $item['debit_account_name'] = $row['debit_account_name'] ?? null;
                    $item['credit_account_name'] = $row['credit_account_name'] ?? null;
                    $item['debit_account_code'] = $row['debit_account_code'] ?? null;
                    $item['credit_account_code'] = $row['credit_account_code'] ?? null;
                }
                $list[] = $item;
            }
        }
        jsonOut(['success' => true, 'transactions' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
        exit;
    }

    // Generic module list
    if ($action === 'module_list' && isset($MODULES)) {
        $module = trim($_GET['module'] ?? '');
        if (!isset($MODULES[$module])) {
            jsonOut(['success' => false, 'message' => 'Unknown module']);
        }
        $table = $MODULES[$module];
        $chkT = $ctrl->query("SHOW TABLES LIKE '$table'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => "Table $table not found. Run config/control_accounting_full.sql"]);
        }
        $where = ['1=1'];
        $params = [];
        $types = '';
        if ($countryId > 0) {
            $chkCol = $ctrl->query("SHOW COLUMNS FROM $table LIKE 'country_id'");
            if ($chkCol && $chkCol->num_rows > 0) {
                $where[] = 'country_id = ?';
                $params[] = $countryId;
                $types .= 'i';
            }
        }
        if ($agencyId > 0) {
            $chkCol = $ctrl->query("SHOW COLUMNS FROM $table LIKE 'agency_id'");
            if ($chkCol && $chkCol->num_rows > 0) {
                $where[] = 'agency_id = ?';
                $params[] = $agencyId;
                $types .= 'i';
            }
        }
        if ($module === 'journal_entries') {
            $where[] = "LOWER(TRIM(COALESCE(status,''))) IN ('posted','approved')";
        }
        $whereClause = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) as total FROM $table WHERE $whereClause";
        $total = 0;
        if ($params) {
            $st = $ctrl->prepare($countSql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $tr = $st->get_result();
            if ($tr) $total = (int) $tr->fetch_assoc()['total'];
        } else {
            $tr = $ctrl->query($countSql);
            if ($tr) $total = (int) $tr->fetch_assoc()['total'];
        }
        $orderBy = 'id';
        $orderDir = 'DESC';
        $sortCol = trim($_GET['sort'] ?? '');
        $orderParam = strtoupper(trim($_GET['order'] ?? 'desc'));
        if (in_array($orderParam, ['ASC', 'DESC'])) $orderDir = $orderParam;
        $listSql = "SELECT * FROM $table WHERE $whereClause ORDER BY $orderBy $orderDir LIMIT $limit OFFSET $offset";
        $list = [];
        if ($params) {
            $st = $ctrl->prepare($listSql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $res = $st->get_result();
        } else {
            $res = $ctrl->query($listSql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[] = $row;
            }
        }
        jsonOut(['success' => true, 'items' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
        exit;
    }

    // Financial Reports (summary)
    if ($action === 'financial_reports') {
        $summary = ['chart_accounts' => 0, 'journal_entries' => 0, 'expenses' => 0, 'receipts' => 0, 'total_revenue' => 0, 'total_expense' => 0];
        foreach (['control_chart_accounts', 'control_journal_entries', 'control_expenses', 'control_receipts'] as $t) {
            $chk = $ctrl->query("SHOW TABLES LIKE '$t'");
            if ($chk && $chk->num_rows > 0) {
                if ($t === 'control_journal_entries') {
                    $r = $ctrl->query("SELECT COUNT(*) as n FROM $t WHERE LOWER(TRIM(COALESCE(status,''))) IN ('posted','approved')");
                } else {
                    $r = $ctrl->query("SELECT COUNT(*) as n FROM $t");
                }
                if ($r) {
                    $summary[str_replace('control_', '', $t)] = (int) $r->fetch_assoc()['n'];
                }
            }
        }
        $r = $ctrl->query("SELECT type, SUM(amount) as total FROM control_accounting_transactions GROUP BY type");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                if ($row['type'] === 'revenue' || $row['type'] === 'settlement_in') $summary['total_revenue'] += (float)$row['total'];
                if ($row['type'] === 'settlement_out') $summary['total_expense'] += (float)$row['total'];
            }
        }
        jsonOut(['success' => true, 'summary' => $summary]);
        exit;
    }

    if ($action === 'financial_report_data') {
        require_once __DIR__ . '/../../includes/control/accounting-financial-report-data.php';
        $p = [
            'report_id' => trim((string) ($_GET['report_id'] ?? '')),
            'country_id' => isset($_GET['country_id']) && ctype_digit((string) $_GET['country_id']) ? (int) $_GET['country_id'] : 0,
            'start_date' => trim((string) ($_GET['start_date'] ?? '')),
            'end_date' => trim((string) ($_GET['end_date'] ?? '')),
            'as_of_date' => trim((string) ($_GET['as_of_date'] ?? '')),
            'search' => trim((string) ($_GET['search'] ?? '')),
            'account_id' => isset($_GET['account_id']) && ctype_digit((string) $_GET['account_id']) ? (int) $_GET['account_id'] : 0,
            'limit' => isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 200,
        ];
        $out = control_build_financial_report_payload($ctrl, $allowedCountryIds, $p);
        jsonOut($out);
        exit;
    }

    if ($action === 'chart_accounts_bulk_status') {
        if (!$canManageAccounting) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) && is_array($input['ids']) ? array_filter(array_map('intval', $input['ids'])) : [];
        $isActive = isset($input['is_active']) && (int)$input['is_active'] ? 1 : 0;
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No IDs provided']);
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Table not found']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $ctrl->prepare("UPDATE control_chart_accounts SET is_active = ? WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids) + 1);
        $params = array_merge([$isActive], $ids);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'updated' => $stmt->affected_rows]);
        }
        jsonOut(['success' => false, 'message' => 'Update failed']);
    }

    if ($action === 'countries_for_select') {
        $list = [];
        $countryWhere = "is_active = 1";
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $countryWhere .= " AND id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
        } elseif ($allowedCountryIds === []) {
            $countryWhere .= " AND 1=0";
        }
        $chkC = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
        if ($chkC && $chkC->num_rows > 0) {
            $r = $ctrl->query("SELECT id, name FROM control_countries WHERE $countryWhere ORDER BY sort_order, name");
            if ($r) while ($row = $r->fetch_assoc()) $list[] = ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        jsonOut(['success' => true, 'countries' => $list]);
        exit;
    }

    if ($action === 'agencies_for_select') {
        $list = [];
        $chkA = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
        if ($chkA && $chkA->num_rows > 0) {
            $countryClause = '';
            if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
                $countryClause = " AND country_id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
            } elseif ($allowedCountryIds === []) {
                $countryClause = " AND 1=0";
            }
            $sql = "SELECT id, name, country_id FROM control_agencies WHERE is_active = 1" . $countryClause;
            $params = [];
            $types = '';
            if ($countryId > 0) {
                $sql .= " AND country_id = ?";
                $params[] = $countryId;
                $types = 'i';
            }
            $sql .= " ORDER BY sort_order, name";
            if ($params) {
                $st = $ctrl->prepare($sql);
                $st->bind_param($types, ...$params);
                $st->execute();
                $r = $st->get_result();
            } else {
                $r = $ctrl->query($sql);
            }
            if ($r) while ($row = $r->fetch_assoc()) $list[] = ['id' => (int)$row['id'], 'name' => $row['name'], 'country_id' => (int)$row['country_id']];
        }
        jsonOut(['success' => true, 'agencies' => $list]);
        exit;
    }

    if ($action === 'chart_accounts_for_select') {
        $list = [];
        $chk = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if ($chk && $chk->num_rows > 0) {
            $sql = "SELECT id, account_code, account_name, account_type FROM control_chart_accounts WHERE is_active = 1";
            $params = [];
            $types = '';
            if ($countryId > 0) {
                $sql .= " AND (country_id = ? OR country_id = 0)";
                $params[] = $countryId;
                $types .= 'i';
            }
            if ($agencyId > 0) {
                $sql .= " AND (agency_id = ? OR agency_id = 0)";
                $params[] = $agencyId;
                $types .= 'i';
            }
            $sql .= " ORDER BY account_code";
            if ($params) {
                $st = $ctrl->prepare($sql);
                $st->bind_param($types, ...$params);
                $st->execute();
                $r = $st->get_result();
            } else {
                $r = $ctrl->query($sql);
            }
            if ($r) while ($row = $r->fetch_assoc()) $list[] = ['id' => (int)$row['id'], 'account_code' => $row['account_code'], 'account_name' => $row['account_name'], 'account_type' => $row['account_type']];
        }
        jsonOut(['success' => true, 'accounts' => $list]);
        exit;
    }

    if ($action === 'next_journal_reference') {
        if (!$canManageAccounting) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        jsonOut(['success' => true, 'reference' => control_next_gl_journal_reference($ctrl)]);
        exit;
    }

    if ($action === 'next_expense_voucher') {
        if (!$canManageAccounting) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        jsonOut(['success' => true, 'voucher_number' => control_next_expense_voucher($ctrl)]);
        exit;
    }

    if ($action === 'next_support_payment_number') {
        if (!$canManageAccounting) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $chkS = $ctrl->query("SHOW TABLES LIKE 'control_support_payments'");
        if (!$chkS || $chkS->num_rows === 0 || !control_table_has_column($ctrl, 'control_support_payments', 'payment_number')) {
            jsonOut(['success' => false, 'message' => 'Support payment numbers not available (run DB migration).']);
        }
        jsonOut(['success' => true, 'payment_number' => control_next_support_payment_number($ctrl)]);
        exit;
    }

    if ($action === 'journal_entry_detail') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $jid = isset($_GET['journal_entry_id']) ? (int) $_GET['journal_entry_id'] : 0;
        if ($jid <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid journal entry']);
        }
        $chkJ = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
        if (!$chkJ || $chkJ->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Journal table not found']);
        }
        $entry = $ctrl->query('SELECT * FROM control_journal_entries WHERE id = ' . $jid . ' LIMIT 1')->fetch_assoc();
        if (!$entry) {
            jsonOut(['success' => false, 'message' => 'Journal entry not found']);
        }
        if (!control_journal_country_allowed($entry, $allowedCountryIds)) {
            jsonOut(['success' => false, 'message' => 'Access denied for this journal entry']);
        }
        $lines = [];
        $chkL = $ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
        if ($chkL && $chkL->num_rows > 0) {
            $lr = $ctrl->query('SELECT * FROM control_journal_entry_lines WHERE journal_entry_id = ' . $jid . ' ORDER BY id ASC');
            if ($lr) {
                while ($row = $lr->fetch_assoc()) {
                    $lines[] = $row;
                }
            }
        }
        jsonOut(['success' => true, 'entry' => $entry, 'lines' => $lines]);
        exit;
    }

    jsonOut(['success' => false, 'message' => 'Unknown action']);
}

// POST - add transaction or module item
if ($method === 'POST') {
    if (!$canManageAccounting) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $rawInput = file_get_contents('php://input');
    $input = is_string($rawInput) && $rawInput !== '' ? (json_decode($rawInput, true) ?: []) : $_POST;
    if (!is_array($input)) $input = [];
    if (!control_validate_csrf_token($input)) {
        jsonOut(['success' => false, 'message' => 'Invalid CSRF token']);
    }
    $addModule = trim($input['_module'] ?? '');
    $postAction = trim($_GET['action'] ?? '');

    if ($postAction === 'sync_registration_accounting') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        require_once __DIR__ . '/../../includes/registration-accounting-sync.php';
        $limit = isset($input['limit']) ? (int) $input['limit'] : 2000;
        $result = backfillRegistrationAccountingSync($ctrl, $limit);
        jsonOut(['success' => true, 'message' => 'Sync complete', 'result' => $result]);
    }

    if ($postAction === 'normalize_numbers') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $hasJ = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
        $hasR = $ctrl->query("SHOW TABLES LIKE 'control_receipts'");
        if (!$hasJ || $hasJ->num_rows === 0 || !$hasR || $hasR->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Required tables not found']);
        }
        $lockName = 'control_normalize_gl_rc_numbers';
        if (!control_acquire_mysql_lock($ctrl, $lockName, 10)) {
            jsonOut(['success' => false, 'message' => 'Could not acquire normalization lock. Try again.']);
        }
        $useTx = @$ctrl->begin_transaction();
        try {
            $stats = control_normalize_gl_rc_numbers($ctrl);
            if ($useTx) {
                $ctrl->commit();
            }
            control_release_mysql_lock($ctrl, $lockName);
            jsonOut([
                'success' => true,
                'message' => 'Normalization completed',
                'result' => $stats
            ]);
        } catch (Throwable $e) {
            if ($useTx) {
                $ctrl->rollback();
            }
            control_release_mysql_lock($ctrl, $lockName);
            jsonOut(['success' => false, 'message' => 'Normalization failed']);
        }
    }

    if ($postAction === 'entry_approvals_mutate') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $op = strtolower(trim($input['op'] ?? ''));
        $ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_unique(array_filter(array_map('intval', $input['ids'])))) : [];
        $selectedCount = count($ids);
        $rejectReason = trim((string) ($input['reject_reason'] ?? ''));
        if (!in_array($op, ['approve', 'reject', 'undo'], true) || empty($ids)) {
            jsonOut(['success' => false, 'message' => 'Invalid operation or no entries selected']);
        }
        if ($op === 'reject') {
            if ($rejectReason === '') {
                jsonOut(['success' => false, 'message' => 'Please choose a rejection reason']);
            }
            if (!in_array($rejectReason, control_rejection_reason_choices(), true)) {
                jsonOut(['success' => false, 'message' => 'Invalid rejection reason']);
            }
        }
        $chkA = $ctrl->query("SHOW TABLES LIKE 'control_entry_approvals'");
        if (!$chkA || $chkA->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Approvals table not found']);
        }
        $hasRejectReasonCol = false;
        $chkReasonCol = @$ctrl->query("SHOW COLUMNS FROM control_entry_approvals LIKE 'rejection_reason'");
        if ($chkReasonCol && $chkReasonCol->num_rows > 0) {
            $hasRejectReasonCol = true;
        }
        $adminId = 0;
        $chkAd = $ctrl->query("SHOW TABLES LIKE 'control_admins'");
        if ($chkAd && $chkAd->num_rows > 0 && !empty($_SESSION['control_username'])) {
            $st = $ctrl->prepare('SELECT id FROM control_admins WHERE username = ? LIMIT 1');
            $un = $_SESSION['control_username'];
            $st->bind_param('s', $un);
            $st->execute();
            $rr = $st->get_result();
            if ($rr && $rr->num_rows > 0) {
                $adminId = (int) $rr->fetch_assoc()['id'];
            }
        }
        $ids = control_filter_approval_ids_by_country($ctrl, $ids, $allowedCountryIds);
        $scopeCount = count($ids);
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No selected approvals are allowed for your country scope']);
        }
        $idList = implode(',', array_map('intval', $ids));
        $useTx = @$ctrl->begin_transaction();
        if ($op === 'approve') {
            $approveSet = "status = 'approved', approved_by = " . (int) $adminId . ", approved_at = NOW()";
            if ($hasRejectReasonCol) {
                $approveSet .= ", rejection_reason = NULL";
            }
            $ok1 = $ctrl->query("UPDATE control_entry_approvals SET $approveSet WHERE id IN ($idList) AND LOWER(TRIM(status)) = 'pending'");
            if (!$ok1) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Approval update failed']);
            }
            $appr = $ctrl->affected_rows;
            $ok2 = $ctrl->query("UPDATE control_journal_entries j INNER JOIN control_entry_approvals a ON j.id = a.journal_entry_id SET j.status = 'posted' WHERE a.id IN ($idList) AND a.status = 'approved'");
            if (!$ok2) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Journal status update failed']);
            }
            $jrn = $ctrl->affected_rows;
        } elseif ($op === 'reject') {
            $rejectSet = "status = 'rejected', approved_by = NULL, approved_at = NULL";
            if ($hasRejectReasonCol) {
                $rejectSet .= ", rejection_reason = '" . $ctrl->real_escape_string($rejectReason) . "'";
            }
            $ok1 = $ctrl->query("UPDATE control_entry_approvals SET $rejectSet WHERE id IN ($idList) AND LOWER(TRIM(status)) = 'pending'");
            if (!$ok1) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Rejection update failed']);
            }
            $appr = $ctrl->affected_rows;
            $ok2 = $ctrl->query("UPDATE control_journal_entries j INNER JOIN control_entry_approvals a ON j.id = a.journal_entry_id SET j.status = 'draft' WHERE a.id IN ($idList) AND a.status = 'rejected'");
            if (!$ok2) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Journal status update failed']);
            }
            $jrn = $ctrl->affected_rows;
        } else {
            $undoSet = "status = 'pending', approved_by = NULL, approved_at = NULL";
            if ($hasRejectReasonCol) {
                $undoSet .= ", rejection_reason = NULL";
            }
            $ok1 = $ctrl->query("UPDATE control_entry_approvals SET $undoSet WHERE id IN ($idList) AND LOWER(TRIM(status)) IN ('approved','rejected')");
            if (!$ok1) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Undo update failed']);
            }
            $appr = $ctrl->affected_rows;
            $ok2 = $ctrl->query("UPDATE control_journal_entries j INNER JOIN control_entry_approvals a ON j.id = a.journal_entry_id SET j.status = 'draft' WHERE a.id IN ($idList) AND LOWER(TRIM(a.status)) = 'pending'");
            if (!$ok2) {
                if ($useTx) $ctrl->rollback();
                jsonOut(['success' => false, 'message' => 'Journal status update failed']);
            }
            $jrn = $ctrl->affected_rows;
        }
        if ($useTx) {
            $ctrl->commit();
        }
        $msg = $op === 'approve' ? 'Approved' : ($op === 'reject' ? 'Rejected' : 'Reverted to pending');
        $skipped = max(0, $scopeCount - (int) $appr);
        jsonOut([
            'success' => true,
            'message' => $msg,
            'approvals_updated' => (int) $appr,
            'journals_updated' => (int) $jrn,
            'selected_count' => (int) $selectedCount,
            'in_scope_count' => (int) $scopeCount,
            'skipped_count' => (int) $skipped
        ]);
    }

    if ($postAction === 'journal_entry_save') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $jid = isset($input['journal_entry_id']) ? (int) $input['journal_entry_id'] : 0;
        if ($jid <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid journal entry']);
        }
        $chkJ = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
        $chkL = $ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
        if (!$chkJ || $chkJ->num_rows === 0 || !$chkL || $chkL->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Journal tables not found']);
        }
        $ex = $ctrl->query('SELECT * FROM control_journal_entries WHERE id = ' . $jid . ' LIMIT 1')->fetch_assoc();
        if (!$ex) {
            jsonOut(['success' => false, 'message' => 'Journal entry not found']);
        }
        if (!control_journal_country_allowed($ex, $allowedCountryIds)) {
            jsonOut(['success' => false, 'message' => 'Access denied for this journal entry']);
        }
        $reference = trim(control_normalize_ascii_digits((string) ($input['reference'] ?? '')));
        $entryDate = trim(control_normalize_ascii_digits((string) ($input['entry_date'] ?? '')));
        if ($reference === '' || $entryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
            jsonOut(['success' => false, 'message' => 'Reference and a valid entry date (Y-m-d) are required']);
        }
        $description = trim((string) ($input['description'] ?? ''));
        $linesIn = $input['lines'] ?? null;
        if (!is_array($linesIn)) {
            jsonOut(['success' => false, 'message' => 'Lines must be an array']);
        }
        $lines = [];
        foreach ($linesIn as $L) {
            if (!is_array($L)) {
                continue;
            }
            $d = round((float) ($L['debit'] ?? 0), 2);
            $c = round((float) ($L['credit'] ?? 0), 2);
            if ($d < 0 || $c < 0) {
                jsonOut(['success' => false, 'message' => 'Negative amounts are not allowed']);
            }
            if ($d == 0.0 && $c == 0.0) {
                continue;
            }
            if ($d > 0 && $c > 0) {
                jsonOut(['success' => false, 'message' => 'Each line must be either debit or credit, not both']);
            }
            $accountId = isset($L['account_id']) ? (int) $L['account_id'] : 0;
            $accountName = trim((string) ($L['account_name'] ?? ''));
            $accountCode = trim((string) ($L['account_code'] ?? ''));
            $lineDesc = trim((string) ($L['description'] ?? ''));
            if ($accountId <= 0 && $accountName === '') {
                jsonOut(['success' => false, 'message' => 'Each line needs a chart account or an account name']);
            }
            if ($accountId > 0) {
                $ca = $ctrl->query('SELECT id, account_code, account_name FROM control_chart_accounts WHERE id = ' . $accountId . ' LIMIT 1')->fetch_assoc();
                if (!$ca) {
                    jsonOut(['success' => false, 'message' => 'Invalid account ID on a line']);
                }
                $accountCode = (string) $ca['account_code'];
                $accountName = (string) $ca['account_name'];
            }
            $lines[] = [
                'account_id' => $accountId > 0 ? $accountId : null,
                'account_code' => $accountCode,
                'account_name' => $accountName,
                'debit' => $d,
                'credit' => $c,
                'description' => $lineDesc,
            ];
        }
        if (count($lines) < 1) {
            jsonOut(['success' => false, 'message' => 'Add at least one line with a debit or credit amount']);
        }
        $sumD = 0.0;
        $sumC = 0.0;
        foreach ($lines as $ln) {
            $sumD += $ln['debit'];
            $sumC += $ln['credit'];
        }
        if (abs($sumD - $sumC) > 0.009) {
            jsonOut(['success' => false, 'message' => 'Total debits must equal total credits']);
        }
        if ($sumD <= 0) {
            jsonOut(['success' => false, 'message' => 'Total amount must be greater than zero']);
        }

        $useTx = @$ctrl->begin_transaction();
        try {
            $stU = $ctrl->prepare('UPDATE control_journal_entries SET reference = ?, entry_date = ?, description = ?, total_debit = ?, total_credit = ? WHERE id = ?');
            $stU->bind_param('sssddi', $reference, $entryDate, $description, $sumD, $sumC, $jid);
            if (!$stU->execute()) {
                throw new Exception('update');
            }
            $stU->close();
            if (!$ctrl->query('DELETE FROM control_journal_entry_lines WHERE journal_entry_id = ' . $jid)) {
                throw new Exception('del');
            }
            $stWithId = $ctrl->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,?,?,?,?,?,?)');
            $stNoId = $ctrl->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,NULL,?,?,?,?,?)');
            if (!$stWithId || !$stNoId) {
                throw new Exception('prepare');
            }
            foreach ($lines as $ln) {
                $accId = $ln['account_id'];
                $acode = $ln['account_code'] !== '' ? $ln['account_code'] : '';
                $aname = $ln['account_name'];
                $d = $ln['debit'];
                $c = $ln['credit'];
                $ld = $ln['description'];
                if ($accId === null) {
                    $stNoId->bind_param('issdds', $jid, $acode, $aname, $d, $c, $ld);
                    if (!$stNoId->execute()) {
                        throw new Exception('ins0');
                    }
                } else {
                    $stWithId->bind_param('iissdds', $jid, $accId, $acode, $aname, $d, $c, $ld);
                    if (!$stWithId->execute()) {
                        throw new Exception('ins1');
                    }
                }
            }
            $stWithId->close();
            $stNoId->close();
            if ($useTx) {
                $ctrl->commit();
            }
            jsonOut(['success' => true, 'message' => 'Journal saved', 'journal_entry_id' => $jid]);
        } catch (Exception $e) {
            if ($useTx) {
                $ctrl->rollback();
            }
            jsonOut(['success' => false, 'message' => 'Save failed']);
        }
    }

    if ($postAction === 'journal_entry_create') {
        if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $chkJ = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
        $chkL = $ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
        if (!$chkJ || $chkJ->num_rows === 0 || !$chkL || $chkL->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Journal tables not found']);
        }
        $countryIdIn = max(0, (int) ($input['country_id'] ?? 0));
        if ($allowedCountryIds !== null && !empty($allowedCountryIds) && $countryIdIn > 0 && !in_array($countryIdIn, array_map('intval', $allowedCountryIds), true)) {
            jsonOut(['success' => false, 'message' => 'Country not allowed for this user']);
        }
        if ($allowedCountryIds === [] && $countryIdIn > 0) {
            jsonOut(['success' => false, 'message' => 'No country access']);
        }
        $entryDate = trim(control_normalize_ascii_digits((string) ($input['entry_date'] ?? '')));
        if ($entryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
            jsonOut(['success' => false, 'message' => 'A valid entry date (Y-m-d) is required']);
        }
        $description = trim((string) ($input['description'] ?? ''));
        $linesIn = $input['lines'] ?? null;
        if (!is_array($linesIn)) {
            jsonOut(['success' => false, 'message' => 'Lines must be an array']);
        }
        $lines = [];
        foreach ($linesIn as $L) {
            if (!is_array($L)) {
                continue;
            }
            $d = round((float) ($L['debit'] ?? 0), 2);
            $c = round((float) ($L['credit'] ?? 0), 2);
            if ($d < 0 || $c < 0) {
                jsonOut(['success' => false, 'message' => 'Negative amounts are not allowed']);
            }
            if ($d == 0.0 && $c == 0.0) {
                continue;
            }
            if ($d > 0 && $c > 0) {
                jsonOut(['success' => false, 'message' => 'Each line must be either debit or credit, not both']);
            }
            $accountId = isset($L['account_id']) ? (int) $L['account_id'] : 0;
            $accountName = trim((string) ($L['account_name'] ?? ''));
            $accountCode = trim((string) ($L['account_code'] ?? ''));
            $lineDesc = trim((string) ($L['description'] ?? ''));
            if ($accountId <= 0 && $accountName === '') {
                jsonOut(['success' => false, 'message' => 'Each line needs a chart account or an account name']);
            }
            if ($accountId > 0) {
                $ca = $ctrl->query('SELECT id, account_code, account_name FROM control_chart_accounts WHERE id = ' . $accountId . ' LIMIT 1')->fetch_assoc();
                if (!$ca) {
                    jsonOut(['success' => false, 'message' => 'Invalid account ID on a line']);
                }
                $accountCode = (string) $ca['account_code'];
                $accountName = (string) $ca['account_name'];
            }
            $lines[] = [
                'account_id' => $accountId > 0 ? $accountId : null,
                'account_code' => $accountCode,
                'account_name' => $accountName,
                'debit' => $d,
                'credit' => $c,
                'description' => $lineDesc,
            ];
        }
        if (count($lines) < 1) {
            jsonOut(['success' => false, 'message' => 'Add at least one line with a debit or credit amount']);
        }
        $sumD = 0.0;
        $sumC = 0.0;
        foreach ($lines as $ln) {
            $sumD += $ln['debit'];
            $sumC += $ln['credit'];
        }
        if (abs($sumD - $sumC) > 0.009) {
            jsonOut(['success' => false, 'message' => 'Total debits must equal total credits']);
        }
        if ($sumD <= 0) {
            jsonOut(['success' => false, 'message' => 'Total amount must be greater than zero']);
        }

        $lockName = 'control_gl_ref_global';
        if (!control_acquire_mysql_lock($ctrl, $lockName, 8)) {
            jsonOut(['success' => false, 'message' => 'Could not reserve journal reference. Please try again.']);
        }
        $useTx = @$ctrl->begin_transaction();
        try {
            $reference = control_next_gl_journal_reference($ctrl);
            $stInsert = $ctrl->prepare('INSERT INTO control_journal_entries (agency_id, country_id, reference, entry_date, description, total_debit, total_credit, status) VALUES (0,?,?,?,?,?,?,?)');
            $stDraft = 'draft';
            $stInsert->bind_param('isssdds', $countryIdIn, $reference, $entryDate, $description, $sumD, $sumC, $stDraft);
            if (!$stInsert->execute()) {
                throw new Exception('insert');
            }
            $jid = (int) $ctrl->insert_id;
            $stInsert->close();
            $stWithId = $ctrl->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,?,?,?,?,?,?)');
            $stNoId = $ctrl->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,NULL,?,?,?,?,?)');
            if (!$stWithId || !$stNoId) {
                throw new Exception('prepare');
            }
            foreach ($lines as $ln) {
                $accId = $ln['account_id'];
                $acode = $ln['account_code'] !== '' ? $ln['account_code'] : '';
                $aname = $ln['account_name'];
                $d = $ln['debit'];
                $c = $ln['credit'];
                $ld = $ln['description'];
                if ($accId === null) {
                    $stNoId->bind_param('issdds', $jid, $acode, $aname, $d, $c, $ld);
                    if (!$stNoId->execute()) {
                        throw new Exception('ins0');
                    }
                } else {
                    $stWithId->bind_param('iissdds', $jid, $accId, $acode, $aname, $d, $c, $ld);
                    if (!$stWithId->execute()) {
                        throw new Exception('ins1');
                    }
                }
            }
            $stWithId->close();
            $stNoId->close();
            $chkA = $ctrl->query("SHOW TABLES LIKE 'control_entry_approvals'");
            if ($chkA && $chkA->num_rows > 0) {
                $ctrl->query('INSERT INTO control_entry_approvals (journal_entry_id, status) VALUES (' . $jid . ", 'pending')");
            }
            if ($useTx) {
                $ctrl->commit();
            }
            control_release_mysql_lock($ctrl, $lockName);
            jsonOut(['success' => true, 'message' => 'Journal created', 'journal_entry_id' => $jid, 'reference' => $reference]);
        } catch (Exception $e) {
            if ($useTx) {
                $ctrl->rollback();
            }
            control_release_mysql_lock($ctrl, $lockName);
            jsonOut(['success' => false, 'message' => 'Create failed']);
        }
    }

    // Bulk activate/deactivate chart accounts (POST with action in query string)
    if ($postAction === 'chart_accounts_bulk_status') {
        $ids = isset($input['ids']) && is_array($input['ids']) ? array_filter(array_map('intval', $input['ids'])) : [];
        $isActive = isset($input['is_active']) && (int)$input['is_active'] ? 1 : 0;
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No IDs provided']);
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Table not found']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $ctrl->prepare("UPDATE control_chart_accounts SET is_active = ? WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids) + 1);
        $params = array_merge([$isActive], $ids);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'updated' => $stmt->affected_rows]);
        }
        jsonOut(['success' => false, 'message' => 'Update failed']);
    }

    // Explicit add chart account (so we never fall through to transaction validation)
    if ($addModule === 'chart_accounts') {
        $accountCode = trim($input['account_code'] ?? '');
        $accountName = trim($input['account_name'] ?? '');
        $accountType = trim($input['account_type'] ?? 'Asset');
        $agencyId = isset($input['agency_id']) ? (int)$input['agency_id'] : 0;
        $countryId = isset($input['country_id']) ? (int)$input['country_id'] : 0;
        $validTypes = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        if ($accountCode === '') {
            jsonOut(['success' => false, 'message' => 'Account code is required']);
        }
        if ($accountName === '') {
            jsonOut(['success' => false, 'message' => 'Account name is required']);
        }
        if (!in_array($accountType, $validTypes, true)) {
            jsonOut(['success' => false, 'message' => 'Account type must be one of: Asset, Liability, Equity, Income, Expense']);
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Chart of accounts table not found']);
        }
        $stmt = $ctrl->prepare("INSERT INTO control_chart_accounts (agency_id, country_id, account_code, account_name, account_type, balance, currency_code, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $currency = trim($input['currency_code'] ?? 'SAR') ?: 'SAR';
        $balance = isset($input['balance']) ? (float)$input['balance'] : 0;
        $stmt->bind_param('iissdss', $agencyId, $countryId, $accountCode, $accountName, $accountType, $balance, $currency);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'id' => (int)$ctrl->insert_id]);
        }
        jsonOut(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }

    // Update chart account (POST with _action=update_chart_account)
    if (trim($input['_action'] ?? '') === 'update_chart_account') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $accountCode = trim($input['account_code'] ?? '');
        $accountName = trim($input['account_name'] ?? '');
        $accountType = trim($input['account_type'] ?? 'Asset');
        $balance = isset($input['balance']) ? (float)$input['balance'] : 0;
        $currency = trim($input['currency_code'] ?? 'SAR') ?: 'SAR';
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
        $validTypes = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        if ($id <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid account ID']);
        }
        if ($accountCode === '') {
            jsonOut(['success' => false, 'message' => 'Account code is required']);
        }
        if ($accountName === '') {
            jsonOut(['success' => false, 'message' => 'Account name is required']);
        }
        if (!in_array($accountType, $validTypes, true)) {
            jsonOut(['success' => false, 'message' => 'Account type must be one of: Asset, Liability, Equity, Income, Expense']);
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Chart of accounts table not found']);
        }
        $stmt = $ctrl->prepare("UPDATE control_chart_accounts SET account_code = ?, account_name = ?, account_type = ?, balance = ?, currency_code = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param('sssdsii', $accountCode, $accountName, $accountType, $balance, $currency, $isActive, $id);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'updated' => $stmt->affected_rows]);
        }
        jsonOut(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }

    // Update cost center (POST with _action=update_cost_center)
    if (trim($input['_action'] ?? '') === 'update_cost_center') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $code = trim((string) ($input['code'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid cost center ID']);
        if ($code === '') jsonOut(['success' => false, 'message' => 'Cost center code is required']);
        if ($name === '') jsonOut(['success' => false, 'message' => 'Cost center name is required']);

        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_cost_centers'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Cost centers table not found']);
        }

        $hasDesc = false;
        $chkC = @$ctrl->query("SHOW COLUMNS FROM control_cost_centers LIKE 'description'");
        if ($chkC && $chkC->num_rows > 0) $hasDesc = true;

        if ($hasDesc) {
            $sql = 'UPDATE control_cost_centers SET code = ?, name = ?, description = ?, is_active = ? WHERE id = ?';
            $st = $ctrl->prepare($sql);
            if (!$st) jsonOut(['success' => false, 'message' => 'Query failed']);
            $descVal = $description !== '' ? $description : '';
            $st->bind_param('sssii', $code, $name, $descVal, $isActive, $id);
        } else {
            $sql = 'UPDATE control_cost_centers SET code = ?, name = ?, is_active = ? WHERE id = ?';
            $st = $ctrl->prepare($sql);
            if (!$st) jsonOut(['success' => false, 'message' => 'Query failed']);
            $st->bind_param('ssii', $code, $name, $isActive, $id);
        }
        $st->execute();
        jsonOut(['success' => true, 'updated' => (int) $st->affected_rows]);
    }

    // Update bank guarantee (POST with _action=update_bank_guarantee)
    if (trim($input['_action'] ?? '') === 'update_bank_guarantee') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $reference = trim((string) ($input['reference'] ?? ''));
        $bankName = trim((string) ($input['bank_name'] ?? ''));
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
        $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
        $startDate = trim((string) ($input['start_date'] ?? ''));
        $endDate = trim((string) ($input['end_date'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'active'));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid bank guarantee ID']);
        if ($reference === '') jsonOut(['success' => false, 'message' => 'Reference is required']);

        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_bank_guarantees'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Bank guarantees table not found']);
        }

        $hasNotes = false;
        $chkN = @$ctrl->query("SHOW COLUMNS FROM control_bank_guarantees LIKE 'notes'");
        if ($chkN && $chkN->num_rows > 0) $hasNotes = true;

        $bankNameVal = $bankName !== '' ? $bankName : null;
        $notesVal = $notes !== '' ? $notes : null;
        $startDateVal = ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ? $startDate : null;
        $endDateVal = ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) ? $endDate : null;
        $amountStr = (string) $amount;

        if ($hasNotes) {
            $sql = 'UPDATE control_bank_guarantees
                    SET reference = ?, bank_name = ?, amount = ?, currency_code = ?, start_date = ?, end_date = ?, status = ?, notes = ?
                    WHERE id = ?';
            $st = $ctrl->prepare($sql);
            if (!$st) jsonOut(['success' => false, 'message' => 'Query failed']);
            $st->bind_param('ssssssssi', $reference, $bankNameVal, $amountStr, $currency, $startDateVal, $endDateVal, $status, $notesVal, $id);
        } else {
            $sql = 'UPDATE control_bank_guarantees
                    SET reference = ?, bank_name = ?, amount = ?, currency_code = ?, start_date = ?, end_date = ?, status = ?
                    WHERE id = ?';
            $st = $ctrl->prepare($sql);
            if (!$st) jsonOut(['success' => false, 'message' => 'Query failed']);
            $st->bind_param('sssssssi', $reference, $bankNameVal, $amountStr, $currency, $startDateVal, $endDateVal, $status, $id);
        }
        $st->execute();
        jsonOut(['success' => true, 'updated' => (int) $st->affected_rows]);
    }

    // Update receipt (POST with _action=update_receipt)
    if (trim($input['_action'] ?? '') === 'update_receipt') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $receiptDate = trim((string) ($input['receipt_date'] ?? ''));
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
        $description = trim((string) ($input['description'] ?? ''));
        $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
        $status = strtolower(trim((string) ($input['status'] ?? 'completed')));
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid receipt ID']);
        if ($receiptDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receiptDate)) {
            jsonOut(['success' => false, 'message' => 'Receipt date must be YYYY-MM-DD']);
        }
        if ($amount <= 0) {
            jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
        }
        if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
            $status = 'completed';
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_receipts'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Receipts table not found']);
        }
        $hasLines = control_table_has_column($ctrl, 'control_receipts', 'lines_json');
        $linesJson = null;
        $touchLines = array_key_exists('lines', $input);
        if ($touchLines && $hasLines) {
            $linesJson = control_encode_receipt_expense_lines_payload($input['lines']);
        }
        if ($hasLines && $touchLines) {
            $st = $ctrl->prepare('UPDATE control_receipts SET receipt_date = ?, amount = ?, description = ?, currency_code = ?, status = ?, lines_json = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sdssssi', $receiptDate, $amount, $description, $currency, $status, $linesJson, $id);
        } else {
            $st = $ctrl->prepare('UPDATE control_receipts SET receipt_date = ?, amount = ?, description = ?, currency_code = ?, status = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sdsssi', $receiptDate, $amount, $description, $currency, $status, $id);
        }
        $st->execute();
        jsonOut(['success' => true, 'updated' => (int) $st->affected_rows]);
    }

    // Update expense (POST with _action=update_expense)
    if (trim($input['_action'] ?? '') === 'update_expense') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $expenseDate = trim((string) ($input['expense_date'] ?? ''));
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
        $description = trim((string) ($input['description'] ?? ''));
        $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
        $status = strtolower(trim((string) ($input['status'] ?? 'pending')));
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid expense ID']);
        if ($expenseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            jsonOut(['success' => false, 'message' => 'Expense date must be YYYY-MM-DD']);
        }
        if ($amount <= 0) {
            jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
        }
        if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
            $status = 'pending';
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_expenses'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Expenses table not found']);
        }
        $hasLines = control_table_has_column($ctrl, 'control_expenses', 'lines_json');
        $linesJson = null;
        $touchLines = array_key_exists('lines', $input);
        if ($touchLines && $hasLines) {
            $linesJson = control_encode_receipt_expense_lines_payload($input['lines']);
        }
        if ($hasLines && $touchLines) {
            $st = $ctrl->prepare('UPDATE control_expenses SET expense_date = ?, amount = ?, description = ?, currency_code = ?, status = ?, lines_json = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sdssssi', $expenseDate, $amount, $description, $currency, $status, $linesJson, $id);
        } else {
            $st = $ctrl->prepare('UPDATE control_expenses SET expense_date = ?, amount = ?, description = ?, currency_code = ?, status = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sdsssi', $expenseDate, $amount, $description, $currency, $status, $id);
        }
        $st->execute();
        jsonOut(['success' => true, 'updated' => (int) $st->affected_rows]);
    }

    // Update support payment (POST with _action=update_support_payment)
    if (trim($input['_action'] ?? '') === 'update_support_payment') {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $paymentDate = trim(control_normalize_ascii_digits((string) ($input['payment_date'] ?? '')));
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
        $description = trim((string) ($input['description'] ?? ''));
        $reference = trim((string) ($input['reference'] ?? ''));
        $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
        $status = strtolower(trim((string) ($input['status'] ?? 'completed')));
        $countryIdIn = isset($input['country_id']) ? (int) $input['country_id'] : 0;
        if ($id <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid support payment ID']);
        }
        if ($paymentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            jsonOut(['success' => false, 'message' => 'Payment date must be YYYY-MM-DD']);
        }
        if ($amount <= 0) {
            jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
        }
        if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
            $status = 'completed';
        }
        if ($countryIdIn > 0 && $allowedCountryIds !== null && !in_array($countryIdIn, $allowedCountryIds, true)) {
            jsonOut(['success' => false, 'message' => 'Country not allowed for this user']);
        }
        if ($allowedCountryIds === [] && $countryIdIn > 0) {
            jsonOut(['success' => false, 'message' => 'No country access']);
        }
        $chkT = $ctrl->query("SHOW TABLES LIKE 'control_support_payments'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Support payments table not found']);
        }
        $hasLinesSp = control_table_has_column($ctrl, 'control_support_payments', 'lines_json');
        $linesJsonSp = null;
        $touchLinesSp = array_key_exists('lines', $input);
        if ($touchLinesSp && $hasLinesSp) {
            $linesJsonSp = control_encode_receipt_expense_lines_payload($input['lines']);
        }
        if ($hasLinesSp && $touchLinesSp) {
            $st = $ctrl->prepare('UPDATE control_support_payments SET payment_date = ?, country_id = ?, amount = ?, currency_code = ?, description = ?, reference = ?, status = ?, lines_json = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sidsssssi', $paymentDate, $countryIdIn, $amount, $currency, $description, $reference, $status, $linesJsonSp, $id);
        } else {
            $st = $ctrl->prepare('UPDATE control_support_payments SET payment_date = ?, country_id = ?, amount = ?, currency_code = ?, description = ?, reference = ?, status = ? WHERE id = ?');
            if (!$st) {
                jsonOut(['success' => false, 'message' => 'Query failed']);
            }
            $st->bind_param('sidssssi', $paymentDate, $countryIdIn, $amount, $currency, $description, $reference, $status, $id);
        }
        $st->execute();
        jsonOut(['success' => true, 'updated' => (int) $st->affected_rows]);
    }

    if ($addModule && isset($MODULES[$addModule])) {
        if ($addModule === 'receipts') {
            $chkT = $ctrl->query("SHOW TABLES LIKE 'control_receipts'");
            if (!$chkT || $chkT->num_rows === 0) {
                jsonOut(['success' => false, 'message' => 'Receipts table not found']);
            }
            $receiptDate = trim(control_normalize_ascii_digits((string) ($input['receipt_date'] ?? date('Y-m-d'))));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $receiptDate)) {
                jsonOut(['success' => false, 'message' => 'Receipt date must be YYYY-MM-DD']);
            }
            $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
            if ($amount <= 0) {
                jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
            }
            $description = trim((string) ($input['description'] ?? ''));
            $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
            $status = strtolower(trim((string) ($input['status'] ?? 'completed')));
            if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
                $status = 'completed';
            }
            $agencyIdIn = isset($input['agency_id']) ? (int) $input['agency_id'] : 0;
            $countryIdIn = isset($input['country_id']) ? (int) $input['country_id'] : 0;
            if ($countryIdIn > 0 && $allowedCountryIds !== null && !in_array($countryIdIn, $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'Country not allowed for this user']);
            }
            $receiptNumberIn = trim((string) ($input['receipt_number'] ?? ''));
            if ($receiptNumberIn === '') {
                $lockName = 'control_receipt_number_global';
                if (!control_acquire_mysql_lock($ctrl, $lockName, 8)) {
                    jsonOut(['success' => false, 'message' => 'Could not reserve receipt number. Please try again.']);
                }
                try {
                    $receiptNumberIn = control_next_receipt_number($ctrl);
                    control_release_mysql_lock($ctrl, $lockName);
                } catch (Exception $e) {
                    control_release_mysql_lock($ctrl, $lockName);
                    jsonOut(['success' => false, 'message' => 'Could not create receipt number']);
                }
            }
            $hasLinesR = control_table_has_column($ctrl, 'control_receipts', 'lines_json');
            $linesJsonIns = null;
            if (array_key_exists('lines', $input) && $hasLinesR) {
                $linesJsonIns = control_encode_receipt_expense_lines_payload($input['lines']);
            }
            if ($hasLinesR) {
                $st = $ctrl->prepare('INSERT INTO control_receipts (agency_id, country_id, receipt_number, receipt_date, amount, currency_code, description, status, lines_json) VALUES (?,?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iissdssss', $agencyIdIn, $countryIdIn, $receiptNumberIn, $receiptDate, $amount, $currency, $description, $status, $linesJsonIns);
            } else {
                $st = $ctrl->prepare('INSERT INTO control_receipts (agency_id, country_id, receipt_number, receipt_date, amount, currency_code, description, status) VALUES (?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iissdsss', $agencyIdIn, $countryIdIn, $receiptNumberIn, $receiptDate, $amount, $currency, $description, $status);
            }
            if ($st->execute()) {
                jsonOut(['success' => true, 'id' => (int) $ctrl->insert_id, 'receipt_number' => $receiptNumberIn]);
            }
            jsonOut(['success' => false, 'message' => 'Insert failed: ' . $st->error]);
        }

        if ($addModule === 'expenses') {
            $chkT = $ctrl->query("SHOW TABLES LIKE 'control_expenses'");
            if (!$chkT || $chkT->num_rows === 0) {
                jsonOut(['success' => false, 'message' => 'Expenses table not found']);
            }
            $expenseDate = trim(control_normalize_ascii_digits((string) ($input['expense_date'] ?? date('Y-m-d'))));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
                jsonOut(['success' => false, 'message' => 'Expense date must be YYYY-MM-DD']);
            }
            $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
            if ($amount <= 0) {
                jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
            }
            $description = trim((string) ($input['description'] ?? ''));
            $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
            $status = strtolower(trim((string) ($input['status'] ?? 'pending')));
            if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
                $status = 'pending';
            }
            $agencyIdIn = isset($input['agency_id']) ? (int) $input['agency_id'] : 0;
            $countryIdIn = isset($input['country_id']) ? (int) $input['country_id'] : 0;
            if ($countryIdIn > 0 && $allowedCountryIds !== null && !in_array($countryIdIn, $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'Country not allowed for this user']);
            }
            $voucherIn = trim((string) ($input['voucher_number'] ?? ''));
            if ($voucherIn === '') {
                $lockName = 'control_expense_voucher_global';
                if (!control_acquire_mysql_lock($ctrl, $lockName, 8)) {
                    jsonOut(['success' => false, 'message' => 'Could not reserve voucher number. Please try again.']);
                }
                try {
                    $voucherIn = control_next_expense_voucher($ctrl);
                    control_release_mysql_lock($ctrl, $lockName);
                } catch (Exception $e) {
                    control_release_mysql_lock($ctrl, $lockName);
                    jsonOut(['success' => false, 'message' => 'Could not create voucher number']);
                }
            }
            $hasLinesE = control_table_has_column($ctrl, 'control_expenses', 'lines_json');
            $linesJsonE = null;
            if (array_key_exists('lines', $input) && $hasLinesE) {
                $linesJsonE = control_encode_receipt_expense_lines_payload($input['lines']);
            }
            if ($hasLinesE) {
                $st = $ctrl->prepare('INSERT INTO control_expenses (agency_id, country_id, voucher_number, expense_date, amount, currency_code, description, status, lines_json) VALUES (?,?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iissdssss', $agencyIdIn, $countryIdIn, $voucherIn, $expenseDate, $amount, $currency, $description, $status, $linesJsonE);
            } else {
                $st = $ctrl->prepare('INSERT INTO control_expenses (agency_id, country_id, voucher_number, expense_date, amount, currency_code, description, status) VALUES (?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iissdsss', $agencyIdIn, $countryIdIn, $voucherIn, $expenseDate, $amount, $currency, $description, $status);
            }
            if ($st->execute()) {
                jsonOut(['success' => true, 'id' => (int) $ctrl->insert_id, 'voucher_number' => $voucherIn]);
            }
            jsonOut(['success' => false, 'message' => 'Insert failed: ' . $st->error]);
        }

        if ($addModule === 'support_payments') {
            $chkT = $ctrl->query("SHOW TABLES LIKE 'control_support_payments'");
            if (!$chkT || $chkT->num_rows === 0) {
                jsonOut(['success' => false, 'message' => 'Support payments table not found']);
            }
            $paymentDate = trim(control_normalize_ascii_digits((string) ($input['payment_date'] ?? date('Y-m-d'))));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
                jsonOut(['success' => false, 'message' => 'Payment date must be YYYY-MM-DD']);
            }
            $amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
            if ($amount <= 0) {
                jsonOut(['success' => false, 'message' => 'Amount must be greater than zero']);
            }
            $description = trim((string) ($input['description'] ?? ''));
            $reference = trim((string) ($input['reference'] ?? ''));
            $currency = trim((string) ($input['currency_code'] ?? 'SAR')) ?: 'SAR';
            $status = strtolower(trim((string) ($input['status'] ?? 'completed')));
            if (!in_array($status, ['completed', 'pending', 'cancelled'], true)) {
                $status = 'completed';
            }
            $agencyIdIn = isset($input['agency_id']) ? (int) $input['agency_id'] : 0;
            $countryIdIn = isset($input['country_id']) ? (int) $input['country_id'] : 0;
            if ($countryIdIn > 0 && $allowedCountryIds !== null && !in_array($countryIdIn, $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'Country not allowed for this user']);
            }
            if ($allowedCountryIds === [] && $countryIdIn > 0) {
                jsonOut(['success' => false, 'message' => 'No country access']);
            }
            $hasPayNum = control_table_has_column($ctrl, 'control_support_payments', 'payment_number');
            $hasLinesSpIns = control_table_has_column($ctrl, 'control_support_payments', 'lines_json');
            $linesJsonSpIns = null;
            if (array_key_exists('lines', $input) && $hasLinesSpIns) {
                $linesJsonSpIns = control_encode_receipt_expense_lines_payload($input['lines']);
            }
            $paymentNumberIn = '';
            if ($hasPayNum) {
                $lockName = 'control_support_payment_number_global';
                if (!control_acquire_mysql_lock($ctrl, $lockName, 8)) {
                    jsonOut(['success' => false, 'message' => 'Could not reserve support payment number. Please try again.']);
                }
                try {
                    $paymentNumberIn = control_next_support_payment_number($ctrl);
                    control_release_mysql_lock($ctrl, $lockName);
                } catch (Exception $e) {
                    control_release_mysql_lock($ctrl, $lockName);
                    jsonOut(['success' => false, 'message' => 'Could not create support payment number']);
                }
                if ($hasLinesSpIns) {
                    $st = $ctrl->prepare('INSERT INTO control_support_payments (agency_id, country_id, payment_number, amount, currency_code, payment_date, description, reference, status, lines_json) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    if (!$st) {
                        jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                    }
                    $st->bind_param('iisdssssss', $agencyIdIn, $countryIdIn, $paymentNumberIn, $amount, $currency, $paymentDate, $description, $reference, $status, $linesJsonSpIns);
                } else {
                    $st = $ctrl->prepare('INSERT INTO control_support_payments (agency_id, country_id, payment_number, amount, currency_code, payment_date, description, reference, status) VALUES (?,?,?,?,?,?,?,?,?)');
                    if (!$st) {
                        jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                    }
                    $st->bind_param('iisdsssss', $agencyIdIn, $countryIdIn, $paymentNumberIn, $amount, $currency, $paymentDate, $description, $reference, $status);
                }
            } elseif ($hasLinesSpIns) {
                $st = $ctrl->prepare('INSERT INTO control_support_payments (agency_id, country_id, amount, currency_code, payment_date, description, reference, status, lines_json) VALUES (?,?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iidssssss', $agencyIdIn, $countryIdIn, $amount, $currency, $paymentDate, $description, $reference, $status, $linesJsonSpIns);
            } else {
                $st = $ctrl->prepare('INSERT INTO control_support_payments (agency_id, country_id, amount, currency_code, payment_date, description, reference, status) VALUES (?,?,?,?,?,?,?,?)');
                if (!$st) {
                    jsonOut(['success' => false, 'message' => 'Insert prepare failed']);
                }
                $st->bind_param('iidsssss', $agencyIdIn, $countryIdIn, $amount, $currency, $paymentDate, $description, $reference, $status);
            }
            if ($st->execute()) {
                $out = ['success' => true, 'id' => (int) $ctrl->insert_id];
                if ($hasPayNum) {
                    $out['payment_number'] = $paymentNumberIn;
                }
                jsonOut($out);
            }
            jsonOut(['success' => false, 'message' => 'Insert failed: ' . $st->error]);
        }

        $table = $MODULES[$addModule];
        $chkT = $ctrl->query("SHOW TABLES LIKE '$table'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => "Table $table not found"]);
        }
        $cols = [];
        $vals = [];
        $types = '';
        $intFields = ['agency_id','country_id','parent_id','cost_center_id','approved_by','entity_id'];
        $floatFields = ['amount','balance','total_debit','total_credit','statement_balance','book_balance'];
        $res = $ctrl->query("SHOW COLUMNS FROM $table");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $f = $row['Field'];
                if (in_array($f, ['id','created_at','updated_at'])) continue;
                if (isset($input[$f]) || array_key_exists($f, $input)) {
                    $cols[] = "`$f`";
                    $v = $input[$f];
                    if ($v === '' && strpos($row['Null'], 'YES') !== false) $v = null;
                    elseif ($v === '' && in_array($f, $intFields)) $v = 0;
                    elseif ($v === '' && in_array($f, $floatFields)) $v = 0;
                    $vals[] = $v;
                    $types .= in_array($f, $intFields) ? 'i' : (in_array($f, $floatFields) ? 'd' : 's');
                }
            }
        }
        if (empty($cols)) {
            jsonOut(['success' => false, 'message' => 'No valid fields']);
        }
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $colList = implode(',', $cols);
        $stmt = $ctrl->prepare("INSERT INTO $table ($colList) VALUES ($placeholders)");
        $stmt->bind_param($types, ...$vals);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'id' => (int) $ctrl->insert_id]);
        }
        jsonOut(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }

    // Bulk delete transactions
    if (isset($input['bulk_delete']) && $input['bulk_delete'] === 'transactions' && !empty($input['ids']) && is_array($input['ids'])) {
        $ids = array_filter(array_map('intval', $input['ids']));
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No valid IDs']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $ctrl->prepare("DELETE FROM control_accounting_transactions WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) {
            jsonOut(['success' => true, 'deleted' => $stmt->affected_rows]);
        }
        jsonOut(['success' => false, 'message' => 'Delete failed']);
    }

    // Bulk delete module items
    $bulkModule = trim($input['bulk_delete_module'] ?? '');
    if ($bulkModule && isset($MODULES[$bulkModule]) && !empty($input['ids']) && is_array($input['ids'])) {
        $ids = array_filter(array_map('intval', $input['ids']));
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No valid IDs']);
        }
        $table = $MODULES[$bulkModule];
        $chkT = $ctrl->query("SHOW TABLES LIKE '$table'");
        if (!$chkT || $chkT->num_rows === 0) {
            jsonOut(['success' => false, 'message' => 'Table not found']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($bulkModule === 'journal_entries') {
            if ($allowedCountryIds !== null) {
                if ($allowedCountryIds === []) {
                    jsonOut(['success' => false, 'message' => 'No country access']);
                }
                $idListRaw = implode(',', array_map('intval', $ids));
                $countryList = implode(',', array_map('intval', $allowedCountryIds));
                $allowedIds = [];
                $ar = $ctrl->query("SELECT id FROM control_journal_entries WHERE id IN ($idListRaw) AND (country_id IN ($countryList) OR country_id = 0)");
                if ($ar) {
                    while ($rw = $ar->fetch_assoc()) {
                        $allowedIds[] = (int) ($rw['id'] ?? 0);
                    }
                }
                $ids = array_values(array_unique(array_filter($allowedIds)));
                if (empty($ids)) {
                    jsonOut(['success' => false, 'message' => 'No selected journals are allowed for your country scope']);
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
            }
            $chkAp = $ctrl->query("SHOW TABLES LIKE 'control_entry_approvals'");
            $chkLn = $ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
            $useTx = @$ctrl->begin_transaction();
            try {
                if ($chkAp && $chkAp->num_rows > 0) {
                    $stA = $ctrl->prepare("DELETE FROM control_entry_approvals WHERE journal_entry_id IN ($placeholders)");
                    $stA->bind_param($types, ...$ids);
                    if (!$stA->execute()) {
                        throw new Exception('approvals');
                    }
                }
                if ($chkLn && $chkLn->num_rows > 0) {
                    $stL = $ctrl->prepare("DELETE FROM control_journal_entry_lines WHERE journal_entry_id IN ($placeholders)");
                    $stL->bind_param($types, ...$ids);
                    if (!$stL->execute()) {
                        throw new Exception('lines');
                    }
                }
                $stmt = $ctrl->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                if (!$stmt->execute()) {
                    throw new Exception('journal');
                }
                $deleted = $stmt->affected_rows;
                if ($useTx) {
                    $ctrl->commit();
                }
                jsonOut(['success' => true, 'deleted' => (int) $deleted]);
            } catch (Exception $e) {
                if ($useTx) {
                    $ctrl->rollback();
                }
                jsonOut(['success' => false, 'message' => 'Delete failed: related records could not be removed']);
            }
        } else {
            $stmt = $ctrl->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                jsonOut(['success' => true, 'deleted' => $stmt->affected_rows]);
            }
            jsonOut(['success' => false, 'message' => 'Delete failed']);
        }
    }

    $type = trim($input['type'] ?? '');
    $amount = isset($input['amount']) ? (float) $input['amount'] : 0;
    $currency = trim($input['currency_code'] ?? 'SAR');
    $description = trim($input['description'] ?? '');
    $reference = trim($input['reference'] ?? '');
    $agencyId = isset($input['agency_id']) && ctype_digit((string)$input['agency_id']) ? (int) $input['agency_id'] : null;
    $countryId = isset($input['country_id']) && ctype_digit((string)$input['country_id']) ? (int) $input['country_id'] : null;
    $debitAccountId = isset($input['debit_account_id']) && ctype_digit((string)$input['debit_account_id']) ? (int) $input['debit_account_id'] : null;
    $creditAccountId = isset($input['credit_account_id']) && ctype_digit((string)$input['credit_account_id']) ? (int) $input['credit_account_id'] : null;

    $validTypes = ['revenue', 'commission', 'settlement_in', 'settlement_out', 'adjustment'];
    if (!in_array($type, $validTypes) || $amount <= 0) {
        jsonOut(['success' => false, 'message' => 'Invalid type or amount']);
    }

    $adminId = null;
    $chkAd = $ctrl->query("SHOW TABLES LIKE 'control_admins'");
    if ($chkAd && $chkAd->num_rows > 0 && !empty($_SESSION['control_username'])) {
        $st = $ctrl->prepare("SELECT id FROM control_admins WHERE username = ? LIMIT 1");
        $un = $_SESSION['control_username'];
        $st->bind_param('s', $un);
        $st->execute();
        $rr = $st->get_result();
        if ($rr && $rr->num_rows > 0) $adminId = (int) $rr->fetch_assoc()['id'];
    }

    $aid = ($agencyId && (int)$agencyId > 0) ? (int)$agencyId : 0;
    $cid = ($countryId && (int)$countryId > 0) ? (int)$countryId : 0;

    if ($cid > 0 && $allowedCountryIds !== null && !in_array($cid, $allowedCountryIds, true)) {
        jsonOut(['success' => false, 'message' => 'You do not have permission to add transactions for this country']);
    }
    if ($aid > 0 && $allowedCountryIds !== null) {
        $arow = $ctrl->query("SELECT country_id FROM control_agencies WHERE id = " . (int)$aid . " LIMIT 1")->fetch_assoc();
        if ($arow && !in_array((int)$arow['country_id'], $allowedCountryIds, true)) {
            jsonOut(['success' => false, 'message' => 'You do not have permission to add transactions for this agency']);
        }
    }

    $hasDebitCredit = false;
    $chkCol = $ctrl->query("SHOW COLUMNS FROM control_accounting_transactions LIKE 'debit_account_id'");
    if ($chkCol && $chkCol->num_rows > 0) $hasDebitCredit = true;

    if ($hasDebitCredit) {
        if ($aid === 0 && $cid === 0) {
            $stmt = $ctrl->prepare("INSERT INTO control_accounting_transactions (type, amount, currency_code, description, reference, debit_account_id, credit_account_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sdsssiii', $type, $amount, $currency, $description, $reference, $debitAccountId, $creditAccountId, $adminId);
        } else {
            $stmt = $ctrl->prepare("INSERT INTO control_accounting_transactions (agency_id, country_id, type, amount, currency_code, description, reference, debit_account_id, credit_account_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisdsssiii', $aid, $cid, $type, $amount, $currency, $description, $reference, $debitAccountId, $creditAccountId, $adminId);
        }
    } else {
        if ($aid === 0 && $cid === 0) {
            $stmt = $ctrl->prepare("INSERT INTO control_accounting_transactions (type, amount, currency_code, description, reference, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sdsssi', $type, $amount, $currency, $description, $reference, $adminId);
        } else {
            $stmt = $ctrl->prepare("INSERT INTO control_accounting_transactions (agency_id, country_id, type, amount, currency_code, description, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisdsssi', $aid, $cid, $type, $amount, $currency, $description, $reference, $adminId);
        }
    }
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'id' => (int) $ctrl->insert_id]);
    }
    jsonOut(['success' => false, 'message' => 'Insert failed']);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);
