/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.accounts.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.accounts.js`.
 */
/**
 * Professional Accounting - Extracted methods
 * Requires: professional.js (class) loaded first
 */
ProfessionalAccounting.prototype.isElementMeasurable = function(element) {
        if (!element || !element.getBoundingClientRect) {
            return false;
        }
        
        // Check element itself
        const style = window.getComputedStyle(element);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        
        // Check all parent elements up to body
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            const parentStyle = window.getComputedStyle(parent);
            // If parent has display:none, child cannot be measured
            if (parentStyle.display === 'none') {
                return false;
            }
                                parent = parent.parentElement;
        }
        
        // Check if element has actual dimensions (after ensuring visibility)
        const rect = element.getBoundingClientRect();
        return rect.width > 0 || rect.height > 0;
    }

ProfessionalAccounting.prototype.loadAccountsForModalSelect = async function(selector) {
        const accountSelect = document.querySelector(selector);
        if (!accountSelect) {
            return;
        }
        
        // Always start with "All Accounts" option - make sure it's properly set
        accountSelect.innerHTML = '<option value="" selected>All Accounts</option>';
        
        try {
            const response = await fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`, { credentials: 'include' });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in loadAccountsForModalSelect:', jsonError);
                data = { success: false, accounts: [] };
            }
            
            if (data.success) {
                if (data.accounts && data.accounts.length > 0) {
                    // "All Accounts" is already there, just add the account options
                data.accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                        const displayText = `${account.account_code || ''} ${account.account_name || 'N/A'}`.trim();
                        option.textContent = displayText;
                    accountSelect.appendChild(option);
                });
                }
            }
        } catch (error) {
            // Keep "All Accounts" option even if loading fails
        }
        
        // Ensure "All Accounts" is selected by default if no account was previously selected
        if (!accountSelect.value && accountSelect.options.length > 0) {
            accountSelect.value = '';
        }
        
        // Force a re-render by triggering a change event
        accountSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

ProfessionalAccounting.prototype.loadCustomersForSelect = async function(selector, invoiceId = null) {
        const customerSelect = document.getElementById(selector);
        if (!customerSelect) {
            return;
        }
        
        customerSelect.innerHTML = '<option value="">Loading customers...</option>';
        
        try {
            const response = await fetch(`${this.apiBase}/customers.php?is_active=1`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in loadCustomersForSelect:', jsonError);
                data = { success: false, customers: [] };
            }
            
            if (data.success && data.customers) {
                customerSelect.innerHTML = '<option value="">Select Customer</option>';
                data.customers.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.id;
                    option.textContent = customer.customer_name || 'N/A';
                    customerSelect.appendChild(option);
                });
                
                // If editing, load invoice and populate form fields
                if (invoiceId) {
                    try {
                        const invResponse = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}`);
                        const invData = await invResponse.json();
                        if (invData.success && invData.invoice) {
                            // Populate form fields
                            const form = document.getElementById('invoiceForm');
                            if (form && invData.invoice) {
                                const inv = invData.invoice;
                                if (form.querySelector('[name="invoice_number"]')) {
                                    form.querySelector('[name="invoice_number"]').value = inv.invoice_number || '';
                                }
                                if (form.querySelector('[name="invoice_date"]')) {
                                    form.querySelector('[name="invoice_date"]').value = inv.invoice_date ? this.formatDateForInput(inv.invoice_date) : '';
                                }
                                if (form.querySelector('[name="due_date"]')) {
                                    form.querySelector('[name="due_date"]').value = inv.due_date ? this.formatDateForInput(inv.due_date) : '';
                                }
                                if (form.querySelector('[name="total_amount"]')) {
                                    form.querySelector('[name="total_amount"]').value = inv.total_amount || 0;
                                }
                                if (form.querySelector('[name="currency"]')) {
                                    form.querySelector('[name="currency"]').value = inv.currency || this.getDefaultCurrencySync();
                                }
                                if (form.querySelector('[name="description"]')) {
                                    form.querySelector('[name="description"]').value = inv.description || '';
                                }
                                // Set customers if available
                                const customersContainer = document.getElementById('invoiceCustomersContainer');
                                if (customersContainer && inv.customers) {
                                    // Parse customers (could be comma-separated or newline-separated)
                                    const customers = inv.customers.split(/[,\n]/).map(c => c.trim()).filter(c => c);
                                    customersContainer.innerHTML = '';
                                    customers.forEach((customer, index) => {
                                        const row = document.createElement('div');
                                        row.className = 'customer-input-row';
                                        row.setAttribute('data-customer-index', index);
                                        const isLast = index === customers.length - 1;
                                        row.innerHTML = `
                                            <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name" value="${this.escapeHtml(customer)}">
                                            ${isLast ? '<button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer"><i class="fas fa-plus"></i></button>' : ''}
                                            ${customers.length > 1 ? '<button type="button" class="btn-remove-customer" data-action="remove-customer" title="Remove Customer"><i class="fas fa-minus"></i></button>' : ''}
                                        `;
                                        customersContainer.appendChild(row);
                                    });
                                    // Re-setup event listeners
                                    this.setupCustomerFields('invoiceCustomersContainer', invoiceId);
                                } else if (customersContainer && inv.customer_name) {
                                    const firstInput = customersContainer.querySelector('.customer-name-input');
                                    if (firstInput) firstInput.value = inv.customer_name;
                                }
                                // Set debit and credit account IDs if available
                                if (inv.debit_account_id && form.querySelector('[name="debit_account_id"]')) {
                                    setTimeout(() => {
                                        const debitSelect = form.querySelector('[name="debit_account_id"]');
                                        if (debitSelect) debitSelect.value = inv.debit_account_id;
                                    }, 300);
                                }
                                if (inv.credit_account_id && form.querySelector('[name="credit_account_id"]')) {
                                    setTimeout(() => {
                                        const creditSelect = form.querySelector('[name="credit_account_id"]');
                                        if (creditSelect) creditSelect.value = inv.credit_account_id;
                                    }, 300);
                                }
                                // Payment Voucher (readonly): show saved value or "Auto-generated"
                                const pvInput = form.querySelector('#invoicePaymentVoucher');
                                if (pvInput) {
                                    pvInput.value = (inv.payment_voucher && inv.payment_voucher.trim() !== '') ? inv.payment_voucher : '';
                                    pvInput.placeholder = 'Auto-generated';
                                }
                                // Tax checkbox: checked when vat_report === 'tax_included'
                                const taxCb = form.querySelector('#invoiceTaxCheckbox');
                                const taxLabel = form.querySelector('#invoiceTaxLabel');
                                if (taxCb) {
                                    taxCb.checked = inv.vat_report === 'tax_included';
                                    if (taxLabel) taxLabel.textContent = taxCb.checked ? 'Tax included' : 'Tax not included';
                                }
                            }
                        }
                    } catch (err) {
                    }
                }
            } else {
                customerSelect.innerHTML = '<option value="">No customers found</option>';
            }
        } catch (error) {
            customerSelect.innerHTML = '<option value="">Error loading customers</option>';
        }
    }

ProfessionalAccounting.prototype.loadVendorsForSelect = async function(selector, billId = null) {
        const vendorSelect = document.getElementById(selector);
        if (!vendorSelect) {
            return;
        }
        
        vendorSelect.innerHTML = '<option value="">Loading vendors...</option>';
        
        try {
            const response = await fetch(`${this.apiBase}/vendors.php?is_active=1`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in loadVendorsForSelect:', jsonError);
                data = { success: false, vendors: [] };
            }
            
            if (data.success && data.vendors) {
                vendorSelect.innerHTML = '<option value="">Select Vendor</option>';
                data.vendors.forEach(vendor => {
                    const option = document.createElement('option');
                    option.value = vendor.id;
                    option.textContent = vendor.vendor_name || 'N/A';
                    vendorSelect.appendChild(option);
                });
                
                // If editing, load bill and set vendor
                if (billId) {
                    try {
                        const billResponse = await fetch(`${this.apiBase}/bills.php?id=${billId}`);
                        const billData = await billResponse.json();
                        if (billData.success && billData.bill) {
                            // Set vendor_id if available
                            if (billData.bill.vendor_id) {
                                vendorSelect.value = billData.bill.vendor_id;
                            }
                            // Populate form fields
                            const form = document.getElementById('billForm');
                            if (form && billData.bill) {
                                const bill = billData.bill;
                                if (form.querySelector('[name="bill_number"]')) {
                                    form.querySelector('[name="bill_number"]').value = bill.bill_number || '';
                                }
                                if (form.querySelector('[name="bill_date"]')) {
                                    form.querySelector('[name="bill_date"]').value = bill.bill_date || '';
                                }
                                if (form.querySelector('[name="due_date"]')) {
                                    form.querySelector('[name="due_date"]').value = bill.due_date || '';
                                }
                                if (form.querySelector('[name="total_amount"]')) {
                                    form.querySelector('[name="total_amount"]').value = bill.total_amount || 0;
                                }
                                if (form.querySelector('[name="currency"]')) {
                                    form.querySelector('[name="currency"]').value = bill.currency || this.getDefaultCurrencySync();
                                }
                                if (form.querySelector('[name="description"]')) {
                                    form.querySelector('[name="description"]').value = bill.description || '';
                                }
                            }
                        }
                    } catch (err) {
                    }
                }
            } else {
                vendorSelect.innerHTML = '<option value="">No vendors found</option>';
            }
        } catch (error) {
            vendorSelect.innerHTML = '<option value="">Error loading vendors</option>';
        }
    }

ProfessionalAccounting.prototype.loadChartOfAccounts = async function() {
        // Find tbody specifically in the modal (not in tab content)
        const modal = document.getElementById('chartOfAccountsModal');
        let tbody = modal ? modal.querySelector('#chartOfAccountsBody') : document.getElementById('chartOfAccountsBody');
        
        if (!tbody) {
            setTimeout(() => {
                const retryModal = document.getElementById('chartOfAccountsModal');
                const retryTbody = retryModal ? retryModal.querySelector('#chartOfAccountsBody') : document.getElementById('chartOfAccountsBody');
                if (retryTbody) {
                    this.loadChartOfAccounts();
                }
            }, 200);
            return;
        }
        
        tbody.innerHTML = '<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
        
        try {
            // Get filters and pagination settings - find within modal
            const modal = document.getElementById('chartOfAccountsModal');
            const accountTypeFilterEl = modal ? modal.querySelector('#coaAccountTypeFilter') : document.getElementById('coaAccountTypeFilter');
            const searchEl = modal ? modal.querySelector('#coaSearch') : document.getElementById('coaSearch');
            const accountTypeFilter = accountTypeFilterEl?.value || '';
            const search = searchEl?.value || '';
            const perPageEl = modal ? modal.querySelector('#coaPerPage') : document.getElementById('coaPerPage');
            this.coaPerPage = perPageEl ? parseInt(perPageEl.value) || 5 : this.coaPerPage;
            this.coaAccountTypeFilter = accountTypeFilter;
            this.coaSearch = search;
            
            let url = `${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`;
            if (accountTypeFilter && accountTypeFilter.trim() !== '') {
                url += `&account_type=${encodeURIComponent(accountTypeFilter)}`;
            }
            const response = await fetch(url, { credentials: 'include' });
            let data;
            try {
                data = await response.json();
            } catch (jsonErr) {
                data = { success: false, accounts: [] };
            }
            if (!response.ok) {
                throw new Error(data.message || data.error || `HTTP error! status: ${response.status}`);
            }
            
            // Re-check tbody after async operation
            const currentModal = document.getElementById('chartOfAccountsModal');
            tbody = currentModal ? currentModal.querySelector('#chartOfAccountsBody') : document.getElementById('chartOfAccountsBody');
            if (!tbody) {
                return;
            }
            
            if (data.success && data.accounts) {
                const allAccounts = [...data.accounts]; // Keep original for summary stats
                let filteredAccounts = [...data.accounts];
                
                // Filter by account type (client-side to ensure it works)
                if (accountTypeFilter && accountTypeFilter.trim() !== '') {
                    // Case-insensitive comparison for account_type (handle ENUM values)
                    const filterUpper = accountTypeFilter.toUpperCase();
                    filteredAccounts = filteredAccounts.filter(account => 
                        (account.account_type || '').toUpperCase() === filterUpper
                    );
                }
                
                // Filter by search term
                if (search) {
                    const searchLower = search.toLowerCase().trim();
                    if (searchLower !== '') {
                        const beforeFilterCount = filteredAccounts.length;
                        filteredAccounts = filteredAccounts.filter(account => {
                            const codeMatch = account.account_code && account.account_code.toLowerCase().includes(searchLower);
                            const nameMatch = account.account_name && account.account_name.toLowerCase().includes(searchLower);
                            const typeMatch = account.account_type && account.account_type.toLowerCase().includes(searchLower);
                            // Also search in entity_type if present
                            const entityMatch = account.entity_type && account.entity_type.toLowerCase().includes(searchLower);
                            return codeMatch || nameMatch || typeMatch || entityMatch;
                        });
                        // If search filtered out all accounts, show warning
                        if (beforeFilterCount > 0 && filteredAccounts.length === 0) {
                            console.warn(`[Chart of Accounts] Search "${search}" filtered out all ${beforeFilterCount} accounts. Clear search to see all accounts.`);
                        }
                    }
                }
                
                // Calculate summary statistics from ALL accounts (not filtered) for accurate totals
                const totalAccounts = allAccounts.length;
                const activeAccounts = allAccounts.filter(a => a.is_active).length;
                const inactiveAccounts = allAccounts.filter(a => !a.is_active).length;
                const totalBalance = allAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                
                // Calculate by account type from ALL accounts (for summary boxes)
                // Normalize account_type to uppercase for comparison (handle new ENUM values)
                const assetsAccounts = allAccounts.filter(a => (a.account_type || '').toUpperCase() === 'ASSET');
                const liabilitiesAccounts = allAccounts.filter(a => (a.account_type || '').toUpperCase() === 'LIABILITY');
                const equityAccounts = allAccounts.filter(a => (a.account_type || '').toUpperCase() === 'EQUITY');
                const incomeAccounts = allAccounts.filter(a => (a.account_type || '').toUpperCase() === 'REVENUE' || (a.account_type || '').toUpperCase() === 'INCOME');
                const expensesAccounts = allAccounts.filter(a => (a.account_type || '').toUpperCase() === 'EXPENSE');
                
                const assetsBalance = assetsAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                const liabilitiesBalance = liabilitiesAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                const equityBalance = equityAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                const incomeBalance = incomeAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                const expensesBalance = expensesAccounts.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
                
                // Get currency from first account (from all accounts, not filtered) or default from system settings
                const defaultCurrency = allAccounts.length > 0 && allAccounts[0].currency ? allAccounts[0].currency : this.getDefaultCurrencySync();
                
                // Update summary cards
                const totalAccountsEl = document.getElementById('modalCoaTotalAccounts');
                const activeEl = document.getElementById('modalCoaActive');
                const inactiveEl = document.getElementById('modalCoaInactive');
                const totalBalanceEl = document.getElementById('modalCoaTotalBalance');
                const assetsCountEl = document.getElementById('modalCoaAssetsCount');
                const assetsBalanceEl = document.getElementById('modalCoaAssetsBalance');
                const liabilitiesCountEl = document.getElementById('modalCoaLiabilitiesCount');
                const liabilitiesBalanceEl = document.getElementById('modalCoaLiabilitiesBalance');
                const equityCountEl = document.getElementById('modalCoaEquityCount');
                const equityBalanceEl = document.getElementById('modalCoaEquityBalance');
                const incomeCountEl = document.getElementById('modalCoaIncomeCount');
                const incomeBalanceEl = document.getElementById('modalCoaIncomeBalance');
                const expensesCountEl = document.getElementById('modalCoaExpensesCount');
                const expensesBalanceEl = document.getElementById('modalCoaExpensesBalance');
                
                if (totalAccountsEl) totalAccountsEl.textContent = totalAccounts;
                if (activeEl) activeEl.textContent = activeAccounts;
                if (inactiveEl) inactiveEl.textContent = inactiveAccounts;
                if (totalBalanceEl) totalBalanceEl.textContent = this.formatCurrency(totalBalance, defaultCurrency);
                if (assetsCountEl) assetsCountEl.textContent = assetsAccounts.length;
                if (assetsBalanceEl) assetsBalanceEl.textContent = this.formatCurrency(assetsBalance, defaultCurrency);
                if (liabilitiesCountEl) liabilitiesCountEl.textContent = liabilitiesAccounts.length;
                if (liabilitiesBalanceEl) liabilitiesBalanceEl.textContent = this.formatCurrency(liabilitiesBalance, defaultCurrency);
                if (equityCountEl) equityCountEl.textContent = equityAccounts.length;
                if (equityBalanceEl) equityBalanceEl.textContent = this.formatCurrency(equityBalance, defaultCurrency);
                if (incomeCountEl) incomeCountEl.textContent = incomeAccounts.length;
                if (incomeBalanceEl) incomeBalanceEl.textContent = this.formatCurrency(incomeBalance, defaultCurrency);
                if (expensesCountEl) expensesCountEl.textContent = expensesAccounts.length;
                if (expensesBalanceEl) expensesBalanceEl.textContent = this.formatCurrency(expensesBalance, defaultCurrency);
                
                // Sort accounts
                filteredAccounts.sort((a, b) => {
                    let aVal = a[this.coaSortColumn];
                    let bVal = b[this.coaSortColumn];
                    
                    // Handle numeric sorting
                    if (this.coaSortColumn === 'opening_balance' || this.coaSortColumn === 'current_balance') {
                        aVal = parseFloat(aVal || 0);
                        bVal = parseFloat(bVal || 0);
                    } else {
                        aVal = (aVal || '').toString().toLowerCase();
                        bVal = (bVal || '').toString().toLowerCase();
                    }
                    
                    if (this.coaSortDirection === 'asc') {
                        return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                    } else {
                        return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                    }
                });
                
                // Update total count (use filtered for pagination, but show total in summary)
                this.coaTotalCount = filteredAccounts.length;
                this.coaTotalAccountsUnfiltered = allAccounts.length; // Store unfiltered count
                this.coaTotalPages = this.coaPerPage > 0 ? Math.ceil(this.coaTotalCount / this.coaPerPage) : 1;
                if (this.coaCurrentPage > this.coaTotalPages && this.coaTotalPages > 0) {
                    this.coaCurrentPage = this.coaTotalPages;
                }
                
                // Paginate
                let paginatedAccounts = filteredAccounts;
                if (this.coaPerPage > 0) {
                    const startIndex = (this.coaCurrentPage - 1) * this.coaPerPage;
                    const endIndex = startIndex + this.coaPerPage;
                    paginatedAccounts = filteredAccounts.slice(startIndex, endIndex);
                }
                
                // Update table wrapper scrolling based on perPage
                const tableWrapper = document.getElementById('modalCoaTableWrapper');
                if (tableWrapper) {
                    if (this.coaPerPage > 5) {
                        tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                        tableWrapper.classList.add('modal-table-wrapper-scroll');
                    } else {
                        tableWrapper.classList.remove('modal-table-wrapper-scroll');
                        tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                    }
                }
                
                if (paginatedAccounts.length > 0) {
                    const html = paginatedAccounts.map(account => {
                        const isSelected = this.coaSelectedAccounts.has(account.id);
                        const openingBalance = parseFloat(account.opening_balance) || 0;
                        const currentBalance = parseFloat(account.current_balance) || 0;
                        const normalBalance = (account.normal_balance || 'DEBIT').toUpperCase();
                        const isDebitAccount = normalBalance === 'DEBIT';
                        const currency = account.currency || this.getDefaultCurrencySync();
                        
                        const entityType = account.entity_type ? account.entity_type.toLowerCase() : '';
                        const entityBadge = entityType ? `<span class="badge badge-info badge-small" title="${entityType.charAt(0).toUpperCase() + entityType.slice(1)} Account">${entityType.charAt(0).toUpperCase() + entityType.slice(1)}</span>` : '';
                        return `
                        <tr data-account-id="${account.id}" class="${isSelected ? 'row-selected' : ''}">
                            <td class="entry-number-cell">${this.escapeHtml(account.account_code || 'N/A')}</td>
                            <td class="entity-name-cell">${this.escapeHtml(account.account_name || 'N/A')} ${entityBadge}</td>
                            <td class="type-cell"><span class="type-badge type-badge-${(account.account_type || '').toLowerCase()}">${this.escapeHtml(account.account_type || 'N/A')}</span></td>
                            <td class="date-cell">${normalBalance}</td>
                            <td class="debit-cell amount-cell ${openingBalance > 0 ? 'has-amount' : ''}">${this.formatCurrency(openingBalance, currency)}</td>
                            <td class="${isDebitAccount ? 'debit-cell' : 'credit-cell'} amount-cell ${currentBalance !== 0 ? 'has-amount' : ''}">${this.formatCurrency(currentBalance, currency)}</td>
                            <td class="status-cell"><span class="status-badge status-${account.is_active ? 'active' : 'inactive'}">${account.is_active ? 'Active' : 'Inactive'}</span></td>
                            <td class="checkbox-column">
                                <input type="checkbox" class="coa-row-checkbox" data-account-id="${account.id}" ${isSelected ? 'checked' : ''}>
                            </td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button class="action-btn view" data-action="view-account" data-id="${account.id}" title="View Account">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" data-action="edit-account" data-id="${account.id}" data-permission="edit_account" title="Edit Account">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn print" data-action="print-account" data-id="${account.id}" title="Print Account">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="action-btn delete" data-action="delete-account" data-id="${account.id}" data-permission="delete_account" title="Delete Account">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    }).join('');
                    tbody.innerHTML = html;
                    
                    // Update pagination
                    this.updateCoaPagination();
                    this.updateCoaBulkActions();
                    
                    // Set data-per-page attribute for CSS targeting
                    const tableWrapper = modal ? modal.querySelector('.coa-table-wrapper') : null;
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', this.coaPerPage.toString());
                    }
                    
                    // Re-setup filters after content loads to ensure listeners are attached
                    setTimeout(() => this.setupChartOfAccountsFilters(), 100);
            } else {
                    const searchMsg = search ? `<p class="accounting-empty-state-hint"><i class="fas fa-info-circle"></i> Search filter "${search}" returned no results. <button type="button" class="btn-clear-coa-search" data-action="clear-coa-search">Clear Search</button></p>` : '';
                    const accountTypeMsg = accountTypeFilter ? `<p class="accounting-empty-state-hint"><i class="fas fa-info-circle"></i> Account type filter "${accountTypeFilter}" is active.</p>` : '';
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-sitemap accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No accounts found</p>${searchMsg}${accountTypeMsg}</div></td></tr>`;
                    this.updateCoaPagination();
                    this.updateCoaBulkActions();
                    
                    // Reset summary cards
                    const defaultCurrency = this.getDefaultCurrencySync();
                    const totalAccountsEl = document.getElementById('modalCoaTotalAccounts');
                    const activeEl = document.getElementById('modalCoaActive');
                    const inactiveEl = document.getElementById('modalCoaInactive');
                    const totalBalanceEl = document.getElementById('modalCoaTotalBalance');
                    const assetsCountEl = document.getElementById('modalCoaAssetsCount');
                    const assetsBalanceEl = document.getElementById('modalCoaAssetsBalance');
                    const liabilitiesCountEl = document.getElementById('modalCoaLiabilitiesCount');
                    const liabilitiesBalanceEl = document.getElementById('modalCoaLiabilitiesBalance');
                    const equityCountEl = document.getElementById('modalCoaEquityCount');
                    const equityBalanceEl = document.getElementById('modalCoaEquityBalance');
                    const incomeCountEl = document.getElementById('modalCoaIncomeCount');
                    const incomeBalanceEl = document.getElementById('modalCoaIncomeBalance');
                    const expensesCountEl = document.getElementById('modalCoaExpensesCount');
                    const expensesBalanceEl = document.getElementById('modalCoaExpensesBalance');
                    
                    if (totalAccountsEl) totalAccountsEl.textContent = '0';
                    if (activeEl) activeEl.textContent = '0';
                    if (inactiveEl) inactiveEl.textContent = '0';
                    if (totalBalanceEl) totalBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (assetsCountEl) assetsCountEl.textContent = '0';
                    if (assetsBalanceEl) assetsBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (liabilitiesCountEl) liabilitiesCountEl.textContent = '0';
                    if (liabilitiesBalanceEl) liabilitiesBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (equityCountEl) equityCountEl.textContent = '0';
                    if (equityBalanceEl) equityBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (incomeCountEl) incomeCountEl.textContent = '0';
                    if (incomeBalanceEl) incomeBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (expensesCountEl) expensesCountEl.textContent = '0';
                    if (expensesBalanceEl) expensesBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-sitemap accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No accounts found</p></div></td></tr>';
                this.coaTotalCount = 0;
                this.coaTotalPages = 0;
                this.updateCoaPagination();
                this.updateCoaBulkActions();
                
                // Reset summary cards
                const defaultCurrency = this.getDefaultCurrencySync();
                const totalAccountsEl = document.getElementById('modalCoaTotalAccounts');
                const activeEl = document.getElementById('modalCoaActive');
                const inactiveEl = document.getElementById('modalCoaInactive');
                const totalBalanceEl = document.getElementById('modalCoaTotalBalance');
                const assetsCountEl = document.getElementById('modalCoaAssetsCount');
                const assetsBalanceEl = document.getElementById('modalCoaAssetsBalance');
                const liabilitiesCountEl = document.getElementById('modalCoaLiabilitiesCount');
                const liabilitiesBalanceEl = document.getElementById('modalCoaLiabilitiesBalance');
                const equityCountEl = document.getElementById('modalCoaEquityCount');
                const equityBalanceEl = document.getElementById('modalCoaEquityBalance');
                const incomeCountEl = document.getElementById('modalCoaIncomeCount');
                const incomeBalanceEl = document.getElementById('modalCoaIncomeBalance');
                const expensesCountEl = document.getElementById('modalCoaExpensesCount');
                const expensesBalanceEl = document.getElementById('modalCoaExpensesBalance');
                
                if (totalAccountsEl) totalAccountsEl.textContent = '0';
                if (activeEl) activeEl.textContent = '0';
                if (inactiveEl) inactiveEl.textContent = '0';
                if (totalBalanceEl) totalBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                if (assetsCountEl) assetsCountEl.textContent = '0';
                if (assetsBalanceEl) assetsBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                if (liabilitiesCountEl) liabilitiesCountEl.textContent = '0';
                if (liabilitiesBalanceEl) liabilitiesBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                if (equityCountEl) equityCountEl.textContent = '0';
                if (equityBalanceEl) equityBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                if (incomeCountEl) incomeCountEl.textContent = '0';
                if (incomeBalanceEl) incomeBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                if (expensesCountEl) expensesCountEl.textContent = '0';
                if (expensesBalanceEl) expensesBalanceEl.textContent = this.formatCurrency(0, defaultCurrency);
            }
        } catch (error) {
            const errorMsg = error.message || 'Unknown error occurred';
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">
                <div class="accounting-empty-state">
                    <i class="fas fa-exclamation-triangle accounting-empty-state-icon"></i>
                    <p class="accounting-empty-state-text">Error loading accounts: ${this.escapeHtml(errorMsg)}</p>
                    <button class="btn btn-sm btn-primary" data-action="retry-load-chart-of-accounts">Retry</button>
                </div>
            </td></tr>`;
            
            // Add event listener for retry button
            const retryBtn = tbody.querySelector('[data-action="retry-load-chart-of-accounts"]');
            if (retryBtn) {
                retryBtn.addEventListener('click', () => {
                    this.loadChartOfAccounts();
                });
            }
        } finally {
            // Apply permissions after table is rendered
            setTimeout(() => {
                const finalModal = document.getElementById('chartOfAccountsModal');
                const finalTbody = finalModal ? finalModal.querySelector('#chartOfAccountsBody') : document.getElementById('chartOfAccountsBody');
                if (finalTbody && finalTbody.children.length > 0 && !finalTbody.innerHTML.includes('Loading')) {
                if (window.UserPermissions && window.UserPermissions.loaded) {
                    window.UserPermissions.applyPermissions();
                    }
                }
            }, 50);
        }
    }


ProfessionalAccounting.prototype.openAccountModal = function(accountId = null) {
        const isEdit = !!accountId;
        const content = `
            <form id="accountForm" data-account-id="${accountId || 'null'}">
                <div class="accounting-modal-form-group">
                    <label>Account Code *</label>
                    <input type="text" name="account_code" id="accountCodeInput" required ${isEdit ? '' : 'readonly'} placeholder="Auto-generated" title="${isEdit ? 'Account code' : 'Account code is auto-generated'}">
                    ${isEdit ? '' : '<small class="form-help-text">Code will be auto-generated based on account type</small>'}
                </div>
                <div class="accounting-modal-form-group">
                    <label>Account Name *</label>
                    <input type="text" name="account_name" required placeholder="e.g., Cash">
                </div>
                <div class="accounting-modal-form-group">
                    <label>Account Type *</label>
                    <select name="account_type" id="accountTypeSelect" required>
                        <option value="ASSET">Asset</option>
                        <option value="LIABILITY">Liability</option>
                        <option value="EQUITY">Equity</option>
                        <option value="REVENUE">Revenue</option>
                        <option value="EXPENSE">Expense</option>
                    </select>
                </div>
                <div class="accounting-modal-form-group">
                    <label>Normal Balance *</label>
                    <select name="normal_balance" required>
                        <option value="DEBIT">Debit</option>
                        <option value="CREDIT">Credit</option>
                    </select>
                </div>
                <div class="accounting-modal-form-group">
                    <label>Opening Balance</label>
                    <input type="number" name="opening_balance" step="0.01" value="0.00" placeholder="0.00">
                </div>
                <div class="accounting-modal-form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Account description"></textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Account</button>
                </div>
            </form>
        `;
        this.showModal(isEdit ? 'Edit Account' : 'Create Account', content);
        // Load account data if editing
        if (accountId) {
            setTimeout(async () => {
                try {
                    const response = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`);
                    let data;
                    try {
                        data = await response.json();
                    } catch (jsonError) {
                        console.error('Error parsing response when loading account data:', jsonError);
                        return; // Silently fail if JSON parsing fails
                    }
                    if (data.success && data.account) {
                        const modal = document.getElementById('accountFormModal');
                        const form = modal ? modal.querySelector('#accountForm') : document.getElementById('accountForm');
                        if (form) {
                            form.querySelector('[name="account_code"]').value = data.account.account_code || '';
                            form.querySelector('[name="account_name"]').value = data.account.account_name || '';
                            // Normalize to uppercase for ENUM values
                            const accountType = (data.account.account_type || 'ASSET').toUpperCase();
                            const normalBalance = (data.account.normal_balance || 'DEBIT').toUpperCase();
                            form.querySelector('[name="account_type"]').value = accountType;
                            form.querySelector('[name="normal_balance"]').value = normalBalance;
                            form.querySelector('[name="opening_balance"]').value = data.account.opening_balance || 0;
                            form.querySelector('[name="description"]').value = data.account.description || '';
                        }
                    }
                } catch (error) {
                }
            }, 100);
        }
        // Add form submit handler and auto-generate account code
        setTimeout(async () => {
            const modal = document.getElementById('accountFormModal');
            const form = modal ? modal.querySelector('#accountForm') : document.getElementById('accountForm');
            if (!form) {
                console.error('Account form not found. Modal ID:', modal?.id, 'Form ID:', document.getElementById('accountForm')?.id);
                this.showToast('Form not found. Please try again.', 'error');
                return;
            }
            
            // Remove any existing listeners by cloning
            const newForm = form.cloneNode(true);
            form.parentNode.replaceChild(newForm, form);
            
            // Auto-generate account code for new accounts
            if (!accountId) {
                const accountTypeSelect = newForm.querySelector('#accountTypeSelect');
                const accountCodeInput = newForm.querySelector('#accountCodeInput');
                
                // Generate code when account type changes
                if (accountTypeSelect && accountCodeInput) {
                    accountTypeSelect.addEventListener('change', async () => {
                        await this.generateAccountCode(accountTypeSelect.value, accountCodeInput);
                    });
                    
                    // Generate initial code
                    await this.generateAccountCode(accountTypeSelect.value, accountCodeInput);
                }
            }
            
            newForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const submitBtn = newForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.textContent : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = accountId ? 'Updating...' : 'Creating...';
                }
                let saveSucceeded = false;
                try {
                    const result = await this.saveAccount(accountId);
                    if (result === true) {
                        saveSucceeded = true;
                    }
                } catch (error) {
                    console.error('Error saving account:', error);
                } finally {
                    // Only re-enable button if save failed (modal will close on success)
                    if (!saveSucceeded && submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText || (accountId ? 'Update Account' : 'Create Account');
                    }
                }
            });
            
            // Also handle close modal button
            const closeBtn = modal ? modal.querySelector('[data-action="close-modal"]') : null;
            if (closeBtn) {
                closeBtn.addEventListener('click', async () => {
                    await this.closeModalWithConfirmation();
                });
            }
            
            // Track form changes
            const formInputs = newForm.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.markFormAsChanged(newForm);
                });
                input.addEventListener('input', () => {
                    this.markFormAsChanged(newForm);
                });
            });
        }, 150);
    }

ProfessionalAccounting.prototype.generateAccountCode = async function(accountType, codeInput) {
        try {
            // Get all accounts to find the highest code for this type
            const response = await fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`, { credentials: 'include' });
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in generateAccountCode:', jsonError);
                data = { success: false, accounts: [] };
            }
            
            if (data.success && data.accounts) {
                // Define base codes for each account type
                const baseCodes = {
                    'Asset': 1000,
                    'Liability': 2000,
                    'Equity': 3000,
                    'Income': 4000,
                    'Expense': 5000
                };
                
                const baseCode = baseCodes[accountType] || 1000;
                
                // Find the highest code for this account type (case-insensitive comparison)
                const accountTypeUpper = (accountType || '').toUpperCase();
                const typeAccounts = data.accounts.filter(acc => (acc.account_type || '').toUpperCase() === accountTypeUpper);
                let maxCode = baseCode;
                
                typeAccounts.forEach(account => {
                    const code = parseInt(account.account_code);
                    if (!isNaN(code) && code >= baseCode && code < baseCode + 1000) {
                        maxCode = Math.max(maxCode, code);
                    }
                });
                
                // Generate next code (increment by 100 for main accounts)
                const nextCode = maxCode + 100;
                codeInput.value = nextCode.toString();
            } else {
                // Fallback: use base code
                const baseCodes = {
                    'Asset': 1000,
                    'Liability': 2000,
                    'Equity': 3000,
                    'Income': 4000,
                    'Expense': 5000
                };
                codeInput.value = (baseCodes[accountType] || 1000).toString();
            }
        } catch (error) {
            // Fallback: use base code
            const baseCodes = {
                'Asset': 1000,
                'Liability': 2000,
                'Equity': 3000,
                'Income': 4000,
                'Expense': 5000
            };
            codeInput.value = (baseCodes[accountType] || 1000).toString();
        }
    }

ProfessionalAccounting.prototype.saveAccount = async function(accountId = null) {
        const modal = document.getElementById('accountFormModal');
        const form = modal ? modal.querySelector('#accountForm') : document.getElementById('accountForm');
        if (!form) {
            this.showToast('Form not found', 'error');
            return false;
        }
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        try {
            const url = accountId ? `${this.apiBase}/accounts.php?id=${accountId}` : `${this.apiBase}/accounts.php`;
            const response = await fetch(url, {
                method: accountId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            let responseData;
            const responseText = await response.text();
            try {
                responseData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse API response:', responseText);
                throw new Error(`Invalid JSON response from server. Status: ${response.status}. Response: ${responseText.substring(0, 200)}`);
            }
            
            if (response.ok && responseData.success !== false) {
                this.markFormAsSaved(form);
                setTimeout(() => {
                    this.showToast(`Account ${accountId ? 'updated' : 'created'} successfully!`, 'success', 4000);
                }, 100);
                setTimeout(() => {
                this.closeModal();
                }, 500);
                // Reload Chart of Accounts if modal is open
                if (document.getElementById('chartOfAccountsBody')) {
                    this.loadChartOfAccounts();
                }
                return true;
            } else {
                const errorMsg = responseData.message || 'Failed to save account. Please try again.';
                this.showToast(errorMsg, 'error', 6000);
                console.error('Account save error:', responseData);
                throw new Error(errorMsg);
            }
        } catch (error) {
            const errorMsg = error.message || 'Error saving account. The API may not be available yet.';
            this.showToast(errorMsg, 'error');
            console.error('Account save exception:', error);
            throw error;
        }
    }

ProfessionalAccounting.prototype.deleteAccount = async function(accountId) {
        try {
            const response = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`, {
                method: 'DELETE'
            });
            
            const responseData = await response.json().catch(() => ({ success: false, message: 'Invalid response' }));
            
            if (response.ok && responseData.success !== false) {
                this.showToast('Account deleted successfully!', 'success');
                this.coaSelectedAccounts.delete(parseInt(accountId));
                this.loadChartOfAccounts();
            } else {
                this.showToast(responseData.message || 'Failed to delete account.', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting account.', 'error');
        }
    }
    
ProfessionalAccounting.prototype.bulkDeleteAccounts = async function(accountIds) {
        if (!accountIds || accountIds.length === 0) return;
        
        try {
            let successCount = 0;
            let failCount = 0;
            
            for (const accountId of accountIds) {
                try {
                    const response = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (response.ok && data.success !== false) {
                        successCount++;
                        this.coaSelectedAccounts.delete(accountId);
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    failCount++;
                }
            }
            
            if (successCount > 0) {
                this.showToast(`${successCount} account(s) deleted successfully${failCount > 0 ? `, ${failCount} failed` : ''}`, successCount === accountIds.length ? 'success' : 'warning');
                this.loadChartOfAccounts();
            } else {
                this.showToast('Failed to delete accounts', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting accounts', 'error');
        }
    }
    
ProfessionalAccounting.prototype.bulkUpdateAccountsStatus = async function(accountIds, isActive) {
        if (!accountIds || accountIds.length === 0) return;
        
        try {
            let successCount = 0;
            let failCount = 0;
            
            for (const accountId of accountIds) {
                try {
                    // First get the account
                    const getResponse = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`);
                    const getData = await getResponse.json();
                    
                    if (getData.success && getData.account) {
                        const account = getData.account;
                        const updateData = {
                            account_code: account.account_code,
                            account_name: account.account_name,
                            account_type: account.account_type,
                            normal_balance: (account.normal_balance || 'DEBIT').toUpperCase(),
                            description: account.description || '',
                            is_active: isActive ? 1 : 0
                        };
                        
                        const response = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(updateData)
                        });
                        
                        const data = await response.json();
                        if (response.ok && data.success !== false) {
                            successCount++;
                        } else {
                            failCount++;
                        }
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    failCount++;
                }
            }
            
            if (successCount > 0) {
                this.showToast(`${successCount} account(s) ${isActive ? 'activated' : 'deactivated'} successfully${failCount > 0 ? `, ${failCount} failed` : ''}`, successCount === accountIds.length ? 'success' : 'warning');
                this.loadChartOfAccounts();
            } else {
                this.showToast(`Failed to ${isActive ? 'activate' : 'deactivate'} accounts`, 'error');
            }
        } catch (error) {
            this.showToast('Error updating account status', 'error');
        }
    }
    

ProfessionalAccounting.prototype.bulkExportAccounts = function(accountIds) {
        if (!accountIds || accountIds.length === 0) return;
        
        // Get all accounts data and filter by selected IDs
        const url = `${this.apiBase}/accounts.php?is_active=1`;
        fetch(url, { credentials: 'include' })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.accounts) {
                    const selectedAccounts = data.accounts.filter(acc => accountIds.includes(acc.id));
                    this.exportAccountsToCSV(selectedAccounts);
                }
            })
            .catch(error => {
                this.showToast('Error exporting accounts', 'error');
            });
    }
    

ProfessionalAccounting.prototype.exportAccountsToCSV = function(accounts) {
        if (!accounts || accounts.length === 0) {
            this.showToast('No accounts to export', 'error');
            return;
        }
        
        const headers = ['Code', 'Name', 'Type', 'Normal', 'Opening', 'Balance', 'Currency', 'Status', 'Description'];
        const rows = accounts.map(account => [
            account.account_code || '',
            account.account_name || '',
            account.account_type || '',
            (account.normal_balance || 'DEBIT').toUpperCase(),
            account.opening_balance || 0,
            account.current_balance || 0,
            account.currency || this.getDefaultCurrencySync(),
            account.is_active ? 'Active' : 'Inactive',
            account.description || ''
        ]);
        
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `chart-of-accounts-${new Date().toISOString().split('T')[0]}.csv`);
        link.classList.add('coa-download-link-hidden');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showToast(`${accounts.length} account(s) exported successfully`, 'success');
    }

    // Pagination Update Functions

ProfessionalAccounting.prototype.updateModalArPagination = function(totalEntries, currentPage, perPage) {
        const totalPages = Math.ceil(totalEntries / perPage);
        const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const endIndex = Math.min(currentPage * perPage, totalEntries);

        // Only update top pagination (matching General Ledger style)
        const infoEl = document.getElementById('modalArPaginationInfoTop');
        const firstBtn = document.getElementById('modalArFirstTop');
        const prevBtn = document.getElementById('modalArPrevTop');
        const nextBtn = document.getElementById('modalArNextTop');
        const lastBtn = document.getElementById('modalArLastTop');
        const pageNumbersEl = document.getElementById('modalArPageNumbersTop');

            if (infoEl) {
                infoEl.textContent = totalEntries > 0 
                ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} invoices`
                : 'No invoices found';
        }

        // Update navigation buttons
        const isFirstPage = currentPage <= 1;
        const isLastPage = currentPage >= totalPages;

        if (firstBtn) {
            firstBtn.disabled = isFirstPage;
            firstBtn.classList.toggle('disabled', isFirstPage);
            firstBtn.setAttribute('data-page', '1');
            }

            if (prevBtn) {
            prevBtn.disabled = isFirstPage;
            prevBtn.classList.toggle('disabled', isFirstPage);
            }

            if (nextBtn) {
            nextBtn.disabled = isLastPage;
            nextBtn.classList.toggle('disabled', isLastPage);
        }

        if (lastBtn) {
            lastBtn.disabled = isLastPage;
            lastBtn.classList.toggle('disabled', isLastPage);
            lastBtn.setAttribute('data-page', totalPages.toString());
            }

            if (pageNumbersEl) {
                let pageNumbersHTML = '';
            
            if (totalPages <= 1) {
                pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
            } else if (totalPages <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= totalPages; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ar-page" data-page="${i}">${i}</button>`;
                }
            } else {
                // Smart pagination for many pages
                let startPage, endPage;
                
                if (currentPage <= 3) {
                    startPage = 1;
                    endPage = 5;
                } else if (currentPage >= totalPages - 2) {
                    startPage = totalPages - 4;
                    endPage = totalPages;
                } else {
                    startPage = currentPage - 2;
                    endPage = currentPage + 2;
                }
                
                if (startPage > 1) {
                    pageNumbersHTML += `<button class="page-number" data-action="modal-ar-page" data-page="1">1</button>`;
                    if (startPage > 2) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ar-page" data-page="${i}">${i}</button>`;
                }
                
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                    pageNumbersHTML += `<button class="page-number" data-action="modal-ar-page" data-page="${totalPages}">${totalPages}</button>`;
                }
            }
            
                pageNumbersEl.innerHTML = pageNumbersHTML;
            }
    }


ProfessionalAccounting.prototype.updateModalApPagination = function(totalEntries, currentPage, perPage) {
        const totalPages = Math.ceil(totalEntries / perPage);
        const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const endIndex = Math.min(currentPage * perPage, totalEntries);

        ['Top', 'Bottom'].forEach(position => {
            const infoEl = document.getElementById(`modalApPaginationInfo${position}`);
            const prevBtn = document.getElementById(`modalApPrev${position}`);
            const nextBtn = document.getElementById(`modalApNext${position}`);
            const pageNumbersEl = document.getElementById(`modalApPageNumbers${position}`);

            if (infoEl) {
                infoEl.textContent = totalEntries > 0 
                    ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                    : 'No entries found';
            }

            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
                prevBtn.classList.toggle('disabled', currentPage <= 1);
            }

            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages;
                nextBtn.classList.toggle('disabled', currentPage >= totalPages);
            }

            if (pageNumbersEl) {
                let pageNumbersHTML = '';
                const maxVisible = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                
                if (endPage - startPage < maxVisible - 1) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage ? 'active' : '';
                    pageNumbersHTML += `<button class="page-btn ${isActive}" data-action="modal-ap-page" data-page="${i}">${i}</button>`;
                }
                pageNumbersEl.innerHTML = pageNumbersHTML;
            }
        });
    }


ProfessionalAccounting.prototype.updateModalBankPagination = function(totalEntries, currentPage, perPage) {
        const totalPages = Math.ceil(totalEntries / perPage);
        const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const endIndex = Math.min(currentPage * perPage, totalEntries);

        // Only update top pagination (matching General Ledger style)
        const infoEl = document.getElementById('modalBankPaginationInfoTop');
        const firstBtn = document.getElementById('modalBankFirstTop');
        const prevBtn = document.getElementById('modalBankPrevTop');
        const nextBtn = document.getElementById('modalBankNextTop');
        const lastBtn = document.getElementById('modalBankLastTop');
        const pageNumbersEl = document.getElementById('modalBankPageNumbersTop');

            if (infoEl) {
                infoEl.textContent = totalEntries > 0 
                ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} transactions`
                : 'No transactions found';
        }

        // Update navigation buttons
        const isFirstPage = currentPage <= 1;
        const isLastPage = currentPage >= totalPages;

        if (firstBtn) {
            firstBtn.disabled = isFirstPage;
            firstBtn.classList.toggle('disabled', isFirstPage);
            firstBtn.setAttribute('data-page', '1');
            }

            if (prevBtn) {
            prevBtn.disabled = isFirstPage;
            prevBtn.classList.toggle('disabled', isFirstPage);
            }

            if (nextBtn) {
            nextBtn.disabled = isLastPage;
            nextBtn.classList.toggle('disabled', isLastPage);
        }

        if (lastBtn) {
            lastBtn.disabled = isLastPage;
            lastBtn.classList.toggle('disabled', isLastPage);
            lastBtn.setAttribute('data-page', totalPages.toString());
            }

            if (pageNumbersEl) {
                let pageNumbersHTML = '';
            
            if (totalPages <= 1) {
                pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
            } else if (totalPages <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= totalPages; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-bank-page" data-page="${i}">${i}</button>`;
                }
            } else {
                // Smart pagination for many pages
                let startPage, endPage;
                
                if (currentPage <= 3) {
                    startPage = 1;
                    endPage = 5;
                } else if (currentPage >= totalPages - 2) {
                    startPage = totalPages - 4;
                    endPage = totalPages;
                } else {
                    startPage = currentPage - 2;
                    endPage = currentPage + 2;
                }
                
                if (startPage > 1) {
                    pageNumbersHTML += `<button class="page-number" data-action="modal-bank-page" data-page="1">1</button>`;
                    if (startPage > 2) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-bank-page" data-page="${i}">${i}</button>`;
                }
                
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                    pageNumbersHTML += `<button class="page-number" data-action="modal-bank-page" data-page="${totalPages}">${totalPages}</button>`;
                }
            }
            
                pageNumbersEl.innerHTML = pageNumbersHTML;
            }
    }


ProfessionalAccounting.prototype.updateModalEntityPagination = function(totalEntries, currentPage, perPage) {
        const totalPages = Math.ceil(totalEntries / perPage);
        const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const endIndex = Math.min(currentPage * perPage, totalEntries);

        ['Top', 'Bottom'].forEach(position => {
            const infoEl = document.getElementById(`modalEntityPaginationInfo${position}`);
            const prevBtn = document.getElementById(`modalEntityPrev${position}`);
            const nextBtn = document.getElementById(`modalEntityNext${position}`);
            const pageNumbersEl = document.getElementById(`modalEntityPageNumbers${position}`);

            if (infoEl) {
                infoEl.textContent = totalEntries > 0 
                    ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                    : 'No entries found';
            }

            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
                prevBtn.classList.toggle('disabled', currentPage <= 1);
            }

            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages;
                nextBtn.classList.toggle('disabled', currentPage >= totalPages);
            }

            if (pageNumbersEl) {
                let pageNumbersHTML = '';
                const maxVisible = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                
                if (endPage - startPage < maxVisible - 1) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage ? 'active' : '';
                    pageNumbersHTML += `<button class="page-btn ${isActive}" data-action="modal-entity-page" data-page="${i}">${i}</button>`;
                }
                pageNumbersEl.innerHTML = pageNumbersHTML;
            }
        });
    }


ProfessionalAccounting.prototype.updateModalLedgerPagination = function(totalEntries, currentPage, perPage) {
        const totalPages = Math.ceil(totalEntries / perPage);
        const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const endIndex = Math.min(currentPage * perPage, totalEntries);

        // Only update top pagination
        const infoEl = document.getElementById('modalLedgerPaginationInfoTop');
        const firstBtn = document.getElementById('modalLedgerFirstTop');
        const prevBtn = document.getElementById('modalLedgerPrevTop');
        const nextBtn = document.getElementById('modalLedgerNextTop');
        const lastBtn = document.getElementById('modalLedgerLastTop');
        const pageNumbersEl = document.getElementById('modalLedgerPageNumbersTop');

            if (infoEl) {
                infoEl.textContent = totalEntries > 0 
                    ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                    : 'No entries found';
            }

        // Update navigation buttons
        const isFirstPage = currentPage <= 1;
        const isLastPage = currentPage >= totalPages;

        if (firstBtn) {
            firstBtn.disabled = isFirstPage;
            firstBtn.classList.toggle('disabled', isFirstPage);
            firstBtn.setAttribute('data-page', '1');
        }

            if (prevBtn) {
            prevBtn.disabled = isFirstPage;
            prevBtn.classList.toggle('disabled', isFirstPage);
            }

            if (nextBtn) {
            nextBtn.disabled = isLastPage;
            nextBtn.classList.toggle('disabled', isLastPage);
        }

        if (lastBtn) {
            lastBtn.disabled = isLastPage;
            lastBtn.classList.toggle('disabled', isLastPage);
            lastBtn.setAttribute('data-page', totalPages.toString());
            }

            if (pageNumbersEl) {
                let pageNumbersHTML = '';
            
            if (totalPages <= 1) {
                pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
            } else if (totalPages <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= totalPages; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ledger-page" data-page="${i}">${i}</button>`;
                }
            } else {
                // Smart pagination for many pages
                let startPage, endPage;
                
                if (currentPage <= 3) {
                    // Near the beginning
                    startPage = 1;
                    endPage = 5;
                } else if (currentPage >= totalPages - 2) {
                    // Near the end
                    startPage = totalPages - 4;
                    endPage = totalPages;
                } else {
                    // In the middle
                    startPage = currentPage - 2;
                    endPage = currentPage + 2;
                }
                
                // Always show first page
                if (startPage > 1) {
                    pageNumbersHTML += `<button class="page-number" data-action="modal-ledger-page" data-page="1">1</button>`;
                    if (startPage > 2) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                }
                
                // Show page range
                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage ? 'page-number-current' : '';
                    pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ledger-page" data-page="${i}">${i}</button>`;
                }
                
                // Always show last page
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                    }
                    pageNumbersHTML += `<button class="page-number" data-action="modal-ledger-page" data-page="${totalPages}">${totalPages}</button>`;
                }
            }
            
                pageNumbersEl.innerHTML = pageNumbersHTML;
            }
    }

    // Bulk Actions Functions

ProfessionalAccounting.prototype.updateBulkActions = function(type) {
        let checkboxes, bulkBar, countEl, tableId;
        
        switch(type) {
            case 'ar':
                tableId = 'modalInvoicesTable';
                bulkBar = document.getElementById('bulkActionsAr');
                countEl = document.getElementById('bulkSelectedCountAr');
                break;
            case 'ap':
                tableId = 'modalBillsTable';
                bulkBar = document.getElementById('bulkActionsAp');
                countEl = document.getElementById('bulkSelectedCountAp');
                break;
            case 'bank':
                tableId = 'modalBankAccountsTable';
                bulkBar = document.getElementById('bulkActionsBank');
                countEl = document.getElementById('bulkSelectedCountBank');
                break;
            case 'entity':
                tableId = 'modalEntityTransactionsTable';
                bulkBar = document.getElementById('bulkActionsEntity');
                countEl = document.getElementById('bulkSelectedCountEntity');
                break;
            case 'ledger':
                tableId = 'modalJournalEntriesTable';
                bulkBar = document.getElementById('bulkActionsLedger');
                countEl = document.getElementById('bulkSelectedCountLedger');
                break;
            default:
                return;
        }
        
        checkboxes = document.querySelectorAll(`#${tableId} tbody input[type="checkbox"]:checked`);
        const count = checkboxes.length;
        
        if (countEl) {
            countEl.textContent = `${count} selected`;
        }
        
        if (bulkBar) {
            bulkBar.classList.toggle('show', count > 0);
        }
    }


ProfessionalAccounting.prototype.getSelectedIds = function(type) {
        let tableId;
        switch(type) {
            case 'ar':
                tableId = 'modalInvoicesTable';
                break;
            case 'ap':
                tableId = 'modalBillsTable';
                break;
            case 'bank':
                tableId = 'modalBankAccountsTable';
                break;
            case 'entity':
                tableId = 'modalEntityTransactionsTable';
                break;
            case 'ledger':
                tableId = 'modalJournalEntriesTable';
                break;
            default:
                return [];
        }
        
        const checkboxes = document.querySelectorAll(`#${tableId} tbody input[type="checkbox"]:checked`);
        return Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
    }

ProfessionalAccounting.prototype.bulkDeleteJournalEntries = async function() {
        const ids = this.getSelectedIds('ledger');
        
        if (ids.length === 0) {
            this.showToast('Please select at least one entry', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Journal Entries',
            `Are you sure you want to delete ${ids.length} journal entry/entries?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            let deleted = 0;
            let failed = 0;
            
            
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/journal-entries.php?id=${id}`, {
                        method: 'DELETE',
                        cache: 'no-store',
                        headers: {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache'
                        }
                    });
                    
                    let data;
                    try {
                        data = await response.json();
                    } catch (jsonError) {
                        const text = await response.text();
                        failed++;
                        continue;
                    }
                    
                    if (response.ok && data.success) {
                        deleted++;
                    } else {
                        failed++;
                        const errorMsg = data?.message || data?.error || `HTTP ${response.status}: ${response.statusText}`;
                        // Show specific error in toast for first failure
                        if (failed === 1) {
                            this.showToast(`Delete failed: ${errorMsg}`, 'error');
                        }
                    }
        } catch (error) {
                    failed++;
                }
            }
            
            
            if (deleted > 0) {
                this.showToast(`Deleted ${deleted} entry/entries successfully${failed > 0 ? `, ${failed} failed` : ''}`, deleted === ids.length ? 'success' : 'warning');
                
                // Clear checkboxes after deletion
                const checkboxes = document.querySelectorAll('input[type="checkbox"][data-id]');
                checkboxes.forEach(cb => cb.checked = false);
                
                // Wait a bit for database commit, then refresh both modal and main table
                setTimeout(async () => {
                    await this.loadModalJournalEntries();
                    await new Promise(resolve => setTimeout(resolve, 200));
                    await this.loadJournalEntries();
                }, 500);
            } else {
                this.showToast('Failed to delete entries', 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete journal entries', 'error');
        }
    }

ProfessionalAccounting.prototype.deleteJournalEntry = async function(id) {
        if (!id) {
            this.showToast('Invalid entry ID', 'error');
            return;
        }

        const confirmed = await this.showConfirmDialog(
            'Delete Journal Entry',
            'Are you sure you want to delete this journal entry?',
            'Delete',
            'Cancel',
            'danger'
        );

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/journal-entries.php?id=${id}`, {
                method: 'DELETE',
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            });

            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                const text = await response.text();
                this.showToast('Failed to delete entry: Invalid response', 'error');
                return;
            }

            if (response.ok && data.success) {
                this.showToast('Entry deleted successfully', 'success');
                
                // Wait a bit for database commit, then refresh both modal and main table
                setTimeout(async () => {
                    await this.loadModalJournalEntries();
                    await new Promise(resolve => setTimeout(resolve, 200));
                    if (typeof this.loadJournalEntries === 'function') {
                        await this.loadJournalEntries();
                    }
                }, 500);
            } else {
                const errorMsg = data?.message || data?.error || `HTTP ${response.status}: ${response.statusText}`;
                this.showToast(`Failed to delete entry: ${errorMsg}`, 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete entry', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkExportJournalEntries = async function() {
        const ids = this.getSelectedIds('ledger');
        if (ids.length === 0) {
            this.showToast('Please select at least one entry', 'warning');
            return;
        }
        
        try {
            // Fetch selected entries and export
            const entries = [];
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/journal-entries.php?id=${id}`);
                    const data = await response.json();
                    if (data.success && data.entry) {
                        entries.push(data.entry);
                    }
                } catch (error) {
                }
            }
            
            if (entries.length > 0) {
                let csv = 'Entry #,Date,Description,Type,Debit,Credit,Status\n';
                entries.forEach(item => {
                    csv += `${item.entry_number || ''},${item.entry_date || ''},${(item.description || '').replace(/,/g, ';')},${item.entry_type || ''},${item.total_debit || 0},${item.total_credit || 0},${item.status || ''}\n`;
                });
                this.downloadCSV(csv, `Selected_Journal_Entries_${new Date().toISOString().split('T')[0]}`);
                this.showToast(`Exported ${entries.length} entry/entries successfully`, 'success');
            } else {
                this.showToast('No entries found to export', 'warning');
            }
        } catch (error) {
            this.showToast('Error exporting entries', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkDeleteInvoices = async function() {
        const ids = this.getSelectedIds('ar');
        if (ids.length === 0) {
            this.showToast('Please select at least one invoice', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Invoices',
            `Are you sure you want to delete ${ids.length} invoice(s)?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            let deleted = 0;
            let failed = 0;
            
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/invoices.php?id=${id}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        deleted++;
                    } else {
                        failed++;
                    }
                } catch (error) {
                    failed++;
                }
            }
            
            if (deleted > 0) {
                this.showToast(`Deleted ${deleted} invoice(s) successfully${failed > 0 ? `, ${failed} failed` : ''}`, deleted === ids.length ? 'success' : 'warning');
            this.loadModalInvoices();
            } else {
                this.showToast('Failed to delete invoices', 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete invoices', 'error');
        }
    }

ProfessionalAccounting.prototype.deleteInvoice = async function(id) {
        if (!id) {
            this.showToast('Invalid invoice ID', 'error');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Invoice',
            'Are you sure you want to delete this invoice? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/invoices.php?id=${id}`, {
                method: 'DELETE'
            });
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in deleteInvoice:', jsonError);
                data = { success: false, message: 'Invalid response from server' };
            }
            
            if (response.ok && data.success) {
                this.showToast('Invoice deleted successfully', 'success');
                this.loadInvoices();
                if (typeof this.loadModalInvoices === 'function') {
                    this.loadModalInvoices();
                }
            } else {
                const errorMsg = data.message || data.error || 'Unknown error';
                this.showToast(`Failed to delete invoice: ${errorMsg}`, 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete invoice', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkDeleteBills = async function() {
        const ids = this.getSelectedIds('ap');
        if (ids.length === 0) {
            this.showToast('Please select at least one bill', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Bills',
            `Are you sure you want to delete ${ids.length} bill(s)?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            let deleted = 0;
            let failed = 0;
            
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/bills.php?id=${id}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        deleted++;
                    } else {
                        failed++;
                    }
                } catch (error) {
                    failed++;
                }
            }
            
            if (deleted > 0) {
                this.showToast(`Deleted ${deleted} bill(s) successfully${failed > 0 ? `, ${failed} failed` : ''}`, deleted === ids.length ? 'success' : 'warning');
            this.loadModalBills();
            } else {
                this.showToast('Failed to delete bills', 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete bills', 'error');
        }
    }

ProfessionalAccounting.prototype.deleteBill = async function(id) {
        if (!id) {
            this.showToast('Invalid bill ID', 'error');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Bill',
            'Are you sure you want to delete this bill? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/bills.php?id=${id}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            
            if (response.ok && data.success) {
                this.showToast('Bill deleted successfully', 'success');
                this.loadBills();
                if (typeof this.loadModalBills === 'function') {
                    this.loadModalBills();
                }
            } else {
                const errorMsg = data.message || data.error || 'Unknown error';
                this.showToast(`Failed to delete bill: ${errorMsg}`, 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete bill', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkDeleteBankAccounts = async function() {
        const ids = this.getSelectedIds('bank');
        if (ids.length === 0) {
            this.showToast('Please select at least one bank account', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Bank Accounts',
            `Are you sure you want to delete ${ids.length} bank account(s)?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }
        
        try {
            let deleted = 0;
            let failed = 0;
            
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/banks.php?id=${id}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        deleted++;
                    } else {
                        failed++;
                    }
                } catch (error) {
                    failed++;
                }
            }
            
            if (deleted > 0) {
                this.showToast(`Deleted ${deleted} bank account(s) successfully${failed > 0 ? `, ${failed} failed` : ''}`, deleted === ids.length ? 'success' : 'warning');
            this.loadModalBankAccounts();
            } else {
                this.showToast('Failed to delete bank accounts', 'error');
            }
        } catch (error) {
            this.showToast('Failed to delete bank accounts', 'error');
        }
    }


ProfessionalAccounting.prototype.bulkExportInvoices = async function() {
        const ids = this.getSelectedIds('ar');
        if (ids.length === 0) {
            this.showToast('Please select at least one invoice', 'warning');
            return;
        }
        
        try {
            const invoices = [];
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/invoices.php?id=${id}`);
                    const data = await response.json();
                    if (data.success && data.invoice) {
                        invoices.push(data.invoice);
                    }
                } catch (error) {
                }
            }
            
            if (invoices.length > 0) {
                let csv = 'Invoice #,Date,Due Date,Customer,Amount,Balance,Status\n';
                invoices.forEach(item => {
                    csv += `${item.invoice_number || ''},${item.invoice_date || ''},${item.due_date || ''},${item.customer_id || ''},${item.total_amount || 0},${item.balance_amount || 0},${item.status || ''}\n`;
                });
                this.downloadCSV(csv, `Selected_Invoices_${new Date().toISOString().split('T')[0]}`);
                this.showToast(`Exported ${invoices.length} invoice(s) successfully`, 'success');
            } else {
                this.showToast('No invoices found to export', 'warning');
            }
        } catch (error) {
            this.showToast('Error exporting invoices', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkExportBills = async function() {
        const ids = this.getSelectedIds('ap');
        if (ids.length === 0) {
            this.showToast('Please select at least one bill', 'warning');
            return;
        }
        
        try {
            const bills = [];
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/bills.php?id=${id}`);
                    const data = await response.json();
                    if (data.success && data.bill) {
                        bills.push(data.bill);
                    }
                } catch (error) {
                }
            }
            
            if (bills.length > 0) {
                let csv = 'Bill #,Date,Due Date,Vendor,Amount,Balance,Status\n';
                bills.forEach(item => {
                    csv += `${item.bill_number || ''},${item.bill_date || ''},${item.due_date || ''},${item.vendor_id || ''},${item.total_amount || 0},${item.balance_amount || 0},${item.status || ''}\n`;
                });
                this.downloadCSV(csv, `Selected_Bills_${new Date().toISOString().split('T')[0]}`);
                this.showToast(`Exported ${bills.length} bill(s) successfully`, 'success');
            } else {
                this.showToast('No bills found to export', 'warning');
            }
        } catch (error) {
            this.showToast('Error exporting bills', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkExportBankAccounts = async function() {
        const ids = this.getSelectedIds('bank');
        if (ids.length === 0) {
            this.showToast('Please select at least one bank account', 'warning');
            return;
        }
        
        try {
            const accounts = [];
            for (const id of ids) {
                try {
                    const response = await fetch(`${this.apiBase}/banks.php?id=${id}`);
                    const data = await response.json();
                    if (data.success && data.bank) {
                        accounts.push(data.bank);
                    }
                } catch (error) {
                }
            }
            
            if (accounts.length > 0) {
                let csv = 'Bank Name,Account Name,Account Number,Current Balance,Currency,Status\n';
                accounts.forEach(item => {
                    csv += `${item.bank_name || ''},${item.account_name || ''},${item.account_number || ''},${item.current_balance || 0},${item.currency || this.getDefaultCurrencySync()},${item.is_active ? 'Active' : 'Inactive'}\n`;
                });
                this.downloadCSV(csv, `Selected_Bank_Accounts_${new Date().toISOString().split('T')[0]}`);
                this.showToast(`Exported ${accounts.length} bank account(s) successfully`, 'success');
            } else {
                this.showToast('No bank accounts found to export', 'warning');
            }
        } catch (error) {
            this.showToast('Error exporting bank accounts', 'error');
        }
    }



ProfessionalAccounting.prototype.showReportLoading = function(container, reportName) {
        // Don't show loading if report is already showing
        if (container.classList.contains('show') && container.innerHTML.trim().length > 100) {
            return;
        }
        
        // Hide reports grid
        const reportsGrid = document.getElementById('modalReportsGrid');
        if (reportsGrid) {
            reportsGrid.classList.add('reports-grid-hidden');
        }
        
        // Clear container and insert loading spinner
        container.innerHTML = `
            <div class="accounting-report-loading">
                <i class="fas fa-spinner fa-spin accounting-report-loading-icon"></i>
                <h3>Generating ${reportName} Report</h3>
                <p>Please wait while we prepare your report...</p>
            </div>
        `;
        
        // Use CSS class to override CSS
        container.classList.add('report-container-visible');
        container.classList.remove('show'); // Remove show class when showing loading
    }

    // Report favorites/bookmarks

ProfessionalAccounting.prototype.saveReportAsFavorite = function(reportType, reportName, filters = {}) {
        const favorites = this.getReportFavorites();
        const favoriteId = `${reportType}_${Date.now()}`;
        
        favorites.push({
            id: favoriteId,
            type: reportType,
            name: reportName,
            filters: filters,
            created: new Date().toISOString()
        });
        
        localStorage.setItem('reportFavorites', JSON.stringify(favorites));
        this.showToast('Report saved as favorite', 'success');
        return favoriteId;
    }
    

ProfessionalAccounting.prototype.getReportFavorites = function() {
        try {
            const stored = localStorage.getItem('reportFavorites');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }
    

ProfessionalAccounting.prototype.removeReportFavorite = function(favoriteId) {
        const favorites = this.getReportFavorites();
        const filtered = favorites.filter(f => f.id !== favoriteId);
        localStorage.setItem('reportFavorites', JSON.stringify(filtered));
        this.showToast('Favorite removed', 'info');
    }
    

ProfessionalAccounting.prototype.loadReportFavorite = function(favorite) {
        // Generate report with saved filters
        const params = new URLSearchParams({
            type: favorite.type,
            ...(favorite.filters.start_date && { start_date: favorite.filters.start_date }),
            ...(favorite.filters.end_date && { end_date: favorite.filters.end_date }),
            ...(favorite.filters.as_of && { as_of: favorite.filters.as_of }),
            ...(favorite.filters.account_id && { account_id: favorite.filters.account_id })
        });
        
        this.generateReport(favorite.type, params);
    }
    
    // Report caching for performance

ProfessionalAccounting.prototype.getCachedReport = function(reportType, params) {
        const cacheKey = `report_cache_${reportType}_${JSON.stringify(params)}`;
        const cached = sessionStorage.getItem(cacheKey);
        
        if (cached) {
            try {
                const data = JSON.parse(cached);
                // Check if cache is still valid (5 minutes)
                if (Date.now() - data.timestamp < 5 * 60 * 1000) {
                    return data.report;
                }
            } catch (e) {
                // Invalid cache, ignore
            }
        }
        return null;
    }
    

ProfessionalAccounting.prototype.cacheReport = function(reportType, params, reportData) {
        const cacheKey = `report_cache_${reportType}_${JSON.stringify(params)}`;
        sessionStorage.setItem(cacheKey, JSON.stringify({
            report: reportData,
            timestamp: Date.now()
        }));
    }
    

ProfessionalAccounting.prototype.clearReportCache = function() {
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith('report_cache_')) {
                sessionStorage.removeItem(key);
            }
        });
    }
    
ProfessionalAccounting.prototype.generateReport = async function(reportType) {
        // Get report name using centralized function
        const reportName = this.getReportName(reportType);
        
        try {
            // Check if there's already a modal open - if so, update it smoothly
            const existingModal = document.querySelector('.accounting-modal.accounting-modal-visible, .accounting-modal.show-modal');
            let modalBody = null;
            
            if (existingModal) {
                modalBody = existingModal.querySelector('.accounting-modal-body');
                if (modalBody) {
                    // Show loading state smoothly
                    modalBody.classList.add('opacity-loading');
                    modalBody.innerHTML = `
                        <div class="accounting-module-modal-content">
                            <div class="module-content report-loading-container">
                                <div class="report-loading-content">
                                    <i class="fas fa-spinner fa-spin report-loading-spinner"></i>
                                    <h3>Generating ${reportName}...</h3>
                                    <p>Please wait while we prepare your report</p>
                                </div>
                            </div>
                        </div>
                    `;
                    // Update modal title
                    const modalHeader = existingModal.querySelector('.accounting-modal-header h3');
                    if (modalHeader) {
                        modalHeader.innerHTML = `<i class="fas fa-file-alt"></i> ${reportName}`;
                    }
                }
            } else {
                // Show loading in a new popup modal
                const loadingContent = `
                    <div class="accounting-module-modal-content">
                        <div class="module-content report-loading-container">
                            <div class="report-loading-content">
                                <i class="fas fa-spinner fa-spin report-loading-spinner"></i>
                                <h3>Generating ${reportName}...</h3>
                                <p>Please wait while we prepare your report</p>
                            </div>
                        </div>
                    </div>
                `;
                this.showModal(reportName, loadingContent, 'large');
                modalBody = document.querySelector('.accounting-modal.accounting-modal-visible .accounting-modal-body, .accounting-modal.show-modal .accounting-modal-body');
            }

            // Build query parameters
            const params = new URLSearchParams();
            params.append('type', reportType);
            
            // Get date parameters from UI if available, or use defaults
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');
            const asOfDateInput = document.getElementById('reportAsOfDate');
            const accountSelect = document.getElementById('reportAccountSelect');
            
            // Determine which date parameters to use based on report type
            const needsDateRange = ['income-statement', 'profit-loss', 'cash-flow', 'cash-book', 'bank-book', 
                'general-ledger', 'general-ledger-report', 'account-statement', 'expense-statement', 
                'value-added', 'entries-by-year', 'changes-equity', 'financial-performance', 'comparative-report'].includes(reportType);
            const needsAsOfDate = ['trial-balance', 'balance-sheet', 'aged-receivables', 'ages-debt-receivable', 
                'ages-credit-receivable', 'aged-payables', 'chart-of-accounts-report', 'fixed-assets', 
                'customer-debits', 'statistical-position'].includes(reportType);
            
            if (needsDateRange) {
                if (startDateInput && startDateInput.value) {
                    params.append('start_date', startDateInput.value);
                }
                if (endDateInput && endDateInput.value) {
                    params.append('end_date', endDateInput.value);
                }
            } else if (needsAsOfDate) {
                if (asOfDateInput && asOfDateInput.value) {
                    params.append('as_of', asOfDateInput.value);
                }
            }
            
            // Add account_id if available and needed
            if (accountSelect && accountSelect.value && accountSelect.value !== '' && ['general-ledger', 'general-ledger-report', 'account-statement'].includes(reportType)) {
                const accountId = parseInt(accountSelect.value);
                if (!isNaN(accountId) && accountId > 0) {
                    params.append('account_id', accountId);
                }
            }
            
            // Add search_term if available (for backend filtering on large datasets)
            const searchInput = document.getElementById('reportSearchInput');
            if (searchInput && searchInput.value && searchInput.value.trim() !== '') {
                params.append('search_term', searchInput.value.trim());
            }

            // Fetch report data
            const response = await fetch(`${this.apiBase}/reports.php?${params.toString()}`, {
                credentials: 'include'
            });
            
            // Check if response is ok
            if (!response.ok) {
                let errorMessage = `Report API error: ${response.status}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // If JSON parsing fails, try to get text response
                    try {
                const errorText = await response.text();
                        if (errorText) {
                            errorMessage = errorText.substring(0, 200);
                        }
                    } catch (textError) {
                        // Ignore text parsing errors
                    }
                }
                throw new Error(errorMessage);
            }
            
            // Try to parse as JSON
            let data;
            try {
                data = await response.json();
                console.log('📥 Report API Response:', data); // Always log the response
            } catch (e) {
                throw new Error('Invalid JSON response from report API');
            }

            // Always display report table, even if data is empty
            const reportData = (data.success && data.report) ? data.report : {};
            
            // Log debug information to console - ALWAYS LOG
            console.log('🔍 Report Type:', reportType);
            console.log('📊 Report Data Keys:', Object.keys(reportData));
            
            // Different report types use different data structures
            const reportsWithAccounts = ['trial-balance', 'general-ledger'];
            const hasAccounts = reportData.accounts && Array.isArray(reportData.accounts);
            
            if (reportsWithAccounts.includes(reportType)) {
                // These reports should have accounts array
                console.log('📋 Accounts Array:', hasAccounts ? `${reportData.accounts.length} items` : 'Not present');
                if (!hasAccounts) {
                    console.warn('⚠️ ' + reportType + ' report missing accounts array');
                } else if (reportData.accounts.length === 0) {
                    console.warn('⚠️ Report returned 0 accounts. Accounts array is empty.');
                } else {
                    console.log(`✅ Report returned ${reportData.accounts.length} accounts`);
                }
            } else {
                // Other reports use different structures - check what they have
                if (reportType === 'income-statement') {
                    const hasRevenue = reportData.revenue && (Array.isArray(reportData.revenue) || Object.keys(reportData.revenue).length > 0);
                    const hasExpenses = reportData.expenses && (Array.isArray(reportData.expenses) || Object.keys(reportData.expenses).length > 0);
                    console.log('💰 Revenue data:', hasRevenue ? 'Present' : 'Missing');
                    console.log('💸 Expenses data:', hasExpenses ? 'Present' : 'Missing');
                } else if (reportType === 'cash-flow') {
                    const hasOperating = reportData.operating && (Array.isArray(reportData.operating) || Object.keys(reportData.operating).length > 0);
                    console.log('💵 Operating activities:', hasOperating ? 'Present' : 'Missing');
                } else if (reportType === 'balance-sheet') {
                    const hasAssets = reportData.assets && (Array.isArray(reportData.assets) || Object.keys(reportData.assets).length > 0);
                    const hasLiabilities = reportData.liabilities && (Array.isArray(reportData.liabilities) || Object.keys(reportData.liabilities).length > 0);
                    const hasEquity = reportData.equity && (Array.isArray(reportData.equity) || Object.keys(reportData.equity).length > 0);
                    console.log('🏦 Assets data:', hasAssets ? 'Present' : 'Missing');
                    console.log('📊 Liabilities data:', hasLiabilities ? 'Present' : 'Missing');
                    console.log('💼 Equity data:', hasEquity ? 'Present' : 'Missing');
                }
            }
            
            if (data.debug) {
                console.log('📊 Report Debug Info:', JSON.stringify(data.debug, null, 2));
                if (data.debug.total_accounts_in_db !== undefined) {
                    console.log(`📋 Total accounts in database: ${data.debug.total_accounts_in_db}`);
                }
                if (data.debug.accounts_count !== undefined) {
                    console.log(`📊 Accounts in report: ${data.debug.accounts_count}`);
                }
                if (data.debug.table_exists_check !== undefined) {
                    console.log(`✅ Table exists check: ${data.debug.table_exists_check}`);
                }
            } else {
                console.warn('⚠️ No debug info in response');
            }
            
            // Setup handlers after report is displayed
            setTimeout(() => {
                this.displayReportInPopupSmooth(reportType, reportName, reportData, existingModal);

                if (data.success && data.report) {
                    this.showToast(`${reportName} report generated successfully!`, 'success');
                } else {
                    this.showToast(`${reportName} report displayed (no data available)`, 'info');
                }
            }, 100);
        } catch (error) {
            console.error('❌ Error generating report:', error);
            // Show report table with empty data instead of placeholder
            const existingModal = document.querySelector('.accounting-modal.accounting-modal-visible, .accounting-modal.show-modal');
            this.displayReportInPopupSmooth(reportType, reportName, {}, existingModal);
            this.showToast(`${reportName} report displayed (no data available)`, 'info');
        }
    }


ProfessionalAccounting.prototype.displayReportInPopupSmooth = function(reportType, reportName, reportData, existingModal = null) {
        // Store report data for pagination
        const isNewReport = this.currentReportType !== reportType;
        this.currentReportType = reportType;
        this.currentReportData = reportData;
        
        // Only reset pagination if it's a new report, otherwise preserve current settings
        if (isNewReport) {
            this.reportCurrentPage = 1;
            this.reportPerPage = 5;
            this.reportSearchTerm = '';
            this.reportTotalCount = 0; // Reset total count for new report
        }
        
        // Format report data into HTML table based on report type
        const reportDate = reportData?.period || reportData?.as_of || new Date().toISOString().split('T')[0];
        const formattedDate = this.formatDate(reportDate);
        const todayFormatted = this.formatDate(new Date().toISOString().split('T')[0]);
        const reportPeriod = reportData?.period ? `Period: ${formattedDate}` : reportData?.as_of ? `As of: ${formattedDate}` : `Generated: ${todayFormatted}`;
        
        let reportHTML = `
            <div class="accounting-module-modal-content">
                <div class="module-content">
            <div class="accounting-report-header professional-report-header">
                <div class="accounting-report-header-top">
                    <div class="report-header-left">
                        <h3 class="accounting-report-header-title">
                            <i class="fas fa-file-alt"></i> ${reportName}
                        </h3>
                        <p class="accounting-report-header-meta">
                            <i class="fas fa-calendar"></i> ${reportPeriod}
                        </p>
                    </div>
                </div>
                <div class="accounting-report-header-buttons">
                    <button class="btn btn-primary" data-action="print-report" title="Print Report">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary" data-action="export-report" title="Export Report">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            ${this.getReportStatusCards(reportType, reportData)}
            <div class="filters-and-pagination-container report-controls-container">
                <div class="filters-bar filters-bar-compact">
                    ${this.getReportDateFiltersHTML(reportType)}
                    ${this.getReportAccountFilterHTML(reportType)}
                    <div class="filter-group filter-group-compact">
                        <label><i class="fas fa-search"></i> Search:</label>
                        <input type="text" id="reportSearchInput" class="filter-input filter-input-compact" 
                               placeholder="Search accounts, descriptions..." 
                               value="${this.reportSearchTerm || ''}">
                    </div>
                    <div class="filter-group filter-group-compact">
                        <label>Show entries:</label>
                        <select id="reportPerPage" class="filter-select filter-select-compact">
                            <option value="5" ${this.reportPerPage === 5 ? 'selected' : ''}>5</option>
                            <option value="10" ${this.reportPerPage === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${this.reportPerPage === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${this.reportPerPage === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${this.reportPerPage === 100 ? 'selected' : ''}>100</option>
                            <option value="999999" ${this.reportPerPage >= 999999 ? 'selected' : ''}>All</option>
                        </select>
                    </div>
                    <div class="filter-group filter-group-compact">
                        <button class="btn btn-primary btn-sm" id="applyReportFilters" data-action="apply-report-filters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div class="filter-group filter-group-compact">
                        <button class="btn btn-secondary btn-sm" id="clearReportFilters" data-action="clear-report-filters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="pagination-container">
                    <div id="reportPaginationInfo" class="pagination-info"></div>
                    <div id="reportPaginationControls" class="pagination-controls"></div>
                </div>
            </div>
            <div class="accounting-report-content professional-report-content">
        `;
        
        // If modal exists, update it smoothly
        if (existingModal) {
            const modalBody = existingModal.querySelector('.accounting-modal-body');
            const modalHeader = existingModal.querySelector('.accounting-modal-header h3');
            
            if (modalBody && modalHeader) {
                // Update header
                modalHeader.innerHTML = `<i class="fas fa-file-alt"></i> ${reportName}`;
                
                // Fade out, update, fade in
                modalBody.classList.add('opacity-disabled');
                
                    requestAnimationFrame(() => {
                    // Complete the HTML
                    switch (reportType) {
                        case 'trial-balance':
                            reportHTML += this.formatTrialBalance(reportData || {});
                            break;
                        case 'income-statement':
                        case 'profit-loss':
                            reportHTML += this.formatIncomeStatement(reportData || {});
                            break;
                        case 'balance-sheet':
                            reportHTML += this.formatBalanceSheet(reportData || {});
                            break;
                        case 'cash-flow':
                            reportHTML += this.formatCashFlow(reportData || {});
                            break;
                        case 'aged-receivables':
                        case 'ages-debt-receivable':
                            reportHTML += this.formatAgedReceivables(reportData || {});
                            break;
                        case 'ages-credit-receivable':
                            reportHTML += this.formatAgedReceivables(reportData || {});
                            break;
                        case 'aged-payables':
                            reportHTML += this.formatAgedPayables(reportData || {});
                            break;
                        case 'cash-book':
                            reportHTML += this.formatCashBook(reportData || {});
                            break;
                        case 'bank-book':
                            reportHTML += this.formatBankBook(reportData || {});
                            break;
                        case 'general-ledger':
                        case 'general-ledger-report':
                            reportHTML += this.formatGeneralLedgerReport(reportData || {});
                            break;
                        case 'account-statement':
                            reportHTML += this.formatAccountStatement(reportData || {});
                            break;
                        case 'expense-statement':
                            reportHTML += this.formatExpenseStatement(reportData || {});
                            break;
                        case 'chart-of-accounts-report':
                            reportHTML += this.formatChartOfAccounts(reportData || {});
                            break;
                        case 'value-added':
                            reportHTML += this.formatValueAdded(reportData || {});
                            break;
                        case 'fixed-assets':
                            reportHTML += this.formatFixedAssets(reportData || {});
                            break;
                        case 'entries-by-year':
                            reportHTML += this.formatEntriesByYear(reportData || {});
                            break;
                        case 'customer-debits':
                            reportHTML += this.formatCustomerDebits(reportData || {});
                            break;
                        case 'statistical-position':
                            reportHTML += this.formatStatisticalPosition(reportData || {});
                            break;
                        case 'changes-equity':
                            reportHTML += this.formatChangesInEquity(reportData || {});
                            break;
                        case 'financial-performance':
                            reportHTML += this.formatFinancialPerformance(reportData || {});
                            break;
                        case 'comparative-report':
                            reportHTML += this.formatComparativeReport(reportData || {});
                            break;
                        default:
                            reportHTML += this.formatGenericReport(reportData || {}, reportName);
                    }
                    
                    reportHTML += `
                        </div>
                    </div>
                </div>
            `;
                    
                    modalBody.innerHTML = reportHTML;
                    
                    // Setup handlers
                    setTimeout(() => {
                        this.setupReportHandlers();
                        this.setupReportPagination();
                    }, 50);
                    
                    // Fade back in
                    requestAnimationFrame(() => {
                        modalBody.classList.remove('opacity-disabled', 'opacity-loading');
                        modalBody.classList.add('opacity-full');
                    });
                });
                
                return;
            }
        }
        
        // Fallback to original method if no existing modal
        this.displayReportInPopup(reportType, reportName, reportData);
    }
    

ProfessionalAccounting.prototype.displayReportInPopup = function(reportType, reportName, reportData) {
        // Store report data for pagination
        const isNewReport = this.currentReportType !== reportType;
        this.currentReportType = reportType;
        this.currentReportData = reportData;
        
        // Only reset pagination if it's a new report, otherwise preserve current settings
        if (isNewReport) {
            this.reportCurrentPage = 1;
            this.reportPerPage = 5;
            this.reportSearchTerm = '';
            this.reportTotalCount = 0; // Reset total count for new report
        }

        // Format report data into HTML table based on report type
        const reportDate = reportData?.period || reportData?.as_of || new Date().toISOString().split('T')[0];
        const formattedDate = this.formatDate(reportDate);
        const todayFormatted = this.formatDate(new Date().toISOString().split('T')[0]);
        const reportPeriod = reportData?.period ? `Period: ${formattedDate}` : reportData?.as_of ? `As of: ${formattedDate}` : `Generated: ${todayFormatted}`;
        
        let reportHTML = `
            <div class="accounting-module-modal-content">
                <div class="module-content">
            <div class="accounting-report-header professional-report-header">
                <div class="accounting-report-header-top">
                    <div class="report-header-left">
                        <h3 class="accounting-report-header-title">
                            <i class="fas fa-file-alt"></i> ${reportName}
                        </h3>
                        <p class="accounting-report-header-meta">
                            <i class="fas fa-calendar"></i> ${reportPeriod}
                        </p>
                    </div>
                </div>
                <div class="accounting-report-header-buttons">
                    <button class="btn btn-primary" data-action="print-report" title="Print Report">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary" data-action="export-report" title="Export Report">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            ${this.getReportStatusCards(reportType, reportData)}
            <div class="filters-and-pagination-container report-controls-container">
                <div class="filters-bar filters-bar-compact">
                    ${this.getReportDateFiltersHTML(reportType)}
                    ${this.getReportAccountFilterHTML(reportType)}
                    <div class="filter-group filter-group-compact">
                        <label><i class="fas fa-search"></i> Search:</label>
                        <input type="text" id="reportSearchInput" class="filter-input filter-input-compact" 
                               placeholder="Search..." 
                               value="${this.reportSearchTerm || ''}">
                    </div>
                    <div class="filter-group filter-group-compact">
                        <label>Show entries:</label>
                        <select id="reportPerPage" class="filter-select filter-select-compact">
                            <option value="5" ${this.reportPerPage === 5 ? 'selected' : ''}>5</option>
                            <option value="10" ${this.reportPerPage === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${this.reportPerPage === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${this.reportPerPage === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${this.reportPerPage === 100 ? 'selected' : ''}>100</option>
                            <option value="999999" ${this.reportPerPage >= 999999 ? 'selected' : ''}>All</option>
                        </select>
                    </div>
                    <div class="filter-group filter-group-compact">
                        <button class="btn btn-primary btn-sm" id="applyReportFilters" data-action="apply-report-filters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div class="filter-group filter-group-compact">
                        <button class="btn btn-secondary btn-sm" id="clearReportFilters" data-action="clear-report-filters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="pagination-container">
                    <div id="reportPaginationInfo" class="pagination-info"></div>
                    <div id="reportPaginationControls" class="pagination-controls"></div>
                </div>
            </div>
            <div class="accounting-report-content professional-report-content">
        `;

        // Format based on report type
        switch (reportType) {
            case 'trial-balance':
                reportHTML += this.formatTrialBalance(reportData || {});
                break;
            case 'income-statement':
            case 'profit-loss':
                reportHTML += this.formatIncomeStatement(reportData || {});
                break;
            case 'balance-sheet':
                reportHTML += this.formatBalanceSheet(reportData || {});
                break;
            case 'cash-flow':
                reportHTML += this.formatCashFlow(reportData || {});
                break;
                case 'aged-receivables':
                case 'ages-debt-receivable':
                    reportHTML += this.formatAgedReceivables(reportData || {});
                    break;
                case 'ages-credit-receivable':
                    reportHTML += this.formatAgedReceivables(reportData || {});
                    break;
            case 'aged-payables':
                reportHTML += this.formatAgedPayables(reportData || {});
                break;
            case 'cash-book':
                reportHTML += this.formatCashBook(reportData || {});
                break;
            case 'bank-book':
                reportHTML += this.formatBankBook(reportData || {});
                break;
            case 'general-ledger':
            case 'general-ledger-report':
                reportHTML += this.formatGeneralLedgerReport(reportData || {});
                break;
            case 'account-statement':
                reportHTML += this.formatAccountStatement(reportData || {});
                break;
            case 'expense-statement':
                reportHTML += this.formatExpenseStatement(reportData || {});
                break;
            case 'chart-of-accounts-report':
                reportHTML += this.formatChartOfAccounts(reportData || {});
                break;
            case 'value-added':
                reportHTML += this.formatValueAdded(reportData || {});
                break;
            case 'fixed-assets':
                reportHTML += this.formatFixedAssets(reportData || {});
                break;
            case 'entries-by-year':
                reportHTML += this.formatEntriesByYear(reportData || {});
                break;
            case 'customer-debits':
                reportHTML += this.formatCustomerDebits(reportData || {});
                break;
            case 'statistical-position':
                reportHTML += this.formatStatisticalPosition(reportData || {});
                break;
            case 'changes-equity':
                reportHTML += this.formatChangesInEquity(reportData || {});
                break;
            case 'financial-performance':
                reportHTML += this.formatFinancialPerformance(reportData || {});
                break;
            case 'comparative-report':
                reportHTML += this.formatComparativeReport(reportData || {});
                break;
            default:
                reportHTML += this.formatGenericReport(reportData || {}, reportName);
        }

        reportHTML += `
                    </div>
                </div>
            </div>
        `;
        
        // Close the loading modal and open the report popup
        this.closeModal();
        
        // Open report in new popup modal
        setTimeout(() => {
            this.showModal(reportName, reportHTML, 'large');
            
            // Setup handlers
            setTimeout(() => {
                this.setupReportHandlers();
                this.setupReportPagination();
            }, 100);
        }, 100);
    }
    

ProfessionalAccounting.prototype.setupReportHandlers = function() {
        const printBtn = document.querySelector('[data-action="print-report"]');
        const exportBtn = document.querySelector('[data-action="export-report"]');
        const refreshBtn = document.getElementById('refreshReportBtn');
        const applyFiltersBtn = document.getElementById('applyReportFilters');
        
        // Setup refresh button
        if (refreshBtn) {
            const newRefreshBtn = refreshBtn.cloneNode(true);
            refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
            newRefreshBtn.addEventListener('click', () => {
                this.refreshCurrentReport();
            });
        }
        
        // Setup date validation
        this.setupDateValidation();
        
        // Setup column sorting
        this.setupColumnSorting();
        
        if (printBtn) {
            // Remove existing listeners by cloning
            const newPrintBtn = printBtn.cloneNode(true);
            printBtn.parentNode.replaceChild(newPrintBtn, printBtn);
            newPrintBtn.addEventListener('click', () => {
                window.print();
            });
        }
        
        if (exportBtn) {
            // Remove existing listeners by cloning
            const newExportBtn = exportBtn.cloneNode(true);
            exportBtn.parentNode.replaceChild(newExportBtn, exportBtn);
            
            // Create dropdown menu for export formats
            newExportBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showExportMenu(newExportBtn);
            });
        }
        
        if (applyFiltersBtn) {
            // Remove existing listeners by cloning
            const newApplyBtn = applyFiltersBtn.cloneNode(true);
            applyFiltersBtn.parentNode.replaceChild(newApplyBtn, applyFiltersBtn);
            newApplyBtn.addEventListener('click', () => {
                this.applyReportFilters();
            });
        }
        
        // Setup clear filters button
        const clearFiltersBtn = document.getElementById('clearReportFilters');
        if (clearFiltersBtn) {
            const newClearBtn = clearFiltersBtn.cloneNode(true);
            clearFiltersBtn.parentNode.replaceChild(newClearBtn, clearFiltersBtn);
            newClearBtn.addEventListener('click', () => {
                this.clearReportFilters();
            });
        }
        
        // Setup search input (search on Enter key or after delay)
        const searchInput = document.getElementById('reportSearchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.reportSearchTerm = e.target.value || '';
                    this.reportCurrentPage = 1;
                    this.refreshReportDisplay();
                }, 500); // Debounce search
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    this.reportSearchTerm = e.target.value || '';
                    this.reportCurrentPage = 1;
                    this.refreshReportDisplay();
                }
            });
        }
        
        // Load accounts if account select exists
        const accountSelect = document.getElementById('reportAccountSelect');
        if (accountSelect) {
            this.loadAccountsForReport();
        }
        
        // Setup date validation
        this.setupDateValidation();
        
        // Setup column sorting
        this.setupColumnSorting();
        
        // Setup keyboard shortcuts
        this.setupKeyboardShortcuts();
        
        // Setup report comparison
        this.setupReportComparison();
    }
    

ProfessionalAccounting.prototype.setupKeyboardShortcuts = function() {
        // Only setup shortcuts when report modal is open
        const reportModal = document.querySelector('.accounting-modal:has(.accounting-report-content)');
        if (!reportModal) return;
        
        document.addEventListener('keydown', (e) => {
            // Only handle shortcuts when report is focused
            if (!reportModal.classList.contains('accounting-modal-visible')) return;
            
            // Ctrl/Cmd + P: Print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                const printBtn = document.querySelector('[data-action="print-report"]');
                if (printBtn) printBtn.click();
            }
            
            // Ctrl/Cmd + E: Export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const exportBtn = document.querySelector('[data-action="export-report"]');
                if (exportBtn) exportBtn.click();
            }
            
            // Ctrl/Cmd + F: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f' && !e.shiftKey) {
                e.preventDefault();
                const searchInput = document.getElementById('reportSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape: Close modal
            if (e.key === 'Escape') {
                const closeBtn = document.querySelector('.accounting-modal-close, [data-action="close-modal"]');
                if (closeBtn) closeBtn.click();
            }
            
            // Arrow keys for pagination (when not in input field)
            if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                if (e.key === 'ArrowLeft' && e.ctrlKey) {
                    e.preventDefault();
                    const prevBtn = document.querySelector('.btn-pagination:not(.btn-pagination-next):not(.btn-pagination-prev)');
                    if (prevBtn && !prevBtn.classList.contains('active')) {
                        const activePage = parseInt(document.querySelector('.btn-pagination.active')?.textContent || '1');
                        if (activePage > 1) {
                            this.reportCurrentPage = activePage - 1;
                            this.refreshReportDisplay();
                        }
                    }
                }
                if (e.key === 'ArrowRight' && e.ctrlKey) {
                    e.preventDefault();
                    const nextBtn = document.querySelector('.btn-pagination-next');
                    if (nextBtn && !nextBtn.disabled) {
                        nextBtn.click();
                    }
                }
            }
        });
    }
    

ProfessionalAccounting.prototype.setupReportComparison = function() {
        // Add compare button and favorites button to report header
        const reportHeader = document.querySelector('.accounting-report-header');
        if (reportHeader) {
            const headerButtons = reportHeader.querySelector('.accounting-report-header-buttons');
            if (headerButtons) {
                // Refresh button handler
                const refreshBtn = document.getElementById('refreshReportBtn');
                if (refreshBtn && !refreshBtn.hasAttribute('data-listener-added')) {
                    refreshBtn.setAttribute('data-listener-added', 'true');
                    refreshBtn.addEventListener('click', () => {
                        this.refreshCurrentReport();
                    });
                }
                
                const headerRight = headerButtons;
                if (headerRight) {
                    // Compare button
                    if (!document.getElementById('compareReportBtn')) {
                        const compareBtn = document.createElement('button');
                        compareBtn.id = 'compareReportBtn';
                        compareBtn.className = 'btn btn-info btn-sm';
                        compareBtn.innerHTML = '<i class="fas fa-balance-scale"></i> Compare';
                        compareBtn.title = 'Compare with another period (Ctrl+Shift+C)';
                        compareBtn.addEventListener('click', () => {
                            this.openReportComparison();
                        });
                        headerRight.insertBefore(compareBtn, headerRight.firstChild);
                    }
                    
                    // Favorites button
                    if (!document.getElementById('saveFavoriteBtn')) {
                        const favoriteBtn = document.createElement('button');
                        favoriteBtn.id = 'saveFavoriteBtn';
                        favoriteBtn.className = 'btn btn-secondary btn-sm';
                        favoriteBtn.innerHTML = '<i class="far fa-star"></i> Save Favorite';
                        favoriteBtn.title = 'Save this report as favorite (Ctrl+S)';
                        favoriteBtn.addEventListener('click', () => {
                            this.saveCurrentReportAsFavorite();
                        });
                        headerRight.insertBefore(favoriteBtn, headerRight.firstChild);
                    }
                }
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                const compareBtn = document.getElementById('compareReportBtn');
                if (compareBtn) compareBtn.click();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 's' && !e.shiftKey) {
                e.preventDefault();
                const favoriteBtn = document.getElementById('saveFavoriteBtn');
                if (favoriteBtn) favoriteBtn.click();
            }
            // F5: Refresh report
            if (e.key === 'F5') {
                e.preventDefault();
                const refreshBtn = document.getElementById('refreshReportBtn');
                if (refreshBtn) refreshBtn.click();
            }
        });
    }
    

ProfessionalAccounting.prototype.refreshCurrentReport = function() {
        if (!this.currentReportType) {
            this.showToast('No report to refresh', 'warning');
            return;
        }
        
        // Show loading state
        const reportContent = document.querySelector('.accounting-report-content');
        if (reportContent) {
            reportContent.classList.add('opacity-loading');
        }
        
        // Regenerate report with current filters
        this.generateReport(this.currentReportType);
    }
    

ProfessionalAccounting.prototype.saveCurrentReportAsFavorite = function() {
        if (!this.currentReportType || !this.currentReportData) {
            this.showToast('No report to save', 'warning');
            return;
        }
        
        const startDateInput = document.getElementById('reportStartDate');
        const endDateInput = document.getElementById('reportEndDate');
        const asOfDateInput = document.getElementById('reportAsOfDate');
        const accountSelect = document.getElementById('reportAccountSelect');
        
        const filters = {
            ...(startDateInput?.value && { start_date: startDateInput.value }),
            ...(endDateInput?.value && { end_date: endDateInput.value }),
            ...(asOfDateInput?.value && { as_of: asOfDateInput.value }),
            ...(accountSelect?.value && { account_id: accountSelect.value })
        };
        
        const reportName = this.getReportName(this.currentReportType);
        this.saveReportAsFavorite(this.currentReportType, reportName, filters);
        
        // Update button icon
        const favoriteBtn = document.getElementById('saveFavoriteBtn');
        if (favoriteBtn) {
            favoriteBtn.innerHTML = '<i class="fas fa-star"></i> Saved';
            favoriteBtn.classList.add('favorited');
            setTimeout(() => {
                favoriteBtn.innerHTML = '<i class="far fa-star"></i> Save Favorite';
                favoriteBtn.classList.remove('favorited');
            }, 2000);
        }
    }
    

ProfessionalAccounting.prototype.openReportComparison = function() {
        // Show modal to select comparison period
        const currentType = this.currentReportType;
        const currentData = this.currentReportData;
        
        if (!currentType || !currentData) {
            this.showToast('No report to compare', 'warning');
            return;
        }
        
        // Create comparison modal
        const comparisonHTML = `
            <div class="accounting-modal accounting-modal-visible" id="reportComparisonModal">
                <div class="accounting-modal-overlay"></div>
                <div class="accounting-modal-content accounting-modal-medium">
                    <div class="accounting-modal-header">
                        <h3><i class="fas fa-balance-scale"></i> Compare Report</h3>
                        <button class="accounting-modal-close" data-action="close-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="accounting-modal-body">
                        <p>Select a different date range to compare with the current report:</p>
                        <div class="accounting-modal-form-group">
                            <label>Comparison Start Date:</label>
                            <input type="text" id="compareStartDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Comparison End Date:</label>
                            <input type="text" id="compareEndDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Or As Of Date:</label>
                            <input type="text" id="compareAsOfDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="accounting-modal-footer">
                            <button class="btn btn-primary" id="generateComparisonBtn">
                                <i class="fas fa-balance-scale"></i> Generate Comparison
                            </button>
                            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', comparisonHTML);
        
        // Setup comparison button
        document.getElementById('generateComparisonBtn').addEventListener('click', async () => {
            const startDate = document.getElementById('compareStartDate').value;
            const endDate = document.getElementById('compareEndDate').value;
            const asOfDate = document.getElementById('compareAsOfDate').value;
            
            if (!startDate && !endDate && !asOfDate) {
                this.showToast('Please select at least one date', 'warning');
                return;
            }
            
            // Generate comparison report
            try {
                const params = new URLSearchParams({
                    type: currentType,
                    ...(startDate && { start_date: startDate }),
                    ...(endDate && { end_date: endDate }),
                    ...(asOfDate && { as_of: asOfDate })
                });
                
                const response = await fetch(`${this.apiBase}/reports.php?${params}`);
                const data = await response.json();
                
                if (data.success && data.report) {
                    this.displayReportComparison(currentData, data.report, currentType);
                    this.closeModal();
                } else {
                    this.showToast('Failed to generate comparison report', 'error');
                }
            } catch (error) {
                this.showToast('Error generating comparison: ' + error.message, 'error');
            }
        });
    }
    

ProfessionalAccounting.prototype.displayReportComparison = function(currentReport, comparisonReport, reportType) {
        // Create side-by-side comparison view
        const reportName = this.getReportName(reportType);
        const comparisonHTML = `
            <div class="accounting-module-modal-content">
                <div class="module-content">
                    <div class="accounting-report-header professional-report-header">
                        <div class="accounting-report-header-top">
                            <div class="report-header-left">
                                <h3 class="accounting-report-header-title">
                                    <i class="fas fa-balance-scale"></i> ${reportName} - Comparison
                                </h3>
                            </div>
                            <div class="report-header-right">
                                <button class="btn btn-primary" data-action="print-report">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="btn btn-secondary" data-action="export-report">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-report-content professional-report-content">
                        <div class="report-comparison-container">
                            <div class="report-comparison-column">
                                <h4>Current Period</h4>
                                ${this.getReportHTMLForComparison(currentReport, reportType)}
                            </div>
                            <div class="report-comparison-column">
                                <h4>Comparison Period</h4>
                                ${this.getReportHTMLForComparison(comparisonReport, reportType)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.showModal(`${reportName} - Comparison`, comparisonHTML, 'large');
        setTimeout(() => {
            this.setupReportHandlers();
        }, 100);
    }
    

ProfessionalAccounting.prototype.getReportHTMLForComparison = function(reportData, reportType) {
        switch (reportType) {
            case 'trial-balance':
                return this.formatTrialBalance(reportData);
            case 'income-statement':
            case 'profit-loss':
                return this.formatIncomeStatement(reportData);
            case 'balance-sheet':
                return this.formatBalanceSheet(reportData);
            case 'cash-flow':
                return this.formatCashFlow(reportData);
            default:
                return this.formatGenericReport(reportData, this.getReportName(reportType));
        }
    }
    

ProfessionalAccounting.prototype.setupDateValidation = function() {
        const startDateInput = document.getElementById('reportStartDate');
        const endDateInput = document.getElementById('reportEndDate');
        const asOfDateInput = document.getElementById('reportAsOfDate');
        
        const validateDate = (input, fieldName) => {
            if (!input) return;
            
            input.addEventListener('blur', () => {
                const value = input.value.trim();
                if (value && !this.isValidDate(value)) {
                    input.classList.add('invalid-date');
                    this.showDateError(input, `Invalid ${fieldName} format. Please use YYYY-MM-DD`);
                } else {
                    input.classList.remove('invalid-date');
                    this.hideDateError(input);
                }
            });
            
            input.addEventListener('input', () => {
                input.classList.remove('invalid-date');
                this.hideDateError(input);
            });
        };
        
        if (startDateInput) validateDate(startDateInput, 'start date');
        if (endDateInput) validateDate(endDateInput, 'end date');
        if (asOfDateInput) validateDate(asOfDateInput, 'as of date');
        
        // Validate date range
        if (startDateInput && endDateInput) {
            const validateRange = () => {
                const start = startDateInput.value;
                const end = endDateInput.value;
                if (start && end && this.isValidDate(start) && this.isValidDate(end)) {
                    if (new Date(start) > new Date(end)) {
                        endDateInput.classList.add('invalid-date');
                        this.showDateError(endDateInput, 'End date must be after start date');
                    } else {
                        endDateInput.classList.remove('invalid-date');
                        this.hideDateError(endDateInput);
                    }
                }
            };
            
            startDateInput.addEventListener('change', validateRange);
            endDateInput.addEventListener('change', validateRange);
        }
    }
    

ProfessionalAccounting.prototype.isValidDate = function(dateString) {
        const regex = /^\d{4}-\d{2}-\d{2}$/;
        if (!regex.test(dateString)) return false;
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    }
    

ProfessionalAccounting.prototype.showDateError = function(input, message) {
        let errorMsg = input.parentElement.querySelector('.date-validation-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'date-validation-message';
            input.parentElement.appendChild(errorMsg);
        }
        errorMsg.textContent = message;
        errorMsg.classList.add('show');
    }
    

ProfessionalAccounting.prototype.hideDateError = function(input) {
        const errorMsg = input.parentElement.querySelector('.date-validation-message');
        if (errorMsg) {
            errorMsg.classList.remove('show');
        }
    }
    

ProfessionalAccounting.prototype.getQuickDatePresets = function() {
        const today = new Date();
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        const startOfYear = new Date(today.getFullYear(), 0, 1);
        const endOfYear = new Date(today.getFullYear(), 11, 31);
        const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
        const lastYearStart = new Date(today.getFullYear() - 1, 0, 1);
        const lastYearEnd = new Date(today.getFullYear() - 1, 11, 31);
        
        const formatDate = (date) => date.toISOString().split('T')[0];
        
        return [
            { label: 'Today', start: formatDate(today), end: formatDate(today) },
            { label: 'This Month', start: formatDate(startOfMonth), end: formatDate(endOfMonth) },
            { label: 'Last Month', start: formatDate(lastMonthStart), end: formatDate(lastMonthEnd) },
            { label: 'This Year', start: formatDate(startOfYear), end: formatDate(endOfYear) },
            { label: 'Last Year', start: formatDate(lastYearStart), end: formatDate(lastYearEnd) },
            { label: 'Last 7 Days', start: formatDate(new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)), end: formatDate(today) },
            { label: 'Last 30 Days', start: formatDate(new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)), end: formatDate(today) },
            { label: 'Last 90 Days', start: formatDate(new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000)), end: formatDate(today) }
        ];
    }
    

ProfessionalAccounting.prototype.applyQuickDatePreset = function(preset) {
        const startDateInput = document.getElementById('reportStartDate');
        const endDateInput = document.getElementById('reportEndDate');
        
        if (startDateInput && preset.start) {
            startDateInput.value = preset.start;
        }
        if (endDateInput && preset.end) {
            endDateInput.value = preset.end;
        }
        
        // Trigger change events
        if (startDateInput) startDateInput.dispatchEvent(new Event('change'));
        if (endDateInput) endDateInput.dispatchEvent(new Event('change'));
        
        this.showToast(`Applied ${preset.label} preset`, 'success');
    }
    

ProfessionalAccounting.prototype.setupColumnSorting = function() {
        // Add sorting to table headers after a short delay to ensure table is rendered
        setTimeout(() => {
            const tables = document.querySelectorAll('.professional-report-table');
            tables.forEach(table => {
                const headers = table.querySelectorAll('thead th');
                headers.forEach((header, index) => {
                    // Skip sorting on action columns or columns that shouldn't be sorted
                    if (header.classList.contains('report-col-type') || 
                        header.textContent.trim() === '' ||
                        header.querySelector('i')) {
                        return;
                    }
                    
                    header.classList.add('sortable');
                    header.addEventListener('click', () => {
                        this.sortTableColumn(table, index, header);
                    });
                });
            });
        }, 300);
    }
    

ProfessionalAccounting.prototype.sortTableColumn = function(table, columnIndex, header) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length === 0) return;
        
        // Determine current sort direction
        const isAsc = header.classList.contains('sort-asc');
        const isDesc = header.classList.contains('sort-desc');
        
        // Remove sort classes from all headers
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Set new sort direction
        const newDirection = isAsc ? 'desc' : 'asc';
        header.classList.add(`sort-${newDirection}`);
        
        // Sort rows
        rows.sort((a, b) => {
            const aCell = a.cells[columnIndex];
            const bCell = b.cells[columnIndex];
            
            if (!aCell || !bCell) return 0;
            
            let aValue = aCell.textContent.trim();
            let bValue = bCell.textContent.trim();
            
            // Try to parse as number
            const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return newDirection === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            return newDirection === 'asc' 
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
    

ProfessionalAccounting.prototype.setupReportPagination = function() {
        const perPageSelect = document.getElementById('reportPerPage');
        if (perPageSelect) {
            // Remove any existing listeners to prevent duplicates
            const newSelect = perPageSelect.cloneNode(true);
            perPageSelect.parentNode.replaceChild(newSelect, perPageSelect);
            
            // Set the value
            newSelect.value = this.reportPerPage.toString();
            
            // Add event listener
            newSelect.addEventListener('change', (e) => {
                const newPerPage = parseInt(e.target.value) || 5;
                if (newPerPage !== this.reportPerPage) {
                    this.reportPerPage = newPerPage;
                    this.reportCurrentPage = 1;
                    this.refreshReportDisplay();
                }
            });
        }
        
        this.updateReportPagination();
    }
    

ProfessionalAccounting.prototype.refreshReportDisplay = function() {
        if (this.currentReportType && this.currentReportData) {
            // Update content smoothly without closing/reopening modal
            this.updateReportContent();
        }
    }
    

ProfessionalAccounting.prototype.updateReportContent = function() {
        // Find the report content container
        const reportContent = document.querySelector('.accounting-report-content.professional-report-content');
        if (!reportContent) {
            // Fallback to full refresh if container not found
            this.displayReportInPopup(this.currentReportType, this.getReportName(this.currentReportType), this.currentReportData);
            return;
        }
        
        // Add fade transition
        reportContent.classList.add('opacity-disabled');
        
        // Use requestAnimationFrame for smooth update
        requestAnimationFrame(() => {
            // Get formatted report HTML
            let reportHTML = '';
            switch (this.currentReportType) {
                case 'trial-balance':
                    reportHTML = this.formatTrialBalance(this.currentReportData || {});
                    break;
                case 'income-statement':
                case 'profit-loss':
                    reportHTML = this.formatIncomeStatement(this.currentReportData || {});
                    break;
                case 'balance-sheet':
                    reportHTML = this.formatBalanceSheet(this.currentReportData || {});
                    break;
                case 'cash-flow':
                    reportHTML = this.formatCashFlow(this.currentReportData || {});
                    break;
                case 'aged-receivables':
                case 'ages-debt-receivable':
                    reportHTML = this.formatAgedReceivables(this.currentReportData || {});
                    break;
                case 'ages-credit-receivable':
                    reportHTML = this.formatAgedReceivables(this.currentReportData || {});
                    break;
                case 'aged-payables':
                    reportHTML = this.formatAgedPayables(this.currentReportData || {});
                    break;
                case 'cash-book':
                    reportHTML = this.formatCashBook(this.currentReportData || {});
                    break;
                case 'bank-book':
                    reportHTML = this.formatBankBook(this.currentReportData || {});
                    break;
                case 'general-ledger':
                case 'general-ledger-report':
                    reportHTML = this.formatGeneralLedgerReport(this.currentReportData || {});
                    break;
                case 'account-statement':
                    reportHTML = this.formatAccountStatement(this.currentReportData || {});
                    break;
                case 'expense-statement':
                    reportHTML = this.formatExpenseStatement(this.currentReportData || {});
                    break;
                case 'chart-of-accounts-report':
                    reportHTML = this.formatChartOfAccounts(this.currentReportData || {});
                    break;
                case 'value-added':
                    reportHTML = this.formatValueAdded(this.currentReportData || {});
                    break;
                case 'fixed-assets':
                    reportHTML = this.formatFixedAssets(this.currentReportData || {});
                    break;
                case 'entries-by-year':
                    reportHTML = this.formatEntriesByYear(this.currentReportData || {});
                    break;
                case 'customer-debits':
                    reportHTML = this.formatCustomerDebits(this.currentReportData || {});
                    break;
                case 'statistical-position':
                    reportHTML = this.formatStatisticalPosition(this.currentReportData || {});
                    break;
                case 'changes-equity':
                    reportHTML = this.formatChangesInEquity(this.currentReportData || {});
                    break;
                case 'financial-performance':
                    reportHTML = this.formatFinancialPerformance(this.currentReportData || {});
                    break;
                case 'comparative-report':
                    reportHTML = this.formatComparativeReport(this.currentReportData || {});
                    break;
                default:
                    reportHTML = this.formatGenericReport(this.currentReportData || {}, this.getReportName(this.currentReportType));
            }
            
            // Update content
        reportContent.innerHTML = reportHTML;
        
            // Update pagination and table wrappers
            this.updateReportPagination();
            
            // Re-setup column sorting after content update
            setTimeout(() => {
                this.setupColumnSorting();
            }, 100);
            
            // Fade back in
            requestAnimationFrame(() => {
                reportContent.classList.remove('opacity-disabled', 'opacity-loading');
                reportContent.classList.add('opacity-full');
            });
        });
    }
    

ProfessionalAccounting.prototype.getReportName = function(reportType) {
        const names = {
            'trial-balance': 'Trial Balance',
            'income-statement': 'Income Statement',
            'balance-sheet': 'Balance Sheet',
            'cash-flow': 'Cash Flow Report',
            'aged-receivables': 'Ages of Debt Receivable',
            'ages-debt-receivable': 'Ages of Debt Receivable',
            'aged-payables': 'Aged Payables',
            'cash-book': 'Cash Book',
            'bank-book': 'Bank Book',
            'general-ledger': 'General Ledger',
            'general-ledger-report': 'General Ledger',
            'account-statement': 'Account Statement',
            'expense-statement': 'Expense Statement',
            'chart-of-accounts-report': 'Chart of Accounts',
            'value-added': 'Value Added',
            'fixed-assets': 'Fixed Assets Report',
            'entries-by-year': 'Entries by Year Report',
            'customer-debits': 'Customer Debits Report',
            'ages-credit-receivable': 'Ages of Credit Receivable',
            'statistical-position': 'Statistical Position Report',
            'changes-equity': 'Changes in Equity',
            'financial-performance': 'Financial Performance',
            'comparative-report': 'Comparative Report'
        };
        return names[reportType] || 'Report';
    }
    

ProfessionalAccounting.prototype.getReportDateFiltersHTML = function(reportType) {
        const needsDateRange = ['income-statement', 'profit-loss', 'cash-flow', 'cash-book', 'bank-book', 
            'general-ledger', 'general-ledger-report', 'account-statement', 'expense-statement', 
            'value-added', 'entries-by-year', 'changes-equity', 'financial-performance', 'comparative-report'].includes(reportType);
        const needsAsOfDate = ['trial-balance', 'balance-sheet', 'aged-receivables', 'ages-debt-receivable', 
            'ages-credit-receivable', 'aged-payables', 'chart-of-accounts-report', 'fixed-assets', 
            'customer-debits', 'statistical-position'].includes(reportType);
        
        let html = '';
        
        if (needsDateRange) {
            const defaultStartDate = new Date();
            defaultStartDate.setMonth(defaultStartDate.getMonth() - 1);
            const defaultEndDate = new Date();
            
            html += `
                <div class="filter-group filter-group-compact">
                    <label>Start Date:</label>
                    <input type="text" id="reportStartDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                           value="${this.formatDateForInput(defaultStartDate.toISOString())}">
                </div>
                <div class="filter-group filter-group-compact">
                    <label>End Date:</label>
                    <input type="text" id="reportEndDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                           value="${this.formatDateForInput(defaultEndDate.toISOString())}">
                </div>
            `;
        } else if (needsAsOfDate) {
            const defaultAsOfDate = new Date();
            
            html += `
                <div class="filter-group filter-group-compact">
                    <label>As of Date:</label>
                    <input type="text" id="reportAsOfDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                           value="${this.formatDateForInput(defaultAsOfDate.toISOString())}">
                </div>
            `;
        }
        
        return html;
    }
    

ProfessionalAccounting.prototype.getReportAccountFilterHTML = function(reportType) {
        const needsAccountId = ['general-ledger', 'general-ledger-report', 'account-statement'].includes(reportType);
        
        if (!needsAccountId) {
            return '';
        }
        
        return `
            <div class="filter-group filter-group-compact">
                <label>Account:</label>
                <select id="reportAccountSelect" class="filter-select filter-select-compact">
                    <option value="">All Accounts</option>
                </select>
            </div>
        `;
    }
    
ProfessionalAccounting.prototype.loadAccountsForReport = async function() {
        const accountSelect = document.getElementById('reportAccountSelect');
        if (!accountSelect) return;
        
        try {
            const response = await fetch(`${this.apiBase}/accounts.php?action=list&is_active=1&ensure_entity_accounts=1`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.accounts) {
                    accountSelect.innerHTML = '<option value="">All Accounts</option>';
                    data.accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = `${account.account_code || ''} - ${account.account_name || ''}`.trim();
                        accountSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading accounts for report:', error);
        }
    }
    

ProfessionalAccounting.prototype.applyReportFilters = function() {
        if (this.currentReportType) {
            // Update search term from input
            const searchInput = document.getElementById('reportSearchInput');
            if (searchInput) {
                this.reportSearchTerm = searchInput.value || '';
            }
            // Reset to page 1 when applying filters
            this.reportCurrentPage = 1;
            // Regenerate report with new filters
            this.generateReport(this.currentReportType);
        }
    }
    

ProfessionalAccounting.prototype.clearReportFilters = function() {
        // Clear search
        const searchInput = document.getElementById('reportSearchInput');
        if (searchInput) {
            searchInput.value = '';
            this.reportSearchTerm = '';
        }
        
        // Reset dates to defaults
        const startDateInput = document.getElementById('reportStartDate');
        const endDateInput = document.getElementById('reportEndDate');
        const asOfDateInput = document.getElementById('reportAsOfDate');
        
        if (startDateInput && endDateInput) {
            const defaultStartDate = new Date();
            defaultStartDate.setMonth(defaultStartDate.getMonth() - 1);
            const defaultEndDate = new Date();
            startDateInput.value = this.formatDateForInput(defaultStartDate.toISOString());
            endDateInput.value = this.formatDateForInput(defaultEndDate.toISOString());
        }
        
        if (asOfDateInput) {
            asOfDateInput.value = this.formatDateForInput(new Date().toISOString());
        }
        
        // Clear account filter
        const accountSelect = document.getElementById('reportAccountSelect');
        if (accountSelect) {
            accountSelect.value = '';
        }
        
        // Reset pagination
        this.reportCurrentPage = 1;
        
        // Regenerate report
        if (this.currentReportType) {
            this.generateReport(this.currentReportType);
        }
    }
    

ProfessionalAccounting.prototype.updateReportPagination = function() {
        const totalCount = this.getReportTotalCount();
        this.reportTotalPages = totalCount > 0 ? Math.max(1, Math.ceil(totalCount / this.reportPerPage)) : 1;
        
        const paginationInfo = document.getElementById('reportPaginationInfo');
        const paginationControls = document.getElementById('reportPaginationControls');
        
        if (paginationInfo) {
            const start = totalCount === 0 ? 0 : (this.reportCurrentPage - 1) * this.reportPerPage + 1;
            const end = totalCount === 0 ? 0 : Math.min(totalCount, this.reportCurrentPage * this.reportPerPage);
            paginationInfo.textContent = `Showing ${start} to ${end} of ${totalCount} entries`;
        }
        
        if (paginationControls) {
            paginationControls.innerHTML = '';
            
            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn-pagination btn-pagination-prev';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i> <span>Previous</span>';
            prevBtn.disabled = this.reportCurrentPage === 1;
            prevBtn.title = 'Previous Page';
            prevBtn.addEventListener('click', () => {
                if (this.reportCurrentPage > 1) {
                    this.reportCurrentPage--;
                    this.refreshReportDisplay();
                }
            });
            paginationControls.appendChild(prevBtn);
            
            // Page numbers (show max 10 pages, with ellipsis if needed)
            const maxVisiblePages = 10;
            let startPage = Math.max(1, this.reportCurrentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(this.reportTotalPages, startPage + maxVisiblePages - 1);
            
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            if (startPage > 1) {
                const firstBtn = document.createElement('button');
                firstBtn.className = 'btn-pagination';
                firstBtn.textContent = '1';
                firstBtn.addEventListener('click', () => {
                    this.reportCurrentPage = 1;
                    this.refreshReportDisplay();
                });
                paginationControls.appendChild(firstBtn);
                
                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '...';
                    paginationControls.appendChild(ellipsis);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn-pagination ${i === this.reportCurrentPage ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    this.reportCurrentPage = i;
                    this.refreshReportDisplay();
                });
                paginationControls.appendChild(pageBtn);
            }
            
            if (endPage < this.reportTotalPages) {
                if (endPage < this.reportTotalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '...';
                    paginationControls.appendChild(ellipsis);
                }
                
                const lastBtn = document.createElement('button');
                lastBtn.className = 'btn-pagination';
                lastBtn.textContent = this.reportTotalPages;
                lastBtn.addEventListener('click', () => {
                    this.reportCurrentPage = this.reportTotalPages;
                    this.refreshReportDisplay();
                });
                paginationControls.appendChild(lastBtn);
            }
            
            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn-pagination btn-pagination-next';
            nextBtn.innerHTML = '<span>Next</span> <i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = this.reportCurrentPage === this.reportTotalPages;
            nextBtn.title = 'Next Page';
            nextBtn.addEventListener('click', () => {
                if (this.reportCurrentPage < this.reportTotalPages) {
                    this.reportCurrentPage++;
                    this.refreshReportDisplay();
                }
            });
            paginationControls.appendChild(nextBtn);
        }
        
        // Update table wrapper scrolling
        const tableWrappers = document.querySelectorAll('.professional-report-table-wrapper');
        tableWrappers.forEach(wrapper => {
            wrapper.setAttribute('data-per-page', this.reportPerPage.toString());
            if (this.reportPerPage > 5) {
                wrapper.classList.add('report-table-scroll');
            } else {
                wrapper.classList.remove('report-table-scroll');
            }
        });
    }
    

ProfessionalAccounting.prototype.getReportTotalCount = function() {
        // If reportTotalCount is already set (from format function with search applied), use it
        if (this.reportTotalCount !== undefined && this.reportTotalCount !== null) {
            return this.reportTotalCount;
        }
        
        if (!this.currentReportData) return 0;
        
        switch (this.currentReportType) {
            case 'trial-balance':
                return this.currentReportData.accounts?.length || 0;
            case 'income-statement':
            case 'profit-loss':
                // Count revenue and expenses
                const revenueCount = this.currentReportData.revenue?.length || 0;
                const expensesCount = this.currentReportData.expenses?.length || 0;
                return revenueCount + expensesCount;
            case 'balance-sheet':
                // Count assets, liabilities, and equity
                const assetsCount = this.currentReportData.assets?.length || 0;
                const liabilitiesCount = this.currentReportData.liabilities?.length || 0;
                const equityCount = this.currentReportData.equity?.length || 0;
                return assetsCount + liabilitiesCount + equityCount;
            case 'cash-flow':
                return this.currentReportData.operating?.length || 0;
            case 'aged-receivables':
            case 'ages-debt-receivable':
            case 'ages-credit-receivable':
                return this.currentReportData.receivables?.length || 0;
            case 'aged-payables':
                return this.currentReportData.payables?.length || 0;
            case 'cash-book':
                return this.currentReportData.transactions?.length || 0;
            case 'bank-book':
                return this.currentReportData.transactions?.length || 0;
            case 'general-ledger':
            case 'general-ledger-report':
                return this.currentReportData.accounts?.length || 0;
            case 'account-statement':
                return this.currentReportData.accounts?.length || 0;
            case 'expense-statement':
                return this.currentReportData.expenses?.length || 0;
            case 'chart-of-accounts-report':
                return this.currentReportData.accounts?.length || 0;
            case 'value-added':
                return this.currentReportData.data?.length || 0;
            case 'fixed-assets':
                return this.currentReportData.assets?.length || 0;
            case 'entries-by-year':
                return this.currentReportData.data?.length || 0;
            case 'customer-debits':
                return this.currentReportData.customers?.length || 0;
            case 'statistical-position':
                return this.currentReportData.data?.length || 0;
            case 'changes-equity':
                // Count all equity changes entries (flattened from equity_changes)
                if (this.currentReportData.data && this.currentReportData.data.length > 0) {
                    return this.currentReportData.data.length;
                }
                if (this.currentReportData.equity_changes) {
                    return this.currentReportData.equity_changes.reduce((sum, equity) => {
                        return sum + (equity.monthly_changes?.length || 1);
                    }, 0);
                }
                return 0;
            case 'financial-performance':
                return this.currentReportData.performance_data?.length || 0;
            case 'comparative-report':
                return this.currentReportData.data?.length || 0;
            default:
                return 0;
        }
    }


ProfessionalAccounting.prototype.displayReportPlaceholderInPopup = function(reportType, reportName) {
        // Show report table with empty data instead of placeholder message
        this.displayReportInPopup(reportType, reportName, {});
        this.showToast(`${reportName} report displayed (no data available)`, 'info');
    }

    // Keep old displayReport for backward compatibility (if used elsewhere)

ProfessionalAccounting.prototype.displayReport = function(reportType, reportName, reportData) {
        this.displayReportInPopup(reportType, reportName, reportData);
    }


ProfessionalAccounting.prototype.formatTrialBalance = function(reportData) {
        // Get all accounts and apply search filter
        let allAccounts = reportData.accounts || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allAccounts = allAccounts.filter(account => {
                const accountCode = (account.account_code || '').toLowerCase();
                const accountName = (account.account_name || '').toLowerCase();
                return accountCode.includes(searchTerm) || accountName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allAccounts.length;
        
        // Get paginated accounts (if perPage is 999999, show all)
        let paginatedAccounts = allAccounts;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedAccounts = allAccounts.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-debit text-right">Debit</th>';
        html += '<th class="report-col-credit text-right">Credit</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedAccounts.length > 0) {
            paginatedAccounts.forEach((account, index) => {
                const balance = parseFloat(account.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(account.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(account.account_name || '')}</td>`;
                html += `<td class="report-col-debit text-right debit-cell">${parseFloat(account.debit || 0) > 0 ? this.formatCurrency(parseFloat(account.debit || 0)) : '-'}</td>`;
                html += `<td class="report-col-credit text-right credit-cell">${parseFloat(account.credit || 0) > 0 ? this.formatCurrency(parseFloat(account.credit || 0)) : '-'}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No accounts found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="2" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.difference || 0))}</strong></td>`;
            html += '</tr>';
            if (Math.abs(parseFloat(reportData.totals.difference || 0)) > 0.01) {
                html += '<tr class="report-balance-warning">';
                html += '<td colspan="5" class="text-center"><i class="fas fa-exclamation-triangle"></i> Trial Balance is not balanced!</td>';
                html += '</tr>';
            }
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatIncomeStatement = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        // Get revenue and expenses, apply search filter
        let revenue = reportData.revenue || [];
        let expenses = reportData.expenses || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            revenue = revenue.filter(item => {
                const month = (item.month || '').toLowerCase();
                return month.includes(searchTerm);
            });
            expenses = expenses.filter(item => {
                const month = (item.month || '').toLowerCase();
                return month.includes(searchTerm);
            });
        }
        
        // Update total count for pagination (combined revenue and expenses)
        this.reportTotalCount = revenue.length + expenses.length;
        
        // Revenue Section
        html += '<div class="report-section revenue-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-arrow-up"></i> Revenue</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr><th class="report-col-period">Period</th><th class="report-col-amount text-right">Total Revenue</th></tr></thead>';
        html += '<tbody>';
        
        if (revenue.length > 0) {
            revenue.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.total_revenue || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="2" class="text-center report-empty-state">No revenue data</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Expenses Section
        html += '<div class="report-section expense-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-arrow-down"></i> Expenses</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr><th class="report-col-period">Period</th><th class="report-col-amount text-right">Total Expenses</th></tr></thead>';
        html += '<tbody>';
        
        if (expenses.length > 0) {
            expenses.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.total_expenses || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="2" class="text-center report-empty-state">No expense data</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Summary Section
        if (reportData.totals) {
            html += '<div class="report-summary-section">';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table report-summary-table">';
            html += '<tbody>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Revenue:</strong></td>';
            html += `<td class="report-summary-value text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_revenue || 0))}</strong></td>`;
            html += '</tr>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Expenses:</strong></td>';
            html += `<td class="report-summary-value text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_expenses || 0))}</strong></td>`;
            html += '</tr>';
            const netIncome = parseFloat(reportData.totals.net_income || 0);
            html += '<tr class="report-summary-row report-net-income-row">';
            html += '<td class="report-summary-label"><strong>Net Income:</strong></td>';
            html += `<td class="report-summary-value text-right ${netIncome >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(netIncome)}</strong></td>`;
            html += '</tr>';
            html += '</tbody></table></div></div>';
        }
        
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.formatBalanceSheet = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        // Get assets, liabilities, and equity, apply search filter
        let assets = reportData.assets || [];
        let liabilities = reportData.liabilities || [];
        let equity = reportData.equity || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            assets = assets.filter(item => {
                const code = (item.account_code || '').toLowerCase();
                const name = (item.account_name || '').toLowerCase();
                return code.includes(searchTerm) || name.includes(searchTerm);
            });
            liabilities = liabilities.filter(item => {
                const code = (item.account_code || '').toLowerCase();
                const name = (item.account_name || '').toLowerCase();
                return code.includes(searchTerm) || name.includes(searchTerm);
            });
            equity = equity.filter(item => {
                const code = (item.account_code || '').toLowerCase();
                const name = (item.account_name || '').toLowerCase();
                return code.includes(searchTerm) || name.includes(searchTerm);
            });
        }
        
        // Update total count for pagination (combined all sections)
        this.reportTotalCount = assets.length + liabilities.length + equity.length;
        
        // Assets Section
        html += '<div class="report-section assets-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-wallet"></i> Assets</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
        html += '<tbody>';
        
        if (assets.length > 0) {
            assets.forEach((asset, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(asset.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(asset.account_name || '')}</td>`;
                html += `<td class="report-col-balance text-right">${this.formatCurrency(parseFloat(asset.balance || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No assets found</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Liabilities Section
        html += '<div class="report-section liabilities-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-file-invoice-dollar"></i> Liabilities</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
        html += '<tbody>';
        
        if (liabilities.length > 0) {
            liabilities.forEach((liability, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(liability.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(liability.account_name || '')}</td>`;
                html += `<td class="report-col-balance text-right debit-cell">${this.formatCurrency(parseFloat(liability.balance || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No liabilities found</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Equity Section
        html += '<div class="report-section equity-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-chart-line"></i> Equity</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
        html += '<tbody>';
        
        if (equity.length > 0) {
            equity.forEach((equityItem, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(equityItem.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(equityItem.account_name || '')}</td>`;
                html += `<td class="report-col-balance text-right">${this.formatCurrency(parseFloat(equityItem.balance || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No equity found</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Summary Section
        if (reportData.totals) {
            html += '<div class="report-summary-section">';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table report-summary-table">';
            html += '<tbody>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Assets:</strong></td>';
            html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_assets || 0))}</strong></td>`;
            html += '</tr>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Liabilities:</strong></td>';
            html += `<td class="report-summary-value text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_liabilities || 0))}</strong></td>`;
            html += '</tr>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Equity:</strong></td>';
            html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_equity || 0))}</strong></td>`;
            html += '</tr>';
            html += '<tr class="report-summary-row report-net-income-row">';
            html += '<td class="report-summary-label"><strong>Total Liabilities + Equity:</strong></td>';
            html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_liabilities_equity || 0))}</strong></td>`;
            html += '</tr>';
            const balanceCheck = Math.abs(parseFloat(reportData.totals.total_assets || 0) - parseFloat(reportData.totals.total_liabilities_equity || 0));
            if (balanceCheck > 0.01) {
                html += '<tr class="report-balance-warning">';
                html += '<td colspan="2" class="text-center"><i class="fas fa-exclamation-triangle"></i> Balance Sheet is not balanced! Difference: ' + this.formatCurrency(balanceCheck) + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table></div></div>';
        }
        
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.formatCashFlow = function(reportData) {
        // Get all operating activities and apply search filter
        let allOperating = reportData?.operating || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allOperating = allOperating.filter(item => {
                const period = (item.period || '').toLowerCase();
                const description = (item.description || '').toLowerCase();
                return period.includes(searchTerm) || description.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allOperating.length;
        
        // Get paginated operating activities (if perPage is 999999, show all)
        let paginatedOperating = allOperating;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedOperating = allOperating.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-sections">';
        
        html += '<div class="report-section operating-section">';
        html += '<div class="report-section-header">';
        html += '<h4><i class="fas fa-money-bill-wave"></i> Operating Activities</h4>';
        html += '</div>';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-period">Period</th>';
        html += '<th class="report-col-amount text-right">Cash In</th>';
        html += '<th class="report-col-amount text-right">Cash Out</th>';
        html += '<th class="report-col-amount text-right">Net Flow</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedOperating.length > 0) {
            paginatedOperating.forEach((item, index) => {
                const netFlow = parseFloat(item.cash_in || 0) - parseFloat(item.cash_out || 0);
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.cash_in || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.cash_out || 0))}</td>`;
                html += `<td class="report-col-amount text-right ${netFlow >= 0 ? 'credit-cell' : 'debit-cell'}">${this.formatCurrency(netFlow)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" class="text-center report-empty-state">No cash flow data</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        
        // Summary Section
        if (reportData.totals) {
            html += '<div class="report-summary-section">';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table report-summary-table">';
            html += '<tbody>';
            const operatingFlow = parseFloat(reportData.totals.operating_cash_flow || 0);
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Operating Cash Flow:</strong></td>';
            html += `<td class="report-summary-value text-right ${operatingFlow >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(operatingFlow)}</strong></td>`;
            html += '</tr>';
            const netFlow = parseFloat(reportData.totals.net_cash_flow || 0);
            html += '<tr class="report-summary-row report-net-income-row">';
            html += '<td class="report-summary-label"><strong>Net Cash Flow:</strong></td>';
            html += `<td class="report-summary-value text-right ${netFlow >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(netFlow)}</strong></td>`;
            html += '</tr>';
            html += '</tbody></table></div></div>';
        }
        
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.formatAgedReceivables = function(reportData) {
        // Get all receivables and apply search filter
        let allReceivables = reportData?.receivables || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allReceivables = allReceivables.filter(item => {
                const invoiceNumber = (item.invoice_number || '').toLowerCase();
                const customerName = (item.customer_name || '').toLowerCase();
                return invoiceNumber.includes(searchTerm) || customerName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allReceivables.length;
        
        // Get paginated receivables (if perPage is 999999, show all)
        let paginatedReceivables = allReceivables;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedReceivables = allReceivables.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-invoice">Invoice #</th>';
        html += '<th class="report-col-customer">Customer</th>';
        html += '<th class="report-col-date">Invoice Date</th>';
        html += '<th class="report-col-date">Due Date</th>';
        html += '<th class="report-col-amount text-right">Total Amount</th>';
        html += '<th class="report-col-amount text-right">Paid</th>';
        html += '<th class="report-col-amount text-right">Balance</th>';
        html += '<th class="report-col-days text-right">Days Overdue</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedReceivables) && paginatedReceivables.length > 0) {
            paginatedReceivables.forEach((item, index) => {
                const daysOverdue = parseInt(item.days_overdue || 0);
                const overdueClass = daysOverdue > 0 ? 'overdue' : '';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'} ${overdueClass}">`;
                const invoiceNumber = this.escapeHtml(item.invoice_number || '');
                const customerName = this.escapeHtml(item.customer_name || '');
                html += `<td class="report-col-invoice" title="${invoiceNumber}"><code>${invoiceNumber}</code></td>`;
                html += `<td class="report-col-customer" title="${customerName}">${customerName}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.invoice_date || '')}">${this.formatDate(item.invoice_date || '')}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.due_date || '')}">${this.formatDate(item.due_date || '')}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.paid_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.balance || 0))}</td>`;
                html += `<td class="report-col-days text-right ${daysOverdue > 0 ? 'overdue-badge' : ''}">${daysOverdue > 0 ? daysOverdue : '-'}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="8" class="text-center report-empty-state">No receivables found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.total_outstanding !== undefined) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="6" class="report-totals-label"><strong>Total Outstanding:</strong></td>';
            html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.total_outstanding || 0))}</strong></td>`;
            html += '<td></td>';
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatAgedPayables = function(reportData) {
        // Get all payables and apply search filter
        let allPayables = reportData?.payables || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allPayables = allPayables.filter(item => {
                const billNumber = (item.bill_number || '').toLowerCase();
                const vendorName = (item.vendor_name || '').toLowerCase();
                return billNumber.includes(searchTerm) || vendorName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allPayables.length;
        
        // Get paginated payables (if perPage is 999999, show all)
        let paginatedPayables = allPayables;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedPayables = allPayables.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-invoice">Bill #</th>';
        html += '<th class="report-col-customer">Vendor</th>';
        html += '<th class="report-col-date">Bill Date</th>';
        html += '<th class="report-col-date">Due Date</th>';
        html += '<th class="report-col-amount text-right">Total Amount</th>';
        html += '<th class="report-col-amount text-right">Paid</th>';
        html += '<th class="report-col-amount text-right">Balance</th>';
        html += '<th class="report-col-days text-right">Days Overdue</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedPayables) && paginatedPayables.length > 0) {
            paginatedPayables.forEach((item, index) => {
                const daysOverdue = parseInt(item.days_overdue || 0);
                const overdueClass = daysOverdue > 0 ? 'overdue' : '';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'} ${overdueClass}">`;
                const billNumber = this.escapeHtml(item.bill_number || '');
                const vendorName = this.escapeHtml(item.vendor_name || '');
                html += `<td class="report-col-invoice" title="${billNumber}"><code>${billNumber}</code></td>`;
                html += `<td class="report-col-customer" title="${vendorName}">${vendorName}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.bill_date || '')}">${this.formatDate(item.bill_date || '')}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.due_date || '')}">${this.formatDate(item.due_date || '')}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.paid_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.balance || 0))}</td>`;
                html += `<td class="report-col-days text-right ${daysOverdue > 0 ? 'overdue-badge' : ''}">${daysOverdue > 0 ? daysOverdue : '-'}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="8" class="text-center report-empty-state">No payables found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.total_outstanding !== undefined) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="6" class="report-totals-label"><strong>Total Outstanding:</strong></td>';
            html += `<td class="report-col-amount text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.total_outstanding || 0))}</strong></td>`;
            html += '<td></td>';
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatCashBook = function(reportData) {
        // Get all transactions and apply search filter
        let allTransactions = reportData?.transactions || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allTransactions = allTransactions.filter(txn => {
                const description = (txn.description || '').toLowerCase();
                const reference = (txn.reference_number || '').toLowerCase();
                const type = (txn.transaction_type || '').toLowerCase();
                return description.includes(searchTerm) || reference.includes(searchTerm) || type.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allTransactions.length;
        
        // Get paginated transactions (if perPage is 999999, show all)
        let paginatedTransactions = allTransactions;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedTransactions = allTransactions.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-name">Reference</th>';
        html += '<th class="report-col-type">Type</th>';
        html += '<th class="report-col-debit text-right">Debit</th>';
        html += '<th class="report-col-credit text-right">Credit</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedTransactions) && paginatedTransactions.length > 0) {
            paginatedTransactions.forEach((txn, index) => {
                const balance = parseFloat(txn.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                html += `<td class="report-col-debit text-right debit-cell">${parseFloat(txn.debit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.debit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-credit text-right credit-cell">${parseFloat(txn.credit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.credit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" class="text-center report-empty-state">No cash transactions found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="4" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.closing_balance || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatBankBook = function(reportData) {
        // Get all transactions and apply search filter
        let allTransactions = reportData?.transactions || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allTransactions = allTransactions.filter(txn => {
                const description = (txn.description || '').toLowerCase();
                const reference = (txn.reference_number || '').toLowerCase();
                const type = (txn.transaction_type || '').toLowerCase();
                const bankName = (txn.bank_account_name || '').toLowerCase();
                return description.includes(searchTerm) || reference.includes(searchTerm) || type.includes(searchTerm) || bankName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allTransactions.length;
        
        // Get paginated transactions (if perPage is 999999, show all)
        let paginatedTransactions = allTransactions;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedTransactions = allTransactions.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-name">Reference</th>';
        html += '<th class="report-col-name">Bank Account</th>';
        html += '<th class="report-col-type">Type</th>';
        html += '<th class="report-col-debit text-right">Debit</th>';
        html += '<th class="report-col-credit text-right">Credit</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedTransactions) && paginatedTransactions.length > 0) {
            paginatedTransactions.forEach((txn, index) => {
                const balance = parseFloat(txn.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.bank_account_name || 'N/A')}</td>`;
                html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                html += `<td class="report-col-debit text-right debit-cell">${parseFloat(txn.debit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.debit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-credit text-right credit-cell">${parseFloat(txn.credit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.credit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="8" class="text-center report-empty-state">No bank transactions found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="5" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.closing_balance || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.getReportStatusCards = function(reportType, reportData) {
        switch (reportType) {
            case 'general-ledger':
            case 'general-ledger-report':
                return this.getGeneralLedgerStatusCards(reportData);
            case 'trial-balance':
                return this.getTrialBalanceStatusCards(reportData);
            case 'income-statement':
            case 'profit-loss':
                return this.getIncomeStatementStatusCards(reportData);
            case 'balance-sheet':
                return this.getBalanceSheetStatusCards(reportData);
            case 'cash-flow':
                return this.getCashFlowStatusCards(reportData);
            case 'aged-receivables':
            case 'ages-debt-receivable':
            case 'ages-credit-receivable':
                return this.getAgedReceivablesStatusCards(reportData);
            case 'aged-payables':
                return this.getAgedPayablesStatusCards(reportData);
            case 'cash-book':
                return this.getCashBookStatusCards(reportData);
            case 'bank-book':
                return this.getBankBookStatusCards(reportData);
            case 'account-statement':
                return this.getAccountStatementStatusCards(reportData);
            case 'expense-statement':
                return this.getExpenseStatementStatusCards(reportData);
            case 'chart-of-accounts-report':
                return this.getChartOfAccountsStatusCards(reportData);
            case 'value-added':
                return this.getValueAddedStatusCards(reportData);
            case 'fixed-assets':
                return this.getFixedAssetsStatusCards(reportData);
            case 'entries-by-year':
                return this.getEntriesByYearStatusCards(reportData);
            case 'customer-debits':
                return this.getCustomerDebitsStatusCards(reportData);
            case 'statistical-position':
                return this.getStatisticalPositionStatusCards(reportData);
            case 'changes-equity':
                return this.getChangesInEquityStatusCards(reportData);
            case 'financial-performance':
                return this.getFinancialPerformanceStatusCards(reportData);
            case 'comparative-report':
                return this.getComparativeReportStatusCards(reportData);
            default:
                return '';
        }
    }


ProfessionalAccounting.prototype.getGeneralLedgerStatusCards = function(reportData) {
        const allAccounts = reportData?.accounts || [];
        
        // Calculate statistics for status cards
        const totalAccounts = allAccounts.length;
        const accountsWithTransactions = allAccounts.filter(acc => (acc.transactions || []).length > 0).length;
        const accountsWithoutTransactions = totalAccounts - accountsWithTransactions;
        const totalTransactions = allAccounts.reduce((sum, acc) => sum + (acc.transactions || []).length, 0);
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const balance = totalDebit - totalCredit;
        const difference = parseFloat(reportData?.totals?.difference || 0);
        
        let html = '<div class="report-status-cards">';
        html += '<div class="stat-card stat-card-primary">';
        html += '<i class="fas fa-book stat-icon stat-icon-primary"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${totalAccounts}</span>`;
        html += '<span class="stat-label">Total Accounts</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-success">';
        html += '<i class="fas fa-check-circle stat-icon stat-icon-success"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${accountsWithTransactions}</span>`;
        html += '<span class="stat-label">With Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-warning">';
        html += '<i class="fas fa-exclamation-circle stat-icon stat-icon-warning"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${accountsWithoutTransactions}</span>`;
        html += '<span class="stat-label">No Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-info">';
        html += '<i class="fas fa-exchange-alt stat-icon stat-icon-info"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${totalTransactions}</span>`;
        html += '<span class="stat-label">Total Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-debit">';
        html += '<i class="fas fa-arrow-down stat-icon stat-icon-debit"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${this.formatCurrency(totalDebit)}</span>`;
        html += '<span class="stat-label">Total Debit</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-credit">';
        html += '<i class="fas fa-arrow-up stat-icon stat-icon-credit"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${this.formatCurrency(totalCredit)}</span>`;
        html += '<span class="stat-label">Total Credit</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-balance">';
        html += `<i class="fas fa-balance-scale stat-icon stat-icon-${balance >= 0 ? 'positive' : 'negative'}"></i>`;
        html += '<div class="stat-info">';
        html += `<span class="stat-value ${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${this.formatCurrency(balance)}</span>`;
        html += '<span class="stat-label">Balance</span>';
        html += '</div>';
        html += '</div>';
        
        if (difference > 0) {
            html += '<div class="stat-card stat-card-warning">';
            html += '<i class="fas fa-exclamation-triangle stat-icon stat-icon-warning"></i>';
            html += '<div class="stat-info">';
            html += `<span class="stat-value">${this.formatCurrency(difference)}</span>`;
            html += '<span class="stat-label">Difference</span>';
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getTrialBalanceStatusCards = function(reportData) {
        const accounts = reportData?.accounts || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const difference = Math.abs(totalDebit - totalCredit);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-balance-scale', accounts.length, 'Total Accounts');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-equals', this.formatCurrency(difference), 'Difference');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getIncomeStatementStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.totals?.total_revenue || 0);
        const expenses = parseFloat(reportData?.totals?.total_expenses || 0);
        const netIncome = revenue - expenses;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Total Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(expenses), 'Total Expenses');
        html += this.createStatCard(netIncome >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netIncome), 'Net Income');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getBalanceSheetStatusCards = function(reportData) {
        const assets = parseFloat(reportData?.totals?.total_assets || 0);
        const liabilities = parseFloat(reportData?.totals?.total_liabilities || 0);
        const equity = parseFloat(reportData?.totals?.total_equity || 0);
        const balance = Math.abs(assets - (liabilities + equity));
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-building', this.formatCurrency(assets), 'Total Assets');
        html += this.createStatCard('debit', 'fa-file-invoice-dollar', this.formatCurrency(liabilities), 'Total Liabilities');
        html += this.createStatCard('info', 'fa-hand-holding-usd', this.formatCurrency(equity), 'Total Equity');
        html += this.createStatCard('balance', 'fa-balance-scale', this.formatCurrency(balance), 'Balance');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getCashFlowStatusCards = function(reportData) {
        // Check multiple possible keys for cash flow totals
        const operating = parseFloat(reportData?.totals?.operating_cash_flow || reportData?.totals?.operating_activities || 0);
        const investing = parseFloat(reportData?.totals?.investing_cash_flow || reportData?.totals?.investing_activities || 0);
        const financing = parseFloat(reportData?.totals?.financing_cash_flow || reportData?.totals?.financing_activities || 0);
        const netCash = parseFloat(reportData?.totals?.net_cash_flow || (operating + investing + financing));
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-exchange-alt', this.formatCurrency(operating), 'Operating');
        html += this.createStatCard('primary', 'fa-chart-line', this.formatCurrency(investing), 'Investing');
        html += this.createStatCard('success', 'fa-money-bill-wave', this.formatCurrency(financing), 'Financing');
        html += this.createStatCard(netCash >= 0 ? 'success' : 'debit', 'fa-wallet', this.formatCurrency(netCash), 'Net Cash Flow');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getAgedReceivablesStatusCards = function(reportData) {
        const receivables = reportData?.receivables || [];
        const totalOutstanding = parseFloat(reportData?.total_outstanding || 0);
        const current = receivables.filter(r => parseInt(r.days_overdue || 0) <= 30).length;
        const overdue = receivables.filter(r => parseInt(r.days_overdue || 0) > 30).length;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-file-invoice-dollar', receivables.length, 'Total Receivables');
        html += this.createStatCard('success', 'fa-check-circle', current, 'Current');
        html += this.createStatCard('warning', 'fa-exclamation-triangle', overdue, 'Overdue');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalOutstanding), 'Outstanding');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getAgedPayablesStatusCards = function(reportData) {
        const payables = reportData?.payables || [];
        const totalOutstanding = parseFloat(reportData?.total_outstanding || 0);
        const current = payables.filter(p => parseInt(p.days_overdue || 0) <= 30).length;
        const overdue = payables.filter(p => parseInt(p.days_overdue || 0) > 30).length;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-file-invoice', payables.length, 'Total Payables');
        html += this.createStatCard('success', 'fa-check-circle', current, 'Current');
        html += this.createStatCard('warning', 'fa-exclamation-triangle', overdue, 'Overdue');
        html += this.createStatCard('credit', 'fa-dollar-sign', this.formatCurrency(totalOutstanding), 'Outstanding');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getCashBookStatusCards = function(reportData) {
        const transactions = reportData?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const closingBalance = parseFloat(reportData?.totals?.closing_balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-exchange-alt', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-wallet', this.formatCurrency(closingBalance), 'Closing Balance');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getBankBookStatusCards = function(reportData) {
        const transactions = reportData?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const closingBalance = parseFloat(reportData?.totals?.closing_balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-university', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-university', this.formatCurrency(closingBalance), 'Closing Balance');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getAccountStatementStatusCards = function(reportData) {
        const transactions = reportData?.accounts?.[0]?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const balance = parseFloat(reportData?.totals?.balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-list', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-balance-scale', this.formatCurrency(balance), 'Balance');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getExpenseStatementStatusCards = function(reportData) {
        const expenses = reportData?.expenses || [];
        const totalExpenses = parseFloat(reportData?.totals?.total_expenses || 0);
        const categories = new Set(expenses.map(e => e.category || 'Uncategorized')).size;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-list', expenses.length, 'Expenses');
        html += this.createStatCard('info', 'fa-tags', categories, 'Categories');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalExpenses), 'Total Expenses');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getChartOfAccountsStatusCards = function(reportData) {
        const accounts = reportData?.accounts || [];
        const active = accounts.filter(a => a.is_active !== false).length;
        const inactive = accounts.length - active;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-book', accounts.length, 'Total Accounts');
        html += this.createStatCard('success', 'fa-check-circle', active, 'Active');
        html += this.createStatCard('warning', 'fa-times-circle', inactive, 'Inactive');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getValueAddedStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.revenue || 0);
        const cogs = parseFloat(reportData?.cogs || 0);
        const valueAdded = revenue - cogs;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(cogs), 'COGS');
        html += this.createStatCard('info', 'fa-plus-circle', this.formatCurrency(valueAdded), 'Value Added');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getFixedAssetsStatusCards = function(reportData) {
        const assets = reportData?.assets || [];
        const totalValue = parseFloat(reportData?.totals?.total_value || 0);
        const totalDepreciation = parseFloat(reportData?.totals?.total_depreciation || 0);
        const netValue = totalValue - totalDepreciation;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-building', assets.length, 'Assets');
        html += this.createStatCard('success', 'fa-dollar-sign', this.formatCurrency(totalValue), 'Total Value');
        html += this.createStatCard('warning', 'fa-arrow-down', this.formatCurrency(totalDepreciation), 'Depreciation');
        html += this.createStatCard('info', 'fa-calculator', this.formatCurrency(netValue), 'Net Value');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getEntriesByYearStatusCards = function(reportData) {
        const entries = reportData?.entries || [];
        const years = new Set(entries.map(e => e.year || 'Unknown')).size;
        const totalEntries = entries.reduce((sum, e) => sum + (e.count || 0), 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-calendar-alt', years, 'Years');
        html += this.createStatCard('info', 'fa-list', totalEntries, 'Total Entries');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getCustomerDebitsStatusCards = function(reportData) {
        const debits = reportData?.debits || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const customers = new Set(debits.map(d => d.customer_id || d.customer_name)).size;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-users', customers, 'Customers');
        html += this.createStatCard('info', 'fa-list', debits.length, 'Debits');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalDebit), 'Total Debit');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getStatisticalPositionStatusCards = function(reportData) {
        const accounts = reportData?.statistics?.total_accounts || 0;
        const transactions = reportData?.statistics?.total_transactions || 0;
        const receivables = reportData?.statistics?.total_receivables || 0;
        const payables = reportData?.statistics?.total_payables || 0;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-book', accounts, 'Accounts');
        html += this.createStatCard('info', 'fa-exchange-alt', transactions, 'Transactions');
        html += this.createStatCard('success', 'fa-file-invoice-dollar', receivables, 'Receivables');
        html += this.createStatCard('warning', 'fa-file-invoice', payables, 'Payables');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getChangesInEquityStatusCards = function(reportData) {
        const changes = reportData?.equity_changes || [];
        const opening = parseFloat(reportData?.totals?.opening_equity || 0);
        const closing = parseFloat(reportData?.totals?.closing_equity || 0);
        const netChange = closing - opening;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-arrow-left', this.formatCurrency(opening), 'Opening');
        html += this.createStatCard('primary', 'fa-list', changes.length, 'Changes');
        html += this.createStatCard('success', 'fa-arrow-right', this.formatCurrency(closing), 'Closing');
        html += this.createStatCard(netChange >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netChange), 'Net Change');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getFinancialPerformanceStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.performance_data?.revenue || 0);
        const expenses = parseFloat(reportData?.performance_data?.expenses || 0);
        const netIncome = revenue - expenses;
        const profitMargin = revenue > 0 ? ((netIncome / revenue) * 100).toFixed(1) + '%' : '0%';
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(expenses), 'Expenses');
        html += this.createStatCard(netIncome >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netIncome), 'Net Income');
        html += this.createStatCard('info', 'fa-percentage', profitMargin, 'Profit Margin');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.getComparativeReportStatusCards = function(reportData) {
        const currentRevenue = parseFloat(reportData?.current_period?.revenue || 0);
        const previousRevenue = parseFloat(reportData?.previous_period?.revenue || 0);
        const revenueChange = currentRevenue - previousRevenue;
        const revenueChangePercent = previousRevenue > 0 ? ((revenueChange / previousRevenue) * 100).toFixed(1) + '%' : '0%';
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-calendar-check', this.formatCurrency(currentRevenue), 'Current Revenue');
        html += this.createStatCard('info', 'fa-calendar', this.formatCurrency(previousRevenue), 'Previous Revenue');
        html += this.createStatCard(revenueChange >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(revenueChange), 'Change');
        html += this.createStatCard('primary', 'fa-percentage', revenueChangePercent, 'Change %');
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.createStatCard = function(type, icon, value, label) {
        const typeClass = `stat-card-${type}`;
        const iconClass = `stat-icon-${type}`;
        return `<div class="stat-card ${typeClass}">
            <i class="fas ${icon} stat-icon ${iconClass}"></i>
            <div class="stat-info">
                <span class="stat-value">${value}</span>
                <span class="stat-label">${label}</span>
            </div>
        </div>`;
    }


ProfessionalAccounting.prototype.formatGeneralLedgerReport = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        const allAccounts = reportData?.accounts || [];
        
        if (Array.isArray(allAccounts) && allAccounts.length > 0) {
            // Apply search filter if search term exists
            let filteredAccounts = allAccounts;
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                filteredAccounts = allAccounts.filter(account => {
                    const accountCode = (account.account_code || '').toLowerCase();
                    const accountName = (account.account_name || '').toLowerCase();
                    const hasMatchingTransaction = (account.transactions || []).some(txn => {
                        const description = (txn.description || '').toLowerCase();
                        const reference = (txn.reference_number || '').toLowerCase();
                        return description.includes(searchTerm) || reference.includes(searchTerm);
                    });
                    return accountCode.includes(searchTerm) || 
                           accountName.includes(searchTerm) || 
                           hasMatchingTransaction;
                });
            }
            
            // Apply pagination to filtered accounts (if perPage is 999999, show all)
            let paginatedAccounts = filteredAccounts;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedAccounts = filteredAccounts.slice(startIndex, endIndex);
            }
            
            // Update total count for pagination (use filtered count)
            this.reportTotalCount = filteredAccounts.length;
            
            paginatedAccounts.forEach((account, accIndex) => {
                html += `<div class="report-section">`;
                html += `<div class="report-section-header">`;
                html += `<h4><code>${this.escapeHtml(account.account_code || '')}</code> ${this.escapeHtml(account.account_name || '')}</h4>`;
                html += `</div>`;
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table">';
                html += '<thead>';
                html += '<tr>';
                html += '<th class="report-col-date">Date</th>';
                html += '<th class="report-col-name">Description</th>';
                html += '<th class="report-col-name">Reference</th>';
                html += '<th class="report-col-type">Type</th>';
                html += '<th class="report-col-debit text-right">Debit</th>';
                html += '<th class="report-col-credit text-right">Credit</th>';
                html += '<th class="report-col-balance text-right">Balance</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                const transactions = account.transactions || [];
                if (transactions.length > 0) {
                    let runningBalance = 0;
                    transactions.forEach((txn, txnIndex) => {
                        const debit = parseFloat(txn.debit_amount || 0);
                        const credit = parseFloat(txn.credit_amount || 0);
                        runningBalance += (debit - credit);
                        const balanceClass = runningBalance >= 0 ? 'balance-positive' : 'balance-negative';
                        html += `<tr class="report-data-row ${txnIndex % 2 === 0 ? 'even' : 'odd'}">`;
                        html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                        html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                        html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                        html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                        html += `<td class="report-col-debit text-right debit-cell">${debit > 0 ? this.formatCurrency(debit) : '-'}</td>`;
                        html += `<td class="report-col-credit text-right credit-cell">${credit > 0 ? this.formatCurrency(credit) : '-'}</td>`;
                        html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(runningBalance)}</td>`;
                        html += '</tr>';
                    });
                    
                    html += '<tr class="report-totals-row">';
                    html += '<td colspan="4" class="report-totals-label"><strong>Account Totals:</strong></td>';
                    html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(account.total_debit || 0))}</strong></td>`;
                    html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(account.total_credit || 0))}</strong></td>`;
                    html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(account.balance || 0))}</strong></td>`;
                    html += '</tr>';
                } else {
                    html += '<tr><td colspan="7" class="text-center report-empty-state">No transactions</td></tr>';
                }
                
                html += '</tbody></table></div></div>';
            });
            
            // Add overall totals footer if available
            if (reportData?.totals) {
                html += '<div class="report-totals-summary">';
                html += '<table class="professional-report-table">';
                html += '<tfoot class="report-totals-footer">';
                html += '<tr class="report-totals-row">';
                html += '<td colspan="4" class="report-totals-label"><strong>GRAND TOTALS:</strong></td>';
                html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
                html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
                html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.difference || 0))}</strong></td>`;
                html += '</tr>';
                html += '</tfoot>';
                html += '</table>';
                html += '</div>';
            }
        } else {
            html += '<div class="report-empty-state">';
            html += '<i class="fas fa-info-circle report-empty-icon"></i>';
            html += '<h3>No Accounts Found</h3>';
            
            // Check if there's debug info
            if (reportData?.debug) {
                if (reportData.debug.message === 'financial_accounts table does not exist') {
                    html += '<p class="report-empty-text">The financial accounts table does not exist in the database. Please ensure the accounting system is properly set up.</p>';
                } else if (reportData.debug.message === 'Failed to query financial_accounts table') {
                    html += '<p class="report-empty-text">Unable to query the accounts table. Database error: ' + this.escapeHtml(reportData.debug.error || 'Unknown error') + '</p>';
                } else if (reportData.debug.message === 'Query executed successfully but returned 0 accounts') {
                    html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
                } else {
                    html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
                }
            } else {
                html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
            }
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }


ProfessionalAccounting.prototype.formatExpenseStatement = function(reportData) {
        // Get all expenses and apply search filter
        let allExpenses = reportData?.expenses || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allExpenses = allExpenses.filter(expense => {
                const category = (expense.category || '').toLowerCase();
                const description = (expense.description || '').toLowerCase();
                const vendor = (expense.vendor_name || '').toLowerCase();
                return category.includes(searchTerm) || description.includes(searchTerm) || vendor.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allExpenses.length;
        
        // Get paginated expenses (if perPage is 999999, show all)
        let paginatedExpenses = allExpenses;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedExpenses = allExpenses.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Category</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-amount text-right">Amount</th>';
        html += '<th class="report-col-days text-right">Count</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedExpenses) && paginatedExpenses.length > 0) {
            paginatedExpenses.forEach((expense, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><span class="badge badge-info">${this.escapeHtml(expense.category || 'Uncategorized')}</span></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(expense.description || '')}</td>`;
                html += `<td class="report-col-date">${this.formatDate(expense.transaction_date || '')}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(expense.total_amount || 0))}</td>`;
                html += `<td class="report-col-days text-right">${expense.transaction_count || 1}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No expenses found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_expenses || 0))}</strong></td>`;
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.transaction_count || 0}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatAccountStatement = function(reportData) {
        // Check if there's a message (e.g., account not selected)
        if (reportData?.message) {
            return `
                <div class="report-empty-state">
                    <i class="fas fa-info-circle report-empty-icon"></i>
                    <h3>${this.escapeHtml(reportData.message)}</h3>
                    <p class="report-empty-text">Please select an account from the filter above to generate the statement.</p>
                </div>
            `;
        }
        // Similar to General Ledger but for a specific account
        return this.formatGeneralLedgerReport(reportData);
    }


ProfessionalAccounting.prototype.formatValueAdded = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-name">Type</th>';
        html += '<th class="report-col-amount text-right">Amount</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(item.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.account_name || '')}</td>`;
                html += `<td class="report-col-name"><span class="badge badge-info">${this.escapeHtml(item.type || '')}</span></td>`;
                html += `<td class="report-col-amount text-right ${item.type === 'Revenue' ? 'credit-cell' : 'debit-cell'}">${this.formatCurrency(parseFloat(item.amount || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" class="text-center report-empty-state">No value added data available</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.value_added || 0))}</strong></td>`;
            html += '</tr>';
            if (reportData.totals.value_added_percentage !== undefined) {
                html += '<tr class="report-totals-row">';
                html += '<td colspan="3" class="report-totals-label"><strong>Value Added Percentage:</strong></td>';
                html += `<td class="report-col-amount text-right"><strong>${parseFloat(reportData.totals.value_added_percentage || 0).toFixed(2)}%</strong></td>`;
                html += '</tr>';
            }
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatFixedAssets = function(reportData) {
        // Get all assets and apply search filter
        let allAssets = reportData?.assets || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allAssets = allAssets.filter(asset => {
                const accountCode = (asset.account_code || '').toLowerCase();
                const accountName = (asset.account_name || '').toLowerCase();
                return accountCode.includes(searchTerm) || accountName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allAssets.length;
        
        // Get paginated assets (if perPage is 999999, show all)
        let paginatedAssets = allAssets;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedAssets = allAssets.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedAssets.length > 0) {
            paginatedAssets.forEach((asset, index) => {
                const balance = parseFloat(asset.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(asset.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(asset.account_name || '')}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(asset.description || '')}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" class="text-center report-empty-state">No fixed assets found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="2" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_assets || 0))}</strong></td>`;
            html += `<td class="report-col-name"><strong>${reportData.totals.asset_count || 0} assets</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatEntriesByYear = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const year = String(item.year || '').toLowerCase();
                return year.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-period">Year</th>';
        html += '<th class="report-col-days text-right">Entry Count</th>';
        html += '<th class="report-col-amount text-right">Total Amount</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-period"><strong>${this.escapeHtml(String(item.year || ''))}</strong></td>`;
                html += `<td class="report-col-days text-right">${item.entry_count || 0}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No entries found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.total_entries || 0}</strong></td>`;
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_amount || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatCustomerDebits = function(reportData) {
        // Get all customers and apply search filter
        let allCustomers = reportData?.customers || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allCustomers = allCustomers.filter(customer => {
                const customerName = (customer.customer_name || '').toLowerCase();
                const customerId = String(customer.customer_id || '').toLowerCase();
                return customerName.includes(searchTerm) || customerId.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allCustomers.length;
        
        // Get paginated customers (if perPage is 999999, show all)
        let paginatedCustomers = allCustomers;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedCustomers = allCustomers.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-customer">Customer</th>';
        html += '<th class="report-col-days text-right">Invoice Count</th>';
        html += '<th class="report-col-amount text-right">Total Invoiced</th>';
        html += '<th class="report-col-amount text-right">Total Paid</th>';
        html += '<th class="report-col-amount text-right">Total Debit</th>';
        html += '<th class="report-col-days text-right">Overdue Count</th>';
        html += '<th class="report-col-date">Latest Due Date</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedCustomers.length > 0) {
            paginatedCustomers.forEach((customer, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-customer">${this.escapeHtml(customer.customer_name || 'N/A')}</td>`;
                html += `<td class="report-col-days text-right">${customer.invoice_count || 0}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(customer.total_invoiced || 0))}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(customer.total_paid || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(customer.total_debit || 0))}</td>`;
                html += `<td class="report-col-days text-right ${customer.overdue_count > 0 ? 'overdue-badge' : ''}">${customer.overdue_count || 0}</td>`;
                html += `<td class="report-col-date">${customer.latest_due_date ? this.formatDate(customer.latest_due_date) : '-'}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" class="text-center report-empty-state">No customer debits found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.total_customers || 0}</strong></td>`;
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_invoiced || 0))}</strong></td>`;
            html += `<td class="report-col-amount text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_paid || 0))}</strong></td>`;
            html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += '<td colspan="2"></td>';
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatStatisticalPosition = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || item.category || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Category</th>';
        html += '<th class="report-col-name">Metric</th>';
        html += '<th class="report-col-amount text-right">Value</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                let displayValue = item.value;
                // Format numbers appropriately
                if (typeof item.value === 'number') {
                    if (item.value > 1000 || (item.value.toString().includes('.') && item.value < 1)) {
                        displayValue = this.formatCurrency(item.value);
                    } else {
                        displayValue = item.value.toLocaleString();
                    }
                } else {
                    displayValue = this.escapeHtml(String(item.value || ''));
                }
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.category || '')}</strong></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.metric || '')}</td>`;
                html += `<td class="report-col-amount text-right">${displayValue}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No statistical data available</td></tr>';
        }
        
        html += '</tbody></table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatChangesInEquity = function(reportData) {
        // Get paginated data - prefer data array, fallback to equity_changes
        let allData = reportData?.data || [];
        if (allData.length === 0 && reportData?.equity_changes) {
            // Flatten equity_changes for display
            reportData.equity_changes.forEach(equity => {
                if (equity.monthly_changes && equity.monthly_changes.length > 0) {
                    equity.monthly_changes.forEach(change => {
                        allData.push({
                            account_code: equity.account_code,
                            account_name: equity.account_name,
                            period: change.period,
                            change_amount: change.change_amount,
                            current_balance: equity.current_balance
                        });
                    });
                } else {
                    // If no monthly changes, show current balance
                    allData.push({
                        account_code: equity.account_code,
                        account_name: equity.account_name,
                        period: 'Current',
                        change_amount: 0,
                        current_balance: equity.current_balance
                    });
                }
            });
        }
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const accountCode = (item.account_code || '').toLowerCase();
                const accountName = (item.account_name || '').toLowerCase();
                const period = (item.period || '').toLowerCase();
                return accountCode.includes(searchTerm) || accountName.includes(searchTerm) || period.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-period">Period</th>';
        html += '<th class="report-col-amount text-right">Change Amount</th>';
        html += '<th class="report-col-amount text-right">Current Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                const changeAmount = parseFloat(item.change_amount || 0);
                const balance = parseFloat(item.current_balance || 0);
                const changeClass = changeAmount >= 0 ? 'credit-cell' : 'debit-cell';
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(item.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.account_name || '')}</td>`;
                html += `<td class="report-col-period">${this.escapeHtml(item.period || '')}</td>`;
                html += `<td class="report-col-amount text-right ${changeClass}">${this.formatCurrency(changeAmount)}</td>`;
                html += `<td class="report-col-amount text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No equity changes found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += '<td></td>';
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_equity || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatFinancialPerformance = function(reportData) {
        // Get all performance data and apply search filter
        let performanceData = reportData?.performance_data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            performanceData = performanceData.filter(item => {
                const metric = (item.metric || item.name || '').toLowerCase();
                return metric.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = performanceData.length;
        
        const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
        const endIndex = startIndex + this.reportPerPage;
        const paginatedData = performanceData.slice(startIndex, endIndex);
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Metric</th>';
        html += '<th class="report-col-amount text-right">Value</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                let displayValue = '';
                if (item.type === 'currency') {
                    displayValue = this.formatCurrency(parseFloat(item.value || 0));
                } else if (item.type === 'percentage') {
                    displayValue = parseFloat(item.value || 0).toFixed(2) + '%';
                } else if (item.type === 'ratio') {
                    displayValue = parseFloat(item.value || 0).toFixed(2);
                } else {
                    displayValue = this.escapeHtml(String(item.value || ''));
                }
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.metric || '')}</strong></td>`;
                html += `<td class="report-col-amount text-right">${displayValue}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="2" class="text-center report-empty-state">No performance data available</td></tr>';
        }
        
        html += '</tbody></table></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatComparativeReport = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-sections">';
        
        // Period labels
        if (reportData?.periods && reportData.periods.length >= 2) {
            html += '<div class="report-section-header">';
            html += `<h4>Comparing: ${reportData.periods[0].label} vs ${reportData.periods[1].label}</h4>`;
            html += '</div>';
        }
        
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Item</th>';
        html += '<th class="report-col-amount text-right">Previous Period</th>';
        html += '<th class="report-col-amount text-right">Current Period</th>';
        html += '<th class="report-col-amount text-right">Change</th>';
        html += '<th class="report-col-amount text-right">Change %</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                const change = parseFloat(item.change || 0);
                const changePercent = parseFloat(item.change_percentage || 0);
                const changeClass = change >= 0 ? 'credit-cell' : 'debit-cell';
                const percentClass = changePercent >= 0 ? 'credit-cell' : 'debit-cell';
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.item || '')}</strong></td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.previous_period || 0))}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.current_period || 0))}</td>`;
                html += `<td class="report-col-amount text-right ${changeClass}">${this.formatCurrency(change)}</td>`;
                html += `<td class="report-col-amount text-right ${percentClass}">${changePercent.toFixed(2)}%</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No comparison data available</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatGenericReport = function(reportData, reportName = 'Report') {
        // Try multiple data sources
        let allData = reportData.data || reportData.assets || reportData.customers || 
                     reportData.receivables || reportData.payables || 
                     reportData.transactions || reportData.expenses ||
                     reportData.performance_data || reportData.comparisons || 
                     reportData.equity_changes || [];
        
        // If still empty, check if it's an array directly
        if (!Array.isArray(allData) && typeof reportData === 'object') {
            // Try to find any array property
            for (const key in reportData) {
                if (Array.isArray(reportData[key]) && reportData[key].length > 0) {
                    allData = reportData[key];
                    break;
                }
            }
        }
        
        // Apply search filter if search term exists
        if (Array.isArray(allData) && this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                if (typeof item === 'object' && item !== null) {
                    return Object.values(item).some(val => 
                        String(val || '').toLowerCase().includes(searchTerm)
                    );
                }
                return String(item || '').toLowerCase().includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = Array.isArray(allData) ? allData.length : 0;
        
        const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
        const endIndex = startIndex + this.reportPerPage;
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = Array.isArray(allData) ? allData : [];
        if (this.reportPerPage < 999999 && Array.isArray(allData)) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-sections">';
        html += '<div class="report-section">';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr>';
        
        // Try to detect columns from data
        if (reportData.columns && Array.isArray(reportData.columns)) {
            reportData.columns.forEach(col => {
                html += `<th>${this.escapeHtml(String(col))}</th>`;
            });
        } else if (paginatedData.length > 0 && typeof paginatedData[0] === 'object') {
            // Use first row keys as columns
            Object.keys(paginatedData[0]).forEach(key => {
                const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += `<th>${this.escapeHtml(formattedKey)}</th>`;
            });
        } else if (Array.isArray(allData) && allData.length > 0 && typeof allData[0] === 'object') {
            // Fallback to allData if paginatedData is empty
            Object.keys(allData[0]).forEach(key => {
                const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += `<th>${this.escapeHtml(formattedKey)}</th>`;
            });
        } else {
            html += '<th>Data</th>';
        }
        
        html += '</tr></thead><tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((row, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                if (typeof row === 'object') {
                    Object.values(row).forEach(val => {
                        // Format currency if it looks like a number
                        let displayVal = val !== null && val !== undefined ? String(val) : '';
                        if (typeof val === 'number' && (val.toString().includes('.') || Math.abs(val) > 100)) {
                            displayVal = this.formatCurrency(val);
                        } else if (typeof val === 'string' && /^\d+\.?\d*$/.test(val.trim()) && parseFloat(val) > 100) {
                            displayVal = this.formatCurrency(parseFloat(val));
                        } else {
                            displayVal = this.escapeHtml(displayVal);
                        }
                        html += `<td>${displayVal}</td>`;
                    });
                } else {
                    html += `<td>${this.escapeHtml(String(row))}</td>`;
                }
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="100%" class="text-center report-empty-state">No data available</td></tr>';
        }
        
        html += '</tbody>';
        
        // Add totals footer if available
        if (reportData.totals && typeof reportData.totals === 'object') {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            const colCount = reportData.columns?.length || (paginatedData.length > 0 && typeof paginatedData[0] === 'object' ? Object.keys(paginatedData[0]).length : (Array.isArray(allData) && allData.length > 0 && typeof allData[0] === 'object' ? Object.keys(allData[0]).length : 1));
            html += `<td colspan="${Math.max(1, colCount - 1)}" class="report-totals-label"><strong>TOTALS</strong></td>`;
            // Try to find a total amount field
            const totalField = Object.keys(reportData.totals).find(k => k.toLowerCase().includes('total'));
            if (totalField) {
                html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals[totalField] || 0))}</strong></td>`;
            } else {
                html += '<td></td>';
            }
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div></div></div>';
        return html;
    }


ProfessionalAccounting.prototype.formatChartOfAccounts = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        const grouped = reportData?.grouped || {};
        let categories = Object.keys(grouped).sort();
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            categories = categories.filter(category => {
                const accounts = grouped[category] || [];
                return category.toLowerCase().includes(searchTerm) || 
                       accounts.some(acc => {
                           const code = (acc.account_code || '').toLowerCase();
                           const name = (acc.account_name || '').toLowerCase();
                           return code.includes(searchTerm) || name.includes(searchTerm);
                       });
            });
        }
        
        // Calculate total accounts for pagination
        let totalAccounts = 0;
        categories.forEach(category => {
            totalAccounts += (grouped[category] || []).length;
        });
        this.reportTotalCount = totalAccounts;
        
        // Apply pagination to categories (if perPage is 999999, show all)
        let paginatedCategories = categories;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedCategories = categories.slice(startIndex, endIndex);
        }
        
        if (paginatedCategories.length > 0) {
            paginatedCategories.forEach((category, catIndex) => {
                html += `<div class="report-section">`;
                html += `<div class="report-section-header">`;
                html += `<h4><i class="fas fa-folder"></i> ${this.escapeHtml(category)}</h4>`;
                html += `</div>`;
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table">';
                html += '<thead>';
                html += '<tr>';
                html += '<th class="report-col-code">Account Code</th>';
                html += '<th class="report-col-name">Account Name</th>';
                html += '<th class="report-col-balance text-right">Balance</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                let accounts = grouped[category] || [];
                
                // Apply search filter to accounts within category
                if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                    const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                    accounts = accounts.filter(acc => {
                        const code = (acc.account_code || '').toLowerCase();
                        const name = (acc.account_name || '').toLowerCase();
                        return code.includes(searchTerm) || name.includes(searchTerm);
                    });
                }
                accounts.forEach((account, accIndex) => {
                    const balance = parseFloat(account.balance || 0);
                    const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                    html += `<tr class="report-data-row ${accIndex % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(account.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(account.account_name || '')}</td>`;
                    html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table></div></div>';
            });
        } else {
            html += '<div class="report-empty-state">';
            html += '<i class="fas fa-info-circle report-empty-icon"></i>';
            html += '<h3>No Accounts Found</h3>';
            html += '<p class="report-empty-text">No accounts found in the system.</p>';
            html += '</div>';
        }
        
        if (reportData?.total_accounts !== undefined) {
            html += '<div class="report-summary-section">';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table report-summary-table">';
            html += '<tbody>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Accounts:</strong></td>';
            html += `<td class="report-summary-value text-right"><strong>${reportData.total_accounts}</strong></td>`;
            html += '</tr>';
            html += '</tbody></table></div></div>';
        }
        
        html += '</div>';
        return html;
    }