<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/settings.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/settings.php`.
 */
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "System Settings";
$pageCss = asset('css/settings.css');
$pageJs = asset('js/settings/settings.js');

include '../includes/header.php';
?>

<div class="settings-wrapper">
    <!-- Settings Categories Grid -->
    <div class="settings-categories">
        <!-- System Settings -->
        <div class="settings-category" data-category="system">
            <h3><i class="fas fa-cog"></i> System Settings</h3>
            <div class="category-items">
                <div class="category-item" data-type="office_manager">
                    <i class="fas fa-user-tie"></i>
                    <span>Office Manager</span>
                </div>
                <div class="category-item" data-type="visa_types">
                    <i class="fas fa-passport"></i>
                    <span>Visa Types</span>
                </div>
                <div class="category-item" data-type="experience">
                    <i class="fas fa-briefcase"></i>
                    <span>Experience Levels</span>
                </div>
            </div>
        </div>

        <!-- Recruitment Settings -->
        <div class="settings-category" data-category="recruitment">
            <h3><i class="fas fa-globe"></i> Recruitment Settings</h3>
            <div class="category-items">
                <div class="category-item" data-type="countries">
                    <i class="fas fa-flag"></i>
                    <span>Countries</span>
                </div>
                <div class="category-item" data-type="jobs">
                    <i class="fas fa-hard-hat"></i>
                    <span>Jobs</span>
                </div>
                <div class="category-item" data-type="age_specs">
                    <i class="fas fa-user"></i>
                    <span>Age Specifications</span>
                </div>
            </div>
        </div>

        <!-- Worker Settings -->
        <div class="settings-category" data-category="worker">
            <h3><i class="fas fa-users"></i> Worker Settings</h3>
            <div class="category-items">
                <div class="category-item" data-type="appearance">
                    <i class="fas fa-eye"></i>
                    <span>Appearance Types</span>
                </div>
                <div class="category-item" data-type="status">
                    <i class="fas fa-info-circle"></i>
                    <span>Worker Status</span>
                </div>
                <div class="category-item" data-type="conditions">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Worker Conditions</span>
                </div>
            </div>
        </div>

        <!-- Arrival Settings -->
        <div class="settings-category" data-category="arrival">
            <h3><i class="fas fa-plane-arrival"></i> Arrival Settings</h3>
            <div class="category-items">
                <div class="category-item" data-type="destinations">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Destinations</span>
                </div>
                <div class="category-item" data-type="terminals">
                    <i class="fas fa-building"></i>
                    <span>Terminals</span>
                </div>
            </div>
        </div>

        <!-- User Management -->
                    <div class="settings-category" data-category="user_management">
                <h3><i class="fas fa-users-cog"></i> User Management</h3>
                <div class="category-items">
                    <?php if (false): ?>
                    <div class="category-item" data-type="users" data-href="system-settings.php">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </div>
                    <?php endif; ?>
                    <div class="category-item" data-type="biometric_settings">
                        <i class="fas fa-fingerprint"></i>
                        <span>Biometric Settings</span>
                    </div>
                    <?php if (false): ?>
                    <div class="category-item" data-type="system_settings" data-href="system-settings.php">
                        <i class="fas fa-cogs"></i>
                        <span>System Settings</span>
                    </div>
                    <?php endif; ?>
                    <div class="category-item" data-type="logout" data-href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </div>
                </div>
            </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <div class="modal-actions">
                    <button class="btn-add" data-action="add-new-item">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    <button class="close-modal" data-action="close-settings-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="data-table-container">
                    <!-- Table or loading state will be rendered here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Form Modal -->
    <div id="formModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="formTitle"></h2>
                <button class="close-modal" data-action="close-form-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="settingsForm">
                    <input type="hidden" name="action" value="">
                    <input type="hidden" name="category" value="">
                    <input type="hidden" name="type" value="">
                    <input type="hidden" name="id" value="">
                    <div id="formFields">
                        <!-- Form fields will be generated here -->
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" data-action="close-form-modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 