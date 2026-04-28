<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control-agencies.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control-agencies.php`.
 */
/**
 * Control Panel: Manage Agencies - full table with pagination, bulk, edit/view/delete
 */
require_once __DIR__ . '/../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('view_control_agencies')) {
    http_response_code(403);
    die('Access denied.');
}

// If not embedded, redirect to unified layout version
if (empty($_GET['embedded'])) {
    $params = [];
    if (!empty($_GET['country_id'])) $params['country_id'] = $_GET['country_id'];
    if (!empty($_GET['agency_id'])) $params['agency_id'] = $_GET['agency_id'];
    if (!empty($_GET['page'])) $params['page'] = $_GET['page'];
    if (!empty($_GET['limit'])) $params['limit'] = $_GET['limit'];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    header('Location: ' . pageUrl('control/agencies.php') . '?' . http_build_query($params));
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';
$countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;

$allowedCountryIds = getAllowedCountryIds($ctrl);
$countries = [];
$countryMap = [];
$countrySlugMap = [];
$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if ($chk && $chk->num_rows > 0) {
    $countrySql = "SELECT id, name, slug FROM control_countries WHERE is_active = 1 ORDER BY sort_order, name";
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $countrySql = "SELECT id, name, slug FROM control_countries WHERE id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ") AND is_active = 1 ORDER BY sort_order, name";
    } elseif ($allowedCountryIds === []) {
        $countrySql = "SELECT id, name, slug FROM control_countries WHERE 1=0";
    }
    $res = $ctrl->query($countrySql);
    if ($res) while ($row = $res->fetch_assoc()) {
        $countries[] = $row;
        $countryMap[(int)$row['id']] = $row['name'];
        $countrySlugMap[(int)$row['id']] = trim((string)($row['slug'] ?? ''));
    }
}

// Server-side data load - table rendered from PHP
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
        // When agency_id is explicitly requested (e.g. from AG link in registration requests), bypass country filter so the agency shows
        $skipCountryFilter = ($agencyIdFilter > 0 && $allowedCountryIds !== null && !empty($allowedCountryIds));
        if ($allowedCountryIds === []) {
            $where[] = '1=0';
            $whereA[] = '1=0';
        } elseif (!$skipCountryFilter && $allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $idsStr = implode(',', array_map('intval', $allowedCountryIds));
            $where[] = "country_id IN ($idsStr)";
            $whereA[] = "a.country_id IN ($idsStr)";
        }
        if ($hasCountryId && $countryId > 0) { $where[] = 'country_id = ' . (int)$countryId; $whereA[] = 'a.country_id = ' . (int)$countryId; }
        if ($agencyIdFilter > 0) { $where[] = 'id = ' . (int)$agencyIdFilter; $whereA[] = 'a.id = ' . (int)$agencyIdFilter; }
        if ($search !== '') { $esc = $ctrl->real_escape_string($search); $where[] = "(name LIKE '%{$esc}%' OR slug LIKE '%{$esc}%' OR site_url LIKE '%{$esc}%')"; $whereA[] = "(a.name LIKE '%{$esc}%' OR a.slug LIKE '%{$esc}%' OR a.site_url LIKE '%{$esc}%')"; }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $whereClauseA = $whereA ? ' WHERE ' . implode(' AND ', $whereA) : '';
        $totalAgencies = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_agencies" . $whereClause)->fetch_assoc()['c'] ?? 0);
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
$renewalDate = function($created) {
    if (!$created) return '-';
    $d = date_create($created);
    return $d ? $d->modify('+1 year')->format('Y-m-d') : '-';
};
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title>Manage Agencies - Control Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/control-agencies.css'); ?>?v=<?php echo time(); ?>">
</head>
<body data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-country-id="<?php echo (int)$countryId; ?>">
    <?php $__baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''); $__path = $_SERVER['REQUEST_URI'] ?? ''; $__basePath = preg_replace('#/pages/[^?]*.*$#', '', $__path) ?: ''; $__fullBase = rtrim($__baseUrl . $__basePath, '/'); ?>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($__fullBase, ENT_QUOTES); ?>" data-api-base="<?php echo htmlspecialchars($__fullBase . '/api', ENT_QUOTES); ?>" data-control="1" class="hidden"></div>
    <?php if (empty($_GET['embedded'])): ?>
    <div class="control-header">
        <div class="control-nav">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/pages/home.php?open=register" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm"><i class="fas fa-external-link-alt me-1"></i> Register Pro</a>
            <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <a href="<?php echo pageUrl('select-country.php'); ?>"><i class="fas fa-globe me-1"></i> Countries</a>
            <a href="<?php echo pageUrl('control/countries.php'); ?>"><i class="fas fa-list me-1"></i> Manage Countries</a>
            <a href="<?php echo pageUrl('control/agencies.php'); ?>"><i class="fas fa-building me-1"></i> Manage Agencies</a>
            <a href="<?php echo pageUrl('control/registration-requests.php'); ?>"><i class="fas fa-user-plus me-1"></i> Registration Requests</a>
            <a href="<?php echo pageUrl('control/support-chats.php'); ?>"><i class="fas fa-comments me-1"></i> Support Chats</a>
            <a href="<?php echo pageUrl('control/accounting.php'); ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-calculator me-1"></i> Accounting</a>
            <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-briefcase me-1"></i> Recruitment Program</a>
            <a href="<?php echo (defined('RATIB_PRO_URL') ? RATIB_PRO_URL : pageUrl('control/dashboard.php')) . (defined('RATIB_PRO_URL') ? '?control=1&own=1' : ''); ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-user me-1"></i> My own Program</a>
        </div>
        <div>
            <span class="text-muted me-3"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="content" id="controlContent">
        <div class="table-card" id="tableCard">
            <h2 class="mb-4"><i class="fas fa-building me-2"></i>Agencies<?php if ($agencyIdFilter): ?> <small class="text-muted">(AG<?php echo str_pad($agencyIdFilter, 4, '0', STR_PAD_LEFT); ?>)</small><?php elseif ($countryId): ?> <small class="text-muted">(filtered by country)</small><?php endif; ?></h2>
            <?php if (!$hasCountryId && $totalAgencies > 0): ?>
            <div class="alert alert-warning py-2 mb-3 agency-country-warn">
                <i class="fas fa-exclamation-triangle me-1"></i> Country column not found. Run <code>config/control_tables_migration_countries.sql</code> in phpMyAdmin to enable it.
            </div>
            <?php endif; ?>
            <form method="get" action="<?php echo !empty($_GET['embedded']) ? pageUrl('control/agencies.php') : ''; ?>" class="controls-bar" id="filterForm" <?php echo !empty($_GET['embedded']) ? 'target="_parent"' : ''; ?>>
                <input type="hidden" name="control" value="1">
                <?php if ($agencyIdFilter): ?><input type="hidden" name="agency_id" value="<?php echo (int)$agencyIdFilter; ?>"><?php endif; ?>
                <?php if (count($countries)): ?>
                <select name="country_id" id="agencyCountrySelectLegacy" class="form-select agency-country-select">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $countryId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?><input type="hidden" name="country_id" value="<?php echo $countryId; ?>"><?php endif; ?>
                <input type="text" name="search" class="search-input" placeholder="Search name, slug, URL..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="button" class="btn btn-control btn-primary" id="btnAdd" data-permission="control_agencies,add_control_agency"><i class="fas fa-plus me-1"></i> Add Agency</button>
                <button type="submit" class="btn btn-control btn-secondary"><i class="fas fa-search me-1"></i> Search</button>
                <button type="button" class="btn btn-control btn-success" id="btnBulkActivate" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-check me-1"></i> Activate</button>
                <button type="button" class="btn btn-control btn-inactive" id="btnBulkDeactivate" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-power-off me-1"></i> Bulk Inactive</button>
                <button type="button" class="btn btn-control btn-warning" id="btnBulkSuspend" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-pause-circle me-1"></i> Suspend</button>
                <button type="button" class="btn btn-control btn-info" id="btnBulkUnsuspend" disabled data-permission="control_agencies,edit_control_agency"><i class="fas fa-play-circle me-1"></i> Unsuspend</button>
                <button type="button" class="btn btn-control btn-danger" id="btnBulkDelete" disabled data-permission="control_agencies,delete_control_agency"><i class="fas fa-trash me-1"></i> Delete Selected</button>
            </form>

            <div class="<?php echo $limit > 5 ? 'table-scroll-wrapper' : ''; ?>">
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
<?php foreach ($agenciesList as $r): ?>
                        <tr>
                            <td><?php echo $fmtId($r['id']); ?></td>
                            <td><?php echo htmlspecialchars($r['name'] ?? $r['agency_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['slug'] ?? '-'); ?></td>
                            <td><?php
$cid = isset($r['country_id']) ? (int)$r['country_id'] : 0;
$cname = ($cid && isset($countryMap[$cid])) ? $countryMap[$cid] : (trim($r['country_name'] ?? '') ?: trim($r['country'] ?? ''));
echo htmlspecialchars($cname ?: '-');
?></td>
                            <td class="url-cell" title="<?php echo htmlspecialchars($r['site_url'] ?? ''); ?>"><?php echo htmlspecialchars(($r['site_url'] ?? '') ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['db_host'] ?? '-'); ?></td>
                            <td><?php echo $r['db_port'] ?? '-'; ?></td>
                            <td><?php echo htmlspecialchars($r['db_user'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['db_name'] ?? '-'); ?></td>
                            <td><?php echo isset($r['created_at']) ? substr($r['created_at'], 0, 10) : '-'; ?></td>
                            <td><?php echo $renewalDate($r['created_at'] ?? null); ?></td>
                            <td><span class="badge <?php echo ($r['is_active'] ?? 1) == 1 ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ($r['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?></span></td>
                            <td><input type="checkbox" class="row-check" data-id="<?php echo (int)$r['id']; ?>"></td>
                            <td class="action-btns">
                                <?php
                                    $cidOpen = isset($r['country_id']) ? (int)$r['country_id'] : 0;
                                    $cslug = isset($countrySlugMap[$cidOpen]) ? trim($countrySlugMap[$cidOpen]) : '';
                                    $ratibBase = rtrim(defined('RATIB_PRO_URL') ? RATIB_PRO_URL : (defined('SITE_URL') ? SITE_URL : ''), '/');
                                    if ($ratibBase === '' && isset($_SERVER['HTTP_HOST'])) {
                                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                        $ratibBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
                                    }
                                    $openQs = 'control=1&agency_id=' . (int)$r['id'];
                                    $openUrl = ($cslug !== '' && $ratibBase !== '')
                                        ? $ratibBase . '/' . $cslug . '/?' . $openQs
                                        : (($ratibBase !== '') ? ($ratibBase . '/pages/dashboard.php?' . $openQs) : (pageUrl('control/dashboard.php') . '?' . $openQs));
                                ?><a href="<?php echo htmlspecialchars($openUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success" data-permission="control_agencies,open_control_agency">Open</a>
                                <a href="<?php echo !empty($_GET['embedded']) ? pageUrl('control/agencies.php') : ''; ?>?country_id=<?php echo (int)($r['country_id'] ?? 0); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-info" <?php echo !empty($_GET['embedded']) ? 'target="_parent"' : ''; ?>>View</a>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-row="<?php echo htmlspecialchars(base64_encode(json_encode($r))); ?>" data-permission="control_agencies,edit_control_agency">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_agencies,delete_control_agency">Delete</button>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="pagination-bar">
                <span class="pagination-info">Showing <?php echo $totalAgencies ? (($page-1)*$limit+1) : 0; ?>-<?php echo min($page*$limit, $totalAgencies); ?> of <?php echo $totalAgencies; ?></span>
                <div class="pagination-btns">
                    <?php 
                    $baseUrl = !empty($_GET['embedded']) ? pageUrl('control/agencies.php') : '';
                    $extraQ = http_build_query(array_filter(['country_id' => $countryId ?: null, 'agency_id' => $agencyIdFilter ?: null, 'search' => $search ?: null]));
                    $prevUrl = $page > 1 ? $baseUrl . '?page=' . ($page-1) . '&limit=' . $limit . ($extraQ ? '&' . $extraQ : '') : '#';
                    $nextUrl = $page < $totalPages ? $baseUrl . '?page=' . ($page+1) . '&limit=' . $limit . ($extraQ ? '&' . $extraQ : '') : '#';
                    ?>
                    <a href="<?php echo $page > 1 ? htmlspecialchars($prevUrl) : '#'; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" <?php echo ($page > 1 && !empty($_GET['embedded'])) ? 'target="_parent"' : ''; ?>>Previous</a>
                    <span class="ms-2 me-2 align-self-center">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                    <a href="<?php echo $page < $totalPages ? htmlspecialchars($nextUrl) : '#'; ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" <?php echo ($page < $totalPages && !empty($_GET['embedded'])) ? 'target="_parent"' : ''; ?>>Next</a>
                </div>
                <form method="get" action="<?php echo !empty($_GET['embedded']) ? pageUrl('control/agencies.php') : ''; ?>" class="page-size d-inline" <?php echo !empty($_GET['embedded']) ? 'target="_parent"' : ''; ?>>
                    <input type="hidden" name="control" value="1">
                    <input type="hidden" name="country_id" value="<?php echo $countryId; ?>">
                    <?php if ($agencyIdFilter): ?><input type="hidden" name="agency_id" value="<?php echo (int)$agencyIdFilter; ?>"><?php endif; ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="limit" id="pageLimitSelect">
                        <option value="5" <?php echo $limit==5?'selected':''; ?>>5</option>
                        <option value="10" <?php echo $limit==10?'selected':''; ?>>10</option>
                        <option value="25" <?php echo $limit==25?'selected':''; ?>>25</option>
                        <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" dir="ltr">
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
                                <a href="<?php echo pageUrl('control/countries.php'); ?>" class="btn btn-outline-secondary btn-sm text-nowrap" title="Add a new country">+ Country</a>
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
                            <label class="form-label">Status</label>
                            <select class="form-control" id="editIsActive">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSave">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- English-only alert modal (avoids browser Arabic dialogs) -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content agencies-modal-dark" dir="ltr">
                <div class="modal-body py-3 text-center">
                    <p id="alertMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer justify-content-center border-top border-secondary py-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <!-- English-only confirm modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content agencies-modal-dark" dir="ltr">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/control/agencies-standalone.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
<?php require_once __DIR__ . '/../includes/control_pending_reg_alert.php'; ?>
</body>
</html>
