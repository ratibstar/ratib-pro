/**
 * EN: Implements frontend interaction behavior in `js/worker/musaned.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/musaned.js`.
 */
// Debug Configuration - Set to false for production (shared across all worker files)
window.DEBUG_MODE = window.DEBUG_MODE !== undefined ? window.DEBUG_MODE : false;
const debugMusaned = {
    log: (...args) => window.DEBUG_MODE && console.log('[Musaned]', ...args),
    error: (...args) => window.DEBUG_MODE && console.error('[Musaned]', ...args),
    warn: (...args) => window.DEBUG_MODE && console.warn('[Musaned]', ...args),
    info: (...args) => window.DEBUG_MODE && console.info('[Musaned]', ...args)
};

class MusanedStatus {
    // Custom modern confirm dialog
    static showCustomConfirm(message, onConfirm, onCancel) {
        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'custom-alert-backdrop';
        
        // Create alert box
        const alertBox = document.createElement('div');
        alertBox.className = 'custom-alert-box';
        alertBox.innerHTML = `
            <div class="custom-alert-header">
                <i class="fas fa-question-circle"></i>
                <span>Confirmation</span>
            </div>
            <div class="custom-alert-body">
                <p>${message}</p>
            </div>
            <div class="custom-alert-footer">
                <button class="custom-alert-btn cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="custom-alert-btn confirm-btn">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        `;
        
        // Add to document
        document.body.appendChild(backdrop);
        document.body.appendChild(alertBox);
        
        // Show with animation
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            alertBox.classList.add('show');
        });
        
        // Close function
        const closeAlert = () => {
            alertBox.classList.remove('show');
            backdrop.classList.remove('show');
            setTimeout(() => {
                alertBox.remove();
                backdrop.remove();
            }, 200);
        };
        
        // Handle confirm
        alertBox.querySelector('.confirm-btn').addEventListener('click', () => {
            closeAlert();
            if (onConfirm) onConfirm();
        });
        
        // Handle cancel
        alertBox.querySelector('.cancel-btn').addEventListener('click', () => {
            closeAlert();
            if (onCancel) onCancel();
        });
        
        // Handle backdrop click
        backdrop.addEventListener('click', () => {
            closeAlert();
            if (onCancel) onCancel();
        });
        
        // Handle escape key
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeAlert();
                if (onCancel) onCancel();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }
    static createStatusSection(title, statusName, worker) {
        const issuesFieldName = statusName.replace('_status', '_issues');
        const raw = worker[statusName];
        const v = raw == null || raw === '' ? 'pending' : String(raw).toLowerCase();
        const isPending = v === 'pending' || !['done', 'not_done', 'issues', 'canceled'].includes(v);
        
        return `
            <div class="status-section">
                <h3>${title}</h3>
                <div class="status-options">
                    <label class="status-option status-pending">
                        <input type="radio" name="${statusName}" value="pending" ${isPending ? 'checked' : ''}>
                        <span class="status-label">Pending</span>
                    </label>
                    <label class="status-option status-done">
                        <input type="radio" name="${statusName}" value="done" ${v === 'done' ? 'checked' : ''}>
                        <span class="status-label">Done</span>
                    </label>
                    <label class="status-option status-not-done">
                        <input type="radio" name="${statusName}" value="not_done" ${v === 'not_done' ? 'checked' : ''}>
                        <span class="status-label">Not Done</span>
                    </label>
                    <label class="status-option status-issues">
                        <input type="radio" name="${statusName}" value="issues" ${v === 'issues' ? 'checked' : ''}>
                        <span class="status-label">Some Issues</span>
                    </label>
                    <label class="status-option status-canceled">
                        <input type="radio" name="${statusName}" value="canceled" ${v === 'canceled' ? 'checked' : ''}>
                        <span class="status-label">Canceled</span>
                    </label>
                </div>
                
                <textarea name="${issuesFieldName}" id="${issuesFieldName}" class="d-none">${worker[issuesFieldName] || ''}</textarea>
            </div>
        `;
    }

    static showForm(worker) {
        // Remove any existing Musaned modal
        const existingModal = document.getElementById('musanedModal');
        if (existingModal) {
            existingModal.remove();
        }
        const toEn = window.toEnglishString || window.toWesternNumerals || (v => v);
        const qs = new URLSearchParams(window.location.search || '');
        let agencyId = qs.get('agency_id') || '';
        let controlFlag = qs.get('control') === '1' ? '1' : '';
        const appCfg = document.getElementById('app-config');
        if (appCfg && appCfg.getAttribute('data-control-pro-bridge') === '1') {
            controlFlag = '1';
            if (!agencyId) {
                const aidCfg = parseInt(appCfg.getAttribute('data-agency-id') || '0', 10);
                if (aidCfg > 0) {
                    agencyId = String(aidCfg);
                }
            }
        }
        // Create Musaned modal HTML
        const modalHTML = `
            <div id="musanedModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-check"></i> Musaned Status Management</h2>
                        <button class="close-modal" data-action="close-musaned-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="worker-info-section">
                            <h3>Worker Information</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Worker ID:</label>
                                    <span>${toEn(worker.formatted_id || worker.id)}</span>
                                </div>
                                <div class="info-item">
                                    <label>Name:</label>
                                    <span>${toEn(worker.worker_name || worker.full_name || 'Not provided')}</span>
                                </div>
                                <div class="info-item">
                                    <label>Nationality:</label>
                                    <span>${toEn(worker.nationality || 'Not provided')}</span>
                                </div>
                                <div class="info-item">
                                    <label>Passport:</label>
                                    <span>${toEn(worker.passport_number || 'Not provided')}</span>
                                </div>
                            </div>
                        </div>
                        
                        <form id="musanedForm" data-action="submit-musaned-form">
                            <input type="hidden" name="worker_id" value="${worker.id}">
                            <input type="hidden" name="_control" value="${controlFlag}">
                            <input type="hidden" name="_agency_id" value="${agencyId}">
                            
                            <div class="status-sections-grid">
                                <div class="status-column">
                                    <h3 class="column-title">Musaned</h3>
                                    ${this.createStatusSection('Musaned Status', 'musaned_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Contract</h3>
                                    ${this.createStatusSection('Contract Status', 'contract_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Embassy</h3>
                                    ${this.createStatusSection('Foreign Embassy Approval', 'embassy_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">EPRO Approval</h3>
                                    ${this.createStatusSection('EPRO Approval', 'epro_approval_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">EPRO Approved</h3>
                                    ${this.createStatusSection('EPRO Approved', 'epro_approved_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">FMOL Approval</h3>
                                    ${this.createStatusSection('FMOL Approval', 'fmol_approval_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">FMOL Approved</h3>
                                    ${this.createStatusSection('FMOL Approved', 'fmol_approved_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Sent Saudi Embassy</h3>
                                    ${this.createStatusSection('Sent Saudi Embassy', 'saudi_embassy_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Visa Issued</h3>
                                    ${this.createStatusSection('Visa Issued', 'visa_issued_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Arrived KSA</h3>
                                    ${this.createStatusSection('Arrived KSA', 'arrived_ksa_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Rejected</h3>
                                    ${this.createStatusSection('Rejected', 'rejected_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Canceled</h3>
                                    ${this.createStatusSection('Canceled', 'canceled_status', worker)}
                                </div>
                                
                                <div class="status-column">
                                    <h3 class="column-title">Visa Canceled</h3>
                                    ${this.createStatusSection('Visa Canceled Outside KSA', 'visa_canceled_status', worker)}
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-cancel" data-action="close-musaned-modal">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Musaned Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listeners for close buttons (replaces inline onclick handlers)
        const modal = document.getElementById('musanedModal');
        if (modal) {
            modal.querySelectorAll('[data-action="close-musaned-modal"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (typeof MusanedStatus.closeForm === 'function') {
                        MusanedStatus.closeForm();
                    }
                });
            });
            
            // Add event listener for form submit (replaces inline onsubmit handler)
            const form = modal.querySelector('[data-action="submit-musaned-form"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (typeof MusanedStatus.handleFormSubmit === 'function') {
                        MusanedStatus.handleFormSubmit(e);
                    }
                });
            }
        }
        
        // Add event listeners
        this.setupFormListeners();
        
        // Add click outside to close functionality
        this.setupClickOutsideListener();
        
        // Store original values for change detection
        this.storeOriginalValues();
    }

    static closeForm() {
        // Always ask for confirmation when closing with custom dialog
        this.showCustomConfirm(
            'Are you sure you want to close the Musaned Status Management?',
            () => {
                // On confirm: Close the modal
                const modal = document.getElementById('musanedModal');
                if (modal) {
                    modal.remove();
                }
            },
            () => {} // On cancel: Do nothing (keep modal open)
        );
    }

    static checkForChanges() {
        // Check if any radio buttons have been changed from their original state
        const form = document.getElementById('musanedForm');
        if (!form) return false;
        
        const radioButtons = form.querySelectorAll('input[type="radio"]');
        for (let radio of radioButtons) {
            const isChecked = radio.checked;
            const wasOriginallyChecked = radio.dataset.originalValue === 'true';
            
            if (isChecked !== wasOriginallyChecked) {
                return true; // Found a change
            }
        }
        
        // Check if any textarea values have changed
        const textareas = form.querySelectorAll('textarea');
        for (let textarea of textareas) {
            if (textarea.value !== (textarea.dataset.originalValue || '')) {
                return true; // Found a change
            }
        }
        
        return false; // No changes detected
    }

    static setupClickOutsideListener() {
        const modal = document.getElementById('musanedModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                // If clicking on the modal backdrop (not the content)
                if (e.target === modal) {
                    this.closeForm();
                }
            });
        }
    }

    static storeOriginalValues() {
        const form = document.getElementById('musanedForm');
        if (!form) return;
        
        // Store original radio button values
        const radioButtons = form.querySelectorAll('input[type="radio"]');
        radioButtons.forEach(radio => {
            radio.dataset.originalValue = radio.checked.toString();
        });
        
        // Store original textarea values
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.dataset.originalValue = textarea.value;
        });
    }

    static setupFormListeners() {
        // List of all status names that have issues sections
        const statusNames = [
            'musaned_status', 'contract_status', 'embassy_status', 'epro_approval_status',
            'epro_approved_status', 'fmol_approval_status', 'fmol_approved_status',
            'saudi_embassy_status', 'visa_issued_status', 'arrived_ksa_status',
            'rejected_status', 'canceled_status', 'visa_canceled_status'
        ];
        
        // Setup click listeners for all status types
        statusNames.forEach(statusName => {
            const radios = document.querySelectorAll(`input[name="${statusName}"]`);
            
            if (radios.length > 0) {
                radios.forEach(radio => {
                    if (radio.value === 'issues') {
                        // For "Some Issues" buttons, use click event only
                        let isProcessing = false;
                        
                        radio.addEventListener('click', function(e) {
                            // Prevent double trigger
                            if (isProcessing) return;
                            isProcessing = true;
                            
                            const form = document.getElementById('musanedForm');
                            const workerId = form.querySelector('input[name="worker_id"]').value;
                            
                            // Get display name from the section
                            const statusSection = this.closest('.status-section');
                            let displayName = statusSection.querySelector('h3').textContent;
                            
                            // Show the popup
                            MusanedStatus.showIssuesPopupInModal(workerId, statusName, displayName);
                            
                            // Reset flag after a short delay
                            setTimeout(() => {
                                isProcessing = false;
                            }, 300);
                        });
                    }
                });
            }
        });
    }

    static async handleFormSubmit(event) {
        event.preventDefault();
        
        // Ask for confirmation before saving with custom dialog
        this.showCustomConfirm(
            'Are you sure you want to save these Musaned status changes?',
            async () => {
                // On confirm: Proceed with saving
                const formData = new FormData(event.target);
                const workerId = formData.get('worker_id');
                
                try {
                    let workersApi = window.WORKERS_API;
                    if (!workersApi) {
                        const base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '';
                        const el = document.getElementById('app-config');
                        const fromCfg = (el && el.getAttribute('data-base-url')) || '';
                        const root = String(base || fromCfg || '').replace(/\/+$/, '');
                        workersApi = root + '/api/workers';
                    }
                    let url = `${workersApi.replace(/\/+$/, '')}/musaned/update.php`;
                    const elCfg = document.getElementById('app-config');
                    if (elCfg && (elCfg.getAttribute('data-control') === '1' || elCfg.getAttribute('data-control-pro-bridge') === '1')) {
                        url += (url.indexOf('?') === -1 ? '?' : '&') + 'control=1';
                    }
                    const response = await fetch(url, {
                        method: 'POST',
                        credentials: 'include',
                        body: formData
                    });
                    
                    const result = await response.json().catch(() => ({}));
                    
                    if (response.ok && result.success) {
                        if (window.notifications && window.notifications.success) {
                            window.notifications.success(result.message || 'Musaned status saved.');
                        }
                        const modal = document.getElementById('musanedModal');
                        if (modal) {
                            modal.remove();
                        }
                        if (window.workerTable) {
                            window.workerTable.loadWorkers();
                        }
                        if (window.unifiedHistory) {
                            await window.unifiedHistory.refreshIfOpen();
                        }
                        document.dispatchEvent(new CustomEvent('history-updated'));
                    } else {
                        const msg = result.error || result.message || ('HTTP ' + response.status);
                        if (window.notifications && window.notifications.error) {
                            window.notifications.error('Could not save Musaned status: ' + msg);
                        } else {
                            debugMusaned.error('Error updating Musaned status:', msg);
                        }
                    }
                } catch (error) {
                    if (window.notifications && window.notifications.error) {
                        window.notifications.error('Could not save Musaned status. Check your connection and try again.');
                    }
                    debugMusaned.error('Error updating Musaned status:', error);
                }
            },
            () => {} // On cancel: Do nothing (keep form open)
        );
    }

    static init() {
        if (this.initialized) return;
        this.initialized = true;
        
        // Initialize status storage if not exists
        if (!localStorage.getItem('musanedStatuses')) {
            localStorage.setItem('musanedStatuses', JSON.stringify({}));
        }

        // Restore saved statuses for all buttons
        document.querySelectorAll('.musaned-btn').forEach(button => {
            const workerId = button.closest('tr').dataset.workerId;
            if (workerId) {
                const savedStatuses = JSON.parse(localStorage.getItem('musanedStatuses') || '{}');
                const workerData = savedStatuses[workerId] || {};
                
                // Find the first status that is set
                for (const [statusName, statusData] of Object.entries(workerData)) {
                    if (statusData && statusData.value) {
                        button.dataset.statusName = statusData.displayName;
                        this.updateButtonDisplay(button, statusData.value, statusData.issues);
                        break;
                    }
                }
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.status-dropdown-content') && !e.target.closest('.musaned-btn')) {
                const dropdown = document.querySelector('.status-dropdown-content');
                const backdrop = document.querySelector('.status-dropdown-backdrop');
                if (dropdown && backdrop) {
                    this.closeModal(dropdown, backdrop);
                }
            }
        });
    }

    static updateButtonDisplay(button, status, issues = '') {
        // Get the status name from the button's data or closest status item
        const statusName = button.dataset.statusName || 'Status';
        
        // Get status text
        let statusText = '';
        if (status === 'done') statusText = 'Done';
        else if (status === 'not_done') statusText = 'Not Done';
        else if (status === 'issues') statusText = issues ? 'Issues*' : 'Issues';
        else if (status === 'canceled') statusText = 'Canceled';
        
        // Update button text and status
        button.innerHTML = `
            <i class="fas fa-check-circle"></i>
            ${statusName}: ${statusText}
        `;
        
        // Add title with issues if present
        if (issues) {
            button.title = `Issues: ${issues}`;
        }
        
        // Update button status class
        button.classList.remove('status-done', 'status-not_done', 'status-issues', 'status-canceled');
        button.classList.add(`status-${status}`);
    }

    static toggleStatus(button) {
        // Remove any existing dropdowns first
        const existingDropdown = document.querySelector('.status-dropdown-content');
        const existingBackdrop = document.querySelector('.status-dropdown-backdrop');
        if (existingDropdown && existingBackdrop) {
            this.closeModal(existingDropdown, existingBackdrop);
            return;
        }
        
        // Create dropdown content
        const dropdown = this.createDropdown();
        document.body.appendChild(dropdown);

        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'status-dropdown-backdrop';
        document.body.appendChild(backdrop);

        // Load saved statuses and apply them
        this.loadSavedStatuses(dropdown, button.dataset.workerId);

        // Show modal and backdrop
        requestAnimationFrame(() => {
            dropdown.classList.add('show');
            backdrop.classList.add('show');
        });

        this.setupModalControls(button, dropdown, backdrop);
    }

    static loadSavedStatuses(dropdown, workerId) {
        const savedStatuses = JSON.parse(localStorage.getItem('musanedStatuses') || '{}');
        const workerData = savedStatuses[workerId] || {};
        
        // Apply saved statuses to radio buttons
        for (const [statusName, statusData] of Object.entries(workerData)) {
            if (statusData && statusData.value) {
                const statusItem = dropdown.querySelector(`[name="${statusName}"][value="${statusData.value}"]`);
                if (statusItem) {
                    statusItem.checked = true;
                    statusItem.closest('label').classList.add('active');
                }
            }
        }
    }

    static createDropdown() {
        const dropdown = document.createElement('div');
        dropdown.className = 'status-dropdown-content';
        dropdown.innerHTML = `
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label">Musaned</span>
                    <div class="status-options">
                        <label><input type="radio" name="musaned_status" value="done"> Done</label>
                        <label><input type="radio" name="musaned_status" value="not_done"> Not Done</label>
                        <label><input type="radio" name="musaned_status" value="issues"> Some Issues</label>
                        <label><input type="radio" name="musaned_status" value="canceled"> Canceled</label>
                        <div class="issues-input d-none">
                            <textarea name="musaned_issues" placeholder="Please describe the issues..." rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <!-- Repeat for other status items with issues input -->
                ${this.getStatusItemWithIssues('Contract', 'contract_status')}
                ${this.getStatusItemWithIssues('Foreign Embassy Approval', 'embassy_status')}
                ${this.getStatusItemWithIssues('EPRO Approval', 'epro_approval_status')}
                ${this.getStatusItemWithIssues('EPRO Approved', 'epro_approved_status')}
                ${this.getStatusItemWithIssues('FMOL Approval', 'fmol_approval_status')}
                ${this.getStatusItemWithIssues('FMOL Approved', 'fmol_approved_status')}
                ${this.getStatusItemWithIssues('Sent Saudi Embassy', 'saudi_embassy_status')}
                ${this.getStatusItemWithIssues('Visa Issued', 'visa_issued_status')}
                ${this.getStatusItemWithIssues('Arrived KSA', 'arrived_ksa_status')}
                ${this.getStatusItemWithIssues('Rejected', 'rejected_status')}
                ${this.getStatusItemWithIssues('Canceled', 'canceled_status')}
                ${this.getStatusItemWithIssues('Visa Canceled Outside KSA', 'visa_canceled_status')}
            </div>
            <div class="status-modal-footer">
                <button type="button" class="status-modal-btn cancel">Cancel</button>
                <button type="button" class="status-modal-btn save">Save Changes</button>
            </div>
        `;
        return dropdown;
    }

    static getStatusItemWithIssues(label, name) {
        return `
            <div class="status-item">
                <span class="status-label">${label}</span>
                <div class="status-options">
                    <label><input type="radio" name="${name}" value="done"> Done</label>
                    <label><input type="radio" name="${name}" value="not_done"> Not Done</label>
                    <label><input type="radio" name="${name}" value="issues"> Some Issues</label>
                    <label><input type="radio" name="${name}" value="canceled"> Canceled</label>
                </div>
            </div>
        `;
    }

    static showIssuesPopupInModal(workerId, statusName, displayName) {
        // Create popup elements with higher z-index to appear in front
        const backdrop = document.createElement('div');
        backdrop.className = 'issues-backdrop-modal';
        
        // Get current issues text from the hidden textarea in the form
        const issuesFieldName = statusName.replace('_status', '_issues');
        const existingTextarea = document.getElementById(issuesFieldName);
        const currentIssues = existingTextarea ? existingTextarea.value : '';
        
        const popup = document.createElement('div');
        popup.className = 'issues-popup-modal';
        popup.innerHTML = `
            <div class="issues-header">
                <div class="issues-title"><i class="fas fa-exclamation-triangle"></i> ${displayName} - Issues Description</div>
                <button class="issues-close">&times;</button>
            </div>
            <div class="issues-body">
                <textarea placeholder="Please describe the issues in detail..." rows="6">${currentIssues}</textarea>
            </div>
            <div class="issues-footer">
                <button class="issues-btn cancel"><i class="fas fa-times"></i> Cancel</button>
                <button class="issues-btn save"><i class="fas fa-save"></i> Save</button>
            </div>
        `;

        // Add to document
        document.body.appendChild(backdrop);
        document.body.appendChild(popup);

        // Show with animation
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            popup.classList.add('show');
        });

        // Setup event handlers
        const closePopup = (shouldRevert = true, showConfirm = false) => {
            const doClose = () => {
                popup.classList.remove('show');
                backdrop.classList.remove('show');
                setTimeout(() => {
                    popup.remove();
                    backdrop.remove();
                }, 200);
                
                // If canceling, revert the radio button
                if (shouldRevert) {
                    const radios = document.querySelectorAll(`input[name="${statusName}"]`);
                    radios.forEach(radio => {
                        if (radio.value === 'issues') {
                            radio.checked = false;
                        }
                    });
                }
            };
            
            // If confirm is requested, ask user first
            if (showConfirm) {
                MusanedStatus.showCustomConfirm(
                    'Are you sure you want to cancel? Changes will not be saved.',
                    doClose,
                    () => {} // On cancel, do nothing (keep popup open)
                );
            } else {
                doClose();
            }
        };

        popup.querySelector('.issues-close').addEventListener('click', () => closePopup(true, true));
        backdrop.addEventListener('click', () => closePopup(true, true));
        popup.querySelector('.issues-btn.cancel').addEventListener('click', () => closePopup(true, true));

        popup.querySelector('.issues-btn.save').addEventListener('click', () => {
            const issues = popup.querySelector('textarea').value;
            
            // Confirm save with custom dialog
            MusanedStatus.showCustomConfirm(
                'Are you sure you want to save these issues?',
                () => {
                    // On confirm: Save to the hidden textarea in the form (for form submission)
                    if (existingTextarea) {
                        existingTextarea.value = issues;
                    }
                    closePopup(false);
                },
                () => {} // On cancel, do nothing (keep popup open)
            );
        });

        // Close on escape key
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closePopup(true, true);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);

        // Focus textarea and move cursor to end
        const textarea = popup.querySelector('textarea');
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    }

    static showIssuesPopup(workerId, statusName, displayName) {
        // Create popup elements
        const backdrop = document.createElement('div');
        backdrop.className = 'issues-backdrop';
        
        // Get saved issues text
        const savedStatuses = JSON.parse(localStorage.getItem('musanedStatuses') || '{}');
        const savedIssues = savedStatuses[workerId]?.[statusName]?.issues || '';
        
        const popup = document.createElement('div');
        popup.className = 'issues-input';
        popup.innerHTML = `
            <div class="issues-header">
                <div class="issues-title">${displayName} Issues</div>
                <button class="issues-close">&times;</button>
            </div>
            <textarea placeholder="Please describe the issues..." rows="4">${savedIssues}</textarea>
            <div class="issues-footer">
                <button class="issues-btn cancel">Cancel</button>
                <button class="issues-btn save">Save</button>
            </div>
        `;

        // Add to document
        document.body.appendChild(backdrop);
        document.body.appendChild(popup);

        // Show with animation
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            popup.classList.add('show');
        });

        // Setup event handlers
        const closePopup = () => {
            // Skip unsaved changes confirmation - close directly
            if (true) {
                popup.classList.remove('show');
                backdrop.classList.remove('show');
                setTimeout(() => {
                    popup.remove();
                    backdrop.remove();
                }, 200);
            }
        };

        popup.querySelector('.issues-close').addEventListener('click', closePopup);
        backdrop.addEventListener('click', closePopup);
        popup.querySelector('.issues-btn.cancel').addEventListener('click', closePopup);

        popup.querySelector('.issues-btn.save').addEventListener('click', () => {
            const issues = popup.querySelector('textarea').value;
            this.saveStatus(workerId, statusName, 'issues', issues);
            popup.classList.remove('show');
            backdrop.classList.remove('show');
            setTimeout(() => {
                popup.remove();
                backdrop.remove();
            }, 200);
        });

        // Close on escape with confirmation
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                // Skip unsaved changes confirmation - close directly
            if (true) {
                    popup.classList.remove('show');
                    backdrop.classList.remove('show');
                    setTimeout(() => {
                        popup.remove();
                        backdrop.remove();
                    }, 200);
                    document.removeEventListener('keydown', escHandler);
                }
            }
        };
        document.addEventListener('keydown', escHandler);

        // Focus textarea and move cursor to end
        const textarea = popup.querySelector('textarea');
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    }

    static setupModalControls(button, dropdown, backdrop) {
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'status-modal-close';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        dropdown.appendChild(closeBtn);

        // Setup radio buttons and labels
        dropdown.querySelectorAll('.status-options label').forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            
            label.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Remove active class from all labels in this group
                const allLabels = label.closest('.status-options').querySelectorAll('label');
                allLabels.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked label and check the radio
                label.classList.add('active');
                radio.checked = true;

                // If "Some Issues" is selected, show the popup
                if (radio.value === 'issues') {
                    const workerId = button.dataset.workerId;
                    const statusName = radio.name;
                    const displayName = label.closest('.status-item').querySelector('.status-label').textContent;
                    this.showIssuesPopup(workerId, statusName, displayName);
                } else {
                    // Save status immediately for other options
                    this.saveStatus(button.dataset.workerId, radio.name, radio.value);
                }
            });
        });

        // Close handlers with confirmation
        const confirmClose = () => {
            // Skip unsaved changes confirmation - close directly
            if (true) {
                this.closeModal(dropdown, backdrop);
            }
        };
        
        backdrop.addEventListener('click', confirmClose);
        closeBtn.addEventListener('click', confirmClose);

        // Setup footer buttons
        const cancelBtn = dropdown.querySelector('.status-modal-btn.cancel');
        const saveBtn = dropdown.querySelector('.status-modal-btn.save');

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                // Skip unsaved changes confirmation - close directly
            if (true) {
                    this.closeModal(dropdown, backdrop);
                }
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.updateButtonStatus(button, dropdown);
                this.closeModal(dropdown, backdrop);
            });
        }

        // Close on escape key with confirmation
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                // Skip unsaved changes confirmation - close directly
            if (true) {
                    this.closeModal(dropdown, backdrop);
                    document.removeEventListener('keydown', escHandler);
                }
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    static async saveStatus(workerId, statusName, value, issues = '') {
        const savedStatuses = JSON.parse(localStorage.getItem('musanedStatuses') || '{}');
        
        if (!savedStatuses[workerId]) {
            savedStatuses[workerId] = {};
        }
        
        // Get the display name for the status
        let displayName = statusName.split('_')[0].toUpperCase();
        if (statusName === 'musaned_status') displayName = 'Musaned';
        else if (statusName === 'contract_status') displayName = 'Contract';
        else if (statusName === 'embassy_status') displayName = 'Embassy';
        else if (statusName === 'epro_approval_status') displayName = 'EPRO App';
        else if (statusName === 'epro_approved_status') displayName = 'EPRO';
        else if (statusName === 'fmol_approval_status') displayName = 'FMOL App';
        else if (statusName === 'fmol_approved_status') displayName = 'FMOL';
        else if (statusName === 'saudi_embassy_status') displayName = 'Saudi Emb';
        else if (statusName === 'visa_issued_status') displayName = 'Visa';
        else if (statusName === 'arrived_ksa_status') displayName = 'KSA';
        else if (statusName === 'rejected_status') displayName = 'Rejected';
        else if (statusName === 'canceled_status') displayName = 'Canceled';
        else if (statusName === 'visa_canceled_status') displayName = 'Visa Can';
        
        // Save both status and display name
        savedStatuses[workerId][statusName] = {
            value: value,
            displayName: displayName,
            issues: value === 'issues' ? issues : ''
        };
        
        localStorage.setItem('musanedStatuses', JSON.stringify(savedStatuses));

        // Update the button in the table for this status
        const button = document.querySelector(`tr[data-worker-id="${workerId}"] .musaned-btn`);
        if (button) {
            button.dataset.statusName = displayName;
            this.updateButtonDisplay(button, value, issues);
        }

        // --- Backend update ---
        try {
            const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
            const response = await fetch(`${workersApi}/core/update-musaned-status.php`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: workerId,
                    status_name: statusName,
                    status_value: value,
                    issues: value === 'issues' ? issues : undefined
                })
            });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to update status');
            }
            // Refresh the table if available
            if (window.workerTable && typeof window.workerTable.loadWorkers === 'function') {
                window.workerTable.loadWorkers();
            }
            // Refresh history if UnifiedHistory modal is open
            if (window.unifiedHistory) {
                await window.unifiedHistory.refreshIfOpen();
            }
            // Dispatch history update event
            document.dispatchEvent(new CustomEvent('history-updated'));
        } catch (error) {
            if (window.notifications && window.notifications.error) {
                window.notifications.error('Failed to update Musaned status on server: ' + error.message);
            } else {
                debugMusaned.error('Failed to update Musaned status on server:', error.message);
            }
        }
    }

    static updateButtonStatus(button, dropdown) {
        // Get all checked statuses
        dropdown.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const statusName = radio.name;
            const statusValue = radio.value;
            // Save each status
            // Use data-worker-id attribute for compatibility
            let workerId = button.getAttribute('data-worker-id');
            if (!workerId) {
                // fallback: try to get from closest tr
                const tr = button.closest('tr');
                workerId = tr ? tr.getAttribute('data-worker-id') || tr.getAttribute('data-id') : undefined;
            }
            this.saveStatus(workerId, statusName, statusValue);
        });
    }

    static closeModal(dropdown, backdrop) {
        if (!dropdown || !backdrop) return;
        
        dropdown.classList.remove('show');
        backdrop.classList.remove('show');

        // Remove elements after animation
        const onTransitionEnd = () => {
            dropdown.remove();
            backdrop.remove();
            dropdown.removeEventListener('transitionend', onTransitionEnd);
        };

        dropdown.addEventListener('transitionend', onTransitionEnd);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MusanedStatus.init());
} else {
    MusanedStatus.init();
}
