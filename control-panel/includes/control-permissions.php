<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control-permissions.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control-permissions.php`.
 */
/**
 * Control Panel Permissions
 */
if (!defined('CONTROL_PERMISSIONS_LOADED')) {
    define('CONTROL_PERMISSIONS_LOADED', true);
}
define('CONTROL_PERM_DASHBOARD', 'control_dashboard');
define('CONTROL_PERM_SELECT_COUNTRY', 'control_select_country');
define('CONTROL_PERM_COUNTRIES', 'control_countries');
define('CONTROL_PERM_AGENCIES', 'control_agencies');
define('CONTROL_PERM_COUNTRY_USERS', 'control_country_users');
define('CONTROL_PERM_REGISTRATION', 'control_registration_requests');
define('CONTROL_PERM_REGISTRATION_VIEW_ALL', 'view_all_control_registration');
define('CONTROL_PERM_SUPPORT', 'control_support_chats');
define('CONTROL_PERM_DESIGNED_SITE', 'control_designed_site');
define('CONTROL_PERM_ACCOUNTING', 'control_accounting');
define('CONTROL_PERM_HR', 'control_hr');
define('CONTROL_PERM_GOVERNMENT', 'control_government');
define('CONTROL_PERM_ADMINS', 'control_admins');
define('CONTROL_PERM_SYSTEM_SETTINGS', 'control_system_settings');
define('CONTROL_PERM_HIDE_DASHBOARD_COUNTRIES_CARD', 'hide_dashboard_countries_card');
define('CONTROL_PERM_HIDE_DASHBOARD_AGENCIES_CARD', 'hide_dashboard_agencies_card');
define('CONTROL_PERM_HIDE_DASHBOARD_PENDING_CARD', 'hide_dashboard_pending_requests_card');
define('CONTROL_PERM_HIDE_DASHBOARD_ACCOUNTING_CARD', 'hide_dashboard_accounting_card');
define('CONTROL_PERM_HIDE_DASHBOARD_QUICK_ACTIONS', 'hide_dashboard_quick_actions');
define('CONTROL_PERM_HIDE_DASHBOARD_RECENT_REQUESTS', 'hide_dashboard_recent_requests');

$GLOBALS['CONTROL_PARENT_GRANTS'] = [
    'control_countries' => ['view_control_countries', 'add_control_country', 'edit_control_country', 'delete_control_country'],
    'control_agencies' => ['view_control_agencies', 'open_control_agency', 'add_control_agency', 'edit_control_agency', 'delete_control_agency'],
    'control_country_users' => ['view_control_country_users'],
    'control_registration_requests' => ['view_control_registration', 'edit_control_registration', 'approve_control_registration', 'reject_control_registration', 'delete_control_registration'],
    'control_support_chats' => [
        'view_control_support',
        'reply_control_support',
        'bulk_select_control_support',
        'bulk_mark_closed_control_support',
        'bulk_mark_open_control_support',
        'bulk_delete_control_support',
    ],
    'control_designed_site' => ['view_control_designed_site'],
    'control_accounting' => ['view_control_accounting', 'manage_control_accounting'],
    'control_hr' => ['view_control_hr', 'manage_control_hr'],
    'gov_admin' => ['control_government', 'view_control_government', 'manage_control_government'],
    'control_government' => ['view_control_government', 'manage_control_government'],
    'control_admins' => ['view_control_admins', 'add_control_admin', 'edit_control_admin', 'delete_control_admin'],
    'control_system_settings' => ['view_control_system_settings', 'edit_control_system_settings', 'manage_control_users', 'manage_control_roles'],
];

function hasControlPermission($permission) {
    if (empty($_SESSION['control_logged_in'])) return false;
    $perms = $_SESSION['control_permissions'] ?? null;
    if (strpos($permission, 'hide_dashboard_') === 0) {
        return is_array($perms) && in_array($permission, $perms, true);
    }
    if ($perms === null || $perms === '*') return true;
    if (!is_array($perms)) return true;
    if (in_array('*', $perms) || in_array($permission, $perms)) return true;
    $parents = $GLOBALS['CONTROL_PARENT_GRANTS'] ?? [];
    foreach ($parents as $parent => $children) {
        if (in_array($parent, $perms) && in_array($permission, $children)) return true;
    }
    return false;
}

function hasControlDashboardCardVisible($permission) {
    if (empty($_SESSION['control_logged_in'])) return false;
    $perms = $_SESSION['control_permissions'] ?? null;
    if ($perms === null || $perms === '*') return true;
    if (is_array($perms) && in_array('*', $perms, true)) return true;
    return is_array($perms) && in_array($permission, $perms, true);
}

function hasControlCountryAccess($countrySlug) {
    if (empty($_SESSION['control_logged_in'])) return false;
    if (hasControlPermission(CONTROL_PERM_SELECT_COUNTRY)) return true;
    return hasControlPermission('country_' . $countrySlug);
}

function getAllowedCountryIds($ctrl) {
    $slugs = getAllowedCountrySlugs();
    if ($slugs === null) return null;
    if (empty($slugs)) return [];
    if (!$ctrl) return [];
    $ids = [];
    $in = implode(',', array_map(function($s) use ($ctrl) {
        return "'" . $ctrl->real_escape_string($s) . "'";
    }, $slugs));
    $res = $ctrl->query("SELECT id FROM control_countries WHERE slug IN ($in) AND is_active = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) $ids[] = (int)$row['id'];
        $res->close();
    }
    return $ids;
}

/**
 * Country IDs for control-panel lists, filters, and country dropdowns.
 *
 * - null: show all active countries (no workspace pin, or invalid session id).
 * - non-empty array: single-country or multi-country scope — no mixing outside this set in one view.
 * - empty array: no country access.
 *
 * Full-access admins (`getAllowedCountryIds` === null): when `control_country_id` is set in session,
 * returns only that country so dashboards/lists do not overlap other countries. Switch country in the
 * panel to change workspace. Without a session country, returns null (global overview until you pick one).
 *
 * Country_* operators: same session pin when the session country is within their allowed slugs;
 * otherwise all allowed slug IDs until they set a valid session country.
 *
 * @return list<int>|null
 */
function getControlPanelCountryScopeIds(?mysqli $ctrl): ?array {
    if (empty($_SESSION['control_logged_in'])) {
        return [];
    }
    $sess = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;

    $allowed = getAllowedCountryIds($ctrl);
    if ($allowed === null) {
        if ($sess > 0 && $ctrl instanceof mysqli) {
            $st = $ctrl->prepare('SELECT id FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1');
            if ($st) {
                $st->bind_param('i', $sess);
                $st->execute();
                $res = $st->get_result();
                $ok = $res && $res->num_rows > 0;
                $st->close();
                if ($ok) {
                    return [$sess];
                }
            }
        }

        return null;
    }
    if ($allowed === []) {
        return [];
    }
    if ($sess > 0 && in_array($sess, $allowed, true)) {
        return [$sess];
    }

    return $allowed;
}

/**
 * True if assigning this control_admins.country_id is allowed for the current operator.
 */
function controlPanelAdminCountryIdAllowed(?mysqli $ctrl, ?int $countryId): bool {
    if ($countryId === null || $countryId <= 0) {
        return true;
    }
    $allowed = getAllowedCountryIds($ctrl);
    if ($allowed === null) {
        return true;
    }

    return in_array($countryId, $allowed, true);
}

function getAllowedCountrySlugs() {
    if (empty($_SESSION['control_logged_in'])) return [];
    $perms = $_SESSION['control_permissions'] ?? null;
    if ($perms === null || $perms === '*' || !is_array($perms)) return null;
    if (in_array('*', $perms) || in_array(CONTROL_PERM_SELECT_COUNTRY, $perms)) return null;
    $slugs = [];
    foreach ($perms as $p) {
        if (strpos($p, 'country_') === 0) $slugs[] = substr($p, 8);
    }
    return $slugs;
}

function getExpandedControlPermissions(array $perms) {
    if (in_array('*', $perms, true)) return $perms;
    $out = $perms;
    $parents = $GLOBALS['CONTROL_PARENT_GRANTS'] ?? [];
    foreach ($parents as $parent => $children) {
        if (in_array($parent, $perms, true)) {
            foreach ($children as $child) {
                if (!in_array($child, $out, true)) $out[] = $child;
            }
        }
    }
    // Ratib Pro HR page uses data-permission slugs from the main app; map control HR access for permissions.js
    $canManageHr = in_array('manage_control_hr', $out, true) || in_array(CONTROL_PERM_HR, $perms, true);
    $canViewHr = in_array('view_control_hr', $out, true) || $canManageHr;
    if ($canViewHr) {
        $ratibHrView = ['view_hr_dashboard', 'view_employees'];
        $ratibHrFull = array_merge($ratibHrView, [
            'add_employee', 'edit_employee', 'delete_employee',
            'manage_attendance', 'manage_leaves', 'manage_salaries',
            'manage_departments', 'manage_positions',
        ]);
        $toAdd = $canManageHr ? $ratibHrFull : $ratibHrView;
        foreach ($toAdd as $p) {
            if (!in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
    }
    if (in_array('gov_admin', $perms, true)) {
        foreach (['control_government', 'view_control_government', 'manage_control_government'] as $gp) {
            if (!in_array($gp, $out, true)) {
                $out[] = $gp;
            }
        }
    }
    return $out;
}

/**
 * Effective slugs for the permissions UI only — reflects what is stored, plus parent→children expansion.
 * Do not imply extra slugs from reply_control_support here, or reopening the modal shows chips “on” that were never saved.
 * Bulk actions at runtime use hasControlSupport* (explicit bulk slugs or full control_support_chats parent only).
 */
function controlPanelEffectivePermissionsForUi(array $userPermissions) {
    if (in_array('*', $userPermissions, true)) {
        return $userPermissions;
    }
    return getExpandedControlPermissions($userPermissions);
}

/** True if $permId is granted for the given stored permission list (modal + APIs). */
function controlPanelPermissionGrantedInList($permId, array $userPermissions) {
    if (in_array('*', $userPermissions, true)) {
        return true;
    }
    $eff = controlPanelEffectivePermissionsForUi($userPermissions);
    return in_array((string) $permId, $eff, true);
}

function requireControlPermission($permission) {
    if (empty($_SESSION['control_logged_in'])) {
        header('Location: ' . (function_exists('pageUrl') ? pageUrl('login.php') : '/pages/login.php'));
        exit;
    }
    $perms = func_get_args();
    foreach ($perms as $p) {
        if (hasControlPermission($p)) return;
    }
    http_response_code(403);
    die('Access denied.');
}

/** Support Chats: select-all + row checkboxes (not implied by reply_control_support — must save bulk or full Support Chats parent). */
function hasControlSupportBulkSelect() {
    if (hasControlPermission(CONTROL_PERM_SUPPORT)) return true;
    return hasControlPermission('bulk_select_control_support');
}

/** Mark closed (bulk bar + modal). */
function hasControlSupportMarkClosed() {
    if (hasControlPermission(CONTROL_PERM_SUPPORT)) return true;
    return hasControlPermission('bulk_mark_closed_control_support');
}

/** Mark open (bulk bar). */
function hasControlSupportMarkOpen() {
    if (hasControlPermission(CONTROL_PERM_SUPPORT)) return true;
    return hasControlPermission('bulk_mark_open_control_support');
}

/** Delete chats (bulk + modal). */
function hasControlSupportDeleteChat() {
    if (hasControlPermission(CONTROL_PERM_SUPPORT)) return true;
    return hasControlPermission('bulk_delete_control_support');
}

/**
 * Country IDs for registration-requests filtering when the user is not explicitly allowed to see all countries.
 * Returns null = no country restriction (view_all_control_registration or * / full admin via hasControlPermission).
 * Returns [] = no scope (show nothing).
 * Returns non-empty list = restrict to these country IDs (plus legacy rows with country_id 0 matching those names only).
 */
function getRegistrationRequestScopeCountryIds($ctrl) {
    if (empty($_SESSION['control_logged_in'])) {
        return [];
    }
    if (hasControlPermission('view_all_control_registration')) {
        return null;
    }
    $sessionCountryId = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
    if ($sessionCountryId > 0) {
        return [$sessionCountryId];
    }
    $slugs = function_exists('getAllowedCountrySlugs') ? getAllowedCountrySlugs() : [];
    if (!is_array($slugs) || empty($slugs)) {
        return [];
    }
    if (!$ctrl || !($ctrl instanceof mysqli)) {
        return [];
    }
    $escapedSlugs = [];
    foreach ($slugs as $slug) {
        $slug = trim((string) $slug);
        if ($slug !== '') {
            $escapedSlugs[] = "'" . $ctrl->real_escape_string($slug) . "'";
        }
    }
    if (empty($escapedSlugs)) {
        return [];
    }
    $in = implode(',', $escapedSlugs);
    $ids = [];
    $res = $ctrl->query("SELECT id FROM control_countries WHERE slug IN ($in) AND is_active = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        $res->close();
    }
    return $ids;
}
