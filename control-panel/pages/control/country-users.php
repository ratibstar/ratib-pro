<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/country-users.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/country-users.php`.
 */
/**
 * Country Users - Manage users for each country from one page.
 * Select a country to see and manage its users table.
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_COUNTRY_USERS, 'view_control_country_users', CONTROL_PERM_AGENCIES, 'view_control_agencies', 'open_control_agency');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$apiBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath . '/api/control';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Country Users', ['css/control/country-users.css'], []);
?>
<div class="control-settings-intro country-users-intro">
    <strong>Country Users (Ratib Pro only)</strong> — Table: <code>users</code> in each country's database. Ratib Pro login users only. <strong>Not</strong> control panel admins. For control panel admins, use <a href="<?php echo pageUrl('control/panel-users.php'); ?>?control=1">Control Panel Users</a>.
</div>

<div class="status-cards">
    <div class="status-card total"><span>Total Countries</span><strong id="stCountriesTotal">0</strong></div>
    <div class="status-card users"><span>Total Users</span><strong id="stUsersTotal">0</strong></div>
    <div class="status-card with-users"><span>Countries With Users</span><strong id="stCountriesWithUsers">0</strong></div>
    <div class="status-card empty"><span>Countries Without Users</span><strong id="stCountriesWithoutUsers">0</strong></div>
</div>

<div class="country-select-section">
    <div id="countryCardsGrid" class="country-cards-grid"></div>
    <div id="countryUsersHelper" class="empty-state">
        <i class="fas fa-globe-americas fa-2x mb-2 country-users-helper-icon"></i>
        <p class="mb-0">Click any country card to view and manage its Ratib Pro users.</p>
    </div>
</div>

<!-- Users table modal (per-country), opened when clicking a country card -->
<div class="modal fade" id="usersTableModal" tabindex="-1" aria-labelledby="usersTableModalTitle" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content country-users-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usersTableModalTitle"><i class="fas fa-users me-2"></i><span id="selectedCountryName">Ratib Pro Users</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="usersTableSection" class="users-table-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><span id="selectedCountryNameSecondary">Users</span></h4>
                        <button type="button" class="btn btn-primary" id="btnAddUser" data-permission="control_system_settings,manage_control_users,edit_control_system_settings"><i class="fas fa-plus me-1"></i>Add User</button>
                    </div>
                    <div id="usersTableContainer">
                        <div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading users...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal (moved to body by JS for correct z-index) -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog">
        <div class="modal-content country-users-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="user_id">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3" id="passwordGroup">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted">Leave blank to keep current password when editing</small>
                    </div>
                    <div class="mb-3">
                        <label for="roleId" class="form-label">Role</label>
                        <select class="form-select" id="roleId" name="role_id"></select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelUser">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveUser" data-permission="control_system_settings,manage_control_users,edit_control_system_settings">Save</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endControlLayout(['js/control/country-users.js']); ?>
