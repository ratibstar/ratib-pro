<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/admins.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/admins.php`.
 */
/**
 * Control Panel: Manage Control Admins
 * Who can log into the control panel (control_admins table)
 */
require_once __DIR__ . '/../../includes/config.php';

if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_ADMINS, 'view_control_admins');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;
$adminsList = [];
$totalAdmins = 0;

$hasAdminCountryCol = false;
try {
    $ccAdm = $ctrl->query("SHOW COLUMNS FROM control_admins LIKE 'country_id'");
    if ($ccAdm && $ccAdm->num_rows > 0) {
        $hasAdminCountryCol = true;
    }
} catch (Throwable $e) { /* ignore */ }

$countriesForSelect = [];
try {
    $chkCt = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
    if ($chkCt && $chkCt->num_rows > 0) {
        $rc = $ctrl->query("SELECT id, name FROM control_countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        if ($rc) {
            while ($row = $rc->fetch_assoc()) {
                $countriesForSelect[] = $row;
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }

try {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_admins'");
    if ($chk && $chk->num_rows > 0) {
        $esc = $search !== '' ? $ctrl->real_escape_string($search) : '';
        if ($hasAdminCountryCol) {
            $where = $search !== '' ? " WHERE a.username LIKE '%{$esc}%' OR a.full_name LIKE '%{$esc}%'" : '';
            $totalAdmins = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_admins a" . $where)->fetch_assoc()['c'] ?? 0);
            $sql = "SELECT a.id, a.username, a.full_name, a.is_active, a.created_at, a.country_id, c.name AS country_name FROM control_admins a LEFT JOIN control_countries c ON c.id = a.country_id"
                . $where . " ORDER BY a.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        } else {
            $where = $search !== '' ? " WHERE username LIKE '%{$esc}%' OR full_name LIKE '%{$esc}%'" : '';
            $totalAdmins = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_admins" . $where)->fetch_assoc()['c'] ?? 0);
            $sql = "SELECT id, username, full_name, is_active, created_at FROM control_admins" . $where . " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }
        $res = $ctrl->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $adminsList[] = $row;
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }

$totalPages = max(1, (int)ceil($totalAdmins / max(1, $limit)));
$fmtId = function($id) { return 'AD' . str_pad((int)$id, 3, '0', STR_PAD_LEFT); };

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Control Admins', ['css/control/admins.css'], []);
?>

<div class="table-card">
    <h2 class="mb-4"><i class="fas fa-user-shield me-2"></i>Control Panel Admins</h2>
    <p class="text-muted mb-3">Manage who can log into the control panel. These accounts are separate from main app users.</p>

    <form method="get" action="" class="controls-bar" id="filterForm">
        <input type="hidden" name="control" value="1">
        <input type="text" name="search" class="search-input" placeholder="Search username or name..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="button" class="btn btn-control btn-primary" id="btnAdd" data-permission="control_admins,add_control_admin"><i class="fas fa-plus me-1"></i> Add Admin</button>
        <button type="button" class="btn btn-control btn-danger" id="btnBulkDelete" disabled data-permission="control_admins,delete_control_admin"><i class="fas fa-trash me-1"></i> Delete Selected</button>
        <button type="submit" class="btn btn-control btn-secondary"><i class="fas fa-search me-1"></i> Search</button>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <?php if ($hasAdminCountryCol): ?><th>Country</th><?php endif; ?>
                    <th>Status</th>
                    <th>Created</th>
                    <th><input type="checkbox" id="selectAll" title="Select all"></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
<?php foreach ($adminsList as $r): ?>
                <tr>
                    <td><?php echo $fmtId($r['id']); ?></td>
                    <td><?php echo htmlspecialchars($r['username'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['full_name'] ?? '-'); ?></td>
                    <?php if ($hasAdminCountryCol): ?>
                    <td><?php echo htmlspecialchars($r['country_name'] ?? ($r['country_id'] ? ('#' . (int)$r['country_id']) : '—')); ?></td>
                    <?php endif; ?>
                    <td><span class="badge <?php echo ($r['is_active'] ?? 1) == 1 ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ($r['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo isset($r['created_at']) ? substr($r['created_at'], 0, 10) : '-'; ?></td>
                    <td><input type="checkbox" class="row-check" data-id="<?php echo (int)$r['id']; ?>"></td>
                    <td class="action-btns">
                        <button type="button" class="btn btn-sm btn-outline-info btn-permissions" data-id="<?php echo (int)$r['id']; ?>" data-username="<?php echo htmlspecialchars($r['username'] ?? ''); ?>" data-permission="control_admins,edit_control_admin"><i class="fas fa-key"></i> Permissions</button>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit" data-id="<?php echo (int)$r['id']; ?>" data-username="<?php echo htmlspecialchars($r['username'] ?? ''); ?>" data-fullname="<?php echo htmlspecialchars($r['full_name'] ?? ''); ?>" data-active="<?php echo (int)($r['is_active'] ?? 1); ?>"<?php echo $hasAdminCountryCol ? ' data-country-id="' . (int)($r['country_id'] ?? 0) . '"' : ''; ?> data-permission="control_admins,edit_control_admin">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo (int)$r['id']; ?>" data-permission="control_admins,delete_control_admin">Delete</button>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <span class="pagination-info">Showing <?php echo $totalAdmins ? (($page-1)*$limit+1) : 0; ?>-<?php echo min($page*$limit, $totalAdmins); ?> of <?php echo $totalAdmins; ?></span>
        <div class="pagination-btns">
            <?php if ($page > 1): ?><a href="?control=1&page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Previous</a><?php endif; ?>
            <span class="ms-2 me-2">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="?control=1&page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Next</a><?php endif; ?>
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

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" dir="ltr">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-control" id="editUsername" required autocomplete="username">
                </div>
                <div class="mb-3" id="editPasswordGroup">
                    <label class="form-label"><span id="passwordLabel">Password *</span></label>
                    <input type="password" class="form-control" id="editPassword" autocomplete="new-password" minlength="4">
                    <div class="form-hint" id="passwordHint">Required when creating. Min 4 characters.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="editFullName" placeholder="Display name">
                </div>
                <?php if ($hasAdminCountryCol): ?>
                <div class="mb-3">
                    <label class="form-label">Home country</label>
                    <select class="form-control" id="editCountryId">
                        <option value="">— None (not tied to a country) —</option>
                        <?php foreach ($countriesForSelect as $co): ?>
                        <option value="<?php echo (int)($co['id'] ?? 0); ?>"><?php echo htmlspecialchars($co['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Used as default country context after login (e.g. registration requests scope).</div>
                </div>
                <?php endif; ?>
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
                <button type="button" class="btn btn-primary" id="btnSave" data-permission="control_admins,add_control_admin,edit_control_admin">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content control-alert-modal" dir="ltr">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="alertModalTitle">Message</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" id="alertModalClose" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3 text-center"><p id="alertMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center py-2 gap-2">
                <button type="button" class="btn btn-secondary btn-sm" id="alertModalCancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="alertModalOk">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content control-confirm-modal" dir="ltr">
            <div class="modal-body py-3 text-center"><p id="confirmMessage" class="mb-0"></p></div>
            <div class="modal-footer justify-content-center py-2 gap-2"><button type="button" class="btn btn-secondary btn-sm" id="confirmCancel">Cancel</button><button type="button" class="btn btn-primary btn-sm" id="confirmOk">OK</button></div>
        </div>
    </div>
</div>

<!-- Permissions modal (Manage User Permissions - like reference pic) -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content perm-modal-content" dir="ltr">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Manage User Permissions <span class="text-muted fw-normal fs-6" id="permUserName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="permUserId">
                <div class="perm-banner perm-banner-info">
                    <i class="fas fa-info-circle me-2"></i>
                    User-specific permissions override role permissions. Leave empty to use role permissions only.
                </div>
                <div class="perm-banner perm-banner-total">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="permTotalText">Total: 0 permissions across 0 groups</span>
                </div>
                <div id="permGroupsContainer" class="perm-groups-container"></div>
            </div>
            <div class="modal-footer perm-modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="permUseRoleOnly"><i class="fas fa-check-square me-1"></i> Select All (Use Role Only)</button>
                <button type="button" class="btn btn-warning text-white" id="permClear"><i class="fas fa-sync-alt me-1"></i> Clear (Use Role Only)</button>
                <button type="button" class="btn btn-primary" id="permSave" data-permission="control_admins,edit_control_admin,manage_control_roles"><i class="fas fa-save me-1"></i> Save Permissions</button>
            </div>
        </div>
    </div>
</div>

<?php endControlLayout(['js/control/admins.js']); ?>
