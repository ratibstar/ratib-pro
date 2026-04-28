/**
 * EN: Implements frontend interaction behavior in `js/profile.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/profile.js`.
 */
/**
 * Profile Page Event Handlers
 * Replaces inline onclick handlers with event listeners
 */

/**
 * Profile Page Event Handlers
 * Replaces inline onclick handlers with event listeners
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get user ID from main-content data attribute
    const mainContent = document.querySelector('.main-content');
    const userId = mainContent?.getAttribute('data-user-id');
    if (userId) {
        window.currentUserId = parseInt(userId);
    }
    
    // Wait for ModernForms to be available
    function initModernForms() {
        if (typeof ModernForms !== 'undefined' && !window.modernForms) {
            window.modernForms = new ModernForms();
            window.modernForms.currentTable = 'users';
        }
    }
    
    // Try to initialize immediately
    initModernForms();
    
    // If not available, wait a bit and try again
    if (!window.modernForms) {
        setTimeout(initModernForms, 100);
    }
    
    // Edit Profile buttons - Use ModernForms (use event delegation for dynamic content)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="edit-profile"]');
        if (!btn) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        // Ensure ModernForms is initialized
        if (!window.modernForms) {
            if (typeof ModernForms !== 'undefined') {
                window.modernForms = new ModernForms();
            } else {
                console.error('ModernForms class not found');
                alert('Form system not loaded. Please refresh the page.');
                return;
            }
        }
        
        // Always ensure currentTable is set to 'users' for profile page
        window.modernForms.currentTable = 'users';
        
        // Get user ID from the button
        const userId = btn.getAttribute('data-user-id') || btn.closest('tr')?.getAttribute('data-user-id');
        const actualUserId = userId || window.currentUserId;
        
        if (!actualUserId) {
            console.error('User ID not found');
            alert('User ID not found. Please refresh the page.');
            return;
        }
        
        // Check if modal elements exist
        const modal = document.getElementById('formPopupModal');
        const title = document.getElementById('formPopupTitle');
        const body = document.getElementById('formPopupBody');
        
        if (!modal || !title || !body) {
            console.error('Modal elements not found', { modal: !!modal, title: !!title, body: !!body });
            alert('Form modal not found. Please refresh the page.');
            return;
        }
        
        // Use ModernForms to open edit form
        try {
            window.modernForms.openFormModal('edit', parseInt(actualUserId)).then(() => {
                // Update title for profile page after modal opens
                const titleEl = document.getElementById('formPopupTitle');
                if (titleEl && window.location.pathname.includes('profile.php')) {
                    titleEl.textContent = 'Edit Profile';
                }
            }).catch(error => {
                console.error('Error opening form:', error);
                alert('Error opening form: ' + error.message);
            });
        } catch (error) {
            console.error('Error opening form:', error);
            alert('Error opening form: ' + error.message);
        }
    });
    
    // View Account History button
    document.querySelectorAll('[data-action="view-account-history"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof viewAccountHistory === 'function') {
                viewAccountHistory();
            }
        });
    });
    
    // View Security Log button
    document.querySelectorAll('[data-action="view-security-log"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof viewSecurityLog === 'function') {
                viewSecurityLog();
            }
        });
    });
    
    // Close Form Modal - handled by ModernForms
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-action="close-form"]');
        if (closeBtn && window.modernForms) {
            e.preventDefault();
            window.modernForms.closeFormModal();
        }
    });
    
    // Close Delete Account Modal
    document.querySelectorAll('[data-action="close-delete-account-modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof closeDeleteAccountModal === 'function') {
                closeDeleteAccountModal();
            }
        });
    });
    
    // Confirm Delete Account
    document.querySelectorAll('[data-action="confirm-delete-account"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof confirmDeleteAccount === 'function') {
                confirmDeleteAccount();
            }
        });
    });
    
    // Password toggle functionality (similar to modern-forms.js)
    document.addEventListener('click', function(e) {
        const passwordToggle = e.target.closest('.password-toggle-container');
        if (passwordToggle) {
            e.preventDefault();
            e.stopPropagation();
            
            const icon = passwordToggle.querySelector('.password-toggle-icon');
            const text = passwordToggle.querySelector('.password-text');
            if (!icon || !text) return;
            
            const isVisible = passwordToggle.getAttribute('data-password-visible') === 'true';
            const encodedPassword = passwordToggle.getAttribute('data-password-value') || '';
            
            if (isVisible) {
                // Hide password
                icon.classList.remove('fa-eye-slash', 'icon-visible');
                icon.classList.add('fa-eye', 'icon-hidden');
                text.classList.remove('password-visible');
                text.textContent = '••••••••';
                passwordToggle.setAttribute('data-password-visible', 'false');
            } else {
                // Show password
                if (encodedPassword) {
                    try {
                        const decodedPassword = decodeURIComponent(escape(atob(encodedPassword)));
                        icon.classList.remove('fa-eye', 'icon-hidden');
                        icon.classList.add('fa-eye-slash', 'icon-visible');
                        text.classList.add('password-visible');
                        text.textContent = decodedPassword;
                        passwordToggle.setAttribute('data-password-visible', 'true');
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
    
    // Fingerprint action buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="fingerprint-action"], [data-action="fingerprint-unregister"]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = parseInt(btn.getAttribute('data-id'));
            const username = btn.getAttribute('data-username') || '';
            const status = btn.getAttribute('data-status') || '';
            const action = btn.getAttribute('data-action');
            
            if (action === 'fingerprint-action') {
                // Register fingerprint
                if (typeof openFingerprintRegistrationModal === 'function') {
                    openFingerprintRegistrationModal(userId, username, false);
                } else if (window.openFingerprintRegistrationModal) {
                    window.openFingerprintRegistrationModal(userId, username, false);
                }
            } else if (action === 'fingerprint-unregister') {
                // Unregister fingerprint
                if (confirm(`Are you sure you want to unregister your fingerprint? You will need to register again to use fingerprint authentication.`)) {
                    if (window.modernForms && typeof window.modernForms.unregisterFingerprintTemplate === 'function') {
                        window.modernForms.unregisterFingerprintTemplate(userId, username).then(() => {
                            location.reload();
                        }).catch(err => {
                            alert('Failed to unregister fingerprint: ' + (err.message || 'Unknown error'));
                        });
                    } else {
                        alert('Fingerprint unregister functionality not available');
                    }
                }
            }
        }
    });
    
    // Permissions button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="manage-user-permissions"]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = parseInt(btn.getAttribute('data-user-id'));
            const username = btn.getAttribute('data-username') || '';
            
            if (window.openUserPermissionsModal) {
                window.openUserPermissionsModal(userId, username);
            } else {
                alert('Permissions management not available');
            }
        }
    });
    
    // Load Recent Activities from System Settings API (same as System Settings uses)
    async function loadProfileActivities() {
        const activitiesList = document.getElementById('profileActivitiesList');
        if (!activitiesList) return;
        
        try {
            // Use ModernForms.loadHistory method (same as System Settings uses)
            if (!window.modernForms) {
                activitiesList.innerHTML = '<p class="no-activities">Loading form system...</p>';
                setTimeout(loadProfileActivities, 500);
                return;
            }
            
            // Load history using the same API as System Settings
            // Filter by current user's activities
            const userId = window.currentUserId || parseInt(document.querySelector('.main-content')?.getAttribute('data-user-id') || '0');
            
            if (!userId) {
                activitiesList.innerHTML = '<p class="no-activities">User ID not found</p>';
                return;
            }
            
            // Use the global history API with user_id filter (same as System Settings uses)
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
            const url = `${baseUrl}/api/core/global-history-api.php?action=get_history&user_id=${userId}&limit=10`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            let data;
            
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid response format');
            }
            
            if (!data.success || !data.data || data.data.length === 0) {
                activitiesList.innerHTML = '<p class="no-activities">No recent activities</p>';
                return;
            }
            
            const userActivities = data.data;
            
            activitiesList.innerHTML = userActivities.map(activity => {
                const action = activity.action || '';
                const tableName = activity.table_name || '';
                const module = activity.module || '';
                const description = activity.description || 
                                  `${action} in ${tableName}${module ? ' (' + module + ')' : ''}`.trim() ||
                                  'Activity';
                const timestamp = activity.created_at || activity.timestamp || '';
                const timeDisplay = timestamp ? new Date(timestamp).toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : 'Date not available';
                
                // Determine icon based on action
                let iconClass = 'fa-history';
                if (action === 'create') iconClass = 'fa-plus-circle';
                else if (action === 'update') iconClass = 'fa-edit';
                else if (action === 'delete') iconClass = 'fa-trash';
                
                return `
                    <div class="activity-item" role="article" tabindex="0">
                        <div class="activity-icon">
                            <i class="fas ${iconClass}"></i>
                        </div>
                        <div class="activity-details">
                            <p class="activity-description">${escapeHtml(description)}</p>
                            <small class="activity-time">${timeDisplay}</small>
                        </div>
                    </div>
                `;
            }).join('');
            
        } catch (error) {
            console.error('Error loading activities:', error);
            activitiesList.innerHTML = '<p class="no-activities">Failed to load activities. Please refresh the page.</p>';
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // View Full History button handler - opens Unified History modal with module selector (same as System Settings)
    // This connects to the same global_history table and uses the same module-history-api.php
    // Shows ALL activities across all modules (not filtered by user)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="view-full-history"]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            
            // Wait for UnifiedHistory class to be available (same as System Settings uses)
            function openHistoryModal() {
                if (window.UnifiedHistory) {
                    // Initialize UnifiedHistory instance if not already created
                    // Reuse the same instance as System Settings (shares the same modal)
                    if (!window.unifiedHistory) {
                        window.unifiedHistory = new window.UnifiedHistory();
                        window.unifiedHistory.initModal();
                    }
                    // Open the modal with "All Modules" selected by default to show entire program history
                    // Pass null for userId to show all users' activities
                    window.unifiedHistory.openModal('all', null);
                } else {
                    // Retry if UnifiedHistory not loaded yet
                    setTimeout(openHistoryModal, 200);
                }
            }
            
            openHistoryModal();
        }
    });
    
    // Load activities on page load (wait for ModernForms to be ready)
    function initActivities() {
        if (window.modernForms && typeof window.modernForms.loadHistory === 'function') {
            loadProfileActivities();
        } else {
            setTimeout(initActivities, 200);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initActivities, 500);
        });
    } else {
        setTimeout(initActivities, 500);
    }
});

// Profile page functions (legacy modal-based functions)
// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

function editProfile() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.classList.remove('d-none', 'hidden');
        modal.classList.add('show');
        // Store original values for change detection
        setTimeout(() => {
            const emailField = document.getElementById('edit_email');
            const phoneField = document.getElementById('edit_phone');
            if (emailField) emailField.setAttribute('data-original', emailField.value || '');
            if (phoneField) phoneField.setAttribute('data-original', phoneField.value || '');
        }, 100);
        // Trigger custom event for modal shown
        modal.dispatchEvent(new Event('shown'));
    }
}

function closeEditProfileModal() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.classList.add('d-none', 'hidden');
        modal.classList.remove('show');
    }
}

function changePassword() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.classList.remove('d-none', 'hidden');
        modal.classList.add('show');
        // Clear form
        const form = document.getElementById('changePasswordForm');
        if (form) {
            form.reset();
            // Store original (empty) values
            form.querySelectorAll('input').forEach(input => {
                input.setAttribute('data-original', '');
            });
        }
        // Trigger custom event for modal shown
        modal.dispatchEvent(new Event('shown'));
    }
}

function closeChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.classList.add('d-none', 'hidden');
        modal.classList.remove('show');
    }
}

function updateAccountSettings() {
    editProfile(); // Same as edit profile
}

function viewAccountHistory() {
    // Scroll to activities section
    const activitiesSection = document.querySelector('.activities-section');
    if (activitiesSection) {
        activitiesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function viewSecurityLog() {
    // Show security information
    alert('Security Information:\n\nYour account is secure. All activities are logged in the Recent Activities section below.');
}

function closeDeleteAccountModal() {
    const modal = document.getElementById('deleteAccountModal');
    if (modal) {
        modal.classList.add('d-none', 'hidden');
        modal.classList.remove('show');
    }
}

function confirmDeleteAccount() {
    const confirmText = document.getElementById('delete_confirm')?.value;
    if (confirmText === 'DELETE') {
        if (confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!')) {
            showProfileError('Account deletion feature is disabled for security. Please contact administrator.');
            closeDeleteAccountModal();
        }
    } else {
        showProfileError('Please type "DELETE" exactly to confirm account deletion.');
        const confirmField = document.getElementById('delete_confirm');
        if (confirmField) confirmField.focus();
    }
}

// Alert helper functions for profile page
function showProfileSuccess(message) {
    const successEl = document.getElementById('successMessage');
    if (successEl) {
        successEl.textContent = message;
        successEl.classList.remove('d-none', 'hidden');
        successEl.classList.add('show');
        setTimeout(() => {
            successEl.classList.remove('show');
            successEl.classList.add('d-none', 'hidden');
        }, 3000);
    } else {
        // Fallback to notification system
        if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        } else {
            alert(message);
        }
    }
}

function showProfileError(message) {
    const errorEl = document.getElementById('errorMessage');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('d-none', 'hidden');
        errorEl.classList.add('show');
        setTimeout(() => {
            errorEl.classList.remove('show');
            errorEl.classList.add('d-none', 'hidden');
        }, 5000);
    } else {
        // Fallback to notification system
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        } else {
            alert(message);
        }
    }
}

// Handle edit profile form submission (legacy modal-based forms)
document.addEventListener('DOMContentLoaded', function() {
    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate form fields
            const emailField = document.getElementById('edit_email');
            const phoneField = document.getElementById('edit_phone');
            const email = emailField.value.trim();
            const phone = phoneField.value.trim();
            
            // Validation
            if (!email) {
                showProfileError('Please enter an email address');
                emailField.focus();
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showProfileError('Please enter a valid email address (e.g., user@example.com)');
                emailField.focus();
                return;
            }
            
            const formData = {
                action: 'update_profile',
                email: email,
                phone: phone
            };
            
            try {
                const response = await fetch(getApiBase() + '/profile/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showProfileSuccess('Profile updated successfully!');
                    setTimeout(() => {
                        closeEditProfileModal();
                        location.reload(); // Reload to show updated data
                    }, 1000);
                } else {
                    showProfileError('Error: ' + (data.message || 'Failed to update profile'));
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showProfileError('Failed to update profile: ' + (error.message || 'Please try again'));
            }
        });
    }
    
    // Handle change password form submission
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const currentPasswordField = document.getElementById('current_password');
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            const currentPassword = currentPasswordField.value;
            const newPassword = newPasswordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            // Validation
            if (!currentPassword) {
                showProfileError('Please enter your current password');
                currentPasswordField.focus();
                return;
            }
            
            if (!newPassword) {
                showProfileError('Please enter a new password');
                newPasswordField.focus();
                return;
            }
            
            if (newPassword.length < 6) {
                showProfileError('New password must be at least 6 characters long');
                newPasswordField.focus();
                return;
            }
            
            if (!confirmPassword) {
                showProfileError('Please confirm your new password');
                confirmPasswordField.focus();
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showProfileError('New passwords do not match! Please try again');
                confirmPasswordField.focus();
                return;
            }
            
            const formData = {
                action: 'change_password',
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            };
            
            try {
                const response = await fetch(getApiBase() + '/profile/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showProfileSuccess('Password changed successfully! Redirecting to login...');
                    setTimeout(() => {
                        closeChangePasswordModal();
                        window.location.href = getBaseUrl() + '/pages/logout.php';
                    }, 1500);
                } else {
                    showProfileError('Error: ' + (data.message || 'Failed to change password'));
                }
            } catch (error) {
                console.error('Error changing password:', error);
                showProfileError('Failed to change password: ' + (error.message || 'Please try again'));
            }
        });
    }
    
    // Helper function to check if profile form has changes
    function hasProfileFormChanges(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        for (let input of inputs) {
            if (input.type === 'password') {
                // For password fields, check if they have any value
                if (input.value && input.value.trim() !== '') {
                    return true;
                }
            } else if (input.type === 'email' || input.type === 'tel') {
                // Check if value differs from original (stored in data-original attribute)
                const original = input.getAttribute('data-original') || '';
                if (input.value.trim() !== original.trim()) {
                    return true;
                }
            } else {
                if (input.value && input.value.trim() !== '') {
                    return true;
                }
            }
        }
        return false;
    }
    
    // Store original values when opening modals
    const editProfileModal = document.getElementById('editProfileModal');
    if (editProfileModal) {
        editProfileModal.addEventListener('shown', function() {
            const emailField = document.getElementById('edit_email');
            const phoneField = document.getElementById('edit_phone');
            if (emailField) emailField.setAttribute('data-original', emailField.value || '');
            if (phoneField) phoneField.setAttribute('data-original', phoneField.value || '');
        });
    }
    
    // Close modals when clicking outside with alerts
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            if (e.target.id === 'editProfileModal') {
                // Check for unsaved changes
                if (hasProfileFormChanges('editProfileForm')) {
                    if (confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
                        closeEditProfileModal();
                    }
                } else {
                    closeEditProfileModal();
                }
            } else if (e.target.id === 'changePasswordModal') {
                // Check for unsaved changes
                if (hasProfileFormChanges('changePasswordForm')) {
                    if (confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
                        closeChangePasswordModal();
                    }
                } else {
                    closeChangePasswordModal();
                }
            } else if (e.target.id === 'deleteAccountModal') {
                closeDeleteAccountModal();
            }
        }
    });
});
