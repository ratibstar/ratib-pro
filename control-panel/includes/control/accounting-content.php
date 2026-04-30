<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/accounting-content.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/accounting-content.php`.
 */
/**
 * Control Panel Accounting – Professional Accounting System style
 * Uses only control panel data (no Ratib Pro)
 */
require_once __DIR__ . '/request-url.php';
$apiBase = control_control_api_base_url();
if (empty($_SESSION['control_csrf_token']) || !is_string($_SESSION['control_csrf_token'])) {
    try {
        $_SESSION['control_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['control_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$controlCsrfToken = (string) $_SESSION['control_csrf_token'];

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

$countries = [];
$countryMap = [];
$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if ($chk && $chk->num_rows > 0) {
    $countrySql = "SELECT id, name FROM control_countries WHERE is_active = 1 ORDER BY sort_order, name";
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $countrySql = "SELECT id, name FROM control_countries WHERE id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ") AND is_active = 1 ORDER BY sort_order, name";
    } elseif ($allowedCountryIds === []) {
        $countrySql = "SELECT id, name FROM control_countries WHERE 1=0";
    }
    $res = $ctrl->query($countrySql);
    if ($res) while ($row = $res->fetch_assoc()) {
        $countries[] = $row;
        $countryMap[(int)$row['id']] = $row['name'];
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$whereBase = [];
if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $idsStr = implode(',', array_map('intval', $allowedCountryIds));
    $whereBase[] = "country_id IN ($idsStr)";
} elseif ($allowedCountryIds === []) {
    $whereBase[] = '1=0';
}
if ($countryId > 0) $whereBase[] = "country_id = " . (int)$countryId;
$whereClause = $whereBase ? ' WHERE ' . implode(' AND ', $whereBase) : '';
$whereClauseT = str_replace('country_id', 't.country_id', $whereClause);
$whereClauseS = str_replace('country_id', 's.country_id', $whereClause);

$summary = ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0, 'receivable' => 0, 'payable' => 0];
$transactions = [];
$totalTransactions = 0;
$recentTransactions = [];
$supportPayments = [];
$invoiceCount = 0;
$billCount = 0;

$chk1 = @$ctrl->query("SHOW TABLES LIKE 'control_accounting_transactions'");
if ($chk1 && $chk1->num_rows > 0) {
    $sumRes = @$ctrl->query("SELECT type, SUM(amount) as tot FROM control_accounting_transactions" . $whereClause . " GROUP BY type");
    if ($sumRes) while ($row = $sumRes->fetch_assoc()) {
        $t = strtolower($row['type'] ?? '');
        if (in_array($t, ['receipt','income','payment_received','revenue','commission','settlement_in'])) { $summary['total_revenue'] += (float)$row['tot']; $summary['cash_balance'] += (float)$row['tot']; }
        elseif (in_array($t, ['expense','payment','disbursement','settlement_out'])) { $summary['total_expenses'] += (float)$row['tot']; $summary['cash_balance'] -= (float)$row['tot']; $summary['payable'] += (float)$row['tot']; }
        elseif (in_array($t, ['receivable','invoice'])) { $summary['receivable'] += (float)$row['tot']; }
    }
    $totalTransactions = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_accounting_transactions" . $whereClause)->fetch_assoc()['c'] ?? 0);
    $res = $ctrl->query("SELECT t.*, c.name as country_name FROM control_accounting_transactions t LEFT JOIN control_countries c ON t.country_id = c.id" . $whereClauseT . " ORDER BY t.id DESC LIMIT 10");
    if ($res) while ($row = $res->fetch_assoc()) $recentTransactions[] = $row;
}

$regRevenueTotal = 0;
$regRevenueTotalRecognized = 0;
$regByPlan = [];
$regByPlanRecognized = [];
$regByCountry = [];
$regByCountryRecognized = [];
$regThisMonthCount = 0;
$regThisMonthCountRecognized = 0;
$regRevenueThisMonth = 0;
$regRevenueThisMonthRecognized = 0;
$regRevenueByMonth = [];
$recentRegPayRows = [];
$chk2 = @$ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
if ($chk2 && $chk2->num_rows > 0) {
    $reqGeo = '';
    if ($countryId > 0) {
        $cid = (int)$countryId;
        $chkAg = @$ctrl->query("SHOW TABLES LIKE 'control_agencies'");
        $agencyMatch = ($chkAg && $chkAg->num_rows > 0) ? " OR (control_registration_requests.agency_id IS NOT NULL AND TRIM(control_registration_requests.agency_id) != '' AND EXISTS (SELECT 1 FROM control_agencies a WHERE a.country_id = $cid AND (a.id = CAST(NULLIF(TRIM(control_registration_requests.agency_id), '') AS UNSIGNED) OR CAST(a.id AS CHAR) = TRIM(control_registration_requests.agency_id))))" : '';
        $reqGeo .= " AND (country_id = $cid OR country_name IN (SELECT name FROM control_countries WHERE id = $cid)$agencyMatch)";
    }
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $idsStr = implode(',', array_map('intval', $allowedCountryIds));
        $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
        $countryNames = [];
        if ($namesRes) {
            while ($nr = $namesRes->fetch_assoc()) {
                $countryNames[] = "'" . $ctrl->real_escape_string((string)$nr['name']) . "'";
            }
        }
        $nameMatch = !empty($countryNames)
            ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))"
            : "";
        $reqGeo .= " AND (country_id IN ($idsStr)$nameMatch)";
    } elseif ($allowedCountryIds === []) {
        $reqGeo .= " AND 1=0";
    }

    $reqPaid = "payment_status = 'paid' AND plan_amount > 0";
    $reqWhereCollected = "status IN ('approved','pending') AND $reqPaid" . $reqGeo;
    $reqWhereRecognized = "status = 'approved' AND $reqPaid" . $reqGeo;

    $r = @$ctrl->query("SELECT COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereCollected");
    if ($r && $row = $r->fetch_assoc()) {
        $regRevenueTotal = (float)$row['tot'];
        $summary['total_revenue'] += $regRevenueTotal;
        $summary['cash_balance'] += $regRevenueTotal;
    }
    $r2 = @$ctrl->query("SELECT COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereRecognized");
    if ($r2 && $row = $r2->fetch_assoc()) {
        $regRevenueTotalRecognized = (float)$row['tot'];
    }

    $resPlan = @$ctrl->query("SELECT plan, COUNT(*) as cnt, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereCollected GROUP BY plan");
    if ($resPlan) {
        while ($row = $resPlan->fetch_assoc()) {
            $regByPlan[] = $row;
        }
    }
    $resPlanR = @$ctrl->query("SELECT plan, COUNT(*) as cnt, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereRecognized GROUP BY plan");
    if ($resPlanR) {
        while ($row = $resPlanR->fetch_assoc()) {
            $regByPlanRecognized[] = $row;
        }
    }

    $resCountry = @$ctrl->query("SELECT COALESCE(country_name, '(Unknown)') as country_name, COUNT(*) as cnt, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereCollected GROUP BY country_name ORDER BY tot DESC");
    if ($resCountry) {
        while ($row = $resCountry->fetch_assoc()) {
            $regByCountry[] = $row;
        }
    }
    $resCountryR = @$ctrl->query("SELECT COALESCE(country_name, '(Unknown)') as country_name, COUNT(*) as cnt, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereRecognized GROUP BY country_name ORDER BY tot DESC");
    if ($resCountryR) {
        while ($row = $resCountryR->fetch_assoc()) {
            $regByCountryRecognized[] = $row;
        }
    }

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $escMs = $ctrl->real_escape_string($monthStart);
    $escMe = $ctrl->real_escape_string($monthEnd);
    $resMonth = @$ctrl->query("SELECT COUNT(*) as c, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereCollected AND DATE(created_at) BETWEEN '$escMs' AND '$escMe'");
    if ($resMonth && $row = $resMonth->fetch_assoc()) {
        $regThisMonthCount = (int)$row['c'];
        $regRevenueThisMonth = (float)$row['tot'];
    }
    $resMonthR = @$ctrl->query("SELECT COUNT(*) as c, COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereRecognized AND DATE(created_at) BETWEEN '$escMs' AND '$escMe'");
    if ($resMonthR && $row = $resMonthR->fetch_assoc()) {
        $regThisMonthCountRecognized = (int)$row['c'];
        $regRevenueThisMonthRecognized = (float)$row['tot'];
    }

    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $ms = $ym . '-01';
        $me = date('Y-m-t', strtotime($ms));
        $ems = $ctrl->real_escape_string($ms);
        $eme = $ctrl->real_escape_string($me);
        $resM = @$ctrl->query("SELECT COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereCollected AND DATE(created_at) BETWEEN '$ems' AND '$eme'");
        $totC = $resM && $row = $resM->fetch_assoc() ? (float)$row['tot'] : 0;
        $resMR = @$ctrl->query("SELECT COALESCE(SUM(plan_amount), 0) as tot FROM control_registration_requests WHERE $reqWhereRecognized AND DATE(created_at) BETWEEN '$ems' AND '$eme'");
        $totR = $resMR && $row = $resMR->fetch_assoc() ? (float)$row['tot'] : 0;
        $regRevenueByMonth[] = ['label' => $ym, 'collected' => $totC, 'recognized' => $totR];
    }

    $resRegRecent = @$ctrl->query("SELECT id, agency_name, plan_amount, created_at, updated_at, country_name FROM control_registration_requests WHERE $reqWhereCollected ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 15");
    if ($resRegRecent) {
        while ($rr = $resRegRecent->fetch_assoc()) {
            $recentRegPayRows[] = $rr;
        }
    }
}

if (!empty($recentTransactions) || !empty($recentRegPayRows)) {
    $mergedRecent = [];
    foreach ($recentTransactions as $t) {
        $ts = strtotime((string)($t['created_at'] ?? ''));
        $mergedRecent[] = ['ts' => $ts ?: 0, 'row' => $t];
    }
    foreach ($recentRegPayRows as $r) {
        $dt = (string)($r['updated_at'] ?? $r['created_at'] ?? '');
        $ts = strtotime($dt);
        $mergedRecent[] = [
            'ts' => $ts ?: 0,
            'row' => [
                'created_at' => $dt,
                'description' => 'Registration payment — ' . trim((string)($r['agency_name'] ?? '')) . ' (#' . (int)($r['id'] ?? 0) . ')',
                'type' => 'payment_received',
                'amount' => (float)($r['plan_amount'] ?? 0),
                'country_name' => $r['country_name'] ?? '',
            ],
        ];
    }
    usort($mergedRecent, function ($a, $b) {
        return $b['ts'] <=> $a['ts'];
    });
    $recentTransactions = [];
    foreach (array_slice($mergedRecent, 0, 10) as $m) {
        $recentTransactions[] = $m['row'];
    }
}

$chk3 = @$ctrl->query("SHOW TABLES LIKE 'control_support_payments'");
if ($chk3 && $chk3->num_rows > 0) {
    $res = $ctrl->query("SELECT s.*, c.name as country_name FROM control_support_payments s LEFT JOIN control_countries c ON s.country_id = c.id" . $whereClauseS . " ORDER BY s.id DESC LIMIT 50");
    if ($res) while ($row = $res->fetch_assoc()) $supportPayments[] = $row;
}

$chk4 = @$ctrl->query("SHOW TABLES LIKE 'control_electronic_invoices'");
if ($chk4 && $chk4->num_rows > 0) {
    $invoiceCount = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_electronic_invoices" . $whereClause)->fetch_assoc()['c'] ?? 0);
}
$chk5 = @$ctrl->query("SHOW TABLES LIKE 'control_expenses'");
if ($chk5 && $chk5->num_rows > 0) {
    $billCount = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_expenses" . $whereClause)->fetch_assoc()['c'] ?? 0);
}

$summary['net_profit'] = $summary['total_revenue'] - $summary['total_expenses'];

$chartAccounts = $costCenters = $bankGuarantees = $journalEntries = $expenses = $receipts = $vouchers = $invoices = $approvals = $reconciliations = [];
function loadTabData($ctrl, $table, $whereClause, $countryMap) {
    $alias = 't';
    $wc = str_replace('country_id', $alias . '.country_id', $whereClause);
    $postedOnly = '';
    if ($table === 'control_journal_entries') {
        $st = "LOWER(TRIM(COALESCE($alias.status,''))) IN ('posted','approved')";
        $postedOnly = (trim($wc) === '') ? (' WHERE ' . $st) : (' AND ' . $st);
    }
    $res = $ctrl->query("SELECT $alias.*, c.name as country_name FROM $table $alias LEFT JOIN control_countries c ON $alias.country_id = c.id $wc $postedOnly ORDER BY $alias.id DESC LIMIT 50");
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}
function loadApprovals($ctrl) {
    $hasRejectReason = false;
    $chk = @$ctrl->query("SHOW COLUMNS FROM control_entry_approvals LIKE 'rejection_reason'");
    if ($chk && $chk->num_rows > 0) {
        $hasRejectReason = true;
    }
    $reasonCol = $hasRejectReason ? "a.rejection_reason" : "'' AS rejection_reason";
    $res = $ctrl->query("SELECT a.id, a.journal_entry_id, a.approved_by, a.approved_at, a.status, a.created_at, $reasonCol, j.reference, j.entry_date, j.description AS journal_description, j.total_debit, j.total_credit, j.status AS journal_status FROM control_entry_approvals a LEFT JOIN control_journal_entries j ON a.journal_entry_id = j.id ORDER BY a.id DESC LIMIT 50");
    $rows = [];
    if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;
    return $rows;
}

/**
 * Human-readable label for one journal line (header row does not store accounts; they live on lines).
 */
function cpJournalLineAccountLabel(array $line, array $chartById) {
    $name = trim((string) ($line['account_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $code = trim((string) ($line['account_code'] ?? ''));
    if ($code !== '') {
        return $code;
    }
    $aid = (int) ($line['account_id'] ?? 0);
    if ($aid > 0 && isset($chartById[$aid])) {
        $c = $chartById[$aid];
        $an = trim((string) ($c['account_name'] ?? ''));
        $ac = trim((string) ($c['account_code'] ?? ''));
        if ($an !== '' && $ac !== '') {
            return $ac . ' — ' . $an;
        }
        if ($an !== '') {
            return $an;
        }
        if ($ac !== '') {
            return $ac;
        }
        return '#' . $aid;
    }
    if ($aid > 0) {
        return '#' . $aid;
    }
    $desc = trim((string) ($line['description'] ?? ''));
    if ($desc !== '') {
        return mb_strlen($desc) > 100 ? mb_substr($desc, 0, 97) . '…' : $desc;
    }
    return '';
}

/**
 * Add debit_account_label / credit_account_label to each journal entry from control_journal_entry_lines.
 */
function enrichJournalEntriesWithLineAccounts($ctrl, array $entries) {
    if (empty($entries)) {
        return $entries;
    }
    $chk = @$ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
    if (!$chk || $chk->num_rows === 0) {
        foreach ($entries as &$e) {
            $e['debit_account_label'] = '—';
            $e['credit_account_label'] = '—';
        }
        unset($e);
        return $entries;
    }
    $ids = [];
    foreach ($entries as $e) {
        $id = (int) ($e['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        return $entries;
    }
    $idList = implode(',', array_map('intval', $ids));
    $res = $ctrl->query("SELECT * FROM control_journal_entry_lines WHERE journal_entry_id IN ($idList) ORDER BY journal_entry_id ASC, id ASC");
    $linesByJe = [];
    $accountIds = [];
    if ($res) {
        while ($ln = $res->fetch_assoc()) {
            $jid = (int) $ln['journal_entry_id'];
            if (!isset($linesByJe[$jid])) {
                $linesByJe[$jid] = [];
            }
            $linesByJe[$jid][] = $ln;
            $aid = (int) ($ln['account_id'] ?? 0);
            if ($aid > 0) {
                $accountIds[$aid] = true;
            }
        }
    }
    $chartById = [];
    if (!empty($accountIds)) {
        $chkC = @$ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
        if ($chkC && $chkC->num_rows > 0) {
            $alist = implode(',', array_map('intval', array_keys($accountIds)));
            $cr = $ctrl->query("SELECT id, account_code, account_name FROM control_chart_accounts WHERE id IN ($alist)");
            if ($cr) {
                while ($c = $cr->fetch_assoc()) {
                    $chartById[(int) $c['id']] = $c;
                }
            }
        }
    }
    foreach ($entries as &$e) {
        $jid = (int) ($e['id'] ?? 0);
        $debitLabels = [];
        $creditLabels = [];
        if ($jid && !empty($linesByJe[$jid])) {
            foreach ($linesByJe[$jid] as $ln) {
                $lab = cpJournalLineAccountLabel($ln, $chartById);
                if ($lab === '') {
                    continue;
                }
                $deb = (float) ($ln['debit'] ?? 0);
                $cre = (float) ($ln['credit'] ?? 0);
                if ($deb > 0 && !in_array($lab, $debitLabels, true)) {
                    $debitLabels[] = $lab;
                }
                if ($cre > 0 && !in_array($lab, $creditLabels, true)) {
                    $creditLabels[] = $lab;
                }
            }
        }
        $e['debit_account_label'] = empty($debitLabels) ? '—' : implode(', ', $debitLabels);
        $e['credit_account_label'] = empty($creditLabels) ? '—' : implode(', ', $creditLabels);
    }
    unset($e);
    return $entries;
}

$tablesWithCountry = ['control_chart_accounts' => 'chartAccounts', 'control_cost_centers' => 'costCenters', 'control_bank_guarantees' => 'bankGuarantees',
    'control_journal_entries' => 'journalEntries', 'control_expenses' => 'expenses', 'control_receipts' => 'receipts',
    'control_disbursement_vouchers' => 'vouchers', 'control_electronic_invoices' => 'invoices', 'control_bank_reconciliations' => 'reconciliations'];
// Chart of accounts: include global defaults (country_id = 0) so they show for all countries
$whereClauseChart = $whereClause;
if ($whereClauseChart !== '' && strpos($whereClauseChart, '1=0') === false) {
    $whereClauseChart = preg_replace('/ WHERE (.+)$/s', ' WHERE ($1 OR country_id = 0)', $whereClause);
}
foreach ($tablesWithCountry as $table => $var) {
    $chk = @$ctrl->query("SHOW TABLES LIKE '$table'");
    if ($chk && $chk->num_rows > 0) {
        $wc = ($table === 'control_chart_accounts') ? $whereClauseChart : $whereClause;
        ${$var} = loadTabData($ctrl, $table, $wc, $countryMap);
    }
}
if (!empty($journalEntries) && $ctrl) {
    $journalEntries = enrichJournalEntriesWithLineAccounts($ctrl, $journalEntries);
}
$chkApprovals = @$ctrl->query("SHOW TABLES LIKE 'control_entry_approvals'");
if ($chkApprovals && $chkApprovals->num_rows > 0) {
    $approvals = loadApprovals($ctrl);
}

$chartTotal = count($chartAccounts);
$chartActive = 0;
$chartBalance = 0;
$chartByType = ['Asset' => ['count' => 0, 'balance' => 0], 'Liability' => ['count' => 0, 'balance' => 0], 'Equity' => ['count' => 0, 'balance' => 0], 'Income' => ['count' => 0, 'balance' => 0], 'Expense' => ['count' => 0, 'balance' => 0]];
foreach ($chartAccounts as $a) {
    if (!empty($a['is_active'])) $chartActive++;
    $chartBalance += (float)($a['balance'] ?? 0);
    $t = $a['account_type'] ?? 'Asset';
    if (!isset($chartByType[$t])) $chartByType[$t] = ['count' => 0, 'balance' => 0];
    $chartByType[$t]['count']++;
    $chartByType[$t]['balance'] += (float)($a['balance'] ?? 0);
}
$costTotal = count($costCenters);
$costActive = 0;
foreach ($costCenters as $c) { if (!empty($c['is_active'])) $costActive++; }
$bankTotal = count($bankGuarantees);
$bankActive = 0;
$bankExpired = 0;
$bankAmount = 0;
$today = date('Y-m-d');
foreach ($bankGuarantees as $b) {
    $bankAmount += (float)($b['amount'] ?? 0);
    $st = strtolower($b['status'] ?? '');
    if ($st === 'active') $bankActive++;
    if (!empty($b['end_date']) && $b['end_date'] < $today) $bankExpired++;
}
$journalTotal = 0;
$journalDraft = 0;
$journalPosted = 0;
$journalDebit = 0.0;
$journalCredit = 0.0;
$chkJStats = @$ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
if ($chkJStats && $chkJStats->num_rows > 0) {
    $jr = @$ctrl->query(
        'SELECT COUNT(*) AS c,'
        . ' SUM(CASE WHEN LOWER(TRIM(COALESCE(status,\'\'))) IN (\'posted\',\'approved\') THEN 1 ELSE 0 END) AS posted_n,'
        . ' SUM(CASE WHEN LOWER(TRIM(COALESCE(status,\'\'))) IN (\'posted\',\'approved\') THEN total_debit ELSE 0 END) AS td,'
        . ' SUM(CASE WHEN LOWER(TRIM(COALESCE(status,\'\'))) IN (\'posted\',\'approved\') THEN total_credit ELSE 0 END) AS tc'
        . ' FROM control_journal_entries' . $whereClause
    );
    if ($jr && $row = $jr->fetch_assoc()) {
        $journalTotal = (int) ($row['c'] ?? 0);
        $journalPosted = (int) ($row['posted_n'] ?? 0);
        $journalDraft = max(0, $journalTotal - $journalPosted);
        $journalDebit = (float) ($row['td'] ?? 0);
        $journalCredit = (float) ($row['tc'] ?? 0);
    }
}
$journalBalance = $journalDebit - $journalCredit;
$currencyLabel = 'SAR';

$formAction = pageUrl('control/accounting.php');
$qBase = 'control=1' . ($countryId ? '&country_id=' . $countryId : '');
$canManageAccountingUi = hasControlPermission(CONTROL_PERM_ACCOUNTING) || hasControlPermission('manage_control_accounting');
?>
<div id="accountingContent" data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-country-id="<?php echo (int) $countryId; ?>" data-csrf-token="<?php echo htmlspecialchars($controlCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" data-can-manage="<?php echo $canManageAccountingUi ? '1' : '0'; ?>" class="accounting-container" lang="en" translate="no" dir="ltr">
    <div class="accounting-header">
        <div class="header-left"><h1><i class="fas fa-calculator"></i> Professional Accounting System</h1></div>
        <div class="header-right">
            <form method="get" action="<?php echo htmlspecialchars($formAction); ?>" class="d-flex gap-2">
                <input type="hidden" name="control" value="1">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <select name="country_id" class="form-select cp-acc-country-select cp-acc-country-select-dark">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $countryId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="accounting-layout">
        <div class="accounting-main-content">
            <div id="financialOverview" class="overview-grid">
                <a href="<?php echo $formAction . '?' . $qBase . '&tab=dashboard'; ?>" class="overview-card revenue"><div class="card-icon"><i class="fas fa-arrow-up"></i></div><div class="card-content"><h3><?php echo number_format($summary['total_revenue'], 2); ?> SAR</h3><p>Total Revenue</p><span class="card-change positive">+0%</span></div></a>
                <a href="#expensesModal" class="overview-card expense" data-cp-acc-modal="expensesModal"><div class="card-icon"><i class="fas fa-arrow-down"></i></div><div class="card-content"><h3><?php echo number_format($summary['total_expenses'], 2); ?> SAR</h3><p>Total Expenses</p><span class="card-change negative">+0%</span></div></a>
                <a href="#reportsModal" class="overview-card profit" data-cp-acc-modal="reportsModal"><div class="card-icon"><i class="fas fa-chart-line"></i></div><div class="card-content"><h3><?php echo number_format($summary['net_profit'], 2); ?> SAR</h3><p>Net Profit</p><span class="card-change">+0%</span></div></a>
                <a href="<?php echo $formAction . '?' . $qBase . '&tab=dashboard'; ?>" class="overview-card balance"><div class="card-icon"><i class="fas fa-wallet"></i></div><div class="card-content"><h3><?php echo number_format($summary['cash_balance'], 2); ?> SAR</h3><p>Cash Balance</p><span class="card-change">+0%</span></div></a>
                <a href="#invoicesModal" class="overview-card receivables" data-cp-acc-modal="invoicesModal"><div class="card-icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="card-content"><h3><?php echo number_format($summary['receivable'], 2); ?> SAR</h3><p>Accounts Receivable</p><span class="card-badge"><?php echo $invoiceCount; ?> invoices</span></div></a>
                <a href="#expensesModal" class="overview-card payables" data-cp-acc-modal="expensesModal"><div class="card-icon"><i class="fas fa-file-invoice"></i></div><div class="card-content"><h3><?php echo number_format($summary['payable'], 2); ?> SAR</h3><p>Accounts Payable</p><span class="card-badge"><?php echo $billCount; ?> bills</span></div></a>
            </div>
            <nav class="accounting-top-nav">
                <ul class="top-nav-menu">
                    <li class="top-nav-item"><a href="<?php echo $formAction . '?' . $qBase . '&tab=dashboard'; ?>" class="top-nav-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i><span>Control Panel</span></a></li>
                    <li class="top-nav-item"><a href="#chartModal" class="top-nav-link" data-cp-acc-modal="chartModal"><i class="fas fa-sitemap"></i><span>Chart of Accounts</span></a></li>
                    <li class="top-nav-item"><a href="#costModal" class="top-nav-link" data-cp-acc-modal="costModal"><i class="fas fa-building"></i><span>Cost Centers</span></a></li>
                    <li class="top-nav-item"><a href="#bankModal" class="top-nav-link" data-cp-acc-modal="bankModal"><i class="fas fa-shield-alt"></i><span>Bank Guarantees</span></a></li>
                    <li class="top-nav-item"><a href="#supportModal" class="top-nav-link" data-cp-acc-modal="supportModal"><i class="fas fa-hand-holding-usd"></i><span>Support Payments</span></a></li>
                    <li class="top-nav-item"><a href="#ledgerModal" class="top-nav-link" data-cp-acc-modal="ledgerModal"><i class="fas fa-book"></i><span>Journal Entries</span></a></li>
                    <li class="top-nav-item"><a href="#expensesModal" class="top-nav-link" data-cp-acc-modal="expensesModal"><i class="fas fa-arrow-down"></i><span>Expenses</span></a></li>
                    <li class="top-nav-item"><a href="#receiptsModal" class="top-nav-link" data-cp-acc-modal="receiptsModal"><i class="fas fa-receipt"></i><span>Receipts</span></a></li>
                    <li class="top-nav-item"><a href="#vouchersModal" class="top-nav-link" data-cp-acc-modal="vouchersModal"><i class="fas fa-file-invoice"></i><span>Disbursement Vouchers</span></a></li>
                    <li class="top-nav-item"><a href="#invoicesModal" class="top-nav-link" data-cp-acc-modal="invoicesModal"><i class="fas fa-file-invoice-dollar"></i><span>Electronic Invoice List</span></a></li>
                    <li class="top-nav-item"><a href="#approvalModal" class="top-nav-link" data-cp-acc-modal="approvalModal"><i class="fas fa-check-circle"></i><span>Entry Approval</span></a></li>
                    <li class="top-nav-item"><a href="#reconcileModal" class="top-nav-link" data-cp-acc-modal="reconcileModal"><i class="fas fa-balance-scale"></i><span>Bank Reconciliation</span></a></li>
                    <li class="top-nav-item"><a href="#reportsModal" class="top-nav-link" data-cp-acc-modal="reportsModal"><i class="fas fa-chart-bar"></i><span>Financial Reports</span></a></li>
                    <li class="top-nav-item"><a href="#registrationRevenueModal" class="top-nav-link" data-cp-acc-modal="registrationRevenueModal"><i class="fas fa-user-plus"></i><span>Registration Revenue</span></a></li>
                </ul>
            </nav>

    <?php if ($tab === 'dashboard' || $tab === 'transactions'): ?>
            <div class="dashboard-grid">
                <div class="dashboard-widget transactions-widget">
                    <div class="widget-header"><h3><i class="fas fa-history"></i> Recent Transactions</h3></div>
                    <div class="widget-content">
                        <?php if (empty($recentTransactions)): ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i><p>No recent transactions</p></div>
                        <?php else: ?>
                        <table class="dashboard-table">
                            <thead><tr><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentTransactions as $t): $isDebit = in_array(strtolower($t['type'] ?? ''), ['expense','payment','disbursement']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($t['created_at'] ?? '', 0, 10)); ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($t['description'] ?? $t['type'] ?? '-', 0, 30)); ?></td>
                                <td><?php echo $isDebit ? number_format((float)($t['amount'] ?? 0), 2) : '—'; ?></td>
                                <td><?php echo !$isDebit ? number_format((float)($t['amount'] ?? 0), 2) : '—'; ?></td>
                                <td><span class="badge bg-success">Done</span></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dashboard-widget quick-actions-widget">
                    <div class="widget-header"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
                    <div class="widget-content">
                        <div class="quick-actions-grid">
                            <a href="#newJournalModal" class="quick-action-btn" data-cp-acc-modal="newJournalModal" data-permission="control_accounting,manage_control_accounting"><i class="fas fa-plus-circle"></i><span>New Entry</span></a>
                            <a href="#invoicesModal" class="quick-action-btn" data-cp-acc-modal="invoicesModal" data-permission="control_accounting,manage_control_accounting"><i class="fas fa-file-invoice-dollar"></i><span>New Invoice</span></a>
                            <a href="#expensesModal" class="quick-action-btn" data-cp-acc-modal="expensesModal" data-permission="control_accounting,manage_control_accounting"><i class="fas fa-money-check-alt"></i><span>Payment</span></a>
                            <a href="#receiptsModal" class="quick-action-btn" data-cp-acc-modal="receiptsModal" data-permission="control_accounting,manage_control_accounting"><i class="fas fa-receipt"></i><span>Receipt</span></a>
                            <a href="#reportsModal" class="quick-action-btn" data-cp-acc-modal="reportsModal"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
                            <a href="<?php echo pageUrl('control/panel-settings.php'); ?>" class="quick-action-btn"><i class="fas fa-cog"></i><span>Settings</span></a>
                        </div>
                    </div>
                </div>
                <div class="dashboard-widget cashflow-widget">
                    <div class="widget-header"><h3><i class="fas fa-exchange-alt"></i> Cash Flow Summary</h3></div>
                    <div class="widget-content">
                        <div class="cashflow-stats">
                            <div class="cashflow-item"><div class="cashflow-label">Cash In</div><div class="cashflow-value positive"><?php echo number_format($summary['total_revenue'], 2); ?> SAR</div><div class="cashflow-period">This Month</div></div>
                            <div class="cashflow-item"><div class="cashflow-label">Cash Out</div><div class="cashflow-value negative"><?php echo number_format($summary['total_expenses'], 2); ?> SAR</div><div class="cashflow-period">This Month</div></div>
                            <div class="cashflow-item"><div class="cashflow-label">Net Flow</div><div class="cashflow-value"><?php echo number_format($summary['net_profit'], 2); ?> SAR</div><div class="cashflow-period">This Month</div></div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-widget summary-widget">
                    <div class="widget-header"><h3><i class="fas fa-chart-pie"></i> Financial Summary</h3></div>
                    <div class="widget-content">
                        <div class="summary-stats">
                            <div class="summary-item"><div class="summary-label">Total Assets</div><div class="summary-value"><?php echo number_format($summary['cash_balance'] + $summary['receivable'], 2); ?> SAR</div></div>
                            <div class="summary-item"><div class="summary-label">Net Income</div><div class="summary-value"><?php echo number_format($summary['net_profit'], 2); ?> SAR</div></div>
                        </div>
                    </div>
                </div>
            </div>
    <?php elseif ($tab === 'support'): ?>
    <div class="acc-panel">
        <h5><i class="fas fa-hand-holding-usd me-2"></i>Support Payments</h5>
        <?php if (empty($supportPayments)): ?>
        <div class="acc-empty"><i class="fas fa-inbox"></i><p class="mb-0">No support payments yet</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table acc-table">
                <thead><tr><th>ID</th><th>Date</th><th>Country</th><th>Amount</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($supportPayments as $s): ?>
                <tr>
                    <td><?php echo (int)$s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['payment_date'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($countryMap[(int)($s['country_id'] ?? 0)] ?? '-'); ?></td>
                    <td><?php echo number_format((float)($s['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($s['currency_code'] ?? 'SAR'); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr($s['description'] ?? '-', 0, 40)); ?></td>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars($s['status'] ?? '-'); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($tab === 'registrations'): ?>
    <div class="acc-panel" id="registrationRevenueTab">
        <h5 class="mb-2"><i class="fas fa-user-plus me-2"></i>Registration Revenue Analytics</h5>
        <p class="small text-muted mb-4">Collected = paid (approved or still pending). Recognized = approved and paid only. Totals use <strong>plan list prices in USD</strong> (N-Genius card capture is SAR at the configured rate).</p>
        <p class="small text-info mb-3"><i class="fas fa-info-circle me-1"></i>Receipts, General Ledger, and Entry Approval read from accounting tables. Marking a registration as paid now creates a matching receipt and a draft journal entry (with a pending approval). Use the button below once to backfill older paid registrations.</p>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3"><div class="p-3 rounded cp-acc-card-dark"><div class="small text-muted">Collected (all paid)</div><div class="h4 mb-0"><?php echo number_format($regRevenueTotal, 2); ?> USD</div></div></div>
            <div class="col-md-6 col-lg-3"><div class="p-3 rounded cp-acc-card-dark"><div class="small text-muted">Recognized (approved &amp; paid)</div><div class="h4 mb-0"><?php echo number_format($regRevenueTotalRecognized, 2); ?> USD</div></div></div>
            <div class="col-md-6 col-lg-3"><div class="p-3 rounded cp-acc-card-dark"><div class="small text-muted">This month — collected</div><div class="h4 mb-0"><?php echo number_format($regRevenueThisMonth, 2); ?> USD</div><div class="small text-muted mt-1"><?php echo (int)$regThisMonthCount; ?> registrations</div></div></div>
            <div class="col-md-6 col-lg-3"><div class="p-3 rounded cp-acc-card-dark"><div class="small text-muted">This month — recognized</div><div class="h4 mb-0"><?php echo number_format($regRevenueThisMonthRecognized, 2); ?> USD</div><div class="small text-muted mt-1"><?php echo (int)$regThisMonthCountRecognized; ?> registrations</div></div></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><div class="p-3 rounded d-flex flex-wrap gap-2 align-items-center cp-acc-card-dark">
                <span class="small text-muted me-2">Export CSV:</span>
                <a href="<?php echo htmlspecialchars($apiBase . '/accounting-registration-revenue-export.php?country_id=' . $countryId . '&scope=collected'); ?>" class="btn btn-sm btn-outline-success" data-permission="control_accounting,view_control_accounting"><i class="fas fa-file-csv me-1"></i> Collected</a>
                <a href="<?php echo htmlspecialchars($apiBase . '/accounting-registration-revenue-export.php?country_id=' . $countryId . '&scope=recognized'); ?>" class="btn btn-sm btn-outline-success" data-permission="control_accounting,view_control_accounting"><i class="fas fa-file-csv me-1"></i> Recognized</a>
            </div></div>
            <div class="col-md-6"><div class="p-3 rounded cp-acc-card-dark">
                <div class="small text-muted mb-2">Backfill missing links for already-paid registrations (safe to run more than once).</div>
                <button type="button" class="btn btn-sm btn-primary" id="btnCpAccSyncRegistrationPaid" data-permission="control_accounting,manage_control_accounting"><i class="fas fa-link me-1"></i> Sync registration payments to accounting</button>
            </div></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><div class="p-3 rounded cp-acc-card-dark"><h6 class="mb-3">By plan — collected</h6><canvas id="regRevenueByPlanChart" height="200"></canvas></div></div>
            <div class="col-md-6"><div class="p-3 rounded cp-acc-card-dark"><h6 class="mb-3">By plan — recognized</h6><canvas id="regRevenueByPlanChartRecognized" height="200"></canvas></div></div>
            <div class="col-12"><div class="p-3 rounded cp-acc-card-dark"><h6 class="mb-3">Revenue last 6 months</h6><canvas id="regRevenueByMonthChart" height="220"></canvas></div></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="mb-2">By plan — collected</h6>
                <div class="table-responsive"><table class="table table-dark table-sm acc-table"><thead><tr><th>Plan</th><th>Count</th><th>Total (USD)</th></tr></thead><tbody><?php foreach ($regByPlan as $p): ?><tr><td><?php echo htmlspecialchars($p['plan'] ?? '-'); ?></td><td><?php echo (int)($p['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($p['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?><?php if (empty($regByPlan)): ?><tr><td colspan="3" class="text-muted">No data</td></tr><?php endif; ?></tbody></table></div>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">By plan — recognized</h6>
                <div class="table-responsive"><table class="table table-dark table-sm acc-table"><thead><tr><th>Plan</th><th>Count</th><th>Total (USD)</th></tr></thead><tbody><?php foreach ($regByPlanRecognized as $p): ?><tr><td><?php echo htmlspecialchars($p['plan'] ?? '-'); ?></td><td><?php echo (int)($p['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($p['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?><?php if (empty($regByPlanRecognized)): ?><tr><td colspan="3" class="text-muted">No data</td></tr><?php endif; ?></tbody></table></div>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">By country — collected</h6>
                <div class="table-responsive"><table class="table table-dark table-sm acc-table"><thead><tr><th>Country</th><th>Count</th><th>Total (USD)</th></tr></thead><tbody><?php foreach ($regByCountry as $c): ?><tr><td><?php echo htmlspecialchars($c['country_name'] ?? '-'); ?></td><td><?php echo (int)($c['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($c['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?><?php if (empty($regByCountry)): ?><tr><td colspan="3" class="text-muted">No data</td></tr><?php endif; ?></tbody></table></div>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">By country — recognized</h6>
                <div class="table-responsive"><table class="table table-dark table-sm acc-table"><thead><tr><th>Country</th><th>Count</th><th>Total (USD)</th></tr></thead><tbody><?php foreach ($regByCountryRecognized as $c): ?><tr><td><?php echo htmlspecialchars($c['country_name'] ?? '-'); ?></td><td><?php echo (int)($c['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($c['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?><?php if (empty($regByCountryRecognized)): ?><tr><td colspan="3" class="text-muted">No data</td></tr><?php endif; ?></tbody></table></div>
            </div>
        </div>
        <?php
        $cpAccRegRevenueCharts = [
            'plan' => array_map(function ($p) {
                return ['plan' => $p['plan'] ?? '-', 'total' => (float)($p['tot'] ?? 0)];
            }, $regByPlan ?? []),
            'planR' => array_map(function ($p) {
                return ['plan' => $p['plan'] ?? '-', 'total' => (float)($p['tot'] ?? 0)];
            }, $regByPlanRecognized ?? []),
            'month' => $regRevenueByMonth ?? [],
        ];
        ?>
        <script type="application/json" id="cp-acc-reg-revenue-data"><?php echo json_encode($cpAccRegRevenueCharts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
    </div>
    <?php else: ?>
    <div class="dashboard-widget">
        <div class="widget-header"><h3><i class="fas fa-folder-open me-2"></i><?php echo ucfirst($tab); ?></h3></div>
        <div class="widget-content"><p class="text-muted mb-0">Unknown tab.</p></div>
    </div>
    <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/accounting-modals.php'; ?>
</div>
