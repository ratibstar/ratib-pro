<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/agencies-content.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/agencies-content.php`.
 */
/**
 * Agencies table content - shared by control/agencies.php and control-agencies.php (embedded)
 * Expects: $ctrl (DB connection), auth already done
 */
$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';
$countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$isControlSuperAdminUi = strtolower(trim((string) ($_SESSION['control_username'] ?? ''))) === 'admin';

// EN: URL helper functions for safe “open agency” behavior and SSO query composition.
// AR: دوال مساعدة لبناء روابط "فتح الوكالة" بشكل آمن وتكوين معاملات SSO.
if (!function_exists('agency_site_url_invalid_for_ratib_pro_open')) {
    /** True if stored site_url must not be used for “Open” into Ratib Pro (e.g. points at this control panel). */
    function agency_site_url_invalid_for_ratib_pro_open($url) {
        $u = trim((string) $url);
        if ($u === '') {
            return true;
        }
        if (stripos($u, 'control-panel') !== false) {
            return true;
        }
        if (preg_match('#/pages/control/#i', $u)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('agency_build_open_sso_url')) {
    /** Append control=1&agency_id= to an absolute URL, merging any existing query string (avoids …php?x=1/?control=…). */
    function agency_build_open_sso_url($absoluteBase, $agencyId) {
        $p = parse_url(trim((string) $absoluteBase));
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return '';
        }
        $path = isset($p['path']) && $p['path'] !== '' ? $p['path'] : '/';
        $q = [];
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
        }
        $q['control'] = '1';
        $q['agency_id'] = (int) $agencyId;
        $port = isset($p['port']) ? ':' . (int) $p['port'] : '';
        return $p['scheme'] . '://' . $p['host'] . $port . $path . '?' . http_build_query($q);
    }
}

if (!function_exists('agency_open_site_url_is_different_host')) {
    /**
     * Use stored site_url for “Open” only when it points at another host (real custom domain).
     * If site_url shares the same host as RATIB_PRO_URL, the path is often wrong (e.g. all rows set to /bangladesh/),
     * so we prefer /{country_slug}/ built from control_countries instead.
     */
    function agency_open_site_url_is_different_host($ratibBase, $siteUrl) {
        $ratibBase = trim((string) $ratibBase);
        $siteUrl = trim((string) $siteUrl);
        if ($ratibBase === '' || $siteUrl === '' || !preg_match('/^https?:\/\//i', $siteUrl)) {
            return false;
        }
        $h1 = @parse_url(rtrim($ratibBase, '/'), PHP_URL_HOST);
        $h2 = @parse_url($siteUrl, PHP_URL_HOST);
        if (!$h1 || !$h2) {
            return false;
        }
        $h1 = strtolower((string) $h1);
        $h2 = strtolower((string) $h2);
        if ($h1 === $h2) {
            return false;
        }
        if (strncmp($h1, 'www.', 4) === 0 && substr($h1, 4) === $h2) {
            return false;
        }
        if (strncmp($h2, 'www.', 4) === 0 && substr($h2, 4) === $h1) {
            return false;
        }
        return true;
    }
}

// EN: Load country scope and list data according to permission constraints and active filters.
// AR: تحميل نطاق الدول وقائمة الوكالات حسب صلاحيات المستخدم والفلاتر الحالية.
$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$countries = [];
$countryMap = [];
$countrySlugMap = [];
$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if ($chk && $chk->num_rows > 0) {
    $countrySql = "SELECT id, name, slug FROM control_countries WHERE is_active = 1 ORDER BY sort_order, name";
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $countrySql = "SELECT id, name, slug FROM control_countries WHERE id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ") AND is_active = 1 ORDER BY sort_order, name";
    } elseif ($allowedCountryIds === []) {
        $countrySql = "SELECT id, name FROM control_countries WHERE 1=0";
    }
    $res = $ctrl->query($countrySql);
    if ($res) while ($row = $res->fetch_assoc()) {
        $countries[] = $row;
        $countryMap[(int)$row['id']] = $row['name'];
        $countrySlugMap[(int)$row['id']] = $row['slug'] ?? '';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 5)));
$search = trim($_GET['search'] ?? '');
$agencyIdFilter = isset($_GET['agency_id']) && ctype_digit($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
$countryIdGet = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : $countryId;
if ($countryIdGet) $countryId = $countryIdGet;
$offset = ($page - 1) * $limit;
$agenciesList = [];
$totalAgencies = 0;
$hasCountryId = false;
try {
    $chk2 = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
    if ($chk2 && $chk2->num_rows > 0) {
        $cols = $ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
        $hasCountryId = ($cols && $cols->num_rows > 0);
        $where = [];
        $whereA = [];
        $skipCountryFilter = ($agencyIdFilter > 0 && $allowedCountryIds !== null && !empty($allowedCountryIds));
        if ($allowedCountryIds === []) {
            $where[] = '1=0';
            $whereA[] = '1=0';
        } elseif ($hasCountryId && !$skipCountryFilter && $allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $idsStr = implode(',', array_map('intval', $allowedCountryIds));
            $where[] = "country_id IN ($idsStr)";
            $whereA[] = "a.country_id IN ($idsStr)";
        }
        if ($hasCountryId && $countryId > 0) { $where[] = 'country_id = ' . (int)$countryId; $whereA[] = 'a.country_id = ' . (int)$countryId; }
        if ($agencyIdFilter > 0) { $where[] = 'id = ' . (int)$agencyIdFilter; $whereA[] = 'a.id = ' . (int)$agencyIdFilter; }
        if ($search !== '') { $esc = $ctrl->real_escape_string($search); $where[] = "(name LIKE '%{$esc}%' OR slug LIKE '%{$esc}%' OR site_url LIKE '%{$esc}%')"; $whereA[] = "(a.name LIKE '%{$esc}%' OR a.slug LIKE '%{$esc}%' OR a.site_url LIKE '%{$esc}%')"; }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $whereClauseA = $whereA ? ' WHERE ' . implode(' AND ', $whereA) : '';
        $countRes = $ctrl->query("SELECT COUNT(*) as c FROM control_agencies" . $whereClause);
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $totalAgencies = (int) ($countRow['c'] ?? 0);
        } else {
            $totalAgencies = 0;
        }
        if ($hasCountryId) {
            $sql = "SELECT a.*, c.name as country_name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id" . $whereClauseA . " ORDER BY a.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        } else {
            $sql = "SELECT * FROM control_agencies" . $whereClause . " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }
        $res2 = $ctrl->query($sql);
        if ($res2) while ($row = $res2->fetch_assoc()) {
            unset($row['db_pass']);
            $agenciesList[] = $row;
        }
    }
} catch (Throwable $e) { /* ignore */ }
$totalPages = max(1, (int)ceil($totalAgencies / $limit));
$fmtId = function($id) { return 'AG' . str_pad((int)$id, 4, '0', STR_PAD_LEFT); };

// EN: Compute dashboard-like agency health metrics for cards and notices in agencies view.
// AR: حساب مؤشرات صحة الوكالات (للبطاقات والتنبيهات) داخل شاشة إدارة الوكالات.
// Scope for summary cards/health should follow current view (per country when selected).
$statsWhere = [];
if ($allowedCountryIds === []) {
    $statsWhere[] = '1=0';
} elseif ($hasCountryId && $allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $statsWhere[] = 'country_id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
}
if ($hasCountryId && $countryId > 0) {
    $statsWhere[] = 'country_id = ' . (int) $countryId;
}
if ($agencyIdFilter > 0) {
    $statsWhere[] = 'id = ' . (int) $agencyIdFilter;
}
$statsWhereClause = $statsWhere ? (' WHERE ' . implode(' AND ', $statsWhere)) : '';

// Per-country agency counts for the country cards view
$countryAgencyCount = [];
try {
    $chkAg = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
    $colAg = $chkAg && $chkAg->num_rows > 0 ? $ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'") : null;
    if ($colAg && $colAg->num_rows > 0 && !empty($countries)) {
        $idsStr = implode(',', array_map(function($c) { return (int)$c['id']; }, $countries));
        $resCount = $ctrl->query("SELECT country_id, COUNT(*) as cnt FROM control_agencies WHERE country_id IN ($idsStr) GROUP BY country_id");
        if ($resCount) while ($row = $resCount->fetch_assoc()) $countryAgencyCount[(int)$row['country_id']] = (int)$row['cnt'];
    }
} catch (Throwable $e) { /* ignore */ }

// Check if renewal_date / is_suspended columns exist
$hasRenewalDate = false;
$hasIsSuspended = false;
$colsCheck = $ctrl->query("SHOW COLUMNS FROM control_agencies");
if ($colsCheck) {
    while ($c = $colsCheck->fetch_assoc()) {
        if ($c['Field'] === 'renewal_date') $hasRenewalDate = true;
        if ($c['Field'] === 'is_suspended') $hasIsSuspended = true;
    }
    $colsCheck->close();
}

// Backfill renewal_date and auto-suspend agencies past grace period (15 days after renewal)
if ($hasRenewalDate) {
    @$ctrl->query("UPDATE control_agencies SET renewal_date = DATE_ADD(DATE(COALESCE(created_at, NOW())), INTERVAL 1 YEAR) WHERE renewal_date IS NULL");
}
if ($hasIsSuspended) {
    @$ctrl->query("UPDATE control_agencies SET is_suspended = 1 WHERE renewal_date IS NOT NULL AND DATE_ADD(renewal_date, INTERVAL 15 DAY) < CURDATE() AND (is_suspended = 0 OR is_suspended IS NULL)");
}

$renewalDate = function($row) use ($hasRenewalDate) {
    if ($hasRenewalDate && !empty($row['renewal_date'])) return substr($row['renewal_date'], 0, 10);
    $created = $row['created_at'] ?? null;
    if (!$created) return '-';
    $d = date_create($created);
    return $d ? $d->modify('+1 year')->format('Y-m-d') : '-';
};

// Renewal alert: agencies due in 10 days or less
$renewalAlerts = [];
if ($hasRenewalDate) {
    $alertConds = [];
    if ($statsWhereClause !== '') {
        $alertConds[] = substr($statsWhereClause, 7);
    }
    $alertConds[] = "renewal_date IS NOT NULL";
    $alertConds[] = "renewal_date <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)";
    $alertConds[] = "renewal_date >= CURDATE()";
    $alertConds[] = "(is_suspended = 0 OR is_suspended IS NULL)";
    $alertRes = $ctrl->query("SELECT id, name, renewal_date FROM control_agencies WHERE " . implode(' AND ', $alertConds) . " ORDER BY renewal_date ASC LIMIT 20");
    if ($alertRes) while ($ar = $alertRes->fetch_assoc()) $renewalAlerts[] = $ar;
}
$linkedAgenciesCount = 0;
$unlinkedAgenciesCount = 0;
$activeAgenciesCount = 0;
$suspendedAgenciesCount = 0;
$inactiveAgenciesCount = 0;
$lastAgencyControlEvent = '';
try {
    $countsRow = $ctrl->query("SELECT 
        SUM(CASE WHEN tenant_id IS NOT NULL AND tenant_id > 0 THEN 1 ELSE 0 END) AS linked_count,
        SUM(CASE WHEN tenant_id IS NULL OR tenant_id <= 0 THEN 1 ELSE 0 END) AS unlinked_count
        FROM control_agencies" . $statsWhereClause);
    if ($countsRow && ($cr = $countsRow->fetch_assoc())) {
        $linkedAgenciesCount = (int) ($cr['linked_count'] ?? 0);
        $unlinkedAgenciesCount = (int) ($cr['unlinked_count'] ?? 0);
    }
    $statusSql = $hasIsSuspended
        ? "SELECT
            SUM(CASE WHEN COALESCE(is_active, 0) = 1 AND COALESCE(is_suspended, 0) = 0 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN COALESCE(is_suspended, 0) = 1 THEN 1 ELSE 0 END) AS suspended_count,
            SUM(CASE WHEN COALESCE(is_active, 0) = 0 AND COALESCE(is_suspended, 0) = 0 THEN 1 ELSE 0 END) AS inactive_count
           FROM control_agencies" . $statsWhereClause
        : "SELECT
            SUM(CASE WHEN COALESCE(is_active, 0) = 1 THEN 1 ELSE 0 END) AS active_count,
            0 AS suspended_count,
            SUM(CASE WHEN COALESCE(is_active, 0) = 0 THEN 1 ELSE 0 END) AS inactive_count
           FROM control_agencies" . $statsWhereClause;
    $statusRow = $ctrl->query($statusSql);
    if ($statusRow && ($sr = $statusRow->fetch_assoc())) {
        $activeAgenciesCount = (int) ($sr['active_count'] ?? 0);
        $suspendedAgenciesCount = (int) ($sr['suspended_count'] ?? 0);
        $inactiveAgenciesCount = (int) ($sr['inactive_count'] ?? 0);
    }
    $evTable = $ctrl->query("SHOW TABLES LIKE 'system_events'");
    if ($evTable && $evTable->num_rows > 0) {
        $evRes = $ctrl->query("SELECT created_at, event_type, message
            FROM system_events
            WHERE event_type IN ('AGENCY_LINKED_TENANT', 'AGENCY_CONTROL_OPENED', 'BULK_OPERATION_COMPLETED')
            ORDER BY id DESC LIMIT 1");
        if ($evRes && ($ev = $evRes->fetch_assoc())) {
            $lastAgencyControlEvent = trim((string) ($ev['created_at'] ?? '')) . ' · ' . trim((string) ($ev['event_type'] ?? ''));
        }
    }
} catch (Throwable $e) { /* ignore */ }
$formAction = pageUrl('control/agencies.php');
$showCountryCards = ($countryId === 0 && !$agencyIdFilter);
$agencyFilterCountryName = '';
if ($agencyIdFilter > 0) {
    if (!empty($agenciesList)) {
        $f0 = $agenciesList[0];
        $agencyFilterCountryName = trim((string)($f0['country_name'] ?? ''));
        if ($agencyFilterCountryName === '' && !empty($f0['country_id'])) {
            $agencyFilterCountryName = trim((string)($countryMap[(int)$f0['country_id']] ?? ''));
        }
    } elseif ($hasCountryId) {
        $dr = @$ctrl->query('SELECT c.name AS country_name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id WHERE a.id = ' . (int)$agencyIdFilter . ' LIMIT 1');
        if ($dr && ($dx = $dr->fetch_assoc())) {
            $agencyFilterCountryName = trim((string)($dx['country_name'] ?? ''));
        }
    }
}
?>
<!-- EN: Main agencies container switches between country-card mode and list mode.
     AR: حاوية الوكالات الرئيسية تعرض إما بطاقات الدول أو جدول الوكالات حسب الفلتر. -->
<div class="agencies-table-card" id="tableCard" data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-country-id="<?php echo (int)$countryId; ?>">
    <h2 class="mb-4"><i class="fas fa-building me-2"></i>Manage Agencies<?php if ($agencyIdFilter): ?> <small class="text-muted">(<?php echo htmlspecialchars($fmtId($agencyIdFilter)); ?><?php if ($agencyFilterCountryName !== ''): ?> — <?php echo htmlspecialchars($agencyFilterCountryName); ?><?php endif; ?>)</small><?php elseif ($countryId): ?> <small class="text-muted">— <?php echo htmlspecialchars($countryMap[$countryId] ?? 'Country'); ?></small><?php endif; ?></h2>

    <?php if ($showCountryCards): ?>
    <!-- Country cards: click a country to see its agencies -->
    <p class="text-muted mb-4">Select a country to view and manage its agencies.</p>
    <div class="agencies-country-cards" id="countryCards">
        <?php foreach ($countries as $c):
            $cid = (int)$c['id'];
            $count = $countryAgencyCount[$cid] ?? 0;
            $cardUrl = $formAction . '?control=1&country_id=' . $cid;
        ?>
        <a href="<?php echo htmlspecialchars($cardUrl); ?>" class="agencies-country-card" data-country-id="<?php echo $cid; ?>">
            <div class="agencies-country-card-icon"><i class="fas fa-globe-americas"></i></div>
            <div class="agencies-country-card-name"><?php echo htmlspecialchars($c['name']); ?></div>
            <div class="agencies-country-card-count"><?php echo $count; ?> <?php echo $count === 1 ? 'agency' : 'agencies'; ?></div>
        </a>
        <?php endforeach; ?>
        <a href="<?php echo htmlspecialchars(pageUrl('control/countries.php') . '?control=1'); ?>" class="agencies-country-card agencies-country-card-add" title="Add a new country" data-permission="control_countries,add_control_country">
            <div class="agencies-country-card-icon"><i class="fas fa-plus"></i></div>
            <div class="agencies-country-card-name">Add new country</div>
            <div class="agencies-country-card-count">Create country</div>
        </a>
    </div>
    <?php if (empty($countries)): ?>
    <div class="alert alert-info">No countries available. Add countries first from <a href="<?php echo htmlspecialchars(pageUrl('control/countries.php')); ?>" data-permission="control_countries,add_control_country">Manage Countries</a>.</div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Agencies list for selected country -->
    <div class="agencies-list-view" id="agenciesListView">
    <div class="agencies-status-cards mb-3">
        <div class="agencies-status-card agencies-status-card-total"><span>Total</span><strong><?php echo (int) $totalAgencies; ?></strong></div>
        <div class="agencies-status-card agencies-status-card-active"><span>Active</span><strong><?php echo (int) $activeAgenciesCount; ?></strong></div>
        <div class="agencies-status-card agencies-status-card-suspended"><span>Suspended</span><strong><?php echo (int) $suspendedAgenciesCount; ?></strong></div>
        <div class="agencies-status-card agencies-status-card-inactive"><span>Inactive</span><strong><?php echo (int) $inactiveAgenciesCount; ?></strong></div>
    </div>
    <div class="agencies-health-card mb-3">
        <strong>Connection Health</strong>
        <span class="ms-3">Linked: <strong class="agencies-health-linked"><?php echo (int) $linkedAgenciesCount; ?></strong></span>
        <span class="ms-3">Unlinked: <strong class="<?php echo $unlinkedAgenciesCount > 0 ? 'agencies-health-unlinked-bad' : 'agencies-health-unlinked-good'; ?>"><?php echo (int) $unlinkedAgenciesCount; ?></strong></span>
        <span class="ms-3 text-muted"><?php echo $lastAgencyControlEvent !== '' ? ('Last event: ' . htmlspecialchars($lastAgencyControlEvent)) : 'Last event: none'; ?></span>
        <?php if ($isControlSuperAdminUi): ?>
        <button type="button" class="btn btn-sm btn-outline-danger ms-3" id="btnRepairTenantLinks" data-permission="control_agencies,delete_control_agency">Repair Missing Tenant Link</button>
        <?php endif; ?>
    </div>
    <a href="<?php echo htmlspecialchars($formAction . '?control=1'); ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i> Back to countries</a>
    <?php if ($agencyIdFilter > 0): ?>
    <div class="alert alert-info py-2 mb-3 agencies-opened-by-id-banner" role="status">
        <i class="fas fa-globe-americas me-2"></i>
        <strong><?php echo htmlspecialchars($fmtId($agencyIdFilter)); ?></strong>
        <?php if ($agencyFilterCountryName !== ''): ?>
            <span class="text-muted">belongs to</span> <strong><?php echo htmlspecialchars($agencyFilterCountryName); ?></strong>
        <?php elseif ($totalAgencies > 0): ?>
            <span class="text-muted">(country not linked on this record)</span>
        <?php else: ?>
            <span class="text-muted">No agency row found for this ID (check access scope or ID).</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!$hasCountryId && $totalAgencies > 0): ?>
    <div class="alert alert-warning py-2 mb-3 alert-agency-country-warn">
        <i class="fas fa-exclamation-triangle me-1"></i> Country column not found. Run <code>config/control_tables_migration_countries.sql</code> in phpMyAdmin to enable it.
    </div>
    <?php endif; ?>
    <?php if (!empty($renewalAlerts)): ?>
    <div class="alert py-2 mb-3 alert-agency-renewal">
        <i class="fas fa-bell me-1"></i> <strong>Renewal alert:</strong> The following agencies have renewal due within 10 days. Please arrange payment to avoid suspension. After the renewal date, a 15-day grace period applies; agencies not paid will be auto-suspended.
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($renewalAlerts as $a): ?>
            <li><strong><?php echo htmlspecialchars($a['name'] ?? 'Agency'); ?></strong> — renewal <?php echo htmlspecialchars(substr($a['renewal_date'], 0, 10)); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <form method="get" action="<?php echo htmlspecialchars($formAction); ?>" class="agencies-controls-bar" id="filterForm">
        <input type="hidden" name="control" value="1">
        <?php if ($agencyIdFilter): ?><input type="hidden" name="agency_id" value="<?php echo (int)$agencyIdFilter; ?>"><?php endif; ?>
        <?php if (count($countries)): ?>
        <select name="country_id" id="agenciesCountrySelect" class="form-select agencies-select-country">
            <option value="">All Countries</option>
            <?php foreach ($countries as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo $countryId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?><input type="hidden" name="country_id" value="<?php echo $countryId; ?>"><?php endif; ?>
        <input type="text" name="search" class="agencies-search-input" placeholder="Search name, slug, URL..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="button" class="btn btn-control agencies-btn-control btn-primary" id="btnAdd" data-permission="control_agencies,add_control_agency"><i class="fas fa-plus me-1"></i> Add Agency</button>
        <button type="submit" class="btn btn-control agencies-btn-control btn-secondary"><i class="fas fa-search me-1"></i> Search</button>
    </form>
    <div class="agencies-controls-bar" id="agenciesBulkActions">
        <button type="button" class="btn btn-control agencies-btn-control btn-success" id="btnBulkActivate" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-check me-1"></i> Bulk Activate</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-inactive" id="btnBulkDeactivate" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-power-off me-1"></i> Bulk Inactive</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-warning" id="btnBulkSuspend" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-pause-circle me-1"></i> Bulk Suspend</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-danger" id="btnBulkDelete" disabled data-permission="control_agencies,delete_control_agency"><i class="fas fa-trash me-1"></i> Bulk Delete</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-info" id="btnBulkSync" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-sync me-1"></i> Bulk Sync</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-secondary" id="btnBulkRebuildDb" disabled data-permission="control_agencies,delete_control_agency"><i class="fas fa-database me-1"></i> Bulk Rebuild DB</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-secondary" id="btnBulkRunMigration" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-tools me-1"></i> Bulk Run Migration</button>
        <button type="button" class="btn btn-control agencies-btn-control btn-secondary" id="btnBulkTestDbConnection" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-plug me-1"></i> Bulk Test DB Connection</button>
    </div>
    <div class="form-check mb-3" title="<?php echo $isControlSuperAdminUi ? 'Allows controlled override for suspended-tenant bulk operations.' : 'Only SUPER_ADMIN can enable override_suspended.'; ?>">
        <input class="form-check-input" type="checkbox" value="1" id="bulkOverrideSuspended" <?php echo $isControlSuperAdminUi ? '' : 'disabled'; ?>>
        <label class="form-check-label" for="bulkOverrideSuspended">
            Override suspended-tenant guard for this bulk run (SUPER_ADMIN)
        </label>
        <?php if (!$isControlSuperAdminUi): ?>
        <div class="form-text text-muted">Locked by role policy: requires SUPER_ADMIN.</div>
        <?php endif; ?>
    </div>
    <div class="alert alert-secondary py-2 mb-3 d-none" id="bulkProgressBox">
        <strong>Bulk Progress:</strong> <span id="bulkProgressText">Starting...</span>
    </div>
    <div class="agencies-audit-card mb-3" id="bulkAuditPanel">
        <div class="agencies-audit-body py-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Bulk Execution Audit</strong>
                <small class="text-muted">Latest SSE events</small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Action</th>
                            <th>Total</th>
                            <th>Success</th>
                            <th>Failed</th>
                            <th>Duration</th>
                            <th>Request ID</th>
                        </tr>
                    </thead>
                    <tbody id="bulkAuditBody">
                        <tr><td colspan="8" class="text-muted">No bulk events yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="<?php echo $limit > 5 ? 'agencies-table-scroll' : ''; ?>">
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Country</th>
                    <th>Site URL</th>
                    <th>DB Host</th>
                    <th>DB Port</th>
                    <th>DB User</th>
                    <th>DB Name</th>
                    <th>Created</th>
                    <th>Renewal</th>
                    <th>Status</th>
                    <th><input type="checkbox" id="selectAll" title="Select all"></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
<?php foreach ($agenciesList as $r):
                    $isSuspended = ($hasIsSuspended && !empty($r['is_suspended']));
                    $isActive = (($r['is_active'] ?? 1) == 1);
                    $rowClass = $isSuspended ? 'row-suspended' : ($isActive ? 'row-active' : 'row-inactive');
?>
                <tr class="<?php echo htmlspecialchars($rowClass); ?>">
                    <td><?php echo $fmtId($r['id']); ?></td>
                    <td><?php echo htmlspecialchars($r['name'] ?? $r['agency_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['slug'] ?? '-'); ?></td>
                    <td><?php
$cid = isset($r['country_id']) ? (int)$r['country_id'] : 0;
$cname = ($cid && isset($countryMap[$cid])) ? $countryMap[$cid] : (trim($r['country_name'] ?? '') ?: trim($r['country'] ?? ''));
echo htmlspecialchars($cname ?: '-');
?></td>
                    <td class="agencies-url-cell" title="<?php echo htmlspecialchars($r['site_url'] ?? ''); ?>"><?php echo htmlspecialchars(($r['site_url'] ?? '') ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['db_host'] ?? '-'); ?></td>
                    <td><?php echo $r['db_port'] ?? '-'; ?></td>
                    <td><?php echo htmlspecialchars($r['db_user'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['db_name'] ?? '-'); ?></td>
                    <td><?php echo isset($r['created_at']) ? substr($r['created_at'], 0, 10) : '-'; ?></td>
                    <td><?php echo $renewalDate($r); ?></td>
                    <td><span class="badge <?php
if ($isSuspended) { echo 'badge-suspended'; } elseif ($isActive) { echo 'badge-active'; } else { echo 'badge-inactive'; }
?>"><?php echo $isSuspended ? 'Suspended' : ($isActive ? 'Active' : 'Inactive'); ?></span></td>
                    <td><input type="checkbox" class="row-check" name="agency_ids[]" value="<?php echo (int)$r['id']; ?>" data-id="<?php echo (int)$r['id']; ?>"></td>
                    <td class="action-btns">
                        <?php
                            $cid = isset($r['country_id']) ? (int)$r['country_id'] : 0;
                            $cslug = isset($countrySlugMap[$cid]) ? trim($countrySlugMap[$cid]) : '';
                            // Base for Ratib Pro root and country slug
                            $ratibBase = rtrim(defined('RATIB_PRO_URL') ? RATIB_PRO_URL : (defined('SITE_URL') ? SITE_URL : ''), '/');
                            if ($ratibBase === '' && isset($_SERVER['HTTP_HOST'])) {
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $ratibBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
                            }
                            // Country root → dashboard.php (see root .htaccess); config seeds tenant DB + control SSO when control session is present.
                            $openQs = 'control=1&agency_id=' . (int)$r['id'];
                            $siteBaseRaw = trim((string)($r['site_url'] ?? ''));
                            $hasSiteUrlFormat = $siteBaseRaw !== '' && preg_match('/^https?:\/\/.+/i', $siteBaseRaw);
                            $useStoredSiteForOpen = $hasSiteUrlFormat && !agency_site_url_invalid_for_ratib_pro_open($siteBaseRaw);
                            $openUrl = '';
                            $openViaRemoteSiteUrl = false;
                            if ($useStoredSiteForOpen && agency_open_site_url_is_different_host($ratibBase, $siteBaseRaw)) {
                                $openUrl = agency_build_open_sso_url($siteBaseRaw, (int)$r['id']);
                                if ($openUrl !== '') {
                                    $openViaRemoteSiteUrl = true;
                                }
                            }
                            if ($openUrl === '') {
                                if ($cslug !== '' && $ratibBase !== '') {
                                    $openUrl = rtrim($ratibBase, '/') . '/' . rawurlencode($cslug) . '/?' . $openQs;
                                } elseif ($ratibBase !== '') {
                                    $openUrl = rtrim($ratibBase, '/') . '/pages/dashboard.php?' . $openQs;
                                } else {
                                    $openUrl = pageUrl('control/dashboard.php') . '?' . $openQs;
                                }
                            }
                            $openTitle = $openViaRemoteSiteUrl
                                ? 'Open agency program (custom site URL)'
                                : (($cslug !== '' && $ratibBase !== '') ? ('Open Ratib Pro (' . $cslug . ')') : 'Open Ratib Pro');
                        ?><?php if ($openUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($openUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success" title="<?php echo htmlspecialchars($openTitle, ENT_QUOTES, 'UTF-8'); ?>" data-permission="control_agencies,open_control_agency">Open</a>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="No program URL configured">Open</button>
                        <?php endif; ?>
                        <?php if ($hasIsSuspended && $isSuspended): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-mark-paid" data-id="<?php echo (int)$r['id']; ?>" data-name="<?php echo htmlspecialchars($r['name'] ?? $r['agency_name'] ?? 'Agency'); ?>" data-permission="control_agencies,edit_control_agency,approve_control_registration">Mark Paid</button>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/control-center.php?tenant_id=' . (int)($r['tenant_id'] ?? 0)); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary btn-agency-control-link" data-action="open_control_center" data-agency-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,view_control_agencies">Control Center</a>
                        <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/event-timeline.php?tenant_id=' . (int)($r['tenant_id'] ?? 0)); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary btn-agency-control-link" data-action="view_events" data-agency-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,view_control_agencies">Events</a>
                        <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/control-center.php#db-control'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary btn-agency-control-link" data-action="view_db_status" data-agency-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,view_control_agencies">DB Status</a>
                        <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/control-center.php#query-console'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary btn-agency-control-link" data-action="view_query_activity" data-agency-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,view_control_agencies">Query Activity</a>
                        <button type="button" class="btn btn-sm btn-outline-info btn-view" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($r))); ?>" data-permission="control_agencies,view_control_agencies">View</button>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($r))); ?>" data-permission="control_agencies,edit_control_agency">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,delete_control_agency">Delete</button>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="agencies-pagination-bar">
        <span class="pagination-info">Showing <?php echo $totalAgencies ? (($page-1)*$limit+1) : 0; ?>-<?php echo min($page*$limit, $totalAgencies); ?> of <?php echo $totalAgencies; ?></span>
        <div class="pagination-btns">
            <?php
            $extraQ = http_build_query(array_filter(['control' => 1, 'country_id' => $countryId ?: null, 'agency_id' => $agencyIdFilter ?: null, 'search' => $search ?: null]));
            $prevUrl = $page > 1 ? $formAction . '?page=' . ($page-1) . '&limit=' . $limit . ($extraQ ? '&' . $extraQ : '') : '#';
            $nextUrl = $page < $totalPages ? $formAction . '?page=' . ($page+1) . '&limit=' . $limit . ($extraQ ? '&' . $extraQ : '') : '#';
            ?>
            <a href="<?php echo $page > 1 ? htmlspecialchars($prevUrl) : '#'; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">Previous</a>
            <span class="ms-2 me-2 align-self-center">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <a href="<?php echo $page < $totalPages ? htmlspecialchars($nextUrl) : '#'; ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next</a>
        </div>
        <form method="get" action="<?php echo htmlspecialchars($formAction); ?>" class="agencies-page-size d-inline">
            <input type="hidden" name="control" value="1">
            <input type="hidden" name="country_id" value="<?php echo $countryId; ?>">
            <?php if ($agencyIdFilter): ?><input type="hidden" name="agency_id" value="<?php echo (int)$agencyIdFilter; ?>"><?php endif; ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <select name="limit" id="agenciesPageLimitSelect">
                <option value="5" <?php echo $limit==5?'selected':''; ?>>5</option>
                <option value="10" <?php echo $limit==10?'selected':''; ?>>10</option>
                <option value="25" <?php echo $limit==25?'selected':''; ?>>25</option>
                <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
            </select>
            <input type="hidden" name="page" value="1">
        </form>
    </div>
    </div><!-- .agencies-list-view -->
    <?php endif; ?>
</div>

<!-- View Modal (read-only) -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content agencies-modal-content agencies-view-modal" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title">View Agency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body agencies-view-body" dir="ltr">
                <div class="row mb-2">
                    <div class="col-4 text-muted">Country</div>
                    <div class="col-8" id="viewCountry">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Name</div>
                    <div class="col-8" id="viewName">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Slug</div>
                    <div class="col-8" id="viewSlug">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Site URL</div>
                    <div class="col-8" id="viewSiteUrl">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">DB Host</div>
                    <div class="col-8" id="viewDbHost">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">DB Port</div>
                    <div class="col-8" id="viewDbPort">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">DB User</div>
                    <div class="col-8" id="viewDbUser">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">DB Name</div>
                    <div class="col-8" id="viewDbName">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Created</div>
                    <div class="col-8" id="viewCreated">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Renewal Date</div>
                    <div class="col-8" id="viewRenewalDate">-</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Status</div>
                    <div class="col-8" id="viewStatus">-</div>
                </div>
                <?php if ($hasIsSuspended): ?>
                <div class="row mb-2" id="viewSuspendedRow">
                    <div class="col-4 text-muted">Suspended</div>
                    <div class="col-8" id="viewSuspended">-</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-warning btn-edit-from-view" id="btnEditFromView" data-permission="control_agencies,edit_control_agency"><i class="fas fa-edit me-1"></i> Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content agencies-modal-content agencies-edit-modal" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Agency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" dir="ltr">
                <input type="hidden" id="editId">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Country *</label>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-control flex-grow-1" id="editCountryId">
                                <option value="">-- Select --</option>
                                <?php foreach ($countries as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $countryId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?php echo pageUrl('control/countries.php'); ?>" class="btn btn-outline-secondary btn-sm text-nowrap" title="Add a new country" data-permission="control_countries,add_control_country">+ Country</a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" id="editName" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" id="editSlug" placeholder="auto if empty">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site URL</label>
                        <input type="text" class="form-control" id="editSiteUrl" placeholder="https://...">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">DB Host</label>
                        <input type="text" class="form-control" id="editDbHost" value="localhost" dir="ltr">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">DB Port</label>
                        <input type="text" class="form-control" id="editDbPort" value="3306" inputmode="numeric" pattern="[0-9]*" dir="ltr" maxlength="5">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">DB User *</label>
                        <input type="text" class="form-control" id="editDbUser">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">DB Pass *</label>
                        <input type="password" class="form-control" id="editDbPass" placeholder="(unchanged if edit)">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">DB Name *</label>
                        <input type="text" class="form-control" id="editDbName">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Renewal Date</label>
                        <input type="date" class="form-control" id="editRenewalDate" placeholder="Leave empty = created+1yr">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="editIsActive">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <?php if ($hasIsSuspended): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Suspended</label>
                        <select class="form-control" id="editIsSuspended">
                            <option value="0">No</option>
                            <option value="1">Yes (non-payment)</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSave" data-permission="control_agencies,add_control_agency,edit_control_agency">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Alert / Confirm modals -->
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content agencies-modal-content" dir="ltr">
            <div class="modal-body py-3 text-center">
                <p id="alertMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer justify-content-center border-top border-secondary py-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content agencies-modal-content" dir="ltr">
            <div class="modal-body py-3 text-center">
                <p id="confirmMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer justify-content-center border-top border-secondary py-2 gap-2">
                <button type="button" class="btn btn-secondary btn-sm" id="confirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmOk">OK</button>
            </div>
        </div>
    </div>
</div>
