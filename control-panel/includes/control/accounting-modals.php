<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/accounting-modals.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/accounting-modals.php`.
 */
/**
 * Control Panel Accounting – Popup modals (Chart of Accounts, Cost Centers, Bank Guarantees, General Ledger, New Journal Entry)
 * Included by accounting-content.php; uses $chartAccounts, $costCenters, $bankGuarantees, $journalEntries and summary vars.
 */
if (!isset($chartAccounts)) $chartAccounts = [];
if (!isset($costCenters)) $costCenters = [];
if (!isset($bankGuarantees)) $bankGuarantees = [];
if (!isset($journalEntries)) $journalEntries = [];
if (!isset($expenses)) $expenses = [];
if (!isset($receipts)) $receipts = [];
if (!isset($vouchers)) $vouchers = [];
if (!isset($invoices)) $invoices = [];
if (!isset($approvals)) $approvals = [];
if (!isset($reconciliations)) $reconciliations = [];
if (!isset($summary)) $summary = ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0, 'receivable' => 0, 'payable' => 0];
$chartInactive = ($chartTotal ?? count($chartAccounts)) - ($chartActive ?? 0);
$costInactive = ($costTotal ?? count($costCenters)) - ($costActive ?? 0);
$expenseTotal = 0;
foreach ($expenses as $e) { $expenseTotal += (float)($e['amount'] ?? 0); }
$receiptTotal = 0;
foreach ($receipts as $r) { $receiptTotal += (float)($r['amount'] ?? 0); }
$voucherTotal = 0;
foreach ($vouchers as $v) { $voucherTotal += (float)($v['amount'] ?? 0); }
$invoiceTotal = 0;
foreach ($invoices as $i) { $invoiceTotal += (float)($i['amount'] ?? 0); }
$approvalPending = count(array_filter($approvals, function ($a) { return strtolower(trim($a['status'] ?? '')) === 'pending'; }));
$approvalApproved = count(array_filter($approvals, function ($a) { return strtolower(trim($a['status'] ?? '')) === 'approved'; }));

// Same report titles as Ratib Pro (getReportName) for Financial Reports modal
$cpAccReportTitles = [
    'trial-balance' => 'Trial Balance',
    'income-statement' => 'Income Statement',
    'balance-sheet' => 'Balance Sheet',
    'cash-flow' => 'Cash Flow Report',
    'cash-flow-report' => 'Cash Flow Report',
    'aged-receivables' => 'Ages of Debt Receivable',
    'ages-debt-receivable' => 'Ages of Debt Receivable',
    'ages-credit-receivable' => 'Ages of Credit Receivable',
    'aged-payables' => 'Aged Payables',
    'cash-book' => 'Cash Book',
    'bank-book' => 'Bank Book',
    'general-ledger' => 'General Ledger',
    'account-statement' => 'Account Statement',
    'expense-statement' => 'Expense Statement',
    'chart-of-accounts' => 'Chart of Accounts',
    'chart-of-accounts-report' => 'Chart of Accounts',
    'value-added' => 'Value Added',
    'fixed-assets' => 'Fixed Assets Report',
    'entries-by-year' => 'Entries by Year Report',
    'customer-debits' => 'Customer Debits Report',
    'statistical-position' => 'Statistical Position Report',
    'changes-equity' => 'Changes in Equity',
    'financial-performance' => 'Financial Performance',
    'comparative-report' => 'Comparative Report',
];

/** Safe JSON text for bootstrap elements (names/codes from DB must not break HTML/JSON with <, &, etc.) */
$cpAccJsonEmbedFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

$cpAccNewJeAccountOptionsHtml = '<option value="">Select Account</option>';
$cpAccNewJeActiveAccountCount = 0;
foreach ($chartAccounts as $a) {
    $aid = (int) ($a['id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }
    if (isset($a['is_active']) && (int) $a['is_active'] === 0) {
        continue;
    }
    $code = trim((string) ($a['account_code'] ?? ''));
    $name = trim((string) ($a['account_name'] ?? ''));
    $albl = ($code !== '' && $name !== '') ? $code . ' — ' . $name : ($name !== '' ? $name : $code);
    $cpAccNewJeAccountOptionsHtml .= '<option value="' . $aid . '">' . htmlspecialchars($albl !== '' ? $albl : ('#' . $aid)) . '</option>';
    $cpAccNewJeActiveAccountCount++;
}
if ($cpAccNewJeActiveAccountCount === 0) {
    $cpAccNewJeAccountOptionsHtml .= '<option value="" disabled>No accounts — add them in Chart of Accounts first</option>';
}

$cpAccNewJeCostCenterOptionsHtml = '<option value="">- Main Center</option>';
foreach ($costCenters ?? [] as $cc) {
    $cid = (int) ($cc['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    if (isset($cc['is_active']) && (int) $cc['is_active'] === 0) {
        continue;
    }
    $code = trim((string) ($cc['code'] ?? ''));
    $name = trim((string) ($cc['name'] ?? ''));
    $clbl = ($code !== '' && $name !== '') ? $code . ' — ' . $name : ($name !== '' ? $name : $code);
    $cpAccNewJeCostCenterOptionsHtml .= '<option value="' . $cid . '">' . htmlspecialchars($clbl !== '' ? $clbl : ('#' . $cid)) . '</option>';
}

function cp_acc_format_gl_reference($reference): string {
    $ref = trim((string) $reference);
    if ($ref !== '' && preg_match('/^GL-\d{4}-(\d+)$/i', $ref, $m)) {
        return 'GL-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    return $ref;
}

function cp_acc_format_receipt_number($receiptNumber): string {
    $num = trim((string) $receiptNumber);
    if ($num !== '' && preg_match('/^RC-(\d+)$/i', $num, $m)) {
        return 'RC-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    if ($num !== '' && preg_match('/^(?:REG|RECEIPT|RCP)-?(\d+)$/i', $num, $m)) {
        return 'RC-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    return $num;
}

function cp_acc_format_expense_voucher($voucherNumber): string {
    $num = trim((string) $voucherNumber);
    if ($num !== '' && preg_match('/^EX-(\d+)$/i', $num, $m)) {
        return 'EX-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    if ($num !== '' && preg_match('/^(?:EXP|EXPENSE|VOUCHER)-?(\d+)$/i', $num, $m)) {
        return 'EX-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    return $num;
}

function cp_acc_format_support_payment_number(?string $paymentNumber, int $fallbackId = 0): string {
    $num = trim((string) $paymentNumber);
    if ($num !== '' && preg_match('/^SP-(\d+)$/i', $num, $m)) {
        return 'SP-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    if ($num !== '' && preg_match('/^(?:SUP|SPP|SUPPORT)-?(\d+)$/i', $num, $m)) {
        return 'SP-' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    if ($fallbackId > 0) {
        return 'SP-' . str_pad((string) $fallbackId, 5, '0', STR_PAD_LEFT);
    }
    return $num !== '' ? $num : '—';
}
?>
<div id="cp-acc-bootstrap-report-titles" class="cp-acc-bootstrap-json" hidden><?php echo json_encode($cpAccReportTitles, $cpAccJsonEmbedFlags); ?></div>
<div id="cp-acc-bootstrap-chart-accounts" class="cp-acc-bootstrap-json" hidden><?php echo json_encode(array_values(array_map(function ($a) {
    return ['id' => (int) ($a['id'] ?? 0), 'code' => (string) ($a['account_code'] ?? ''), 'name' => (string) ($a['account_name'] ?? '')];
}, $chartAccounts)), $cpAccJsonEmbedFlags); ?></div>
<div id="cp-acc-bootstrap-cost-centers" class="cp-acc-bootstrap-json" hidden><?php echo json_encode(array_values(array_map(function ($c) {
    return ['id' => (int) ($c['id'] ?? 0), 'code' => (string) ($c['code'] ?? ''), 'name' => (string) ($c['name'] ?? '')];
}, $costCenters ?? [])), $cpAccJsonEmbedFlags); ?></div>
<?php // Modal styles: css/accounting-modals.css; behavior: js/accounting-modals.js (after Flatpickr + DOM). ?>

<!-- Chart of Accounts Modal -->
<div id="chartModal" class="cp-acc-modal" role="dialog" aria-labelledby="chartModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="chartModalTitle"><i class="fas fa-sitemap"></i> Chart of Accounts</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccChartModalReloadBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card"><div class="num"><?php echo $chartTotal ?? 0; ?></div><div class="lbl">Total Accounts</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo $chartActive ?? 0; ?></div><div class="lbl">Active</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo $chartInactive; ?></div><div class="lbl">Inactive</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo number_format($chartBalance ?? 0, 2); ?> <?php echo $currencyLabel ?? 'SAR'; ?></div><div class="lbl">Total Balance</div></div>
                <?php foreach ($chartByType ?? [] as $t => $d): ?>
                <div class="cp-acc-summary-card"><div class="num"><?php echo $d['count']; ?> <?php echo number_format($d['balance'], 2); ?></div><div class="lbl"><?php echo htmlspecialchars($t); ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="cp-acc-filters">
                <label>Account Type:</label><select id="chartFilterType"><option value="">All</option><option value="Asset">Asset</option><option value="Liability">Liability</option><option value="Equity">Equity</option><option value="Income">Income</option><option value="Expense">Expense</option></select>
                <label>Search:</label><input type="text" id="chartFilterSearch" class="cp-acc-w180" placeholder="Search by code or name">
                <label>Show:</label><select id="chartFilterShow"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                <button type="button" class="btn btn-primary btn-sm" id="chartNewAccountBtn"><i class="fas fa-plus"></i> New Account</button>
                <button type="button" class="btn btn-secondary btn-sm" id="chartExportAllBtn"><i class="fas fa-download"></i> Export</button>
            </div>
            <div class="cp-acc-bulk">
                <span class="info" id="chartSelectedInfo">0 selected</span>
                <button type="button" class="btn btn-danger btn-sm" id="chartBulkDeleteBtn">Delete Selected</button>
                <button type="button" class="btn btn-secondary btn-sm" id="chartBulkExportBtn">Export Selected</button>
                <button type="button" class="btn btn-success btn-sm" id="chartBulkActivateBtn">Activate</button>
                <button type="button" class="btn btn-danger btn-sm" id="chartBulkDeactivateBtn">Deactivate</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th><input type="checkbox" id="chartSelectAll"></th><th>Code</th><th>Name</th><th>Type</th><th>Normal</th><th>Opening</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($chartAccounts)): ?>
                    <tr><td colspan="9"><div class="cp-acc-empty"><i class="fas fa-folder-open"></i>No accounts found</div></td></tr>
                    <?php else: foreach ($chartAccounts as $r): ?>
                    <tr data-id="<?php echo (int)($r['id'] ?? 0); ?>" data-balance="<?php echo htmlspecialchars((string)(float)($r['balance'] ?? 0)); ?>" data-currency="<?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?>" data-active="<?php echo !empty($r['is_active']) ? '1' : '0'; ?>">
                        <td><input type="checkbox" class="chart-select" value="<?php echo (int)($r['id'] ?? 0); ?>"></td>
                        <td class="chart-code"><?php echo htmlspecialchars($r['account_code'] ?? '-'); ?></td>
                        <td class="chart-name"><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td>
                        <td class="chart-type"><?php echo htmlspecialchars($r['account_type'] ?? '-'); ?></td>
                        <td>—</td>
                        <td>0.00</td>
                        <td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><span class="badge bg-<?php echo !empty($r['is_active']) ? 'success' : 'secondary'; ?>"><?php echo !empty($r['is_active']) ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary chart-edit-btn me-1">Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger chart-delete-btn">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing all <?php echo count($chartAccounts); ?> accounts</span> <div><button class="btn btn-sm btn-secondary">«</button> <button class="btn btn-sm btn-secondary">&lt;</button> <span> 1 </span> <button class="btn btn-sm btn-secondary">&gt;</button> <button class="btn btn-sm btn-secondary">»</button></div></div>
        </div>
    </div>
</div>

<?php
$cpAccSupportCountryOptionsHtml = '<option value="0">— Not set —</option>';
if (!empty($countries) && is_array($countries)) {
    foreach ($countries as $c) {
        $cid = (int)($c['id'] ?? 0);
        $cpAccSupportCountryOptionsHtml .= '<option value="' . $cid . '">' . htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
    }
}
?>
<!-- Support Payments Modal -->
<div id="supportModal" class="cp-acc-modal" role="dialog" aria-labelledby="supportModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="supportModalTitle"><i class="fas fa-hand-holding-usd"></i> Support Payments</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm" id="cpAccSupportNewBtn"><i class="fas fa-plus"></i> New Payment</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-blue"><div class="num"><?php echo count($supportPayments); ?></div><div class="lbl">Total Payments</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo number_format((float)array_sum(array_map(function ($s) { return (float)($s['amount'] ?? 0); }, $supportPayments ?? [])), 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" id="cpAccSupportDateFrom" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" id="cpAccSupportDateTo" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Search:</label><input type="text" class="cp-acc-w180" id="cpAccSupportSearch" placeholder="Country or description...">
                <label>Status:</label><select id="cpAccSupportStatusFilter"><option value="">All</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>
                <label>Show:</label><select id="cpAccSupportPageSize"><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-bulk d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="info" id="cpAccSupportSelectionInfo">0 selected</span>
                <button type="button" class="btn btn-danger btn-sm" id="cpAccSupportBulkDelete"><i class="fas fa-trash-alt me-1"></i>Delete selected</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccSupportBulkExport"><i class="fas fa-file-csv me-1"></i>Export selected</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table" id="cpAccSupportTable">
                    <thead><tr><th>Ref #</th><th>Date</th><th>Country</th><th>Amount</th><th>Description</th><th>Status</th><th class="text-center cp-acc-th-col-checkbox"><input type="checkbox" id="cpAccSupportSelectAll" title="Select all visible"></th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($supportPayments)): ?>
                    <tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No support payments yet</div></td></tr>
                    <?php else: foreach ($supportPayments as $s): ?>
                    <tr class="cp-acc-support-row"
                        data-id="<?php echo (int)($s['id'] ?? 0); ?>"
                        data-payment-number="<?php echo htmlspecialchars(cp_acc_format_support_payment_number($s['payment_number'] ?? '', (int)($s['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"
                        data-date="<?php echo htmlspecialchars((string)($s['payment_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-country-id="<?php echo (int)($s['country_id'] ?? 0); ?>"
                        data-country-name="<?php echo htmlspecialchars((string)($s['country_name'] ?? ($countryMap[(int)($s['country_id'] ?? 0)] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                        data-amount="<?php echo htmlspecialchars((string)($s['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-currency="<?php echo htmlspecialchars((string)($s['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-description="<?php echo htmlspecialchars((string)($s['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-reference="<?php echo htmlspecialchars((string)($s['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-status="<?php echo htmlspecialchars((string)($s['status'] ?? 'completed'), ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if (!empty($s['lines_json']) && is_string($s['lines_json'])): ?>data-lines-json="<?php echo htmlspecialchars($s['lines_json'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <td><?php echo htmlspecialchars(cp_acc_format_support_payment_number($s['payment_number'] ?? '', (int)($s['id'] ?? 0))); ?></td>
                        <td><?php echo htmlspecialchars($s['payment_date'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($countryMap[(int)($s['country_id'] ?? 0)] ?? ($s['country_name'] ?? '-')); ?></td>
                        <td><?php echo number_format((float)($s['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($s['currency_code'] ?? 'SAR'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($s['description'] ?? '-', 0, 60)); ?></td>
                        <td><span class="badge bg-<?php echo ($s['status'] ?? '') === 'completed' ? 'success' : (($s['status'] ?? '') === 'cancelled' ? 'secondary' : 'warning'); ?>"><?php echo htmlspecialchars($s['status'] ?? 'completed'); ?></span></td>
                        <td class="text-center"><input type="checkbox" class="cp-acc-support-cb form-check-input" value="<?php echo (int)($s['id'] ?? 0); ?>" aria-label="Select row"></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-info cp-acc-support-view me-1"><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn btn-sm btn-outline-warning cp-acc-support-edit me-1"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger cp-acc-support-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($supportPayments); ?> support payments</span></div>
        </div>
    </div>
</div>

<!-- Support Payment add/edit/view form (debit/credit lines like General Ledger / Receipts) -->
<div id="cpAccSupportPaymentFormModal" class="cp-acc-modal" role="dialog" aria-labelledby="cpAccSupportPaymentFormTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="cpAccSupportPaymentFormTitle"><i class="fas fa-hand-holding-usd"></i> New Support Payment</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="cpAccSupportFormId" value="">
            <div class="mb-3">
                <label class="form-label">Payment Date *</label>
                <input type="text" class="form-control cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" inputmode="numeric" placeholder="YYYY-MM-DD" id="cpAccSupportFormDate" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Country</label>
                <select class="form-select" id="cpAccSupportFormCountry"><?php echo $cpAccSupportCountryOptionsHtml; ?></select>
            </div>
            <div class="mb-3 cp-acc-support-payment-number-row">
                <label class="form-label">Reference #</label>
                <input type="text" class="form-control" id="cpAccSupportFormPaymentNumber" value="" readonly tabindex="-1" autocomplete="off" placeholder="Assigned on save">
                <p class="small text-muted mb-0 mt-1">Global sequence SP-00001, SP-00002, … (same value as the list “Ref #” column).</p>
            </div>
            <div class="mb-3">
                <label class="form-label">External reference <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" id="cpAccSupportFormReference" placeholder="PO, ticket ID, etc." autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea class="form-control" id="cpAccSupportFormDesc" rows="2" placeholder="Description"></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" id="cpAccSupportFormCurrency" value="SAR" maxlength="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="cpAccSupportFormStatus">
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <h6 class="text-success mb-2"><i class="fas fa-arrow-down"></i> DEBIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccSpJeDebitBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="debit" title="Add debit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Debit: <span id="cpAccSpJeTotalDebit">0.00</span></p>
            <h6 class="text-danger mb-2"><i class="fas fa-arrow-up"></i> CREDIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccSpJeCreditBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="credit" title="Add credit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Credit: <span id="cpAccSpJeTotalCredit">0.00</span></p>
            <div class="p-2 rounded mb-3 cp-acc-unbalanced-warning"><i class="fas fa-exclamation-triangle text-warning"></i> <span id="cpAccSpJeBalanceMsg">UNBALANCED: 0.00</span></div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="cpAccSupportFormSaveBtn"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Registration Revenue Modal -->
<div id="registrationRevenueModal" class="cp-acc-modal" role="dialog" aria-labelledby="registrationRevenueModalTitle">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="registrationRevenueModalTitle"><i class="fas fa-user-plus"></i> Registration Revenue</h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo number_format($regRevenueTotal ?? 0, 2); ?> SAR</div><div class="lbl">Collected</div></div>
                <div class="cp-acc-summary-card cp-acc-card-blue"><div class="num"><?php echo number_format($regRevenueTotalRecognized ?? 0, 2); ?> SAR</div><div class="lbl">Recognized</div></div>
                <div class="cp-acc-summary-card cp-acc-card-purple"><div class="num"><?php echo (int)($regThisMonthCount ?? 0); ?></div><div class="lbl">This Month (Collected)</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo (int)($regThisMonthCountRecognized ?? 0); ?></div><div class="lbl">This Month (Recognized)</div></div>
            </div>
            <div class="cp-acc-filters">
                <a href="<?php echo htmlspecialchars($apiBase . '/accounting-registration-revenue-export.php?country_id=' . $countryId . '&scope=collected'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> Export Collected</a>
                <a href="<?php echo htmlspecialchars($apiBase . '/accounting-registration-revenue-export.php?country_id=' . $countryId . '&scope=recognized'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> Export Recognized</a>
                <button type="button" class="btn btn-sm btn-primary" id="btnCpAccSyncRegistrationPaid"><i class="fas fa-link me-1"></i> Sync Paid Registrations</button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="mb-2">By Plan - Collected</h6>
                    <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Plan</th><th>Count</th><th>Total (SAR)</th></tr></thead><tbody>
                    <?php foreach ($regByPlan as $p): ?><tr><td><?php echo htmlspecialchars($p['plan'] ?? '-'); ?></td><td><?php echo (int)($p['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($p['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                    <?php if (empty($regByPlan)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No data</div></td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-2">By Plan - Recognized</h6>
                    <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Plan</th><th>Count</th><th>Total (SAR)</th></tr></thead><tbody>
                    <?php foreach ($regByPlanRecognized as $p): ?><tr><td><?php echo htmlspecialchars($p['plan'] ?? '-'); ?></td><td><?php echo (int)($p['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($p['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                    <?php if (empty($regByPlanRecognized)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No data</div></td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-2">By Country - Collected</h6>
                    <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Country</th><th>Count</th><th>Total (SAR)</th></tr></thead><tbody>
                    <?php foreach ($regByCountry as $c): ?><tr><td><?php echo htmlspecialchars($c['country_name'] ?? '-'); ?></td><td><?php echo (int)($c['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($c['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                    <?php if (empty($regByCountry)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No data</div></td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-2">By Country - Recognized</h6>
                    <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Country</th><th>Count</th><th>Total (SAR)</th></tr></thead><tbody>
                    <?php foreach ($regByCountryRecognized as $c): ?><tr><td><?php echo htmlspecialchars($c['country_name'] ?? '-'); ?></td><td><?php echo (int)($c['cnt'] ?? 0); ?></td><td><?php echo number_format((float)($c['tot'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                    <?php if (empty($regByCountryRecognized)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No data</div></td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Chart Account Form Modal -->
<div id="chartAccountFormModal" class="cp-acc-modal" role="dialog" aria-labelledby="chartAccountFormModalTitle">
    <div class="cp-acc-modal-content cp-acc-modal-sm">
        <div class="cp-acc-modal-header">
            <h2 id="chartAccountFormModalTitle"><i class="fas fa-wallet"></i> <span id="chartAccountFormTitleText">New Account</span></h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <input type="hidden" id="chartAccountFormId" value="">
            <div class="mb-3">
                <label for="chartAccountFormCode" class="form-label">Account code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="chartAccountFormCode" placeholder="e.g. 1010" required>
            </div>
            <div class="mb-3">
                <label for="chartAccountFormName" class="form-label">Account name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="chartAccountFormName" placeholder="e.g. Cash in Hand" required>
            </div>
            <div class="mb-3">
                <label for="chartAccountFormType" class="form-label">Type <span class="text-danger">*</span></label>
                <select class="form-select" id="chartAccountFormType">
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Income">Income</option>
                    <option value="Expense">Expense</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="chartAccountFormBalance" class="form-label">Opening balance</label>
                <input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control cp-acc-amount-en" id="chartAccountFormBalance" placeholder="0.00" value="0.00">
            </div>
            <div class="mb-3">
                <label for="chartAccountFormCurrency" class="form-label">Currency</label>
                <input type="text" class="form-control" id="chartAccountFormCurrency" placeholder="SAR" value="SAR" maxlength="10">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="chartAccountFormActive" checked>
                <label class="form-check-label" for="chartAccountFormActive">Active</label>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="chartAccountFormSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Cost Center Form Modal -->
<div id="costCenterFormModal" class="cp-acc-modal" role="dialog">
    <div class="cp-acc-modal-content cp-acc-modal-sm">
        <div class="cp-acc-modal-header">
            <h2 id="costCenterFormTitle"><i class="fas fa-building"></i> Add Cost Center</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="costCenterFormId" value="">
            <div class="mb-3">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="costCenterFormCode" placeholder="e.g. CC001" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="costCenterFormName" placeholder="Cost center name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="costCenterFormDescription" rows="2" placeholder="Description"></textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="costCenterFormActive" checked>
                <label class="form-check-label" for="costCenterFormActive">Active</label>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="costCenterFormSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Bank Guarantee Form Modal -->
<div id="bankGuaranteeFormModal" class="cp-acc-modal" role="dialog">
    <div class="cp-acc-modal-content cp-acc-modal-sm">
        <div class="cp-acc-modal-header">
            <h2 id="bankGuaranteeFormTitle"><i class="fas fa-shield-alt"></i> Add Bank Guarantee</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="bankGuaranteeFormId" value="">
            <div class="mb-3">
                <label class="form-label">Reference <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="bankGuaranteeFormRef" placeholder="Reference number" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Bank Name</label>
                <input type="text" class="form-control" id="bankGuaranteeFormBank" placeholder="Bank name">
            </div>
            <div class="mb-3">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control cp-acc-amount-en" id="bankGuaranteeFormAmount" value="0.00" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Currency</label>
                <input type="text" class="form-control" id="bankGuaranteeFormCurrency" value="SAR" maxlength="10">
            </div>
            <div class="mb-3">
                <label class="form-label">Start Date</label>
                <input type="text" class="form-control cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" id="bankGuaranteeFormStart">
            </div>
            <div class="mb-3">
                <label class="form-label">End Date</label>
                <input type="text" class="form-control cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" id="bankGuaranteeFormEnd">
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="bankGuaranteeFormStatus">
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="bankGuaranteeFormNotes" rows="2" placeholder="Notes"></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="bankGuaranteeFormSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Generic placeholder (Voucher / Invoice / Reconciliation — not receipt) -->
<div id="cpAccGenericFormModal" class="cp-acc-modal" role="dialog">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="cpAccGenericFormTitle"><i class="fas fa-plus-circle"></i> New Entry</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <p class="text-muted mb-3" id="cpAccGenericFormPlaceholderText">This form is not available yet.</p>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt: same layout as New Journal Entry (debit/credit lines, totals, balance) -->
<div id="cpAccReceiptJournalModal" class="cp-acc-modal" role="dialog" aria-labelledby="cpAccReceiptJournalTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="cpAccReceiptJournalTitle"><i class="fas fa-receipt"></i> New Receipt</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="cpAccRcJeRecordId" value="">
            <input type="hidden" id="cpAccRcJeCountryId" value="<?php echo isset($countryId) ? (int) $countryId : 0; ?>">
            <div class="mb-3 d-none" id="cpAccRcNumberRow">
                <label class="form-label">Receipt #</label>
                <input type="text" class="form-control" id="cpAccRcJeReceiptNumber" value="" readonly tabindex="-1" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label">Receipt Date *</label>
                <input type="text" class="form-control cp-acc-rc-je-fp" id="cpAccRcJeDate" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Branch *</label>
                <select class="form-select" id="cpAccRcJeBranch"><option>Main Branch</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">Customers</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="cpAccRcJeCustomer" placeholder="Enter customer name" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpAccRcJeCustomerPlus" aria-label="Add">+</button>
                </div>
            </div>
            <p class="small text-muted mb-3 mb-md-2">Reference is assigned automatically on save (<strong>RC-00001</strong> global sequence) and shown in Receipts and when you edit the entry.</p>
            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea class="form-control" rows="2" id="cpAccRcJeDescription" placeholder="Description"></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" id="cpAccRcJeCurrency" value="SAR" maxlength="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="cpAccRcJeStatus">
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <h6 class="text-success mb-2"><i class="fas fa-arrow-down"></i> DEBIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccRcJeDebitBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="debit" title="Add debit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Debit: <span id="cpAccRcJeTotalDebit">0.00</span></p>
            <h6 class="text-danger mb-2"><i class="fas fa-arrow-up"></i> CREDIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccRcJeCreditBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="credit" title="Add credit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Credit: <span id="cpAccRcJeTotalCredit">0.00</span></p>
            <div class="p-2 rounded mb-3 cp-acc-unbalanced-warning"><i class="fas fa-exclamation-triangle text-warning"></i> <span id="cpAccRcJeBalanceMsg">UNBALANCED: 0.00</span></div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="cpAccRcJeSaveBtn"><i class="fas fa-save me-1"></i>Save Receipt</button>
            </div>
        </div>
    </div>
</div>

<!-- Cost Centers Modal -->
<div id="costModal" class="cp-acc-modal" role="dialog" aria-labelledby="costModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="costModalTitle"><i class="fas fa-building"></i> Cost Centers</h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <button type="button" class="btn btn-primary mb-3" id="costModalAddBtn"><i class="fas fa-plus"></i> Add Cost Center</button>
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-purple"><div class="num"><?php echo $costTotal ?? 0; ?></div><div class="lbl">Total Cost Centers</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo $costActive ?? 0; ?></div><div class="lbl">Active</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo $costInactive; ?></div><div class="lbl">Inactive</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Search:</label><input type="text" class="cp-acc-w200" placeholder="Search cost centers...">
                <label>Status:</label><select><option value="">All</option><option>Active</option><option>Inactive</option></select>
                <label>Show:</label><select><option>10</option><option>25</option><option>50</option></select>
                <button type="button" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($costCenters)): ?>
                    <tr><td colspan="5"><div class="cp-acc-empty"><i class="fas fa-folder-open"></i>No cost centers found</div></td></tr>
                    <?php else: foreach ($costCenters as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['code'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '', 0, 60)); ?></td>
                        <td><span class="badge bg-<?php echo !empty($r['is_active']) ? 'success' : 'secondary'; ?>"><?php echo !empty($r['is_active']) ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary cp-acc-cost-edit-btn"
                                data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                data-code="<?php echo htmlspecialchars((string)($r['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-name="<?php echo htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-description="<?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-is-active="<?php echo !empty($r['is_active']) ? '1' : '0'; ?>"
                            >Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($costCenters); ?> - <?php echo count($costCenters); ?> of <?php echo count($costCenters); ?> cost centers</span> <div>Previous | Page 1 of 1 | Next</div></div>
        </div>
    </div>
</div>

<!-- Bank Guarantees Modal -->
<div id="bankModal" class="cp-acc-modal" role="dialog" aria-labelledby="bankModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="bankModalTitle"><i class="fas fa-shield-alt"></i> Letters of Bank Guarantee</h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <button type="button" class="btn btn-primary mb-3 cp-acc-btn-gradient-purple" id="bankModalAddBtn"><i class="fas fa-plus"></i> Add Bank Guarantee</button>
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-purple"><div class="num"><?php echo $bankTotal ?? 0; ?></div><div class="lbl">Total Guarantees</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo $bankActive ?? 0; ?></div><div class="lbl">Active</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo $bankExpired ?? 0; ?></div><div class="lbl">Expired</div></div>
                <div class="cp-acc-summary-card cp-acc-card-blue"><div class="num"><?php echo number_format($bankAmount ?? 0, 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo date('Y-m-d'); ?>">
                <label>Search:</label><input type="text" class="cp-acc-w180" placeholder="Search guarantees...">
                <label>Status:</label><select><option value="">All</option><option>Active</option><option>Expired</option></select>
                <label>Show:</label><select><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th>Reference Number</th><th>Bank Name</th><th>Amount</th><th>Issue Date</th><th>Expiry Date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($bankGuarantees)): ?>
                    <tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-folder-open"></i>No bank guarantees found</div></td></tr>
                    <?php else: foreach ($bankGuarantees as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(cp_acc_format_gl_reference($r['reference'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($r['bank_name'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><?php echo htmlspecialchars($r['start_date'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['end_date'] ?? '-'); ?></td>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($r['status'] ?? '-'); ?></span></td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary cp-acc-bank-edit-btn"
                                data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                data-reference="<?php echo htmlspecialchars((string)($r['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-bank-name="<?php echo htmlspecialchars((string)($r['bank_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-amount="<?php echo htmlspecialchars((string)($r['amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                                data-currency-code="<?php echo htmlspecialchars((string)($r['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-start-date="<?php echo htmlspecialchars((string)($r['start_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-end-date="<?php echo htmlspecialchars((string)($r['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="<?php echo htmlspecialchars((string)($r['status'] ?? 'active'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-notes="<?php echo htmlspecialchars(str_replace(array("\r", "\n"), ' ', (string)($r['notes'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                            >Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing 0 - 0 of 0 guarantees</span> <div>Previous | Page 1 of 1 | Next</div></div>
        </div>
    </div>
</div>

<!-- General Ledger (Journal Entries) Modal -->
<div id="ledgerModal" class="cp-acc-modal" role="dialog" aria-labelledby="ledgerModalTitle" lang="en">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="ledgerModalTitle"><i class="fas fa-book"></i> General Ledger</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-warning btn-sm" id="cpAccNormalizeNumbersBtn" title="Normalize GL/RC numbers"><i class="fas fa-magic"></i> Normalize Numbers</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerRefreshBtn" title="Reload data"><i class="fas fa-sync-alt"></i> Refresh</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card"><div class="num"><?php echo $journalTotal ?? 0; ?></div><div class="lbl">Total Entries</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo number_format($journalDebit ?? 0, 2); ?> <?php echo $currencyLabel ?? 'SAR'; ?></div><div class="lbl">Total Debit</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo number_format($journalCredit ?? 0, 2); ?> <?php echo $currencyLabel ?? 'SAR'; ?></div><div class="lbl">Total Credit</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo number_format($journalBalance ?? 0, 2); ?> <?php echo $currencyLabel ?? 'SAR'; ?></div><div class="lbl">Balance</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo $journalDraft ?? 0; ?></div><div class="lbl">Draft</div></div>
                <div class="cp-acc-summary-card"><div class="num"><?php echo $journalPosted ?? 0; ?></div><div class="lbl">Posted</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><span class="d-inline-flex align-items-center gap-1 flex-wrap"><input type="text" class="cp-acc-ledger-fp form-control form-control-sm" id="cpAccLedgerDateFrom" value="" placeholder="YYYY-MM-DD" autocomplete="off" readonly><button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 cp-acc-ledger-date-clear" data-target="cpAccLedgerDateFrom" title="Clear">Clear</button></span>
                <label>Date To:</label><span class="d-inline-flex align-items-center gap-1 flex-wrap"><input type="text" class="cp-acc-ledger-fp form-control form-control-sm" id="cpAccLedgerDateTo" value="" placeholder="YYYY-MM-DD" autocomplete="off" readonly><button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 cp-acc-ledger-date-clear" data-target="cpAccLedgerDateTo" title="Clear">Clear</button></span>
                <label>Account:</label><select id="cpAccLedgerAccountFilter"><option value="">All</option></select>
                <label>Search:</label><input type="text" class="cp-acc-w180" id="cpAccLedgerSearch" placeholder="Search entries...">
                <label>Show:</label><select id="cpAccLedgerPageSize"><option>10</option><option>25</option><option>50</option></select>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerPrintBtn" title="Print"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerExportVisibleBtn" title="Export rows currently shown in the table">CSV</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerCopyVisibleBtn" title="Copy visible rows (tab-separated)">Copy</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerExcelVisibleBtn" title="Download visible rows as Excel-openable file">Excel</button>
                <button type="button" class="btn btn-primary btn-sm" data-cp-acc-modal="newJournalModal"><i class="fas fa-plus"></i> New Journal</button>
            </div>
            <p class="small text-muted mb-2 mt-1"><i class="fas fa-info-circle"></i> <strong>Posted / approved only.</strong> Drafts and pending approvals are not listed here — use <strong>Entry Approval</strong>.</p>
            <div class="cp-acc-bulk d-flex flex-wrap align-items-center gap-2">
                <span class="info" id="cpAccLedgerSelectionInfo">0 selected</span>
                <button type="button" class="btn btn-danger btn-sm" id="cpAccLedgerBulkDelete"><i class="fas fa-trash-alt me-1"></i>Delete selected</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccLedgerBulkExport"><i class="fas fa-file-csv me-1"></i>Export selected</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table" id="cpAccLedgerTable">
                    <thead><tr><th>Entry Number</th><th>Journal Date</th><th>Total Debit</th><th>Total Credit</th><th>Debit Account</th><th>Credit Account</th><th>Description</th><th class="text-center cp-acc-th-col-checkbox"><input type="checkbox" id="cpAccLedgerSelectAll" title="Select all visible"></th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($journalEntries)): ?>
                    <tr><td colspan="9"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No posted journals loaded — widen date filters or approve entries in <strong>Entry Approval</strong>.</div></td></tr>
                    <?php else: foreach ($journalEntries as $r):
                        $jid = (int)($r['id'] ?? 0);
                        $jst = strtolower(trim((string)($r['status'] ?? 'draft')));
                    ?>
                    <tr class="cp-acc-ledger-row" data-journal-id="<?php echo $jid; ?>" data-entry-date="<?php echo htmlspecialchars((string)($r['entry_date'] ?? '')); ?>" data-status="<?php echo htmlspecialchars($jst); ?>">
                        <td><?php echo htmlspecialchars(cp_acc_format_gl_reference($r['reference'] ?? '#' . $jid)); ?></td>
                        <td><?php echo htmlspecialchars($r['entry_date'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['total_debit'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($r['total_credit'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars($r['debit_account_label'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['credit_account_label'] ?? '—'); ?></td>
                        <?php $ledgerDesc = (string)($r['description'] ?? ''); ?>
                        <td title="<?php echo htmlspecialchars($ledgerDesc !== '' ? $ledgerDesc : '—'); ?>"><?php echo htmlspecialchars($ledgerDesc !== '' ? (mb_strlen($ledgerDesc) > 100 ? mb_substr($ledgerDesc, 0, 97) . '…' : $ledgerDesc) : '—'); ?></td>
                        <td class="text-center"><input type="checkbox" class="cp-acc-ledger-cb form-check-input" value="<?php echo $jid; ?>" aria-label="Select row"></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-info cp-acc-ledger-view me-1" data-journal-id="<?php echo $jid; ?>"><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn btn-sm btn-outline-warning cp-acc-ledger-edit me-1" data-journal-id="<?php echo $jid; ?>"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger cp-acc-ledger-delete" data-journal-id="<?php echo $jid; ?>"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing up to <?php echo count($journalEntries); ?> loaded entries — use filters above</span></div>
        </div>
    </div>
</div>

<!-- Expenses Modal -->
<div id="expensesModal" class="cp-acc-modal" role="dialog" aria-labelledby="expensesModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="expensesModalTitle"><i class="fas fa-arrow-down"></i> Expenses</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm" id="cpAccExpenseNewBtn"><i class="fas fa-plus"></i> New Expense</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-red"><div class="num"><?php echo count($expenses); ?></div><div class="lbl">Total Records</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo number_format($expenseTotal ?? 0, 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo count(array_filter($expenses, function($e){ return ($e['status'] ?? '') === 'completed'; })); ?></div><div class="lbl">Completed</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" id="cpAccExpenseDateFrom" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" id="cpAccExpenseDateTo" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Search:</label><input type="text" class="cp-acc-w180" id="cpAccExpenseSearch" placeholder="Search...">
                <label>Status:</label><select id="cpAccExpenseStatusFilter"><option value="">All</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>
                <label>Show:</label><select id="cpAccExpensePageSize"><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-bulk d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="info" id="cpAccExpenseSelectionInfo">0 selected</span>
                <button type="button" class="btn btn-danger btn-sm" id="cpAccExpenseBulkDelete"><i class="fas fa-trash-alt me-1"></i>Delete selected</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccExpenseBulkExport"><i class="fas fa-file-csv me-1"></i>Export selected</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table" id="cpAccExpensesTable">
                    <thead><tr><th>Voucher #</th><th>Date</th><th>Amount</th><th>Description</th><th>Status</th><th>Country</th><th class="text-center cp-acc-th-col-checkbox"><input type="checkbox" id="cpAccExpenseSelectAll" title="Select all visible"></th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No expenses recorded yet</div></td></tr>
                    <?php else: foreach ($expenses as $r): ?>
                    <tr class="cp-acc-expense-row"
                        data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                        data-voucher-number="<?php echo htmlspecialchars((string)($r['voucher_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-date="<?php echo htmlspecialchars((string)($r['expense_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-amount="<?php echo htmlspecialchars((string)($r['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-currency="<?php echo htmlspecialchars((string)($r['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-description="<?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-status="<?php echo htmlspecialchars((string)($r['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-country="<?php echo htmlspecialchars((string)($r['country_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if (!empty($r['lines_json']) && is_string($r['lines_json'])): ?>data-lines-json="<?php echo htmlspecialchars($r['lines_json'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <td><?php echo htmlspecialchars(cp_acc_format_expense_voucher($r['voucher_number'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($r['expense_date'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '-', 0, 50)); ?></td>
                        <td><span class="badge bg-<?php echo ($r['status'] ?? '') === 'completed' ? 'success' : (($r['status'] ?? '') === 'cancelled' ? 'secondary' : 'warning'); ?>"><?php echo htmlspecialchars($r['status'] ?? 'pending'); ?></span></td>
                        <td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td>
                        <td class="text-center"><input type="checkbox" class="cp-acc-expense-cb form-check-input" value="<?php echo (int)($r['id'] ?? 0); ?>" aria-label="Select row"></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-info cp-acc-expense-view me-1"><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn btn-sm btn-outline-warning cp-acc-expense-edit me-1"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger cp-acc-expense-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($expenses); ?> expenses</span></div>
        </div>
    </div>
</div>

<!-- Expense: same layout as New Journal Entry -->
<div id="cpAccExpenseFormModal" class="cp-acc-modal" role="dialog" aria-labelledby="cpAccExpenseFormTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="cpAccExpenseFormTitle"><i class="fas fa-arrow-down"></i> New Expense</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="cpAccExpenseFormId" value="">
            <input type="hidden" id="cpAccExJeCountryId" value="<?php echo isset($countryId) ? (int) $countryId : 0; ?>">
            <div class="mb-3">
                <label class="form-label">Expense Date *</label>
                <input type="text" class="form-control cp-acc-ex-je-fp" id="cpAccExJeDate" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Branch *</label>
                <select class="form-select" id="cpAccExJeBranch"><option>Main Branch</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">Customers</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="cpAccExJeCustomer" placeholder="Enter customer name" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpAccExJeCustomerPlus" aria-label="Add">+</button>
                </div>
            </div>
            <div class="mb-3 cp-acc-ex-voucher-row">
                <label class="form-label">Voucher #</label>
                <input type="text" class="form-control" id="cpAccExJeVoucherPreview" value="" readonly placeholder="Assigned on save">
            </div>
            <p class="small text-muted mb-3 mb-md-2">Reference is assigned automatically on save (<strong>EX-00001</strong> global sequence) and shown in Expenses and when you edit the entry.</p>
            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea class="form-control" rows="2" id="cpAccExJeDescription" placeholder="Description"></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" id="cpAccExJeCurrency" value="SAR" maxlength="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="cpAccExJeStatus">
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <h6 class="text-success mb-2"><i class="fas fa-arrow-down"></i> DEBIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccExJeDebitBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="debit" title="Add debit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Debit: <span id="cpAccExJeTotalDebit">0.00</span></p>
            <h6 class="text-danger mb-2"><i class="fas fa-arrow-up"></i> CREDIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccExJeCreditBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-je-like-add="credit" title="Add credit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Credit: <span id="cpAccExJeTotalCredit">0.00</span></p>
            <div class="p-2 rounded mb-3 cp-acc-unbalanced-warning"><i class="fas fa-exclamation-triangle text-warning"></i> <span id="cpAccExJeBalanceMsg">UNBALANCED: 0.00</span></div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="cpAccExpenseFormSaveBtn"><i class="fas fa-save me-1"></i>Save Expense</button>
            </div>
        </div>
    </div>
</div>

<!-- Receipts Modal -->
<div id="receiptsModal" class="cp-acc-modal" role="dialog" aria-labelledby="receiptsModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="receiptsModalTitle"><i class="fas fa-receipt"></i> Receipts</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm cp-acc-open-generic-form" data-form-title="New Receipt" data-form-module="receipts"><i class="fas fa-plus"></i> New Receipt</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo count($receipts); ?></div><div class="lbl">Total Receipts</div></div>
                <div class="cp-acc-summary-card cp-acc-card-blue"><div class="num"><?php echo number_format($receiptTotal ?? 0, 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" id="cpAccReceiptDateFrom" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" id="cpAccReceiptDateTo" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Search:</label><input type="text" class="cp-acc-w180" id="cpAccReceiptSearch" placeholder="Search...">
                <label>Show:</label><select id="cpAccReceiptPageSize"><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-bulk d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="info" id="cpAccReceiptSelectionInfo">0 selected</span>
                <button type="button" class="btn btn-danger btn-sm" id="cpAccReceiptBulkDelete"><i class="fas fa-trash-alt me-1"></i>Delete selected</button>
                <button type="button" class="btn btn-secondary btn-sm" id="cpAccReceiptBulkExport"><i class="fas fa-file-csv me-1"></i>Export selected</button>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table" id="cpAccReceiptsTable">
                    <thead><tr><th>Receipt #</th><th>Date</th><th>Amount</th><th>Description</th><th>Status</th><th>Country</th><th class="text-center cp-acc-th-col-checkbox"><input type="checkbox" id="cpAccReceiptSelectAll" title="Select all visible"></th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($receipts)): ?>
                    <tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No receipts yet</div></td></tr>
                    <?php else: foreach ($receipts as $r): ?>
                    <tr class="cp-acc-receipt-row"
                        data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                        data-receipt-number="<?php echo htmlspecialchars((string)($r['receipt_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-date="<?php echo htmlspecialchars((string)($r['receipt_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-amount="<?php echo htmlspecialchars((string)($r['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-currency="<?php echo htmlspecialchars((string)($r['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-description="<?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-status="<?php echo htmlspecialchars((string)($r['status'] ?? 'completed'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-country="<?php echo htmlspecialchars((string)($r['country_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if (!empty($r['lines_json']) && is_string($r['lines_json'])): ?>data-lines-json="<?php echo htmlspecialchars($r['lines_json'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <td><?php echo htmlspecialchars(cp_acc_format_receipt_number($r['receipt_number'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($r['receipt_date'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '-', 0, 50)); ?></td>
                        <td><span class="badge bg-success"><?php echo htmlspecialchars($r['status'] ?? 'completed'); ?></span></td>
                        <td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td>
                        <td class="text-center"><input type="checkbox" class="cp-acc-receipt-cb form-check-input" value="<?php echo (int)($r['id'] ?? 0); ?>" aria-label="Select row"></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-info cp-acc-receipt-view me-1"><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn btn-sm btn-outline-warning cp-acc-receipt-edit me-1"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger cp-acc-receipt-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($receipts); ?> receipts</span></div>
        </div>
    </div>
</div>

<!-- Disbursement Vouchers Modal -->
<div id="vouchersModal" class="cp-acc-modal" role="dialog" aria-labelledby="vouchersModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="vouchersModalTitle"><i class="fas fa-file-invoice"></i> Disbursement Vouchers</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm cp-acc-open-generic-form" data-form-title="New Voucher"><i class="fas fa-plus"></i> New Voucher</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-purple"><div class="num"><?php echo count($vouchers); ?></div><div class="lbl">Total Vouchers</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo number_format($voucherTotal ?? 0, 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo count(array_filter($vouchers, function($v){ return ($v['status'] ?? '') === 'approved'; })); ?></div><div class="lbl">Approved</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Search:</label><input type="text" class="cp-acc-w180" placeholder="Payee or voucher #...">
                <label>Status:</label><select><option value="">All</option><option>Approved</option><option>Pending</option></select>
                <label>Show:</label><select><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th>Voucher #</th><th>Date</th><th>Payee</th><th>Amount</th><th>Status</th><th>Country</th></tr></thead>
                    <tbody>
                    <?php if (empty($vouchers)): ?>
                    <tr><td colspan="6"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No disbursement vouchers yet</div></td></tr>
                    <?php else: foreach ($vouchers as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['voucher_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['voucher_date'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['payee'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><span class="badge bg-<?php echo ($r['status'] ?? '') === 'approved' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($r['status'] ?? 'pending'); ?></span></td>
                        <td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($vouchers); ?> vouchers</span></div>
        </div>
    </div>
</div>

<!-- Electronic Invoice List Modal -->
<div id="invoicesModal" class="cp-acc-modal" role="dialog" aria-labelledby="invoicesModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="invoicesModalTitle"><i class="fas fa-file-invoice-dollar"></i> Electronic Invoice List</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm cp-acc-open-generic-form" data-form-title="New Invoice"><i class="fas fa-plus"></i> New Invoice</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-blue"><div class="num"><?php echo count($invoices); ?></div><div class="lbl">Total Invoices</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo number_format($invoiceTotal ?? 0, 2); ?> SAR</div><div class="lbl">Total Amount</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo count(array_filter($invoices, function($i){ return ($i['status'] ?? '') !== 'completed'; })); ?></div><div class="lbl">Draft / Pending</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Date From:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Date To:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Search:</label><input type="text" class="cp-acc-w180" placeholder="Invoice # or description...">
                <label>Status:</label><select><option value="">All</option><option>Completed</option><option>Draft</option></select>
                <label>Show:</label><select><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th>Invoice #</th><th>Date</th><th>Amount</th><th>Description</th><th>Status</th><th>Country</th></tr></thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="6"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No electronic invoices yet</div></td></tr>
                    <?php else: foreach ($invoices as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['invoice_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['invoice_date'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo htmlspecialchars($r['currency_code'] ?? 'SAR'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '-', 0, 50)); ?></td>
                        <td><span class="badge bg-<?php echo ($r['status'] ?? '') === 'completed' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($r['status'] ?? 'draft'); ?></span></td>
                        <td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($invoices); ?> invoices</span></div>
        </div>
    </div>
</div>

<!-- Entry Approval Modal -->
<div id="approvalModal" class="cp-acc-modal" role="dialog" aria-labelledby="approvalModalTitle" lang="en">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="approvalModalTitle"><i class="fas fa-check-circle"></i> Entry Approval</h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-purple"><div class="num"><?php echo count($approvals); ?></div><div class="lbl">Total Entries</div></div>
                <div class="cp-acc-summary-card cp-acc-card-amber"><div class="num"><?php echo $approvalPending ?? 0; ?></div><div class="lbl">Pending</div></div>
                <div class="cp-acc-summary-card cp-acc-card-green"><div class="num"><?php echo $approvalApproved ?? 0; ?></div><div class="lbl">Approved</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Status:</label><select id="cpAccApprovalFilterStatus"><option value="">All</option><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select>
                <label>Date From:</label><span class="d-inline-flex align-items-center gap-1 flex-wrap"><input type="text" class="cp-acc-approval-fp form-control form-control-sm" id="cpAccApprovalDateFrom" value="" placeholder="YYYY-MM-DD" inputmode="numeric" autocomplete="off" readonly><button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 cp-acc-clear-approval-date" data-target="cpAccApprovalDateFrom" title="Clear date">Clear</button></span>
                <label>Date To:</label><span class="d-inline-flex align-items-center gap-1 flex-wrap"><input type="text" class="cp-acc-approval-fp form-control form-control-sm" id="cpAccApprovalDateTo" value="" placeholder="YYYY-MM-DD" inputmode="numeric" autocomplete="off" readonly><button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 cp-acc-clear-approval-date" data-target="cpAccApprovalDateTo" title="Clear date">Clear</button></span>
                <label>Show:</label><select id="cpAccApprovalPageSize"><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-approval-bulk mb-2 d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-success" id="cpAccBulkApproveApprovals"><i class="fas fa-check-double me-1"></i>Bulk approve</button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="cpAccBulkRejectApprovals"><i class="fas fa-ban me-1"></i>Bulk reject</button>
                <button type="button" class="btn btn-sm btn-outline-info" id="cpAccBulkUndoApprovals"><i class="fas fa-undo me-1"></i>Bulk undo</button>
                <span class="small text-muted ms-1" id="cpAccApprovalSelectionInfo">0 selected</span>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table" id="cpAccApprovalTable">
                    <thead><tr><th>Journal Entry</th><th>Date</th><th>Status</th><th>Reject Reason</th><th>Created</th><th class="text-center cp-acc-th-col-checkbox"><input type="checkbox" id="cpAccApprovalSelectAll" title="Select all pending"></th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($approvals)): ?>
                    <tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No entry approvals to show</div></td></tr>
                    <?php else: foreach ($approvals as $r):
                        $stRaw = strtolower(trim((string)($r['status'] ?? 'pending')));
                        $isPending = ($stRaw === 'pending');
                        $badgeClass = $stRaw === 'approved' ? 'success' : ($stRaw === 'rejected' ? 'danger' : 'warning');
                        $rejectReason = trim((string)($r['rejection_reason'] ?? ''));
                        $jid = (int)($r['journal_entry_id'] ?? 0);
                        $aid = (int)($r['id'] ?? 0);
                    ?>
                    <tr class="cp-acc-approval-row" data-approval-id="<?php echo $aid; ?>" data-journal-id="<?php echo $jid; ?>" data-status="<?php echo htmlspecialchars($stRaw); ?>" data-entry-date="<?php echo htmlspecialchars((string)($r['entry_date'] ?? '')); ?>">
                        <td><?php echo htmlspecialchars(cp_acc_format_gl_reference($r['reference'] ?? '#' . $jid)); ?></td>
                        <td><?php echo htmlspecialchars($r['entry_date'] ?? '-'); ?></td>
                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($r['status'] ?? 'pending'); ?></span></td>
                        <td><?php echo htmlspecialchars($rejectReason !== '' ? $rejectReason : '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['created_at'] ?? '-'); ?></td>
                        <td class="text-center"><input type="checkbox" class="cp-acc-approval-cb form-check-input" value="<?php echo $aid; ?>" aria-label="Select"></td>
                        <td class="text-nowrap">
                            <?php if ($jid > 0): ?><button type="button" class="btn btn-sm btn-outline-info cp-acc-approval-view me-1" data-journal-id="<?php echo $jid; ?>"><i class="fas fa-eye"></i> View</button><?php endif; ?>
                            <?php if ($isPending): ?>
                            <button type="button" class="btn btn-sm btn-success cp-acc-approval-approve me-1" data-approval-id="<?php echo $aid; ?>"><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn btn-sm btn-outline-danger cp-acc-approval-reject" data-approval-id="<?php echo $aid; ?>"><i class="fas fa-times"></i> Reject</button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-warning cp-acc-approval-undo" data-approval-id="<?php echo $aid; ?>"><i class="fas fa-undo"></i> Undo</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($approvals); ?> entries</span></div>
        </div>
    </div>
</div>

<!-- Reject Reason Modal -->
<div id="approvalRejectReasonModal" class="cp-acc-modal" role="dialog" aria-labelledby="approvalRejectReasonTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-sm">
        <div class="cp-acc-modal-header">
            <h2 id="approvalRejectReasonTitle"><i class="fas fa-ban"></i> Reject Entry</h2>
            <div class="cp-acc-modal-actions">
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <label for="cpAccRejectReasonSelect" class="mb-2 d-block">Rejection reason</label>
            <select id="cpAccRejectReasonSelect" class="form-select form-select-sm mb-3">
                <option value="">Select reason...</option>
                <option value="Missing supporting documents">Missing supporting documents</option>
                <option value="Incorrect account mapping">Incorrect account mapping</option>
                <option value="Unbalanced journal entry">Unbalanced journal entry</option>
                <option value="Amount mismatch">Amount mismatch</option>
                <option value="Duplicate entry">Duplicate entry</option>
                <option value="Wrong period/date">Wrong period/date</option>
                <option value="Policy compliance issue">Policy compliance issue</option>
                <option value="Other">Other</option>
            </select>
            <div id="cpAccRejectReasonError" class="text-danger small mb-2 d-none">Please select a rejection reason.</div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cpAccRejectReasonCancelBtn">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="cpAccRejectReasonConfirmBtn">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Journal detail (from Entry Approval View) -->
<div id="approvalJournalViewModal" class="cp-acc-modal" role="dialog" aria-labelledby="approvalJournalViewTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-md">
        <div class="cp-acc-modal-header">
            <h2 id="approvalJournalViewTitle"><i class="fas fa-book"></i> Journal entry</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body" id="approvalJournalViewBody">
            <p class="text-muted mb-0">Loading…</p>
        </div>
    </div>
</div>

<!-- Bank Reconciliation Modal -->
<div id="reconcileModal" class="cp-acc-modal" role="dialog" aria-labelledby="reconcileModalTitle">
    <div class="cp-acc-modal-content">
        <div class="cp-acc-modal-header">
            <h2 id="reconcileModalTitle"><i class="fas fa-balance-scale"></i> Bank Reconciliation</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-primary btn-sm cp-acc-open-generic-form" data-form-title="New Reconciliation"><i class="fas fa-plus"></i> New Reconciliation</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-summary-row">
                <div class="cp-acc-summary-card cp-acc-card-teal"><div class="num"><?php echo count($reconciliations); ?></div><div class="lbl">Reconciliations</div></div>
            </div>
            <div class="cp-acc-filters">
                <label>Statement Date From:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Statement Date To:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD">
                <label>Country:</label><select><option value="">All</option></select>
                <label>Show:</label><select><option>10</option><option>25</option><option>50</option></select>
            </div>
            <div class="cp-acc-table-wrap">
                <table class="cp-acc-table">
                    <thead><tr><th>Statement Date</th><th>Statement Balance</th><th>Reconciled At</th><th>Country</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($reconciliations)): ?>
                    <tr><td colspan="5"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No bank reconciliations yet</div></td></tr>
                    <?php else: foreach ($reconciliations as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['statement_date'] ?? '-'); ?></td>
                        <td><?php echo number_format((float)($r['statement_balance'] ?? 0), 2); ?> SAR</td>
                        <td><?php echo htmlspecialchars($r['reconciled_at'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td>
                        <td><button class="btn btn-sm btn-outline-secondary">View</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cp-acc-pagination"><span class="info">Showing <?php echo count($reconciliations); ?> reconciliations</span></div>
        </div>
    </div>
</div>

<!-- Financial Reports Modal (Ratib Pro style: summary + category filters + report cards grid) -->
<div id="reportsModal" class="cp-acc-modal" role="dialog" aria-labelledby="reportsModalTitle" lang="en" translate="no" dir="ltr">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="reportsModalTitle"><i class="fas fa-chart-bar"></i> Financial Reports</h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Refresh</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body">
            <div class="cp-acc-report-summary-row">
                <div class="cp-acc-report-summary-card"><div class="lbl">Total Reports</div><div class="num">20</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Financial</div><div class="num">13</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Operational</div><div class="num">7</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Balance Reports</div><div class="num">3</div><div class="sublbl">Trial Balance, Balance Sheet, Cash Flow Report</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Transaction Reports</div><div class="num">5</div><div class="sublbl">Cash Book, Bank Book, Ledger, Account Statement, Chart</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Aging Reports</div><div class="num">2</div><div class="sublbl">Debt Receivable, Credit Receivable</div></div>
                <div class="cp-acc-report-summary-card"><div class="lbl">Analysis Reports</div><div class="num">6</div><div class="sublbl">Income, Expense, Performance, Equity, Comparative</div></div>
            </div>
            <div class="cp-acc-report-filters">
                <label>Category:</label><select><option value="">All</option><option>Financial</option><option>Operational</option><option>Balance</option><option>Transaction</option><option>Aging</option><option>Analysis</option></select>
                <label>Search:</label><input type="text" class="cp-acc-w200" placeholder="Search reports...">
                <button type="button" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export All</button>
            </div>
            <div class="cp-acc-report-cards-grid" id="reportCardsGrid">
                <div class="cp-acc-report-card" data-report-id="trial-balance" title="Run report"><div class="icon"><i class="fas fa-balance-scale"></i></div><div class="title">Trial Balance</div><div class="desc">Summary of all account balances</div></div>
                <div class="cp-acc-report-card" data-report-id="income-statement" title="Run report"><div class="icon"><i class="fas fa-chart-line"></i></div><div class="title">Income Statement</div><div class="desc">Revenue and expenses report</div></div>
                <div class="cp-acc-report-card" data-report-id="balance-sheet" title="Run report"><div class="icon"><i class="fas fa-file-alt"></i></div><div class="title">Balance Sheet</div><div class="desc">Assets, liabilities, and equity</div></div>
                <div class="cp-acc-report-card" data-report-id="cash-flow" title="Run report"><div class="icon"><i class="fas fa-exchange-alt"></i></div><div class="title">Cash Flow Report</div><div class="desc">Cash inflows and outflows</div></div>
                <div class="cp-acc-report-card" data-report-id="general-ledger" title="Run report"><div class="icon"><i class="fas fa-book"></i></div><div class="title">General Ledger</div><div class="desc">Complete ledger report</div></div>
                <div class="cp-acc-report-card" data-report-id="account-statement" title="Run report"><div class="icon"><i class="fas fa-file-invoice"></i></div><div class="title">Account Statement</div><div class="desc">Individual account statement</div></div>
                <div class="cp-acc-report-card" data-report-id="ages-debt-receivable" title="Run report"><div class="icon"><i class="fas fa-clock"></i></div><div class="title">Ages of Debt Receivable</div><div class="desc">Outstanding invoices by age</div></div>
                <div class="cp-acc-report-card" data-report-id="ages-credit-receivable" title="Run report"><div class="icon"><i class="fas fa-clock"></i></div><div class="title">Ages of Credit Receivable</div><div class="desc">Outstanding credit by age</div></div>
                <div class="cp-acc-report-card" data-report-id="cash-book" title="Run report"><div class="icon"><i class="fas fa-book-open"></i></div><div class="title">Cash Book</div><div class="desc">All cash transactions</div></div>
                <div class="cp-acc-report-card" data-report-id="bank-book" title="Run report"><div class="icon"><i class="fas fa-university"></i></div><div class="title">Bank Book</div><div class="desc">All bank transactions</div></div>
                <div class="cp-acc-report-card" data-report-id="chart-of-accounts-report" title="Run report"><div class="icon"><i class="fas fa-sitemap"></i></div><div class="title">Chart of Accounts</div><div class="desc">Account structure overview</div></div>
                <div class="cp-acc-report-card" data-report-id="value-added" title="Run report"><div class="icon"><i class="fas fa-plus-circle"></i></div><div class="title">Value Added</div><div class="desc">Value added analysis report</div></div>
                <div class="cp-acc-report-card" data-report-id="fixed-assets" title="Run report"><div class="icon"><i class="fas fa-building"></i></div><div class="title">Fixed Assets Report</div><div class="desc">Fixed assets overview</div></div>
                <div class="cp-acc-report-card" data-report-id="entries-by-year" title="Run report"><div class="icon"><i class="fas fa-calendar-alt"></i></div><div class="title">Entries by Year Report</div><div class="desc">Annual entries summary</div></div>
                <div class="cp-acc-report-card" data-report-id="customer-debits" title="Run report"><div class="icon"><i class="fas fa-user-minus"></i></div><div class="title">Customer Debits Report</div><div class="desc">Customer debits analysis</div></div>
                <div class="cp-acc-report-card" data-report-id="statistical-position" title="Run report"><div class="icon"><i class="fas fa-chart-pie"></i></div><div class="title">Statistical Position Report</div><div class="desc">Statistical financial position</div></div>
                <div class="cp-acc-report-card" data-report-id="changes-equity" title="Run report"><div class="icon"><i class="fas fa-chart-area"></i></div><div class="title">Changes in Equity</div><div class="desc">Equity changes over time</div></div>
                <div class="cp-acc-report-card" data-report-id="financial-performance" title="Run report"><div class="icon"><i class="fas fa-tachometer-alt"></i></div><div class="title">Financial Performance</div><div class="desc">Financial performance metrics</div></div>
                <div class="cp-acc-report-card" data-report-id="comparative-report" title="Run report"><div class="icon"><i class="fas fa-columns"></i></div><div class="title">Comparative Report</div><div class="desc">Period comparison analysis</div></div>
                <div class="cp-acc-report-card" data-report-id="expense-statement" title="Run report"><div class="icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="title">Expense Statement</div><div class="desc">Detailed expense breakdown</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Report Viewer Modal (shows table/form for the clicked report card) -->
<div id="reportViewerModal" class="cp-acc-modal" role="dialog" aria-labelledby="reportViewerTitle" lang="en" translate="no" dir="ltr">
    <div class="cp-acc-modal-content cp-acc-report-viewer-content cp-acc-modal-md">
        <div class="cp-acc-modal-header">
            <h2 id="reportViewerTitle"><i class="fas fa-file-alt"></i> <span id="reportViewerTitleText">Report</span></h2>
            <div class="cp-acc-modal-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" title="Save Favorite" data-cp-acc-report-action="save-favorite"><i class="far fa-star"></i> Save Favorite</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" title="Compare" data-cp-acc-report-action="compare"><i class="fas fa-balance-scale"></i> Compare</button>
                <button type="button" class="btn btn-secondary btn-sm" title="Print" data-cp-acc-report-action="print"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm" title="Export" data-cp-acc-report-action="export"><i class="fas fa-download"></i> Export</button>
                <button type="button" class="btn btn-secondary btn-sm" title="Refresh" data-cp-acc-report-action="refresh"><i class="fas fa-sync-alt"></i> Refresh</button>
                <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
            </div>
        </div>
        <div class="cp-acc-modal-body" id="reportViewerBody">
            <?php
            $currencyLabel = $currencyLabel ?? 'SAR';
            $recentTransactions = $recentTransactions ?? [];
            $reportStartDate = date('Y-m-01');
            $reportEndDate = date('Y-m-d');
            $reportAsOfDate = date('Y-m-d');
            $totalAccounts = count($chartAccounts ?? []);
            $totalEntries = count($journalEntries ?? []);
            $totalDebit = array_sum(array_column($journalEntries ?? [], 'total_debit'));
            $totalCredit = array_sum(array_column($journalEntries ?? [], 'total_credit'));
            ?>
            <!-- Trial Balance -->
            <div id="report-trial-balance" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Total Accounts</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($totalDebit, 2); ?></span><span class="lbl">Total Debit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($totalCredit, 2); ?></span><span class="lbl">Total Credit</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                <button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Code</th><th>Account Name</th><th>Type</th><th>Balance (<?php echo $currencyLabel; ?>)</th></tr></thead><tbody>
                <?php if (empty($chartAccounts)): ?><tr><td colspan="4"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No accounts</div></td></tr>
                <?php else: foreach ($chartAccounts as $r): ?><tr><td><?php echo htmlspecialchars($r['account_code'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_type'] ?? '-'); ?></td><td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td></tr><?php endforeach; endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="3">TOTALS</td><td><?php echo number_format($totalDebit, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo min(10, $totalAccounts); ?> of <?php echo $totalAccounts; ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Income Statement -->
            <div id="report-income-statement" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></span><span class="lbl">Revenue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></span><span class="lbl">Expenses</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['net_profit'] ?? 0, 2); ?></span><span class="lbl">Net Profit</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Item</th><th>Amount (<?php echo $currencyLabel; ?>)</th></tr></thead><tbody>
                <tr><td>Total Revenue</td><td class="text-success"><?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></td></tr>
                <tr><td>Total Expenses</td><td class="text-danger"><?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></td></tr>
                <tr><td><strong>Net Profit</strong></td><td><strong><?php echo number_format($summary['net_profit'] ?? 0, 2); ?></strong></td></tr>
                </tbody><tfoot><tr class="report-totals-row"><td>TOTALS</td><td><?php echo number_format($summary['net_profit'] ?? 0, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to 3 of 3 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary" disabled>Next</button></div></div>
            </div>
            <!-- Balance Sheet -->
            <?php $balanceSheetAssetTotal = array_sum(array_column(array_filter($chartAccounts ?? [], function($a) { return strtolower($a['account_type'] ?? '') === 'asset'; }), 'balance')); ?>
            <div id="report-balance-sheet" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($balanceSheetAssetTotal, 2); ?></span><span class="lbl">Total Assets</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Accounts</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                <button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Type</th><th>Account</th><th>Balance (<?php echo $currencyLabel; ?>)</th></tr></thead><tbody>
                <?php foreach ($chartAccounts as $r): ?><tr><td><?php echo htmlspecialchars($r['account_type'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td><td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                <?php if (empty($chartAccounts)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No data</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="2">TOTALS</td><td><?php echo number_format(array_sum(array_column($chartAccounts ?? [], 'balance')), 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo min(10, $totalAccounts); ?> of <?php echo $totalAccounts; ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Cash Flow Report (Ratib: cash-flow) -->
            <div id="report-cash-flow" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Operating</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Investing</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Financing</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Net Cash Flow</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Period</th><th>%</th><th><i class="fas fa-arrow-up"></i> Cash In</th><th><i class="fas fa-arrow-down"></i> Cash Out</th><th>Net Flow</th></tr></thead><tbody>
                <?php if (empty($recentTransactions)): ?><tr><td colspan="5"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No cash flow data</div></td></tr>
                <?php else: foreach ($recentTransactions as $t): ?><tr><td><?php echo htmlspecialchars(substr($t['created_at'] ?? '', 0, 10)); ?></td><td>—</td><td><?php echo (float)($t['amount'] ?? 0) >= 0 ? number_format((float)($t['amount'] ?? 0), 2) : '0.00'; ?></td><td><?php echo (float)($t['amount'] ?? 0) < 0 ? number_format(abs((float)($t['amount'] ?? 0)), 2) : '0.00'; ?></td><td><?php echo number_format((float)($t['amount'] ?? 0), 2); ?></td></tr><?php endforeach; endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="2">TOTALS</td><td>0.00</td><td>0.00</td><td><?php echo $currencyLabel; ?> 0.00</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($recentTransactions); ?> of <?php echo count($recentTransactions); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- General Ledger report ($journalEntries = posted/approved only from accounting-content loadTabData) -->
            <div id="report-general-ledger" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Total Accounts</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $totalEntries; ?></span><span class="lbl">With Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val"><?php echo max(0, $totalAccounts - $totalEntries); ?></span><span class="lbl">No Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $totalEntries; ?></span><span class="lbl">Total Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($totalDebit, 2); ?></span><span class="lbl">Total Debit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($totalCredit, 2); ?></span><span class="lbl">Total Credit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($totalDebit - $totalCredit, 2); ?></span><span class="lbl">Balance</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>Account:</label><select class="cp-acc-report-gl-account-select"><option value="">All</option><?php foreach ($chartAccounts as $a): ?><option value="<?php echo (int)($a['id'] ?? 0); ?>"><?php echo htmlspecialchars(($a['account_code'] ?? '') . ' ' . ($a['account_name'] ?? '')); ?></option><?php endforeach; ?></select>
                    <label>SEARCH:</label><input type="text" class="cp-acc-report-search" placeholder="Search accounts, description" autocomplete="off">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                <button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody>
                <?php foreach ($journalEntries as $r): ?><tr><td><?php echo htmlspecialchars($r['entry_date'] ?? '-'); ?></td><td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '-', 0, 30)); ?></td><td><?php echo htmlspecialchars(cp_acc_format_gl_reference($r['reference'] ?? '#' . ($r['id'] ?? ''))); ?></td><td>Journal</td><td><?php echo number_format((float)($r['total_debit'] ?? 0), 2); ?></td><td><?php echo number_format((float)($r['total_credit'] ?? 0), 2); ?></td><td>—</td></tr><?php endforeach; ?>
                <?php if (empty($journalEntries)): ?><tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No posted journals in this range — approve entries in <strong>Entry Approval</strong> to show them here.</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="4">TOTALS</td><td><?php echo number_format($totalDebit, 2); ?></td><td><?php echo number_format($totalCredit, 2); ?></td><td><?php echo number_format($totalDebit - $totalCredit, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo min(10, $totalEntries); ?> of <?php echo $totalEntries; ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Account Statement -->
            <div id="report-account-statement" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val">0</span><span class="lbl">Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Debit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Credit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Balance</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>Account:</label><select class="cp-acc-report-account-select"><option value="">Select account</option><?php foreach ($chartAccounts as $a): ?><option value="<?php echo (int)($a['id'] ?? 0); ?>"><?php echo htmlspecialchars(($a['account_code'] ?? '') . ' - ' . ($a['account_name'] ?? '')); ?></option><?php endforeach; ?></select>
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody><tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-info-circle"></i> Please select an account to generate the statement.<br><small>Please select an account from the filter above to generate the statement.</small></div></td></tr></tbody></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to 0 of 0 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary" disabled>Next</button></div></div>
            </div>
            <!-- Ages of Debt Receivable -->
            <div id="report-ages-debt-receivable" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo count($invoices); ?></span><span class="lbl">Total Receivables</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val">0</span><span class="lbl">Current</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val">0</span><span class="lbl">Overdue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($invoiceTotal ?? 0, 2); ?></span><span class="lbl">Outstanding</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Invoice #</th><th>Customer</th><th>Invoice Date</th><th>Due Date</th><th>Total Amount</th><th>Paid</th><th>Balance</th><th>Days Overdue</th></tr></thead><tbody>
                <?php foreach ($invoices as $r): ?><tr><td><?php echo htmlspecialchars($r['invoice_number'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['invoice_date'] ?? '-'); ?></td><td>—</td><td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo $currencyLabel; ?></td><td>0.00</td><td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td><td>—</td></tr><?php endforeach; ?>
                <?php if (empty($invoices)): ?><tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No receivables found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="6">TOTAL OUTSTANDING:</td><td colspan="2"><?php echo $currencyLabel; ?> <?php echo number_format($invoiceTotal ?? 0, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($invoices); ?> of <?php echo count($invoices); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Ages of Credit Receivable -->
            <div id="report-ages-credit-receivable" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo count($vouchers) + count($expenses); ?></span><span class="lbl">Total Payables</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format(($voucherTotal ?? 0) + array_sum(array_column($expenses ?? [], 'amount')), 2); ?></span><span class="lbl">Outstanding</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Voucher #</th><th>Payee</th><th>Date</th><th>Due Date</th><th>Total Amount</th><th>Paid</th><th>Balance</th><th>Days Overdue</th></tr></thead><tbody>
                <?php foreach (array_merge($vouchers ?? [], $expenses ?? []) as $r): ?><tr><td><?php echo htmlspecialchars($r['voucher_number'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['payee'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['voucher_date'] ?? $r['expense_date'] ?? '-'); ?></td><td>—</td><td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> <?php echo $currencyLabel; ?></td><td>0.00</td><td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td><td>—</td></tr><?php endforeach; ?>
                <?php if (empty($vouchers) && empty($expenses)): ?><tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No payables found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="6">TOTAL OUTSTANDING:</td><td colspan="2"><?php echo $currencyLabel; ?> <?php echo number_format(($voucherTotal ?? 0) + array_sum(array_column($expenses ?? [], 'amount')), 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($vouchers ?? []) + count($expenses ?? []); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Cash Book -->
            <div id="report-cash-book" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo count($recentTransactions); ?></span><span class="lbl">Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Debit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Credit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Closing Balance</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody>
                <?php foreach ($recentTransactions as $t): ?><tr><td><?php echo htmlspecialchars(substr($t['created_at'] ?? '', 0, 10)); ?></td><td><?php echo htmlspecialchars(mb_substr($t['description'] ?? $t['type'] ?? '-', 0, 40)); ?></td><td>—</td><td><?php echo htmlspecialchars($t['type'] ?? '-'); ?></td><td><?php echo (float)($t['amount'] ?? 0) >= 0 ? number_format((float)($t['amount'] ?? 0), 2) : '0.00'; ?></td><td><?php echo (float)($t['amount'] ?? 0) < 0 ? number_format(abs((float)($t['amount'] ?? 0)), 2) : '0.00'; ?></td><td><?php echo number_format((float)($t['amount'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                <?php if (empty($recentTransactions)): ?><tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No cash transactions found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="4">TOTALS</td><td>0.00</td><td>0.00</td><td><?php echo $currencyLabel; ?> 0.00</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($recentTransactions); ?> of <?php echo count($recentTransactions); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Bank Book -->
            <div id="report-bank-book" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Generated: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo count($recentTransactions); ?></span><span class="lbl">Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Debit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Total Credit</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Closing Balance</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Bank Account</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody>
                <?php foreach ($recentTransactions as $t): ?><tr><td><?php echo htmlspecialchars(substr($t['created_at'] ?? '', 0, 10)); ?></td><td><?php echo htmlspecialchars(mb_substr($t['description'] ?? $t['type'] ?? '-', 0, 40)); ?></td><td>—</td><td>—</td><td><?php echo htmlspecialchars($t['type'] ?? '-'); ?></td><td><?php echo (float)($t['amount'] ?? 0) >= 0 ? number_format((float)($t['amount'] ?? 0), 2) : '0.00'; ?></td><td><?php echo (float)($t['amount'] ?? 0) < 0 ? number_format(abs((float)($t['amount'] ?? 0)), 2) : '0.00'; ?></td><td><?php echo number_format((float)($t['amount'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                <?php if (empty($recentTransactions)): ?><tr><td colspan="8"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No bank transactions found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="5">TOTALS</td><td>0.00</td><td>0.00</td><td><?php echo $currencyLabel; ?> 0.00</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($recentTransactions); ?> of <?php echo count($recentTransactions); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Chart of Accounts (Ratib: chart-of-accounts-report) -->
            <div id="report-chart-of-accounts-report" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Total Accounts</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Active</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val">0</span><span class="lbl">Inactive</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                <button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Account Code</th><th>Account Name</th><th>Balance</th></tr></thead><tbody>
                <?php 
                $lastType = '';
                foreach ($chartAccounts as $r): 
                    $type = $r['account_type'] ?? '';
                    if ($type !== $lastType) { $lastType = $type; echo '<tr class="bg-dark"><td colspan="3"><strong>' . htmlspecialchars($type ?: 'Other') . '</strong></td></tr>'; }
                ?><tr><td><?php echo htmlspecialchars($r['account_code'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td><td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?> <?php echo $currencyLabel; ?></td></tr><?php endforeach; ?>
                <?php if (empty($chartAccounts)): ?><tr><td colspan="3"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No accounts</div></td></tr><?php endif; ?>
                </tbody></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo min(5, $totalAccounts); ?> of <?php echo $totalAccounts; ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Value Added -->
            <div id="report-value-added" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></span><span class="lbl">Revenue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></span><span class="lbl">COGS</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format(($summary['total_revenue'] ?? 0) - ($summary['total_expenses'] ?? 0), 2); ?></span><span class="lbl">Value Added</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Account Code</th><th>Account Name</th><th>Type</th><th>Amount</th></tr></thead><tbody>
                <tr><td>5000</td><td>Operating Expenses</td><td>Cost of Goods Sold</td><td><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></td></tr>
                <tr><td>—</td><td>—</td><td>—</td><td>—</td></tr>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="3">TOTALS</td><td>-<?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></td></tr><tr><td colspan="3">VALUE ADDED PERCENTAGE:</td><td>0.00%</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to 5 of 14 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Fixed Assets -->
            <div id="report-fixed-assets" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <?php $assetAccounts = array_filter($chartAccounts ?? [], function($a) { return strtolower($a['account_type'] ?? '') === 'asset'; }); $assetCount = count($assetAccounts); $assetTotal = array_sum(array_column($assetAccounts, 'balance')); ?>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $assetCount; ?></span><span class="lbl">Assets</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($assetTotal, 2); ?></span><span class="lbl">Total Value</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Depreciation</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($assetTotal, 2); ?></span><span class="lbl">Net Value</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Account Code</th><th>Account Name</th><th>Balance</th><th>Description</th></tr></thead><tbody>
                <?php foreach ($assetAccounts as $r): ?><tr><td><?php echo htmlspecialchars($r['account_code'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td><td><?php echo $currencyLabel; ?> <?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td><td>—</td></tr><?php endforeach; ?>
                <?php if (empty($assetAccounts)): ?><tr><td colspan="4"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No fixed assets</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="2">TOTALS</td><td><?php echo $currencyLabel; ?> <?php echo number_format($assetTotal, 2); ?></td><td><?php echo $assetCount; ?> assets</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo min(3, $assetCount); ?> of <?php echo $assetCount; ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Entries by Year -->
            <?php
            $byYear = [];
            foreach ($journalEntries ?? [] as $j) { $y = substr($j['entry_date'] ?? '', 0, 4); if ($y) { if (!isset($byYear[$y])) $byYear[$y] = ['count' => 0, 'debit' => 0, 'credit' => 0]; $byYear[$y]['count']++; $byYear[$y]['debit'] += (float)($j['total_debit'] ?? 0); $byYear[$y]['credit'] += (float)($j['total_credit'] ?? 0); } }
            $totalByYearEntries = array_sum(array_column($byYear, 'count'));
            $totalByYearAmount = array_sum(array_column($byYear, 'debit'));
            ?>
            <div id="report-entries-by-year" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo htmlspecialchars($reportStartDate); ?> to <?php echo htmlspecialchars($reportEndDate); ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo count($byYear); ?></span><span class="lbl">Years</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $totalByYearEntries; ?></span><span class="lbl">Total Entries</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Year</th><th>%</th><th>Entry Count</th><th>Total Amount</th></tr></thead><tbody>
                <?php foreach ($byYear as $year => $d): ?><tr><td><?php echo $year; ?></td><td>—</td><td><?php echo $d['count']; ?></td><td><?php echo $currencyLabel; ?> <?php echo number_format($d['debit'], 2); ?></td></tr><?php endforeach; ?>
                <?php if (empty($byYear)): ?><tr><td colspan="4"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No entries found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="2">TOTALS</td><td><?php echo $totalByYearEntries; ?></td><td><?php echo $currencyLabel; ?> <?php echo number_format($totalByYearAmount, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($byYear); ?> of <?php echo count($byYear); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Customer Debits -->
            <div id="report-customer-debits" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo count($invoices); ?></span><span class="lbl">Customers</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo count($invoices); ?></span><span class="lbl">Debits</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($invoiceTotal ?? 0, 2); ?></span><span class="lbl">Total Debit</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Customer</th><th>Invoice Count</th><th>Total Invoiced</th><th>Total Paid</th><th>Total Debit</th><th>Overdue Count</th><th>Latest Due Date</th></tr></thead><tbody>
                <?php foreach ($invoices as $r): ?><tr><td><?php echo htmlspecialchars($r['country_name'] ?? '-'); ?></td><td>1</td><td><?php echo $currencyLabel; ?> <?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> <?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td><td>0</td><td>—</td></tr><?php endforeach; ?>
                <?php if (empty($invoices)): ?><tr><td colspan="7"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No customer debits found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td>TOTALS</td><td><?php echo count($invoices); ?></td><td><?php echo $currencyLabel; ?> <?php echo number_format($invoiceTotal ?? 0, 2); ?></td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> <?php echo number_format($invoiceTotal ?? 0, 2); ?></td><td colspan="2">—</td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($invoices); ?> of <?php echo count($invoices); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Statistical Position -->
            <div id="report-statistical-position" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> As of: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo $totalAccounts; ?></span><span class="lbl">Accounts</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $totalEntries; ?></span><span class="lbl">Transactions</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo count($invoices); ?></span><span class="lbl">Receivables</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-amber"><span class="val"><?php echo count($vouchers) + count($expenses); ?></span><span class="lbl">Payables</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>AS OF DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportAsOfDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Category</th><th>Metric</th><th>Value</th></tr></thead><tbody>
                <tr><td>Accounts</td><td>Total</td><td><?php echo $totalAccounts; ?></td></tr>
                <tr><td>Accounts</td><td>Active</td><td><?php echo $totalAccounts; ?></td></tr>
                <tr><td>Accounts</td><td>Inactive</td><td>0</td></tr>
                <tr><td>Transactions</td><td>Total</td><td><?php echo $totalEntries; ?></td></tr>
                <tr><td>Transactions</td><td>Total amount</td><td><?php echo $totalDebit; ?></td></tr>
                <tr><td>Receivables</td><td>Count</td><td><?php echo count($invoices); ?></td></tr>
                <tr><td>Payables</td><td>Count</td><td><?php echo count($vouchers) + count($expenses); ?></td></tr>
                </tbody></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to 5 of 20 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Changes in Equity -->
            <?php $equityAccounts = array_filter($chartAccounts ?? [], function($a) { return strtolower($a['account_type'] ?? '') === 'equity'; }); $equityTotal = array_sum(array_column($equityAccounts, 'balance')); ?>
            <div id="report-changes-equity" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Opening</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo count($equityAccounts); ?></span><span class="lbl">Changes</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($equityTotal, 2); ?></span><span class="lbl">Closing</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Net Change</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Account Code</th><th>Account Name</th><th>Period</th><th>Change Amount</th><th>Current Balance</th></tr></thead><tbody>
                <?php foreach ($equityAccounts as $r): ?><tr><td><?php echo htmlspecialchars($r['account_code'] ?? '-'); ?></td><td><?php echo htmlspecialchars($r['account_name'] ?? '-'); ?></td><td>Current</td><td class="text-danger"><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> <?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td></tr><?php endforeach; ?>
                <?php if (empty($equityAccounts)): ?><tr><td colspan="5"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No equity accounts</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="4">TOTALS</td><td><?php echo $currencyLabel; ?> <?php echo number_format($equityTotal, 2); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to <?php echo count($equityAccounts); ?> of <?php echo count($equityAccounts); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Financial Performance -->
            <div id="report-financial-performance" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></span><span class="lbl">Revenue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></span><span class="lbl">Expenses</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($summary['net_profit'] ?? 0, 2); ?></span><span class="lbl">Net Income</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val">0%</span><span class="lbl">Profit Margin</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>
                <tr><td>Total Revenue</td><td><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></td></tr>
                <tr><td>Total Expenses</td><td><?php echo $currencyLabel; ?> <?php echo number_format($summary['total_expenses'] ?? 0, 2); ?></td></tr>
                <tr><td>Net Income</td><td><?php echo $currencyLabel; ?> <?php echo number_format($summary['net_profit'] ?? 0, 2); ?></td></tr>
                <tr><td>Total Assets</td><td><?php echo $currencyLabel; ?> <?php echo number_format(array_sum(array_column($chartAccounts ?? [], 'balance')), 2); ?></td></tr>
                <tr><td>Total Liabilities</td><td><?php echo $currencyLabel; ?> 0.00</td></tr>
                </tbody></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to 5 of 9 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
            <!-- Comparative Report -->
            <div id="report-comparative-report" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Generated: <?php echo $reportAsOfDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Current Revenue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Previous Revenue</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-green"><span class="val"><?php echo $currencyLabel; ?> 0.00</span><span class="lbl">Change</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val">0%</span><span class="lbl">Change %</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
                    <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                    <button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                </div>
                <p class="small text-muted mb-2">Comparing: Oct 2025 - Dec 2025 vs Jan 2026 - Mar 2026</p>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Item</th><th>Previous Period</th><th>Current Period</th><th>Change</th><th>Change %</th></tr></thead><tbody>
                <tr><td>Revenue</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td>0.00%</td></tr>
                <tr><td>Expenses</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td>0.00%</td></tr>
                <tr><td>Net Income</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td><?php echo $currencyLabel; ?> 0.00</td><td>0.00%</td></tr>
                </tbody></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 1 to 3 of 3 entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary" disabled>Next</button></div></div>
            </div>
            <!-- Expense Statement -->
            <?php $expenseTotalAmt = array_sum(array_column($expenses ?? [], 'amount')); ?>
            <div id="report-expense-statement" class="cp-acc-report-panel">
                <div class="report-period"><i class="fas fa-calendar-alt"></i> Period: <?php echo $reportStartDate; ?> to <?php echo $reportEndDate; ?></div>
                <div class="cp-acc-report-status-cards">
                    <div class="cp-acc-report-status-card cp-acc-card-purple"><span class="val"><?php echo count($expenses); ?></span><span class="lbl">Expenses</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-blue"><span class="val">0</span><span class="lbl">Categories</span></div>
                    <div class="cp-acc-report-status-card cp-acc-card-red"><span class="val"><?php echo $currencyLabel; ?> <?php echo number_format($expenseTotalAmt, 2); ?></span><span class="lbl">Total Expenses</span></div>
                </div>
                <div class="cp-acc-report-filters-bar">
                    <label>START DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportStartDate; ?>">
                    <label>END DATE:</label><input type="text" class="cp-acc-fp-en" lang="en" dir="ltr" autocomplete="off" readonly inputmode="numeric" placeholder="YYYY-MM-DD" value="<?php echo $reportEndDate; ?>">
                    <label>SEARCH:</label><input type="text" placeholder="Search accounts, description">
                    <label>SHOW ENTRIES:</label><select class="cp-acc-report-show-entries"><option value="5">5</option><option value="10">10</option><option value="25">25</option></select>
<button type="button" class="btn btn-sm btn-apply-filters cp-acc-report-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-sm btn-clear-report cp-acc-report-clear">Clear</button>
                </div>
                <div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Category</th><th>Description</th><th>Date</th><th>Amount</th><th>Count</th></tr></thead><tbody>
                <?php foreach ($expenses as $r): ?><tr><td>—</td><td><?php echo htmlspecialchars(mb_substr($r['description'] ?? '-', 0, 40)); ?></td><td><?php echo htmlspecialchars($r['expense_date'] ?? '-'); ?></td><td><?php echo $currencyLabel; ?> <?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td><td>1</td></tr><?php endforeach; ?>
                <?php if (empty($expenses)): ?><tr><td colspan="5"><div class="cp-acc-empty"><i class="fas fa-inbox"></i>No expenses found</div></td></tr><?php endif; ?>
                </tbody><tfoot><tr class="report-totals-row"><td colspan="3">TOTALS</td><td><?php echo $currencyLabel; ?> <?php echo number_format($expenseTotalAmt, 2); ?></td><td><?php echo count($expenses); ?></td></tr></tfoot></table></div>
                <div class="cp-acc-report-pagination"><span>Showing 0 to <?php echo count($expenses); ?> of <?php echo count($expenses); ?> entries</span><div class="nav"><button class="btn btn-sm btn-outline-secondary" disabled>Previous</button><button class="btn btn-sm btn-primary">1</button><button class="btn btn-sm btn-outline-secondary">Next</button></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit journal (from General Ledger) — layout matches New Journal Entry -->
<div id="ledgerJournalEditModal" class="cp-acc-modal" role="dialog" aria-labelledby="ledgerJournalEditTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="ledgerJournalEditTitle"><i class="fas fa-edit"></i> Edit journal entry</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="cpAccJeEditId" value="">
            <p class="small text-muted mb-3" id="cpAccJeEditStatusHint"></p>
            <div class="mb-3">
                <label class="form-label">Journal Date *</label>
                <input type="text" class="form-control cp-acc-je-edit-fp" id="cpAccJeEditDate" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Branch *</label>
                <select class="form-select" id="cpAccJeEditBranch"><option>Main Branch</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">Customers</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="cpAccJeEditCustomer" placeholder="Enter customer name" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpAccJeEditCustomerPlus" aria-label="Add">+</button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea class="form-control" rows="2" id="cpAccJeEditDescription" placeholder="Description"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Reference *</label>
                <input type="text" class="form-control" id="cpAccJeEditReference" autocomplete="off">
            </div>
            <h6 class="text-success mb-2"><i class="fas fa-arrow-down"></i> DEBIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccJeEditDebitBody"></tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Debit: <span id="cpAccJeEditTotalDebit">0.00</span></p>
            <div class="mb-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="cpAccJeEditAddDebit"><i class="fas fa-plus"></i> Debit line</button></div>
            <h6 class="text-danger mb-2"><i class="fas fa-arrow-up"></i> CREDIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccJeEditCreditBody"></tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Credit: <span id="cpAccJeEditTotalCredit">0.00</span></p>
            <div class="mb-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="cpAccJeEditAddCredit"><i class="fas fa-plus"></i> Credit line</button></div>
            <div class="p-2 rounded mb-3 cp-acc-unbalanced-warning"><i class="fas fa-exclamation-triangle text-warning"></i> <span id="cpAccJeEditBalanceMsg">UNBALANCED: 0.00</span></div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary" id="cpAccJeEditSave"><i class="fas fa-save me-1"></i>Save</button>
                <button type="button" class="btn btn-secondary" data-cp-acc-je-edit-close>Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- New Journal Entry Modal -->
<div id="newJournalModal" class="cp-acc-modal" role="dialog" aria-labelledby="newJournalModalTitle" lang="en">
    <div class="cp-acc-modal-content cp-acc-modal-lg">
        <div class="cp-acc-modal-header">
            <h2 id="newJournalModalTitle"><i class="fas fa-plus-circle"></i> New Journal Entry</h2>
            <span class="cp-acc-close" aria-label="Close"><i class="fas fa-times fa-lg"></i></span>
        </div>
        <div class="cp-acc-modal-body cp-acc-form-dark">
            <input type="hidden" id="cpAccNewJeCountryId" value="<?php echo isset($countryId) ? (int) $countryId : 0; ?>">
            <div class="mb-3">
                <label class="form-label">Journal Date *</label>
                <input type="text" class="form-control cp-acc-new-je-fp" id="cpAccNewJeDate" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Branch *</label>
                <select class="form-select" id="cpAccNewJeBranch"><option>Main Branch</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">Customers</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="cpAccNewJeCustomer" placeholder="Enter customer name" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpAccNewJeCustomerPlus" aria-label="Add">+</button>
                </div>
            </div>
            <p class="small text-muted mb-3 mb-md-2">Reference is assigned automatically on save (<strong>GL-00001</strong> global sequence) and shown in the General Ledger and when you edit the entry.</p>
            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea class="form-control" rows="2" id="cpAccNewJeDescription" placeholder="Description"></textarea>
            </div>
            <h6 class="text-success mb-2"><i class="fas fa-arrow-down"></i> DEBIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccNewJeDebitBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-new-je-add="debit" title="Add debit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Debit: <span id="cpAccNewJeTotalDebit">0.00</span></p>
            <h6 class="text-danger mb-2"><i class="fas fa-arrow-up"></i> CREDIT</h6>
            <div class="cp-acc-table-wrap mb-2">
                <table class="cp-acc-table">
                    <thead><tr><th>Account Name</th><th>Cost Center</th><th>Description</th><th>VAT Report</th><th>Amount</th><th class="cp-acc-th-col-action"></th></tr></thead>
                    <tbody id="cpAccNewJeCreditBody">
                    <tr class="cp-acc-new-je-row">
                        <td>
                            <select class="form-select form-select-sm cp-acc-new-je-account"><?php echo $cpAccNewJeAccountOptionsHtml; ?></select>
                            <input type="text" class="form-control form-control-sm mt-1 cp-acc-new-je-acct-name d-none" placeholder="Account name (if not in chart)" autocomplete="off">
                        </td>
                        <td><select class="form-select form-select-sm cp-acc-new-je-costcenter"><?php echo $cpAccNewJeCostCenterOptionsHtml; ?></select></td>
                        <td><input type="text" class="form-control form-control-sm cp-acc-new-je-line-desc" placeholder="Description" autocomplete="off"></td>
                        <td><input type="checkbox" class="form-check-input cp-acc-new-je-vat" title="VAT (display only until stored on lines)"></td>
                        <td><input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control form-control-sm cp-acc-new-je-amount cp-acc-amount-en" value="0.00" placeholder="0.00"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" data-cp-acc-new-je-add="credit" title="Add credit line">+</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-3">Total Credit: <span id="cpAccNewJeTotalCredit">0.00</span></p>
            <div class="p-2 rounded mb-3 cp-acc-unbalanced-warning"><i class="fas fa-exclamation-triangle text-warning"></i> <span id="cpAccNewJeBalanceMsg">UNBALANCED: 0.00</span></div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary cp-acc-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="cpAccNewJeSaveBtn"><i class="fas fa-save me-1"></i>Save Journal Entry</button>
            </div>
        </div>
    </div>
</div>

