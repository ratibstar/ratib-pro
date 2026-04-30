<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/registration-requests-content.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/registration-requests-content.php`.
 */
/**
 * Registration Requests content - shared by control/registration-requests.php
 * Expects: $ctrl (DB connection), auth already done
 */
$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = rtrim($baseUrl, '/') . '/api/control';
/** Public Ratib Pro site (registration + N-Genius checkout), not /control-panel/pages/home.php */
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$publicSiteRoot = (defined('SITE_URL') && is_string(SITE_URL) && SITE_URL !== '')
    ? rtrim(SITE_URL, '/')
    : ($host !== '' ? $scheme . '://' . $host . preg_replace('#/control-panel$#', '', $basePath, 1) : '');
$registerProUrl = ($publicSiteRoot !== '' ? $publicSiteRoot : $baseUrl) . '/pages/home.php?open=register&plan=gold&years=1';

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$countries = [];
$chk = @$ctrl->query("SHOW TABLES LIKE 'control_countries'");
if ($chk && $chk->num_rows > 0) {
    $countrySql = "SELECT id, name FROM control_countries WHERE is_active = 1 ORDER BY sort_order, name";
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $countrySql = "SELECT id, name FROM control_countries WHERE id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ") AND is_active = 1 ORDER BY sort_order, name";
    } elseif ($allowedCountryIds === []) {
        $countrySql = "SELECT id, name FROM control_countries WHERE 1=0";
    }
    $res = @$ctrl->query($countrySql);
    if ($res) while ($row = $res->fetch_assoc()) $countries[] = $row;
}
$nameToCountryId = [];
foreach ($countries as $__c) {
    $nm = strtolower(trim(preg_replace('/\s+/u', ' ', (string)($__c['name'] ?? ''))));
    if ($nm !== '') {
        $nameToCountryId[$nm] = (int)($__c['id'] ?? 0);
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
$status = trim($_GET['status'] ?? '');
$planFilter = trim($_GET['plan'] ?? '');
$search = trim($_GET['search'] ?? '');
$paymentStatusFilter = trim($_GET['payment_status'] ?? '');
/* Default: show all calendar dates unless user picked a range (or explicitly turned off all_dates). */
$dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
$dateToRaw = trim((string)($_GET['date_to'] ?? ''));
if (isset($_GET['all_dates'])) {
    $allDates = ((string) $_GET['all_dates'] === '1');
} elseif ($dateFromRaw !== '' || $dateToRaw !== '') {
    $allDates = false;
} else {
    $allDates = true;
}
$dateFrom = $dateFromRaw;
$dateTo = $dateToRaw;
if (!$allDates && $dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
}
if ($dateFrom !== '') {
    $ts = strtotime($dateFrom);
    $dateFrom = ($ts !== false) ? date('Y-m-d', $ts) : '';
}
if ($dateTo !== '') {
    $ts = strtotime($dateTo);
    $dateTo = ($ts !== false) ? date('Y-m-d', $ts) : '';
}
$amountMin = null;
$amountMax = null;
$rawAmountMin = trim((string)($_GET['amount_min'] ?? ''));
$rawAmountMax = trim((string)($_GET['amount_max'] ?? ''));
if ($rawAmountMin !== '' && is_numeric($rawAmountMin)) {
    $amountMin = (float)$rawAmountMin;
}
if ($rawAmountMax !== '' && is_numeric($rawAmountMax)) {
    $amountMax = (float)$rawAmountMax;
}
$requestsList = [];
$totalRequests = 0;
$totalPages = 1;
$offset = 0;
$tableExists = false;
/** Pending rows matching the same filters as the table (for banner; sidebar badge stays unfiltered). */
$pendingFilteredCount = 0;

// Build query string for links (all current filters)
$queryParams = ['control' => '1', 'status' => $status, 'plan' => $planFilter, 'search' => $search, 'payment_status' => $paymentStatusFilter, 'limit' => $limit];
if ($allDates) {
    $queryParams['all_dates'] = '1';
} else {
    $queryParams['date_from'] = $dateFrom;
    $queryParams['date_to'] = $dateTo;
}
if ($amountMin !== null) {
    $queryParams['amount_min'] = $amountMin;
}
if ($amountMax !== null) {
    $queryParams['amount_max'] = $amountMax;
}
$queryString = http_build_query(array_filter($queryParams, function($v) { return $v !== '' && $v !== null; }));

$reqAgencyState = [];
$hasCountryIdCol = false;
$createdAgencyCountryIdMap = [];
$hasRegAgencySuspendedCol = false;
$chk2 = @$ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
if ($chk2 && $chk2->num_rows > 0) {
    $tableExists = true;
    $colCountry = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
    $hasCountryIdCol = ($colCountry && $colCountry->num_rows > 0);
    $colPaymentStatus = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
    $hasPaymentStatusCol = ($colPaymentStatus && $colPaymentStatus->num_rows > 0);
    $colPlanAmount = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'");
    $hasPlanAmountCol = ($colPlanAmount && $colPlanAmount->num_rows > 0);
    $conds = [];
    $regScopeIds = function_exists('getRegistrationRequestScopeCountryIds') ? getRegistrationRequestScopeCountryIds($ctrl) : null;
    $regViewAllCountries = ($regScopeIds === null);
    if ($regScopeIds === []) {
        if (!$regViewAllCountries) {
            $conds[] = '1=0';
        }
    } elseif (!$regViewAllCountries && $regScopeIds !== null && !empty($regScopeIds) && $hasCountryIdCol) {
        $idsStr = implode(',', array_map('intval', $regScopeIds));
        $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
        $countryNames = [];
        if ($namesRes) {
            while ($r = $namesRes->fetch_assoc()) {
                $countryNames[] = "'" . $ctrl->real_escape_string($r['name']) . "'";
            }
        }
        $nameMatch = !empty($countryNames) ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))" : '';
        $conds[] = "(country_id IN ($idsStr)$nameMatch)";
    }
    if ($status !== '' && in_array($status, ['pending','approved','rejected'])) { $conds[] = "status = '" . $ctrl->real_escape_string($status) . "'"; }
    if ($planFilter !== '' && in_array($planFilter, ['pro','gold','platinum'])) { $conds[] = "plan = '" . $ctrl->real_escape_string($planFilter) . "'"; }
    if ($hasPaymentStatusCol && $paymentStatusFilter === 'unpaid') {
        /* Not settled: anything except explicit paid (schema also has failed; older rows may use other non-paid values). */
        $conds[] = "(LOWER(TRIM(COALESCE(payment_status,''))) <> 'paid')";
    } elseif ($hasPaymentStatusCol && $paymentStatusFilter !== '' && in_array($paymentStatusFilter, ['pending', 'paid', 'failed'], true)) {
        $conds[] = "payment_status = '" . $ctrl->real_escape_string($paymentStatusFilter) . "'";
    }
    if ($dateFrom !== '') {
        $d = date('Y-m-d', strtotime($dateFrom));
        if ($d) $conds[] = "DATE(created_at) >= '" . $ctrl->real_escape_string($d) . "'";
    }
    if ($dateTo !== '') {
        $d = date('Y-m-d', strtotime($dateTo));
        if ($d) $conds[] = "DATE(created_at) <= '" . $ctrl->real_escape_string($d) . "'";
    }
    if ($hasPlanAmountCol && $amountMin !== null && is_finite($amountMin)) {
        $conds[] = "COALESCE(plan_amount, 0) >= " . (float)$amountMin;
    }
    if ($hasPlanAmountCol && $amountMax !== null && is_finite($amountMax)) {
        $conds[] = "COALESCE(plan_amount, 0) <= " . (float)$amountMax;
    }
    if ($search !== '') {
        $q = $ctrl->real_escape_string($search);
        $idNum = (int)preg_replace('/[^0-9]/', '', $search);
        $idPart = ($idNum > 0) ? "id = " . $idNum . " OR " : "";
        $colChk = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
        $hasAgencyIdCol = ($colChk && $colChk->num_rows > 0);
        $agencyIdPart = $hasAgencyIdCol ? " OR agency_id LIKE '%" . $q . "%'" : "";
        $conds[] = "(" . $idPart . "agency_name LIKE '%" . $q . "%'" . $agencyIdPart . " OR contact_email LIKE '%" . $q . "%' OR country_name LIKE '%" . $q . "%' OR notes LIKE '%" . $q . "%')";
    }
    /* List default: hide approved (they live under Manage Agencies). Stats/export still use $where unless noted. */
    $condsForList = $conds;
    if ($status === '') {
        $condsForList[] = "LOWER(TRIM(COALESCE(status,''))) <> 'approved'";
    }
    // Default safety: without explicit payment filter, show only paid registrations
    // (plus Pro inquiry rows) so unpaid paid-plans don't appear in active queue.
    if ($hasPaymentStatusCol && $paymentStatusFilter === '') {
        $condsForList[] = "(LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' OR LOWER(TRIM(COALESCE(plan,''))) = 'pro')";
    }
    $whereForList = count($condsForList) ? ' WHERE ' . implode(' AND ', $condsForList) : '';
    $where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';
    // Keep banner count aligned with visible queue filters.
    $pendingAlertConds = $condsForList;
    $pendingAlertConds[] = "status = 'pending'";
    $pendingAlertWhere = count($pendingAlertConds) ? ' WHERE ' . implode(' AND ', $pendingAlertConds) : " WHERE status = 'pending'";
    $pendingAlertRes = $ctrl->query("SELECT COUNT(*) AS c FROM control_registration_requests" . $pendingAlertWhere);
    if ($pendingAlertRes && ($pRow = $pendingAlertRes->fetch_assoc())) {
        $pendingFilteredCount = (int)($pRow['c'] ?? 0);
    }
    $totalRequests = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests" . $whereForList)->fetch_assoc()['c'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRequests / max(1, $limit)));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;
    $res2 = $ctrl->query("SELECT * FROM control_registration_requests" . $whereForList . " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
    if ($res2) while ($row = $res2->fetch_assoc()) $requestsList[] = $row;

    $ngMergePath = __DIR__ . '/../registration_requests_ngenius_display_merge.php';
    if (is_readable($ngMergePath)) {
        require_once $ngMergePath;
        if (function_exists('registration_requests_merge_ngenius_orders_for_display')) {
            registration_requests_merge_ngenius_orders_for_display($ctrl, $requestsList);
        }
    }
    // Normalize legacy/alternate column names so table columns always map correctly.
    $pickFirstNonEmpty = static function (array $row, array $keys, $default = null) {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if ($v === null) {
                continue;
            }
            if (is_string($v) && trim($v) === '') {
                continue;
            }
            return $v;
        }
        return $default;
    };
    foreach ($requestsList as $i => $rowN) {
        $requestsList[$i]['agency_id'] = $pickFirstNonEmpty($rowN, ['agency_id', 'reg_agency_id', 'agency_code'], $rowN['agency_id'] ?? '');
        $requestsList[$i]['country_name'] = $pickFirstNonEmpty($rowN, ['country_name', 'country', 'reg_country_name'], $rowN['country_name'] ?? '');
        $requestsList[$i]['contact_phone'] = $pickFirstNonEmpty($rowN, ['contact_phone', 'phone', 'reg_contact_phone'], $rowN['contact_phone'] ?? '');
        $requestsList[$i]['desired_site_url'] = $pickFirstNonEmpty($rowN, ['desired_site_url', 'site_url', 'reg_desired_site_url', 'website'], $rowN['desired_site_url'] ?? '');
        $requestsList[$i]['plan'] = $pickFirstNonEmpty($rowN, ['plan', 'plan_key'], $rowN['plan'] ?? '');
        $requestsList[$i]['plan_amount'] = $pickFirstNonEmpty($rowN, ['plan_amount', 'amount', 'total_amount'], $rowN['plan_amount'] ?? null);
        $requestsList[$i]['payment_status'] = $pickFirstNonEmpty($rowN, ['payment_status', 'pay_status'], $rowN['payment_status'] ?? null);
        $requestsList[$i]['created_agency_id'] = (int)$pickFirstNonEmpty($rowN, ['created_agency_id', 'agency_created_id', 'approved_agency_id'], $rowN['created_agency_id'] ?? 0);
    }

    $chkAgSusp = @$ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
    if ($chkAgSusp && $chkAgSusp->num_rows > 0) {
        $hasRegAgencySuspendedCol = true;
    }
    $agencyIdsNeeded = [];
    foreach ($requestsList as $rr) {
        $rid = (int)($rr['created_agency_id'] ?? 0);
        if ($rid > 0) {
            $agencyIdsNeeded[$rid] = true;
        }
    }
    if (!empty($agencyIdsNeeded)) {
        $inList = implode(',', array_map('intval', array_keys($agencyIdsNeeded)));
        $colAgCo = @$ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
        $hasAgencyTableCountryId = ($colAgCo && $colAgCo->num_rows > 0);
        $agSql = 'SELECT id, is_active' . ($hasRegAgencySuspendedCol ? ', COALESCE(is_suspended, 0) AS is_suspended' : '') . ($hasAgencyTableCountryId ? ', country_id' : '') . ' FROM control_agencies WHERE id IN (' . $inList . ')';
        $agRes = @$ctrl->query($agSql);
        if ($agRes) {
            while ($ar = $agRes->fetch_assoc()) {
                $reqAgencyState[(int)$ar['id']] = $ar;
                if ($hasAgencyTableCountryId && isset($ar['country_id']) && (int)$ar['country_id'] > 0) {
                    $createdAgencyCountryIdMap[(int)$ar['id']] = (int)$ar['country_id'];
                }
            }
        }
    }
}

$resolveManageAgenciesCountryId = static function (array $r, int $createdAgencyDbId, bool $hasReqCountryCol, array $agencyCountryMap, array $nameToId): int {
    if ($hasReqCountryCol && !empty($r['country_id']) && (int)$r['country_id'] > 0) {
        return (int)$r['country_id'];
    }
    if ($createdAgencyDbId > 0 && !empty($agencyCountryMap[$createdAgencyDbId])) {
        return (int)$agencyCountryMap[$createdAgencyDbId];
    }
    $cn = strtolower(trim(preg_replace('/\s+/u', ' ', (string)($r['country_name'] ?? ''))));
    if ($cn !== '' && isset($nameToId[$cn])) {
        return (int)$nameToId[$cn];
    }
    return 0;
};
$fmtId = function($id) { return 'REQ' . str_pad((int)$id, 4, '0', STR_PAD_LEFT); };
$fmtAgencyId = function($id) { return $id ? ('AG' . str_pad((int)$id, 4, '0', STR_PAD_LEFT)) : '-'; };
/** Gregorian English display (e.g. Mar 23, 2026) — not locale/Hijri dependent */
$fmtDateEn = function($raw) {
    if ($raw === null || $raw === '') {
        return '-';
    }
    $ts = strtotime((string)$raw);
    if ($ts === false) {
        return htmlspecialchars(substr((string)$raw, 0, 10));
    }
    return htmlspecialchars(date('M j, Y', $ts));
};
$reqRowVisualClass = function (array $r) use ($reqAgencyState, $hasRegAgencySuspendedCol) {
    $aid = (int)($r['created_agency_id'] ?? 0);
    $st = $r['status'] ?? 'pending';
    $parts = [];
    if ($aid > 0 && isset($reqAgencyState[$aid])) {
        $ag = $reqAgencyState[$aid];
        $inactive = ((int)($ag['is_active'] ?? 1)) === 0;
        $suspended = $hasRegAgencySuspendedCol && (int)($ag['is_suspended'] ?? 0) === 1;
        if ($inactive) {
            $parts[] = 'req-row-agency-inactive';
        }
        if ($suspended) {
            $parts[] = 'req-row-agency-suspended';
        }
        if (!$inactive && !$suspended) {
            $parts[] = 'req-row-agency-ok';
        }
    } else {
        if ($st === 'pending') {
            $parts[] = 'req-row-req-pending';
        } elseif ($st === 'rejected') {
            $parts[] = 'req-row-req-rejected';
        } elseif ($st === 'approved') {
            $parts[] = 'req-row-req-approved';
        }
    }
    return implode(' ', $parts);
};
$formAction = pageUrl('control/registration-requests.php');
$agenciesManageUrl = pageUrl('control/agencies.php');
$regBase = ($publicSiteRoot !== '' ? $publicSiteRoot : $baseUrl) . '/pages/home.php';

$statusCards = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'paid' => 0,
    'unpaid' => 0,
    'active' => 0,
    'inactive' => 0
];
if ($tableExists) {
    // Card totals must match table filters (including default hide-approved and default paid/pro safety).
    if ($hasPaymentStatusCol) {
        $statusSql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' THEN 1 ELSE 0 END) AS paid,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(payment_status,''))) <> 'paid' THEN 1 ELSE 0 END) AS unpaid
                  FROM control_registration_requests" . $whereForList;
    } else {
        $statusSql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    0 AS paid,
                    0 AS unpaid
                  FROM control_registration_requests" . $whereForList;
    }
    $statusRes = @$ctrl->query($statusSql);
    if ($statusRes && ($statusRow = $statusRes->fetch_assoc())) {
        $statusCards['pending'] = (int)($statusRow['pending'] ?? 0);
        $statusCards['approved'] = (int)($statusRow['approved'] ?? 0);
        $statusCards['rejected'] = (int)($statusRow['rejected'] ?? 0);
        $statusCards['paid'] = (int)($statusRow['paid'] ?? 0);
        $statusCards['unpaid'] = (int)($statusRow['unpaid'] ?? 0);
    }
    /* First card = rows visible in the table (excludes approved when status filter is default). */
    $statusCards['total'] = $totalRequests;

    // Active/inactive should also follow the same visible table filters.
    $agencyWhere = $whereForList ? ($whereForList . " AND created_agency_id > 0") : " WHERE created_agency_id > 0";
    $agencyIds = [];
    $agencyIdRes = @$ctrl->query("SELECT DISTINCT created_agency_id FROM control_registration_requests" . $agencyWhere);
    if ($agencyIdRes) {
        while ($ar = $agencyIdRes->fetch_assoc()) {
            $aid = (int)($ar['created_agency_id'] ?? 0);
            if ($aid > 0) $agencyIds[$aid] = true;
        }
    }
    if (!empty($agencyIds)) {
        $idsList = implode(',', array_map('intval', array_keys($agencyIds)));
        $colIsActive = @$ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'is_active'");
        $hasIsActiveCol = ($colIsActive && $colIsActive->num_rows > 0);
        if ($hasIsActiveCol) {
            $agencyStateRes = @$ctrl->query("SELECT id, is_active FROM control_agencies WHERE id IN ($idsList)");
            if ($agencyStateRes) {
                while ($ag = $agencyStateRes->fetch_assoc()) {
                    if ((int)($ag['is_active'] ?? 1) === 1) $statusCards['active']++;
                    else $statusCards['inactive']++;
                }
            }
        }
    }
}
?>
<div id="registrationRequestsContent" data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-agencies-url="<?php echo htmlspecialchars(pageUrl('control/agencies.php')); ?>" data-pending-filtered-total="<?php echo (int)$pendingFilteredCount; ?>">
<div id="pendingAlertBanner" class="pending-alert-banner d-none">
    <span><i class="fas fa-bell me-2"></i><span id="pendingAlertCount">0</span> pending registration request(s) need your attention.</span>
    <button type="button" class="btn btn-sm btn-outline-dark" id="btnDismissPendingAlert">Dismiss</button>
</div>
<div class="req-table-card">
    <h2 class="mb-3"><i class="fas fa-user-plus me-2"></i>Registration Requests</h2>
    <div class="req-status-cards">
        <div class="req-status-card"><div class="k">Total filtered</div><div class="v"><?php echo (int)$statusCards['total']; ?></div></div>
        <div class="req-status-card pending"><div class="k">Pending</div><div class="v"><?php echo (int)$statusCards['pending']; ?></div></div>
        <div class="req-status-card approved"><div class="k">Approved</div><div class="v"><?php echo (int)$statusCards['approved']; ?></div></div>
        <div class="req-status-card rejected"><div class="k">Rejected</div><div class="v"><?php echo (int)$statusCards['rejected']; ?></div></div>
        <div class="req-status-card paid"><div class="k">Paid</div><div class="v"><?php echo (int)$statusCards['paid']; ?></div></div>
        <div class="req-status-card unpaid"><div class="k">Unpaid</div><div class="v"><?php echo (int)$statusCards['unpaid']; ?></div></div>
        <div class="req-status-card active"><div class="k">Active</div><div class="v"><?php echo (int)$statusCards['active']; ?></div></div>
        <div class="req-status-card inactive"><div class="k">Inactive</div><div class="v"><?php echo (int)$statusCards['inactive']; ?></div></div>
    </div>
    <div class="req-reg-link-box">
        <span class="text-muted">Registration links:</span>
        <select id="regLinkSelect" class="form-control req-ctrl-input req-width-auto">
            <option value="<?php echo htmlspecialchars($registerProUrl); ?>">Recommended — Gold, 1 year (N-Genius)</option>
            <option value="<?php echo htmlspecialchars($regBase . '?open=register'); ?>">Open register only (defaults to Gold)</option>
            <option value="<?php echo htmlspecialchars($regBase . '?open=register&plan=pro'); ?>">Pro (non-paid inquiry)</option>
            <option value="<?php echo htmlspecialchars($regBase . '?open=register&plan=gold&amount=550'); ?>">Gold $550</option>
            <option value="<?php echo htmlspecialchars($regBase . '?open=register&plan=platinum&amount=600'); ?>">Platinum $600</option>
        </select>
        <input type="text" id="regLink" readonly value="<?php echo htmlspecialchars($registerProUrl); ?>" class="req-reg-link-input">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyLink"><i class="fas fa-copy me-1"></i> Copy</button>
    </div>
    <div class="req-quick-filters mb-2">
        <span class="text-muted me-2">Quick:</span>
        <?php
        $moStart = date('Y-m-01');
        $moEnd = date('Y-m-d');
        $qfUnpaid = ($paymentStatusFilter === 'unpaid');
        $qfPaid = ($paymentStatusFilter === 'paid');
        $qfThisMo = (!$allDates && $dateFrom === $moStart && $dateTo === $moEnd);
        $qfAllDates = ($allDates && $paymentStatusFilter === '' && $amountMin === null && $amountMax === null);
        $qfHigh = ($amountMin !== null && is_finite($amountMin) && $amountMin >= 100);
        ?>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;all_dates=1&amp;payment_status=unpaid" class="btn btn-sm <?php echo $qfUnpaid ? 'btn-warning' : 'btn-outline-warning'; ?> me-1">Unpaid only</a>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;date_from=<?php echo rawurlencode($moStart); ?>&amp;date_to=<?php echo rawurlencode($moEnd); ?>" class="btn btn-sm <?php echo $qfThisMo ? 'btn-info' : 'btn-outline-info'; ?> me-1">This month</a>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;all_dates=1" class="btn btn-sm <?php echo $qfAllDates ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-1">All dates</a>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;all_dates=1&amp;payment_status=paid" class="btn btn-sm <?php echo $qfPaid ? 'btn-success' : 'btn-outline-success'; ?> me-1">Paid only</a>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;all_dates=1&amp;amount_min=100" class="btn btn-sm <?php echo $qfHigh ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-1">High value (≥100)</a>
    </div>
    <form method="get" action="<?php echo htmlspecialchars($formAction); ?>" class="req-controls-bar">
        <input type="hidden" name="control" value="1">
        <?php if ($allDates): ?><input type="hidden" name="all_dates" value="1"><?php endif; ?>
        <input type="text" name="search" class="form-control req-ctrl-input req-search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="plan" class="form-control req-ctrl-input req-plan-select js-auto-submit">
            <option value="">All plans</option>
            <option value="pro" <?php echo $planFilter === 'pro' ? 'selected' : ''; ?>>Pro</option>
            <option value="gold" <?php echo $planFilter === 'gold' ? 'selected' : ''; ?>>Gold</option>
            <option value="platinum" <?php echo $planFilter === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
        </select>
        <select name="status" class="form-control req-ctrl-input req-status-select js-auto-submit" title="Default hides approved rows (agencies live under Manage Agencies)">
            <option value="">Queue (hide approved)</option>
            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <select name="payment_status" class="form-control req-ctrl-input req-plan-select js-auto-submit">
            <option value="">Payment</option>
            <option value="paid" <?php echo $paymentStatusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="unpaid" <?php echo $paymentStatusFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            <option value="pending" <?php echo $paymentStatusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
        </select>
        <input type="text" name="date_from" autocomplete="off" class="form-control req-ctrl-input req-date-filter req-date-en" value="<?php echo $dateFrom !== '' ? htmlspecialchars($dateFrom) : ''; ?>" placeholder="YYYY-MM-DD" lang="en" dir="ltr" title="English calendar — click to pick">
        <input type="text" name="date_to" autocomplete="off" class="form-control req-ctrl-input req-date-filter req-date-en" value="<?php echo $dateTo !== '' ? htmlspecialchars($dateTo) : ''; ?>" placeholder="YYYY-MM-DD" lang="en" dir="ltr" title="English calendar — click to pick">
        <input type="number" name="amount_min" class="form-control req-ctrl-input req-amount-input" value="<?php echo $amountMin !== null ? (float)$amountMin : ''; ?>" placeholder="Min $" step="0.01" min="0">
        <input type="number" name="amount_max" class="form-control req-ctrl-input req-amount-input" value="<?php echo $amountMax !== null ? (float)$amountMax : ''; ?>" placeholder="Max $" step="0.01" min="0">
        <div class="req-per-page d-flex align-items-center flex-nowrap gap-1 ms-1">
            <label for="reqLimitSelect" class="text-muted small mb-0 text-nowrap">Show</label>
            <select id="reqLimitSelect" name="limit" class="form-control req-ctrl-input req-limit-select js-cp-reg-page-limit" title="Rows per page" autocomplete="off">
            <option value="5" <?php echo $limit === 5 ? 'selected' : ''; ?>>5</option>
            <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
            <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
        </select>
            <span class="text-muted small mb-0 text-nowrap">entries</span>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-search me-1"></i> Go</button>
        <a href="<?php echo htmlspecialchars($formAction); ?>?control=1&amp;all_dates=1" class="btn btn-outline-secondary btn-sm" title="Reset filters (all dates)"><i class="fas fa-sync-alt"></i></a>
        <a href="<?php echo htmlspecialchars($apiBase . '/registration-requests-export.php?' . $queryString); ?>" class="btn btn-outline-success btn-sm" target="_blank" data-permission="control_registration_requests,view_control_registration"><i class="fas fa-file-csv me-1"></i> Export CSV</a>
    </form>
    <?php if (!$tableExists): ?>
    <p class="text-warning">Run the migration: <code>config/control_registration_requests.sql</code></p>
    <?php else: ?>
    <p class="text-muted small mb-2">Search by Reg ID, Agency, Email, Country, or Notes. Approved requests are hidden by default (open <a href="<?php echo htmlspecialchars($agenciesManageUrl); ?>?control=1">Manage Agencies</a> or set Status to Approved).</p>
    <div class="req-bulk-bar">
        <span class="req-bulk-count"><span id="reqBulkSelectedCount">0</span> selected</span>
        <button type="button" class="btn btn-sm btn-outline-info" id="btnSelectAllRows">Select page</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSelectedRows">Clear</button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnBulkDelete" data-permission="control_registration_requests,delete_control_registration">Bulk Delete</button>
        <button type="button" class="btn btn-sm btn-outline-warning" id="btnBulkReject" data-permission="control_registration_requests,reject_control_registration">Bulk Reject</button>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnBulkMarkPaid" data-permission="control_registration_requests,edit_control_registration,approve_control_registration">Bulk Mark Paid</button>
        <button type="button" class="btn btn-sm btn-outline-warning" id="btnBulkSuspendAgency" data-permission="control_agencies,edit_control_agency">Suspend Agency</button>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnBulkUnsuspendAgency" data-permission="control_agencies,edit_control_agency">Unsuspend Agency</button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnBulkDeactivateAgency" data-permission="control_agencies,edit_control_agency">Deactivate Agency</button>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnBulkActivateAgency" data-permission="control_agencies,edit_control_agency">Activate Agency</button>
    </div>
    <p class="req-row-color-key">Row background: <span class="text-danger">red</span> = inactive agency, <span class="text-warning">yellow</span> = suspended, <span class="text-success">green</span> = active agency; <span class="text-warning">amber</span> = pending request, <span class="text-danger">dark red</span> = rejected, <span class="text-info">blue</span> = approved (no agency yet).</p>
    <div class="req-table-wrap">
        <table class="table table-dark req-table">
            <colgroup>
                <col class="req-col-reg">
                <col class="req-col-created">
                <col class="req-col-agency">
                <col class="req-col-agency-user">
                <col class="req-col-country">
                <col class="req-col-email">
                <col class="req-col-phone">
                <col class="req-col-site">
                <col class="req-col-notes">
                <col class="req-col-plan">
                <col class="req-col-amount">
                <col class="req-col-payment">
                <col class="req-col-status">
                <col class="req-col-created-agency">
                <col class="req-select-col">
                <col class="req-col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th class="req-col-reg">Reg ID</th><th class="req-col-created">Created</th><th class="req-col-agency">Agency</th><th class="req-col-agency-user">Agency ID</th><th class="req-col-country">Country</th><th class="req-col-email">Email</th><th class="req-col-phone">Phone</th><th class="req-col-site">Site URL</th><th class="req-col-notes">Notes</th><th class="req-col-plan">Plan</th><th class="req-col-amount">Amount</th><th class="req-col-payment">Payment</th><th class="req-col-status">Status</th><th class="req-col-created-agency">Created Agency</th><th class="req-select-col"><input type="checkbox" id="reqCheckAll" title="Select all on this page"></th><th class="req-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($requestsList as $r): $s = $r['status'] ?? 'pending'; $aid = (int)($r['created_agency_id'] ?? 0);
                $countryCell = (string)(($r['country_name'] ?? '') ?: ($r['country'] ?? '') ?: ($r['reg_country_name'] ?? '') ?: '');
                $phoneCell = (string)(($r['contact_phone'] ?? '') ?: ($r['phone'] ?? '') ?: ($r['reg_contact_phone'] ?? '') ?: '');
                $siteCell = (string)(($r['desired_site_url'] ?? '') ?: ($r['site_url'] ?? '') ?: ($r['reg_desired_site_url'] ?? '') ?: '');
                $planCell = trim((string)(($r['plan'] ?? '') ?: ($r['plan_key'] ?? '') ?: ''));
                $amountRaw = $r['plan_amount'] ?? ($r['total_amount'] ?? ($r['amount'] ?? null));
                $agActiveAttr = null;
                $agSuspAttr = null;
                if ($aid > 0 && isset($reqAgencyState[$aid])) {
                    $ag0 = $reqAgencyState[$aid];
                    $agActiveAttr = ((int)($ag0['is_active'] ?? 1)) === 0 ? '0' : '1';
                    $agSuspAttr = ($hasRegAgencySuspendedCol && (int)($ag0['is_suspended'] ?? 0) === 1) ? '1' : '0';
                }
                $rowVisClass = $reqRowVisualClass($r);
                $manageCidJson = $aid ? $resolveManageAgenciesCountryId($r, $aid, $hasCountryIdCol, $createdAgencyCountryIdMap, $nameToCountryId) : 0;
                $jsonRowForReq = $r;
                if ($manageCidJson > 0) {
                    $jsonRowForReq['_manage_country_id'] = $manageCidJson;
                }
                ?>
                <tr class="<?php echo htmlspecialchars($rowVisClass); ?>" data-id="<?php echo (int)$r['id']; ?>" data-status="<?php echo htmlspecialchars($s); ?>" data-payment-status="<?php echo htmlspecialchars((string)($r['payment_status'] ?? '')); ?>" data-plan-amount="<?php echo htmlspecialchars((string)($r['plan_amount'] ?? '')); ?>" data-created-agency-id="<?php echo (int)$aid; ?>"<?php if ($agActiveAttr !== null): ?> data-agency-is-active="<?php echo htmlspecialchars($agActiveAttr); ?>" data-agency-suspended="<?php echo htmlspecialchars($agSuspAttr); ?>"<?php endif; ?> data-json="<?php echo htmlspecialchars(base64_encode(json_encode($jsonRowForReq))); ?>">
                    <td class="req-col-reg req-col-clip"><strong><?php echo $fmtId($r['id']); ?></strong></td>
                    <td class="req-col-created req-col-clip" lang="en" dir="ltr"><span class="req-en-date-block"><?php echo $fmtDateEn($r['created_at'] ?? null); ?></span></td>
                    <td class="req-col-agency req-col-clip" title="<?php echo htmlspecialchars($r['agency_name'] ?? ''); ?>"><?php echo htmlspecialchars($r['agency_name'] ?? '-'); ?></td>
                    <td class="req-col-agency-user req-col-clip" title="<?php echo htmlspecialchars((string)($r['agency_id'] ?? '')); ?>"><?php echo htmlspecialchars($r['agency_id'] ?? '-'); ?></td>
                    <td class="req-col-country req-col-clip" title="<?php echo htmlspecialchars($countryCell); ?>"><?php echo htmlspecialchars($countryCell !== '' ? $countryCell : '-'); ?></td>
                    <td class="req-col-email req-col-clip"><a href="mailto:<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>" title="<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>"><?php echo htmlspecialchars($r['contact_email'] ?? '-'); ?></a></td>
                    <td class="req-col-phone req-col-clip" title="<?php echo htmlspecialchars($phoneCell); ?>"><?php echo htmlspecialchars($phoneCell !== '' ? $phoneCell : '-'); ?></td>
                    <td class="req-col-site req-col-clip" title="<?php echo htmlspecialchars($siteCell); ?>"><?php echo htmlspecialchars($siteCell !== '' ? $siteCell : '-'); ?></td>
                    <td class="req-col-notes req-col-clip" title="<?php echo htmlspecialchars($r['notes'] ?? ''); ?>"><?php echo htmlspecialchars($r['notes'] ?? '-'); ?></td>
                    <td class="req-col-plan req-col-clip"><?php echo htmlspecialchars($planCell !== '' ? $planCell : '-'); ?></td>
                    <td class="req-col-amount req-col-clip"><?php echo ($amountRaw !== null && $amountRaw !== '') ? '$' . number_format((float)$amountRaw, 2) : '-'; ?></td>
                    <td class="req-col-payment req-col-clip"><?php
                        $payStatus = $r['payment_status'] ?? null;
                        $payMethod = $r['payment_method'] ?? null;
                        if ($payStatus || $payMethod) {
                            $payStatusText = $payStatus ? ucfirst($payStatus) : 'N/A';
                            $payMethodText = $payMethod ? ' (' . htmlspecialchars($payMethod) . ')' : '';
                            $payBadgeClass = ($payStatus === 'paid') ? 'badge-success' : (($payStatus === 'unpaid') ? 'badge-warning' : 'badge-secondary');
                            $payTitle = $payStatusText . ($payMethod ? ' (' . $payMethod . ')' : '');
                            echo '<span class="badge ' . $payBadgeClass . '" title="' . htmlspecialchars($payTitle) . '">' . htmlspecialchars($payStatusText) . $payMethodText . '</span>';
                        } else {
                            echo '<span class="text-muted">-</span>';
                        }
                    ?></td>
                    <td class="req-col-status req-col-clip"><span class="badge badge-<?php echo $s; ?>"><?php echo ucfirst($s); ?></span></td>
                    <td class="req-col-created-agency req-col-clip"><?php
                    if ($aid) {
                        if ($manageCidJson > 0) {
                            $agOpenQs = ['control' => '1', 'country_id' => (string)$manageCidJson];
                        } else {
                            $agOpenQs = ['control' => '1', 'agency_id' => (string)(int)$aid];
                        }
                        $agOpenUrl = $agenciesManageUrl . '?' . http_build_query($agOpenQs);
                        $agTitle = $fmtAgencyId($aid);
                        if (!empty($r['country_name'])) {
                            $agTitle .= ' — ' . (string)$r['country_name'];
                        }
                        echo '<a href="' . htmlspecialchars($agOpenUrl) . '" title="' . htmlspecialchars($agTitle) . '">' . htmlspecialchars($fmtAgencyId($aid)) . '</a>';
                    } else {
                        echo '-';
                    }
                    ?></td>
                    <td class="req-select-col"><input type="checkbox" class="req-row-check" value="<?php echo (int)$r['id']; ?>" aria-label="Select <?php echo htmlspecialchars($fmtId($r['id'])); ?>"></td>
                    <td class="action-btns req-col-actions">
                        <button type="button" class="btn btn-sm btn-outline-info btn-view" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($jsonRowForReq))); ?>" data-permission="control_registration_requests,view_control_registration">View</button>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($jsonRowForReq))); ?>" data-permission="control_registration_requests,edit_control_registration,approve_control_registration">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_registration_requests,delete_control_registration">Delete</button>
                        <?php if (($r['payment_status'] ?? '') !== 'paid'): ?>
                        <button type="button" class="btn btn-sm btn-outline-success btn-mark-paid" data-id="<?php echo (int)$r['id']; ?>" data-amount="<?php echo htmlspecialchars((string)($r['plan_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-permission="control_registration_requests,edit_control_registration,approve_control_registration">Mark Paid</button>
                        <?php endif; ?>
                        <?php if ($s === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-outline-success btn-approve" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_registration_requests,approve_control_registration">Approve</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-reject" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_registration_requests,reject_control_registration">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-3 border-top border-secondary">
        <span class="text-muted">Showing <?php echo $totalRequests ? ($offset + 1) : 0; ?>-<?php echo min($offset + $limit, $totalRequests); ?> of <?php echo $totalRequests; ?> <span class="text-muted opacity-75">(<?php echo (int)$limit; ?> per page)</span></span>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($page > 1): ?><a href="<?php echo htmlspecialchars($formAction . '?' . $queryString . '&page=' . ($page - 1)); ?>" class="btn btn-sm btn-outline-secondary">Previous</a><?php endif; ?>
            <span>Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="<?php echo htmlspecialchars($formAction . '?' . $queryString . '&page=' . ($page + 1)); ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- View modal (read-only form) -->
<div class="modal fade req-modal-dark" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content req-modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title">Request Details <span id="viewReqId" class="text-muted"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body req-view-body">
                <table class="table table-sm table-borderless req-view-table">
                    <tr><td class="req-view-label">Reg ID</td><td id="viewId"></td></tr>
                    <tr><td class="req-view-label">Created</td><td id="viewCreated" lang="en" dir="ltr"></td></tr>
                    <tr><td class="req-view-label">Agency</td><td id="viewAgency"></td></tr>
                    <tr><td class="req-view-label">Agency ID</td><td id="viewAgencyIdUser"></td></tr>
                    <tr><td class="req-view-label">Country</td><td id="viewCountry"></td></tr>
                    <tr><td class="req-view-label">Email</td><td id="viewEmail"></td></tr>
                    <tr><td class="req-view-label">Phone</td><td id="viewPhone"></td></tr>
                    <tr><td class="req-view-label">Site URL</td><td id="viewSiteUrl" class="text-break"></td></tr>
                    <tr><td class="req-view-label">Notes</td><td id="viewNotes" class="text-break"></td></tr>
                    <tr><td class="req-view-label">Plan</td><td id="viewPlan"></td></tr>
                    <tr><td class="req-view-label">Amount</td><td id="viewAmount"></td></tr>
                    <tr><td class="req-view-label">Payment</td><td id="viewPayment"></td></tr>
                    <tr><td class="req-view-label">Status</td><td id="viewStatus"></td></tr>
                    <tr><td class="req-view-label">Created Agency</td><td id="viewAgencyId"></td></tr>
                    <tr><td class="req-view-label text-muted req-divider-top" colspan="2"><small>Additional</small></td></tr>
                    <tr><td class="req-view-label">Years</td><td id="viewYears"></td></tr>
                    <tr><td class="req-view-label">Last updated</td><td id="viewUpdated" lang="en" dir="ltr"></td></tr>
                    <tr><td class="req-view-label">IP</td><td id="viewIp"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-warning btn-edit-from-view" id="btnEditFromView" data-permission="control_registration_requests,edit_control_registration"><i class="fas fa-edit me-1"></i> Edit</button>
            </div>
        </div>
    </div>
</div>
<!-- Edit modal -->
<div class="modal fade req-modal-dark" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content req-modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title">Edit Request <span id="editReqId" class="text-muted"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="mb-3"><label class="form-label">Agency *</label><input type="text" class="form-control req-input-dark" id="editAgencyName" required></div>
                <div class="mb-3"><label class="form-label">Agency ID</label><input type="text" class="form-control req-input-dark" id="editAgencyId" maxlength="128" autocomplete="off" title="User-facing agency code (table column Agency ID)"></div>
                <div class="mb-3"><label class="form-label">Country</label><input type="text" class="form-control req-input-dark" id="editCountryName"></div>
                <div class="mb-3"><label class="form-label">Email *</label><input type="email" class="form-control req-input-dark" id="editContactEmail" required></div>
                <div class="mb-3"><label class="form-label">Phone</label><input type="text" class="form-control req-input-dark" id="editContactPhone"></div>
                <div class="mb-3"><label class="form-label">Site URL</label><input type="url" class="form-control req-input-dark" id="editSiteUrl"></div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control req-input-dark" id="editNotes" rows="3"></textarea></div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Plan</label>
                        <select class="form-select req-input-dark" id="editPlan">
                            <option value="">(unchanged)</option>
                            <option value="pro">Pro</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Amount</label>
                        <input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off" class="form-control req-input-dark" id="editPlanAmount" placeholder="Leave blank to keep current" title="Western digits 0-9">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Years</label>
                        <input type="text" inputmode="numeric" lang="en" dir="ltr" autocomplete="off" class="form-control req-input-dark" id="editYears" placeholder="Leave blank to keep current" title="Western digits 0-9">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment status</label>
                        <select class="form-select req-input-dark" id="editPaymentStatus">
                            <option value="">(unchanged)</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment method</label>
                        <select class="form-select req-input-dark" id="editPaymentMethod">
                            <option value="">(unchanged)</option>
                            <option value="paypal">PayPal</option>
                            <option value="tap">Tap</option>
                            <option value="register">Register First (Pay Later)</option>
                        </select>
                    </div>
                </div>
                <hr class="border-secondary my-3">
                <p class="text-muted small mb-2">Read-only (matches table)</p>
                <div class="mb-3"><label class="form-label">Created</label><input type="text" class="form-control req-input-dark" id="editCreatedAtRo" readonly tabindex="-1"></div>
                <div class="mb-3"><label class="form-label">Status</label><input type="text" class="form-control req-input-dark" id="editStatusRo" readonly tabindex="-1"></div>
                <div class="mb-3"><label class="form-label">Created Agency</label><div id="editCreatedAgencyRo" class="form-control req-input-dark req-created-agency-ro"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnEditSave" data-permission="control_registration_requests,edit_control_registration">Save</button>
            </div>
        </div>
    </div>
</div>
<!-- Approve modal -->
<div class="modal fade req-modal-dark" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content req-modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title">Approve & Create Agency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveRequestId">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Country *</label><select class="form-control req-input-dark" id="approveCountryId"><option value="">-- Select --</option><?php foreach ($countries as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Name *</label><input type="text" class="form-control req-input-dark" id="approveName" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Slug</label><input type="text" class="form-control req-input-dark" id="approveSlug" placeholder="auto"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Site URL *</label><input type="url" class="form-control req-input-dark" id="approveSiteUrl" placeholder="https://..."></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">DB Host</label><input type="text" class="form-control req-input-dark" id="approveDbHost" value="localhost"></div>
                    <div class="col-md-2 mb-3"><label class="form-label">DB Port</label><input type="text" class="form-control req-input-dark" id="approveDbPort" value="3306"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">DB User *</label><input type="text" class="form-control req-input-dark" id="approveDbUser"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">DB Pass *</label><input type="password" class="form-control req-input-dark" id="approveDbPass"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">DB Name *</label><input type="text" class="form-control req-input-dark" id="approveDbName"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnApproveSubmit" data-permission="control_registration_requests,approve_control_registration,control_agencies,add_control_agency"><i class="fas fa-check me-1"></i> Create Agency & Approve</button>
            </div>
        </div>
    </div>
</div>
<!-- Alert / Confirm modals -->
<div class="modal fade req-modal-dark" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content req-modal-content" dir="ltr">
            <div class="modal-body py-3 text-center"><p id="alertMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center py-2"><button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button></div>
        </div>
    </div>
</div>
<div class="modal fade req-modal-dark" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content req-modal-content" dir="ltr">
            <div class="modal-body py-3 text-center"><p id="confirmMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center py-2 gap-2"><button type="button" class="btn btn-secondary btn-sm" id="confirmCancel">Cancel</button><button type="button" class="btn btn-primary btn-sm" id="confirmOk">OK</button></div>
        </div>
    </div>
</div>
