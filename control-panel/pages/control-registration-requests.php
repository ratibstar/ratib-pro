<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control-registration-requests.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control-registration-requests.php`.
 */
/**
 * Control Panel: Registration requests - list, approve (create agency), reject
 */
require_once __DIR__ . '/../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('view_control_registration')) {
    http_response_code(403);
    die('Access denied.');
}

// If not embedded, redirect to unified layout version
if (empty($_GET['embedded'])) {
    $queryParams = http_build_query(array_intersect_key($_GET, array_flip(['status', 'plan', 'search', 'page', 'limit'])));
    header('Location: ' . pageUrl('control/registration-requests.php') . ($queryParams ? '?' . $queryParams : ''));
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) die('Control panel database unavailable.');

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';
$registerProUrl = $baseUrl . '/pages/home.php?open=register&plan=gold&years=1';

$allowedCountryIds = getAllowedCountryIds($ctrl);
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
$embNameToCountryId = [];
foreach ($countries as $__ec) {
    $enm = strtolower(trim(preg_replace('/\s+/u', ' ', (string)($__ec['name'] ?? ''))));
    if ($enm !== '') {
        $embNameToCountryId[$enm] = (int)$__ec['id'];
    }
}
$embCreatedAgencyCountryMap = [];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
$status = trim($_GET['status'] ?? '');
$planFilter = trim($_GET['plan'] ?? '');
$paymentStatusFilter = strtolower(trim((string)($_GET['payment_status'] ?? '')));
if (!in_array($paymentStatusFilter, ['paid', 'unpaid', 'pending', 'failed'], true)) {
    $paymentStatusFilter = '';
}
$search = trim($_GET['search'] ?? '');
$requestsList = [];
$totalRequests = 0;
$totalPages = 1;
$offset = 0;

$tableExists = false;
$hasCountryIdCol = false;
$chk2 = @$ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
if ($chk2 && $chk2->num_rows > 0) {
    $tableExists = true;
    $colCountry = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
    $hasCountryIdCol = ($colCountry && $colCountry->num_rows > 0);
    $conds = [];
    $embScopeIds = function_exists('getRegistrationRequestScopeCountryIds') ? getRegistrationRequestScopeCountryIds($ctrl) : null;
    $embRegViewAll = ($embScopeIds === null);
    if ($embScopeIds === []) {
        if (!$embRegViewAll) {
            $conds[] = '1=0';
        }
    } elseif (!$embRegViewAll && $embScopeIds !== null && !empty($embScopeIds) && $hasCountryIdCol) {
        $idsStr = implode(',', array_map('intval', $embScopeIds));
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
    if ($status !== '' && in_array($status, ['pending','approved','rejected'])) {
        $conds[] = "status = '" . $ctrl->real_escape_string($status) . "'";
    }
    if ($planFilter !== '' && in_array($planFilter, ['pro','gold','platinum'])) {
        $conds[] = "plan = '" . $ctrl->real_escape_string($planFilter) . "'";
    }
    $colPayStatus = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
    $hasPaymentStatusCol = ($colPayStatus && $colPayStatus->num_rows > 0);
    if ($hasPaymentStatusCol && $paymentStatusFilter !== '') {
        if ($paymentStatusFilter === 'unpaid') {
            $conds[] = "(LOWER(TRIM(COALESCE(payment_status,''))) <> 'paid')";
        } else {
            $conds[] = "LOWER(TRIM(COALESCE(payment_status,''))) = '" . $ctrl->real_escape_string($paymentStatusFilter) . "'";
        }
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
    $condsForList = $conds;
    if ($status === '') {
        $condsForList[] = "LOWER(TRIM(COALESCE(status,''))) <> 'approved'";
    }
    // Default safety: without explicit payment filter, hide unpaid paid-plans from main queue.
    if ($hasPaymentStatusCol && $paymentStatusFilter === '') {
        $condsForList[] = "(LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' OR LOWER(TRIM(COALESCE(plan,''))) = 'pro')";
    }
    $whereForList = count($condsForList) ? ' WHERE ' . implode(' AND ', $condsForList) : '';
    $where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';
    $totalRequests = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests" . $whereForList)->fetch_assoc()['c'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRequests / max(1, $limit)));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;
    $res2 = $ctrl->query("SELECT * FROM control_registration_requests" . $whereForList . " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
    if ($res2) while ($row = $res2->fetch_assoc()) $requestsList[] = $row;

    $ngMergePath = __DIR__ . '/../includes/registration_requests_ngenius_display_merge.php';
    if (is_readable($ngMergePath)) {
        require_once $ngMergePath;
        if (function_exists('registration_requests_merge_ngenius_orders_for_display')) {
            registration_requests_merge_ngenius_orders_for_display($ctrl, $requestsList);
        }
    }
    $embAids = [];
    foreach ($requestsList as $er) {
        $eid = (int)($er['created_agency_id'] ?? 0);
        if ($eid > 0) {
            $embAids[$eid] = true;
        }
    }
    if (!empty($embAids)) {
        $embColAg = @$ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
        if ($embColAg && $embColAg->num_rows > 0) {
            $embIn = implode(',', array_map('intval', array_keys($embAids)));
            $embAr = @$ctrl->query("SELECT id, country_id FROM control_agencies WHERE id IN ($embIn)");
            if ($embAr) {
                while ($ex = $embAr->fetch_assoc()) {
                    $cx = (int)($ex['country_id'] ?? 0);
                    if ($cx > 0) {
                        $embCreatedAgencyCountryMap[(int)$ex['id']] = $cx;
                    }
                }
            }
        }
    }
}
$fmtId = function($id) { return 'REQ' . str_pad((int)$id, 4, '0', STR_PAD_LEFT); };
$fmtAgencyId = function($id) { return $id ? ('AG' . str_pad((int)$id, 4, '0', STR_PAD_LEFT)) : '-'; };
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title>Registration Requests - Control Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/control-registration-requests.css'); ?>?v=<?php echo time(); ?>">
</head>
<body data-api-base="<?php echo htmlspecialchars(rtrim($apiBase, '/')); ?>" data-agencies-url="<?php echo htmlspecialchars(pageUrl('control/agencies.php')); ?>">
    <?php $__baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''); $__path = $_SERVER['REQUEST_URI'] ?? ''; $__basePath = preg_replace('#/pages/[^?]*.*$#', '', $__path) ?: ''; $__fullBase = rtrim($__baseUrl . $__basePath, '/'); ?>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($__fullBase, ENT_QUOTES); ?>" data-api-base="<?php echo htmlspecialchars($__fullBase . '/api', ENT_QUOTES); ?>" data-control="1" class="hidden"></div>
    <?php if (empty($_GET['embedded'])): ?>
    <div class="control-header">
        <div class="control-nav">
            <a href="<?php echo htmlspecialchars($registerProUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm"><i class="fas fa-external-link-alt me-1"></i> Register Pro</a>
            <a href="<?php echo pageUrl('control/dashboard.php'); ?>"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <a href="<?php echo pageUrl('select-country.php'); ?>"><i class="fas fa-globe me-1"></i> Countries</a>
            <a href="<?php echo pageUrl('control/agencies.php'); ?>"><i class="fas fa-building me-1"></i> Manage Agencies</a>
            <a href="<?php echo pageUrl('control/registration-requests.php'); ?>"><i class="fas fa-user-plus me-1"></i> Registration Requests</a>
            <a href="<?php echo pageUrl('control/support-chats.php'); ?>"><i class="fas fa-comments me-1"></i> Support Chats</a>
            <a href="<?php echo pageUrl('control/accounting.php'); ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-calculator me-1"></i> Accounting</a>
            <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-briefcase me-1"></i> Recruitment Program</a>
            <a href="<?php echo (defined('RATIB_PRO_URL') ? RATIB_PRO_URL . '?control=1&own=1' : pageUrl('control/dashboard.php')); ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-user me-1"></i> My own Program</a>
        </div>
        <div>
            <span class="text-muted me-3"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
    <?php endif; ?>

    <div id="pendingAlertBanner" class="pending-alert-banner d-none">
        <span class="pending-alert-text"><i class="fas fa-bell me-2"></i><span id="pendingAlertCount">0</span> pending registration request(s) need your attention.</span>
        <button type="button" class="btn btn-sm btn-outline-light" id="btnDismissPendingAlert">Dismiss</button>
    </div>
    <div class="content">
        <div class="table-card">
            <h2 class="mb-3"><i class="fas fa-user-plus me-2"></i>Registration Requests</h2>

            <?php
            $regBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath . '/pages/home.php';
            $regBaseWithOpen = $regBase . '?open=register';
            ?>
            <div class="reg-link-box">
                <span class="text-muted">Registration links:</span>
                <select id="regLinkSelect" class="form-control ctrl-input req-width-auto">
                    <option value="<?php echo htmlspecialchars($registerProUrl); ?>">Recommended — Gold, 1 year (N-Genius)</option>
                    <option value="<?php echo htmlspecialchars($regBaseWithOpen); ?>">Open register only (defaults to Gold)</option>
                    <option value="<?php echo htmlspecialchars($regBaseWithOpen . '&plan=pro'); ?>">Pro (non-paid inquiry)</option>
                    <option value="<?php echo htmlspecialchars($regBaseWithOpen . '&plan=gold&amount=550'); ?>">Gold $550</option>
                    <option value="<?php echo htmlspecialchars($regBaseWithOpen . '&plan=platinum&amount=600'); ?>">Platinum $600</option>
                </select>
                <input type="text" id="regLink" class="req-reg-link-input" readonly value="<?php echo htmlspecialchars($registerProUrl); ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyLink"><i class="fas fa-copy me-1"></i> Copy</button>
            </div>

            <form method="get" class="controls-bar">
                <input type="hidden" name="control" value="1">
                <input type="text" name="search" class="form-control ctrl-input req-search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="plan" class="form-control ctrl-input req-plan-select js-auto-submit">
                    <option value="">All plans</option>
                    <option value="pro" <?php echo $planFilter === 'pro' ? 'selected' : ''; ?>>Pro</option>
                    <option value="gold" <?php echo $planFilter === 'gold' ? 'selected' : ''; ?>>Gold</option>
                    <option value="platinum" <?php echo $planFilter === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                </select>
                <select name="status" class="form-control ctrl-input req-status-select js-auto-submit" title="Default hides approved (agencies under Manage Agencies)">
                    <option value="">Queue (hide approved)</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <div class="d-flex align-items-center flex-nowrap gap-1 ms-1">
                    <label for="reqLimitSelectEmb" class="text-muted small mb-0 text-nowrap">Show</label>
                    <select id="reqLimitSelectEmb" name="limit" class="form-control ctrl-input js-cp-reg-page-limit req-limit-select" title="Rows per page" autocomplete="off">
                    <option value="5" <?php echo $limit === 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                </select>
                    <span class="text-muted small mb-0 text-nowrap">entries</span>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-search me-1"></i> Go</button>
                <a href="?control=1" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fas fa-sync-alt"></i></a>
            </form>

            <?php if (!$tableExists): ?>
            <p class="text-warning">Run the migration: <code>config/control_registration_requests.sql</code></p>
            <?php else: ?>
            <p class="text-muted small mb-2">Search by Reg ID, Agency, Email, Country, or Notes. Rows stay compact; hover for full text.</p>
            <div class="req-table-wrap">
                <table class="table table-dark req-table req-table-fixed">
                    <colgroup>
                        <col class="req-col-w80"><col class="req-col-w110"><col class="req-col-w90"><col class="req-col-w80"><col class="req-col-w120"><col class="req-col-w100"><col class="req-col-w130"><col class="req-col-w90"><col class="req-col-w50"><col class="req-col-w70"><col class="req-col-w100"><col class="req-col-w80"><col class="req-col-w85"><col class="req-col-w85"><col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Reg ID</th>
                            <th>Agency</th>
                            <th>Agency ID</th>
                            <th>Country</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Site URL</th>
                            <th>Notes</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Created Agency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php $rowNum = $offset; foreach ($requestsList as $r): $rowNum++; $s = $r['status'] ?? 'pending'; $aid = (int)($r['created_agency_id'] ?? 0);
                        $embManageCid = 0;
                        if ($aid) {
                            if ($hasCountryIdCol && !empty($r['country_id']) && (int)$r['country_id'] > 0) {
                                $embManageCid = (int)$r['country_id'];
                            } elseif (!empty($embCreatedAgencyCountryMap[$aid])) {
                                $embManageCid = (int)$embCreatedAgencyCountryMap[$aid];
                            } else {
                                $embNk = strtolower(trim(preg_replace('/\s+/u', ' ', (string)($r['country_name'] ?? ''))));
                                if ($embNk !== '' && isset($embNameToCountryId[$embNk])) {
                                    $embManageCid = (int)$embNameToCountryId[$embNk];
                                }
                            }
                        }
                        $embJsonRow = $r;
                        if ($embManageCid > 0) {
                            $embJsonRow['_manage_country_id'] = $embManageCid;
                        }
                        ?>
                        <tr data-id="<?php echo (int)$r['id']; ?>" data-json="<?php echo htmlspecialchars(base64_encode(json_encode($embJsonRow))); ?>">
                            <td><strong><?php echo $fmtId($r['id']); ?></strong></td>
                            <td class="td-agency" title="<?php echo htmlspecialchars($r['agency_name'] ?? ''); ?>"><?php echo htmlspecialchars($r['agency_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['agency_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(($r['country_name'] ?? '') ?: '-'); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>"><?php echo htmlspecialchars($r['contact_email'] ?? '-'); ?></a></td>
                            <td><?php echo htmlspecialchars($r['contact_phone'] ?? '-'); ?></td>
                            <td class="td-siteurl" title="<?php echo htmlspecialchars($r['desired_site_url'] ?? ''); ?>"><?php echo htmlspecialchars($r['desired_site_url'] ?? '-'); ?></td>
                            <td class="td-notes" title="<?php echo htmlspecialchars($r['notes'] ?? ''); ?>"><?php echo htmlspecialchars($r['notes'] ?? '-'); ?></td>
                            <td><?php
                                $planDisp = trim((string) ($r['plan'] ?? ''));
                                echo htmlspecialchars($planDisp !== '' ? $planDisp : '-');
                            ?></td>
                            <td><?php echo isset($r['plan_amount']) && $r['plan_amount'] !== null && $r['plan_amount'] !== '' ? '$' . number_format((float)$r['plan_amount']) : '-'; ?></td>
                            <td>
                                <?php 
                                $payStatus = $r['payment_status'] ?? null;
                                $payMethod = $r['payment_method'] ?? null;
                                if ($payStatus || $payMethod): 
                                    $payStatusText = $payStatus ? ucfirst($payStatus) : 'N/A';
                                    $payMethodText = $payMethod ? ' (' . htmlspecialchars($payMethod) . ')' : '';
                                    $payBadgeClass = ($payStatus === 'paid') ? 'badge-success' : (($payStatus === 'unpaid') ? 'badge-warning' : 'badge-secondary');
                                ?>
                                    <span class="badge <?php echo $payBadgeClass; ?>" title="Payment: <?php echo htmlspecialchars($payStatusText . $payMethodText); ?>">
                                        <?php echo htmlspecialchars($payStatusText); ?><?php echo $payMethodText; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?php echo $s; ?>"><?php echo ucfirst($s); ?></span></td>
                            <td><?php echo isset($r['created_at']) ? substr($r['created_at'], 0, 10) : '-'; ?></td>
                            <td><?php
                            if ($aid) {
                                if ($embManageCid > 0) {
                                    $embQs = ['control' => '1', 'country_id' => (string)$embManageCid];
                                } else {
                                    $embQs = ['control' => '1', 'agency_id' => (string)(int)$aid];
                                }
                                $embAgUrl = pageUrl('control/agencies.php') . '?' . http_build_query($embQs);
                                echo '<a href="' . htmlspecialchars($embAgUrl) . '" target="_parent">' . htmlspecialchars($fmtAgencyId($aid)) . '</a>';
                            } else {
                                echo '-';
                            }
                            ?></td>
                            <td class="action-btns">
                                <button type="button" class="btn btn-sm btn-outline-info btn-view" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($embJsonRow))); ?>" data-permission="control_registration_requests,view_control_registration">View</button>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($embJsonRow))); ?>" data-permission="control_registration_requests,edit_control_registration,approve_control_registration">Edit</button>
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

            <div class="pagination-bar d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-3 border-top border-secondary">
                <span class="text-muted">Showing <?php echo $totalRequests ? ($offset + 1) : 0; ?>-<?php echo min($offset + $limit, $totalRequests); ?> of <?php echo $totalRequests; ?> <span class="text-muted opacity-75">(<?php echo (int)$limit; ?> per page)</span></span>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($page > 1): ?><a href="?control=1&page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&status=<?php echo urlencode($status); ?>&plan=<?php echo urlencode($planFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary">Previous</a><?php endif; ?>
                    <span>Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?><a href="?control=1&page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&status=<?php echo urlencode($status); ?>&plan=<?php echo urlencode($planFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View details modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content req-modal-dark" dir="ltr">
                <div class="modal-header req-modal-header">
                    <h5 class="modal-title">Request Details <span id="viewReqId" class="text-muted"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-borderless req-view-table">
                        <tr><td class="text-muted req-cell-label">Reg ID</td><td id="viewId"></td></tr>
                        <tr><td class="text-muted">Created</td><td id="viewCreated"></td></tr>
                        <tr><td class="text-muted">Agency</td><td id="viewAgency"></td></tr>
                        <tr><td class="text-muted">Agency ID</td><td id="viewAgencyIdUser"></td></tr>
                        <tr><td class="text-muted">Country</td><td id="viewCountry"></td></tr>
                        <tr><td class="text-muted">Email</td><td id="viewEmail"></td></tr>
                        <tr><td class="text-muted">Phone</td><td id="viewPhone"></td></tr>
                        <tr><td class="text-muted">Site URL</td><td id="viewSiteUrl" class="text-break"></td></tr>
                        <tr><td class="text-muted">Notes</td><td id="viewNotes" class="text-break req-pre-wrap"></td></tr>
                        <tr><td class="text-muted">Plan</td><td id="viewPlan"></td></tr>
                        <tr><td class="text-muted">Amount</td><td id="viewAmount"></td></tr>
                        <tr><td class="text-muted">Payment</td><td id="viewPayment"></td></tr>
                        <tr><td class="text-muted">Status</td><td id="viewStatus"></td></tr>
                        <tr><td class="text-muted">Created Agency</td><td id="viewAgencyId"></td></tr>
                        <tr><td class="text-muted req-divider-top" colspan="2"><small>Additional</small></td></tr>
                        <tr><td class="text-muted">Years</td><td id="viewYears"></td></tr>
                        <tr><td class="text-muted">Last updated</td><td id="viewUpdated"></td></tr>
                        <tr><td class="text-muted">IP</td><td id="viewIp"></td></tr>
                    </table>
                </div>
                <div class="modal-footer req-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-warning" id="btnEditFromView" data-permission="control_registration_requests,edit_control_registration"><i class="fas fa-edit me-1"></i> Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content req-modal-dark" dir="ltr">
                <div class="modal-header req-modal-header">
                    <h5 class="modal-title">Edit Request <span id="editReqId" class="text-muted"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editId">
                    <div class="mb-3">
                        <label class="form-label">Agency *</label>
                        <input type="text" class="form-control req-input-dark" id="editAgencyName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Agency ID</label>
                        <input type="text" class="form-control req-input-dark" id="editAgencyId" maxlength="128">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" class="form-control req-input-dark" id="editCountryName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control req-input-dark" id="editContactEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control req-input-dark" id="editContactPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Site URL</label>
                        <input type="url" class="form-control req-input-dark" id="editSiteUrl">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control req-input-dark" id="editNotes" rows="3"></textarea>
                    </div>
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
                    <hr class="req-hr">
                    <p class="text-muted small mb-2">Read-only</p>
                    <div class="mb-3">
                        <label class="form-label">Created</label>
                        <input type="text" class="form-control req-input-dark" id="editCreatedAtRo" readonly tabindex="-1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control req-input-dark" id="editStatusRo" readonly tabindex="-1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Created Agency</label>
                        <div id="editCreatedAgencyRo" class="form-control req-input-dark req-created-agency-ro"></div>
                    </div>
                </div>
                <div class="modal-footer req-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnEditSave">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve modal: create agency form -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" dir="ltr">
                <div class="modal-header">
                    <h5 class="modal-title">Approve & Create Agency</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="approveRequestId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country *</label>
                            <select class="form-control" id="approveCountryId">
                                <option value="">-- Select --</option>
                                <?php foreach ($countries as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" id="approveName" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" id="approveSlug" placeholder="auto">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site URL *</label>
                            <input type="url" class="form-control" id="approveSiteUrl" placeholder="https://...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">DB Host</label>
                            <input type="text" class="form-control" id="approveDbHost" value="localhost">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">DB Port</label>
                            <input type="text" class="form-control" id="approveDbPort" value="3306" inputmode="numeric">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">DB User *</label>
                            <input type="text" class="form-control" id="approveDbUser">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">DB Pass *</label>
                            <input type="password" class="form-control" id="approveDbPass">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DB Name *</label>
                            <input type="text" class="form-control" id="approveDbName">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnApproveSubmit"><i class="fas fa-check me-1"></i> Create Agency & Approve</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content req-modal-dark" dir="ltr">
                <div class="modal-body py-3 text-center">
                    <p id="alertMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer justify-content-center py-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content req-modal-dark" dir="ltr">
                <div class="modal-body py-3 text-center">
                    <p id="confirmMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer justify-content-center py-2 gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" id="confirmCancel">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmOk">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/control/registration-requests-page.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
<?php require_once __DIR__ . '/../includes/control_pending_reg_alert.php'; ?>
</body>
</html>
