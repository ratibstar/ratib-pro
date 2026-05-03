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
    $out = ['description' => false];
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
        }
    } catch (Throwable $e) {
        error_log('partnerAgencyStmtJelColumnFlags: ' . $e->getMessage());
    }

    return $out;
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
function partnerAgencyStmtBuildForAccount(PDO $conn, int $linkedId, string $start, string $end): array
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

    // Always show at least one table row so the statement does not look "blank" when there are no journals.
    array_unshift($lines, [
        'date' => $start,
        'reference' => '—',
        'entry_number' => '',
        'description' => 'Opening balance (start of selected range)',
        'debit' => 0.0,
        'credit' => 0.0,
        'balance' => round($openingPeriod, 2),
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
    ];
}
