/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.init.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.init.js`.
 */
// Delegated handler for COA Clear Search (no inline onclick)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="clear-coa-search"]');
    if (btn) {
        e.preventDefault();
        var el = document.getElementById('coaSearch');
        if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
    }
}, true);

// Ensure all API fetches include credentials (session cookies) - run before any API calls
(function() {
    var _fetch = window.fetch;
    if (typeof _fetch !== 'function') return;
    window.fetch = function(url, opts) {
        var u = (typeof url === 'string') ? url : (url && url.url ? url.url : '');
        var finalOpts = opts;
        if (u.indexOf('/api/') >= 0) {
            // Clone opts to avoid mutating the original object
            finalOpts = opts ? Object.assign({}, opts) : {};
            if (finalOpts.credentials === undefined) {
                finalOpts.credentials = 'include';
            }
        }
        return _fetch.call(this, url, finalOpts);
    };
})();

// Add handleNavClick/handleQuickAction to professionalAccounting (runs when instance exists)
function attachNavHandlers() {
    var pa = window.professionalAccounting;
    if (!pa) return;
    if (typeof pa.handleNavClick !== 'function') {
        pa.handleNavClick = function(tabName) {
            if (!tabName) return;
            try {
                if (tabName === 'dashboard') this.switchTab('dashboard');
                else if (tabName === 'chart-of-accounts') { this.openChartOfAccountsModal(); this.switchTab('dashboard'); }
                else if (tabName === 'cost-centers') { this.openCostCentersModal(); this.switchTab('dashboard'); }
                else if (tabName === 'bank-guarantee') { this.openBankGuaranteeModal(); this.switchTab('dashboard'); }
                else if (tabName === 'support-payments') { this.openSupportPaymentsModal(); this.switchTab('dashboard'); }
                else if (tabName === 'journal-entries' || tabName === 'general-ledger') { this.openGeneralLedgerModal(); this.switchTab('dashboard'); }
                else if (tabName === 'expenses') { this.openVouchersModal('expenses'); this.switchTab('dashboard'); }
                else if (tabName === 'receipts') { this.openReceiptVouchersModal(); this.switchTab('dashboard'); }
                else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers') { this.openVouchersModal(); this.switchTab('dashboard'); }
                else if (tabName === 'accounts-payable') { this.openPayablesModal(); this.switchTab('dashboard'); }
                else if (['electronic-invoices','invoices','accounts-receivable'].indexOf(tabName) >= 0) { this.openReceivablesModal(); this.switchTab('dashboard'); }
                else if (tabName === 'entry-approval') { this.openEntryApprovalModal(); this.switchTab('dashboard'); }
                else if (['bank-reconciliation','banking-cash','banking'].indexOf(tabName) >= 0) { this.loadBankingCashModal(); this.switchTab('dashboard'); }
                else if (tabName === 'financial-reports' || tabName === 'reports') { this.openReportsModal(); this.switchTab('dashboard'); }
                else this.switchTab(tabName);
            } catch (e) { console.error('handleNavClick error:', e); }
        };
    }
    if (typeof pa.handleQuickAction !== 'function') {
        pa.handleQuickAction = function(action) {
            if (!action) return;
            try {
                if (action === 'quick-entry') this.openQuickEntry();
                else if (action === 'open-receivables-modal') this.openInvoiceModal();
                else if (action === 'open-payment-voucher') this.openPaymentVoucherModal();
                else if (action === 'open-receipt-voucher') this.openReceiptVoucherModal();
                else if (action === 'open-reports-modal') this.openReportsModal();
                else if (action === 'open-settings-modal') this.openSettingsModal();
            } catch (e) { console.error('handleQuickAction error:', e); }
        };
    }
}

window.ACCOUNTING_DEBUG = (typeof window.ACCOUNTING_DEBUG !== 'undefined') ? window.ACCOUNTING_DEBUG : false;
function _log() {
    if (window.ACCOUNTING_DEBUG && console && console.log) console.log('[Accounting Tab Debug]', ...arguments);
}

// Global wrappers for inline onclick
window.handleAccountingNavClick = function(tabName) {
    _log('handleAccountingNavClick', tabName, 'instance=', !!window.professionalAccounting);
    if (window.professionalAccounting && typeof window.professionalAccounting.handleNavClick === 'function') {
        window.professionalAccounting.handleNavClick(tabName);
        _log('handleNavClick executed', tabName);
    } else {
        console.warn('[Accounting] Nav click FAILED - professionalAccounting not ready', tabName);
    }
};
window.handleAccountingQuickAction = function(action) {
    _log('handleAccountingQuickAction', action, 'instance=', !!window.professionalAccounting);
    if (window.professionalAccounting && typeof window.professionalAccounting.handleQuickAction === 'function') {
        window.professionalAccounting.handleQuickAction(action);
        _log('handleQuickAction executed', action);
    } else {
        console.warn('[Accounting] Quick action FAILED - professionalAccounting not ready', action);
    }
};

// Log ALL clicks in accounting area when debug on (runs first in capture)
document.addEventListener('click', function(e) {
    if (!window.ACCOUNTING_DEBUG) return;
    var inAcc = e.target.closest('.accounting-container');
    if (!inAcc) return;
    var nav = e.target.closest('.top-nav-link, .quick-action-btn, .tab-btn');
    var pe = nav ? window.getComputedStyle(nav).pointerEvents : 'n/a';
    var disp = nav ? window.getComputedStyle(nav).display : 'n/a';
    console.log('[Accounting Tab Debug] CLICK', {
        target: (e.target.tagName || '') + (e.target.className ? '.' + String(e.target.className).split(/\s+/)[0] : ''),
        onNav: !!nav,
        pointerEvents: pe,
        display: disp,
        tab: nav && nav.dataset ? nav.dataset.tab : null,
        action: nav && nav.dataset ? nav.dataset.action : null
    });
}, true);

// Emergency capture-phase handler
document.addEventListener('click', function(e) {
    var el = e.target.closest('.top-nav-link[data-tab], .quick-action-btn[data-action], .tab-btn[data-tab]');
    if (!el || el.classList.contains('permission-denied')) return;
    _log('CAPTURE: click on', el.className, 'tab=', el.dataset.tab, 'action=', el.dataset.action);
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if (el.classList.contains('top-nav-link') || el.classList.contains('tab-btn')) {
        var tab = el.dataset.tab;
        if (window.professionalAccounting && window.professionalAccounting.handleNavClick) {
            _log('CAPTURE: calling handleNavClick', tab);
            window.professionalAccounting.handleNavClick(tab);
        } else {
            console.warn('[Accounting] CAPTURE: professionalAccounting not ready for tab', tab);
        }
    } else if (el.classList.contains('quick-action-btn')) {
        var action = el.dataset.action;
        if (window.professionalAccounting && window.professionalAccounting.handleQuickAction) {
            _log('CAPTURE: calling handleQuickAction', action);
            window.professionalAccounting.handleQuickAction(action);
        } else {
            console.warn('[Accounting] CAPTURE: professionalAccounting not ready for action', action);
        }
    }
}, true);

function patchApiCredentials() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var orig = ProfessionalAccounting.prototype.refreshDashboardCards;
    if (!orig) return;
    ProfessionalAccounting.prototype.refreshDashboardCards = async function() {
        try {
            var response = await fetch(this.apiBase + '/unified-calculations.php?type=all', { credentials: 'include' });
            var data = await response.json();
            if (data.success && data.dashboard) {
                this.updateOverviewCards({
                    total_revenue: data.dashboard.total_revenue,
                    total_expenses: data.dashboard.total_expenses,
                    net_profit: data.dashboard.net_profit,
                    cash_balance: data.dashboard.cash_balance,
                    total_receivables: data.dashboard.total_receivables,
                    total_payables: data.dashboard.total_payables,
                    receivables_count: data.dashboard.receivables_count,
                    payables_count: data.dashboard.payables_count
                });
            }
            if (typeof this.loadCashFlowSummary === 'function') this.loadCashFlowSummary();
            if (typeof this.loadFinancialSummary === 'function') this.loadFinancialSummary();
        } catch (err) {
            console.error('Error refreshing dashboard cards:', err);
        }
    };
}

window.patchBankGuaranteeButtons = function() {
    var tbody = document.getElementById('bankGuaranteeTableBody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) return;
    
    var patched = 0;
    rows.forEach(function(row, index) {
        // Skip empty rows
        if (row.querySelector('.empty-state, .loading-state')) return;
        
        // Find actions cell - last td in the row
        var tds = row.querySelectorAll('td');
        if (tds.length === 0) return;
        var actionsCell = tds[tds.length - 1];
        if (!actionsCell) return;
        
        // Check if already patched
        if (actionsCell.querySelector('.bank-guarantee-actions')) {
            return; // Already patched
        }
        
        // Get ID from checkbox (most reliable)
        var checkbox = row.querySelector('.bank-guarantee-checkbox');
        var bgId = checkbox ? checkbox.value : '';
        
        // If no checkbox, try to extract from any element in the cell
        if (!bgId) {
            var anyBtn = actionsCell.querySelector('button, a, [data-id]');
            if (anyBtn) {
                bgId = anyBtn.getAttribute('data-id') || '';
                if (!bgId && anyBtn.getAttribute('href')) {
                    var match = anyBtn.getAttribute('href').match(/[?&]id=(\d+)/);
                    if (match) bgId = match[1];
                }
            }
        }
        
        // If still no ID, try to get from row data attribute or any other source
        if (!bgId) {
            bgId = row.getAttribute('data-id') || '';
        }
        
        // Replace entire cell content - use CSS classes only, no inline styles
        actionsCell.className = 'actions-cell';
        actionsCell.innerHTML = '<div class="bank-guarantee-actions">' +
            '<button class="action-btn action-btn-edit" data-action="edit-bank-guarantee" data-id="' + (bgId || '') + '" title="Edit">' +
            '<i class="fas fa-edit"></i>' +
            '<span class="btn-label">Edit</span>' +
            '</button>' +
            '<button class="action-btn action-btn-delete" data-action="delete-bank-guarantee" data-id="' + (bgId || '') + '" title="Delete">' +
            '<i class="fas fa-trash"></i>' +
            '<span class="btn-label">Delete</span>' +
            '</button>' +
            '</div>';
        
        patched++;
    });
    
}

function patchBankGuaranteesLoad() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var origRender = ProfessionalAccounting.prototype.renderBankGuaranteeTable;
    if (!origRender) return;
    ProfessionalAccounting.prototype.renderBankGuaranteeTable = function() {
        var filtered = (this.bankGuaranteeData || []).slice();
        var searchTerm = ((this.bankGuaranteeSearchTerm || '') + '').toLowerCase();
        if (searchTerm) {
            filtered = filtered.filter(function(bg) {
                return (bg.reference_number && bg.reference_number.toLowerCase().indexOf(searchTerm) >= 0) ||
                    (bg.bank_name && bg.bank_name.toLowerCase().indexOf(searchTerm) >= 0);
            });
        }
        var statusFilterEl = document.getElementById('bankGuaranteeStatusFilter');
        var statusFilter = statusFilterEl ? statusFilterEl.value : '';
        if (statusFilter) {
            filtered = filtered.filter(function(bg) { return bg.status === statusFilter; });
        }
        var totalCount = filtered.length;
        var perPage = this.bankGuaranteePerPage || 10;
        var totalPages = Math.ceil(totalCount / perPage) || 1;
        if (this.bankGuaranteeCurrentPage > totalPages) {
            this.bankGuaranteeCurrentPage = Math.max(1, totalPages);
        }
        
        // Call original render first
        origRender.apply(this, arguments);
        
        // Patch buttons after render
        setTimeout(patchBankGuaranteeButtons, 50);
        setTimeout(patchBankGuaranteeButtons, 150);
        setTimeout(patchBankGuaranteeButtons, 300);
        setTimeout(patchBankGuaranteeButtons, 600);
    };
    
    // Also set up MutationObserver to catch dynamic updates
    var observer = new MutationObserver(function(mutations) {
        var shouldPatch = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0 || mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.id === 'bankGuaranteeTableBody' || 
                            node.querySelector('#bankGuaranteeTableBody') ||
                            node.classList.contains('bank-guarantee-actions') ||
                            node.closest('#bankGuaranteeTableBody')) {
                            shouldPatch = true;
                        }
                    }
                });
            }
        });
        if (shouldPatch) {
            console.log('[Bank Guarantee Patch] MutationObserver triggered patch');
            setTimeout(patchBankGuaranteeButtons, 50);
        }
    });
    
    // Observe the modal container and table body
    setTimeout(function() {
        var modal = document.getElementById('bankGuaranteeModal');
        var tbody = document.getElementById('bankGuaranteeTableBody');
        if (modal) {
            observer.observe(modal, { childList: true, subtree: true });
        }
        if (tbody) {
            observer.observe(tbody, { childList: true, subtree: true });
        }
    }, 1000);
    
    // Also patch immediately if table already exists
    setTimeout(function() {
        if (document.getElementById('bankGuaranteeTableBody')) {
            patchBankGuaranteeButtons();
        }
    }, 2000);
}

function patchPaymentVoucherDropdowns() {
    var pa = window.professionalAccounting;
    if (!pa || typeof pa.loadPaymentVoucherAccountOptions !== 'function') return;
    var apiBase = pa.apiBase || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/accounting';
    pa.loadPaymentVoucherAccountOptions = async function(modalOrForm) {
        var container = modalOrForm || document;
        var cashSelect = container.querySelector('#paymentVoucherCashAccount') || container.querySelector('#cash_account');
        var payeeSelect = container.querySelector('#paymentVoucherPayee') || container.querySelector('#collected_from');
        if (!cashSelect || !payeeSelect) return;
        cashSelect.innerHTML = '<option value="">Loading...</option>';
        payeeSelect.innerHTML = '<option value="">Loading...</option>';
        try {
            var res = await Promise.all([
                fetch(apiBase + '/banks.php', { credentials: 'include' }).catch(function() { return { ok: false }; }),
                fetch(apiBase + '/vendors.php', { credentials: 'include' }).catch(function() { return { ok: false }; }),
                fetch(apiBase + '/accounts.php?is_active=1&ensure_entity_accounts=1', { credentials: 'include' }).catch(function() { return { ok: false }; })
            ]);
            var banks = (res[0].ok ? await res[0].json().catch(function() { return {}; }) : {}).banks || [];
            var vendors = (res[1].ok ? await res[1].json().catch(function() { return {}; }) : {}).vendors || [];
            var accounts = (res[2].ok ? await res[2].json().catch(function() { return {}; }) : {}).accounts || [];
            if (!Array.isArray(banks)) banks = []; if (!Array.isArray(vendors)) vendors = []; if (!Array.isArray(accounts)) accounts = [];
            var allOptions = [];
            allOptions.push({ value: '0', label: 'Cash' });
            banks.forEach(function(b) {
                allOptions.push({ value: 'bank_' + b.id, label: (b.account_name || b.bank_name || 'Bank ' + b.id) + (b.account_number ? ' (' + b.account_number + ')' : '') });
            });
            vendors.forEach(function(v) {
                allOptions.push({ value: 'vendor_' + v.id, label: v.vendor_name || 'Vendor ' + v.id });
            });
            accounts.forEach(function(a) {
                allOptions.push({ value: 'gl_' + a.id, label: (a.account_code ? a.account_code + ' ' : '') + (a.account_name || 'Account ' + a.id) });
            });
            var fillSelect = function(sel, placeholder) {
                sel.innerHTML = '<option value="">' + placeholder + '</option>';
                allOptions.forEach(function(item) {
                    var o = document.createElement('option');
                    o.value = item.value;
                    o.textContent = item.label;
                    sel.appendChild(o);
                });
            };
            fillSelect(cashSelect, 'Select Cash/Bank');
            fillSelect(payeeSelect, 'Select Payee / Expense Account');
        } catch (e) {
            cashSelect.innerHTML = '<option value="">Error loading</option>';
            payeeSelect.innerHTML = '<option value="">Error loading</option>';
        }
    };
}

function patchLoadBankGuarantees() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var origLoad = ProfessionalAccounting.prototype.loadBankGuarantees;
    if (!origLoad) return;
    ProfessionalAccounting.prototype.loadBankGuarantees = async function() {
        var result = await origLoad.apply(this, arguments);
        // Patch buttons after data loads
        setTimeout(patchBankGuaranteeButtons, 200);
        setTimeout(patchBankGuaranteeButtons, 500);
        // Remove Apply button if it exists
        setTimeout(function() {
            var applyBtn = document.getElementById('bankGuaranteeApplyFilters');
            if (applyBtn) {
                applyBtn.remove();
            }
        }, 100);
        return result;
    };
}

function patchBankGuaranteeModalOpen() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var origOpen = ProfessionalAccounting.prototype.openBankGuaranteeModal;
    if (!origOpen) return;
    ProfessionalAccounting.prototype.openBankGuaranteeModal = function() {
        var result = origOpen.apply(this, arguments);
        // Remove Apply button after modal opens
        setTimeout(function() {
            var applyBtn = document.getElementById('bankGuaranteeApplyFilters');
            if (applyBtn) {
                applyBtn.remove();
            }
            // Ensure filters auto-apply
            var searchInput = document.getElementById('bankGuaranteeSearch');
            var statusFilter = document.getElementById('bankGuaranteeStatusFilter');
            var dateFrom = document.getElementById('bankGuaranteeDateFrom');
            var dateTo = document.getElementById('bankGuaranteeDateTo');
            var pageSize = document.getElementById('bankGuaranteePageSize');
            
            if (searchInput && !searchInput.hasAttribute('data-auto-apply')) {
                searchInput.setAttribute('data-auto-apply', 'true');
                var searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        if (window.professionalAccounting) {
                            window.professionalAccounting.bankGuaranteeCurrentPage = 1;
                            window.professionalAccounting.renderBankGuaranteeTable();
                        }
                    }, 300);
                });
            }
            
            if (statusFilter && !statusFilter.hasAttribute('data-auto-apply')) {
                statusFilter.setAttribute('data-auto-apply', 'true');
                statusFilter.addEventListener('change', function() {
                    if (window.professionalAccounting) {
                        window.professionalAccounting.bankGuaranteeCurrentPage = 1;
                        window.professionalAccounting.renderBankGuaranteeTable();
                    }
                });
            }
            
            if (dateFrom && !dateFrom.hasAttribute('data-auto-apply')) {
                dateFrom.setAttribute('data-auto-apply', 'true');
                dateFrom.addEventListener('change', function() {
                    if (window.professionalAccounting) {
                        window.professionalAccounting.bankGuaranteeCurrentPage = 1;
                        window.professionalAccounting.loadBankGuarantees();
                    }
                });
            }
            
            if (dateTo && !dateTo.hasAttribute('data-auto-apply')) {
                dateTo.setAttribute('data-auto-apply', 'true');
                dateTo.addEventListener('change', function() {
                    if (window.professionalAccounting) {
                        window.professionalAccounting.bankGuaranteeCurrentPage = 1;
                        window.professionalAccounting.loadBankGuarantees();
                    }
                });
            }
            
            if (pageSize && !pageSize.hasAttribute('data-auto-apply')) {
                pageSize.setAttribute('data-auto-apply', 'true');
                pageSize.addEventListener('change', function() {
                    if (window.professionalAccounting) {
                        window.professionalAccounting.bankGuaranteePerPage = parseInt(this.value);
                        window.professionalAccounting.bankGuaranteeCurrentPage = 1;
                        window.professionalAccounting.renderBankGuaranteeTable();
                    }
                });
            }
        }, 200);
        return result;
    };
}

function initProfessionalAccounting() {
    if (window.professionalAccounting) {
        attachNavHandlers();
        patchApiCredentials();
        patchBankGuaranteesLoad();
        patchLoadBankGuarantees();
        patchBankGuaranteeModalOpen();
        patchPaymentVoucherDropdowns();
        _log('Already initialized');
        return;
    }
    try {
        if (typeof ProfessionalAccounting !== 'undefined') {
            window.professionalAccounting = new ProfessionalAccounting();
        }
        attachNavHandlers();
        patchApiCredentials();
        patchBankGuaranteesLoad();
        patchLoadBankGuarantees();
        patchBankGuaranteeModalOpen();
        patchPaymentVoucherDropdowns();
        _log('Init OK');
        if (window.ACCOUNTING_DEBUG) {
            var b = document.getElementById('accounting-debug-bar');
            if (!b) {
                b = document.createElement('div');
                b.id = 'accounting-debug-bar';
                b.className = 'accounting-debug-bar';
                b.textContent = '[Accounting Debug] ACCOUNTING_DEBUG=true. Click a tab, check Console. Set ACCOUNTING_DEBUG=false to hide.';
                document.body.appendChild(b);
            }
        }
    } catch (err) {
        console.error('[Accounting] Init error:', err);
    }
}

// Run patch immediately so it applies before any save (IIFE must self-invoke)
(function() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var orig = ProfessionalAccounting.prototype.saveBankGuarantee;
    if (!orig) return;
    ProfessionalAccounting.prototype.saveBankGuarantee = async function(id) {
        // Prevent double submission - only one save at a time
        if (this._bankGuaranteeSaving) return;
        this._bankGuaranteeSaving = true;
        
        var ref = document.getElementById('bgReferenceNumber');
        var bank = document.getElementById('bgBankName');
        var amt = document.getElementById('bgAmount');
        var curr = document.getElementById('bgCurrency');
        var issueEl = document.getElementById('bgIssueDate');
        var expEl = document.getElementById('bgExpiryDate');
        var statusEl = document.getElementById('bgStatus');
        var descEl = document.getElementById('bgDescription');
        var referenceNumber = ref ? ref.value.trim() : '';
        var bankName = bank ? bank.value.trim() : '';
        var amount = parseFloat(amt ? amt.value : 0) || 0;
        var currency = curr ? curr.value : 'SAR';
        var issueDateRaw = issueEl ? issueEl.value : '';
        var expiryDateRaw = expEl ? expEl.value : '';
        var status = statusEl ? statusEl.value : 'active';
        var description = descEl ? descEl.value.trim() : '';
        if (!referenceNumber || !bankName || !issueDateRaw) {
            this.showToast('Reference number, bank name, and issue date are required', 'error');
            this._bankGuaranteeSaving = false;
            return;
        }
        var issueDate = typeof this.formatDateForAPI === 'function' ? this.formatDateForAPI(issueDateRaw) : issueDateRaw;
        var expiryDate = (expiryDateRaw && expiryDateRaw.trim()) ? (typeof this.formatDateForAPI === 'function' ? this.formatDateForAPI(expiryDateRaw) : expiryDateRaw) : null;
        if (!issueDate) issueDate = issueDateRaw;
        
        // No confirmation dialog - save immediately
        
        // Disable submit button to prevent double submission
        var form = document.getElementById('bankGuaranteeForm');
        var submitBtn = form ? form.querySelector('button[type="submit"], button.btn-primary') : null;
        var wasDisabled = submitBtn ? submitBtn.disabled : false;
        if (submitBtn) {
            submitBtn.disabled = true;
            if (submitBtn.textContent) submitBtn.textContent = (id ? 'Updating...' : 'Creating...');
        }
        
        try {
            var url = id ? this.apiBase + '/bank-guarantees.php?id=' + id : this.apiBase + '/bank-guarantees.php';
            var method = id ? 'PUT' : 'POST';
            var response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    reference_number: referenceNumber,
                    bank_name: bankName,
                    amount: amount,
                    currency: currency || 'SAR',
                    issue_date: issueDate,
                    expiry_date: expiryDate,
                    status: (status && ['active','expired','cancelled'].indexOf(status) >= 0) ? status : 'active',
                    description: description
                })
            });
            var data = {};
            var responseText = '';
            try {
                responseText = await response.text();
                data = responseText ? JSON.parse(responseText) : { success: false, message: 'Empty server response' };
            } catch (parseErr) {
                console.error('Failed to parse response:', parseErr, 'Response text:', responseText);
                data = { success: false, message: 'Invalid server response: ' + (responseText || 'Empty') };
            }
            // Check for errors: either HTTP error OR success: false from backend
            if (!response.ok || (data && data.success === false)) {
                var errMsg = (data && data.message) ? data.message : ('HTTP ' + response.status + ' ' + response.statusText + ' – Failed to save');
                // Only log to console if it's not a validation error (400 with message is expected)
                if (response.status !== 400 || !data.message) {
                    console.error('Bank guarantee save failed:', errMsg, 'Response:', data);
                }
                this.showToast(errMsg, 'error');
                
                // If it's a reference number duplicate error, focus on that field
                if (errMsg.toLowerCase().indexOf('reference number') >= 0 && ref) {
                    ref.focus();
                    ref.select();
                    ref.classList.add('is-invalid');
                    setTimeout(function() {
                        if (ref) ref.classList.remove('is-invalid');
                    }, 3000);
                }
                
                // Re-enable button on error - form stays open so user can fix
                if (submitBtn) {
                    submitBtn.disabled = wasDisabled;
                    if (submitBtn.textContent) submitBtn.textContent = (id ? 'Update' : 'Create') + ' Bank Guarantee';
                }
                this._bankGuaranteeSaving = false;
                return; // Don't close modal or reload table on error
            }
            if (data.success) {
                this.showToast(data.message || (id ? 'Bank guarantee updated' : 'Bank guarantee created'), 'success');
                var formModal = document.getElementById('bankGuaranteeFormModal');
                if (formModal) {
                    // Close modal directly without confirmation since we just saved successfully
                    formModal.classList.remove('accounting-modal-visible', 'show-modal');
                    formModal.classList.add('accounting-modal-hidden');
                    formModal.removeAttribute('data-modal-visible');
                    if (formModal.parentNode) formModal.remove();
                    document.body.classList.remove('body-no-scroll');
                    var overlays = document.querySelectorAll('.accounting-modal-overlay');
                    overlays.forEach(function(overlay) {
                        var parentModal = overlay.closest('.accounting-modal');
                        if (!parentModal || parentModal.classList.contains('accounting-modal-hidden')) {
                            overlay.remove();
                        }
                    });
                    if (this.activeModal === formModal) this.activeModal = null;
                }
                if (typeof this.loadBankGuarantees === 'function') this.loadBankGuarantees();
                this._bankGuaranteeSaving = false;
            } else {
                var errMsg = (data && data.message) ? data.message : ('HTTP ' + (response ? response.status : '') + ' – Failed to save');
                if (response.status !== 400 || !data.message) {
                    console.error('Bank guarantee save failed:', errMsg, 'Response:', data);
                }
                this.showToast(errMsg, 'error');
                if (submitBtn) {
                    submitBtn.disabled = wasDisabled;
                    if (submitBtn.textContent) submitBtn.textContent = (id ? 'Update' : 'Create') + ' Bank Guarantee';
                }
                this._bankGuaranteeSaving = false;
            }
        } catch (err) {
            this.showToast(err && err.message ? err.message : 'Error saving bank guarantee', 'error');
            this._bankGuaranteeSaving = false;
            // Re-enable button on error
            if (submitBtn) {
                submitBtn.disabled = wasDisabled;
                if (submitBtn.textContent) submitBtn.textContent = (id ? 'Update' : 'Create') + ' Bank Guarantee';
            }
        }
    };
})();

// Patch deleteBankGuarantee - send id in body as fallback (some servers strip query params for DELETE)
(function() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var orig = ProfessionalAccounting.prototype.deleteBankGuarantee;
    if (!orig) return;
    ProfessionalAccounting.prototype.deleteBankGuarantee = async function(id) {
        var confirmed = await this.showConfirmDialog('Delete Bank Guarantee', 'Are you sure you want to delete this bank guarantee?', 'Delete', 'Cancel', 'danger');
        if (!confirmed) return;
        try {
            var response = await fetch(this.apiBase + '/bank-guarantees.php?id=' + id, {
                method: 'DELETE',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var data = {};
            try { data = await response.json(); } catch (_) { data = { success: false, message: 'Invalid response' }; }
            if (data.success) {
                this.showToast('Bank guarantee deleted successfully', 'success');
                if (typeof this.loadBankGuarantees === 'function') this.loadBankGuarantees();
            } else {
                this.showToast(data.message || 'Failed to delete bank guarantee', 'error');
            }
        } catch (err) {
            this.showToast(err && err.message ? err.message : 'Error deleting bank guarantee', 'error');
        }
    };
})();

// Patch deleteSelectedBankGuarantees - send id in body for each DELETE
(function() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    var orig = ProfessionalAccounting.prototype.deleteSelectedBankGuarantees;
    if (!orig) return;
    ProfessionalAccounting.prototype.deleteSelectedBankGuarantees = async function() {
        var modal = document.getElementById('bankGuaranteeModal');
        if (!modal) return;
        var checked = Array.from(modal.querySelectorAll('.bank-guarantee-checkbox:checked')).map(function(cb) { return parseInt(cb.value, 10); });
        if (checked.length === 0) {
            this.showToast('Please select bank guarantees to delete', 'warning');
            return;
        }
        var confirmed = await this.showConfirmDialog('Delete Bank Guarantees', 'Are you sure you want to delete ' + checked.length + ' bank guarantee(s)?', 'Delete', 'Cancel', 'danger');
        if (!confirmed) return;
        try {
            for (var i = 0; i < checked.length; i++) {
                var id = checked[i];
                await fetch(this.apiBase + '/bank-guarantees.php?id=' + id, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
            }
            this.showToast(checked.length + ' bank guarantee(s) deleted successfully', 'success');
            if (typeof this.loadBankGuarantees === 'function') this.loadBankGuarantees();
        } catch (err) {
            this.showToast(err && err.message ? err.message : 'Error deleting bank guarantees', 'error');
        }
    };
})();

(function runPatchesEarly() {
    if (typeof ProfessionalAccounting !== 'undefined') {
        patchApiCredentials();
        patchBankGuaranteesLoad();
        patchLoadBankGuarantees();
        patchBankGuaranteeModalOpen();
    }
})();

document.addEventListener('DOMContentLoaded', initProfessionalAccounting);
if (document.readyState !== 'loading') initProfessionalAccounting();

// English locale for date inputs (Flatpickr) - moved from accounting.php inline
(function initEnglishDatePickers() {
    document.documentElement.setAttribute('lang', 'en');
    var origToLocale = Date.prototype.toLocaleDateString;
    Date.prototype.toLocaleDateString = function(locales, options) {
        if (!locales || (typeof locales === 'string' && locales.indexOf('en') !== 0)) locales = 'en-US';
        return origToLocale.call(this, locales, options);
    };
    var origToLocaleStr = Date.prototype.toLocaleString;
    Date.prototype.toLocaleString = function(locales, options) {
        if (!locales || (typeof locales === 'string' && locales.indexOf('en') !== 0)) locales = 'en-US';
        return origToLocaleStr.call(this, locales, options);
    };
    var englishLocale = {
        weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] },
        months: { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] },
        firstDayOfWeek: 0, rangeSeparator: ' to ', weekAbbreviation: 'Wk', scrollTitle: 'Scroll to increment', toggleTitle: 'Click to toggle',
        amPM: ['AM','PM'], yearAriaLabel: 'Year', monthAriaLabel: 'Month', hourAriaLabel: 'Hour', minuteAriaLabel: 'Minute', time_24hr: false
    };
    window.initializeEnglishDatePickers = function(container) {
        container = container || document;
        if (typeof flatpickr === 'undefined') {
            setTimeout(function() { window.initializeEnglishDatePickers(container); }, 100);
            return;
        }
        var inputs = container.querySelectorAll('input[type="date"], input.date-input');
        inputs.forEach(function(input) {
            if (input._flatpickr) return;
            var origVal = input.value;
            if (input.type === 'date') input.type = 'text';
            try {
                if (origVal && /^\d{4}-\d{2}-\d{2}$/.test(origVal)) {
                    var p = origVal.split('-');
                    input.value = p[1] + '/' + p[2] + '/' + p[0];
                }
                flatpickr(input, { theme: 'dark', locale: englishLocale, dateFormat: 'm/d/Y', altInput: false, allowInput: true, enableTime: false, time_24hr: false, defaultDate: input.value || null, clickOpens: true });
            } catch (e) {
                input.type = 'date';
            }
        });
    };
    function runInit() {
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 500);
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 1500);
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 3000);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runInit);
    } else {
        runInit();
    }
    var obs = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'INPUT' && (node.type === 'date' || node.classList.contains('date-input')) && !node._flatpickr) {
                    if (node.type === 'date') { node.type = 'text'; node.classList.add('date-input'); }
                    setTimeout(function() { window.initializeEnglishDatePickers(node.parentElement || document); }, 50);
                }
                var dt = node.querySelectorAll && node.querySelectorAll('input[type="date"], input.date-input');
                if (dt && dt.length) {
                    dt.forEach(function(inp) { if (inp.type === 'date') { inp.type = 'text'; inp.classList.add('date-input'); } });
                    setTimeout(function() { window.initializeEnglishDatePickers(node); }, 50);
                }
            });
        });
    });
    obs.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['type', 'class'] });
})();

// Receipt voucher edit data binding - moved from accounting.php inline
(function() {
    window.__EDIT_RECEIPT_DATA__ = null;
    var _orig = window.fetch;
    window.fetch = function(url, opts) {
        var u = typeof url === 'string' ? url : (url && (url.url || url.href) ? (url.url || url.href) : '');
        var method = (opts && opts.method) ? String(opts.method).toUpperCase() : 'GET';
        return _orig.call(this, url, opts).then(function(res) {
            if (String(u).indexOf('payment-receipts.php') !== -1 && String(u).indexOf('id=') !== -1 && method === 'GET' && res.ok) {
                var clone = res.clone();
                clone.text().then(function(text) {
                    try {
                        if (text && text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                        var data = JSON.parse(text);
                        if (data.success && data.receipt) {
                            var r = data.receipt;
                            var cashVal = (r.cash_account_option_value != null) ? String(r.cash_account_option_value) : '';
                            var collVal = (r.collected_from_option_value != null) ? String(r.collected_from_option_value) : '';
                            if (!cashVal || !collVal) {
                                var bankId = r.bank_account_id != null && r.bank_account_id !== '' ? Number(r.bank_account_id) : null;
                                var accId = r.account_id != null && r.account_id !== '' ? Number(r.account_id) : null;
                                var custId = r.customer_id != null && r.customer_id !== '' ? Number(r.customer_id) : null;
                                var collId = r.collected_from_account_id != null && r.collected_from_account_id !== '' ? Number(r.collected_from_account_id) : null;
                                if (!cashVal) cashVal = bankId ? 'bank_' + bankId : (accId ? 'gl_' + accId : (r.bank_account_id === 0 || r.bank_account_id === '0' ? '0' : ''));
                                if (!collVal) collVal = custId ? 'customer_' + custId : (collId ? 'gl_' + collId : '');
                            }
                            window.__EDIT_RECEIPT_DATA__ = { cash_account_id: cashVal, collected_from_id: collVal };
                        }
                    } catch (e) {}
                });
            }
            return res;
        });
    };
    function optionExists(sel, val) {
        if (!sel || !sel.options) return false;
        if (val === '') return sel.options[0] && sel.options[0].value === '';
        for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value === val) return true; }
        return false;
    }
    function forceSetSelect(sel, val) {
        if (!sel) return false;
        if (val === '') { sel.selectedIndex = 0; } else {
            var idx = -1;
            for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value === val) { idx = i; break; } }
            if (idx >= 0) sel.selectedIndex = idx; else return false;
        }
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.$ && window.$.fn.select2 && window.$(sel).data('select2')) window.$(sel).trigger('change.select2');
        return true;
    }
    var forceInterval = null;
    function startForceBinding() {
        if (forceInterval) return;
        forceInterval = setInterval(function() {
            if (!window.__EDIT_RECEIPT_DATA__) return;
            var modal = document.getElementById('receiptVoucherModal');
            if (!modal || modal.classList.contains('accounting-modal-hidden')) return;
            var cashEl = modal.querySelector('#cash_account');
            var collEl = modal.querySelector('#collected_from');
            if (!cashEl || !collEl) return;
            var cv = window.__EDIT_RECEIPT_DATA__.cash_account_id;
            var clv = window.__EDIT_RECEIPT_DATA__.collected_from_id;
            if (!optionExists(cashEl, cv) || !optionExists(collEl, clv)) return;
            if (forceSetSelect(cashEl, cv) && forceSetSelect(collEl, clv)) {
                clearInterval(forceInterval);
                forceInterval = null;
                window.__EDIT_RECEIPT_DATA__ = null;
            }
        }, 50);
    }
    setInterval(function() {
        if (window.__EDIT_RECEIPT_DATA__) {
            var modal = document.getElementById('receiptVoucherModal');
            if (modal && !modal.classList.contains('accounting-modal-hidden')) startForceBinding();
        }
    }, 100);
})();

// openEntityTransactionModal: required by Entities modal / entity transactions (part4 not loaded)
(function() {
    if (typeof ProfessionalAccounting === 'undefined') return;
    ProfessionalAccounting.prototype.openEntityTransactionModal = async function(transactionId) {
        var entityType = 'agent';
        var entityId = null;
        var entityName = 'Entity';
        var path = (window.location.pathname || '').toLowerCase();
        if (path.indexOf('subagent') >= 0) entityType = 'subagent';
        else if (path.indexOf('worker') >= 0) entityType = 'worker';
        else if (path.indexOf('hr') >= 0) entityType = 'hr';
        if (transactionId) {
            try {
                var r = await fetch(this.apiBase + '/entity-transactions.php?id=' + transactionId, { credentials: 'include' });
                var d = await r.json();
                if (d.success && d.transaction) {
                    var t = d.transaction;
                    entityType = (t.entity_type || entityType).toLowerCase();
                    entityId = t.entity_id || entityId;
                    entityName = t.entity_name || entityName || (entityType.charAt(0).toUpperCase() + entityType.slice(1)) + ' ' + (entityId || '');
                }
            } catch (e) {}
        }
        if (typeof window.openAccountingModal === 'function') {
            await window.openAccountingModal(entityType, entityId || 0, entityName);
            if (transactionId) {
                var self = this;
                setTimeout(async function() {
                    try {
                        var res = await fetch(self.apiBase + '/entity-transactions.php?id=' + transactionId, { credentials: 'include' });
                        var data = await res.json();
                        if (data.success && data.transaction) {
                            var trans = data.transaction;
                            var form = document.getElementById('entityTransactionForm');
                            if (form) {
                                form.setAttribute('data-transaction-id', transactionId);
                                var dateF = document.getElementById('entityTransactionDate');
                                if (dateF) dateF.value = trans.transaction_date || '';
                                var debitF = document.getElementById('entityTransactionDebit');
                                if (debitF) debitF.value = (parseFloat(trans.debit_amount) || parseFloat(trans.debit) || 0).toFixed(2);
                                var creditF = document.getElementById('entityTransactionCredit');
                                if (creditF) creditF.value = (parseFloat(trans.credit_amount) || parseFloat(trans.credit) || 0).toFixed(2);
                                var descF = document.getElementById('entityTransactionDescription');
                                if (descF) descF.value = trans.description || '';
                                var refF = document.getElementById('entityTransactionReference');
                                if (refF) refF.value = trans.reference_number || '';
                                var currF = document.getElementById('entityTransactionCurrency');
                                if (currF) currF.value = trans.currency || self.getDefaultCurrencySync();
                                var typeF = document.getElementById('entityTransactionType');
                                if (typeF && (trans.entry_type || trans.transaction_type)) typeF.value = trans.entry_type || trans.transaction_type;
                                var statusF = document.getElementById('entityTransactionStatus');
                                if (statusF && trans.status) statusF.value = trans.status;
                                var accountSel = document.getElementById('entityTransactionAccount');
                                if (accountSel && trans.account_id) {
                                    if (typeof self.loadAccountsForSelect === 'function') await self.loadAccountsForSelect('entityTransactionAccount');
                                    setTimeout(function() { if (accountSel) accountSel.value = trans.account_id; }, 200);
                                }
                            }
                        }
                    } catch (err) { self.showToast('Failed to load transaction data', 'error'); }
                }, 300);
            }
        } else {
            this.showToast('Accounting modal system not available. Please refresh the page.', 'error');
        }
    };

    // getCurrencyOptionsHTML: used by openSettingsModal and others (part4 not loaded)
    ProfessionalAccounting.prototype.getCurrencyOptionsHTML = async function(selectedCurrency) {
        if (window.currencyUtils && typeof window.currencyUtils.getCurrencyOptionsHTML === 'function') {
            return await window.currencyUtils.getCurrencyOptionsHTML(selectedCurrency || this.getDefaultCurrencySync());
        }
        var c = selectedCurrency || this.getDefaultCurrencySync() || 'SAR';
        return '<option value="' + c + '">' + c + '</option>';
    };

    // openSettingsModal: open Accounting Settings modal (part3 not loaded)
    ProfessionalAccounting.prototype.openSettingsModal = async function() {
        var self = this;
        var today = new Date();
        var fiscalYearStart = this.formatDateForInput(new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0]);
        var fiscalYearEnd = this.formatDateForInput(new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0]);
        var currencyOptionsHTML = await this.getCurrencyOptionsHTML(this.getDefaultCurrencySync());
        var content = '<div class="accounting-module-modal-content"><div class="module-header"><div class="header-actions">' +
            '<button class="btn btn-sm btn-primary" data-action="save-settings" type="button"><i class="fas fa-save"></i> Save All Settings</button>' +
            '<button class="btn btn-sm btn-secondary" data-action="reset-settings" type="button"><i class="fas fa-undo"></i> Reset</button>' +
            '<button class="btn btn-sm btn-secondary" data-action="export-settings" type="button"><i class="fas fa-download"></i> Export</button></div></div>' +
            '<div class="module-content"><div class="settings-summary-cards"><div class="settings-summary-card"><div class="summary-card-icon"><i class="fas fa-percent"></i></div><div class="summary-card-content"><h4 id="modalSettingsTaxRate">15%</h4><p>Tax Rate</p></div></div>' +
            '<div class="settings-summary-card"><div class="summary-card-icon"><i class="fas fa-calculator"></i></div><div class="summary-card-content"><h4 id="modalSettingsTaxMethod">Inclusive</h4><p>Tax Method</p></div></div>' +
            '<div class="settings-summary-card"><div class="summary-card-icon"><i class="fas fa-dollar-sign"></i></div><div class="summary-card-content"><h4 id="modalSettingsCurrency">SAR</h4><p>Default Currency</p></div></div>' +
            '<div class="settings-summary-card"><div class="summary-card-icon"><i class="fas fa-calendar-alt"></i></div><div class="summary-card-content"><h4 id="modalSettingsFiscalYear">' + today.getFullYear() + '</h4><p>Fiscal Year</p></div></div></div></div>' +
            '<div class="settings-search-bar"><div class="search-input-wrapper"><i class="fas fa-search"></i><input type="text" id="modalSettingsSearch" class="settings-search-input" placeholder="Search settings..."></div></div>' +
            '<div class="settings-sections-container" id="modalSettingsSectionsContainer"><div class="settings-grid">' +
            '<div class="setting-item"><label for="defaultTaxRate">Default Tax Rate (%)</label><input type="number" id="defaultTaxRate" step="0.01" min="0" max="100" value="15" data-setting-key="default_tax_rate" data-setting-type="number"></div>' +
            '<div class="setting-item"><label for="taxMethod">Tax Method</label><select id="taxMethod" data-setting-key="tax_calculation_method" data-setting-type="text"><option value="inclusive">Tax Inclusive</option><option value="exclusive">Tax Exclusive</option></select></div>' +
            '<div class="setting-item"><label for="fiscalYearStart">Fiscal Year Start</label><input type="text" id="fiscalYearStart" class="date-input" value="' + fiscalYearStart + '" data-setting-key="fiscal_year_start" data-setting-type="date" placeholder="MM/DD/YYYY"></div>' +
            '<div class="setting-item"><label for="fiscalYearEnd">Fiscal Year End</label><input type="text" id="fiscalYearEnd" class="date-input" value="' + fiscalYearEnd + '" data-setting-key="fiscal_year_end" data-setting-type="date" placeholder="MM/DD/YYYY"></div>' +
            '<div class="setting-item"><label for="defaultCurrency">Default Currency</label><select id="defaultCurrency" data-setting-key="default_currency" data-setting-type="text">' + currencyOptionsHTML + '</select></div>' +
            '</div></div></div></div>';
        this.showModal('Accounting Settings', content, 'large', 'accountingSettingsModal');
        setTimeout(async function() {
            await self.loadSettings();
            self.setupSettingsHandlers();
            self.setupSettingsFilters();
            self.updateSettingsSummary();
            var inputs = document.querySelectorAll('#accountingSettingsModal input, #accountingSettingsModal select');
            inputs.forEach(function(input) {
                input.addEventListener('change', function() { self.updateSettingsSummary(); input.classList.add('setting-changed'); });
            });
        }, 100);
    };

    // Aliases for nav/quick actions (part3/part4 not loaded)
    ProfessionalAccounting.prototype.openInvoiceModal = function(invoiceId) {
        this.openReceivablesModal();
    };
    ProfessionalAccounting.prototype.openBillModal = function(billId) {
        this.openPayablesModal();
    };

    // Stubs to avoid "is not a function" when user clicks View/Print/Duplicate/Void (part5/part6 not loaded)
    ProfessionalAccounting.prototype.viewEntityTransaction = async function(transactionId) {
        this.openEntityTransactionModal(transactionId);
    };
    ProfessionalAccounting.prototype.viewJournalEntry = async function(entryId) {
        if (typeof this.openJournalEntryModal === 'function') this.openJournalEntryModal(entryId);
        else this.showToast('View journal entry: ' + entryId, 'info');
    };
    ProfessionalAccounting.prototype.printTransaction = async function(transactionId) {
        this.showToast('Print transaction – not available in this view', 'info');
    };
    ProfessionalAccounting.prototype.printJournalEntry = async function(entryId) {
        this.showToast('Print journal entry – use Reports or export', 'info');
    };
    ProfessionalAccounting.prototype.printTransactions = async function() {
        this.showToast('Print transactions – use Export for CSV', 'info');
    };
    ProfessionalAccounting.prototype.duplicateEntityTransaction = async function(transactionId) {
        this.showToast('Duplicate transaction – use Edit then Save as new', 'info');
    };
    ProfessionalAccounting.prototype.voidEntityTransaction = async function(transactionId) {
        this.showToast('Void transaction – use Entry Approval or reverse entry', 'info');
    };
})();
