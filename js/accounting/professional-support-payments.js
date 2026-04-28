/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional-support-payments.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional-support-payments.js`.
 */
/**
 * Support Payments overlay - adds # column and Edit button
 * Load after professional.js
 */
(function() {
    'use strict';
    if (typeof ProfessionalAccounting === 'undefined') return;

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
    };

    ProfessionalAccounting.prototype.loadSupportPayments = async function() {
        const tbody = document.getElementById('supportPaymentsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="loading-row"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading...</span></div></td></tr>';
        try {
            const response = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=payment`, { credentials: 'include' });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || data.error || `HTTP ${response.status}`);
            }
            const vouchers = (data.success && data.vouchers) ? data.vouchers : [];
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
                tbody.innerHTML = vouchers.map((v) => {
                    const voucherNum = v.voucher_number || v.reference_number || 'N/A';
                    return `
                    <tr>
                        <td>${this.escapeHtml(voucherNum)}</td>
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
                `;
                }).join('');
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
    };

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
    };
})();
