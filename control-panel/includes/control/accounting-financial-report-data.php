<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/accounting-financial-report-data.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/accounting-financial-report-data.php`.
 */
/**
 * Build JSON payload for Financial Reports viewer (date-filtered).
 * Used by api/control/accounting.php?action=financial_report_data
 */
declare(strict_types=1);

/**
 * @param mysqli $ctrl
 * @param array<int>|null $allowedCountryIds
 * @param array<string, mixed> $p
 * @return array<string, mixed>
 */
function control_build_financial_report_payload(mysqli $ctrl, ?array $allowedCountryIds, array $p): array
{
    $currency = 'SAR';
    $reportId = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($p['report_id'] ?? '')));
    if ($reportId === '') {
        return ['success' => false, 'message' => 'Missing report_id'];
    }

    $countryIdIn = max(0, (int) ($p['country_id'] ?? 0));
    if ($allowedCountryIds !== null && !empty($allowedCountryIds) && $countryIdIn > 0
        && !in_array($countryIdIn, array_map('intval', $allowedCountryIds), true)) {
        return ['success' => false, 'message' => 'Country not allowed'];
    }
    if ($allowedCountryIds === [] && $countryIdIn > 0) {
        return ['success' => false, 'message' => 'No country access'];
    }

    $start = control_report_parse_date((string) ($p['start_date'] ?? ''));
    $end = control_report_parse_date((string) ($p['end_date'] ?? ''));
    $asOf = control_report_parse_date((string) ($p['as_of_date'] ?? ''));
    if ($start === null) {
        $start = date('Y-m-01');
    }
    if ($end === null) {
        $end = date('Y-m-d');
    }
    if ($asOf === null) {
        $asOf = $end;
    }
    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    $search = mb_strtolower(trim((string) ($p['search'] ?? '')));
    $accountId = max(0, (int) ($p['account_id'] ?? 0));
    $limit = max(1, min(500, (int) ($p['limit'] ?? 200)));

    $jeCountry = control_report_je_country_sql($allowedCountryIds, $countryIdIn);
    $txCountry = control_report_tx_country_sql($allowedCountryIds, $countryIdIn);
    $chartWhere = control_report_chart_where_sql($allowedCountryIds, $countryIdIn);

    $chkJe = $ctrl->query("SHOW TABLES LIKE 'control_journal_entries'");
    $hasJe = $chkJe && $chkJe->num_rows > 0;
    $chkJl = $ctrl->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
    $hasJl = $chkJl && $chkJl->num_rows > 0;
    $chkCa = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
    $hasCa = $chkCa && $chkCa->num_rows > 0;
    $chkTx = $ctrl->query("SHOW TABLES LIKE 'control_accounting_transactions'");
    $hasTx = $chkTx && $chkTx->num_rows > 0;

    switch ($reportId) {
        case 'trial-balance':
            return control_fr_trial_balance($ctrl, $reportId, $start, $end, $asOf, $search, $limit, $currency, $chartWhere, $jeCountry, $hasJe, $hasCa);
        case 'income-statement':
            return control_fr_income_statement($ctrl, $reportId, $start, $end, $currency, $txCountry, $hasTx);
        case 'balance-sheet':
            return control_fr_balance_sheet($ctrl, $reportId, $asOf, $search, $limit, $currency, $chartWhere, $hasCa);
        case 'cash-flow':
        case 'cash-book':
        case 'bank-book':
            return control_fr_transactions_book($ctrl, $reportId, $start, $end, $search, $limit, $currency, $txCountry, $hasTx, $reportId === 'bank-book');
        case 'general-ledger':
            return control_fr_general_ledger($ctrl, $reportId, $start, $end, $accountId, $search, $limit, $currency, $jeCountry, $chartWhere, $hasJe, $hasJl, $hasCa);
        case 'account-statement':
            return control_fr_account_statement($ctrl, $reportId, $start, $end, $accountId, $search, $limit, $currency, $jeCountry, $hasJe, $hasJl, $hasCa);
        case 'chart-of-accounts-report':
            return control_fr_chart_list($ctrl, $reportId, $asOf, $search, $limit, $currency, $chartWhere, $hasCa);
        case 'value-added':
            return control_fr_value_added($ctrl, $reportId, $start, $end, $currency, $txCountry, $hasTx);
        case 'fixed-assets':
            return control_fr_fixed_assets($ctrl, $reportId, $asOf, $search, $limit, $currency, $chartWhere, $hasCa);
        case 'entries-by-year':
            return control_fr_entries_by_year($ctrl, $reportId, $start, $end, $currency, $jeCountry, $hasJe);
        case 'ages-debt-receivable':
        case 'customer-debits':
            return control_fr_invoices_aging($ctrl, $reportId, $asOf, $search, $limit, $currency, $allowedCountryIds, $countryIdIn);
        case 'ages-credit-receivable':
            return control_fr_payables_aging($ctrl, $reportId, $asOf, $search, $limit, $currency, $allowedCountryIds, $countryIdIn, $ctrl);
        case 'statistical-position':
            return control_fr_statistical($ctrl, $reportId, $asOf, $currency, $chartWhere, $jeCountry, $hasCa, $hasJe, $allowedCountryIds, $countryIdIn);
        case 'changes-equity':
            return control_fr_equity_changes($ctrl, $reportId, $start, $end, $search, $limit, $currency, $chartWhere, $jeCountry, $hasJe, $hasJl, $hasCa);
        case 'financial-performance':
            return control_fr_financial_performance($ctrl, $reportId, $start, $end, $currency, $txCountry, $hasTx, $chartWhere, $hasCa);
        case 'comparative-report':
            return control_fr_comparative($ctrl, $reportId, $start, $end, $currency, $txCountry, $hasTx);
        case 'expense-statement':
            return control_fr_expense_statement($ctrl, $reportId, $start, $end, $search, $limit, $currency, $allowedCountryIds, $countryIdIn, $ctrl);
        default:
            return ['success' => false, 'message' => 'Unknown report_id'];
    }
}

function control_report_parse_date(string $s): ?string
{
    $s = trim(control_normalize_ascii_digits($s));
    if ($s === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    return $s;
}

function control_report_je_country_sql(?array $allowedCountryIds, int $countryId): string
{
    if ($allowedCountryIds === []) {
        return '1=0';
    }
    if ($countryId > 0) {
        if ($allowedCountryIds !== null && !in_array($countryId, array_map('intval', $allowedCountryIds), true)) {
            return '1=0';
        }
        return 'j.country_id = ' . $countryId;
    }
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        return 'j.country_id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
    }
    return '1=1';
}

/** Only journals that have been approved for posting (excludes draft / pending approval). */
function control_fr_journal_posted_only_sql(): string
{
    return "LOWER(TRIM(COALESCE(j.status,''))) IN ('posted','approved')";
}

function control_report_tx_country_sql(?array $allowedCountryIds, int $countryId): string
{
    if ($allowedCountryIds === []) {
        return '1=0';
    }
    if ($countryId > 0) {
        if ($allowedCountryIds !== null && !in_array($countryId, array_map('intval', $allowedCountryIds), true)) {
            return '1=0';
        }
        return 't.country_id = ' . $countryId;
    }
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        return 't.country_id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
    }
    return '1=1';
}

function control_report_chart_where_sql(?array $allowedCountryIds, int $countryId): string
{
    $base = 'ca.is_active = 1';
    if ($allowedCountryIds === []) {
        return '1=0';
    }
    if ($countryId > 0) {
        if ($allowedCountryIds !== null && !in_array($countryId, array_map('intval', $allowedCountryIds), true)) {
            return '1=0';
        }
        return $base . ' AND (ca.country_id = 0 OR ca.country_id = ' . $countryId . ')';
    }
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $ids = implode(',', array_map('intval', $allowedCountryIds));
        return $base . ' AND (ca.country_id = 0 OR ca.country_id IN (' . $ids . '))';
    }
    return $base;
}

function control_tx_revenue_types(): array
{
    return ['receipt', 'income', 'payment_received', 'revenue', 'commission', 'settlement_in'];
}

function control_tx_expense_types(): array
{
    return ['expense', 'payment', 'disbursement', 'settlement_out'];
}

function control_fr_sum_tx_by_range(mysqli $ctrl, string $txCountry, string $start, string $end): array
{
    $rev = 0.0;
    $exp = 0.0;
    $st = $ctrl->prepare('SELECT type, SUM(amount) as tot FROM control_accounting_transactions t WHERE DATE(t.created_at) BETWEEN ? AND ? AND ' . $txCountry . ' GROUP BY type');
    if (!$st) {
        return ['revenue' => 0.0, 'expense' => 0.0];
    }
    $st->bind_param('ss', $start, $end);
    $st->execute();
    $r = $st->get_result();
    $revTypes = control_tx_revenue_types();
    $expTypes = control_tx_expense_types();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $t = strtolower((string) ($row['type'] ?? ''));
            $tot = (float) ($row['tot'] ?? 0);
            if (in_array($t, $revTypes, true)) {
                $rev += $tot;
            }
            if (in_array($t, $expTypes, true)) {
                $exp += $tot;
            }
        }
    }
    $st->close();
    return ['revenue' => $rev, 'expense' => $exp];
}

function control_fr_journal_totals_in_range(mysqli $ctrl, string $jeCountry, string $start, string $end): array
{
    $posted = control_fr_journal_posted_only_sql();
    $sql = 'SELECT COALESCE(SUM(j.total_debit),0) AS td, COALESCE(SUM(j.total_credit),0) AS tc, COUNT(*) AS n FROM control_journal_entries j WHERE j.entry_date >= ? AND j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted;
    $st = $ctrl->prepare($sql);
    if (!$st) {
        return ['debit' => 0.0, 'credit' => 0.0, 'count' => 0];
    }
    $st->bind_param('ss', $start, $end);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
    return [
        'debit' => (float) ($row['td'] ?? 0),
        'credit' => (float) ($row['tc'] ?? 0),
        'count' => (int) ($row['n'] ?? 0),
    ];
}

function control_fr_fetch_chart_accounts(mysqli $ctrl, string $chartWhere): array
{
    $sql = 'SELECT ca.id, ca.account_code, ca.account_name, ca.account_type, ca.balance, ca.country_id, c.name AS country_name
            FROM control_chart_accounts ca
            LEFT JOIN control_countries c ON c.id = ca.country_id
            WHERE ' . $chartWhere . ' ORDER BY ca.account_type, ca.account_code, ca.id';
    $rows = [];
    $r = $ctrl->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function control_fr_filter_search_rows(array $rows, string $search, callable $textFn): array
{
    if ($search === '') {
        return $rows;
    }
    return array_values(array_filter($rows, function ($row) use ($search, $textFn) {
        return mb_strpos(mb_strtolower($textFn($row)), $search) !== false;
    }));
}

function control_fr_trial_balance(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    string $chartWhere,
    string $jeCountry,
    bool $hasJe,
    bool $hasCa
): array {
    if (!$hasCa) {
        return control_fr_empty_payload($reportId, 'As of: ' . $asOf, [], ['Code', 'Account Name', 'Type', 'Balance (' . $currency . ')'], $currency);
    }
    $accounts = control_fr_fetch_chart_accounts($ctrl, $chartWhere);
    $accounts = control_fr_filter_search_rows($accounts, $search, function ($r) {
        return ($r['account_code'] ?? '') . ' ' . ($r['account_name'] ?? '') . ' ' . ($r['account_type'] ?? '');
    });
    $jt = $hasJe ? control_fr_journal_totals_in_range($ctrl, $jeCountry, $start, $end) : ['debit' => 0.0, 'credit' => 0.0, 'count' => 0];
    $sumBal = 0.0;
    foreach ($accounts as $a) {
        $sumBal += (float) ($a['balance'] ?? 0);
    }
    $cards = [
        ['val' => (string) count($accounts), 'lbl' => 'Total Accounts', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($jt['debit'], 2), 'lbl' => 'Period Debit (Journals)', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($jt['credit'], 2), 'lbl' => 'Period Credit (Journals)', 'card_class' => 'cp-acc-card-blue'],
    ];
    $rows = [];
    foreach (array_slice($accounts, 0, $limit) as $r) {
        $rows[] = ['cells' => [
            (string) ($r['account_code'] ?? '-'),
            (string) ($r['account_name'] ?? '-'),
            (string) ($r['account_type'] ?? '-'),
            number_format((float) ($r['balance'] ?? 0), 2),
        ]];
    }
    $footer = [[
        ['text' => 'TOTALS (balances)', 'colspan' => 3],
        ['text' => number_format($sumBal, 2)],
    ]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end . ' · As of balances: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Code', 'Account Name', 'Type', 'Balance (' . $currency . ')'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_income_statement(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $currency,
    string $txCountry,
    bool $hasTx
): array {
    $rev = $exp = 0.0;
    if ($hasTx) {
        $s = control_fr_sum_tx_by_range($ctrl, $txCountry, $start, $end);
        $rev = $s['revenue'];
        $exp = $s['expense'];
    }
    $net = $rev - $exp;
    $cards = [
        ['val' => $currency . ' ' . number_format($rev, 2), 'lbl' => 'Revenue', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($exp, 2), 'lbl' => 'Expenses', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($net, 2), 'lbl' => 'Net Profit', 'card_class' => 'cp-acc-card-blue'],
    ];
    $rows = [
        ['cells' => ['Total Revenue', number_format($rev, 2)]],
        ['cells' => ['Total Expenses', number_format($exp, 2)]],
        ['cells' => ['Net Profit', number_format($net, 2)]],
    ];
    $footer = [[['text' => 'TOTALS', 'colspan' => 1], ['text' => number_format($net, 2)]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Item', 'Amount (' . $currency . ')'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_balance_sheet(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    string $chartWhere,
    bool $hasCa
): array {
    if (!$hasCa) {
        return control_fr_empty_payload($reportId, 'As of: ' . $asOf, [], ['Type', 'Account', 'Balance (' . $currency . ')'], $currency);
    }
    $accounts = control_fr_fetch_chart_accounts($ctrl, $chartWhere);
    $accounts = control_fr_filter_search_rows($accounts, $search, function ($r) {
        return ($r['account_type'] ?? '') . ' ' . ($r['account_name'] ?? '');
    });
    $asset = $liab = $eq = 0.0;
    foreach ($accounts as $a) {
        $b = (float) ($a['balance'] ?? 0);
        $t = strtolower((string) ($a['account_type'] ?? ''));
        if ($t === 'asset') {
            $asset += $b;
        } elseif ($t === 'liability') {
            $liab += $b;
        } elseif ($t === 'equity') {
            $eq += $b;
        }
    }
    $cards = [
        ['val' => $currency . ' ' . number_format($asset, 2), 'lbl' => 'Total Assets', 'card_class' => 'cp-acc-card-purple'],
        ['val' => (string) count($accounts), 'lbl' => 'Accounts', 'card_class' => 'cp-acc-card-amber'],
    ];
    $rows = [];
    foreach (array_slice($accounts, 0, $limit) as $r) {
        $rows[] = ['cells' => [
            (string) ($r['account_type'] ?? '-'),
            (string) ($r['account_name'] ?? '-'),
            number_format((float) ($r['balance'] ?? 0), 2),
        ]];
    }
    $sum = array_sum(array_column($accounts, 'balance'));
    $footer = [[['text' => 'TOTALS', 'colspan' => 2], ['text' => number_format((float) $sum, 2)]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Type', 'Account', 'Balance (' . $currency . ')'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_transactions_book(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $search,
    int $limit,
    string $currency,
    string $txCountry,
    bool $hasTx,
    bool $bankLabel
): array {
    if (!$hasTx) {
        $cols = $bankLabel
            ? ['Date', 'Description', 'Reference', 'Bank Account', 'Type', 'Debit', 'Credit', 'Balance']
            : ['Date', 'Description', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'];
        return control_fr_empty_payload($reportId, 'Period: ' . $start . ' to ' . $end, [], $cols, $currency);
    }
    $sql = 'SELECT t.created_at, t.description, t.reference, t.type, t.amount, t.currency_code
            FROM control_accounting_transactions t
            WHERE DATE(t.created_at) BETWEEN ? AND ? AND ' . $txCountry . '
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT ' . (int) $limit;
    $st = $ctrl->prepare($sql);
    if (!$st) {
        return ['success' => false, 'message' => 'Query failed'];
    }
    $st->bind_param('ss', $start, $end);
    $st->execute();
    $res = $st->get_result();
    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    $st->close();
    $list = control_fr_filter_search_rows($list, $search, function ($r) {
        return ($r['description'] ?? '') . ' ' . ($r['reference'] ?? '') . ' ' . ($r['type'] ?? '');
    });
    $totD = $totC = 0.0;
    $bal = 0.0;
    $rows = [];
    foreach ($list as $trow) {
        $amt = (float) ($trow['amount'] ?? 0);
        $typ = strtolower((string) ($trow['type'] ?? ''));
        if (in_array($typ, control_tx_revenue_types(), true)) {
            $deb = $amt;
            $cre = 0.0;
            $bal += $amt;
        } elseif (in_array($typ, control_tx_expense_types(), true)) {
            $deb = 0.0;
            $cre = $amt;
            $bal -= $amt;
        } else {
            // Fallback for unknown legacy types.
            $deb = $amt >= 0 ? $amt : 0.0;
            $cre = $amt < 0 ? abs($amt) : 0.0;
            $bal += $amt;
        }
        $totD += $deb;
        $totC += $cre;
        $d = substr((string) ($trow['created_at'] ?? ''), 0, 10);
        if ($bankLabel) {
            $rows[] = ['cells' => [
                $d,
                mb_substr((string) ($trow['description'] ?? '-'), 0, 40),
                $trow['reference'] ?? '—',
                '—',
                (string) ($trow['type'] ?? '-'),
                number_format($deb, 2),
                number_format($cre, 2),
                number_format($bal, 2),
            ]];
        } else {
            $rows[] = ['cells' => [
                $d,
                mb_substr((string) ($trow['description'] ?? '-'), 0, 40),
                $trow['reference'] ?? '—',
                (string) ($trow['type'] ?? '-'),
                number_format($deb, 2),
                number_format($cre, 2),
                number_format($bal, 2),
            ]];
        }
    }
    $n = count($list);
    $cards = [
        ['val' => (string) $n, 'lbl' => 'Transactions', 'card_class' => 'cp-acc-card-blue'],
        ['val' => $currency . ' ' . number_format($totD, 2), 'lbl' => 'Total Debit', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($totC, 2), 'lbl' => 'Total Credit', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($bal, 2), 'lbl' => 'Net', 'card_class' => 'cp-acc-card-purple'],
    ];
    $cols = $bankLabel
        ? ['Date', 'Description', 'Reference', 'Bank Account', 'Type', 'Debit', 'Credit', 'Balance']
        : ['Date', 'Description', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'];
    $footer = [[
        ['text' => 'TOTALS', 'colspan' => $bankLabel ? 5 : 4],
        ['text' => number_format($totD, 2)],
        ['text' => number_format($totC, 2)],
        ['text' => $currency . ' ' . number_format($bal, 2)],
    ]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => ['columns' => $cols, 'rows' => $rows, 'footer_rows' => $footer],
    ];
}

function control_fr_enrich_journal_rows(mysqli $ctrl, array $entries): array
{
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
    $ids = array_values(array_unique(array_filter(array_map(function ($e) {
        return (int) ($e['id'] ?? 0);
    }, $entries))));
    if (empty($ids)) {
        return $entries;
    }
    $idList = implode(',', $ids);
    $res = $ctrl->query("SELECT * FROM control_journal_entry_lines WHERE journal_entry_id IN ($idList) ORDER BY journal_entry_id ASC, id ASC");
    $linesByJe = [];
    $accountIds = [];
    if ($res) {
        while ($ln = $res->fetch_assoc()) {
            $jid = (int) $ln['journal_entry_id'];
            $linesByJe[$jid][] = $ln;
            if ((int) ($ln['account_id'] ?? 0) > 0) {
                $accountIds[(int) $ln['account_id']] = true;
            }
        }
    }
    $chartById = [];
    if (!empty($accountIds)) {
        $alist = implode(',', array_map('intval', array_keys($accountIds)));
        $cr = $ctrl->query("SELECT id, account_code, account_name FROM control_chart_accounts WHERE id IN ($alist)");
        if ($cr) {
            while ($c = $cr->fetch_assoc()) {
                $chartById[(int) $c['id']] = $c;
            }
        }
    }
    foreach ($entries as &$e) {
        $jid = (int) ($e['id'] ?? 0);
        $debitLabels = [];
        $creditLabels = [];
        if ($jid && !empty($linesByJe[$jid])) {
            foreach ($linesByJe[$jid] as $ln) {
                $lab = control_fr_line_label($ln, $chartById);
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

function control_fr_line_label(array $line, array $chartById): string
{
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
        return $an !== '' ? $an : ($ac !== '' ? $ac : '#' . $aid);
    }
    if ($aid > 0) {
        return '#' . $aid;
    }
    $desc = trim((string) ($line['description'] ?? ''));
    return $desc !== '' ? (mb_strlen($desc) > 80 ? mb_substr($desc, 0, 77) . '…' : $desc) : '';
}

function control_fr_general_ledger(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    int $accountId,
    string $search,
    int $limit,
    string $currency,
    string $jeCountry,
    string $chartWhere,
    bool $hasJe,
    bool $hasJl,
    bool $hasCa
): array {
    if (!$hasJe) {
        return control_fr_empty_payload($reportId, 'Period: ' . $start . ' to ' . $end, [], ['Date', 'Description', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'], $currency);
    }
    $posted = control_fr_journal_posted_only_sql();
    if ($accountId > 0 && $hasJl) {
        $sql = 'SELECT DISTINCT j.* FROM control_journal_entries j
                INNER JOIN control_journal_entry_lines jl ON jl.journal_entry_id = j.id AND jl.account_id = ?
                WHERE j.entry_date >= ? AND j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted . '
                ORDER BY j.entry_date DESC, j.id DESC LIMIT ' . (int) $limit;
        $st = $ctrl->prepare($sql);
        if (!$st) {
            return ['success' => false, 'message' => 'Query failed'];
        }
        $st->bind_param('iss', $accountId, $start, $end);
    } else {
        $sql = 'SELECT j.* FROM control_journal_entries j WHERE j.entry_date >= ? AND j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted . ' ORDER BY j.entry_date DESC, j.id DESC LIMIT ' . (int) $limit;
        $st = $ctrl->prepare($sql);
        if (!$st) {
            return ['success' => false, 'message' => 'Query failed'];
        }
        $st->bind_param('ss', $start, $end);
    }
    $st->execute();
    $res = $st->get_result();
    $entries = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $entries[] = $row;
        }
    }
    $st->close();
    if ($hasJl) {
        $entries = control_fr_enrich_journal_rows($ctrl, $entries);
    }
    $entries = control_fr_filter_search_rows($entries, $search, function ($r) {
        return (string) ($r['entry_date'] ?? '') . ' ' . ($r['description'] ?? '') . ' ' . ($r['reference'] ?? '') . ' ' . ($r['debit_account_label'] ?? '') . ' ' . ($r['credit_account_label'] ?? '');
    });
    $totD = $totC = 0.0;
    $rows = [];
    foreach ($entries as $j) {
        $d = (float) ($j['total_debit'] ?? 0);
        $c = (float) ($j['total_credit'] ?? 0);
        $totD += $d;
        $totC += $c;
        $desc = (string) ($j['description'] ?? '-');
        if (mb_strlen($desc) > 30) {
            $desc = mb_substr($desc, 0, 27) . '…';
        }
        $rows[] = ['cells' => [
            (string) ($j['entry_date'] ?? '-'),
            $desc,
            (string) ($j['reference'] ?? ''),
            'Journal',
            number_format($d, 2),
            number_format($c, 2),
            '—',
        ]];
    }
    $nAcc = 0;
    if ($hasCa) {
        $cr = $ctrl->query('SELECT COUNT(*) AS n FROM control_chart_accounts ca WHERE ' . $chartWhere);
        if ($cr && $crow = $cr->fetch_assoc()) {
            $nAcc = (int) ($crow['n'] ?? 0);
        }
    }
    $nJe = count($entries);
    $cards = [
        ['val' => (string) $nAcc, 'lbl' => 'Total Accounts', 'card_class' => 'cp-acc-card-purple'],
        ['val' => (string) min($nJe, $nAcc > 0 ? $nJe : 0), 'lbl' => 'With Transactions', 'card_class' => 'cp-acc-card-green'],
        ['val' => (string) max(0, $nAcc - $nJe), 'lbl' => 'No Transactions', 'card_class' => 'cp-acc-card-amber'],
        ['val' => (string) $nJe, 'lbl' => 'Total Transactions', 'card_class' => 'cp-acc-card-blue'],
        ['val' => $currency . ' ' . number_format($totD, 2), 'lbl' => 'Total Debit', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($totC, 2), 'lbl' => 'Total Credit', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($totD - $totC, 2), 'lbl' => 'Balance', 'card_class' => 'cp-acc-card-purple'],
    ];
    $footer = [[
        ['text' => 'TOTALS', 'colspan' => 4],
        ['text' => number_format($totD, 2)],
        ['text' => number_format($totC, 2)],
        ['text' => number_format($totD - $totC, 2)],
    ]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Date', 'Description', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_account_statement(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    int $accountId,
    string $search,
    int $limit,
    string $currency,
    string $jeCountry,
    bool $hasJe,
    bool $hasJl,
    bool $hasCa
): array {
    $cols = ['Date', 'Description', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'];
    if ($accountId <= 0 || !$hasJe || !$hasJl) {
        $cards = [
            ['val' => '0', 'lbl' => 'Transactions', 'card_class' => 'cp-acc-card-blue'],
            ['val' => $currency . ' 0.00', 'lbl' => 'Total Debit', 'card_class' => 'cp-acc-card-red'],
            ['val' => $currency . ' 0.00', 'lbl' => 'Total Credit', 'card_class' => 'cp-acc-card-green'],
            ['val' => $currency . ' 0.00', 'lbl' => 'Balance', 'card_class' => 'cp-acc-card-purple'],
        ];
        return [
            'success' => true,
            'report_id' => $reportId,
            'meta' => ['currency' => $currency],
            'period_text' => 'Period: ' . $start . ' to ' . $end,
            'summary_cards' => $cards,
            'table' => [
                'columns' => $cols,
                'rows' => [],
                'empty_message' => 'Select an account in the filter and apply to load lines.',
                'footer_rows' => [],
            ],
        ];
    }
    $posted = control_fr_journal_posted_only_sql();
    $sql = 'SELECT j.entry_date, j.description, j.reference, l.debit, l.credit, l.description AS line_desc
            FROM control_journal_entry_lines l
            INNER JOIN control_journal_entries j ON j.id = l.journal_entry_id
            WHERE l.account_id = ? AND j.entry_date >= ? AND j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted . '
            ORDER BY j.entry_date ASC, l.id ASC
            LIMIT ' . (int) $limit;
    $st = $ctrl->prepare($sql);
    if (!$st) {
        return ['success' => false, 'message' => 'Query failed'];
    }
    $st->bind_param('iss', $accountId, $start, $end);
    $st->execute();
    $res = $st->get_result();
    $lines = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lines[] = $row;
        }
    }
    $st->close();
    $lines = control_fr_filter_search_rows($lines, $search, function ($r) {
        return ($r['description'] ?? '') . ' ' . ($r['line_desc'] ?? '') . ' ' . ($r['reference'] ?? '');
    });
    $run = 0.0;
    $totD = $totC = 0.0;
    $rows = [];
    foreach ($lines as $ln) {
        $d = (float) ($ln['debit'] ?? 0);
        $c = (float) ($ln['credit'] ?? 0);
        $totD += $d;
        $totC += $c;
        $run += $d - $c;
        $desc = trim((string) ($ln['line_desc'] ?? ''));
        if ($desc === '') {
            $desc = (string) ($ln['description'] ?? '-');
        }
        if (mb_strlen($desc) > 40) {
            $desc = mb_substr($desc, 0, 37) . '…';
        }
        $rows[] = ['cells' => [
            (string) ($ln['entry_date'] ?? '-'),
            $desc,
            (string) ($ln['reference'] ?? '—'),
            'Journal',
            number_format($d, 2),
            number_format($c, 2),
            number_format($run, 2),
        ]];
    }
    $cards = [
        ['val' => (string) count($lines), 'lbl' => 'Transactions', 'card_class' => 'cp-acc-card-blue'],
        ['val' => $currency . ' ' . number_format($totD, 2), 'lbl' => 'Total Debit', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($totC, 2), 'lbl' => 'Total Credit', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($run, 2), 'lbl' => 'Balance', 'card_class' => 'cp-acc-card-purple'],
    ];
    $footer = [[
        ['text' => 'TOTALS', 'colspan' => 4],
        ['text' => number_format($totD, 2)],
        ['text' => number_format($totC, 2)],
        ['text' => number_format($run, 2)],
    ]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => ['columns' => $cols, 'rows' => $rows, 'footer_rows' => $footer],
    ];
}

function control_fr_chart_list(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    string $chartWhere,
    bool $hasCa
): array {
    if (!$hasCa) {
        return control_fr_empty_payload($reportId, 'As of: ' . $asOf, [], ['Account Code', 'Account Name', 'Balance'], $currency);
    }
    $accounts = control_fr_fetch_chart_accounts($ctrl, $chartWhere);
    $accounts = control_fr_filter_search_rows($accounts, $search, function ($r) {
        return ($r['account_code'] ?? '') . ' ' . ($r['account_name'] ?? '');
    });
    $active = count($accounts);
    $cards = [
        ['val' => (string) $active, 'lbl' => 'Total Accounts', 'card_class' => 'cp-acc-card-purple'],
        ['val' => (string) $active, 'lbl' => 'Active', 'card_class' => 'cp-acc-card-green'],
        ['val' => '0', 'lbl' => 'Inactive', 'card_class' => 'cp-acc-card-amber'],
    ];
    $rows = [];
    $lastType = '';
    foreach (array_slice($accounts, 0, $limit) as $r) {
        $type = (string) ($r['account_type'] ?? '');
        if ($type !== $lastType) {
            $lastType = $type;
            $rows[] = ['cells' => [strtoupper($type ?: 'Other')], 'row_class' => 'bg-dark', 'section_header' => true, 'colspan' => 3];
        }
        $rows[] = ['cells' => [
            (string) ($r['account_code'] ?? '-'),
            (string) ($r['account_name'] ?? '-'),
            number_format((float) ($r['balance'] ?? 0), 2) . ' ' . $currency,
        ]];
    }
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Account Code', 'Account Name', 'Balance'],
            'rows' => $rows,
            'footer_rows' => [],
        ],
    ];
}

function control_fr_value_added(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $currency,
    string $txCountry,
    bool $hasTx
): array {
    $rev = $exp = 0.0;
    if ($hasTx) {
        $s = control_fr_sum_tx_by_range($ctrl, $txCountry, $start, $end);
        $rev = $s['revenue'];
        $exp = $s['expense'];
    }
    $va = $rev - $exp;
    $pct = $rev > 0 ? ($va / $rev) * 100 : 0.0;
    $cards = [
        ['val' => $currency . ' ' . number_format($rev, 2), 'lbl' => 'Revenue', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($exp, 2), 'lbl' => 'COGS', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($va, 2), 'lbl' => 'Value Added', 'card_class' => 'cp-acc-card-blue'],
    ];
    $rows = [
        ['cells' => ['5000', 'Operating Expenses', 'Cost of Goods Sold', $currency . ' ' . number_format($exp, 2)]],
    ];
    $footer = [
        [['text' => 'TOTALS', 'colspan' => 3], ['text' => '-' . $currency . ' ' . number_format($exp, 2)]],
        [['text' => 'VALUE ADDED PERCENTAGE:', 'colspan' => 3], ['text' => number_format($pct, 2) . '%']],
    ];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Account Code', 'Account Name', 'Type', 'Amount'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_fixed_assets(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    string $chartWhere,
    bool $hasCa
): array {
    if (!$hasCa) {
        return control_fr_empty_payload($reportId, 'As of: ' . $asOf, [], ['Account Code', 'Account Name', 'Balance', 'Description'], $currency);
    }
    $accounts = control_fr_fetch_chart_accounts($ctrl, $chartWhere);
    $assets = array_values(array_filter($accounts, function ($a) {
        return strtolower((string) ($a['account_type'] ?? '')) === 'asset';
    }));
    $assets = control_fr_filter_search_rows($assets, $search, function ($r) {
        return ($r['account_code'] ?? '') . ' ' . ($r['account_name'] ?? '');
    });
    $sum = array_sum(array_column($assets, 'balance'));
    $cards = [
        ['val' => (string) count($assets), 'lbl' => 'Assets', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($sum, 2), 'lbl' => 'Total Value', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' 0.00', 'lbl' => 'Depreciation', 'card_class' => 'cp-acc-card-amber'],
        ['val' => $currency . ' ' . number_format($sum, 2), 'lbl' => 'Net Value', 'card_class' => 'cp-acc-card-blue'],
    ];
    $rows = [];
    foreach (array_slice($assets, 0, $limit) as $r) {
        $rows[] = ['cells' => [
            (string) ($r['account_code'] ?? '-'),
            (string) ($r['account_name'] ?? '-'),
            $currency . ' ' . number_format((float) ($r['balance'] ?? 0), 2),
            '—',
        ]];
    }
    $footer = [[['text' => 'TOTALS', 'colspan' => 2], ['text' => $currency . ' ' . number_format($sum, 2)], ['text' => count($assets) . ' assets']]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Account Code', 'Account Name', 'Balance', 'Description'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_entries_by_year(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $currency,
    string $jeCountry,
    bool $hasJe
): array {
    if (!$hasJe) {
        return control_fr_empty_payload($reportId, 'Period: ' . $start . ' to ' . $end, [], ['Year', '%', 'Entry Count', 'Total Amount'], $currency);
    }
    $posted = control_fr_journal_posted_only_sql();
    $sql = 'SELECT SUBSTRING(j.entry_date,1,4) AS y, COUNT(*) AS cnt, SUM(j.total_debit) AS td
            FROM control_journal_entries j
            WHERE j.entry_date >= ? AND j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted . '
            GROUP BY y ORDER BY y';
    $st = $ctrl->prepare($sql);
    if (!$st) {
        return ['success' => false, 'message' => 'Query failed'];
    }
    $st->bind_param('ss', $start, $end);
    $st->execute();
    $res = $st->get_result();
    $byYear = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $byYear[] = $row;
        }
    }
    $st->close();
    $totE = 0;
    $totA = 0.0;
    $rows = [];
    foreach ($byYear as $row) {
        $cnt = (int) ($row['cnt'] ?? 0);
        $amt = (float) ($row['td'] ?? 0);
        $totE += $cnt;
        $totA += $amt;
        $rows[] = ['cells' => [(string) ($row['y'] ?? ''), '—', (string) $cnt, $currency . ' ' . number_format($amt, 2)]];
    }
    $cards = [
        ['val' => (string) count($byYear), 'lbl' => 'Years', 'card_class' => 'cp-acc-card-blue'],
        ['val' => (string) $totE, 'lbl' => 'Total Entries', 'card_class' => 'cp-acc-card-purple'],
    ];
    $footer = [[['text' => 'TOTALS', 'colspan' => 2], ['text' => (string) $totE], ['text' => $currency . ' ' . number_format($totA, 2)]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Year', '%', 'Entry Count', 'Total Amount'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_module_country_where(?array $allowedCountryIds, int $countryId, string $alias = ''): string
{
    $col = $alias === '' ? 'country_id' : $alias . '.country_id';
    if ($allowedCountryIds === []) {
        return '1=0';
    }
    if ($countryId > 0) {
        if ($allowedCountryIds !== null && !in_array($countryId, array_map('intval', $allowedCountryIds), true)) {
            return '1=0';
        }
        return $col . ' = ' . $countryId;
    }
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        return $col . ' IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
    }
    return '1=1';
}

function control_fr_invoices_aging(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    ?array $allowedCountryIds,
    int $countryId
): array {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_electronic_invoices'");
    if (!$chk || $chk->num_rows === 0) {
        return control_fr_empty_payload($reportId, 'As of: ' . $asOf, [], ['Invoice #', 'Customer', 'Invoice Date', 'Due Date', 'Total Amount', 'Paid', 'Balance', 'Days Overdue'], $currency);
    }
    $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 'i');
    $sql = "SELECT i.*, c.name AS country_name FROM control_electronic_invoices i
            LEFT JOIN control_countries c ON c.id = i.country_id
            WHERE $wc AND (i.invoice_date IS NULL OR i.invoice_date <= ?)
            ORDER BY i.id DESC LIMIT " . (int) $limit;
    $st = $ctrl->prepare($sql);
    if (!$st) {
        return ['success' => false, 'message' => 'Query failed'];
    }
    $st->bind_param('s', $asOf);
    $st->execute();
    $res = $st->get_result();
    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    $st->close();
    $list = control_fr_filter_search_rows($list, $search, function ($r) {
        return ($r['invoice_number'] ?? '') . ' ' . ($r['country_name'] ?? '');
    });
    $outTotal = 0.0;
    $rows = [];
    foreach ($list as $r) {
        $amt = (float) ($r['amount'] ?? 0);
        $outTotal += $amt;
        $rows[] = ['cells' => [
            (string) ($r['invoice_number'] ?? '-'),
            (string) ($r['country_name'] ?? '-'),
            (string) ($r['invoice_date'] ?? '-'),
            '—',
            number_format($amt, 2) . ' ' . $currency,
            '0.00',
            number_format($amt, 2),
            '—',
        ]];
    }
    $cards = [
        ['val' => (string) count($list), 'lbl' => $reportId === 'customer-debits' ? 'Customers' : 'Total Receivables', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($outTotal, 2), 'lbl' => 'Outstanding', 'card_class' => 'cp-acc-card-red'],
    ];
    $footer = [[['text' => 'TOTAL OUTSTANDING:', 'colspan' => 6], ['text' => $currency . ' ' . number_format($outTotal, 2), 'colspan' => 2]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Invoice #', 'Customer', 'Invoice Date', 'Due Date', 'Total Amount', 'Paid', 'Balance', 'Days Overdue'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_payables_aging(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $search,
    int $limit,
    string $currency,
    ?array $allowedCountryIds,
    int $countryId,
    mysqli $conn
): array {
    $list = [];
    foreach (['control_disbursement_vouchers', 'control_expenses'] as $table) {
        $chk = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$chk || $chk->num_rows === 0) {
            continue;
        }
        $dateCol = $table === 'control_expenses' ? 'expense_date' : 'voucher_date';
        $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 't');
        $sql = "SELECT t.*, c.name AS country_name, '$table' AS src FROM $table t
                LEFT JOIN control_countries c ON c.id = t.country_id
                WHERE $wc AND (t.$dateCol IS NULL OR t.$dateCol <= ?) ORDER BY t.id DESC LIMIT " . (int) $limit;
        $st = $conn->prepare($sql);
        if ($st) {
            $st->bind_param('s', $asOf);
            $st->execute();
            $r = $st->get_result();
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $list[] = $row;
                }
            }
            $st->close();
        }
    }
    $list = control_fr_filter_search_rows($list, $search, function ($r) {
        return ($r['voucher_number'] ?? '') . ' ' . ($r['payee'] ?? '') . ' ' . ($r['description'] ?? '');
    });
    $outTotal = 0.0;
    $rows = [];
    foreach (array_slice($list, 0, $limit) as $r) {
        $amt = (float) ($r['amount'] ?? 0);
        $outTotal += $amt;
        $d = (string) ($r['voucher_date'] ?? $r['expense_date'] ?? '-');
        $rows[] = ['cells' => [
            (string) ($r['voucher_number'] ?? '-'),
            (string) ($r['payee'] ?? '-'),
            $d,
            '—',
            number_format($amt, 2) . ' ' . $currency,
            '0.00',
            number_format($amt, 2),
            '—',
        ]];
    }
    $cards = [
        ['val' => (string) count($list), 'lbl' => 'Total Payables', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($outTotal, 2), 'lbl' => 'Outstanding', 'card_class' => 'cp-acc-card-red'],
    ];
    $footer = [[['text' => 'TOTAL OUTSTANDING:', 'colspan' => 6], ['text' => $currency . ' ' . number_format($outTotal, 2), 'colspan' => 2]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Voucher #', 'Payee', 'Date', 'Due Date', 'Total Amount', 'Paid', 'Balance', 'Days Overdue'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_statistical(
    mysqli $ctrl,
    string $reportId,
    string $asOf,
    string $currency,
    string $chartWhere,
    string $jeCountry,
    bool $hasCa,
    bool $hasJe,
    ?array $allowedCountryIds,
    int $countryId
): array {
    $nAcc = $nJe = 0;
    if ($hasCa) {
        $r = $ctrl->query('SELECT COUNT(*) AS n FROM control_chart_accounts ca WHERE ' . $chartWhere);
        if ($r && $row = $r->fetch_assoc()) {
            $nAcc = (int) $row['n'];
        }
    }
    if ($hasJe) {
        $posted = control_fr_journal_posted_only_sql();
        $st = $ctrl->prepare('SELECT COUNT(*) AS n FROM control_journal_entries j WHERE j.entry_date <= ? AND ' . $jeCountry . ' AND ' . $posted);
        if ($st) {
            $st->bind_param('s', $asOf);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $nJe = (int) ($row['n'] ?? 0);
            $st->close();
        }
    }
    $invC = $payC = 0;
    $chkI = $ctrl->query("SHOW TABLES LIKE 'control_electronic_invoices'");
    if ($chkI && $chkI->num_rows > 0) {
        $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 'i');
        $r = $ctrl->query("SELECT COUNT(*) AS n FROM control_electronic_invoices i WHERE $wc AND (i.invoice_date IS NULL OR i.invoice_date <= '" . $ctrl->real_escape_string($asOf) . "')");
        if ($r && $row = $r->fetch_assoc()) {
            $invC = (int) $row['n'];
        }
    }
    $chkV = $ctrl->query("SHOW TABLES LIKE 'control_disbursement_vouchers'");
    if ($chkV && $chkV->num_rows > 0) {
        $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 't');
        $r = $ctrl->query("SELECT COUNT(*) AS n FROM control_disbursement_vouchers t WHERE $wc");
        if ($r && $row = $r->fetch_assoc()) {
            $payC += (int) $row['n'];
        }
    }
    $chkE = $ctrl->query("SHOW TABLES LIKE 'control_expenses'");
    if ($chkE && $chkE->num_rows > 0) {
        $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 't');
        $r = $ctrl->query("SELECT COUNT(*) AS n FROM control_expenses t WHERE $wc");
        if ($r && $row = $r->fetch_assoc()) {
            $payC += (int) $row['n'];
        }
    }
    $cards = [
        ['val' => (string) $nAcc, 'lbl' => 'Accounts', 'card_class' => 'cp-acc-card-purple'],
        ['val' => (string) $nJe, 'lbl' => 'Transactions', 'card_class' => 'cp-acc-card-blue'],
        ['val' => (string) $invC, 'lbl' => 'Receivables', 'card_class' => 'cp-acc-card-green'],
        ['val' => (string) $payC, 'lbl' => 'Payables', 'card_class' => 'cp-acc-card-amber'],
    ];
    $rows = [
        ['cells' => ['Accounts', 'Total', (string) $nAcc]],
        ['cells' => ['Accounts', 'Active', (string) $nAcc]],
        ['cells' => ['Accounts', 'Inactive', '0']],
        ['cells' => ['Transactions', 'Total', (string) $nJe]],
        ['cells' => ['Receivables', 'Count', (string) $invC]],
        ['cells' => ['Payables', 'Count', (string) $payC]],
    ];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'As of: ' . $asOf,
        'summary_cards' => $cards,
        'table' => ['columns' => ['Category', 'Metric', 'Value'], 'rows' => $rows, 'footer_rows' => []],
    ];
}

function control_fr_equity_changes(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $search,
    int $limit,
    string $currency,
    string $chartWhere,
    string $jeCountry,
    bool $hasJe,
    bool $hasJl,
    bool $hasCa
): array {
    if (!$hasCa) {
        return control_fr_empty_payload($reportId, 'Period: ' . $start . ' to ' . $end, [], ['Account Code', 'Account Name', 'Period', 'Change Amount', 'Current Balance'], $currency);
    }
    $accounts = control_fr_fetch_chart_accounts($ctrl, $chartWhere);
    $equity = array_values(array_filter($accounts, function ($a) {
        return strtolower((string) ($a['account_type'] ?? '')) === 'equity';
    }));
    $equity = control_fr_filter_search_rows($equity, $search, function ($r) {
        return ($r['account_code'] ?? '') . ' ' . ($r['account_name'] ?? '');
    });
    $eqTotal = array_sum(array_column($equity, 'balance'));
    $cards = [
        ['val' => $currency . ' 0.00', 'lbl' => 'Opening', 'card_class' => 'cp-acc-card-blue'],
        ['val' => (string) count($equity), 'lbl' => 'Changes', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($eqTotal, 2), 'lbl' => 'Closing', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' 0.00', 'lbl' => 'Net Change', 'card_class' => 'cp-acc-card-green'],
    ];
    $rows = [];
    foreach (array_slice($equity, 0, $limit) as $r) {
        $rows[] = ['cells' => [
            (string) ($r['account_code'] ?? '-'),
            (string) ($r['account_name'] ?? '-'),
            'Current',
            $currency . ' 0.00',
            $currency . ' ' . number_format((float) ($r['balance'] ?? 0), 2),
        ]];
    }
    $footer = [[['text' => 'TOTALS', 'colspan' => 4], ['text' => $currency . ' ' . number_format($eqTotal, 2)]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Account Code', 'Account Name', 'Period', 'Change Amount', 'Current Balance'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_financial_performance(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $currency,
    string $txCountry,
    bool $hasTx,
    string $chartWhere,
    bool $hasCa
): array {
    $rev = $exp = 0.0;
    if ($hasTx) {
        $s = control_fr_sum_tx_by_range($ctrl, $txCountry, $start, $end);
        $rev = $s['revenue'];
        $exp = $s['expense'];
    }
    $net = $rev - $exp;
    $margin = $rev > 0 ? ($net / $rev) * 100 : 0.0;
    $assets = 0.0;
    if ($hasCa) {
        $r = $ctrl->query('SELECT COALESCE(SUM(ca.balance),0) AS s FROM control_chart_accounts ca WHERE ' . $chartWhere);
        if ($r && $row = $r->fetch_assoc()) {
            $assets = (float) ($row['s'] ?? 0);
        }
    }
    $cards = [
        ['val' => $currency . ' ' . number_format($rev, 2), 'lbl' => 'Revenue', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($exp, 2), 'lbl' => 'Expenses', 'card_class' => 'cp-acc-card-red'],
        ['val' => $currency . ' ' . number_format($net, 2), 'lbl' => 'Net Income', 'card_class' => 'cp-acc-card-green'],
        ['val' => number_format($margin, 2) . '%', 'lbl' => 'Profit Margin', 'card_class' => 'cp-acc-card-blue'],
    ];
    $rows = [
        ['cells' => ['Total Revenue', $currency . ' ' . number_format($rev, 2)]],
        ['cells' => ['Total Expenses', $currency . ' ' . number_format($exp, 2)]],
        ['cells' => ['Net Income', $currency . ' ' . number_format($net, 2)]],
        ['cells' => ['Total Assets', $currency . ' ' . number_format($assets, 2)]],
        ['cells' => ['Total Liabilities', $currency . ' 0.00']],
    ];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => ['columns' => ['Metric', 'Value'], 'rows' => $rows, 'footer_rows' => []],
    ];
}

function control_fr_comparative(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $currency,
    string $txCountry,
    bool $hasTx
): array {
    $ts = strtotime($start);
    $te = strtotime($end);
    $len = max(1, (int) (($te - $ts) / 86400) + 1);
    $prevEnd = date('Y-m-d', strtotime($start . ' -1 day'));
    $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($len - 1) . ' days'));
    $cur = $hasTx ? control_fr_sum_tx_by_range($ctrl, $txCountry, $start, $end) : ['revenue' => 0.0, 'expense' => 0.0];
    $prev = $hasTx ? control_fr_sum_tx_by_range($ctrl, $txCountry, $prevStart, $prevEnd) : ['revenue' => 0.0, 'expense' => 0.0];
    $chg = $cur['revenue'] - $prev['revenue'];
    $pct = $prev['revenue'] > 0 ? ($chg / $prev['revenue']) * 100 : 0.0;
    $cards = [
        ['val' => $currency . ' ' . number_format($cur['revenue'], 2), 'lbl' => 'Current Revenue', 'card_class' => 'cp-acc-card-green'],
        ['val' => $currency . ' ' . number_format($prev['revenue'], 2), 'lbl' => 'Previous Revenue', 'card_class' => 'cp-acc-card-blue'],
        ['val' => $currency . ' ' . number_format($chg, 2), 'lbl' => 'Change', 'card_class' => 'cp-acc-card-green'],
        ['val' => number_format($pct, 2) . '%', 'lbl' => 'Change %', 'card_class' => 'cp-acc-card-purple'],
    ];
    $rows = [
        ['cells' => ['Revenue (current)', $currency . ' ' . number_format($cur['revenue'], 2)]],
        ['cells' => ['Revenue (previous)', $currency . ' ' . number_format($prev['revenue'], 2)]],
        ['cells' => ['Expense (current)', $currency . ' ' . number_format($cur['expense'], 2)]],
        ['cells' => ['Expense (previous)', $currency . ' ' . number_format($prev['expense'], 2)]],
    ];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency, 'prev_period' => $prevStart . ' to ' . $prevEnd],
        'period_text' => 'Current: ' . $start . ' to ' . $end . ' · Previous: ' . $prevStart . ' to ' . $prevEnd,
        'summary_cards' => $cards,
        'table' => ['columns' => ['Metric', 'Value'], 'rows' => $rows, 'footer_rows' => []],
    ];
}

function control_fr_expense_statement(
    mysqli $ctrl,
    string $reportId,
    string $start,
    string $end,
    string $search,
    int $limit,
    string $currency,
    ?array $allowedCountryIds,
    int $countryId,
    mysqli $conn
): array {
    $chk = $conn->query("SHOW TABLES LIKE 'control_expenses'");
    if (!$chk || $chk->num_rows === 0) {
        return control_fr_empty_payload($reportId, 'Period: ' . $start . ' to ' . $end, [], ['Voucher #', 'Description', 'Date', 'Amount', 'Qty'], $currency);
    }
    $wc = control_fr_module_country_where($allowedCountryIds, $countryId, 't');
    $sql = "SELECT t.* FROM control_expenses t WHERE $wc AND t.expense_date >= ? AND t.expense_date <= ? ORDER BY t.expense_date DESC, t.id DESC LIMIT " . (int) $limit;
    $st = $conn->prepare($sql);
    if (!$st) {
        return ['success' => false, 'message' => 'Query failed'];
    }
    $st->bind_param('ss', $start, $end);
    $st->execute();
    $res = $st->get_result();
    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    $st->close();
    $list = control_fr_filter_search_rows($list, $search, function ($r) {
        return ($r['description'] ?? '') . ' ' . ($r['voucher_number'] ?? '');
    });
    $sum = 0.0;
    $rows = [];
    foreach ($list as $r) {
        $amt = (float) ($r['amount'] ?? 0);
        $sum += $amt;
        $rows[] = ['cells' => [
            '—',
            mb_substr((string) ($r['description'] ?? '-'), 0, 40),
            (string) ($r['expense_date'] ?? '-'),
            $currency . ' ' . number_format($amt, 2),
            '1',
        ]];
    }
    $cards = [
        ['val' => (string) count($list), 'lbl' => 'Expense Lines', 'card_class' => 'cp-acc-card-purple'],
        ['val' => $currency . ' ' . number_format($sum, 2), 'lbl' => 'Total', 'card_class' => 'cp-acc-card-red'],
    ];
    $footer = [[['text' => 'TOTALS', 'colspan' => 3], ['text' => $currency . ' ' . number_format($sum, 2)], ['text' => (string) count($list)]]];
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => 'Period: ' . $start . ' to ' . $end,
        'summary_cards' => $cards,
        'table' => [
            'columns' => ['Voucher #', 'Description', 'Date', 'Amount', 'Qty'],
            'rows' => $rows,
            'footer_rows' => $footer,
        ],
    ];
}

function control_fr_empty_payload(string $reportId, string $periodText, array $cards, array $columns, string $currency): array
{
    return [
        'success' => true,
        'report_id' => $reportId,
        'meta' => ['currency' => $currency],
        'period_text' => $periodText,
        'summary_cards' => $cards,
        'table' => [
            'columns' => $columns,
            'rows' => [],
            'empty_message' => 'No data for this period.',
            'footer_rows' => [],
        ],
    ];
}
