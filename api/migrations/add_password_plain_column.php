/*
 * EN: Frontend helper script for triggering password migration endpoint and showing UI feedback.
 * AR: سكربت واجهة لتشغيل ترحيل كلمة المرور عبر API مع عرض رسائل الحالة للمستخدم.
 */
// System Settings - Button handlers that integrate with ModernForms
// Handle close fingerprint modal and other actions

// Run password_plain migration
document.addEventListener('DOMContentLoaded', function() {
    const migrationBtn = document.getElementById('runPasswordMigrationBtn');
    if (migrationBtn) {
        migrationBtn.addEventListener('click', async function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Migration...';
            btn.disabled = true;
            
            try {
                const response = await fetch('../api/migrations/add_password_plain_column.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first to check if it's valid JSON
                const responseText = await response.text();
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
                }
                
                if (result.success) {
                    let message = result.message;
                    if (result.stats) {
                        message += `\n\nTotal Users: ${result.stats.total_users}\n`;
                        message += `Users with password_plain: ${result.stats.users_with_password_plain}\n`;
                        message += `Users without password_plain: ${result.stats.users_without_password_plain}`;
                    }
                    if (result.note) {
                        message += `\n\n${result.note}`;
                    }
                    
                    alert(message);
                    
                    // Refresh users table if it's open
                    if (window.modernForms && window.modernForms.currentTable === 'users') {
                        await window.modernForms.loadTableData('users');
                        window.modernForms.renderTable();
                    }
                } else {
                    alert('Migration failed: ' + result.message);
                }
            } catch (error) {
                alert('Error running migration: ' + error.message);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }
});

// CRITICAL: MutationObserver to catch and mask any passwords that appear in the DOM
const passwordMaskObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    // Check all cells in the added node
                    const cells = node.querySelectorAll ? node.querySelectorAll('td, .cell-password, [class*="password"]') : [];
                    cells.forEach(function(cell) {
                        const cellText = (cell.textContent || cell.innerText || '').trim();
                        const hasToggle = cell.querySelector && cell.querySelector('.password-toggle-container');
                        
                        // If cell has text that looks like password but no toggle, mask it immediately
                        if (!hasToggle && cellText && 
                            cellText !== '••••••••' && 
                            cellText !== 'Not Set' &&
                            cellText.length < 60 &&
                            !cellText.startsWith('$2y$') &&
                            !cellText.startsWith('$2a$') &&
                            !cellText.startsWith('$2b$') &&
                            !cellText.includes('fa-') &&
                            !cellText.includes('password-toggle')) {
                            
                            // Check if this is a password column
                            const isPasswordCell = cell.classList && (
                                cell.classList.contains('cell-password') ||
                                cell.className.includes('cell-password') ||
                                cell.closest('tr')?.querySelector('td.cell-password') === cell
                            );
                            
                            if (isPasswordCell || cell.closest('table')?.querySelector('thead th')?.textContent?.toLowerCase().includes('password')) {
                                const row = cell.closest('tr');
                                const userId = row?.getAttribute('data-id') || 
                                              row?.querySelector('input[data-id]')?.getAttribute('data-id') || '';
                                let encodedPassword = '';
                                try {
                                    encodedPassword = btoa(unescape(encodeURIComponent(cellText)));
                                } catch (e) {
                                    encodedPassword = '';
                                }
                                if (cell.innerHTML !== undefined) {
                                    cell.className = 'cell-clip cell-password';
                                    cell.innerHTML = `
                                        <span class="password-status password-toggle-container" 
                                              data-user-id="${userId}" 
                                              data-password-visible="false"
                                              data-password-value="${encodedPassword.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}"
                                              title="Click to toggle password visibility">
                                            <i class="fas fa-eye password-toggle-icon icon-hidden"></i>
                                            <span class="password-text">••••••••</span>
                                        </span>
                                    `;
                                }
                            }
                        }
                    });
                }
            });
        }
    });
});

// Start observing the document body for password cells
if (document.body) {
    passwordMaskObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
}

// Global password toggle handler for main table (not just modals)
document.addEventListener('click', function(e) {
    // Handle password toggle in main table
    const passwordToggle = e.target.closest('.password-toggle-container');
    const passwordCell = e.target.closest('.cell-password');
    const passwordStatus = e.target.closest('.password-status');
    
    // Only handle if it's a password-related element and not in a modal
    if ((passwordToggle || passwordCell || passwordStatus) && !e.target.closest('.modern-modal-content')) {
        // Don't handle if it's a button click (like permissions button)
        if (e.target.closest('button') && !e.target.closest('.password-toggle-container')) return;
        
        const container = passwordToggle || passwordStatus || (passwordCell ? passwordCell.querySelector('.password-toggle-container') || passwordCell.querySelector('.password-status') : null);
        if (!container) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const icon = container.querySelector('.password-toggle-icon');
        const text = container.querySelector('.password-text');
        if (!icon || !text) return;
        
        const isVisible = container.getAttribute('data-password-visible') === 'true';
        const encodedPassword = container.getAttribute('data-password-value') || '';
        
        if (isVisible) {
            // Hide password - show dots
            icon.classList.remove('fa-eye-slash', 'icon-visible');
            icon.classList.add('fa-eye', 'icon-hidden');
            text.classList.remove('password-visible');
            text.textContent = '••••••••';
            container.setAttribute('data-password-visible', 'false');
        } else {
            // Show password
            if (encodedPassword) {
                try {
                    const decodedPassword = decodeURIComponent(escape(atob(encodedPassword)));
                    icon.classList.remove('fa-eye', 'icon-hidden');
                    icon.classList.add('fa-eye-slash', 'icon-visible');
                    text.classList.add('password-visible');
                    text.textContent = decodedPassword;
                    container.setAttribute('data-password-visible', 'true');
                } catch (err) {
                    console.error('Error decoding password:', err);
                    text.textContent = 'Error';
                }
            } else {
                text.textContent = 'Not Set';
            }
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-action="close-fingerprint-modal"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof closeFingerprintRegistrationModal === 'function') {
                closeFingerprintRegistrationModal();
            }
        });
    });
    
    document.querySelectorAll('[data-action="execute-fingerprint-registration"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof executeFingerprintRegistration === 'function') {
                executeFingerprintRegistration();
            }
        });
    });
    
    document.querySelectorAll('[data-action="close-user-permissions-modal"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof closeUserPermissionsModal === 'function') {
                closeUserPermissionsModal();
            }
        });
    });
    
    document.querySelectorAll('[data-action="select-all-user-permissions"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof selectAllUserPermissionsGlobal === 'function') {
                selectAllUserPermissionsGlobal();
            }
        });
    });
    
    document.querySelectorAll('[data-action="clear-user-permissions"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof clearUserPermissions === 'function') {
                clearUserPermissions();
            }
        });
    });
    
    document.querySelectorAll('[data-action="save-user-permissions"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof saveUserPermissions === 'function') {
                saveUserPermissions();
            }
        });
    });
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="open-setting-modal"]');
    if (btn && window.modernForms) {
        const key = btn.getAttribute('data-setting');
        if (key) {
            // Map setting keys to table names
            const tableMap = {
                'office_manager': 'office_managers',
                'visa_types': 'visa_types',
                'recruitment_countries': 'recruitment_countries',
                'job_categories': 'job_categories',
                'age_specifications': 'age_specifications',
                'appearance_specifications': 'appearance_specifications',
                'status_specifications': 'status_specifications',
                'request_statuses': 'request_statuses',
                'arrival_agencies': 'arrival_agencies',
                'arrival_stations': 'arrival_stations',
                'worker_statuses': 'worker_statuses',
                'system_config': 'system_config',
                'currencies': 'currencies',
                'users': 'users'
            };
            
            const tableName = tableMap[key] || key;
            window.modernForms.openSettingModal(tableName);
        }
    }
    
    // Handle System History button - Use Unified History with module selector
    const historyBtn = e.target.closest('[data-action="open-system-history"]');
    if (historyBtn && window.UnifiedHistory) {
        e.preventDefault();
        e.stopPropagation();
        if (!window.unifiedHistory) {
            window.unifiedHistory = new UnifiedHistory();
            window.unifiedHistory.initModal();
        }
        window.unifiedHistory.openModal('all'); // Default to "All Modules" to show entire program history
    }
    
    // Handle User Management button - Open user management overlay
    const userMgmtBtn = e.target.closest('[data-action="open-user-management"]');
    if (userMgmtBtn) {
        e.preventDefault();
        e.stopPropagation();
        if (window.modernForms) {
            window.modernForms.openSettingModal('users');
        }
    }
});

// Auto-open modal from URL parameter - IMMEDIATE
function autoOpenSettingModal() {
    const urlParams = new URLSearchParams(window.location.search);
    const setting = urlParams.get('setting');
    
    if (!setting) return;
    
    // Map setting keys to table names
    const tableMap = {
        'office_manager': 'office_managers',
        'visa_types': 'visa_types',
        'recruitment_countries': 'recruitment_countries',
        'job_categories': 'job_categories',
        'age_specifications': 'age_specifications',
        'appearance_specifications': 'appearance_specifications',
        'status_specifications': 'status_specifications',
        'request_statuses': 'request_statuses',
        'arrival_agencies': 'arrival_agencies',
        'arrival_stations': 'arrival_stations',
        'worker_statuses': 'worker_statuses',
        'system_config': 'system_config',
        'users': 'users'
    };
    
    const tableName = tableMap[setting] || setting;
    
    // Try to open immediately if modernForms is ready
    if (window.modernForms && typeof window.modernForms.openSettingModal === 'function') {
        window.modernForms.openSettingModal(tableName);
        // Clean up URL parameter
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        return;
    }
    
    // If not ready, wait for it with faster polling
    let attempts = 0;
    const maxAttempts = 50; // 2.5 seconds max wait
    
    const checkModernForms = setInterval(function() {
        attempts++;
        
        if (window.modernForms && typeof window.modernForms.openSettingModal === 'function') {
            clearInterval(checkModernForms);
            window.modernForms.openSettingModal(tableName);
            // Clean up URL parameter
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        } else if (attempts >= maxAttempts) {
            clearInterval(checkModernForms);
        }
    }, 50); // Check every 50ms for faster response
}

// Try to open immediately - multiple strategies for fastest response
function triggerAutoOpen() {
    const urlParams = new URLSearchParams(window.location.search);
    const setting = urlParams.get('setting');
    
    if (!setting) return;
    
    // Strategy 1: Try to click the button directly (fastest)
    const button = document.querySelector(`[data-action="open-setting-modal"][data-setting="${setting}"]`);
    if (button) {
        button.click();
        return;
    }
    
    // Strategy 2: Use modernForms if available
    autoOpenSettingModal();
}

// Try immediately when script loads (before DOM ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', triggerAutoOpen);
} else {
    // DOM already loaded
    triggerAutoOpen();
}

// Also try on window load as backup
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('setting') && !document.getElementById('mainModal')?.classList.contains('show')) {
        triggerAutoOpen();
    }
});

// Fingerprint Registration Modal Functions
let currentFingerprintUserId = null;
let currentFingerprintUsername = null;

window.openFingerprintRegistrationModal = function(userId, username, isUpdate = false) {
    const modal = document.getElementById('fingerprintRegistrationModal');
    const title = document.getElementById('fingerprintModalTitle');
    const usernameEl = document.getElementById('fingerprintRegUsername');
    const userIdEl = document.getElementById('fingerprintRegUserId');
    const statusDiv = document.getElementById('fingerprintRegistrationStatus');
    const registerBtn = document.getElementById('fingerprintRegisterBtn');
    
    if (!modal) {
        return;
    }
    
    currentFingerprintUserId = userId;
    currentFingerprintUsername = username;
    
    // Update modal content
    title.textContent = isUpdate ? 'Update Fingerprint' : 'Register Fingerprint';
    usernameEl.textContent = username || 'Unknown';
    userIdEl.textContent = userId || '-';
    statusDiv.className = 'fingerprint-status-hidden';
    statusDiv.textContent = '';
    registerBtn.disabled = false;
    registerBtn.innerHTML = isUpdate 
        ? '<i class="fas fa-sync-alt"></i> Update Fingerprint'
        : '<i class="fas fa-fingerprint"></i> Register Fingerprint';
    
    // Show modal
    modal.classList.remove('modal-hidden');
    modal.classList.add('show');
    document.body.classList.add('modal-open');
}

function closeFingerprintRegistrationModal() {
    const modal = document.getElementById('fingerprintRegistrationModal');
    const statusDiv = document.getElementById('fingerprintRegistrationStatus');
    
    if (modal) {
        modal.classList.remove('show');
        modal.classList.add('modal-hidden');
        document.body.classList.remove('modal-open');
    }
    
    // Reset status
    if (statusDiv) {
        statusDiv.className = 'fingerprint-status-hidden';
        statusDiv.textContent = '';
    }
    
    currentFingerprintUserId = null;
    currentFingerprintUsername = null;
}

async function executeFingerprintRegistration() {
    if (!currentFingerprintUserId || !currentFingerprintUsername) {
        showFingerprintRegistrationStatus('❌ Invalid user information', 'error');
        return;
    }
    
    const statusDiv = document.getElementById('fingerprintRegistrationStatus');
    const registerBtn = document.getElementById('fingerprintRegisterBtn');
    
    // First, check if WebAuthn is supported
    if (!window.PublicKeyCredential) {
        showFingerprintRegistrationStatus('❌ Your browser does not support fingerprint authentication. Please use Chrome, Edge, or Firefox.', 'error');
        return;
    }
    
    // Check if Windows Hello is available BEFORE attempting registration
    statusDiv.className = 'fingerprint-status-visible info-message';
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking Windows Hello availability...';
    registerBtn.disabled = true;
    registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    
    try {
        const isAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        
        if (!isAvailable) {
            const setupInstructions = `
                <div class="fingerprint-error-content">
                    <p class="fingerprint-error-title"><strong>❌ Windows Hello is not set up on your device.</strong></p>
                    
                    <div class="fingerprint-error-box">
                        <p class="fingerprint-error-box-title">
                            <i class="fas fa-exclamation-triangle"></i> <strong>PREREQUISITE:</strong> You must have a Windows password set up first!
                        </p>
                        <p class="fingerprint-error-box-text">
                            If you see "You must add a password before you can use this sign-in option", set up a password first in Sign-in options > Password.
                        </p>
                    </div>
                    
                    <p class="fingerprint-error-text">Follow these steps to set up Windows Hello:</p>
                    <ol class="fingerprint-instructions-list">
                        <li><strong>First:</strong> Set up a Windows password (if not already set) in Sign-in options > Password</li>
                        <li>Press <strong>Windows Key + I</strong> to open Windows Settings</li>
                        <li>Click on <strong>"Accounts"</strong></li>
                        <li>Click on <strong>"Sign-in options"</strong> in the left menu</li>
                        <li>Find <strong>"Windows Hello Fingerprint"</strong> section</li>
                        <li>Click <strong>"Set up"</strong> button (should be enabled after password is set)</li>
                        <li>Follow the on-screen instructions to enroll your fingerprint</li>
                        <li>Come back here and try registering again</li>
                    </ol>
                    <p class="fingerprint-tip-box">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> You can also search for "Windows Hello" in the Start menu for quick access.
                    </p>
                </div>
            `;
            statusDiv.className = 'fingerprint-status-visible error-message';
            statusDiv.innerHTML = setupInstructions;
            registerBtn.disabled = false;
            registerBtn.innerHTML = '<i class="fas fa-fingerprint"></i> Register Fingerprint';
            return;
        }
        
        // Windows Hello is available, proceed with registration
        statusDiv.className = 'fingerprint-status-visible info-message';
        statusDiv.innerHTML = '<i class="fas fa-fingerprint"></i> <strong>Ready to scan!</strong> Please place your finger on the scanner when prompted...';
        registerBtn.innerHTML = '<i class="fas fa-fingerprint"></i> Waiting for scan...';
        
        // Use ModernForms instance if available
        if (window.modernForms && typeof window.modernForms.registerFingerprintTemplate === 'function') {
            // Update status during scan
            statusDiv.className = 'fingerprint-status-visible info-message';
            statusDiv.innerHTML = '<i class="fas fa-fingerprint"></i> <strong>Scanning...</strong> Please place your finger on the scanner now...';
            
            await window.modernForms.registerFingerprintTemplate(currentFingerprintUserId, currentFingerprintUsername);
            
            // Show success
            statusDiv.className = 'fingerprint-status-visible success-message';
            statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> <strong>Success!</strong> Fingerprint registered successfully. The user can now use fingerprint authentication to log in.';
            
            // Close modal after delay
            setTimeout(() => {
                closeFingerprintRegistrationModal();
                
                // Refresh the table if ModernForms is available
                if (window.modernForms && typeof window.modernForms.refreshData === 'function') {
                    window.modernForms.refreshData().then(() => {
                        if (window.modernForms && typeof window.modernForms.loadTableStats === 'function') {
                            window.modernForms.loadTableStats('users').then(stats => {
                                if (window.modernForms && typeof window.modernForms.renderTableWithStats === 'function') {
                                    window.modernForms.renderTableWithStats(stats);
                                }
                            });
                        }
                    });
                }
                
                // Show notification
                if (window.modernForms && typeof window.modernForms.showNotification === 'function') {
                    window.modernForms.showNotification(
                        `✅ Fingerprint registered successfully for ${currentFingerprintUsername}!`,
                        'success',
                        5000
                    );
                }
            }, 2000);
        } else {
            throw new Error('Registration service not available');
        }
    } catch (error) {
        // Check if user cancelled - handle silently
        if (error.name === 'CancellationError' || (error.message && error.message.includes('cancelled'))) {
            // User cancelled - silently reset the button and hide status
            statusDiv.className = 'fingerprint-status-hidden';
            statusDiv.innerHTML = '';
            registerBtn.disabled = false;
            registerBtn.innerHTML = '<i class="fas fa-fingerprint"></i> Register Fingerprint';
            // Don't log cancellation as an error - it's a user choice
            return;
        }
        
            statusDiv.className = 'fingerprint-status-visible error-message';
        
        let errorMessage = error.message || 'Failed to register fingerprint';
        
        // Provide helpful error messages
        if (error.name === 'NotAllowedError' || errorMessage.includes('Windows Hello')) {
            errorMessage = `
                <div class="fingerprint-error-content">
                    <p class="fingerprint-error-title"><strong>❌ Windows Hello Setup Required</strong></p>
                    <p class="fingerprint-error-text">The fingerprint scan was cancelled or Windows Hello is not fully set up.</p>
                    
                    <div class="fingerprint-error-box">
                        <p class="fingerprint-error-box-title">
                            <i class="fas fa-exclamation-triangle"></i> <strong>IMPORTANT:</strong> You must have a Windows password set up first!
                        </p>
                        <p class="fingerprint-error-box-text">
                            If you see "You must add a password before you can use this sign-in option", set up a password first.
                        </p>
                    </div>
                    
                    <p class="fingerprint-error-text"><strong>To set up Windows Hello:</strong></p>
                    <ol class="fingerprint-instructions-list">
                        <li><strong>First:</strong> Set up a Windows password in Sign-in options > Password (if not already set)</li>
                        <li>Press <strong>Windows Key + I</strong> to open Settings</li>
                        <li>Go to <strong>Accounts > Sign-in options</strong></li>
                        <li>Click <strong>"Windows Hello Fingerprint"</strong></li>
                        <li>Click <strong>"Set up"</strong> and enroll your fingerprint</li>
                        <li>Return here and try again</li>
                    </ol>
                </div>
            `;
        } else {
            errorMessage = `<i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> ${errorMessage}`;
        }
        
        statusDiv.innerHTML = errorMessage;
        registerBtn.disabled = false;
        registerBtn.innerHTML = '<i class="fas fa-fingerprint"></i> Register Fingerprint';
    }
}

function showFingerprintRegistrationStatus(message, type) {
    const statusDiv = document.getElementById('fingerprintRegistrationStatus');
    if (statusDiv) {
        statusDiv.className = `fingerprint-status-visible ${type}-message`;
        statusDiv.textContent = message;
    }
}

// Close modal on outside click or ESC key
document.addEventListener('click', function(e) {
    const modal = document.getElementById('fingerprintRegistrationModal');
    if (modal && e.target === modal) {
        closeFingerprintRegistrationModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('fingerprintRegistrationModal');
        if (modal && modal.classList.contains('show')) {
            closeFingerprintRegistrationModal();
        }
        const permModal = document.getElementById('permissionsManagementModal');
        if (permModal && permModal.classList.contains('show')) {
            closePermissionsManagementModal();
        }
    }
});

// ============================================
// USER PERMISSIONS MANAGEMENT
// ============================================

let currentUserPermissionsUserId = null;
let currentUserPermissionsUsername = null;
let userPermissionsData = null;

// Open user permissions modal
window.openUserPermissionsModal = function(userId, username) {
    const modal = document.getElementById('userPermissionsManagementModal');
    const container = document.getElementById('userPermissionsGroupsContainer');
    const userNameSpan = document.getElementById('userPermissionsUserName');
    const userIdSpan = document.getElementById('userPermissionsUserId');
    const statusDiv = document.getElementById('userPermissionsStatus');
    
    if (!modal || !container) {
        return;
    }
    
    currentUserPermissionsUserId = userId;
    currentUserPermissionsUsername = username || 'Unknown';
    
    // Update modal title and user info
    if (userNameSpan) userNameSpan.textContent = username || 'Unknown User';
    if (userIdSpan) userIdSpan.textContent = `User ID: ${userId}`;
    
    // Show modal
    modal.classList.remove('modal-hidden');
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    // Clear previous content
    container.innerHTML = '<div class="permissions-loading">Loading permissions...</div>';
    if (statusDiv) {
        statusDiv.className = 'permissions-status-hidden';
        statusDiv.innerHTML = '';
    }
    
    // Load permissions
    loadUserPermissions(userId);
};

// Close user permissions modal
window.closeUserPermissionsModal = async function() {
    // Check if there are unsaved changes
    const container = document.getElementById('userPermissionsGroupsContainer');
    if (container) {
        const grantedButtons = container.querySelectorAll('.permission-btn.permission-granted');
        if (grantedButtons.length > 0) {
            const confirmed = await showUserPermissionsConfirm(
                'Unsaved Changes',
                'You have unsaved permission changes. Are you sure you want to close without saving?',
                'warning',
                'Discard Changes',
                'Cancel'
            );
            
            if (!confirmed) {
                return;
            }
        }
    }
    
    const modal = document.getElementById('userPermissionsManagementModal');
    if (modal) {
        modal.classList.add('modal-hidden');
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
    
    currentUserPermissionsUserId = null;
    currentUserPermissionsUsername = null;
    userPermissionsData = null;
};

// Load user permissions
async function loadUserPermissions(userId) {
    const container = document.getElementById('userPermissionsGroupsContainer');
    const statusDiv = document.getElementById('userPermissionsStatus');
    
    try {
        // Fetch all permission groups
        const response = await fetch(`../api/settings/get_permissions_groups.php?user_id=${userId}`);
        
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type');
        let data;
        
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but got: ${contentType}. Response: ${text.substring(0, 200)}`);
        }
        
        // Try to parse JSON even if status is not OK
        try {
            data = await response.json();
        } catch (jsonError) {
            // If JSON parsing fails, try to get the text
            const text = await response.text();
            throw new Error(`Failed to parse JSON response (Status: ${response.status}): ${text.substring(0, 200)}`);
        }
        
        if (!response.ok) {
            // If we got JSON but status is not OK, use the error message from JSON
            throw new Error(data.message || `HTTP error! status: ${response.status}`);
        }
        
        if (data.success && data.groups) {
            userPermissionsData = data;
            renderUserPermissionsGroups(data.groups, data.user_permissions || []);
        } else {
            container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + (data.message || 'Unknown error') + '</div>';
        }
    } catch (error) {
        container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + error.message + '</div>';
        console.error('Error loading user permissions:', error);
    }
}

// Render user permissions groups (reuse same rendering logic)
function renderUserPermissionsGroups(groups, userPermissions) {
    const container = document.getElementById('userPermissionsGroupsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<div class="permissions-error">No permission groups found</div>';
        return;
    }
    
    // Calculate total permissions
    let totalPermissions = 0;
    for (const group of groups) {
        if (group.permissions) {
            totalPermissions += group.permissions.length;
        }
    }
    
    // Show summary
    if (totalPermissions > 0) {
        const summary = document.createElement('div');
        summary.className = 'permissions-summary';
        summary.style.cssText = 'margin-bottom: 1rem; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 6px; color: var(--text); font-size: 0.9rem;';
        summary.innerHTML = `<i class="fas fa-info-circle"></i> <strong>Total:</strong> ${totalPermissions} permissions across ${groups.length} groups`;
        container.appendChild(summary);
    }
    
    const hasUserSpecificSelections = Array.isArray(userPermissions) && userPermissions.length > 0;
    
    // Render groups (reuse same function from role permissions)
    for (const group of groups) {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'permissions-group';
        
        const header = document.createElement('div');
        header.className = 'permissions-group-header';
        
        const title = document.createElement('h3');
        title.className = 'permissions-group-title';
        title.innerHTML = `${group.name} <span class="permissions-group-count">${group.count}</span>`;
        
        const actions = document.createElement('div');
        actions.className = 'permissions-group-actions';
        
        const selectAllBtn = document.createElement('button');
        selectAllBtn.className = 'permissions-btn-select-all';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
        selectAllBtn.onclick = () => selectAllUserPermissions(group.id);
        
        const cancelAllBtn = document.createElement('button');
        cancelAllBtn.className = 'permissions-btn-cancel-all';
        cancelAllBtn.innerHTML = '<i class="fas fa-times"></i> Cancel All';
        cancelAllBtn.onclick = () => cancelAllUserPermissions(group.id);
        
        actions.appendChild(selectAllBtn);
        actions.appendChild(cancelAllBtn);
        
        header.appendChild(title);
        header.appendChild(actions);
        
        const permissionsGrid = document.createElement('div');
        permissionsGrid.className = 'permissions-grid';
        permissionsGrid.id = `user-permissions-group-${group.id}`;
        
        if (group.permissions && group.permissions.length > 0) {
            for (const permission of group.permissions) {
                const permBtn = document.createElement('button');
                permBtn.className = 'permission-btn';
                permBtn.dataset.groupId = group.id;
                permBtn.dataset.permissionId = permission.id;
                permBtn.textContent = permission.name;
                
                if (hasUserSpecificSelections && userPermissions.includes(permission.id)) {
                    permBtn.classList.add('permission-granted');
                }
                
                permBtn.onclick = () => toggleUserPermission(permission.id, group.id);
                permissionsGrid.appendChild(permBtn);
            }
        }
        
        groupDiv.appendChild(header);
        groupDiv.appendChild(permissionsGrid);
        container.appendChild(groupDiv);
    }
}

// Toggle user permission
window.toggleUserPermission = function(permissionId, groupId) {
    const permBtn = document.querySelector(`#user-permissions-group-${groupId} .permission-btn[data-permission-id="${permissionId}"]`);
    if (permBtn) {
        permBtn.classList.toggle('permission-granted');
    }
};

// Select all user permissions in group
window.selectAllUserPermissions = function(groupId) {
    const groupDiv = document.querySelector(`#user-permissions-group-${groupId}`);
    if (groupDiv) {
        const buttons = groupDiv.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.add('permission-granted');
        }
    }
};

// Cancel all user permissions in group
window.cancelAllUserPermissions = function(groupId) {
    const groupDiv = document.querySelector(`#user-permissions-group-${groupId}`);
    if (groupDiv) {
        const buttons = groupDiv.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.remove('permission-granted');
        }
    }
};

// Save user permissions
window.saveUserPermissions = async function() {
    if (!currentUserPermissionsUserId || Number.isNaN(currentUserPermissionsUserId)) {
        showUserPermissionsStatus('Invalid user selected', 'error');
        return;
    }
    
    // Collect all granted permissions first to show in confirmation
    const grantedPermissions = [];
    const permissionButtons = document.querySelectorAll('#userPermissionsGroupsContainer .permission-btn.permission-granted');
    for (const btn of permissionButtons) {
        const permId = btn.dataset.permissionId;
        if (permId) {
            grantedPermissions.push(permId);
        }
    }
    
    // Show confirmation
    const confirmed = await showUserPermissionsConfirm(
        'Save Permissions',
        `Are you sure you want to save <strong>${grantedPermissions.length}</strong> permission${grantedPermissions.length !== 1 ? 's' : ''} for <strong>${currentUserPermissionsUsername}</strong>?`,
        'info',
        'Save',
        'Cancel'
    );
    
    if (!confirmed) {
        return;
    }
    
    const saveBtn = document.getElementById('saveUserPermissionsBtn');
    
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const response = await fetch('../api/permissions/save_user_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUserPermissionsUserId,
                permissions: grantedPermissions
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            const grantedCount = grantedPermissions.length;
            showUserPermissionsStatus(
                `Permissions saved successfully! ${grantedCount} permission${grantedCount !== 1 ? 's' : ''} granted.`,
                'success'
            );
            
            // Show notification
            if (window.modernForms && typeof window.modernForms.showNotification === 'function') {
                window.modernForms.showNotification(
                    `Permissions saved successfully for ${currentUserPermissionsUsername}!`,
                    'success',
                    3000
                );
            }
            
            // Refresh history if open
            if (window.unifiedHistory) {
                await window.unifiedHistory.refreshIfOpen();
            }
            
            // Refresh table
            if (window.modernForms && typeof window.modernForms.refreshData === 'function') {
                setTimeout(() => {
                    window.modernForms.refreshData();
                }, 500);
            }
            
            // Close modal after delay
            setTimeout(() => {
                closeUserPermissionsModal();
            }, 2000);
        } else {
            showUserPermissionsStatus('Error: ' + (data.message || 'Failed to save permissions'), 'error');
        }
    } catch (error) {
        showUserPermissionsStatus('Error: ' + error.message, 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Permissions';
        }
    }
};

// Modern confirmation dialog for user permissions
function showUserPermissionsConfirm(title, message, type = 'warning', confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'user-permissions-confirm-overlay';
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 10002; display: flex; align-items: center; justify-content: center;';
        
        const iconMap = {
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            danger: 'fa-times-circle'
        };
        
        const colorMap = {
            warning: '#f59e0b',
            info: '#3b82f6',
            success: '#10b981',
            danger: '#ef4444'
        };
        
        overlay.innerHTML = `
            <div class="user-permissions-confirm-dialog" style="background: #1f2937; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); border: 1px solid #374151; max-width: 420px; width: 90%; transform: scale(0.95); opacity: 0; transition: all 0.2s ease;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid #374151; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: ${colorMap[type] || colorMap.warning}20; display: flex; align-items: center; justify-content: center; color: ${colorMap[type] || colorMap.warning}; font-size: 20px;">
                        <i class="fas ${iconMap[type] || iconMap.warning}"></i>
                    </div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #f9fafb;">${title}</h3>
                </div>
                <div style="padding: 20px 24px;">
                    <p style="margin: 0; font-size: 14px; color: #d1d5db; line-height: 1.6;">${message}</p>
                </div>
                <div style="padding: 16px 24px 20px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #374151;">
                    <button class="user-permissions-confirm-cancel" style="padding: 8px 20px; background: #374151; color: #d1d5db; border: 1px solid #4b5563; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;">${cancelText}</button>
                    <button class="user-permissions-confirm-ok" style="padding: 8px 20px; background: ${colorMap[type] || colorMap.warning}; color: #ffffff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;">${confirmText}</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Animate in
        setTimeout(() => {
            const dialog = overlay.querySelector('.user-permissions-confirm-dialog');
            dialog.style.transform = 'scale(1)';
            dialog.style.opacity = '1';
        }, 10);
        
        const close = (result) => {
            const dialog = overlay.querySelector('.user-permissions-confirm-dialog');
            dialog.style.transform = 'scale(0.95)';
            dialog.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                resolve(result);
            }, 200);
        };
        
        overlay.querySelector('.user-permissions-confirm-cancel').addEventListener('click', () => close(false));
        overlay.querySelector('.user-permissions-confirm-ok').addEventListener('click', () => close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(false);
        });
        
        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                close(false);
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    });
}

// Select all user permissions (across all groups)
window.selectAllUserPermissionsGlobal = async function() {
    const container = document.getElementById('userPermissionsGroupsContainer');
    if (!container) {
        return;
    }
    
    const confirmed = await showUserPermissionsConfirm(
        'Select All Permissions',
        `Are you sure you want to select all permissions for ${currentUserPermissionsUsername || 'this user'}? This will grant access to all available features.`,
        'info',
        'Select All',
        'Cancel'
    );
    
    if (!confirmed) {
        return;
    }
    
    // Select all permission buttons across all groups
    const allButtons = container.querySelectorAll('.permission-btn');
    let selectedCount = 0;
    
    for (const btn of allButtons) {
        if (!btn.classList.contains('permission-granted')) {
            btn.classList.add('permission-granted');
            selectedCount++;
        }
    }
    
    if (selectedCount > 0) {
        showUserPermissionsStatus(`Selected ${selectedCount} permissions. Click "Save Permissions" to apply.`, 'success');
    } else {
        showUserPermissionsStatus('All permissions are already selected.', 'info');
    }
};

// Clear user permissions (use role only)
window.clearUserPermissions = async function() {
    if (!currentUserPermissionsUserId || Number.isNaN(currentUserPermissionsUserId)) {
        showUserPermissionsStatus('Error: Invalid user ID', 'error');
        return;
    }
    
    const confirmed = await showUserPermissionsConfirm(
        'Clear User Permissions',
        `Are you sure you want to clear all custom permissions for <strong>${currentUserPermissionsUsername}</strong>? They will use role permissions only.`,
        'warning',
        'Clear Permissions',
        'Cancel'
    );
    
    if (!confirmed) {
        return;
    }
    
    const container = document.getElementById('userPermissionsGroupsContainer');
    const statusDiv = document.getElementById('userPermissionsStatus');
    
    // Show loading state
    if (statusDiv) {
        statusDiv.className = 'permissions-status permissions-status-info';
        statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing permissions...';
    }
    
    try {
        const response = await fetch('../api/permissions/save_user_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUserPermissionsUserId,
                permissions: []
            })
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        let data;
        
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but got: ${contentType}. Response: ${text.substring(0, 200)}`);
        }
        
        try {
            data = await response.json();
        } catch (jsonError) {
            const text = await response.text();
            throw new Error(`Failed to parse JSON response (Status: ${response.status}): ${text.substring(0, 200)}`);
        }
        
        if (!response.ok || !data.success) {
            throw new Error(data.message || `HTTP error! status: ${response.status}`);
        }
        
        // Clear all UI checkboxes
        if (container) {
            const allButtons = container.querySelectorAll('.permission-btn.permission-granted');
            allButtons.forEach(btn => {
                btn.classList.remove('permission-granted');
            });
        }
        
        showUserPermissionsStatus('Permissions cleared. User will now use role permissions only.', 'success');
        
        // Reload permissions to show updated state
        await loadUserPermissions(currentUserPermissionsUserId);
        
        // Refresh history if open
        if (window.unifiedHistory) {
            await window.unifiedHistory.refreshIfOpen();
        }
        
        // Refresh the users table if modernForms is available
        if (window.modernForms && typeof window.modernForms.refreshData === 'function') {
            window.modernForms.refreshData();
        }
        
    } catch (error) {
        console.error('Error clearing user permissions:', error);
        showUserPermissionsStatus('Error: ' + error.message, 'error');
    }
};

// Show user permissions status
function showUserPermissionsStatus(message, type) {
    const statusDiv = document.getElementById('userPermissionsStatus');
    if (!statusDiv) return;
    
    statusDiv.className = `permissions-status-visible ${type}-message`;
    statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
    
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.className = 'permissions-status-hidden';
        }, 3000);
    }
}

// Handle click outside user permissions modal
document.addEventListener('click', function(e) {
    const userPermModal = document.getElementById('userPermissionsManagementModal');
    if (userPermModal && e.target === userPermModal) {
        closeUserPermissionsModal();
    }
});

// Handle ESC key for user permissions modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const userPermModal = document.getElementById('userPermissionsManagementModal');
        if (userPermModal && userPermModal.classList.contains('show')) {
            closeUserPermissionsModal();
        }
    }
});

// ============================================
// PERMISSIONS MANAGEMENT (ROLES)
// ============================================

let currentPermissionsRoleId = null;
let permissionsData = null;

// Handle permissions management button click
document.addEventListener('click', function(e) {
});

function openPermissionsManagementModal() {
    const modal = document.getElementById('permissionsManagementModal');
    const roleSelect = document.getElementById('permissionsRoleSelect');
    const container = document.getElementById('permissionsGroupsContainer');
    const modalTitle = document.getElementById('permissionsModalTitle');
    const statusDiv = document.getElementById('permissionsStatus');
    
    if (!modal || !roleSelect || !container) {
        return;
    }
    
    // Show modal
    modal.classList.remove('modal-hidden');
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    // Reset modal title
    if (modalTitle) {
        modalTitle.textContent = 'Manage Permissions';
    }
    
    // Reset state
    currentPermissionsRoleId = null;
    permissionsData = null;
    container.innerHTML = '<div class="permissions-loading">Please select a role to manage permissions</div>';
    
    // Clear status
    if (statusDiv) {
        statusDiv.className = 'permissions-status-hidden';
        statusDiv.textContent = '';
    }
    
    // Load roles
    loadRolesForPermissions();
}

// Make closePermissionsManagementModal globally accessible
window.closePermissionsManagementModal = function() {
    const modal = document.getElementById('permissionsManagementModal');
    const statusDiv = document.getElementById('permissionsStatus');
    
    if (modal) {
        modal.classList.remove('show');
        modal.classList.add('modal-hidden');
        document.body.classList.remove('modal-open');
    }
    
    if (statusDiv) {
        statusDiv.className = 'permissions-status-hidden';
        statusDiv.textContent = '';
    }
    
    currentPermissionsRoleId = null;
    permissionsData = null;
};

// Keep local reference
function closePermissionsManagementModal() {
    window.closePermissionsManagementModal();
}

async function loadRolesForPermissions() {
    const roleSelect = document.getElementById('permissionsRoleSelect');
    if (!roleSelect) return;
    
    try {
        const response = await fetch('../api/admin/get_roles.php');
        const data = await response.json();
        
        if (data.success && data.roles) {
            if (data.roles.length === 0) {
                roleSelect.innerHTML = '<option value="">No roles found. Please create a role first.</option>';
                roleSelect.disabled = true;
                return;
            }
            
            roleSelect.innerHTML = '<option value="">Select a role...</option>';
            roleSelect.disabled = false;
            for (const role of data.roles) {
                const option = document.createElement('option');
                option.value = role.role_id;
                option.textContent = role.role_name + (role.description ? ' - ' + role.description : '');
                roleSelect.appendChild(option);
            }
            
            // Use event delegation or replace the element to avoid duplicate listeners
            roleSelect.onchange = function() {
                const roleId = Number.parseInt(this.value, 10);
                const modalTitle = document.getElementById('permissionsModalTitle');
                
                if (roleId) {
                    // Update modal title with role name
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && modalTitle) {
                        modalTitle.textContent = `Manage Permissions - ${selectedOption.textContent.split(' - ')[0]}`;
                    }
                    loadPermissionsForRole(roleId);
                } else {
                    if (modalTitle) {
                        modalTitle.textContent = 'Manage Permissions';
                    }
                    const container = document.getElementById('permissionsGroupsContainer');
                    if (container) {
                        container.innerHTML = '<div class="permissions-loading">Please select a role to manage permissions</div>';
                    }
                    currentPermissionsRoleId = null;
                }
            };
        } else {
            roleSelect.innerHTML = '<option value="">Error loading roles</option>';
            roleSelect.disabled = true;
            const container = document.getElementById('permissionsGroupsContainer');
            if (container) {
                container.innerHTML = '<div class="permissions-error">Unable to load roles. Please refresh the page.</div>';
            }
        }
    } catch (error) {
        roleSelect.innerHTML = '<option value="">Error loading roles</option>';
        roleSelect.disabled = true;
        const container = document.getElementById('permissionsGroupsContainer');
        if (container) {
            container.innerHTML = '<div class="permissions-error">Failed to load roles: ' + error.message + '</div>';
        }
    }
}

async function loadPermissionsForRole(roleId) {
    const container = document.getElementById('permissionsGroupsContainer');
    const statusDiv = document.getElementById('permissionsStatus');
    
    if (!container) return;
    
    if (!roleId || Number.isNaN(roleId)) {
        container.innerHTML = '<div class="permissions-error">Invalid role ID</div>';
        return;
    }
    
    currentPermissionsRoleId = roleId;
    container.innerHTML = '<div class="permissions-loading">Loading permissions...</div>';
    
    // Clear any previous status messages
    if (statusDiv) {
        statusDiv.className = 'permissions-status-hidden';
        statusDiv.textContent = '';
    }
    
    try {
        const response = await fetch(`../api/settings/get_permissions_groups.php?role_id=${roleId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.groups) {
            permissionsData = data;
            renderPermissionsGroups(data.groups);
        } else {
            container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + (data.message || 'Unknown error') + '</div>';
        }
    } catch (error) {
        container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + error.message + '</div>';
    }
}

function renderPermissionsGroups(groups) {
    const container = document.getElementById('permissionsGroupsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<div class="permissions-error">No permission groups found</div>';
        return;
    }
    
    // Calculate total permissions for summary
    let totalPermissions = 0;
    for (const group of groups) {
        if (group.permissions) {
            totalPermissions += group.permissions.length;
        }
    }
    
    // Show summary
    if (totalPermissions > 0) {
        const summary = document.createElement('div');
        summary.className = 'permissions-summary';
        summary.style.cssText = 'margin-bottom: 1rem; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 6px; color: var(--text); font-size: 0.9rem;';
        summary.innerHTML = `<i class="fas fa-info-circle"></i> <strong>Total:</strong> ${totalPermissions} permissions across ${groups.length} groups`;
        container.appendChild(summary);
    }
    
    for (const group of groups) {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'permissions-group';
        
        const header = document.createElement('div');
        header.className = 'permissions-group-header';
        
        const title = document.createElement('h3');
        title.className = 'permissions-group-title';
        title.innerHTML = `${group.name} <span class="permissions-group-count">${group.count}</span>`;
        
        const actions = document.createElement('div');
        actions.className = 'permissions-group-actions';
        
        const selectAllBtn = document.createElement('button');
        selectAllBtn.className = 'permissions-btn-select-all';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
        selectAllBtn.onclick = () => selectAllPermissions(group.id);
        
        const cancelAllBtn = document.createElement('button');
        cancelAllBtn.className = 'permissions-btn-cancel-all';
        cancelAllBtn.innerHTML = '<i class="fas fa-times"></i> Cancel All';
        cancelAllBtn.onclick = () => cancelAllPermissions(group.id);
        
        actions.appendChild(selectAllBtn);
        actions.appendChild(cancelAllBtn);
        
        header.appendChild(title);
        header.appendChild(actions);
        
        const permissionsGrid = document.createElement('div');
        permissionsGrid.className = 'permissions-grid';
        permissionsGrid.id = `permissions-group-${group.id}`;
        
        if (group.permissions && group.permissions.length > 0) {
            for (const permission of group.permissions) {
                const permBtn = document.createElement('button');
                permBtn.className = 'permission-btn';
                permBtn.dataset.groupId = group.id;
                permBtn.dataset.permissionId = permission.id;
                permBtn.textContent = permission.name;
                permBtn.onclick = () => togglePermission(permission.id, group.id);
                
                if (permission.granted) {
                    permBtn.classList.add('permission-granted');
                }
                
                permissionsGrid.appendChild(permBtn);
            }
        }
        
        groupDiv.appendChild(header);
        groupDiv.appendChild(permissionsGrid);
        container.appendChild(groupDiv);
    }
}

// Make functions globally accessible for onclick handlers
window.togglePermission = function(permissionId, groupId) {
    const btn = document.querySelector(`[data-permission-id="${permissionId}"]`);
    if (!btn) {
        return;
    }
    
    btn.classList.toggle('permission-granted');
};

window.selectAllPermissions = function(groupId) {
    const groupDiv = document.getElementById(`permissions-group-${groupId}`);
    if (!groupDiv) {
        return;
    }
    
        const buttons = groupDiv.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.add('permission-granted');
        }
};

window.cancelAllPermissions = function(groupId) {
    const groupDiv = document.getElementById(`permissions-group-${groupId}`);
    if (!groupDiv) {
        return;
    }
    
        const buttons = groupDiv.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.remove('permission-granted');
        }
};

// Also keep local references for internal use
function togglePermission(permissionId, groupId) {
    window.togglePermission(permissionId, groupId);
}

function selectAllPermissions(groupId) {
    window.selectAllPermissions(groupId);
}

function cancelAllPermissions(groupId) {
    window.cancelAllPermissions(groupId);
}

// Make savePermissions globally accessible
window.savePermissions = async function() {
    if (!currentPermissionsRoleId || Number.isNaN(currentPermissionsRoleId)) {
        showPermissionsStatus('Please select a role first', 'error');
        return;
    }
    
    // Get role name for confirmation
    const roleSelect = document.getElementById('permissionsRoleSelect');
    const roleName = roleSelect?.options[roleSelect.selectedIndex]?.textContent?.split(' - ')[0] || 'this role';
    
    // Show confirmation dialog
    if (window.modernForms && typeof window.modernForms.confirmDialog === 'function') {
        const confirmed = await window.modernForms.confirmDialog(
            `Are you sure you want to save permissions for "${roleName}"? This will update all permissions for this role.`
        );
        if (!confirmed) {
            return;
        }
    }
    
    const saveBtn = document.getElementById('savePermissionsBtn');
    
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    // Collect all granted permissions
    const grantedPermissions = [];
    const permissionButtons = document.querySelectorAll('.permission-btn.permission-granted');
    for (const btn of permissionButtons) {
        const permId = btn.dataset.permissionId;
        if (permId) {
            grantedPermissions.push(permId);
        }
    }
    
    try {
        const response = await fetch('../api/settings/save_role_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                role_id: currentPermissionsRoleId,
                permissions: grantedPermissions
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            const grantedCount = grantedPermissions.length;
            showPermissionsStatus(
                `Permissions saved successfully! ${grantedCount} permission${grantedCount !== 1 ? 's' : ''} granted.`, 
                'success'
            );
            
            // Show notification if available
            if (window.modernForms && typeof window.modernForms.showNotification === 'function') {
                window.modernForms.showNotification(
                    `Permissions saved successfully for ${roleName}!`,
                    'success',
                    3000
                );
            }
            
            // Refresh history if open
            if (window.unifiedHistory) {
                await window.unifiedHistory.refreshIfOpen();
            }
            
            // Reload permissions to reflect saved state
            setTimeout(() => {
                loadPermissionsForRole(currentPermissionsRoleId);
            }, 500);
            setTimeout(() => {
                closePermissionsManagementModal();
            }, 2000);
        } else {
            showPermissionsStatus('Error: ' + (data.message || 'Failed to save permissions'), 'error');
        }
    } catch (error) {
        showPermissionsStatus('Error: ' + error.message, 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Permissions';
        }
    }
};

// Keep local reference
async function savePermissions() {
    return window.savePermissions();
}

function showPermissionsStatus(message, type) {
    const statusDiv = document.getElementById('permissionsStatus');
    if (!statusDiv) return;
    
    statusDiv.className = `permissions-status-visible ${type}-message`;
    statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
}