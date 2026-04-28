/**
 * EN: Implements frontend interaction behavior in `js/individual-reports.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/individual-reports.js`.
 */
/**
 * Individual Reports JavaScript
 * Handles individual entity report generation and display
 * Force English locale for all dates - no Arabic.
 */

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

class IndividualReports {
    constructor() {
        this.currentEntity = null;
        this.currentEntityType = null;
        this.currentTab = 'overview';
        this.charts = {};
        this.data = {};
        this.filters = {
            dateRange: null,
            activityType: 'all',
            activityDateRange: null
        };
        
        this.init();
    }

    init() {
        this.initializeDatePickers();
        this.bindEvents();
        this.hideReportContent();
        // Skip checkConnection - test-connection.php may not exist
        // this.checkConnection();
    }

    initializeDatePickers() {
        // English locale for Flatpickr (no Arabic) - set globally before creating instances
        const flatpickrEnglishLocale = {
            weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] },
            months: { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] },
            firstDayOfWeek: 0,
            rangeSeparator: ' to ',
            weekAbbreviation: 'Wk',
            scrollTitle: 'Scroll to increment',
            toggleTitle: 'Click to toggle',
            amPM: ['AM','PM'],
            yearAriaLabel: 'Year',
            monthAriaLabel: 'Month',
            hourAriaLabel: 'Hour',
            minuteAriaLabel: 'Minute',
            time_24hr: false,
            todayLabel: 'Today',
            clearLabel: 'Clear',
            closeLabel: 'Close'
        };
        
        // Set Flatpickr and Moment locales globally
        if (typeof moment !== 'undefined' && moment.locale) moment.locale('en');
        if (typeof flatpickr !== 'undefined' && flatpickr.localize) {
            flatpickr.localize(flatpickrEnglishLocale);
        }

        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (dateFromInput && dateToInput) {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            const startStr = formatDate(thirtyDaysAgo);
            const endStr = formatDate(today);
            dateFromInput.value = startStr;
            dateToInput.value = endStr;
            
            this.filters.dateRange = { start: startStr, end: endStr };
            
            // Use Flatpickr with English locale so calendar and numbers are always in English
            if (typeof flatpickr !== 'undefined') {
                const fpOpts = { theme: 'dark', locale: flatpickrEnglishLocale, dateFormat: 'Y-m-d', altInput: false, allowInput: true, enableTime: false, clickOpens: true, disableMobile: 'true' };
                flatpickr(dateFromInput, { ...fpOpts, defaultDate: startStr, onChange: () => this.updateDateRange() });
                flatpickr(dateToInput, { ...fpOpts, defaultDate: endStr, onChange: () => this.updateDateRange() });
            } else {
                dateFromInput.addEventListener('change', () => this.updateDateRange());
                dateToInput.addEventListener('change', () => this.updateDateRange());
            }
        }
        
        // Activity date range picker — force English (no Arabic)
        if (typeof $ !== 'undefined' && $.fn.daterangepicker) {
            const drpLocale = {
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
            $('#activityDateRange').daterangepicker({
                startDate: moment().subtract(7, 'days'),
                endDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()]
                },
                locale: drpLocale
            }, (start, end) => {
                this.filters.activityDateRange = {
                    start: start.format('YYYY-MM-DD'),
                    end: end.format('YYYY-MM-DD')
                };
                this.loadActivities();
            });
        }
    }
    
    updateDateRange() {
        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        
        if (dateFromInput && dateToInput) {
            this.filters.dateRange = {
                start: dateFromInput.value || null,
                end: dateToInput.value || null
            };
            
            // Only reload if entity is selected
            if (this.currentEntity && this.currentEntityType) {
                this.loadIndividualReport();
            }
        }
    }

    bindEvents() {
        // Close button handler - use direct event listener
        setTimeout(() => {
            const closeBtn = document.getElementById('closeIndividualReportsBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeModal();
                });
                
                // Also handle clicks on the icon
                const closeIcon = closeBtn.querySelector('i');
                if (closeIcon) {
                    closeIcon.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.closeModal();
                    });
                }
            }
        }, 100);
        
        // Overlay click handler - close when clicking outside
        setTimeout(() => {
            const overlay = document.querySelector('.individual-reports-overlay');
            if (overlay) {
                overlay.addEventListener('click', (e) => {
                    // Only close if clicking directly on overlay, not on container
                    if (e.target === overlay) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.closeModal();
                    }
                });
            }
        }, 100);
        
        // Prevent container clicks from closing modal
        setTimeout(() => {
            const container = document.querySelector('.individual-reports-container');
            if (container) {
                container.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
        }, 100);
        
        // Also handle clicks on modal itself (outside container)
        setTimeout(() => {
            const modal = document.getElementById('individualReportsModal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    // If clicking on modal background (not container), close it
                    if (e.target === modal || e.target.classList.contains('individual-reports-overlay')) {
                        this.closeModal();
                    }
                });
            }
        }, 100);
        
        // Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('individualReportsModal');
                if (modal && modal.classList.contains('show')) {
                    this.closeModal();
                }
            }
        });
        
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.getAttribute('data-tab');
                this.switchTab(tab);
            });
        });

        // Activity type filter
        document.getElementById('activityTypeFilter')?.addEventListener('change', (e) => {
            this.filters.activityType = e.target.value;
            this.loadActivities();
        });

        // Document upload form
        document.getElementById('documentUploadForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            this.handleDocumentUpload(formData);
        });
        
        // Action button handlers - use event delegation with better icon handling
        const self = this;
        const handleButtonClick = function(e) {
            // Find the button element (could be clicked directly or via icon inside)
            let button = e.target;
            
            // If clicked on icon or text inside button, find the button parent
            if (button.tagName === 'I' || button.tagName === 'SPAN') {
                button = button.closest('button');
            }
            
            // If still not a button, try closest with data-action
            if (!button || button.tagName !== 'BUTTON') {
                button = e.target.closest('[data-action]');
            }
            
            if (!button) return;
            
            const action = button.getAttribute('data-action');
            if (!action) return;
            
            // Prevent default and stop propagation
            e.preventDefault();
            e.stopPropagation();
            
            // Execute action
            switch(action) {
                case 'back-to-reports':
                    // Navigate back to Reports Dashboard
                    window.location.href = getBaseUrl() + '/pages/reports.php';
                    break;
                case 'refresh-report':
                    if (window.individualReports) {
                        window.individualReports.loadIndividualReport();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'export-report':
                    if (window.individualReports && window.individualReports.currentEntity) {
                        const params = new URLSearchParams({
                            action: 'export_report',
                            entity_type: window.individualReports.currentEntityType,
                            entity_id: window.individualReports.currentEntity,
                            format: 'csv'
                        });
                        window.location.href = `${getApiBase()}/reports/individual-reports.php?${params.toString()}`;
                    } else {
                        alert('Please select an entity first');
                    }
                    break;
                case 'print-report':
                    if (window.individualReports) {
                        printIndividualReport();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'edit-entity':
                    if (window.individualReports) {
                        window.individualReports.editEntity();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'view-entity':
                    if (window.individualReports) {
                        window.individualReports.viewEntity();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'cancel-selection':
                    if (window.individualReports) {
                        window.individualReports.cancelSelection();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'upload-document':
                    if (window.individualReports) {
                        window.individualReports.uploadDocument();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'generate-document':
                    if (window.individualReports) {
                        window.individualReports.generateDocument();
                    } else {
                        alert('Reports system not initialized. Please refresh the page.');
                    }
                    break;
                case 'load-entity-options':
                    // This is for the select element, handled separately
                    break;
                case 'load-individual-report':
                    // This is for the select element, handled separately
                    break;
                case 'close-modal':
                    const modalId = button.getAttribute('data-modal');
                    if (modalId && window.individualReports) {
                        window.individualReports.closeModal(modalId);
                    }
                    break;
                case 'view-document':
                    const viewDocId = button.getAttribute('data-doc-id');
                    if (viewDocId && window.individualReports) {
                        window.individualReports.viewDocument(viewDocId);
                    }
                    break;
                case 'download-document':
                    const downloadDocId = button.getAttribute('data-doc-id');
                    if (downloadDocId && window.individualReports) {
                        window.individualReports.downloadDocument(downloadDocId);
                    }
                    break;
                case 'delete-document':
                    const deleteDocId = button.getAttribute('data-doc-id');
                    if (deleteDocId && window.individualReports) {
                        window.individualReports.deleteDocument(deleteDocId);
                    }
                    break;
            }
        };
        
        // Add event listener to document
        document.addEventListener('click', handleButtonClick, true); // Use capture phase
        
        // Also add direct listeners to buttons as fallback
        setTimeout(() => {
            const buttons = document.querySelectorAll('[data-action]');
            buttons.forEach(btn => {
                if (btn.tagName === 'BUTTON') {
                    btn.addEventListener('click', handleButtonClick);
                }
            });
        }, 500);
        
        // Direct back button handler as backup
        setTimeout(() => {
            const backBtn = document.querySelector('[data-action="back-to-reports"]');
            if (backBtn) {
                backBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.href = getBaseUrl() + '/pages/reports.php';
                });
            }
        }, 200);
        
        // Direct print button handler as backup
        setTimeout(() => {
            const printBtn = document.querySelector('[data-action="print-report"]');
            if (printBtn) {
                printBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    printIndividualReport();
                });
            }
        }, 200);
        
        // Entity type change handler
        document.getElementById('entityType')?.addEventListener('change', () => {
            this.loadEntityOptions();
        });
        
        // Entity select change handler
        document.getElementById('entitySelect')?.addEventListener('change', () => {
            this.loadIndividualReport();
        });
    }

    async loadEntityOptions() {
        const entityType = document.getElementById('entityType').value;
        const entitySelect = document.getElementById('entitySelect');
        
        if (!entityType) {
            entitySelect.disabled = true;
            entitySelect.innerHTML = '<option value="">Select an entity first</option>';
            return;
        }

        try {
            entitySelect.innerHTML = '<option value="">Loading entities...</option>';
            entitySelect.disabled = true;
            
            // Fetch real data from API with cache busting
            const timestamp = new Date().getTime();
            const apiUrl = `${getApiBase()}/reports/individual-reports.php?action=get_entities&entity_type=${entityType}&t=${timestamp}`;
            const response = await fetch(apiUrl);
            
            let data;
            const responseText = await response.text();
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(`Invalid response from server: ${responseText.substring(0, 200)}`);
            }
            
            if (!response.ok) {
                const errorMsg = data?.message || data?.error || `HTTP error! status: ${response.status}`;
                throw new Error(errorMsg);
            }
            
            if (data.success) {
                entitySelect.disabled = false;
                entitySelect.innerHTML = '<option value="">Select an entity</option>' +
                    data.data.map(entity => 
                        `<option value="${entity.id}">${entity.name} (${entity.status})</option>`
                    ).join('');
            } else {
                entitySelect.innerHTML = '<option value="">Error loading entities</option>';
                this.showError('Failed to load entities: ' + (data.message || 'Unknown error'));
            }
                
        } catch (error) {
            entitySelect.innerHTML = '<option value="">Error loading entities</option>';
            this.showError('Failed to load entities: ' + (error.message || 'Unknown error'));
        }
    }

    getMockEntities(entityType) {
        const mockData = {
            'agents': [
                { id: 1, name: 'John Smith' },
                { id: 2, name: 'Sarah Johnson' },
                { id: 3, name: 'Mike Wilson' },
                { id: 4, name: 'Lisa Davis' }
            ],
            'subagents': [
                { id: 1, name: 'Alex Brown' },
                { id: 2, name: 'Emma Wilson' },
                { id: 3, name: 'David Lee' },
                { id: 4, name: 'Maria Garcia' }
            ],
            'workers': [
                { id: 1, name: 'Ahmed Hassan' },
                { id: 2, name: 'Mohammed Ali' },
                { id: 3, name: 'Fatima Ahmed' },
                { id: 4, name: 'Omar Khalil' }
            ],
            'cases': [
                { id: 1, name: 'Case #2024-001' },
                { id: 2, name: 'Case #2024-002' },
                { id: 3, name: 'Case #2024-003' },
                { id: 4, name: 'Case #2024-004' }
            ],
            'hr': [
                { id: 1, name: 'Jennifer Smith' },
                { id: 2, name: 'Robert Johnson' },
                { id: 3, name: 'Emily Davis' },
                { id: 4, name: 'Michael Brown' }
            ]
        };
        
        return mockData[entityType] || [];
    }

    async loadIndividualReport() {
        const entityId = document.getElementById('entitySelect').value;
        const entityType = document.getElementById('entityType').value;
        
        if (!entityId || !entityType) {
            this.hideReportContent();
            return;
        }

        this.currentEntity = entityId;
        this.currentEntityType = entityType;

        try {
            this.showLoading();
            
            // Build API URL with date range parameters
            const params = new URLSearchParams({
                action: 'get_individual_report',
                entity_type: entityType,
                entity_id: entityId
            });
            
            // Add date range if available (read from inputs)
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            
            if (dateFromInput && dateFromInput.value) {
                params.append('start_date', dateFromInput.value);
            }
            if (dateToInput && dateToInput.value) {
                params.append('end_date', dateToInput.value);
            }
            
            // Fetch real data from API
            const response = await fetch(`${getApiBase()}/reports/individual-reports.php?${params.toString()}`);
            
            let data;
            const responseText = await response.text();
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(`Invalid response from server: ${responseText.substring(0, 200)}`);
            }
            
            if (!response.ok) {
                const errorMsg = data?.message || data?.error || `HTTP error! status: ${response.status}`;
                throw new Error(errorMsg);
            }
            
            if (data && data.success) {
                this.data = data.data;
                
                // Safely update components with null checks
                if (data.data.entity) this.updateEntityHeader(data.data.entity);
                if (data.data.overview) this.updateOverview(data.data.overview);
                if (data.data.performance) this.updatePerformanceCharts(data.data.performance);
                if (data.data.financial) this.updateFinancialCharts(data.data.financial);
                if (data.data.activities) this.updateActivities(data.data.activities);
                if (data.data.documents) this.updateDocuments(data.data.documents);
                
                this.showReportContent();
            } else {
                const errorMsg = data?.message || 'Unknown error';
                this.showError('Failed to load report: ' + errorMsg);
            }
            
        } catch (error) {
            this.showError('Failed to load individual report: ' + (error.message || 'Unknown error'));
        } finally {
            this.hideLoading();
        }
    }

    createMockReportData(entityType, entityId) {
        const entityNames = {
            'agents': ['John Smith', 'Sarah Johnson', 'Mike Wilson', 'Lisa Davis'],
            'subagents': ['Alex Brown', 'Emma Wilson', 'David Lee', 'Maria Garcia'],
            'workers': ['Ahmed Hassan', 'Mohammed Ali', 'Fatima Ahmed', 'Omar Khalil'],
            'cases': ['Case #2024-001', 'Case #2024-002', 'Case #2024-003', 'Case #2024-004'],
            'hr': ['Jennifer Smith', 'Robert Johnson', 'Emily Davis', 'Michael Brown']
        };

        const entityName = entityNames[entityType]?.[entityId - 1] || `Entity ${entityId}`;
        
        return {
            entity: {
                name: entityName,
                status: 'active',
                type: entityType
            },
            overview: {
                metrics: [
                    { label: 'Total Revenue', value: '$12,450' },
                    { label: 'Performance Score', value: '95%' },
                    { label: 'Active Projects', value: '8' },
                    { label: 'Client Rating', value: '4.8/5' }
                ],
                activities: [
                    {
                        title: 'Login Activity',
                        description: 'User logged into the system',
                        time: '2 hours ago',
                        icon: 'fas fa-sign-in-alt'
                    },
                    {
                        title: 'Data Update',
                        description: 'Profile information updated',
                        time: '4 hours ago',
                        icon: 'fas fa-edit'
                    },
                    {
                        title: 'Document Upload',
                        description: 'New document uploaded',
                        time: '1 day ago',
                        icon: 'fas fa-upload'
                    }
                ]
            },
            performance: {
                trends: {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Performance',
                            data: [65, 78, 85, 92, 88, 95],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4
                        }]
                    }
                },
                goals: {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Remaining'],
                        datasets: [{
                            data: [75, 25],
                            backgroundColor: ['#4CAF50', '#333'],
                            borderWidth: 0
                        }]
                    }
                }
            },
            financial: {
                revenue: {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Revenue',
                            data: [12000, 15000, 18000, 22000, 19000, 25000],
                            backgroundColor: 'rgba(33, 150, 243, 0.8)',
                            borderColor: '#2196F3'
                        }]
                    }
                },
                commission: {
                    type: 'pie',
                    data: {
                        labels: ['Direct Commission', 'Team Commission', 'Bonus'],
                        datasets: [{
                            data: [60, 30, 10],
                            backgroundColor: ['#667eea', '#4CAF50', '#FF9800'],
                            borderWidth: 0
                        }]
                    }
                },
                transactions: [
                    {
                        date: '2024-01-15',
                        type: 'Commission',
                        amount: '$1,250.00',
                        status: 'completed',
                        description: 'Monthly commission payment'
                    },
                    {
                        date: '2024-01-10',
                        type: 'Bonus',
                        amount: '$500.00',
                        status: 'completed',
                        description: 'Performance bonus'
                    }
                ]
            },
            activities: [
                {
                    title: 'Login Activity',
                    time: '2 hours ago',
                    description: 'User logged into the system',
                    type: 'login',
                    user: 'System'
                },
                {
                    title: 'Data Update',
                    time: '4 hours ago',
                    description: 'Profile information updated',
                    type: 'update',
                    user: 'Admin'
                }
            ],
            documents: [
                {
                    id: '1',
                    title: 'Contract Agreement',
                    type: 'Contract',
                    date: '2024-01-15',
                    size: '2.5 MB',
                    icon: 'fas fa-file-contract'
                },
                {
                    id: '2',
                    title: 'Performance Report',
                    type: 'Report',
                    date: '2024-01-10',
                    size: '1.2 MB',
                    icon: 'fas fa-chart-line'
                }
            ]
        };
    }

    updateEntityHeader(entity) {
        const entityNameEl = document.getElementById('entityName');
        const entityTypeTextEl = document.getElementById('entityTypeText');
        const entityStatusEl = document.getElementById('entityStatus');
        
        if (entityNameEl) entityNameEl.textContent = entity.name || 'Unknown';
        if (entityTypeTextEl) entityTypeTextEl.textContent = this.getEntityTypeLabel(this.currentEntityType);
        if (entityStatusEl) {
            entityStatusEl.textContent = entity.status || 'UNKNOWN';
            entityStatusEl.className = `status-badge status-${(entity.status || 'unknown').toLowerCase()}`;
        }
    }

    getEntityTypeLabel(type) {
        const labels = {
            'agents': 'Agent',
            'subagents': 'SubAgent',
            'workers': 'Worker',
            'cases': 'Case',
            'hr': 'Employee'
        };
        return labels[type] || 'Entity';
    }

    updateOverview(overview) {
        if (!overview) {
            this.showEmptyState('overview', 'No overview data available');
            return;
        }
        
        // Update metrics with modern animations
        const keyMetrics = document.getElementById('keyMetrics');
        if (keyMetrics) {
            if (overview.metrics && overview.metrics.length > 0) {
                keyMetrics.innerHTML = overview.metrics.map((metric, index) => {
                    return `
                    <div class="metric-item modern-metric metric-animate" data-delay="${index * 0.1}">
                        <div class="metric-icon">
                            <i class="${this.getMetricIcon(metric.label)}"></i>
                        </div>
                        <span class="metric-value">${this.formatMetricValue(metric.value)}</span>
                        <span class="metric-label">${metric.label}</span>
                    </div>
                `;
                }).join('');
                
                // Apply animation delays via CSS custom properties
                setTimeout(() => {
                    keyMetrics.querySelectorAll('.metric-animate[data-delay]').forEach(el => {
                        const delay = Number.parseFloat(el.getAttribute('data-delay')) || 0;
                        el.style.setProperty('--animation-delay', `${delay}s`);
                    });
                }, 0);
            } else {
                keyMetrics.innerHTML = '<div class="empty-metrics"><i class="fas fa-chart-bar"></i><p>No metrics available</p></div>';
            }
        }

        // Update activities with modern timeline
        const recentActivity = document.getElementById('recentActivity');
        if (recentActivity) {
            if (overview.activities && overview.activities.length > 0) {
                recentActivity.innerHTML = overview.activities.map((activity, index) => {
                    const activityType = activity.type || 'default';
                    return `
                    <div class="timeline-item modern-timeline timeline-animate" data-delay="${index * 0.1}">
                        <div class="timeline-icon timeline-icon-${activityType}">
                            <i class="${activity.icon || 'fas fa-circle'}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">${escapeHtml(activity.title || 'Activity')}</div>
                            <div class="timeline-description">${escapeHtml(activity.description || '')}</div>
                            <div class="timeline-time">
                                <i class="fas fa-clock"></i> ${this.formatTime(activity.time || '')}
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
                
                // Apply animation delays via CSS custom properties
                setTimeout(() => {
                    recentActivity.querySelectorAll('.timeline-animate[data-delay]').forEach(el => {
                        const delay = Number.parseFloat(el.getAttribute('data-delay')) || 0;
                        el.style.setProperty('--animation-delay', `${delay}s`);
                    });
                }, 0);
            } else {
                recentActivity.innerHTML = '<div class="empty-timeline"><i class="fas fa-history"></i><p>No recent activities</p></div>';
            }
        }
    }
    
    getMetricIcon(label) {
        const icons = {
            'Total Commissions': 'fas fa-dollar-sign',
            'Total Salary': 'fas fa-money-bill-wave',
            'Active Cases': 'fas fa-briefcase',
            'Performance Score': 'fas fa-chart-line',
            'Client Satisfaction': 'fas fa-smile',
            'Attendance Rate': 'fas fa-calendar-check',
            'Team Size': 'fas fa-users',
            'Department': 'fas fa-building',
            'Position': 'fas fa-briefcase',
            'Case Status': 'fas fa-info-circle',
            'Case Number': 'fas fa-hashtag',
            'Created Date': 'fas fa-calendar',
            'Priority': 'fas fa-flag'
        };
        return icons[label] || 'fas fa-chart-bar';
    }
    
    formatMetricValue(value) {
        if (typeof value === 'string' && value.includes('$')) return value;
        if (typeof value === 'string' && value.includes('%')) return value;
        if (typeof value === 'number') {
            if (value >= 1000) return value.toLocaleString();
            return value.toString();
        }
        return value || 'N/A';
    }
    
    getActivityColor(type) {
        const colors = {
            'login': 'linear-gradient(135deg, #667eea, #764ba2)',
            'transaction': 'linear-gradient(135deg, #4CAF50, #45a049)',
            'update': 'linear-gradient(135deg, #FF9800, #f57c00)',
            'document': 'linear-gradient(135deg, #2196F3, #1976D2)',
            'default': 'linear-gradient(135deg, #667eea, #764ba2)'
        };
        return colors[type] || colors.default;
    }
    
    formatTime(timeStr) {
        if (!timeStr) return 'Unknown time';
        try {
            const date = new Date(timeStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            return date.toLocaleDateString();
        } catch (e) {
            return timeStr;
        }
    }
    
    // escapeHtml() is now a global function defined in reports.js
    // Using global escapeHtml() instead of class method to avoid duplication
    
    showEmptyState(tab, message) {
        const tabContent = document.getElementById(tab);
        if (tabContent) {
            const existingContent = tabContent.querySelector('.tab-content-inner');
            if (existingContent) {
                existingContent.innerHTML = `
                    <div class="empty-state-modern">
                        <i class="fas fa-inbox"></i>
                        <h3>${message}</h3>
                        <p>No data available for this section</p>
                    </div>
                `;
            }
        }
    }

    updatePerformanceCharts(performance) {
        if (!performance) {
            this.showEmptyState('performance', 'No performance data available');
            return;
        }
        
        // Update performance trends chart with modern styling
        if (performance.trends && performance.trends.chart) {
            const trendsData = {
                type: 'line',
                data: performance.trends.chart,
                options: {
                    ...this.getModernChartOptions(),
                    plugins: {
                        ...this.getModernChartOptions().plugins,
                        title: {
                            display: true,
                            text: 'Performance Trends',
                            color: '#fff',
                            font: { size: 16, weight: 'bold' }
                        }
                    }
                }
            };
            this.updateChart('performanceChart', trendsData);
        } else {
            this.showChartEmptyState('performanceChart', 'No performance trends data');
        }
        
        // Update goal progress chart
        if (performance.goals && performance.goals.chart) {
            const goalsData = {
                type: 'doughnut',
                data: performance.goals.chart,
                options: {
                    ...this.getModernChartOptions(),
                    plugins: {
                        ...this.getModernChartOptions().plugins,
                        title: {
                            display: true,
                            text: 'Goal Progress',
                            color: '#fff',
                            font: { size: 16, weight: 'bold' }
                        }
                    }
                }
            };
            this.updateChart('goalChart', goalsData);
        } else {
            this.showChartEmptyState('goalChart', 'No goal progress data');
        }
    }
    
    getModernChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#fff',
                        font: { size: 12 },
                        padding: 15,
                        usePointStyle: true
                    },
                    position: 'top'
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    cornerRadius: 12,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y || context.parsed}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#ccc',
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)',
                        drawBorder: false
                    }
                },
                y: {
                    ticks: {
                        color: '#ccc',
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)',
                        drawBorder: false
                    },
                    beginAtZero: true
                }
            }
        };
    }
    
    showChartEmptyState(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            const parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = `
                    <div class="chart-empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>${message}</p>
                    </div>
                `;
            }
        }
    }

    updateFinancialCharts(financial) {
        if (!financial) return;
        
        // Update revenue chart
        if (financial.revenue && financial.revenue.chart) {
            this.updateChart('revenueChart', {
                type: 'bar',
                data: financial.revenue.chart
            });
        }
        
        // Update commission chart
        if (financial.commission) {
            const commissionData = {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending'],
                    datasets: [{
                        data: [financial.commission.paid || 0, financial.commission.pending || 0],
                        backgroundColor: ['#4CAF50', '#FF9800']
                    }]
                }
            };
            this.updateChart('commissionChart', commissionData);
        }
        
        // Update transaction table
        if (financial.transactions) {
            this.updateTransactionTable(financial.transactions);
        }
    }

    updateChart(canvasId, chartData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        
        if (!chartData || !chartData.data) {
            this.showChartEmptyState(canvasId, 'No chart data available');
            return;
        }

        // Destroy existing chart if it exists
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        // Merge custom options with modern defaults
        const options = {
            ...this.getModernChartOptions(),
            ...(chartData.options || {})
        };

        this.charts[canvasId] = new Chart(ctx, {
            type: chartData.type || 'line',
            data: chartData.data,
            options: options
        });
    }

    updateTransactionTable(transactions) {
        const tbody = document.querySelector('#transactionTable tbody');
        if (!tbody) return;
        
        if (!transactions || transactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="empty-table-state">
                        <i class="fas fa-receipt"></i>
                        <p>No transactions found</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = transactions.map((transaction, index) => {
            const date = transaction.date ? new Date(transaction.date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            }) : 'N/A';
            const amount = transaction.amount ? (typeof transaction.amount === 'string' ? transaction.amount : '$' + Number.parseFloat(transaction.amount).toFixed(2)) : '$0.00';
            const type = (transaction.type || 'N/A').toUpperCase();
            const status = (transaction.status || 'completed').toLowerCase();
            const description = escapeHtml(transaction.description || 'No description');
            const amountClass = amount.includes('-') ? 'negative' : 'positive';
            
            return `
                <tr class="transaction-row transaction-animate" data-delay="${index * 0.05}">
                    <td><i class="fas fa-calendar-alt"></i> ${date}</td>
                    <td><span class="transaction-type">${type}</span></td>
                    <td class="transaction-amount ${amountClass}">
                        <i class="fas fa-${amount.includes('-') ? 'arrow-down' : 'arrow-up'}"></i>
                        ${amount}
                    </td>
                    <td><span class="status-badge status-${status}">${status.toUpperCase()}</span></td>
                    <td class="transaction-description">${description}</td>
                </tr>
            `;
        }).join('');
        
        // Apply animation delays via CSS custom properties
        setTimeout(() => {
            tbody.querySelectorAll('.transaction-animate[data-delay]').forEach(el => {
                const delay = Number.parseFloat(el.getAttribute('data-delay')) || 0;
                el.style.setProperty('--animation-delay', `${delay}s`);
            });
        }, 0);
    }

    async loadActivities() {
        if (!this.currentEntity || !this.currentEntityType) return;

        try {
            const params = new URLSearchParams({
                action: 'get_activities',
                entity_type: this.currentEntityType,
                entity_id: this.currentEntity,
                activity_type: this.filters.activityType,
                start_date: this.filters.activityDateRange?.start || '',
                end_date: this.filters.activityDateRange?.end || ''
            });

            const response = await fetch(`${getApiBase()}/reports/individual-reports.php?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.updateActivities(result.data);
            }
        } catch (error) {
            // Silent fail for activities
        }
    }

    updateActivities(activities) {
        const activityList = document.getElementById('activityList');
        if (!activityList) return;
        
        if (!activities || activities.length === 0) {
            activityList.innerHTML = `
                <div class="empty-activities">
                    <i class="fas fa-history"></i>
                    <h3>No activities found</h3>
                    <p>There are no activities to display for the selected filters.</p>
                </div>
            `;
            return;
        }
        
        activityList.innerHTML = activities.map((activity, index) => {
            const activityType = activity.type || 'default';
            const icon = this.getActivityIcon(activityType);
            
            return `
                <div class="activity-item modern-activity activity-animate" data-delay="${index * 0.1}">
                    <div class="activity-icon-wrapper activity-icon-${activityType}">
                        <i class="${icon}"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-header">
                            <div class="activity-title">${escapeHtml(activity.title || 'Activity')}</div>
                            <div class="activity-time">
                                <i class="fas fa-clock"></i> ${this.formatTime(activity.time || '')}
                            </div>
                        </div>
                        <div class="activity-description">${escapeHtml(activity.description || '')}</div>
                        <div class="activity-meta">
                            <span class="activity-tag">
                                <i class="fas fa-tag"></i> ${activity.type || 'general'}
                            </span>
                            ${activity.user ? `<span class="activity-user"><i class="fas fa-user"></i> ${escapeHtml(activity.user)}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Apply animation delays via CSS custom properties
        setTimeout(() => {
            activityList.querySelectorAll('.activity-animate[data-delay]').forEach(el => {
                const delay = Number.parseFloat(el.getAttribute('data-delay')) || 0;
                el.style.setProperty('--animation-delay', `${delay}s`);
            });
        }, 0);
    }
    
    getActivityIcon(type) {
        const icons = {
            'login': 'fas fa-sign-in-alt',
            'transaction': 'fas fa-exchange-alt',
            'update': 'fas fa-edit',
            'document': 'fas fa-file',
            'create': 'fas fa-plus-circle',
            'delete': 'fas fa-trash',
            'view': 'fas fa-eye',
            'default': 'fas fa-circle'
        };
        return icons[type] || icons.default;
    }

    updateDocuments(documents) {
        const documentsGrid = document.getElementById('documentsGrid');
        if (!documentsGrid) return;
        
        if (!documents || documents.length === 0) {
            documentsGrid.innerHTML = `
                <div class="empty-documents">
                    <i class="fas fa-folder-open"></i>
                    <h3>No documents found</h3>
                    <p>Upload documents to get started</p>
                    <button class="btn-primary" data-action="upload-document">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </div>
            `;
            return;
        }
        
        documentsGrid.innerHTML = documents.map((doc, index) => {
            const fileType = this.getFileType(doc.title || doc.type || '');
            const icon = doc.icon || this.getDocumentIcon(fileType);
            
            return `
                <div class="document-card modern-document document-animate" data-delay="${index * 0.1}" data-doc-id="${doc.id}">
                    <div class="document-icon-wrapper document-icon-${fileType}">
                        <i class="${icon}"></i>
                    </div>
                    <div class="document-info">
                        <div class="document-title">${escapeHtml(doc.title || 'Untitled Document')}</div>
                        <div class="document-meta">
                            <span><i class="fas fa-tag"></i> ${doc.type || 'Document'}</span>
                            <span><i class="fas fa-calendar"></i> ${this.formatTime(doc.date || '')}</span>
                            <span><i class="fas fa-weight"></i> ${doc.size || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="document-actions">
                        <button class="document-btn btn-view" data-action="view-document" data-doc-id="${doc.id}" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="document-btn btn-download" data-action="download-document" data-doc-id="${doc.id}" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="document-btn btn-delete" data-action="delete-document" data-doc-id="${doc.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        // Apply animation delays via CSS custom properties
        setTimeout(() => {
            documentsGrid.querySelectorAll('.document-animate[data-delay]').forEach(el => {
                const delay = Number.parseFloat(el.getAttribute('data-delay')) || 0;
                el.style.setProperty('--animation-delay', `${delay}s`);
            });
        }, 0);
    }
    
    getFileType(filename) {
        const ext = filename.split('.').pop()?.toLowerCase() || '';
        const types = {
            'pdf': 'pdf',
            'doc': 'word',
            'docx': 'word',
            'xls': 'excel',
            'xlsx': 'excel',
            'jpg': 'image',
            'jpeg': 'image',
            'png': 'image',
            'gif': 'image'
        };
        return types[ext] || 'file';
    }
    
    getDocumentIcon(type) {
        const icons = {
            'pdf': 'fas fa-file-pdf',
            'word': 'fas fa-file-word',
            'excel': 'fas fa-file-excel',
            'image': 'fas fa-file-image',
            'file': 'fas fa-file'
        };
        return icons[type] || icons.file;
    }
    
    getDocumentColor(type) {
        const colors = {
            'pdf': 'linear-gradient(135deg, #f44336, #d32f2f)',
            'word': 'linear-gradient(135deg, #2196F3, #1976D2)',
            'excel': 'linear-gradient(135deg, #4CAF50, #388E3C)',
            'image': 'linear-gradient(135deg, #FF9800, #F57C00)',
            'file': 'linear-gradient(135deg, #667eea, #764ba2)'
        };
        return colors[type] || colors.file;
    }
    
    async viewDocument(docId) {
        window.open(`${getApiBase()}/reports/individual-reports.php?action=view_document&document_id=${docId}`, '_blank');
    }
    
    async downloadDocument(docId) {
        window.location.href = `${getApiBase()}/reports/individual-reports.php?action=download_document&document_id=${docId}`;
    }
    
    async deleteDocument(docId) {
        if (!confirm('Are you sure you want to delete this document?')) return;
        
        try {
            const response = await fetch(`${getApiBase()}/reports/individual-reports.php?action=delete_document&document_id=${docId}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.loadIndividualReport(); // Refresh documents
            } else {
                alert('Failed to delete document: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Failed to delete document. Please try again.');
        }
    }

    switchTab(tab) {
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        
        // Update active content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tab).classList.add('active');
        
        this.currentTab = tab;

        // Load tab-specific data
        if (tab === 'activities') {
            this.loadActivities();
        }
    }

    showReportContent() {
        const content = document.getElementById('individualReportContent');
        const emptyState = document.getElementById('emptyState');
        
        if (content) {
            content.classList.remove('d-none');
            content.classList.add('show-content');
        }
        
        if (emptyState) {
            emptyState.classList.add('d-none');
            emptyState.classList.remove('show-empty');
        }
        
        // Ensure the entity header is visible
        const entityHeader = document.querySelector('.entity-header');
        if (entityHeader) {
            entityHeader.classList.add('show-header');
        }
    }

    hideReportContent() {
        const content = document.getElementById('individualReportContent');
        const emptyState = document.getElementById('emptyState');
        
        if (content) {
            content.classList.add('d-none');
            content.classList.remove('show-content');
        }
        
        if (emptyState) {
            emptyState.classList.remove('d-none');
            emptyState.classList.add('show-empty');
        }
    }

    showLoading() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) {
            loadingState.classList.add('show-loading');
            loadingState.classList.remove('d-none');
        }
    }

    hideLoading() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) {
            loadingState.classList.remove('show-loading');
            loadingState.classList.add('d-none');
        }
    }

    showError(message) {
        alert(message);
    }
    
    editEntity() {
        if (!this.currentEntity || !this.currentEntityType) {
            alert('No entity selected');
            return;
        }
        
        const entityId = this.currentEntity;
        const entityType = this.currentEntityType;
        
        // Redirect to main system pages with edit parameter - direct to client form
        const editUrls = {
            'agents': `${getBaseUrl()}/pages/agent.php?edit=${entityId}`,
            'subagents': `${getBaseUrl()}/pages/subagent.php?edit=${entityId}`,
            'workers': `${getBaseUrl()}/pages/Worker.php?edit=${entityId}`,
            'cases': `${getBaseUrl()}/pages/cases/cases-table.php?edit=${entityId}`,
            'hr': `${getBaseUrl()}/pages/hr.php?edit=${entityId}`
        };
        
        if (editUrls[entityType]) {
            window.location.href = editUrls[entityType];
        } else {
            alert('Edit functionality not available for this entity type');
        }
    }
    
    viewEntity() {
        if (!this.currentEntity || !this.currentEntityType) {
            alert('No entity selected');
            return;
        }
        
        const entityId = this.currentEntity;
        const entityType = this.currentEntityType;
        
        // Redirect to main system pages with view parameter - direct to client form
        const viewUrls = {
            'agents': `${getBaseUrl()}/pages/agent.php?view=${entityId}`,
            'subagents': `${getBaseUrl()}/pages/subagent.php?view=${entityId}`,
            'workers': `${getBaseUrl()}/pages/Worker.php?view=${entityId}`,
            'cases': `${getBaseUrl()}/pages/cases/cases-table.php?view=${entityId}`,
            'hr': `${getBaseUrl()}/pages/hr.php?view=${entityId}`
        };
        
        if (viewUrls[entityType]) {
            window.location.href = viewUrls[entityType];
        } else {
            alert('View functionality not available for this entity type');
        }
    }
    
    cancelSelection() {
        // Reset the form
        const entityTypeSelect = document.getElementById('entityType');
        const entitySelect = document.getElementById('entitySelect');
        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        
        if (entityTypeSelect) entityTypeSelect.value = '';
        if (entitySelect) {
            entitySelect.value = '';
            entitySelect.disabled = true;
            entitySelect.innerHTML = '<option value="">Select an entity first</option>';
        }
        
        // Reset date inputs to default (30 days ago to today)
        if (dateFromInput && dateToInput) {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            const fromStr = formatDate(thirtyDaysAgo);
            const toStr = formatDate(today);
            dateFromInput.value = fromStr;
            dateToInput.value = toStr;
            if (dateFromInput._flatpickr) dateFromInput._flatpickr.setDate(fromStr, false);
            if (dateToInput._flatpickr) dateToInput._flatpickr.setDate(toStr, false);
        }
        
        // Reset current entity
        this.currentEntity = null;
        this.currentEntityType = null;
        
        // Hide the report content
        this.hideReportContent();
    }
    
    async uploadDocument() {
        // Check if entity is selected
        if (!this.currentEntity || !this.currentEntityType) {
            alert('Please select an entity first');
            return;
        }
        
        // Open the upload modal
        this.openModal('documentUploadModal');
    }
    
    async handleDocumentUpload(formData) {
        if (!this.currentEntity || !this.currentEntityType) {
            alert('Please select an entity first');
            return;
        }
        
        // Add entity info to form data
        formData.append('entity_type', this.currentEntityType);
        formData.append('entity_id', this.currentEntity);
        
        try {
            const response = await fetch(getApiBase() + '/reports/individual-reports.php?action=upload_document', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.closeModal('documentUploadModal');
                // Reset form
                const form = document.getElementById('documentUploadForm');
                if (form) form.reset();
                // Refresh to show new document
                this.loadIndividualReport();
                alert('Document uploaded successfully!');
            } else {
                throw new Error(result.message || 'Failed to upload document');
            }
        } catch (error) {
            alert('Failed to upload document: ' + error.message);
        }
    }
    
    generateDocument() {
        if (!this.currentEntity || !this.currentEntityType) {
            alert('Please select an entity first');
            return;
        }
        
        const params = new URLSearchParams({
            entity_type: this.currentEntityType,
            entity_id: this.currentEntity,
            format: 'pdf'
        });
        
        window.open(`${getApiBase()}/reports/individual-reports.php?action=generate_document&${params}`, '_blank');
    }

    closeModal(modalId) {
        if (modalId && modalId !== 'individualReportsModal') {
            // Close other modals (like document upload)
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                // Restore body scroll
                document.body.classList.remove('modal-open');
            }
        } else {
            // Close individual reports modal - redirect back to Reports Dashboard
            const modal = document.getElementById('individualReportsModal');
            if (modal) {
                // Restore body scroll
                document.body.classList.remove('modal-open');
                // Redirect back to Reports Dashboard
                window.location.href = getBaseUrl() + '/pages/reports.php';
            }
        }
    }
    
    resetForm() {
        const entityTypeSelect = document.getElementById('entityType');
        const entitySelect = document.getElementById('entitySelect');
        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        
        if (entityTypeSelect) entityTypeSelect.value = '';
        if (entitySelect) {
            entitySelect.innerHTML = '<option value="">Select an entity first</option>';
            entitySelect.value = '';
            entitySelect.disabled = true;
        }
        
        // Reset date inputs to default (30 days ago to today)
        if (dateFromInput && dateToInput) {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            const fromStr = formatDate(thirtyDaysAgo);
            const toStr = formatDate(today);
            dateFromInput.value = fromStr;
            dateToInput.value = toStr;
            if (dateFromInput._flatpickr) dateFromInput._flatpickr.setDate(fromStr, false);
            if (dateToInput._flatpickr) dateToInput._flatpickr.setDate(toStr, false);
        }
        
        this.currentEntity = null;
        this.currentEntityType = null;
        this.hideReportContent();
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            // Prevent body scroll when modal is open
            document.body.classList.add('modal-open');
        }
    }

    async checkConnection() {
        const statusElement = document.getElementById('connectionStatus');
        if (!statusElement) return;

        statusElement.className = 'connection-status connecting';
        statusElement.innerHTML = '<i class="fas fa-circle"></i><span>Checking connection...</span>';

        try {
            const response = await fetch(getApiBase() + '/reports/test-connection.php');
            
            if (!response.ok) {
                const responseText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                statusElement.className = 'connection-status connected';
                statusElement.innerHTML = '<i class="fas fa-circle"></i><span>Connected</span>';
            } else {
                statusElement.className = 'connection-status disconnected';
                statusElement.innerHTML = '<i class="fas fa-circle"></i><span>Connection Failed: ' + (result.message || 'Unknown error') + '</span>';
            }
        } catch (error) {
            statusElement.className = 'connection-status disconnected';
            statusElement.innerHTML = '<i class="fas fa-circle"></i><span>Connection Error: ' + error.message + '</span>';
        }
    }
}

// Global functions for backward compatibility
function loadEntityOptions() {
    if (window.individualReports) {
        window.individualReports.loadEntityOptions();
    }
}

function loadIndividualReport() {
    if (window.individualReports) {
        window.individualReports.loadIndividualReport();
    }
}

function refreshIndividualReport() {
    if (window.individualReports) {
        window.individualReports.loadIndividualReport();
    }
}

function exportIndividualReport() {
    if (!window.individualReports || !window.individualReports.currentEntity) {
        alert('Please select an entity first');
        return;
    }
    
    const params = new URLSearchParams({
        action: 'export_report',
        entity_type: window.individualReports.currentEntityType,
        entity_id: window.individualReports.currentEntity,
        format: 'csv'
    });
    
    window.location.href = `${getApiBase()}/reports/individual-reports.php?${params.toString()}`;
}

function printIndividualReport() {
    // Check if there's content to print
    const reportContent = document.getElementById('individualReportContent');
    const emptyState = document.getElementById('emptyState');
    
    // More lenient check - check if content exists and is visible
    const hasContent = reportContent && 
                       !reportContent.classList.contains('d-none') &&
                       reportContent.classList.contains('show-content') &&
                       reportContent.offsetHeight > 0;
    
    if (!hasContent) {
        alert('Please select an entity first to print the report');
        return;
    }
    
    // Store original tab state
    const allTabs = document.querySelectorAll('.tab-content');
    const originalActive = [];
    
    allTabs.forEach((tab, index) => {
        originalActive[index] = tab.classList.contains('active');
        // Show all tabs for printing
        tab.classList.add('active', 'print-visible');
    });
    
    // Hide elements that shouldn't be printed
    const elementsToHide = document.querySelectorAll('.individual-reports-close, .report-actions, .btn-action, .entity-actions, .connection-status');
    elementsToHide.forEach(el => {
        el.classList.add('print-hidden');
    });
    
    // Trigger print after a short delay to ensure styles are applied
    setTimeout(() => {
        window.print();
        
        // Restore tab visibility after print dialog closes
        setTimeout(() => {
            // Restore original tab states
            allTabs.forEach((tab, index) => {
                tab.classList.remove('print-visible');
                if (!originalActive[index]) {
                    tab.classList.remove('active');
                }
            });
            
            // Restore hidden elements
            elementsToHide.forEach(el => {
                el.classList.remove('print-hidden');
            });
        }, 1000);
    }, 100);
}

// Global wrapper functions for backward compatibility and onclick handlers
function editEntity() {
    if (window.individualReports) {
        window.individualReports.editEntity();
    } else {
        alert('Individual Reports not initialized');
    }
}

function viewEntity() {
    if (window.individualReports) {
        window.individualReports.viewEntity();
    } else {
        alert('Individual Reports not initialized');
    }
}

function uploadDocument() {
    if (window.individualReports) {
        window.individualReports.uploadDocument();
    } else {
        alert('Individual Reports not initialized');
    }
}

function generateDocument() {
    if (window.individualReports) {
        window.individualReports.generateDocument();
    } else {
        alert('Individual Reports not initialized');
    }
}

function viewDocument(documentId) {
    window.open(`${getApiBase()}/reports/individual-reports.php?action=view_document&id=${documentId}`, '_blank');
}

function downloadDocument(documentId) {
    window.open(`${getApiBase()}/reports/individual-reports.php?action=download_document&id=${documentId}`, '_blank');
}

function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document?')) {
        fetch(`${getApiBase()}/reports/individual-reports.php?action=delete_document&id=${documentId}`, {
            method: 'DELETE'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                alert('Document deleted successfully!');
                if (window.individualReports) {
                    window.individualReports.loadIndividualReport();
                }
            } else {
                alert('Failed to delete document: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Failed to delete document: ' + error.message);
        });
    }
}

function cancelSelection() {
    if (window.individualReports) {
        window.individualReports.cancelSelection();
    } else {
        alert('Individual Reports not initialized');
    }
}

// Initialize when DOM is ready
function initializeIndividualReports() {
    try {
        if (!window.individualReports) {
            window.individualReports = new IndividualReports();
        }
    } catch (error) {
        // Silent initialization error
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeIndividualReports);
} else {
    // DOM already loaded
    initializeIndividualReports();
}

// Also expose initialization function globally for manual initialization if needed
window.initIndividualReports = initializeIndividualReports;

// Debug helper function (kept for manual debugging if needed)
window.debugIndividualReports = function() {
    return {
        instance: window.individualReports,
        currentEntity: window.individualReports?.currentEntity,
        currentEntityType: window.individualReports?.currentEntityType,
        buttons: Array.from(document.querySelectorAll('[data-action]')).map(btn => ({
            action: btn.getAttribute('data-action'),
            className: btn.className
        }))
    };
};
