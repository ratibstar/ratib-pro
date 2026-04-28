<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/includes/system-settings-body.inc.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/includes/system-settings-body.inc.php`.
 */
/** System Settings body: dashboard-content + modals. Used for direct embed. */
?>
<div class="dashboard-content" id="systemSettingsContent">
    <div class="header-bar"><div class="logo-section"></div></div>
    <div class="main-content">
        <div class="container">
            <div class="system-settings-container">
                <div class="header-section">
                    <div class="header-title-row">
                        <h1 class="header-title"><i class="fas fa-cogs"></i> System Settings</h1>
                        <?php if (true): ?>
                        <button type="button" class="reset-app-btn" data-action="reset-all-data" data-permission="manage_settings" title="Clear all data"><i class="fas fa-eraser"></i> Reset App</button>
                        <?php endif; ?>
                    </div>
                    <p class="header-subtitle">Manage all system configurations and settings</p>
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-number" id="totalUsers">-</div><div class="stat-label">Total Users</div></div>
                        <div class="stat-card"><div class="stat-number" id="totalAgents">-</div><div class="stat-label">Total Agents</div></div>
                        <div class="stat-card"><div class="stat-number" id="totalWorkers">-</div><div class="stat-label">Total Workers</div></div>
                        <div class="stat-card"><div class="stat-number" id="totalCases">-</div><div class="stat-label">Total Cases</div></div>
                    </div>
                </div>
                <div class="settings-grid">
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-users-cog"></i> Users</h3><span class="setting-count" id="usersCount">-</span></div>
                        <p>Add, edit, or remove user accounts for this country's program</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="users" data-permission="manage_settings"><i class="fas fa-user-edit"></i> Manage Users</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-building"></i> Office Manager</h3><span class="setting-count">1</span></div>
                        <p>Manage office administrator information and contact details</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="office_manager" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Office Manager</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-passport"></i> Visa Management</h3><span class="setting-count" id="visaCount">-</span></div>
                        <p>Configure visa types, requirements, and processing details</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="visa_types" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Visa Types</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-users"></i> Recruitment Settings</h3><span class="setting-count" id="recruitmentCount">-</span></div>
                        <p>Manage countries, job categories, and recruitment specifications</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="recruitment_countries" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Countries</button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="job_categories" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Job Categories</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-user-check"></i> Age &amp; Appearance</h3><span class="setting-count" id="ageCount">-</span></div>
                        <p>Configure age specifications and appearance requirements</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="age_specifications" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Age Specs</button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="appearance_specifications" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Appearance</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-tags"></i> Status Management</h3><span class="setting-count" id="statusCount">-</span></div>
                        <p>Configure status specifications and request statuses</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="status_specifications" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Status Specs</button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="request_statuses" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Request Statuses</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-plane-arrival"></i> Arrival Management</h3><span class="setting-count" id="arrivalCount">-</span></div>
                        <p>Manage arrival agencies and stations</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="arrival_agencies" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Agencies</button>
                        <button class="modern-btn modern-btn-secondary" data-action="open-setting-modal" data-setting="arrival_stations" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Stations</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-user-clock"></i> Worker Status</h3><span class="setting-count" id="workerStatusCount">-</span></div>
                        <p>Configure worker status types and categories</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="worker_statuses" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Worker Statuses</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-money-bill-wave"></i> Currency Management</h3><span class="setting-count" id="currencyCount">-</span></div>
                        <p>Manage available currencies</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="currencies" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage Currencies</button>
                    </div>
                    <div class="setting-card" data-permission="manage_settings">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-cog"></i> System Configuration</h3><span class="setting-count" id="systemConfigCount">-</span></div>
                        <p>Manage system settings, configurations, and preferences</p>
                        <div class="setting-actions">
                            <button class="modern-btn modern-btn-primary" data-action="open-company-info" data-permission="manage_settings"><i class="fas fa-building"></i> Company Information</button>
                            <button class="modern-btn modern-btn-primary" data-action="open-setting-modal" data-setting="system_config" data-permission="manage_settings"><i class="fas fa-edit"></i> Manage System Config</button>
                        </div>
                    </div>
                    <div class="setting-card" id="systemHistorySettingCard" data-permission="view_system_history">
                        <div class="setting-header"><h3 class="setting-title"><i class="fas fa-history"></i> System History</h3><span class="setting-count" id="systemHistoryCountBadge">-</span></div>
                        <p>View activity history for Agents, Workers, Cases, HR, Settings</p>
                        <button class="modern-btn modern-btn-primary" data-action="open-system-history" data-permission="view_system_history"><i class="fas fa-history"></i> View History</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Main Modal -->
<div id="mainModal" class="modern-modal modal-hidden"><div class="modern-modal-content"><div class="modern-modal-header"><h2 class="modern-modal-title" id="modalTitle">Settings</h2></div><div id="modalBody"></div></div></div>
<!-- Form Popup Modal -->
<div id="formPopupModal" class="modern-modal modal-hidden"><div class="modern-modal-content form-popup-content"><div class="modern-modal-header"><h2 class="modern-modal-title" id="formPopupTitle">Add Item</h2></div><div id="formPopupBody"></div></div></div>
<!-- Quick Add Modal -->
<div id="quickAddModal" class="modern-modal modal-hidden"><div class="modern-modal-content"><div class="modern-modal-header"><h2 class="modern-modal-title">Quick Add</h2></div><div class="quick-add-grid" id="quickAddGrid"></div></div></div>
<!-- History Modal -->
<div id="historyModal" class="modern-modal modal-hidden"><div class="modern-modal-content history-modal-content"><div class="modern-modal-header"><h2 class="modern-modal-title" id="historyModalTitle">Activity History</h2><button class="modal-close" data-action="close-history-modal"><i class="fas fa-times"></i></button></div><div id="historyModalBody" class="history-modal-body"></div></div></div>
<!-- Fingerprint Modal -->
<div id="fingerprintRegistrationModal" class="modern-modal modal-hidden"><div class="modern-modal-content fingerprint-modal-content"><div class="modern-modal-header"><h2 class="modern-modal-title"><i class="fas fa-fingerprint"></i> <span id="fingerprintModalTitle">Register Fingerprint</span></h2><button class="modal-close" data-action="close-fingerprint-modal"><i class="fas fa-times"></i></button></div><div class="fingerprint-registration-body"><div id="fingerprintRegistrationStatus" class="fingerprint-status-hidden"></div></div><div class="fingerprint-actions"><button class="modern-btn modern-btn-secondary fingerprint-action-btn" data-action="close-fingerprint-modal"><i class="fas fa-times"></i> Cancel</button><button id="fingerprintRegisterBtn" class="modern-btn modern-btn-primary fingerprint-action-btn" data-action="execute-fingerprint-registration"><i class="fas fa-fingerprint"></i> Register Fingerprint</button></div></div></div>
<!-- User Permissions Modal -->
<div id="userPermissionsManagementModal" class="modern-modal modal-hidden"><div class="modern-modal-content permissions-modal-content"><div class="modern-modal-header"><h2 class="modern-modal-title"><i class="fas fa-user-key"></i> <span id="userPermissionsModalTitle">Manage User Permissions</span></h2><button class="modal-close" data-action="close-user-permissions-modal"><i class="fas fa-times"></i></button></div><div class="permissions-management-body"><div class="permissions-user-info"><div class="user-info-card"><i class="fas fa-user"></i><div><strong id="userPermissionsUserName">Loading...</strong><small id="userPermissionsUserId">User ID: -</small></div></div><div class="permissions-note"><i class="fas fa-info-circle"></i> User-specific permissions override role permissions.</div><div class="permissions-legend"><span class="legend-green">Green</span> = Active (granted), <span class="legend-red">Red</span> = Inactive (not granted). Click to toggle.</div></div><div id="userPermissionsGroupsContainer" class="permissions-groups-container"></div><div id="userPermissionsStatus" class="permissions-status-hidden"></div></div><div class="permissions-actions"><button class="modern-btn modern-btn-secondary permissions-action-btn" data-action="close-user-permissions-modal"><i class="fas fa-times"></i> Cancel</button><button class="modern-btn modern-btn-info permissions-action-btn" data-action="select-all-user-permissions"><i class="fas fa-check-square"></i> Select All</button><button class="modern-btn modern-btn-warning permissions-action-btn" data-action="clear-user-permissions"><i class="fas fa-undo"></i> Clear</button><button id="saveUserPermissionsBtn" class="modern-btn modern-btn-primary permissions-action-btn" data-action="save-user-permissions"><i class="fas fa-save"></i> Save Permissions</button></div></div></div>
