/**
 * EN: Implements frontend interaction behavior in `js/worker/modern-alerts.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/modern-alerts.js`.
 */
/* ========================================
   MODERN CONFIRMATION ALERTS
   Beautiful, animated alert system for worker forms
   ======================================== */

class ModernWorkerAlert {
    static isAlertOpen = false;
    
    /**
     * Show a modern confirmation alert
     * @param {Object} options - Alert configuration
     * @param {string} options.title - Alert title
     * @param {string} options.message - Alert message
     * @param {string} options.type - Alert type: 'warning', 'info', 'success', 'danger'
     * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {string} options.confirmClass - Confirm button class: 'confirm', 'save', 'danger' (default: 'confirm')
     * @param {boolean} options.showCancel - Show cancel button (default: true)
     * @returns {Promise<Object>} Promise that resolves with {action: 'confirm'|'cancel'}
     */
    static show(options = {}) {
        // Prevent multiple alerts
        if (this.isAlertOpen) {
            return Promise.resolve({ action: 'cancel' });
        }
        
        // Auto-confirm if offline and it's an unsaved changes alert
        const isOffline = !navigator.onLine;
        if (isOffline) {
            const title = options.title || '';
            const message = options.message || '';
            const isUnsavedAlert = 
                title.includes('Unsaved Changes') || 
                title.includes('unsaved changes') || 
                title.includes('Discard Changes') || 
                title.includes('Close Form') ||
                title.includes('Cancel Changes') ||
                message.includes('unsaved changes') ||
                message.includes('close without saving') ||
                message.includes('discard');
            
            if (isUnsavedAlert) {
                return Promise.resolve({ action: 'confirm' });
            }
        }
        
        this.isAlertOpen = true;
        
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'modern-alert-overlay';
            
            const alert = document.createElement('div');
            alert.className = 'modern-alert-container';
            
            // Icon mapping
            const iconMap = {
                warning: { icon: 'fa-exclamation-triangle', class: 'warning' },
                info: { icon: 'fa-info-circle', class: 'info' },
                success: { icon: 'fa-check-circle', class: 'success' },
                danger: { icon: 'fa-times-circle', class: 'danger' }
            };
            
            const alertType = options.type || 'warning';
            const iconInfo = iconMap[alertType] || iconMap.warning;
            
            // Button class mapping
            const buttonClassMap = {
                confirm: 'modern-alert-btn-confirm',
                save: 'modern-alert-btn-save',
                danger: 'modern-alert-btn-danger'
            };
            
            const confirmClass = buttonClassMap[options.confirmClass] || buttonClassMap.confirm;
            const showCancel = options.showCancel !== false;
            
            alert.innerHTML = `
                <button class="modern-alert-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modern-alert-header">
                    <div class="modern-alert-icon ${iconInfo.class}">
                        <i class="fas ${iconInfo.icon}"></i>
                    </div>
                    <h3 class="modern-alert-title">${options.title || 'Confirm Action'}</h3>
                </div>
                <div class="modern-alert-body">
                    <p class="modern-alert-message">${options.message || 'Are you sure you want to proceed?'}</p>
                </div>
                <div class="modern-alert-footer">
                    ${showCancel ? `<button class="modern-alert-btn modern-alert-btn-cancel" data-action="cancel">${options.cancelText || 'Cancel'}</button>` : ''}
                    <button class="modern-alert-btn ${confirmClass}" data-action="confirm">${options.confirmText || 'Confirm'}</button>
                </div>
            `;
            
            overlay.appendChild(alert);
            document.body.appendChild(overlay);
            
            // Trigger animation
            requestAnimationFrame(() => {
                overlay.classList.add('show');
            });
            
            // Handle button clicks
            const handleAction = (action) => {
                // Animate out
                alert.classList.add('animating-out');
                
                setTimeout(() => {
                    overlay.remove();
                    this.isAlertOpen = false;
                    resolve({ action });
                }, 200);
            };
            
            // Button click handlers
            alert.addEventListener('click', (e) => {
                const btn = e.target.closest('.modern-alert-btn');
                if (btn) {
                    const action = btn.getAttribute('data-action');
                    if (action) {
                        handleAction(action);
                    }
                }
                
                // Close button
                if (e.target.closest('.modern-alert-close')) {
                    handleAction('cancel');
                }
            });
            
            // Click outside to close (only if showCancel is true)
            if (showCancel) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        handleAction('cancel');
                    }
                });
            }
            
            // ESC key to close
            const handleEsc = (e) => {
                if (e.key === 'Escape' && this.isAlertOpen) {
                    handleAction('cancel');
                    document.removeEventListener('keydown', handleEsc);
                }
            };
            document.addEventListener('keydown', handleEsc);
        });
    }
    
    /**
     * Show close form confirmation
     */
    static showCloseForm() {
        return this.show({
            title: 'Close Form',
            message: 'You have unsaved changes. Are you sure you want to close the form? All unsaved changes will be lost.',
            type: 'warning',
            confirmText: 'Close',
            cancelText: 'Stay',
            confirmClass: 'danger'
        });
    }
    
    /**
     * Show cancel form confirmation
     */
    static showCancelForm() {
        return this.show({
            title: 'Cancel Changes',
            message: 'Are you sure you want to cancel? All unsaved changes will be lost.',
            type: 'warning',
            confirmText: 'Discard Changes',
            cancelText: 'Keep Editing',
            confirmClass: 'danger'
        });
    }
    
    /**
     * Show save confirmation
     */
    static showSaveConfirm() {
        return this.show({
            title: 'Save Worker',
            message: 'Are you sure you want to save this worker? Please review all information before proceeding.',
            type: 'info',
            confirmText: 'Save',
            cancelText: 'Cancel',
            confirmClass: 'save'
        });
    }
    
    /**
     * Show click outside confirmation
     */
    static showClickOutside() {
        return this.show({
            title: 'Unsaved Changes',
            message: 'You have unsaved changes. Clicking outside will close the form and discard all changes. Are you sure?',
            type: 'warning',
            confirmText: 'Close',
            cancelText: 'Stay',
            confirmClass: 'danger'
        });
    }
}

// Export to window for global access
window.ModernWorkerAlert = ModernWorkerAlert;
