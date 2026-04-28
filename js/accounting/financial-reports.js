/**
 * EN: Implements frontend interaction behavior in `js/accounting/financial-reports.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/financial-reports.js`.
 */
/**
 * Financial Reports Module - JavaScript
 * Implements ledger-driven design principles from ACCOUNTING_UI_UX_REDESIGN.md
 * All reports read ONLY from general_ledger table
 */

(function() {
    'use strict';

    const API_BASE = getApiBase();
    let currentReportType = null;
    let currentReportData = null;

    // Initialize module
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        loadCostCenters();
    });

    function initializeEventListeners() {
        // Period selector
        document.getElementById('period-select')?.addEventListener('change', handlePeriodChange);
        
        // Generate report button
        document.getElementById('btn-generate-report')?.addEventListener('click', applyFilters);
        
        // Report modal controls
        document.getElementById('btn-close-report-modal')?.addEventListener('click', closeReportModal);
        document.getElementById('btn-close-report')?.addEventListener('click', closeReportModal);
        document.getElementById('btn-export-pdf')?.addEventListener('click', () => exportCurrentReport('pdf'));
        document.getElementById('btn-export-excel')?.addEventListener('click', () => exportCurrentReport('excel'));
        
        // Refresh button
        document.querySelector('[data-action="refresh"]')?.addEventListener('click', applyFilters);
        
        // Delegated handlers for report table buttons (no inline onclick)
        document.getElementById('reports-list-table')?.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="generate-report"], [data-action="export-report"]');
            if (!btn) return;
            var reportType = btn.getAttribute('data-report-type');
            var action = btn.getAttribute('data-action');
            if (action === 'generate-report' && reportType) {
                generateReport(reportType);
            } else if (action === 'export-report' && reportType) {
                var format = btn.getAttribute('data-format') || 'pdf';
                exportReport(reportType, format);
            }
        });
    }

    function handlePeriodChange() {
        const period = document.getElementById('period-select').value;
        const customRange = document.getElementById('custom-date-range');
        const customRangeTo = document.getElementById('custom-date-range-to');
        
        if (period === 'custom') {
            customRange.classList.add('filter-group-visible');
            customRangeTo.classList.add('filter-group-visible');
        } else {
            customRange.classList.remove('filter-group-visible');
            customRangeTo.classList.remove('filter-group-visible');
        }
    }

    function loadCostCenters() {
        // TODO: Implement API call to load cost centers
    }

    function applyFilters() {
        const period = document.getElementById('period-select').value;
        const costCenterId = document.getElementById('cost-center-filter').value;
        
        // Update period display
        document.querySelectorAll('#period-display').forEach(el => {
            el.textContent = getPeriodLabel(period);
        });
        
        // Update cost center display
        const costCenterName = costCenterId ? 
            document.getElementById('cost-center-filter').selectedOptions[0].text : 'All';
        document.querySelectorAll('#cost-center-display').forEach(el => {
            el.textContent = costCenterName;
        });
    }

    function getPeriodLabel(period) {
        const labels = {
            'current-month': 'Current Month',
            'last-month': 'Last Month',
            'current-quarter': 'Current Quarter',
            'last-quarter': 'Last Quarter',
            'current-year': 'Current Year',
            'last-year': 'Last Year',
            'custom': 'Custom Range'
        };
        return labels[period] || period;
    }

    // Global functions for report generation
    window.generateReport = function(reportType) {
        currentReportType = reportType;
        const period = document.getElementById('period-select').value;
        const costCenterId = document.getElementById('cost-center-filter').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        
        const params = {
            report_type: reportType,
            period: period,
            cost_center_id: costCenterId || null,
            date_from: dateFrom || null,
            date_to: dateTo || null
        };
        
        // Show loading state
        showReportModal();
        document.getElementById('report-content').innerHTML = '<div class="loading-skeleton loading-skeleton-report"></div>';
        
        // TODO: Implement API call to generate report
        // For now, show placeholder
        setTimeout(() => {
            renderReport(reportType, getMockReportData(reportType));
        }, 500);
    };

    function renderReport(reportType, data) {
        const content = document.getElementById('report-content');
        const title = document.getElementById('report-modal-title');
        
        title.textContent = getReportTitle(reportType);
        
        switch(reportType) {
            case 'trial-balance':
                content.innerHTML = renderTrialBalance(data);
                break;
            case 'income-statement':
                content.innerHTML = renderIncomeStatement(data);
                break;
            case 'balance-sheet':
                content.innerHTML = renderBalanceSheet(data);
                break;
            case 'cash-flow':
                content.innerHTML = renderCashFlow(data);
                break;
            case 'vat':
                content.innerHTML = renderVATReport(data);
                break;
            case 'aged-receivables':
                content.innerHTML = renderAgedReceivables(data);
                break;
            case 'aged-payables':
                content.innerHTML = renderAgedPayables(data);
                break;
            default:
                content.innerHTML = '<p>Report type not implemented</p>';
        }
        
        currentReportData = data;
    }

    function renderTrialBalance(data) {
        const isBalanced = Math.abs((data.total_debit || 0) - (data.total_credit || 0)) < 0.01;
        const balanceClass = isBalanced ? 'balanced' : 'unbalanced';
        const balanceIcon = isBalanced ? '✓' : '⚠';
        
        return `
            <div class="report-header">
                <h3>Trial Balance</h3>
                <p>Period: ${data.period || 'N/A'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th class="amount">Debit</th>
                        <th class="amount">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    ${(data.accounts || []).map(acc => `
                        <tr>
                            <td>${escapeHtml(acc.account_code || '')}</td>
                            <td>${escapeHtml(acc.account_name || '')}</td>
                            <td class="amount">${formatCurrency(acc.debit || 0)}</td>
                            <td class="amount">${formatCurrency(acc.credit || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_debit || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_credit || 0)}</strong></td>
                    </tr>
                    ${!isBalanced ? `
                        <tr class="total-row ${balanceClass}">
                            <td colspan="2"><strong>DIFFERENCE</strong></td>
                            <td class="amount" colspan="2"><strong>${formatCurrency(Math.abs((data.total_debit || 0) - (data.total_credit || 0)))}</strong></td>
                        </tr>
                    ` : ''}
                </tbody>
            </table>
            <div class="balance-indicator report-balance-indicator ${balanceClass}">
                <span class="icon">${balanceIcon}</span>
                <span>${isBalanced ? 'BALANCED' : 'UNBALANCED - Review entries'}</span>
            </div>
        `;
    }

    function renderIncomeStatement(data) {
        return `
            <div class="report-header">
                <h3>Income Statement</h3>
                <p>Period: ${data.period || 'N/A'}</p>
                <p>Cost Center: ${data.cost_center || 'All'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2"><strong>REVENUE</strong></td></tr>
                    ${(data.revenue || []).map(rev => `
                        <tr>
                            <td>${escapeHtml(rev.account_code || '')} ${escapeHtml(rev.account_name || '')}</td>
                            <td class="amount">${formatCurrency(rev.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Total Revenue</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_revenue || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>EXPENSES</strong></td></tr>
                    ${(data.expenses || []).map(exp => `
                        <tr>
                            <td>${escapeHtml(exp.account_code || '')} ${escapeHtml(exp.account_name || '')}</td>
                            <td class="amount">${formatCurrency(exp.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Total Expenses</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_expenses || 0)}</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>NET INCOME</strong></td>
                        <td class="amount"><strong>${formatCurrency((data.total_revenue || 0) - (data.total_expenses || 0))}</strong></td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    function renderBalanceSheet(data) {
        const isBalanced = Math.abs((data.total_assets || 0) - ((data.total_liabilities || 0) + (data.total_equity || 0))) < 0.01;
        
        return `
            <div class="report-header">
                <h3>Balance Sheet</h3>
                <p>As of: ${data.as_of_date || 'N/A'}</p>
                <p>Cost Center: ${data.cost_center || 'All'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2"><strong>ASSETS</strong></td></tr>
                    ${(data.assets || []).map(asset => `
                        <tr>
                            <td>${escapeHtml(asset.account_code || '')} ${escapeHtml(asset.account_name || '')}</td>
                            <td class="amount">${formatCurrency(asset.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Total Assets</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_assets || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>LIABILITIES</strong></td></tr>
                    ${(data.liabilities || []).map(liab => `
                        <tr>
                            <td>${escapeHtml(liab.account_code || '')} ${escapeHtml(liab.account_name || '')}</td>
                            <td class="amount">${formatCurrency(liab.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Total Liabilities</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_liabilities || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>EQUITY</strong></td></tr>
                    ${(data.equity || []).map(eq => `
                        <tr>
                            <td>${escapeHtml(eq.account_code || '')} ${escapeHtml(eq.account_name || '')}</td>
                            <td class="amount">${formatCurrency(eq.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Total Equity</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_equity || 0)}</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL LIABILITIES & EQUITY</strong></td>
                        <td class="amount"><strong>${formatCurrency((data.total_liabilities || 0) + (data.total_equity || 0))}</strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="balance-indicator report-balance-indicator ${isBalanced ? 'balanced' : 'unbalanced'}">
                <span class="icon">${isBalanced ? '✓' : '⚠'}</span>
                <span>${isBalanced ? 'Balanced: Assets = Liabilities + Equity' : 'Unbalanced'}</span>
            </div>
        `;
    }

    function renderCashFlow(data) {
        return `
            <div class="report-header">
                <h3>Cash Flow Statement</h3>
                <p>Period: ${data.period || 'N/A'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2"><strong>OPERATING ACTIVITIES</strong></td></tr>
                    ${(data.operating || []).map(item => `
                        <tr>
                            <td>${escapeHtml(item.description || '')}</td>
                            <td class="amount">${formatCurrency(item.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Net Operating Cash Flow</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.net_operating || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>INVESTING ACTIVITIES</strong></td></tr>
                    ${(data.investing || []).map(item => `
                        <tr>
                            <td>${escapeHtml(item.description || '')}</td>
                            <td class="amount">${formatCurrency(item.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Net Investing Cash Flow</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.net_investing || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>FINANCING ACTIVITIES</strong></td></tr>
                    ${(data.financing || []).map(item => `
                        <tr>
                            <td>${escapeHtml(item.description || '')}</td>
                            <td class="amount">${formatCurrency(item.amount || 0)}</td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>Net Financing Cash Flow</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.net_financing || 0)}</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>NET CHANGE IN CASH</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.net_change || 0)}</strong></td>
                    </tr>
                    <tr>
                        <td>Beginning Cash Balance</td>
                        <td class="amount">${formatCurrency(data.beginning_balance || 0)}</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Ending Cash Balance</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.ending_balance || 0)}</strong></td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    function renderVATReport(data) {
        return `
            <div class="report-header">
                <h3>VAT Report</h3>
                <p>Period: ${data.period || 'N/A'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2"><strong>VAT RECEIVABLE (Input VAT)</strong></td></tr>
                    <tr class="total-row">
                        <td><strong>Total VAT Receivable</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_vat_receivable || 0)}</strong></td>
                    </tr>
                    <tr><td colspan="2"><strong>VAT PAYABLE (Output VAT)</strong></td></tr>
                    <tr class="total-row">
                        <td><strong>Total VAT Payable</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_vat_payable || 0)}</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>NET VAT POSITION</strong></td>
                        <td class="amount"><strong>${formatCurrency((data.total_vat_receivable || 0) - (data.total_vat_payable || 0))}</strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="report-warning-box">
                <p><strong>Note:</strong> ${(data.total_vat_receivable || 0) - (data.total_vat_payable || 0) < 0 ? 
                    'Amount due to tax authority' : 'Amount receivable from tax authority'}</p>
            </div>
        `;
    }

    function renderAgedReceivables(data) {
        return `
            <div class="report-header">
                <h3>Aged Receivables</h3>
                <p>As of: ${data.as_of_date || 'N/A'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="amount">Current</th>
                        <th class="amount">30 Days</th>
                        <th class="amount">60 Days</th>
                        <th class="amount">90+ Days</th>
                        <th class="amount">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${(data.customers || []).map(customer => `
                        <tr>
                            <td>${escapeHtml(customer.name || '')}</td>
                            <td class="amount">${formatCurrency(customer.current || 0)}</td>
                            <td class="amount">${formatCurrency(customer.days30 || 0)}</td>
                            <td class="amount">${formatCurrency(customer.days60 || 0)}</td>
                            <td class="amount">${formatCurrency(customer.days90 || 0)}</td>
                            <td class="amount"><strong>${formatCurrency(customer.total || 0)}</strong></td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>TOTAL</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_current || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_30 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_60 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_90 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.grand_total || 0)}</strong></td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    function renderAgedPayables(data) {
        return `
            <div class="report-header">
                <h3>Aged Payables</h3>
                <p>As of: ${data.as_of_date || 'N/A'}</p>
                <p>Generated: ${formatDateTime(new Date())}</p>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th class="amount">Current</th>
                        <th class="amount">30 Days</th>
                        <th class="amount">60 Days</th>
                        <th class="amount">90+ Days</th>
                        <th class="amount">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${(data.vendors || []).map(vendor => `
                        <tr>
                            <td>${escapeHtml(vendor.name || '')}</td>
                            <td class="amount">${formatCurrency(vendor.current || 0)}</td>
                            <td class="amount">${formatCurrency(vendor.days30 || 0)}</td>
                            <td class="amount">${formatCurrency(vendor.days60 || 0)}</td>
                            <td class="amount">${formatCurrency(vendor.days90 || 0)}</td>
                            <td class="amount"><strong>${formatCurrency(vendor.total || 0)}</strong></td>
                        </tr>
                    `).join('')}
                    <tr class="total-row">
                        <td><strong>TOTAL</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_current || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_30 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_60 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.total_90 || 0)}</strong></td>
                        <td class="amount"><strong>${formatCurrency(data.grand_total || 0)}</strong></td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    function getReportTitle(reportType) {
        const titles = {
            'trial-balance': 'Trial Balance',
            'income-statement': 'Income Statement',
            'balance-sheet': 'Balance Sheet',
            'cash-flow': 'Cash Flow Statement',
            'vat': 'VAT Report',
            'aged-receivables': 'Aged Receivables',
            'aged-payables': 'Aged Payables'
        };
        return titles[reportType] || 'Report';
    }

    function getMockReportData(reportType) {
        // Mock data for demonstration - TODO: Replace with actual API calls
        return {
            period: getPeriodLabel(document.getElementById('period-select').value),
            accounts: [],
            total_debit: 0,
            total_credit: 0
        };
    }

    function showReportModal() {
        document.getElementById('report-modal').classList.remove('accounting-modal-hidden');
        document.getElementById('report-modal').classList.add('accounting-modal-visible');
    }

    function closeReportModal() {
        document.getElementById('report-modal').classList.add('accounting-modal-hidden');
        document.getElementById('report-modal').classList.remove('accounting-modal-visible');
        currentReportType = null;
        currentReportData = null;
    }

    window.exportReport = function(reportType, format) {
        const period = document.getElementById('period-select').value;
        const costCenterId = document.getElementById('cost-center-filter').value;
        
        // TODO: Implement API call to export report
        console.log('Exporting report:', { reportType, format, period, costCenterId });
    };

    function exportCurrentReport(format) {
        if (!currentReportType) return;
        exportReport(currentReportType, format);
    }

    // Utility functions
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-SA', { 
            style: 'currency', 
            currency: 'SAR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount || 0);
    }

    function formatDateTime(date) {
        // Format as MM/DD/YYYY HH:MM AM/PM (English format)
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = date.getHours();
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        return `${month}/${day}/${year} ${displayHours}:${minutes} ${ampm}`;
    }
    
    // Keep old function for backward compatibility but use English format
    function formatDateTimeOld(date) {
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
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
