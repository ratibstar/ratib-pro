/**
 * EN: Implements frontend interaction behavior in `js/system-settings.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/system-settings.js`.
 */
// System Settings - Button handlers that integrate with ModernForms
function getSystemSettingsApiBase() {
    const base = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) ? window.APP_CONFIG.baseUrl + '/api' : '') || ((window.BASE_PATH || '') + '/api');
    return (typeof base === 'string' && base) ? base.replace(/\/$/, '') : '/api';
}
// True when we're in the control panel (so save/load must use control DB)
function isControlPanelContext() {
    const el = document.getElementById('app-config');
    if (el && el.getAttribute('data-control') === '1') return true;
    if (typeof window.location !== 'undefined') {
        if (window.location.search && window.location.search.includes('control=1')) return true;
        if (window.location.pathname && window.location.pathname.indexOf('/control/') !== -1) return true;
    }
    return false;
}
/**
 * Load/save user permission *lists* for the control-panel app only (embedded system settings there).
 * Program pages often keep ?control=1 for SSO — permission lists still come from /api/settings/get_permissions_groups.php unless path is /control/.
 */
function useControlUserPermissionsApi() {
    if (typeof window.location !== 'undefined' && window.location.pathname) {
        const p = window.location.pathname;
        if (p.indexOf('/control/') !== -1 || p.indexOf('\\control\\') !== -1) {
            return true;
        }
    }
    const el = document.getElementById('app-config');
    if (el && el.getAttribute('data-control-user-permissions-api') === '1') {
        return true;
    }
    return false;
}
// Base URL for control panel API (no trailing slash). Uses same origin + path so subdir installs work.
function getControlApiBase() {
    var base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '';
    if (typeof base !== 'string') base = '';
    base = base.replace(/\/$/, '');
    if (typeof window.location !== 'undefined' && window.location.origin) {
        var origin = window.location.origin;
        if (!base) return origin;
        if (base.indexOf(origin) === 0) return base;
        return origin + base;
    }
    return base || '';
}
// Full URL for control permissions endpoints (load/save/check). Prefers server-set data-control-api-path.
function getControlPermissionsUrl(path, query) {
    var el = document.getElementById('app-config');
    var controlPath = (el && el.getAttribute('data-control-api-path')) || (window.APP_CONFIG && window.APP_CONFIG.controlApiPath) || '';
    var pathPart = (path || '').toString().replace(/^\//, '');
    if (controlPath) {
        var base = controlPath.replace(/\/$/, '');
        // If controlPath is already a full URL (http/https), use it directly; otherwise prepend origin
        var isFullUrl = /^https?:\/\//i.test(base);
        var url = isFullUrl ? base : ((window.location.origin || '') + (base.indexOf('/') === 0 ? '' : '/') + base);
        url += pathPart ? (url.endsWith('/') ? '' : '/') + pathPart : '';
        if (query) url += (url.indexOf('?') !== -1 ? '&' : '?') + query;
        return url;
    }
    var base = getControlApiBase();
    var url = base + '/api/control/' + (path || '').replace(/^\//, '');
    if (query) url += (url.indexOf('?') !== -1 ? '&' : '?') + query;
    return url;
}
function appendControlParam(url) {
    if (!isControlPanelContext()) return url;
    if (url.includes('control=1')) return url;
    return url + (url.includes('?') ? '&' : '?') + 'control=1';
}
/** Pass agency_id from the current page URL so permission APIs know we are in program workspace (not master control). */
function appendPageAgencyIdParam(url) {
    try {
        if (typeof window === 'undefined' || !window.location || !window.location.search) {
            return url;
        }
        const aid = new URLSearchParams(window.location.search).get('agency_id');
        if (!aid || String(aid).trim() === '') {
            return url;
        }
        if (url.indexOf('agency_id=') !== -1) {
            return url;
        }
        const sep = url.indexOf('?') !== -1 ? '&' : '?';
        return url + sep + 'agency_id=' + encodeURIComponent(aid);
    } catch (e) {
        return url;
    }
}
// Handle close fingerprint modal and other actions
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

// Handle Escape key for user permissions modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('userPermissionsManagementModal');
        if (modal && !modal.classList.contains('modal-hidden')) {
            e.preventDefault();
            if (typeof closeUserPermissionsModal === 'function') {
                closeUserPermissionsModal();
            }
        }
    }
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
    
    // Handle Company Info button - Open company information form
    const companyInfoBtn = e.target.closest('[data-action="open-company-info"]');
    if (companyInfoBtn && window.modernForms) {
        e.preventDefault();
        e.stopPropagation();
        window.modernForms.openCompanyInfoForm();
    }

    // Reset App - clear all data (Dashboard, Agent, SubAgent, Workers, Cases, Accounting, HR, Reports, Contact, Notifications, Help, System Settings history)
    const resetBtn = e.target.closest('[data-action="reset-all-data"]');
    if (resetBtn) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('Reset the app? This will clear all data for: Dashboard, Agents, SubAgents, Workers, Cases, Accounting, HR, Reports, Contact, Notifications, Help & Learning Center, and System Settings history.\n\nUsers and permissions are kept. This cannot be undone.')) return;
        const btn = resetBtn;
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
        var baseUrl = (document.documentElement.getAttribute('data-base-url') || '').replace(/\/$/, '');
        var url = baseUrl + '/api/admin/clear_all_data.php?action=clear_all_data&confirm=1';
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                return r.text().then(function(t) {
                    if (!r.ok) return { _notOk: true, status: r.status, body: t };
                    try { return JSON.parse(t); } catch (e) { return { _notJson: true, body: t }; }
                });
            })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = origText;
                if (data._notOk) {
                    var msg = data.body && data.body.trim().indexOf('<') !== 0 ? data.body.trim() : ('HTTP ' + data.status);
                    alert('Reset failed: ' + msg + '\n\nIf you see "File not found", ensure api/admin/clear_all_data.php is uploaded to the server.');
                    return;
                }
                if (data._notJson) {
                    var preview = (data.body || '').trim().substring(0, 80);
                    alert('Reset failed: Server did not return JSON. Response: ' + preview + '\n\nEnsure api/admin/clear_all_data.php exists at: ' + url);
                    return;
                }
                if (data.success) {
                    alert('App reset complete. ' + (data.total_affected !== undefined ? data.total_affected + ' records cleared.' : ''));
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = origText;
                alert('Request failed: ' + (err.message || 'Network error') + '\n\nCheck that api/admin/clear_all_data.php exists on the server.');
            });
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
                        // Check if we're in profile modal context (dashboard profile modal)
                        const profileModal = document.getElementById('mainModal');
                        const modalBody = profileModal?.querySelector('#modalBody') || profileModal?.querySelector('.modal-body');
                        const isProfileModal = profileModal && modalBody && modalBody.classList.contains('profile-modal-content');
                        
                        if (isProfileModal && window.modernForms.currentTable === 'users') {
                            // Re-filter to show only current user in profile modal
                            const profileCard = document.querySelector('.system-card[data-action="open-profile-modal"]');
                            const currentUserId = profileCard ? parseInt(profileCard.getAttribute('data-user-id')) : null;
                            
                            if (currentUserId && window.modernForms.data && Array.isArray(window.modernForms.data)) {
                                // Filter data to show only current user
                                window.modernForms.data = window.modernForms.data.filter(user => user.user_id == currentUserId);
                                
                                // Check for duplicates and remove them
                                const seen = new Set();
                                window.modernForms.data = window.modernForms.data.filter(user => {
                                    if (seen.has(user.user_id)) {
                                        return false;
                                    }
                                    seen.add(user.user_id);
                                    return true;
                                });
                                
                                // Recalculate stats for filtered data
                                const filteredStats = {
                                    total: window.modernForms.data.length,
                                    active: window.modernForms.data.filter(user => {
                                        const status = user.status || user.is_active;
                                        return status === 'active' || status === 1 || status === '1';
                                    }).length,
                                    inactive: window.modernForms.data.filter(user => {
                                        const status = user.status || user.is_active;
                                        return status === 'inactive' || status === 0 || status === '0';
                                    }).length,
                                    today: 0,
                                    thisWeek: 0,
                                    thisMonth: 0
                                };
                                
                                window.modernForms.currentTableStats = filteredStats;
                                window.modernForms.renderTableWithStats(filteredStats);
                            } else {
                                // Fallback to normal refresh if filtering fails
                        if (window.modernForms && typeof window.modernForms.loadTableStats === 'function') {
                            window.modernForms.loadTableStats('users').then(stats => {
                                if (window.modernForms && typeof window.modernForms.renderTableWithStats === 'function') {
                                    window.modernForms.renderTableWithStats(stats);
                                }
                            });
                                }
                            }
                        } else {
                            // Normal refresh for system settings pages
                            if (window.modernForms && typeof window.modernForms.loadTableStats === 'function') {
                                window.modernForms.loadTableStats('users').then(stats => {
                                    if (window.modernForms && typeof window.modernForms.renderTableWithStats === 'function') {
                                        window.modernForms.renderTableWithStats(stats);
                                    }
                                });
                            }
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
let originalUserPermissions = null; // Store original permissions to detect changes
let isLoadingPermissions = false; // Track loading state
let currentAbortController = null; // For canceling fetch requests

// Open user permissions modal
window.openUserPermissionsModal = function(userId, username) {
    // Validate input
    if (!userId || Number.isNaN(parseInt(userId)) || parseInt(userId) <= 0) {
        console.error('Invalid user ID:', userId);
        return;
    }
    
    const modal = document.getElementById('userPermissionsManagementModal');
    const container = document.getElementById('userPermissionsGroupsContainer');
    const userNameSpan = document.getElementById('userPermissionsUserName');
    const userIdSpan = document.getElementById('userPermissionsUserId');
    const statusDiv = document.getElementById('userPermissionsStatus');
    
    if (!modal || !container) {
        console.error('User permissions modal elements not found');
        return;
    }
    
    // Prevent opening if already open with same user
    if (!modal.classList.contains('modal-hidden') && currentUserPermissionsUserId === parseInt(userId)) {
        return; // Already open for this user
    }
    
    // Cancel any ongoing requests
    if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
    }
    
    currentUserPermissionsUserId = parseInt(userId);
    currentUserPermissionsUsername = username || 'Unknown';
    
    // Update modal title and user info
    if (userNameSpan) userNameSpan.textContent = escapeHtml(username || 'Unknown User');
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
    
    // Focus first focusable element (close button)
    setTimeout(() => {
        const closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }, 100);
    
    // Load permissions
    loadUserPermissions(userId);
};

// Build normalized permission list from DOM (same logic as save) for consistent comparison
function getNormalizedUserPermissionsFromDom() {
    const container = document.getElementById('userPermissionsGroupsContainer');
    if (!container) return [];
    const granted = [];
    container.querySelectorAll('.permission-btn:not(.permission-inactive)').forEach(btn => {
        const id = btn.dataset.permissionId;
        if (id) granted.push(id);
    });
    const groups = userPermissionsData && userPermissionsData.groups ? userPermissionsData.groups : [];
    const grantedSet = new Set(granted);
    const toRemove = new Set();
    for (const group of groups) {
        if (!group.permissions || group.permissions.length < 2) continue;
        const first = group.permissions[0];
        const parentId = first.id;
        const isFullAccessParent = (first.name && first.name.indexOf('Full Access') !== -1) || first.id === group.id;
        if (!isFullAccessParent || !grantedSet.has(parentId)) continue;
        const allIdsInGroup = group.permissions.map(p => p.id);
        const allGranted = allIdsInGroup.every(id => grantedSet.has(id));
        if (!allGranted) toRemove.add(parentId);
    }
    return granted.filter(id => !toRemove.has(id));
}

// Close user permissions modal
window.closeUserPermissionsModal = async function() {
    // Cancel any ongoing requests
    if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
    }
    
    // Check if there are unsaved changes: compare normalized current state with original (same normalization as save)
    if (document.getElementById('userPermissionsGroupsContainer') && !isLoadingPermissions) {
        const currentPermissions = getNormalizedUserPermissionsFromDom();
        const originalPerms = originalUserPermissions || [];
        const hasChanges = currentPermissions.length !== originalPerms.length ||
                          !currentPermissions.every(perm => originalPerms.includes(perm)) ||
                          !originalPerms.every(perm => currentPermissions.includes(perm));
        
        if (hasChanges) {
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
    originalUserPermissions = null;
    isLoadingPermissions = false;
};

// Load user permissions
async function loadUserPermissions(userId) {
    const container = document.getElementById('userPermissionsGroupsContainer');
    const statusDiv = document.getElementById('userPermissionsStatus');
    
    // Validate user ID
    if (!userId || Number.isNaN(parseInt(userId)) || parseInt(userId) <= 0) {
        if (container) {
            container.innerHTML = '<div class="permissions-error">Invalid user ID</div>';
        }
        return;
    }
    
    // Cancel previous request if any
    if (currentAbortController) {
        currentAbortController.abort();
    }
    
    // Create new abort controller
    currentAbortController = new AbortController();
    isLoadingPermissions = true;
    
    try {
        // Control panel: single endpoint (user_permissions.php) does both load and save with same DB connection
        const qs = 'user_id=' + userId + '&_=' + Date.now();
        const url = appendPageAgencyIdParam(useControlUserPermissionsApi()
            ? getControlPermissionsUrl('user_permissions.php', qs)
            : appendControlParam(getSystemSettingsApiBase() + '/settings/get_permissions_groups.php?' + qs));
        const response = await fetch(url, {
            signal: currentAbortController.signal,
            credentials: 'include',
            cache: 'no-store'
        });
        
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
            originalUserPermissions = Array.isArray(data.user_permissions) ? [...data.user_permissions] : [];
            renderUserPermissionsGroups(data.groups, data.user_permissions || []);
        } else {
            if (container) {
                container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + escapeHtml(data.message || 'Unknown error') + '</div>';
            }
        }
    } catch (error) {
        // Don't show error if request was aborted (user closed modal)
        if (error.name === 'AbortError') {
            return;
        }
        
        if (container) {
            container.innerHTML = '<div class="permissions-error">Error loading permissions: ' + escapeHtml(error.message) + '</div>';
        }
        console.error('Error loading user permissions:', error);
    } finally {
        isLoadingPermissions = false;
        currentAbortController = null;
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
            const grantedSet = Array.isArray(userPermissions) && userPermissions.length > 0
                ? new Set(userPermissions.map(String))
                : null;
            for (const permission of group.permissions) {
                const permBtn = document.createElement('button');
                permBtn.className = 'permission-btn';
                permBtn.dataset.groupId = group.id;
                permBtn.dataset.permissionId = permission.id;
                permBtn.textContent = permission.name;
                // Green = active (granted), Red = inactive (not granted)
                const isGranted = permission.granted === true || (grantedSet && grantedSet.has(String(permission.id)));
                if (!isGranted) {
                    permBtn.classList.add('permission-inactive');
                    permBtn.setAttribute('aria-pressed', 'false');
                } else {
                    permBtn.setAttribute('aria-pressed', 'true');
                }
                permBtn.title = permBtn.classList.contains('permission-inactive') ? 'Inactive (not granted) — click to grant' : 'Active (granted) — click to revoke';
                permBtn.onclick = () => toggleUserPermission(permission.id, group.id);
                permissionsGrid.appendChild(permBtn);
            }
        }
        
        groupDiv.appendChild(header);
        groupDiv.appendChild(permissionsGrid);
        container.appendChild(groupDiv);
        updateUserPermissionsSelectAllState(group.id);
    }
}

// Update Select All button visual state for a group (checked when all permissions in group are granted = none inactive)
function updateUserPermissionsSelectAllState(groupId) {
    const grid = document.getElementById(`user-permissions-group-${groupId}`);
    if (!grid) return;
    const groupDiv = grid.closest('.permissions-group');
    const selectAllBtn = groupDiv ? groupDiv.querySelector('.permissions-btn-select-all') : null;
    if (!selectAllBtn) return;
    const buttons = grid.querySelectorAll('.permission-btn');
    const inactive = grid.querySelectorAll('.permission-btn.permission-inactive');
    if (buttons.length > 0 && inactive.length === 0) {
        selectAllBtn.classList.add('permission-granted');
    } else {
        selectAllBtn.classList.remove('permission-granted');
    }
}

// Toggle user permission: green = active (granted), red = inactive (not granted).
window.toggleUserPermission = function(permissionId, groupId) {
    const grid = document.getElementById(`user-permissions-group-${groupId}`);
    if (!grid) return;
    const permBtn = grid.querySelector(`.permission-btn[data-permission-id="${permissionId}"]`);
    if (!permBtn) return;
    const group = userPermissionsData && userPermissionsData.groups && userPermissionsData.groups.find(g => g.id === groupId);
    const first = group && group.permissions && group.permissions.length > 0 ? group.permissions[0] : null;
    const isFullAccessParent = first && ((first.name && first.name.indexOf('Full Access') !== -1) || first.id === group.id);
    const parentId = isFullAccessParent ? first.id : null;
    const wasActive = !permBtn.classList.contains('permission-inactive');
    permBtn.classList.toggle('permission-inactive');
    permBtn.setAttribute('aria-pressed', permBtn.classList.contains('permission-inactive') ? 'false' : 'true');
    permBtn.title = permBtn.classList.contains('permission-inactive') ? 'Inactive (not granted) — click to grant' : 'Active (granted) — click to revoke';
    if (wasActive && parentId && parentId !== permissionId) {
        const parentBtn = grid.querySelector(`.permission-btn[data-permission-id="${parentId}"]`);
        if (parentBtn && !parentBtn.classList.contains('permission-inactive')) {
            parentBtn.classList.add('permission-inactive');
            parentBtn.setAttribute('aria-pressed', 'false');
            parentBtn.title = 'Inactive (not granted) — click to grant';
        }
    }
    updateUserPermissionsSelectAllState(groupId);
};

// Select all user permissions in group (all become green = active)
window.selectAllUserPermissions = function(groupId) {
    const grid = document.getElementById(`user-permissions-group-${groupId}`);
    if (grid) {
        const buttons = grid.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.remove('permission-inactive');
            btn.setAttribute('aria-pressed', 'true');
            btn.title = 'Active (granted) — click to revoke';
        }
        const groupDiv = grid.closest('.permissions-group');
        const selectAllBtn = groupDiv ? groupDiv.querySelector('.permissions-btn-select-all') : null;
        if (selectAllBtn) selectAllBtn.classList.add('permission-granted');
    }
};

// Cancel all user permissions in group (all become red = inactive)
window.cancelAllUserPermissions = function(groupId) {
    const grid = document.getElementById(`user-permissions-group-${groupId}`);
    if (grid) {
        const buttons = grid.querySelectorAll('.permission-btn');
        for (const btn of buttons) {
            btn.classList.add('permission-inactive');
            btn.setAttribute('aria-pressed', 'false');
            btn.title = 'Inactive (not granted) — click to grant';
        }
        const groupDiv = grid.closest('.permissions-group');
        const selectAllBtn = groupDiv ? groupDiv.querySelector('.permissions-btn-select-all') : null;
        if (selectAllBtn) selectAllBtn.classList.remove('permission-granted');
    }
};

// Save user permissions
window.saveUserPermissions = async function() {
    // Validate user ID
    if (!currentUserPermissionsUserId || Number.isNaN(currentUserPermissionsUserId) || currentUserPermissionsUserId <= 0) {
        showUserPermissionsStatus('Invalid user selected', 'error');
        return;
    }
    
    // Prevent multiple simultaneous saves
    const saveBtn = document.getElementById('saveUserPermissionsBtn');
    if (saveBtn && saveBtn.disabled) {
        return; // Already saving
    }
    
    // Collect all granted permissions from DOM (green = no permission-inactive)
    const grantedPermissions = [];
    const permissionButtons = document.querySelectorAll('#userPermissionsGroupsContainer .permission-btn:not(.permission-inactive)');
    for (const btn of permissionButtons) {
        const permId = btn.dataset.permissionId;
        if (permId) {
            grantedPermissions.push(permId);
        }
    }
    
    // Normalize: only remove a "Full Access" parent when it's checked but not all children are checked.
    // Do NOT treat the first permission in every group as parent (e.g. "View Dashboard" in Control Core is a real permission).
    const groups = userPermissionsData && userPermissionsData.groups ? userPermissionsData.groups : [];
    const grantedSet = new Set(grantedPermissions);
    const toRemove = new Set();
    for (const group of groups) {
        if (!group.permissions || group.permissions.length < 2) continue;
        const first = group.permissions[0];
        const parentId = first.id;
        const isFullAccessParent = (first.name && first.name.indexOf('Full Access') !== -1) || first.id === group.id;
        if (!isFullAccessParent || !grantedSet.has(parentId)) continue;
        const allIdsInGroup = group.permissions.map(p => p.id);
        const allGranted = allIdsInGroup.every(id => grantedSet.has(id));
        if (!allGranted) toRemove.add(parentId);
    }
    const normalizedGranted = grantedPermissions.filter(id => !toRemove.has(id));
    
    // Show confirmation (escape username to prevent XSS)
    const safeUsername = escapeHtml(currentUserPermissionsUsername || 'this user');
    const confirmed = await showUserPermissionsConfirm(
        'Save Permissions',
        `Are you sure you want to save <strong>${normalizedGranted.length}</strong> permission${normalizedGranted.length !== 1 ? 's' : ''} for <strong>${safeUsername}</strong>?`,
        'info',
        'Save',
        'Cancel'
    );
    
    if (!confirmed) {
        return;
    }
    
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const saveUrl = appendPageAgencyIdParam(useControlUserPermissionsApi()
            ? getControlPermissionsUrl('user_permissions.php')
            : appendControlParam(getSystemSettingsApiBase() + '/permissions/save_user_permissions.php'));
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUserPermissionsUserId,
                permissions: normalizedGranted
            }),
            credentials: 'include'
        });
        
        // Check content type before parsing JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but got: ${contentType}. Response: ${text.substring(0, 200)}`);
        }
        
        // Parse JSON once
        let data;
        try {
            data = await response.json();
        } catch (jsonError) {
            const text = await response.text();
            throw new Error(`Failed to parse JSON response (Status: ${response.status}): ${text.substring(0, 200)}`);
        }
        
        if (!response.ok) {
            var errMsg = data.message || ('HTTP ' + response.status);
            if (response.status === 401) errMsg = 'Session expired or not logged in to control panel. Please refresh and log in again.';
            if (response.status === 403) errMsg = 'You do not have permission to save permissions. ' + (data.message || '');
            throw new Error(errMsg);
        }
        
        if (data.success) {
            // Update original permissions to match current state (so unsaved changes detection works correctly)
            originalUserPermissions = [...normalizedGranted];
            
            const grantedCount = (data.saved_permissions && data.saved_permissions.length) !== undefined ? (data.saved_permissions.length) : normalizedGranted.length;
            let statusMsg = 'Permissions saved successfully! ' + grantedCount + ' permission' + (grantedCount !== 1 ? 's' : '') + ' saved.';
            if (data.rows_updated === 0 && data.db_name) {
                statusMsg += ' (Warning: 0 rows updated in DB: ' + escapeHtml(data.db_name) + ')';
            } else if (data.db_name) {
                statusMsg += ' (DB: ' + escapeHtml(data.db_name) + ')';
            }
            if (data.warning) statusMsg += ' ' + data.warning;
            if (data.source === 'control') statusMsg += ' (master control)';
            showUserPermissionsStatus(statusMsg, data.rows_updated === 0 ? 'error' : 'success');
            
            // Reload permissions from server so modal shows what was actually persisted
            if (currentUserPermissionsUserId) {
                await loadUserPermissions(currentUserPermissionsUserId);
            }
            
            // Show notification
            if (window.modernForms && typeof window.modernForms.showNotification === 'function') {
                window.modernForms.showNotification(
                    `Permissions saved successfully for ${escapeHtml(currentUserPermissionsUsername || 'user')}!`,
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
            
            // Close modal after delay (user can also close manually after seeing reloaded state)
            setTimeout(() => {
                closeUserPermissionsModal();
            }, 3000);
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

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modern confirmation dialog for user permissions
function showUserPermissionsConfirm(title, message, type = 'warning', confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'user-permissions-confirm-overlay';
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
        
        // Escape user input to prevent XSS
        const safeTitle = escapeHtml(title);
        const safeMessage = message; // Message can contain HTML tags like <strong>, so we'll handle it carefully
        const safeConfirmText = escapeHtml(confirmText);
        const safeCancelText = escapeHtml(cancelText);
        
        const confirmType = type || 'warning';
        overlay.innerHTML = `
            <div class="user-permissions-confirm-dialog">
                <div class="confirm-header">
                    <div class="confirm-icon ${confirmType}"><i class="fas ${iconMap[confirmType] || iconMap.warning}"></i></div>
                    <h3 class="confirm-title">${safeTitle}</h3>
                </div>
                <div class="confirm-body">
                    <p>${safeMessage}</p>
                </div>
                <div class="confirm-footer">
                    <button class="user-permissions-confirm-cancel">${safeCancelText}</button>
                    <button class="user-permissions-confirm-ok ${confirmType}">${safeConfirmText}</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Animate in
        setTimeout(() => {
            const dialog = overlay.querySelector('.user-permissions-confirm-dialog');
            if (dialog) {
                dialog.classList.add('visible');
            }
        }, 10);
        
        let isClosing = false;
        const close = (result) => {
            if (isClosing) return;
            isClosing = true;
            
            const dialog = overlay.querySelector('.user-permissions-confirm-dialog');
            if (dialog) {
                dialog.classList.remove('visible');
            }
            setTimeout(() => {
                overlay.remove();
                document.removeEventListener('keydown', handleEscape);
                resolve(result);
            }, 200);
        };
        
        const cancelBtn = overlay.querySelector('.user-permissions-confirm-cancel');
        const okBtn = overlay.querySelector('.user-permissions-confirm-ok');
        
        if (cancelBtn) cancelBtn.addEventListener('click', () => close(false));
        if (okBtn) okBtn.addEventListener('click', () => close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(false);
        });
        
        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close(false);
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
    
    const safeUsername = escapeHtml(currentUserPermissionsUsername || 'this user');
    const confirmed = await showUserPermissionsConfirm(
        'Select All Permissions',
        `Are you sure you want to select all permissions for <strong>${safeUsername}</strong>? This will grant access to all available features.`,
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
        if (btn.classList.contains('permission-inactive')) {
            btn.classList.remove('permission-inactive');
            btn.setAttribute('aria-pressed', 'true');
            btn.title = 'Active (granted) — click to revoke';
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
    if (!currentUserPermissionsUserId || Number.isNaN(currentUserPermissionsUserId) || currentUserPermissionsUserId <= 0) {
        showUserPermissionsStatus('Error: Invalid user ID', 'error');
        return;
    }
    
    const safeUsername = escapeHtml(currentUserPermissionsUsername || 'this user');
    const confirmed = await showUserPermissionsConfirm(
        'Clear User Permissions',
        `Are you sure you want to clear all custom permissions for <strong>${safeUsername}</strong>? They will use role permissions only.`,
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
        const clearUrl = appendPageAgencyIdParam(useControlUserPermissionsApi()
            ? getControlPermissionsUrl('user_permissions.php')
            : appendControlParam(getSystemSettingsApiBase() + '/permissions/save_user_permissions.php'));
        const response = await fetch(clearUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUserPermissionsUserId,
                permissions: []
            }),
            credentials: 'include'
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
        
        // Clear all UI: all become red = inactive until reload paints role defaults
        if (container) {
            const allButtons = container.querySelectorAll('.permission-btn');
            allButtons.forEach(btn => {
                btn.classList.add('permission-inactive');
                btn.setAttribute('aria-pressed', 'false');
                btn.title = 'Inactive (not granted) — click to grant';
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
    
    // Escape message to prevent XSS
    const safeMessage = escapeHtml(message);
    
    statusDiv.className = `permissions-status-visible ${type}-message`;
    statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${safeMessage}`;
    
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.className = 'permissions-status-hidden';
        }, 3000);
    }
}

// Handle click outside user permissions modal
document.addEventListener('click', function(e) {
    const userPermModal = document.getElementById('userPermissionsManagementModal');
    if (userPermModal && !userPermModal.classList.contains('modal-hidden') && e.target === userPermModal) {
        // Only close if clicking directly on the modal overlay, not on its children
        if (typeof closeUserPermissionsModal === 'function') {
            closeUserPermissionsModal();
        }
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
        const apiBase = getSystemSettingsApiBase();
        const url = appendControlParam(apiBase + '/admin/get_roles.php');
        const response = await fetch(url, { credentials: 'include' });
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
        const apiBase = getSystemSettingsApiBase();
        let url = appendControlParam(`${apiBase}/settings/get_permissions_groups.php?role_id=${roleId}`);
        url = appendPageAgencyIdParam(url);
        const response = await fetch(url, { credentials: 'include' });
        
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
                if (!permission.granted) {
                    permBtn.classList.add('permission-inactive');
                    permBtn.setAttribute('aria-pressed', 'false');
                } else {
                    permBtn.setAttribute('aria-pressed', 'true');
                }
                permBtn.title = permBtn.classList.contains('permission-inactive') ? 'Inactive (not granted) — click to grant' : 'Active (granted) — click to revoke';
                permBtn.onclick = () => togglePermission(permission.id, group.id);
                
                permissionsGrid.appendChild(permBtn);
            }
        }
        
        groupDiv.appendChild(header);
        groupDiv.appendChild(permissionsGrid);
        container.appendChild(groupDiv);
    }
}

// Make functions globally accessible for onclick handlers (green = active, red = inactive)
window.togglePermission = function(permissionId, groupId) {
    const btn = document.querySelector(`#permissions-group-${groupId} .permission-btn[data-permission-id="${permissionId}"]`);
    if (!btn) {
        return;
    }
    btn.classList.toggle('permission-inactive');
    btn.setAttribute('aria-pressed', btn.classList.contains('permission-inactive') ? 'false' : 'true');
    btn.title = btn.classList.contains('permission-inactive') ? 'Inactive (not granted) — click to grant' : 'Active (granted) — click to revoke';
};

window.selectAllPermissions = function(groupId) {
    const groupDiv = document.getElementById(`permissions-group-${groupId}`);
    if (!groupDiv) {
        return;
    }
    const buttons = groupDiv.querySelectorAll('.permission-btn');
    for (const btn of buttons) {
        btn.classList.remove('permission-inactive');
        btn.setAttribute('aria-pressed', 'true');
        btn.title = 'Active (granted) — click to revoke';
    }
};

window.cancelAllPermissions = function(groupId) {
    const groupDiv = document.getElementById(`permissions-group-${groupId}`);
    if (!groupDiv) {
        return;
    }
    const buttons = groupDiv.querySelectorAll('.permission-btn');
    for (const btn of buttons) {
        btn.classList.add('permission-inactive');
        btn.setAttribute('aria-pressed', 'false');
        btn.title = 'Inactive (not granted) — click to grant';
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
    
    // Collect all granted permissions (green = not inactive)
    const grantedPermissions = [];
    const permissionButtons = document.querySelectorAll('#permissionsGroupsContainer .permission-btn:not(.permission-inactive)');
    for (const btn of permissionButtons) {
        const permId = btn.dataset.permissionId;
        if (permId) {
            grantedPermissions.push(permId);
        }
    }
    
    try {
        const apiBase = getSystemSettingsApiBase();
        const url = appendControlParam(apiBase + '/settings/save_role_permissions.php');
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                role_id: currentPermissionsRoleId,
                permissions: grantedPermissions
            }),
            credentials: 'include'
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