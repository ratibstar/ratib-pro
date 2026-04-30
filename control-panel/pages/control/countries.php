<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/countries.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/countries.php`.
 */
/**
 * Control Panel: Manage Countries
 * Unified layout with sidebar
 */
require_once __DIR__ . '/../../includes/config.php';

// EN: Enforce control-panel auth/session and country-management permission.
// AR: فرض التحقق من جلسة لوحة التحكم وصلاحية إدارة الدول.
$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_COUNTRIES, 'view_control_countries');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

// EN: Build same-origin control API base to keep panel routing stable under subpaths.
// AR: إنشاء مسار API بنفس النطاق لضمان استقرار التوجيه عند العمل داخل مسارات فرعية.
// API path
$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';

// EN: Server-side filtering/pagination for countries table with access scoping.
// AR: تنفيذ الفلترة والترقيم على الخادم لجدول الدول ضمن نطاق الصلاحيات.
$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
// Server-side data load
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;
$countriesList = [];
$totalCountries = 0;
$activeCountries = 0;
$inactiveCountries = 0;
// EN: Query table existence, counts, and paginated rows defensively.
// AR: تنفيذ فحوص وجود الجداول والعدادات والصفوف المرقمة بشكل دفاعي.
try {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
    if ($chk && $chk->num_rows > 0) {
        $esc = $ctrl->real_escape_string($search);
        $searchClause = $search !== '' ? "(name LIKE '%{$esc}%' OR slug LIKE '%{$esc}%')" : '';
        if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
            $idsStr = implode(',', array_map('intval', $allowedCountryIds));
            $where = " WHERE id IN ($idsStr)" . ($searchClause ? " AND $searchClause" : '');
        } elseif ($allowedCountryIds === []) {
            $where = ' WHERE 1=0';
        } else {
            $where = $searchClause ? " WHERE $searchClause" : '';
        }
        $totalCountries = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_countries" . $where)->fetch_assoc()['c'] ?? 0);
        $statusRes = $ctrl->query("SELECT
            SUM(CASE WHEN COALESCE(is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN COALESCE(is_active, 1) = 0 THEN 1 ELSE 0 END) AS inactive_count
            FROM control_countries" . $where);
        if ($statusRes && ($sr = $statusRes->fetch_assoc())) {
            $activeCountries = (int)($sr['active_count'] ?? 0);
            $inactiveCountries = (int)($sr['inactive_count'] ?? 0);
        }
        $res = $ctrl->query("SELECT * FROM control_countries" . $where . " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        if ($res) while ($row = $res->fetch_assoc()) $countriesList[] = $row;
    }
} catch (Throwable $e) { /* ignore */ }
$totalPages = max(1, (int)ceil($totalCountries / $limit));
$fmtId = function($id) { return 'CO' . str_pad((int)$id, 3, '0', STR_PAD_LEFT); };

// EN: Render through unified control layout wrapper for consistent shell/sidebar.
// AR: العرض عبر قالب لوحة التحكم الموحد للحفاظ على نفس الهيكل والشريط الجانبي.
// Use unified layout
require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Manage Countries', ['css/control/admins.css', 'css/control/countries.css'], []);
?>

<!-- EN: Main countries management card (filters, bulk actions, results table). -->
<!-- AR: البطاقة الرئيسية لإدارة الدول (فلاتر، عمليات جماعية، وجدول النتائج). -->
<div class="table-card" id="tableCard">
    <h2 class="mb-4"><i class="fas fa-globe me-2"></i>Countries</h2>
    <div class="status-cards">
        <div class="status-card total"><span>Total Countries</span><strong><?php echo (int) $totalCountries; ?></strong></div>
        <div class="status-card active"><span>Active</span><strong><?php echo (int) $activeCountries; ?></strong></div>
        <div class="status-card inactive"><span>Inactive</span><strong><?php echo (int) $inactiveCountries; ?></strong></div>
    </div>
    <form method="get" action="" class="controls-bar" id="filterForm">
        <input type="hidden" name="control" value="1">
        <input type="text" name="search" class="search-input" placeholder="Search name or slug..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="button" class="btn btn-control btn-primary" id="btnAdd" data-permission="control_countries,add_control_country"><i class="fas fa-plus me-1"></i> Add Country</button>
        <button type="button" class="btn btn-control btn-success" id="btnBulkActivate" disabled data-permission="control_countries,edit_control_country"><i class="fas fa-check me-1"></i> Bulk Activate</button>
        <button type="button" class="btn btn-control btn-warning" id="btnBulkInactivate" disabled data-permission="control_countries,edit_control_country"><i class="fas fa-ban me-1"></i> Bulk Inactivate</button>
        <button type="button" class="btn btn-control btn-danger" id="btnBulkDelete" disabled data-permission="control_countries,delete_control_country"><i class="fas fa-trash me-1"></i> Bulk Delete</button>
        <button type="submit" class="btn btn-control btn-secondary"><i class="fas fa-search me-1"></i> Search</button>
    </form>
    <div class="bulk-progress-box" id="bulkProgressBox">Bulk status: idle.</div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th><input type="checkbox" id="selectAll" title="Select all"></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
<?php foreach ($countriesList as $r): ?>
                <tr>
                    <td><?php echo $fmtId($r['id']); ?></td>
                    <td><?php echo htmlspecialchars($r['name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['slug'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo ($r['is_active'] ?? 1) == 1 ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ($r['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo isset($r['created_at']) ? substr($r['created_at'], 0, 10) : '-'; ?></td>
                    <td><input type="checkbox" class="row-check" data-id="<?php echo (int)$r['id']; ?>"></td>
                    <td class="action-btns">
                        <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1&country_id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-info" data-permission="control_agencies,view_control_agencies">View Agencies</a>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-id="<?php echo (int)$r['id']; ?>" data-name="<?php echo htmlspecialchars($r['name'] ?? ''); ?>" data-slug="<?php echo htmlspecialchars($r['slug'] ?? ''); ?>" data-active="<?php echo (int)($r['is_active'] ?? 1); ?>" data-permission="control_countries,edit_control_country">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_countries,delete_control_country">Delete</button>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- EN: Pagination summary + navigation + page-size selector. -->
    <!-- AR: ملخص الترقيم + أزرار التنقل + اختيار حجم الصفحة. -->
    <div class="pagination-bar">
        <span class="pagination-info">Showing <?php echo $totalCountries ? (($page-1)*$limit+1) : 0; ?>-<?php echo min($page*$limit, $totalCountries); ?> of <?php echo $totalCountries; ?></span>
        <div class="pagination-btns">
            <?php if ($page > 1): ?><a href="?control=1&page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary">Previous</a><?php endif; ?>
            <span class="ms-2 me-2">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="?control=1&page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?>
        </div>
        <form method="get" class="page-size d-inline">
            <input type="hidden" name="control" value="1">
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

<!-- EN: Shared modal for add/edit country record. -->
<!-- AR: نافذة مشتركة لإضافة/تعديل بيانات الدولة. -->
<!-- Add/Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Country</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" dir="ltr">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" id="editName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" id="editSlug" placeholder="auto-generated if empty">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="editIsActive">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSave" data-permission="control_countries,add_control_country,edit_control_country">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" dir="ltr">
            <div class="modal-body py-3 text-center"><p id="alertMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center border-top border-secondary py-2"><button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" dir="ltr">
            <div class="modal-body py-3 text-center"><p id="confirmMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center border-top border-secondary py-2 gap-2"><button type="button" class="btn btn-secondary btn-sm" id="confirmCancel">Cancel</button><button type="button" class="btn btn-primary btn-sm" id="confirmOk">OK</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endControlLayout(['js/control/countries.js']); ?>
