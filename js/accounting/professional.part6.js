/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part6.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part6.js`.
 */
/** Professional Accounting - Part 6 (lines 25199-30198) */
        const content = `
            <div class="accounting-modal-content">
                <div class="modal-header-actions">
                    <button class="btn btn-primary" id="addReceiptVoucherBtn" data-action="add-receipt-voucher">
                        <i class="fas fa-plus"></i> Add Receipt Voucher
                    </button>
                </div>

                <div class="accounting-filters-row">
                    <div class="accounting-filter-group">
                        <label>Filter by Year:</label>
                        <select id="receiptVouchersYearFilter" class="form-control">
                            <option value="" ${this.receiptVouchersYearFilter === '' || this.receiptVouchersYearFilter === null ? 'selected' : ''}>All Years</option>
                            <option value="previous">Previous Years</option>
                            ${years.map(y => `<option value="${y}" ${y === this.receiptVouchersYearFilter ? 'selected' : ''}>${y}</option>`).join('')}
                        </select>
                    </div>
                    <div class="accounting-filter-group">
                        <label>Display:</label>
                        <select id="receiptVouchersPerPageSelect" class="form-control">
                            <option value="10" ${this.receiptVouchersPerPage === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${this.receiptVouchersPerPage === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${this.receiptVouchersPerPage === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${this.receiptVouchersPerPage === 100 ? 'selected' : ''}>100</option>
                        </select>
                        <span>Records</span>
                    </div>
                    <div class="accounting-filter-group">
                        <label>Search:</label>
                        <input type="text" id="receiptVouchersSearch" class="form-control" placeholder="Search..." value="${this.receiptVouchersSearch || ''}">
                    </div>
                    <div class="accounting-filter-group export-buttons-group">
                        <button class="btn btn-sm btn-export" data-action="print-receipt-vouchers" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="btn btn-sm btn-export" data-action="pdf-receipt-vouchers" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-export" data-action="csv-receipt-vouchers" title="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button class="btn btn-sm btn-export" data-action="copy-receipt-vouchers" title="Copy">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-export active" data-action="excel-receipt-vouchers" title="Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                    </div>
                </div>

                <div class="data-table-container modal-table-wrapper" id="receiptVouchersTableWrapper">
                    <table class="data-table modal-table-fixed" id="receiptVouchersTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="receipt_number">
                                    Voucher Number <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="payment_date">
                                    Date <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="description">
                                    Description <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="customer_name">
                                    Payee / Customer Name <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="bank_account_name">
                                    Bank / Cash Account Name <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable text-right" data-sort="amount">
                                    Amount <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="cost_center_name">
                                    Cost Center <i class="fas fa-sort"></i>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receiptVouchersTableBody">
                            <tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="modal-pagination" id="receiptVouchersPagination">
                    <!-- Pagination will be inserted here -->
                </div>
            </div>
        `;

        this.showModal('Receipt Vouchers', content, 'large', 'receiptVouchersListModal');

        // Setup event listeners
        setTimeout(() => {
            const yearFilter = document.getElementById('receiptVouchersYearFilter');
            const perPageSelect = document.getElementById('receiptVouchersPerPageSelect');
            const searchInput = document.getElementById('receiptVouchersSearch');
            const addBtn = document.getElementById('addReceiptVoucherBtn');

            if (yearFilter) {
                yearFilter.addEventListener('change', (e) => {
                    this.receiptVouchersYearFilter = e.target.value === 'previous' ? 'previous' : (e.target.value ? parseInt(e.target.value) : null);
                    this.receiptVouchersCurrentPage = 1;
                    this.loadReceiptVouchers();
                });
            }

            if (perPageSelect) {
                perPageSelect.addEventListener('change', (e) => {
                    this.receiptVouchersPerPage = parseInt(e.target.value);
                    this.receiptVouchersCurrentPage = 1;
                    this.loadReceiptVouchers();
                });
            }

            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.receiptVouchersSearch = e.target.value;
                        this.receiptVouchersCurrentPage = 1;
                        this.loadReceiptVouchers();
                    }, 300);
                });
            }

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    this.openReceiptVoucherModal();
                });
            }

            // View/Edit action buttons: delegate on table wrapper so it works after tbody re-renders
            const tableWrapper = document.getElementById('receiptVouchersTableWrapper');
            if (tableWrapper) {
                tableWrapper.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-action="view-receipt-voucher"], [data-action="edit-receipt-voucher"]');
                    if (btn) {
                        e.preventDefault();
                        e.stopPropagation();
                        const id = btn.getAttribute('data-id');
                        if (id) this.openReceiptVoucherModal(parseInt(id, 10));
                    }
                });
            }

            // Export buttons
            document.querySelectorAll('[data-action^="print-"], [data-action^="pdf-"], [data-action^="csv-"], [data-action^="copy-"], [data-action^="excel-"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = e.currentTarget.getAttribute('data-action');
                    this.exportReceiptVouchers(action.replace('receipt-vouchers', '').replace('-', ''));
                });
            });

            // Sortable headers
            document.querySelectorAll('#receiptVouchersTable th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    const sortField = th.getAttribute('data-sort');
                    // Toggle sort direction
                    this.receiptVouchersSortField = sortField;
                    this.receiptVouchersSortDir = (this.receiptVouchersSortField === sortField && this.receiptVouchersSortDir === 'asc') ? 'desc' : 'asc';
                    this.loadReceiptVouchers();
                });
            });

            // Load data
            this.loadReceiptVouchers();
        }, 100);
    }

ProfessionalAccounting.prototype.loadReceiptVouchers = async function() {
        const tbody = document.getElementById('receiptVouchersTableBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        try {
            let url = `${this.apiBase}/payment-receipts.php`;
            const params = [];

            if (this.receiptVouchersYearFilter) {
                if (this.receiptVouchersYearFilter === 'previous') {
                    const currentYear = new Date().getFullYear();
                    params.push(`date_from=1900-01-01&date_to=${currentYear - 1}-12-31`);
                } else {
                    params.push(`year=${this.receiptVouchersYearFilter}`);
                }
            }

            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            url += (url.includes('?') ? '&' : '?') + '_t=' + Date.now();

            const response = await fetch(url, { credentials: 'include' });
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.text();
                    if (errorData) {
                        try {
                            const errorJson = JSON.parse(errorData);
                            if (errorJson.message) {
                                errorMessage = errorJson.message;
                            }
                        } catch (e) {
                            // If not JSON, use the raw text (might be PHP error)
                            if (errorData.length < 500) {
                                errorMessage = errorData;
                            }
                        }
                    }
                } catch (e) {
                    // Keep default error message
                }
                throw new Error(errorMessage);
            }

            const data = await response.json();
            if (data.success && data.receipts && Array.isArray(data.receipts)) {
                let receipts = data.receipts;

                // Apply search filter
                if (this.receiptVouchersSearch) {
                    const searchLower = this.receiptVouchersSearch.toLowerCase();
                    receipts = receipts.filter(r =>
                        ((r.receipt_number || r.voucher_number) && String(r.receipt_number || r.voucher_number).toLowerCase().includes(searchLower)) ||
                        (r.customer_name && r.customer_name.toLowerCase().includes(searchLower)) ||
                        (r.bank_account_name && r.bank_account_name.toLowerCase().includes(searchLower)) ||
                        (r.description && r.description.toLowerCase().includes(searchLower))
                    );
                }

                // Apply sorting
                if (this.receiptVouchersSortField) {
                    receipts.sort((a, b) => {
                        let aVal = a[this.receiptVouchersSortField] || '';
                        let bVal = b[this.receiptVouchersSortField] || '';
                        
                        if (this.receiptVouchersSortField === 'amount') {
                            aVal = parseFloat(aVal) || 0;
                            bVal = parseFloat(bVal) || 0;
                        } else if (this.receiptVouchersSortField === 'payment_date') {
                            aVal = new Date(aVal);
                            bVal = new Date(bVal);
                        } else {
                            aVal = String(aVal).toLowerCase();
                            bVal = String(bVal).toLowerCase();
                        }

                        if (this.receiptVouchersSortDir === 'asc') {
                            return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                        } else {
                            return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                        }
                    });
                }

                // Pagination
                const totalRecords = receipts.length;
                const totalPages = Math.ceil(totalRecords / this.receiptVouchersPerPage);
                const startIndex = (this.receiptVouchersCurrentPage - 1) * this.receiptVouchersPerPage;
                const endIndex = startIndex + this.receiptVouchersPerPage;
                const paginatedReceipts = receipts.slice(startIndex, endIndex);

                // Render table
                if (paginatedReceipts.length > 0) {
                    tbody.innerHTML = paginatedReceipts.map(receipt => {
                        const voucherNumber = this.escapeHtml(receipt.receipt_number || receipt.voucher_number || '');
                        const date = receipt.payment_date ? this.formatDate(receipt.payment_date) : '-';
                        const description = this.escapeHtml(receipt.description || receipt.notes || '');
                        const customerName = this.escapeHtml(receipt.customer_name || 'N/A');
                        const bankAccountName = this.escapeHtml(receipt.bank_account_name || 'Cash');
                        const amount = this.formatCurrency(parseFloat(receipt.amount || 0), receipt.currency || 'SAR');
                        const costCenter = this.escapeHtml(receipt.cost_center_name || '');

                        return `
                            <tr>
                                <td>${voucherNumber}</td>
                                <td>${date}</td>
                                <td>${description}</td>
                                <td>${customerName}</td>
                                <td>${bankAccountName}</td>
                                <td class="text-right">${amount}</td>
                                <td>${costCenter}</td>
                                <td>
                                    <div class="action-buttons">
                                        <input type="checkbox" class="row-checkbox" data-id="${receipt.id}">
                                        <button class="action-btn view" data-action="view-receipt-voucher" data-id="${receipt.id}" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" data-action="edit-receipt-voucher" data-id="${receipt.id}" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    // Update table wrapper scrolling
                    const tableWrapper = document.getElementById('receiptVouchersTableWrapper');
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', this.receiptVouchersPerPage.toString());
                        if (this.receiptVouchersPerPage > 5) {
                            tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-scroll');
                        } else {
                            tableWrapper.classList.remove('modal-table-wrapper-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                        }
                    }

                    // Update pagination
                    this.updateReceiptVouchersPagination(totalRecords, this.receiptVouchersCurrentPage, totalPages);
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center ledger-empty-state"><i class="fas fa-receipt ledger-empty-icon"></i><p class="ledger-empty-message">No receipt vouchers found</p><p class="text-muted small">Filter: Year ' + (this.receiptVouchersYearFilter || 'All') + '. Try &quot;All Years&quot; or click Refresh.</p></td></tr>';
                    this.updateReceiptVouchersPagination(0, 1, 1);
                }
            } else {
                const errMsg = (data && data.message) ? data.message : 'API did not return receipts list';
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">' + this.escapeHtml(errMsg) + '</td></tr>';
                this.updateReceiptVouchersPagination(0, 1, 1);
            }
        } catch (error) {
            console.error('Error loading receipt vouchers:', error);
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error loading receipt vouchers: ${error.message}</td></tr>`;
            this.showToast('Failed to load receipt vouchers', 'error');
        }
    }

ProfessionalAccounting.prototype.updateReceiptVouchersPagination = function(totalRecords, currentPage, totalPages) {
        const paginationEl = document.getElementById('receiptVouchersPagination');
        if (!paginationEl) return;

        const startRecord = totalRecords > 0 ? (currentPage - 1) * this.receiptVouchersPerPage + 1 : 0;
        const endRecord = Math.min(currentPage * this.receiptVouchersPerPage, totalRecords);

        paginationEl.innerHTML = `
            <div class="pagination-info">
                ${totalRecords > 0 ? `Showing ${startRecord} to ${endRecord} of ${totalRecords} records` : 'No records found'}
            </div>
            <div class="pagination-controls">
                <button class="btn btn-sm" ${currentPage === 1 ? 'disabled' : ''} data-action="prev-receipt-vouchers-page">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                ${Array.from({ length: Math.min(totalPages, 10) }, (_, i) => {
                    const page = i + 1;
                    return `<button class="btn btn-sm ${page === currentPage ? 'active' : ''}" data-action="goto-receipt-vouchers-page" data-page="${page}">${page}</button>`;
                }).join('')}
                <button class="btn btn-sm" ${currentPage === totalPages ? 'disabled' : ''} data-action="next-receipt-vouchers-page">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;

        // Add event listeners
        paginationEl.querySelectorAll('[data-action="prev-receipt-vouchers-page"]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (this.receiptVouchersCurrentPage > 1) {
                    this.receiptVouchersCurrentPage--;
                    this.loadReceiptVouchers();
                }
            });
        });

        paginationEl.querySelectorAll('[data-action="next-receipt-vouchers-page"]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (this.receiptVouchersCurrentPage < totalPages) {
                    this.receiptVouchersCurrentPage++;
                    this.loadReceiptVouchers();
                }
            });
        });

        paginationEl.querySelectorAll('[data-action="goto-receipt-vouchers-page"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const page = parseInt(e.currentTarget.getAttribute('data-page'));
                this.receiptVouchersCurrentPage = page;
                this.loadReceiptVouchers();
            });
        });
    }

ProfessionalAccounting.prototype.exportReceiptVouchers = async function(format) {
        var f = (format || '').toLowerCase();
        if (f !== 'csv' && f !== 'excel') {
            this.showToast('Export to ' + (format || 'this format') + ' – coming soon', 'info');
            return;
        }
        try {
            this.showToast('Exporting receipt vouchers...', 'info');
            var url = this.apiBase + '/payment-receipts.php';
            var response = await fetch(url, { credentials: 'include' });
            var data = await response.json().catch(function() { return { success: false }; });
            if (!data.success || !Array.isArray(data.receipts)) {
                this.showToast((data && data.message) ? data.message : 'Failed to load receipts', 'error');
                return;
            }
            var headers = ['Voucher #', 'Date', 'Description', 'Payee / Customer', 'Bank / Cash', 'Amount', 'Currency', 'Cost Center', 'Status'];
            var escapeCsv = function(v) {
                if (v == null || v === undefined) return '';
                var s = String(v);
                if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            };
            var rows = data.receipts.map(function(r) {
                if (!r || typeof r !== 'object') return ['', '', '', '', '', '', '', '', ''];
                var desc = (r.description || r.notes) ? String(r.description || r.notes).replace(/\r/g, ' ').replace(/\n/g, ' ') : '';
                return [
                    (r.receipt_number || r.voucher_number || r.id) != null ? String(r.receipt_number || r.voucher_number || r.id) : '',
                    (r.payment_date || r.voucher_date) != null ? String(r.payment_date || r.voucher_date) : '',
                    desc,
                    r.customer_name != null ? String(r.customer_name) : '',
                    r.bank_account_id === 0 ? 'Cash' : (r.bank_account_name != null ? String(r.bank_account_name) : ''),
                    r.amount != null && r.amount !== '' ? r.amount : '',
                    r.currency != null ? String(r.currency) : 'SAR',
                    r.cost_center_name != null ? String(r.cost_center_name) : '',
                    r.status != null ? String(r.status) : ''
                ];
            });
            var csv = headers.map(escapeCsv).join(',') + '\r\n' + rows.map(function(r) { return r.map(escapeCsv).join(','); }).join('\r\n');
            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
            var link = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = link;
            a.download = 'receipt_vouchers_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(link);
            this.showToast('Receipt vouchers exported', 'success');
        } catch (err) {
            var msg = (err && typeof err.message === 'string') ? err.message : 'Unknown error';
            this.showToast('Export failed: ' + msg, 'error');
        }
    }

ProfessionalAccounting.prototype.checkTablesExist = async function() {
        try {
            const response = await fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`, { credentials: 'include' });
            const data = await response.json();
            const setupButton = document.querySelector('[data-action="setup-tables"]');
            if (setupButton) {
                setupButton.style.display = (data.success && data.accounts && data.accounts.length > 0) ? 'none' : '';
            }
        } catch (error) {
            // Silently fail - tables check is not critical
        }
    }

ProfessionalAccounting.prototype.loadInvoicesForSelect = async function(selector, customerId = null) {
        const invoiceSelect = document.getElementById(selector);
        if (!invoiceSelect) {
            return;
        }
        
        invoiceSelect.innerHTML = '<option value="">Loading invoices...</option>';
        
        try {
            let url = `${this.apiBase}/invoices.php?status=unpaid`;
            if (customerId) {
                url += `&customer_id=${customerId}`;
            }
            
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in loadInvoicesForSelect:', jsonError);
                data = { success: false, invoices: [] };
            }
            
            if (data.success && data.invoices) {
                invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
                data.invoices.forEach(invoice => {
                    const option = document.createElement('option');
                    option.value = invoice.id;
                    const balance = parseFloat(invoice.balance_amount || invoice.total_amount || 0);
                    option.textContent = `${invoice.invoice_number || 'N/A'} - ${this.formatCurrency(balance, invoice.currency || this.getDefaultCurrencySync())}`;
                    option.dataset.balance = balance;
                    invoiceSelect.appendChild(option);
                });
            } else {
                invoiceSelect.innerHTML = '<option value="">No unpaid invoices found</option>';
            }
        } catch (error) {
            invoiceSelect.innerHTML = '<option value="">Error loading invoices</option>';
            console.error('Error loading invoices for select:', error);
        }
    }

ProfessionalAccounting.prototype.loadBillsForSelect = async function(selector, vendorId = null) {
        const billSelect = document.getElementById(selector);
        if (!billSelect) {
            return;
        }
        
        billSelect.innerHTML = '<option value="">Loading bills...</option>';
        
        try {
            let url = `${this.apiBase}/bills.php?status=unpaid`;
            if (vendorId) {
                url += `&vendor_id=${vendorId}`;
            }
            
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error('Error parsing response in loadBillsForSelect:', jsonError);
                data = { success: false, bills: [] };
            }
            
            if (data.success && data.bills) {
                billSelect.innerHTML = '<option value="">Select Bill</option>';
                data.bills.forEach(bill => {
                    const option = document.createElement('option');
                    option.value = bill.id;
                    const balance = parseFloat(bill.balance_amount || bill.total_amount || 0);
                    option.textContent = `${bill.bill_number || 'N/A'} - ${this.formatCurrency(balance, bill.currency || this.getDefaultCurrencySync())}`;
                    option.dataset.balance = balance;
                    billSelect.appendChild(option);
                });
            } else {
                billSelect.innerHTML = '<option value="">No unpaid bills found</option>';
            }
        } catch (error) {
            billSelect.innerHTML = '<option value="">Error loading bills</option>';
            console.error('Error loading bills for select:', error);
        }
    }

ProfessionalAccounting.prototype.loadBankingCashModal = async function() {
        // Reset selection when opening modal
        this.bankingSelectedAccounts.clear();
        
        // Open banking & cash modal - similar to other modals
        const modal = document.getElementById('bankingCashModal');
        if (modal) {
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
            this.activeModal = modal;
            
            // Ensure status cards exist - add them if missing
            const moduleContent = modal.querySelector('.accounting-module-modal-content');
            if (moduleContent) {
                const statusCards = moduleContent.querySelector('#bankingStatusCards');
                if (!statusCards) {
                    // Status cards don't exist, add them before filters
                    const filtersContainer = moduleContent.querySelector('.filters-and-pagination-container');
                    if (filtersContainer) {
                        const statusCardsHTML = `
                            <div class="report-status-cards" id="bankingStatusCards">
                                <div class="stat-card stat-card-primary">
                                    <div class="stat-icon stat-icon-primary">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Total Accounts</div>
                                        <div class="stat-value" id="bankingTotalAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-success">
                                    <div class="stat-icon stat-icon-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Active Accounts</div>
                                        <div class="stat-value" id="bankingActiveAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-warning">
                                    <div class="stat-icon stat-icon-warning">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Inactive Accounts</div>
                                        <div class="stat-value" id="bankingInactiveAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-balance">
                                    <div class="stat-icon stat-icon-info">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Total Balance</div>
                                        <div class="stat-value" id="bankingTotalBalance">SAR 0.00</div>
                                    </div>
                                </div>
                            </div>
                        `;
                        filtersContainer.insertAdjacentHTML('beforebegin', statusCardsHTML);
                    }
                }
            }
            
            // Load bank accounts if not already loaded
            const bankAccountsList = document.getElementById('bankAccountsList');
            const bankAccountsTableBody = document.getElementById('bankAccountsTableBody');
            if ((bankAccountsList && (!bankAccountsList.innerHTML || bankAccountsList.innerHTML.includes('Loading'))) ||
                (bankAccountsTableBody && (!bankAccountsTableBody.innerHTML || bankAccountsTableBody.innerHTML.includes('Loading')))) {
                await this.loadBankAccounts();
            } else {
                // Update status cards even if accounts are already loaded
                this.updateBankingStatusCards();
            }
            
            // Setup handlers for existing modal
            this.setupBankingCashHandlers();
            
            // Set initial table container scrolling (no scroll for 5 entries)
            const tableContainer = modal.querySelector('#bankAccountsTableContainer');
            if (tableContainer) {
                if (this.bankingPerPage <= 5) {
                    // No scrolling for 5 or fewer entries
                    tableContainer.classList.remove('modal-table-wrapper-scroll');
                    tableContainer.classList.add('modal-table-wrapper-no-scroll');
                    const table = tableContainer.querySelector('#bankAccountsTable');
                    if (table) {
                        table.classList.add('banking-table-no-scroll');
                    }
                    // Disable modal body scrolling
                    const modalBody = modal.querySelector('.accounting-modal-body');
                    if (modalBody) {
                        modalBody.classList.remove('banking-modal-scroll');
                        modalBody.classList.add('banking-modal-no-scroll');
                    }
                    const moduleContent = modal.querySelector('.accounting-module-modal-content');
                    if (moduleContent) {
                        moduleContent.classList.remove('banking-content-scroll');
                        moduleContent.classList.add('banking-content-no-scroll');
                    }
                } else {
                    // Enable scrolling for more entries
                    tableContainer.classList.remove('modal-table-wrapper-no-scroll');
                    tableContainer.classList.add('modal-table-wrapper-scroll');
                    const table = tableContainer.querySelector('#bankAccountsTable');
                    if (table) {
                        table.classList.remove('banking-table-no-scroll');
                    }
                    // Keep modal body and content from scrolling (scrolling happens inside table only)
                    const modalBody = modal.querySelector('.accounting-modal-body');
                    if (modalBody) {
                        modalBody.classList.add('banking-modal-no-scroll');
                        modalBody.classList.remove('banking-modal-scroll');
                    }
                    const moduleContent = modal.querySelector('.accounting-module-modal-content');
                    if (moduleContent) {
                        moduleContent.classList.add('banking-content-no-scroll');
                        moduleContent.classList.remove('banking-content-scroll');
                    }
                }
            }
        } else {
            // Create banking & cash modal if it doesn't exist
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header">
                    </div>
                    <div class="module-content">
                        <!-- Status Cards -->
                        <div class="report-status-cards" id="bankingStatusCards">
                            <div class="stat-card stat-card-primary">
                                <div class="stat-icon stat-icon-primary">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Total Accounts</div>
                                    <div class="stat-value" id="bankingTotalAccounts">0</div>
                                </div>
                            </div>
                            <div class="stat-card stat-card-success">
                                <div class="stat-icon stat-icon-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Active Accounts</div>
                                    <div class="stat-value" id="bankingActiveAccounts">0</div>
                                </div>
                            </div>
                            <div class="stat-card stat-card-warning">
                                <div class="stat-icon stat-icon-warning">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Inactive Accounts</div>
                                    <div class="stat-value" id="bankingInactiveAccounts">0</div>
                                </div>
                            </div>
                            <div class="stat-card stat-card-balance">
                                <div class="stat-icon stat-icon-info">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Total Balance</div>
                                    <div class="stat-value" id="bankingTotalBalance">SAR 0.00</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters and Search Bar -->
                        <div class="filters-and-pagination-container">
                            <div class="filters-bar">
                                <div class="filter-group">
                                    <label><i class="fas fa-search"></i> Search:</label>
                                    <input type="text" id="bankingSearchInput" class="filter-input" placeholder="Search by bank name, account name, or number..." value="${this.bankingSearch || ''}">
                                </div>
                                <div class="filter-group">
                                    <label>Account Type:</label>
                                    <select id="bankingTypeFilter" class="filter-select">
                                        <option value="">All Types</option>
                                        <option value="Checking" ${this.bankingTypeFilter === 'Checking' ? 'selected' : ''}>Checking</option>
                                        <option value="Savings" ${this.bankingTypeFilter === 'Savings' ? 'selected' : ''}>Savings</option>
                                        <option value="Money Market" ${this.bankingTypeFilter === 'Money Market' ? 'selected' : ''}>Money Market</option>
                                        <option value="Certificate of Deposit" ${this.bankingTypeFilter === 'Certificate of Deposit' ? 'selected' : ''}>Certificate of Deposit</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status:</label>
                                    <select id="bankingStatusFilter" class="filter-select">
                                        <option value="">All Status</option>
                                        <option value="active" ${this.bankingStatusFilter === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${this.bankingStatusFilter === 'inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Per Page:</label>
                                    <select id="bankingPerPageSelect" class="filter-select">
                                        <option value="5" ${this.bankingPerPage === 5 ? 'selected' : ''}>5</option>
                                        <option value="10" ${this.bankingPerPage === 10 ? 'selected' : ''}>10</option>
                                        <option value="25" ${this.bankingPerPage === 25 ? 'selected' : ''}>25</option>
                                        <option value="50" ${this.bankingPerPage === 50 ? 'selected' : ''}>50</option>
                                        <option value="100" ${this.bankingPerPage === 100 ? 'selected' : ''}>100</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary btn-sm" data-action="new-bank-account">
                                    <i class="fas fa-plus"></i> New Bank Account
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="refresh-banking">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar" id="bankingBulkActions" style="display: none;">
                            <!-- Pagination Controls (moved to left of bulk buttons) -->
                            <div class="pagination-container" id="bankingPaginationContainer">
                                <div class="pagination-info">
                                    Showing <span id="bankingPageStart">0</span> to <span id="bankingPageEnd">0</span> of <span id="bankingTotalCount">0</span> accounts
                                </div>
                                <div class="pagination-controls">
                                    <button class="btn btn-sm btn-secondary" id="bankingPrevPage" disabled>
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <div class="page-numbers" id="bankingPageNumbers"></div>
                                    <button class="btn btn-sm btn-secondary" id="bankingNextPage" disabled>
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="bulk-actions-info">
                                <span id="bankingSelectedCount">0</span> account(s) selected
                            </div>
                            <div class="bulk-actions-buttons">
                                <button class="btn btn-sm btn-success" data-action="activate-selected-banks">
                                    <i class="fas fa-check-circle"></i> Activate
                                </button>
                                <button class="btn btn-sm btn-warning" data-action="inactivate-selected-banks">
                                    <i class="fas fa-times-circle"></i> Inactivate
                                </button>
                                <button class="btn btn-sm btn-info" data-action="export-selected-banks">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="print-selected-banks">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="btn btn-sm btn-danger" data-action="delete-selected-banks">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="bankAccountsTableContainer">
                            <table class="data-table modal-table-fixed banking-table-no-scroll" id="bankAccountsTable">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="id">
                                            ID <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="bank_name">
                                            Bank Name <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="account_name">
                                            Account Name <i class="fas fa-sort"></i>
                                        </th>
                                        <th>Account Number</th>
                                        <th class="sortable" data-sort="account_type">
                                            Account Type <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="amount-column sortable" data-sort="opening_balance">
                                            Opening Balance <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="amount-column sortable" data-sort="current_balance">
                                            Current Balance <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="is_active">
                                            Status <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="checkbox-column">
                                            <input type="checkbox" id="bankingSelectAll" title="Select All">
                                        </th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="bankAccountsTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center">
                                            <i class="fas fa-spinner fa-spin"></i> Loading bank accounts...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Banking & Cash', content, 'large', 'bankingCashModal');
            
            // Load bank accounts and setup handlers after modal is shown
            setTimeout(async () => {
                await this.loadBankAccounts();
                this.setupBankingCashHandlers();
                
                // Set initial table container scrolling (no scroll for 5 entries)
                const modal = document.getElementById('bankingCashModal');
                if (modal) {
                    const tableContainer = modal.querySelector('#bankAccountsTableContainer');
                    if (tableContainer) {
                        if (this.bankingPerPage <= 5) {
                            // No scrolling for 5 or fewer entries
                            tableContainer.classList.remove('modal-table-wrapper-scroll');
                            tableContainer.classList.add('modal-table-wrapper-no-scroll');
                            const table = tableContainer.querySelector('#bankAccountsTable');
                            if (table) {
                                table.classList.add('banking-table-no-scroll');
                            }
                            // Disable modal body scrolling
                            const modalBody = modal.querySelector('.accounting-modal-body');
                            if (modalBody) {
                                modalBody.classList.remove('banking-modal-scroll');
                                modalBody.classList.add('banking-modal-no-scroll');
                            }
                            const moduleContent = modal.querySelector('.accounting-module-modal-content');
                            if (moduleContent) {
                                moduleContent.classList.remove('banking-content-scroll');
                                moduleContent.classList.add('banking-content-no-scroll');
                }
            } else {
                            // Enable scrolling for more entries
                            tableContainer.classList.remove('modal-table-wrapper-no-scroll');
                            tableContainer.classList.add('modal-table-wrapper-scroll');
                            const table = tableContainer.querySelector('#bankAccountsTable');
                            if (table) {
                                table.classList.remove('banking-table-no-scroll');
                            }
                            // Keep modal body and content from scrolling (scrolling happens inside table only)
                            const modalBody = modal.querySelector('.accounting-modal-body');
                            if (modalBody) {
                                modalBody.classList.add('banking-modal-no-scroll');
                                modalBody.classList.remove('banking-modal-scroll');
                            }
                            const moduleContent = modal.querySelector('.accounting-module-modal-content');
                            if (moduleContent) {
                                moduleContent.classList.add('banking-content-no-scroll');
                                moduleContent.classList.remove('banking-content-scroll');
                            }
                        }
                    }
                }
            }, 100);
        }
    }

ProfessionalAccounting.prototype.setupBankingCashHandlers = function() {
        const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // New Bank Account button
        const newBankAccountBtn = modal.querySelector('[data-action="new-bank-account"]');
        if (newBankAccountBtn) {
            const newBtn = newBankAccountBtn.cloneNode(true);
            newBankAccountBtn.parentNode.replaceChild(newBtn, newBankAccountBtn);
            newBtn.addEventListener('click', () => {
                // Check if function exists directly
                if (typeof this.openBankAccountForm === 'function') {
                    this.openBankAccountForm();
                } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.openBankAccountForm === 'function') {
                    // Try prototype method
                    ProfessionalAccounting.prototype.openBankAccountForm.call(this);
                } else {
                    console.error('openBankAccountForm function not found');
                    this.showToast('Bank account form function not available. Please refresh the page.', 'error');
                }
            });
        }
        
        // Refresh button
        const refreshBtn = modal.querySelector('[data-action="refresh-banking"]');
        if (refreshBtn) {
            const newRefreshBtn = refreshBtn.cloneNode(true);
            refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
            newRefreshBtn.addEventListener('click', async () => {
                await this.loadBankAccounts();
                this.showToast('Bank accounts refreshed', 'success');
            });
        }
        
        // Apply filters button
        const applyFiltersBtn = modal.querySelector('[data-action="apply-banking-filters"]');
        if (applyFiltersBtn) {
            const newBtn = applyFiltersBtn.cloneNode(true);
            applyFiltersBtn.parentNode.replaceChild(newBtn, applyFiltersBtn);
            newBtn.addEventListener('click', () => {
                this.applyBankingFilters();
            });
        }
        
        // Search input (debounced)
        const searchInput = modal.querySelector('#bankingSearchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.bankingSearch = e.target.value;
                    this.bankingCurrentPage = 1;
                    this.loadBankAccounts();
                }, 500);
            });
        }
        
        // Account Type filter (auto-apply)
        const typeFilter = modal.querySelector('#bankingTypeFilter');
        if (typeFilter) {
            typeFilter.addEventListener('change', (e) => {
                this.bankingTypeFilter = e.target.value;
                this.bankingCurrentPage = 1;
                this.loadBankAccounts();
            });
        }
        
        // Status filter (auto-apply)
        const statusFilter = modal.querySelector('#bankingStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.bankingStatusFilter = e.target.value;
                this.bankingCurrentPage = 1;
                this.loadBankAccounts();
            });
        }
        
        // Per page selector
        const perPageSelect = modal.querySelector('#bankingPerPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', (e) => {
                this.bankingPerPage = parseInt(e.target.value);
                this.bankingCurrentPage = 1;
                this.loadBankAccounts();
            });
        }
        
        // Pagination buttons
        const prevBtn = modal.querySelector('#bankingPrevPage');
        if (prevBtn) {
            const newBtn = prevBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newBtn, prevBtn);
            newBtn.addEventListener('click', () => {
                if (this.bankingCurrentPage > 1) {
                    this.bankingCurrentPage--;
                    this.loadBankAccounts();
                }
            });
        }
        
        const nextBtn = modal.querySelector('#bankingNextPage');
        if (nextBtn) {
            const newBtn = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newBtn, nextBtn);
            newBtn.addEventListener('click', () => {
                if (this.bankingCurrentPage < this.bankingTotalPages) {
                    this.bankingCurrentPage++;
                    this.loadBankAccounts();
                }
            });
        }
        
        // Page number buttons
        const pageNumbersContainer = modal.querySelector('#bankingPageNumbers');
        if (pageNumbersContainer) {
            pageNumbersContainer.addEventListener('click', (e) => {
                const pageBtn = e.target.closest('.page-number');
                if (pageBtn && !pageBtn.classList.contains('active')) { 
                    const page = parseInt(pageBtn.dataset.page);
                    if (page && page >= 1 && page <= this.bankingTotalPages) {
                        this.bankingCurrentPage = page;
                        this.loadBankAccounts();
                    }
                }
            });
        }
        
        // Sortable column headers
        modal.querySelectorAll('.sortable').forEach(header => {
            const newHeader = header.cloneNode(true);
            header.parentNode.replaceChild(newHeader, header);
            newHeader.addEventListener('click', () => {
                const column = newHeader.dataset.sort;
                if (this.bankingSortColumn === column) {
                    this.bankingSortDirection = this.bankingSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.bankingSortColumn = column;
                    this.bankingSortDirection = 'asc';
                }
                this.loadBankAccounts();
            });
        });
        
        // Select All checkbox
        const selectAllCheckbox = modal.querySelector('#bankingSelectAll');
        if (selectAllCheckbox) {
            const newCheckbox = selectAllCheckbox.cloneNode(true);
            selectAllCheckbox.parentNode.replaceChild(newCheckbox, selectAllCheckbox);
            newCheckbox.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                const tbody = modal.querySelector('#bankAccountsTableBody');
                if (tbody) {
                    tbody.querySelectorAll('.bank-account-checkbox').forEach(checkbox => {
                        checkbox.checked = isChecked;
                        const bankId = parseInt(checkbox.dataset.bankId);
                        if (!isNaN(bankId)) {
                            if (isChecked) {
                                this.bankingSelectedAccounts.add(bankId);
                            } else {
                                this.bankingSelectedAccounts.delete(bankId);
                            }
                        }
                    });
                    // Update row selection classes
                    tbody.querySelectorAll('tr[data-bank-id]').forEach(row => {
                        const bankId = parseInt(row.dataset.bankId);
                        if (!isNaN(bankId)) {
                            if (isChecked) {
                                row.classList.add('row-selected');
                            } else {
                                row.classList.remove('row-selected');
                            }
                        }
                    });
                }
                this.updateBankingBulkActionsBar();
            });
        }
        
        // Bulk action buttons
        const exportSelectedBtn = modal.querySelector('[data-action="export-selected-banks"]');
        if (exportSelectedBtn) {
            const newBtn = exportSelectedBtn.cloneNode(true);
            exportSelectedBtn.parentNode.replaceChild(newBtn, exportSelectedBtn);
            newBtn.addEventListener('click', () => {
                this.exportSelectedBankAccounts();
            });
        }
        
        const printSelectedBtn = modal.querySelector('[data-action="print-selected-banks"]');
        if (printSelectedBtn) {
            const newBtn = printSelectedBtn.cloneNode(true);
            printSelectedBtn.parentNode.replaceChild(newBtn, printSelectedBtn);
            newBtn.addEventListener('click', () => {
                this.printSelectedBankAccounts();
            });
        }
        
        const deleteSelectedBtn = modal.querySelector('[data-action="delete-selected-banks"]');
        if (deleteSelectedBtn) {
            const newBtn = deleteSelectedBtn.cloneNode(true);
            deleteSelectedBtn.parentNode.replaceChild(newBtn, deleteSelectedBtn);
            newBtn.addEventListener('click', () => {
                this.deleteSelectedBankAccounts();
            });
        }
        
        // Activate selected button
        const activateSelectedBtn = modal.querySelector('[data-action="activate-selected-banks"]');
        if (activateSelectedBtn) {
            const newBtn = activateSelectedBtn.cloneNode(true);
            activateSelectedBtn.parentNode.replaceChild(newBtn, activateSelectedBtn);
            newBtn.addEventListener('click', () => {
                this.activateSelectedBankAccounts();
            });
        }
        
        // Inactivate selected button
        const inactivateSelectedBtn = modal.querySelector('[data-action="inactivate-selected-banks"]');
        if (inactivateSelectedBtn) {
            const newBtn = inactivateSelectedBtn.cloneNode(true);
            inactivateSelectedBtn.parentNode.replaceChild(newBtn, inactivateSelectedBtn);
            newBtn.addEventListener('click', () => {
                this.inactivateSelectedBankAccounts();
                        });
                    }
                }
                
ProfessionalAccounting.prototype.setupBankingBulkActions = function() {
        const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // Individual checkboxes
        modal.querySelectorAll('.bank-account-checkbox').forEach(checkbox => {
            const newCheckbox = checkbox.cloneNode(true);
            checkbox.parentNode.replaceChild(newCheckbox, checkbox);
            newCheckbox.addEventListener('change', (e) => {
                const bankId = parseInt(e.target.dataset.bankId);
                const row = e.target.closest('tr');
                
                if (e.target.checked) {
                    this.bankingSelectedAccounts.add(bankId);
                    if (row) row.classList.add('row-selected');
                    } else {
                    this.bankingSelectedAccounts.delete(bankId);
                    if (row) row.classList.remove('row-selected');
                }
                
                this.updateBankingBulkActionsBar();
                this.updateSelectAllCheckbox();
            });
        });
    }

ProfessionalAccounting.prototype.updateBankingBulkActionsBar = function() {
        const bulkActionsBar = document.getElementById('bankingBulkActions');
        const selectedCountEl = document.getElementById('bankingSelectedCount');
        
        if (bulkActionsBar && selectedCountEl) {
            const count = this.bankingSelectedAccounts.size;
            selectedCountEl.textContent = count;
            bulkActionsBar.style.display = count > 0 ? 'flex' : 'none';
        }
    }

ProfessionalAccounting.prototype.updateSelectAllCheckbox = function() {
        const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        const selectAllCheckbox = modal.querySelector('#bankingSelectAll');
        if (!selectAllCheckbox) return;
        
        const allCheckboxes = modal.querySelectorAll('.bank-account-checkbox');
        const checkedCount = Array.from(allCheckboxes).filter(cb => cb.checked).length;
        
        selectAllCheckbox.checked = allCheckboxes.length > 0 && checkedCount === allCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }

ProfessionalAccounting.prototype.clearBankingFilters = function() {
        this.bankingSearch = '';
        this.bankingTypeFilter = '';
        this.bankingStatusFilter = '';
        this.bankingCurrentPage = 1;
        
        const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (modal) {
            const searchInput = modal.querySelector('#bankingSearchInput');
            const typeFilter = modal.querySelector('#bankingTypeFilter');
            const statusFilter = modal.querySelector('#bankingStatusFilter');
            
            if (searchInput) searchInput.value = '';
            if (typeFilter) typeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
        }
        
        this.loadBankAccounts();
    }

ProfessionalAccounting.prototype.deleteSelectedBankAccounts = async function() {
        if (this.bankingSelectedAccounts.size === 0) {
            this.showToast('No accounts selected', 'warning');
            return;
        }
        
        const accountIds = Array.from(this.bankingSelectedAccounts);
        const count = accountIds.length;
        
        const confirmed = await this.showConfirmDialog(
            `Delete ${count} Account(s)?`,
            `Are you sure you want to permanently delete ${count} selected bank account(s)? This action cannot be undone and will permanently remove the accounts from the database.`,
            'Delete',
            'Cancel',
            'danger'
        );
        
        if (!confirmed) return;
        
        let successCount = 0;
        let failCount = 0;
        const errors = [];
        
        for (const accountId of accountIds) {
            try {
                const response = await fetch(`${this.apiBase}/banks.php?id=${accountId}`, {
                    method: 'DELETE'
                });
                
                if (!response.ok) {
                    failCount++;
                    errors.push(`Account ${accountId}: HTTP ${response.status}`);
                    continue;
                }
                
                const data = await response.json();
                if (data.success) {
                    successCount++;
                    this.bankingSelectedAccounts.delete(accountId);
                } else {
                    failCount++;
                    errors.push(`Account ${accountId}: ${data.message || 'Delete failed'}`);
                }
            } catch (error) {
                failCount++;
                errors.push(`Account ${accountId}: ${error.message}`);
                console.error(`Error deleting account ${accountId}:`, error);
            }
        }
        
        if (successCount > 0) {
            this.showToast(`${successCount} account(s) deleted successfully`, 'success');
        }
        if (failCount > 0) {
            const errorMsg = errors.length > 0 ? errors.slice(0, 3).join('; ') : '';
            this.showToast(`Failed to delete ${failCount} account(s). ${errorMsg}`, 'error');
        }
        
        // Clear selection after operation
        this.bankingSelectedAccounts.clear();
        await this.loadBankAccounts();
    }

ProfessionalAccounting.prototype.exportSelectedBankAccounts = function() {
        if (this.bankingSelectedAccounts.size === 0) {
            this.showToast('No accounts selected', 'warning');
            return;
        }
        
        const selectedBanks = this.bankingAllAccounts.filter(bank => 
            this.bankingSelectedAccounts.has(bank.id)
        );
        
        if (selectedBanks.length === 0) {
            this.showToast('No accounts to export', 'warning');
            return;
        }
        
        // Create CSV content
        const headers = ['ID', 'Bank Name', 'Account Name', 'Account Number', 'Account Type', 'Opening Balance', 'Current Balance', 'Status'];
        const rows = selectedBanks.map(bank => {
            const formattedId = `BA${String(bank.id).padStart(3, '0')}`;
            return [
                formattedId,
                bank.bank_name || '',
                bank.account_name || '',
                bank.account_number || '',
                bank.account_type || 'Checking',
                bank.opening_balance || 0,
                bank.current_balance || 0,
                bank.is_active ? 'Active' : 'Inactive'
            ];
        });
        
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `bank_accounts_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showToast(`${selectedBanks.length} account(s) exported successfully`, 'success');
    }

ProfessionalAccounting.prototype.printSelectedBankAccounts = function() {
        if (this.bankingSelectedAccounts.size === 0) {
            this.showToast('No accounts selected', 'warning');
            return;
        }
        
        const selectedBanks = this.bankingAllAccounts.filter(bank => 
            this.bankingSelectedAccounts.has(bank.id)
        );
        
        if (selectedBanks.length === 0) {
            this.showToast('No accounts to print', 'warning');
            return;
        }
        
        // Create print window
        const printWindow = window.open('', '_blank');
        const formattedId = (id) => `BA${String(id).padStart(3, '0')}`;
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
                <head>
                <title>Bank Accounts Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #4CAF50; color: white; }
                    tr:nth-child(even) { background-color: #f2f2f2; }
                    .amount { text-align: right; }
                </style>
                </head>
            <body>
                <h1>Bank Accounts Report</h1>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                <p>Total Accounts: ${selectedBanks.length}</p>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bank Name</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
                            <th>Account Type</th>
                            <th class="amount">Opening Balance</th>
                            <th class="amount">Current Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${selectedBanks.map(bank => `
                            <tr>
                                <td>${formattedId(bank.id)}</td>
                                <td>${this.escapeHtml(bank.bank_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_number || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_type || 'Checking')}</td>
                                <td class="amount">${this.formatCurrency(bank.opening_balance || 0)}</td>
                                <td class="amount">${this.formatCurrency(bank.current_balance || 0)}</td>
                                <td>${bank.is_active ? 'Active' : 'Inactive'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
        
        this.showToast(`${selectedBanks.length} account(s) sent to printer`, 'success');
    }

ProfessionalAccounting.prototype.activateSelectedBankAccounts = async function() {
        if (this.bankingSelectedAccounts.size === 0) {
            this.showToast('No accounts selected', 'warning');
            return;
        }
        
        const accountIds = Array.from(this.bankingSelectedAccounts);
        const count = accountIds.length;
        
        const confirmed = await this.showConfirmDialog(
            `Activate ${count} Account(s)?`,
            `Are you sure you want to activate ${count} selected bank account(s)?`,
            'Activate',
            'Cancel'
        );
        
        if (!confirmed) return;
        
        let successCount = 0;
        let failCount = 0;
        const errors = [];
        
        for (const accountId of accountIds) {
            try {
                // First, get the current bank account data
                const getResponse = await fetch(`${this.apiBase}/banks.php?id=${accountId}`);
                if (!getResponse.ok) {
                    failCount++;
                    errors.push(`Account ${accountId}: HTTP ${getResponse.status}`);
                    continue;
                }
                
                const getData = await getResponse.json();
                
                if (getData.success && getData.bank) {
                    const bank = getData.bank;
                    // Update with is_active = 1, preserving other fields
                    const updateResponse = await fetch(`${this.apiBase}/banks.php?id=${accountId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            bank_name: bank.bank_name || '',
                            account_name: bank.account_name || '',
                            account_number: bank.account_number || '',
                            account_type: bank.account_type || 'Checking',
                            is_active: 1
                        })
                    });
                    
                    if (!updateResponse.ok) {
                        failCount++;
                        errors.push(`Account ${accountId}: HTTP ${updateResponse.status}`);
                        continue;
                    }
                    
                    const updateData = await updateResponse.json();
                    if (updateData.success) {
                        successCount++;
                    } else {
                        failCount++;
                        errors.push(`Account ${accountId}: ${updateData.message || 'Update failed'}`);
                    }
                } else {
                    failCount++;
                    errors.push(`Account ${accountId}: ${getData.message || 'Failed to load account data'}`);
                    }
                } catch (error) {
                failCount++;
                errors.push(`Account ${accountId}: ${error.message}`);
                console.error(`Error activating account ${accountId}:`, error);
            }
        }
        
        if (successCount > 0) {
            this.showToast(`${successCount} account(s) activated successfully`, 'success');
        }
        if (failCount > 0) {
            const errorMsg = errors.length > 0 ? errors.slice(0, 3).join('; ') : '';
            this.showToast(`Failed to activate ${failCount} account(s). ${errorMsg}`, 'error');
        }
        
        // Clear selection after operation
        this.bankingSelectedAccounts.clear();
        await this.loadBankAccounts();
    }

ProfessionalAccounting.prototype.inactivateSelectedBankAccounts = async function() {
        if (this.bankingSelectedAccounts.size === 0) {
            this.showToast('No accounts selected', 'warning');
            return;
        }
        
        const accountIds = Array.from(this.bankingSelectedAccounts);
        const count = accountIds.length;
        
        const confirmed = await this.showConfirmDialog(
            `Inactivate ${count} Account(s)?`,
            `Are you sure you want to inactivate ${count} selected bank account(s)?`,
            'Inactivate',
            'Cancel'
        );
        
        if (!confirmed) return;
        
        let successCount = 0;
        let failCount = 0;
        const errors = [];
        
        for (const accountId of accountIds) {
            try {
                // First, get the current bank account data
                const getResponse = await fetch(`${this.apiBase}/banks.php?id=${accountId}`);
                if (!getResponse.ok) {
                    failCount++;
                    errors.push(`Account ${accountId}: HTTP ${getResponse.status}`);
                    continue;
                }
                
                const getData = await getResponse.json();
                
                if (getData.success && getData.bank) {
                    const bank = getData.bank;
                    // Update with is_active = 0, preserving other fields
                    const updateResponse = await fetch(`${this.apiBase}/banks.php?id=${accountId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            bank_name: bank.bank_name || '',
                            account_name: bank.account_name || '',
                            account_number: bank.account_number || '',
                            account_type: bank.account_type || 'Checking',
                            is_active: 0
                        })
                    });
                    
                    if (!updateResponse.ok) {
                        failCount++;
                        errors.push(`Account ${accountId}: HTTP ${updateResponse.status}`);
                        continue;
                    }
                    
                    const updateData = await updateResponse.json();
                    if (updateData.success) {
                        successCount++;
                    } else {
                        failCount++;
                        errors.push(`Account ${accountId}: ${updateData.message || 'Update failed'}`);
                    }
                } else {
                    failCount++;
                    errors.push(`Account ${accountId}: ${getData.message || 'Failed to load account data'}`);
                    }
                } catch (error) {
                failCount++;
                errors.push(`Account ${accountId}: ${error.message}`);
                console.error(`Error inactivating account ${accountId}:`, error);
            }
        }
        
        if (successCount > 0) {
            this.showToast(`${successCount} account(s) inactivated successfully`, 'success');
        }
        if (failCount > 0) {
            const errorMsg = errors.length > 0 ? errors.slice(0, 3).join('; ') : '';
            this.showToast(`Failed to inactivate ${failCount} account(s). ${errorMsg}`, 'error');
        }
        
        // Clear selection after operation
        this.bankingSelectedAccounts.clear();
        // Reset status filter to show all accounts after inactivation (so user can see inactive accounts)
        this.bankingStatusFilter = '';
        const modal = document.getElementById('bankingCashModal');
        if (modal) {
            const statusFilter = modal.querySelector('#bankingStatusFilter');
            if (statusFilter) statusFilter.value = '';
        }
        await this.loadBankAccounts();
    }

    // Additional Missing Methods - Stub implementations to prevent errors
    
ProfessionalAccounting.prototype.viewVoucher = async function(voucherId, voucherType) {
        try {
            const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}`);
            const data = await response.json();
            if (data.success && data.voucher) {
                const voucher = data.voucher;
                const content = `
                    <div class="voucher-details">
                        <h3>${voucherType === 'payment' ? 'Payment' : 'Receipt'} Voucher #${voucher.voucher_number || voucherId}</h3>
                        <div class="detail-row">
                            <label>Date:</label>
                            <span>${voucher.voucher_date || 'N/A'}</span>
                            </div>
                        <div class="detail-row">
                            <label>Amount:</label>
                            <span>${this.formatCurrency(voucher.amount || 0, voucher.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Description:</label>
                            <span>${this.escapeHtml(voucher.description || 'N/A')}</span>
                        </div>
                    </div>
                `;
                this.showModal(`View ${voucherType === 'payment' ? 'Payment' : 'Receipt'} Voucher`, content);
            } else {
                this.showToast(data.message || 'Failed to load voucher', 'error');
            }
        } catch (error) {
            this.showToast('Error loading voucher: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.printVoucher = async function(voucherId, voucherType) {
        try {
            const response = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?id=${voucherId}&type=${voucherType}&format=print`, {
                credentials: 'include'
            });
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing voucher: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.printInvoice = async function(invoiceId) {
        try {
            const response = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}&format=print`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing invoice: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.printBill = async function(billId) {
        try {
            const response = await fetch(`${this.apiBase}/bills.php?id=${billId}&format=print`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing bill: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.openBankTransactionModal = async function() {
        // Initialize pagination state
        this.bankTransCurrentPage = this.bankTransCurrentPage || 1;
        this.bankTransPerPage = this.bankTransPerPage || 10;
        this.bankTransSearch = this.bankTransSearch || '';
        this.bankTransData = this.bankTransData || [];
        
        const content = `
            <div class="accounting-module-modal-content">
                <div class="module-header">
                    <h2><i class="fas fa-exchange-alt"></i> Bank Transactions</h2>
                    <div class="header-actions">
                        <button class="btn btn-primary" data-action="new-bank-transaction">
                            <i class="fas fa-plus"></i> New Transaction
                        </button>
                    </div>
                </div>
                <div class="module-content">
                    <div class="filters-bar">
                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="bankTransSearch" class="filter-input" placeholder="Search transactions..." value="${this.bankTransSearch}">
                            </div>
                        <div class="filter-group">
                            <label>Bank Account:</label>
                            <select id="bankTransAccountFilter" class="filter-select">
                                <option value="">All Accounts</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Type:</label>
                            <select id="bankTransTypeFilter" class="filter-select">
                                <option value="">All Types</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary btn-sm" id="bankTransApplyFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                    <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                        <table class="data-table modal-table-fixed">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Bank Account</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="amount-column">Amount</th>
                                    <th>Reference</th>
                                    <th class="actions-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="bankTransTableBody">
                                <tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination-bottom">
                        <div class="pagination-info" id="bankTransPaginationInfo"></div>
                        <div class="pagination-controls">
                            <button class="btn btn-sm btn-secondary" id="bankTransPrevBtn">Previous</button>
                            <span id="bankTransPageNumbers"></span>
                            <button class="btn btn-sm btn-secondary" id="bankTransNextBtn">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.showModal('Bank Transactions', content, 'large');
        
        setTimeout(async () => {
            await this.loadBankTransactions();
            this.setupBankTransactionHandlers();
        }, 100);
    }

ProfessionalAccounting.prototype.loadBankTransactions = async function() {
        try {
            const response = await fetch(`${this.apiBase}/bank-transactions.php`);
            const data = await response.json();
            
            if (data.success && data.transactions) {
                this.bankTransData = data.transactions;
                this.renderBankTransactionsTable();
                
                // Populate bank account filter
                const accountFilter = document.getElementById('bankTransAccountFilter');
                if (accountFilter) {
                    const accounts = [...new Set(this.bankTransData.map(t => t.bank_account_name || t.bank_name).filter(Boolean))];
                    accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account;
                        option.textContent = account;
                        accountFilter.appendChild(option);
                    });
                }
            } else {
                this.bankTransData = [];
                this.renderBankTransactionsTable();
                    }
                } catch (error) {
            console.error('Error loading bank transactions:', error);
            this.bankTransData = [];
            this.renderBankTransactionsTable();
        }
    }

ProfessionalAccounting.prototype.renderBankTransactionsTable = function() {
        const tbody = document.getElementById('bankTransTableBody');
        if (!tbody) return;
        
        let filtered = [...this.bankTransData];
        
        // Apply search filter
        const searchTerm = (this.bankTransSearch || '').toLowerCase();
        if (searchTerm) {
            filtered = filtered.filter(t =>
                (t.description && t.description.toLowerCase().includes(searchTerm)) ||
                (t.reference_number && t.reference_number.toLowerCase().includes(searchTerm)) ||
                (t.bank_account_name && t.bank_account_name.toLowerCase().includes(searchTerm))
            );
        }
        
        // Apply account filter
        const accountFilter = document.getElementById('bankTransAccountFilter')?.value;
        if (accountFilter) {
            filtered = filtered.filter(t => 
                (t.bank_account_name || t.bank_name) === accountFilter
            );
        }
        
        // Apply type filter
        const typeFilter = document.getElementById('bankTransTypeFilter')?.value;
        if (typeFilter) {
            filtered = filtered.filter(t => t.transaction_type === typeFilter);
        }
        
        // Pagination
        const totalCount = filtered.length;
        const totalPages = Math.ceil(totalCount / this.bankTransPerPage);
        const start = (this.bankTransCurrentPage - 1) * this.bankTransPerPage;
        const end = start + this.bankTransPerPage;
        const paginated = filtered.slice(start, end);
        
        // Render table
        if (paginated.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No transactions found</td></tr>';
            } else {
            tbody.innerHTML = paginated.map(trans => `
                <tr>
                    <td>${trans.transaction_date || 'N/A'}</td>
                    <td>${this.escapeHtml(trans.bank_account_name || trans.bank_name || 'N/A')}</td>
                    <td><span class="badge badge-${trans.transaction_type === 'deposit' ? 'success' : trans.transaction_type === 'withdrawal' ? 'danger' : 'info'}">${this.escapeHtml(trans.transaction_type || 'N/A')}</span></td>
                    <td>${this.escapeHtml(trans.description || 'N/A')}</td>
                    <td class="amount-column">${this.formatCurrency(trans.amount || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                    <td>${this.escapeHtml(trans.reference_number || 'N/A')}</td>
                    <td class="actions-column">
                        <button class="btn btn-sm btn-info" data-action="view-bank-transaction" data-id="${trans.id}">
                                    <i class="fas fa-eye"></i>
                                </button>
                        <button class="btn btn-sm btn-danger" data-action="delete-bank-transaction" data-id="${trans.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                        </td>
                    </tr>
            `).join('');
        }
        
        // Update pagination
        const paginationInfo = document.getElementById('bankTransPaginationInfo');
        if (paginationInfo) {
            paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
        }
        
        const pageNumbers = document.getElementById('bankTransPageNumbers');
        if (pageNumbers) {
            pageNumbers.textContent = `Page ${this.bankTransCurrentPage} of ${totalPages || 1}`;
        }
        
        const prevBtn = document.getElementById('bankTransPrevBtn');
        const nextBtn = document.getElementById('bankTransNextBtn');
        if (prevBtn) prevBtn.disabled = this.bankTransCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.bankTransCurrentPage >= totalPages;
    }

ProfessionalAccounting.prototype.setupBankTransactionHandlers = function() {
        const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // Search handler
        const searchInput = document.getElementById('bankTransSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                this.bankTransSearch = e.target.value.trim();
                searchTimeout = setTimeout(() => {
                    this.bankTransCurrentPage = 1;
                    this.renderBankTransactionsTable();
                }, 300);
            });
        }
        
        // Filter handlers
        const accountFilter = document.getElementById('bankTransAccountFilter');
        const typeFilter = document.getElementById('bankTransTypeFilter');
        const applyFiltersBtn = document.getElementById('bankTransApplyFilters');
        
        if (accountFilter) {
            accountFilter.addEventListener('change', () => {
                this.bankTransCurrentPage = 1;
                this.renderBankTransactionsTable();
            });
        }
        
        if (typeFilter) {
            typeFilter.addEventListener('change', () => {
                this.bankTransCurrentPage = 1;
                this.renderBankTransactionsTable();
            });
        }
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.bankTransCurrentPage = 1;
                this.renderBankTransactionsTable();
            });
        }
        
        // Pagination handlers
        const prevBtn = document.getElementById('bankTransPrevBtn');
        const nextBtn = document.getElementById('bankTransNextBtn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.bankTransCurrentPage > 1) {
                    this.bankTransCurrentPage--;
                    this.renderBankTransactionsTable();
                }
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil((this.bankTransData?.length || 0) / this.bankTransPerPage);
                if (this.bankTransCurrentPage < totalPages) {
                    this.bankTransCurrentPage++;
                    this.renderBankTransactionsTable();
                }
            });
        }
        
        // Action handlers
        modal.querySelectorAll('[data-action="view-bank-transaction"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                this.viewBankTransaction(id);
            });
        });
        
        modal.querySelectorAll('[data-action="delete-bank-transaction"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                this.deleteBankTransaction(id);
            });
        });
        
        modal.querySelectorAll('[data-action="new-bank-transaction"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.openBankTransactionForm();
            });
        });
    }

ProfessionalAccounting.prototype.openBankTransactionForm = async function(transactionId = null) {
        const isEdit = transactionId !== null;
        let transactionData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`);
                const data = await response.json();
                if (data.success && data.transaction) {
                    transactionData = data.transaction;
                } else {
                    this.showToast(data.message || 'Failed to load transaction', 'error');
                    return;
                }
            } catch (error) {
                this.showToast('Error loading transaction: ' + error.message, 'error');
                return;
            }
        }
        
        // Load bank accounts for dropdown
        let bankAccounts = [];
        try {
            const response = await fetch(`${this.apiBase}/banks.php`);
            const data = await response.json();
            if (data.success && data.accounts) {
                bankAccounts = data.accounts;
            }
        } catch (error) {
            console.error('Error loading bank accounts:', error);
        }
        
        const formContent = `
            <form id="bankTransactionForm">
                <div class="accounting-modal-form-group">
                    <label for="bankTransAccount">Bank Account <span class="required">*</span></label>
                    <select id="bankTransAccount" name="bank_account_id" class="form-control" required>
                        <option value="">Select Bank Account</option>
                        ${bankAccounts.map(acc => `
                            <option value="${acc.id}" ${transactionData && transactionData.bank_account_id == acc.id ? 'selected' : ''}>
                                ${this.escapeHtml(acc.bank_name || '')} - ${this.escapeHtml(acc.account_name || '')}
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="accounting-modal-form-group">
                    <label for="bankTransDate">Transaction Date <span class="required">*</span></label>
                    <input type="text" id="bankTransDate" name="transaction_date" class="form-control date-input" required value="${transactionData ? transactionData.transaction_date || '' : ''}" placeholder="MM/DD/YYYY">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="bankTransType">Transaction Type <span class="required">*</span></label>
                    <select id="bankTransType" name="transaction_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="deposit" ${transactionData && transactionData.transaction_type === 'deposit' ? 'selected' : ''}>Deposit</option>
                        <option value="withdrawal" ${transactionData && transactionData.transaction_type === 'withdrawal' ? 'selected' : ''}>Withdrawal</option>
                        <option value="transfer" ${transactionData && transactionData.transaction_type === 'transfer' ? 'selected' : ''}>Transfer</option>
                    </select>
                </div>
                <div class="accounting-modal-form-group">
                    <label for="bankTransAmount">Amount <span class="required">*</span></label>
                    <input type="number" id="bankTransAmount" name="amount" class="form-control" required step="0.01" min="0" value="${transactionData ? transactionData.amount || '' : ''}" placeholder="0.00">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="bankTransDescription">Description</label>
                    <textarea id="bankTransDescription" name="description" class="form-control" rows="3" placeholder="Transaction description">${transactionData ? this.escapeHtml(transactionData.description || '') : ''}</textarea>
                </div>
                <div class="accounting-modal-form-group">
                    <label for="bankTransReference">Reference Number</label>
                    <input type="text" id="bankTransReference" name="reference_number" class="form-control" value="${transactionData ? this.escapeHtml(transactionData.reference_number || '') : ''}" placeholder="Optional reference">
                </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Transaction</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Bank Transaction' : 'New Bank Transaction', formContent, 'normal', 'bankTransactionFormModal');
        
        setTimeout(() => {
            const form = document.getElementById('bankTransactionForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveBankTransaction(transactionId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveBankTransaction = async function(transactionId = null) {
        try {
            const bankAccountId = document.getElementById('bankTransAccount')?.value;
            const transactionDate = document.getElementById('bankTransDate')?.value;
            const transactionType = document.getElementById('bankTransType')?.value;
            const amount = parseFloat(document.getElementById('bankTransAmount')?.value || 0);
            const description = document.getElementById('bankTransDescription')?.value.trim();
            const referenceNumber = document.getElementById('bankTransReference')?.value.trim();
            
            if (!bankAccountId || !transactionDate || !transactionType) {
                this.showToast('All required fields must be filled', 'error');
                return;
            }
            
            if (amount <= 0) {
                this.showToast('Amount must be greater than 0', 'error');
            return;
        }
        
            const method = transactionId ? 'PUT' : 'POST';
            const url = transactionId ? `${this.apiBase}/bank-transactions.php?id=${transactionId}` : `${this.apiBase}/bank-transactions.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    bank_account_id: parseInt(bankAccountId), 
                    transaction_date: transactionDate, 
                    transaction_type: transactionType, 
                    amount: amount, 
                    description, 
                    reference_number: referenceNumber 
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(transactionId ? 'Bank transaction updated successfully' : 'Bank transaction created successfully', 'success');
                const modal = document.getElementById('bankTransactionFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                await this.loadBankTransactions();
            } else {
                this.showToast(data.message || 'Failed to save bank transaction', 'error');
            }
        } catch (error) {
            this.showToast('Error saving bank transaction: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.openBankReconciliationModal = async function() {
        const content = `
            <div class="accounting-module-modal-content">
                <div class="module-header">
                    <h2><i class="fas fa-balance-scale"></i> Bank Reconciliation</h2>
                    <div class="header-actions">
                        <button class="btn btn-primary" data-action="new-reconciliation">
                            <i class="fas fa-plus"></i> New Reconciliation
                                        </button>
                    </div>
                </div>
                <div class="module-content">
                    <div class="filters-bar">
                        <div class="filter-group">
                            <label>Bank Account:</label>
                            <select id="reconciliationAccountFilter" class="filter-select">
                                <option value="">Select Account</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status:</label>
                            <select id="reconciliationStatusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary btn-sm" id="reconciliationApplyFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                                        </button>
                                </div>
                    <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                        <table class="data-table modal-table-fixed">
                            <thead>
                                <tr>
                                    <th>Reconciliation Date</th>
                                    <th>Bank Account</th>
                                    <th>Statement Balance</th>
                                    <th>Book Balance</th>
                                    <th>Difference</th>
                                    <th>Status</th>
                                    <th class="actions-column">Actions</th>
                        </tr>
                            </thead>
                            <tbody id="reconciliationTableBody">
                                <tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        this.showModal('Bank Reconciliation', content, 'large');
        
        setTimeout(async () => {
            await this.loadBankReconciliations();
            this.setupBankReconciliationHandlers();
        }, 100);
    }

ProfessionalAccounting.prototype.loadBankReconciliations = async function() {
        try {
            const response = await fetch(`${this.apiBase}/bank-reconciliation.php`);
            const data = await response.json();
            
            if (data.success && data.reconciliations) {
                this.reconciliationData = data.reconciliations;
                this.renderBankReconciliationsTable();
                
                // Populate bank account filter
                const accountFilter = document.getElementById('reconciliationAccountFilter');
                if (accountFilter) {
                    const accounts = [...new Set(this.reconciliationData.map(r => r.bank_account_name || r.account_name).filter(Boolean))];
                    accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account;
                        option.textContent = account;
                        accountFilter.appendChild(option);
                    });
                }
            } else {
                this.reconciliationData = [];
                this.renderBankReconciliationsTable();
            }
        } catch (error) {
            console.error('Error loading bank reconciliations:', error);
            this.reconciliationData = [];
            this.renderBankReconciliationsTable();
        }
    }

ProfessionalAccounting.prototype.renderBankReconciliationsTable = function() {
        const tbody = document.getElementById('reconciliationTableBody');
        if (!tbody) return;
        
        let filtered = [...(this.reconciliationData || [])];
        
        // Apply account filter
        const accountFilter = document.getElementById('reconciliationAccountFilter')?.value;
        if (accountFilter) {
            filtered = filtered.filter(r => 
                (r.bank_account_name || r.account_name) === accountFilter
            );
        }
        
        // Apply status filter
        const statusFilter = document.getElementById('reconciliationStatusFilter')?.value;
        if (statusFilter) {
            filtered = filtered.filter(r => r.status === statusFilter);
        }
        
        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No reconciliations found</td></tr>';
            } else {
            tbody.innerHTML = filtered.map(rec => {
                const difference = (rec.statement_balance || 0) - (rec.book_balance || 0);
                const statusClass = rec.status === 'completed' ? 'success' : rec.status === 'in_progress' ? 'warning' : 'secondary';
                    return `
                    <tr>
                        <td>${rec.reconciliation_date || rec.date || 'N/A'}</td>
                        <td>${this.escapeHtml(rec.bank_account_name || rec.account_name || 'N/A')}</td>
                        <td class="amount-column">${this.formatCurrency(rec.statement_balance || 0, rec.currency || this.getDefaultCurrencySync())}</td>
                        <td class="amount-column">${this.formatCurrency(rec.book_balance || 0, rec.currency || this.getDefaultCurrencySync())}</td>
                        <td class="amount-column ${difference !== 0 ? 'text-danger' : ''}">${this.formatCurrency(difference, rec.currency || this.getDefaultCurrencySync())}</td>
                        <td><span class="badge badge-${statusClass}">${this.escapeHtml(rec.status || 'N/A')}</span></td>
                        <td class="actions-column">
                            <button class="btn btn-sm btn-info" data-action="view-reconciliation" data-id="${rec.id}">
                                <i class="fas fa-eye"></i>
                                </button>
                            ${rec.status !== 'completed' ? `
                                <button class="btn btn-sm btn-success" data-action="complete-reconciliation" data-id="${rec.id}">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            </td>
                        </tr>
                    `;
                }).join('');
        }
    }

ProfessionalAccounting.prototype.setupBankReconciliationHandlers = function() {
        const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // Filter handlers
        const accountFilter = document.getElementById('reconciliationAccountFilter');
        const statusFilter = document.getElementById('reconciliationStatusFilter');
        const applyFiltersBtn = document.getElementById('reconciliationApplyFilters');
        
        if (accountFilter) {
            accountFilter.addEventListener('change', () => {
                this.renderBankReconciliationsTable();
            });
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.renderBankReconciliationsTable();
            });
        }
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.renderBankReconciliationsTable();
            });
        }
        
        // Action handlers
        modal.querySelectorAll('[data-action="view-reconciliation"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                this.viewReconciliation(id);
            });
        });
        
        modal.querySelectorAll('[data-action="complete-reconciliation"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                this.completeReconciliation(id);
            });
        });
        
        modal.querySelectorAll('[data-action="new-reconciliation"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.openReconciliationForm();
            });
        });
    }

ProfessionalAccounting.prototype.viewReconciliation = async function(id) {
        try {
            const response = await fetch(`${this.apiBase}/bank-reconciliation.php?id=${id}`);
            const data = await response.json();
            if (data.success && data.reconciliation) {
                const rec = data.reconciliation;
                const content = `
                    <div class="reconciliation-details">
                        <h3>Reconciliation #${rec.id}</h3>
                        <div class="detail-row">
                            <label>Date:</label>
                            <span>${rec.reconciliation_date || rec.date || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Bank Account:</label>
                            <span>${this.escapeHtml(rec.bank_account_name || rec.account_name || 'N/A')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Statement Balance:</label>
                            <span>${this.formatCurrency(rec.statement_balance || 0, rec.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Book Balance:</label>
                            <span>${this.formatCurrency(rec.book_balance || 0, rec.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Difference:</label>
                            <span>${this.formatCurrency((rec.statement_balance || 0) - (rec.book_balance || 0), rec.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Status:</label>
                            <span>${this.escapeHtml(rec.status || 'N/A')}</span>
                        </div>
                    </div>
                `;
                this.showModal('View Reconciliation', content);
            } else {
                this.showToast(data.message || 'Failed to load reconciliation', 'error');
            }
        } catch (error) {
            this.showToast('Error loading reconciliation: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.completeReconciliation = async function(id) {
        try {
            const response = await fetch(`${this.apiBase}/bank-reconciliation.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 'completed' })
            });
            const data = await response.json();
            if (data.success) {
                this.showToast('Reconciliation completed successfully', 'success');
                await this.loadBankReconciliations();
            } else {
                this.showToast(data.message || 'Failed to complete reconciliation', 'error');
            }
        } catch (error) {
            this.showToast('Error completing reconciliation: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.openReconciliationForm = async function(reconciliationId = null) {
        const isEdit = reconciliationId !== null;
        let reconciliationData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/bank-reconciliation.php?id=${reconciliationId}`);
                const data = await response.json();
                if (data.success && data.reconciliation) {
                    reconciliationData = data.reconciliation;
            } else {
                    this.showToast(data.message || 'Failed to load reconciliation', 'error');
                    return;
            }
        } catch (error) {
                this.showToast('Error loading reconciliation: ' + error.message, 'error');
                return;
            }
        }
        
        // Load bank accounts for dropdown
        let bankAccounts = [];
        try {
            const response = await fetch(`${this.apiBase}/banks.php`);
            const data = await response.json();
            if (data.success && data.accounts) {
                bankAccounts = data.accounts;
            }
        } catch (error) {
            console.error('Error loading bank accounts:', error);
        }
        
        const formContent = `
            <form id="reconciliationForm">
                <div class="accounting-modal-form-group">
                    <label for="reconciliationAccount">Bank Account <span class="required">*</span></label>
                    <select id="reconciliationAccount" name="bank_account_id" class="form-control" required>
                        <option value="">Select Bank Account</option>
                        ${bankAccounts.map(acc => `
                            <option value="${acc.id}" ${reconciliationData && reconciliationData.bank_account_id == acc.id ? 'selected' : ''}>
                                ${this.escapeHtml(acc.bank_name || '')} - ${this.escapeHtml(acc.account_name || '')}
                            </option>
                        `).join('')}
                    </select>
                </div>
                    <div class="accounting-modal-form-group">
                    <label for="reconciliationDate">Reconciliation Date <span class="required">*</span></label>
                    <input type="text" id="reconciliationDate" name="reconciliation_date" class="form-control date-input" required value="${reconciliationData ? reconciliationData.reconciliation_date || reconciliationData.date || '' : ''}" placeholder="MM/DD/YYYY">
                    </div>
                    <div class="accounting-modal-form-group">
                    <label for="reconciliationStatementBalance">Statement Balance <span class="required">*</span></label>
                    <input type="number" id="reconciliationStatementBalance" name="statement_balance" class="form-control" required step="0.01" value="${reconciliationData ? reconciliationData.statement_balance || '' : ''}" placeholder="0.00">
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="reconciliationBookBalance">Book Balance <span class="required">*</span></label>
                    <input type="number" id="reconciliationBookBalance" name="book_balance" class="form-control" required step="0.01" value="${reconciliationData ? reconciliationData.book_balance || '' : ''}" placeholder="0.00">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="reconciliationNotes">Notes</label>
                    <textarea id="reconciliationNotes" name="notes" class="form-control" rows="3" placeholder="Reconciliation notes">${reconciliationData ? this.escapeHtml(reconciliationData.notes || '') : ''}</textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Reconciliation</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Reconciliation' : 'New Reconciliation', formContent, 'normal', 'reconciliationFormModal');
        
        setTimeout(() => {
            const form = document.getElementById('reconciliationForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveReconciliation(reconciliationId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveReconciliation = async function(reconciliationId = null) {
        try {
            const bankAccountId = document.getElementById('reconciliationAccount')?.value;
            const reconciliationDate = document.getElementById('reconciliationDate')?.value;
            const statementBalance = parseFloat(document.getElementById('reconciliationStatementBalance')?.value || 0);
            const bookBalance = parseFloat(document.getElementById('reconciliationBookBalance')?.value || 0);
            const notes = document.getElementById('reconciliationNotes')?.value.trim();
            
            if (!bankAccountId || !reconciliationDate) {
                this.showToast('Bank account and reconciliation date are required', 'error');
                return;
            }
            
            const method = reconciliationId ? 'PUT' : 'POST';
            const url = reconciliationId ? `${this.apiBase}/bank-reconciliation.php?id=${reconciliationId}` : `${this.apiBase}/bank-reconciliation.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    bank_account_id: parseInt(bankAccountId), 
                    reconciliation_date: reconciliationDate, 
                    statement_balance: statementBalance, 
                    book_balance: bookBalance, 
                    notes 
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(reconciliationId ? 'Reconciliation updated successfully' : 'Reconciliation created successfully', 'success');
                const modal = document.getElementById('reconciliationFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                await this.loadBankReconciliations();
                                } else {
                this.showToast(data.message || 'Failed to save reconciliation', 'error');
            }
        } catch (error) {
            this.showToast('Error saving reconciliation: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.viewBankTransaction = async function(transactionId) {
        try {
            const response = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`);
            const data = await response.json();
            if (data.success && data.transaction) {
                const trans = data.transaction;
                const content = `
                    <div class="transaction-details">
                        <h3>Bank Transaction #${trans.id}</h3>
                        <div class="detail-row">
                            <label>Date:</label>
                            <span>${trans.transaction_date || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Amount:</label>
                            <span>${this.formatCurrency(trans.amount || 0, trans.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Description:</label>
                            <span>${this.escapeHtml(trans.description || 'N/A')}</span>
                        </div>
                    </div>
                `;
                this.showModal('View Bank Transaction', content);
                                        } else {
                this.showToast(data.message || 'Failed to load transaction', 'error');
                        }
                                    } catch (error) {
            this.showToast('Error loading transaction: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteBankTransaction = async function(transactionId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Bank Transaction',
            'Are you sure you want to delete this bank transaction? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Bank transaction deleted successfully', 'success');
                    this.loadBankAccounts();
                        } else {
                    this.showToast(data.message || 'Failed to delete transaction', 'error');
                    }
                } catch (error) {
                this.showToast('Error deleting transaction: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.openPeriodsModal = async function() {
        this.periodsCurrentPage = this.periodsCurrentPage || 1;
        this.periodsPerPage = this.periodsPerPage || 10;
        this.periodsSearch = this.periodsSearch || '';
        
        const content = `
            <div class="accounting-module-modal-content">
                <div class="module-header">
                    <h2><i class="fas fa-calendar-alt"></i> Accounting Periods</h2>
                    <div class="header-actions">
                        <button class="btn btn-primary" data-action="new-period">
                            <i class="fas fa-plus"></i> New Period
                        </button>
                    </div>
                </div>
                <div class="module-content">
                    <div class="filters-bar">
                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="periodsSearch" class="filter-input" placeholder="Search periods..." value="${this.periodsSearch}">
                        </div>
                        <div class="filter-group">
                            <label>Status:</label>
                            <select id="periodsStatusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary btn-sm" id="periodsApplyFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                        <table class="data-table modal-table-fixed">
                            <thead>
                                <tr>
                                    <th>Period Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th class="actions-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="periodsTableBody">
                                <tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination-bottom">
                        <div class="pagination-info" id="periodsPaginationInfo"></div>
                        <div class="pagination-controls">
                            <button class="btn btn-sm btn-secondary" id="periodsPrevBtn">Previous</button>
                            <span id="periodsPageNumbers"></span>
                            <button class="btn btn-sm btn-secondary" id="periodsNextBtn">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.showModal('Accounting Periods', content, 'large');
        
        setTimeout(async () => {
            await this.loadPeriods();
            this.setupPeriodsHandlers();
        }, 100);
    }

ProfessionalAccounting.prototype.loadPeriods = async function() {
        try {
            const response = await fetch(`${this.apiBase}/periods.php`);
                            const data = await response.json();
                            
            if (data.success && data.periods) {
                this.periodsData = data.periods;
                            } else {
                this.periodsData = [];
                            }
            this.renderPeriodsTable();
                        } catch (error) {
            console.error('Error loading periods:', error);
            this.periodsData = [];
            this.renderPeriodsTable();
        }
    }

ProfessionalAccounting.prototype.renderPeriodsTable = function() {
        const tbody = document.getElementById('periodsTableBody');
        if (!tbody) return;
        
        let filtered = [...(this.periodsData || [])];
        
        const searchTerm = (this.periodsSearch || '').toLowerCase();
        if (searchTerm) {
            filtered = filtered.filter(p =>
                (p.period_name && p.period_name.toLowerCase().includes(searchTerm)) ||
                (p.name && p.name.toLowerCase().includes(searchTerm))
            );
        }
        
        const statusFilter = document.getElementById('periodsStatusFilter')?.value;
        if (statusFilter) {
            filtered = filtered.filter(p => p.status === statusFilter);
        }
        
        const totalCount = filtered.length;
        const totalPages = Math.ceil(totalCount / this.periodsPerPage);
        const start = (this.periodsCurrentPage - 1) * this.periodsPerPage;
        const end = start + this.periodsPerPage;
        const paginated = filtered.slice(start, end);
        
        if (paginated.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No periods found</td></tr>';
                    } else {
            tbody.innerHTML = paginated.map(period => `
                <tr>
                    <td>${this.escapeHtml(period.period_name || period.name || 'N/A')}</td>
                    <td>${period.start_date || 'N/A'}</td>
                    <td>${period.end_date || 'N/A'}</td>
                    <td><span class="badge badge-${period.status === 'closed' ? 'secondary' : 'success'}">${this.escapeHtml(period.status || 'open')}</span></td>
                    <td class="actions-column">
                        <button class="btn btn-sm btn-info" data-action="view-period" data-id="${period.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${period.status !== 'closed' ? `
                            <button class="btn btn-sm btn-warning" data-action="close-period" data-id="${period.id}">
                                <i class="fas fa-lock"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }
        
        const paginationInfo = document.getElementById('periodsPaginationInfo');
        if (paginationInfo) {
            paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
        }
        
        const pageNumbers = document.getElementById('periodsPageNumbers');
        if (pageNumbers) {
            pageNumbers.textContent = `Page ${this.periodsCurrentPage} of ${totalPages || 1}`;
        }
        
        const prevBtn = document.getElementById('periodsPrevBtn');
        const nextBtn = document.getElementById('periodsNextBtn');
        if (prevBtn) prevBtn.disabled = this.periodsCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.periodsCurrentPage >= totalPages;
    }

ProfessionalAccounting.prototype.setupPeriodsHandlers = function() {
        const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        const searchInput = document.getElementById('periodsSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                this.periodsSearch = e.target.value.trim();
                searchTimeout = setTimeout(() => {
                    this.periodsCurrentPage = 1;
                    this.renderPeriodsTable();
                }, 300);
            });
        }
        
        const statusFilter = document.getElementById('periodsStatusFilter');
        const applyFiltersBtn = document.getElementById('periodsApplyFilters');
        
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.periodsCurrentPage = 1;
                this.renderPeriodsTable();
            });
        }
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.periodsCurrentPage = 1;
                this.renderPeriodsTable();
            });
        }
        
        const prevBtn = document.getElementById('periodsPrevBtn');
        const nextBtn = document.getElementById('periodsNextBtn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.periodsCurrentPage > 1) {
                    this.periodsCurrentPage--;
                    this.renderPeriodsTable();
                }
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil((this.periodsData?.length || 0) / this.periodsPerPage);
                if (this.periodsCurrentPage < totalPages) {
                    this.periodsCurrentPage++;
                    this.renderPeriodsTable();
                    }
                });
            }
        
        modal.querySelectorAll('[data-action="new-period"]').forEach(btn => {
            btn.addEventListener('click', () => this.openPeriodForm());
        });
    }

ProfessionalAccounting.prototype.openTaxSettingsModal = async function() {
        this.taxSettingsCurrentPage = this.taxSettingsCurrentPage || 1;
        this.taxSettingsPerPage = this.taxSettingsPerPage || 10;
        this.taxSettingsSearch = this.taxSettingsSearch || '';
        
        const content = `
            <div class="accounting-module-modal-content">
                <div class="module-header">
                    <h2><i class="fas fa-percent"></i> Tax Settings</h2>
                    <div class="header-actions">
                        <button class="btn btn-primary" data-action="new-tax-setting">
                            <i class="fas fa-plus"></i> New Tax Setting
                        </button>
                </div>
                </div>
                <div class="module-content">
                    <div class="filters-bar">
                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="taxSettingsSearch" class="filter-input" placeholder="Search tax settings..." value="${this.taxSettingsSearch}">
                </div>
                        <div class="filter-group">
                            <label>Type:</label>
                            <select id="taxSettingsTypeFilter" class="filter-select">
                                <option value="">All Types</option>
                                <option value="sales">Sales Tax</option>
                                <option value="purchase">Purchase Tax</option>
                                <option value="vat">VAT</option>
                        </select>
                    </div>
                        <button class="btn btn-secondary btn-sm" id="taxSettingsApplyFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                        <table class="data-table modal-table-fixed">
                            <thead>
                                <tr>
                                    <th>Tax Name</th>
                                    <th>Type</th>
                                    <th>Rate (%)</th>
                                    <th>Status</th>
                                    <th class="actions-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="taxSettingsTableBody">
                                <tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                </div>
                    <div class="table-pagination-bottom">
                        <div class="pagination-info" id="taxSettingsPaginationInfo"></div>
                        <div class="pagination-controls">
                            <button class="btn btn-sm btn-secondary" id="taxSettingsPrevBtn">Previous</button>
                            <span id="taxSettingsPageNumbers"></span>
                            <button class="btn btn-sm btn-secondary" id="taxSettingsNextBtn">Next</button>
                </div>
                </div>
                </div>
                </div>
        `;
        
        this.showModal('Tax Settings', content, 'large');
        
        setTimeout(async () => {
            await this.loadTaxSettings();
            this.setupTaxSettingsHandlers();
        }, 100);
    }

ProfessionalAccounting.prototype.loadTaxSettings = async function() {
        try {
            const response = await fetch(`${this.apiBase}/tax-settings.php`);
                const data = await response.json();
            
            if (data.success && data.tax_settings) {
                this.taxSettingsData = data.tax_settings;
            } else {
                this.taxSettingsData = [];
            }
            this.renderTaxSettingsTable();
            } catch (error) {
            console.error('Error loading tax settings:', error);
            this.taxSettingsData = [];
            this.renderTaxSettingsTable();
        }
    }

ProfessionalAccounting.prototype.renderTaxSettingsTable = function() {
        const tbody = document.getElementById('taxSettingsTableBody');
        if (!tbody) return;
        
        let filtered = [...(this.taxSettingsData || [])];
        
        const searchTerm = (this.taxSettingsSearch || '').toLowerCase();
        if (searchTerm) {
            filtered = filtered.filter(t =>
                (t.tax_name && t.tax_name.toLowerCase().includes(searchTerm)) ||
                (t.name && t.name.toLowerCase().includes(searchTerm))
            );
        }
        
        const typeFilter = document.getElementById('taxSettingsTypeFilter')?.value;
        if (typeFilter) {
            filtered = filtered.filter(t => t.tax_type === typeFilter);
        }
        
        const totalCount = filtered.length;
        const totalPages = Math.ceil(totalCount / this.taxSettingsPerPage);
        const start = (this.taxSettingsCurrentPage - 1) * this.taxSettingsPerPage;
        const end = start + this.taxSettingsPerPage;
        const paginated = filtered.slice(start, end);
        
        if (paginated.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No tax settings found</td></tr>';
                                } else {
            tbody.innerHTML = paginated.map(tax => `
                <tr>
                    <td>${this.escapeHtml(tax.tax_name || tax.name || 'N/A')}</td>
                    <td><span class="badge badge-info">${this.escapeHtml(tax.tax_type || tax.type || 'N/A')}</span></td>
                    <td>${tax.tax_rate || tax.rate || 0}%</td>
                    <td><span class="badge badge-${tax.status === 'active' ? 'success' : 'secondary'}">${this.escapeHtml(tax.status || 'inactive')}</span></td>
                    <td class="actions-column">
                        <button class="btn btn-sm btn-info" data-action="edit-tax-setting" data-id="${tax.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        const paginationInfo = document.getElementById('taxSettingsPaginationInfo');
        if (paginationInfo) {
            paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
        }
        
        const pageNumbers = document.getElementById('taxSettingsPageNumbers');
        if (pageNumbers) {
            pageNumbers.textContent = `Page ${this.taxSettingsCurrentPage} of ${totalPages || 1}`;
        }
        
        const prevBtn = document.getElementById('taxSettingsPrevBtn');
        const nextBtn = document.getElementById('taxSettingsNextBtn');
        if (prevBtn) prevBtn.disabled = this.taxSettingsCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.taxSettingsCurrentPage >= totalPages;
    }

ProfessionalAccounting.prototype.setupTaxSettingsHandlers = function() {
        const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        const searchInput = document.getElementById('taxSettingsSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                this.taxSettingsSearch = e.target.value.trim();
                searchTimeout = setTimeout(() => {
                    this.taxSettingsCurrentPage = 1;
                    this.renderTaxSettingsTable();
                }, 300);
            });
        }
        
        const typeFilter = document.getElementById('taxSettingsTypeFilter');
        const applyFiltersBtn = document.getElementById('taxSettingsApplyFilters');
        
        if (typeFilter) {
            typeFilter.addEventListener('change', () => {
                this.taxSettingsCurrentPage = 1;
                this.renderTaxSettingsTable();
            });
        }
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.taxSettingsCurrentPage = 1;
                this.renderTaxSettingsTable();
            });
        }
        
        const prevBtn = document.getElementById('taxSettingsPrevBtn');
        const nextBtn = document.getElementById('taxSettingsNextBtn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.taxSettingsCurrentPage > 1) {
                    this.taxSettingsCurrentPage--;
                    this.renderTaxSettingsTable();
                        }
                    });
                }
                
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil((this.taxSettingsData?.length || 0) / this.taxSettingsPerPage);
                if (this.taxSettingsCurrentPage < totalPages) {
                    this.taxSettingsCurrentPage++;
                    this.renderTaxSettingsTable();
                }
            });
        }
        
        modal.querySelectorAll('[data-action="new-tax-setting"]').forEach(btn => {
            btn.addEventListener('click', () => this.openTaxSettingForm());
        });
        
        modal.querySelectorAll('[data-action="edit-tax-setting"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.target.closest('button').dataset.id);
                this.openTaxSettingForm(id);
            });
        });
    }

ProfessionalAccounting.prototype.openCustomerForm = async function(customerId = null) {
        const isEdit = customerId !== null;
        let customerData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/customers.php?id=${customerId}`);
                const data = await response.json();
                if (data.success && data.customer) {
                    customerData = data.customer;
                } else {
                    this.showToast(data.message || 'Failed to load customer', 'error');
                    return;
                        }
                    } catch (error) {
                this.showToast('Error loading customer: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = `
            <form id="customerForm">
                            <div class="accounting-modal-form-group">
                    <label for="customerName">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customerName" name="name" class="form-control" required value="${customerData ? this.escapeHtml(customerData.name || '') : ''}" placeholder="Enter customer name">
                        </div>
                            <div class="accounting-modal-form-group">
                    <label for="customerEmail">Email</label>
                    <input type="email" id="customerEmail" name="email" class="form-control" value="${customerData ? this.escapeHtml(customerData.email || '') : ''}" placeholder="customer@example.com">
                            </div>
                            <div class="accounting-modal-form-group">
                    <label for="customerPhone">Phone</label>
                    <input type="tel" id="customerPhone" name="phone" class="form-control" value="${customerData ? this.escapeHtml(customerData.phone || '') : ''}" placeholder="+966 50 123 4567">
                        </div>
                            <div class="accounting-modal-form-group">
                    <label for="customerAddress">Address</label>
                    <textarea id="customerAddress" name="address" class="form-control" rows="3" placeholder="Enter address">${customerData ? this.escapeHtml(customerData.address || '') : ''}</textarea>
                            </div>
                            <div class="accounting-modal-form-group">
                    <label for="customerTaxId">Tax ID</label>
                    <input type="text" id="customerTaxId" name="tax_id" class="form-control" value="${customerData ? this.escapeHtml(customerData.tax_id || '') : ''}" placeholder="Tax identification number">
                            </div>
                            <div class="accounting-modal-form-group">
                    <label for="customerStatus">Status</label>
                    <select id="customerStatus" name="status" class="form-control">
                        <option value="active" ${customerData && customerData.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${customerData && customerData.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                    </div>
                    <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Customer</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Customer' : 'New Customer', formContent, 'normal', 'customerFormModal');
        
                    setTimeout(() => {
            const form = document.getElementById('customerForm');
                        if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveCustomer(customerId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveCustomer = async function(customerId = null) {
        try {
            const name = document.getElementById('customerName')?.value.trim();
            const email = document.getElementById('customerEmail')?.value.trim();
            const phone = document.getElementById('customerPhone')?.value.trim();
            const address = document.getElementById('customerAddress')?.value.trim();
            const taxId = document.getElementById('customerTaxId')?.value.trim();
            const status = document.getElementById('customerStatus')?.value || 'active';
            
            if (!name) {
                this.showToast('Customer name is required', 'error');
                return;
            }
            
            const method = customerId ? 'PUT' : 'POST';
            const url = customerId ? `${this.apiBase}/customers.php?id=${customerId}` : `${this.apiBase}/customers.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, phone, address, tax_id: taxId, status })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(customerId ? 'Customer updated successfully' : 'Customer created successfully', 'success');
                const modal = document.getElementById('customerFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                // Refresh customers list if modal is open
                if (document.getElementById('customersModal')) {
                    // Would reload customers list here
                }
            } else {
                this.showToast(data.message || 'Failed to save customer', 'error');
            }
        } catch (error) {
            this.showToast('Error saving customer: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteCustomer = async function(customerId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Customer',
            'Are you sure you want to delete this customer? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/customers.php?id=${customerId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Customer deleted successfully', 'success');
            } else {
                    this.showToast(data.message || 'Failed to delete customer', 'error');
            }
        } catch (error) {
                this.showToast('Error deleting customer: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.openVendorForm = async function(vendorId = null) {
        const isEdit = vendorId !== null;
        let vendorData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/vendors.php?id=${vendorId}`);
            const data = await response.json();
                if (data.success && data.vendor) {
                    vendorData = data.vendor;
                } else {
                    this.showToast(data.message || 'Failed to load vendor', 'error');
                    return;
            }
        } catch (error) {
                this.showToast('Error loading vendor: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = `
            <form id="vendorForm">
                <div class="accounting-modal-form-group">
                    <label for="vendorName">Vendor Name <span class="required">*</span></label>
                    <input type="text" id="vendorName" name="name" class="form-control" required value="${vendorData ? this.escapeHtml(vendorData.name || '') : ''}" placeholder="Enter vendor name">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="vendorEmail">Email</label>
                    <input type="email" id="vendorEmail" name="email" class="form-control" value="${vendorData ? this.escapeHtml(vendorData.email || '') : ''}" placeholder="vendor@example.com">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="vendorPhone">Phone</label>
                    <input type="tel" id="vendorPhone" name="phone" class="form-control" value="${vendorData ? this.escapeHtml(vendorData.phone || '') : ''}" placeholder="+966 50 123 4567">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="vendorAddress">Address</label>
                    <textarea id="vendorAddress" name="address" class="form-control" rows="3" placeholder="Enter address">${vendorData ? this.escapeHtml(vendorData.address || '') : ''}</textarea>
                </div>
                <div class="accounting-modal-form-group">
                    <label for="vendorTaxId">Tax ID</label>
                    <input type="text" id="vendorTaxId" name="tax_id" class="form-control" value="${vendorData ? this.escapeHtml(vendorData.tax_id || '') : ''}" placeholder="Tax identification number">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="vendorStatus">Status</label>
                    <select id="vendorStatus" name="status" class="form-control">
                        <option value="active" ${vendorData && vendorData.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${vendorData && vendorData.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Vendor</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Vendor' : 'New Vendor', formContent, 'normal', 'vendorFormModal');
        
        setTimeout(() => {
            const form = document.getElementById('vendorForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                            e.preventDefault();
                    await this.saveVendor(vendorId);
                });
                                    }
                                }, 100);
                            }

ProfessionalAccounting.prototype.saveVendor = async function(vendorId = null) {
        try {
            const name = document.getElementById('vendorName')?.value.trim();
            const email = document.getElementById('vendorEmail')?.value.trim();
            const phone = document.getElementById('vendorPhone')?.value.trim();
            const address = document.getElementById('vendorAddress')?.value.trim();
            const taxId = document.getElementById('vendorTaxId')?.value.trim();
            const status = document.getElementById('vendorStatus')?.value || 'active';
            
            if (!name) {
                this.showToast('Vendor name is required', 'error');
                        return;
                    }
                    
            const method = vendorId ? 'PUT' : 'POST';
            const url = vendorId ? `${this.apiBase}/vendors.php?id=${vendorId}` : `${this.apiBase}/vendors.php`;
            
            const response = await fetch(url, {
                method: method,
                            headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, phone, address, tax_id: taxId, status })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(vendorId ? 'Vendor updated successfully' : 'Vendor created successfully', 'success');
                const modal = document.getElementById('vendorFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                        } else {
                this.showToast(data.message || 'Failed to save vendor', 'error');
                        }
                    } catch (error) {
            this.showToast('Error saving vendor: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteVendor = async function(vendorId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Vendor',
            'Are you sure you want to delete this vendor? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/vendors.php?id=${vendorId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Vendor deleted successfully', 'success');
                        } else {
                    this.showToast(data.message || 'Failed to delete vendor', 'error');
                        }
                    } catch (error) {
                this.showToast('Error deleting vendor: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.openPeriodForm = function(periodId = null) {
        const isEdit = periodId !== null;
        const formContent = `
            <form id="periodForm">
            <div class="accounting-modal-form-group">
                    <label for="periodName">Period Name <span class="required">*</span></label>
                    <input type="text" id="periodName" name="period_name" class="form-control" required placeholder="e.g., 2024 Fiscal Year">
            </div>
            <div class="accounting-modal-form-group">
                    <label for="periodStartDate">Start Date <span class="required">*</span></label>
                    <input type="text" id="periodStartDate" name="start_date" class="form-control date-input" required placeholder="MM/DD/YYYY">
            </div>
            <div class="accounting-modal-form-group">
                    <label for="periodEndDate">End Date <span class="required">*</span></label>
                    <input type="text" id="periodEndDate" name="end_date" class="form-control date-input" required placeholder="MM/DD/YYYY">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="periodStatus">Status</label>
                    <select id="periodStatus" name="status" class="form-control">
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
            </div>
            <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Period</button>
                <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
            </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Period' : 'New Period', formContent, 'normal', 'periodFormModal');
        
        setTimeout(() => {
            const form = document.getElementById('periodForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.savePeriod(periodId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.savePeriod = async function(periodId = null) {
        try {
            const periodName = document.getElementById('periodName')?.value.trim();
            const startDate = document.getElementById('periodStartDate')?.value;
            const endDate = document.getElementById('periodEndDate')?.value;
            const status = document.getElementById('periodStatus')?.value || 'open';
            
            if (!periodName || !startDate || !endDate) {
                this.showToast('All fields are required', 'error');
                        return;
                    }
                    
            if (new Date(startDate) > new Date(endDate)) {
                this.showToast('Start date must be before end date', 'error');
                return;
            }
            
            const method = periodId ? 'PUT' : 'POST';
            const url = periodId ? `${this.apiBase}/periods.php?id=${periodId}` : `${this.apiBase}/periods.php`;
            
            const response = await fetch(url, {
                method: method,
                            headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ period_name: periodName, start_date: startDate, end_date: endDate, status })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(periodId ? 'Period updated successfully' : 'Period created successfully', 'success');
                const modal = document.getElementById('periodFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                await this.loadPeriods();
                        } else {
                this.showToast(data.message || 'Failed to save period', 'error');
                        }
                    } catch (error) {
            this.showToast('Error saving period: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.openTaxSettingForm = function(taxSettingId = null) {
        const isEdit = taxSettingId !== null;
        const formContent = `
            <form id="taxSettingForm">
                <div class="accounting-modal-form-group">
                    <label for="taxName">Tax Name <span class="required">*</span></label>
                    <input type="text" id="taxName" name="tax_name" class="form-control" required placeholder="e.g., VAT, Sales Tax">
                            </div>
                <div class="accounting-modal-form-group">
                    <label for="taxType">Tax Type <span class="required">*</span></label>
                    <select id="taxType" name="tax_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="sales">Sales Tax</option>
                        <option value="purchase">Purchase Tax</option>
                        <option value="vat">VAT</option>
                                </select>
                            </div>
                <div class="accounting-modal-form-group">
                    <label for="taxRate">Tax Rate (%) <span class="required">*</span></label>
                    <input type="number" id="taxRate" name="tax_rate" class="form-control" required step="0.01" min="0" max="100" placeholder="15.00">
                            </div>
                <div class="accounting-modal-form-group">
                    <label for="taxStatus">Status</label>
                    <select id="taxStatus" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                                </select>
                            </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Tax Setting</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Tax Setting' : 'New Tax Setting', formContent, 'normal', 'taxSettingFormModal');
        
        setTimeout(() => {
            const form = document.getElementById('taxSettingForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveTaxSetting(taxSettingId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveTaxSetting = async function(taxSettingId = null) {
        try {
            const taxName = document.getElementById('taxName')?.value.trim();
            const taxType = document.getElementById('taxType')?.value;
            const taxRate = parseFloat(document.getElementById('taxRate')?.value || 0);
            const status = document.getElementById('taxStatus')?.value || 'active';
            
            if (!taxName || !taxType) {
                this.showToast('Tax name and type are required', 'error');
                return;
            }
            
            if (isNaN(taxRate) || taxRate < 0 || taxRate > 100) {
                this.showToast('Tax rate must be between 0 and 100', 'error');
                return;
            }
            
            const method = taxSettingId ? 'PUT' : 'POST';
            const url = taxSettingId ? `${this.apiBase}/tax-settings.php?id=${taxSettingId}` : `${this.apiBase}/tax-settings.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tax_name: taxName, tax_type: taxType, tax_rate: taxRate, status })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(taxSettingId ? 'Tax setting updated successfully' : 'Tax setting created successfully', 'success');
                const modal = document.getElementById('taxSettingFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
                await this.loadTaxSettings();
            } else {
                this.showToast(data.message || 'Failed to save tax setting', 'error');
            }
        } catch (error) {
            this.showToast('Error saving tax setting: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.openBudgetForm = async function(budgetId = null) {
        const isEdit = budgetId !== null;
        let budgetData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/budgets.php?id=${budgetId}`);
                const data = await response.json();
                if (data.success && data.budget) {
                    budgetData = data.budget;
                    } else {
                    this.showToast(data.message || 'Failed to load budget', 'error');
                    return;
                }
            } catch (error) {
                this.showToast('Error loading budget: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = `
            <form id="budgetForm">
                <div class="accounting-modal-form-group">
                    <label for="budgetName">Budget Name <span class="required">*</span></label>
                    <input type="text" id="budgetName" name="budget_name" class="form-control" required value="${budgetData ? this.escapeHtml(budgetData.budget_name || budgetData.name || '') : ''}" placeholder="e.g., 2024 Annual Budget">
                                </div>
                <div class="accounting-modal-form-group">
                    <label for="budgetPeriod">Period <span class="required">*</span></label>
                    <select id="budgetPeriod" name="period_id" class="form-control" required>
                        <option value="">Select Period</option>
                    </select>
                </div>
                <div class="accounting-modal-form-group">
                    <label for="budgetStartDate">Start Date <span class="required">*</span></label>
                    <input type="text" id="budgetStartDate" name="start_date" class="form-control date-input" required value="${budgetData && budgetData.start_date ? this.formatDateForInput(budgetData.start_date) : ''}" placeholder="MM/DD/YYYY">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="budgetEndDate">End Date <span class="required">*</span></label>
                    <input type="text" id="budgetEndDate" name="end_date" class="form-control date-input" required value="${budgetData && budgetData.end_date ? this.formatDateForInput(budgetData.end_date) : ''}" placeholder="MM/DD/YYYY">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="budgetDescription">Description</label>
                    <textarea id="budgetDescription" name="description" class="form-control" rows="3" placeholder="Budget description">${budgetData ? this.escapeHtml(budgetData.description || '') : ''}</textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Budget</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Budget' : 'New Budget', formContent, 'normal', 'budgetFormModal');
        
        setTimeout(async () => {
            // Load periods for dropdown
            try {
                const response = await fetch(`${this.apiBase}/periods.php`);
            const data = await response.json();
                const periodSelect = document.getElementById('budgetPeriod');
                if (periodSelect && data.success && data.periods) {
                    periodSelect.innerHTML = '<option value="">Select Period</option>';
                    data.periods.forEach(period => {
                        const option = document.createElement('option');
                        option.value = period.id;
                        option.textContent = period.period_name || period.name;
                        if (budgetData && budgetData.period_id == period.id) option.selected = true;
                        periodSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading periods:', error);
            }
            
            const form = document.getElementById('budgetForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveBudget(budgetId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveBudget = async function(budgetId = null) {
        try {
            const budgetName = document.getElementById('budgetName')?.value.trim();
            const periodId = document.getElementById('budgetPeriod')?.value;
            const startDate = document.getElementById('budgetStartDate')?.value;
            const endDate = document.getElementById('budgetEndDate')?.value;
            const description = document.getElementById('budgetDescription')?.value.trim();
            
            if (!budgetName || !periodId || !startDate || !endDate) {
                this.showToast('All required fields must be filled', 'error');
                return;
            }
            
            const method = budgetId ? 'PUT' : 'POST';
            const url = budgetId ? `${this.apiBase}/budgets.php?id=${budgetId}` : `${this.apiBase}/budgets.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ budget_name: budgetName, period_id: parseInt(periodId), start_date: startDate, end_date: endDate, description })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(budgetId ? 'Budget updated successfully' : 'Budget created successfully', 'success');
                const modal = document.getElementById('budgetFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
            } else {
                this.showToast(data.message || 'Failed to save budget', 'error');
            }
        } catch (error) {
            this.showToast('Error saving budget: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteBudget = async function(budgetId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Budget',
            'Are you sure you want to delete this budget? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/budgets.php?id=${budgetId}`, {
                    method: 'DELETE'
                });
            const data = await response.json();
            if (data.success) {
                    this.showToast('Budget deleted successfully', 'success');
            } else {
                    this.showToast(data.message || 'Failed to delete budget', 'error');
            }
        } catch (error) {
                this.showToast('Error deleting budget: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.openFinancialClosingForm = async function(closingId = null) {
        const isEdit = closingId !== null;
        let closingData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/financial-closings.php?id=${closingId}`);
                const data = await response.json();
                if (data.success && data.closing) {
                    closingData = data.closing;
                } else {
                    this.showToast(data.message || 'Failed to load financial closing', 'error');
                    return;
                }
            } catch (error) {
                this.showToast('Error loading financial closing: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = `
            <form id="financialClosingForm">
                <div class="accounting-modal-form-group">
                    <label for="closingPeriod">Period <span class="required">*</span></label>
                    <select id="closingPeriod" name="period_id" class="form-control" required>
                        <option value="">Select Period</option>
                    </select>
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="closingDate">Closing Date <span class="required">*</span></label>
                    <input type="text" id="closingDate" name="closing_date" class="form-control date-input" required value="${closingData ? closingData.closing_date || '' : ''}" placeholder="MM/DD/YYYY">
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="closingNotes">Notes</label>
                    <textarea id="closingNotes" name="notes" class="form-control" rows="4" placeholder="Closing notes and remarks">${closingData ? this.escapeHtml(closingData.notes || '') : ''}</textarea>
                </div>
                ${isEdit && closingData && closingData.status === 'completed' ? `
                    <div class="accounting-modal-form-group">
                        <label>Status</label>
                        <input type="text" class="form-control" value="Completed" readonly>
                    </div>
                ` : ''}
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Closing</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Financial Closing' : 'New Financial Closing', formContent, 'normal', 'financialClosingFormModal');
        
        setTimeout(async () => {
            // Load periods for dropdown
            try {
                const response = await fetch(`${this.apiBase}/periods.php`);
                const data = await response.json();
                const periodSelect = document.getElementById('closingPeriod');
                if (periodSelect && data.success && data.periods) {
                    periodSelect.innerHTML = '<option value="">Select Period</option>';
                    data.periods.forEach(period => {
                        const option = document.createElement('option');
                        option.value = period.id;
                        option.textContent = period.period_name || period.name;
                        if (closingData && closingData.period_id == period.id) option.selected = true;
                        periodSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading periods:', error);
            }
            
            const form = document.getElementById('financialClosingForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.saveFinancialClosing(closingId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.saveFinancialClosing = async function(closingId = null) {
        try {
            const periodId = document.getElementById('closingPeriod')?.value;
            const closingDate = document.getElementById('closingDate')?.value;
            const notes = document.getElementById('closingNotes')?.value.trim();
            
            if (!periodId || !closingDate) {
                this.showToast('Period and closing date are required', 'error');
                return;
            }
            
            const method = closingId ? 'PUT' : 'POST';
            const url = closingId ? `${this.apiBase}/financial-closings.php?id=${closingId}` : `${this.apiBase}/financial-closings.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ period_id: parseInt(periodId), closing_date: closingDate, notes })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(closingId ? 'Financial closing updated successfully' : 'Financial closing created successfully', 'success');
                const modal = document.getElementById('financialClosingFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
            } else {
                this.showToast(data.message || 'Failed to save financial closing', 'error');
            }
        } catch (error) {
            this.showToast('Error saving financial closing: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.completeFinancialClosing = async function(closingId) {
        try {
            const response = await fetch(`${this.apiBase}/financial-closings.php?id=${closingId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 'completed' })
            });
            const data = await response.json();
            if (data.success) {
                this.showToast('Financial closing completed successfully', 'success');
            } else {
                this.showToast(data.message || 'Failed to complete closing', 'error');
            }
        } catch (error) {
            this.showToast('Error completing closing: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteFinancialClosing = async function(closingId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Financial Closing',
            'Are you sure you want to delete this financial closing? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/financial-closings.php?id=${closingId}`, {
                    method: 'DELETE'
                });
            const data = await response.json();
                if (data.success) {
                    this.showToast('Financial closing deleted successfully', 'success');
            } else {
                    this.showToast(data.message || 'Failed to delete closing', 'error');
            }
        } catch (error) {
                this.showToast('Error deleting closing: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.openPaymentAllocationForm = async function(allocationId = null) {
        const isEdit = allocationId !== null;
        let allocationData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/payment-allocations.php?id=${allocationId}`);
                const data = await response.json();
                if (data.success && data.allocation) {
                    allocationData = data.allocation;
                } else {
                    this.showToast(data.message || 'Failed to load payment allocation', 'error');
                    return;
                }
            } catch (error) {
                this.showToast('Error loading payment allocation: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = `
            <form id="paymentAllocationForm">
                <div class="accounting-modal-form-group">
                    <label for="allocationType">Allocation Type <span class="required">*</span></label>
                    <select id="allocationType" name="allocation_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="payment" ${allocationData && allocationData.allocation_type === 'payment' ? 'selected' : ''}>Payment</option>
                        <option value="receipt" ${allocationData && allocationData.allocation_type === 'receipt' ? 'selected' : ''}>Receipt</option>
                    </select>
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="allocationPaymentId">Payment/Receipt <span class="required">*</span></label>
                    <select id="allocationPaymentId" name="payment_id" class="form-control" required>
                        <option value="">Select Payment/Receipt</option>
                    </select>
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="allocationInvoiceId">Invoice/Bill <span class="required">*</span></label>
                    <select id="allocationInvoiceId" name="invoice_id" class="form-control" required>
                        <option value="">Select Invoice/Bill</option>
                        </select>
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="allocationAmount">Allocated Amount <span class="required">*</span></label>
                    <input type="number" id="allocationAmount" name="amount" class="form-control" required step="0.01" min="0" value="${allocationData ? allocationData.amount || '' : ''}" placeholder="0.00">
                </div>
                <div class="accounting-modal-form-group">
                    <label for="allocationDate">Allocation Date <span class="required">*</span></label>
                    <input type="text" id="allocationDate" name="allocation_date" class="form-control date-input" required value="${allocationData ? allocationData.allocation_date || '' : ''}" placeholder="MM/DD/YYYY">
                    </div>
                <div class="accounting-modal-form-group">
                    <label for="allocationNotes">Notes</label>
                    <textarea id="allocationNotes" name="notes" class="form-control" rows="3" placeholder="Allocation notes">${allocationData ? this.escapeHtml(allocationData.notes || '') : ''}</textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Allocation</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
            </form>
        `;
        
        this.showModal(isEdit ? 'Edit Payment Allocation' : 'New Payment Allocation', formContent, 'normal', 'paymentAllocationFormModal');
        
        setTimeout(async () => {
            // Load payments/receipts and invoices/bills for dropdowns
            // This would need to be implemented based on your API structure
            const form = document.getElementById('paymentAllocationForm');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.savePaymentAllocation(allocationId);
                });
            }
        }, 100);
    }

ProfessionalAccounting.prototype.savePaymentAllocation = async function(allocationId = null) {
        try {
            const allocationType = document.getElementById('allocationType')?.value;
            const paymentId = document.getElementById('allocationPaymentId')?.value;
            const invoiceId = document.getElementById('allocationInvoiceId')?.value;
            const amount = parseFloat(document.getElementById('allocationAmount')?.value || 0);
            const allocationDate = document.getElementById('allocationDate')?.value;
            const notes = document.getElementById('allocationNotes')?.value.trim();
            
            if (!allocationType || !paymentId || !invoiceId || !allocationDate) {
                this.showToast('All required fields must be filled', 'error');
                return;
            }
            
            if (amount <= 0) {
                this.showToast('Allocated amount must be greater than 0', 'error');
                return;
            }
            
            const method = allocationId ? 'PUT' : 'POST';
            const url = allocationId ? `${this.apiBase}/payment-allocations.php?id=${allocationId}` : `${this.apiBase}/payment-allocations.php`;
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    allocation_type: allocationType, 
                    payment_id: parseInt(paymentId), 
                    invoice_id: parseInt(invoiceId), 
                    amount: amount, 
                    allocation_date: allocationDate, 
                    notes 
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showToast(allocationId ? 'Payment allocation updated successfully' : 'Payment allocation created successfully', 'success');
                const modal = document.getElementById('paymentAllocationFormModal');
                if (modal) await this.closeModalWithConfirmation(modal);
            } else {
                this.showToast(data.message || 'Failed to save payment allocation', 'error');
            }
        } catch (error) {
            this.showToast('Error saving payment allocation: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deletePaymentAllocation = async function(allocationId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Payment Allocation',
            'Are you sure you want to delete this payment allocation? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/payment-allocations.php?id=${allocationId}`, {
                method: 'DELETE'
            });
            const data = await response.json();
                if (data.success) {
                    this.showToast('Payment allocation deleted successfully', 'success');
            } else {
                    this.showToast(data.message || 'Failed to delete allocation', 'error');
            }
        } catch (error) {
                this.showToast('Error deleting allocation: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.exportJournalEntries = async function() {
        try {
            this.showToast('Exporting journal entries...', 'info');
            const response = await fetch(`${this.apiBase}/journal-entries.php?export=1&format=csv`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `journal_entries_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.showToast('Journal entries exported successfully', 'success');
            } else {
                const data = await response.json();
                this.showToast(data.message || 'Failed to export journal entries', 'error');
            }
        } catch (error) {
            this.showToast('Error exporting journal entries: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportInvoices = async function() {
        try {
            this.showToast('Exporting invoices...', 'info');
            const response = await fetch(`${this.apiBase}/invoices.php?export=1&format=csv`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `invoices_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.showToast('Invoices exported successfully', 'success');
            } else {
                const data = await response.json();
                this.showToast(data.message || 'Failed to export invoices', 'error');
            }
        } catch (error) {
            var errMsg = (error && typeof error.message === 'string') ? error.message : 'Unknown error';
            this.showToast('Error exporting invoices: ' + errMsg, 'error');
        }
    }

ProfessionalAccounting.prototype.exportBills = async function() {
        try {
            this.showToast('Exporting bills...', 'info');
            const response = await fetch(`${this.apiBase}/bills.php?export=1&format=csv`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `bills_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.showToast('Bills exported successfully', 'success');
            } else {
                const data = await response.json();
                this.showToast(data.message || 'Failed to export bills', 'error');
            }
        } catch (error) {
            var errMsg = (error && typeof error.message === 'string') ? error.message : 'Unknown error';
            this.showToast('Error exporting bills: ' + errMsg, 'error');
        }
    }

ProfessionalAccounting.prototype.exportVouchers = async function() {
        try {
            this.showToast('Exporting vouchers...', 'info');
            const [payResp, recResp] = await Promise.all([
                fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=payment`, { credentials: 'include' }),
                fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=receipt`, { credentials: 'include' })
            ]);
            const payData = await payResp.json().catch(() => ({ success: false }));
            const recData = await recResp.json().catch(() => ({ success: false }));
            const rows = [];
            const headers = ['Voucher #', 'Date', 'Type', 'Amount', 'Currency', 'Status', 'Reference', 'Description'];
            const escapeCsv = function(v) {
                if (v == null) return '';
                var s = String(v);
                if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            };
            if (payData.success && Array.isArray(payData.vouchers)) {
                payData.vouchers.forEach(function(v) {
                    rows.push([
                        v.voucher_number || v.reference_number || v.id || '',
                        v.voucher_date || v.payment_date || '',
                        'Payment',
                        v.amount != null ? v.amount : '',
                        v.currency || 'SAR',
                        v.status || '',
                        v.reference_number || '',
                        (v.description || v.notes || '').replace(/\r/g, ' ').replace(/\n/g, ' ')
                    ]);
                });
            }
            if (recData.success && Array.isArray(recData.vouchers)) {
                recData.vouchers.forEach(function(v) {
                    rows.push([
                        v.voucher_number || v.reference_number || v.id || '',
                        v.voucher_date || v.payment_date || '',
                        'Receipt',
                        v.amount != null ? v.amount : '',
                        v.currency || 'SAR',
                        v.status || '',
                        v.reference_number || '',
                        (v.description || v.notes || '').replace(/\r/g, ' ').replace(/\n/g, ' ')
                    ]);
                });
            }
            var csv = headers.map(escapeCsv).join(',') + '\r\n' +
                rows.map(function(r) { return r.map(escapeCsv).join(','); }).join('\r\n');
            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'vouchers_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.showToast(rows.length > 0 ? 'Vouchers exported successfully' : 'No vouchers to export', rows.length > 0 ? 'success' : 'info');
        } catch (error) {
            var msg = (error && typeof error.message === 'string') ? error.message : 'Unknown error';
            this.showToast('Error exporting vouchers: ' + msg, 'error');
        }
    }

ProfessionalAccounting.prototype.exportBankTransactions = async function() {
        try {
            this.showToast('Exporting bank transactions...', 'info');
            const response = await fetch(`${this.apiBase}/bank-transactions.php?export=1&format=csv`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `bank_transactions_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.showToast('Bank transactions exported successfully', 'success');
            } else {
                const data = await response.json();
                this.showToast(data.message || 'Failed to export bank transactions', 'error');
            }
        } catch (error) {
            var errMsg = (error && typeof error.message === 'string') ? error.message : 'Unknown error';
            this.showToast('Error exporting bank transactions: ' + errMsg, 'error');
        }
    }

ProfessionalAccounting.prototype.showNewFollowupForm = function() {
        const form = document.getElementById('newFollowupForm');
        if (form) {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

ProfessionalAccounting.prototype.closeNewFollowupForm = function() {
        const form = document.getElementById('newFollowupForm');
        if (form) {
            form.style.display = 'none';
        }
    }

ProfessionalAccounting.prototype.showNewMessageForm = function() {
        const form = document.getElementById('newMessageForm');
        if (form) {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

ProfessionalAccounting.prototype.closeNewMessageForm = function() {
        const form = document.getElementById('newMessageForm');
        if (form) {
            form.style.display = 'none';
        }
    }

ProfessionalAccounting.prototype.markMessageRead = async function(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_read: 1 })
            });
            const data = await response.json();
            if (data.success) {
                await this.loadMessages();
            }
        } catch (error) {
            console.error('Error marking message as read:', error);
        }
    }

ProfessionalAccounting.prototype.markAllMessagesRead = async function() {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?action=mark_all_read`, {
                method: 'PUT'
            });
            const data = await response.json();
            if (data.success) {
                this.showToast('All messages marked as read', 'success');
                await this.loadMessages();
            }
        } catch (error) {
            this.showToast('Error marking messages as read: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.viewMessage = async function(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`);
            const data = await response.json();
            if (data.success && data.message) {
                const msg = data.message;
                const content = `
                    <div class="message-details">
                        <h3>${this.escapeHtml(msg.subject || 'Message')}</h3>
                        <div class="detail-row">
                            <label>From:</label>
                            <span>${this.escapeHtml(msg.from_name || 'System')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Date:</label>
                            <span>${msg.created_at || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Message:</label>
                            <div>${this.escapeHtml(msg.message || 'N/A')}</div>
                        </div>
                    </div>
                `;
                this.showModal('View Message', content);
                // Mark as read
                await this.markMessageRead(messageId);
            } else {
                this.showToast(data.message || 'Failed to load message', 'error');
            }
        } catch (error) {
            this.showToast('Error loading message: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.printMessage = async function(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}&format=print`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing message: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.duplicateMessage = async function(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}&action=duplicate`, {
                method: 'POST'
            });
            const data = await response.json();
            if (data.success) {
                this.showToast('Message duplicated successfully', 'success');
                await this.loadMessages();
        } else {
                this.showToast(data.message || 'Failed to duplicate message', 'error');
            }
        } catch (error) {
            this.showToast('Error duplicating message: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportMessage = async function(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`);
            const data = await response.json();
            if (data.success && data.message) {
                const jsonData = JSON.stringify(data.message, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `message-${messageId}-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            URL.revokeObjectURL(url);
                this.showToast('Message exported successfully', 'success');
            } else {
                this.showToast('Error: ' + (data.message || 'Failed to load message'), 'error');
            }
        } catch (error) {
            this.showToast('Error exporting message: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteMessage = async function(messageId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Message',
            'Are you sure you want to delete this message? This action cannot be undone.',
            'Delete',
            'Cancel',
            'danger'
        );
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Message deleted successfully', 'success');
                    await this.loadMessages();
                } else {
                    this.showToast(data.message || 'Failed to delete message', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting message: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.setupFollowupMessages = function() {
        // Setup event listeners for followup/message forms
        // This is typically called during initialization
    }

ProfessionalAccounting.prototype.updateBulkActionsFollowups = function() {
        const checkboxes = document.querySelectorAll('#followupsList input[type="checkbox"]:checked');
        const count = checkboxes.length;
        const bulkBar = document.getElementById('followupsBulkActions');
        const countEl = document.getElementById('bulkSelectedCountFollowups');
        
        if (countEl) {
            countEl.textContent = `${count} selected`;
        }
        
        if (bulkBar) {
            bulkBar.classList.toggle('bulk-actions-bar-hidden', count === 0);
            bulkBar.classList.toggle('show', count > 0);
        }
    }

ProfessionalAccounting.prototype.bulkDeleteFollowups = async function() {
        const checkboxes = document.querySelectorAll('#followupsList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one follow-up', 'warning');
                return;
            }

        const confirmed = await this.showConfirmDialog(
            'Delete Follow-ups',
            `Are you sure you want to delete ${ids.length} follow-up(s)? This action cannot be undone.`,
            'Delete',
            'Cancel',
            'danger'
        );
        
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/followups.php`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids })
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast(`${ids.length} follow-up(s) deleted successfully`, 'success');
                    await this.loadFollowups();
                } else {
                    this.showToast(data.message || 'Failed to delete follow-ups', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting follow-ups: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.bulkExportFollowups = async function() {
        const checkboxes = document.querySelectorAll('#followupsList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one follow-up', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/followups.php?ids=${ids.join(',')}&format=export`);
            if (response.ok) {
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `followups-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast(`${ids.length} follow-up(s) exported successfully`, 'success');
            } else {
                this.showToast('Failed to export follow-ups', 'error');
            }
        } catch (error) {
            this.showToast('Error exporting follow-ups: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.bulkPrintFollowups = async function() {
        const checkboxes = document.querySelectorAll('#followupsList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one follow-up', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/followups.php?ids=${ids.join(',')}&format=print`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing follow-ups: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.updateBulkActionsMessages = function() {
        const checkboxes = document.querySelectorAll('#messagesList input[type="checkbox"]:checked');
        const count = checkboxes.length;
        const bulkBar = document.getElementById('messagesBulkActions');
        const countEl = document.getElementById('bulkSelectedCountMessages');
        
        if (countEl) {
            countEl.textContent = `${count} selected`;
        }
        
        if (bulkBar) {
            bulkBar.classList.toggle('bulk-actions-bar-hidden', count === 0);
            bulkBar.classList.toggle('show', count > 0);
        }
    }

ProfessionalAccounting.prototype.bulkDeleteMessages = async function() {
        const checkboxes = document.querySelectorAll('#messagesList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one message', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Delete Messages',
            `Are you sure you want to delete ${ids.length} message(s)? This action cannot be undone.`,
            'Delete',
            'Cancel',
            'danger'
        );
        
        if (confirmed) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php`, {
                    method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids })
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast(`${ids.length} message(s) deleted successfully`, 'success');
                    await this.loadMessages();
                        } else {
                    this.showToast(data.message || 'Failed to delete messages', 'error');
                        }
                    } catch (error) {
                this.showToast('Error deleting messages: ' + error.message, 'error');
            }
        }
    }

ProfessionalAccounting.prototype.bulkExportMessages = async function() {
        const checkboxes = document.querySelectorAll('#messagesList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one message', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/messages.php?ids=${ids.join(',')}&format=export`);
            if (response.ok) {
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `messages-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast(`${ids.length} message(s) exported successfully`, 'success');
                } else {
                this.showToast('Failed to export messages', 'error');
            }
        } catch (error) {
            this.showToast('Error exporting messages: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.bulkPrintMessages = async function() {
        const checkboxes = document.querySelectorAll('#messagesList input[type="checkbox"]:checked');
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        
        if (ids.length === 0) {
            this.showToast('Please select at least one message', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/messages.php?ids=${ids.join(',')}&format=print`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const printWindow = window.open(url, '_blank');
                if (printWindow) {
                    printWindow.onload = () => printWindow.print();
                }
            } else {
                this.showToast('Failed to generate print view', 'error');
            }
        } catch (error) {
            this.showToast('Error printing messages: ' + error.message, 'error');
        }
    }

    // Additional Missing Voucher Methods
    
ProfessionalAccounting.prototype.duplicateVoucher = async function(voucherId, voucherType) {
        try {
            const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}&action=duplicate`, {
                method: 'POST'
            });
            const data = await response.json();
            if (data.success) {
                this.showToast(`${voucherType === 'payment' ? 'Payment' : 'Receipt'} voucher duplicated successfully`, 'success');
                // Refresh vouchers list if modal is open
                if (document.getElementById('vouchersModal')?.classList.contains('accounting-modal-visible')) {
                    // Trigger refresh if there's a refresh handler
                    const refreshBtn = document.querySelector('[data-action="refresh-vouchers"]');
                    if (refreshBtn) refreshBtn.click();
                }
            } else {
                this.showToast(data.message || 'Failed to duplicate voucher', 'error');
            }
        } catch (error) {
            this.showToast('Error duplicating voucher: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportSingleVoucher = async function(voucherId, voucherType) {
        try {
            const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}`);
            const data = await response.json();
            if (data.success && data.voucher) {
                const jsonData = JSON.stringify(data.voucher, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const ref = (data.voucher.voucher_number || data.voucher.receipt_number || voucherId);
                a.download = `${voucherType}-voucher-${ref}-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast('Voucher exported successfully', 'success');
            } else {
                this.showToast(data.message || 'Failed to load voucher', 'error');
            }
        } catch (error) {
            this.showToast('Error exporting voucher: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteVoucher = async function(voucherId, voucherType) {
        try {
            const url = `${this.apiBase}/receipt-payment-vouchers.php?id=${voucherId}&type=${voucherType}`;
            const response = await fetch(url, {
                method: 'DELETE',
                credentials: 'include'
            });
            const data = await response.json();
            if (data.success) {
                this.showToast(`${voucherType === 'payment' ? 'Payment' : 'Receipt'} voucher deleted successfully`, 'success');
                // Refresh vouchers list if modal is open
                if (document.getElementById('vouchersModal')?.classList.contains('accounting-modal-visible')) {
                    const refreshBtn = document.querySelector('[data-action="refresh-vouchers"]');
                    if (refreshBtn) refreshBtn.click();
                }
                // Refresh Support Payments if that modal is open
                if (document.getElementById('supportPaymentsModal')?.classList.contains('accounting-modal-visible') && voucherType === 'payment') {
                    this.loadSupportPayments();
                }
            } else {
                this.showToast(data.message || 'Failed to delete voucher', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting voucher: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportTransactions = function() {
        try {
            const tbody = document.getElementById('entityTransactionsBody');
            if (!tbody) {
                this.showToast('No transactions to export', 'warning');
                return;
            }

            const rows = tbody.querySelectorAll('tr[data-id]');
            if (rows.length === 0) {
                this.showToast('No transactions to export', 'warning');
                return;
            }

            const transactions = Array.from(rows).map(row => {
                const id = row.getAttribute('data-id');
                const cells = row.querySelectorAll('td');
                return {
                    id: id,
                    date: cells[1]?.textContent?.trim() || '',
                    entity: cells[2]?.textContent?.trim() || '',
                    type: cells[3]?.textContent?.trim() || '',
                    reference: cells[4]?.textContent?.trim() || '',
                    description: cells[5]?.textContent?.trim() || '',
                    debit: cells[6]?.textContent?.trim() || '',
                    credit: cells[7]?.textContent?.trim() || '',
                    status: cells[8]?.textContent?.trim() || ''
                };
            });

            const jsonData = JSON.stringify(transactions, null, 2);
            const blob = new Blob([jsonData], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `transactions-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            this.showToast(`${transactions.length} transaction(s) exported successfully`, 'success');
        } catch (error) {
            this.showToast('Error exporting transactions: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.printTransactions = async function() {
        try {
            const tbody = document.getElementById('entityTransactionsBody');
            if (!tbody) {
                this.showToast('No transactions to print', 'warning');
                return;
            }

            const rows = tbody.querySelectorAll('tr[data-id]');
            if (rows.length === 0) {
                this.showToast('No transactions to print', 'warning');
                return;
            }

            // Create print-friendly HTML
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Transactions Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <h1>Transactions Report</h1>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Entity</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                html += '<tr>';
                for (let i = 0; i < 9; i++) {
                    html += `<td>${cells[i]?.textContent?.trim() || ''}</td>`;
                }
                html += '</tr>';
            });

            html += `
                        </tbody>
                    </table>
                </body>
                </html>
            `;

            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(html);
                printWindow.document.close();
                printWindow.onload = () => printWindow.print();
            }
        } catch (error) {
            this.showToast('Error printing transactions: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.exportCurrentReport = function(format = 'csv') {
        if (!this.currentReportType || !this.currentReportData) {
            this.showToast('No report to export', 'warning');
            return;
        }

        try {
            const reportName = this.getReportName(this.currentReportType);
            const dateStr = new Date().toISOString().split('T')[0];
            
            if (format === 'csv') {
                this.exportReportToCSV(reportName, dateStr);
            } else if (format === 'excel') {
                this.exportReportToExcel(reportName, dateStr);
            } else if (format === 'json') {
                const reportData = {
                    type: this.currentReportType,
                    data: this.currentReportData,
                    generated: new Date().toISOString()
                };
                const jsonData = JSON.stringify(reportData, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `report-${this.currentReportType}-${dateStr}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            
            this.showToast(`Report exported as ${format.toUpperCase()} successfully`, 'success');
        } catch (error) {
            this.showToast('Error exporting report: ' + error.message, 'error');
        }
    }
    
ProfessionalAccounting.prototype.exportReportToCSV = function(reportName, dateStr) {
        let csvContent = '';
        
        // Add report header
        csvContent += `${reportName} Report\n`;
        csvContent += `Generated: ${dateStr}\n\n`;
        
        // Get data based on report type
        const reportData = this.currentReportData;
        
        if (this.currentReportType === 'trial-balance' || this.currentReportType === 'general-ledger') {
            const accounts = reportData?.accounts || [];
            if (accounts.length > 0) {
                // CSV Headers
                if (this.currentReportType === 'trial-balance') {
                    csvContent += 'Account Code,Account Name,Debit,Credit,Balance\n';
                    accounts.forEach(account => {
                        csvContent += `"${account.account_code || ''}","${account.account_name || ''}",${account.debit || 0},${account.credit || 0},${account.balance || 0}\n`;
                    });
                } else {
                    // General Ledger - more complex structure
                    csvContent += 'Account Code,Account Name,Date,Description,Reference,Type,Debit,Credit,Balance\n';
                    accounts.forEach(account => {
                        const transactions = account.transactions || [];
                        if (transactions.length > 0) {
                            transactions.forEach(txn => {
                                csvContent += `"${account.account_code || ''}","${account.account_name || ''}","${txn.transaction_date || ''}","${(txn.description || '').replace(/"/g, '""')}","${txn.reference_number || ''}","${txn.transaction_type || ''}",${txn.debit_amount || 0},${txn.credit_amount || 0},${(txn.debit_amount || 0) - (txn.credit_amount || 0)}\n`;
                            });
                        } else {
                            csvContent += `"${account.account_code || ''}","${account.account_name || ''}","","No transactions","","",0,0,0\n`;
                        }
                    });
                }
            }
        } else if (this.currentReportType === 'income-statement') {
            csvContent += 'Period,Revenue,Expenses,Net Income\n';
            const revenue = reportData?.revenue || [];
            const expenses = reportData?.expenses || [];
            const periods = new Set([...revenue.map(r => r.month), ...expenses.map(e => e.month)]);
            periods.forEach(period => {
                const rev = revenue.find(r => r.month === period)?.total_revenue || 0;
                const exp = expenses.find(e => e.month === period)?.total_expenses || 0;
                csvContent += `"${period}",${rev},${exp},${rev - exp}\n`;
            });
        } else if (this.currentReportType === 'balance-sheet') {
            csvContent += 'Account Code,Account Name,Type,Balance\n';
            const assets = reportData?.assets || [];
            const liabilities = reportData?.liabilities || [];
            const equity = reportData?.equity || [];
            [...assets, ...liabilities, ...equity].forEach(item => {
                const type = assets.includes(item) ? 'Asset' : (liabilities.includes(item) ? 'Liability' : 'Equity');
                csvContent += `"${item.account_code || ''}","${item.account_name || ''}","${type}",${item.balance || 0}\n`;
            });
        } else if (this.currentReportType === 'cash-flow') {
            csvContent += 'Period,Cash In,Cash Out,Net Flow\n';
            const operating = reportData?.operating || [];
            operating.forEach(item => {
                csvContent += `"${item.month || item.period || ''}",${item.cash_in || 0},${item.cash_out || 0},${(item.cash_in || 0) - (item.cash_out || 0)}\n`;
            });
        } else if (this.currentReportType === 'aged-receivables' || this.currentReportType === 'ages-debt-receivable' || this.currentReportType === 'ages-credit-receivable') {
            csvContent += 'Invoice Number,Customer,Invoice Date,Due Date,Total Amount,Paid,Balance,Days Overdue\n';
            const receivables = reportData?.receivables || [];
            receivables.forEach(item => {
                csvContent += `"${item.invoice_number || ''}","${(item.customer_name || '').replace(/"/g, '""')}","${item.invoice_date || ''}","${item.due_date || ''}",${item.total_amount || 0},${item.paid_amount || 0},${item.balance || 0},${item.days_overdue || 0}\n`;
            });
        } else if (this.currentReportType === 'aged-payables') {
            csvContent += 'Bill Number,Vendor,Bill Date,Due Date,Total Amount,Paid,Balance,Days Overdue\n';
            const payables = reportData?.payables || [];
            payables.forEach(item => {
                csvContent += `"${item.bill_number || ''}","${(item.vendor_name || '').replace(/"/g, '""')}","${item.bill_date || ''}","${item.due_date || ''}",${item.total_amount || 0},${item.paid_amount || 0},${item.balance || 0},${item.days_overdue || 0}\n`;
            });
        } else if (this.currentReportType === 'cash-book' || this.currentReportType === 'bank-book') {
            csvContent += 'Date,Description,Reference,Type,Debit,Credit,Balance\n';
            const transactions = reportData?.transactions || [];
            transactions.forEach(item => {
                csvContent += `"${item.transaction_date || ''}","${(item.description || '').replace(/"/g, '""')}","${item.reference_number || ''}","${item.transaction_type || ''}",${item.debit_amount || 0},${item.credit_amount || 0},${item.balance || 0}\n`;
            });
        } else if (this.currentReportType === 'expense-statement') {
            csvContent += 'Date,Category,Description,Amount\n';
            const expenses = reportData?.expenses || [];
            expenses.forEach(item => {
                csvContent += `"${item.date || ''}","${item.category || ''}","${(item.description || '').replace(/"/g, '""')}",${item.amount || 0}\n`;
            });
        } else if (this.currentReportType === 'chart-of-accounts-report') {
            csvContent += 'Account Code,Account Name,Category,Balance\n';
            const grouped = reportData?.grouped || {};
            Object.keys(grouped).forEach(category => {
                const accounts = grouped[category] || [];
                accounts.forEach(account => {
                    csvContent += `"${account.account_code || ''}","${(account.account_name || '').replace(/"/g, '""')}","${category}",${account.balance || 0}\n`;
                });
            });
        } else if (this.currentReportType === 'fixed-assets') {
            csvContent += 'Account Code,Account Name,Purchase Date,Cost,Depreciation,Current Value\n';
            const assets = reportData?.assets || [];
            assets.forEach(item => {
                csvContent += `"${item.account_code || ''}","${(item.account_name || '').replace(/"/g, '""')}","${item.purchase_date || ''}",${item.cost || 0},${item.depreciation || 0},${item.current_value || item.balance || 0}\n`;
            });
        } else if (this.currentReportType === 'customer-debits') {
            csvContent += 'Customer,Total Invoiced,Total Paid,Balance\n';
            const customers = reportData?.customers || reportData?.debits || [];
            customers.forEach(item => {
                csvContent += `"${(item.customer_name || item.name || '').replace(/"/g, '""')}",${item.total_invoiced || item.total_debit || 0},${item.total_paid || 0},${item.balance || 0}\n`;
            });
        } else if (this.currentReportType === 'value-added' || this.currentReportType === 'entries-by-year' || this.currentReportType === 'statistical-position' || this.currentReportType === 'financial-performance' || this.currentReportType === 'comparative-report' || this.currentReportType === 'changes-equity') {
            // Generic export for reports with data array
            const allData = reportData?.data || reportData?.equity_changes || reportData?.performance_data || [];
            if (allData.length > 0) {
                // Use first row keys as headers
                const headers = Object.keys(allData[0]);
                csvContent += headers.map(h => h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(',') + '\n';
                allData.forEach(row => {
                    csvContent += headers.map(h => {
                        const val = row[h];
                        if (typeof val === 'string') {
                            return `"${val.replace(/"/g, '""')}"`;
                        }
                        return val !== null && val !== undefined ? val : '';
                    }).join(',') + '\n';
                });
            }
        } else {
            // Fallback: export any available data structure
            if (reportData?.data && Array.isArray(reportData.data) && reportData.data.length > 0) {
                const headers = Object.keys(reportData.data[0]);
                csvContent += headers.map(h => h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(',') + '\n';
                reportData.data.forEach(row => {
                    csvContent += headers.map(h => {
                        const val = row[h];
                        if (typeof val === 'string') {
                            return `"${val.replace(/"/g, '""')}"`;
                        }
                        return val !== null && val !== undefined ? val : '';
                    }).join(',') + '\n';
                });
            } else {
                // Export as key-value pairs if no structured data
                csvContent += 'Field,Value\n';
                Object.keys(reportData).forEach(key => {
                    if (key !== 'data' && typeof reportData[key] !== 'object') {
                        csvContent += `"${key}","${String(reportData[key]).replace(/"/g, '""')}"\n`;
                    }
                });
            }
        }
        
        // Add totals if available
        if (reportData?.totals) {
            csvContent += '\n';
            csvContent += 'Totals\n';
            Object.keys(reportData.totals).forEach(key => {
                csvContent += `${key},${reportData.totals[key]}\n`;
            });
        }
        
        // Create and download CSV
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `report-${this.currentReportType}-${dateStr}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
ProfessionalAccounting.prototype.exportReportToExcel = function(reportName, dateStr) {
        // For Excel, we'll create a CSV with Excel-compatible format
        // Most browsers can open CSV files in Excel
        this.exportReportToCSV(reportName, dateStr);
        
        // Alternatively, we could use a library like SheetJS, but CSV works for most cases
        // If you want true Excel format, you'd need to add a library like xlsx
    }
    
ProfessionalAccounting.prototype.showExportMenu = function(button) {
        // Remove existing menu if any
        const existingMenu = document.querySelector('.export-format-menu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }
        
        // Create dropdown menu
        const menu = document.createElement('div');
        menu.className = 'export-format-menu';
        menu.innerHTML = `
            <div class="export-menu-item" data-format="csv">
                <i class="fas fa-file-csv"></i> Export as CSV
            </div>
            <div class="export-menu-item" data-format="excel">
                <i class="fas fa-file-excel"></i> Export as Excel
            </div>
            <div class="export-menu-item" data-format="json">
                <i class="fas fa-file-code"></i> Export as JSON
            </div>
        `;
        
        // Position menu near button
        const rect = button.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = (rect.bottom + 5) + 'px';
        menu.style.left = rect.left + 'px';
        menu.style.zIndex = '10000';
        menu.style.backgroundColor = '#fff';
        menu.style.border = '1px solid #ddd';
        menu.style.borderRadius = '4px';
        menu.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
        menu.style.padding = '4px 0';
        menu.style.minWidth = '180px';
        
        // Style menu items
        const style = document.createElement('style');
        style.textContent = `
            .export-format-menu .export-menu-item {
                padding: 8px 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                color: #333;
            }
            .export-format-menu .export-menu-item:hover {
                background-color: #f5f5f5;
            }
            .export-format-menu .export-menu-item i {
                width: 16px;
            }
        `;
        if (!document.getElementById('export-menu-styles')) {
            style.id = 'export-menu-styles';
            document.head.appendChild(style);
        }
        
        document.body.appendChild(menu);
        
        // Handle menu item clicks
        menu.querySelectorAll('.export-menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const format = item.getAttribute('data-format');
                this.exportCurrentReport(format);
                menu.remove();
            });
        });
        
        // Close menu when clicking outside
        const closeMenu = (e) => {
            if (!menu.contains(e.target) && e.target !== button) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        };
        setTimeout(() => document.addEventListener('click', closeMenu), 100);
    }

ProfessionalAccounting.prototype.exportAllReports = async function() {
        try {
            const response = await fetch(`${this.apiBase}/reports.php?action=export_all`);
            if (response.ok) {
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `all-reports-${new Date().toISOString().split('T')[0]}.zip`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast('All reports exported successfully', 'success');
            } else {
                this.showToast('Failed to export reports', 'error');
                }
            } catch (error) {
            this.showToast('Error exporting reports: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.checkAndGenerateAlerts = async function() {
        // Check for alerts and generate them if needed
        // This runs once per day on page load
        try {
            const lastAlertCheck = localStorage.getItem('lastAlertCheck');
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            
            // Only check once per day
            if (lastAlertCheck === today) {
                return;
            }
            
            const response = await fetch(`${this.apiBase}/auto-generate-alerts.php`, {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update last check date
                localStorage.setItem('lastAlertCheck', today);
                
                // Show notification if alerts were generated
                if (data.alerts_generated && data.alerts_generated > 0) {
                    this.showToast(`${data.alerts_generated} new alert(s) generated`, 'info', 7000);
                }
            }
        } catch (error) {
            // Silently fail - alert generation is not critical
            // Error logged but not shown to user
        }
    }

    // Missing Handler Setup Methods - Aliases for consistency
    
ProfessionalAccounting.prototype.setupCostCentersHandlers = function() {
        // Alias for setupCostCentersEventHandlers
        this.setupCostCentersEventHandlers();
    }

ProfessionalAccounting.prototype.setupBankGuaranteeHandlers = function() {
        // Alias for setupBankGuaranteesEventHandlers
        this.setupBankGuaranteesEventHandlers();
    }

ProfessionalAccounting.prototype.setupEntryApprovalHandlers = function() {
        // Setup entry approval handlers including search and filter
        this.setupEntryApprovalEventHandlers();
        
        const modal = document.getElementById('entryApprovalModal');
        if (!modal) return;

        // Search input handler
        const searchInput = document.getElementById('entryApprovalSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                this.entryApprovalSearchTerm = e.target.value.trim();
                searchTimeout = setTimeout(() => {
                    this.entryApprovalCurrentPage = 1;
                    this.renderEntryApprovalTable();
                }, 300);
            });
        }

        // Status filter handler
        const statusFilter = document.getElementById('entryApprovalStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.entryApprovalCurrentPage = 1;
                this.loadEntryApproval(e.target.value);
            });
        }

        // Page size handler
        const pageSize = document.getElementById('entryApprovalPageSize');
        if (pageSize) {
            pageSize.addEventListener('change', (e) => {
                this.entryApprovalPerPage = parseInt(e.target.value);
                this.entryApprovalCurrentPage = 1;
                this.renderEntryApprovalTable();
            });
        }

        // Apply filters button
        const applyFiltersBtn = document.getElementById('entryApprovalApplyFilters');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.entryApprovalCurrentPage = 1;
                const statusFilter = document.getElementById('entryApprovalStatusFilter');
                this.loadEntryApproval(statusFilter ? statusFilter.value : 'all');
            });
        }

        // Pagination handlers
        const prevBtn = document.getElementById('entryApprovalPrevBtn');
        const nextBtn = document.getElementById('entryApprovalNextBtn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.entryApprovalCurrentPage > 1) {
                    this.entryApprovalCurrentPage--;
                    this.renderEntryApprovalTable();
                }
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil(this.entryApprovalTotalCount / this.entryApprovalPerPage);
                if (this.entryApprovalCurrentPage < totalPages) {
                    this.entryApprovalCurrentPage++;
                    this.renderEntryApprovalTable();
                }
            });
        }
    }

    // Missing Render Methods
    
ProfessionalAccounting.prototype.renderCostCentersTable = function() {
        const tbody = document.getElementById('costCentersTableBody');
        if (!tbody) return;

        // Apply search filter
        let filtered = [...this.costCentersData];
        const searchTerm = (this.costCentersSearchTerm || '').toLowerCase();
        if (searchTerm) {
            filtered = filtered.filter(cc => 
                (cc.code && cc.code.toLowerCase().includes(searchTerm)) ||
                (cc.name && cc.name.toLowerCase().includes(searchTerm)) ||
                (cc.description && cc.description.toLowerCase().includes(searchTerm))
            );
        }

        // Apply status filter
        const statusFilter = document.getElementById('costCentersStatusFilter')?.value;
        if (statusFilter) {
            filtered = filtered.filter(cc => cc.status === statusFilter);
        }

        // Update total count
        this.costCentersTotalCount = filtered.length;
        const totalPages = Math.ceil(this.costCentersTotalCount / this.costCentersPerPage);
        this.costCentersTotalPages = totalPages || 1;

        // Paginate
        const start = (this.costCentersCurrentPage - 1) * this.costCentersPerPage;
        const end = start + this.costCentersPerPage;
        const paginated = filtered.slice(start, end);

        // Render
        if (paginated.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No cost centers found</p>
                        </div>
                        </td>
                </tr>
            `;
        } else {
            tbody.innerHTML = paginated.map(cc => `
                <tr>
                    <td>${this.escapeHtml(cc.code || '')}</td>
                    <td>${this.escapeHtml(cc.name || '')}</td>
                    <td>${this.escapeHtml(cc.description || '')}</td>
                    <td>
                        <span class="badge ${cc.status === 'active' ? 'badge-success' : 'badge-secondary'}">
                            ${cc.status === 'active' ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <input type="checkbox" class="cost-center-checkbox" value="${cc.id}">
                    </td>
                    <td>
                        <button class="btn-icon btn-edit" data-action="edit-cost-center" data-id="${cc.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                            </button>
                        <button class="btn-icon btn-delete" data-action="delete-cost-center" data-id="${cc.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
        }

        // Update pagination UI
        this.updateCostCentersPagination();
        
        // Re-attach event handlers
        this.setupCostCentersEventHandlers();
    }