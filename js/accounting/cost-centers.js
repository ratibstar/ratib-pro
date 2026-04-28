/**
 * EN: Implements frontend interaction behavior in `js/accounting/cost-centers.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/cost-centers.js`.
 */
/**
 * Cost Centers Module - JavaScript
 * Implements ledger-driven design principles from ACCOUNTING_UI_UX_REDESIGN.md
 * Cost center totals read ONLY from general_ledger
 */

(function() {
    'use strict';

    const API_BASE = getApiBase();
    let costCentersData = [];
    let currentEditingId = null;

    // Initialize module
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        loadCostCenters();
    });

    function initializeEventListeners() {
        // New cost center button
        document.getElementById('btn-new-cost-center')?.addEventListener('click', showNewCostCenterForm);
        document.getElementById('btn-add-first-cost-center')?.addEventListener('click', showNewCostCenterForm);
        
        // Modal controls
        document.getElementById('btn-close-modal')?.addEventListener('click', closeModal);
        document.getElementById('btn-cancel')?.addEventListener('click', closeModal);
        
        // Form submission
        document.getElementById('cost-center-form')?.addEventListener('submit', handleFormSubmit);
        
        // View detailed report
        document.getElementById('btn-view-detailed-report')?.addEventListener('click', viewDetailedReport);
        
        // Filters
        document.getElementById('btn-apply-filters')?.addEventListener('click', applyFilters);
        
        // Refresh button
        document.querySelector('[data-action="refresh"]')?.addEventListener('click', loadCostCenters);
        
        // Delegated handlers for cost centers table (no inline onclick)
        document.getElementById('cost-centers-table')?.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="edit-cost-center"], [data-action="delete-cost-center"], [data-action="view-cost-center-report"]');
            if (!btn) return;
            var id = parseInt(btn.getAttribute('data-id'), 10);
            var action = btn.getAttribute('data-action');
            if (action === 'edit-cost-center') editCostCenter(id);
            else if (action === 'delete-cost-center') deleteCostCenter(id);
            else if (action === 'view-cost-center-report') viewCostCenterReport(id);
        });
    }

    function loadCostCenters() {
        // TODO: Implement API call to load cost centers with totals from general_ledger
        // Totals should be calculated from general_ledger GROUP BY cost_center_id
        renderCostCentersTable([]);
    }

    function renderCostCentersTable(costCenters) {
        const tbody = document.getElementById('cost-centers-tbody');
        if (!tbody) return;

        if (costCenters.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-sitemap"></i></div>
                        <div class="message">No cost centers found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-cost-center">
                                <i class="fas fa-plus"></i> Add First Cost Center
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('btn-add-first-cost-center')?.addEventListener('click', showNewCostCenterForm);
            return;
        }

        tbody.innerHTML = costCenters.map(cc => {
            const net = (cc.total_revenue || 0) - (cc.total_expenses || 0);
            const netClass = net < 0 ? 'negative' : '';
            
            return `
                <tr>
                    <td>${escapeHtml(cc.code || '')}</td>
                    <td>${escapeHtml(cc.name || '')}</td>
                    <td>${renderStatusBadge(cc.status)}</td>
                    <td class="amount">${formatCurrency(cc.total_expenses || 0)}</td>
                    <td class="amount">${formatCurrency(cc.total_revenue || 0)}</td>
                    <td class="amount ${netClass}">${formatCurrency(net)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="edit-cost-center" data-id="${cc.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger" data-action="delete-cost-center" data-id="${cc.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="view-cost-center-report" data-id="${cc.id}">
                            <i class="fas fa-chart-bar"></i> Report
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderStatusBadge(status) {
        const statusMap = {
            'active': { class: 'status-posted', label: 'Active' },
            'inactive': { class: 'status-draft', label: 'Inactive' }
        };
        const statusInfo = statusMap[status] || { class: 'status-draft', label: status };
        return `<span class="status-badge ${statusInfo.class}">${statusInfo.label}</span>`;
    }

    function showNewCostCenterForm() {
        currentEditingId = null;
        document.getElementById('modal-title').textContent = 'New Cost Center';
        document.getElementById('cost-center-form').reset();
        document.getElementById('cost-center-id').value = '';
        document.getElementById('cost-center-summary').classList.remove('visible');
        document.getElementById('cost-center-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('cost-center-modal').classList.add('accounting-modal-visible');
    }

    function closeModal() {
        document.getElementById('cost-center-modal').classList.add('accounting-modal-hidden');
        document.getElementById('cost-center-modal').classList.remove('accounting-modal-visible');
        currentEditingId = null;
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // TODO: Implement API call to save cost center
        console.log('Saving cost center:', data);
        
        closeModal();
        loadCostCenters();
    }

    function loadCostCenterSummary(costCenterId) {
        // TODO: Implement API call to load cost center summary from general_ledger
        // Query: SELECT SUM(debit) as total_expenses, SUM(credit) as total_revenue 
        // FROM general_ledger WHERE cost_center_id = ? GROUP BY cost_center_id
        
        const summary = {
            total_expenses: 0,
            total_revenue: 0,
            net: 0
        };
        
        document.getElementById('total-expenses').textContent = formatCurrency(summary.total_expenses);
        document.getElementById('total-revenue').textContent = formatCurrency(summary.total_revenue);
        document.getElementById('net-amount').textContent = formatCurrency(summary.net);
        document.getElementById('cost-center-summary').classList.add('visible');
    }

    function viewDetailedReport(costCenterId) {
        // TODO: Navigate to cost center detailed report
        // Should show all ledger entries filtered by cost_center_id
        console.log('View detailed report for cost center:', costCenterId);
    }

    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        // TODO: Implement filter logic
        loadCostCenters();
    }

    // Global functions for inline event handlers
    window.editCostCenter = function(id) {
        currentEditingId = id;
        // TODO: Load cost center data and populate form
        document.getElementById('modal-title').textContent = 'Edit Cost Center';
        loadCostCenterSummary(id);
        document.getElementById('cost-center-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('cost-center-modal').classList.add('accounting-modal-visible');
    };

    window.deleteCostCenter = function(id) {
        if (confirm('Are you sure you want to delete this cost center?')) {
            // TODO: Implement API call to delete cost center
            loadCostCenters();
        }
    };

    window.viewCostCenterReport = function(id) {
        // TODO: Navigate to cost center report page
        console.log('View cost center report:', id);
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

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getApiBase() {
        return window.API_BASE || '/api/accounting';
    }

})();
