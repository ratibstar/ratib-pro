<?php
/**
 * Shared GL statement builder for partner agencies (staff + partner portal).
 */

declare(strict_types=1);

/**
 * @return array{0: string, 1: string} ISO start and end dates
 */
function partnerAgencyStmtNormalizeDates(?string $startRaw, ?string $endRaw): array
{
    $startRaw = $startRaw !== null ? trim($startRaw) : '';
    $endRaw = $endRaw !== null ? trim($endRaw) : '';
    $start = null;
    $end = null;
    if ($startRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw)) {
        $start = $startRaw;
    }
    if ($endRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw)) {
        $end = $endRaw;
    }
    if ($start === null || $end === null) {
        $end = $end ?? (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $start = $start ?? (new DateTimeImmutable($end))->modify('-11 months')->modify('first day of this month')->format('Y-m-d');
    }
    if ($start > $end) {
        return [$end, $start];
    }

    return [$start, $end];
}

/** @return array<string, bool> */
function partnerAgencyStmtJeColumnFlags(PDO $conn): array
{
    $out = ['is_posted' => false, 'posting_status' => false, 'status' => false, 'entry_number' => false, 'reference_number' => false];
    try {
        $st = $conn->query('SHOW COLUMNS FROM journal_entries');
        if (!$st) {
            return $out;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $f = strtolower((string) ($row['Field'] ?? ''));
            if (isset($out[$f])) {
                $out[$f] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('partnerAgencyStmtJeColumnFlags: ' . $e->getMessage());
    }

    return $out;
}

/** @return array<string, bool> */
function partnerAgencyStmtJelColumnFlags(PDO $conn): array
{
    $out = ['description' => false, 'entity_type' => false, 'entity_id' => false];
    try {
        $st = $conn->query('SHOW COLUMNS FROM journal_entry_lines');
        if (!$st) {
            return $out;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $f = strtolower((string) ($row['Field'] ?? ''));
            if ($f === 'description') {
                $out['description'] = true;
            }
            if ($f === 'entity_type') {
                $out['entity_type'] = true;
            }
            if ($f === 'entity_id') {
                $out['entity_id'] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('partnerAgencyStmtJelColumnFlags: ' . $e->getMessage());
    }

    return $out;
}

/**
 * Deployments for a partner agency with worker display name (same shape as portal needs).
 *
 * @return list<array<string, mixed>>
 */
function partnerAgencyStmtListDeployments(PDO $conn, int $partnerAgencyId): array
{
    if ($partnerAgencyId <= 0) {
        return [];
    }
    try {
        $st = $conn->prepare(
            'SELECT wd.id AS deployment_id, wd.worker_id, wd.status, wd.contract_start, wd.contract_end,
                    wd.country, wd.job_title, wd.salary,
                    COALESCE(NULLIF(TRIM(w.worker_name), \'\'), CONCAT(\'Worker #\', wd.worker_id)) AS worker_name
             FROM worker_deployments wd
             LEFT JOIN workers w ON w.id = wd.worker_id
             WHERE wd.partner_agency_id = ?
             ORDER BY wd.id DESC'
        );
        $st->execute([$partnerAgencyId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        error_log('partnerAgencyStmtListDeployments: ' . $e->getMessage());

        return [];
    }
}

/**
 * Roll up journal lines on the linked account (date range) to each deployment using worker entity tags.
 * Multiple placements for the same worker split that worker's tagged amounts evenly across those rows.
 *
 * @param list<array<string, mixed>> $rawLines Lines as stored before opening-balance row is prepended
 * @return array{0: list<array<string, mixed>>, 1: string}
 */
function partnerAgencyStmtBuildDeploymentActivity(
    PDO $conn,
    int $partnerAgencyId,
    array $rawLines,
    string $normal,
    float $totalDebit,
    float $totalCredit
): array {
    $note = '';
    $deployments = partnerAgencyStmtListDeployments($conn, $partnerAgencyId);
    if ($deployments === []) {
        return [[], ''];
    }

    $jelFlags = partnerAgencyStmtJelColumnFlags($conn);
    if (!$jelFlags['entity_type'] || !$jelFlags['entity_id']) {
        $note = 'Per-worker amounts appear when journal lines include entity type “worker” and the worker id (Accounting → Journal entries).';
        $rows = [];
        foreach ($deployments as $d) {
            $depId = (int) ($d['deployment_id'] ?? 0);
            $wid = (int) ($d['worker_id'] ?? 0);
            $rows[] = [
                'deployment_id' => $depId,
                'worker_id' => $wid,
                'worker_name' => (string) ($d['worker_name'] ?? ''),
                'status' => (string) ($d['status'] ?? ''),
                'contract_start' => $d['contract_start'] ?? null,
                'job_title' => (string) ($d['job_title'] ?? ''),
                'country' => (string) ($d['country'] ?? ''),
                'salary' => $d['salary'] ?? null,
                'period_debit' => 0.0,
                'period_credit' => 0.0,
                'period_net' => 0.0,
            ];
        }

        return [$rows, $note];
    }

    $workerTotals = [];
    foreach ($rawLines as $ln) {
        $et = strtolower(trim((string) ($ln['entity_type'] ?? '')));
        $eid = (int) ($ln['entity_id'] ?? 0);
        if ($et !== 'worker' || $eid <= 0) {
            continue;
        }
        if (!isset($workerTotals[$eid])) {
            $workerTotals[$eid] = ['dr' => 0.0, 'cr' => 0.0];
        }
        $workerTotals[$eid]['dr'] += (float) ($ln['debit'] ?? 0);
        $workerTotals[$eid]['cr'] += (float) ($ln['credit'] ?? 0);
    }

    $countByWorker = [];
    foreach ($deployments as $d) {
        $wid = (int) ($d['worker_id'] ?? 0);
        if ($wid > 0) {
            $countByWorker[$wid] = ($countByWorker[$wid] ?? 0) + 1;
        }
    }

    $rows = [];
    foreach ($deployments as $d) {
        $depId = (int) ($d['deployment_id'] ?? 0);
        $wid = (int) ($d['worker_id'] ?? 0);
        $n = $wid > 0 ? max(1, (int) ($countByWorker[$wid] ?? 1)) : 1;
        $wt = $wid > 0 && isset($workerTotals[$wid]) ? $workerTotals[$wid] : ['dr' => 0.0, 'cr' => 0.0];
        $dr = round($wt['dr'] / $n, 2);
        $cr = round($wt['cr'] / $n, 2);
        $net = strtoupper($normal) === 'CREDIT' ? round($cr - $dr, 2) : round($dr - $cr, 2);
        $rows[] = [
            'deployment_id' => $depId,
            'worker_id' => $wid,
            'worker_name' => (string) ($d['worker_name'] ?? ''),
            'status' => (string) ($d['status'] ?? ''),
            'contract_start' => $d['contract_start'] ?? null,
            'job_title' => (string) ($d['job_title'] ?? ''),
            'country' => (string) ($d['country'] ?? ''),
            'salary' => $d['salary'] ?? null,
            'period_debit' => $dr,
            'period_credit' => $cr,
            'period_net' => $net,
        ];
    }

    $sumRowDr = 0.0;
    $sumRowCr = 0.0;
    foreach ($rows as $r) {
        $sumRowDr += (float) ($r['period_debit'] ?? 0);
        $sumRowCr += (float) ($r['period_credit'] ?? 0);
    }
    $gapDr = round($totalDebit - $sumRowDr, 2);
    $gapCr = round($totalCredit - $sumRowCr, 2);
    if (abs($gapDr) >= 0.01 || abs($gapCr) >= 0.01) {
        $netOther = strtoupper($normal) === 'CREDIT' ? round($gapCr - $gapDr, 2) : round($gapDr - $gapCr, 2);
        $rows[] = [
            'deployment_id' => null,
            'worker_id' => null,
            'worker_name' => 'Other on linked account',
            'status' => '',
            'contract_start' => null,
            'job_title' => '',
            'country' => '',
            'salary' => null,
            'period_debit' => $gapDr,
            'period_credit' => $gapCr,
            'period_net' => $netOther,
            'is_other' => true,
        ];
    }

    return [$rows, $note];
}

function partnerAgencyStmtJePostedSql(array $flags): string
{
    if ($flags['is_posted']) {
        return ' AND COALESCE(je.is_posted, 1) = 1 ';
    }
    if ($flags['posting_status']) {
        return " AND LOWER(TRIM(COALESCE(je.posting_status,''))) IN ('posted','completed','closed') ";
    }
    if ($flags['status']) {
        return " AND LOWER(TRIM(COALESCE(je.status,''))) NOT IN ('draft','void','cancelled','rejected') ";
    }

    return '';
}

/**
 * @return array<string, mixed>
 */
function partnerAgencyStmtBuildForAccount(PDO $conn, int $linkedId, string $start, string $end, ?int $partnerAgencyId = null): array
{
    if ($linkedId <= 0) {
        throw new InvalidArgumentException('Invalid account id');
    }

    $stFa = $conn->prepare(
        'SELECT id, account_code, account_name, COALESCE(normal_balance, \'Debit\') AS normal_balance,
                COALESCE(opening_balance, 0) AS opening_balance
         FROM financial_accounts WHERE id = ? LIMIT 1'
    );
    $stFa->execute([$linkedId]);
    $fa = $stFa->fetch(PDO::FETCH_ASSOC);
    if (!$fa) {
        throw new RuntimeException('Linked financial account not found');
    }

    $normal = strtoupper(trim((string) ($fa['normal_balance'] ?? 'Debit')));
    if ($normal !== 'CREDIT') {
        $normal = 'DEBIT';
    }

    $tableJe = false;
    $tableJel = false;
    try {
        $chk = $conn->query("SHOW TABLES LIKE 'journal_entries'");
        $tableJe = $chk && $chk->fetch();
        $chk2 = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
        $tableJel = $chk2 && $chk2->fetch();
    } catch (Throwable $e) {
        error_log('partnerAgencyStmtBuildForAccount tables: ' . $e->getMessage());
    }

    $lines = [];
    $totalDebit = 0.0;
    $totalCredit = 0.0;
    $priorDebit = 0.0;
    $priorCredit = 0.0;

    if ($tableJe && $tableJel) {
        $jeFlags = partnerAgencyStmtJeColumnFlags($conn);
        $jelFlags = partnerAgencyStmtJelColumnFlags($conn);
        $postedSql = partnerAgencyStmtJePostedSql($jeFlags);
        $refField = $jeFlags['reference_number'] ? 'je.reference_number' : "CONCAT('JE-', je.id)";
        $numField = $jeFlags['entry_number'] ? 'je.entry_number' : "CONCAT('JE-', je.id)";
        $lineDesc = $jelFlags['description'] ? 'jel.description' : "''";

        $entitySel = '';
        if (!empty($jelFlags['entity_type']) && !empty($jelFlags['entity_id'])) {
            $entitySel = ", COALESCE(jel.entity_type,'') AS jel_entity_type, COALESCE(jel.entity_id,0) AS jel_entity_id";
        }

        $baseFrom = " FROM journal_entry_lines jel
            INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
            WHERE jel.account_id = ? {$postedSql} ";

        $sumPrior = 'SELECT COALESCE(SUM(jel.debit_amount),0) AS dr, COALESCE(SUM(jel.credit_amount),0) AS cr' . $baseFrom
            . ' AND je.entry_date < ?';
        $stP = $conn->prepare($sumPrior);
        $stP->execute([$linkedId, $start]);
        $pr = $stP->fetch(PDO::FETCH_ASSOC) ?: ['dr' => 0, 'cr' => 0];
        $priorDebit = (float) ($pr['dr'] ?? 0);
        $priorCredit = (float) ($pr['cr'] ?? 0);

        $sel = "SELECT je.entry_date AS d, {$numField} AS entry_no, {$refField} AS ref_no,
                COALESCE(NULLIF(TRIM({$lineDesc}),''), NULLIF(TRIM(je.description),''), '') AS memo,
                COALESCE(jel.debit_amount,0) AS debit_amount, COALESCE(jel.credit_amount,0) AS credit_amount
                {$entitySel}
                " . $baseFrom . ' AND je.entry_date >= ? AND je.entry_date <= ?
                ORDER BY je.entry_date ASC, je.id ASC, jel.id ASC';
        $stL = $conn->prepare($sel);
        $stL->execute([$linkedId, $start, $end]);
        while ($row = $stL->fetch(PDO::FETCH_ASSOC)) {
            $dr = (float) ($row['debit_amount'] ?? 0);
            $cr = (float) ($row['credit_amount'] ?? 0);
            $totalDebit += $dr;
            $totalCredit += $cr;
            $lines[] = [
                'date' => (string) ($row['d'] ?? ''),
                'reference' => (string) ($row['ref_no'] ?? ''),
                'entry_number' => (string) ($row['entry_no'] ?? ''),
                'description' => (string) ($row['memo'] ?? ''),
                'debit' => $dr,
                'credit' => $cr,
                'entity_type' => isset($row['jel_entity_type']) ? (string) $row['jel_entity_type'] : '',
                'entity_id' => isset($row['jel_entity_id']) ? (int) $row['jel_entity_id'] : 0,
            ];
        }
    }

    $openingBase = (float) ($fa['opening_balance'] ?? 0);
    if ($normal === 'CREDIT') {
        $openingMovement = $priorCredit - $priorDebit;
    } else {
        $openingMovement = $priorDebit - $priorCredit;
    }
    $openingPeriod = $openingBase + $openingMovement;

    $running = $openingPeriod;
    foreach ($lines as &$ln) {
        $dr = (float) ($ln['debit'] ?? 0);
        $cr = (float) ($ln['credit'] ?? 0);
        if ($normal === 'CREDIT') {
            $running += $cr - $dr;
        } else {
            $running += $dr - $cr;
        }
        $ln['balance'] = $running;
    }
    unset($ln);

    $closing = $running;

    $byMonth = [];
    foreach ($lines as $ln) {
        $d = (string) ($ln['date'] ?? '');
        if (strlen($d) < 7) {
            continue;
        }
        $key = substr($d, 0, 7);
        if (!isset($byMonth[$key])) {
            $byMonth[$key] = ['debit' => 0.0, 'credit' => 0.0];
        }
        $byMonth[$key]['debit'] += (float) ($ln['debit'] ?? 0);
        $byMonth[$key]['credit'] += (float) ($ln['credit'] ?? 0);
    }
    ksort($byMonth);
    $chartByMonth = [];
    foreach ($byMonth as $key => $tot) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $key . '-01') ?: new DateTimeImmutable($key . '-01');
        $chartByMonth[] = [
            'key' => $key,
            'label' => $dt->format('M Y'),
            'debit' => round($tot['debit'], 2),
            'credit' => round($tot['credit'], 2),
        ];
    }

    $deploymentActivity = [];
    $deploymentNote = '';
    if ($partnerAgencyId !== null && $partnerAgencyId > 0) {
        list($deploymentActivity, $deploymentNote) = partnerAgencyStmtBuildDeploymentActivity(
            $conn,
            $partnerAgencyId,
            $lines,
            $normal,
            $totalDebit,
            $totalCredit
        );
    }

    // Always show at least one table row so the statement does not look "blank" when there are no journals.
    array_unshift($lines, [
        'date' => $start,
        'reference' => '—',
        'entry_number' => '',
        'description' => 'Opening balance (start of selected range)',
        'debit' => 0.0,
        'credit' => 0.0,
        'balance' => round($openingPeriod, 2),
        'entity_type' => '',
        'entity_id' => 0,
    ]);

    return [
        'account_id' => $linkedId,
        'account_code' => $fa['account_code'] ?? null,
        'account_name' => $fa['account_name'] ?? null,
        'normal_balance' => $normal,
        'start_date' => $start,
        'end_date' => $end,
        'opening_balance' => round($openingPeriod, 2),
        'closing_balance' => round($closing, 2),
        'total_debit' => round($totalDebit, 2),
        'total_credit' => round($totalCredit, 2),
        'lines' => $lines,
        'chart_by_month' => $chartByMonth,
        'deployment_activity' => $deploymentActivity,
        'deployment_activity_note' => $deploymentNote,
    ];
}
