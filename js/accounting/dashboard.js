/**
 * EN: Implements frontend interaction behavior in `js/accounting/dashboard.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/dashboard.js`.
 */
/**
 * Accounting Dashboard Component
 * 
 * Implements Control Panel (Dashboard) per ACCOUNTING_UI_UX_REDESIGN.md
 * 
 * Features:
 * - KPIs from general_ledger only
 * - Trial Balance validation
 * - Pending Approvals
 * - Recent Activity
 * - Cash Flow
 * - Period Status with lock warnings
 */

(function() {
    'use strict';

    // Configuration
    const API_ENDPOINT = 'api/accounting/dashboard-data.php';
    const REFRESH_INTERVAL = 60000; // 60 seconds
    
    // State
    let dashboardData = null;
    let refreshTimer = null;
    
    /**
     * Initialize dashboard
     */
    function initDashboard() {
        const dashboardContainer = document.getElementById('accounting-dashboard');
        if (!dashboardContainer) {
            return; // Dashboard not on this page
        }
        
        // Create dashboard structure
        createDashboardStructure(dashboardContainer);
        
        // Load initial data
        loadDashboardData();
        
        // Set up auto-refresh
        refreshTimer = setInterval(loadDashboardData, REFRESH_INTERVAL);
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
        });
    }
    
    /**
     * Create dashboard HTML structure
     */
    function createDashboardStructure(container) {
        container.innerHTML = `
            <div class="accounting-dashboard-header">
                <h1>Accounting Dashboard</h1>
                <div class="dashboard-controls">
                    <select id="dashboard-period" class="period-selector">
                        <option value="current">Current Period</option>
                        <option value="last-month">Last Month</option>
                        <option value="last-quarter">Last Quarter</option>
                        <option value="last-year">Last Year</option>
                    </select>
                    <button id="refresh-dashboard" class="btn-refresh" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="dashboard-loading" id="dashboard-loading">
                <div class="loading-spinner"></div>
                <p>Loading dashboard data...</p>
            </div>
            
            <div class="dashboard-content-wrapper" id="dashboard-content" style="display: none;">
                <!-- KPI Cards -->
                <div class="dashboard-kpis">
                    <div class="kpi-card kpi-assets">
                        <div class="kpi-label">Assets</div>
                        <div class="kpi-value" id="kpi-assets">0.00</div>
                        <button class="kpi-action" data-action="view-assets">View</button>
                    </div>
                    <div class="kpi-card kpi-liabilities">
                        <div class="kpi-label">Liabilities</div>
                        <div class="kpi-value" id="kpi-liabilities">0.00</div>
                        <button class="kpi-action" data-action="view-liabilities">View</button>
                    </div>
                    <div class="kpi-card kpi-equity">
                        <div class="kpi-label">Equity</div>
                        <div class="kpi-value" id="kpi-equity">0.00</div>
                        <button class="kpi-action" data-action="view-equity">View</button>
                    </div>
                    <div class="kpi-card kpi-net-income">
                        <div class="kpi-label">Net Income</div>
                        <div class="kpi-value" id="kpi-net-income">0.00</div>
                        <button class="kpi-action" data-action="view-income">View</button>
                    </div>
                </div>
                
                <!-- Trial Balance Summary -->
                <div class="dashboard-section trial-balance-section">
                    <h2>Trial Balance Summary</h2>
                    <div class="trial-balance-content">
                        <div class="trial-balance-totals">
                            <div class="total-item">
                                <span class="total-label">Total Debit:</span>
                                <span class="total-value" id="tb-total-debit">0.00</span>
                            </div>
                            <div class="total-item">
                                <span class="total-label">Total Credit:</span>
                                <span class="total-value" id="tb-total-credit">0.00</span>
                            </div>
                        </div>
                        <div class="balance-indicator" id="balance-indicator">
                            <span class="balance-icon">✓</span>
                            <span class="balance-text">Balanced</span>
                        </div>
                        <button class="btn-view-trial-balance" data-action="view-trial-balance">
                            View Full Trial Balance
                        </button>
                    </div>
                </div>
                
                <!-- Two Column Layout -->
                <div class="dashboard-two-column">
                    <!-- Pending Approvals -->
                    <div class="dashboard-section pending-approvals-section">
                        <h2>Pending Approvals</h2>
                        <div class="pending-summary" id="pending-summary">
                            <div class="pending-item">
                                <span class="pending-count" id="pending-journal-entries">0</span>
                                <span class="pending-label">Journal Entries</span>
                            </div>
                            <div class="pending-item">
                                <span class="pending-count" id="pending-expenses">0</span>
                                <span class="pending-label">Expenses</span>
                            </div>
                        </div>
                        <div class="pending-list" id="pending-list">
                            <p class="no-data">No pending approvals</p>
                        </div>
                        <button class="btn-review-all" data-action="review-approvals">
                            Review All
                        </button>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="dashboard-section recent-activity-section">
                        <h2>Recent Activity</h2>
                        <div class="activity-list" id="activity-list">
                            <p class="no-data">No recent activity</p>
                        </div>
                    </div>
                </div>
                
                <!-- Cash Flow -->
                <div class="dashboard-section cash-flow-section">
                    <h2>Cash Flow (Last 30 Days)</h2>
                    <div class="cash-flow-content">
                        <div class="cash-flow-totals">
                            <div class="cash-flow-item">
                                <span class="cash-flow-label">Inflow:</span>
                                <span class="cash-flow-value positive" id="cash-inflow">0.00</span>
                            </div>
                            <div class="cash-flow-item">
                                <span class="cash-flow-label">Outflow:</span>
                                <span class="cash-flow-value negative" id="cash-outflow">0.00</span>
                            </div>
                            <div class="cash-flow-item">
                                <span class="cash-flow-label">Net:</span>
                                <span class="cash-flow-value" id="cash-net">0.00</span>
                            </div>
                        </div>
                        <div class="cash-flow-chart" id="cash-flow-chart">
                            <!-- Chart will be rendered here if Chart.js is available -->
                        </div>
                    </div>
                </div>
                
                <!-- Period Status -->
                <div class="dashboard-section period-status-section">
                    <h2>Period Status</h2>
                    <div class="period-status-content" id="period-status-content">
                        <div class="period-info">
                            <div class="period-item">
                                <span class="period-label">Current Period:</span>
                                <span class="period-value" id="current-period-status">Open</span>
                            </div>
                            <div class="period-item">
                                <span class="period-label">Last Closed:</span>
                                <span class="period-value" id="last-closed-period">-</span>
                            </div>
                        </div>
                        <div class="period-warning dashboard-hidden" id="period-warning">
                            <span class="warning-icon">⚠</span>
                            <span class="warning-text" id="warning-text"></span>
                        </div>
                        <div class="period-lock-warning dashboard-hidden" id="period-lock-warning">
                            <span class="lock-icon">🔒</span>
                            <span class="lock-text">This period is locked. Entries cannot be modified.</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-error dashboard-hidden" id="dashboard-error">
                <div class="error-icon">⚠</div>
                <div class="error-message" id="error-message"></div>
                <button class="btn-retry" data-action="retry-dashboard">Retry</button>
            </div>
        `;
        
        // Attach event listeners
        attachEventListeners();
    }
    
    /**
     * Attach event listeners
     */
    function attachEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadDashboardData);
        }
        
        // Period selector
        const periodSelector = document.getElementById('dashboard-period');
        if (periodSelector) {
            periodSelector.addEventListener('change', function() {
                const period = this.value;
                loadDashboardData(period);
            });
        }
        
        // KPI action buttons
        document.querySelectorAll('.kpi-action').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                handleKpiAction(action);
            });
        });
        
        // View Trial Balance button
        const viewTrialBalanceBtn = document.querySelector('.btn-view-trial-balance');
        if (viewTrialBalanceBtn) {
            viewTrialBalanceBtn.addEventListener('click', function() {
                window.location.href = 'pages/account/reports.php?report=trial_balance';
            });
        }
        
        // Review All button
        const reviewAllBtn = document.querySelector('.btn-review-all');
        if (reviewAllBtn) {
            reviewAllBtn.addEventListener('click', function() {
                window.location.href = 'pages/account/journal/entry-approval.php';
            });
        }
        
        // Retry button (no inline onclick)
        document.querySelector('[data-action="retry-dashboard"]')?.addEventListener('click', function() {
            loadDashboardData();
        });
    }
    
    /**
     * Load dashboard data from API
     */
    async function loadDashboardData(period = 'current') {
        const loadingEl = document.getElementById('dashboard-loading');
        const contentEl = document.getElementById('dashboard-content');
        const errorEl = document.getElementById('dashboard-error');
        
        // Show loading, hide content and error
        if (loadingEl) loadingEl.classList.remove('dashboard-hidden');
        if (contentEl) contentEl.classList.add('dashboard-hidden');
        if (errorEl) errorEl.classList.add('dashboard-hidden');
        
        try {
            // Calculate date range based on period
            const dateParams = getDateParamsForPeriod(period);
            const url = `${API_ENDPOINT}?${new URLSearchParams(dateParams).toString()}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load dashboard data');
            }
            
            dashboardData = data;
            renderDashboard(data);
            
            // Hide loading, show content
            if (loadingEl) loadingEl.classList.add('dashboard-hidden');
            if (contentEl) contentEl.classList.remove('dashboard-hidden');
            
        } catch (error) {
            console.error('Dashboard load error:', error);
            
            // Show error
            if (loadingEl) loadingEl.classList.add('dashboard-hidden');
            if (contentEl) contentEl.classList.add('dashboard-hidden');
            if (errorEl) {
                errorEl.classList.remove('dashboard-hidden');
                errorEl.classList.add('dashboard-visible');
                const errorMsg = document.getElementById('error-message');
                if (errorMsg) {
                    errorMsg.textContent = error.message || 'Failed to load dashboard data';
                }
            }
        }
    }
    
    /**
     * Get date parameters for period selector
     */
    function getDateParamsForPeriod(period) {
        const today = new Date();
        const params = {
            as_of_date: formatDateForAPI(today)
        };
        
        switch (period) {
            case 'last-month':
                const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                params.period_start = formatDateForAPI(new Date(lastMonth.getFullYear(), lastMonth.getMonth(), 1));
                params.period_end = formatDateForAPI(new Date(lastMonth.getFullYear(), lastMonth.getMonth() + 1, 0));
                break;
            case 'last-quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                const quarterStart = new Date(today.getFullYear(), quarter * 3, 1);
                const quarterEnd = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                params.period_start = formatDateForAPI(quarterStart);
                params.period_end = formatDateForAPI(quarterEnd);
                break;
            case 'last-year':
                params.period_start = formatDateForAPI(new Date(today.getFullYear() - 1, 0, 1));
                params.period_end = formatDateForAPI(new Date(today.getFullYear() - 1, 11, 31));
                break;
            default: // current
                params.period_start = formatDateForAPI(new Date(today.getFullYear(), today.getMonth(), 1));
                params.period_end = formatDateForAPI(new Date(today.getFullYear(), today.getMonth() + 1, 0));
        }
        
        return params;
    }
    
    /**
     * Format date as MM/DD/YYYY for display
     */
    function formatDate(date) {
        if (!date) return '';
        try {
            const d = new Date(date);
            if (isNaN(d.getTime())) return '';
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${month}/${day}/${year}`;
        } catch (e) {
            return '';
        }
    }
    
    /**
     * Format date as YYYY-MM-DD for API requests
     */
    function formatDateForAPI(date) {
        if (!date) return '';
        try {
            const d = new Date(date);
            if (isNaN(d.getTime())) return '';
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        } catch (e) {
            return '';
        }
    }
    
    // Legacy function kept for backward compatibility
    function formatDateForAPIOld(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Render dashboard with data
     */
    function renderDashboard(data) {
        // Render KPIs
        renderKPIs(data.kpis);
        
        // Render Trial Balance
        renderTrialBalance(data.trial_balance);
        
        // Render Pending Approvals
        renderPendingApprovals(data.pending_approvals);
        
        // Render Recent Activity
        renderRecentActivity(data.recent_activity);
        
        // Render Cash Flow
        renderCashFlow(data.cash_flow);
        
        // Render Period Status
        renderPeriodStatus(data.period_status);
    }
    
    /**
     * Render KPI cards
     */
    function renderKPIs(kpis) {
        document.getElementById('kpi-assets').textContent = formatCurrency(kpis.assets);
        document.getElementById('kpi-liabilities').textContent = formatCurrency(kpis.liabilities);
        document.getElementById('kpi-equity').textContent = formatCurrency(kpis.equity);
        document.getElementById('kpi-net-income').textContent = formatCurrency(kpis.net_income);
        
        // Add color class based on value
        const netIncomeEl = document.getElementById('kpi-net-income');
        if (netIncomeEl) {
            netIncomeEl.className = 'kpi-value ' + (kpis.net_income >= 0 ? 'positive' : 'negative');
        }
    }
    
    /**
     * Render Trial Balance
     */
    function renderTrialBalance(tb) {
        document.getElementById('tb-total-debit').textContent = formatCurrency(tb.total_debit);
        document.getElementById('tb-total-credit').textContent = formatCurrency(tb.total_credit);
        
        const indicator = document.getElementById('balance-indicator');
        if (indicator) {
            if (tb.is_balanced) {
                indicator.className = 'balance-indicator balanced';
                indicator.innerHTML = '<span class="balance-icon">✓</span><span class="balance-text">Balanced</span>';
            } else {
                indicator.className = 'balance-indicator unbalanced';
                indicator.innerHTML = `<span class="balance-icon">⚠</span><span class="balance-text">Unbalanced: ${formatCurrency(tb.difference)}</span>`;
            }
        }
    }
    
    /**
     * Render Pending Approvals
     */
    function renderPendingApprovals(pending) {
        document.getElementById('pending-journal-entries').textContent = pending.journal_entries || 0;
        document.getElementById('pending-expenses').textContent = pending.expenses || 0;
        
        const listEl = document.getElementById('pending-list');
        if (!listEl) return;
        
        if (pending.items && pending.items.length > 0) {
            listEl.innerHTML = pending.items.slice(0, 5).map(item => `
                <div class="pending-item-row">
                    <div class="pending-item-info">
                        <span class="pending-entry-number">${escapeHtml(item.entry_number || 'N/A')}</span>
                        <span class="pending-description">${escapeHtml(item.description || '')}</span>
                    </div>
                    <div class="pending-item-amount">${formatCurrency(item.amount)}</div>
                </div>
            `).join('');
        } else {
            listEl.innerHTML = '<p class="no-data">No pending approvals</p>';
        }
    }
    
    /**
     * Render Recent Activity
     */
    function renderRecentActivity(activity) {
        const listEl = document.getElementById('activity-list');
        if (!listEl) return;
        
        if (activity && activity.length > 0) {
            listEl.innerHTML = activity.map(item => {
                const statusClass = getStatusClass(item.status, item.is_posted, item.is_locked);
                const statusText = getStatusText(item.status, item.is_posted, item.is_locked);
                
                return `
                    <div class="activity-item">
                        <div class="activity-type">${escapeHtml(item.entry_type || 'Journal Entry')}</div>
                        <div class="activity-details">
                            <span class="activity-entry">${escapeHtml(item.entry_number || '')}</span>
                            <span class="activity-description">${escapeHtml(item.description || '')}</span>
                        </div>
                        <div class="activity-status ${statusClass}">${escapeHtml(statusText)}</div>
                        <div class="activity-date">${formatDateDisplay(item.created_at)}</div>
                    </div>
                `;
            }).join('');
        } else {
            listEl.innerHTML = '<p class="no-data">No recent activity</p>';
        }
    }
    
    /**
     * Render Cash Flow
     */
    function renderCashFlow(cf) {
        document.getElementById('cash-inflow').textContent = formatCurrency(cf.inflow);
        document.getElementById('cash-outflow').textContent = formatCurrency(cf.outflow);
        
        const netEl = document.getElementById('cash-net');
        if (netEl) {
            netEl.textContent = formatCurrency(cf.net);
            netEl.className = 'cash-flow-value ' + (cf.net >= 0 ? 'positive' : 'negative');
        }
        
        // Render chart if Chart.js is available
        renderCashFlowChart(cf);
    }
    
    /**
     * Render Cash Flow Chart
     */
    function renderCashFlowChart(cf) {
        const chartEl = document.getElementById('cash-flow-chart');
        if (!chartEl || typeof Chart === 'undefined') {
            return; // Chart.js not available
        }
        
        // Destroy existing chart if any
        if (window.cashFlowChart) {
            window.cashFlowChart.destroy();
        }
        
        window.cashFlowChart = new Chart(chartEl, {
            type: 'line',
            data: {
                labels: ['Inflow', 'Outflow', 'Net'],
                datasets: [{
                    label: 'Cash Flow',
                    data: [cf.inflow, cf.outflow, cf.net],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    /**
     * Render Period Status
     */
    function renderPeriodStatus(ps) {
        document.getElementById('current-period-status').textContent = ps.current_period;
        
        const lastClosedEl = document.getElementById('last-closed-period');
        if (lastClosedEl) {
            if (ps.last_closed) {
                lastClosedEl.textContent = ps.last_closed.name || formatDateDisplay(ps.last_closed.end_date);
            } else {
                lastClosedEl.textContent = '-';
            }
        }
        
        // Show/hide warnings
        const warningEl = document.getElementById('period-warning');
        const lockWarningEl = document.getElementById('period-lock-warning');
        
        if (ps.is_locked && lockWarningEl) {
            lockWarningEl.classList.remove('dashboard-hidden');
        } else if (lockWarningEl) {
            lockWarningEl.classList.add('dashboard-hidden');
        }
        
        if (ps.warning && warningEl) {
            warningEl.classList.remove('dashboard-hidden');
            document.getElementById('warning-text').textContent = ps.warning;
        } else if (warningEl) {
            warningEl.classList.add('dashboard-hidden');
        }
    }
    
    /**
     * Handle KPI action clicks
     */
    function handleKpiAction(action) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
        
        switch (action) {
            case 'view-assets':
                window.location.href = `${baseUrl}/pages/account/chart/index.php?type=ASSET`;
                break;
            case 'view-liabilities':
                window.location.href = `${baseUrl}/pages/account/chart/index.php?type=LIABILITY`;
                break;
            case 'view-equity':
                window.location.href = `${baseUrl}/pages/account/chart/index.php?type=EQUITY`;
                break;
            case 'view-income':
                window.location.href = `${baseUrl}/pages/account/reports.php?report=income_statement`;
                break;
        }
    }
    
    /**
     * Get status class for styling
     */
    function getStatusClass(status, isPosted, isLocked) {
        if (isLocked) return 'status-locked';
        if (isPosted) return 'status-posted';
        if (status === 'Approved') return 'status-approved';
        if (status === 'Draft') return 'status-draft';
        return 'status-pending';
    }
    
    /**
     * Get status text
     */
    function getStatusText(status, isPosted, isLocked) {
        if (isLocked) return 'Locked';
        if (isPosted) return 'Posted';
        if (status === 'Approved') return 'Approved';
        if (status === 'Draft') return 'Draft';
        return status || 'Pending';
    }
    
    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount || 0);
    }
    
    /**
     * Format date for display
     */
    function formatDateDisplay(dateString) {
        if (!dateString) return '';
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
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
    
    // Export for global access
    window.AccountingDashboard = {
        load: loadDashboardData,
        refresh: loadDashboardData
    };
    
})();
