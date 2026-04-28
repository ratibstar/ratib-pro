/**
 * EN: Implements frontend interaction behavior in `js/accounting/bank-guarantees.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/bank-guarantees.js`.
 */
/**
 * Bank Guarantees Module - JavaScript
 * Implements ledger-driven design principles from ACCOUNTING_UI_UX_REDESIGN.md
 */

(function() {
    'use strict';

    const API_BASE = getApiBase();
    let guaranteesData = [];
    let currentEditingId = null;

    // Initialize module
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        loadGuarantees();
        loadBanks();
    });

    function initializeEventListeners() {
        // New guarantee button
        document.getElementById('btn-new-guarantee')?.addEventListener('click', showNewGuaranteeForm);
        document.getElementById('btn-add-first-guarantee')?.addEventListener('click', showNewGuaranteeForm);
        
        // Modal controls
        document.getElementById('btn-close-modal')?.addEventListener('click', closeModal);
        document.getElementById('btn-cancel')?.addEventListener('click', closeModal);
        
        // Form submission
        document.getElementById('guarantee-form')?.addEventListener('submit', handleFormSubmit);
        
        // Filters
        document.getElementById('btn-apply-filters')?.addEventListener('click', applyFilters);
        
        // Refresh button
        document.querySelector('[data-action="refresh"]')?.addEventListener('click', loadGuarantees);
        
        // Delegated handlers for guarantees table (no inline onclick)
        document.getElementById('guarantees-table')?.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="edit-guarantee"], [data-action="delete-guarantee"]');
            if (!btn) return;
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (btn.getAttribute('data-action') === 'edit-guarantee') editGuarantee(id);
            else if (btn.getAttribute('data-action') === 'delete-guarantee') deleteGuarantee(id);
        });
    }

    function loadGuarantees() {
        // TODO: Implement API call to load guarantees
        // For now, show empty state
        renderGuaranteesTable([]);
    }

    function loadBanks() {
        // TODO: Implement API call to load banks for dropdown
    }

    function renderGuaranteesTable(guarantees) {
        const tbody = document.getElementById('guarantees-tbody');
        if (!tbody) return;

        if (guarantees.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-file-contract"></i></div>
                        <div class="message">No bank guarantees found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-guarantee">
                                <i class="fas fa-plus"></i> Add First Guarantee
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            // Re-attach event listener
            document.getElementById('btn-add-first-guarantee')?.addEventListener('click', showNewGuaranteeForm);
            return;
        }

        tbody.innerHTML = guarantees.map(guarantee => `
            <tr>
                <td>${escapeHtml(guarantee.guarantee_number || '')}</td>
                <td>${escapeHtml(guarantee.bank_name || '')}</td>
                <td class="amount">${formatCurrency(guarantee.amount || 0)}</td>
                <td>${formatDate(guarantee.issue_date)}</td>
                <td>${formatDate(guarantee.expiry_date)}</td>
                <td>${renderStatusBadge(guarantee.status)}</td>
                <td>
                    <button class="btn btn-sm btn-primary" data-action="edit-guarantee" data-id="${guarantee.id}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger" data-action="delete-guarantee" data-id="${guarantee.id}">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderStatusBadge(status) {
        const statusMap = {
            'active': { class: 'status-posted', label: 'Active' },
            'expired': { class: 'status-draft', label: 'Expired' }
        };
        const statusInfo = statusMap[status] || { class: 'status-draft', label: status };
        return `<span class="status-badge ${statusInfo.class}">${statusInfo.label}</span>`;
    }

    function showNewGuaranteeForm() {
        currentEditingId = null;
        document.getElementById('modal-title').textContent = 'New Bank Guarantee';
        document.getElementById('guarantee-form').reset();
        document.getElementById('guarantee-id').value = '';
        document.getElementById('guarantee-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('guarantee-modal').classList.add('accounting-modal-visible');
    }

    function closeModal() {
        document.getElementById('guarantee-modal').classList.add('accounting-modal-hidden');
        document.getElementById('guarantee-modal').classList.remove('accounting-modal-visible');
        currentEditingId = null;
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // TODO: Implement API call to save guarantee
        console.log('Saving guarantee:', data);
        
        // Close modal and refresh
        closeModal();
        loadGuarantees();
    }

    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        const bankId = document.getElementById('bankFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        // TODO: Implement filter logic
        loadGuarantees();
    }

    // Global functions for inline event handlers
    window.editGuarantee = function(id) {
        currentEditingId = id;
        // TODO: Load guarantee data and populate form
        document.getElementById('modal-title').textContent = 'Edit Bank Guarantee';
        document.getElementById('guarantee-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('guarantee-modal').classList.add('accounting-modal-visible');
    };

    window.deleteGuarantee = function(id) {
        if (confirm('Are you sure you want to delete this guarantee?')) {
            // TODO: Implement API call to delete guarantee
            loadGuarantees();
        }
    };

    // Utility functions
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-SA', { 
            style: 'currency', 
            currency: 'SAR' 
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

    function getApiBase() {
        // Get API base URL from global config or default
        return window.API_BASE || '/api/accounting';
    }

})();
