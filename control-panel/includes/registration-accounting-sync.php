<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/registration-accounting-sync.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/registration-accounting-sync.php`.
 */
/**
 * Mirror paid registration requests into accounting tables (receipts, journal draft + approval).
 * Idempotent by matching the generated registration description/amount pair.
 */

if (!function_exists('registrationAccountingResolveCountryId')) {
    function registrationAccountingResolveCountryId($mysqli, array $row)
    {
        $cid = isset($row['country_id']) ? (int) $row['country_id'] : 0;
        if ($cid > 0) {
            return $cid;
        }
        $name = trim((string) ($row['country_name'] ?? ''));
        if ($name === '') {
            return 0;
        }
        $st = $mysqli->prepare('SELECT id FROM control_countries WHERE name = ? AND is_active = 1 LIMIT 1');
        if (!$st) {
            return 0;
        }
        $st->bind_param('s', $name);
        $st->execute();
        $res = $st->get_result();
        if ($res && $r = $res->fetch_assoc()) {
            return (int) $r['id'];
        }

        return 0;
    }
}

if (!function_exists('registrationAccountingResolveAgencyId')) {
    function registrationAccountingResolveAgencyId(array $row)
    {
        $aid = trim((string) ($row['agency_id'] ?? ''));
        if ($aid === '' || !ctype_digit($aid)) {
            return 0;
        }

        return (int) $aid;
    }
}

if (!function_exists('syncPaidRegistrationToAccounting')) {
    function registrationAccountingNextRcNumber(mysqli $mysqli): string
    {
        $maxSeq = 0;
        $res = $mysqli->query("SELECT receipt_number FROM control_receipts WHERE receipt_number IS NOT NULL AND TRIM(receipt_number) <> ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $num = trim((string) ($row['receipt_number'] ?? ''));
                if ($num !== '' && preg_match('/^(?:RC|REG|RECEIPT|RCP)-?(\d+)$/i', $num, $m)) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            }
        }
        return 'RC-' . sprintf('%05d', $maxSeq + 1);
    }

    function registrationAccountingNextGlReference(mysqli $mysqli): string
    {
        if (function_exists('control_next_gl_journal_reference')) {
            return control_next_gl_journal_reference($mysqli);
        }
        $maxSeq = 0;
        $res = $mysqli->query("SELECT reference FROM control_journal_entries WHERE reference IS NOT NULL AND TRIM(reference) <> ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ref = trim((string) ($row['reference'] ?? ''));
                if ($ref !== '' && preg_match('/^GL-(?:\d{4}-)?(\d+)$/i', $ref, $m)) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            }
        }
        return 'GL-' . sprintf('%05d', $maxSeq + 1);
    }

    /**
     * @param mysqli $mysqli
     * @param array  $row Full row from control_registration_requests
     * @return array{ receipt: bool, journal: bool, approval: bool, skipped: bool }
     */
    function syncPaidRegistrationToAccounting($mysqli, array $row)
    {
        $out = ['receipt' => false, 'journal' => false, 'approval' => false, 'skipped' => true];
        $registrationId = (int) ($row['id'] ?? 0);
        if ($registrationId <= 0) {
            return $out;
        }
        if (strtolower((string) ($row['payment_status'] ?? '')) !== 'paid') {
            return $out;
        }
        $amount = isset($row['plan_amount']) ? (float) $row['plan_amount'] : 0;
        if ($amount <= 0) {
            return $out;
        }

        $agencyId = registrationAccountingResolveAgencyId($row);
        $countryId = registrationAccountingResolveCountryId($mysqli, $row);
        $agencyName = trim((string) ($row['agency_name'] ?? 'Agency'));
        $desc = 'Registration payment — ' . $agencyName . ' (request #' . $registrationId . ')';

        $today = date('Y-m-d');
        if (!empty($row['updated_at'])) {
            $u = strtotime((string) $row['updated_at']);
            if ($u) {
                $today = date('Y-m-d', $u);
            }
        }

        $chkR = @$mysqli->query("SHOW TABLES LIKE 'control_receipts'");
        if ($chkR && $chkR->num_rows > 0) {
            $dup = $mysqli->query("SELECT id FROM control_receipts WHERE description = '" . $mysqli->real_escape_string($desc) . "' AND amount = " . (float) $amount . " LIMIT 1");
            if (!$dup || $dup->num_rows === 0) {
                $receiptNum = registrationAccountingNextRcNumber($mysqli);
                $stmt = $mysqli->prepare('INSERT INTO control_receipts (agency_id, country_id, receipt_number, receipt_date, amount, currency_code, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    $cur = 'SAR';
                    $status = 'completed';
                    $stmt->bind_param('iissdsss', $agencyId, $countryId, $receiptNum, $today, $amount, $cur, $desc, $status);
                    if ($stmt->execute()) {
                        $out['receipt'] = true;
                    }
                    $stmt->close();
                }
            }
        }

        $chkJ = @$mysqli->query("SHOW TABLES LIKE 'control_journal_entries'");
        $chkL = @$mysqli->query("SHOW TABLES LIKE 'control_journal_entry_lines'");
        if ($chkJ && $chkJ->num_rows > 0 && $chkL && $chkL->num_rows > 0) {
            $dupJ = $mysqli->query("SELECT id FROM control_journal_entries WHERE description = '" . $mysqli->real_escape_string($desc) . "' AND total_debit = " . (float) $amount . " LIMIT 1");
            if (!$dupJ || $dupJ->num_rows === 0) {
                $jeRef = registrationAccountingNextGlReference($mysqli);
                $stmt = $mysqli->prepare('INSERT INTO control_journal_entries (agency_id, country_id, reference, entry_date, description, total_debit, total_credit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    $stDraft = 'draft';
                    $stmt->bind_param('iisssdds', $agencyId, $countryId, $jeRef, $today, $desc, $amount, $amount, $stDraft);
                    $useTx = @$mysqli->begin_transaction();
                    if ($stmt->execute()) {
                        $jid = (int) $mysqli->insert_id;
                        $lineOk = true;
                        $line1 = $mysqli->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, debit, credit, description, account_name) VALUES (?, ?, ?, ?, ?)');
                        if ($line1) {
                            $d = $amount;
                            $c = 0.0;
                            $ld = 'Cash / bank — registration #' . $registrationId;
                            $debitAcctName = 'Cash / bank';
                            $line1->bind_param('iddss', $jid, $d, $c, $ld, $debitAcctName);
                            if (!$line1->execute()) {
                                $lineOk = false;
                            }
                            $line1->close();
                        } else {
                            $lineOk = false;
                        }
                        $line2 = $mysqli->prepare('INSERT INTO control_journal_entry_lines (journal_entry_id, debit, credit, description, account_name) VALUES (?, ?, ?, ?, ?)');
                        if ($line2 && $lineOk) {
                            $d = 0.0;
                            $c = $amount;
                            $ld = 'Registration revenue #' . $registrationId;
                            $creditAcctName = 'Registration revenue';
                            $line2->bind_param('iddss', $jid, $d, $c, $ld, $creditAcctName);
                            if (!$line2->execute()) {
                                $lineOk = false;
                            }
                            $line2->close();
                        } elseif ($lineOk) {
                            $lineOk = false;
                        }

                        $chkA = @$mysqli->query("SHOW TABLES LIKE 'control_entry_approvals'");
                        $approvalOk = true;
                        if ($chkA && $chkA->num_rows > 0 && $lineOk) {
                            if (!$mysqli->query('INSERT INTO control_entry_approvals (journal_entry_id, status) VALUES (' . (int) $jid . ", 'pending')")) {
                                $approvalOk = false;
                            }
                        }

                        if ($lineOk && $approvalOk) {
                            if ($useTx) {
                                $mysqli->commit();
                            }
                            $out['journal'] = true;
                            if ($chkA && $chkA->num_rows > 0) {
                                $out['approval'] = true;
                            }
                        } else {
                            if ($useTx) {
                                $mysqli->rollback();
                            } else {
                                $mysqli->query('DELETE FROM control_journal_entries WHERE id = ' . (int) $jid);
                            }
                        }
                    } elseif ($useTx) {
                        $mysqli->rollback();
                    }
                    $stmt->close();
                }
            }
        }

        $out['skipped'] = !($out['receipt'] || $out['journal']);

        return $out;
    }
}

if (!function_exists('backfillRegistrationAccountingSync')) {
    /**
     * Create missing receipts / journal rows for already-paid registrations (batch).
     *
     * @return array{ processed: int, receipts_created: int, journals_created: int }
     */
    function backfillRegistrationAccountingSync($mysqli, $limit = 2000)
    {
        $limit = max(1, min(5000, (int) $limit));
        $processed = $receiptsCreated = $journalsCreated = 0;
        $chk = @$mysqli->query("SHOW TABLES LIKE 'control_registration_requests'");
        if (!$chk || $chk->num_rows === 0) {
            return ['processed' => 0, 'receipts_created' => 0, 'journals_created' => 0];
        }
        $res = $mysqli->query('SELECT * FROM control_registration_requests WHERE payment_status = \'paid\' AND COALESCE(plan_amount,0) > 0 ORDER BY id ASC LIMIT ' . $limit);
        if (!$res) {
            return ['processed' => 0, 'receipts_created' => 0, 'journals_created' => 0];
        }
        while ($row = $res->fetch_assoc()) {
            $processed++;
            $r = syncPaidRegistrationToAccounting($mysqli, $row);
            if ($r['receipt']) {
                $receiptsCreated++;
            }
            if ($r['journal']) {
                $journalsCreated++;
            }
        }

        return [
            'processed' => $processed,
            'receipts_created' => $receiptsCreated,
            'journals_created' => $journalsCreated,
        ];
    }
}
