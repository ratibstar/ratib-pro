<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/registration_requests_ngenius_display_merge.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/registration_requests_ngenius_display_merge.php`.
 */

declare(strict_types=1);

/**
 * Merge ngenius_reg_orders into control_registration_requests rows for display/export/API.
 * The control DB often has no copy of plan/amount/reg_* when sync failed; main site DB holds truth.
 *
 * Must never throw: control panel uses MYSQLI_REPORT_STRICT; failed cross-DB queries would white-screen the page.
 */

if (!function_exists('registration_requests_parse_ngenius_order_id_from_notes')) {
    function registration_requests_parse_ngenius_order_id_from_notes(string $notes): int
    {
        if ($notes === '') {
            return 0;
        }
        if (preg_match('/Auto\s+universal\s+link\s+order[_\s]*id\s*=\s*(\d+)/i', $notes, $m)) {
            return max(0, (int) $m[1]);
        }
        if (preg_match('/\border[_\s]*id\s*=\s*(\d+)/i', $notes, $m)) {
            return max(0, (int) $m[1]);
        }

        return 0;
    }
}

if (!function_exists('registration_requests_cell_empty')) {
    function registration_requests_cell_empty($v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_string($v) && trim($v) === '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('registration_requests_fetch_ngenius_orders_map')) {
    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    function registration_requests_fetch_ngenius_orders_map(mysqli $ctrl, array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static function ($x): bool {
            return (int) $x > 0;
        });
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        if (!defined('DB_HOST') || !defined('DB_USER')) {
            return [];
        }

        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';
        $user = (string) DB_USER;

        $mainDb = defined('RATIB_PRO_DB_NAME') ? trim((string) RATIB_PRO_DB_NAME) : 'outratib_out';
        if ($mainDb === '') {
            return [];
        }

        $aux = null;
        $closeAux = false;

        try {
            $chk = $ctrl->query("SHOW TABLES LIKE 'ngenius_reg_orders'");
            if ($chk && $chk->num_rows > 0) {
                $exec = $ctrl;
            } else {
                $aux = new mysqli((string) DB_HOST, $user, $pass, $mainDb, $port);
                if ($aux->connect_errno !== 0) {
                    return [];
                }
                $aux->set_charset('utf8mb4');
                $exec = $aux;
                $closeAux = true;
            }

            $inList = implode(',', $ids);
            $sql = "SELECT id, plan_key, years, total_amount, reg_agency_name, reg_agency_id, reg_country_id, reg_country_name,
                           reg_contact_phone, reg_desired_site_url, email
                    FROM ngenius_reg_orders WHERE id IN ({$inList})";
            $res = $exec->query($sql);
            $out = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $oid = (int) ($row['id'] ?? 0);
                    if ($oid > 0) {
                        $out[$oid] = $row;
                    }
                }
            }

            return $out;
        } catch (Throwable $e) {
            return [];
        } finally {
            if ($closeAux && $aux instanceof mysqli) {
                try {
                    $aux->close();
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }
}

if (!function_exists('registration_requests_fetch_ngenius_orders_by_control_request_map')) {
    /**
     * @param list<int> $requestIds
     * @return array<int, array<string, mixed>>
     */
    function registration_requests_fetch_ngenius_orders_by_control_request_map(mysqli $ctrl, array $requestIds): array
    {
        $requestIds = array_map('intval', $requestIds);
        $requestIds = array_filter($requestIds, static function ($x): bool {
            return (int) $x > 0;
        });
        $requestIds = array_values(array_unique($requestIds));
        if ($requestIds === []) {
            return [];
        }

        if (!defined('DB_HOST') || !defined('DB_USER')) {
            return [];
        }
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';
        $user = (string) DB_USER;
        $mainDb = defined('RATIB_PRO_DB_NAME') ? trim((string) RATIB_PRO_DB_NAME) : 'outratib_out';
        if ($mainDb === '') {
            return [];
        }

        $aux = null;
        $closeAux = false;
        try {
            $chk = $ctrl->query("SHOW TABLES LIKE 'ngenius_reg_orders'");
            if ($chk && $chk->num_rows > 0) {
                $exec = $ctrl;
            } else {
                $aux = new mysqli((string) DB_HOST, $user, $pass, $mainDb, $port);
                if ($aux->connect_errno !== 0) {
                    return [];
                }
                $aux->set_charset('utf8mb4');
                $exec = $aux;
                $closeAux = true;
            }

            $inList = implode(',', $requestIds);
            $sql = "SELECT id, control_request_id, plan_key, years, total_amount, reg_agency_name, reg_agency_id, reg_country_id, reg_country_name,
                           reg_contact_phone, reg_desired_site_url, email
                    FROM ngenius_reg_orders WHERE control_request_id IN ({$inList})";
            $res = $exec->query($sql);
            $out = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rid = (int) ($row['control_request_id'] ?? 0);
                    if ($rid > 0) {
                        $out[$rid] = $row;
                    }
                }
            }

            return $out;
        } catch (Throwable $e) {
            return [];
        } finally {
            if ($closeAux && $aux instanceof mysqli) {
                try {
                    $aux->close();
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }
}

if (!function_exists('registration_requests_merge_ngenius_orders_for_display')) {
    /**
     * @param list<array<string, mixed>> $requestsList
     */
    function registration_requests_merge_ngenius_orders_for_display(mysqli $ctrl, array &$requestsList): void
    {
        if ($requestsList === []) {
            return;
        }

        try {
            $orderIds = [];
            $requestIds = [];
            foreach ($requestsList as $r) {
                $rid = (int)($r['id'] ?? 0);
                if ($rid > 0) {
                    $requestIds[] = $rid;
                }
                $oid = registration_requests_parse_ngenius_order_id_from_notes((string) ($r['notes'] ?? ''));
                if ($oid > 0) {
                    $orderIds[] = $oid;
                }
            }
            if ($orderIds === [] && $requestIds === []) {
                return;
            }

            $byOrder = registration_requests_fetch_ngenius_orders_map($ctrl, $orderIds);
            $byControlRequest = registration_requests_fetch_ngenius_orders_by_control_request_map($ctrl, $requestIds);
            if ($byOrder === [] && $byControlRequest === []) {
                return;
            }

            foreach ($requestsList as $i => $r) {
                $oid = registration_requests_parse_ngenius_order_id_from_notes((string) ($r['notes'] ?? ''));
                $rid = (int)($r['id'] ?? 0);
                if ($oid > 0 && isset($byOrder[$oid])) {
                    $o = $byOrder[$oid];
                } elseif ($rid > 0 && isset($byControlRequest[$rid])) {
                    $o = $byControlRequest[$rid];
                } else {
                    continue;
                }

                $okPlan = strtolower(trim((string) ($o['plan_key'] ?? '')));
                $planDisp = $okPlan !== '' ? substr($okPlan, 0, 32) : 'pro';

                if (registration_requests_cell_empty($r['plan'] ?? null)) {
                    $requestsList[$i]['plan'] = $planDisp;
                }

                if (registration_requests_cell_empty($r['plan_amount'] ?? null) && isset($o['total_amount'])) {
                    $requestsList[$i]['plan_amount'] = $o['total_amount'];
                }

                $yearsO = (int) ($o['years'] ?? 0);
                if (registration_requests_cell_empty($r['years'] ?? null) && $yearsO > 0) {
                    $requestsList[$i]['years'] = $yearsO;
                }

                $regName = trim((string) ($o['reg_agency_name'] ?? ''));
                $agencyCur = trim((string) ($r['agency_name'] ?? ''));
                $isGenericAgency = $agencyCur === '' || preg_match('/^N-Genius\s+PLAN$/i', $agencyCur) === 1;
                if ($isGenericAgency) {
                    if ($regName !== '') {
                        $requestsList[$i]['agency_name'] = $regName;
                    } elseif ($okPlan !== '') {
                        $requestsList[$i]['agency_name'] = 'N-Genius ' . strtoupper($okPlan);
                    }
                }

                if (registration_requests_cell_empty($r['agency_id'] ?? null)) {
                    $aid = trim((string) ($o['reg_agency_id'] ?? ''));
                    if ($aid !== '') {
                        $requestsList[$i]['agency_id'] = $aid;
                    }
                }

                $cid = (int) ($o['reg_country_id'] ?? 0);
                if (registration_requests_cell_empty($r['country_id'] ?? null) && $cid > 0) {
                    $requestsList[$i]['country_id'] = $cid;
                }

                if (registration_requests_cell_empty($r['country_name'] ?? null)) {
                    $cn = trim((string) ($o['reg_country_name'] ?? ''));
                    if ($cn !== '') {
                        $requestsList[$i]['country_name'] = $cn;
                    }
                }

                if (registration_requests_cell_empty($r['contact_phone'] ?? null)) {
                    $ph = trim((string) ($o['reg_contact_phone'] ?? ''));
                    if ($ph !== '') {
                        $requestsList[$i]['contact_phone'] = $ph;
                    }
                }

                if (registration_requests_cell_empty($r['desired_site_url'] ?? null)) {
                    $su = trim((string) ($o['reg_desired_site_url'] ?? ''));
                    if ($su !== '') {
                        $requestsList[$i]['desired_site_url'] = $su;
                    }
                }

                if (registration_requests_cell_empty($r['contact_email'] ?? null)) {
                    $em = trim((string) ($o['email'] ?? ''));
                    if ($em !== '') {
                        $requestsList[$i]['contact_email'] = $em;
                    }
                }
            }
        } catch (Throwable $e) {
            // Never break the registration requests page
        }
    }
}
