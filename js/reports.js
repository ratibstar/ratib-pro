/**
 * EN: Implements frontend interaction behavior in `js/reports.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/reports.js`.
 */
// Reports Dashboard JavaScript

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

let currentCategory = 'agents';
let performanceChart = null;
let revenueChart = null;
let currentData = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeReports();
});

function initializeReports() {
    // Remove inline onclick handlers and add event listeners
    document.querySelectorAll('[data-action="refresh-reports"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof refreshReports === 'function') {
                refreshReports();
            }
        });
    });
    
    document.querySelectorAll('[data-action="export-report"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof exportReport === 'function') {
                exportReport();
            }
        });
    });
    
    document.querySelectorAll('[data-action="print-report"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof printReport === 'function') {
                printReport();
            }
        });
    });
    
    document.querySelectorAll('[data-action="go-to-individual-reports"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof goToIndividualReports === 'function') {
                goToIndividualReports();
            }
        });
    });

    // Initialize date range picker
    initializeDateRangePicker();
    
    // Load initial data for agents category
    loadCategoryData('agents');
    
    // Handle category switching
    setupCategorySwitching();
    
    // Handle filter changes
    setupFilters();
    
    // Handle total reports card click
    setupTotalReportsCard();
    
    // Handle tab switching
    setupTabs();
}

function initializeDateRangePicker() {
    if (typeof moment !== 'undefined' && moment.locale) moment.locale('en');
    if (typeof $ !== 'undefined' && $.fn.daterangepicker) {
        var enLocale = {
            format: 'YYYY-MM-DD',
            applyLabel: 'Apply',
            cancelLabel: 'Cancel',
            fromLabel: 'From',
            toLabel: 'To',
            customRangeLabel: 'Custom',
            daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            firstDay: 0
        };
        $('#dateRangePicker').daterangepicker({
            opens: 'left',
            locale: enLocale
        }, function(start, end) {
            applyFilters();
        });
    } else {
        // Fallback if daterangepicker not loaded
        $('#dateRangePicker').on('change', function() {
            applyFilters();
        });
    }
}

function setupCategorySwitching() {
    document.addEventListener('click', function(e) {
        const categoryCard = e.target.closest('[data-action="switch-category"]');
        if (categoryCard) {
            const category = categoryCard.dataset.category;
            if (category) {
                switchCategory(category);
                
                // Update active state
                document.querySelectorAll('.category-card').forEach(card => {
                    card.classList.remove('active');
                });
                categoryCard.classList.add('active');
            }
        }
    });
}

function setupFilters() {
    const statusFilter = document.getElementById('statusFilter');
    const sortBy = document.getElementById('sortBy');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
    
    if (sortBy) {
        sortBy.addEventListener('change', applyFilters);
    }
}

function setupTotalReportsCard() {
    const totalReportsCard = document.querySelector('.total-reports-card[data-href]');
    if (totalReportsCard) {
        totalReportsCard.addEventListener('click', function() {
            const href = this.dataset.href;
            if (href) {
                globalThis.location.href = href;
            }
        });
    }
}

function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            
            // Update active state
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Switch tab content
            switchTab(tab);
        });
    });
}

function switchTab(tab) {
    // Hide all tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active', 'show-tab-content');
        content.classList.add('hide-tab-content');
    });
    
    // Show selected tab content
    const tabContent = document.getElementById(tab);
    if (tabContent) {
        tabContent.classList.add('active', 'show-tab-content');
        tabContent.classList.remove('hide-tab-content');
        
        // Update content based on tab
        if (tab === 'summary' || tab === 'details') {
            if (currentData?.tableData) {
                updateTable(currentData.tableData);
            }
        }
    }
}

function switchCategory(category) {
    currentCategory = category;
    loadCategoryData(category);
}

function loadCategoryData(category) {
    showLoading();
    
    const filters = getFilters();
    const params = new URLSearchParams({
        action: 'get_category_data',
        category: category,
        ...filters
    });
    
    const url = `${getApiBase()}/reports/reports.php?${params}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentData = data.data;
                
                // Show analytics cards at the top (use stats data which has the correct structure)
                updateAnalyticsTop(data.data.stats);
                updateCharts(data.data.charts);
                updateTable(data.data.tableData);
            } else {
                showError('Failed to load data: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            showError('Failed to load data. Please try again. Error: ' + error.message);
        })
        .finally(() => {
            hideLoading();
        });
}

function getFilters() {
    const dateRange = document.getElementById('dateRangePicker')?.value || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    const sortBy = document.getElementById('sortBy')?.value || 'date';
    
    const filters = {
        status: status,
        sort_by: sortBy
    };
    
    if (dateRange) {
        const dates = dateRange.split(' - ');
        if (dates.length === 2) {
            filters.start_date = dates[0];
            filters.end_date = dates[1];
        }
    }
    
    return filters;
}

function applyFilters() {
    loadCategoryData(currentCategory);
}

function updateCharts(charts) {
    if (!charts) return;
    
    // Update performance chart
    if (charts.performance) {
        updateChart('performanceChart', charts.performance);
    }
    
    // Update revenue chart
    if (charts.revenue) {
        updateChart('revenueChart', charts.revenue);
    }
}

function updateChart(canvasId, chartConfig) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        canvas.parentElement.innerHTML = '<p class="chart-error-message">Chart library not loaded</p>';
        return;
    }
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (canvasId === 'performanceChart' && performanceChart) {
        performanceChart.destroy();
        performanceChart = null;
    } else if (canvasId === 'revenueChart' && revenueChart) {
        revenueChart.destroy();
        revenueChart = null;
    }
    
    // Validate chart config
    if (!chartConfig?.data) {
        return;
    }
    
    // Ensure we have labels and datasets
    const chartData = {
        labels: chartConfig.data.labels || [],
        datasets: chartConfig.data.datasets || []
    };
    
    // If no data, show message
    if (chartData.labels.length === 0 || chartData.datasets.length === 0) {
        canvas.parentElement.innerHTML = '<p class="chart-empty-message">No data available for this period</p>';
        return;
    }
    
    // Create new chart
    try {
        const chart = new Chart(ctx, {
            type: chartConfig.type || 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#fff',
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#999', font: { size: 10 } },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        ticks: { color: '#999', font: { size: 10 } },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        beginAtZero: true
                    }
                }
            }
        });
        
        if (canvasId === 'performanceChart') {
            performanceChart = chart;
        } else if (canvasId === 'revenueChart') {
            revenueChart = chart;
        }
    } catch (error) {
        canvas.parentElement.innerHTML = '<p class="chart-error-message chart-error-critical">Error loading chart</p>';
    }
}

function updateTable(tableData) {
    // Prepare data: Summary shows top active items, Detailed View shows all
    let summaryData = [];
    const detailedData = Array.isArray(tableData) ? [...tableData] : [];
    
    if (tableData && Array.isArray(tableData) && tableData.length > 0) {
        // Summary: Show only active/important items (top 10), sorted by performance/revenue
        const activeItems = tableData.filter(row => {
            const status = (row.status || 'unknown').toLowerCase();
            return status === 'active' || status === 'approved' || status === 'completed';
        });
        
        if (activeItems.length > 0) {
            // Sort active items by performance/revenue and take top 10
            summaryData = activeItems
                .sort((a, b) => {
                    // Sort by performance (higher first) or revenue
                    const perfA = Number.parseFloat((a.performance || '0%').replace('%', '')) || 0;
                    const perfB = Number.parseFloat((b.performance || '0%').replace('%', '')) || 0;
                    if (perfB !== perfA) return perfB - perfA;
                    
                    const revA = Number.parseFloat((a.revenue || '$0').replaceAll(/[^0-9.-]/g, '')) || 0;
                    const revB = Number.parseFloat((b.revenue || '$0').replaceAll(/[^0-9.-]/g, '')) || 0;
                    return revB - revA;
                })
                .slice(0, 10); // Top 10 for summary
        } else {
            // If no active items, show top 10 by date
            summaryData = [...tableData]
                .sort((a, b) => {
                    const dateA = new Date(a.joinDate || a.created_at || 0);
                    const dateB = new Date(b.joinDate || b.created_at || 0);
                    return dateB - dateA;
                })
                .slice(0, 10);
        }
    }
    
    const headers = getTableHeaders(currentCategory);
    const colCount = headers.length;
    const emptyRow = `<tr><td colspan="${colCount}" class="table-empty-message">No data available</td></tr>`;
    
    // Update Summary tab (condensed view - top active items only)
    const summaryTableBody = document.querySelector('#summary .reports-table tbody');
    const summaryTableHead = document.querySelector('#summary .reports-table thead');
    
    if (summaryTableBody) {
        summaryTableBody.innerHTML = '';
        if (!summaryData || summaryData.length === 0) {
            summaryTableBody.innerHTML = emptyRow;
        } else {
            summaryData.forEach((row) => {
                const tr = createTableRow(row);
                summaryTableBody.appendChild(tr);
            });
        }
    }
    
    if (summaryTableHead) {
        summaryTableHead.innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';
    }
    
    // Update Detailed View tab (full data - ALL items)
    const detailsTableBody = document.querySelector('#details .reports-table tbody');
    const detailsTableHead = document.querySelector('#details .reports-table thead');
    
    if (detailsTableBody) {
        detailsTableBody.innerHTML = '';
        if (!detailedData || detailedData.length === 0) {
            detailsTableBody.innerHTML = emptyRow;
        } else {
            detailedData.forEach((row) => {
                const tr = createTableRow(row);
                detailsTableBody.appendChild(tr);
            });
        }
    }
    
    if (detailsTableHead) {
        detailsTableHead.innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';
    }
    
    // Helper function to create table row
    function createTableRow(row) {
        const tr = document.createElement('tr');
        const status = (row.status || 'unknown').toLowerCase();
        const statusClass = ['active', 'pending', 'completed', 'inactive', 'suspended', 'unknown'].includes(status) 
            ? status 
            : 'unknown';
        
        tr.innerHTML = `
            <td>${escapeHtml(row.name || 'N/A')}</td>
            <td><span class="status-badge status-${statusClass}">${escapeHtml((row.status || 'unknown').toUpperCase())}</span></td>
            <td>${escapeHtml(row.revenue || 'N/A')}</td>
            <td>${escapeHtml(row.performance || 'N/A')}</td>
            <td>${escapeHtml(row.joinDate || 'N/A')}</td>
            <td>
                <button class="btn-view" data-action="view-details" data-id="${row.id}" data-category="${currentCategory}" title="View Details">
                    <i class="fas fa-eye"></i> View
                </button>
            </td>
        `;
        // Add event listener after innerHTML is set
        const viewBtn = tr.querySelector('[data-action="view-details"]');
        if (viewBtn) {
            viewBtn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const category = this.getAttribute('data-category');
                if (typeof viewDetails === 'function') {
                    viewDetails(id, category);
                }
            });
        }
        return tr;
    }
    
}

function updateAnalyticsTop(statsData) {
    // Display analytics cards at the top (in quick-stats section)
    // Make sure we get the FIRST .quick-stats container (the one at the top)
    const reportFilters = document.querySelector('.report-filters');
    let quickStatsContainer = reportFilters?.nextElementSibling;
    
    // Fallback to querySelector if nextElementSibling doesn't work
    if (!quickStatsContainer || !(quickStatsContainer.classList && quickStatsContainer.classList.contains('quick-stats'))) {
        const containers = document.querySelectorAll('.quick-stats');
        if (containers.length > 0) {
            // Use the first one (should be at the top)
            quickStatsContainer = containers[0];
        } else {
            return;
        }
    }
    
    if (!quickStatsContainer) {
        return;
    }
    
    // Clear existing content
    quickStatsContainer.innerHTML = '';
    
    if (!statsData || statsData.length === 0) {
        quickStatsContainer.innerHTML = '<p class="empty-stats-message">No statistics available</p>';
        return;
    }
    
    // Ensure container is visible and has proper styling
    quickStatsContainer.classList.add('show-quick-stats', 'expanded');
    
    statsData.forEach(item => {
        const card = document.createElement('div');
        card.className = 'stat-card';
        // Handle 0 values correctly - only use 'N/A' if value is null/undefined, not if it's 0
        const value = (item.value !== null && item.value !== undefined) ? item.value : 'N/A';
        const label = item.label || 'N/A';
        const icon = item.icon || 'fas fa-chart-bar';
        const color = item.color || '#667eea';
        
        const colorClass = getStatIconColorClass(color);
        card.innerHTML = `
            <i class="${icon} ${colorClass}"></i>
            <div class="stat-info">
                <span class="stat-value">${escapeHtml(value)}</span>
                <span class="stat-label">${escapeHtml(label)}</span>
            </div>
        `;
        quickStatsContainer.appendChild(card);
    });
}


function getStatIconColorClass(color) {
    const colorMap = {
        '#667eea': 'stat-icon-primary',
        '#4CAF50': 'stat-icon-success',
        '#FF9800': 'stat-icon-warning',
        '#2196F3': 'stat-icon-info',
        '#f44336': 'stat-icon-danger'
    };
    return colorMap[color] || 'stat-icon-primary';
}

function getTableHeaders(category) {
    const headers = {
        'agents': ['Name', 'Status', 'Revenue', 'Performance', 'Join Date', 'Actions'],
        'subagents': ['Name', 'Status', 'Commission', 'Performance', 'Join Date', 'Actions'],
        'workers': ['Name', 'Status', 'Salary', 'Performance', 'Hire Date', 'Actions'],
        'cases': ['Case Number', 'Status', 'Amount', 'Progress', 'Created Date', 'Actions'],
        'hr': ['Name', 'Status', 'Salary', 'Performance', 'Hire Date', 'Actions'],
        'financial': ['Description', 'Type', 'Amount', 'Balance', 'Date', 'Actions']
    };
    
    return headers[category] || headers['agents'];
}

function refreshReports() {
    loadCategoryData(currentCategory);
}

function exportReport() {
    const filters = getFilters();
    const params = new URLSearchParams({
        action: 'export_data',
        category: currentCategory,
        format: 'csv',
        ...filters
    });
    
    globalThis.location.href = `${getApiBase()}/reports/reports.php?${params}`;
}

function printReport() {
    globalThis.print();
}

function goToIndividualReports() {
    globalThis.location.href = getBaseUrl() + '/pages/individual-reports.php';
}

function viewDetails(id, category) {
    // Navigate to detail page based on category
    const routes = {
        'agents': getBaseUrl() + '/pages/agent.php?id=',
        'subagents': getBaseUrl() + '/pages/subagent.php?id=',
        'workers': getBaseUrl() + '/pages/Worker.php?id=',
        'cases': getBaseUrl() + '/pages/cases/cases-table.php?id=',
        'hr': getBaseUrl() + '/pages/hr.php?id=',
        'financial': getBaseUrl() + '/pages/accounting.php?id='
    };
    
    const route = routes[category] || routes['agents'];
    globalThis.location.href = route + id;
}

function showLoading() {
    const container = document.querySelector('.reports-container');
    if (container) {
        const loading = document.createElement('div');
        loading.id = 'reports-loading';
        loading.className = 'loading-overlay';
        loading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        container.appendChild(loading);
    }
}

function hideLoading() {
    const loading = document.getElementById('reports-loading');
    if (loading) {
        loading.remove();
    }
}

function showError(message) {
    const container = document.querySelector('.reports-container');
    if (container) {
        const error = document.createElement('div');
        error.className = 'error-message';
        error.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        container.insertBefore(error, container.firstChild);
        
        setTimeout(() => {
            error.remove();
        }, 5000);
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Global functions for onclick handlers
globalThis.refreshReports = refreshReports;
globalThis.exportReport = exportReport;
globalThis.printReport = printReport;
globalThis.goToIndividualReports = goToIndividualReports;
globalThis.viewDetails = viewDetails;
