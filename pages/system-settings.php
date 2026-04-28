<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/system-settings.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/system-settings.php`.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permission_middleware.php';

// Ratib Pro only: allow agency admin (role_id=1 or manage_settings); must be a real `users` row.
$isAgencyAdmin = !empty($_SESSION['logged_in'])
    && (int)($_SESSION['user_id'] ?? 0) > 0
    && (
        (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1)
        || (function_exists('hasPermission') && hasPermission('manage_settings'))
    );
if (!$isAgencyAdmin) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$embeddedMode = !empty($_GET['embedded']);
$directInclude = defined('SYSTEM_SETTINGS_DIRECT_INCLUDE') && SYSTEM_SETTINGS_DIRECT_INCLUDE;
if ($directInclude) {
    $baseUrl = getBaseUrl();
}
$countriesCitiesVersion = file_exists(__DIR__ . '/../js/countries-cities.js')
    ? filemtime(__DIR__ . '/../js/countries-cities.js')
    : time();
$modernFormsVersion = file_exists(__DIR__ . '/../js/modern-forms.js')
    ? filemtime(__DIR__ . '/../js/modern-forms.js')
    : time();
$systemSettingsJsVersion = file_exists(__DIR__ . '/../js/system-settings.js')
    ? filemtime(__DIR__ . '/../js/system-settings.js')
    : time();
$systemSettingsAlertsVersion = file_exists(__DIR__ . '/../js/system-settings-alerts.js')
    ? filemtime(__DIR__ . '/../js/system-settings-alerts.js')
    : time();
$unifiedHistoryVersion = file_exists(__DIR__ . '/../js/unified-history.js')
    ? filemtime(__DIR__ . '/../js/unified-history.js')
    : time();

if ($directInclude) {
    ?>
<div id="app-config" data-base-path="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($baseUrl . '/api', ENT_QUOTES, 'UTF-8'); ?>" class="hidden"></div>
<script>
(function(){var e=document.getElementById('app-config');if(e){window.APP_CONFIG=window.APP_CONFIG||{};window.APP_CONFIG.baseUrl=e.getAttribute('data-base-url')||e.getAttribute('data-base-path')||'';window.APP_CONFIG.apiBase=e.getAttribute('data-api-base')||(window.APP_CONFIG.baseUrl+'/api');window.BASE_PATH=window.APP_CONFIG.baseUrl;window.API_BASE=window.APP_CONFIG.apiBase;}})();
</script>
<div class="content-wrapper system-settings-direct-wrap" style="margin-left:0;width:100%;">
<?php
    include __DIR__ . '/includes/system-settings-body.inc.php';
    ?>
</div>
<script src="<?php echo asset('js/countries-cities.js'); ?>?v=<?php echo $countriesCitiesVersion; ?>"></script>
<script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo asset('js/system-settings-alerts.js'); ?>?v=<?php echo $systemSettingsAlertsVersion; ?>"></script>
<script src="<?php echo asset('js/modern-forms.js'); ?>?v=<?php echo $modernFormsVersion; ?>"></script>
<script src="<?php echo asset('js/system-settings.js'); ?>?v=<?php echo $systemSettingsJsVersion; ?>"></script>
<script src="<?php echo asset('js/unified-history.js'); ?>?v=<?php echo $unifiedHistoryVersion; ?>"></script>
<script src="<?php echo asset('js/system-settings/visa-types-auto-open.js'); ?>?v=<?php echo $unifiedHistoryVersion; ?>"></script>
<?php
    return;
}

if ($embeddedMode) {
    $baseUrl = getBaseUrl();
    $cssVer = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/nav.css'); ?>?v=<?php echo $cssVer; ?>">
    <link rel="stylesheet" href="<?php echo asset('css/system-settings.css'); ?>?v=<?php echo $cssVer; ?>">
    <script src="<?php echo asset('js/utils/header-config.js'); ?>"></script>
</head>
<body class="system-settings-embedded">
<div id="app-config" data-base-path="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($baseUrl . '/api', ENT_QUOTES, 'UTF-8'); ?>" class="hidden"></div>
<div class="content-wrapper" style="margin-left:0;width:100%;">
<?php
} else {
    $pageTitle = "System Settings";
    $pageCss = ["../css/system-settings.css?v=" . time()];
    include '../includes/header.php';
}
?>

<div class="dashboard-content" id="systemSettingsContent">
    <div class="header-bar">
        <div class="logo-section">
        </div>
    </div>
    <div class="main-content">
        <div class="container">
            <div class="system-settings-container">
                <!-- Header Section -->
                <div class="header-section">
                    <div class="header-title-row">
                        <h1 class="header-title">
                            <i class="fas fa-cogs"></i>
                            System Settings
                        </h1>
                        <?php if (true): ?>
                        <button type="button" class="reset-app-btn" data-action="reset-all-data" data-permission="manage_settings" title="Clear all data: Dashboard, Agents, Workers, Cases, Accounting, HR, Reports, Contact, Notifications, Help &amp; System Settings history. Users kept.">
                            <i class="fas fa-eraser"></i> Reset App
                        </button>
                        <?php endif; ?>
                    </div>
                    <p class="header-subtitle">Manage all system configurations and settings</p>
                    
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number" id="totalUsers">-</div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="totalAgents">-</div>
                            <div class="stat-label">Total Agents</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="totalWorkers">-</div>
                            <div class="stat-label">Total Workers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="totalCases">-</div>
                            <div class="stat-label">Total Cases</div>
                        </div>
                    </div>


                </div>

                <!-- Settings Grid -->
                <div class="settings-grid">
                    <!-- Users -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-users-cog"></i>
                                Users
                            </h3>
                            <span class="setting-count" id="usersCount">-</span>
                        </div>
                        <p>Add, edit, or remove user accounts for this country's program</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="users" data-permission="manage_settings">
                            <i class="fas fa-user-edit"></i>
                            Manage Users
                        </button>
                    </div>

                    <!-- Office Manager -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-building"></i>
                                Office Manager
                            </h3>
                            <span class="setting-count">1</span>
                        </div>
                        <p>Manage office administrator information and contact details</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="office_manager" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Office Manager
                        </button>
                    </div>

                    <!-- Visa Management -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-passport"></i>
                                Visa Management
                            </h3>
                            <span class="setting-count" id="visaCount">-</span>
                        </div>
                        <p>Configure visa types, requirements, and processing details</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="visa_types" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Visa Types
                        </button>
                    </div>

                    <!-- Recruitment Settings -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-users"></i>
                                Recruitment Settings
                            </h3>
                            <span class="setting-count" id="recruitmentCount">-</span>
                        </div>
                        <p>Manage countries, job categories, and recruitment specifications</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="recruitment_countries" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Countries
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="job_categories" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Job Categories
                        </button>
                    </div>

                    <!-- Age & Appearance -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-user-check"></i>
                                Age & Appearance
                            </h3>
                            <span class="setting-count" id="ageCount">-</span>
                        </div>
                        <p>Configure age specifications and appearance requirements</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="age_specifications" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Age Specs
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="appearance_specifications" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Appearance
                        </button>
                    </div>

                    <!-- Status Management -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-tags"></i>
                                Status Management
                            </h3>
                            <span class="setting-count" id="statusCount">-</span>
                        </div>
                        <p>Configure status specifications and request statuses</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="status_specifications" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Status Specs
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="request_statuses" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Request Statuses
                        </button>
                    </div>

                    <!-- Arrival Management -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-plane-arrival"></i>
                                Arrival Management
                            </h3>
                            <span class="setting-count" id="arrivalCount">-</span>
                        </div>
                        <p>Manage arrival agencies and stations</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="arrival_agencies" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Agencies
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="arrival_stations" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Stations
                        </button>
                    </div>

                    <!-- Worker Status -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-user-clock"></i>
                                Worker Status
                            </h3>
                            <span class="setting-count" id="workerStatusCount">-</span>
                        </div>
                        <p>Configure worker status types and categories</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="worker_statuses" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Worker Statuses
                        </button>
                    </div>

                    <!-- Currency Management -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-money-bill-wave"></i>
                                Currency Management
                            </h3>
                            <span class="setting-count" id="currencyCount">-</span>
                        </div>
                        <p>Manage available currencies for Agent, SubAgent, Workers, Accounting, and HR modules</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="currencies" data-permission="manage_settings">
                            <i class="fas fa-edit"></i>
                            Manage Currencies
                        </button>
                    </div>

                    <!-- System Configuration -->
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-cog"></i>
                                System Configuration
                            </h3>
                            <span class="setting-count" id="systemConfigCount">-</span>
                        </div>
                        <p>Manage system settings, configurations, and preferences</p>
                        <div class="setting-actions">
                            <button class="modern-btn modern-btn-primary" data-action="open-company-info" data-permission="manage_settings">
                                <i class="fas fa-building"></i>
                                Company Information
                            </button>
                            <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="system_config" data-permission="manage_settings">
                                <i class="fas fa-edit"></i>
                                Manage System Config
                            </button>
                        </div>
                    </div>

                    <!-- System History -->
                    <div class="setting-card" id="systemHistorySettingCard" data-permission="view_system_history">
                        <div class="setting-header">
                            <h3 class="setting-title">
                                <i class="fas fa-history"></i>
                                System History
                            </h3>
                            <span class="setting-count" id="systemHistoryCountBadge">-</span>
                        </div>
                        <p>Select a module and view its activity history: Agents, Workers, Cases, HR, Settings, and more</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-system-history" data-permission="view_system_history">
                            <i class="fas fa-history"></i>
                            View History
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Modal -->
<div id="mainModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="modalTitle">Settings</h2>
        </div>
        <div id="modalBody">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<!-- Form Popup Modal -->
<div id="formPopupModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content form-popup-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="formPopupTitle">Add Item</h2>
        </div>
        <div id="formPopupBody">
            <!-- Form content will be loaded here -->
        </div>
    </div>
</div>

<!-- Quick Add Modal -->
<div id="quickAddModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title">Quick Add</h2>
        </div>
        <div class="quick-add-grid" id="quickAddGrid">
            <!-- Quick add options will be loaded here -->
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="historyModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content history-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="historyModalTitle">Activity History</h2>
            <button class="modal-close" data-action="close-history-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="historyModalBody" class="history-modal-body">
            <!-- History content will be loaded here -->
        </div>
    </div>
</div>

<?php if (true): ?>
<!-- Fingerprint Registration Modal (agency only - hidden in control panel) -->
<div id="fingerprintRegistrationModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content fingerprint-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title">
                <i class="fas fa-fingerprint"></i>
                <span id="fingerprintModalTitle">Register Fingerprint</span>
            </h2>
            <button class="modal-close" data-action="close-fingerprint-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="fingerprint-registration-body">
            <div class="fingerprint-info-section fingerprint-user-info">
                <h3 class="fingerprint-section-title">
                    <i class="fas fa-user"></i> User Information
                </h3>
                <div class="fingerprint-info-grid">
                    <strong>Username:</strong>
                    <span id="fingerprintRegUsername">-</span>
                    <strong>User ID:</strong>
                    <span id="fingerprintRegUserId">-</span>
                </div>
            </div>
            
            <div class="fingerprint-info-section fingerprint-prerequisites">
                <h3 class="fingerprint-section-title fingerprint-section-title-warning">
                    <i class="fas fa-exclamation-triangle"></i> Prerequisites
                </h3>
                <p class="fingerprint-text">
                    <strong>1. Set up Windows Password first</strong> (Required for Windows Hello)
                </p>
                <p class="fingerprint-text">
                    <strong>2. Set up Windows Hello Fingerprint</strong> in Windows Settings > Accounts > Sign-in options
                </p>
            </div>
            
            <div class="fingerprint-info-section fingerprint-instructions">
                <h3 class="fingerprint-section-title fingerprint-section-title-success">
                    <i class="fas fa-info-circle"></i> How to Register
                </h3>
                <ol class="fingerprint-steps-list">
                    <li>Click <strong>"Register Fingerprint"</strong> button below</li>
                    <li><strong>Place your finger</strong> on the scanner when prompted</li>
                    <li>Wait for the scan to complete</li>
                    <li>Your fingerprint will be saved automatically</li>
                </ol>
            </div>
            
            <div id="fingerprintRegistrationStatus" class="fingerprint-status-hidden"></div>
        </div>
        
        <div class="fingerprint-actions">
            <button class="modern-btn modern-btn-secondary fingerprint-action-btn" data-action="close-fingerprint-modal">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="fingerprintRegisterBtn" class="modern-btn modern-btn-primary fingerprint-action-btn" data-action="execute-fingerprint-registration">
                <i class="fas fa-fingerprint"></i> Register Fingerprint
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- User Permissions Management Modal -->
<div id="userPermissionsManagementModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content permissions-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title">
                <i class="fas fa-user-key"></i>
                <span id="userPermissionsModalTitle">Manage User Permissions</span>
            </h2>
            <button class="modal-close" data-action="close-user-permissions-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="permissions-management-body">
            <div class="permissions-user-info">
                <div class="user-info-card">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong id="userPermissionsUserName">Loading...</strong>
                        <small id="userPermissionsUserId">User ID: -</small>
                    </div>
                </div>
                <div class="permissions-note">
                    <i class="fas fa-info-circle"></i>
                    <span>User-specific permissions override role permissions. Leave empty to use role permissions only.</span>
                </div>
            </div>
            
            <div id="userPermissionsGroupsContainer" class="permissions-groups-container">
                <!-- Permission groups will be loaded here -->
            </div>
            
            <div id="userPermissionsStatus" class="permissions-status-hidden"></div>
        </div>
        
        <div class="permissions-actions">
            <button class="modern-btn modern-btn-secondary permissions-action-btn" data-action="close-user-permissions-modal">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="modern-btn modern-btn-info permissions-action-btn" data-action="select-all-user-permissions">
                <i class="fas fa-check-square"></i> Select All (Use Role Only)
            </button>
            <button class="modern-btn modern-btn-warning permissions-action-btn" data-action="clear-user-permissions">
                <i class="fas fa-undo"></i> Clear (Use Role Only)
            </button>
            <button id="saveUserPermissionsBtn" class="modern-btn modern-btn-primary permissions-action-btn" data-action="save-user-permissions">
                <i class="fas fa-save"></i> Save Permissions
            </button>
        </div>
    </div>
</div>

<script src="<?php echo asset('js/countries-cities.js'); ?>?v=<?php echo $countriesCitiesVersion; ?>"></script>
<script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo asset('js/system-settings-alerts.js'); ?>?v=<?php echo $systemSettingsAlertsVersion; ?>"></script>
<script src="<?php echo asset('js/modern-forms.js'); ?>?v=<?php echo $modernFormsVersion; ?>"></script>
<script src="<?php echo asset('js/system-settings.js'); ?>?v=<?php echo $systemSettingsJsVersion; ?>"></script>
<script src="<?php echo asset('js/unified-history.js'); ?>?v=<?php echo $unifiedHistoryVersion; ?>"></script>
<script src="<?php echo asset('js/system-settings/visa-types-auto-open.js'); ?>?v=<?php echo $unifiedHistoryVersion; ?>"></script>
<?php if ($embeddedMode): ?>
<script src="<?php echo asset('js/system-settings-debug.js'); ?>?v=<?php echo time(); ?>"></script>
<?php endif; ?>
<?php if ($embeddedMode): ?>
</div></body></html>
<?php else: ?>
</body></html>
<?php endif; ?> 

