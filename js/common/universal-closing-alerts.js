/**
 * EN: Implements frontend interaction behavior in `js/common/universal-closing-alerts.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/common/universal-closing-alerts.js`.
 */
/**
 * Universal Closing Alerts for All Forms
 * Provides modern closing alerts for any form across the application
 */

// Universal Closing Alert System
if (typeof window.UniversalClosingAlerts === 'undefined') {
const UniversalClosingAlerts = {
    // Show closing alert modal
    showClosingAlert(options = {}) {
        return new Promise((resolve) => {
            // CRITICAL: If offline, NEVER show unsaved changes alerts - CHECK FIRST
            const isOffline = !navigator.onLine;
            
            if (isOffline) {
                // Auto-confirm (allow close) when offline
                resolve(true);
                return;
            }
            
            // Worker forms are now handled through handleFormClose which checks for changes
            
            const modal = document.getElementById('closingAlertModal');
            
            // DOUBLE CHECK: If modal somehow exists and is visible while offline, hide it
            if (modal && !navigator.onLine) {
                modal.setAttribute('data-offline-hidden', 'true');
                modal.classList.add('d-none', 'offline-hidden');
                resolve(true);
                return;
            }
            const cancelBtn = document.getElementById('closingAlertCancel');
            const discardBtn = document.getElementById('closingAlertDiscard');
            const title = modal.querySelector('h3');
            const message = modal.querySelector('p');
            const discardText = discardBtn.querySelector('span') || discardBtn;
            
            if (!modal || !cancelBtn || !discardBtn) {
                resolve(false);
                return;
            }
            
            // Update modal content based on options
            if (options.title) title.textContent = options.title;
            if (options.message) message.textContent = options.message;
            if (options.discardText) discardText.textContent = options.discardText;
            
            // Update modal theme for delete confirmations (styles in CSS)
            if (options.type === 'delete' || options.title?.toLowerCase().includes('delete')) {
                modal.classList.add('delete-confirmation');
                const icon = modal.querySelector('.closing-alert-icon i');
                if (icon) {
                    icon.className = 'fas fa-trash-alt';
                }
            } else {
                modal.classList.remove('delete-confirmation');
                const icon = modal.querySelector('.closing-alert-icon i');
                if (icon) {
                    icon.className = 'fas fa-exclamation-triangle';
                }
            }
            
            // TRIPLE CHECK: Verify still online before showing
            if (!navigator.onLine) {
                resolve(true);
                return;
            }
            
            modal.classList.remove('d-none');
            
            // QUADRUPLE CHECK: Immediately hide if offline detected after display
            if (!navigator.onLine) {
                modal.setAttribute('data-offline-hidden', 'true');
                modal.classList.add('d-none', 'offline-hidden');
                resolve(true);
                return;
            }
            
            const handleCancel = () => {
                modal.classList.add('d-none');
                resolve(false);
                cancelBtn.removeEventListener('click', handleCancel);
                discardBtn.removeEventListener('click', handleDiscard);
            };
            
            const handleDiscard = () => {
                modal.classList.add('d-none');
                resolve(true);
                cancelBtn.removeEventListener('click', handleCancel);
                discardBtn.removeEventListener('click', handleDiscard);
            };
            
            cancelBtn.addEventListener('click', handleCancel);
            discardBtn.addEventListener('click', handleDiscard);
        });
    },

    // Check if form has changes
    hasFormChanges(formSelector) {
        const form = document.querySelector(formSelector);
        if (!form) return false;
        
        // If form has originalValues dataset, use that for comparison (worker forms)
        if (form.dataset.originalValues) {
            try {
                const originalValues = JSON.parse(form.dataset.originalValues);
                const inputs = form.querySelectorAll('input, select, textarea');
                for (let input of inputs) {
                    const key = input.name || input.id;
                    if (!key || key === 'csrf_token') continue; // Skip CSRF token
                    const currentValue = input.value || '';
                    const originalValue = originalValues[key] || '';
                    if (currentValue !== originalValue) {
                        return true; // Found a change
                    }
                }
                return false; // No changes detected
            } catch (e) {
                // Fall back to simple check if parsing fails
            }
        }
        
        // For forms without originalValues, check if form is completely empty
        // Only consider it as having changes if user has actually typed something
        const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea');
        let hasUserInput = false;
        for (let input of inputs) {
            if (input.value && input.value.trim() !== '') {
                hasUserInput = true;
                break;
            }
        }
        
        // If no text inputs have values, check selects (but ignore default "Select..." options)
        if (!hasUserInput) {
            const selects = form.querySelectorAll('select');
            for (let select of selects) {
                const val = select.value || '';
                if (val && val.trim() !== '' && !val.includes('Select')) {
                    hasUserInput = true;
                    break;
                }
            }
        }
        
        return hasUserInput;
    },

    // Enhanced form close handling
    async handleFormClose(formSelector, closeCallback) {
        // CRITICAL: If offline, always allow close without alert
        if (!navigator.onLine) {
            if (closeCallback && typeof closeCallback === 'function') {
                closeCallback();
            }
            return;
        }
        
        // Check for form changes and show alert if needed
        if (this.hasFormChanges(formSelector)) {
            const shouldClose = await this.showClosingAlert();
            if (shouldClose) {
                if (closeCallback && typeof closeCallback === 'function') {
                    closeCallback();
                }
            }
        } else {
            if (closeCallback && typeof closeCallback === 'function') {
                closeCallback();
            }
        }
    },

    // Setup form with closing alerts
    setupForm(formSelector, modalSelector, closeCallback) {
        const form = document.querySelector(formSelector);
        const modal = document.querySelector(modalSelector);
        
        // Silently return if form/modal doesn't exist (expected on pages without these elements)
        if (!form || !modal) {
            return;
        }

        // Handle close button clicks
        const closeButtons = modal.querySelectorAll('[data-action="close-form"], .close-modal, .cancel-btn');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleFormClose(formSelector, closeCallback);
            });
        });

        // Handle outside clicks
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.handleFormClose(formSelector, closeCallback);
            }
        });

        // Handle ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('d-none') && modal.offsetParent !== null) {
                this.handleFormClose(formSelector, closeCallback);
            }
        });
    },

    // Initialize all forms on page
    init() {
        // Subagent forms
        this.setupForm('#subagentFormMain', '#editForm', () => {
            const modal = document.getElementById('editForm');
            if (modal) {
                modal.classList.add('subagent-modal-hidden');
            }
            const form = document.getElementById('subagentFormMain');
            if (form) {
                form.reset();
            }
            window.currentSubagentId = null;
        });

        // Agent forms
        this.setupForm('#agentForm', '#editAgentModal', () => {
            const modal = document.getElementById('editAgentModal');
            if (modal) {
                modal.classList.add('subagent-modal-hidden');
            }
            const form = document.getElementById('agentForm');
            if (form) {
                form.reset();
            }
        });

        // Worker forms - DISABLED: Worker forms have their own close handling
        // this.setupForm('#workerForm', '#editWorkerModal', () => {
        //     const modal = document.getElementById('editWorkerModal');
        //     if (modal) {
        //         modal.style.display = 'none';
        //     }
        //     const form = document.getElementById('workerForm');
        //     if (form) {
        //         form.reset();
        //     }
        // });

        // Account forms
        this.setupForm('#accountDetailsForm', '#accountDetailsModal', () => {
            const modal = document.getElementById('accountDetailsModal');
            if (modal) {
                modal.classList.add('subagent-modal-hidden');
            }
            const form = document.getElementById('accountDetailsForm');
            if (form) {
                form.reset();
            }
        });


    }
};

// CRITICAL: Hide closing alert modal immediately if offline
function hideClosingAlertIfOffline() {
    if (!navigator.onLine) {
        const modal = document.getElementById('closingAlertModal');
        if (modal) {
            modal.setAttribute('data-offline-hidden', 'true');
            modal.classList.add('d-none', 'offline-hidden');
        }
    }
}

// Hide immediately if already loaded
hideClosingAlertIfOffline();

// Hide on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        hideClosingAlertIfOffline();
        UniversalClosingAlerts.init();
    });
} else {
    hideClosingAlertIfOffline();
    UniversalClosingAlerts.init();
}

// Hide when going offline
window.addEventListener('offline', () => {
    hideClosingAlertIfOffline();
});

// Make it globally available
window.UniversalClosingAlerts = UniversalClosingAlerts;
} // End of redeclaration check
