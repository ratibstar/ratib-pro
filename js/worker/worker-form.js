/**
 * EN: Implements frontend interaction behavior in `js/worker/worker-form.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/worker-form.js`.
 */
// Worker Form Functionality

// Convert Arabic/Persian numerals to Western; remove Arabic letters entirely
window.toEnglishString = function(val) {
    if (val == null || val === '') return val;
    let s = String(val);
    const numMap = { '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9' };
    s = s.replace(/[٠-٩۰-۹]/g, d => numMap[d] || d);
    s = s.replace(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/g, '');
    return s.replace(/\s+/g, ' ').trim();
};

window.toWesternNumerals = function(val) { return window.toEnglishString(val); };

// Debug Configuration - Set to false for production (shared across all worker files)
window.DEBUG_MODE = window.DEBUG_MODE !== undefined ? window.DEBUG_MODE : false;
const debugForm = {
    log: (...args) => window.DEBUG_MODE && console.log('[Worker-Form]', ...args),
    error: (...args) => window.DEBUG_MODE && console.error('[Worker-Form]', ...args),
    warn: (...args) => window.DEBUG_MODE && console.warn('[Worker-Form]', ...args),
    info: (...args) => window.DEBUG_MODE && console.info('[Worker-Form]', ...args)
};

function ratibWorkerSiteBase() {
    const el = document.getElementById('app-config');
    const base = (el && el.getAttribute('data-base-url')) || document.documentElement.getAttribute('data-base-url') || '';
    return String(base || '').replace(/\/+$/, '');
}

function ratibCountriesCitiesControlSuffix() {
    const el = document.getElementById('app-config');
    if (!el) return '';
    if (el.getAttribute('data-control') === '1' || el.getAttribute('data-control-pro-bridge') === '1') {
        return '&control=1';
    }
    return '';
}

// Modern Alert System for Worker Form
class ModernFormAlert {
    static show(title, message, type = 'warning', options = {}) {
        return new Promise((resolve) => {
            // Remove any existing alerts first
            const existingAlerts = document.querySelectorAll('.modern-form-alert-overlay');
            existingAlerts.forEach(alert => alert.remove());
            
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const showCancel = options.showCancel !== false;
            
            // Icon and color based on type
            const typeConfig = {
                warning: { icon: 'fa-exclamation-triangle', color: '#f39c12', bgGradient: 'linear-gradient(135deg, rgba(243, 156, 18, 0.15) 0%, rgba(230, 126, 34, 0.1) 100%)', iconBg: 'linear-gradient(135deg, #f39c12 0%, #e67e22 100%)' },
                info: { icon: 'fa-info-circle', color: '#3498db', bgGradient: 'linear-gradient(135deg, rgba(52, 152, 219, 0.15) 0%, rgba(41, 128, 185, 0.1) 100%)', iconBg: 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)' },
                danger: { icon: 'fa-times-circle', color: '#e74c3c', bgGradient: 'linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(192, 57, 43, 0.1) 100%)', iconBg: 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)' },
                success: { icon: 'fa-check-circle', color: '#2ecc71', bgGradient: 'linear-gradient(135deg, rgba(46, 204, 113, 0.15) 0%, rgba(39, 174, 96, 0.1) 100%)', iconBg: 'linear-gradient(135deg, #2ecc71 0%, #27ae60 100%)' }
            };
            
            const config = typeConfig[type] || typeConfig.warning;
            
            const overlay = document.createElement('div');
            overlay.className = 'modern-form-alert-overlay';
            
            overlay.innerHTML = `
                <div class="modern-form-alert-container" data-type="${type}">
                    <div class="modern-form-alert-header">
                        <div class="modern-form-alert-icon">
                            <i class="fas ${config.icon}"></i>
                        </div>
                        <h3 class="modern-form-alert-title">${title}</h3>
                    </div>
                    <div class="modern-form-alert-body">
                        <p class="modern-form-alert-message">${message}</p>
                    </div>
                    <div class="modern-form-alert-footer">
                        ${showCancel ? `<button class="modern-form-alert-btn modern-form-alert-btn-cancel" data-action="cancel">${cancelText}</button>` : ''}
                        <button class="modern-form-alert-btn modern-form-alert-btn-confirm" data-action="confirm">${confirmText}</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Trigger animation
            setTimeout(() => {
                overlay.classList.add('show');
            }, 10);
            
            // Handle button clicks
            const handleClick = (e) => {
                if (e.target.classList.contains('modern-form-alert-btn')) {
                    const action = e.target.getAttribute('data-action');
                    this.close(overlay);
                    resolve(action === 'confirm');
                    overlay.removeEventListener('click', handleClick);
                }
            };
            
            overlay.addEventListener('click', handleClick);
            
            // Close on overlay click (outside modal)
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.close(overlay);
                    resolve(false);
                }
            });
        });
    }
    
    static close(overlay) {
        overlay.classList.remove('show');
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 300);
    }
}

// Make ModernFormAlert available globally
window.ModernFormAlert = ModernFormAlert;

// Export HTML CSS - This CSS is also stored in css/worker/worker-table-styles.css between EXPORT_START and EXPORT_END markers
// This constant serves as a fallback and matches the CSS in the CSS file
const EXPORT_HTML_CSS = `
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            color: #ffffff; 
            background: #1a1a1a; 
            font-size: 14px;
        }
        .form-wrapper { 
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 15px;
            padding: 12px;
            margin: 0 auto;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        h2 { 
            color: #ffffff;
            margin: 0 0 10px 0;
            font-size: 120%;
            font-weight: 600;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        h2::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        .form-sidebar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            font-weight: 500;
        }
        .sidebar-nav-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: #ffffff;
        }
        .form-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            padding: 12px;
        }
        .section { 
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 6px;
        }
        .section h3 {
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }
        .form-group { 
            margin-bottom: 10px; 
        }
        .form-label { 
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            display: block; 
            margin-bottom: 6px; 
            font-size: 14px;
        }
        .form-control, .form-select, input[type="text"], input[type="email"], 
        input[type="tel"], input[type="date"], input[type="number"],
        select, textarea { 
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid rgba(255, 255, 255, 0.2); 
            border-radius: 6px; 
            background: rgba(255, 255, 255, 0.05); 
            color: #ffffff; 
            font-size: 14px;
        }
        .form-control:disabled, .form-select:disabled, 
        input:disabled, select:disabled, textarea:disabled {
            background: rgba(255, 255, 255, 0.03);
            color: rgba(255, 255, 255, 0.7);
            cursor: default;
            opacity: 0.8;
        }
        /* Contact section - 3 columns */
        .section.contact-info .form-row {
            grid-template-columns: repeat(3, 1fr);
        }
        /* Documents section - 3 columns */
        .section.documents .form-row {
            grid-template-columns: repeat(3, 1fr);
        }
        /* Full width fields */
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        @media print {
            body { 
                background: #fff; 
                color: #000; 
                padding: 10px; 
            }
            .form-wrapper {
                background: #fff;
                box-shadow: none;
            }
            .section {
                background: #f9fafb;
                border: 1px solid #e2e8f0;
            }
            .form-control, .form-select, input, select, textarea {
                background: #fff;
                color: #000;
                border: 1px solid #ccc;
            }
            h2, .form-label, .section h3 {
                color: #000;
            }
        }
    `;

document.addEventListener('DOMContentLoaded', function() {
    function getCountryProfile() {
        if (typeof window.RATIB_COUNTRY_PROFILE === 'string' && window.RATIB_COUNTRY_PROFILE.trim()) {
            return window.RATIB_COUNTRY_PROFILE.trim().toLowerCase();
        }
        const config = document.getElementById('app-config');
        const text = [
            config?.getAttribute('data-country-name'),
            config?.getAttribute('data-country-code')
        ].map(value => String(value || '').toLowerCase()).join(' ');
        if (text.includes('indonesia') || text.includes('indonesian') || /\bidn?\b/.test(text)) return 'indonesia';
        if (text.includes('bangladesh') || /\bbd\b/.test(text)) return 'bangladesh';
        if (text.includes('sri lanka') || text.includes('srilanka') || /\blk\b/.test(text)) return 'sri_lanka';
        if (text.includes('kenya') || /\bke\b/.test(text)) return 'kenya';
        return 'default';
    }

    function getWorkflowStages() {
        const rows = document.querySelectorAll('#workerForm [data-workflow-stage]');
        const unique = [];
        rows.forEach(row => {
            const key = String(row.getAttribute('data-workflow-stage') || '').trim();
            if (key && !unique.includes(key)) unique.push(key);
        });
        return unique.length > 0 ? unique : ['identity'];
    }

    function isIndonesiaProgramContext() {
        return getCountryProfile() === 'indonesia';
    }

    function updateIndonesiaComplianceVisibility() {
        const container = document.getElementById('workerFormContainer');
        if (!container) return;
        const show = isIndonesiaProgramContext();
        container.classList.toggle('indonesia-compliance-visible', show);
        document.body.classList.toggle('indonesia-compliance-visible', show);
        if (!show) {
            document.querySelectorAll(
                '#workerFormContainer .indonesia-compliance-field, ' +
                '#documentsModal .indonesia-compliance-field, ' +
                '.indonesia-compliance-card, ' +
                'option[value="contract_signed"], ' +
                'option[value="insurance"], ' +
                'option[value="exit_permit"], ' +
                'option[value="training_certificate"]'
            ).forEach(el => el.remove());
        }
    }

    function applyCountrySpecificWorkerLabels() {
        const profile = getCountryProfile();
        const labelsByProfile = {
            indonesia: {
                government: 'Government Approval',
                workPermit: 'Exit Permit',
                contract: 'Signed Contract',
                travel: 'Travel Readiness'
            },
            bangladesh: {
                government: 'BMET Registration',
                workPermit: 'Work Permit',
                contract: 'Overseas Contract',
                travel: 'Travel Clearance'
            },
            sri_lanka: {
                government: 'SLBFE Registration',
                workPermit: 'Work Permit',
                contract: 'Employment Contract',
                travel: 'Departure Clearance'
            },
            kenya: {
                government: 'NITA Registration',
                workPermit: 'Work Permit',
                contract: 'Employment Contract',
                travel: 'Travel Clearance'
            },
            default: {
                government: 'Government Registration',
                workPermit: 'Work Permit',
                contract: 'Contract',
                travel: 'Travel & Departure'
            }
        };
        const labelsOverride = window.RATIB_COUNTRY_PROFILE_CONFIG && window.RATIB_COUNTRY_PROFILE_CONFIG.labels
            ? window.RATIB_COUNTRY_PROFILE_CONFIG.labels
            : null;
        const labels = labelsOverride || labelsByProfile[profile] || labelsByProfile.default;
        const labelTargets = [
            ['.doc-row.country-compliance[data-workflow-stage="government"] .form-label', labels.government],
            ['.doc-row.country-compliance[data-workflow-stage="work_permit"] .form-label', labels.workPermit],
            ['.doc-row.contract-compliance[data-workflow-stage="contract"] .form-label', labels.contract],
            ['.doc-row.contract-compliance[data-workflow-stage="travel"] .form-label', labels.travel]
        ];
        labelTargets.forEach(function (entry) {
            document.querySelectorAll(entry[0]).forEach(function (el) {
                el.textContent = entry[1];
            });
        });
    }

    function getCountrySpecificRequirements(profile) {
        if (window.RATIB_COUNTRY_PROFILE_CONFIG
            && Array.isArray(window.RATIB_COUNTRY_PROFILE_CONFIG.requirements)
            && window.RATIB_COUNTRY_PROFILE_CONFIG.requirements.length > 0) {
            return window.RATIB_COUNTRY_PROFILE_CONFIG.requirements.map(function (x) { return String(x || '').trim(); }).filter(Boolean);
        }
        const common = [
            'full_name',
            'gender',
            'agent_id',
            'identity_number',
            'passport_number',
            'police_number',
            'medical_number',
            'visa_number',
            'ticket_number'
        ];
        const byCountry = {
            indonesia: [
                'training_certificate_number',
                'contract_signed_number',
                'insurance_number',
                'exit_permit_number',
                'approval_reference_id'
            ],
            bangladesh: [
                'government_registration_number',
                'work_permit_number',
                'insurance_policy_number',
                'salary',
                'contract_duration',
                'flight_ticket_number',
                'predeparture_training_completed',
                'contract_verified'
            ],
            sri_lanka: [
                'government_registration_number',
                'work_permit_number',
                'insurance_policy_number',
                'salary',
                'contract_duration',
                'flight_ticket_number',
                'predeparture_training_completed',
                'contract_verified'
            ],
            kenya: [
                'government_registration_number',
                'work_permit_number',
                'insurance_policy_number',
                'salary',
                'contract_duration',
                'flight_ticket_number',
                'predeparture_training_completed',
                'contract_verified'
            ],
            default: [
                'government_registration_number',
                'work_permit_number'
            ]
        };
        return common.concat(byCountry[profile] || byCountry.default);
    }

    function applyCountrySpecificRequirements() {
        const profile = getCountryProfile();
        const requiredNames = getCountrySpecificRequirements(profile);
        const allCandidates = document.querySelectorAll('#workerForm [name]');
        allCandidates.forEach(function (el) {
            const name = String(el.getAttribute('name') || '').trim();
            if (!name) return;
            // Preserve explicit required in markup for core fields.
            if (el.hasAttribute('data-base-required')) return;
            if (el.hasAttribute('required')) {
                el.setAttribute('data-base-required', '1');
            }
        });
        allCandidates.forEach(function (el) {
            const name = String(el.getAttribute('name') || '').trim();
            if (!name) return;
            const isBaseRequired = el.getAttribute('data-base-required') === '1';
            const shouldRequire = requiredNames.includes(name) || isBaseRequired;
            if (shouldRequire) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
    }

    function mountCountryRequirementsPanel() {
        const form = document.getElementById('workerForm');
        if (!form) return;
        if (form.querySelector('#countryRequirementsPanel')) return;
        const panel = document.createElement('div');
        panel.id = 'countryRequirementsPanel';
        panel.className = 'alert alert-info mb-3';
        panel.innerHTML = '<strong>Country Requirements</strong><div id="countryRequirementsList" class="small mt-2"></div>';
        const content = form.querySelector('.form-content');
        if (content) {
            form.insertBefore(panel, content);
        } else {
            form.prepend(panel);
        }
    }

    function updateCountryRequirementsPanelLive() {
        const form = document.getElementById('workerForm');
        if (!form) return;
        const listEl = document.getElementById('countryRequirementsList');
        if (!listEl) return;
        const profile = getCountryProfile();
        const req = getCountrySpecificRequirements(profile);
        const missing = [];
        req.forEach(function (name) {
            const el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            const val = (el.value || '').toString().trim();
            if (!val) missing.push(name);
        });
        const doneCount = Math.max(0, req.length - missing.length);
        listEl.innerHTML =
            '<div><span class="badge bg-primary">Profile: ' + profile + '</span> ' +
            '<span class="badge bg-success ms-1">Done: ' + doneCount + '/' + req.length + '</span> ' +
            '<span class="badge bg-danger ms-1">Missing: ' + missing.length + '</span></div>' +
            (missing.length ? ('<div class="mt-1">Missing fields: ' + missing.join(', ') + '</div>') : '<div class="mt-1 text-success">All required country fields are completed.</div>');
    }

    window.updateIndonesiaComplianceVisibility = updateIndonesiaComplianceVisibility;

    function parseStageCompletedMap(rawValue) {
        if (!rawValue) return {};
        if (typeof rawValue === 'object') return rawValue;
        try {
            const parsed = JSON.parse(rawValue);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_e) {
            return {};
        }
    }

    function setRowReadonly(row, readonly) {
        const controls = row.querySelectorAll('input, select, textarea, button.upload-btn');
        controls.forEach(control => {
            if (control.classList.contains('upload-btn')) {
                control.disabled = readonly;
                return;
            }
            if (control.type === 'hidden') return;
            if (readonly) {
                control.setAttribute('disabled', 'disabled');
                control.setAttribute('readonly', 'readonly');
            } else {
                control.removeAttribute('disabled');
                control.removeAttribute('readonly');
            }
        });
    }

    function syncStageDisplayLabels() {
        const rows = document.querySelectorAll('#workerForm [data-workflow-stage][data-stage-label]');
        rows.forEach(row => {
            const displayLabel = String(row.getAttribute('data-stage-label') || '').trim();
            if (!displayLabel) return;
            const labelEl = row.querySelector('.doc-group .form-label');
            if (labelEl) {
                labelEl.textContent = displayLabel;
            }
        });
    }

    function applyWorkflowStageUI() {
        const form = document.getElementById('workerForm');
        if (!form) return;
        syncStageDisplayLabels();
        const workflowStages = getWorkflowStages();

        const currentStageInput = form.querySelector('[name="current_stage"]');
        const stageCompletedInput = form.querySelector('[name="stage_completed"]');
        const currentStage = (currentStageInput?.value || workflowStages[0]).trim();
        const stageCompleted = parseStageCompletedMap(stageCompletedInput?.value || '{}');
        const currentIndex = Math.max(0, workflowStages.indexOf(currentStage));

        const rows = form.querySelectorAll('[data-workflow-stage]');
        rows.forEach(row => {
            const stageKey = row.getAttribute('data-workflow-stage') || '';
            const stageIndex = workflowStages.indexOf(stageKey);
            if (stageIndex < 0) return;

            const isCompleted = Boolean(stageCompleted[stageKey]);
            const isCurrent = stageIndex === currentIndex;
            const isFuture = stageIndex > currentIndex && !isCompleted;

            row.style.display = isFuture ? 'none' : '';
            row.classList.toggle('workflow-stage-readonly', isCompleted && !isCurrent);
            setRowReadonly(row, isCompleted && !isCurrent);
        });

        const completedCount = workflowStages.filter(stage => Boolean(stageCompleted[stage])).length;
        const progress = Math.round((completedCount / Math.max(1, workflowStages.length)) * 100);
        const fill = document.getElementById('workflowProgressFill');
        const text = document.getElementById('workflowProgressText');
        if (fill) fill.style.width = `${progress}%`;
        if (text) text.textContent = `${progress}%`;
    }

    applyCountrySpecificWorkerLabels();
    applyCountrySpecificRequirements();
    mountCountryRequirementsPanel();
    updateCountryRequirementsPanelLive();

    // Function to setup custom job title dropdown
    function setupCustomJobTitleDropdown() {
        const jobTitleSelect = document.getElementById('job_title');
        const dropdownContainer = document.getElementById('job_title_dropdown');
        const tagsContainer = document.getElementById('selectedJobTitleTags');
        
        if (!jobTitleSelect || !dropdownContainer || !tagsContainer) {
            return;
        }
        
        const trigger = dropdownContainer.querySelector('.custom-dropdown-trigger');
        const menu = dropdownContainer.querySelector('.custom-dropdown-menu');
        const placeholder = trigger.querySelector('.dropdown-placeholder');
        let checkboxes = menu.querySelectorAll('input[type="checkbox"]');
        
        // Function to sync checkboxes with select
        const syncSelectToCheckboxes = () => {
            const selectedValues = Array.from(jobTitleSelect.selectedOptions).map(opt => opt.value);
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectedValues.includes(checkbox.value);
            });
        };
        
        // Function to sync checkboxes to select
        const syncCheckboxesToSelect = () => {
            const currentSelected = Array.from(jobTitleSelect.selectedOptions).map(opt => opt.value);
            const shouldBeSelected = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);
            
            const needsUpdate = currentSelected.length !== shouldBeSelected.length ||
                              !currentSelected.every(val => shouldBeSelected.includes(val));
            
            if (needsUpdate) {
                Array.from(jobTitleSelect.options).forEach(opt => opt.selected = false);
                
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const option = Array.from(jobTitleSelect.options).find(opt => opt.value === checkbox.value);
                        if (option) {
                            option.selected = true;
                        }
                    }
                });
                
                jobTitleSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        
        // Function to update tags display
        const updateTagsDisplay = () => {
            const selectedOptions = Array.from(jobTitleSelect.selectedOptions);
            
            tagsContainer.innerHTML = '';
            
            if (selectedOptions.length === 0) {
                placeholder.textContent = 'Select job titles and skills...';
                placeholder.classList.add('placeholder-default');
                placeholder.classList.remove('placeholder-selected');
            } else {
                placeholder.textContent = `${selectedOptions.length} item(s) selected`;
                placeholder.classList.add('placeholder-selected');
                placeholder.classList.remove('placeholder-default');
            }
            
            selectedOptions.forEach(option => {
                const tag = document.createElement('span');
                tag.className = 'selected-tag';
                tag.innerHTML = `
                    ${option.textContent}
                    <span class="tag-remove" data-value="${option.value}">×</span>
                `;
                
                tag.querySelector('.tag-remove').addEventListener('click', (e) => {
                    e.stopPropagation();
                    const checkbox = Array.from(checkboxes).find(cb => cb.value === option.value);
                    if (checkbox) {
                        checkbox.checked = false;
                        syncCheckboxesToSelect();
                    }
                });
                
                tagsContainer.appendChild(tag);
            });
        };
        
        // Always sync checkboxes and tags, even if already initialized
        const currentCheckboxes = menu.querySelectorAll('input[type="checkbox"]');
        if (currentCheckboxes.length > 0) {
            const selectedValues = Array.from(jobTitleSelect.selectedOptions).map(opt => opt.value);
            currentCheckboxes.forEach(checkbox => {
                checkbox.checked = selectedValues.includes(checkbox.value);
            });
        }
        updateTagsDisplay();
        
        // If already initialized, just return (event listeners are already set up)
        if (dropdownContainer.dataset.initialized === 'true') {
            return;
        }
        
        dropdownContainer.dataset.initialized = 'true';
        
        // Toggle dropdown
        if (!trigger.dataset.listenerAdded) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const isOpen = menu.classList.contains('show');
                if (isOpen) {
                    menu.classList.remove('show');
                    trigger.classList.remove('active');
                } else {
                    menu.classList.add('show');
                    trigger.classList.add('active');
                }
            });
            trigger.dataset.listenerAdded = 'true';
        }
        
        // Close dropdown when clicking outside
        const closeDropdownHandler = (e) => {
            if (!dropdownContainer.contains(e.target)) {
                menu.classList.remove('show');
                trigger.classList.remove('active');
            }
        };
        if (window._jobTitleDropdownCloseHandler) {
            document.removeEventListener('click', window._jobTitleDropdownCloseHandler);
        }
        window._jobTitleDropdownCloseHandler = closeDropdownHandler;
        document.addEventListener('click', closeDropdownHandler);
        
        // Handle checkbox changes
        checkboxes.forEach(checkbox => {
            if (!checkbox.dataset.listenerAdded) {
                checkbox.addEventListener('change', () => {
                    syncCheckboxesToSelect();
                });
                checkbox.dataset.listenerAdded = 'true';
            }
            
            const label = checkbox.closest('.custom-option');
            if (label && !label.dataset.listenerAdded) {
                label.addEventListener('click', (e) => {
                    if (e.target !== checkbox) {
                        e.preventDefault();
                        e.stopPropagation();
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                label.dataset.listenerAdded = 'true';
            }
        });
        
        // Update tags when select changes
        let isSyncing = false;
        const selectChangeHandler = () => {
            if (isSyncing) return;
            isSyncing = true;
            syncSelectToCheckboxes();
            updateTagsDisplay();
            setTimeout(() => { isSyncing = false; }, 100);
        };
        
        if (jobTitleSelect._customDropdownChangeHandler) {
            jobTitleSelect.removeEventListener('change', jobTitleSelect._customDropdownChangeHandler);
        }
        jobTitleSelect._customDropdownChangeHandler = selectChangeHandler;
        jobTitleSelect.addEventListener('change', selectChangeHandler);
        
        // Initial sync
        syncSelectToCheckboxes();
        updateTagsDisplay();
    }
    
    // Make function globally available
    window.setupCustomJobTitleDropdown = setupCustomJobTitleDropdown;
    
    // Setup custom dropdown
    setupCustomJobTitleDropdown();
    
    // Sidebar navigation functionality
    const sidebarNavItems = document.querySelectorAll('.sidebar-nav-item');
    const sections = document.querySelectorAll('.section');
    
    sidebarNavItems.forEach(item => {
        item.addEventListener('click', function() {
            const targetSection = this.getAttribute('data-section');
            
            // Remove active class from all items
            sidebarNavItems.forEach(nav => nav.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
            
            // Scroll to section (smooth scroll within the form content)
            const targetElement = document.querySelector(`.section.${targetSection}, .lifecycle-block.${targetSection}`);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // Get form elements
    const workerFormContainer = document.getElementById('workerFormContainer');
    const closeBtn = document.querySelector('.close-btn');
    const cancelBtn = document.querySelector('.btn-cancel');
    const saveBtn = document.querySelector('.btn-save');
    const workerForm = document.getElementById('workerForm');
    const formTitle = document.querySelector('#workerFormContainer h2');
    
    // Function to ensure mobile scrolling works - shared by both add and edit forms
    function ensureMobileScrolling(container) {
        if (!container) container = workerFormContainer;
        if (!container) return;
        
        if (window.innerWidth <= 768 && container.classList.contains('show')) {
            const formWrapper = container.querySelector('.form-wrapper');
            const formContent = container.querySelector('.form-content');
            if (formWrapper && formContent) {
                // Critical: Set min-height: 0 for flex scrolling to work
                formWrapper.style.setProperty('min-height', '0', 'important');
                formWrapper.style.setProperty('display', 'flex', 'important');
                formWrapper.style.setProperty('flex-direction', 'column', 'important');
                // Ensure form-wrapper is scrollable
                formWrapper.style.setProperty('overflow-y', 'scroll', 'important');
                formWrapper.style.setProperty('overflow-x', 'hidden', 'important');
                formWrapper.style.setProperty('height', '100vh', 'important');
                formWrapper.style.setProperty('max-height', '100vh', 'important');
                formWrapper.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                // Ensure form-content can grow and scroll
                formContent.style.setProperty('flex', '1 1 auto', 'important');
                formContent.style.setProperty('min-height', '0', 'important');
                formContent.style.setProperty('min-height', 'calc(100vh + 800px)', 'important'); // Increased to ensure Documents section is visible
                formContent.style.setProperty('padding-bottom', '600px', 'important'); // Increased padding to ensure all fields are accessible
                formContent.style.setProperty('overflow-y', 'visible', 'important');
                formContent.style.setProperty('overflow-x', 'hidden', 'important');
                
                // Ensure Documents section is fully visible
                const documentsSection = container.querySelector('.section.documents');
                if (documentsSection) {
                    documentsSection.style.setProperty('min-height', 'auto', 'important');
                    documentsSection.style.setProperty('max-height', 'none', 'important');
                    documentsSection.style.setProperty('overflow', 'visible', 'important');
                    documentsSection.style.setProperty('padding-bottom', '20px', 'important');
                    documentsSection.style.setProperty('margin-bottom', '40px', 'important');
                }
                // Ensure container doesn't prevent scrolling
                container.style.setProperty('overflow-y', 'visible', 'important');
                container.style.setProperty('overflow-x', 'hidden', 'important');
            }
        }
    }
    
    // Call on load and resize
    window.addEventListener('resize', () => ensureMobileScrolling());
    // Make function globally available
    window.ensureMobileScrolling = ensureMobileScrolling;
    
    // Track if user has actually interacted with the form
    // Start as false and only set to true on REAL user input
    let userHasInteracted = false;
    
    // Add a delay before allowing alert checks (give form time to fully load)
    let formReadyForAlertCheck = false;
    setTimeout(() => {
        formReadyForAlertCheck = true;
    }, 2000); // Wait 2 seconds after page load before allowing alert checks
    
    // Listen for user interactions on the form - ONLY for actual user input (not programmatic)
    if (workerForm) {
        // Track if this is a user-initiated event
        let isUserEvent = false;
        
        workerForm.addEventListener('input', function(e) {
            // Only count as interaction if it's a real user event (not programmatic)
            if (e.isTrusted === true) {
                userHasInteracted = true;
                // Store original values on first interaction
                if (!workerForm.dataset.originalValues) {
                    const originalValues = {};
                    const inputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
                    inputs.forEach(input => {
                        const key = input.name || input.id;
                        if (key) {
                            originalValues[key] = input.value || '';
                        }
                    });
                    workerForm.dataset.originalValues = JSON.stringify(originalValues);
                }
            }
        }, true); // Use capture phase
        
        workerForm.addEventListener('change', function(e) {
            // Only count as interaction if it's a real user event (not programmatic)
            if (e.isTrusted === true) {
                userHasInteracted = true;
                // Store original values on first interaction
                if (!workerForm.dataset.originalValues) {
                    const originalValues = {};
                    const inputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
                    inputs.forEach(input => {
                        const key = input.name || input.id;
                        if (key) {
                            originalValues[key] = input.value || '';
                        }
                    });
                    workerForm.dataset.originalValues = JSON.stringify(originalValues);
                }
            }
        }, true); // Use capture phase
        
        // Also listen for keydown to catch typing
        workerForm.addEventListener('keydown', function(e) {
            if (e.isTrusted === true && !e.ctrlKey && !e.metaKey && !e.altKey) {
                // User is typing (not shortcuts)
                if (e.key.length === 1 || ['Backspace', 'Delete', 'Enter'].includes(e.key)) {
                    userHasInteracted = true;
                }
            }
        }, true);

        // Keep country requirement panel live with any value changes.
        workerForm.addEventListener('input', function () {
            updateCountryRequirementsPanelLive();
        });
        workerForm.addEventListener('change', function () {
            updateCountryRequirementsPanelLive();
        });
    }
    
    // Function to actually close the form (called after confirmation)
    function performClose() {
        let partnerAgenciesReturnUrl = null;
        let returnPartnerAgencyId = null;
        try {
            const u = new URL(window.location.href);
            const retPid = u.searchParams.get('return_partner_agency');
            if (retPid && /^\d+$/.test(retPid) && parseInt(retPid, 10) > 0) {
                returnPartnerAgencyId = parseInt(retPid, 10);
                const retQs = new URLSearchParams();
                ['control', 'agency_id'].forEach((k) => {
                    if (u.searchParams.has(k)) {
                        retQs.set(k, u.searchParams.get(k));
                    }
                });
                retQs.set('open_sent_workers', String(returnPartnerAgencyId));
                partnerAgenciesReturnUrl = `partner-agencies.php?${retQs.toString()}`;
            }
        } catch (e) {
            debugForm.warn('performClose: could not build partner-agencies return URL', e);
        }

        if (workerFormContainer) {
            workerFormContainer.classList.remove('show');
            // DO NOT reset form here - it was causing status to revert
            // Form will be reset only when opening for new worker
            // Reset form title to default
            if (formTitle) {
                formTitle.textContent = 'Add New Worker';
            }
            // Clear hidden ID field
            const idField = workerForm.querySelector('input[name="id"]');
            if (idField) {
                idField.value = '';
            }
            // Reset user interaction flag
            userHasInteracted = false;
        }

        if (partnerAgenciesReturnUrl && returnPartnerAgencyId) {
            let useHistoryBack = false;
            try {
                if (document.referrer && window.history.length > 1) {
                    const ref = new URL(document.referrer);
                    if (ref.origin === window.location.origin && ref.pathname.indexOf('partner-agencies.php') !== -1) {
                        useHistoryBack = true;
                    }
                }
            } catch (e) {
                useHistoryBack = false;
            }
            if (useHistoryBack) {
                try {
                    sessionStorage.setItem(
                        'ratib_reopen_workers_sent',
                        JSON.stringify({ partnerId: returnPartnerAgencyId, t: Date.now() })
                    );
                } catch (e) {
                    /* ignore */
                }
                window.history.back();
                return;
            }
            window.location.assign(partnerAgenciesReturnUrl);
            return;
        }

        // Deep-link (?view= / ?edit=) hides the main table with .print-hidden — restore it or the page looks blank.
        const tableContainerEl = document.querySelector('.table-container');
        if (tableContainerEl) {
            tableContainerEl.classList.remove('print-hidden');
        }
        const workerTableShell = document.querySelector('.worker-table-container');
        if (workerTableShell) {
            workerTableShell.classList.remove('print-hidden');
        }
        const cw = document.querySelector('.content-wrapper');
        if (cw) {
            const ph = cw.querySelector('.page-header');
            if (ph) {
                ph.classList.remove('print-hidden');
            }
        }
        if (workerFormContainer) {
            workerFormContainer.classList.remove('view-mode');
        }
        if (workerForm) {
            workerForm.dataset.viewMode = 'false';
        }
        if (typeof setFormViewMode === 'function') {
            setFormViewMode(false);
        }

        try {
            const u = new URL(window.location.href);
            if (u.searchParams.has('view') || u.searchParams.has('edit') || u.searchParams.has('return_partner_agency')) {
                u.searchParams.delete('view');
                u.searchParams.delete('edit');
                u.searchParams.delete('return_partner_agency');
                window.history.replaceState({}, '', u.pathname + u.search + u.hash);
            }
        } catch (e) {
            debugForm.warn('performClose: could not strip view/edit from URL', e);
        }
    }
    
    // Function to close form - uses modern alert system
    async function closeForm() {
        debugForm.log('[Worker Form] closeForm called');
        // Check if form is closing after successful save - skip alert
        if (window.workerFormClosingAfterSave) {
            debugForm.log('Form closing after save, skipping confirmation');
            window.workerFormClosingAfterSave = false; // Reset flag
            performClose();
            return;
        }
        
        // Check if form has unsaved changes
        if (hasUnsavedChanges() && userHasInteracted) {
            const confirmed = await ModernFormAlert.show(
                'Unsaved Changes',
                'You have unsaved changes. Are you sure you want to close the form?',
                'warning',
                { confirmText: 'Close', cancelText: 'Cancel' }
            );
            if (confirmed) {
                performClose();
            } else {
                debugForm.log('[Worker Form] User cancelled close - keeping form open');
            }
        } else {
            // No changes or no interaction - close without alert
            performClose();
        }
    }
    
    // Make closeForm available globally so modal-handlers.js can use it
    window.closeWorkerForm = closeForm;
    
    // Attach button handlers - use event delegation for reliability
    const attachCloseHandlers = () => {
        const closeBtn = document.querySelector('#workerFormContainer .close-btn');
        const cancelBtn = document.querySelector('#workerFormContainer .btn-cancel');
        
        if (closeBtn && !closeBtn.dataset.handlerAttached) {
            closeBtn.dataset.handlerAttached = 'true';
            closeBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                debugForm.log('[Worker Form] Close button clicked');
                await closeForm();
            });
        }
        
        if (cancelBtn && !cancelBtn.dataset.handlerAttached) {
            // Make sure it's not from delete modal or documents modal
            const deleteModal = document.getElementById('deleteConfirmModal');
            const documentsModal = document.getElementById('documentsModal');
            if ((!deleteModal || !deleteModal.contains(cancelBtn)) && 
                (!documentsModal || !documentsModal.contains(cancelBtn))) {
                cancelBtn.dataset.handlerAttached = 'true';
                cancelBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    debugForm.log('[Worker Form] Cancel button clicked');
                    await closeForm();
                });
            }
        }
    };
    
    // Attach handlers immediately
    attachCloseHandlers();
    
    // Also attach when form container changes (buttons might be recreated)
    if (workerFormContainer) {
        const observer = new MutationObserver(() => {
            attachCloseHandlers();
        });
        observer.observe(workerFormContainer, { childList: true, subtree: true });
    }
    
    // Add click-outside detection for form container
    if (workerFormContainer) {
        workerFormContainer.addEventListener('click', async function(e) {
            // Only trigger if clicking directly on the overlay/container background
            if (e.target === workerFormContainer) {
                e.preventDefault();
                e.stopPropagation();
                debugForm.log('[Worker Form] Clicked outside form');
                await closeForm();
            }
        });
    }
    
    if (workerForm) {
        const convertToEnglish = (el) => {
            if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.name !== 'csrf_token' && window.toEnglishString) {
                const converted = window.toEnglishString(el.value);
                if (converted !== el.value) el.value = converted;
            }
        };
        workerForm.addEventListener('input', function(e) {
            convertToEnglish(e.target);
        });
        workerForm.addEventListener('paste', function(e) {
            setTimeout(() => convertToEnglish(e.target), 0);
        });
    }
    
    // Form submission handler to prevent double submission
    if (workerForm) {
        workerForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            debugForm.log('Form submit event prevented - using button click handler instead');
        });
    }

    // Save button functionality
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (validateForm()) {
                if (window.saveWorker) {
                    window.saveWorker(e);
                }
            }
        });
    }
    
    // Function to check if form has unsaved changes
    function hasUnsavedChanges() {
        if (!workerForm) return false;
        
        // CRITICAL: If user hasn't interacted, NO changes
        if (!userHasInteracted) {
            return false;
        }
        
        // If original values haven't been stored yet, don't consider it as having changes
        if (!workerForm.dataset.originalValues) {
            return false; // No changes detected yet - form still initializing
        }
        
        // Compare current values with original
        try {
            const originalValues = JSON.parse(workerForm.dataset.originalValues);
            const inputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
            for (const input of inputs) {
                const key = input.name || input.id;
                if (!key) continue; // Skip if no name or id
                const currentValue = input.value || '';
                const originalValue = originalValues[key] || '';
                if (currentValue !== originalValue) {
                    return true; // Found a change
                }
            }
        } catch (e) {
            // If parsing fails, no changes
            return false;
        }
        
        return false;
    }
    
    // Function to set form title
    function setFormTitle(title) {
        if (formTitle) {
            formTitle.textContent = title;
        }
    }
    
    // Function to open form for adding new worker
    async function openAddWorkerForm() {
        setFormTitle('Add New Worker');
        
        // Reset user interaction flag and alert check readiness
        userHasInteracted = false;
        formReadyForAlertCheck = false;
        
        // Record when form was opened
        if (workerForm) {
            workerForm.dataset.openedTime = Date.now().toString();
        }
        
        // Wait 2 seconds before allowing alert checks (give form time to fully load)
        setTimeout(() => {
            formReadyForAlertCheck = true;
        }, 2000);
        
        // Reset form completely
        if (workerForm) {
            workerForm.reset();
            // Clear hidden ID field
            const idField = workerForm.querySelector('input[name="id"]');
            if (idField) {
                idField.value = '';
            }
            // Clear original values
            delete workerForm.dataset.originalValues;
        }
        
        if (workerFormContainer) {
            workerFormContainer.classList.remove('force-hidden');
            workerFormContainer.classList.add('show');
            
            // Mobile: Ensure scrolling works - use setTimeout to ensure DOM is ready
            setTimeout(() => {
                ensureMobileScrolling(workerFormContainer);
            }, 100);
            
            if (typeof window.initializeEnglishDatePickers === 'function') {
                setTimeout(function() { window.initializeEnglishDatePickers(workerFormContainer); }, 50);
            }
        }
        
        // Load agents, subagents, and countries for dropdowns
        await loadAgentsAndSubagents();
        
        // Re-setup event listeners after form is shown (in case elements were recreated)
        setupAgentChangeListener();
        setupCountryChangeListener();
        
        // Reset status indicators to pending for new worker
        resetStatusIndicators();
        updateIndonesiaComplianceVisibility();
        applyWorkflowStageUI();
        const currentStageField = workerForm?.querySelector('[name="current_stage"]');
        const stageCompletedField = workerForm?.querySelector('[name="stage_completed"]');
        if (currentStageField) currentStageField.value = getWorkflowStages()[0];
        if (stageCompletedField) stageCompletedField.value = '{}';
        applyWorkflowStageUI();
    }
    
    // Function to reset status indicators to pending
    function resetStatusIndicators() {
        const docTypes = ['identity', 'passport', 'training_certificate', 'contract_signed', 'insurance', 'police', 'medical', 'visa', 'exit_permit', 'ticket', 'country_compliance_primary', 'country_compliance_secondary', 'contract_deployment_primary', 'contract_deployment_secondary', 'contract_deployment_verification'];
        
        docTypes.forEach(docType => {
            const statusWrapper = document.querySelector(`.status-wrapper[data-doc-type="${docType}"]`);
            if (!statusWrapper) return;
            
            const indicator = statusWrapper.querySelector('.status-indicator');
            const text = statusWrapper.querySelector('.status-text');
            const statusInput = document.querySelector(`input[name="${docType}_status"]`);
            
            if (indicator) {
                indicator.classList.remove('status-pending', 'status-ok', 'status-not_ok');
                indicator.classList.add('status-pending');
            }
            
            if (text) {
                text.classList.remove('status-pending', 'status-ok', 'status-not_ok');
                text.classList.add('status-pending');
                text.textContent = 'pending';
            }
            
            if (statusInput) {
                statusInput.value = 'pending';
            }
        });
    }
    
    // Function to open form for editing worker
    async function openEditWorkerForm(workerId, viewMode = false) {
        setFormTitle(viewMode ? 'View Worker' : 'Edit Worker');
        
        // Reset user interaction flag and alert check readiness
        userHasInteracted = false;
        formReadyForAlertCheck = false;
        
        // Record when form was opened
        if (workerForm) {
            workerForm.dataset.openedTime = Date.now().toString();
            workerForm.dataset.viewMode = viewMode ? 'true' : 'false';
        }
        
        // Allow alert checks after form data is in (was 2000ms; shorter feels much snappier)
        setTimeout(() => {
            formReadyForAlertCheck = true;
        }, viewMode ? 200 : 550);
        
        if (workerFormContainer) {
            workerFormContainer.classList.remove('force-hidden');
            workerFormContainer.classList.add('show');
            
            requestAnimationFrame(() => {
                ensureMobileScrolling(workerFormContainer);
            });
            
            if (typeof window.initializeEnglishDatePickers === 'function') {
                requestAnimationFrame(() => {
                    window.initializeEnglishDatePickers(workerFormContainer);
                });
            }
            
            if (viewMode) {
                workerFormContainer.classList.add('view-mode');
                // Set view mode immediately before data loads to prevent flash
                setFormViewMode(true);
            } else {
                workerFormContainer.classList.remove('view-mode');
            }
        }
        
        // Clear form first to prevent showing previous worker's data
        if (workerForm) {
            // Store current worker ID to verify later
            workerForm.dataset.currentWorkerId = workerId;
            
            // Reset the form completely
            workerForm.reset();
            
            // Clear hidden ID field
            const idField = workerForm.querySelector('input[name="id"]');
            if (idField) {
                idField.value = '';
            }
            
            // Manually clear ALL input fields (text, email, tel, date, number, textarea)
            const allInputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input.date-input, input[type="number"], input[type="hidden"], textarea');
            allInputs.forEach(input => {
                if (input.name !== 'csrf_token') { // Keep CSRF token
                    if (input._flatpickr) {
                        input._flatpickr.clear();
                    } else {
                        input.value = '';
                    }
                }
            });
            
            // Clear all select dropdowns
            const allSelects = workerForm.querySelectorAll('select');
            allSelects.forEach(select => {
                select.selectedIndex = 0; // Reset to first option
                select.value = '';
            });
            
            
            debugForm.log('✅ Form cleared for worker ID:', workerId);
        }
        
        const loadWorkerJson = async () => {
            const timestamp = new Date().getTime();
            const randomId = Math.random().toString(36).substring(7);
            const basePath = window.WORKERS_API || '/ratibprogram/api/workers';
            const apiUrl = `${basePath}/core/get.php?id=${workerId}&t=${timestamp}&r=${randomId}&workerId=${workerId}`;
            debugForm.log('Fetching worker data from:', apiUrl);
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                cache: 'no-store'
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        };

        let data;
        try {
            if (viewMode) {
                data = await loadWorkerJson();
            } else {
                [, data] = await Promise.all([loadAgentsAndSubagents(), loadWorkerJson()]);
            }
        } catch (error) {
            debugForm.error('Error loading worker data:', error);
            if (typeof window.SimpleAlert !== 'undefined' && window.SimpleAlert.show) {
                window.SimpleAlert.show('Error', 'Error loading worker data: ' + (error.message || 'Please try again.'), 'danger', { notification: true });
            } else {
                debugForm.error('Error loading worker data: ' + (error.message || 'Please try again.'));
            }
            return;
        }

        setupAgentChangeListener();
        setupCountryChangeListener();

        try {
            debugForm.log('API Response:', data);
            
            // Support multiple API response structures
            let worker = null;
            if (data.success && data.data?.workers?.length > 0) {
                worker = data.data.workers[0];
            } else if (data.success && data.data?.worker) {
                worker = data.data.worker;
            } else if (data.success && data.worker) {
                worker = data.worker;
            }
            
            if (worker) {
                
                // CRITICAL: Verify we got the correct worker
                const requestedId = String(workerId);
                const receivedId = String(worker.id);
                
                debugForm.log('=== WORKER DATA VERIFICATION ===');
                debugForm.log('Requested Worker ID:', requestedId);
                debugForm.log('Received Worker ID:', receivedId);
                debugForm.log('Worker full_name:', worker.worker_name || worker.full_name);
                debugForm.log('Worker agent_id:', worker.agent_id);
                debugForm.log('Full worker object:', worker);
                
                if (receivedId !== requestedId) {
                    debugForm.error('⚠️ CRITICAL: Worker ID mismatch!');
                    debugForm.error('Requested:', requestedId, 'Received:', receivedId);
                    // Don't populate if wrong worker
                    return;
                }
                
                // Double-check form is for this worker
                if (workerForm && workerForm.dataset.currentWorkerId !== requestedId) {
                    debugForm.warn('⚠️ Form worker ID mismatch, clearing and retrying...');
                    workerForm.dataset.currentWorkerId = requestedId;
                }
                
                // Populate form - agents/subagents are already loaded
                await populateEditForm(worker, viewMode);
                
                // Set form to view mode if needed (disable all fields)
                // Note: view mode is already set above for immediate effect
                if (viewMode) {
                    // Re-apply view mode after data is populated to ensure all fields are disabled
                    setFormViewMode(true);
                } else {
                    setFormViewMode(false);
                }
                
                // Store original values after form is populated (but only after user interacts)
                // We'll store them when user first interacts, not on load
                
            } else {
                debugForm.error('Failed to load worker data');
                if (typeof window.SimpleAlert !== 'undefined' && window.SimpleAlert.show) {
                    window.SimpleAlert.show('Error', 'Failed to load worker data. Please try again.', 'danger', { notification: true });
                } else {
                    debugForm.error('Failed to load worker data. Please try again.');
                }
            }
        } catch (error) {
            debugForm.error('Error loading worker data:', error);
            if (typeof window.SimpleAlert !== 'undefined' && window.SimpleAlert.show) {
                window.SimpleAlert.show('Error', 'Error loading worker data: ' + (error.message || 'Please try again.'), 'danger', { notification: true });
            } else {
                debugForm.error('Error loading worker data: ' + (error.message || 'Please try again.'));
            }
        }
    }
    
    // Function to set form to view mode (disable all fields) or edit mode (enable all fields)
    function setFormViewMode(viewMode) {
        if (!workerForm) return;
        
        const allInputs = workerForm.querySelectorAll('input, select, textarea, button[type="submit"]');
        const saveButton = document.querySelector('.btn-save');
        const fileInputs = workerForm.querySelectorAll('input[type="file"]');
        const statusWrappers = workerForm.querySelectorAll('.status-wrapper');
        
        allInputs.forEach(input => {
            // Skip CSRF token and hidden ID field
            if (input.name === 'csrf_token' || (input.type === 'hidden' && input.name === 'id')) {
                return;
            }
            
            if (viewMode) {
                input.disabled = true;
                input.readOnly = true;
            } else {
                input.disabled = false;
                input.readOnly = false;
            }
        });
        
        // Disable file inputs and upload buttons
        fileInputs.forEach(input => {
            if (viewMode) {
                input.disabled = true;
            } else {
                input.disabled = false;
            }
        });
        
        // Handle status wrappers - disable clicks in view mode
        statusWrappers.forEach(wrapper => {
            if (viewMode) {
                wrapper.classList.add('view-mode-disabled');
            } else {
                wrapper.classList.remove('view-mode-disabled');
            }
        });
        
        // Hide save button in view mode and add print/export buttons
        const formActions = workerForm.querySelector('.form-actions');
        if (formActions) {
            if (viewMode) {
                // Hide save button
                if (saveButton) {
                    saveButton.classList.add('hidden');
                    saveButton.disabled = true;
                }
                
                // Check if print/export buttons already exist
                let printBtn = formActions.querySelector('.btn-print-worker');
                let exportBtn = formActions.querySelector('.btn-export-worker');
                
                if (!printBtn) {
                    printBtn = document.createElement('button');
                    printBtn.type = 'button';
                    printBtn.className = 'btn-print-worker';
                    printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
                    printBtn.addEventListener('click', function() {
                        printWorkerForm();
                    });
                    // Insert before cancel button
                    const cancelBtn = formActions.querySelector('.btn-cancel');
                    if (cancelBtn) {
                        formActions.insertBefore(printBtn, cancelBtn);
                    } else {
                        formActions.appendChild(printBtn);
                    }
                }
                
                if (!exportBtn) {
                    exportBtn = document.createElement('button');
                    exportBtn.type = 'button';
                    exportBtn.className = 'btn-export-worker';
                    exportBtn.innerHTML = '<i class="fas fa-file-export"></i> Export';
                    exportBtn.addEventListener('click', function() {
                        exportWorkerForm();
                    });
                    // Insert before print button
                    formActions.insertBefore(exportBtn, printBtn);
                }
                
                // Show print/export buttons
                printBtn.classList.remove('hidden');
                exportBtn.classList.remove('hidden');
            } else {
                // Show save button
                if (saveButton) {
                    saveButton.classList.remove('hidden');
                    saveButton.disabled = false;
                }
                
                // Hide print/export buttons
                const printBtn = formActions.querySelector('.btn-print-worker');
                const exportBtn = formActions.querySelector('.btn-export-worker');
                if (printBtn) printBtn.classList.add('hidden');
                if (exportBtn) exportBtn.classList.add('hidden');
            }
        }
        
        // Disable custom dropdowns in view mode (handled by CSS via .view-mode class)
    }
    
    // Function to print worker form (print current page)
    function printWorkerForm() {
        if (!workerFormContainer) return;
        
        // Add print class to container for print styling
        workerFormContainer.classList.add('print-mode');
        
        // Hide buttons and non-printable elements for print (handled by CSS .print-mode class)
        
        // Trigger print
        window.print();
        
        // Remove print class and restore elements after print
        setTimeout(() => {
            workerFormContainer.classList.remove('print-mode');
        }, 500);
    }
    
    // Function to export worker form (download HTML file)
    function exportWorkerForm() {
        if (!workerFormContainer || !workerForm) return;
        
        // FIRST: Collect ALL values from original form BEFORE cloning
        const valueMap = new Map();
        
        // Get all form elements from original form
        const allOriginalElements = workerForm.querySelectorAll('input, select, textarea');
        debugForm.log('🔍 Collecting values from', allOriginalElements.length, 'form elements');
        
        allOriginalElements.forEach(originalEl => {
            const name = originalEl.name;
            const id = originalEl.id;
            const type = originalEl.type;
            let value = null;
            
            // Skip hidden fields we don't need
            if (type === 'hidden' && (name === 'csrf_token' || name === 'id')) {
                return;
            }
            
            if (originalEl.tagName === 'SELECT') {
                if (originalEl.multiple) {
                    // Multi-select: get all selected values
                    const selected = Array.from(originalEl.selectedOptions).map(opt => ({
                        value: opt.value,
                        text: opt.text.trim()
                    }));
                    value = selected.length > 0 ? selected : [];
                } else {
                    // Single select: get value and text
                    const selectedIndex = originalEl.selectedIndex;
                    if (selectedIndex >= 0 && originalEl.options[selectedIndex]) {
                        const option = originalEl.options[selectedIndex];
                        value = {
                            value: option.value,
                            text: option.text.trim()
                        };
                    } else {
                        value = { value: '', text: '' };
                    }
                }
            } else {
                // Input or textarea: get value directly (including empty strings)
                value = originalEl.value !== null && originalEl.value !== undefined ? originalEl.value : '';
            }
            
            // Store by both name and ID for maximum compatibility
            if (name && name !== 'csrf_token' && name !== 'id') {
                valueMap.set(`name:${name}`, value);
                debugForm.log(`📝 Stored name:${name} =`, value);
            }
            if (id) {
                valueMap.set(`id:${id}`, value);
                debugForm.log(`📝 Stored id:${id} =`, value);
            }
        });
        
        debugForm.log('✅ Total values collected:', valueMap.size);
        
        // NOW clone the form
        const exportContent = workerFormContainer.cloneNode(true);
        
        // Remove buttons and non-printable elements
        const buttons = exportContent.querySelectorAll('button, .close-btn');
        buttons.forEach(btn => btn.remove());
        
        const exportForm = exportContent.querySelector('#workerForm');
        
        if (exportForm) {
            // Apply ALL collected values to cloned form
            const allExportElements = exportForm.querySelectorAll('input, select, textarea');
            debugForm.log('🔄 Applying values to', allExportElements.length, 'export elements');
            
            allExportElements.forEach(exportEl => {
                const name = exportEl.name;
                const id = exportEl.id;
                const type = exportEl.type;
                
                // Skip hidden fields we don't need
                if (type === 'hidden' && (name === 'csrf_token' || name === 'id')) {
                    return;
                }
                
                // Try to get value from map
                let value = null;
                if (name && name !== 'csrf_token' && name !== 'id') {
                    value = valueMap.get(`name:${name}`);
                }
                if ((value === null || value === undefined) && id) {
                    value = valueMap.get(`id:${id}`);
                }
                
                // Apply the value (including empty strings)
                if (value !== null && value !== undefined) {
                    debugForm.log(`✏️ Applying to ${name || id}:`, value);
                    
                    if (exportEl.tagName === 'SELECT') {
                        if (exportEl.multiple) {
                            // Multi-select
                            if (Array.isArray(value)) {
                                exportEl.value = '';
                                value.forEach(item => {
                                    const val = typeof item === 'object' ? item.value : item;
                                    const option = exportEl.querySelector(`option[value="${val}"]`);
                                    if (option) {
                                        option.selected = true;
                                    }
                                });
                            }
                        } else {
                            // Single select
                            if (typeof value === 'object' && value !== null && value.value !== undefined) {
                                exportEl.value = value.value;
                                // Update to show text
                                const option = exportEl.querySelector(`option[value="${value.value}"]`);
                                if (option) {
                                    exportEl.innerHTML = '';
                                    const newOption = document.createElement('option');
                                    newOption.value = value.value;
                                    newOption.textContent = value.text || value.value;
                                    newOption.selected = true;
                                    exportEl.appendChild(newOption);
                                }
                            } else if (value !== null && value !== undefined) {
                                exportEl.value = value;
                            }
                        }
                    } else {
                        // Input or textarea - apply value directly
                        const finalValue = typeof value === 'object' && value !== null ? (value.value || '') : (value || '');
                        exportEl.value = finalValue;
                    }
                    
                    // Always remove disabled/readonly attributes
                    exportEl.removeAttribute('disabled');
                    exportEl.removeAttribute('readonly');
                    
                    // Remove placeholder if value exists and is not empty
                    if (exportEl.value && exportEl.value !== '') {
                        exportEl.removeAttribute('placeholder');
                    }
                    
                    debugForm.log(`✅ Applied value to ${name || id}:`, exportEl.value);
                } else {
                    debugForm.log(`⚠️ No value found for ${name || id}`);
                }
            });
            
            // Special handling for job_titles - remove entire custom dropdown and show only selected items
            const jobTitlesSelect = exportForm.querySelector('select[name="job_titles[]"], select[name="job_title[]"], select#job_titles, select#job_title');
            const jobTitleDropdown = exportForm.querySelector('#job_title_dropdown');
            const selectedJobTitleTags = exportForm.querySelector('#selectedJobTitleTags');
            
            if (jobTitlesSelect || jobTitleDropdown) {
                const jobTitlesValue = valueMap.get(`name:job_titles[]`) || valueMap.get(`name:job_title[]`) || 
                                      valueMap.get(`id:job_titles`) || valueMap.get(`id:job_title`);
                
                // Find the form group containing job titles
                const jobTitleFormGroup = (jobTitlesSelect || jobTitleDropdown)?.closest('.form-group');
                
                if (jobTitleFormGroup) {
                    // Remove the entire custom dropdown structure
                    if (jobTitleDropdown) jobTitleDropdown.remove();
                    if (jobTitlesSelect) jobTitlesSelect.remove();
                    if (selectedJobTitleTags) selectedJobTitleTags.remove();
                    
                    // Create a simple display showing only selected items
                    const displayDiv = document.createElement('div');
                    displayDiv.className = 'job-titles-display';
                    
                    if (jobTitlesValue && Array.isArray(jobTitlesValue) && jobTitlesValue.length > 0) {
                        const selectedTexts = jobTitlesValue.map(item => 
                            typeof item === 'object' ? item.text : item
                        ).filter(text => text);
                        displayDiv.textContent = selectedTexts.join(', ');
                    } else {
                        displayDiv.textContent = 'None selected';
                    }
                    
                    // Add the display to the form group
                    jobTitleFormGroup.appendChild(displayDiv);
                }
            }
            
            // Remove Select2 wrappers
            const select2Wrappers = exportForm.querySelectorAll('.select2-container, .select2-selection');
            select2Wrappers.forEach(wrapper => wrapper.remove());
            
            // Remove file inputs and upload buttons
            const fileInputs = exportForm.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                const parent = input.closest('.form-group, .doc-row');
                if (parent) {
                    const uploadBtn = parent.querySelector('.upload-btn, button[type="button"]');
                    if (uploadBtn) uploadBtn.remove();
                    input.remove();
                } else {
                    input.remove();
                }
            });
        }
        
        // CRITICAL: Force all values to be set as ATTRIBUTES (not just properties) so they appear in innerHTML
        if (exportForm) {
            const allExportElements = exportForm.querySelectorAll('input, select, textarea');
            allExportElements.forEach(exportEl => {
                const name = exportEl.name;
                const id = exportEl.id;
                const type = exportEl.type;
                
                // Skip hidden fields we don't need
                if (type === 'hidden' && (name === 'csrf_token' || name === 'id')) {
                    return;
                }
                
                // Get value from map
                let value = null;
                if (name && name !== 'csrf_token' && name !== 'id') {
                    value = valueMap.get(`name:${name}`);
                }
                if ((value === null || value === undefined) && id) {
                    value = valueMap.get(`id:${id}`);
                }
                
                // Force set BOTH property AND attribute
                if (value !== null && value !== undefined) {
                    if (exportEl.tagName === 'SELECT') {
                        if (exportEl.multiple) {
                            if (Array.isArray(value)) {
                                value.forEach(item => {
                                    const val = typeof item === 'object' ? item.value : item;
                                    const option = exportEl.querySelector(`option[value="${val}"]`);
                                    if (option) {
                                        option.selected = true;
                                        option.setAttribute('selected', 'selected');
                                    }
                                });
                            }
                        } else {
                            if (typeof value === 'object' && value !== null && value.value !== undefined) {
                                exportEl.value = value.value;
                                exportEl.setAttribute('value', value.value);
                                // Update innerHTML to show text
                                const option = exportEl.querySelector(`option[value="${value.value}"]`);
                                if (option) {
                                    exportEl.innerHTML = '';
                                    const newOption = document.createElement('option');
                                    newOption.value = value.value;
                                    newOption.textContent = value.text || value.value;
                                    newOption.selected = true;
                                    newOption.setAttribute('selected', 'selected');
                                    exportEl.appendChild(newOption);
                                }
                            } else if (value !== null && value !== undefined && value !== '') {
                                exportEl.value = value;
                                exportEl.setAttribute('value', value);
                            }
                        }
                    } else {
                        // Input or textarea - set BOTH property AND attribute
                        const finalValue = typeof value === 'object' && value !== null ? (value.value || '') : (value || '');
                        exportEl.value = finalValue;
                        exportEl.setAttribute('value', finalValue);
                    }
                }
            });
        }
        
        // Get form title
        const formTitle = exportContent.querySelector('h2');
        const titleText = formTitle ? formTitle.textContent : 'Worker Details';
        
        // Get worker ID for filename
        const workerId = workerForm.querySelector('input[name="id"]')?.value || 'worker';
        const fileName = `worker-${workerId}-${new Date().toISOString().split('T')[0]}.html`;
        
        // Use export CSS constant (matches css/worker/worker-table-styles.css EXPORT_START/EXPORT_END section)
        const exportCSS = EXPORT_HTML_CSS;
        
        // Create HTML content using CSS constant (which matches the CSS file)
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${titleText}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>${exportCSS}</style>
</head>
<body>
    ${exportContent.innerHTML}
</body>
</html>`;
        
        // Create blob and download
        const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    // Function to open form in view mode (read-only)
    async function openViewWorkerForm(workerId) {
        await openEditWorkerForm(workerId, true);
    }
    
    let _ratibAgentsListCache = null;
    let _ratibAgentsListCachedAt = 0;
    const RATIB_AGENTS_CACHE_MS = 120000;

    // Function to load agents and subagents into dropdowns
    async function loadAgentsAndSubagents() {
        try {
            debugForm.log('Loading agents, subagents, and countries...');
            
            const agentSelect = document.getElementById('agent_id');
            const apiBase = window.API_BASE || '/ratibprogram/api';
            let agents = null;

            if (
                agentSelect
                && _ratibAgentsListCache
                && Date.now() - _ratibAgentsListCachedAt < RATIB_AGENTS_CACHE_MS
            ) {
                agents = _ratibAgentsListCache;
                debugForm.log('✅ Agents reused from cache:', agents.length);
            } else {
                const agentResponse = await fetch(`${apiBase}/agents/get.php?limit=1000`);
                if (!agentResponse.ok) {
                    debugForm.error('Failed to load agents, status:', agentResponse.status);
                    agents = null;
                } else {
                    const agentData = await agentResponse.json();
                    if (agentSelect && agentData.success) {
                        if (Array.isArray(agentData.data)) {
                            agents = agentData.data;
                        } else if (agentData.data?.list) {
                            agents = agentData.data.list;
                        } else if (agentData.data?.agents) {
                            agents = agentData.data.agents;
                        } else {
                            agents = [];
                        }
                        const agentIds = new Set();
                        agents = agents.filter((a) => {
                            const id = a.agent_id ?? a.id;
                            if (agentIds.has(id)) return false;
                            agentIds.add(id);
                            return true;
                        });
                        _ratibAgentsListCache = agents;
                        _ratibAgentsListCachedAt = Date.now();
                    } else {
                        debugForm.warn('Agent data structure issue:', agentData);
                        agents = [];
                    }
                }
            }

            if (agentSelect && Array.isArray(agents)) {
                agentSelect.innerHTML = '<option value="">Select Agent</option>';
                agents.forEach((agent) => {
                    const option = document.createElement('option');
                    option.value = agent.agent_id || agent.id;
                    option.textContent = window.toEnglishString
                        ? window.toEnglishString(`${agent.formatted_id || agent.agent_id || agent.id} - ${agent.full_name || agent.agent_name}`)
                        : `${agent.formatted_id || agent.agent_id || agent.id} - ${agent.full_name || agent.agent_name}`;
                    agentSelect.appendChild(option);
                });
                debugForm.log('✅ Agents in DOM:', agents.length);
            }
            
            // Country dropdown is populated by populateCountryDropdown() function via API
            // No longer using hardcoded countriesCities - all data comes from System Settings
            const countrySelect = document.getElementById('country');
            if (countrySelect) {
                debugForm.log('✅ Country dropdown will be populated from System Settings via API');
            }
        } catch (error) {
            debugForm.error('Error loading agents and subagents:', error);
        }
    }
    
    // Helper function to map database status to form status
    function mapDatabaseStatusToFormStatus(dbStatus) {
        if (!dbStatus) return 'active';
        
        // Map database values to form dropdown values
        const statusMap = {
            'approved': 'active',
            'active': 'active',
            'inactive': 'inactive',
            'rejected': 'inactive',
            'pending': 'pending',
            'suspended': 'suspended'
        };
        
        const mappedStatus = statusMap[dbStatus.toLowerCase()] || 'active';
        debugForm.log('Mapping status:', dbStatus, '->', mappedStatus);
        return mappedStatus;
    }

    /**
     * View-only: core/get.php already joins agent_name + subagent_name.
     * Building two options avoids agents/get.php?limit=1000 and subagents/get.php (large latency wins).
     */
    function seedViewModeAgentDropdowns(worker) {
        if (!worker) return;
        const toEng = (v) => (window.toEnglishString ? window.toEnglishString(String(v ?? '')) : String(v ?? ''));

        const agentSelect = document.getElementById('agent_id');
        if (agentSelect && worker.agent_id) {
            agentSelect.innerHTML = '<option value="">Select Agent</option>';
            const opt = document.createElement('option');
            opt.value = String(worker.agent_id);
            const nm = worker.agent_name ? String(worker.agent_name).trim() : '';
            opt.textContent = nm ? `${toEng(worker.agent_id)} - ${toEng(nm)}` : `Agent #${toEng(worker.agent_id)}`;
            agentSelect.appendChild(opt);
            agentSelect.value = String(worker.agent_id);
        }

        const subSel = document.getElementById('subagent_id');
        if (subSel) {
            subSel.innerHTML = '<option value="">Select Subagent</option>';
            if (worker.subagent_id) {
                const o2 = document.createElement('option');
                o2.value = String(worker.subagent_id);
                const snm = worker.subagent_name ? String(worker.subagent_name).trim() : '';
                o2.textContent = snm ? `${toEng(worker.subagent_id)} - ${toEng(snm)}` : `Subagent #${toEng(worker.subagent_id)}`;
                subSel.appendChild(o2);
                subSel.value = String(worker.subagent_id);
            }
        }
    }
    
    // Function to populate edit form
    async function populateEditForm(worker, isViewMode = false) {
        // Verify worker ID matches form's expected worker
        const expectedWorkerId = workerForm?.dataset.currentWorkerId;
        if (expectedWorkerId && String(worker.id) !== String(expectedWorkerId)) {
            debugForm.error('⚠️ Worker ID mismatch in populateEditForm!');
            debugForm.error('Expected:', expectedWorkerId, 'Got:', worker.id);
            return;
        }
        
        debugForm.log('=== POPULATING FORM ===');
        debugForm.log('Worker ID:', worker.id);
        
        // Set hidden ID field FIRST - critical for save/update to work
        const idField = workerForm?.querySelector('input[name="id"]');
        if (idField) {
            idField.value = String(worker.id);
            debugForm.log('Set hidden id field to:', idField.value);
        }
        
        // Reset dropdown initialization flag to allow fresh setup
        const dropdownContainer = document.getElementById('job_title_dropdown');
        if (dropdownContainer) {
            dropdownContainer.dataset.initialized = 'false';
        }
        
        // Clear all form fields first to prevent showing previous worker's data (except id, set above)
        if (workerForm) {
            // Reset the form to clear all values
            workerForm.reset();
            
            // Re-set the id immediately after reset - reset() clears it
            if (idField) {
                idField.value = String(worker.id);
            }
            
            // Also manually clear all input fields, selects, and textareas
            const allInputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="number"], textarea');
            allInputs.forEach(input => {
                if (input.name !== 'csrf_token' && input.name !== 'id') {
                    input.value = '';
                }
            });
            
            // Clear all select dropdowns (except agent, subagent, country, city, job_title which will be populated)
            const allSelects = workerForm.querySelectorAll('select');
            allSelects.forEach(select => {
                const selectId = select.id || '';
                const selectName = select.name || '';
                
                if (!['agent_id', 'subagent_id', 'country', 'city', 'job_title'].includes(selectId) && 
                    !selectName.includes('job_title')) {
                    select.selectedIndex = 0; // Reset to first option
                    select.value = '';
                }
            });
            
            // Clear selected tags container
            const tagsContainer = document.getElementById('selectedJobTitleTags');
            if (tagsContainer) {
                tagsContainer.innerHTML = '';
            }
            
            // Reset dropdown placeholder
            const dropdownTrigger = document.querySelector('#job_title_dropdown .custom-dropdown-trigger .dropdown-placeholder');
            if (dropdownTrigger) {
                dropdownTrigger.textContent = 'Select job titles and skills...';
                dropdownTrigger.classList.add('placeholder-default');
                dropdownTrigger.classList.remove('placeholder-selected');
            }
            
            debugForm.log('✅ Form cleared before populating');
        }
        
        const toEnglish = (val) => window.toEnglishString ? window.toEnglishString(val) : val;
        
        // Helper function to clean date values
        const cleanDate = (dateValue) => {
            if (!dateValue || dateValue === '0000-00-00' || dateValue === '0000-00-00 00:00:00' || dateValue.trim() === '') {
                return '';
            }
            return dateValue;
        };
        
        // Map database fields to form fields (all values pass through toEnglish to convert Arabic)
        const fieldMappings = {
            'id': toEnglish(worker.id) ?? worker.id ?? '',
            'workflow_id': toEnglish(worker.workflow_id || ''),
            'current_stage': worker.current_stage || getWorkflowStages()[0],
            'stage_completed': typeof worker.stage_completed === 'string' ? worker.stage_completed : JSON.stringify(worker.stage_completed || {}),
            'full_name': toEnglish(worker.worker_name || worker.full_name || ''),
            'nationality': toEnglish(worker.nationality || ''),
            'gender': worker.gender || '',
            'age': toEnglish(worker.age) ?? worker.age ?? '',
            'marital_status': worker.marital_status || '',
            'language': worker.language || '',
            'birth_date': cleanDate(worker.birth_date || worker.date_of_birth),
            'place_of_birth': toEnglish(worker.place_of_birth || worker.placeOfBirth || worker.birth_place || worker.birthPlace || ''),
            'job_title': toEnglish(worker.job_title || ''),
            'qualification': worker.qualification || '',
            'skills': worker.skills || '',
            'local_experience': toEnglish(worker.local_experience || ''),
            'abroad_experience': toEnglish(worker.abroad_experience || ''),
            'phone': toEnglish(worker.phone || worker.contact_number || ''),
            'email': toEnglish(worker.email || ''),
            'country': toEnglish(worker.country || ''),
            'city': toEnglish(worker.city || ''),
            'address': toEnglish(worker.address || ''),
            'identity_number': toEnglish(worker.identity_number || ''),
            'identity_date': cleanDate(worker.identity_date),
            'passport_number': toEnglish(worker.passport_number || ''),
            'passport_date': cleanDate(worker.passport_date),
            'passport_expiry': cleanDate(worker.passport_expiry),
            // Updated: lifecycle fields for non-Indonesia edit/view prefill.
            'passport_expiry_date': cleanDate(worker.passport_expiry_date),
            'personal_photo_url': toEnglish(worker.personal_photo_url || ''),
            'education_level': toEnglish(worker.education_level || ''),
            'work_experience': toEnglish(worker.work_experience || ''),
            'is_identity_verified': String(worker.is_identity_verified ?? '0'),
            'biometric_id': toEnglish(worker.biometric_id || ''),
            'demand_letter_id': toEnglish(worker.demand_letter_id || ''),
            'salary': toEnglish(worker.salary) ?? worker.salary ?? '',
            'working_hours': toEnglish(worker.working_hours || ''),
            'contract_duration': toEnglish(worker.contract_duration || ''),
            'vacation_days': toEnglish(worker.vacation_days) ?? worker.vacation_days ?? '',
            'accommodation_details': toEnglish(worker.accommodation_details || ''),
            'food_details': toEnglish(worker.food_details || ''),
            'transport_details': toEnglish(worker.transport_details || ''),
            'insurance_details': toEnglish(worker.insurance_details || ''),
            'medical_check_date': cleanDate(worker.medical_check_date),
            'predeparture_training_completed': String(worker.predeparture_training_completed ?? '0'),
            'training_notes': toEnglish(worker.training_notes || ''),
            'government_registration_number': toEnglish(worker.government_registration_number || ''),
            'worker_card_number': toEnglish(worker.worker_card_number || ''),
            'exit_clearance_status': worker.exit_clearance_status || 'pending',
            'work_permit_number': toEnglish(worker.work_permit_number || ''),
            'contract_verified': String(worker.contract_verified ?? '0'),
            'flight_ticket_number': toEnglish(worker.flight_ticket_number || ''),
            'travel_date': cleanDate(worker.travel_date),
            'insurance_policy_number': toEnglish(worker.insurance_policy_number || ''),
            'police_number': toEnglish(worker.police_number || ''),
            'police_date': cleanDate(worker.police_date),
            'medical_number': toEnglish(worker.medical_number || ''),
            'medical_date': cleanDate(worker.medical_date),
            'visa_number': toEnglish(worker.visa_number || ''),
            'visa_date': cleanDate(worker.visa_date),
            'ticket_number': toEnglish(worker.ticket_number || ''),
            'ticket_date': cleanDate(worker.ticket_date),
            'training_certificate_number': toEnglish(worker.training_certificate_number || ''),
            'training_certificate_date': cleanDate(worker.training_certificate_date),
            'status_stage': worker.status_stage || 'registered',
            'training_status': worker.training_status || 'not_started',
            'training_center': toEnglish(worker.training_center || ''),
            'language_level': worker.language_level || 'basic',
            'medical_center_name': toEnglish(worker.medical_center_name || ''),
            'contract_signed_number': toEnglish(worker.contract_signed_number || ''),
            'insurance_number': toEnglish(worker.insurance_number || ''),
            'exit_permit_number': toEnglish(worker.exit_permit_number || ''),
            'gov_approval_status': worker.gov_approval_status || 'pending',
            'approval_reference_id': toEnglish(worker.approval_reference_id || ''),
            'emergency_name': toEnglish(worker.emergency_name || ''),
            'emergency_relation': worker.emergency_relation || '',
            'emergency_phone': toEnglish(worker.emergency_phone || ''),
            'emergency_address': toEnglish(worker.emergency_address || ''),
            'agent_id': toEnglish(worker.agent_id) ?? worker.agent_id ?? '',
            'subagent_id': toEnglish(worker.subagent_id) ?? worker.subagent_id ?? '',
            'status': mapDatabaseStatusToFormStatus(worker.status) || 'active'
        };
        
        // Populate form fields (except agent_id, subagent_id, country, city, job_title - these need special handling)
        // Note: place_of_birth, skills, and job_title are included here but will also be set again after async operations
        Object.keys(fieldMappings).forEach(fieldName => {
            if (!['agent_id', 'subagent_id', 'country', 'city', 'job_title', 'medical_status', 'visa_status'].includes(fieldName)) {
            const element = workerForm.querySelector(`[name="${fieldName}"]`);
            if (element) {
                    // Handle date fields - skip invalid dates (use setDate if Flatpickr is initialized)
                    if (fieldName.includes('_date') || fieldName.includes('date')) {
                        const dateValue = fieldMappings[fieldName];
                        if (element._flatpickr) {
                            element._flatpickr.setDate(dateValue && dateValue !== '0000-00-00' && dateValue !== '' ? dateValue : null);
                        } else if (dateValue && dateValue !== '0000-00-00' && dateValue !== '') {
                            element.value = dateValue;
                        } else {
                            element.value = '';
                        }
                    } else {
                        const value = fieldMappings[fieldName] || '';
                        element.value = value;
                        // For select elements, also verify the option exists and try to set it
                        if (element.tagName === 'SELECT' && value) {
                            // Try to set the value
                            element.value = value;
                            // Verify it was set correctly
                            if (element.value !== value) {
                                // Try to find by text content
                                const optionFound = Array.from(element.options).find(opt => 
                                    opt.value === value || opt.textContent.trim() === value
                                );
                                if (optionFound) {
                                    element.value = optionFound.value;
                                } else {
                                    debugForm.warn(`Option value "${value}" not found in select "${fieldName}"`);
                                }
                            }
                        }
                    }
                    debugForm.log(`Set ${fieldName} to:`, element.value);
                } else {
                    debugForm.warn(`Field not found: ${fieldName}`);
                }
            }
        });

        // Explicitly convert age field to Western numerals (browser may display type=number in locale digits)
        const ageEl = workerForm.querySelector('[name="age"]');
        if (ageEl && ageEl.value && window.toEnglishString) {
            const converted = window.toEnglishString(ageEl.value);
            if (converted !== ageEl.value) ageEl.value = converted;
        }
        
        // Store values for place_of_birth, skills, and job_title to set them after all async operations
        const savedPlaceOfBirth = fieldMappings.place_of_birth || '';
        const savedSkills = fieldMappings.skills || '';
        const savedJobTitle = fieldMappings.job_title || '';
        
        // Function to set place_of_birth, skills, and job_title - will be called after all async operations
        const setPlaceOfBirthAndSkills = () => {
            if (!workerForm) {
                return;
            }
            
            // Set place_of_birth
            const placeOfBirthField = workerForm.querySelector('[name="place_of_birth"]');
            if (placeOfBirthField && savedPlaceOfBirth) {
                placeOfBirthField.value = savedPlaceOfBirth;
            }
            
            // Set skills
            const skillsField = workerForm.querySelector('[name="skills"]');
            if (skillsField && savedSkills) {
                if (skillsField.tagName === 'SELECT') {
                    const optionFound = Array.from(skillsField.options).find(opt => 
                        opt.value === savedSkills || opt.textContent.trim() === savedSkills
                    );
                    if (optionFound) {
                        skillsField.value = optionFound.value;
                    } else if (savedSkills) {
                        skillsField.value = savedSkills;
                    }
                } else {
                    skillsField.value = savedSkills;
                }
            }
            
            // Set job_title (multi-select)
            const jobTitleField = workerForm.querySelector('[name="job_title[]"]') || workerForm.querySelector('[name="job_title"]');
            
            if (jobTitleField && savedJobTitle) {
                if (jobTitleField.tagName === 'SELECT' && jobTitleField.multiple) {
                    // Parse savedJobTitle - could be comma-separated string or array
                    let jobTitleArray = [];
                    if (Array.isArray(savedJobTitle)) {
                        jobTitleArray = savedJobTitle;
                    } else if (typeof savedJobTitle === 'string') {
                        // Always split by comma if it's a string (even if no comma, split will return array with one element)
                        jobTitleArray = savedJobTitle.split(',').map(s => s.trim()).filter(s => s);
                    } else if (savedJobTitle) {
                        jobTitleArray = [String(savedJobTitle)];
                    }
                    
                    // Check current selections first
                    const currentSelected = Array.from(jobTitleField.selectedOptions).map(opt => opt.value);
                    
                    const needsUpdate = jobTitleArray.length !== currentSelected.length || 
                                      !jobTitleArray.every(val => currentSelected.includes(val));
                    
                    // Only update if selections are different
                    if (needsUpdate) {
                        Array.from(jobTitleField.options).forEach(opt => opt.selected = false);
                        
                        jobTitleArray.forEach(jobValue => {
                            const optionFound = Array.from(jobTitleField.options).find(opt => 
                                opt.value === jobValue || opt.value === jobValue.trim() || 
                                opt.textContent.trim().toLowerCase() === jobValue.toLowerCase()
                            );
                            if (optionFound) {
                                optionFound.selected = true;
                            }
                        });
                    }
                    
                    // Sync dropdown after setting selections
                    if (isViewMode) {
                        requestAnimationFrame(() => {
                            if (typeof window.setupCustomJobTitleDropdown === 'function') {
                                window.setupCustomJobTitleDropdown();
                            }
                        });
                    } else {
                        setTimeout(() => {
                            if (typeof window.setupCustomJobTitleDropdown === 'function') {
                                window.setupCustomJobTitleDropdown();
                            }
                        }, 50);
                    }
                } else if (jobTitleField.tagName === 'SELECT') {
                    // Single select
                    const optionFound = Array.from(jobTitleField.options).find(opt => 
                        opt.value === savedJobTitle || opt.textContent.trim() === savedJobTitle
                    );
                    if (optionFound) {
                        jobTitleField.value = optionFound.value;
                    } else if (savedJobTitle) {
                        jobTitleField.value = savedJobTitle;
                    }
                } else {
                    jobTitleField.value = savedJobTitle;
                }
            }
        };
        
        // Agent + subagents and country + cities run in parallel; city list uses real await (no 150ms guess).
        const agentSelect = document.getElementById('agent_id');
        if (isViewMode) {
            seedViewModeAgentDropdowns(worker);
        }
        let agentFound = false;
        if (agentSelect && worker.agent_id) {
            const agentIdStr = String(worker.agent_id);
            const agentIdNum = parseInt(worker.agent_id, 10);

            debugForm.log('Setting agent value:', worker.agent_id, 'Type:', typeof worker.agent_id);

            agentSelect.value = agentIdStr;
            if (agentSelect.value === agentIdStr || agentSelect.value == worker.agent_id) {
                agentFound = true;
                debugForm.log('✅ Agent value set by direct assignment:', agentSelect.value);
            } else {
                for (let i = 0; i < agentSelect.options.length; i++) {
                    const option = agentSelect.options[i];
                    const optionValue = option.value;
                    const optionValueStr = String(optionValue);
                    const optionValueNum = parseInt(optionValue, 10);
                    if (optionValue === agentIdStr ||
                        optionValue == worker.agent_id ||
                        optionValueStr === agentIdStr ||
                        optionValueNum === agentIdNum ||
                        optionValueNum == worker.agent_id) {
                        agentSelect.selectedIndex = i;
                        agentSelect.value = optionValue;
                        agentFound = true;
                        debugForm.log('✅ Agent value set by index matching:', worker.agent_id, 'Matched option value:', optionValue);
                        break;
                    }
                }
            }

            if (agentFound) {
                debugForm.log('✅ Agent selected - Value:', agentSelect.value);
            } else {
                debugForm.warn('⚠️ Agent ID not found in dropdown:', worker.agent_id);
                debugForm.warn('Available option values:', Array.from(agentSelect.options).map((opt) => opt.value));
            }
        } else if (agentSelect && !worker.agent_id) {
            debugForm.log('⚠️ Worker has no agent_id');
        } else if (!agentSelect) {
            debugForm.error('⚠️ Agent select element not found');
        }

        const restoreCountryAndCity = async () => {
            if (!worker.country || !worker.country.trim()) return;
            const savedCity = worker.city;
            const countryValue = worker.country.trim();
            const countrySelect = document.getElementById('country');
            if (!countrySelect) return;

            let countryFound = false;
            for (let i = 0; i < countrySelect.options.length; i++) {
                const optionValue = countrySelect.options[i].value.trim();
                if (optionValue === countryValue || optionValue.toLowerCase() === countryValue.toLowerCase()) {
                    countrySelect.selectedIndex = i;
                    countrySelect.value = countryValue;
                    countryFound = true;
                    debugForm.log('✅ Country set:', countryValue);

                    if (typeof window.loadCitiesByCountry === 'function') {
                        await window.loadCitiesByCountry(worker.country, 'city');
                    }

                    const citySelect = document.getElementById('city');
                    if (citySelect && savedCity) {
                        const cityValue = String(savedCity).trim();
                        let cityFound = false;
                        for (let j = 0; j < citySelect.options.length; j++) {
                            const cv = citySelect.options[j].value.trim();
                            if (cv === cityValue || cv.toLowerCase() === cityValue.toLowerCase()) {
                                citySelect.selectedIndex = j;
                                citySelect.value = cityValue;
                                cityFound = true;
                                citySelect.setAttribute('data-city-set', 'true');
                                debugForm.log('✅ City restored:', cityValue);
                                break;
                            }
                        }
                        if (!cityFound && cityValue) {
                            const option = document.createElement('option');
                            option.value = cityValue;
                            option.textContent = cityValue;
                            option.selected = true;
                            citySelect.appendChild(option);
                            debugForm.log('✅ Created and selected city option:', cityValue);
                        }
                    }
                    break;
                }
            }
            if (!countryFound) {
                debugForm.warn('Country not found in dropdown:', countryValue);
            }
        };

        const subagentPromise = (isViewMode || !(agentSelect && worker.agent_id && agentFound))
            ? Promise.resolve()
            : loadSubagentsForAgent(worker.agent_id, worker.subagent_id);

        await Promise.all([subagentPromise, restoreCountryAndCity()]);
        updateIndonesiaComplianceVisibility();
        applyWorkflowStageUI();
        
        // Update document status indicators AFTER all other initialization
        updateStatusIndicators(worker);
        
        // Set place_of_birth, skills, and job_title last (was 800ms fixed wait — now minimal settle)
        if (isViewMode) {
            await new Promise((resolve) => {
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            });
        } else {
            await new Promise((resolve) => setTimeout(resolve, 250));
        }
        setPlaceOfBirthAndSkills();
        
        if (isViewMode) {
            requestAnimationFrame(() => {
                if (typeof window.setupCustomJobTitleDropdown === 'function') {
                    window.setupCustomJobTitleDropdown();
                }
            });
        } else {
            setTimeout(() => {
                if (typeof window.setupCustomJobTitleDropdown === 'function') {
                    window.setupCustomJobTitleDropdown();
                }
            }, 400);
        }
        
        // Note: Removed MutationObserver to allow users to clear/edit place_of_birth and skills fields
    }
    
    // Function to update document status indicators in form
    function updateStatusIndicators(worker) {
        if (!worker) return;
        
        const docTypes = ['identity', 'passport', 'training_certificate', 'contract_signed', 'insurance', 'police', 'medical', 'visa', 'exit_permit', 'ticket', 'country_compliance_primary', 'country_compliance_secondary', 'contract_deployment_primary', 'contract_deployment_secondary', 'contract_deployment_verification'];
        
        docTypes.forEach(docType => {
            const statusWrapper = document.querySelector(`.status-wrapper[data-doc-type="${docType}"]`);
            if (!statusWrapper) return;
            
            const indicator = statusWrapper.querySelector('.status-indicator');
            const text = statusWrapper.querySelector('.status-text');
            
            if (!indicator || !text) return;
            
            const status = worker[`${docType}_status`] || 'pending';
            const normalizedStatus = status.toLowerCase().trim();
            
            // Remove all status classes
            indicator.classList.remove('status-pending', 'status-ok', 'status-not_ok');
            text.classList.remove('status-pending', 'status-ok', 'status-not_ok');
            
            // Add new status class
            const statusClass = normalizedStatus === 'not_ok' ? 'status-not_ok' : `status-${normalizedStatus}`;
            indicator.classList.add(statusClass);
            text.classList.add(statusClass);
            
            // Update text content
            const statusTextMap = {
                'ok': 'ok',
                'not_ok': 'not ok',
                'pending': 'pending'
            };
            text.textContent = statusTextMap[normalizedStatus] || 'pending';
            
            // Update hidden input field
            const statusInput = document.querySelector(`input[name="${docType}_status"]`);
            if (statusInput) {
                statusInput.value = normalizedStatus;
            }
        });
    }
    
    // Function to validate form
    function validateForm() {
        const requiredFields = workerForm.querySelectorAll('[required]');
        let isValid = true;
        let missingFields = [];
        
        // Clear previous validation states
        workerForm.querySelectorAll('.form-control, .form-select').forEach(field => {
            field.classList.remove('error', 'success');
        });
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
                const fieldName = field.name || field.id || 'field';
                missingFields.push(fieldName.replace('_', ' '));
            } else {
                field.classList.add('success');
            }
        });
        
        // Additional validation
        const emailField = workerForm.querySelector('[name="email"]');
        if (emailField && emailField.value && !isValidEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
            missingFields.push('valid email address');
        }
        
        const phoneField = workerForm.querySelector('[name="phone"]');
        if (phoneField && phoneField.value && !isValidPhone(phoneField.value)) {
            phoneField.classList.add('error');
            isValid = false;
            missingFields.push('valid phone number');
        }
        
        const ageField = workerForm.querySelector('[name="age"]');
        if (ageField && ageField.value) {
            const age = parseInt(ageField.value);
            if (age < 18 || age > 65) {
                ageField.classList.add('error');
                isValid = false;
                missingFields.push('age between 18-65');
            }
        }
        
        if (!isValid) {
            const message = 'Please fix the following issues:\n\n' + missingFields.map(field => `• ${field}`).join('\n');
            debugForm.warn(message);
        }
        
        return isValid;
    }
    
    // Helper function to validate email
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Helper function to validate phone
    function isValidPhone(phone) {
        const phoneRegex = /^[\d\-\+\(\)\s]{7,}$/;
        return phoneRegex.test(phone);
    }
    
    // Function to save worker - removed duplicate, using the one from worker-consolidated.js
    
    // Upload button functionality
    const uploadBtns = document.querySelectorAll('.upload-btn');
    uploadBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const fileInput = this.nextElementSibling;
            if (fileInput && fileInput.type === 'file') {
                fileInput.click();
            }
        });
    });
    
    // File input change handlers
    const fileInputs = document.querySelectorAll('.file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.files.length > 0) {
                const uploadBtn = this.previousElementSibling;
                
                if (uploadBtn) uploadBtn.innerHTML = 'UPLOADED';
            }
        });
    });
    
    // Handle edit button clicks - skip if from worker table (tbody handles it to avoid double openEditWorkerForm)
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-worker');
        if (editBtn && !e.target.closest('#workerTableBody') && !e.target.closest('.mobile-worker-cards')) {
            e.preventDefault();
            const workerId = editBtn.getAttribute('data-worker-id');
            if (workerId) openEditWorkerForm(workerId);
        }
        
        // Check if clicked element is an add worker button
        if (e.target.classList.contains('add-worker') || e.target.closest('.add-worker')) {
            e.preventDefault();
            openAddWorkerForm();
        }
    });
    
    // Function to load subagents for a specific agent (mutex prevents duplicate/overlapping calls)
    let _loadSubagentsRequestId = 0;
    async function loadSubagentsForAgent(agentId, selectedSubagentId = null) {
        const subagentSelect = document.getElementById('subagent_id');
        if (!subagentSelect) return;
        if (!agentId) {
            subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
            return;
        }
        
        const thisRequestId = ++_loadSubagentsRequestId;
        try {
            subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
                debugForm.log('Loading subagents for agent ID:', agentId);
                const apiBase = window.API_BASE || '/ratibprogram/api';
                debugForm.log('API URL:', `${apiBase}/subagents/get.php?agent_id=${agentId}`);
            const response = await fetch(`${apiBase}/subagents/get.php?agent_id=${agentId}`);
            debugForm.log('Response status:', response.status);
            const data = await response.json();
            debugForm.log('Subagents API response:', data);
            
            if (thisRequestId !== _loadSubagentsRequestId) return;
            
            if (data.success) {
                    // Handle different response formats:
                    // 1. When agent_id specified: data.data = [subagent1, subagent2, ...]
                    // 2. When no agent_id: data.data.subagents = [subagent1, subagent2, ...]
                    let subagents = [];
                    if (Array.isArray(data.data)) {
                        // Direct array format (when agent_id is specified)
                        subagents = data.data;
                    } else if (data.data?.subagents) {
                        // Nested format (when no agent_id)
                        subagents = data.data.subagents;
                    } else if (data.subagents) {
                        // Fallback format
                        subagents = data.subagents;
                    }
                    debugForm.log('Found subagents:', subagents);
                    
                    // Deduplicate by subagent_id (API or race conditions may return duplicates)
                    const seen = new Set();
                    subagents = subagents.filter(s => {
                        const id = s.subagent_id ?? s.id;
                        if (seen.has(id)) return false;
                        seen.add(id);
                        return true;
                    });
                    
                    if (subagents.length > 0) {
                        // If only ONE subagent exists, auto-select it
                        if (subagents.length === 1) {
                            const singleSubagent = subagents[0];
                            const label = (window.toEnglishString || (v=>v))(`${singleSubagent.formatted_id || singleSubagent.subagent_id} - ${singleSubagent.full_name}`);
                            subagentSelect.innerHTML = `<option value="${singleSubagent.subagent_id}" selected>${label}</option>`;
                            debugForm.log('Auto-selected single subagent:', singleSubagent);
                        } else {
                            // Multiple subagents: show dropdown list
                            subagents.forEach(subagent => {
                                const option = document.createElement('option');
                                option.value = subagent.subagent_id;
                                option.textContent = window.toEnglishString ? window.toEnglishString(`${subagent.formatted_id || subagent.subagent_id} - ${subagent.full_name}`) : `${subagent.formatted_id || subagent.subagent_id} - ${subagent.full_name}`;
                                
                                // Select the subagent if it matches the worker's subagent (try both string and number comparison)
                                if (selectedSubagentId) {
                                    const subagentIdStr = String(subagent.subagent_id);
                                    const selectedIdStr = String(selectedSubagentId);
                                    if (subagentIdStr === selectedIdStr || subagent.subagent_id == selectedSubagentId) {
                                    option.selected = true;
                                        debugForm.log('✅ Selected subagent option:', selectedSubagentId);
                                    }
                                }
                                
                                subagentSelect.appendChild(option);
                            });
                            
                            // After adding all options, explicitly set the value if we have a selectedSubagentId
                            if (selectedSubagentId) {
                                const selectedIdStr = String(selectedSubagentId);
                                subagentSelect.value = selectedIdStr;
                                
                                // Verify it was set
                                if (subagentSelect.value === selectedIdStr) {
                                    debugForm.log('✅ Subagent value confirmed set:', selectedSubagentId);
                                } else {
                                    debugForm.warn('⚠️ Subagent value may not have been set correctly:', selectedSubagentId, 'Current value:', subagentSelect.value);
                                    // Try setting by finding the option
                                    for (let i = 0; i < subagentSelect.options.length; i++) {
                                        const optionValue = String(subagentSelect.options[i].value);
                                        if (optionValue === selectedIdStr || optionValue == selectedSubagentId) {
                                            subagentSelect.selectedIndex = i;
                                            debugForm.log('✅ Subagent set by index:', selectedSubagentId);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Add a "No subagents found" option if no subagents exist for this agent
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No subagents found for this agent';
                        option.disabled = true;
                        subagentSelect.appendChild(option);
                    }
                }
        } catch (error) {
            debugForm.error('Error loading subagents:', error);
        } finally {
            if (subagentSelect && thisRequestId === _loadSubagentsRequestId) {
                const opts = subagentSelect.querySelectorAll('option[value]:not([value=""])');
                const seen = new Set();
                opts.forEach(opt => {
                    if (seen.has(opt.value)) opt.remove();
                    else seen.add(opt.value);
                });
            }
        }
    }

    // Handle agent selection to load subagents
    function setupAgentChangeListener() {
    const agentSelect = document.getElementById('agent_id');
        if (agentSelect && !agentSelect.hasAttribute('data-listener-setup')) {
            agentSelect.setAttribute('data-listener-setup', 'true');
        agentSelect.addEventListener('change', async function() {
            const agentId = this.value;
                if (agentId) {
            debugForm.log('Agent changed to ID:', agentId);
            await loadSubagentsForAgent(agentId);
                } else {
                    // Clear subagents if no agent selected
                    const subagentSelect = document.getElementById('subagent_id');
                    if (subagentSelect) {
                        subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
                    }
                }
        });
    }
    }
    
    // Make function globally available
    window.setupAgentChangeListener = setupAgentChangeListener;
    
    // Setup agent change listener on page load
    setupAgentChangeListener();
    
    // Test function for debugging subagents
    window.testSubagentsAPI = async function(agentId) {
        debugForm.log('Testing subagents API for agent ID:', agentId);
        try {
            const apiBase = window.API_BASE || '/ratibprogram/api';
            const response = await fetch(`${apiBase}/subagents/get.php?agent_id=${agentId}`);
            const data = await response.json();
            debugForm.log('Raw API response:', data);
            return data;
        } catch (error) {
            debugForm.error('API test error:', error);
            return null;
        }
    };
    

    // Make functions globally available
    window.openAddWorkerForm = openAddWorkerForm;
    window.openEditWorkerForm = openEditWorkerForm;
    window.openViewWorkerForm = openViewWorkerForm;
    // window.closeWorkerForm is already set earlier in the file
    window.loadSubagentsForAgent = loadSubagentsForAgent;

    // Navigation handled by global navigation.js - removed duplicate code
    
    // Setup navigation - Check if function exists first
    // Note: setupWorkerNavigation is handled by navigation.js globally
    // No need to call it here as it's already set up globally

    // Populate country dropdown on page load from API
    async function populateCountryDropdown() {
        const countrySelect = document.getElementById('country');
        if (!countrySelect) return;
        
        try {
            const baseUrl = ratibWorkerSiteBase();
            const timestamp = new Date().getTime();
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=countries&_t=${timestamp}${ratibCountriesCitiesControlSuffix()}`;
            
            debugForm.log('[Worker] Loading countries from:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                cache: 'no-cache'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            debugForm.log('[Worker] Countries API response:', data);
            
            // Only populate if countries exist in System Settings
            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                // Clear existing options except the first one
                const firstOption = countrySelect.querySelector('option[value=""]');
                countrySelect.innerHTML = '';
                if (firstOption) {
                    countrySelect.appendChild(firstOption);
                }
                
                // Add all countries from the API
                data.countries.sort().forEach(country => {
                    const option = document.createElement('option');
                    option.value = country;
                    option.textContent = country;
                    countrySelect.appendChild(option);
                });
                debugForm.log(`[Worker] Loaded ${data.countries.length} countries into dropdown`);
            } else {
                debugForm.warn('[Worker] No countries returned from API or API returned error');
                // No countries in System Settings - keep dropdown empty
                const firstOption = countrySelect.querySelector('option[value=""]');
                countrySelect.innerHTML = '';
                if (firstOption) {
                    countrySelect.appendChild(firstOption);
                }
            }
        } catch (error) {
            debugForm.error('Failed to load countries:', error);
            countrySelect.innerHTML = '<option value="">Error loading countries</option>';
        }
    }
    
    // Initialize country dropdown on page load
    populateCountryDropdown();

    // Function to load cities by country from API
    window.loadCitiesByCountry = async function(country, cityFieldId) {
        const citySelect = document.getElementById(cityFieldId);
        if (!citySelect) {
            debugForm.warn('City select element not found:', cityFieldId);
            return;
        }
        
        if (!country || country.trim() === '') {
            citySelect.innerHTML = '<option value="">Select Country First</option>';
            return;
        }
        
        debugForm.log('Loading cities for country:', country);
        
        // Clear existing options
        citySelect.innerHTML = '<option value="">Loading cities...</option>';
        
        try {
            const baseUrl = ratibWorkerSiteBase();
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(country)}${ratibCountriesCitiesControlSuffix()}`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && Array.isArray(data.cities) && data.cities.length > 0) {
                citySelect.innerHTML = '<option value="">Select City</option>';
                data.cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
                debugForm.log('✅ Cities loaded for', country, ':', data.cities.length);
            } else {
                citySelect.innerHTML = '<option value="">No cities available for this country</option>';
                debugForm.warn('⚠️ No cities found for country:', country);
            }
        } catch (error) {
            debugForm.error('Failed to load cities:', error);
            citySelect.innerHTML = '<option value="">Error loading cities</option>';
            debugForm.warn('⚠️ Error loading cities for country:', country);
        }
    };
    
    // Setup country change event listener to load cities
    function setupCountryChangeListener() {
        const countrySelect = document.getElementById('country');
        if (countrySelect && !countrySelect.hasAttribute('data-listener-setup')) {
            countrySelect.setAttribute('data-listener-setup', 'true');
            countrySelect.addEventListener('change', function() {
                const country = this.value;
                updateIndonesiaComplianceVisibility();
                applyWorkflowStageUI();
                if (country) {
                    debugForm.log('Country changed to:', country);
                    loadCitiesByCountry(country, 'city');
                } else {
                    // Clear cities if no country selected
                    const citySelect = document.getElementById('city');
                    if (citySelect) {
                        citySelect.innerHTML = '<option value="">Select Country First</option>';
                    }
                }
            });
        }
        updateIndonesiaComplianceVisibility();
        applyWorkflowStageUI();
    }
    
    // Make function globally available
    window.setupCountryChangeListener = setupCountryChangeListener;

    // Check for edit/view parameters and open modals automatically
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    // Hide table container when edit/view is present to show form directly
    if (editId || viewId) {
        const tableContainer = document.querySelector('.table-container');
        const workerTableContainer = document.querySelector('.worker-table-container');
        const contentWrapper = document.querySelector('.content-wrapper');
        if (tableContainer) tableContainer.classList.add('print-hidden');
        if (workerTableContainer) workerTableContainer.classList.add('print-hidden');
        if (contentWrapper) {
            const pageHeader = contentWrapper.querySelector('.page-header');
            if (pageHeader) pageHeader.classList.add('print-hidden');
        }
    }
    
    // Open deep-linked edit/view as soon as layout has applied (avoid fixed 1500ms wait).
    const scheduleDeepLinkOpen = (fn) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                try {
                    fn();
                } catch (e) {
                    debugForm.error('Deep-link open failed:', e);
                }
            });
        });
    };

    if (editId) {
        const wid = parseInt(editId, 10);
        if (Number.isFinite(wid) && wid > 0) {
            scheduleDeepLinkOpen(() => {
                if (window.openEditWorkerForm) {
                    window.openEditWorkerForm(wid);
                } else {
                    debugForm.error('openEditWorkerForm function not available');
                }
            });
        }
    } else if (viewId) {
        const wid = parseInt(viewId, 10);
        if (Number.isFinite(wid) && wid > 0) {
            scheduleDeepLinkOpen(() => {
                if (window.openViewWorkerForm) {
                    window.openViewWorkerForm(wid);
                } else if (window.openEditWorkerForm) {
                    window.openEditWorkerForm(wid, true);
                } else {
                    debugForm.error('openViewWorkerForm function not available');
                }
            });
        }
    }
});
