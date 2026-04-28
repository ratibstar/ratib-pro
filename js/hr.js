/**
 * EN: Implements frontend interaction behavior in `js/hr.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/hr.js`.
 */
// HR Management System JavaScript

/** Match PHP hr_normalize_email_string — trim, strip bidi/invisible chars, remove spaces */
function HR_normalizeEmailForSubmit(raw) {
    if (raw == null || raw === '') return '';
    let s = String(raw).trim();
    s = s.replace(/[\u200B-\u200D\uFEFF\u200E\u200F\u202A-\u202E\u061C\u2066-\u2069]/g, '');
    s = s.replace(/\s+/g, '');
    return s;
}

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

/** Site `/api` root (no trailing slash). Empty when Control HR is isolated (use bundled countries). */
function getRatibApiBaseTrimmed() {
    const elCfg = document.getElementById('app-config');
    if ((window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) || (elCfg && elCfg.getAttribute('data-control-hr-api-base'))) {
        return '';
    }
    let b = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
    b = String(b || '').replace(/\/+$/, '');
    if (b) return b;
    const el = document.getElementById('app-config');
    if (el) {
        b = (el.getAttribute('data-api-base') || '').replace(/\/+$/, '');
        if (b) return b;
    }
    const htmlBase = (document.documentElement.getAttribute('data-base-url') || '').replace(/\/+$/, '');
    return htmlBase ? `${htmlBase}/api` : '';
}

// Append control=1 when main-app HR hits /api/hr with control DB (not CP-hosted HR proxy).
function appendHRControlParam(url) {
    const el = document.getElementById('app-config');
    if (!el || el.getAttribute('data-control') !== '1') return url;
    if (window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) return url;
    if (el.getAttribute('data-control-hr-api-base')) return url;
    return url + (url.includes('?') ? '&' : '?') + 'control=1';
}

/**
 * api/hr/* URLs. Control Panel HR uses /control-panel/api/control/hr/*.php (no main Ratib /api/hr).
 */
function hrApiUrl(pathFromApiHr) {
    const raw = (pathFromApiHr.startsWith('/') ? pathFromApiHr : '/' + pathFromApiHr).trim();
    const el = document.getElementById('app-config');
    const cpHr = (window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) || (el && el.getAttribute('data-control-hr-api-base')) || '';
    if (cpHr) {
        const base = String(cpHr).replace(/\/+$/, '');
        const m = raw.match(/^\/hr\/([a-z0-9_.-]+\.php)(\?.*)?$/i);
        if (m) {
            return base + '/' + m[1] + (m[2] || '');
        }
    }
    const p = pathFromApiHr.startsWith('/') ? pathFromApiHr : '/' + pathFromApiHr;
    return appendHRControlParam(getApiBase() + p);
}

// Payroll form: make currency and employee selects searchable (moved from inline script)
function initPayrollSearchableSelects() {
    function makeSelectSearchable(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        const defaultKey = selectId + '_default';
        const savedValue = localStorage.getItem(defaultKey);
        if (savedValue) select.value = savedValue;
        select.addEventListener('change', function() { localStorage.setItem(defaultKey, this.value); });
        select.addEventListener('click', function(e) {
            if (e.target === this && this.size === 1) {
                this.size = 10;
                this.classList.add('hr-select-expanded');
            }
        });
        select.addEventListener('blur', function() {
            setTimeout(() => {
                this.size = 1;
                this.classList.remove('hr-select-expanded');
            }, 200);
        });
        select.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { this.size = 1; this.blur(); }
        });
    }
    makeSelectSearchable('currency');
    makeSelectSearchable('employee_id');
}

// Convert Arabic/Indic numerals to Western (0-9) - ensures English-only display
function toWesternNumerals(str) {
    if (str == null || str === '') return '';
    str = String(str).trim();
    var arabic = '٠١٢٣٤٥٦٧٨٩';
    var persian = '۰۱۲۳۴۵۶۷۸۹';
    
    // Convert Arabic numerals
    str = str.replace(/[٠-٩]/g, function(d) { return String(arabic.indexOf(d)); })
             .replace(/[۰-۹]/g, function(d) { return String(persian.indexOf(d)); });
    
    // Remove any non-numeric characters except decimal point and minus sign
    // This removes Greek letters (Λ, Γ, etc.) and other invalid characters
    str = str.replace(/[^\d.\-]/g, '');
    
    // Ensure only one decimal point
    var parts = str.split('.');
    if (parts.length > 2) {
        str = parts[0] + '.' + parts.slice(1).join('');
    }
    
    return str;
}

// Escape HTML to prevent XSS attacks
function escapeHtml(text) {
    if (text == null || text === '') return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// Global HR variables
let hrModal;
let currentHRModule = null;
let hrData = {};

// Initialize HR system
document.addEventListener('DOMContentLoaded', function() {
    // Check for edit/view parameters and hide table to show form directly
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    // Hide table container when edit/view is present to show form directly
    if (editId || viewId) {
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            const hrHeader = mainContent.querySelector('.hr-header');
            const hrStatsGrid = mainContent.querySelector('.hr-stats-grid');
            const hrModules = mainContent.querySelectorAll('.hr-module');
            if (hrHeader) hrHeader.style.display = 'none';
            if (hrStatsGrid) hrStatsGrid.style.display = 'none';
            hrModules.forEach(module => {
                if (module) module.style.display = 'none';
            });
        }
    }

    try {
        initializeHRSystem();
    } catch (err) {
        console.error('HR: initializeHRSystem failed', err);
    }
    loadHRStats();
    try {
        setupHREventListeners();
    } catch (err) {
        console.error('HR: setupHREventListeners failed', err);
    }
});

// Initialize HR system
function initializeHRSystem() {
    const hrModalEl = document.getElementById('hrModal');
    if (!hrModalEl) {
        console.warn('HR: #hrModal not found; modal features disabled');
        return;
    }
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.warn('HR: Bootstrap Modal not available yet; retrying…');
        var attempts = 0;
        var t = setInterval(function() {
            attempts++;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal && document.getElementById('hrModal')) {
                clearInterval(t);
                try {
                    finishModalSetup(document.getElementById('hrModal'));
                } catch (e) {
                    console.error('HR: deferred modal init failed', e);
                }
            } else if (attempts > 40) {
                clearInterval(t);
                console.error('HR: Bootstrap Modal never became available');
            }
        }, 50);
        return;
    }
    finishModalSetup(hrModalEl);
}

/** Control panel shell nests #hrModal inside .control-content (overflow auto); reparent to body so clicks/backdrop/focus work. */
function reparentHRModalIfControlShell(hrModalEl) {
    const app = document.getElementById('app-config');
    if (!app || app.getAttribute('data-control') !== '1' || !hrModalEl || hrModalEl.parentElement === document.body) {
        return;
    }
    document.body.appendChild(hrModalEl);
}

function finishModalSetup(hrModalEl) {
    reparentHRModalIfControlShell(hrModalEl);
    hrModal = new bootstrap.Modal(hrModalEl);

    // Fix aria-hidden accessibility warning: modal must not have aria-hidden when visible/focused
    hrModalEl.addEventListener('shown.bs.modal', function() {
        this.removeAttribute('aria-hidden');
        this.setAttribute('aria-modal', 'true');
    });
    hrModalEl.addEventListener('hide.bs.modal', function() {
        // Move focus out of modal before hide to avoid "aria-hidden on focused element" console warning
        if (document.activeElement && this.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });
    hrModalEl.addEventListener('hidden.bs.modal', function() {
        this.setAttribute('aria-hidden', 'true');
    });
    (function() {
        var obs = new MutationObserver(function() {
            if (hrModalEl.classList.contains('show') && hrModalEl.getAttribute('aria-hidden') === 'true') {
                hrModalEl.removeAttribute('aria-hidden');
            }
        });
        obs.observe(hrModalEl, { attributes: true, attributeFilter: ['aria-hidden', 'class'] });
    })();
}


// Event delegation for HR modal (replaces inline onclick handlers)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-hr-action]');
    if (!btn) return;
    if (btn.tagName === 'A') e.preventDefault();
    const action = btn.getAttribute('data-hr-action');
    const module = btn.getAttribute('data-hr-module');
    const id = btn.getAttribute('data-hr-id');
    const page = btn.getAttribute('data-hr-page');
    const limit = btn.getAttribute('data-hr-limit');
    if (action === 'closeModal' && typeof closeHRModal === 'function') closeHRModal();
    else if (action === 'pagination' && module && typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination(module, parseInt(page) || 1, parseInt(limit) || 5);
    else if (action === 'showForm' && module && typeof showHRForm === 'function') showHRForm(module, btn.getAttribute('data-hr-form-action') || 'add');
    else if (action === 'viewEmployee' && id) {
        const empId = parseInt(id);
        if (!isNaN(empId) && empId > 0 && typeof viewEmployee === 'function') viewEmployee(empId);
    }
    else if (action === 'editEmployee' && id) {
        const empId = parseInt(id);
        if (!isNaN(empId) && empId > 0 && typeof editEmployee === 'function') editEmployee(empId);
    }
    else if (action === 'deleteEmployee' && id) {
        const empId = parseInt(id);
        if (!isNaN(empId) && empId > 0 && typeof deleteEmployee === 'function') deleteEmployee(empId);
    }
    else if (action === 'bulkDeleteEmployees' && typeof bulkDeleteEmployees === 'function') bulkDeleteEmployees();
    else if (action === 'bulkSetEmployeeStatus' && typeof bulkSetEmployeeStatus === 'function') {
        const status = btn.getAttribute('data-hr-status');
        if (status) bulkSetEmployeeStatus(status);
    }
    else if (action === 'viewAttendance' && id && typeof viewAttendance === 'function') viewAttendance(parseInt(id));
    else if (action === 'editAttendance' && id && typeof editAttendance === 'function') editAttendance(parseInt(id));
    else if (action === 'deleteAttendance' && id && typeof deleteAttendance === 'function') deleteAttendance(parseInt(id));
    else if (action === 'bulkDeleteAttendance' && typeof bulkDeleteAttendance === 'function') bulkDeleteAttendance();
    else if (action === 'bulkSetAttendanceStatus' && typeof bulkSetAttendanceStatus === 'function') {
        const status = btn.getAttribute('data-hr-status');
        if (status) bulkSetAttendanceStatus(status);
    }
    else if (action === 'viewAdvance' && id) {
        const advId = parseInt(id);
        if (!isNaN(advId) && advId > 0 && typeof viewAdvance === 'function') viewAdvance(advId);
    }
    else if (action === 'editAdvance' && id) {
        const advId = parseInt(id);
        if (!isNaN(advId) && advId > 0 && typeof editAdvance === 'function') editAdvance(advId);
    }
    else if (action === 'deleteAdvance' && id) {
        const advId = parseInt(id);
        if (!isNaN(advId) && advId > 0 && typeof deleteAdvance === 'function') deleteAdvance(advId);
    }
    else if (action === 'bulkDeleteAdvances' && typeof bulkDeleteAdvances === 'function') bulkDeleteAdvances();
    else if (action === 'bulkSetAdvanceStatus' && typeof bulkSetAdvanceStatus === 'function') {
        const status = btn.getAttribute('data-hr-status');
        if (status) bulkSetAdvanceStatus(status);
    }
    else if (action === 'viewSalary' && id) {
        const salId = parseInt(id);
        if (!isNaN(salId) && salId > 0 && typeof viewSalary === 'function') viewSalary(salId);
    }
    else if (action === 'editSalary' && id) {
        const salId = parseInt(id);
        if (!isNaN(salId) && salId > 0 && typeof editSalary === 'function') editSalary(salId);
    }
    else if (action === 'deleteSalary' && id) {
        const salId = parseInt(id);
        if (!isNaN(salId) && salId > 0 && typeof deleteSalary === 'function') deleteSalary(salId);
    }
    else if (action === 'viewDocument' && id) {
        const docId = parseInt(id);
        if (!isNaN(docId) && docId > 0 && typeof viewDocument === 'function') viewDocument(docId);
    }
    else if (action === 'editDocument' && id) {
        const docId = parseInt(id);
        if (!isNaN(docId) && docId > 0 && typeof editDocument === 'function') editDocument(docId);
    }
    else if (action === 'downloadDocument' && id) {
        const docId = parseInt(id);
        if (!isNaN(docId) && docId > 0 && typeof downloadDocument === 'function') downloadDocument(docId);
    }
    else if (action === 'deleteDocument' && id) {
        const docId = parseInt(id);
        if (!isNaN(docId) && docId > 0 && typeof deleteDocument === 'function') deleteDocument(docId);
    }
    else if (action === 'viewVehicle' && id) {
        const vehId = parseInt(id);
        if (!isNaN(vehId) && vehId > 0 && typeof viewVehicle === 'function') viewVehicle(vehId);
    }
    else if (action === 'editVehicle' && id) {
        const vehId = parseInt(id);
        if (!isNaN(vehId) && vehId > 0 && typeof editVehicle === 'function') editVehicle(vehId);
    }
    else if (action === 'deleteVehicle' && id) {
        const vehId = parseInt(id);
        if (!isNaN(vehId) && vehId > 0 && typeof deleteVehicle === 'function') deleteVehicle(vehId);
    }
    else if (action === 'bulkDeleteVehicles' && typeof bulkDeleteVehicles === 'function') bulkDeleteVehicles();
    else if (action === 'bulkSetVehicleStatus' && typeof bulkSetVehicleStatus === 'function') {
        const status = btn.getAttribute('data-hr-status');
        if (status) bulkSetVehicleStatus(status);
    }
    else if (action === 'bulkApprovePayroll' && typeof bulkApprovePayroll === 'function') bulkApprovePayroll();
    else if (action === 'bulkRejectPayroll' && typeof bulkRejectPayroll === 'function') bulkRejectPayroll();
    else if (action === 'bulkProcessPayroll' && typeof bulkProcessPayroll === 'function') bulkProcessPayroll();
    else if (action === 'bulkDeletePayroll' && typeof bulkDeletePayroll === 'function') bulkDeletePayroll();
    else if (action === 'bulkActivateDocuments' && typeof bulkActivateDocuments === 'function') bulkActivateDocuments();
    else if (action === 'bulkDeactivateDocuments' && typeof bulkDeactivateDocuments === 'function') bulkDeactivateDocuments();
    else if (action === 'bulkArchiveDocuments' && typeof bulkArchiveDocuments === 'function') bulkArchiveDocuments();
    else if (action === 'bulkDeleteDocuments' && typeof bulkDeleteDocuments === 'function') bulkDeleteDocuments();
    else if (action === 'saveHRSettings' && typeof saveHRSettings === 'function') saveHRSettings();
    else if (action === 'printDocument' && id && typeof printDocument === 'function') printDocument(parseInt(id), btn.getAttribute('data-hr-filename') || '');
    else if (action === 'printHRView' && typeof printHRViewForm === 'function') printHRViewForm();
    else if (action === 'downloadHRView' && typeof downloadHRViewForm === 'function') downloadHRViewForm();
    else if (action === 'employeesSearchBtn') {
        var inp = btn.closest('.input-group') && btn.closest('.input-group').querySelector('[data-hr-action="employeesSearchInput"]');
        if (inp && typeof runEmployeesSearch === 'function') runEmployeesSearch(inp, true);
    } else if (action === 'vehiclesSearchBtn') {
        var inp = btn.closest('.input-group') && btn.closest('.input-group').querySelector('[data-hr-action="vehiclesSearchInput"]');
        if (inp && typeof runVehiclesSearch === 'function') runVehiclesSearch(inp, true);
    } else if (action === 'attendanceSearchBtn') {
        var inp = btn.closest('.input-group') && btn.closest('.input-group').querySelector('[data-hr-action="attendanceSearchInput"]');
        if (inp && typeof runAttendanceSearch === 'function') runAttendanceSearch(inp, true);
    } else if (action === 'advancesSearchBtn') {
        var inp = btn.closest('.input-group') && btn.closest('.input-group').querySelector('[data-hr-action="advancesSearchInput"]');
        if (inp && typeof runAdvancesSearch === 'function') runAdvancesSearch(inp, true);
    } else if (action === 'payrollSearchBtn') {
        var inp = btn.closest('.input-group') && btn.closest('.input-group').querySelector('[data-hr-action="payrollSearchInput"]');
        if (inp && typeof runPayrollSearch === 'function') runPayrollSearch(inp, true);
    }
});
document.addEventListener('change', function(e) {
    const el = e.target.closest('[data-hr-action]');
    if (!el) return;
    const action = el.getAttribute('data-hr-action');
    if (action === 'paginationSelect') {
        const module = el.getAttribute('data-hr-module');
        const limit = el.value;
        if (module && typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination(module, 1, parseInt(limit) || 5);
    } else if (action === 'toggleAllPayroll' && typeof toggleAllPayroll === 'function') {
        toggleAllPayroll(el);
    } else if (action === 'toggleAllDocuments' && typeof toggleAllDocuments === 'function') {
        toggleAllDocuments(el);
    } else if (action === 'toggleAllVehicles' && typeof toggleAllVehicles === 'function') {
        toggleAllVehicles(el);
    } else if (action === 'vehiclesStatusFilter') {
        const sel = el;
        if (sel) {
            window.hrVehiclesFilter = window.hrVehiclesFilter || {};
            window.hrVehiclesFilter.status = sel.value || '';
            if (typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination('vehicles', 1, 5);
        }
    } else if (action === 'vehiclesSearchInput') {
        if (el && typeof runVehiclesSearch === 'function') runVehiclesSearch(el, true);
    } else if (action === 'toggleAllEmployees' && typeof toggleAllEmployees === 'function') {
        toggleAllEmployees(el);
    } else if (action === 'employeesStatusFilter') {
        if (el) {
            window.hrEmployeesFilter = window.hrEmployeesFilter || {};
            window.hrEmployeesFilter.status = el.value || '';
            if (typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination('employees', 1, 5);
        }
    } else if (action === 'employeesSearchInput') {
        if (el && typeof runEmployeesSearch === 'function') runEmployeesSearch(el, true);
    } else if (action === 'toggleAllAttendance' && typeof toggleAllAttendance === 'function') {
        toggleAllAttendance(el);
    } else if (action === 'attendanceStatusFilter') {
        if (el) {
            window.hrAttendanceFilter = window.hrAttendanceFilter || {};
            window.hrAttendanceFilter.status = el.value || '';
            if (typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination('attendance', 1, 5);
        }
    } else if (action === 'attendanceSearchInput') {
        if (el && typeof runAttendanceSearch === 'function') runAttendanceSearch(el, true);
    } else if (action === 'toggleAllAdvances' && typeof toggleAllAdvances === 'function') {
        toggleAllAdvances(el);
    } else if (action === 'advancesStatusFilter') {
        if (el) {
            window.hrAdvancesFilter = window.hrAdvancesFilter || {};
            window.hrAdvancesFilter.status = el.value || '';
            if (typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination('advances', 1, 5);
        }
    } else if (action === 'advancesSearchInput') {
        if (el && typeof runAdvancesSearch === 'function') runAdvancesSearch(el, true);
    } else if (action === 'payrollStatusFilter') {
        if (el) {
            window.hrPayrollFilter = window.hrPayrollFilter || {};
            window.hrPayrollFilter.status = el.value || '';
            if (typeof loadHRContentWithPagination === 'function') loadHRContentWithPagination('payroll', 1, 5);
        }
    } else if (action === 'payrollSearchInput') {
        if (el && typeof runPayrollSearch === 'function') runPayrollSearch(el, true);
    }
});
var vehiclesSearchDebounceTimer;
function runVehiclesSearch(inp, thenFocus) {
    window.hrVehiclesFilter = window.hrVehiclesFilter || {};
    window.hrVehiclesFilter.search = inp && inp.value ? inp.value.trim() : '';
    if (typeof loadHRContentWithPagination === 'function') {
        var p = loadHRContentWithPagination('vehicles', 1, 5);
        if (thenFocus && p && p.then) {
            p.then(function() {
                var el = document.querySelector('[data-hr-action="vehiclesSearchInput"]');
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        }
    }
}
document.addEventListener('input', function(e) {
    var el = e.target.closest('[data-hr-action="vehiclesSearchInput"]');
    if (el) {
        clearTimeout(vehiclesSearchDebounceTimer);
        vehiclesSearchDebounceTimer = setTimeout(function() { runVehiclesSearch(el, true); }, 400);
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    var el = e.target.closest('[data-hr-action="vehiclesSearchInput"]');
    if (el) { e.preventDefault(); clearTimeout(vehiclesSearchDebounceTimer); runVehiclesSearch(el, true); return; }
    el = e.target.closest('[data-hr-action="employeesSearchInput"]');
    if (el) { e.preventDefault(); clearTimeout(employeesSearchDebounceTimer); runEmployeesSearch(el, true); return; }
    el = e.target.closest('[data-hr-action="attendanceSearchInput"]');
    if (el) { e.preventDefault(); clearTimeout(attendanceSearchDebounceTimer); runAttendanceSearch(el, true); return; }
    el = e.target.closest('[data-hr-action="advancesSearchInput"]');
    if (el) { e.preventDefault(); clearTimeout(advancesSearchDebounceTimer); runAdvancesSearch(el, true); }
});
var employeesSearchDebounceTimer, attendanceSearchDebounceTimer, advancesSearchDebounceTimer, payrollSearchDebounceTimer;
function runEmployeesSearch(inp, thenFocus) {
    window.hrEmployeesFilter = window.hrEmployeesFilter || {};
    window.hrEmployeesFilter.search = inp && inp.value ? inp.value.trim() : '';
    if (typeof loadHRContentWithPagination === 'function') {
        var p = loadHRContentWithPagination('employees', 1, 5);
        if (thenFocus && p && p.then) {
            p.then(function() {
                var el = document.querySelector('[data-hr-action="employeesSearchInput"]');
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        }
    }
}
function runAttendanceSearch(inp, thenFocus) {
    window.hrAttendanceFilter = window.hrAttendanceFilter || {};
    window.hrAttendanceFilter.search = inp && inp.value ? inp.value.trim() : '';
    if (typeof loadHRContentWithPagination === 'function') {
        var p = loadHRContentWithPagination('attendance', 1, 5);
        if (thenFocus && p && p.then) {
            p.then(function() {
                var el = document.querySelector('[data-hr-action="attendanceSearchInput"]');
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        }
    }
}
function runAdvancesSearch(inp, thenFocus) {
    window.hrAdvancesFilter = window.hrAdvancesFilter || {};
    window.hrAdvancesFilter.search = inp && inp.value ? inp.value.trim() : '';
    if (typeof loadHRContentWithPagination === 'function') {
        var p = loadHRContentWithPagination('advances', 1, 5);
        if (thenFocus && p && p.then) {
            p.then(function() {
                var el = document.querySelector('[data-hr-action="advancesSearchInput"]');
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        }
    }
}
function runPayrollSearch(inp, thenFocus) {
    window.hrPayrollFilter = window.hrPayrollFilter || {};
    window.hrPayrollFilter.search = inp && inp.value ? inp.value.trim() : '';
    if (typeof loadHRContentWithPagination === 'function') {
        var p = loadHRContentWithPagination('payroll', 1, 5);
        if (thenFocus && p && p.then) {
            p.then(function() {
                var el = document.querySelector('[data-hr-action="payrollSearchInput"]');
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        }
    }
}
document.addEventListener('input', function(e) {
    var el = e.target.closest('[data-hr-action="employeesSearchInput"]');
    if (el) {
        clearTimeout(employeesSearchDebounceTimer);
        employeesSearchDebounceTimer = setTimeout(function() { runEmployeesSearch(el, true); }, 400);
        return;
    }
    el = e.target.closest('[data-hr-action="attendanceSearchInput"]');
    if (el) {
        clearTimeout(attendanceSearchDebounceTimer);
        attendanceSearchDebounceTimer = setTimeout(function() { runAttendanceSearch(el, true); }, 400);
        return;
    }
    el = e.target.closest('[data-hr-action="advancesSearchInput"]');
    if (el) {
        clearTimeout(advancesSearchDebounceTimer);
        advancesSearchDebounceTimer = setTimeout(function() { runAdvancesSearch(el, true); }, 400);
        return;
    }
    el = e.target.closest('[data-hr-action="payrollSearchInput"]');
    if (el) {
        clearTimeout(payrollSearchDebounceTimer);
        payrollSearchDebounceTimer = setTimeout(function() { runPayrollSearch(el, true); }, 400);
    }
});

// Setup event listeners
function setupHREventListeners() {
    // Auto-refresh stats every 30 seconds
    // Store interval ID for cleanup
    window.hrStatsInterval = setInterval(loadHRStats, 30000);
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (window.hrStatsInterval) {
            clearInterval(window.hrStatsInterval);
        }
    });
    
    // Add Employee button
    const addEmployeeBtn = document.getElementById('addEmployeeBtn');
    if (addEmployeeBtn) {
        addEmployeeBtn.addEventListener('click', () => showHRForm('employees', 'add'));
    }
    
    // Mark Attendance button
    const markAttendanceBtn = document.getElementById('markAttendanceBtn');
    if (markAttendanceBtn) {
        markAttendanceBtn.addEventListener('click', () => showHRForm('attendance', 'mark'));
    }
    
    // Configure Settings button
    const configureSettingsBtn = document.getElementById('configureSettingsBtn');
    if (configureSettingsBtn) {
        configureSettingsBtn.addEventListener('click', () => showHRForm('settings'));
    }
    
    // Stat cards - click to view module
    document.querySelectorAll('.stat-card[data-hr-module]').forEach(card => {
        card.addEventListener('click', function() {
            const module = this.getAttribute('data-hr-module');
            showHRForm(module);
        });
    });
    
    // Module cards - click to view module
    document.querySelectorAll('.module-card[data-hr-module]').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button inside
            if (e.target.closest('button')) return;
            const module = this.getAttribute('data-hr-module');
            showHRForm(module);
        });
    });
    
    // Module action buttons
    document.querySelectorAll('[data-hr-action][data-hr-module]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            const module = this.getAttribute('data-hr-module');
            const action = this.getAttribute('data-hr-action');
            showHRForm(module, action);
        });
    });
}

// Load HR statistics
async function loadHRStats() {
    if (!navigator.onLine) {
        updateHRStats({
            employees: 0,
            attendance: 0,
            advances: 0,
            salaries: 0,
            documents: 0,
            cars: 0
        });
        return;
    }
    try {
        const response = await fetch(hrApiUrl('/hr/stats.php'));
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.success) {
            updateHRStats(data.data);
        } else {
            throw new Error(data.message || 'Failed to load HR stats');
        }
    } catch (error) {
        var isNetworkError = error instanceof TypeError && (error.message === 'Failed to fetch' || error.message.includes('fetch'));
        if (!isNetworkError) {
            console.error('Error loading HR stats:', error);
        }
        updateHRStats({
            employees: 0,
            attendance: 0,
            advances: 0,
            salaries: 0,
            documents: 0,
            cars: 0
        });
    }
}

// Update HR statistics display
function updateHRStats(stats) {
    document.getElementById('employeeCount').textContent = stats.employees || 0;
    document.getElementById('attendanceCount').textContent = stats.attendance || 0;
    document.getElementById('advanceCount').textContent = stats.advances || 0;
    document.getElementById('salaryCount').textContent = stats.salaries || 0;
    document.getElementById('documentCount').textContent = stats.documents || 0;
    document.getElementById('carCount').textContent = stats.cars || 0;
}

// Show HR form modal
function showHRForm(module, action = 'list') {
    currentHRModule = module;
    window.currentHRModule = module;
    window.currentHRAction = action;

    const modalTitle = document.getElementById('hrModalTitle');
    const modalBody = document.getElementById('hrModalBody');
    
    // Set modal title
    modalTitle.textContent = getHRModuleTitle(module, action);
    
    // Load module content (5 rows per page for all list views)
    loadHRModuleContent(module, action, modalBody, 1, 5);
    
    // Show modal
    hrModal.show();
}

// Get HR module title
function getHRModuleTitle(module, action) {
    const titles = {
        'employees': {
            'list': 'Employee Management',
            'view': 'Employee Management',
            'add': 'Add New Employee',
            'edit': 'Edit Employee'
        },
        'attendance': {
            'list': 'Attendance Records',
            'view': 'Attendance Records',
            'mark': 'Mark Attendance'
        },
        'advances': {
            'list': 'Employee Advances',
            'view': 'Employee Advances',
            'add': 'New Advance Request'
        },
        'salaries': {
            'list': 'Payroll Management',
            'view': 'Payroll Management',
            'process': 'Process Payroll'
        },
        'documents': {
            'list': 'Document Management',
            'view': 'Document Management',
            'upload': 'Upload Document'
        },
        'cars': {
            'list': 'Vehicle Management',
            'view': 'Vehicle Management',
            'add': 'Add New Vehicle'
        },
        'vehicles': {
            'list': 'Vehicle Management',
            'view': 'Vehicle Management',
            'add': 'Add New Vehicle'
        },
        'settings': {
            'list': 'HR Settings'
        }
    };
    
    return titles[module]?.[action] || 'HR Management';
}

// Load HR module content
async function loadHRModuleContent(module, action, container, page = 1, limit = 5) {
    container.innerHTML = '<div class="hr-loading">Loading...</div>';
    
    try {
        let content = '';
        
        switch (module) {
            case 'employees': {
                const empFilter = window.hrEmployeesFilter || {};
                content = await loadEmployeesContent(action, page, limit, empFilter.status || '', empFilter.search || '', empFilter.department || '');
                break;
            }
            case 'attendance': {
                const attFilter = window.hrAttendanceFilter || {};
                content = await loadAttendanceContent(action, page, limit, attFilter.status || '', attFilter.search || '');
                break;
            }
            case 'advances': {
                const advFilter = window.hrAdvancesFilter || {};
                content = await loadAdvancesContent(action, page, limit, advFilter.status || '', advFilter.search || '');
                break;
            }
            case 'salaries':
            case 'payroll': {
                const payrollFilter = window.hrPayrollFilter || {};
                content = await loadPayrollContent(action, page, limit, payrollFilter.status || '', payrollFilter.search || '');
                break;
            }
            case 'documents':
                content = await loadDocumentsContent(action, page, limit);
                break;
            case 'cars':
            case 'vehicles': {
                const filter = window.hrVehiclesFilter || {};
                const status = filter.status || '';
                const search = filter.search || '';
                content = await loadVehiclesContent(action, page, limit, status, search);
                break;
            }
            case 'settings':
                content = await loadSettingsContent();
                break;
            default:
                content = '<div class="hr-message error">Invalid module</div>';
        }
        
        container.innerHTML = content;
        setupHRFormHandlers();
        
        // Sanitize HR settings inputs if this is the settings module
        if (module === 'settings') {
            // Immediate sanitization
            setTimeout(() => {
                sanitizeHRSettingsInputs();
            }, 50);
            
            // Also run migration to convert any existing Arabic numerals in database
            setTimeout(() => {
                migrateHRSettingsNumerals();
            }, 200);
            
            // Additional sanitization after a short delay to catch any late-rendering issues
            setTimeout(() => {
                sanitizeHRSettingsInputs();
            }, 500);
        }
        
        if (typeof initHRDatePickers === 'function') {
            setTimeout(function() { initHRDatePickers(container); }, 150);
        }
        
        // Re-apply permissions after HR content is rendered - wait a bit to ensure permissions are loaded
        setTimeout(() => {
            if (window.UserPermissions) {
                if (window.UserPermissions.loaded) {
                    window.UserPermissions.applyPermissions();
                } else {
                    // If permissions not loaded yet, wait for them to load
                    window.UserPermissions.load().then(() => {
                        window.UserPermissions.applyPermissions();
                    });
                }
            }
        }, 100);
        
    } catch (error) {
        console.error('Error loading HR module content:', error);
        container.innerHTML = '<div class="hr-message error">Failed to load content</div>';
    }
}

// Load HR content
async function loadHRContent(module, action = null, page = 1, limit = 5) {
    const container = document.getElementById('hrContent');
    if (!container) return;
    
    window.currentHRModule = module;
    window.currentHRAction = action;
    
    await loadHRModuleContent(module, action, container, page, limit);
}

// Load HR content with pagination (for modal system)
async function loadHRContentWithPagination(module, page = 1, limit = 5) {
    const modalBody = document.getElementById('hrModalBody');
    if (!modalBody) return;
    
    
    window.currentHRModule = module;
    window.currentHRAction = 'list';
    
    await loadHRModuleContent(module, 'list', modalBody, page, limit);
}

// All form functions are now in hr-forms.js

// Migrate existing Arabic numerals in database to Western numerals
async function migrateHRSettingsNumerals() {
    try {
        // Use GET instead of POST to avoid permission issues
        const response = await fetch(hrApiUrl('/hr/settings.php?action=migrate-numerals'));
        if (!response.ok) {
            // If migration fails, that's okay - we'll handle conversion on display
            return;
        }
        const result = await response.json();
        if (result.success && result.updated > 0) {
            console.log(`Migrated ${result.updated} HR settings value(s) from Arabic to Western numerals`);
            // Reload settings after migration
            setTimeout(() => {
                const modalBody = document.getElementById('hrModalBody');
                if (modalBody && window.currentHRModule === 'settings') {
                    loadHRModuleContent('settings', 'list', modalBody);
                }
            }, 500);
        }
    } catch (error) {
        // Silent fail - migration is optional, conversion happens on display anyway
        console.debug('HR settings migration check failed:', error);
    }
}

// Sanitize HR settings inputs - force Western numerals and add real-time conversion
function sanitizeHRSettingsInputs() {
    const inputs = ['working_hours', 'overtime_rate', 'payroll_day', 'tax_rate'];
    
    // STRICT: allow ONLY ASCII 0-9 and period (no Arabic, no Greek, nothing else)
    const asciiNumericOnly = (val) => {
        if (!val) return '';
        let s = String(val);
        let out = '';
        for (let i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c >= 48 && c <= 57) out += s[i];  // 0-9
            else if (c === 46 && out.indexOf('.') === -1) out += '.';  // one decimal only
        }
        return out;
    };
    
    inputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            // Force value to ASCII digits only
            input.value = asciiNumericOnly(input.value);
            
            // Set default if empty or invalid
            const defaults = { 'working_hours': '8', 'overtime_rate': '1.5', 'payroll_day': '25', 'tax_rate': '15' };
            if (!input.value || isNaN(parseFloat(input.value))) {
                input.value = defaults[inputId] || '';
            }
            
            // Block non-ASCII numeric keys
            input.addEventListener('keypress', function(e) {
                var c = e.which || e.keyCode;
                if (c === 46) {
                    if (this.value.indexOf('.') !== -1) e.preventDefault();
                    return;
                }
                if (c < 48 || c > 57) e.preventDefault();
            });
            
            // On input, strip anything that isn't 0-9 or .
            input.addEventListener('input', function(e) {
                var before = this.value;
                var after = asciiNumericOnly(before);
                if (after !== before) {
                    var pos = this.selectionStart;
                    this.value = after;
                    this.setSelectionRange(Math.min(pos, after.length), Math.min(pos, after.length));
                }
            });
            
            input.addEventListener('change', function(e) {
                this.value = asciiNumericOnly(this.value);
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var pasted = (e.clipboardData || window.clipboardData).getData('text');
                var start = this.selectionStart, end = this.selectionEnd;
                var current = this.value;
                var newVal = current.slice(0, start) + asciiNumericOnly(pasted) + current.slice(end);
                this.value = asciiNumericOnly(newVal);
            });
            
            input.addEventListener('blur', function(e) {
                this.value = asciiNumericOnly(this.value);
            });
            
            // Force LTR direction and Western numerals via attributes
            input.setAttribute('dir', 'ltr');
            input.setAttribute('lang', 'en');
            input.style.direction = 'ltr';
            input.style.textAlign = 'left';
        }
    });
}

// Load settings content
async function loadSettingsContent() {
    try {
        // Load current settings from API
        const response = await fetch(hrApiUrl('/hr/settings.php?action=get'));
        const result = await response.json();
        
        const settings = result.success && result.data ? result.data : {};
        
        // Convert all values to Western numerals to prevent Arabic display
        // Use String() to ensure we're working with strings, then convert
        const workingHours = toWesternNumerals(String(settings.working_hours || '8').trim());
        const overtimeRate = toWesternNumerals(String(settings.overtime_rate || '1.5').trim());
        const payrollDay = toWesternNumerals(String(settings.payroll_day || '25').trim());
        const taxRate = toWesternNumerals(String(settings.tax_rate || '15').trim());
        
        // Double-check: ensure no Arabic numerals remain and remove any invalid characters
        const ensureWestern = (val) => {
            if (!val) return val;
            let result = String(val).trim();
            
            // Remove Arabic numerals
            const arabic = '٠١٢٣٤٥٦٧٨٩';
            const persian = '۰۱۲۳۴۵۶۷۸۹';
            for (let i = 0; i <= 9; i++) {
                result = result.replace(new RegExp(arabic[i], 'g'), String(i));
                result = result.replace(new RegExp(persian[i], 'g'), String(i));
            }
            
            // Remove any non-numeric characters except decimal point and minus sign
            // This will remove Greek letters like Λ, Γ, etc.
            result = result.replace(/[^\d.\-]/g, '');
            
            // Ensure only one decimal point
            const parts = result.split('.');
            if (parts.length > 2) {
                result = parts[0] + '.' + parts.slice(1).join('');
            }
            
            return result;
        };
        
        const finalWorkingHours = ensureWestern(workingHours) || '8';
        const finalOvertimeRate = ensureWestern(overtimeRate) || '1.5';
        const finalPayrollDay = ensureWestern(payrollDay) || '25';
        const finalTaxRate = ensureWestern(taxRate) || '15';
        
        // Use ONLY ASCII digits 0-9 and period - no other script can appear
        const asciiOnly = (v) => String(v).replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1') || '';
        const wh = asciiOnly(finalWorkingHours) || '8';
        const ot = asciiOnly(finalOvertimeRate) || '1.5';
        const pd = asciiOnly(finalPayrollDay) || '25';
        const tr = asciiOnly(finalTaxRate) || '15';
        
        return `
            <div class="hr-form">
                <div class="form-content">
                    <div class="form-group">
                        <label for="working_hours">Working Hours</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="working_hours" value="${wh}" placeholder="8" required dir="ltr" lang="en" data-min="1" data-max="24">
                    </div>
                    <div class="form-group">
                        <label for="overtime_rate">Overtime Rate</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="overtime_rate" value="${ot}" placeholder="1.5" required dir="ltr" lang="en" data-min="1" data-max="24">
                    </div>
                    <div class="form-group">
                        <label for="payroll_day">Payroll Day</label>
                        <input type="text" inputmode="decimal" pattern="[0-9]+" maxlength="2" class="form-control hr-settings-numeric" id="payroll_day" value="${pd}" placeholder="25" required dir="ltr" lang="en" data-min="1" data-max="31">
                    </div>
                    <div class="form-group">
                        <label for="tax_rate">Tax Rate (%)</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="tax_rate" value="${tr}" placeholder="15" required dir="ltr" lang="en" data-min="0" data-max="100">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" data-hr-action="saveHRSettings">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error loading HR settings:', error);
        // Return form with default values if API fails - ensure Western numerals
        return `
            <div class="hr-form">
                <div class="form-content">
                    <div class="form-group">
                        <label for="working_hours">Working Hours</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="working_hours" value="8" placeholder="8" required dir="ltr" lang="en" data-min="1" data-max="24">
                    </div>
                    <div class="form-group">
                        <label for="overtime_rate">Overtime Rate</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="overtime_rate" value="1.5" placeholder="1.5" required dir="ltr" lang="en" data-min="1" data-max="24">
                    </div>
                    <div class="form-group">
                        <label for="payroll_day">Payroll Day</label>
                        <input type="text" inputmode="decimal" pattern="[0-9]+" maxlength="2" class="form-control hr-settings-numeric" id="payroll_day" value="25" placeholder="25" required dir="ltr" lang="en" data-min="1" data-max="31">
                    </div>
                    <div class="form-group">
                        <label for="tax_rate">Tax Rate (%)</label>
                        <input type="text" inputmode="decimal" pattern="[0-9.]+" maxlength="5" class="form-control hr-settings-numeric" id="tax_rate" value="15" placeholder="15" required dir="ltr" lang="en" data-min="0" data-max="100">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" data-hr-action="saveHRSettings">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        `;
    }
}

// Setup form handlers
function setupHRFormHandlers() {
    // Employee form handler
    const employeeForm = document.getElementById('employeeForm');
    if (employeeForm) {
        // Remove existing listener to avoid duplicates (clone the form)
        const newForm = employeeForm.cloneNode(true);
        employeeForm.parentNode.replaceChild(newForm, employeeForm);
        // cloneNode copies data-listener-* but not listeners — city dropdown never loads without this
        // Country→city uses delegated change on #hrModal (see countries-cities-handler.js)

        // Get the new form reference
        const updatedForm = document.getElementById('employeeForm');
        if (updatedForm) {
            updatedForm.addEventListener('submit', handleEmployeeSubmit);
            updatedForm.setAttribute('data-submit-handler', 'attached');
        }
        
        // Initialize country and city dropdowns for employee form
        setTimeout(async () => {
            if (typeof populateCountryDropdown === 'function') {
                await populateCountryDropdown();
            } else if (typeof window.populateCountryDropdown === 'function') {
                await window.populateCountryDropdown();
            } else {
                // Fallback: manually populate country dropdown from API
                const countrySelect = document.getElementById('country');
                const citySelect = document.getElementById('city');
                
                if (countrySelect) {
                    try {
                        const apiRoot = typeof getRatibApiBaseTrimmed === 'function' ? getRatibApiBaseTrimmed() : '';
                        const url = `${apiRoot}/admin/get_countries_cities.php?action=countries`;
                        
                        const response = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' }
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            
                            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                                // Ensure select has proper attributes
                                countrySelect.setAttribute('dir', 'ltr');
                                countrySelect.setAttribute('lang', 'en');
                                // Populate countries if not already populated
                                if (countrySelect.options.length <= 1) {
                                    countrySelect.innerHTML = '<option value="">Select Country</option>';
                                    data.countries.sort().forEach(country => {
                                        const option = document.createElement('option');
                                        option.value = country;
                                        option.textContent = country;
                                        countrySelect.appendChild(option);
                                    });
                                }
                                
                                // Setup city loading on country change
                                if (citySelect) {
                                    // Ensure city select has proper attributes
                                    citySelect.setAttribute('dir', 'ltr');
                                    citySelect.setAttribute('lang', 'en');
                                    countrySelect.addEventListener('change', async function() {
                                        const selectedCountry = this.value;
                                        citySelect.innerHTML = '<option value="">Select Country First</option>';
                                        
                                        if (selectedCountry) {
                                            try {
                                                const citiesUrl = `${apiRoot}/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(selectedCountry)}`;
                                                const citiesResponse = await fetch(citiesUrl, {
                                                    method: 'GET',
                                                    credentials: 'same-origin',
                                                    headers: { 'Content-Type': 'application/json' }
                                                });
                                                
                                                if (citiesResponse.ok) {
                                                    const citiesData = await citiesResponse.json();
                                                    if (citiesData.success && Array.isArray(citiesData.cities)) {
                                                        citiesData.cities.forEach(city => {
                                                            const option = document.createElement('option');
                                                            option.value = city;
                                                            option.textContent = city;
                                                            citySelect.appendChild(option);
                                                        });
                                                    }
                                                }
                                            } catch (error) {
                                                console.error('Failed to load cities:', error);
                                            }
                                        }
                                    });
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Failed to load countries:', error);
                    }
                }
            }
        }, 300);
    }
    
    // Attendance form handler
    const attendanceForm = document.getElementById('attendanceForm');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', handleAttendanceSubmit);
        loadEmployeesForAttendance();
        setTimeout(() => {
            if (typeof initTimePickers === 'function') {
                initTimePickers();
            }
        }, 200);
    }
    
    // Advances form handler
    const advancesForm = document.getElementById('advancesForm');
    if (advancesForm) {
        advancesForm.addEventListener('submit', handleAdvancesSubmit);
    }
    
    // Payroll form handler
    const payrollForm = document.getElementById('payrollForm');
    if (payrollForm) {
        payrollForm.addEventListener('submit', handlePayrollSubmit);
        setTimeout(initPayrollSearchableSelects, 500);
    }
    
    // Documents form handler
    const documentsForm = document.getElementById('documentsForm');
    if (documentsForm) {
        documentsForm.addEventListener('submit', handleDocumentsSubmit);
    }
    
    // Vehicles form handler
    const vehiclesForm = document.getElementById('vehiclesForm');
    if (vehiclesForm) {
        vehiclesForm.addEventListener('submit', handleVehiclesSubmit);
    }
}

// Handle employee form submission
async function handleEmployeeSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        const hiddenIdInput = form.querySelector('input[name="id"][type="hidden"]');
        const isEditMode = form.hasAttribute('data-edit-mode') || hiddenIdInput;
        const employeeId = form.getAttribute('data-employee-id') || (hiddenIdInput ? hiddenIdInput.value : null);
        
        // Email: trim, strip invisible/RTL chars and spaces (matches api/hr/employees.php normalization)
        if (data.email != null && data.email !== '') {
            data.email = HR_normalizeEmailForSubmit(String(data.email));
        }
        if (!isEditMode && (data.email === undefined || data.email === '')) {
            showHRMessage('Email is required. Scroll up in this form if you do not see the Email field.', 'error');
            form.dataset.submitting = 'false';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            return;
        }
        if (data.email && typeof data.email === 'string' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            showHRMessage('Invalid email. Example: name@company.com — no spaces; domain must have a dot (e.g. .com).', 'error');
            form.dataset.submitting = 'false';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            return;
        }
        
        if (isEditMode && employeeId) {
            // Handle edit mode
            try {
            showHRMessage('Updating employee...', 'info');
            
            // Remove id from data if it's in the body (we use URL parameter)
            const updateData = { ...data };
            if (updateData.id) {
                delete updateData.id;
            }
            
            const updateUrl = hrApiUrl(`/hr/employees.php?action=update&id=${employeeId}`);
            const updateResponse = await fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updateData)
            });
            
            // Check if response is ok
            if (!updateResponse.ok) {
                const errorText = await updateResponse.text();
                try {
                    const errorResult = JSON.parse(errorText);
                    showHRMessage(errorResult.message || 'Failed to update employee', 'error');
                } catch (e) {
                    showHRMessage('Server error: ' + updateResponse.status + ' ' + updateResponse.statusText, 'error');
                }
                return;
            }
            
            const responseText = await updateResponse.text();
            let updateResult;
            
            try {
                updateResult = JSON.parse(responseText);
            } catch (e) {
                showHRMessage('Server error: Invalid response format', 'error');
                return;
            }
            
            if (updateResult.success) {
                
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                showHRMessage('Employee updated successfully!', 'success');
                
                // Refresh stats
                loadHRStats();
                
                // Close modal first, then refresh employee list
                if (typeof closeHRModal === 'function') {
                    closeHRModal();
                } else if (hrModal && typeof hrModal.hide === 'function') {
                    hrModal.hide();
                }
                
                // Wait for modal to close, then reload and show updated list
                setTimeout(() => {
                    // Force reload by clearing the container first and reloading directly
                    const modalBody = document.getElementById('hrModalBody');
                    
                    if (modalBody) {
                        // Clear first to force fresh load
                        modalBody.innerHTML = '<div class="hr-loading">Refreshing...</div>';
                        
                        // Force reload with fresh data - use direct load instead of showHRForm
                        loadHRModuleContent('employees', 'list', modalBody, 1, 5).then(() => {
                            // Show the modal with fresh data
                            const modalTitle = document.getElementById('hrModalTitle');
                            if (modalTitle) {
                                modalTitle.textContent = 'Employees';
                            }
                            
                            if (hrModal && typeof hrModal.show === 'function') {
                                hrModal.show();
                            }
                        }).catch(error => {
                            console.error('Error reloading employees:', error);
                            modalBody.innerHTML = '<div class="hr-message error">Failed to reload employees: ' + error.message + '</div>';
                        });
                    } else {
                        // Fallback: use showHRForm
                        showHRForm('employees', 'list');
                    }
                }, 300);
            } else {
                showHRMessage(updateResult.message || 'Failed to update employee', 'error');
            }
            } catch (error) {
                console.error('Error updating employee:', error);
                showHRMessage('Failed to update employee: ' + error.message, 'error');
            }
            return;
        }
        
        // Handle add mode
        try {
        const response = await fetch(hrApiUrl('/hr/employees.php?action=add'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check if response is ok before parsing
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorResult = JSON.parse(errorText);
                errorMessage = errorResult.message || errorMessage;
            } catch (e) {
                // Not JSON, use raw text
                errorMessage = errorText || errorMessage;
            }
            throw new Error(errorMessage);
        }
        
        // Parse JSON response
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            throw new Error('Invalid JSON response from server');
        }
        
        if (result.success) {
            // Refresh history if UnifiedHistory modal is open
            if (window.unifiedHistory) {
                await window.unifiedHistory.refreshIfOpen();
            }
            
            showHRMessage('Employee added successfully!', 'success');
            hrModal.hide();
            loadHRStats();
            // Refresh employee dropdowns in other forms
            refreshEmployeeDropdowns();
            
            // Refresh employee list to show the new employee
            setTimeout(() => {
                showHRForm('employees', 'list');
            }, 500);
        } else {
            showHRMessage(result.message || 'Failed to add employee', 'error');
        }
        } catch (error) {
            console.error('Error adding employee:', error);
            
            // Check if it's a JSON parse error
            if (error instanceof SyntaxError && error.message.includes('JSON')) {
                console.error('Invalid JSON response from server. This usually indicates a PHP error.');
                showHRMessage('Server error: Invalid response format. Please check the server logs.', 'error');
            } else {
                showHRMessage('Failed to add employee: ' + error.message, 'error');
            }
        } finally {
            // Re-enable form submission
            form.dataset.submitting = 'false';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    } catch (error) {
        // Catch any unexpected errors from the outer try block
        console.error('Unexpected error in handleEmployeeSubmit:', error);
        showHRMessage('An unexpected error occurred: ' + error.message, 'error');
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Handle attendance form submission
async function handleAttendanceSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const response = await fetch(hrApiUrl('/hr/attendance.php?action=add'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check response status before parsing
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                errorMessage = errorText.substring(0, 200) || errorMessage;
            }
            showHRMessage(errorMessage, 'error');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Attendance marked successfully!', 'success');
            hrModal.hide();
            loadHRStats();
        } else {
            showHRMessage(result.message || 'Failed to mark attendance', 'error');
        }
    } catch (error) {
        console.error('Error marking attendance:', error);
        showHRMessage('Failed to mark attendance: ' + (error.message || 'Unknown error'), 'error');
    } finally {
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Handle advances form submission
async function handleAdvancesSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const response = await fetch(hrApiUrl('/hr/advances.php?action=add'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check response status before parsing
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                errorMessage = errorText.substring(0, 200) || errorMessage;
            }
            showHRMessage(errorMessage, 'error');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Advance request submitted successfully!', 'success');
            hrModal.hide();
            loadHRStats();
            // Refresh the advances list if we're viewing it
            if (window.currentHRModule === 'advances') {
                loadHRContent('advances');
            }
        } else {
            showHRMessage(result.message || 'Failed to submit advance request', 'error');
        }
    } catch (error) {
        console.error('Error submitting advance request:', error);
        
        // Check if it's a JSON parse error
        if (error instanceof SyntaxError && error.message.includes('JSON')) {
            console.error('Invalid JSON response from server. This usually indicates a PHP error.');
            showHRMessage('Server error: Invalid response format. Please check the server logs.', 'error');
        } else {
            showHRMessage('Failed to submit advance request: ' + error.message, 'error');
        }
    } finally {
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Handle payroll form submission
async function handlePayrollSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const response = await fetch(hrApiUrl('/hr/salaries.php?action=add'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check response status before parsing
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                errorMessage = errorText.substring(0, 200) || errorMessage;
            }
            showHRMessage(errorMessage, 'error');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Payroll processed successfully!', 'success');
            loadHRStats();
            // Refresh the table without closing modal
            await refreshPayrollTableContent();
        } else {
            showHRMessage(result.message || 'Failed to process payroll', 'error');
        }
    } catch (error) {
        console.error('Error processing payroll:', error);
        
        // Check if it's a JSON parse error
        if (error instanceof SyntaxError && error.message.includes('JSON')) {
            console.error('Invalid JSON response from server. This usually indicates a PHP error.');
            showHRMessage('Server error: Invalid response format. Please check the server logs.', 'error');
        } else {
            showHRMessage('Failed to process payroll: ' + error.message, 'error');
        }
    } finally {
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Handle documents form submission
async function handleDocumentsSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    }
    
    try {
        const formData = new FormData(form);
        
        const response = await fetch(hrApiUrl('/hr/documents.php?action=add'), {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Document uploaded successfully!', 'success');
            hrModal.hide();
            loadHRStats();
            // Refresh the documents list if we're viewing it
            if (window.currentHRModule === 'documents') {
                loadHRContent('documents');
            }
        } else {
            showHRMessage(result.message || 'Failed to upload document', 'error');
        }
    } catch (error) {
        console.error('Error uploading document:', error);
        showHRMessage('Failed to upload document: ' + (error.message || 'Unknown error'), 'error');
    } finally {
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Handle vehicles form submission
async function handleVehiclesSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    
    // Prevent double submission
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const response = await fetch(hrApiUrl('/hr/cars.php?action=add'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check response status before parsing
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                errorMessage = errorText.substring(0, 200) || errorMessage;
            }
            showHRMessage(errorMessage, 'error');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Vehicle added successfully!', 'success');
            hrModal.hide();
            loadHRStats();
            if (window.currentHRModule === 'vehicles' || window.currentHRModule === 'cars') {
                loadHRContent('vehicles');
            }
        } else {
            showHRMessage(result.message || 'Failed to add vehicle', 'error');
        }
    } catch (error) {
        console.error('Error adding vehicle:', error);
        showHRMessage('Failed to add vehicle: ' + (error.message || 'Unknown error'), 'error');
    } finally {
        // Re-enable form submission
        form.dataset.submitting = 'false';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
}

// Initialize hour/minute dropdowns for time pickers
function initTimePickers() {
    // Initialize check-in time picker
    const checkInHour = document.getElementById('check_in_hour');
    const checkInMinute = document.getElementById('check_in_minute');
    const checkInTime = document.getElementById('check_in_time');
    
    if (checkInHour && checkInMinute && checkInTime) {
        // Populate hours (0-23)
        if (checkInHour.options.length <= 1) {
            for (let h = 0; h < 24; h++) {
                const option = document.createElement('option');
                option.value = String(h).padStart(2, '0');
                option.textContent = String(h).padStart(2, '0');
                checkInHour.appendChild(option);
            }
        }
        
        // Populate minutes (0-59, step 1)
        if (checkInMinute.options.length <= 1) {
            for (let m = 0; m < 60; m++) {
                const option = document.createElement('option');
                option.value = String(m).padStart(2, '0');
                option.textContent = String(m).padStart(2, '0');
                checkInMinute.appendChild(option);
            }
        }
        
        // Update hidden field when dropdowns change
        function updateCheckInTime() {
            const hour = checkInHour.value;
            const minute = checkInMinute.value;
            if (hour && minute) {
                checkInTime.value = hour + ':' + minute + ':00';
            } else {
                checkInTime.value = '';
            }
        }
        
        checkInHour.addEventListener('change', updateCheckInTime);
        checkInMinute.addEventListener('change', updateCheckInTime);
    }
    
    // Initialize check-out time picker
    const checkOutHour = document.getElementById('check_out_hour');
    const checkOutMinute = document.getElementById('check_out_minute');
    const checkOutTime = document.getElementById('check_out_time');
    
    if (checkOutHour && checkOutMinute && checkOutTime) {
        // Populate hours (0-23)
        if (checkOutHour.options.length <= 1) {
            for (let h = 0; h < 24; h++) {
                const option = document.createElement('option');
                option.value = String(h).padStart(2, '0');
                option.textContent = String(h).padStart(2, '0');
                checkOutHour.appendChild(option);
            }
        }
        
        // Populate minutes (0-59, step 1)
        if (checkOutMinute.options.length <= 1) {
            for (let m = 0; m < 60; m++) {
                const option = document.createElement('option');
                option.value = String(m).padStart(2, '0');
                option.textContent = String(m).padStart(2, '0');
                checkOutMinute.appendChild(option);
            }
        }
        
        // Update hidden field when dropdowns change
        function updateCheckOutTime() {
            const hour = checkOutHour.value;
            const minute = checkOutMinute.value;
            if (hour && minute) {
                checkOutTime.value = hour + ':' + minute + ':00';
            } else {
                checkOutTime.value = '';
            }
        }
        
        checkOutHour.addEventListener('change', updateCheckOutTime);
        checkOutMinute.addEventListener('change', updateCheckOutTime);
    }
}

// Load employees for attendance dropdown
async function loadEmployeesForAttendance() {
    try {
        const response = await fetch(hrApiUrl('/hr/employees.php?action=list&limit=100'));
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('employee_id');
            if (select) {
                // Ensure select has proper attributes
                select.setAttribute('dir', 'ltr');
                select.setAttribute('lang', 'en');
                select.innerHTML = '<option value="">Select Employee</option>';
                data.data.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = `${employee.name} (${employee.employee_id})`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

// Refresh all employee dropdowns across different forms
async function refreshEmployeeDropdowns() {
    // Refresh attendance form dropdown
    if (document.getElementById('employee_id')) {
        await loadEmployeesForAttendance();
    }
    
    // Refresh advance form dropdown
    if (typeof loadEmployeesForAdvance === 'function') {
        await loadEmployeesForAdvance();
    }
    
    // Refresh any other employee dropdowns that might exist
    const employeeSelects = document.querySelectorAll('select[id*="employee"], select[name*="employee"]');
    employeeSelects.forEach(async (select) => {
        if (select.id !== 'employee_id') { // Avoid duplicate loading
            await loadEmployeesForSelect(select);
        }
    });
}

// Load employees for a specific select element
// preserveValue: optional value to select after load (for edit forms)
async function loadEmployeesForSelect(selectElement, preserveValue) {
    try {
        const response = await fetch(hrApiUrl('/hr/employees.php?action=list&limit=100'));
        const data = await response.json();
        
        if (data.success) {
            const currentValue = preserveValue != null ? String(preserveValue) : selectElement.value;
            selectElement.innerHTML = '<option value="">Select Employee</option>';
            data.data.forEach(employee => {
                const option = document.createElement('option');
                option.value = employee.id;
                option.textContent = `${employee.name} (${employee.employee_id})`;
                selectElement.appendChild(option);
            });
            if (currentValue) {
                selectElement.value = currentValue;
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    } catch (error) {
        console.error('Error loading employees for select:', error);
    }
}

// View employee
async function viewEmployee(id) {
    if (!id) { showHRMessage('Invalid employee ID', 'error'); return; }
    try {
        const response = await fetch(hrApiUrl(`/hr/employees.php?action=get&id=${id}`));
        const result = await response.json();
        if (result.success && result.data) {
            const e = result.data;
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            modalTitle.textContent = 'View Employee';
            modalBody.innerHTML = `
                <div class="document-view-details employee-view-details" dir="ltr" lang="en">
                    <div class="view-detail-row"><strong>ID:</strong> ${escapeHtml(e.employee_id || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Name:</strong> ${escapeHtml(e.name || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Email:</strong> ${escapeHtml(e.email || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Phone:</strong> ${escapeHtml(e.phone || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Type:</strong> ${escapeHtml(e.type || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Department:</strong> ${escapeHtml(e.department || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Position:</strong> ${escapeHtml(e.position || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Status:</strong> <span class="status-badge ${escapeHtml((e.status || '').toLowerCase())}">${escapeHtml(e.status || 'N/A')}</span></div>
                    <div class="view-detail-row"><strong>Join Date:</strong> ${escapeHtml(e.join_date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Birth Date:</strong> ${(e.birthdate && e.birthdate !== '0000-00-00') ? escapeHtml(e.birthdate) : 'N/A'}</div>
                    <div class="view-detail-row"><strong>Address:</strong> ${escapeHtml(e.address || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Basic Salary:</strong> ${e.basic_salary != null && e.basic_salary !== '' ? escapeHtml(String(e.basic_salary)) : 'N/A'}</div>
                    <div class="document-view-actions mt-3">
                        <button type="button" class="btn btn-info" data-hr-action="downloadHRView"><i class="fas fa-download"></i> Download</button>
                        <button type="button" class="btn btn-info" data-hr-action="printHRView"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn btn-warning" data-hr-action="editEmployee" data-hr-id="${escapeHtml(String(e.id))}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn-secondary" data-hr-action="closeModal"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            hrModal.show();
        } else {
            showHRMessage(result.message || 'Failed to load employee', 'error');
        }
    } catch (error) {
        console.error('Error loading employee:', error);
        showHRMessage('Failed to load employee', 'error');
    }
}

// Edit employee
async function editEmployee(id) {
    try {
        // Show modal with loading state immediately - DON'T load employee list first
        const modalBody = document.getElementById('hrModalBody');
        const modalTitle = document.getElementById('hrModalTitle');
        
        modalTitle.textContent = 'Edit Employee';
        modalBody.innerHTML = '<div class="hr-loading">Loading employee data...</div>';
        
        // Show modal
        hrModal.show();
        
        // Fetch employee data
        const response = await fetch(hrApiUrl(`/hr/employees.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const employee = result.data;
            
            // Wait a moment for modal to fully show
            setTimeout(() => {
                // Create and populate the edit form
                const formHtml = createEmployeeForm();
                modalBody.innerHTML = `
                    <div class="modal-title">Edit Employee</div>
                    ${formHtml}
                `;
                
                // Setup form handlers first (this might clone the form)
                setupHRFormHandlers();
                
                // Wait a bit for DOM to be ready, then populate all fields
                setTimeout(() => {
                    // Function to populate all form fields
                    function populateAllFields() {
                        // Basic text/date fields
                        const nameField = document.getElementById('name');
                        const emailField = document.getElementById('email');
                        const phoneField = document.getElementById('phone');
                        const birthdateField = document.getElementById('birthdate');
                        const joinDateField = document.getElementById('join_date');
                        const basicSalaryField = document.getElementById('basic_salary');
                        const statusField = document.getElementById('status');
                        const typeField = document.getElementById('type');
                        const addressField = document.getElementById('address');
                        
                        if (nameField) nameField.value = employee.name || '';
                        if (emailField) emailField.value = employee.email || '';
                        if (phoneField) phoneField.value = toWesternNumerals(employee.phone) || employee.phone || '';
                        if (birthdateField) birthdateField.value = (employee.birthdate && employee.birthdate !== '0000-00-00') ? toWesternNumerals(employee.birthdate) : '';
                        if (joinDateField) joinDateField.value = toWesternNumerals(employee.join_date) || employee.join_date || '';
                        if (basicSalaryField) basicSalaryField.value = toWesternNumerals(String(employee.basic_salary || '').replace(/[٬،]/g, '').replace(/[٫,]/g, '.')) || '';
                        if (statusField) statusField.value = employee.status || 'Active';
                        if (typeField) typeField.value = employee.type || 'Full-time';
                        if (addressField) addressField.value = employee.address || '';
                        
                        // Handle department dropdown
                        const departmentField = document.getElementById('department');
                        if (departmentField && employee.department) {
                            const deptValue = (employee.department || '').trim();
                            if (deptValue) {
                                // Check if the department value exists in options
                                let foundOption = null;
                                for (let i = 0; i < departmentField.options.length; i++) {
                                    const opt = departmentField.options[i];
                                    if (opt.value.trim() === deptValue || opt.textContent.trim() === deptValue) {
                                        foundOption = opt;
                                        break;
                                    }
                                }
                                
                                if (foundOption) {
                                    departmentField.value = foundOption.value;
                                } else {
                                    // Create option if it doesn't exist
                                    const option = document.createElement('option');
                                    option.value = deptValue;
                                    option.textContent = deptValue;
                                    departmentField.appendChild(option);
                                    departmentField.value = deptValue;
                                }
                            }
                        }
                        
                        // Handle position dropdown
                        const positionField = document.getElementById('position');
                        if (positionField && employee.position) {
                            const posValue = (employee.position || '').trim();
                            if (posValue) {
                                // Check if the position value exists in options
                                let foundOption = null;
                                for (let i = 0; i < positionField.options.length; i++) {
                                    const opt = positionField.options[i];
                                    if (opt.value.trim() === posValue || opt.textContent.trim() === posValue) {
                                        foundOption = opt;
                                        break;
                                    }
                                }
                                
                                if (foundOption) {
                                    positionField.value = foundOption.value;
                                } else {
                                    // Create option if it doesn't exist
                                    const option = document.createElement('option');
                                    option.value = posValue;
                                    option.textContent = posValue;
                                    positionField.appendChild(option);
                                    positionField.value = posValue;
                                }
                            }
                        }
                        
                        // Handle country and city dropdowns
                const savedCountry = employee.country;
                const savedCity = employee.city;
                
                    const countrySelect = document.getElementById('country');
                    if (countrySelect) {
                        // Populate countries from API if not already populated
                        if (countrySelect.options.length <= 1) {
                            try {
                                const apiRoot = typeof getRatibApiBaseTrimmed === 'function' ? getRatibApiBaseTrimmed() : '';
                                const url = `${apiRoot}/admin/get_countries_cities.php?action=countries`;
                                
                                fetch(url, {
                                    method: 'GET',
                                    credentials: 'same-origin',
                                    headers: { 'Content-Type': 'application/json' }
                                }).then(response => response.json())
                                .then(data => {
                                    if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                                        data.countries.sort().forEach(country => {
                                            const option = document.createElement('option');
                                            option.value = country;
                                            option.textContent = country;
                                            countrySelect.appendChild(option);
                                        });
                                    }
                                }).catch(err => console.error('Failed to load countries:', err));
                            } catch (error) {
                                console.error('Error loading countries:', error);
                            }
                        }
                        
                            // Set the country value if we have a saved country
                            if (savedCountry) {
                        countrySelect.value = savedCountry;
                        
                        // Load cities for the selected country
                                if (typeof loadCitiesByCountry === 'function') {
                            loadCitiesByCountry(savedCountry, 'city').then(() => {
                                // Wait for cities to load, then set the city value
                                setTimeout(() => {
                                    const citySelect = document.getElementById('city');
                                    if (citySelect && savedCity) {
                                        citySelect.value = savedCity;
                                        // If city not found, create option
                                        if (citySelect.value !== savedCity) {
                                            const option = document.createElement('option');
                                            option.value = savedCity;
                                            option.textContent = savedCity;
                                            option.selected = true;
                                            citySelect.appendChild(option);
                                            citySelect.value = savedCity;
                                        }
                                    }
                                }, 200);
                            }).catch(err => console.error('Error loading cities:', err));
                                } else if (typeof window.loadCitiesByCountry === 'function') {
                                    window.loadCitiesByCountry(savedCountry, 'city').then(() => {
                                        setTimeout(() => {
                                            const citySelect = document.getElementById('city');
                                            if (citySelect && savedCity) {
                                                citySelect.value = savedCity;
                                                if (citySelect.value !== savedCity) {
                                                    const option = document.createElement('option');
                                                    option.value = savedCity;
                                                    option.textContent = savedCity;
                                                    option.selected = true;
                                                    citySelect.appendChild(option);
                                                    citySelect.value = savedCity;
                                                }
                                            }
                                        }, 200);
                                    }).catch(err => console.error('Error loading cities:', err));
                                }
                            }
                        }
                    }
                    
                    // Populate fields now
                    populateAllFields();
                    
                    // Also populate again after a short delay to ensure dropdowns are ready
                    setTimeout(populateAllFields, 100);
                    setTimeout(populateAllFields, 300);
                    
                }, 50);
                
                // Add hidden ID field and mark form as edit mode
                setTimeout(() => {
                    const form = document.getElementById('employeeForm');
                    if (!form) {
                        return;
                    }
                    
                    // Remove existing hidden ID field if present
                    const existingIdField = form.querySelector('input[name="id"][type="hidden"]');
                    if (existingIdField) {
                        existingIdField.remove();
                    }
                    
                    const hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = 'id';
                    hiddenId.id = 'employeeId';
                    hiddenId.value = id;
                    form.appendChild(hiddenId);
                    
                    // Mark form as edit mode
                    form.setAttribute('data-edit-mode', 'true');
                    form.setAttribute('data-employee-id', id);
                }, 100);
                    
            }, 0); // Smooth delay for transition
        } else {
            showHRMessage(result.message || 'Failed to load employee data', 'error');
        }
    } catch (error) {
        console.error('Error loading employee:', error);
        showHRMessage('Failed to load employee data', 'error');
    }
}

// Delete employee
async function deleteEmployee(id) {
    if (confirm('Are you sure you want to delete this employee?')) {
        try {
            const response = await fetch(hrApiUrl(`/hr/employees.php?action=delete&id=${id}`), {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                showHRMessage('Employee deleted successfully!', 'success');
                loadHRStats();
                
                // Close modal and refresh list
                if (typeof closeHRModal === 'function') {
                    closeHRModal();
                } else if (hrModal && typeof hrModal.hide === 'function') {
                    hrModal.hide();
                }
                
                setTimeout(() => {
                    showHRForm('employees', 'list');
                }, 500);
            } else {
                showHRMessage(result.message || 'Failed to delete employee', 'error');
            }
        } catch (error) {
            console.error('Error deleting employee:', error);
            showHRMessage('Failed to delete employee', 'error');
        }
    }
}

// Save HR settings
async function saveHRSettings() {
    const workingHoursInput = document.getElementById('working_hours');
    const overtimeRateInput = document.getElementById('overtime_rate');
    const payrollDayInput = document.getElementById('payroll_day');
    const taxRateInput = document.getElementById('tax_rate');
    
    // Enhanced conversion function
    const forceWestern = (val) => {
        if (!val) return val;
        let result = String(val).trim();
        const arabic = '٠١٢٣٤٥٦٧٨٩';
        const persian = '۰۱۲۳۴۵۶۷۸۹';
        for (let i = 0; i <= 9; i++) {
            result = result.replace(new RegExp(arabic[i], 'g'), String(i));
            result = result.replace(new RegExp(persian[i], 'g'), String(i));
        }
        return result;
    };
    
    // Get values and convert Arabic numerals to Western numerals (multiple passes for safety)
    let workingHours = forceWestern(workingHoursInput?.value || '');
    let overtimeRate = forceWestern(overtimeRateInput?.value || '');
    let payrollDay = forceWestern(payrollDayInput?.value || '');
    let taxRate = forceWestern(taxRateInput?.value || '');
    
    // Apply toWesternNumerals as well if available
    if (typeof toWesternNumerals === 'function') {
        workingHours = toWesternNumerals(workingHours);
        overtimeRate = toWesternNumerals(overtimeRate);
        payrollDay = toWesternNumerals(payrollDay);
        taxRate = toWesternNumerals(taxRate);
    }
    
    // Final pass: ASCII 0-9 and . only (no Arabic, no Greek)
    const toAsciiNum = (v) => {
        var s = String(v), out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c >= 48 && c <= 57) out += s[i];
            else if (c === 46 && out.indexOf('.') === -1) out += '.';
        }
        return out;
    };
    workingHours = toAsciiNum(workingHours);
    overtimeRate = toAsciiNum(overtimeRate);
    payrollDay = toAsciiNum(payrollDay);
    taxRate = toAsciiNum(taxRate);
    
    if (!workingHours || !overtimeRate || !payrollDay || !taxRate) {
        showHRMessage('Please fill all settings fields', 'error');
        return;
    }
    
    const settings = {
        working_hours: workingHours,
        overtime_rate: overtimeRate,
        payroll_day: payrollDay,
        tax_rate: taxRate
    };
    
    try {
        showHRMessage('Saving settings...', 'info');
        
        const response = await fetch(hrApiUrl('/hr/settings.php?action=save'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Settings saved successfully!', 'success');
            setTimeout(() => {
                closeHRModal();
            }, 1500);
        } else {
            showHRMessage(result.message || 'Failed to save settings', 'error');
        }
    } catch (error) {
        console.error('Error saving HR settings:', error);
        showHRMessage('Failed to save settings: ' + error.message, 'error');
    }
}

// Show HR message
function showHRMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `hr-message ${type}`;
    messageDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${escapeHtml(message)}`;
    
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.hr-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Add new message
    const modalBody = document.getElementById('hrModalBody');
    if (modalBody) {
        modalBody.insertBefore(messageDiv, modalBody.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

// Close HR modal
function closeHRModal() {
    if (hrModal) {
        hrModal.hide();
    }
}

// View advance
async function viewAdvance(id) {
    if (!id) { showHRMessage('Invalid advance ID', 'error'); return; }
    try {
        const response = await fetch(hrApiUrl(`/hr/advances.php?action=get&id=${id}`));
        const result = await response.json();
        if (result.success && result.data) {
            const a = result.data;
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            modalTitle.textContent = 'View Advance';
            modalBody.innerHTML = `
                <div class="document-view-details" dir="ltr" lang="en">
                    <div class="view-detail-row"><strong>ID:</strong> ${escapeHtml(a.record_id || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Employee:</strong> ${escapeHtml(a.employee_name || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Request Date:</strong> ${escapeHtml(a.request_date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Amount:</strong> ${escapeHtml(String(a.amount || 'N/A'))}</div>
                    <div class="view-detail-row"><strong>Repayment Date:</strong> ${escapeHtml(a.repayment_date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Purpose:</strong> ${escapeHtml(a.purpose || '-')}</div>
                    <div class="view-detail-row"><strong>Status:</strong> <span class="status-badge ${escapeHtml((a.status || '').toLowerCase())}">${escapeHtml(a.status || 'N/A')}</span></div>
                    <div class="document-view-actions mt-3">
                        <button type="button" class="btn btn-info" data-hr-action="downloadHRView"><i class="fas fa-download"></i> Download</button>
                        <button type="button" class="btn btn-info" data-hr-action="printHRView"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn btn-warning" data-hr-action="editAdvance" data-hr-id="${escapeHtml(String(a.id))}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn-secondary" data-hr-action="closeModal"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            hrModal.show();
        } else { showHRMessage(result.message || 'Failed to load advance', 'error'); }
    } catch (error) {
        console.error('Error loading advance:', error);
        showHRMessage('Failed to load advance', 'error');
    }
}

// Advances bulk actions
function toggleAllAdvances(checkbox) {
    document.querySelectorAll('.advance-checkbox').forEach(cb => { cb.checked = checkbox.checked; });
}
function getSelectedAdvanceIds() {
    return Array.from(document.querySelectorAll('.advance-checkbox:checked')).map(cb => cb.value);
}
async function bulkDeleteAdvances() {
    const ids = getSelectedAdvanceIds();
    if (!ids.length) { showHRMessage('Please select at least one advance', 'warning'); return; }
    if (!confirm('Delete ' + ids.length + ' advance(s)?')) return;
    let success = 0;
    for (const id of ids) {
        try {
            const res = await fetch(hrApiUrl(`/hr/advances.php?action=delete&id=${id}`), { method: 'DELETE' });
            const r = await res.json();
            if (r.success) success++;
        } catch (_) {}
    }
    if (success > 0) {
        showHRMessage(success + ' advance(s) deleted!', 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'advances') await loadHRModuleContent('advances', 'list', modalBody, 1, 5);
    } else { showHRMessage('Failed to delete', 'error'); }
}
async function bulkSetAdvanceStatus(status) {
    const ids = getSelectedAdvanceIds();
    if (!ids.length) { showHRMessage('Please select at least one advance', 'warning'); return; }
    if (!confirm('Set ' + ids.length + ' advance(s) to ' + status + '?')) return;
    let success = 0;
    for (const id of ids) {
        try {
            const res = await fetch(hrApiUrl(`/hr/advances.php?action=update&id=${id}`), {
                method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: status })
            });
            const r = await res.json();
            if (r.success) success++;
        } catch (_) {}
    }
    if (success > 0) {
        showHRMessage(success + ' advance(s) updated!', 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'advances') await loadHRModuleContent('advances', 'list', modalBody, 1, 5);
    } else { showHRMessage('Failed to update', 'error'); }
}

// Edit advance
async function editAdvance(id) {
    try {
        // Show modal with loading state
        const modalBody = document.getElementById('hrModalBody');
        const modalTitle = document.getElementById('hrModalTitle');

        modalTitle.textContent = 'Edit Advance';
        modalBody.innerHTML = '<div class="hr-loading">Loading advance data...</div>';

        // Show modal
        hrModal.show();
        
        // Fetch advance data
        const response = await fetch(hrApiUrl(`/hr/advances.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const advance = result.data;
            
            // Wait a moment for modal to fully show
            setTimeout(async () => {
                const formHtml = createAdvancesForm();
                modalBody.innerHTML = `
                    <div class="modal-title">Edit Advance</div>
                    ${formHtml}
                `;
                
                // Populate basic fields
                document.getElementById('request_date').value = toWesternNumerals(advance.request_date || '') || '';
                document.getElementById('amount').value = toWesternNumerals(String(advance.amount || '').replace(/[٬،]/g, '').replace(/[٫,]/g, '.')) || advance.amount || '';
                document.getElementById('repayment_date').value = toWesternNumerals(advance.repayment_date || '') || '';
                document.getElementById('status').value = advance.status || 'pending';
                document.getElementById('purpose').value = advance.purpose || '';
                
                // Load employees dropdown and then select the employee
                const savedEmployeeId = advance.employee_id;
                
                // Load employee dropdown
                await new Promise((resolve) => {
                    loadEmployeesForAdvance();
                    setTimeout(resolve, 200);
                });
                
                // Select the employee
                const employeeSelect = document.getElementById('employee_id');
                if (employeeSelect && savedEmployeeId) {
                    employeeSelect.value = String(savedEmployeeId);
                    console.log('Set employee_id to:', savedEmployeeId);
                }
                
                // Add hidden ID field
                const form = document.getElementById('advancesForm');
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = id;
                form.appendChild(hiddenId);
                
                // Update form submit handler for edit
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    const formData = new FormData(form);
                    const advanceData = {
                        employee_id: formData.get('employee_id'),
                        request_date: formData.get('request_date'),
                        amount: formData.get('amount'),
                        repayment_date: formData.get('repayment_date'),
                        purpose: formData.get('purpose'),
                        status: formData.get('status')
                    };
                    
                    const updateResponse = await fetch(hrApiUrl(`/hr/advances.php?action=update&id=${id}`), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(advanceData)
                    });
                    
                    const updateResult = await updateResponse.json();
                    
                    if (updateResult.success) {
                        showHRMessage('Advance updated successfully!', 'success');
                        loadHRStats();
                        showHRForm('advances', 'list');
                    } else {
                        showHRMessage(updateResult.message || 'Failed to update advance', 'error');
                    }
                }, true);
            }, 300);
        } else {
            showHRMessage(result.message || 'Failed to load advance data', 'error');
        }
    } catch (error) {
        console.error('Error loading advance:', error);
        showHRMessage('Failed to load advance data', 'error');
    }
}

// Delete advance
async function deleteAdvance(id) {
    if (!confirm('Are you sure you want to delete this advance request?')) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl(`/hr/advances.php?action=delete&id=${id}`), {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Advance deleted successfully!', 'success');
            loadHRStats();
            showHRForm('advances');
        } else {
            showHRMessage(result.message || 'Failed to delete advance', 'error');
        }
    } catch (error) {
        console.error('Error deleting advance:', error);
        showHRMessage('Failed to delete advance', 'error');
    }
}

// View salary
async function viewSalary(id) {
    if (!id) { showHRMessage('Invalid payroll ID', 'error'); return; }
    try {
        const response = await fetch(hrApiUrl(`/hr/salaries.php?action=get&id=${id}`));
        const result = await response.json();
        if (result.success && result.data) {
            const s = result.data;
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            modalTitle.textContent = 'View Payroll';
            modalBody.innerHTML = `
                <div class="document-view-details" dir="ltr" lang="en">
                    <div class="view-detail-row"><strong>ID:</strong> ${escapeHtml(s.record_id || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Employee:</strong> ${escapeHtml(s.employee_name || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Salary Month:</strong> ${escapeHtml(s.salary_month || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Working Days:</strong> ${escapeHtml(String(s.working_days || 'N/A'))}</div>
                    <div class="view-detail-row"><strong>Basic Salary:</strong> ${escapeHtml(String(s.basic_salary || '0'))}</div>
                    <div class="view-detail-row"><strong>Total Earnings:</strong> ${escapeHtml(String(s.total_earnings || '0'))}</div>
                    <div class="view-detail-row"><strong>Total Deductions:</strong> ${escapeHtml(String(s.total_deductions || '0'))}</div>
                    <div class="view-detail-row"><strong>Net Salary:</strong> <strong>${escapeHtml(String(s.net_salary || '0'))}</strong></div>
                    <div class="view-detail-row"><strong>Status:</strong> <span class="status-badge ${escapeHtml((s.status || 'pending').toLowerCase())}">${escapeHtml(s.status || 'Pending')}</span></div>
                    <div class="document-view-actions mt-3">
                        <button type="button" class="btn btn-info" data-hr-action="downloadHRView"><i class="fas fa-download"></i> Download</button>
                        <button type="button" class="btn btn-info" data-hr-action="printHRView"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn btn-warning" data-hr-action="editSalary" data-hr-id="${escapeHtml(String(s.id))}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn-secondary" data-hr-action="closeModal"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            hrModal.show();
        } else { showHRMessage(result.message || 'Failed to load payroll', 'error'); }
    } catch (error) {
        console.error('Error loading payroll:', error);
        showHRMessage('Failed to load payroll', 'error');
    }
}

// Edit salary
async function editSalary(id) {
    try {
        // Show modal with loading state
        const modalBody = document.getElementById('hrModalBody');
        const modalTitle = document.getElementById('hrModalTitle');

        modalTitle.textContent = 'Edit Payroll';
        modalBody.innerHTML = '<div class="hr-loading">Loading payroll data...</div>';

        // Show modal
        hrModal.show();
        
        // Fetch salary data
        const response = await fetch(hrApiUrl(`/hr/salaries.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const salary = result.data;
            
            // Wait a moment for modal to fully show
            setTimeout(async () => {
                const formHtml = createPayrollForm();
                modalBody.innerHTML = `
                    <div class="modal-title">Edit Payroll</div>
                    ${formHtml}
                `;
                
                // Function to convert numeric value to dropdown value format
                const convertToDropdownValue = (value) => {
                    if (!value) return '';
                    // Remove .00 from end of numbers to match dropdown values
                    const strVal = String(value).replace(/\.00$/, '');
                    return strVal;
                };
                
                // Populate basic fields
                document.getElementById('salary_month').value = toWesternNumerals(salary.salary_month || '') || '';
                document.getElementById('working_days').value = salary.working_days ? toWesternNumerals(String(salary.working_days)) : '';
                document.getElementById('basic_salary').value = convertToDropdownValue(toWesternNumerals(String(salary.basic_salary || '')));
                document.getElementById('housing_allowance').value = convertToDropdownValue(salary.housing_allowance);
                document.getElementById('transportation').value = convertToDropdownValue(salary.transportation);
                document.getElementById('overtime_hours').value = convertToDropdownValue(salary.overtime_hours);
                document.getElementById('overtime_rate').value = convertToDropdownValue(salary.overtime_rate);
                document.getElementById('bonus').value = convertToDropdownValue(salary.bonus);
                document.getElementById('insurance').value = convertToDropdownValue(salary.insurance);
                document.getElementById('tax_percentage').value = convertToDropdownValue(salary.tax_percentage);
                document.getElementById('other_deductions').value = convertToDropdownValue(salary.other_deductions);
                
                // Load employees dropdown and then select the employee
                const savedEmployeeId = salary.employee_id != null ? String(salary.employee_id) : '';
                const savedEmployeeName = salary.employee_name || '';
                const savedCurrency = salary.currency || localStorage.getItem('currency_default') || 'SAR';
                
                // Normalize currency code to uppercase
                const normalizedCurrency = savedCurrency ? String(savedCurrency).toUpperCase().trim() : 'SAR';
                
                // CRITICAL: Remove localStorage values to prevent makeSelectSearchable from overriding
                localStorage.removeItem('currency_default');
                localStorage.removeItem('employee_id_default');
                
                // Load employee dropdown - MUST await so options exist before setting value
                const employeeSelect = document.getElementById('employee_id');
                if (employeeSelect) {
                    await loadEmployeesForSelect(employeeSelect, savedEmployeeId);
                    if (savedEmployeeId) {
                        // Ensure saved employee exists as option (e.g. if deleted or not in list)
                        const hasOption = Array.from(employeeSelect.options).some(o => o.value === savedEmployeeId);
                        if (!hasOption && savedEmployeeName) {
                            const opt = document.createElement('option');
                            opt.value = savedEmployeeId;
                            opt.textContent = savedEmployeeName + ' (ID: ' + savedEmployeeId + ')';
                            employeeSelect.appendChild(opt);
                        }
                        employeeSelect.value = savedEmployeeId;
                        if (employeeSelect.value !== savedEmployeeId) {
                            const opt = Array.from(employeeSelect.options).find(o => o.value === savedEmployeeId);
                            if (opt) opt.selected = true;
                        }
                        // Re-apply employee value at intervals (in case any script overwrites it)
                        const setEmployeeValue = () => {
                            if (employeeSelect && savedEmployeeId && Array.from(employeeSelect.options).some(o => o.value === savedEmployeeId)) {
                                employeeSelect.value = savedEmployeeId;
                            }
                        };
                        setTimeout(setEmployeeValue, 100);
                        setTimeout(setEmployeeValue, 500);
                        setTimeout(setEmployeeValue, 1000);
                    }
                }
                
                // Populate and set currency dropdown
                const currencySelect = document.getElementById('currency');
                
                if (currencySelect) {
                    if (window.currencyUtils && typeof window.currencyUtils.populateCurrencySelect === 'function') {
                        // Force refresh to get fresh currencies
                        await window.currencyUtils.fetchCurrencies(true);
                        
                        // Populate the currency dropdown with the saved currency
                        await window.currencyUtils.populateCurrencySelect(currencySelect, normalizedCurrency);
                        
                        // Wait a bit for options to be populated
                        await new Promise(resolve => setTimeout(resolve, 300));
                        
                        // Check if currency is in the options, if not manually add it
                        const hasCurrency = Array.from(currencySelect.options).some(opt => opt.value === normalizedCurrency);
                        if (!hasCurrency && normalizedCurrency) {
                            const currencyOption = document.createElement('option');
                            currencyOption.value = normalizedCurrency;
                            // Try to get currency name from fetched currencies
                            const currencies = await window.currencyUtils.fetchCurrencies(true);
                            const currencyInfo = currencies.find(c => c.code === normalizedCurrency);
                            currencyOption.textContent = currencyInfo ? currencyInfo.label : `${normalizedCurrency} - ${normalizedCurrency}`;
                            currencySelect.appendChild(currencyOption);
                        }
                    } else {
                        // Fallback: manually add currency option if currencyUtils not available
                        if (normalizedCurrency) {
                            const currencyOption = document.createElement('option');
                            currencyOption.value = normalizedCurrency;
                            currencyOption.textContent = `${normalizedCurrency} - ${normalizedCurrency}`;
                            currencySelect.appendChild(currencyOption);
                        }
                    }
                    
                    // Function to set currency value (with retry logic)
                    function setCurrencyValue() {
                        if (!normalizedCurrency || !currencySelect) return;
                        
                        // Try setting the value directly
                        currencySelect.value = normalizedCurrency;
                        
                        // If value still doesn't match, find and select by iterating options
                        if (currencySelect.value !== normalizedCurrency && currencySelect.options.length > 1) {
                            for (let i = 0; i < currencySelect.options.length; i++) {
                                const optionValue = currencySelect.options[i].value.toUpperCase().trim();
                                if (optionValue === normalizedCurrency) {
                                    currencySelect.selectedIndex = i;
                                    currencySelect.value = currencySelect.options[i].value;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Set immediately after population
                    setCurrencyValue();
                    
                    // Use setInterval to continuously ensure the value is set correctly
                    // This will override any attempts by makeSelectSearchable to change it
                    const valueGuard = setInterval(() => {
                        if (currencySelect.value !== normalizedCurrency && currencySelect.options.length > 1) {
                            setCurrencyValue();
                        }
                    }, 50); // Check every 50ms
                    
                    // Stop the guard after 3 seconds (when makeSelectSearchable should be done)
                    setTimeout(() => {
                        clearInterval(valueGuard);
                        setCurrencyValue(); // Final set to ensure it's correct
                    }, 3000);
                    
                    // Also set at specific intervals as backup
                    setTimeout(() => setCurrencyValue(), 100);
                    setTimeout(() => setCurrencyValue(), 300);
                    setTimeout(() => setCurrencyValue(), 520); // Just after makeSelectSearchable runs (500ms)
                    setTimeout(() => setCurrencyValue(), 600);
                    setTimeout(() => setCurrencyValue(), 800);
                    setTimeout(() => setCurrencyValue(), 1000);
                    setTimeout(() => setCurrencyValue(), 1500);
                    setTimeout(() => setCurrencyValue(), 2000);
                    setTimeout(() => setCurrencyValue(), 2500); // Final attempt
                }
                
                // Add hidden ID field
                const form = document.getElementById('payrollForm');
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = id;
                form.appendChild(hiddenId);
                
                // Update form submit handler for edit
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    const formData = new FormData(form);
                    const salaryData = {
                        employee_id: formData.get('employee_id'),
                        currency: formData.get('currency'),
                        salary_month: formData.get('salary_month'),
                        working_days: formData.get('working_days'),
                        basic_salary: formData.get('basic_salary'),
                        housing_allowance: formData.get('housing_allowance'),
                        transportation: formData.get('transportation'),
                        overtime_hours: formData.get('overtime_hours'),
                        overtime_rate: formData.get('overtime_rate'),
                        bonus: formData.get('bonus'),
                        insurance: formData.get('insurance'),
                        tax_percentage: formData.get('tax_percentage'),
                        other_deductions: formData.get('other_deductions')
                    };
                    
                    const updateResponse = await fetch(hrApiUrl(`/hr/salaries.php?action=update&id=${id}`), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(salaryData)
                    });
                    
                    const updateResult = await updateResponse.json();
                    
                    if (updateResult.success) {
                        showHRMessage('Payroll updated successfully!', 'success');
                        loadHRStats();
                        // Close the edit form and refresh the table
                        if (hrModal) hrModal.hide();
                        setTimeout(async () => {
                            const modalBody = document.getElementById('hrModalBody');
                            if (modalBody) {
                                modalBody.innerHTML = ''; // Clear modal body to force fresh load
                            }
                            await refreshPayrollTableContent();
                            if (hrModal) hrModal.show();
                        }, 300);
                    } else {
                        showHRMessage(updateResult.message || 'Failed to update payroll', 'error');
                    }
                }, true);
            }, 300);
        } else {
            showHRMessage(result.message || 'Failed to load payroll data', 'error');
        }
    } catch (error) {
        console.error('Error loading payroll:', error);
        showHRMessage('Failed to load payroll data', 'error');
    }
}

// Delete salary
async function deleteSalary(id) {
    if (!confirm('Are you sure you want to delete this salary record?')) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl(`/hr/salaries.php?action=delete&id=${id}`), {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Salary record deleted successfully!', 'success');
            loadHRStats();
            // Refresh table without closing modal
            await refreshPayrollTableContent();
        } else {
            showHRMessage(result.message || 'Failed to delete salary record', 'error');
        }
    } catch (error) {
        console.error('Error deleting salary:', error);
        showHRMessage('Failed to delete salary record', 'error');
    }
}

// View document
async function viewDocument(id) {
    if (!id) {
        showHRMessage('Invalid document ID', 'error');
        return;
    }
    
    try {
        // Fetch document details
        const response = await fetch(hrApiUrl(`/hr/documents.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const doc = result.data;
            
            // Show modal with document details
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            
            modalTitle.textContent = 'View Document';
            
            // Build URLs: use file_path; new uploads have ASCII filenames, old ones may need encoding
            const base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '';
            const baseClean = (typeof base === 'string' && base) ? base.replace(/\/$/, '') : '';
            let pathPart = (doc.file_path && doc.file_path.replace) ? doc.file_path.replace(/^https?:\/\/[^/]+/, '') : '';
            if (!pathPart) pathPart = '/uploads/documents/' + encodeURIComponent(doc.file_name || '');
            else if (/[\u0080-\uFFFF]/.test(pathPart)) {
                const parts = pathPart.split('/');
                parts[parts.length - 1] = encodeURIComponent(parts[parts.length - 1]);
                pathPart = parts.join('/');
            }
            const directUrl = baseClean + (pathPart.startsWith('/') ? pathPart : '/' + pathPart);
            const apiUrl = hrApiUrl(`/hr/documents.php?action=view&id=${doc.id}`);
            const mimeType = doc.mime_type || '';
            const isImage = mimeType.startsWith('image/');
            const isPdf = mimeType === 'application/pdf';
            
            let viewerContent = '';
            const safeAlt = (doc.file_name || '').replace(/"/g, '&quot;');
            
            if (isImage) {
                // Try direct URL first; on error fall back to API
                viewerContent = `<img src="${directUrl}" alt="${safeAlt}" class="viewer-img" data-fallback="${apiUrl}">`;
            } else if (isPdf) {
                viewerContent = `<iframe src="${apiUrl}" class="viewer-iframe"></iframe>`;
            } else {
                viewerContent = `
                    <div class="doc-content-center">
                        <i class="fas fa-file-alt icon-3em hr-text-secondary mb-3"></i>
                        <p class="hr-text-secondary mb-3">This file type cannot be previewed inline.</p>
                        <a href="${directUrl}" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> Open in New Tab
                        </a>
                    </div>
                `;
            }
            
            modalBody.innerHTML = `
                <div class="document-view-container" dir="ltr" lang="en">
                    <div class="document-view-header">
                        <h4 class="document-view-title">${doc.title || doc.file_name}</h4>
                        <div class="document-view-info">
                            <div><strong>Employee:</strong> ${doc.employee_name || 'N/A'}</div>
                            <div><strong>Type:</strong> ${doc.document_type || 'N/A'}</div>
                            <div><strong>Department:</strong> ${doc.department || 'N/A'}</div>
                            <div><strong>Issue Date:</strong> ${doc.issue_date || 'N/A'}</div>
                            ${doc.expiry_date ? `<div><strong>Expiry Date:</strong> ${doc.expiry_date}</div>` : ''}
                            <div><strong>Document #:</strong> ${doc.document_number || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="document-view-preview">
                        ${viewerContent}
                    </div>
                    ${doc.description ? `<div class="document-view-description">
                        <strong>Description:</strong><br>
                        <p>${doc.description}</p>
                    </div>` : ''}
                    <div class="document-view-actions">
                        <button type="button" class="btn btn-success document-view-btn" data-hr-action="downloadDocument" data-hr-id="${doc.id}">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button type="button" class="btn btn-primary document-view-btn" data-hr-action="printDocument" data-hr-id="${doc.id}" data-hr-filename="${(doc.file_name || '').replace(/"/g, '&quot;')}">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            `;
            
            // Show modal
            hrModal.show();
            
            // Setup image fallback handler (after innerHTML is set)
            const viewerImg = modalBody.querySelector('.viewer-img[data-fallback]');
            if (viewerImg) {
                viewerImg.addEventListener('error', function() {
                    const fallback = this.getAttribute('data-fallback');
                    if (fallback) {
                        this.src = fallback;
                    }
                });
            }
        } else {
            showHRMessage(result.message || 'Failed to load document', 'error');
        }
    } catch (error) {
        console.error('Error viewing document:', error);
        showHRMessage('Failed to view document', 'error');
    }
}

// Edit document
async function editDocument(id) {
    if (!id) {
        showHRMessage('Invalid document ID', 'error');
        return;
    }
    try {
        const modalBody = document.getElementById('hrModalBody');
        const modalTitle = document.getElementById('hrModalTitle');
        modalTitle.textContent = 'Edit Document';
        modalBody.innerHTML = '<div class="hr-loading">Loading document...</div>';
        hrModal.show();

        const response = await fetch(hrApiUrl(`/hr/documents.php?action=get&id=${id}`));
        const result = await response.json();

        if (result.success && result.data) {
            const doc = result.data;
            setTimeout(async () => {
                const formHtml = createDocumentsForm();
                modalBody.innerHTML = `<div class="modal-title">Edit Document</div>${formHtml}`;

                const form = document.getElementById('documentsForm');
                const fileGroup = form.querySelector('[for="file_upload"]')?.closest('.form-group');
                if (fileGroup) {
                    fileGroup.innerHTML = '<label for="file_upload">Upload File (Optional - to replace current)</label><input type="file" class="form-control" id="file_upload" name="file_upload" dir="ltr" lang="en">';
                }
                
                // Initialize file input wrapper and date pickers after form is loaded
                setTimeout(function() {
                    if (typeof initHRNoArabicSanitizer === 'function') {
                        initHRNoArabicSanitizer(form);
                    }
                    if (typeof initHRDatePickers === 'function') {
                        initHRDatePickers(form);
                    }
                }, 100);

                document.getElementById('title').value = doc.title || '';
                document.getElementById('document_type').value = doc.document_type || '';
                document.getElementById('department').value = doc.department || '';
                document.getElementById('issue_date').value = toWesternNumerals(doc.issue_date || '') || '';
                document.getElementById('expiry_date').value = toWesternNumerals(doc.expiry_date || '') || '';
                document.getElementById('document_number').value = doc.document_number || '';
                document.getElementById('description').value = doc.description || '';

                const employeeSelect = document.getElementById('employee_id');
                if (employeeSelect) {
                    let savedEmployeeId = doc.employee_id != null ? String(doc.employee_id) : '';
                    const savedEmployeeName = doc.employee_name || '';
                    localStorage.removeItem('employee_id_default');
                    await loadEmployeesForSelect(employeeSelect, savedEmployeeId);
                    if (savedEmployeeId) {
                        const hasOption = Array.from(employeeSelect.options).some(o => o.value === savedEmployeeId);
                        if (!hasOption && savedEmployeeName) {
                            const byName = Array.from(employeeSelect.options).find(o =>
                                o.textContent && o.textContent.trim().toLowerCase().startsWith(savedEmployeeName.trim().toLowerCase()));
                            if (byName) savedEmployeeId = byName.value;
                            else {
                                const opt = document.createElement('option');
                                opt.value = savedEmployeeId;
                                opt.textContent = savedEmployeeName + ' (ID: ' + savedEmployeeId + ')';
                                employeeSelect.appendChild(opt);
                            }
                        }
                    }
                    const applyEmployeeSelection = () => {
                        if (!employeeSelect) return;
                        const byName = savedEmployeeName && Array.from(employeeSelect.options).find(o =>
                            o.textContent && o.textContent.includes(savedEmployeeName));
                        const val = savedEmployeeId || (byName ? byName.value : '');
                        if (val && Array.from(employeeSelect.options).some(o => o.value === val)) {
                            employeeSelect.value = val;
                            employeeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };
                    applyEmployeeSelection();
                    setTimeout(applyEmployeeSelection, 50);
                    setTimeout(applyEmployeeSelection, 100);
                    setTimeout(applyEmployeeSelection, 500);
                    setTimeout(applyEmployeeSelection, 1000);
                }

                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = id;
                form.appendChild(hiddenId);

                if (typeof initHRDatePickers === 'function') initHRDatePickers(modalBody);

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    const formData = new FormData(form);
                    const docData = {
                        employee_id: formData.get('employee_id') || doc.employee_id,
                        title: formData.get('title'),
                        document_type: formData.get('document_type'),
                        department: formData.get('department'),
                        issue_date: formData.get('issue_date'),
                        expiry_date: formData.get('expiry_date') || null,
                        document_number: formData.get('document_number'),
                        description: formData.get('description') || '',
                        status: doc.status || 'active'
                    };
                    const updateResponse = await fetch(hrApiUrl(`/hr/documents.php?action=update&id=${id}`), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(docData)
                    });
                    const updateResult = await updateResponse.json();
                    if (updateResult.success) {
                        showHRMessage('Document updated successfully!', 'success');
                        loadHRStats();
                        hrModal.hide();
                        setTimeout(() => showHRForm('documents', 'list'), 300);
                    } else {
                        showHRMessage(updateResult.message || 'Failed to update document', 'error');
                    }
                }, true);
            }, 300);
        } else {
            showHRMessage(result.message || 'Failed to load document', 'error');
        }
    } catch (error) {
        console.error('Error loading document:', error);
        showHRMessage('Failed to load document', 'error');
    }
}

// Download document
async function downloadDocument(id) {
    if (!id) {
        showHRMessage('Invalid document ID', 'error');
        return;
    }
    
    try {
        // Fetch document details to get the correct filename
        const response = await fetch(hrApiUrl(`/hr/documents.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const doc = result.data;
            
            // Fetch the file as blob to handle download properly
            const downloadResponse = await fetch(hrApiUrl(`/hr/documents.php?action=download&id=${id}`));
            const blob = await downloadResponse.blob();
            
            // Create object URL from blob
            const url = window.URL.createObjectURL(blob);
            
            // Create a temporary link to download the document with correct filename
        const link = document.createElement('a');
            link.href = url;
            link.download = doc.file_name || `document_${id}`;
            link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
            
            // Clean up
            setTimeout(() => {
        document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }, 100);
        } else {
            showHRMessage('Failed to get document details', 'error');
        }
    } catch (error) {
        console.error('Error downloading document:', error);
        showHRMessage('Failed to download document', 'error');
    }
}

// Delete document
async function deleteDocument(id) {
    if (!id) {
        showHRMessage('Invalid document ID', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this document?')) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl(`/hr/documents.php?action=delete&id=${id}`), {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Document deleted successfully!', 'success');
            loadHRStats();
            if (window.currentHRModule === 'documents') {
                showHRForm('documents', 'list');
            }
        } else {
            showHRMessage(result.message || 'Failed to delete document', 'error');
        }
    } catch (error) {
        console.error('Error deleting document:', error);
        showHRMessage('Failed to delete document', 'error');
    }
}

// Vehicle management functions
async function viewVehicle(id) {
    if (!id) {
        showHRMessage('Invalid vehicle ID', 'error');
        return;
    }
    try {
        const response = await fetch(hrApiUrl(`/hr/cars.php?action=get&id=${id}`));
        const result = await response.json();
        if (result.success && result.data) {
            const v = result.data;
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            modalTitle.textContent = 'View Vehicle';
            modalBody.innerHTML = `
                <div class="document-view-details vehicle-view-details" dir="ltr" lang="en">
                    <div class="view-detail-row"><strong>ID:</strong> ${escapeHtml(v.record_id || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Vehicle Number:</strong> ${escapeHtml(v.vehicle_number || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Vehicle Model:</strong> ${escapeHtml(v.vehicle_model || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Driver:</strong> ${escapeHtml(v.driver_name || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Status:</strong> <span class="status-badge ${escapeHtml((v.status || '').toLowerCase())}">${escapeHtml(v.status || 'N/A')}</span></div>
                    <div class="view-detail-row"><strong>Registration Date:</strong> ${escapeHtml(v.registration_date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Insurance Expiry:</strong> ${escapeHtml(v.insurance_expiry || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Maintenance Due:</strong> ${escapeHtml(v.maintenance_due_date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Notes:</strong> ${escapeHtml(v.notes || '-')}</div>
                    <div class="document-view-actions mt-3">
                        <button type="button" class="btn btn-info" data-hr-action="downloadHRView"><i class="fas fa-download"></i> Download</button>
                        <button type="button" class="btn btn-info" data-hr-action="printHRView"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn btn-warning" data-hr-action="editVehicle" data-hr-id="${escapeHtml(String(v.id))}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn-secondary" data-hr-action="closeModal"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            hrModal.show();
        } else {
            showHRMessage(result.message || 'Failed to load vehicle', 'error');
        }
    } catch (error) {
        console.error('Error loading vehicle:', error);
        showHRMessage('Failed to load vehicle', 'error');
    }
}

async function editVehicle(id) {
    if (!id) {
        showHRMessage('Invalid vehicle ID', 'error');
        return;
    }
    
    try {
        // Fetch vehicle data
        const response = await fetch(hrApiUrl(`/hr/cars.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const vehicle = result.data;
            
            // Show modal with loading state
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            
            modalTitle.textContent = 'Edit Vehicle';
            modalBody.innerHTML = '<div class="hr-loading">Loading vehicle data...</div>';
            hrModal.show();
            
            // Wait for modal to fully show, then populate form
            setTimeout(async () => {
                // Create form HTML (simplified vehicle form)
                const formHtml = `
                    <form id="vehicleForm" class="hr-form" dir="ltr" lang="en">
                        <div class="form-content">
                            <div class="form-group">
                                <label for="vehicle_number">Vehicle Number *</label>
                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" value="${vehicle.vehicle_number || ''}" required dir="ltr" lang="en">
                            </div>
                            <div class="form-group">
                                <label for="vehicle_model">Model *</label>
                                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" value="${vehicle.vehicle_model || ''}" required dir="ltr" lang="en">
                            </div>
                            <div class="form-group">
                                <label for="driver_id">Driver</label>
                                <select class="form-select" id="driver_id" name="driver_id" dir="ltr" lang="en">
                                    <option value="">Select Driver</option>
                                    <!-- drivers will be loaded here -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select class="form-select" id="status" name="status" required dir="ltr" lang="en">
                                    <option value="available" ${(vehicle.status && vehicle.status.toLowerCase()) === 'available' ? 'selected' : ''}>Available</option>
                                    <option value="inuse" ${(vehicle.status && vehicle.status.toLowerCase()) === 'inuse' ? 'selected' : ''}>In Use</option>
                                    <option value="maintenance" ${(vehicle.status && vehicle.status.toLowerCase()) === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="registration_expiry">Registration Expiry</label>
                                <input type="text" class="form-control date-input" id="registration_expiry" name="registration_expiry" value="${vehicle.registration_expiry || ''}" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                            </div>
                            <div class="form-group">
                                <label for="insurance_expiry">Insurance Expiry</label>
                                <input type="text" class="form-control date-input" id="insurance_expiry" name="insurance_expiry" value="${vehicle.insurance_expiry || ''}" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                            </div>
                            <div class="form-group">
                                <label for="maintenance_expiry">Maintenance Expiry</label>
                                <input type="text" class="form-control date-input" id="maintenance_expiry" name="maintenance_expiry" value="${vehicle.maintenance_expiry || ''}" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" data-hr-action="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
                        </div>
                    </form>
                `;
                
                modalBody.innerHTML = `
                    <div class="modal-title">Edit Vehicle</div>
                    ${formHtml}
                `;
                
                // Get form reference
                const form = document.getElementById('vehicleForm');
                
                // Load drivers
                await loadEmployeesForSelect(document.getElementById('driver_id'));
                
                // Set selected driver if exists - wait for dropdown to load
                if (vehicle.driver_id) {
                    const driverSelect = document.getElementById('driver_id');
                    setTimeout(() => {
                        driverSelect.value = String(vehicle.driver_id);
                        if (driverSelect.value !== String(vehicle.driver_id)) {
                            // Fallback: create option if not found
                            const option = document.createElement('option');
                            option.value = String(vehicle.driver_id);
                            option.textContent = vehicle.driver_name || 'Selected Driver';
                            option.selected = true;
                            driverSelect.appendChild(option);
                        }
                    }, 100);
                }
                
                // Initialize form handlers and date pickers
                setTimeout(function() {
                    if (typeof initHRNoArabicSanitizer === 'function') {
                        initHRNoArabicSanitizer(form);
                    }
                    if (typeof initHRDatePickers === 'function') {
                        initHRDatePickers(form);
                    }
                }, 100);
                
                // Add hidden ID field and submit handler
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = id;
                form.appendChild(hiddenId);
                
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    const formData = new FormData(form);
                    // Map field names to match API
                    const vehicleData = {
                        vehicle_number: formData.get('vehicle_number'),
                        vehicle_model: formData.get('vehicle_model'),
                        driver_id: formData.get('driver_id'),
                        status: formData.get('status'),
                        registration_date: formData.get('registration_expiry'),
                        insurance_expiry: formData.get('insurance_expiry'),
                        maintenance_due_date: formData.get('maintenance_expiry')
                    };
                    
                    try {
                        const updateResponse = await fetch(hrApiUrl(`/hr/cars.php?action=update&id=${id}`), {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(vehicleData)
                        });
                        
                        const updateResult = await updateResponse.json();
                        
                        if (updateResult.success) {
                            showHRMessage('Vehicle updated successfully!', 'success');
                            loadHRStats();
                            
                            // Close edit form but keep modal open with table
                            const timestamp = new Date().getTime();
                            const modalBody = document.getElementById('hrModalBody');
                            const modalTitle = document.getElementById('hrModalTitle');
                            
                            modalTitle.textContent = getHRModuleTitle('vehicles', 'list');
                            modalBody.innerHTML = '<div class="hr-loading">Loading vehicles...</div>';
                            
                            // Load table content without closing modal
                            await loadHRModuleContent('vehicles', 'list', modalBody, 1, 5);
                        } else {
                            showHRMessage(updateResult.message || 'Failed to update vehicle', 'error');
                        }
                    } catch (error) {
                        console.error('Error updating vehicle:', error);
                        showHRMessage('Failed to update vehicle', 'error');
                    }
                }, true);
            }, 300);
        } else {
            showHRMessage(result.message || 'Failed to load vehicle data', 'error');
        }
    } catch (error) {
        console.error('Error loading vehicle:', error);
        showHRMessage('Failed to load vehicle data', 'error');
    }
}

async function deleteVehicle(id) {
    if (!id) {
        showHRMessage('Invalid vehicle ID', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this vehicle?')) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl(`/hr/cars.php?action=delete&id=${id}`), {
            method: 'DELETE'
        });
        const result = await response.json();
        
        if (result.success) {
            showHRMessage('Vehicle deleted successfully!', 'success');
            loadHRStats();
            
            // Refresh table without closing modal
            const modalBody = document.getElementById('hrModalBody');
            if (modalBody) {
                modalBody.innerHTML = '<div class="hr-loading">Loading vehicles...</div>';
                await loadHRModuleContent('vehicles', 'list', modalBody, 1, 5);
            }
        } else {
            showHRMessage(result.message || 'Failed to delete vehicle', 'error');
        }
    } catch (error) {
        console.error('Error deleting vehicle:', error);
        showHRMessage('Failed to delete vehicle', 'error');
    }
}

// View attendance
async function viewAttendance(id) {
    if (!id) { showHRMessage('Invalid attendance ID', 'error'); return; }
    try {
        const response = await fetch(hrApiUrl(`/hr/attendance.php?action=get&id=${id}`));
        const result = await response.json();
        if (result.success && result.data) {
            const a = result.data;
            const modalBody = document.getElementById('hrModalBody');
            const modalTitle = document.getElementById('hrModalTitle');
            modalTitle.textContent = 'View Attendance';
            modalBody.innerHTML = `
                <div class="document-view-details" dir="ltr" lang="en">
                    <div class="view-detail-row"><strong>ID:</strong> ${escapeHtml(a.record_id || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Employee:</strong> ${escapeHtml(a.employee_name || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Date:</strong> ${escapeHtml(a.date || 'N/A')}</div>
                    <div class="view-detail-row"><strong>Check In:</strong> ${escapeHtml(toWesternNumerals(String(a.check_in_time || '').replace(/^-/, '')) || '-')}</div>
                    <div class="view-detail-row"><strong>Check Out:</strong> ${escapeHtml(toWesternNumerals(String(a.check_out_time || '').replace(/^-/, '')) || '-')}</div>
                    <div class="view-detail-row"><strong>Status:</strong> <span class="status-badge ${escapeHtml((a.status || '').toLowerCase())}">${escapeHtml(a.status || 'N/A')}</span></div>
                    <div class="view-detail-row"><strong>Notes:</strong> ${escapeHtml(a.notes || '-')}</div>
                    <div class="document-view-actions mt-3">
                        <button type="button" class="btn btn-info" data-hr-action="downloadHRView"><i class="fas fa-download"></i> Download</button>
                        <button type="button" class="btn btn-info" data-hr-action="printHRView"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn btn-warning" data-hr-action="editAttendance" data-hr-id="${escapeHtml(String(a.id))}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn-secondary" data-hr-action="closeModal"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            hrModal.show();
        } else { showHRMessage(result.message || 'Failed to load attendance', 'error'); }
    } catch (error) {
        console.error('Error loading attendance:', error);
        showHRMessage('Failed to load attendance', 'error');
    }
}

// Attendance bulk actions
function toggleAllAttendance(checkbox) {
    document.querySelectorAll('.attendance-checkbox').forEach(cb => { cb.checked = checkbox.checked; });
}
function getSelectedAttendanceIds() {
    return Array.from(document.querySelectorAll('.attendance-checkbox:checked')).map(cb => cb.value);
}
async function bulkDeleteAttendance() {
    const ids = getSelectedAttendanceIds();
    if (!ids.length) { showHRMessage('Please select at least one record', 'warning'); return; }
    if (!confirm('Delete ' + ids.length + ' attendance record(s)?')) return;
    let success = 0;
    for (const id of ids) {
        try {
            const res = await fetch(hrApiUrl(`/hr/attendance.php?action=delete&id=${id}`), { method: 'DELETE' });
            const r = await res.json();
            if (r.success) success++;
        } catch (_) {}
    }
    if (success > 0) {
        showHRMessage(success + ' record(s) deleted!', 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'attendance') await loadHRModuleContent('attendance', 'list', modalBody, 1, 5);
    } else { showHRMessage('Failed to delete', 'error'); }
}
async function bulkSetAttendanceStatus(status) {
    const ids = getSelectedAttendanceIds();
    if (!ids.length) { showHRMessage('Please select at least one record', 'warning'); return; }
    if (!confirm('Set ' + ids.length + ' record(s) to ' + status + '?')) return;
    let success = 0;
    for (const id of ids) {
        try {
            const res = await fetch(hrApiUrl(`/hr/attendance.php?action=update&id=${id}`), {
                method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: status })
            });
            const r = await res.json();
            if (r.success) success++;
        } catch (_) {}
    }
    if (success > 0) {
        showHRMessage(success + ' record(s) updated!', 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'attendance') await loadHRModuleContent('attendance', 'list', modalBody, 1, 5);
    } else { showHRMessage('Failed to update', 'error'); }
}

// Edit attendance
async function editAttendance(id) {
    try {
        // Show modal with loading state
        const modalBody = document.getElementById('hrModalBody');
        const modalTitle = document.getElementById('hrModalTitle');
        
        modalTitle.textContent = 'Edit Attendance';
        modalBody.innerHTML = '<div class="hr-loading">Loading attendance data...</div>';
        
        // Show modal
        hrModal.show();
        
        // Fetch attendance data
        const response = await fetch(hrApiUrl(`/hr/attendance.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const attendance = result.data;
            
            // Wait a moment for modal to fully show
            setTimeout(async () => {
                const formHtml = createAttendanceForm();
                modalBody.innerHTML = `
                    <div class="modal-title">Edit Attendance</div>
                    ${formHtml}
                `;
                
                // Populate other fields - convert Arabic numerals to Western
                document.getElementById('date').value = toWesternNumerals(attendance.date || '') || '';
                
                // Initialize time pickers
                initTimePickers();
                
                // Parse and populate time pickers
                var checkIn = toWesternNumerals(String(attendance.check_in_time || '').replace(/^-/, ''));
                var checkOut = toWesternNumerals(String(attendance.check_out_time || '').replace(/^-/, ''));
                
                // Parse time format (HH:MM:SS or HH:MM)
                if (checkIn) {
                    var checkInParts = checkIn.split(':');
                    if (checkInParts.length >= 2) {
                        document.getElementById('check_in_hour').value = checkInParts[0].padStart(2, '0');
                        document.getElementById('check_in_minute').value = checkInParts[1].padStart(2, '0');
                    }
                }
                
                if (checkOut) {
                    var checkOutParts = checkOut.split(':');
                    if (checkOutParts.length >= 2) {
                        document.getElementById('check_out_hour').value = checkOutParts[0].padStart(2, '0');
                        document.getElementById('check_out_minute').value = checkOutParts[1].padStart(2, '0');
                    }
                }
                
                document.getElementById('status').value = attendance.status || 'present';
                document.getElementById('notes').value = attendance.notes || '';
                
                // Save the employee_id to select it after dropdown loads
                const savedEmployeeId = attendance.employee_id;
                console.log('Saved employee_id:', savedEmployeeId);
                
                // Load employees dropdown first, then select the employee
                await loadEmployeesForAttendance();
                
                // Add a small delay to ensure dropdown is fully rendered
                await new Promise(resolve => setTimeout(resolve, 100));
                
                // Select the saved employee ID after dropdown is populated
                const employeeSelect = document.getElementById('employee_id');
                if (employeeSelect && savedEmployeeId) {
                    // Simply set the value directly - it should match the numeric ID
                    employeeSelect.value = String(savedEmployeeId);
                    console.log('Set employee_id to:', savedEmployeeId, 'Current value:', employeeSelect.value);
                    
                    // Verify it was set correctly
                    if (employeeSelect.value === String(savedEmployeeId)) {
                        console.log('Successfully selected employee:', employeeSelect.options[employeeSelect.selectedIndex].textContent);
                    } else {
                        console.log('Failed to set employee. Trying manual selection...');
                        for (let i = 0; i < employeeSelect.options.length; i++) {
                            if (employeeSelect.options[i].value == savedEmployeeId) {
                                employeeSelect.selectedIndex = i;
                                console.log('Manually selected at index:', i);
                                break;
                            }
                        }
                    }
                }
                
                // Add hidden ID field
                const form = document.getElementById('attendanceForm');
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = id;
                form.appendChild(hiddenId);
                
                // Update form submit handler for edit
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    if (data.check_in_time) data.check_in_time = toWesternNumerals(String(data.check_in_time));
                    if (data.check_out_time) data.check_out_time = toWesternNumerals(String(data.check_out_time));
                    
                    try {
                        showHRMessage('Updating attendance...', 'info');
                        
                        const updateResponse = await fetch(hrApiUrl(`/hr/attendance.php?action=update&id=${id}`), {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(data)
                        });
                        
                        const updateResult = await updateResponse.json();
                        
                        if (updateResult.success) {
                            showHRMessage('Attendance updated successfully!', 'success');
                            setTimeout(() => {
                                closeHRModal();
                                loadHRStats();
                                showHRForm('attendance', 'list');
                            }, 1500);
                        } else {
                            showHRMessage(updateResult.message || 'Failed to update attendance', 'error');
                        }
                    } catch (error) {
                        console.error('Error updating attendance:', error);
                        showHRMessage('Failed to update attendance', 'error');
                    }
                }, true);
            }, 300);
        } else {
            showHRMessage(result.message || 'Failed to load attendance data', 'error');
        }
    } catch (error) {
        console.error('Error loading attendance:', error);
        showHRMessage('Failed to load attendance data', 'error');
    }
}

// Delete attendance
async function deleteAttendance(id) {
    if (confirm('Are you sure you want to delete this attendance record?')) {
        try {
            const response = await fetch(hrApiUrl(`/hr/attendance.php?action=delete&id=${id}`), {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                showHRMessage('Attendance deleted successfully!', 'success');
                loadHRStats();
                showHRForm('attendance', 'list');
            } else {
                showHRMessage(result.message || 'Failed to delete attendance', 'error');
            }
        } catch (error) {
            console.error('Error deleting attendance:', error);
            showHRMessage('Failed to delete attendance', 'error');
        }
    }
}

// Export functions for global access
window.showHRForm = showHRForm;
window.editEmployee = editEmployee;
window.deleteEmployee = deleteEmployee;
window.viewAttendance = viewAttendance;
window.editAttendance = editAttendance;
window.deleteAttendance = deleteAttendance;
window.toggleAllAttendance = toggleAllAttendance;
window.bulkDeleteAttendance = bulkDeleteAttendance;
window.bulkSetAttendanceStatus = bulkSetAttendanceStatus;
window.viewAdvance = viewAdvance;
window.editAdvance = editAdvance;
window.deleteAdvance = deleteAdvance;
window.toggleAllAdvances = toggleAllAdvances;
window.bulkDeleteAdvances = bulkDeleteAdvances;
window.bulkSetAdvanceStatus = bulkSetAdvanceStatus;
window.viewSalary = viewSalary;
window.editSalary = editSalary;
window.printHRViewForm = printHRViewForm;
window.downloadHRViewForm = downloadHRViewForm;
window.deleteSalary = deleteSalary;

// Refresh payroll table content without closing modal
async function refreshPayrollTableContent() {
    try {
        const modalBody = document.getElementById('hrModalBody');
        if (!modalBody) return;
        
        const payrollFilter = window.hrPayrollFilter || {};
        const content = await loadPayrollContent('list', 1, 5, payrollFilter.status || '', payrollFilter.search || '');
        modalBody.innerHTML = content;
        console.log('Payroll table content refreshed with updated statuses');
    } catch (error) {
        console.error('Error refreshing payroll table:', error);
        showHRMessage('Failed to refresh payroll table', 'error');
    }
}

// Bulk payroll actions
function toggleAllPayroll(checkbox) {
    console.log('toggleAllPayroll called with checked:', checkbox.checked);
    const checkboxes = document.querySelectorAll('.payroll-checkbox');
    console.log('Found checkboxes:', checkboxes.length);
    checkboxes.forEach(cb => {
        console.log('Setting checkbox value:', cb.value, 'to:', checkbox.checked);
        cb.checked = checkbox.checked;
    });
}

function selectAllPayroll() {
    console.log('selectAllPayroll called');
    const checkboxes = document.querySelectorAll('.payroll-checkbox');
    console.log('Found checkboxes:', checkboxes.length);
    checkboxes.forEach(cb => cb.checked = true);
    const selectAll = document.getElementById('selectAllPayroll');
    if (selectAll) selectAll.checked = true;
}

function deselectAllPayroll() {
    console.log('deselectAllPayroll called');
    const checkboxes = document.querySelectorAll('.payroll-checkbox');
    console.log('Found checkboxes:', checkboxes.length);
    checkboxes.forEach(cb => cb.checked = false);
    const selectAll = document.getElementById('selectAllPayroll');
    if (selectAll) selectAll.checked = false;
}

function getSelectedPayrollIds() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox:checked');
    console.log('Getting selected IDs. Checked count:', checkboxes.length);
    const ids = Array.from(checkboxes).map(cb => cb.value);
    console.log('Selected IDs:', ids);
    return ids;
}

async function bulkApprovePayroll() {
    console.log('bulkApprovePayroll called');
    const selectedIds = getSelectedPayrollIds();
    console.log('Selected IDs:', selectedIds);
    
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one payroll record', 'warning');
        return;
    }
    
    if (!confirm(`Mark ${selectedIds.length} payroll record(s) as Processed?`)) {
        return;
    }
    
    try {
        console.log('Sending request to bulk-update with:', { ids: selectedIds, status: 'processed' });
        const response = await fetch(hrApiUrl('/hr/salaries.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'processed' })
        });
        
        const result = await response.json();
        console.log('API Response:', result);
        
        if (result.success) {
            // Refresh the table content first, then show message
            await refreshPayrollTableContent();
            showHRMessage(`${selectedIds.length} payroll record(s) marked as Processed!`, 'success');
            loadHRStats();
        } else {
            showHRMessage(result.message || 'Failed to approve payroll records', 'error');
        }
    } catch (error) {
        console.error('Error approving payroll:', error);
        showHRMessage('Failed to approve payroll records', 'error');
    }
}

async function bulkRejectPayroll() {
    const selectedIds = getSelectedPayrollIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one payroll record', 'warning');
        return;
    }
    
    if (!confirm(`Reset ${selectedIds.length} payroll record(s) to Pending status?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/salaries.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'pending' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh table content first
            await refreshPayrollTableContent();
            showHRMessage(`${selectedIds.length} payroll record(s) reset to Pending!`, 'success');
            loadHRStats();
        } else {
            showHRMessage(result.message || 'Failed to reject payroll records', 'error');
        }
    } catch (error) {
        console.error('Error rejecting payroll:', error);
        showHRMessage('Failed to reject payroll records', 'error');
    }
}

async function bulkProcessPayroll() {
    const selectedIds = getSelectedPayrollIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one payroll record', 'warning');
        return;
    }
    
    if (!confirm(`Mark ${selectedIds.length} payroll record(s) as Paid?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/salaries.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'paid' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh table content first
            await refreshPayrollTableContent();
            showHRMessage(`${selectedIds.length} payroll record(s) marked as Paid!`, 'success');
            loadHRStats();
        } else {
            showHRMessage(result.message || 'Failed to process payroll records', 'error');
        }
    } catch (error) {
        console.error('Error processing payroll:', error);
        showHRMessage('Failed to process payroll records', 'error');
    }
}

async function bulkDeletePayroll() {
    const selectedIds = getSelectedPayrollIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one payroll record', 'warning');
        return;
    }
    
    if (!confirm(`Delete ${selectedIds.length} selected payroll record(s)? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/salaries.php?action=bulk-delete'), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh table content first
            await refreshPayrollTableContent();
            showHRMessage(`${selectedIds.length} payroll record(s) deleted successfully!`, 'success');
            loadHRStats();
        } else {
            showHRMessage(result.message || 'Failed to delete payroll records', 'error');
        }
    } catch (error) {
        console.error('Error deleting payroll:', error);
        showHRMessage('Failed to delete payroll records', 'error');
    }
}

// Export bulk functions to window
window.toggleAllPayroll = toggleAllPayroll;
window.selectAllPayroll = selectAllPayroll;
window.deselectAllPayroll = deselectAllPayroll;
window.bulkApprovePayroll = bulkApprovePayroll;
window.bulkRejectPayroll = bulkRejectPayroll;
window.bulkProcessPayroll = bulkProcessPayroll;
window.bulkDeletePayroll = bulkDeletePayroll;
// Print HR view form (Employee, Payroll, Attendance, Advances, Vehicle)
function printHRViewForm() {
    const modalTitle = document.getElementById('hrModalTitle');
    const modalBody = document.getElementById('hrModalBody');
    if (!modalBody) return;
    const title = modalTitle ? modalTitle.textContent : 'HR Record';
    const content = modalBody.innerHTML;
    const printWindow = window.open('', '_blank');
    if (!printWindow) { showHRMessage('Please allow popups to print', 'warning'); return; }
    printWindow.document.write(`
        <!DOCTYPE html><html><head><title>${title}</title>
        <style>body{font-family:Arial,sans-serif;padding:20px;color:#333;background:#fff}
        .document-view-details{background:#f5f5f5;padding:1rem;border-radius:8px;margin:1rem 0}
        .view-detail-row{padding:0.4rem 0;border-bottom:1px solid #ddd;display:flex;gap:0.5rem}
        .view-detail-row strong{min-width:140px;color:#555}
        .status-badge{padding:0.2rem 0.5rem;border-radius:4px;font-size:0.85rem}
        .document-view-actions{margin-top:1rem;padding-top:1rem;border-top:1px solid #ddd}
        @media print{.document-view-actions{display:none}}</style></head>
        <body><h2>${title}</h2>${content}</body></html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 250);
}

// Download HR view form as HTML
function downloadHRViewForm() {
    const modalTitle = document.getElementById('hrModalTitle');
    const modalBody = document.getElementById('hrModalBody');
    if (!modalBody) return;
    const title = (modalTitle ? modalTitle.textContent : 'HR Record').replace(/[<>:"/\\|?*]/g, '_');
    const content = modalBody.innerHTML;
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
<style>body{font-family:Arial,sans-serif;padding:20px;color:#333;background:#fff}
.document-view-details{background:#f5f5f5;padding:1rem;border-radius:8px;margin:1rem 0}
.view-detail-row{padding:0.4rem 0;border-bottom:1px solid #ddd;display:flex;gap:0.5rem}
.view-detail-row strong{min-width:140px;color:#555}
.status-badge{padding:0.2rem 0.5rem;border-radius:4px;font-size:0.85rem}
.document-view-actions{display:none}</style></head>
<body><h2>${title}</h2>${content}</body></html>`;
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = title + '_' + new Date().toISOString().slice(0,10) + '.html';
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 100);
}

// Print document
async function printDocument(id, fileName) {
    if (!id) {
        showHRMessage('Invalid document ID', 'error');
        return;
    }
    
    try {
        // Fetch document details
        const response = await fetch(hrApiUrl(`/hr/documents.php?action=get&id=${id}`));
        const result = await response.json();
        
        if (result.success && result.data) {
            const doc = result.data;
            const fileUrl = doc.file_path ? doc.file_path : '';
            const mimeType = doc.mime_type || '';
            
            // Create a hidden iframe, load the document, then print
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = 'none';
            document.body.appendChild(iframe);
            
            // Load document into iframe
            iframe.onload = function() {
                try {
                    // Trigger print dialog
                    iframe.contentWindow.print();
                    // Remove iframe after print dialog closes
                    setTimeout(() => {
                        document.body.removeChild(iframe);
                    }, 1000);
                } catch (e) {
                    console.error('Print error:', e);
                    document.body.removeChild(iframe);
                    showHRMessage('Failed to print document', 'error');
                }
            };
            
            iframe.src = fileUrl;
        } else {
            showHRMessage(result.message || 'Failed to load document for printing', 'error');
        }
    } catch (error) {
        console.error('Error printing document:', error);
        showHRMessage('Failed to print document', 'error');
    }
}

// Bulk document actions
function toggleAllDocuments(checkbox) {
    const checkboxes = document.querySelectorAll('.document-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function getSelectedDocumentIds() {
    const checkboxes = document.querySelectorAll('.document-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    console.log('Getting selected document IDs. Checked count:', checkboxes.length);
    console.log('Selected IDs:', ids);
    return ids;
}

async function bulkActivateDocuments() {
    const selectedIds = getSelectedDocumentIds();
    
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one document', 'warning');
        return;
    }
    
    if (!confirm(`Activate ${selectedIds.length} document(s)?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/documents.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'active' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage(`${selectedIds.length} document(s) activated successfully!`, 'success');
            loadHRStats();
            if (window.currentHRModule === 'documents') {
                showHRForm('documents', 'list');
            }
        } else {
            showHRMessage(result.message || 'Failed to activate documents', 'error');
        }
    } catch (error) {
        console.error('Error activating documents:', error);
        showHRMessage('Failed to activate documents', 'error');
    }
}

async function bulkDeactivateDocuments() {
    const selectedIds = getSelectedDocumentIds();
    
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one document', 'warning');
        return;
    }
    
    if (!confirm(`Deactivate ${selectedIds.length} document(s)?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/documents.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'inactive' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage(`${selectedIds.length} document(s) deactivated successfully!`, 'success');
            loadHRStats();
            if (window.currentHRModule === 'documents') {
                showHRForm('documents', 'list');
            }
        } else {
            showHRMessage(result.message || 'Failed to deactivate documents', 'error');
        }
    } catch (error) {
        console.error('Error deactivating documents:', error);
        showHRMessage('Failed to deactivate documents', 'error');
    }
}

async function bulkArchiveDocuments() {
    const selectedIds = getSelectedDocumentIds();
    
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one document', 'warning');
        return;
    }
    
    if (!confirm(`Archive ${selectedIds.length} document(s)?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/documents.php?action=bulk-update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds, status: 'archived' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage(`${selectedIds.length} document(s) archived successfully!`, 'success');
            loadHRStats();
            if (window.currentHRModule === 'documents') {
                showHRForm('documents', 'list');
            }
        } else {
            showHRMessage(result.message || 'Failed to archive documents', 'error');
        }
    } catch (error) {
        console.error('Error archiving documents:', error);
        showHRMessage('Failed to archive documents', 'error');
    }
}

async function bulkDeleteDocuments() {
    const selectedIds = getSelectedDocumentIds();
    
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one document', 'warning');
        return;
    }
    
    if (!confirm(`Delete ${selectedIds.length} document(s)?`)) {
        return;
    }
    
    try {
        const response = await fetch(hrApiUrl('/hr/documents.php?action=bulk-delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: selectedIds })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showHRMessage(`${selectedIds.length} document(s) deleted successfully!`, 'success');
            loadHRStats();
            if (window.currentHRModule === 'documents') {
                showHRForm('documents', 'list');
            }
        } else {
            showHRMessage(result.message || 'Failed to delete documents', 'error');
        }
    } catch (error) {
        console.error('Error deleting documents:', error);
        showHRMessage('Failed to delete documents', 'error');
    }
}

// Employee bulk actions
function toggleAllEmployees(checkbox) {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => { cb.checked = checkbox.checked; });
}
function getSelectedEmployeeIds() {
    const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}
async function bulkDeleteEmployees() {
    const selectedIds = getSelectedEmployeeIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one employee', 'warning');
        return;
    }
    if (!confirm('Delete ' + selectedIds.length + ' employee(s)?')) return;
    let success = 0, failed = 0;
    for (const id of selectedIds) {
        try {
            const res = await fetch(hrApiUrl(`/hr/employees.php?action=delete&id=${id}`), { method: 'DELETE' });
            const r = await res.json();
            if (r.success) success++; else failed++;
        } catch (_) { failed++; }
    }
    if (success > 0) {
        showHRMessage(success + ' employee(s) deleted!' + (failed ? ' ' + failed + ' failed.' : ''), failed ? 'warning' : 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'employees') {
            await loadHRModuleContent('employees', 'list', modalBody, 1, 5);
        }
    } else {
        showHRMessage('Failed to delete employees', 'error');
    }
}
async function bulkSetEmployeeStatus(status) {
    const selectedIds = getSelectedEmployeeIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one employee', 'warning');
        return;
    }
    const label = status === 'active' ? 'Active' : 'Inactive';
    if (!confirm('Set ' + selectedIds.length + ' employee(s) to ' + label + '?')) return;
    let success = 0, failed = 0;
    for (const id of selectedIds) {
        try {
            const res = await fetch(hrApiUrl(`/hr/employees.php?action=update&id=${id}`), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: status })
            });
            const r = await res.json();
            if (r.success) success++; else failed++;
        } catch (_) { failed++; }
    }
    if (success > 0) {
        showHRMessage(success + ' employee(s) updated!' + (failed ? ' ' + failed + ' failed.' : ''), failed ? 'warning' : 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && window.currentHRModule === 'employees') {
            await loadHRModuleContent('employees', 'list', modalBody, 1, 5);
        }
    } else {
        showHRMessage('Failed to update employees', 'error');
    }
}

// Vehicle bulk actions
function toggleAllVehicles(checkbox) {
    const checkboxes = document.querySelectorAll('.vehicle-checkbox');
    checkboxes.forEach(cb => { cb.checked = checkbox.checked; });
}

function getSelectedVehicleIds() {
    const checkboxes = document.querySelectorAll('.vehicle-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

async function bulkDeleteVehicles() {
    const selectedIds = getSelectedVehicleIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one vehicle', 'warning');
        return;
    }
    if (!confirm(`Delete ${selectedIds.length} vehicle(s)?`)) return;
    let success = 0, failed = 0;
    for (const id of selectedIds) {
        try {
            const res = await fetch(hrApiUrl(`/hr/cars.php?action=delete&id=${id}`), { method: 'DELETE' });
            const r = await res.json();
            if (r.success) success++; else failed++;
        } catch (_) { failed++; }
    }
    if (success > 0) {
        showHRMessage(`${success} vehicle(s) deleted successfully!` + (failed ? ` ${failed} failed.` : ''), failed ? 'warning' : 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && (window.currentHRModule === 'vehicles' || window.currentHRModule === 'cars')) {
            await loadHRModuleContent('vehicles', 'list', modalBody, 1, 5);
        }
    } else {
        showHRMessage('Failed to delete vehicles', 'error');
    }
}

async function bulkSetVehicleStatus(status) {
    const selectedIds = getSelectedVehicleIds();
    if (selectedIds.length === 0) {
        showHRMessage('Please select at least one vehicle', 'warning');
        return;
    }
    const statusLabels = { available: 'Available', inuse: 'In Use', maintenance: 'Maintenance' };
    if (!confirm(`Set ${selectedIds.length} vehicle(s) to ${statusLabels[status] || status}?`)) return;
    let success = 0, failed = 0;
    for (const id of selectedIds) {
        try {
            const res = await fetch(hrApiUrl(`/hr/cars.php?action=update&id=${id}`), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: status })
            });
            const r = await res.json();
            if (r.success) success++; else failed++;
        } catch (_) { failed++; }
    }
    if (success > 0) {
        showHRMessage(`${success} vehicle(s) updated!` + (failed ? ` ${failed} failed.` : ''), failed ? 'warning' : 'success');
        loadHRStats();
        const modalBody = document.getElementById('hrModalBody');
        if (modalBody && (window.currentHRModule === 'vehicles' || window.currentHRModule === 'cars')) {
            await loadHRModuleContent('vehicles', 'list', modalBody, 1, 5);
        }
    } else {
        showHRMessage('Failed to update vehicles', 'error');
    }
}

window.viewDocument = viewDocument;
window.editDocument = editDocument;
window.downloadDocument = downloadDocument;
window.deleteDocument = deleteDocument;
window.printDocument = printDocument;
window.toggleAllDocuments = toggleAllDocuments;
window.bulkActivateDocuments = bulkActivateDocuments;
window.bulkDeactivateDocuments = bulkDeactivateDocuments;
window.bulkArchiveDocuments = bulkArchiveDocuments;
window.bulkDeleteDocuments = bulkDeleteDocuments;
window.viewEmployee = viewEmployee;
window.toggleAllEmployees = toggleAllEmployees;
window.bulkDeleteEmployees = bulkDeleteEmployees;
window.bulkSetEmployeeStatus = bulkSetEmployeeStatus;
window.viewVehicle = viewVehicle;
window.editVehicle = editVehicle;
window.deleteVehicle = deleteVehicle;
window.toggleAllVehicles = toggleAllVehicles;
window.bulkDeleteVehicles = bulkDeleteVehicles;
window.bulkSetVehicleStatus = bulkSetVehicleStatus;
window.saveHRSettings = saveHRSettings;
window.closeHRModal = closeHRModal;
window.loadHRContent = loadHRContent;
window.loadHRContentWithPagination = loadHRContentWithPagination;
