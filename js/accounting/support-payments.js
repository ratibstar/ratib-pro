/**
 * EN: Implements frontend interaction behavior in `js/accounting/support-payments.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/support-payments.js`.
 */
/**
 * Support Payments Module - JavaScript
 * Implements ledger-driven design principles from ACCOUNTING_UI_UX_REDESIGN.md
 * Follows same pattern as Disbursement Vouchers
 */

(function() {
    'use strict';

    const API_BASE = getApiBase();
    let paymentsData = [];
    let currentEditingId = null;
    let debitLines = [];
    let creditLines = [];
    let accounts = [];
    let costCenters = [];

    // Initialize module
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        loadPayments();
        loadAccounts();
        loadCostCenters();
        initializeForm();
    });

    function initializeEventListeners() {
        // New payment button
        document.getElementById('btn-new-payment')?.addEventListener('click', showNewPaymentForm);
        document.getElementById('btn-add-first-payment')?.addEventListener('click', showNewPaymentForm);
        
        // Modal controls
        document.getElementById('btn-close-modal')?.addEventListener('click', closeModal);
        document.getElementById('btn-cancel')?.addEventListener('click', closeModal);
        
        // Form submission
        document.getElementById('payment-form')?.addEventListener('submit', handleFormSubmit);
        document.getElementById('btn-save-draft')?.addEventListener('click', handleSaveDraft);
        
        // Line management
        document.getElementById('btn-add-debit-line')?.addEventListener('click', addDebitLine);
        document.getElementById('btn-add-credit-line')?.addEventListener('click', addCreditLine);
        
        // Filters
        document.getElementById('btn-apply-filters')?.addEventListener('click', applyFilters);
        
        // Refresh button
        document.querySelector('[data-action="refresh"]')?.addEventListener('click', loadPayments);
        
        // Delegated handlers for payments table (no inline onclick)
        document.getElementById('payments-table')?.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="edit-payment"], [data-action="delete-payment"]');
            if (btn) {
                var id = parseInt(btn.getAttribute('data-id'), 10);
                if (btn.getAttribute('data-action') === 'edit-payment') editPayment(id);
                else if (btn.getAttribute('data-action') === 'delete-payment') deletePayment(id);
                return;
            }
            var link = e.target.closest('.link-view-je');
            if (link) {
                e.preventDefault();
                viewJournalEntry(parseInt(link.getAttribute('data-je-id'), 10));
            }
        });
        
        // Delegated handlers for ledger line add/remove
        document.getElementById('payment-modal')?.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="add-line-after"], [data-action="remove-line"]');
            if (!btn) return;
            var side = btn.getAttribute('data-side');
            var lineId = parseInt(btn.getAttribute('data-line-id'), 10);
            if (btn.getAttribute('data-action') === 'add-line-after') addLineAfter(side, lineId);
            else removeLine(side, lineId);
        });
    }

    function initializeForm() {
        // Add initial debit and credit lines
        addDebitLine();
        addCreditLine();
    }

    function loadPayments() {
        // TODO: Implement API call to load payments
        renderPaymentsTable([]);
    }

    function loadAccounts() {
        // TODO: Implement API call to load accounts
        // Accounts should be grouped by type: ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
    }

    function loadCostCenters() {
        // TODO: Implement API call to load cost centers
    }

    function renderPaymentsTable(payments) {
        const tbody = document.getElementById('payments-tbody');
        if (!tbody) return;

        if (payments.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="message">No support payments found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-payment">
                                <i class="fas fa-plus"></i> Add First Payment
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('btn-add-first-payment')?.addEventListener('click', showNewPaymentForm);
            return;
        }

        tbody.innerHTML = payments.map(payment => `
            <tr>
                <td>${escapeHtml(payment.payment_number || '')}</td>
                <td>${formatDate(payment.payment_date)}</td>
                <td>${escapeHtml(payment.recipient || '')}</td>
                <td class="amount">${formatCurrency(payment.amount || 0)}</td>
                <td>${renderStatusBadge(payment.status)}</td>
                <td>${payment.journal_entry_id ? `<a href="#" class="link-view-je" data-je-id="${payment.journal_entry_id}">JE-${payment.journal_entry_id}</a>` : '-'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" data-action="edit-payment" data-id="${payment.id}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger" data-action="delete-payment" data-id="${payment.id}" ${payment.is_locked ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderStatusBadge(status) {
        const statusMap = {
            'draft': { class: 'status-draft', label: 'Draft' },
            'posted': { class: 'status-posted', label: 'Posted' },
            'locked': { class: 'status-locked', label: 'Locked' }
        };
        const statusInfo = statusMap[status] || { class: 'status-draft', label: status };
        return `<span class="status-badge ${statusInfo.class}">${statusInfo.label}</span>`;
    }

    function addDebitLine() {
        const lineId = Date.now();
        debitLines.push({
            id: lineId,
            account_id: null,
            cost_center_id: null,
            description: '',
            vat_report: false,
            amount: 0
        });

        const container = document.getElementById('debit-lines-container');
        const lineHtml = createLedgerLineRow(lineId, 'debit');
        container.insertAdjacentHTML('beforeend', lineHtml);
        
        attachLineEventListeners(lineId, 'debit');
        updateTotals();
    }

    function addCreditLine() {
        const lineId = Date.now();
        creditLines.push({
            id: lineId,
            account_id: null,
            cost_center_id: null,
            description: '',
            vat_report: false,
            amount: 0
        });

        const container = document.getElementById('credit-lines-container');
        const lineHtml = createLedgerLineRow(lineId, 'credit');
        container.insertAdjacentHTML('beforeend', lineHtml);
        
        attachLineEventListeners(lineId, 'credit');
        updateTotals();
    }

    function createLedgerLineRow(lineId, side) {
        return `
            <div class="ledger-line-row" data-line-id="${lineId}" data-side="${side}">
                <div class="line-field account-field">
                    <label>Account <span class="required">*</span></label>
                    <select class="account-select form-control" data-validate-type="${side}" required>
                        <option value="">Select Account</option>
                        ${renderAccountOptions(side)}
                    </select>
                    <span class="account-balance-tooltip"></span>
                </div>
                <div class="line-field cost-center-field">
                    <label>Cost Center</label>
                    <select class="cost-center-select form-control">
                        <option value="">All</option>
                        ${renderCostCenterOptions()}
                    </select>
                </div>
                <div class="line-field description-field">
                    <label>Description</label>
                    <input type="text" class="line-description form-control" placeholder="Line description">
                </div>
                <div class="line-field vat-field">
                    <label>VAT Report</label>
                    <input type="checkbox" class="vat-checkbox">
                </div>
                <div class="line-field amount-field">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" step="0.01" class="line-amount form-control" data-side="${side}" min="0" required>
                </div>
                <div class="line-actions">
                    <button type="button" class="btn-add-line" title="Add Line" data-action="add-line-after" data-side="${side}" data-line-id="${lineId}">+</button>
                    <button type="button" class="btn-remove-line" title="Remove Line" data-action="remove-line" data-side="${side}" data-line-id="${lineId}">-</button>
                </div>
            </div>
        `;
    }

    function renderAccountOptions(side) {
        // Filter accounts by type based on side
        // Debit: ASSET or EXPENSE
        // Credit: LIABILITY, EQUITY, or REVENUE
        const allowedTypes = side === 'debit' ? ['ASSET', 'EXPENSE'] : ['LIABILITY', 'EQUITY', 'REVENUE'];
        
        // TODO: Group accounts by type
        return accounts
            .filter(acc => allowedTypes.includes(acc.account_type))
            .map(acc => `<option value="${acc.id}">[${acc.account_code}] ${acc.account_name}</option>`)
            .join('');
    }

    function renderCostCenterOptions() {
        return costCenters
            .map(cc => `<option value="${cc.id}">${cc.name}</option>`)
            .join('');
    }

    function attachLineEventListeners(lineId, side) {
        const line = document.querySelector(`[data-line-id="${lineId}"]`);
        if (!line) return;

        const amountInput = line.querySelector('.line-amount');
        const accountSelect = line.querySelector('.account-select');

        amountInput?.addEventListener('input', debounce(() => {
            updateLineAmount(lineId, side, parseFloat(amountInput.value) || 0);
            updateTotals();
        }, 300));

        accountSelect?.addEventListener('change', () => {
            validateAccountType(lineId, side, accountSelect.value);
            updateTotals();
        });
    }

    function updateLineAmount(lineId, side, amount) {
        const lines = side === 'debit' ? debitLines : creditLines;
        const line = lines.find(l => l.id === lineId);
        if (line) {
            line.amount = amount;
        }
    }

    function validateAccountType(lineId, side, accountId) {
        const account = accounts.find(a => a.id == accountId);
        if (!account) return;

        const allowedTypes = side === 'debit' ? ['ASSET', 'EXPENSE'] : ['LIABILITY', 'EQUITY', 'REVENUE'];
        const isValid = allowedTypes.includes(account.account_type);

        const line = document.querySelector(`[data-line-id="${lineId}"]`);
        const accountSelect = line?.querySelector('.account-select');
        
        if (accountSelect) {
            if (!isValid) {
                accountSelect.classList.add('error');
                accountSelect.setAttribute('title', `Invalid account type. ${side === 'debit' ? 'Debit' : 'Credit'} side requires ${allowedTypes.join(' or ')} accounts.`);
            } else {
                accountSelect.classList.remove('error');
                accountSelect.removeAttribute('title');
            }
        }
    }

    function updateTotals() {
        const totalDebit = debitLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        const totalCredit = creditLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        const difference = Math.abs(totalDebit - totalCredit);
        const isBalanced = difference < 0.01;

        // Update section totals
        document.getElementById('total-debit').textContent = formatCurrency(totalDebit);
        document.getElementById('total-credit').textContent = formatCurrency(totalCredit);
        document.getElementById('footer-total-debit').textContent = formatCurrency(totalDebit);
        document.getElementById('footer-total-credit').textContent = formatCurrency(totalCredit);

        // Update balance footer
        const balanceFooter = document.getElementById('balance-footer');
        const balanceIndicator = document.getElementById('balance-indicator');
        const postButton = document.getElementById('btn-post');

        if (isBalanced) {
            balanceFooter.classList.remove('unbalanced');
            balanceFooter.classList.add('balanced');
            balanceIndicator.innerHTML = '<span class="icon">✓</span> <span class="balance-text">BALANCED</span>';
            balanceIndicator.classList.remove('unbalanced');
            balanceIndicator.classList.add('balanced');
            postButton.disabled = false;
        } else {
            balanceFooter.classList.remove('balanced');
            balanceFooter.classList.add('unbalanced');
            balanceIndicator.innerHTML = `<span class="icon">⚠</span> <span class="balance-text">UNBALANCED: ${formatCurrency(difference)}</span>`;
            balanceIndicator.classList.remove('balanced');
            balanceIndicator.classList.add('unbalanced');
            postButton.disabled = true;
        }
    }

    function showNewPaymentForm() {
        currentEditingId = null;
        debitLines = [];
        creditLines = [];
        document.getElementById('modal-title').textContent = 'New Support Payment';
        document.getElementById('payment-form').reset();
        document.getElementById('payment-id').value = '';
        document.getElementById('debit-lines-container').innerHTML = '';
        document.getElementById('credit-lines-container').innerHTML = '';
        initializeForm();
        document.getElementById('payment-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('payment-modal').classList.add('accounting-modal-visible');
    }

    function closeModal() {
        document.getElementById('payment-modal').classList.add('accounting-modal-hidden');
        document.getElementById('payment-modal').classList.remove('accounting-modal-visible');
        currentEditingId = null;
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        
        if (!validateBalance()) {
            alert('Entry must be balanced before posting.');
            return;
        }

        const formData = collectFormData();
        
        // TODO: Implement API call to post payment
        console.log('Posting payment:', formData);
        
        closeModal();
        loadPayments();
    }

    function handleSaveDraft() {
        const formData = collectFormData();
        formData.status = 'draft';
        
        // TODO: Implement API call to save draft
        console.log('Saving draft:', formData);
        
        closeModal();
        loadPayments();
    }

    function collectFormData() {
        const form = document.getElementById('payment-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        data.debit_lines = debitLines;
        data.credit_lines = creditLines;
        data.total_debit = debitLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        data.total_credit = creditLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        
        return data;
    }

    function validateBalance() {
        const totalDebit = debitLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        const totalCredit = creditLines.reduce((sum, line) => sum + (line.amount || 0), 0);
        return Math.abs(totalDebit - totalCredit) < 0.01;
    }

    function applyFilters() {
        // TODO: Implement filter logic
        loadPayments();
    }

    // Global functions for inline event handlers
    window.addLineAfter = function(side, afterLineId) {
        if (side === 'debit') {
            addDebitLine();
        } else {
            addCreditLine();
        }
    };

    window.removeLine = function(side, lineId) {
        if (side === 'debit') {
            debitLines = debitLines.filter(l => l.id !== lineId);
        } else {
            creditLines = creditLines.filter(l => l.id !== lineId);
        }
        document.querySelector(`[data-line-id="${lineId}"]`)?.remove();
        updateTotals();
    };

    window.editPayment = function(id) {
        currentEditingId = id;
        // TODO: Load payment data and populate form
        document.getElementById('modal-title').textContent = 'Edit Support Payment';
        document.getElementById('payment-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('payment-modal').classList.add('accounting-modal-visible');
    };

    window.deletePayment = function(id) {
        if (confirm('Are you sure you want to delete this payment?')) {
            // TODO: Implement API call to delete payment
            loadPayments();
        }
    };

    window.viewJournalEntry = function(jeId) {
        // TODO: Navigate to journal entry view
        console.log('View journal entry:', jeId);
    };

    // Utility functions
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-SA', { 
            style: 'currency', 
            currency: 'SAR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount || 0);
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            // Format as MM/DD/YYYY (English format)
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${month}/${day}/${year}`;
        } catch (e) {
            return dateString;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function getApiBase() {
        return window.API_BASE || '/api/accounting';
    }

})();
