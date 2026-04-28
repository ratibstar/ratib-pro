/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part5.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part5.js`.
 */
/** Professional Accounting - Part 5 (lines 20199-25198) */
ProfessionalAccounting.prototype.getChartOfAccountsModalContent = function() {
        return `
            <div class="accounting-module-modal-content">
                <div class="module-content">
                    <div class="summary-cards-mini-header">
                        <div class="summary-cards-mini">
                            <div class="summary-mini-card">
                                <h4>Total Accounts</h4>
                                <p id="modalCoaTotalAccounts">0</p>
                    </div>
                            <div class="summary-mini-card">
                                <h4>Active</h4>
                                <p id="modalCoaActive">0</p>
                </div>
                            <div class="summary-mini-card">
                                <h4>Inactive</h4>
                                <p id="modalCoaInactive">0</p>
                            </div>
                            <div class="summary-mini-card">
                                <h4>Total Balance</h4>
                                <p id="modalCoaTotalBalance">SAR 0.00</p>
                            </div>
                            <div class="summary-entity-card">
                                <h4>Assets</h4>
                                <p id="modalCoaAssetsCount">0</p>
                                <span id="modalCoaAssetsBalance" class="entity-amount">SAR 0.00</span>
                            </div>
                            <div class="summary-entity-card">
                                <h4>Liabilities</h4>
                                <p id="modalCoaLiabilitiesCount">0</p>
                                <span id="modalCoaLiabilitiesBalance" class="entity-amount">SAR 0.00</span>
                            </div>
                            <div class="summary-entity-card">
                                <h4>Equity</h4>
                                <p id="modalCoaEquityCount">0</p>
                                <span id="modalCoaEquityBalance" class="entity-amount">SAR 0.00</span>
                            </div>
                            <div class="summary-entity-card">
                                <h4>Income</h4>
                                <p id="modalCoaIncomeCount">0</p>
                                <span id="modalCoaIncomeBalance" class="entity-amount">SAR 0.00</span>
                            </div>
                            <div class="summary-entity-card">
                                <h4>Expenses</h4>
                                <p id="modalCoaExpensesCount">0</p>
                                <span id="modalCoaExpensesBalance" class="entity-amount">SAR 0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="filters-and-pagination-container">
                        <div class="filters-bar filters-bar-compact">
                            <div class="filter-group filter-group-compact">
                            <label>Account Type:</label>
                                <select id="coaAccountTypeFilter" class="filter-select filter-select-compact">
                                <option value="">All Types</option>
                                <option value="Asset">Asset</option>
                                <option value="Liability">Liability</option>
                                <option value="Equity">Equity</option>
                                <option value="Income">Income</option>
                                <option value="Expense">Expense</option>
                            </select>
                        </div>
                            <div class="filter-group filter-group-compact">
                            <label>Search:</label>
                                <input type="text" id="coaSearch" class="filter-input filter-input-compact" placeholder="Search by code or name...">
                        </div>
                            <div class="filter-group filter-group-compact">
                                <label>Show:</label>
                                <select id="coaPerPage" class="filter-select filter-select-compact">
                                    <option value="5" selected>5</option>
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                    </div>
                            <button class="btn btn-primary btn-sm" data-action="new-account" data-permission="add_account,view_chart_accounts">
                                <i class="fas fa-plus"></i> New Account
                            </button>
                            <button class="btn btn-secondary btn-sm" data-action="export-accounts" data-permission="view_chart_accounts">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="table-pagination-top" id="coaPaginationTop">
                            <div class="pagination-info" id="coaPaginationInfoTop"></div>
                            <div class="pagination-controls">
                                <button class="btn-pagination btn-pagination-nav" id="coaFirstTop" data-action="coa-page" data-page="1" title="First Page">
                                    <i class="fas fa-angle-double-left"></i>
                                </button>
                                <button class="btn-pagination btn-pagination-nav" id="coaPrevTop" data-action="coa-prev" title="Previous Page">
                                    <i class="fas fa-angle-left"></i>
                                </button>
                                <span id="coaPageNumbersTop" class="pagination-numbers"></span>
                                <button class="btn-pagination btn-pagination-nav" id="coaNextTop" data-action="coa-next" title="Next Page">
                                    <i class="fas fa-angle-right"></i>
                                </button>
                                <button class="btn-pagination btn-pagination-nav" id="coaLastTop" data-action="coa-page" data-page="1" title="Last Page">
                                    <i class="fas fa-angle-double-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Bulk Actions Bar -->
                    <div class="bulk-actions-bar" id="bulkActionsCoa">
                        <span class="bulk-selected-count" id="bulkSelectedCountCoa">0 selected</span>
                        <div class="bulk-action-buttons">
                            <button class="btn btn-sm btn-danger" data-action="bulk-delete-coa" data-permission="delete_account">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="bulk-export-coa">
                                <i class="fas fa-download"></i> Export Selected
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="bulk-activate-coa" data-permission="edit_account">
                                <i class="fas fa-check"></i> Activate
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="bulk-deactivate-coa" data-permission="edit_account">
                                <i class="fas fa-times"></i> Deactivate
                            </button>
                        </div>
                    </div>
                    <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="modalCoaTableWrapper">
                        <table class="data-table modal-table-fixed" id="chartOfAccountsTable">
                            <thead>
                                <tr>
                                    <th class="invoice-column" data-sort="account_code">
                                        Code <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="entity-name-column" data-sort="account_name">
                                        Name <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="entity-type-column" data-sort="account_type">
                                        Type <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="date-column" data-sort="normal_balance">
                                        Normal <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="amount-column debit-cell" data-sort="opening_balance">
                                        Opening <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="amount-column debit-cell" data-sort="current_balance">
                                        Balance <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="status-column" data-sort="is_active">
                                        Status <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="checkbox-column">
                                        <input type="checkbox" id="coaSelectAll" data-action="coa-select-all" title="Select All">
                                    </th>
                                    <th class="actions-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="chartOfAccountsBody">
                                <tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    // Customer Management
ProfessionalAccounting.prototype.openCustomersModal = async function() {
        const content = await this.getCustomersModalContent();
        this.showModal('Customer Management', content, 'large');
        await this.loadCustomers();
    }

ProfessionalAccounting.prototype.getCustomersModalContent = async function() {
        return `
            <div class="accounting-modal-content-full">
                <div class="module-header">
                    <h3><i class="fas fa-users"></i> Customers</h3>
                    <button class="btn btn-primary" data-action="new-customer">
                        <i class="fas fa-plus"></i> New Customer
                    </button>
                </div>
                <div class="data-table-container">
                    <table class="data-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Credit Limit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customersBody">
                            <tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

ProfessionalAccounting.prototype.loadCustomers = async function() {
        const tbody = document.getElementById('customersBody');
        if (!tbody) return;
        
        try {
            const response = await fetch(`${this.apiBase}/customers.php`);
            const data = await response.json().catch(function() { return {}; });
            if (data.success && data.customers) {
                tbody.innerHTML = data.customers.map(customer => `
                    <tr>
                        <td>${customer.id}</td>
                        <td>${this.escapeHtml(customer.customer_name)}</td>
                        <td>${this.escapeHtml(customer.contact_person || '-')}</td>
                        <td>${this.escapeHtml(customer.email || '-')}</td>
                        <td>${this.escapeHtml(customer.phone || '-')}</td>
                        <td>${this.formatCurrency(customer.credit_limit || 0)}</td>
                        <td><span class="status-badge ${customer.is_active ? 'active' : 'inactive'}">${customer.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-action="edit-customer" data-id="${customer.id}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-customer" data-id="${customer.id}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No customers found</td></tr>';
            }
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading customers</td></tr>';
        }
    }

    // Vendor Management
ProfessionalAccounting.prototype.openVendorsModal = async function() {
        const content = await this.getVendorsModalContent();
        this.showModal('Vendor Management', content, 'large');
        await this.loadVendors();
    }

ProfessionalAccounting.prototype.getVendorsModalContent = async function() {
        return `
            <div class="accounting-modal-content-full">
                <div class="module-header">
                    <h3><i class="fas fa-truck"></i> Vendors</h3>
                    <button class="btn btn-primary" data-action="new-vendor">
                        <i class="fas fa-plus"></i> New Vendor
                    </button>
                </div>
                <div class="data-table-container">
                    <table class="data-table" id="vendorsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Payment Terms</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vendorsBody">
                            <tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

ProfessionalAccounting.prototype.loadVendors = async function() {
        const tbody = document.getElementById('vendorsBody');
        if (!tbody) return;
        
        try {
            const response = await fetch(`${this.apiBase}/vendors.php`);
            const data = await response.json().catch(function() { return {}; });
            if (data.success && data.vendors) {
                tbody.innerHTML = data.vendors.map(vendor => `
                    <tr>
                        <td>${vendor.id}</td>
                        <td>${this.escapeHtml(vendor.vendor_name)}</td>
                        <td>${this.escapeHtml(vendor.contact_person || '-')}</td>
                        <td>${this.escapeHtml(vendor.email || '-')}</td>
                        <td>${this.escapeHtml(vendor.phone || '-')}</td>
                        <td>${vendor.payment_terms || 30} days</td>
                        <td><span class="status-badge ${vendor.is_active ? 'active' : 'inactive'}">${vendor.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-action="edit-vendor" data-id="${vendor.id}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-vendor" data-id="${vendor.id}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No vendors found</td></tr>';
            }
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading vendors</td></tr>';
        }
    }

    // Quick Entry Modal
ProfessionalAccounting.prototype.openQuickEntryModal = function() {
        const content = this.getQuickEntryModalContent();
        this.showModal('Quick Entry', content);
        
        setTimeout(async () => {
            await this.loadAccountsForSelect('quickEntryAccountSelect');
            // Populate currency dropdown
            const currencySelect = document.getElementById('quickEntryCurrency');
            if (currencySelect && window.currencyUtils) {
                try {
                    const defaultCurrency = this.getDefaultCurrencySync();
                    await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                } catch (error) {
                    console.error('Error populating quick entry currency:', error);
                }
            }
        }, 100);
    }

ProfessionalAccounting.prototype.getQuickEntryModalContent = function() {
        return `
            <form id="quickEntryForm" class="accounting-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="text" name="date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                    </div>
                    <div class="form-group">
                        <label>Account *</label>
                        <select name="account_id" id="quickEntryAccountSelect" required>
                            <option value="">Select Account...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required rows="3" placeholder="Transaction description"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="debit-label">Debit Amount</label>
                        <input type="number" name="debit" class="debit-input" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="credit-label">Credit Amount</label>
                        <input type="number" name="credit" class="credit-input" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" id="quickEntryCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" placeholder="Optional reference">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </div>
            </form>
        `;
    }

    // Receive Payment Modal
ProfessionalAccounting.prototype.getReceivePaymentModalContent = function(invoiceId = null) {
        return `
            <form id="receivePaymentForm" class="accounting-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Date *</label>
                        <input type="text" name="payment_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Customers</label>
                        <div id="receivePaymentCustomersContainer">
                            <div class="customer-input-row" data-customer-index="0">
                                <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                                <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Invoice</label>
                        <select name="invoice_id" id="receivePaymentInvoiceSelect">
                            <option value="">Select Invoice...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" id="receivePaymentCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Reference Number</label>
                        <input type="text" name="reference_number" placeholder="Reference number">
                    </div>
                    <div class="form-group">
                        <label>Cheque Number</label>
                        <input type="text" name="cheque_number" placeholder="If paid by cheque">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        `;
    }

    // Make Payment Modal
ProfessionalAccounting.prototype.getMakePaymentModalContent = function(billId = null) {
        return `
            <form id="makePaymentForm" class="accounting-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Date *</label>
                        <input type="text" name="payment_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                    </div>
                    <div class="form-group">
                        <label>Vendor</label>
                        <select name="vendor_id" id="makePaymentVendorSelect">
                            <option value="">Select Vendor...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bill</label>
                        <select name="bill_id" id="makePaymentBillSelect">
                            <option value="">Select Bill...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" id="quickEntryCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Reference Number</label>
                        <input type="text" name="reference_number" placeholder="Reference number">
                    </div>
                    <div class="form-group">
                        <label>Cheque Number</label>
                        <input type="text" name="cheque_number" placeholder="If paid by cheque">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        `;
    }

ProfessionalAccounting.prototype.getFinancialPeriodsModalContent = function() {
        return `
            <div class="accounting-modal-content-full">
                <div class="module-header">
                    <h3><i class="fas fa-calendar-alt"></i> Financial Periods</h3>
                    <button class="btn btn-primary" data-action="new-period">
                        <i class="fas fa-plus"></i> New Period
                    </button>
                </div>
                <div class="data-table-container">
                    <table class="data-table" id="periodsTable">
                        <thead>
                            <tr>
                                <th>Period Name</th>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="periodsBody">
                            <tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

ProfessionalAccounting.prototype.getTaxSettingsModalContent = function() {
        return `
            <div class="accounting-modal-content-full">
                <div class="module-header">
                    <h3><i class="fas fa-percentage"></i> Tax Settings</h3>
                    <button class="btn btn-primary" data-action="new-tax-setting">
                        <i class="fas fa-plus"></i> New Tax Setting
                    </button>
                </div>
                <div class="data-table-container">
                    <table class="data-table" id="taxSettingsTable">
                        <thead>
                            <tr>
                                <th>Setting Key</th>
                                <th>Value</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="taxSettingsBody">
                            <tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // Save methods
ProfessionalAccounting.prototype.saveJournalEntry = async function(entryId = null) {
        const form = document.getElementById('journalEntryForm');
        if (!form) {
            this.showToast('Form not found', 'error');
            return false;
        }
        
        // Collect all debit and credit lines
        const debitLines = [];
        const creditLines = [];
        
        // Collect debit lines
        const debitRows = form.querySelectorAll('#journalDebitLinesBody .ledger-line-row');
        debitRows.forEach((row, index) => {
            const accountSelect = row.querySelector('.account-select');
            const costCenterSelect = row.querySelector('.cost-center-select');
            const descriptionInput = row.querySelector('.line-description');
            const vatCheckbox = row.querySelector('.vat-checkbox');
            const amountInput = row.querySelector('.debit-amount');
            
            const accountId = accountSelect ? parseInt(accountSelect.value) : 0;
            const amount = amountInput ? parseFloat(amountInput.value || 0) : 0;
            
            // Only include lines with account and amount > 0 (round amount to 2 decimals)
            if (accountId > 0 && amount > 0) {
                debitLines.push({
                    account_id: accountId,
                    cost_center_id: costCenterSelect ? (parseInt(costCenterSelect.value) || null) : null,
                    description: descriptionInput ? descriptionInput.value.trim() : '',
                    vat_report: vatCheckbox ? vatCheckbox.checked : false,
                    amount: Math.round(amount * 100) / 100
                });
            }
        });
        
        // Collect credit lines
        const creditRows = form.querySelectorAll('#journalCreditLinesBody .ledger-line-row');
        creditRows.forEach((row, index) => {
            const accountSelect = row.querySelector('.account-select');
            const costCenterSelect = row.querySelector('.cost-center-select');
            const descriptionInput = row.querySelector('.line-description');
            const vatCheckbox = row.querySelector('.vat-checkbox');
            const amountInput = row.querySelector('.credit-amount');
            
            const accountId = accountSelect ? parseInt(accountSelect.value) : 0;
            const amount = amountInput ? parseFloat(amountInput.value || 0) : 0;
            
            // Only include lines with account and amount > 0 (round amount to 2 decimals)
            if (accountId > 0 && amount > 0) {
                creditLines.push({
                    account_id: accountId,
                    cost_center_id: costCenterSelect ? (parseInt(costCenterSelect.value) || null) : null,
                    description: descriptionInput ? descriptionInput.value.trim() : '',
                    vat_report: vatCheckbox ? vatCheckbox.checked : false,
                    amount: Math.round(amount * 100) / 100
                });
            }
        });
        
        // Validate required fields
        const entryDate = form.querySelector('#journalEntryDate')?.value;
        const branchId = form.querySelector('#journalBranchSelect')?.value;
        const description = form.querySelector('textarea[name="description"]')?.value?.trim();
        
        if (!entryDate) {
            this.showToast('Please select a journal date', 'error');
            return false;
        }
        if (!branchId) {
            this.showToast('Please select a branch', 'error');
            return false;
        }
        if (!description) {
            this.showToast('Please enter a description', 'error');
            return false;
        }
        if (debitLines.length === 0 && creditLines.length === 0) {
            this.showToast('Please add at least one debit or credit line with an account and amount', 'error');
            return false;
        }
        
        // Calculate totals (round to 2 decimals to avoid float noise)
        const rawDebit = debitLines.reduce((sum, line) => sum + line.amount, 0);
        const rawCredit = creditLines.reduce((sum, line) => sum + line.amount, 0);
        const totalDebit = Math.round(rawDebit * 100) / 100;
        const totalCredit = Math.round(rawCredit * 100) / 100;
        
        // Validate balance (tolerance 0.01 for rounding)
        const difference = Math.abs(totalDebit - totalCredit);
        if (difference > 0.01) {
            this.showToast(`Entry is not balanced. Debit: ${totalDebit.toFixed(2)}, Credit: ${totalCredit.toFixed(2)}, Difference: ${difference.toFixed(2)}`, 'error');
            return false;
        }
        
        if (totalDebit === 0 && totalCredit === 0) {
            this.showToast('Please enter amounts for at least one line', 'error');
            return false;
        }
        
        // Build data object
        // For now, send the first debit/credit line to maintain API compatibility
        // TODO: Update API to handle multiple lines (debit_lines and credit_lines arrays)
        const firstDebitLine = debitLines.length > 0 ? debitLines[0] : null;
        const firstCreditLine = creditLines.length > 0 ? creditLines[0] : null;
        
        // Use first debit line if available, otherwise use first credit line
        const primaryLine = firstDebitLine || firstCreditLine;
        if (!primaryLine) {
            this.showToast('Please add at least one line with an account and amount', 'error');
            return false;
        }
        
        const data = {
            entry_date: entryDate,
            branch_id: parseInt(branchId) || branchId,
            description: description,
            account_id: primaryLine.account_id,
            debit: firstDebitLine ? Math.round(firstDebitLine.amount * 100) / 100 : 0,
            credit: firstCreditLine ? Math.round(firstCreditLine.amount * 100) / 100 : 0,
            total_debit: totalDebit,
            total_credit: totalCredit,
            currency: 'SAR', // Default currency - can be made configurable later
            debit_lines: debitLines, // Include for future API support
            credit_lines: creditLines, // Include for future API support
            cost_center_id: primaryLine.cost_center_id || null
        };
        
        // Note: The API currently only supports one line per entry
        // Multiple lines will need API update to loop through debit_lines and credit_lines arrays
        
        try {
            const url = entryId ? `${this.apiBase}/journal-entries.php?id=${entryId}` : `${this.apiBase}/journal-entries.php`;

            // Never let the UI hang forever if the server stalls
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000);

            const response = await fetch(url, {
                method: entryId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            
            const responseText = await response.text();
            
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse server response as JSON. Response:', responseText);
                // Show more details in error message
                let errorMsg = 'Server returned an error. ';
                if (response.status === 500) {
                    errorMsg += 'Internal server error. ';
                }
                if (responseText && responseText.trim().length > 0) {
                    // Try to extract meaningful error from response
                    const errorPreview = responseText.substring(0, 300).replace(/\n/g, ' ');
                    errorMsg += `Error details: ${errorPreview}`;
                } else {
                    errorMsg += 'Please check server logs for details.';
                }
                responseData = { success: false, message: errorMsg };
            }
            
            // Log the actual response for debugging
            if (!response.ok || !responseData.success) {
                console.error('Journal entry API response:', {
                    status: response.status,
                    statusText: response.statusText,
                    responseData: responseData,
                    responseText: responseText.substring(0, 1000)
                });
            }
            
            const isSuccess = response.ok && !!(responseData && responseData.success);
            if (isSuccess) {
                // Mark form as saved to prevent confirmation dialog
                const form = document.getElementById('journalEntryForm');
                if (form) {
                    form.setAttribute('data-saved', 'true');
                }
                
                this.showToast(`Journal entry ${entryId ? 'updated' : 'created'} successfully!`, 'success');
                
                // Close modal immediately without confirmation
                const modal = form ? form.closest('.accounting-modal') : null;
                if (modal) {
                    modal.classList.remove('accounting-modal-visible', 'show-modal');
                    modal.classList.add('accounting-modal-hidden');
                    modal.removeAttribute('data-modal-visible');
                    if (modal.parentNode) {
                        modal.remove();
                    }
                    if (this.activeModal === modal) {
                        this.activeModal = null;
                    }
                    document.body.classList.remove('body-no-scroll');
                }
                
                // Wait a bit for modal to close and database commit, then refresh
                setTimeout(async () => {
                    // New entries are sorted DESC; jump to page 1 so the new row is visible immediately.
                    if (!entryId) {
                        this.modalLedgerCurrentPage = 1;
                    }
                    // Refresh the General Ledger modal table if it's open
                    if (typeof this.loadModalJournalEntries === 'function') {
                        await this.loadModalJournalEntries();
                    }
                    
                    // Refresh the main journal entries table
                if (typeof this.loadJournalEntries === 'function') {
                        await this.loadJournalEntries();
                }
                    
                    // Refresh all modules to keep data synchronized
                    this.refreshAllModules();
                }, 500);

                return true;
            } else {
                const errorMsg = responseData.message || responseData.error || `Failed to save journal entry (HTTP ${response.status}). Please try again.`;
                this.showToast(errorMsg, 'error');
                return false;
            }
        } catch (error) {
            // Handle timeout explicitly
            if (error && (error.name === 'AbortError' || error.code === 20)) {
                this.showToast('Saving is taking too long. Please try again.', 'error');
                return false;
            }
            const errorMsg = error.message || 'Error saving journal entry. Please check your connection and try again.';
            this.showToast(errorMsg, 'error');
            console.error('Journal entry save error:', error);
            return false;
        }
    }

ProfessionalAccounting.prototype.saveInvoice = async function(invoiceId = null) {
        const form = document.getElementById('invoiceForm');
        if (!form) {
            this.showToast('Invoice form not found', 'error');
            return;
        }
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Handle customers array properly - store as comma-separated string
        const customerInputs = form.querySelectorAll('input[name="customers[]"]');
        const customers = Array.from(customerInputs)
            .map(input => input.value.trim())
            .filter(value => value !== '');
        
        if (customers.length > 0) {
            data.customers = customers.join(', ');
        } else {
            data.customers = '';
        }
        
        // Remove the customers[] entries from data since we've processed them
        delete data['customers[]'];
        
        // Handle entity fields - convert empty strings to null
        if (data.agent_id === '' || data.agent_id === null) {
            data.agent_id = null;
        } else {
            data.agent_id = parseInt(data.agent_id) || null;
        }
        if (data.subagent_id === '' || data.subagent_id === null) {
            data.subagent_id = null;
        } else {
            data.subagent_id = parseInt(data.subagent_id) || null;
        }
        if (data.worker_id === '' || data.worker_id === null) {
            data.worker_id = null;
        } else {
            data.worker_id = parseInt(data.worker_id) || null;
        }
        if (data.hr_id === '' || data.hr_id === null) {
            data.hr_id = null;
        } else {
            data.hr_id = parseInt(data.hr_id) || null;
        }
        
        // Map entity fields to entity_type and entity_id
        // Priority: agent > subagent > worker > hr
        if (data.agent_id) {
            data.entity_type = 'agent';
            data.entity_id = data.agent_id;
        } else if (data.subagent_id) {
            data.entity_type = 'subagent';
            data.entity_id = data.subagent_id;
        } else if (data.worker_id) {
            data.entity_type = 'worker';
            data.entity_id = data.worker_id;
        } else if (data.hr_id) {
            data.entity_type = 'hr';
            data.entity_id = data.hr_id;
        } else {
            data.entity_type = null;
            data.entity_id = null;
        }
        
        // Remove individual entity fields as API expects entity_type and entity_id
        delete data.agent_id;
        delete data.subagent_id;
        delete data.worker_id;
        delete data.hr_id;
        
        // Set customer_id to null if empty (API expects null, not empty string)
        if (!data.customer_id || data.customer_id === '') {
            data.customer_id = null;
        } else {
            data.customer_id = parseInt(data.customer_id) || null;
        }
        
        // Ensure numeric fields are properly formatted
        if (data.total_amount) {
            data.total_amount = parseFloat(data.total_amount) || 0;
        }
        if (data.debit_account_id) {
            data.debit_account_id = parseInt(data.debit_account_id) || null;
        }
        if (data.credit_account_id) {
            data.credit_account_id = parseInt(data.credit_account_id) || null;
        }
        
        // Tax: from checkbox (tax_included). Stored as vat_report: "tax_included" | "tax_not_included"
        const taxCb = form.querySelector('#invoiceTaxCheckbox');
        data.tax_included = !!(taxCb && taxCb.checked);
        delete data.vat_report;
        
        // Payment Voucher is auto-generated by API; don't send
        delete data.payment_voucher;
        
        try {
            const url = invoiceId ? `${this.apiBase}/invoices.php?id=${invoiceId}` : `${this.apiBase}/invoices.php`;
            const response = await fetch(url, {
                method: invoiceId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            let responseData;
            let responseText = '';
            try {
                responseText = await response.text();
                if (responseText) {
                    try {
                        responseData = JSON.parse(responseText);
                    } catch (jsonError) {
                        // If it's not JSON, it might be an HTML error page or PHP error
                        responseData = { 
                            success: false, 
                            message: `Server error (${response.status}). The server returned a non-JSON response.`,
                            error: 'Invalid JSON response',
                            rawResponse: responseText.substring(0, 1000)
                        };
                    }
                } else {
                    responseData = { success: false, message: 'Empty response from server' };
                }
            } catch (parseError) {
                responseData = { 
                    success: false, 
                    message: `Server error (${response.status}). Unable to read response.`,
                    error: parseError.message
                };
            }
            
            if (response.ok && responseData.success !== false) {
                this.showToast(`Invoice ${invoiceId ? 'updated' : 'created'} successfully!`, 'success');
                
                // Reload modal invoices BEFORE closing (in case modal is still open)
                const modalInvoicesBody = document.getElementById('modalInvoicesBody');
                if (modalInvoicesBody) {
                    await this.loadModalInvoices();
                }
                
                // Close the invoice form modal
                this.closeModal();
                
                // Refresh all modules to keep data synchronized
                this.refreshAllModules();
                
                // Reload main page invoices if function exists
                if (typeof this.loadInvoices === 'function') {
                    this.loadInvoices();
                }
            } else {
                const errorMessage = responseData.message || responseData.error || `Server error (${response.status}). Please check the server logs.`;
                this.showToast(errorMessage, 'error');
            }
        } catch (error) {
            this.showToast('Error saving invoice: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.saveBill = async function(billId = null) {
        const form = document.getElementById('billForm');
        if (!form) {
            this.showToast('Bill form not found', 'error');
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Validate required fields
        if (!data.bill_date) {
            this.showToast('Bill date is required', 'error');
            return;
        }
        if (!data.due_date) {
            this.showToast('Due date is required', 'error');
            return;
        }
        if (!data.total_amount || parseFloat(data.total_amount) <= 0) {
            this.showToast('Total amount must be greater than 0', 'error');
            return;
        }
        
        
        try {
            const url = billId ? `${this.apiBase}/bills.php?id=${billId}` : `${this.apiBase}/bills.php`;
            const response = await fetch(url, {
                method: billId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const responseData = await response.json().catch(() => ({ success: false, message: 'Invalid response' }));
            
            
            if (response.ok && responseData.success !== false) {
                this.showToast(`Bill ${billId ? 'updated' : 'created'} successfully!`, 'success');
                
                // Reload modal bills BEFORE closing (in case modal is still open)
                const modalBillsBody = document.getElementById('modalBillsBody');
                if (modalBillsBody) {
                    await this.loadModalBills();
                }
                
                // Close the bill form modal
                this.closeModal();
                
                // Refresh all modules to keep data synchronized
                this.refreshAllModules();
                
                // Reload main page bills if function exists
                if (typeof this.loadBills === 'function') {
                    this.loadBills();
                }
            } else {
                this.showToast(responseData.message || 'Failed to save bill. Please try again.', 'error');
            }
        } catch (error) {
            this.showToast('Error saving bill. The API may not be available yet.', 'error');
        }
    }

ProfessionalAccounting.prototype.saveBankAccount = async function(bankId = null) {
        // Prevent duplicate submissions - double check
        if (this._savingBankAccount) {
            console.warn('saveBankAccount: Duplicate call prevented');
            return;
        }
        this._savingBankAccount = true;
        
        const form = document.getElementById('bankAccountForm');
        if (!form || !form.isConnected) {
            this.showToast('Bank account form not found', 'error');
            this._savingBankAccount = false;
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Map form field names to API field names
        if (data.initial_balance !== undefined) {
            data.opening_balance = data.initial_balance;
            delete data.initial_balance;
        }
        
        // Validate required fields
        if (!data.bank_name || !data.account_name) {
            this.showToast('Bank name and account name are required', 'error');
            this._savingBankAccount = false;
            return;
        }
        
        const isEdit = bankId !== null && bankId > 0;
        const method = isEdit ? 'PUT' : 'POST';
        const url = isEdit ? `${this.apiBase}/banks.php?id=${bankId}` : `${this.apiBase}/banks.php`;
        
        let timeoutId = null;
        let controller = null;
        try {
            // Create AbortController for timeout protection
            controller = new AbortController();
            timeoutId = setTimeout(() => {
                if (controller) {
                    controller.abort();
                }
            }, 30000); // 30 second timeout
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                signal: controller.signal
            });
            
            // Clear timeout immediately after fetch completes
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            
            // Check if request was aborted before parsing
            if (controller.signal.aborted) {
                throw new DOMException('Request was aborted', 'AbortError');
            }
            
            const responseData = await response.json().catch(() => ({ success: false, message: 'Invalid response' }));
            
            // Verify response is actually successful
            if (response.ok && responseData && responseData.success !== false) {
                this.showToast(`Bank account ${isEdit ? 'updated' : 'added'} successfully!`, 'success');
                
                // Close the bank account form modal first
                const modal = document.getElementById('bankAccountFormModal');
                if (modal) {
                    this.closeModal();
                }
                
                // Reload banking cash modal table if open (only once, loadBankAccounts already calls setupBankAccountActions)
                const bankAccountsTableBody = document.getElementById('bankAccountsTableBody');
                if (bankAccountsTableBody) {
                    await this.loadBankAccounts();
                }
                
                // Reload modal bank accounts if exists (for other modals)
                const modalBankAccountsBody = document.getElementById('modalBankAccountsBody');
                if (modalBankAccountsBody) {
                    await this.loadModalBankAccounts();
                }
            } else {
                this.showToast(responseData.message || `Failed to ${isEdit ? 'update' : 'add'} bank account. Please try again.`, 'error');
            }
        } catch (error) {
            // Clear timeout if still active
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            
            // Abort controller if still active
            if (controller && !controller.signal.aborted) {
                controller.abort();
            }
            
            if (error.name === 'AbortError' || error instanceof DOMException && error.name === 'AbortError') {
                this.showToast('Request timeout. Please check your connection and try again.', 'error');
            } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showToast('Network error. Please check your connection and try again.', 'error');
            } else {
                this.showToast(`Error saving bank account: ${error.message || 'Unknown error'}`, 'error');
            }
        } finally {
            // Always clear timeout and reset flag, even on timeout or network errors
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            this._savingBankAccount = false;
        }
    }

ProfessionalAccounting.prototype.saveEntityTransaction = async function(transactionId = null) {
        const form = document.getElementById('entityTransactionForm');
        if (!form) {
            this.showToast('Form not found', 'error');
            return;
        }
        
        // Also try to get transaction ID from form attribute if not provided
        if (!transactionId) {
            const transactionIdAttr = form.getAttribute('data-transaction-id');
            transactionId = transactionIdAttr && transactionIdAttr !== 'null' ? transactionIdAttr : null;
        }
        
        // Get debit and credit values from form FIRST, before FormData (to avoid empty string issues)
        const debitField = form.querySelector('[name="debit"]') || document.getElementById('entityTransactionDebit');
        const creditField = form.querySelector('[name="credit"]') || document.getElementById('entityTransactionCredit');
        const typeField = form.querySelector('[name="entry_type"]') || document.getElementById('entityTransactionType');
        
        // Read values directly from fields
        let debitValue = 0;
        let creditValue = 0;
        let entryTypeValue = null;
        
        if (debitField && debitField.value && debitField.value.trim() !== '') {
            debitValue = parseFloat(debitField.value);
            if (isNaN(debitValue)) debitValue = 0;
        }
        
        if (creditField && creditField.value && creditField.value.trim() !== '') {
            creditValue = parseFloat(creditField.value);
            if (isNaN(creditValue)) creditValue = 0;
        }
        
        // Read entry_type directly from form field
        if (typeField) {
            entryTypeValue = typeField.value ? typeField.value.trim() : null;
        } else {
            // Try alternative selectors
            const altTypeField = document.getElementById('entityTransactionType') || 
                                form.querySelector('select[name="entry_type"]') ||
                                form.querySelector('select[name="type"]');
            if (altTypeField) {
                entryTypeValue = altTypeField.value ? altTypeField.value.trim() : null;
            }
        }
        
        // Get entity value from dropdown BEFORE FormData (to parse it correctly)
        const entitySelect = form.querySelector('[name="entity_id"]') || 
                             document.getElementById('entitySelect') ||
                             form.querySelector('#entitySelect');
        let entityType = null;
        let entityId = null;
        
        if (entitySelect && entitySelect.value) {
            const entityValue = entitySelect.value;
            
            // Parse entity value (format: "entityType:entityId" or just "entityId")
            if (entityValue.includes(':')) {
                const entityParts = entityValue.split(':');
                if (entityParts.length === 2) {
                    entityType = entityParts[0].toLowerCase();
                    entityId = parseInt(entityParts[1]);
                }
            } else if (entityValue && !isNaN(parseInt(entityValue))) {
                // If it's just a number, we need entity_type from somewhere else
                entityId = parseInt(entityValue);
            }
        } else {
        }
        
        // Now get form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Override with directly read values (to ensure they're not lost)
        data.debit = debitValue;
        data.debit_amount = debitValue;
        data.credit = creditValue;
        data.credit_amount = creditValue;
        
        // Override entry_type with directly read value (to ensure it's not lost)
        if (entryTypeValue) {
            data.entry_type = entryTypeValue;
        } else if (data.entry_type) {
            // Fallback to FormData value if direct read failed
            data.entry_type = data.entry_type;
        } else {
            // Default to Manual if not provided
            data.entry_type = 'Manual';
        }
        
        // Override entity values with parsed values from dropdown
        if (entityType && entityId) {
            data.entity_type = entityType;
            data.entity_id = entityId;
        } else {
            // Fallback to FormData values if parsing failed
            // Normalize entity_type to lowercase
            if (data.entity_type) {
                data.entity_type = data.entity_type.toLowerCase();
            }
            
            // Convert entity_id to integer (if it's not in "type:id" format)
            if (data.entity_id) {
                // Check if it's in "type:id" format
                if (typeof data.entity_id === 'string' && data.entity_id.includes(':')) {
                    const parts = data.entity_id.split(':');
                    if (parts.length === 2) {
                        data.entity_type = parts[0].toLowerCase();
                        data.entity_id = parseInt(parts[1]);
                    }
                } else {
                    data.entity_id = parseInt(data.entity_id);
                }
            }
        }
        
        // Calculate total_amount from debit or credit (whichever is greater)
        if (data.debit > 0 || data.credit > 0) {
            data.total_amount = Math.max(data.debit || 0, data.credit || 0);
        } else if (data.amount) {
            // Fallback to amount if debit/credit not provided
            data.total_amount = parseFloat(data.amount);
            delete data.amount; // Remove 'amount', use 'total_amount' for API
        }
        
        // Map form field names to API field names
        if (data.transaction_date) {
            data.transaction_date = data.transaction_date;
        }
        
        // Also set transaction_type for backward compatibility (but don't override entry_type)
        if (!data.transaction_type) {
            // If transaction_type not provided, derive it from debit/credit
            if (data.debit > 0) {
                data.transaction_type = 'Expense';
            } else if (data.credit > 0) {
                data.transaction_type = 'Income';
            } else {
                data.transaction_type = 'Expense'; // Default
            }
        }
        // Handle reference_number (new field name matching journal entry form)
        if (data.reference_number) {
            data.reference_number = data.reference_number;
            // Also set reference for backward compatibility if API expects it
            if (!data.reference) {
                data.reference = data.reference_number;
            }
        } else if (data.reference) {
            // Fallback: if reference_number not present, use reference
            data.reference_number = data.reference;
        }
        
        // Add transaction ID to request body for PUT requests
        if (transactionId) {
            data.id = parseInt(transactionId);
        }
        
        try {
            const url = `${this.apiBase}/entity-transactions.php${transactionId ? `?id=${transactionId}` : ''}`;
            const response = await fetch(url, {
                method: transactionId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const responseData = await response.json().catch(() => ({ success: false, message: 'Invalid response' }));
            
            if (response.ok && responseData.success !== false) {
                this.showToast(`Transaction ${transactionId ? 'updated' : 'created'} successfully!`, 'success');
                
                // Close modal immediately to avoid showing form reset
                this.closeModal();
                
                // Wait a bit for modal to close and database commit, then refresh
                setTimeout(async () => {
                    // Refresh the main General Ledger table
                    if (typeof this.loadJournalEntries === 'function') {
                        await this.loadJournalEntries();
                    }
                    
                    // Refresh entity transactions table with cache busting
                    const tbody = document.getElementById('entityTransactionsBody');
                    if (tbody) {
                        // Reset to first page to see the updated transaction
                        this.entityTransactionsCurrentPage = 1;
                        // Force reload with cache busting
                        await this.loadEntityTransactionsData();
                    }
                    
                // Refresh all modules to keep data synchronized
                this.refreshAllModules();
                    
                    // Refresh modal transactions if open
                if (typeof this.loadModalTransactions === 'function') {
                    this.loadModalTransactions();
                }
                }, 800);
            } else {
                this.showToast(responseData.message || 'Failed to save transaction. Please try again.', 'error');
            }
        } catch (error) {
            this.showToast('Error saving transaction. The API may not be available yet.', 'error');
        }
    }

ProfessionalAccounting.prototype.loadEntitiesForSelect = async function(entityType, selectElement, agentId = null, subagentId = null) {
        if (!selectElement) {
            return;
        }
        
        if (!entityType || entityType === '') {
            selectElement.innerHTML = '<option value="">Select Entity</option>';
            return;
        }
        
        try {
            // Show loading state
            selectElement.innerHTML = '<option value="">Loading...</option>';
            selectElement.disabled = true;
            
            let url = `${this.apiBase}/entities.php?entity_type=${encodeURIComponent(entityType)}&_t=${Date.now()}`;
            if (agentId !== null && agentId !== '') {
                url += `&agent_id=${encodeURIComponent(agentId)}`;
            }
            if (subagentId !== null && subagentId !== '') {
                url += `&subagent_id=${encodeURIComponent(subagentId)}`;
            }
            const response = await fetch(url, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load entities');
            }
            
            if (data.entities && Array.isArray(data.entities)) {
                // Deduplicate entities using Map for unique keys
                // Primary deduplication: by entity_type:ID combination
                const uniqueEntities = new Map();
                const seenIds = new Set(); // Track by ID only for this entity type
                
                data.entities.forEach(entity => {
                    if (!entity || !entity.id) return;
                    
                    const entityId = String(entity.id);
                    const key = `${(entity.entity_type || entityType).toLowerCase()}:${entityId}`;
                    
                    // Skip if we've already seen this exact ID (primary check)
                    if (seenIds.has(entityId)) {
                        return;
                    }
                    
                    // Only add if not already in map by key
                    if (!uniqueEntities.has(key)) {
                        uniqueEntities.set(key, entity);
                        seenIds.add(entityId);
                    }
                });
                
                // Convert to array and sort alphabetically
                const sortedEntities = Array.from(uniqueEntities.values()).sort((a, b) => {
                    const nameA = (a.display_name || a.name || '').toLowerCase();
                    const nameB = (b.display_name || b.name || '').toLowerCase();
                    return nameA.localeCompare(nameB);
                });
                
                // Clear and rebuild dropdown - clear completely first
                selectElement.innerHTML = '';
                
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Entity';
                selectElement.appendChild(defaultOption);
                
                let finalUnique = null;
                
                if (sortedEntities.length === 0) {
                    const noDataOption = document.createElement('option');
                    noDataOption.value = '';
                    noDataOption.textContent = 'No entities found';
                    noDataOption.disabled = true;
                    selectElement.appendChild(noDataOption);
                } else {
                    // Final deduplication check before adding to DOM - only by ID value
                    finalUnique = new Map();
                    const finalSeenIds = new Set();
                    
                    sortedEntities.forEach(entity => {
                        const entityId = String(entity.id);
                        
                        // Skip if we've already added this exact ID (not by text, as names can repeat)
                        if (finalSeenIds.has(entityId)) {
                            return;
                        }
                        
                        // Add to final unique set
                        finalUnique.set(entityId, entity);
                        finalSeenIds.add(entityId);
                    });
                    
                    // Now add only truly unique entities by ID
                    // Also check DOM to prevent duplicates
                    const existingValues = new Set();
                    Array.from(finalUnique.values()).forEach(entity => {
                        const entityId = String(entity.id);
                        const optionText = entity.display_name || entity.name || `Entity #${entity.id}`;
                        
                        if (existingValues.has(entityId)) {
                            return;
                        }
                        
                        // Also check if option with this value already exists
                        const existingOption = Array.from(selectElement.options).find(opt => opt.value === entityId);
                        if (existingOption) {
                            return;
                        }
                        
                        const option = document.createElement('option');
                        // For modal filter, use format: entity_type:entity_id
                        // For other selects, use just entity_id
                        const isModalFilter = selectElement.id === 'modalEntityFilter';
                        option.value = isModalFilter ? `${entityType}:${entityId}` : entityId;
                        option.textContent = optionText;
                        selectElement.appendChild(option);
                        existingValues.add(entityId);
                    });
                }
                
                selectElement.disabled = false;
            } else {
                selectElement.innerHTML = '<option value="">No entities available</option>';
                selectElement.disabled = false;
            }
        } catch (error) {
            selectElement.innerHTML = '<option value="">Error loading entities</option>';
            selectElement.disabled = false;
            this.showToast('Failed to load entities: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.setupCustomerFields = function(containerId, editId = null) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // If editing, load existing customers
        if (editId) {
            // This will be handled when form data is loaded
            // For now, we'll keep the single field
        }

        // Handle add customer button clicks
        container.addEventListener('click', (e) => {
            if (e.target.closest('.btn-add-customer')) {
                e.preventDefault();
                const currentRow = e.target.closest('.customer-input-row');
                const index = parseInt(currentRow.getAttribute('data-customer-index')) + 1;
                
                // Hide the add button on current row
                const addBtn = currentRow.querySelector('.btn-add-customer');
                if (addBtn) addBtn.style.display = 'none';
                
                // Create new row
                const newRow = document.createElement('div');
                newRow.className = 'customer-input-row';
                newRow.setAttribute('data-customer-index', index);
                newRow.innerHTML = `
                    <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                    <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="btn-remove-customer" data-action="remove-customer" title="Remove Customer">
                        <i class="fas fa-minus"></i>
                    </button>
                `;
                
                container.appendChild(newRow);
            }
            
            // Handle remove customer button clicks
            if (e.target.closest('.btn-remove-customer')) {
                e.preventDefault();
                const rowToRemove = e.target.closest('.customer-input-row');
                const allRows = container.querySelectorAll('.customer-input-row');
                
                if (allRows.length > 1) {
                    rowToRemove.remove();
                    
                    // Show add button on last remaining row if it was hidden
                    const remainingRows = container.querySelectorAll('.customer-input-row');
                    if (remainingRows.length > 0) {
                        const lastRow = remainingRows[remainingRows.length - 1];
                        const addBtn = lastRow.querySelector('.btn-add-customer');
                        if (addBtn) addBtn.style.display = 'inline-block';
                    }
                }
            }
        });
    }

ProfessionalAccounting.prototype.setupEntityCascadingDropdowns = function(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        const agentSelect = form.querySelector('[name="agent_id"]');
        const subagentSelect = form.querySelector('[name="subagent_id"]');
        const workerSelect = form.querySelector('[name="worker_id"]');

        // When agent changes, reload subagents and clear workers
        if (agentSelect && subagentSelect) {
            // Remove existing listeners by cloning
            const newAgentSelect = agentSelect.cloneNode(true);
            agentSelect.parentNode.replaceChild(newAgentSelect, agentSelect);
            
            newAgentSelect.addEventListener('change', async (e) => {
                const agentId = e.target.value;
                if (subagentSelect) {
                    subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
                }
                if (workerSelect) {
                    workerSelect.innerHTML = '<option value="">Select Worker</option>';
                }
                
                if (agentId && subagentSelect) {
                    await this.loadEntitiesForSelect('subagent', subagentSelect, agentId, null);
                } else if (subagentSelect) {
                    await this.loadEntitiesForSelect('subagent', subagentSelect, null, null);
                }
            });
        }

        // When subagent changes, reload workers
        if (subagentSelect && workerSelect) {
            // Remove existing listeners by cloning
            const newSubagentSelect = subagentSelect.cloneNode(true);
            subagentSelect.parentNode.replaceChild(newSubagentSelect, subagentSelect);
            
            newSubagentSelect.addEventListener('change', async (e) => {
                const subagentId = e.target.value;
                if (workerSelect) {
                    workerSelect.innerHTML = '<option value="">Select Worker</option>';
                }
                
                if (subagentId && workerSelect) {
                    await this.loadEntitiesForSelect('worker', workerSelect, null, subagentId);
                } else if (workerSelect) {
                    await this.loadEntitiesForSelect('worker', workerSelect, null, null);
                }
            });
        }
    }

ProfessionalAccounting.prototype.loadEntitiesForModal = async function() {
        const entitySelect = document.getElementById('modalEntityFilter');
        const entityTypeFilter = document.getElementById('modalEntityTypeFilter');
        
        if (!entitySelect) return;

        // Initialize with "All" option only
        entitySelect.innerHTML = '<option value="">All</option>';
        
        // If an entity type is already selected, load entities for that type
        if (entityTypeFilter && entityTypeFilter.value) {
            const entityType = entityTypeFilter.value;
            const normalized = this.normalizeEntityTypeForModal(entityType);
            if (normalized) {
                await this.loadEntitiesForSelect(normalized, entitySelect);
                // Ensure "All" option is at the beginning
                if (entitySelect.options.length > 1 && entitySelect.options[0].value !== '') {
                    const allOption = document.createElement('option');
                    allOption.value = '';
                    allOption.textContent = 'All';
                    entitySelect.insertBefore(allOption, entitySelect.firstChild);
                }
            }
        }
    }
    
ProfessionalAccounting.prototype.normalizeEntityTypeForModal = function(entityType) {
        if (!entityType) return '';
        const normalized = entityType.toLowerCase();
        const entityTypeMap = {
            'agents': 'agent',
            'subagents': 'subagent',
            'workers': 'worker',
            'hr': 'hr'
        };
        return entityTypeMap[normalized] || normalized;
    }

ProfessionalAccounting.prototype.restoreReportsGrid = function() {
        const reportsGrid = document.getElementById('modalReportsGrid');
        const reportContent = document.getElementById('modalReportContent');
        
        if (reportsGrid) {
            reportsGrid.classList.remove('reports-grid-hidden');
            reportsGrid.classList.add('reports-grid-visible');
        }
        
        if (reportContent) {
            reportContent.classList.remove('report-content-visible');
            reportContent.classList.add('report-content-hidden');
            reportContent.classList.remove('show');
            reportContent.innerHTML = '';
        }
        
        this.attachReportCardListeners();
    }

ProfessionalAccounting.prototype.viewEntityTransaction = async function(transactionId) {
        try {
            const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
            const data = await response.json().catch(function() { return {}; });
            if (!data.success || !data.transaction) {
                this.showToast((data && data.message) || 'Failed to load transaction', 'error');
                return;
            }
            {
                const trans = data.transaction;
                const entityTypeName = trans.entity_type ? trans.entity_type.charAt(0).toUpperCase() + trans.entity_type.slice(1) : 'Unknown';
                
                const content = `
                    <div class="transaction-details-view">
                        <div class="detail-row">
                            <div class="detail-label">Transaction ID:</div>
                            <div class="detail-value">${trans.id || transactionId}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Reference Number:</div>
                            <div class="detail-value">${trans.reference_number || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entity Type:</div>
                            <div class="detail-value"><span class="badge badge-info">${entityTypeName}</span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entity Name:</div>
                            <div class="detail-value">${this.escapeHtml(trans.entity_name || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Transaction Date:</div>
                            <div class="detail-value">${trans.transaction_date || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Transaction Type:</div>
                            <div class="detail-value">
                                <span class="badge ${trans.transaction_type === 'Income' ? 'badge-success' : 'badge-danger'}">
                                    ${trans.transaction_type || 'N/A'}
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Amount:</div>
                            <div class="detail-value"><strong>${this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync())}</strong></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Currency:</div>
                            <div class="detail-value"><strong>${trans.currency || this.getDefaultCurrencySync()}</strong></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label debit-label">Debit Amount:</div>
                            <div class="detail-value debit-value">${trans.debit_amount && parseFloat(trans.debit_amount) > 0 ? this.formatCurrency(trans.debit_amount, trans.currency || this.getDefaultCurrencySync()) : (trans.transaction_type === 'Expense' ? this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync()) : '-')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label credit-label">Credit Amount:</div>
                            <div class="detail-value credit-value">${trans.credit_amount && parseFloat(trans.credit_amount) > 0 ? this.formatCurrency(trans.credit_amount, trans.currency || this.getDefaultCurrencySync()) : (trans.transaction_type === 'Income' ? this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync()) : '-')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Description:</div>
                            <div class="detail-value">${this.escapeHtml(trans.description || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Category:</div>
                            <div class="detail-value">${this.escapeHtml(trans.category || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <span class="badge badge-info">${trans.status || 'Posted'}</span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created By:</div>
                            <div class="detail-value">${this.escapeHtml(trans.created_by_name || 'System')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created At:</div>
                            <div class="detail-value">${trans.created_at || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Updated At:</div>
                            <div class="detail-value">${trans.updated_at && trans.updated_at !== trans.created_at ? trans.updated_at : (trans.updated_at || trans.created_at || 'N/A')}</div>
                        </div>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Close</button>
                        <button type="button" class="btn btn-primary" data-action="print-transaction" data-id="${transactionId}">
                            <i class="fas fa-print"></i> Print Transaction
                        </button>
                    </div>
                `;
                
                this.showModal('Transaction Details', content);
                
                // Attach print button handler using event delegation
                setTimeout(() => {
                    const modal = document.getElementById('accountingModal');
                    if (modal) {
                        const printBtn = modal.querySelector('[data-action="print-transaction"][data-id="' + transactionId + '"]');
                        if (printBtn) {
                            printBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                this.printTransaction(transactionId);
                            });
                        }
                    }
                }, 100);
            }
        } catch (error) {
            this.showToast(error && error.message ? error.message : 'Error loading transaction details', 'error');
        }
    }

ProfessionalAccounting.prototype.viewJournalEntry = async function(entryId) {
        try {
            const response = await fetch(`${this.apiBase}/journal-entries.php?id=${entryId}`);
            const data = await response.json().catch(function() { return {}; });
            if (!data.success || !data.entry) {
                this.showToast((data && data.message) || 'Failed to load journal entry', 'error');
                return;
            }
            {
                const entry = data.entry;
                const entryTypeName = entry.entry_type || 'Manual';
                const entryTypeClass = entryTypeName.toLowerCase();
                
                const content = `
                    <div class="transaction-details-view">
                        <div class="detail-row">
                            <div class="detail-label">Entry ID:</div>
                            <div class="detail-value">${entry.id || entryId}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entry Number:</div>
                            <div class="detail-value">${this.escapeHtml(entry.entry_number || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Reference Number:</div>
                            <div class="detail-value">${this.escapeHtml(entry.reference_number || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entry Date:</div>
                            <div class="detail-value">${entry.entry_date || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entry Type:</div>
                            <div class="detail-value">
                                <span class="badge badge-${entryTypeClass === 'income' ? 'success' : entryTypeClass === 'expense' ? 'danger' : 'info'}">${this.escapeHtml(entryTypeName)}</span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Description:</div>
                            <div class="detail-value">${this.escapeHtml(entry.description || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label debit-label">Debit Amount:</div>
                            <div class="detail-value debit-value">${entry.total_debit && parseFloat(entry.total_debit) > 0 ? this.formatCurrency(entry.total_debit, entry.currency || this.getDefaultCurrencySync()) : '-'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label credit-label">Credit Amount:</div>
                            <div class="detail-value credit-value">${entry.total_credit && parseFloat(entry.total_credit) > 0 ? this.formatCurrency(entry.total_credit, entry.currency || this.getDefaultCurrencySync()) : '-'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Currency:</div>
                            <div class="detail-value"><strong>${entry.currency || this.getDefaultCurrencySync()}</strong></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Account:</div>
                            <div class="detail-value">${entry.account_name ? this.escapeHtml(entry.account_name) : (entry.account_id ? `Account ID: ${entry.account_id}` : 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Entity:</div>
                            <div class="detail-value">${entry.entity_name ? this.escapeHtml(entry.entity_name) : (entry.journal_entity ? this.escapeHtml(entry.journal_entity) : 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <span class="badge badge-info">${this.escapeHtml(((entry.status || 'Draft').toLowerCase() === 'draft') ? 'Waiting for approval' : (entry.status || 'Draft'))}</span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created By:</div>
                            <div class="detail-value">${this.escapeHtml(entry.created_by_name || 'System')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created At:</div>
                            <div class="detail-value">${entry.created_at || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Updated At:</div>
                            <div class="detail-value">${entry.updated_at && entry.updated_at !== entry.created_at ? entry.updated_at : (entry.updated_at || entry.created_at || 'N/A')}</div>
                        </div>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Close</button>
                        <button type="button" class="btn btn-primary" data-action="print-journal-entry" data-id="${entryId}">
                            <i class="fas fa-print"></i> Print Entry
                        </button>
                    </div>
                `;
                
                this.showModal('Journal Entry Details', content);
                
                // Attach button handlers
                setTimeout(() => {
                    const modal = document.getElementById('accountingModal');
                    if (modal) {
                        const printBtn = modal.querySelector('[data-action="print-journal-entry"][data-id="' + entryId + '"]');
                        if (printBtn) {
                            printBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                this.printJournalEntry(entryId);
                            });
                        }
                    }
                }, 100);
            }
        } catch (error) {
            this.showToast(error && error.message ? error.message : 'Error loading journal entry details', 'error');
        }
    }

ProfessionalAccounting.prototype.duplicateEntityTransaction = async function(transactionId) {
        try {
            const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
            const data = await response.json().catch(function() { return {}; });
            if (!data.success || !data.transaction) {
                this.showToast((data && data.message) || 'Failed to load transaction', 'error');
                return;
            }
            {
                const trans = data.transaction;
                
                // Open the modal in create mode with pre-filled data
                const content = await this.getEntityTransactionModalContent(null);
                this.showModal('Duplicate Transaction', content);
                
                // After modal is shown, populate the form with transaction data
                setTimeout(() => {
                    const form = document.getElementById('entityTransactionForm');
                    if (form) {
                        // Set entity type
                        const entityTypeSelect = document.getElementById('entityTypeSelect');
                        if (entityTypeSelect && trans.entity_type) {
                            entityTypeSelect.value = trans.entity_type;
                            entityTypeSelect.dispatchEvent(new Event('change'));
                        }
                        
                        // Set entity after loading
                        setTimeout(async () => {
                            const entitySelect = document.getElementById('entitySelect');
                            if (entitySelect && trans.entity_id && trans.entity_type) {
                                await this.loadEntitiesForSelect(trans.entity_type, entitySelect);
                                setTimeout(() => {
                                    const entityId = String(trans.entity_id);
                                    const optionExists = Array.from(entitySelect.options).some(opt => opt.value === entityId);
                                    if (optionExists) {
                                        entitySelect.value = entityId;
                                    } else {
                                        // Wait a bit more if option not ready yet
                                        setTimeout(() => {
                                            const optionExists2 = Array.from(entitySelect.options).some(opt => opt.value === entityId);
                                            if (optionExists2) {
                                                entitySelect.value = entityId;
                                            }
                                        }, 300);
                                    }
                                }, 300);
                            }
                            
                            // Set other fields
                            const dateInput = form.querySelector('[name="transaction_date"]');
                            if (dateInput) {
                                const dateValue = trans.transaction_date || new Date().toISOString();
                                dateInput.value = this.formatDateForInput(dateValue);
                            }
                            
                            const typeSelect = form.querySelector('[name="transaction_type"]');
                            if (typeSelect) typeSelect.value = trans.transaction_type || 'Expense';
                            
                            const amountInput = form.querySelector('[name="amount"]');
                            if (amountInput) amountInput.value = trans.total_amount || 0;
                            
                            const descTextarea = form.querySelector('[name="description"]');
                            if (descTextarea) descTextarea.value = trans.description || '';
                            
                            const categoryInput = form.querySelector('[name="category"]');
                            if (categoryInput) categoryInput.value = trans.category || 'other';
                            
                            // Clear reference number (will be auto-generated)
                            const refInput = form.querySelector('[name="reference_number"]');
                            if (refInput) refInput.value = '';
                            
                            // Update submit button text
                            const submitBtn = form.querySelector('button[type="submit"]');
                            if (submitBtn) submitBtn.textContent = 'Create Duplicate Transaction';
                        }, 200);
                    }
                }, 100);
            }
        } catch (error) {
            this.showToast(error && error.message ? error.message : 'Error duplicating transaction', 'error');
        }
    }

ProfessionalAccounting.prototype.voidEntityTransaction = async function(transactionId) {
        const confirmed = await this.showConfirmDialog(
            'Void Transaction',
            'Are you sure you want to void this transaction? This action cannot be undone and will maintain the transaction in the audit trail.',
            'Void',
            'Cancel',
            'warning'
        );
        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/entity-transactions.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: transactionId,
                    status: 'Voided'
                })
            });
            const data = await response.json().catch(function() { return {}; });
            if (data.success) {
                this.showToast('Transaction voided successfully', 'success');
                // Refresh the transactions table
                this.loadModalTransactions();
            } else {
                this.showToast(data.message || 'Failed to void transaction', 'error');
            }
        } catch (error) {
            this.showToast('Failed to void transaction', 'error');
        }
    }

ProfessionalAccounting.prototype.printJournalEntry = async function(entryId) {
        try {
            const response = await fetch(`${this.apiBase}/journal-entries.php?id=${entryId}`);
            const data = await response.json().catch(function() { return {}; });
            if (!data.success || !data.entry) {
                this.showToast((data && data.message) || 'Failed to load entry for printing', 'error');
                return;
            }
            {
                const entry = data.entry;
                const entryTypeName = entry.entry_type || 'Manual';
                
                // Create a new window with print content
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                if (!printWindow) {
                    this.showToast('Please allow popups to print journal entries', 'warning');
                    return;
                }
                
                // Create complete HTML document for printing
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Journal Entry Receipt</title>
                        <link rel="stylesheet" href="${(window.APP_CONFIG && window.APP_CONFIG.baseUrl) || ''}/css/accounting/professional.css">
                    </head>
                    <body class="print-reset">
                        <div class="transaction-print-view">
                            <div class="print-header">
                                <h2>Journal Entry Receipt</h2>
                                <div class="print-date">Generated: ${this.formatDate(new Date().toISOString().split('T')[0])}</div>
                            </div>
                            <div class="print-body">
                                <table class="print-table">
                                    <tr>
                                        <td class="print-label">Entry ID:</td>
                                        <td class="print-value">${entry.id || entryId}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Entry Number:</td>
                                        <td class="print-value">${this.escapeHtml(entry.entry_number || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Reference Number:</td>
                                        <td class="print-value">${this.escapeHtml(entry.reference_number || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Entry Date:</td>
                                        <td class="print-value">${entry.entry_date || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Entry Type:</td>
                                        <td class="print-value">${this.escapeHtml(entryTypeName)}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Description:</td>
                                        <td class="print-value">${this.escapeHtml(entry.description || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Account:</td>
                                        <td class="print-value">${entry.account_name ? this.escapeHtml(entry.account_name) : (entry.account_id ? `Account ID: ${entry.account_id}` : 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Debit Amount:</td>
                                        <td class="print-value">${entry.total_debit && parseFloat(entry.total_debit) > 0 ? this.formatCurrency(entry.total_debit, entry.currency || this.getDefaultCurrencySync()) : '-'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Credit Amount:</td>
                                        <td class="print-value">${entry.total_credit && parseFloat(entry.total_credit) > 0 ? this.formatCurrency(entry.total_credit, entry.currency || this.getDefaultCurrencySync()) : '-'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Currency:</td>
                                        <td class="print-value"><strong>${entry.currency || this.getDefaultCurrencySync()}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Status:</td>
                                        <td class="print-value">${this.escapeHtml(((entry.status || 'Draft').toLowerCase() === 'draft') ? 'Waiting for approval' : (entry.status || 'Draft'))}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Created By:</td>
                                        <td class="print-value">${this.escapeHtml(entry.created_by_name || 'System')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Created At:</td>
                                        <td class="print-value">${entry.created_at || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Updated At:</td>
                                        <td class="print-value">${entry.updated_at && entry.updated_at !== entry.created_at ? entry.updated_at : (entry.updated_at || entry.created_at || 'N/A')}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="print-footer">
                                <p>This is a computer-generated document.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                
                // Add event listener for print after window loads
                printWindow.addEventListener('load', function() {
                    printWindow.print();
                    printWindow.addEventListener('afterprint', function() {
                        printWindow.close();
                    });
                });
            }
        } catch (error) {
            this.showToast(error && error.message ? error.message : 'Error printing journal entry', 'error');
        }
    }

ProfessionalAccounting.prototype.printTransaction = async function(transactionId) {
        try {
            const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
            const data = await response.json().catch(function() { return {}; });
            if (!data.success || !data.transaction) {
                this.showToast((data && data.message) || 'Failed to load transaction for printing', 'error');
                return;
            }
            {
                const trans = data.transaction;
                const entityTypeName = trans.entity_type ? trans.entity_type.charAt(0).toUpperCase() + trans.entity_type.slice(1) : 'Unknown';
                
                // Create a new window with print content
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                if (!printWindow) {
                    this.showToast('Please allow popups to print transactions', 'warning');
                    return;
                }
                
                // Create complete HTML document for printing
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Transaction Receipt</title>
                        <link rel="stylesheet" href="${(window.APP_CONFIG && window.APP_CONFIG.baseUrl) || ''}/css/accounting/professional.css">
                    </head>
                    <body class="print-reset">
                        <div class="transaction-print-view">
                            <div class="print-header">
                                <h2>Transaction Receipt</h2>
                                <div class="print-date">Printed: ${this.formatDate(new Date().toISOString().split('T')[0])}</div>
                            </div>
                            <div class="print-body">
                                <table class="print-table">
                                    <tr>
                                        <td class="print-label">Transaction ID:</td>
                                        <td class="print-value">${trans.id || transactionId}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Reference Number:</td>
                                        <td class="print-value">${trans.reference_number || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Entity Type:</td>
                                        <td class="print-value">${entityTypeName}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Entity Name:</td>
                                        <td class="print-value">${this.escapeHtml(trans.entity_name || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Transaction Date:</td>
                                        <td class="print-value">${trans.transaction_date || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Transaction Type:</td>
                                        <td class="print-value">${trans.transaction_type || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Amount:</td>
                                        <td class="print-value"><strong>${this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync())}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Currency:</td>
                                        <td class="print-value"><strong>${trans.currency || this.getDefaultCurrencySync()}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Debit Amount:</td>
                                        <td class="print-value">${trans.debit_amount && parseFloat(trans.debit_amount) > 0 ? this.formatCurrency(trans.debit_amount, trans.currency || this.getDefaultCurrencySync()) : (trans.transaction_type === 'Expense' ? this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync()) : '-')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Credit Amount:</td>
                                        <td class="print-value">${trans.credit_amount && parseFloat(trans.credit_amount) > 0 ? this.formatCurrency(trans.credit_amount, trans.currency || this.getDefaultCurrencySync()) : (trans.transaction_type === 'Income' ? this.formatCurrency(trans.total_amount || 0, trans.currency || this.getDefaultCurrencySync()) : '-')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Description:</td>
                                        <td class="print-value">${this.escapeHtml(trans.description || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Category:</td>
                                        <td class="print-value">${this.escapeHtml(trans.category || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Status:</td>
                                        <td class="print-value">${trans.status || 'Posted'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Created By:</td>
                                        <td class="print-value">${this.escapeHtml(trans.created_by_name || 'System')}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Created At:</td>
                                        <td class="print-value">${trans.created_at || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <td class="print-label">Updated At:</td>
                                        <td class="print-value">${trans.updated_at && trans.updated_at !== trans.created_at ? trans.updated_at : (trans.updated_at || trans.created_at || 'N/A')}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="print-footer">
                                <p>This is a system-generated transaction receipt.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.addEventListener('load', function() {
                    printWindow.print();
                    printWindow.addEventListener('afterprint', function() {
                        printWindow.close();
                    });
                });
            }
        } catch (error) {
            this.showToast(error && error.message ? error.message : 'Error loading transaction details', 'error');
        }
    }

ProfessionalAccounting.prototype.openTransactionsModal = function() {
        const modal = document.getElementById('transactionsModal');
        if (modal) {
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
            this.transactionsCurrentPage = 1;
            
            // Reset filters to defaults
            const entriesPerPage = document.getElementById('entriesPerPage');
            const entityTypeFilter = document.getElementById('modalEntityTypeFilter');
            const entityFilter = document.getElementById('modalEntityFilter');
            
            if (entriesPerPage) entriesPerPage.value = '5';
            if (entityTypeFilter) entityTypeFilter.value = '';
            
            // Clear entity filter dropdown completely
            if (entityFilter) {
                entityFilter.innerHTML = '<option value="">All</option>';
                entityFilter.value = '';
            }
            
            this.transactionsPerPage = 5;
            this.loadModalTransactions();
            
            // Load entities after a short delay to ensure modal is fully rendered
            setTimeout(() => {
                this.loadEntitiesForModal();
            }, 100);
        }
    }

ProfessionalAccounting.prototype.closeTransactionsModal = function() {
        const modal = document.getElementById('transactionsModal');
        if (modal) {
            modal.classList.add('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-visible');
        }
    }

    // Follow-ups Functions
ProfessionalAccounting.prototype.ensureModalsExist = function() {
        // Check if followupModal exists
        let followupModal = document.getElementById('followupModal');
        if (!followupModal) {
            followupModal = this.createFollowupModal();
            document.body.appendChild(followupModal);
        }
        
        // Check if messagesModal exists
        let messagesModal = document.getElementById('messagesModal');
        if (!messagesModal) {
            messagesModal = this.createMessagesModal();
            document.body.appendChild(messagesModal);
        }
    }
    
ProfessionalAccounting.prototype.createFollowupModal = function() {
        const modal = document.createElement('div');
        modal.id = 'followupModal';
        modal.className = 'accounting-modal accounting-modal-overlay accounting-modal-hidden';
        modal.innerHTML = `
            <div class="accounting-modal-content followup-modal">
                <div class="modal-header">
                    <h2><i class="fas fa-tasks"></i> Follow-up Tasks</h2>
                    <button class="modal-close" data-action="close-followup-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="filters-bar filters-bar-compact">
                        <div class="filter-group filter-group-compact">
                            <label>Status:</label>
                            <select id="followupStatusFilter" class="filter-select filter-select-compact">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>Priority:</label>
                            <select id="followupPriorityFilter" class="filter-select filter-select-compact">
                                <option value="">All</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>Type:</label>
                            <select id="followupTypeFilter" class="filter-select filter-select-compact">
                                <option value="">All Types</option>
                                <option value="transaction">Transaction</option>
                                <option value="invoice">Invoice</option>
                                <option value="bill">Bill</option>
                                <option value="journal_entry">Journal Entry</option>
                                <option value="account">Account</option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm" data-action="new-followup">
                            <i class="fas fa-plus"></i> New Follow-up
                        </button>
                        <button class="btn btn-secondary btn-sm" data-action="refresh-followups">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="bulk-actions-bar bulk-actions-bar-hidden" id="followupsBulkActions">
                        <span class="bulk-selected-count" id="bulkSelectedCountFollowups">0 selected</span>
                        <div class="bulk-action-buttons">
                            <button class="btn btn-sm btn-danger" data-action="bulk-delete-followups">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="bulk-export-followups">
                                <i class="fas fa-download"></i> Export Selected
                            </button>
                            <button class="btn btn-sm btn-primary" data-action="bulk-print-followups">
                                <i class="fas fa-print"></i> Print Selected
                            </button>
                        </div>
                    </div>
                    <div class="followups-list" id="followupsList">
                        <div class="loading-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading follow-ups...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }
    
ProfessionalAccounting.prototype.createMessagesModal = function() {
        const modal = document.createElement('div');
        modal.id = 'messagesModal';
        modal.className = 'accounting-modal accounting-modal-overlay accounting-modal-hidden';
        modal.innerHTML = `
            <div class="accounting-modal-content messages-modal">
                <div class="modal-header">
                    <h2>
                        <i class="fas fa-envelope"></i> Messages & Notifications
                        <span id="unreadMessagesBadge" class="badge badge-danger unread-badge">0</span>
                    </h2>
                    <div class="header-actions">
                        <button class="btn btn-primary btn-sm" data-action="new-message">
                            <i class="fas fa-plus"></i> New Message
                        </button>
                        <button class="btn btn-secondary btn-sm" data-action="mark-all-read">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                        <button class="modal-close" data-action="close-messages-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="filters-bar filters-bar-compact">
                        <div class="filter-group filter-group-compact">
                            <label>Type:</label>
                            <select id="messageTypeFilter" class="filter-select filter-select-compact">
                                <option value="">All</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="success">Success</option>
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>Category:</label>
                            <select id="messageCategoryFilter" class="filter-select filter-select-compact">
                                <option value="">All Categories</option>
                                <option value="overdue_invoice">Overdue Invoices</option>
                                <option value="low_balance">Low Balance</option>
                                <option value="transaction_alert">Transaction Alerts</option>
                                <option value="system_notification">System Notifications</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>
                                <input type="checkbox" id="unreadOnlyFilter" class="filter-checkbox">
                                Unread Only
                            </label>
                        </div>
                        <button class="btn btn-secondary btn-sm" data-action="refresh-messages">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="bulk-actions-bar bulk-actions-bar-hidden" id="messagesBulkActions">
                        <span class="bulk-selected-count" id="bulkSelectedCountMessages">0 selected</span>
                        <div class="bulk-action-buttons">
                            <button class="btn btn-sm btn-danger" data-action="bulk-delete-messages">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="bulk-export-messages">
                                <i class="fas fa-download"></i> Export Selected
                            </button>
                            <button class="btn btn-sm btn-primary" data-action="bulk-print-messages">
                                <i class="fas fa-print"></i> Print Selected
                            </button>
                        </div>
                    </div>
                    <div class="messages-list" id="messagesList">
                        <div class="loading-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading messages...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }
    
ProfessionalAccounting.prototype.openFollowupModal = function() {
        // Ensure modals exist before trying to open
        this.ensureModalsExist();
        
        const modal = document.getElementById('followupModal');
        if (!modal) {
            // Wait a bit and try again
            setTimeout(() => {
                const retryModal = document.getElementById('followupModal');
                if (retryModal) {
                    this.openFollowupModal();
                }
            }, 500);
            return;
        }
        
        if (modal) {
            // Remove existing handlers if any
            if (modal._handleClickOutside) {
                modal.removeEventListener('click', modal._handleClickOutside);
            }
            if (modal._handleEscape) {
                document.removeEventListener('keydown', modal._handleEscape);
            }
            
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible', 'show-modal');
            modal.setAttribute('data-modal-visible', 'true');
            this.activeModal = modal;
            
            // Add click-outside-to-close handler
            modal._handleClickOutside = async (e) => {
                // Only close if clicking directly on modal backdrop, not modal content
                if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                    e.stopPropagation();
                    await this.closeModalWithConfirmation(modal);
                }
            };
            modal.addEventListener('click', modal._handleClickOutside);
            
            // Add Escape key handler
            modal._handleEscape = async (e) => {
                if (e.key === 'Escape' && modal.classList.contains('accounting-modal-visible')) {
                    e.preventDefault();
                    await this.closeModalWithConfirmation(modal);
                }
            };
            document.addEventListener('keydown', modal._handleEscape);
            
            // Small delay to ensure modal is visible before loading
            setTimeout(() => {
                this.loadFollowups();
            }, 100);
            // Setup filter listeners
            setTimeout(() => {
                const statusFilter = document.getElementById('followupStatusFilter');
                const priorityFilter = document.getElementById('followupPriorityFilter');
                const typeFilter = document.getElementById('followupTypeFilter');
                if (statusFilter) {
                    statusFilter.removeEventListener('change', this.followupFilterHandler);
                    this.followupFilterHandler = () => this.loadFollowups();
                    statusFilter.addEventListener('change', this.followupFilterHandler);
                }
                if (priorityFilter) {
                    priorityFilter.removeEventListener('change', this.followupFilterHandler);
                    priorityFilter.addEventListener('change', this.followupFilterHandler);
                }
                if (typeFilter) {
                    typeFilter.removeEventListener('change', this.followupFilterHandler);
                    typeFilter.addEventListener('change', this.followupFilterHandler);
                }
            }, 100);
        }
    }

ProfessionalAccounting.prototype.closeFollowupModal = function() {
        const modal = document.getElementById('followupModal');
        if (modal) {
            // Remove event listeners
            if (modal._handleClickOutside) {
                modal.removeEventListener('click', modal._handleClickOutside);
                delete modal._handleClickOutside;
            }
            if (modal._handleEscape) {
                document.removeEventListener('keydown', modal._handleEscape);
                delete modal._handleEscape;
            }
            
            modal.classList.add('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-visible', 'show-modal');
            modal.removeAttribute('data-modal-visible');
            if (this.activeModal === modal) {
                this.activeModal = null;
            }
        }
    }

ProfessionalAccounting.prototype.loadFollowups = async function() {
        try {
            const statusFilter = document.getElementById('followupStatusFilter')?.value || '';
            const priorityFilter = document.getElementById('followupPriorityFilter')?.value || '';
            const typeFilter = document.getElementById('followupTypeFilter')?.value || '';
            const entriesPerPage = parseInt(document.getElementById('followupEntriesPerPage')?.value || '5');
            
            // Update per page if changed
            if (entriesPerPage !== this.followupPerPage) {
                this.followupPerPage = entriesPerPage;
                this.followupCurrentPage = 1; // Reset to first page
            }
            
            let url = `${this.apiBase}/followups.php?`;
            if (statusFilter) url += `status=${encodeURIComponent(statusFilter)}&`;
            if (priorityFilter) url += `priority=${encodeURIComponent(priorityFilter)}&`;
            if (typeFilter) url += `related_type=${encodeURIComponent(typeFilter)}&`;
            
            const response = await fetch(url, {
                cache: 'no-store',
                headers: { 'Cache-Control': 'no-cache' }
            });
            
            if (!response.ok) {
                if (response.status === 503) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar first.');
                }
                const errorText = await response.text().catch(() => 'Unknown error');
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            
            const data = await response.json();
            
            const listContainer = document.getElementById('followupsList');
            if (!listContainer) {
                this.showToast('Error: Follow-ups container not found', 'error');
                return;
            }
            
            if (data.success && Array.isArray(data.followups)) {
                // Store all followups
                this.allFollowups = data.followups;
                this.followupTotalCount = data.followups.length;
                
                // Calculate pagination
                this.followupTotalPages = Math.max(1, Math.ceil(this.followupTotalCount / this.followupPerPage));
                if (this.followupCurrentPage > this.followupTotalPages) {
                    this.followupCurrentPage = this.followupTotalPages;
                }
                
                // Get paginated subset
                const startIndex = (this.followupCurrentPage - 1) * this.followupPerPage;
                const endIndex = startIndex + this.followupPerPage;
                const paginatedFollowups = data.followups.slice(startIndex, endIndex);
                
                if (paginatedFollowups.length === 0 && this.followupTotalCount === 0) {
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-tasks empty-state-icon"></i>
                            <p class="empty-state-text large">No follow-ups found</p>
                            <p class="empty-state-hint">Click "New Follow-up" button above to create your first task</p>
                        </div>
                    `;
                    this.updateFollowupPagination();
                    return;
                }
                
                let html = '<div class="followup-items-container">';
                html += `
                    <div class="bulk-select-header">
                        <label class="bulk-select-all-label">
                            <input type="checkbox" id="bulkSelectAllFollowups" data-action="bulk-select-all-followups">
                            Select All
                        </label>
                    </div>
                `;
                paginatedFollowups.forEach(followup => {
                    const priorityColors = {
                        'urgent': '#ef4444',
                        'high': '#f59e0b',
                        'medium': '#3b82f6',
                        'low': '#6b7280'
                    };
                    const statusColors = {
                        'pending': '#f59e0b',
                        'in_progress': '#3b82f6',
                        'completed': '#10b981',
                        'cancelled': '#6b7280'
                    };
                    const priorityColor = priorityColors[followup.priority] || '#6b7280';
                    const statusColor = statusColors[followup.status] || '#6b7280';
                    const dueDate = followup.due_date ? new Date(followup.due_date) : null;
                    const isOverdue = dueDate && dueDate < new Date() && followup.status !== 'completed';
                    
                    const overdueClass = isOverdue ? ' overdue' : '';
                    html += `
                        <div class="followup-item${overdueClass}" data-id="${followup.id}" data-priority="${followup.priority}">
                            <div class="followup-item-checkbox">
                                <input type="checkbox" class="followup-checkbox" data-id="${followup.id}" data-action="select-followup">
                            </div>
                            <div class="followup-item-header">
                                <div class="followup-item-content">
                                    <h4 class="followup-item-title">
                                        ${this.escapeHtml(followup.title)}
                                    </h4>
                                    ${followup.description ? `
                                        <p class="followup-item-description">
                                            ${this.escapeHtml(followup.description.substring(0, 100))}${followup.description.length > 100 ? '...' : ''}
                                        </p>
                                    ` : ''}
                                </div>
                                <div class="followup-item-badges">
                                    <span class="priority-badge ${followup.priority}">
                                        ${followup.priority}
                                    </span>
                                    <span class="status-badge ${followup.status}">
                                        ${followup.status.replace('_', ' ')}
                                    </span>
                                </div>
                            </div>
                            <div class="followup-item-meta">
                                <div class="followup-item-meta-info">
                                    <span><i class="fas fa-tag"></i> ${followup.related_type}</span>
                                    ${dueDate ? `
                                        <span class="due-date${isOverdue ? ' overdue' : ''}">
                                            <i class="fas fa-calendar"></i> Due: ${this.formatDate(dueDate)}
                                            ${isOverdue ? ' (Overdue)' : ''}
                                        </span>
                                    ` : ''}
                                    ${followup.assigned_to_name ? `
                                        <span><i class="fas fa-user"></i> ${this.escapeHtml(followup.assigned_to_name)}</span>
                                    ` : ''}
                                </div>
                                <div class="followup-item-actions">
                                    ${followup.status !== 'completed' ? `
                                        <button class="btn btn-sm btn-success" data-action="complete-followup" data-id="${followup.id}" title="Complete">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    ` : ''}
                                    <button class="btn btn-sm btn-info" data-action="view-followup" data-id="${followup.id}" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary" data-action="edit-followup" data-id="${followup.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" data-action="print-followup" data-id="${followup.id}" title="Print">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-action="duplicate-followup" data-id="${followup.id}" title="Duplicate">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" data-action="export-followup" data-id="${followup.id}" title="Export">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-action="delete-followup" data-id="${followup.id}" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                listContainer.innerHTML = html;
                
                // Update pagination controls
                this.updateFollowupPagination();
                
                // Setup bulk action handlers and event delegation for delete buttons
                setTimeout(() => {
                    const selectAllCheckbox = document.getElementById('bulkSelectAllFollowups');
                    const followupCheckboxes = document.querySelectorAll('.followup-checkbox');
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.removeEventListener('change', this._bulkSelectAllFollowupsHandler);
                        this._bulkSelectAllFollowupsHandler = (e) => {
                            const checked = e.target.checked;
                            followupCheckboxes.forEach(cb => {
                                cb.checked = checked;
                            });
                            this.updateBulkActionsFollowups();
                        };
                        selectAllCheckbox.addEventListener('change', this._bulkSelectAllFollowupsHandler);
                    }
                    
                    followupCheckboxes.forEach(checkbox => {
                        checkbox.removeEventListener('change', this._followupCheckboxHandler);
                        this._followupCheckboxHandler = () => {
                            this.updateBulkActionsFollowups();
                        };
                        checkbox.addEventListener('change', this._followupCheckboxHandler);
                    });
                    
                    // Use event delegation on the container for delete buttons
                    listContainer.removeEventListener('click', this._followupsContainerClickHandler);
                    const self = this;
                    this._followupsContainerClickHandler = (e) => {
                        const deleteBtn = e.target.closest('button[data-action="delete-followup"]');
                        if (deleteBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            const followupId = deleteBtn.getAttribute('data-id');
                            if (followupId) {
                                self.deleteFollowup(followupId);
                            }
                        }
                    };
                    listContainer.addEventListener('click', this._followupsContainerClickHandler, true);
                }, 100);
            } else {
                let errorMessage = data.message || 'Unknown error';
                if (data.message && (data.message.includes('table not found') || data.message.includes('503'))) {
                    errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                }
                listContainer.innerHTML = `
                    <div class="empty-state-container">
                        <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                        <p class="empty-state-text">
                            Error loading follow-ups: ${errorMessage}
                        </p>
                        ${errorMessage.includes('table not found') || errorMessage.includes('503') ? `
                            <div class="setup-button-container">
                                <button class="btn btn-primary" data-action="setup-followup-messages">
                                    <i class="fas fa-database"></i> Setup Database Tables
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
        } catch (error) {
            const listContainer = document.getElementById('followupsList');
            if (listContainer) {
                let errorMessage = error.message;
                if (error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found')) {
                    errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                }
                listContainer.innerHTML = `
                    <div class="empty-state-container">
                        <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                        <p class="empty-state-text">
                            Error: ${errorMessage}
                        </p>
                        ${error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found') ? `
                            <div class="setup-button-container">
                                <button class="btn btn-primary" data-action="setup-followup-messages">
                                    <i class="fas fa-database"></i> Setup Database Tables
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            } else {
                this.showToast('Error: Follow-ups container not found', 'error');
            }
        }
    }

    // Messages Functions
ProfessionalAccounting.prototype.openMessagesModal = function() {
        // Ensure modals exist before trying to open
        this.ensureModalsExist();
        
        const modal = document.getElementById('messagesModal');
        if (modal) {
            // Remove existing handlers if any
            if (modal._handleClickOutside) {
                modal.removeEventListener('click', modal._handleClickOutside);
            }
            if (modal._handleEscape) {
                document.removeEventListener('keydown', modal._handleEscape);
            }
            
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible', 'show-modal');
            modal.setAttribute('data-modal-visible', 'true');
            this.activeModal = modal;
            
            // Add click-outside-to-close handler
            modal._handleClickOutside = async (e) => {
                // Don't handle if clicking on action buttons - let them bubble up
                if (e.target.closest('[data-action]')) {
                    return;
                }
                // Only close if clicking directly on modal backdrop, not modal content
                if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                    e.stopPropagation();
                    await this.closeModalWithConfirmation(modal);
                }
            };
            modal.addEventListener('click', modal._handleClickOutside);
            
            // Add Escape key handler
            modal._handleEscape = async (e) => {
                if (e.key === 'Escape' && modal.classList.contains('accounting-modal-visible')) {
                    e.preventDefault();
                    await this.closeModalWithConfirmation(modal);
                }
            };
            document.addEventListener('keydown', modal._handleEscape);
            
            this.loadMessages();
            // Setup filter listeners
            setTimeout(() => {
                const typeFilter = document.getElementById('messageTypeFilter');
                const categoryFilter = document.getElementById('messageCategoryFilter');
                const unreadOnlyFilter = document.getElementById('unreadOnlyFilter');
                if (typeFilter) {
                    typeFilter.removeEventListener('change', this.messageFilterHandler);
                    this.messageFilterHandler = () => {
                        this.messageCurrentPage = 1;
                        this.loadMessages();
                    };
                    typeFilter.addEventListener('change', this.messageFilterHandler);
                }
                if (categoryFilter) {
                    categoryFilter.removeEventListener('change', this.messageFilterHandler);
                    categoryFilter.addEventListener('change', this.messageFilterHandler);
                }
                if (unreadOnlyFilter) {
                    unreadOnlyFilter.removeEventListener('change', this.messageFilterHandler);
                    unreadOnlyFilter.addEventListener('change', this.messageFilterHandler);
                }
                const entriesPerPage = document.getElementById('messageEntriesPerPage');
                if (entriesPerPage) {
                    entriesPerPage.removeEventListener('change', this.messageFilterHandler);
                    entriesPerPage.addEventListener('change', this.messageFilterHandler);
                }
            }, 100);
        }
    }

ProfessionalAccounting.prototype.closeMessagesModal = function() {
        const modal = document.getElementById('messagesModal');
        if (modal) {
            // Remove event listeners
            if (modal._handleClickOutside) {
                modal.removeEventListener('click', modal._handleClickOutside);
                delete modal._handleClickOutside;
            }
            if (modal._handleEscape) {
                document.removeEventListener('keydown', modal._handleEscape);
                delete modal._handleEscape;
            }
            
            modal.classList.add('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-visible', 'show-modal');
            modal.removeAttribute('data-modal-visible');
            if (this.activeModal === modal) {
                this.activeModal = null;
            }
        }
    }

ProfessionalAccounting.prototype.loadMessages = async function() {
        try {
            const typeFilter = document.getElementById('messageTypeFilter')?.value || '';
            const categoryFilter = document.getElementById('messageCategoryFilter')?.value || '';
            const unreadOnly = document.getElementById('unreadOnlyFilter')?.checked || false;
            const entriesPerPage = parseInt(document.getElementById('messageEntriesPerPage')?.value || '5');
            
            // Update per page if changed
            if (entriesPerPage !== this.messagePerPage) {
                this.messagePerPage = entriesPerPage;
                this.messageCurrentPage = 1; // Reset to first page
            }
            
            let url = `${this.apiBase}/messages.php?`;
            if (typeFilter) url += `type=${encodeURIComponent(typeFilter)}&`;
            if (categoryFilter) url += `category=${encodeURIComponent(categoryFilter)}&`;
            if (unreadOnly) url += `unread_only=true&`;
            
            const response = await fetch(url, {
                cache: 'no-store',
                headers: { 'Cache-Control': 'no-cache' }
            });
            
            if (!response.ok) {
                if (response.status === 503) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar first.');
                }
                const errorText = await response.text().catch(() => 'Unknown error');
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            
            const data = await response.json();
            const listContainer = document.getElementById('messagesList');
            const unreadBadge = document.getElementById('unreadMessagesBadge');
            
            if (!listContainer) return;
            
            // Update unread badge
            if (unreadBadge && data.unread_count !== undefined) {
                if (data.unread_count > 0) {
                    unreadBadge.textContent = data.unread_count;
                    unreadBadge.classList.add('visible-inline-block');
                    unreadBadge.classList.remove('hidden');
                } else {
                    unreadBadge.classList.add('hidden');
                    unreadBadge.classList.remove('visible-inline-block');
                }
            }
            
            if (data.success && data.messages) {
                // Store all messages
                this.allMessages = data.messages;
                this.messageTotalCount = data.messages.length;
                
                // Calculate pagination
                this.messageTotalPages = Math.max(1, Math.ceil(this.messageTotalCount / this.messagePerPage));
                if (this.messageCurrentPage > this.messageTotalPages) {
                    this.messageCurrentPage = this.messageTotalPages;
                }
                
                // Get paginated subset
                const startIndex = (this.messageCurrentPage - 1) * this.messagePerPage;
                const endIndex = startIndex + this.messagePerPage;
                const paginatedMessages = data.messages.slice(startIndex, endIndex);
                
                if (paginatedMessages.length === 0 && this.messageTotalCount === 0) {
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-envelope-open empty-state-icon"></i>
                            <p class="empty-state-text">No messages found</p>
                        </div>
                    `;
                    this.updateMessagePagination();
                    return;
                }
                
                const typeColors = {
                    'info': '#3b82f6',
                    'warning': '#f59e0b',
                    'error': '#ef4444',
                    'success': '#10b981',
                    'alert': '#8b5cf6'
                };
                
                let html = '<div class="message-items-container">';
                html += `
                    <div class="bulk-select-header">
                        <label class="bulk-select-all-label">
                            <input type="checkbox" id="bulkSelectAllMessages" data-action="bulk-select-all-messages">
                            Select All
                        </label>
                    </div>
                `;
                paginatedMessages.forEach(msg => {
                    const isRead = msg.is_read_by_user === 1;
                    const createdAt = new Date(msg.created_at);
                    const readClass = isRead ? '' : ' unread';
                    
                    html += `
                        <div class="message-item${readClass}" data-id="${msg.id}" data-type="${msg.type}">
                            <div class="message-item-checkbox">
                                <input type="checkbox" class="message-checkbox" data-id="${msg.id}" data-action="select-message">
                            </div>
                            <div class="message-item-header">
                                <div class="message-item-content">
                                    <div class="message-item-title-wrapper">
                                        <h4 class="message-item-title">
                                            ${!isRead ? '<i class="fas fa-circle unread-indicator"></i> ' : ''}
                                            ${this.escapeHtml(msg.title)}
                                        </h4>
                                        ${msg.is_important ? `
                                            <span class="important-badge">
                                                IMPORTANT
                                            </span>
                                        ` : ''}
                                    </div>
                                    <p class="message-item-body">
                                        ${this.escapeHtml(msg.message)}
                                    </p>
                                </div>
                                <div class="message-item-badges">
                                    <span class="type-badge type-${msg.type}">
                                        ${msg.type}
                                    </span>
                                </div>
                            </div>
                            <div class="message-item-meta">
                                <div class="message-item-meta-info">
                                    <span><i class="fas fa-tag"></i> ${msg.category.replace('_', ' ')}</span>
                                    <span><i class="fas fa-clock"></i> ${this.formatDate(createdAt)}</span>
                                    ${msg.related_type ? `
                                        <span><i class="fas fa-link"></i> ${msg.related_type}</span>
                                    ` : ''}
                                </div>
                                <div class="message-item-actions">
                                    ${!isRead ? `
                                        <button class="btn btn-sm btn-success" data-action="mark-message-read" data-id="${msg.id}" title="Mark Read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    ` : ''}
                                    <button class="btn btn-sm btn-info" data-action="view-message" data-id="${msg.id}" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ${msg.action_url ? `
                                        <a href="${msg.action_url}" class="btn btn-sm btn-success" title="Open Link">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    ` : ''}
                                    <button class="btn btn-sm btn-secondary" data-action="print-message" data-id="${msg.id}" title="Print">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-action="duplicate-message" data-id="${msg.id}" title="Duplicate">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" data-action="export-message" data-id="${msg.id}" title="Export">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-action="delete-message" data-id="${msg.id}" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                listContainer.innerHTML = html;
                
                // Update pagination controls
                this.updateMessagePagination();
                
                // Setup bulk action handlers and direct delete handlers after rendering
                setTimeout(() => {
                    const selectAllCheckbox = document.getElementById('bulkSelectAllMessages');
                    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.removeEventListener('change', this._bulkSelectAllMessagesHandler);
                        this._bulkSelectAllMessagesHandler = (e) => {
                            const checked = e.target.checked;
                            messageCheckboxes.forEach(cb => {
                                cb.checked = checked;
                            });
                            this.updateBulkActionsMessages();
                        };
                        selectAllCheckbox.addEventListener('change', this._bulkSelectAllMessagesHandler);
                    }
                    
                    messageCheckboxes.forEach(checkbox => {
                        checkbox.removeEventListener('change', this._messageCheckboxHandler);
                        this._messageCheckboxHandler = () => {
                            this.updateBulkActionsMessages();
                        };
                        checkbox.addEventListener('change', this._messageCheckboxHandler);
                    });
                    
                    // Use event delegation on the container for delete buttons
                    listContainer.removeEventListener('click', this._messagesContainerClickHandler);
                    const self = this;
                    this._messagesContainerClickHandler = (e) => {
                        const deleteBtn = e.target.closest('button[data-action="delete-message"]');
                        if (deleteBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            const messageId = deleteBtn.getAttribute('data-id');
                            if (messageId) {
                                self.deleteMessage(messageId);
                            }
                        }
                    };
                    listContainer.addEventListener('click', this._messagesContainerClickHandler, true);
                }, 100);
            } else {
                let errorMessage = data.message || 'Unknown error';
                if (data.message && (data.message.includes('table not found') || data.message.includes('503'))) {
                    errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                }
                listContainer.innerHTML = `
                    <div class="empty-state-container">
                        <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                        <p class="empty-state-text">
                            Error loading messages: ${errorMessage}
                        </p>
                        ${errorMessage.includes('table not found') || errorMessage.includes('503') ? `
                            <div class="setup-button-container">
                                <button class="btn btn-primary" data-action="setup-followup-messages">
                                    <i class="fas fa-database"></i> Setup Database Tables
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
        } catch (error) {
            const listContainer = document.getElementById('messagesList');
            if (listContainer) {
                let errorMessage = error.message;
                if (error.message.includes('503') || error.message.includes('Service Unavailable')) {
                    errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                }
                listContainer.innerHTML = `
                    <div class="empty-state-container">
                        <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                        <p class="empty-state-text">
                            Error: ${errorMessage}
                        </p>
                        ${error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found') ? `
                            <div class="setup-button-container">
                                <button class="btn btn-primary" data-action="setup-followup-messages">
                                    <i class="fas fa-database"></i> Setup Database Tables
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
        }
    }

    // Follow-up Action Functions
ProfessionalAccounting.prototype.completeFollowup = async function(followupId) {
        try {
            const response = await fetch(`${this.apiBase}/followups.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: parseInt(followupId),
                    status: 'completed'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast('Follow-up marked as completed!', 'success');
                this.loadFollowups(); // Refresh the list
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to complete follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error completing follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.editFollowup = async function(followupId) {
        try {
            // Fetch the follow-up data
            const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
            const data = await response.json();
            
            if (!data.success || !data.followup) {
                this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
                return;
            }
            
            const followup = data.followup;
            
            // Populate the form fields
            document.getElementById('editFollowupId').value = followup.id;
            document.getElementById('editFollowupTitle').value = followup.title || '';
            document.getElementById('editFollowupDescription').value = followup.description || '';
            document.getElementById('editFollowupRelatedType').value = followup.related_type || '';
            document.getElementById('editFollowupRelatedId').value = followup.related_id || 0;
            document.getElementById('editFollowupDueDate').value = followup.due_date ? followup.due_date.split(' ')[0] : '';
            document.getElementById('editFollowupPriority').value = followup.priority || 'medium';
            document.getElementById('editFollowupStatus').value = followup.status || 'pending';
            document.getElementById('editFollowupNotes').value = followup.notes || '';
            
            // Show the modal
            this.showEditFollowupForm();
        } catch (error) {
            this.showToast('Error loading follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.showEditFollowupForm = function() {
        const modal = document.getElementById('editFollowupModal');
        if (modal) {
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
        }
    }

ProfessionalAccounting.prototype.closeEditFollowupForm = function() {
        const modal = document.getElementById('editFollowupModal');
        if (modal) {
            modal.classList.add('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-visible');
            // Reset form
            document.getElementById('editFollowupForm')?.reset();
        }
    }

ProfessionalAccounting.prototype.saveEditFollowup = async function(formData) {
        try {
            const response = await fetch(`${this.apiBase}/followups.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast('Follow-up updated successfully!', 'success');
                this.closeEditFollowupForm();
                this.loadFollowups(); // Refresh the list
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to update follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error saving follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteFollowup = async function(followupId) {
        try {
            const followupIdInt = parseInt(followupId);
            if (!followupIdInt || isNaN(followupIdInt)) {
                this.showToast('Invalid follow-up ID', 'error');
                return;
            }
            
            const response = await fetch(`${this.apiBase}/followups.php?id=${followupIdInt}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                const errorText = await response.text().catch(() => 'Unknown error');
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const data = await response.json();
            if (data.success) {
                this.showToast('Follow-up deleted successfully!', 'success');
                this.loadFollowups(); // Refresh the list
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to delete follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error deleting follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.viewFollowup = async function(followupId) {
        // Open edit modal in view mode
        await this.editFollowup(followupId);
        // You can add view-only mode logic here if needed
    }

ProfessionalAccounting.prototype.printFollowup = async function(followupId) {
        try {
            const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
            const data = await response.json();
            
            if (data.success && data.followup) {
                const followup = data.followup;
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                if (!printWindow) {
                    this.showToast('Please allow popups to print follow-up', 'warning');
                    return;
                }
                
                const dueDate = followup.due_date ? this.formatDate(followup.due_date) : 'N/A';
                const createdAt = followup.created_at ? this.formatDate(followup.created_at) : 'N/A';
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Follow-up Task</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            h2 { color: #333; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            td { padding: 8px; border-bottom: 1px solid #ddd; }
                            .label { font-weight: bold; width: 150px; }
                            .value { }
                        </style>
                    </head>
                    <body>
                        <h2>Follow-up Task</h2>
                        <table>
                            <tr><td class="label">Title:</td><td class="value">${this.escapeHtml(followup.title || 'N/A')}</td></tr>
                            <tr><td class="label">Description:</td><td class="value">${this.escapeHtml(followup.description || 'N/A')}</td></tr>
                            <tr><td class="label">Status:</td><td class="value">${followup.status || 'N/A'}</td></tr>
                            <tr><td class="label">Priority:</td><td class="value">${followup.priority || 'N/A'}</td></tr>
                            <tr><td class="label">Due Date:</td><td class="value">${dueDate}</td></tr>
                            <tr><td class="label">Related Type:</td><td class="value">${followup.related_type || 'N/A'}</td></tr>
                            <tr><td class="label">Related ID:</td><td class="value">${followup.related_id || 'N/A'}</td></tr>
                            <tr><td class="label">Created:</td><td class="value">${createdAt}</td></tr>
                            ${followup.notes ? `<tr><td class="label">Notes:</td><td class="value">${this.escapeHtml(followup.notes)}</td></tr>` : ''}
                        </table>
                        <p class="print-footer">Printed: ${new Date().toLocaleString()}</p>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                setTimeout(() => {
                    printWindow.print();
                }, 250);
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error printing follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.duplicateFollowup = async function(followupId) {
        try {
            const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
            const data = await response.json();
            
            if (data.success && data.followup) {
                const followup = data.followup;
                // Open new followup form with duplicated data
                this.showNewFollowupForm();
                setTimeout(() => {
                    document.getElementById('followupTitle').value = followup.title + ' (Copy)';
                    document.getElementById('followupDescription').value = followup.description || '';
                    document.getElementById('followupPriority').value = followup.priority || 'medium';
                    document.getElementById('followupStatus').value = 'pending';
                    document.getElementById('followupType').value = followup.related_type || '';
                    if (followup.due_date) {
                        document.getElementById('followupDueDate').value = followup.due_date.split(' ')[0];
                    }
                }, 100);
                this.showToast('Follow-up data loaded for duplication', 'success');
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error duplicating follow-up: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportFollowup = async function(followupId) {
        try {
            const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
            const data = await response.json();
            
            if (data.success && data.followup) {
                const followup = data.followup;
                const jsonData = JSON.stringify(followup, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `followup-${followup.id}-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast('Follow-up exported successfully', 'success');
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
            }
        } catch (error) {
            this.showToast('Error exporting follow-up: ' + error.message, 'error');
        }
    }

    // Missing Methods Implementation
    
ProfessionalAccounting.prototype.loadModalTransactions = async function() {
        const tbody = document.getElementById('modalEntityTransactionsTable')?.querySelector('tbody');
        if (!tbody) return;

        try {
            // Show loading state
            tbody.innerHTML = `
                <tr>
                    <td colspan="17" class="loading-row">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading transactions...</span>
                        </div>
                    </td>
                </tr>
            `;

            // Get filters from modal
            const entityTypeFilter = document.getElementById('modalEntityTypeFilter');
            const entityFilter = document.getElementById('modalEntityFilter');
            const searchInput = document.getElementById('modalLedgerSearch');
            const entriesPerPage = document.getElementById('entriesPerPage');
            
            const entityType = entityTypeFilter ? entityTypeFilter.value : '';
            const entityId = entityFilter ? entityFilter.value : '';
            const search = searchInput ? searchInput.value.trim() : '';
            const perPage = entriesPerPage ? parseInt(entriesPerPage.value) : this.transactionsPerPage;

            // Build API URL
            let url = `${this.apiBase}/transactions.php?limit=${perPage}&page=${this.transactionsCurrentPage}&_t=${Date.now()}`;
            if (entityType) url += `&entity_type=${encodeURIComponent(entityType)}`;
            if (entityId) url += `&entity_id=${encodeURIComponent(entityId)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;

            const response = await fetch(url, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            
            const data = await response.json();

            if (!data.success || !data.transactions) {
                throw new Error(data.message || 'Failed to load transactions');
            }

            // Update pagination
            this.transactionsTotalCount = data.total || data.transactions.length;
            this.transactionsTotalPages = Math.ceil(this.transactionsTotalCount / perPage);
            this.transactionsPerPage = perPage;

            // Render transactions
            if (data.transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="17" class="empty-row">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No transactions found</p>
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = data.transactions.map(trans => {
                    const entityTypeName = trans.entity_type ? trans.entity_type.charAt(0).toUpperCase() + trans.entity_type.slice(1) : 'N/A';
                    const statusBadge = trans.status === 'posted' ? 'badge-success' : 'badge-warning';
                    return `
                        <tr>
                            <td>${trans.id || ''}</td>
                            <td>${trans.transaction_date || ''}</td>
                            <td>${this.escapeHtml(trans.entity_name || 'N/A')}</td>
                            <td><span class="badge badge-info">${entityTypeName}</span></td>
                            <td>${this.escapeHtml(trans.reference_number || '')}</td>
                            <td>${this.escapeHtml(trans.description || '')}</td>
                            <td class="text-right">${this.formatCurrency(trans.debit || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                            <td class="text-right">${this.formatCurrency(trans.credit || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                            <td><span class="badge ${statusBadge}">${trans.status || 'draft'}</span></td>
                            <td class="checkbox-col">
                                <input type="checkbox" class="checkbox-modern" data-id="${trans.id}">
                            </td>
                            <td class="actions-col">
                                <button class="btn-icon" data-action="view-transaction" data-id="${trans.id}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" data-action="edit-transaction" data-id="${trans.id}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Update pagination controls
            this.updateModalTransactionsPagination();
        } catch (error) {
            const tbody = document.getElementById('modalEntityTransactionsTable')?.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="17" class="error-row">
                            <div class="error-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Error loading transactions: ${this.escapeHtml(error.message)}</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            console.error('Error loading modal transactions:', error);
        }
    }

ProfessionalAccounting.prototype.updateModalTransactionsPagination = function() {
        const paginationTop = document.getElementById('modalLedgerPaginationTop');
        const paginationInfo = document.getElementById('modalLedgerPaginationInfoTop');
        
        if (paginationInfo) {
            const start = (this.transactionsCurrentPage - 1) * this.transactionsPerPage + 1;
            const end = Math.min(this.transactionsCurrentPage * this.transactionsPerPage, this.transactionsTotalCount);
            paginationInfo.textContent = `Showing ${start} to ${end} of ${this.transactionsTotalCount} entries`;
        }

        if (paginationTop) {
            let html = '<div class="pagination-controls">';
            
            // Previous button
            html += `<button class="btn btn-sm ${this.transactionsCurrentPage <= 1 ? 'disabled' : ''}" 
                     data-action="modal-transactions-prev" ${this.transactionsCurrentPage <= 1 ? 'disabled' : ''}>
                     <i class="fas fa-chevron-left"></i> Previous</button>`;
            
            // Page numbers
            const maxPages = Math.min(this.transactionsTotalPages, 10);
            const startPage = Math.max(1, this.transactionsCurrentPage - 5);
            const endPage = Math.min(this.transactionsTotalPages, startPage + 9);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="btn btn-sm ${i === this.transactionsCurrentPage ? 'btn-primary' : ''}" 
                         data-action="modal-transactions-page" data-page="${i}">${i}</button>`;
            }
            
            // Next button
            html += `<button class="btn btn-sm ${this.transactionsCurrentPage >= this.transactionsTotalPages ? 'disabled' : ''}" 
                     data-action="modal-transactions-next" ${this.transactionsCurrentPage >= this.transactionsTotalPages ? 'disabled' : ''}>
                     Next <i class="fas fa-chevron-right"></i></button>`;
            
            html += '</div>';
            paginationTop.innerHTML = html;
        }
    }

ProfessionalAccounting.prototype.openVouchersModal = function(mode) {
        this.vouchersModalMode = (mode === 'expenses') ? 'expenses' : 'all';
        const isExpensesOnly = this.vouchersModalMode === 'expenses';
        const modalTitle = isExpensesOnly ? 'Expenses (Payment Vouchers)' : 'Vouchers';
        const modal = document.getElementById('vouchersModal');
        if (modal) {
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
            this.activeModal = modal;
            const titleEl = modal.querySelector('.accounting-modal-header h3');
            if (titleEl) titleEl.textContent = modalTitle;
            const receiptBtn = modal.querySelector('[data-action="new-receipt-voucher"]');
            if (receiptBtn) receiptBtn.style.display = isExpensesOnly ? 'none' : '';
            setTimeout(() => {
                this.loadVouchers();
                this.setupVouchersHandlers();
            }, 100);
        } else {
            const receiptButtonHtml = isExpensesOnly ? '' : `
                            <button class="btn btn-primary btn-sm" data-action="new-receipt-voucher">
                                <i class="fas fa-plus"></i> New Receipt Voucher
                            </button>`;
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="filters-bar filters-bar-compact">
                            <button class="btn btn-primary btn-sm" data-action="new-payment-voucher">
                                <i class="fas fa-plus"></i> New Payment Voucher
                            </button>
                            ${receiptButtonHtml}
                            <button class="btn btn-secondary btn-sm" data-action="refresh-vouchers">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                            </div>
                        <div class="table-wrapper-modern">
                            <table class="table-modern" id="vouchersTable">
                                <thead>
                                    <tr>
                                        <th>VOUCHER #</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="vouchersTableBody">
                                    <tr>
                                        <td colspan="7" class="loading-row">
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <span>Loading vouchers...</span>
                        </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                `;
            this.showModal(modalTitle, content, 'large', 'vouchersModal');
            setTimeout(() => {
                this.loadVouchers();
                this.setupVouchersHandlers();
            }, 100);
        }
    }

ProfessionalAccounting.prototype.openSupportPaymentsModal = function() {
        const modal = document.getElementById('supportPaymentsModal');
        if (modal) {
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
            this.activeModal = modal;
            setTimeout(() => {
                this.loadSupportPayments();
                this.setupSupportPaymentsHandlers();
            }, 100);
        } else {
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="filters-bar filters-bar-compact">
                            <button class="btn btn-primary btn-sm" data-action="new-payment-voucher">
                                <i class="fas fa-plus"></i> New Payment Voucher
                            </button>
                            <button class="btn btn-secondary btn-sm" data-action="refresh-support-payments">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                        <div class="table-wrapper-modern">
                            <table class="table-modern" id="supportPaymentsTable">
                                <thead>
                                    <tr>
                                        <th>VOUCHER #</th>
                                        <th>DATE</th>
                                        <th>TYPE</th>
                                        <th>REFERENCE</th>
                                        <th>AMOUNT</th>
                                        <th>STATUS</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody id="supportPaymentsTableBody">
                                    <tr>
                                        <td colspan="7" class="loading-row">
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <span>Loading support payments...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Support Payments', content, 'large', 'supportPaymentsModal');
            setTimeout(() => {
                this.loadSupportPayments();
                this.setupSupportPaymentsHandlers();
            }, 100);
        }
    }

ProfessionalAccounting.prototype.loadSupportPayments = async function() {
        const tbody = document.getElementById('supportPaymentsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="loading-row"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading...</span></div></td></tr>';
        try {
            const response = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=payment`, { credentials: 'include' });
            const data = await response.json().catch(function() { return { success: false, message: 'Invalid response' }; });
            if (!response.ok) {
                throw new Error((data && data.message) || (data && data.error) || 'HTTP ' + response.status);
            }
            const vouchers = (data && data.success && Array.isArray(data.vouchers)) ? data.vouchers : [];
            if (vouchers.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-hand-holding-usd"></i>
                                <p>No support payments found</p>
                                <button class="btn btn-primary btn-sm mt-2" data-action="new-payment-voucher">
                                    <i class="fas fa-plus"></i> Add Payment Voucher
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = vouchers.map((v) => `
                    <tr>
                        <td>${this.escapeHtml(v.voucher_number || v.reference_number || 'N/A')}</td>
                        <td>${v.voucher_date || v.payment_date || 'N/A'}</td>
                        <td><span class="badge badge-danger">Payment</span></td>
                        <td>${this.escapeHtml(v.reference_number || '-')}</td>
                        <td>${this.formatCurrency(parseFloat(v.amount) || 0, v.currency || 'SAR')}</td>
                        <td>
                            <span class="badge badge-${(v.status || 'Draft') === 'Draft' ? 'secondary' : (v.status === 'Cleared' || v.status === 'Deposited' || v.status === 'Posted') ? 'success' : 'warning'}">
                                ${this.escapeHtml(v.status || 'Draft')}
                            </span>
                        </td>
                        <td class="actions-column">
                            <button class="btn btn-sm btn-info" data-action="view-voucher" data-id="${v.id}" data-type="payment" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" data-action="edit-voucher" data-id="${v.id}" data-type="payment" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary" data-action="print-voucher" data-id="${v.id}" data-type="payment" title="Print">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-voucher" data-id="${v.id}" data-type="payment" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading support payments:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle"></i> Error loading support payments: ${this.escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

ProfessionalAccounting.prototype.setupSupportPaymentsHandlers = function() {
        const modal = document.getElementById('supportPaymentsModal');
        if (!modal) return;
        modal.querySelector('[data-action="refresh-support-payments"]')?.addEventListener('click', () => this.loadSupportPayments());
        modal.querySelector('[data-action="new-payment-voucher"]')?.addEventListener('click', () => this.openPaymentVoucherModal());
        modal.addEventListener('click', (e) => {
            const viewBtn = e.target.closest('[data-action="view-voucher"][data-type="payment"]');
            const editBtn = e.target.closest('[data-action="edit-voucher"][data-type="payment"]');
            const printBtn = e.target.closest('[data-action="print-voucher"][data-type="payment"]');
            const delBtn = e.target.closest('[data-action="delete-voucher"][data-type="payment"]');
            if (viewBtn) {
                const id = viewBtn.getAttribute('data-id');
                this.openPaymentVoucherModal(parseInt(id, 10));
            } else if (editBtn) {
                const id = editBtn.getAttribute('data-id');
                this.openPaymentVoucherModal(parseInt(id, 10));
            } else if (printBtn) {
                const id = printBtn.getAttribute('data-id');
                this.printVoucher(id, 'payment');
            } else if (delBtn) {
                const id = delBtn.getAttribute('data-id');
                if (id) {
                    (async () => {
                        const confirmed = typeof this.showConfirmDialog === 'function'
                            ? await this.showConfirmDialog('Delete Payment Voucher', 'Are you sure you want to delete this payment voucher?', 'Delete', 'Cancel', 'danger')
                            : confirm('Delete this payment voucher?');
                        if (confirmed) {
                            this.deleteVoucher(parseInt(id, 10), 'payment');
                            setTimeout(() => this.loadSupportPayments(), 500);
                        }
                    })();
                }
            }
        });
    }

// loadVouchers: defined in professional.management.js only (uses receipt-payment-vouchers.php for save/list consistency)

ProfessionalAccounting.prototype.setupVouchersHandlers = function() {
        const modal = document.getElementById('vouchersModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // Refresh button
        const refreshBtn = modal.querySelector('[data-action="refresh-vouchers"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadVouchers();
            });
        }
        
        // New payment voucher button
        const newPaymentBtn = modal.querySelector('[data-action="new-payment-voucher"]');
        if (newPaymentBtn) {
            newPaymentBtn.addEventListener('click', () => {
                this.openPaymentVoucherModal();
            });
        }
        
        // New receipt voucher button
        const newReceiptBtn = modal.querySelector('[data-action="new-receipt-voucher"]');
        if (newReceiptBtn) {
            newReceiptBtn.addEventListener('click', () => {
                this.openReceiptVoucherModal();
            });
        }
        
        // View voucher buttons
        modal.querySelectorAll('[data-action="view-voucher"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                const type = e.target.closest('button').dataset.type;
                this.viewVoucher(id, type);
            });
        });
        
        // Print voucher buttons
        modal.querySelectorAll('[data-action="print-voucher"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                const type = e.target.closest('button').dataset.type;
                this.printVoucher(id, type);
            });
        });
        
        // Delete voucher buttons
        modal.querySelectorAll('[data-action="delete-voucher"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                const type = e.target.closest('button').dataset.type;
                this.deleteVoucher(id, type);
            });
        });
    }

ProfessionalAccounting.prototype.openPaymentVoucherModal = async function(voucherId = null) {
        const title = voucherId ? 'Edit Payment Voucher' : 'Add Payment Voucher';
        const content = this.getPaymentVoucherModalContent(voucherId);
        this.showModal(title, content, 'large', 'paymentVoucherModal');
        const modal = document.getElementById('paymentVoucherModal');
        if (!modal) return;
        
        // Create a simple close function for this modal
        const closePaymentModal = async () => {
            try {
                modal.classList.remove('accounting-modal-visible', 'show-modal');
                modal.classList.add('accounting-modal-hidden');
                modal.removeAttribute('data-modal-visible');
                if (modal.parentNode) {
                    modal.remove();
                }
                if (this.activeModal === modal) {
                    this.activeModal = null;
                }
                document.body.classList.remove('body-no-scroll');
            } catch (e) {
                console.error('Error closing payment modal:', e);
            }
        };
        
        const form = modal.querySelector('#paymentVoucherForm');
        if (!form) return;
        
        // Prevent form submission from cancel/close buttons - MUST be first
        form.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (button) {
                const btnId = button.id;
                const btnText = button.textContent.trim().toLowerCase();
                const hasCloseAction = button.hasAttribute('data-action') && button.getAttribute('data-action') === 'close-modal';
                const isCancelBtn = btnId === 'paymentVoucherCancelBtn' || (btnText.includes('cancel') && button.classList.contains('btn-secondary'));
                
                if (hasCloseAction || isCancelBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    // Close the modal directly from form handler
                    await closePaymentModal();
                    return false;
                }
            }
        }, { capture: true });
        
        // Also add a direct handler on the cancel button itself as backup
        setTimeout(() => {
            const cancelBtn = form.querySelector('#paymentVoucherCancelBtn') || form.querySelector('button[data-action="close-modal"]');
            if (cancelBtn) {
                const directCancelHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closePaymentModal();
                    return false;
                };
                cancelBtn.addEventListener('click', directCancelHandler, { capture: true });
                cancelBtn.addEventListener('click', directCancelHandler, { capture: false });
                cancelBtn.onclick = directCancelHandler;
            }
        }, 300);
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.savePaymentVoucher(voucherId);
        });
        const dateInput = form.querySelector('[name="voucher_date"]');
        if (dateInput && !dateInput.value) {
            dateInput.value = this.formatDateForInput(new Date().toISOString());
        }
        await this.loadPaymentVoucherAccountOptions(modal);
        await this.loadCostCentersForPaymentVoucher(modal);
        if (window.currencyUtils && window.currencyUtils.populateCurrencySelect) {
            const currencySelect = form.querySelector('[name="currency"]');
            if (currencySelect) {
                var pop = window.currencyUtils.populateCurrencySelect(currencySelect.id || 'paymentVoucherCurrency');
                if (pop && typeof pop.then === 'function') await pop;
            }
        }
        if (voucherId) {
            try {
                const res = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?id=${voucherId}&type=payment`, { credentials: 'include' });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.voucher) {
                        var v = data.voucher;
                        this.applyPaymentVoucherDataToForm(v, form);
                        var cashEl = form.querySelector('#paymentVoucherCashAccount');
                        if (cashEl) {
                            var rawBank = v.bank_account_id !== undefined ? v.bank_account_id : (v.bankAccountId !== undefined ? v.bankAccountId : (v.bank_account && v.bank_account.id));
                            var bankId = (rawBank !== undefined && rawBank !== null && rawBank !== '') ? Number(rawBank) : null;
                            var sourceId = (v.source_account_id !== undefined && v.source_account_id !== null && v.source_account_id !== '') ? Number(v.source_account_id) : null;
                            if (sourceId == null && v.account_id != null && v.account_id !== '') sourceId = Number(v.account_id);
                            var val = (bankId !== null && bankId > 0) ? ('bank_' + bankId) : ((bankId === 0 || rawBank === '0' || rawBank === 0) ? '0' : ((sourceId !== null && sourceId > 0) ? ('gl_' + sourceId) : ''));
                            debug.rawBank = rawBank;
                            debug.bankId = bankId;
                            debug.sourceAccountId = sourceId;
                            debug.targetValue = val;
                            debug.optionValues = Array.from(cashEl.options).map(function(o) { return o.value; });
                            debug.optionCount = cashEl.options.length;
                            form.setAttribute('data-cash-bank-value', val || '');
                            var setCash = function() {
                                if (!cashEl) return;
                                var targetVal = form.getAttribute('data-cash-bank-value') || '';
                                if (!targetVal) return;
                                var hasOption = Array.from(cashEl.options).some(function(o) { return o.value === targetVal; });
                                if (!hasOption) {
                                    if (targetVal === '0') return;
                                    var id = targetVal.replace('bank_', '').replace('gl_', '');
                                    var opt = document.createElement('option');
                                    opt.value = targetVal;
                                    opt.textContent = targetVal.indexOf('gl_') === 0 ? ('GL #' + id) : ('Bank #' + id);
                                    cashEl.appendChild(opt);
                                }
                                var before = cashEl.value;
                                if (cashEl.value !== targetVal) {
                                    cashEl.value = targetVal;
                                    var idx = Array.from(cashEl.options).findIndex(function(o) { return o.value === targetVal; });
                                    if (idx >= 0) cashEl.selectedIndex = idx;
                                }
                            };
                            setCash();
                            if (val) {
                                var attempts = 0;
                                var t = setInterval(function() {
                                    var prev = cashEl.value;
                                    setCash();
                                    attempts++;
                                    if (attempts >= 20) clearInterval(t);
                                }, 200);
                                setTimeout(function() { clearInterval(t); }, 4500);
                            }
                            if (typeof MutationObserver !== 'undefined' && (window.ACCOUNTING_DEBUG_CASH_BANK === true)) {
                                var obs = new MutationObserver(function() {});
                                obs.observe(cashEl, { childList: true, subtree: true });
                            }
                            if ((bankId === null || bankId === undefined) && cashEl.parentNode) {
                                var hint = form.querySelector('#paymentVoucherCashHint');
                                if (!hint) {
                                    hint = document.createElement('small');
                                    hint.id = 'paymentVoucherCashHint';
                                    hint.className = 'form-text text-muted';
                                    hint.textContent = 'No cash/bank saved. Select one and click Update.';
                                    hint.style.marginTop = '4px';
                                    cashEl.parentNode.appendChild(hint);
                                }
                                cashEl.addEventListener('change', function clearHint() {
                                    if (this.value) {
                                        var h = form.querySelector('#paymentVoucherCashHint');
                                        if (h) h.remove();
                                        this.removeEventListener('change', clearHint);
                                    }
                                });
                            }
                        }
                    }
                }
            } catch (e) {
                this.showToast('Failed to load payment voucher', 'error');
            }
        } else {
        }
        
        // Force attach handlers directly to buttons after a short delay
        setTimeout(() => {
            // Cancel button - remove all handlers and add new one
            const cancelBtn = modal.querySelector('#paymentVoucherCancelBtn') || modal.querySelector('button[data-action="close-modal"]');
            if (cancelBtn) {
                // Clone to remove all event listeners
                const newCancelBtn = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                
                const cancelHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    
                    // Prevent form submission
                    const form = this.closest('form');
                    if (form) {
                        form.preventDefault && form.preventDefault();
                        form.stopPropagation && form.stopPropagation();
                    }
                    
                    await closePaymentModal();
                    return false;
                };
                
                // Set onclick first (fires before addEventListener)
                newCancelBtn.onclick = cancelHandler;
                // Then addEventListener with capture (fires early)
                newCancelBtn.addEventListener('click', cancelHandler, { capture: true, once: false });
                // Also add without capture as backup
                newCancelBtn.addEventListener('click', cancelHandler, { capture: false, once: false });
            }
            
            // X button - remove all handlers and add new one
            const closeBtn = modal.querySelector('.accounting-modal-close');
            if (closeBtn) {
                const newCloseBtn = closeBtn.cloneNode(true);
                closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                
                const closeHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closePaymentModal();
                    return false;
                };
                
                newCloseBtn.onclick = closeHandler;
                newCloseBtn.addEventListener('click', closeHandler, true);
            }
            
            // Overlay click
            const overlay = modal.querySelector('.accounting-modal-overlay');
            if (overlay) {
                const overlayHandler = async function(e) {
                    if (e.target === overlay) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closePaymentModal();
                        return false;
                    }
                };
                
                overlay.onclick = overlayHandler;
                overlay.addEventListener('click', overlayHandler, true);
            }
            
            // Modal backdrop click
            const backdropHandler = async function(e) {
                if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closePaymentModal();
                    return false;
                }
            };
            
            modal.onclick = backdropHandler;
            modal.addEventListener('click', backdropHandler, true);
        }, 200);
    }

ProfessionalAccounting.prototype.getPaymentVoucherModalContent = function(voucherId = null) {
        const isEdit = !!voucherId;
        const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
        return `
            <form id="paymentVoucherForm" data-voucher-id="${voucherId || 'null'}">
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Voucher Number</label>
                        <input type="text" name="voucher_number" id="paymentVoucherNumber" readonly placeholder="Auto-generated">
                        <small class="form-text">Auto-generated</small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Voucher Date *</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="voucher_date" id="paymentVoucherDate" class="date-input" required value="${today}" placeholder="MM/DD/YYYY">
                            <i class="fas fa-calendar-alt date-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Cash / Bank Account *</label>
                        <select name="bank_account_id" id="paymentVoucherCashAccount" required>
                            <option value="">Loading bank accounts...</option>
                        </select>
                        <small class="form-text" id="paymentVoucherBankHelpText" style="display: none;"></small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Payee / Expense Account *</label>
                        <select name="vendor_id" id="paymentVoucherPayee" required>
                            <option value="">Loading vendors & accounts...</option>
                        </select>
                        <small class="form-text" id="paymentVoucherPayeeHelpText" style="display: none;"></small>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Amount *</label>
                        <input type="text" name="amount" inputmode="decimal" required placeholder="0.00" dir="ltr" lang="en" style="direction:ltr;unicode-bidi:embed" data-amount-input="western">
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Cost Center</label>
                        <select name="cost_center_id" id="paymentVoucherCostCenter">
                            <option value="">Select option</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Currency</label>
                        <select name="currency" id="paymentVoucherCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Status</label>
                        <select name="status" id="paymentVoucherStatus">
                            <option value="Draft" selected>Draft</option>
                            <option value="Posted">Posted</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Reversed">Reversed</option>
                        </select>
                        <small class="form-text">Draft = not finalized; Posted = recorded/cleared</small>
                    </div>
                </div>
                <div class="accounting-modal-form-group full-width">
                    <label>Description</label>
                    <textarea name="notes" rows="3" placeholder="Description"></textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="button" class="btn btn-secondary" id="paymentVoucherCancelBtn" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="paymentVoucherSaveBtn">
                        <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Save'}
                    </button>
                </div>
            </form>
        `;
    }

ProfessionalAccounting.prototype.loadPaymentVoucherAccountOptions = async function(modalOrForm = null) {
        const container = modalOrForm || document;
        const cashSelect = container.querySelector('#paymentVoucherCashAccount');
        const payeeSelect = container.querySelector('#paymentVoucherPayee');
        if (!cashSelect || !payeeSelect) return;
        cashSelect.innerHTML = '<option value="">Loading...</option>';
        payeeSelect.innerHTML = '<option value="">Loading...</option>';
        try {
            const [banksRes, vendorsRes, accountsRes] = await Promise.all([
                fetch(`${this.apiBase}/banks.php`, { credentials: 'include' }).catch(() => ({ ok: false })),
                fetch(`${this.apiBase}/vendors.php`, { credentials: 'include' }).catch(() => ({ ok: false })),
                fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`, { credentials: 'include' }).catch(() => ({ ok: false }))
            ]);
            const banksData = banksRes.ok ? await banksRes.json().catch(() => ({})) : {};
            const banks = (banksData.success && banksData.banks) ? banksData.banks : [];
            const vendorsData = vendorsRes.ok ? await vendorsRes.json().catch(() => ({})) : {};
            const vendors = (vendorsData.success && vendorsData.vendors) ? vendorsData.vendors : [];
            const accountsData = accountsRes.ok ? await accountsRes.json().catch(() => ({})) : {};
            const accounts = (accountsData.success && accountsData.accounts) ? accountsData.accounts : [];
            cashSelect.innerHTML = '<option value="">Select Cash/Bank</option><option value="0">Cash</option>';
            banks.forEach(b => {
                const opt = document.createElement('option');
                opt.value = `bank_${b.id}`;
                opt.textContent = (b.account_name || b.bank_name || `Bank ${b.id}`) + (b.account_number ? ` (${b.account_number})` : '');
                cashSelect.appendChild(opt);
            });
            if (accounts.length) {
                const cashGrp = document.createElement('optgroup');
                cashGrp.label = 'GL Accounts (Cash/Bank/Asset)';
                accounts.filter(a => {
                    const t = (a.account_type || '').toLowerCase();
                    return t.includes('asset') || t.includes('cash') || t.includes('bank') || !t;
                }).forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = `gl_${a.id}`;
                    opt.textContent = (a.account_code ? `${a.account_code} ` : '') + (a.account_name || `Account ${a.id}`);
                    cashGrp.appendChild(opt);
                });
                if (cashGrp.children.length) cashSelect.appendChild(cashGrp);
            }
            payeeSelect.innerHTML = '<option value="">Select Payee / Expense Account</option>';
            if (vendors.length) {
                const grp = document.createElement('optgroup');
                grp.label = 'Vendors';
                vendors.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = `vendor_${v.id}`;
                    opt.textContent = v.vendor_name || `Vendor ${v.id}`;
                    grp.appendChild(opt);
                });
                payeeSelect.appendChild(grp);
            }
            if (accounts.length) {
                const grp = document.createElement('optgroup');
                grp.label = 'All GL Accounts';
                accounts.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = `gl_${a.id}`;
                    opt.textContent = (a.account_code ? `${a.account_code} ` : '') + (a.account_name || `Account ${a.id}`);
                    grp.appendChild(opt);
                });
                payeeSelect.appendChild(grp);
            }
        } catch (e) {
            console.error('Error loading payment voucher options:', e);
            cashSelect.innerHTML = '<option value="">Error loading</option>';
            payeeSelect.innerHTML = '<option value="">Error loading</option>';
        }
    }

ProfessionalAccounting.prototype.loadCostCentersForPaymentVoucher = async function(modalOrForm = null) {
        const container = modalOrForm || document;
        const select = container.querySelector('#paymentVoucherCostCenter');
        if (!select) return;
        try {
            const response = await fetch(`${this.apiBase}/cost-centers.php`, { credentials: 'include' });
            const data = await response.json();
            if (data.success && data.cost_centers) {
                select.innerHTML = '<option value="">Select option</option>';
                data.cost_centers.forEach(cc => {
                    const opt = document.createElement('option');
                    opt.value = cc.id;
                    opt.textContent = cc.code ? `${cc.code} - ${cc.name}` : cc.name;
                    select.appendChild(opt);
                });
            }
        } catch (e) {
            select.innerHTML = '<option value="">No cost centers</option>';
        }
    }

ProfessionalAccounting.prototype.applyPaymentVoucherDataToForm = function(voucher, form) {
        if (!form || !voucher) return;
        var arabicToWestern = function(s) {
            if (s === undefined || s === null || s === '') return '';
            s = String(s);
            var map = { '\u0660': '0', '\u0661': '1', '\u0662': '2', '\u0663': '3', '\u0664': '4', '\u0665': '5', '\u0666': '6', '\u0667': '7', '\u0668': '8', '\u0669': '9', '\u06F0': '0', '\u06F1': '1', '\u06F2': '2', '\u06F3': '3', '\u06F4': '4', '\u06F5': '5', '\u06F6': '6', '\u06F7': '7', '\u06F8': '8', '\u06F9': '9' };
            return s.replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function(c) { return map[c] || c; });
        };
        const set = (sel, val) => {
            const el = form.querySelector(sel);
            if (el) el.value = (val !== undefined && val !== null && val !== '') ? String(val) : '';
        };
        const ensureOptionAndSet = (selectEl, value, label) => {
            if (!selectEl || !value) return;
            const hasOption = Array.from(selectEl.options).some(o => o.value === value);
            if (!hasOption && label) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = label;
                selectEl.appendChild(opt);
            }
            selectEl.value = value;
        };
        const num = (v) => (v !== undefined && v !== null && v !== '') ? Number(v) : null;
        const str = (v) => (v !== undefined && v !== null) ? String(v) : '';
        set('[name="voucher_number"]', str(voucher.voucher_number));
        set('[name="voucher_date"]', str(voucher.voucher_date || voucher.payment_date));
        var amountVal = voucher.amount !== undefined && voucher.amount !== null ? voucher.amount : '';
        var amountStr = amountVal !== '' ? arabicToWestern(String(amountVal)) : '';
        set('[name="amount"]', amountStr);
        var amtInput = form.querySelector('[name="amount"]');
        if (amtInput) { amtInput.setAttribute('dir', 'ltr'); amtInput.setAttribute('lang', 'en'); }
        set('[name="payment_method"]', str(voucher.payment_method || 'Cash'));
        set('[name="cost_center_id"]', num(voucher.cost_center_id));
        set('[name="currency"]', str(voucher.currency || 'SAR'));
        set('[name="status"]', str(voucher.status || 'Draft'));
        set('[name="notes"]', str(voucher.notes || voucher.description || ''));
        const cashEl = form.querySelector('#paymentVoucherCashAccount');
        if (cashEl) {
            const rawBank = voucher.bank_account_id !== undefined ? voucher.bank_account_id : (voucher.bankAccountId !== undefined ? voucher.bankAccountId : (voucher.bank_account && voucher.bank_account.id));
            const bankId = num(rawBank);
            const sourceAccountId = num(voucher.source_account_id) ?? num(voucher.account_id);
            if (bankId !== null && bankId > 0) {
                ensureOptionAndSet(cashEl, 'bank_' + bankId, 'Bank #' + bankId);
            } else if (bankId === 0 || rawBank === '0') {
                cashEl.value = '0';
            } else if (sourceAccountId !== null && sourceAccountId > 0) {
                ensureOptionAndSet(cashEl, 'gl_' + sourceAccountId, 'GL #' + sourceAccountId);
            } else {
                cashEl.value = '';
            }
        }
        const payeeEl = form.querySelector('#paymentVoucherPayee');
        if (payeeEl) {
            const vendorId = num(voucher.vendor_id);
            const payeeAccountId = num(voucher.account_id);
            if (vendorId !== null && vendorId > 0) {
                ensureOptionAndSet(payeeEl, 'vendor_' + vendorId, 'Vendor #' + vendorId);
            } else if (payeeAccountId !== null && payeeAccountId > 0) {
                ensureOptionAndSet(payeeEl, 'gl_' + payeeAccountId, 'GL #' + payeeAccountId);
            } else {
                payeeEl.value = '';
            }
        }
        form.setAttribute('lang', 'en');
        form.querySelectorAll('select[required], input[required]').forEach(function(el) {
            el.setCustomValidity('');
        });
    }

ProfessionalAccounting.prototype.savePaymentVoucher = async function(voucherId = null) {
        const form = document.getElementById('paymentVoucherForm');
        if (!form) return;
        try {
            const fd = new FormData(form);
            const data = Object.fromEntries(fd);
            var amtStr = String(data.amount || '').replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function(c) {
                var m = { '\u0660':'0','\u0661':'1','\u0662':'2','\u0663':'3','\u0664':'4','\u0665':'5','\u0666':'6','\u0667':'7','\u0668':'8','\u0669':'9','\u06F0':'0','\u06F1':'1','\u06F2':'2','\u06F3':'3','\u06F4':'4','\u06F5':'5','\u06F6':'6','\u06F7':'7','\u06F8':'8','\u06F9':'9' };
                return m[c] || c;
            });
            data.amount = parseFloat(amtStr) || 0;
            if (data.amount <= 0 || isNaN(data.amount)) {
                this.showToast('Amount must be greater than 0', 'error');
                return;
            }
            if (!data.payment_method) data.payment_method = 'Cash';
            if (!data.bank_account_id || data.bank_account_id === '') {
                if (voucherId) delete data.bank_account_id;
                else data.bank_account_id = null;
                data.source_account_id = data.source_account_id || null;
            } else if (data.bank_account_id === '0') {
                data.bank_account_id = 0;
                data.source_account_id = data.source_account_id || null;
            } else if (String(data.bank_account_id).startsWith('bank_')) {
                data.bank_account_id = parseInt(String(data.bank_account_id).replace('bank_', ''));
                data.source_account_id = data.source_account_id || null;
            } else if (String(data.bank_account_id).startsWith('gl_')) {
                data.source_account_id = parseInt(String(data.bank_account_id).replace('gl_', ''), 10) || null;
                data.bank_account_id = null;
            } else {
                data.bank_account_id = parseInt(data.bank_account_id, 10) || null;
                data.source_account_id = data.source_account_id || null;
            }
            if (!data.vendor_id || data.vendor_id === '') {
                data.vendor_id = null;
                data.account_id = null;
            } else if (String(data.vendor_id).startsWith('vendor_')) {
                data.vendor_id = parseInt(String(data.vendor_id).replace('vendor_', ''));
                data.account_id = null;
            } else if (String(data.vendor_id).startsWith('gl_')) {
                data.account_id = parseInt(String(data.vendor_id).replace('gl_', ''));
                data.vendor_id = null;
            } else {
                data.vendor_id = parseInt(data.vendor_id);
                data.account_id = null;
            }
            if (!data.cost_center_id || data.cost_center_id === '') data.cost_center_id = null;
            if (!data.notes || data.notes.trim() === '') data.notes = null;
            if (data.notes && !data.description) data.description = data.notes;
            delete data.voucher_number;
            const url = `${this.apiBase}/receipt-payment-vouchers.php?type=payment` + (voucherId ? `&id=${voucherId}` : '');
            const method = voucherId ? 'PUT' : 'POST';
            const response = await fetch(url, {
                method,
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json().catch(() => ({}));
            if (result.success) {
                this.showToast(voucherId ? 'Payment voucher updated' : 'Payment voucher created', 'success');
                this.closeModal();
                if (document.getElementById('supportPaymentsTableBody')) await this.loadSupportPayments();
                if (document.getElementById('vouchersTableBody')) await this.loadVouchers();
            } else {
                throw new Error(result.message || 'Failed to save payment voucher');
            }
        } catch (error) {
            this.showToast(error.message || 'Failed to save payment voucher', 'error');
        }
    }

ProfessionalAccounting.prototype.openReceiptVoucherModal = async function(voucherId = null) {
        const title = voucherId ? 'Edit Receipt Voucher' : 'Add Receipt Voucher';
        const content = this.getReceiptVoucherModalContent(voucherId);
        this.showModal(title, content, 'large');
        const modal = document.getElementById('receiptVoucherModal');
        if (!modal) return;
        
        // Create a simple close function for this modal
        const closeReceiptModal = async () => {
            try {
                modal.classList.remove('accounting-modal-visible', 'show-modal');
                modal.classList.add('accounting-modal-hidden');
                modal.removeAttribute('data-modal-visible');
                if (modal.parentNode) {
                    modal.remove();
                }
                if (this.activeModal === modal) {
                    this.activeModal = null;
                }
                document.body.classList.remove('body-no-scroll');
            } catch (e) {
                console.error('Error closing receipt modal:', e);
            }
        };
        const form = modal.querySelector('#receiptVoucherForm');
        if (!form) return;
        
        // Prevent form submission from cancel/close buttons - MUST be first
        form.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (button) {
                const btnId = button.id;
                const btnText = button.textContent.trim().toLowerCase();
                const hasCloseAction = button.hasAttribute('data-action') && button.getAttribute('data-action') === 'close-modal';
                const isCancelBtn = btnId === 'receiptVoucherCancelBtn' || (btnText.includes('cancel') && button.classList.contains('btn-secondary'));
                
                if (hasCloseAction || isCancelBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    // Close the modal directly from form handler
                    await closeReceiptModal();
                    return false;
                }
            }
        }, { capture: true });
        
        // Also add a direct handler on the cancel button itself as backup
        setTimeout(() => {
            const cancelBtn = form.querySelector('#receiptVoucherCancelBtn') || form.querySelector('button[data-action="close-modal"]');
            if (cancelBtn) {
                const directCancelHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closeReceiptModal();
                    return false;
                };
                cancelBtn.addEventListener('click', directCancelHandler, { capture: true });
                cancelBtn.addEventListener('click', directCancelHandler, { capture: false });
                cancelBtn.onclick = directCancelHandler;
            }
        }, 300);
        
        // Form submit handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveReceiptVoucher(voucherId);
        });
        const dateInput = form.querySelector('[name="payment_date"]');
        if (dateInput && !dateInput.value) {
            dateInput.value = this.formatDateForInput(new Date().toISOString());
        }
        await this.loadReceiptVoucherAccountOptions(modal);
        await this.loadCostCentersForReceiptVoucher(modal);
        if (window.currencyUtils && window.currencyUtils.populateCurrencySelect) {
            const currencySelect = form.querySelector('[name="currency"]');
            if (currencySelect) {
                window.currencyUtils.populateCurrencySelect(currencySelect.id || 'receiptVoucherCurrency');
            }
        }
        if (voucherId) {
            let receipt = null;
            try {
                const res = await fetch(`${this.apiBase}/payment-receipts.php?id=${voucherId}`);
                if (res.ok) {
                    let text = await res.text();
                    if (text && text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                    const data = JSON.parse(text);
                    if (data.success && data.receipt) receipt = data.receipt;
                }
            } catch (e) {
                console.error('Failed to fetch receipt for edit:', e);
            }
            if (receipt) {
                this.applyReceiptDataToEditForm(receipt, modal);
                const cashEl = modal.querySelector('#cash_account');
                const collectedEl = modal.querySelector('#collected_from');
                const bankVal = this._receiptBankSelectValue(receipt);
                const customerVal = this._receiptCollectedSelectValue(receipt);
                this._setReceiptSelectValueAndTrigger(cashEl, bankVal, bankVal === '0' ? 'Cash' : (bankVal.startsWith('bank_') ? 'Bank' : 'GL'));
                this._setReceiptSelectValueAndTrigger(collectedEl, customerVal, customerVal ? (customerVal.startsWith('customer_') ? 'Customer' : 'GL') : '');
            } else {
                await this.loadReceiptVoucherData(voucherId, form);
            }
        }
        const addBankBtn = modal.querySelector('#addBankAccountBtn');
        if (addBankBtn) {
            addBankBtn.addEventListener('click', async () => {
                // Check if function exists directly
                if (typeof this.openBankAccountForm === 'function') {
                    await this.openBankAccountForm();
                } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.openBankAccountForm === 'function') {
                    // Try prototype method
                    await ProfessionalAccounting.prototype.openBankAccountForm.call(this);
                } else {
                    console.error('openBankAccountForm function not found');
                    this.showToast('Bank account form function not available. Please refresh the page.', 'error');
                }
                const checkInterval = setInterval(() => {
                    const bankModal = document.querySelector('.accounting-modal:has(#bankAccountForm)');
                    if (!bankModal || bankModal.classList.contains('accounting-modal-hidden')) {
                        clearInterval(checkInterval);
                        this.loadReceiptVoucherAccountOptions(modal);
                    }
                }, 500);
            });
        }
        const addCustomerBtn = modal.querySelector('#addCustomerBtn');
        if (addCustomerBtn) {
            addCustomerBtn.addEventListener('click', async () => {
                await this.openCustomerForm();
                const checkInterval = setInterval(() => {
                    const customerModal = document.querySelector('.accounting-modal:has(#customerForm)');
                    if (!customerModal || customerModal.classList.contains('accounting-modal-hidden')) {
                        clearInterval(checkInterval);
                        this.loadReceiptVoucherAccountOptions(modal);
                    }
                }, 500);
            });
        }
        const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
        const vatLabel = form.querySelector('#receiptVoucherVatLabel');
        if (vatCheckbox && vatLabel) {
            const updateVatLabel = () => {
                vatLabel.textContent = vatCheckbox.checked ? 'Value Added Tax' : 'No Value Added Tax';
            };
            vatCheckbox.addEventListener('change', updateVatLabel);
            updateVatLabel();
        }
        
        // Force attach handlers directly to buttons after a short delay
        setTimeout(() => {
            // Cancel button - remove all handlers and add new one
            const cancelBtn = modal.querySelector('#receiptVoucherCancelBtn') || modal.querySelector('button[data-action="close-modal"]');
            if (cancelBtn) {
                // Clone to remove all event listeners
                const newCancelBtn = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                
                const cancelHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    
                    // Prevent form submission
                    const form = this.closest('form');
                    if (form) {
                        form.preventDefault && form.preventDefault();
                        form.stopPropagation && form.stopPropagation();
                    }
                    
                    await closeReceiptModal();
                    return false;
                };
                
                // Set onclick first (fires before addEventListener)
                newCancelBtn.onclick = cancelHandler;
                // Then addEventListener with capture (fires early)
                newCancelBtn.addEventListener('click', cancelHandler, { capture: true, once: false });
                // Also add without capture as backup
                newCancelBtn.addEventListener('click', cancelHandler, { capture: false, once: false });
            }
            
            // X button - remove all handlers and add new one
            const closeBtn = modal.querySelector('.accounting-modal-close');
            if (closeBtn) {
                const newCloseBtn = closeBtn.cloneNode(true);
                closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                
                const closeHandler = async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closeReceiptModal();
                    return false;
                };
                
                newCloseBtn.onclick = closeHandler;
                newCloseBtn.addEventListener('click', closeHandler, true);
            }
            
            // Overlay click
            const overlay = modal.querySelector('.accounting-modal-overlay');
            if (overlay) {
                const overlayHandler = async function(e) {
                    if (e.target === overlay) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closeReceiptModal();
                        return false;
                    }
                };
                
                overlay.onclick = overlayHandler;
                overlay.addEventListener('click', overlayHandler, true);
            }
            
            // Modal backdrop click
            const backdropHandler = async function(e) {
                if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    await closeReceiptModal();
                    return false;
                }
            };
            
            modal.onclick = backdropHandler;
            modal.addEventListener('click', backdropHandler, true);
        }, 200);
    }

ProfessionalAccounting.prototype._receiptBankSelectValue = function(receipt) {
        if (receipt.cash_account_option_value !== undefined && receipt.cash_account_option_value !== null && receipt.cash_account_option_value !== '') {
            return String(receipt.cash_account_option_value);
        }
        // Check for Cash (0) first
        if (receipt.bank_account_id === 0 || receipt.bank_account_id === '0') return '0';
        const bankId = receipt.bank_account_id != null && receipt.bank_account_id !== '' ? Number(receipt.bank_account_id) : null;
        const accountId = receipt.account_id != null && receipt.account_id !== '' ? Number(receipt.account_id) : null;
        if (bankId) return `bank_${bankId}`;
        if (accountId) return `gl_${accountId}`;
        return '';
    }

ProfessionalAccounting.prototype._receiptCollectedSelectValue = function(receipt) {
        if (receipt.collected_from_option_value !== undefined && receipt.collected_from_option_value !== null && receipt.collected_from_option_value !== '') {
            return String(receipt.collected_from_option_value);
        }
        if (receipt.customer_id === 0 || receipt.customer_id === '0') return '0';
        const customerId = receipt.customer_id != null && receipt.customer_id !== '' ? Number(receipt.customer_id) : null;
        const collectedFromId = receipt.collected_from_account_id != null && receipt.collected_from_account_id !== '' ? Number(receipt.collected_from_account_id) : null;
        if (customerId) return `customer_${customerId}`;
        if (collectedFromId) return `gl_${collectedFromId}`;
        return '';
    }

ProfessionalAccounting.prototype._setReceiptSelectValueAndTrigger = function(select, value, fallbackLabelPrefix) {
        if (!select) return;
        const strVal = String(value);
        let idx = -1;
        if (strVal === '') {
            idx = 0;
        } else {
            idx = Array.from(select.options).findIndex(o => String(o.value) === strVal);
            if (idx < 0) {
                const opt = document.createElement('option');
                opt.value = strVal;
                opt.textContent = fallbackLabelPrefix ? `${fallbackLabelPrefix} #${strVal.replace(/^(bank_|gl_|customer_)/, '')}` : strVal;
                select.appendChild(opt);
                idx = Array.from(select.options).findIndex(o => String(o.value) === strVal);
            }
        }
        if (idx >= 0) {
            select.selectedIndex = idx;
            for (let i = 0; i < select.options.length; i++) {
                select.options[i].selected = (i === idx);
            }
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
        if (typeof window.$ !== 'undefined' && window.$.fn.select2 && window.$(select).data('select2')) {
            window.$(select).trigger('change.select2');
        }
    }

ProfessionalAccounting.prototype.getReceiptVoucherModalContent = function(receiptId = null) {
        const isEdit = !!receiptId;
        const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
        
        return `
            <form id="receiptVoucherForm" data-receipt-id="${receiptId || 'null'}">
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Voucher Number</label>
                        <input type="text" name="receipt_number" id="receiptVoucherNumber" readonly placeholder="Auto-generated">
                        <small class="form-text">Auto-generated</small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Voucher Date *</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="payment_date" id="receiptVoucherDate" class="date-input" required value="${today}" placeholder="MM/DD/YYYY">
                            <i class="fas fa-calendar-alt date-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Cash / Bank Account *</label>
                        <div style="display: flex; gap: 8px; align-items: flex-start;">
                            <select name="bank_account_id" id="cash_account" required style="flex: 1;">
                                <option value="">Loading bank accounts...</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline" id="addBankAccountBtn" title="Add Bank Account" style="white-space: nowrap; padding: 8px 12px;">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <small class="form-text" id="bankAccountHelpText" style="display: none;"></small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Collected From</label>
                        <div style="display: flex; gap: 8px; align-items: flex-start;">
                            <select name="customer_id" id="collected_from" style="flex: 1;">
                                <option value="">Loading accounts...</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline" id="addCustomerBtn" title="Add Customer" style="white-space: nowrap; padding: 8px 12px;">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <small class="form-text" id="customerHelpText" style="display: none;"></small>
                    </div>
                </div>

                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required placeholder="Amount">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Cost Center</label>
                        <select name="cost_center_id" id="receiptVoucherCostCenter">
                            <option value="">Select option</option>
                        </select>
                    </div>
                </div>

                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Value Added Tax:</label>
                        <div class="tax-checkbox-row">
                            <input type="checkbox" name="vat_report" id="receiptVoucherVatCheckbox" value="1">
                            <span id="receiptVoucherVatLabel" class="tax-label">No Value Added Tax</span>
                        </div>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Currency</label>
                        <select name="currency" id="receiptVoucherCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                </div>

                <div class="accounting-modal-form-group full-width">
                    <label>Statement / Description</label>
                    <textarea name="notes" rows="3" placeholder="Description"></textarea>
                </div>

                <div class="accounting-modal-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Save'}
                    </button>
                </div>
            </form>
        `;
    }

ProfessionalAccounting.prototype.loadReceiptVoucherAccountOptions = async function(modalOrForm = null) {
        const container = modalOrForm || document;
        const cashSelect = container.querySelector ? container.querySelector('#cash_account') : document.getElementById('cash_account');
        const collectedSelect = container.querySelector ? container.querySelector('#collected_from') : document.getElementById('collected_from');
        if (!cashSelect || !collectedSelect) return;
        cashSelect.innerHTML = '<option value="">Loading accounts...</option>';
        collectedSelect.innerHTML = '<option value="">Loading accounts...</option>';
        try {
            const [banksResponse, customersResponse, accountsResponse] = await Promise.all([
                fetch(`${this.apiBase}/banks.php`).catch(() => ({ ok: false })),
                fetch(`${this.apiBase}/customers.php`).catch(() => ({ ok: false })),
                fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`).catch(() => ({ ok: false }))
            ]);
            const banksData = banksResponse.ok ? await banksResponse.json().catch(() => ({})) : {};
            const banks = (banksData.success && banksData.banks && Array.isArray(banksData.banks)) ? banksData.banks : [];
            const customersData = customersResponse.ok ? await customersResponse.json().catch(() => ({})) : {};
            const customers = (customersData.success && customersData.customers && Array.isArray(customersData.customers)) ? customersData.customers : [];
            let accountsData = accountsResponse.ok ? await accountsResponse.json().catch(() => ({})) : {};
            let accounts = (accountsData.success && accountsData.accounts && Array.isArray(accountsData.accounts)) ? accountsData.accounts : [];
            const byEntityFirst = (accs) => {
                const o = { agent: [], subagent: [], worker: [], hr: [], other: [] };
                (accs || []).forEach(a => { const et = (a.entity_type || '').toLowerCase(); if (et && o[et]) o[et].push(a); else o.other.push(a); });
                return o;
            };
            if (accounts.length > 0 && (byEntityFirst(accounts).worker.length === 0 || byEntityFirst(accounts).hr.length === 0)) {
                try {
                    const retryRes = await fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1&_t=${Date.now()}`);
                    if (retryRes.ok) {
                        const retryData = await retryRes.json().catch(() => ({}));
                        if (retryData.success && Array.isArray(retryData.accounts) && retryData.accounts.length > 0) {
                            accounts = retryData.accounts;
                        }
                    }
                } catch (_) {}
            }
            const populateSelect = (select, emptyVal, emptyText, includeCash) => {
                select.innerHTML = '';
                const addOpt = (val, text, disabled) => {
                    const o = document.createElement('option');
                    o.value = val !== undefined ? val : '';
                    o.textContent = text || '';
                    if (disabled) o.disabled = true;
                    select.appendChild(o);
                };
                addOpt(emptyVal, emptyText);
                if (includeCash) addOpt('0', 'Cash');
                if (banks.length > 0) {
                    addOpt('', '─── Bank Accounts ───', true);
                    banks.forEach(b => {
                        let t = b.account_name || b.bank_name || `Bank ${b.id}`;
                        if (b.account_number) t += ` (${b.account_number})`;
                        if (b.is_active === false || b.is_active === 0) t += ' [Inactive]';
                        addOpt(`bank_${b.id}`, t);
                    });
                }
                if (customers.length > 0) {
                    addOpt('', '─── Customers ───', true);
                    customers.forEach(c => {
                        let t = c.customer_name || `Customer ${c.id}`;
                        if (c.contact_person) t += ` - ${c.contact_person}`;
                        if (c.is_active === false || c.is_active === 0) t += ' [Inactive]';
                        addOpt(`customer_${c.id}`, t);
                    });
                }
                if (accounts.length > 0) {
                    const entityLabels = { agent: 'Agents', subagent: 'SubAgents', worker: 'Workers', hr: 'HR' };
                    const byEntity = { agent: [], subagent: [], worker: [], hr: [], other: [] };
                    accounts.forEach(a => {
                        const et = (a.entity_type || '').toLowerCase();
                        if (et && byEntity[et]) byEntity[et].push(a);
                        else byEntity.other.push(a);
                    });
                    ['agent', 'subagent', 'worker', 'hr'].forEach(et => {
                        addOpt('', `─── ${entityLabels[et]} ───`, true);
                        if (byEntity[et].length > 0) {
                            byEntity[et].forEach(a => {
                                let t = a.account_code ? `${a.account_code} ${a.account_name || `Account ${a.id}`}` : (a.account_name || `Account ${a.id}`);
                                if (a.account_type) t += ` (${a.account_type})`;
                                if (a.is_active === false || a.is_active === 0) t += ' [Inactive]';
                                addOpt(`gl_${a.id}`, t);
                            });
                        } else {
                            addOpt('', '— No accounts assigned yet —', true);
                        }
                    });
                    if (byEntity.other.length > 0) {
                        addOpt('', '─── General Ledger Accounts ───', true);
                        byEntity.other.forEach(a => {
                            let t = a.account_code ? `${a.account_code} ${a.account_name || `Account ${a.id}`}` : (a.account_name || `Account ${a.id}`);
                            if (a.account_type) t += ` (${a.account_type})`;
                            if (a.is_active === false || a.is_active === 0) t += ' [Inactive]';
                            addOpt(`gl_${a.id}`, t);
                        });
                    }
                }
            };
            populateSelect(cashSelect, '', 'Select option', true);
            populateSelect(collectedSelect, '', 'Select option', true);
            const totalCount = banks.length + customers.length + accounts.length;
            const bankHelp = (container.querySelector ? container.querySelector('#bankAccountHelpText') : null) || document.getElementById('bankAccountHelpText');
            if (bankHelp) {
                bankHelp.textContent = totalCount > 0 ? `${banks.length} bank(s), ${customers.length} customer(s), ${accounts.length} GL account(s) available` : 'No accounts found. Cash option is available.';
                bankHelp.style.display = 'block';
                bankHelp.style.color = totalCount > 0 ? '#4caf50' : '#ff9800';
            }
            const collHelp = (container.querySelector ? container.querySelector('#customerHelpText') : null) || document.getElementById('customerHelpText');
            if (collHelp) {
                collHelp.textContent = totalCount > 0 ? `${banks.length} bank(s), ${customers.length} customer(s), ${accounts.length} GL account(s) available` : 'No accounts found. Cash option is available.';
                collHelp.style.display = 'block';
                collHelp.style.color = totalCount > 0 ? '#4caf50' : '#ff9800';
            }
        } catch (error) {
            console.error('Error loading receipt voucher account options:', error);
            cashSelect.innerHTML = '<option value="">Error loading accounts</option>';
            collectedSelect.innerHTML = '<option value="">Error loading accounts</option>';
            const cashOpt = document.createElement('option');
            cashOpt.value = '0';
            cashOpt.textContent = 'Cash';
            cashSelect.appendChild(cashOpt);
        }
    }

ProfessionalAccounting.prototype.loadBankAccountsForReceiptVoucher = async function(modalOrForm = null) {
        await this.loadReceiptVoucherAccountOptions(modalOrForm);
    }

ProfessionalAccounting.prototype.loadCustomersForReceiptVoucher = async function(modalOrForm = null) {
        await this.loadReceiptVoucherAccountOptions(modalOrForm);
    }

ProfessionalAccounting.prototype.loadCostCentersForReceiptVoucher = async function(modalOrForm = null) {
        const container = modalOrForm || document;
        const select = container.querySelector ? container.querySelector('#receiptVoucherCostCenter') : document.getElementById('receiptVoucherCostCenter');
        if (!select) return;
        
        try {
            const response = await fetch(`${this.apiBase}/cost-centers.php`);
            const data = await response.json();
            
            if (data.success && data.cost_centers) {
                select.innerHTML = '<option value="">Select option</option>';
                data.cost_centers.forEach(cc => {
                    const option = document.createElement('option');
                    option.value = cc.id;
                    const displayName = cc.code ? `${cc.code} - ${cc.name}` : cc.name;
                    option.textContent = displayName;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No cost centers found</option>';
            }
        } catch (error) {
            console.error('Error loading cost centers:', error);
            select.innerHTML = '<option value="">Error loading cost centers</option>';
        }
    }

ProfessionalAccounting.prototype.applyReceiptDataToEditForm = function(receipt, modalEl = null) {
        const modal = modalEl || document.getElementById('receiptVoucherModal');
        const form = modal ? modal.querySelector('#receiptVoucherForm') : document.getElementById('receiptVoucherForm');
        if (!form || !receipt) return;
        if (form.querySelector('[name="receipt_number"]')) form.querySelector('[name="receipt_number"]').value = receipt.receipt_number || receipt.voucher_number || '';
        if (form.querySelector('[name="payment_date"]')) form.querySelector('[name="payment_date"]').value = receipt.payment_date || '';
        if (form.querySelector('[name="amount"]')) form.querySelector('[name="amount"]').value = receipt.amount || '';
        if (form.querySelector('[name="cost_center_id"]')) form.querySelector('[name="cost_center_id"]').value = receipt.cost_center_id || '';
        if (form.querySelector('[name="payment_method"]')) form.querySelector('[name="payment_method"]').value = receipt.payment_method || 'Cash';
        if (form.querySelector('[name="currency"]')) form.querySelector('[name="currency"]').value = receipt.currency || 'SAR';
        if (form.querySelector('[name="notes"]')) form.querySelector('[name="notes"]').value = receipt.notes || receipt.description || '';
        const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
        if (vatCheckbox) vatCheckbox.checked = receipt.vat_report === '1' || receipt.vat_report === true || receipt.vat_report === 1;
        const vatLabel = form.querySelector('#receiptVoucherVatLabel');
        if (vatLabel) vatLabel.textContent = vatCheckbox && vatCheckbox.checked ? 'Value Added Tax' : 'No Value Added Tax';
    }

ProfessionalAccounting.prototype.loadReceiptVoucherData = async function(receiptId, formEl = null) {
        // Always use form inside Edit modal so we target the visible form
        const modal = document.getElementById('receiptVoucherModal');
        const form = formEl && formEl.closest && formEl.closest('#receiptVoucherModal') ? formEl : (modal ? modal.querySelector('#receiptVoucherForm') : null) || document.getElementById('receiptVoucherForm');
        if (!form) return;
        try {
            const response = await fetch(`${this.apiBase}/payment-receipts.php?id=${receiptId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.receipt) {
                const receipt = data.receipt;
                const bankId = receipt.bank_account_id != null && receipt.bank_account_id !== '' ? Number(receipt.bank_account_id) : null;
                const accountId = receipt.account_id != null && receipt.account_id !== '' ? Number(receipt.account_id) : null;
                const customerId = receipt.customer_id != null && receipt.customer_id !== '' ? Number(receipt.customer_id) : null;
                const collectedFromId = receipt.collected_from_account_id != null && receipt.collected_from_account_id !== '' ? Number(receipt.collected_from_account_id) : null;
                // Only show saved value: null = "Select option", 0 = Cash, else bank_/gl_
                const bankVal = bankId ? `bank_${bankId}` : (accountId ? `gl_${accountId}` : (receipt.bank_account_id === 0 ? '0' : ''));
                const customerVal = customerId ? `customer_${customerId}` : (collectedFromId ? `gl_${collectedFromId}` : '');

                const ensureOptionAndSetValue = (select, value, label) => {
                    if (!select) return;
                    const strVal = String(value);
                    if (strVal === '') {
                        select.value = '';
                        return;
                    }
                    let opt = select.querySelector(`option[value="${strVal}"]`);
                    if (!opt) {
                        opt = document.createElement('option');
                        opt.value = strVal;
                        opt.textContent = label || strVal;
                        select.appendChild(opt);
                    }
                    select.value = strVal;
                    if (select.value !== strVal) {
                        const idx = Array.from(select.options).findIndex(o => o.value === strVal);
                        if (idx >= 0) select.selectedIndex = idx;
                    }
                };

                const applyDropdowns = () => {
                    const f = document.getElementById('receiptVoucherModal')?.querySelector('#receiptVoucherForm') || document.getElementById('receiptVoucherForm');
                    if (!f) return;
                    const bankSelect = f.querySelector('[name="bank_account_id"]');
                    const customerSelect = f.querySelector('[name="customer_id"]');
                    if (bankSelect) {
                        ensureOptionAndSetValue(bankSelect, bankVal, bankVal === '0' ? 'Cash' : (bankVal.startsWith('bank_') ? `Bank #${bankVal.replace('bank_', '')}` : `GL #${bankVal.replace('gl_', '')}`));
                    }
                    if (customerSelect) {
                        if (customerVal) {
                            ensureOptionAndSetValue(customerSelect, customerVal, customerVal.startsWith('customer_') ? `Customer #${customerVal.replace('customer_', '')}` : `GL #${customerVal.replace('gl_', '')}`);
                        } else {
                            customerSelect.value = '';
                        }
                    }
                };

                // Populate simple fields first
                if (form.querySelector('[name="receipt_number"]')) {
                    form.querySelector('[name="receipt_number"]').value = receipt.receipt_number || receipt.voucher_number || '';
                }
                if (form.querySelector('[name="payment_date"]')) {
                    form.querySelector('[name="payment_date"]').value = receipt.payment_date || '';
                }
                applyDropdowns();
                requestAnimationFrame(applyDropdowns);
                setTimeout(applyDropdowns, 0);
                setTimeout(applyDropdowns, 80);
                setTimeout(applyDropdowns, 250);
                setTimeout(applyDropdowns, 500);
                if (form.querySelector('[name="amount"]')) {
                    form.querySelector('[name="amount"]').value = receipt.amount || '';
                }
                if (form.querySelector('[name="cost_center_id"]')) {
                    form.querySelector('[name="cost_center_id"]').value = receipt.cost_center_id || '';
                }
                if (form.querySelector('[name="payment_method"]')) {
                    form.querySelector('[name="payment_method"]').value = receipt.payment_method || 'Cash';
                }
                if (form.querySelector('[name="currency"]')) {
                    form.querySelector('[name="currency"]').value = receipt.currency || 'SAR';
                }
                if (form.querySelector('[name="notes"]')) {
                    form.querySelector('[name="notes"]').value = receipt.notes || receipt.description || '';
                }
                
                // Handle VAT checkbox
                const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
                if (vatCheckbox) {
                    vatCheckbox.checked = receipt.vat_report === '1' || receipt.vat_report === true || receipt.vat_report === 1;
                    const vatLabel = form.querySelector('#receiptVoucherVatLabel');
                    if (vatLabel) {
                        vatLabel.textContent = vatCheckbox.checked ? 'Value Added Tax' : 'No Value Added Tax';
                    }
                }
                
            } else {
                throw new Error(data.message || 'Failed to load receipt voucher');
            }
        } catch (error) {
            console.error('Error loading receipt voucher:', error);
            this.showToast(error.message || 'Failed to load receipt voucher', 'error');
        }
    }

ProfessionalAccounting.prototype.saveReceiptVoucher = async function(receiptId = null) {
        const form = document.getElementById('receiptVoucherForm');
        if (!form) return;
        
        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Convert amount to float
            data.amount = parseFloat(data.amount) || 0;
            if (data.amount <= 0 || isNaN(data.amount)) {
                this.showToast('Amount must be greater than 0', 'error');
                return;
            }
            
            // Handle VAT checkbox
            const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
            data.vat_report = vatCheckbox && vatCheckbox.checked ? '1' : '0';
            
            // Set default payment method if not provided
            if (!data.payment_method) data.payment_method = 'Cash';
            
            // Handle Customer/GL Account/Bank selection for "Account Collected From"
            // Values can be: empty, 'customer_X', 'gl_X', or 'bank_X' (same options as Cash/Bank)
            if (!data.customer_id || data.customer_id === '') {
                data.customer_id = null;
                data.collected_from_account_id = null;
            } else if (typeof data.customer_id === 'string' && data.customer_id.startsWith('customer_')) {
                data.customer_id = parseInt(data.customer_id.replace('customer_', ''));
                data.collected_from_account_id = null;
            } else if (typeof data.customer_id === 'string' && data.customer_id.startsWith('gl_')) {
                data.collected_from_account_id = parseInt(data.customer_id.replace('gl_', ''));
                data.customer_id = null;
            } else if (typeof data.customer_id === 'string' && data.customer_id.startsWith('bank_')) {
                data.collected_from_bank_id = parseInt(data.customer_id.replace('bank_', ''));
                data.customer_id = null;
                data.collected_from_account_id = null;
            } else if (data.customer_id === '0') {
                data.customer_id = 0;
                data.collected_from_account_id = null;
            } else {
                // Legacy format (direct ID) - assume customer
                data.customer_id = parseInt(data.customer_id);
                data.collected_from_account_id = null;
            }
            
            // Handle Cash/Bank/GL Account selection
            // Values can be: '' (no selection), '0' (Cash), 'bank_X' (Bank Account), or 'gl_X' (GL Account)
            if (!data.bank_account_id || data.bank_account_id === '') {
                // No selection
                data.bank_account_id = null;
                data.account_id = null;
            } else if (data.bank_account_id === '0') {
                // Cash selected - store as 0
                data.bank_account_id = 0;
                data.account_id = null;
            } else if (typeof data.bank_account_id === 'string' && data.bank_account_id.startsWith('bank_')) {
                // Bank account selected
                data.bank_account_id = parseInt(data.bank_account_id.replace('bank_', ''));
                data.account_id = null;
            } else if (typeof data.bank_account_id === 'string' && data.bank_account_id.startsWith('gl_')) {
                // GL account selected
                data.account_id = parseInt(data.bank_account_id.replace('gl_', ''));
                data.bank_account_id = null;
            } else {
                // Legacy format (direct ID) - assume bank account
                data.bank_account_id = parseInt(data.bank_account_id);
                data.account_id = null;
            }
            
            if (!data.cost_center_id || data.cost_center_id === '') data.cost_center_id = null;
            if (!data.notes || data.notes.trim() === '') data.notes = null;
            
            // Ensure description field is set if notes is provided
            if (data.notes && !data.description) {
                data.description = data.notes;
            }
            
            // Don't send receipt_number if empty (let API auto-generate)
            if (!data.receipt_number || data.receipt_number.trim() === '') {
                delete data.receipt_number;
            }
            
            const url = receiptId 
                ? `${this.apiBase}/payment-receipts.php?id=${receiptId}`
                : `${this.apiBase}/payment-receipts.php`;
            
            const method = receiptId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            // Check if response is ok and has content
            if (!response.ok) {
                const errorText = await response.text();
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    errorData = { success: false, message: `HTTP ${response.status}: ${errorText || response.statusText}` };
                }
                let msg = errorData.message || `HTTP error! status: ${response.status}`;
                if (errorData.debug && errorData.debug.message) {
                    msg += ' [' + errorData.debug.message + (errorData.debug.file ? ' ' + errorData.debug.file + (errorData.debug.line ? ':' + errorData.debug.line : '') : '') + ']';
                }
                throw new Error(msg);
            }
            
            // Get response text once (body is consumable only once)
            let responseText = await response.text();
            // Strip BOM and leading/trailing whitespace that can cause JSON parse to fail
            if (typeof responseText === 'string') {
                if (responseText.charCodeAt(0) === 0xFEFF) {
                    responseText = responseText.slice(1);
                }
                responseText = responseText.trim();
            }
            if (!responseText) {
                throw new Error('Empty response from server. Check that the receipt API is reachable and returns JSON.');
            }
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                const preview = responseText.length > 80 ? responseText.substring(0, 80) + '...' : responseText;
                throw new Error('Invalid JSON from server: ' + (e.message || 'parse error') + (preview ? ' (response: ' + preview + ')' : ''));
            }
            
            if (result.success) {
                // If new receipt was created, update the receipt number field to show the generated number
                if (!receiptId && (result.receipt_id || result.voucher_id)) {
                    const receiptNumberField = form.querySelector('#receiptVoucherNumber');
                    const num = result.voucher_number || result.receipt_number;
                    if (receiptNumberField && num) {
                        receiptNumberField.value = num;
                    }
                }
                
                // Show success message with journal entry info if available
                let successMessage = receiptId ? 'Receipt voucher updated successfully' : 'Receipt voucher created successfully';
                if (result.journal_entry && result.journal_entry.success) {
                    successMessage += ' • Journal entry posted to General Ledger';
                }
                
                this.showToast(successMessage, 'success');
                this.closeModal();
                if (document.getElementById('receiptVouchersTableBody')) {
                    await this.loadReceiptVouchers();
                }
            } else {
                // Enhanced error handling: show server message and debug info if present
                let errorMessage = result.message || 'Failed to save receipt voucher';
                if (result.error && typeof result.error === 'string') {
                    errorMessage += ': ' + result.error;
                }
                if (result.debug && result.debug.message) {
                    errorMessage += ' (' + result.debug.message + (result.debug.file ? ' in ' + result.debug.file + (result.debug.line ? ':' + result.debug.line : '') : '') + ')';
                }
                throw new Error(errorMessage);
            }
        } catch (error) {
            console.error('Error saving receipt voucher:', error);
            this.showToast(error.message || 'Failed to save receipt voucher', 'error');
        }
    }
