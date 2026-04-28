<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/profile.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/profile.php`.
 */
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "My Profile";
$pageCss = [
    asset('css/dashboard.css'),
    asset('css/profile.css') . "?v=" . time(),
    asset('css/system-settings.css') . "?v=" . time()
];
$modernFormsVersion = file_exists(__DIR__ . '/../js/modern-forms.js')
    ? filemtime(__DIR__ . '/../js/modern-forms.js')
    : time();
$countriesCitiesVersion = file_exists(__DIR__ . '/../js/countries-cities.js')
    ? filemtime(__DIR__ . '/../js/countries-cities.js')
    : time();

$unifiedHistoryVersion = file_exists(__DIR__ . '/../js/unified-history.js')
    ? filemtime(__DIR__ . '/../js/unified-history.js')
    : time();

$pageJs = [
    asset('js/dashboard.js') . "?v=" . time(),
    asset('js/countries-cities.js') . "?v=" . $countriesCitiesVersion,
    asset('js/modern-forms.js') . "?v=" . $modernFormsVersion,
    asset('js/profile.js') . "?v=" . time(),
    asset('js/system-settings.js') . "?v=" . time(),
    asset('js/unified-history.js') . "?v=" . $unifiedHistoryVersion
];

// Get full user profile data from database (similar to settings-api.php users query)
$userProfile = [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? 'Unknown',
    'email' => '',
    'phone' => '',
    'country_id' => null,
    'city' => '',
    'password_plain' => '',
    'has_fingerprint' => 0,
    'fingerprint_status' => 'Not Registered',
    'role' => ucfirst($_SESSION['role'] ?? 'User'),
    'role_id' => $_SESSION['role_id'] ?? null,
    'status' => 'active',
    'last_login' => 'Never',
    'created_at' => '',
    'role_description' => ''
];

try {
    // Get full user data including fingerprint status, country, city, password_plain
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 1 ELSE 0 END AS has_fingerprint,
            CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 'Registered' ELSE 'Not Registered' END AS fingerprint_status,
               r.role_name, r.description as role_description
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN (
            SELECT user_id, MAX(id) AS latest_template_id
            FROM fingerprint_templates
            WHERE template_data IS NOT NULL AND template_data <> ''
            GROUP BY user_id
        ) fp ON u.user_id = fp.user_id
        LEFT JOIN (
            SELECT user_id, credential_id
            FROM webauthn_credentials
            GROUP BY user_id
        ) wc ON u.user_id = wc.user_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $userProfile = [
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? '',
            'country_id' => $userData['country_id'] ?? null,
            'city' => $userData['city'] ?? '',
            'password_plain' => $userData['password_plain'] ?? '',
            'has_fingerprint' => $userData['has_fingerprint'] ?? 0,
            'fingerprint_status' => $userData['fingerprint_status'] ?? 'Not Registered',
            'role' => $userData['role_name'] ?? ucfirst($_SESSION['role'] ?? 'User'),
            'role_id' => $userData['role_id'] ?? null,
            'status' => $userData['status'] ?? 'active',
            'last_login' => $userData['last_login'] ? date('M d, Y H:i', strtotime($userData['last_login'])) : 'Never',
            'created_at' => $userData['created_at'] ? date('M d, Y', strtotime($userData['created_at'])) : '',
            'role_description' => $userData['role_description'] ?? ''
        ];
        
        // Get country name if country_id exists
        if ($userProfile['country_id']) {
            try {
                $countryStmt = $conn->prepare("SELECT country_name FROM recruitment_countries WHERE id = ?");
                $countryStmt->bind_param("i", $userProfile['country_id']);
                $countryStmt->execute();
                $countryResult = $countryStmt->get_result();
                if ($countryResult->num_rows > 0) {
                    $countryData = $countryResult->fetch_assoc();
                    $userProfile['country_name'] = $countryData['country_name'];
                }
            } catch (Exception $e) {
                // Country lookup failed, use ID or empty
            }
        }
    }
    
    // Get recent activities for this user
    $activities = null;
    $userId = intval($_SESSION['user_id']);
    
    // Try multiple history tables in order of preference
    try {
        // First try: activity_logs table
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $activities = $conn->query("
                SELECT al.*, u.username, al.description as activity_text,
                       COALESCE(al.description, CONCAT(al.action, ' - ', al.module)) as display_text
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.user_id = {$userId}
                ORDER BY al.created_at DESC LIMIT 10
            ");
            if ($activities && $activities->num_rows > 0) {
                // Success, activities found
            } else {
                $activities = null;
            }
        }
    } catch (Exception $e) {
        error_log("Activity logs query error: " . $e->getMessage());
        $activities = null;
    }
    
    // If activity_logs didn't work, try global_history
    if (!$activities || $activities->num_rows === 0) {
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $activities = $conn->query("
                    SELECT gh.*, gh.user_name as username,
                           CONCAT(gh.action, ' in ', gh.table_name, ' (', gh.module, ')') as display_text
                    FROM global_history gh
                    WHERE gh.user_id = {$userId}
                    ORDER BY gh.created_at DESC LIMIT 10
                ");
            }
        } catch (Exception $e) {
            error_log("Global history query error: " . $e->getMessage());
            $activities = null;
        }
    }
    
    // Last resort: try system_history
    if (!$activities || ($activities && $activities->num_rows === 0)) {
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'system_history'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $activities = $conn->query("
                    SELECT sh.*, u.username,
                           COALESCE(sh.description, CONCAT(sh.action, ' - ', sh.module)) as display_text
                    FROM system_history sh
                    LEFT JOIN users u ON sh.user_id = u.user_id
                    WHERE sh.user_id = {$userId}
                    ORDER BY sh.created_at DESC LIMIT 10
                ");
            }
        } catch (Exception $e) {
            error_log("System history query error: " . $e->getMessage());
            $activities = null;
        }
    }
} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    $activities = null;
}

include '../includes/header.php';
?>

<div class="main-content" data-user-id="<?php echo $userProfile['user_id']; ?>">
    <div class="header-bar">
        <h1>👤 My Profile</h1>
        <div class="flashing-text" id="flashingText">System Ready</div>
    </div>

    <!-- Success/Error Messages -->
    <div id="successMessage" class="alert alert-success d-none"></div>
    <div id="errorMessage" class="alert alert-danger d-none"></div>

    <!-- User Profile Table View (Similar to System Settings Users Modal) -->
    <div class="modern-data-table profile-table">
        <div class="table-container">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Country</th>
                        <th>City</th>
                        <th>Fingerprint</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-user-id="<?php echo $userProfile['user_id']; ?>">
                        <!-- Username -->
                        <td>
                            <span class="cell-clip cell-name"><?php echo htmlspecialchars($userProfile['username']); ?></span>
                        </td>
                        <!-- Password -->
                        <td class="cell-password">
                            <?php
                            $hasPassword = !empty($userProfile['password_plain']);
                            $encodedPassword = $hasPassword ? base64_encode($userProfile['password_plain']) : '';
                            ?>
                            <span class="password-status password-toggle-container" 
                                  data-user-id="<?php echo $userProfile['user_id']; ?>" 
                                  data-password-visible="false"
                                  data-password-value="<?php echo htmlspecialchars($encodedPassword); ?>">
                                <i class="fas fa-eye password-toggle-icon"></i>
                                <span class="password-text"><?php echo $hasPassword ? '••••••••' : 'Not Set'; ?></span>
                            </span>
                        </td>
                        <!-- Email -->
                        <td>
                            <span class="cell-clip cell-email"><?php echo htmlspecialchars($userProfile['email'] ?: '-'); ?></span>
                        </td>
                        <!-- Phone -->
                        <td>
                            <span class="cell-clip cell-phone"><?php echo htmlspecialchars($userProfile['phone'] ?: '-'); ?></span>
                        </td>
                        <!-- Country -->
                        <td>
                            <span class="cell-clip cell-country_id"><?php echo htmlspecialchars($userProfile['country_name'] ?? ($userProfile['country_id'] ?: '-')); ?></span>
                        </td>
                        <!-- City -->
                        <td>
                            <span class="cell-clip cell-city"><?php echo htmlspecialchars($userProfile['city'] ?: '-'); ?></span>
                        </td>
                        <!-- Fingerprint -->
                        <td class="cell-fingerprint_status">
                            <?php
                            $isRegistered = ($userProfile['fingerprint_status'] ?? '') === 'Registered';
                            $buttonClass = $isRegistered ? 'modern-btn-danger' : 'modern-btn-success';
                            $icon = $isRegistered ? 'fa-times-circle' : 'fa-fingerprint';
                            $label = $isRegistered ? 'Unregister' : 'Register';
                            $action = $isRegistered ? 'fingerprint-unregister' : 'fingerprint-action';
                            ?>
                            <button type="button" class="modern-btn modern-btn-sm <?php echo $buttonClass; ?> fingerprint-action-btn" 
                                data-action="<?php echo $action; ?>"
                                data-id="<?php echo $userProfile['user_id']; ?>"
                                data-username="<?php echo htmlspecialchars($userProfile['username']); ?>"
                                data-status="<?php echo htmlspecialchars($userProfile['fingerprint_status']); ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                                <span class="btn-text"><?php echo $label; ?></span>
                </button>
                        </td>
                        <!-- Status -->
                        <td class="cell-status">
                            <span class="status-badge <?php echo $userProfile['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst($userProfile['status']); ?>
                            </span>
                        </td>
                        <!-- Actions -->
                        <td class="actions-cell">
                            <button class="modern-btn modern-btn-sm modern-btn-primary" data-action="edit-profile" data-user-id="<?php echo $userProfile['user_id']; ?>" title="Edit Profile" aria-label="Edit Profile">
                                <i class="fas fa-edit"></i>
                                <span class="btn-text">Edit</span>
                </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="activities-section">
        <div class="activities-header">
            <h2>📜 Recent Activities</h2>
            <button class="modern-btn modern-btn-sm modern-btn-secondary view-full-history-btn" data-action="view-full-history" data-permission="view_system_history" title="View Full History">
                <i class="fas fa-history"></i>
                <span>View Full History</span>
            </button>
        </div>
        <div class="activity-list" id="profileActivitiesList">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i> Loading activities...
            </div>
        </div>
    </div>
</div>

<!-- Form Popup Modal (Same as System Settings) -->
<div id="formPopupModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content form-popup-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="formPopupTitle">Edit Profile</h2>
            <button class="modal-close" data-action="close-form">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="formPopupBody">
            <!-- Form content will be loaded here by ModernForms -->
        </div>
    </div>
</div>

<!-- History Modal (Same as System Settings) -->
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

<!-- Delete Account Modal -->
<div id="deleteAccountModal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🗑️ Delete Account</h2>
            <span class="close" data-action="close-delete-account-modal">&times;</span>
        </div>
        <div class="modal-body">
            <p><strong>Warning:</strong> This action cannot be undone!</p>
            <p>Are you sure you want to delete your account?</p>
            <div class="form-group">
                <label for="delete_confirm">Type "DELETE" to confirm:</label>
                <input type="text" id="delete_confirm" name="delete_confirm" placeholder="Type DELETE here">
            </div>
            <div class="form-actions">
                <button class="btn btn-danger" data-action="confirm-delete-account">Delete Account</button>
                <button class="btn btn-secondary" data-action="close-delete-account-modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Fingerprint Registration Modal (from system-settings.php) -->
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

<!-- User Permissions Management Modal (from system-settings.php) -->
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
            <button id="saveUserPermissionsBtn" class="modern-btn modern-btn-primary permissions-action-btn" data-action="save-user-permissions">
                <i class="fas fa-save"></i> Save Permissions
            </button>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>

