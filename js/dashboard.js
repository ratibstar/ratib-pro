/**
 * EN: Implements frontend interaction behavior in `js/dashboard.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/dashboard.js`.
 */
// Dashboard JavaScript Functions

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

// Initialize dashboard card click handlers
document.addEventListener('DOMContentLoaded', function() {
    // Show Help Center notification on first visit (once per session)
    // Function is now self-initializing and independent
    // It will run automatically even if this code has errors
    
    loadDashboardStats().then(() => {
        // Initialize charts after stats are loaded (only if chart containers exist)
        const chartContainers = document.querySelectorAll('.chart-container');
        if (chartContainers.length > 0) {
        setTimeout(initializeDashboardCharts, 300);
        }
    });
    // Handle dashboard card clicks - check permissions before navigating
    const cards = document.querySelectorAll('.system-card[data-href]');
    cards.forEach(card => {
        card.addEventListener('click', function() {
            // Check if card is hidden by permissions (shouldn't happen if properly hidden, but safety check)
            const computedStyle = window.getComputedStyle(this);
            if (computedStyle.display === 'none' || this.classList.contains('permission-denied') || this.classList.contains('hidden') || this.classList.contains('d-none')) {
                return; // Don't navigate if permission denied
            }
            
            // Check permission if data-permission attribute exists
            const requiredPermission = this.getAttribute('data-permission');
            if (requiredPermission && window.UserPermissions && window.UserPermissions.loaded) {
                if (!window.UserPermissions.has(requiredPermission)) {
                    alert('You do not have permission to access this page.');
                    return;
                }
            }
            
            const href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
        
        // Add keyboard support
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                
                // Check permission if data-permission attribute exists
                const requiredPermission = this.getAttribute('data-permission');
                if (requiredPermission && window.UserPermissions && window.UserPermissions.loaded) {
                    if (!window.UserPermissions.has(requiredPermission)) {
                        alert('You do not have permission to access this page.');
                        return;
                    }
                }
                
                const href = this.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            }
        });
    });
    
    // Handle profile modal card click - use same design as system-settings users table
    const profileCard = document.querySelector('.system-card[data-action="open-profile-modal"]');
    if (profileCard) {
        profileCard.addEventListener('click', function() {
            openProfileModal();
        });
        
        profileCard.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openProfileModal();
            }
        });
    }
});

// Help Center notification is now handled by global js/help-center-notification.js
// This ensures it works on all pages and navigates in the same page

// Open profile modal using same design as system-settings users table
async function openProfileModal() {
    // Check if modernForms is available
    if (!window.modernForms) {
        // Wait for modernForms to load
        let attempts = 0;
        const maxAttempts = 50;
        const checkInterval = setInterval(() => {
            attempts++;
            if (window.modernForms || attempts >= maxAttempts) {
                clearInterval(checkInterval);
                if (window.modernForms) {
                    openProfileModalWithFilter();
                } else {
                    console.error('ModernForms not available');
                    alert('Profile modal is loading. Please try again in a moment.');
                }
            }
        }, 100);
        return;
    }
    
    openProfileModalWithFilter();
}

// Open users modal filtered to current user
async function openProfileModalWithFilter() {
    const modal = document.getElementById('mainModal');
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    
    if (!modal || !title || !body) {
        console.error('Modal elements not found');
        return;
    }
    
    title.textContent = '👤 My Profile';
    body.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading profile...</div>';
    
    // Add class to modal body and modal content to enable profile-specific CSS
    body.classList.add('profile-modal-content');
    const modalContent = modal.querySelector('.modern-modal-content');
    if (modalContent) {
        modalContent.classList.add('profile-modal-content');
    }
    
    modal.classList.remove('modal-hidden');
    modal.classList.add('show');
    
    try {
        // Get current user ID from profile card data attribute FIRST
        const profileCard = document.querySelector('.system-card[data-action="open-profile-modal"]');
        const currentUserId = profileCard ? parseInt(profileCard.getAttribute('data-user-id')) : null;
        
        if (!currentUserId) {
            body.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>User ID not found</p></div>';
            return;
        }
        
        // Use modernForms to load users data
        window.modernForms.currentTable = 'users';
        
        // Load data but keep modal body showing loading state
        await window.modernForms.loadData();
        
        // Filter data IMMEDIATELY after loading, before any rendering
        if (window.modernForms.data && Array.isArray(window.modernForms.data)) {
            window.modernForms.data = window.modernForms.data.filter(user => user.user_id == currentUserId);
        }
        
        // Calculate stats from filtered data only (before rendering)
        const filteredData = window.modernForms.data || [];
        const stats = {
            total: filteredData.length,
            active: filteredData.filter(user => {
                const status = user.status || user.is_active;
                return status === 'active' || status === 1 || status === '1';
            }).length,
            inactive: filteredData.filter(user => {
                const status = user.status || user.is_active;
                return status === 'inactive' || status === 0 || status === '0';
            }).length,
            today: 0,
            thisWeek: 0,
            thisMonth: 0
        };
        
        // Store stats to prevent recalculation during render
        window.modernForms.currentTableStats = stats;
        
        // Now render table with filtered data (no flash of all users)
        window.modernForms.renderTableWithStats(stats);
        
        // Wait a bit for renderTable to complete and DOM to update
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Add history section after table renders
        await addProfileHistoryAndChart(body, currentUserId);
        
        // All hiding is now handled by CSS in dashboard.css
        // CSS selectors target #mainModal .modal-body elements
        // No inline styles needed - CSS handles everything
    } catch (error) {
        console.error('Error loading profile:', error);
        body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading profile: ${error.message}</p></div>`;
    }
}

// Add history section to profile modal (chart removed per user request)
async function addProfileHistoryAndChart(modalBody, userId) {
    if (!modalBody || !userId) {
        console.error('addProfileHistoryAndChart: Missing modalBody or userId', { modalBody: !!modalBody, userId });
        return;
    }
    
    // Check if activities section already exists
    const existingActivitiesSection = modalBody.querySelector('.profile-activities-section');
    if (existingActivitiesSection) {
        return; // Already added
    }
    
    // Get the table container to append after
    const tableContainer = modalBody.querySelector('.modern-data-table');
    if (!tableContainer) {
        console.error('addProfileHistoryAndChart: Could not find .modern-data-table');
        return;
    }
    
    // Create activities section HTML
    const activitiesHTML = `
        <div class="profile-activities-section">
            <div class="activities-header">
                <h3>📜 Recent Activities</h3>
            </div>
            <div class="activity-list" id="profileModalActivitiesList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i> Loading activities...
                </div>
            </div>
        </div>
    `;
    
    // Append activities section after table
    tableContainer.insertAdjacentHTML('afterend', activitiesHTML);
    
    // Load activities
    await loadProfileModalActivities(userId);
}

// Load activities for profile modal
async function loadProfileModalActivities(userId) {
    const activitiesList = document.getElementById('profileModalActivitiesList');
    if (!activitiesList) return;
    
    try {
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
        const url = `${baseUrl}/api/core/global-history-api.php?action=get_history&user_id=${userId}&limit=10`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid response format');
        }
        
        if (!data.success || !data.data || data.data.length === 0) {
            activitiesList.innerHTML = '<p class="no-activities">No recent activities</p>';
            return;
        }
        
        const userActivities = data.data;
        
        activitiesList.innerHTML = userActivities.map(activity => {
            const action = activity.action || '';
            const tableName = activity.table_name || '';
            const module = activity.module || '';
            const description = activity.description || 
                              `${action} in ${tableName}${module ? ' (' + module + ')' : ''}`.trim() ||
                              'Activity';
            const timestamp = activity.created_at || activity.timestamp || '';
            const timeDisplay = timestamp ? new Date(timestamp).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) : 'Date not available';
            
            let iconClass = 'fa-history';
            if (action === 'create') iconClass = 'fa-plus-circle';
            else if (action === 'update') iconClass = 'fa-edit';
            else if (action === 'delete') iconClass = 'fa-trash';
            
            const escapedDescription = description.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            
            return `
                <div class="activity-item" role="article" tabindex="0">
                    <div class="activity-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="activity-details">
                        <p class="activity-description">${escapedDescription}</p>
                        <small class="activity-time">${timeDisplay}</small>
                    </div>
                </div>
            `;
        }).join('');
        
    } catch (error) {
        activitiesList.innerHTML = '<p class="no-activities">Failed to load activities. Please refresh the page.</p>';
    }
}

// Initialize activity chart for profile modal
async function initializeProfileActivityChart(userId) {
    const canvas = document.getElementById('profileActivityChart');
    if (!canvas) {
        console.error('initializeProfileActivityChart: Canvas element not found');
        return;
    }
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        if (canvas.parentElement) {
            canvas.parentElement.innerHTML = '<p class="no-activities">Chart.js not loaded. Please refresh the page.</p>';
        }
        return;
    }
    
    try {
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
        const url = `${baseUrl}/api/core/global-history-api.php?action=get_history&user_id=${userId}&limit=30`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid response format');
        }
        
        // Handle empty data - still show chart with zero values
        let labels = [];
        let counts = [];
        
        if (!data.success || !data.data || data.data.length === 0) {
            // Generate labels for last 7 days with zero counts
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateKey = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                labels.push(dateKey);
                counts.push(0);
            }
        } else {
            // Group activities by date
            const activitiesByDate = {};
            data.data.forEach(activity => {
                const date = new Date(activity.created_at || activity.timestamp);
                const dateKey = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                if (!activitiesByDate[dateKey]) {
                    activitiesByDate[dateKey] = 0;
                }
                activitiesByDate[dateKey]++;
            });
            
            // Get last 7 days
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateKey = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                labels.push(dateKey);
                counts.push(activitiesByDate[dateKey] || 0);
            }
        }
        
        // Destroy existing chart if it exists
        if (window.profileActivityChartInstance) {
            window.profileActivityChartInstance.destroy();
        }
        
        // Create new chart
        const ctx = canvas.getContext('2d');
        window.profileActivityChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Activities',
                    data: counts,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        return;
        
        
    } catch (error) {
        canvas.parentElement.innerHTML = '<p class="no-activities">Failed to load chart data</p>';
    }
}


// Close modal handler
document.addEventListener('click', function(e) {
    if (e.target.closest('[data-action="close-modal"]') || 
        (e.target.id === 'mainModal' && e.target === e.currentTarget)) {
        const modal = document.getElementById('mainModal');
        if (modal) {
            modal.classList.add('modal-hidden');
            modal.classList.remove('show');
        }
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('mainModal');
        if (modal && !modal.classList.contains('modal-hidden')) {
            modal.classList.add('modal-hidden');
            modal.classList.remove('show');
        }
    }
});

// Load dashboard statistics from API (works with same-origin and live deployment)
// Use AbortController to prevent race conditions
let dashboardStatsController = null;
async function loadDashboardStats() {
    // Abort previous request if still pending
    if (dashboardStatsController) {
        dashboardStatsController.abort();
    }
    
    dashboardStatsController = new AbortController();
    const lastLoginEl = document.getElementById('lastLoginTime');
    const apiBase = getApiBase();
    const url = (apiBase ? apiBase + '/dashboard/stats.php' : (window.BASE_PATH || '') + '/api/dashboard/stats.php');
    try {
        const response = await fetch(url, { 
            credentials: 'same-origin',
            signal: dashboardStatsController.signal
        });
        if (!response.ok) {
            if (lastLoginEl) lastLoginEl.textContent = 'Never';
            return false;
        }
        const result = await response.json();
        
        if (result && result.success && result.data) {
            const stats = result.data;
            if (lastLoginEl && stats && typeof stats.last_login_time !== 'undefined') {
                lastLoginEl.textContent = stats.last_login_time || 'Never';
            }
            const chartContainers = document.querySelectorAll('.chart-container');
            if (chartContainers.length > 0 && typeof initializeDashboardCharts === 'function') {
                setTimeout(initializeDashboardCharts, 100);
            }
            dashboardStatsController = null; // Clear controller on success
            return true;
        }
        if (lastLoginEl) lastLoginEl.textContent = 'Never';
        dashboardStatsController = null;
        return false;
    } catch (error) {
        // Ignore abort errors (expected when cancelling)
        if (error.name !== 'AbortError') {
            if (lastLoginEl) lastLoginEl.textContent = 'Never';
        }
        dashboardStatsController = null;
        return false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Remove any password breach alerts
    const alerts = document.querySelectorAll('[data-testid="password-break-alert"], .password-break-alert, [role="alert"]');
    alerts.forEach(alert => {
        if (alert.textContent.includes('password') && alert.textContent.includes('breach')) {
            alert.remove();
        }
    });
    
    // Prevent Chrome from showing password alerts
    if (window.chrome && window.chrome.webstore) {
        const meta = document.createElement('meta');
        meta.name = 'password-manager';
        meta.content = 'disabled';
        document.head.appendChild(meta);
    }
    
    // Flashing text functionality
    const flashingText = document.getElementById('flashingText');
    const messages = [
        'System Ready',
        'Welcome Back',
        'All Systems Active',
        'Dashboard Loaded'
    ];
    
    let currentIndex = 0;
    let flashingTextInterval = null;
    
    function updateFlashingText() {
        if (flashingText) {
            flashingText.textContent = messages[currentIndex];
            // Remove animation class to restart
            flashingText.classList.remove('animate');
            flashingText.offsetHeight; // Trigger reflow
            // Re-add animation class
            flashingText.classList.add('animate');
            currentIndex = (currentIndex + 1) % messages.length;
        }
    }
    
    // Start the flashing text
    if (flashingText) {
        updateFlashingText();
        flashingTextInterval = setInterval(updateFlashingText, 3000);
        
        // Clean up interval when page unloads
        window.addEventListener('beforeunload', function() {
            if (flashingTextInterval) {
                clearInterval(flashingTextInterval);
            }
        });
    }
    
    // Add keyboard navigation support for cards
    const cards = document.querySelectorAll('.system-card[role="button"]');
    cards.forEach(card => {
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
        
        // Add touch feedback for mobile
        card.addEventListener('touchstart', function() {
            card.classList.add('card-touch-active');
        });
        
        card.addEventListener('touchend', function() {
            card.classList.remove('card-touch-active');
        });
    });
    
    // Add keyboard support for quick action buttons
    const quickActionButtons = document.querySelectorAll('.quick-actions button');
    quickActionButtons.forEach(button => {
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                button.click();
            }
        });
    });
    
    // Optimize scrolling on mobile
    const mainContent = document.querySelector('.main-content');
    if (mainContent && 'ontouchstart' in window) {
        let startY = 0;
        let currentY = 0;
        
        mainContent.addEventListener('touchstart', function(e) {
            startY = e.touches[0].pageY;
        }, { passive: true });
        
        mainContent.addEventListener('touchmove', function(e) {
            currentY = e.touches[0].pageY;
        }, { passive: true });
    }
    
    // Detect viewport size and add class for extra small screens
    function checkViewport() {
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        const body = document.body;
        
        if (vw <= 360) {
            body.classList.add('extra-small-mobile');
        } else {
            body.classList.remove('extra-small-mobile');
        }
        
        if (vw <= 480) {
            body.classList.add('small-mobile');
        } else {
            body.classList.remove('small-mobile');
        }
        
        if (vw <= 767) {
            body.classList.add('mobile-view');
        } else {
            body.classList.remove('mobile-view');
        }
    }
    
    checkViewport();
    window.addEventListener('resize', checkViewport);
});

// ========================================
// MODERN CHARTS INITIALIZATION
// ========================================

let dashboardCharts = {
    systemOverview: null,
    statusDistribution: null,
    cases: null,
    activityTrends: null
};

// Flag to prevent concurrent initialization
let isInitializingCharts = false;
let chartRetryCount = 0;
const MAX_CHART_RETRIES = 50; // Maximum 5 seconds (50 * 100ms)

// Initialize all dashboard charts
async function initializeDashboardCharts() {
    // Check if chart containers exist - if not, don't initialize charts
    const chartContainers = document.querySelectorAll('.chart-container');
    if (chartContainers.length === 0) {
        // No chart containers on this page, skip chart initialization
        return;
    }
    
    // Prevent concurrent initialization
    if (isInitializingCharts) {
        return;
    }
    
    // Wait for Chart.js to load, but only if charts are needed
    if (typeof Chart === 'undefined') {
        chartRetryCount++;
        if (chartRetryCount >= MAX_CHART_RETRIES) {
            console.warn('Chart.js failed to load after maximum retries. Charts will not be initialized.');
            chartRetryCount = 0; // Reset for next attempt
            return;
        }
        setTimeout(initializeDashboardCharts, 100);
        return;
    }
    
    // Reset retry count on success
    chartRetryCount = 0;
    isInitializingCharts = true;
    
    try {
        // Check if chart containers exist
        const chartContainers = document.querySelectorAll('.chart-container');
        if (chartContainers.length === 0) {
            console.warn('Chart containers not found, charts section may not be visible');
            // Don't return here, let finally block run
        } else {

    // Show loading state (only if canvas doesn't exist and no error/empty state)
    chartContainers.forEach(container => {
        const canvas = container.querySelector('canvas');
        const hasState = container.querySelector('.chart-empty-state, .chart-error-state, .chart-loading');
        if (!canvas && !hasState) {
            container.innerHTML = '<div class="chart-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading chart...</p></div>';
        }
    });

    // Helper function to safely parse integer from element
    const safeParseInt = (elementId, defaultValue = 0) => {
        const el = document.getElementById(elementId);
        if (!el) return defaultValue;
        const value = parseInt(el.textContent || defaultValue);
        return isNaN(value) ? defaultValue : Math.max(0, value); // Ensure non-negative
    };
    
    // Get stats from the page with validation
    const stats = {
        agents: {
            total: safeParseInt('totalAgents', 0),
            active: safeParseInt('activeAgents', 0),
            inactive: safeParseInt('inactiveAgents', 0)
        },
        subAgents: {
            total: safeParseInt('totalSubAgents', 0),
            active: safeParseInt('activeSubAgents', 0),
            inactive: safeParseInt('inactiveSubAgents', 0)
        },
        workers: {
            total: safeParseInt('totalWorkers', 0),
            active: safeParseInt('activeWorkers', 0),
            inactive: safeParseInt('inactiveWorkers', 0)
        },
        cases: {
            total: safeParseInt('totalCases', 0),
            open: safeParseInt('openCases', 0),
            urgent: safeParseInt('urgentCases', 0),
            resolved: safeParseInt('resolvedCases', 0)
        },
        hr: {
            total: safeParseInt('totalHR', 0),
            active: safeParseInt('activeHR', 0),
            inactive: safeParseInt('inactiveHR', 0)
        },
        contacts: {
            total: safeParseInt('totalContacts', 0),
            active: safeParseInt('activeContacts', 0),
            inactive: safeParseInt('inactiveContacts', 0)
        }
    };
    
    // Validate stats structure
    const validateStats = (statsObj) => {
        const requiredKeys = ['agents', 'subAgents', 'workers', 'cases', 'hr', 'contacts'];
        for (const key of requiredKeys) {
            if (!statsObj[key] || typeof statsObj[key] !== 'object') {
                return false;
            }
            const subKeys = key === 'cases' ? ['total', 'open', 'urgent', 'resolved'] : ['total', 'active', 'inactive'];
            for (const subKey of subKeys) {
                if (typeof statsObj[key][subKey] !== 'number' || isNaN(statsObj[key][subKey])) {
                    statsObj[key][subKey] = 0; // Fix invalid values
                }
            }
        }
        return true;
    };
    
    // Validate and fix stats
    if (!validateStats(stats)) {
        console.error('Invalid stats structure, using defaults');
        // Stats already have defaults from safeParseInt, but ensure structure is valid
    }

    // Define colors for charts
    const colors = {
        primary: '#667eea',
        success: '#28a745',
        warning: '#ffc107',
        danger: '#dc3545',
        info: '#17a2b8',
        secondary: '#6c757d'
    };

    // 1. System Overview Chart (Bar Chart)
    const systemOverviewCtx = document.getElementById('systemOverviewChart');
    if (systemOverviewCtx) {
        try {
            if (dashboardCharts.systemOverview) {
                dashboardCharts.systemOverview.destroy();
            }
            dashboardCharts.systemOverview = new Chart(systemOverviewCtx, {
                type: 'bar',
                data: {
                    labels: ['Agents', 'SubAgents', 'Workers', 'HR', 'Contacts'],
                    datasets: [{
                        label: 'Total',
                        data: [
                            stats.agents?.total || 0,
                            stats.subAgents?.total || 0,
                            stats.workers?.total || 0,
                            stats.hr?.total || 0,
                            stats.contacts?.total || 0
                        ],
                        backgroundColor: colors.primary,
                        borderColor: colors.secondary,
                        borderWidth: 2,
                        borderRadius: 8
                    }, {
                        label: 'Active',
                        data: [
                            stats.agents.active,
                            stats.subAgents.active,
                            stats.workers.active,
                            stats.hr.active,
                            stats.contacts.active
                        ],
                        backgroundColor: colors.success,
                        borderColor: colors.secondary,
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#fff',
                                font: { size: 12 },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: colors.primary,
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#ccc', font: { size: 11 } },
                            grid: { color: 'rgba(255, 255, 255, 0.1)', display: false }
                        },
                        y: {
                            ticks: { color: '#ccc', font: { size: 11 } },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            beginAtZero: true
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating system overview chart:', error);
            systemOverviewCtx.parentElement.innerHTML = '<div class="chart-error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading chart</p></div>';
        }
    }

    // 2. Status Distribution Chart (Doughnut Chart)
    const statusDistributionCtx = document.getElementById('statusDistributionChart');
    if (statusDistributionCtx) {
        try {
            if (dashboardCharts.statusDistribution) {
                dashboardCharts.statusDistribution.destroy();
            }
            // Safely calculate totals with validation
            const totalActive = (stats.agents?.active || 0) + (stats.subAgents?.active || 0) + 
                               (stats.workers?.active || 0) + (stats.hr?.active || 0) + 
                               (stats.contacts?.active || 0);
            const totalInactive = (stats.agents?.inactive || 0) + (stats.subAgents?.inactive || 0) + 
                                 (stats.workers?.inactive || 0) + (stats.hr?.inactive || 0) + 
                                 (stats.contacts?.inactive || 0);
            
            // Handle edge case: if both are 0, show empty state
            if (totalActive === 0 && totalInactive === 0) {
                const container = statusDistributionCtx.parentElement;
                if (container && !container.querySelector('.chart-empty-state')) {
                    container.innerHTML = '<div class="chart-empty-state"><i class="fas fa-inbox"></i><p>No status data available</p></div>';
                }
                // Continue to next chart instead of returning
            } else {
            
            dashboardCharts.statusDistribution = new Chart(statusDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Inactive'],
                    datasets: [{
                        data: [totalActive, totalInactive],
                        backgroundColor: [colors.success, colors.danger],
                        borderColor: ['#1e7e34', '#c82333'],
                        borderWidth: 3,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: '#fff',
                                font: { size: 12 },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: colors.success,
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = totalActive + totalInactive;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
            }
        } catch (error) {
            console.error('Error creating status distribution chart:', error);
            statusDistributionCtx.parentElement.innerHTML = '<div class="chart-error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading chart</p></div>';
        }
    }

    // 3. Cases Breakdown Chart (Pie Chart)
    const casesCtx = document.getElementById('casesChart');
    if (casesCtx && stats.cases && (stats.cases.total || 0) > 0) {
        try {
            if (dashboardCharts.cases) {
                dashboardCharts.cases.destroy();
            }
            dashboardCharts.cases = new Chart(casesCtx, {
                type: 'pie',
                data: {
                    labels: ['Open', 'Urgent', 'Resolved'],
                    datasets: [{
                        data: [
                            stats.cases?.open || 0,
                            stats.cases?.urgent || 0,
                            stats.cases?.resolved || 0
                        ],
                        backgroundColor: [colors.warning, colors.danger, colors.success],
                        borderColor: ['#e0a800', '#c82333', '#1e7e34'],
                        borderWidth: 3,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: '#fff',
                                font: { size: 12 },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: colors.warning,
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = (stats.cases?.open || 0) + (stats.cases?.urgent || 0) + (stats.cases?.resolved || 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating cases chart:', error);
            casesCtx.parentElement.innerHTML = '<div class="chart-error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading chart</p></div>';
        }
    } else if (casesCtx && stats.cases.total === 0) {
        // Show empty state if no cases
        const container = casesCtx.parentElement;
        if (container && !container.querySelector('.chart-empty-state')) {
            container.innerHTML = '<div class="chart-empty-state"><i class="fas fa-inbox"></i><p>No cases data available</p></div>';
        }
    }

    // 4. Activity Trends Chart (Line Chart)
    const activityTrendsCtx = document.getElementById('activityTrendsChart');
    if (activityTrendsCtx) {
        try {
            // Destroy existing chart from registry first (most reliable)
            if (typeof Chart !== 'undefined' && Chart.getChart(activityTrendsCtx)) {
                try {
                    const existingChart = Chart.getChart(activityTrendsCtx);
                    existingChart.destroy();
                    // Wait a bit for Chart.js to fully clean up
                    await new Promise(resolve => setTimeout(resolve, 50));
                } catch (e) {
                    console.warn('Error destroying existing chart from registry:', e);
                }
            }
            
            // Also destroy from our reference
            if (dashboardCharts.activityTrends) {
                try {
                    dashboardCharts.activityTrends.destroy();
                } catch (e) {
                    console.warn('Error destroying existing activity trends chart:', e);
                }
                dashboardCharts.activityTrends = null;
            }
            
            // Get selected days from dropdown (default 30)
            const rangeSelect = document.getElementById('activityTrendsRange');
            const days = rangeSelect ? parseInt(rangeSelect.value) || 30 : 30;
            
            // Generate labels for selected days
            const labels = [];
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            
            // Final check: ensure canvas is completely free
            if (typeof Chart !== 'undefined' && Chart.getChart(activityTrendsCtx)) {
                console.warn('Canvas still in use after destruction, forcing cleanup...');
                try {
                    Chart.getChart(activityTrendsCtx).destroy();
                    await new Promise(resolve => setTimeout(resolve, 50));
                } catch (e) {
                    console.warn('Error in final cleanup:', e);
                }
            }
            
            // Try to fetch real activity data, fallback to sample data
            let activityData;
            try {
                activityData = await fetchActivityTrendsData(labels, days);
                // Validate data structure
                if (!activityData || !Array.isArray(activityData.activities) || !Array.isArray(activityData.reports)) {
                    throw new Error('Invalid data structure');
                }
                if (activityData.activities.length !== labels.length || activityData.reports.length !== labels.length) {
                    throw new Error('Data length mismatch');
                }
            } catch (error) {
                console.warn('Error fetching activity trends data, using sample data:', error);
                // Generate sample data as fallback
                const generateSampleData = (base, variance) => {
                    return labels.map(() => Math.max(0, Math.floor(base + (Math.random() * variance * 2) - variance)));
                };
                activityData = {
                    activities: generateSampleData(10, 5),
                    reports: generateSampleData(5, 3)
                };
            }
            
            dashboardCharts.activityTrends = new Chart(activityTrendsCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Activities',
                        data: activityData.activities,
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: colors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }, {
                        label: 'Reports',
                        data: activityData.reports,
                        borderColor: colors.success,
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: colors.success,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#fff',
                                font: { size: 12 },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: colors.primary,
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#ccc', font: { size: 11 } },
                            grid: { color: 'rgba(255, 255, 255, 0.1)', display: false }
                        },
                        y: {
                            ticks: { color: '#ccc', font: { size: 11 } },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            beginAtZero: true
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        } catch (error) {
            console.error('Error creating activity trends chart:', error);
            activityTrendsCtx.parentElement.innerHTML = '<div class="chart-error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading chart</p></div>';
        }
    }
        } // Close the else block for chartContainers check
        
        // Initialize chart enhancements (buttons, click handlers, etc.) after charts are ready
        setTimeout(() => {
            if (typeof initializeChartEnhancements === 'function') {
                initializeChartEnhancements();
            }
        }, 500);
    } finally {
        // Always reset initialization flag, even if there was an error
        isInitializingCharts = false;
    }
}

// Fetch real activity trends data from API
async function fetchActivityTrendsData(labels, days = 7) {
    try {
        // Try to fetch from activity_logs if API exists
        const apiBase = getApiBase();
        if (!apiBase) {
            throw new Error('API base URL not configured');
        }
        
        const response = await fetch(apiBase + '/dashboard/activity-trends.php?days=' + days, { credentials: 'same-origin' });
        
        if (response.ok) {
            const result = await response.json();
            if (result && result.success && result.data && Array.isArray(result.data)) {
                // API returns array with {date, activities, reports} for each day
                // Create a map for quick lookup
                const dataMap = {};
                result.data.forEach(d => {
                    const dateKey = d.date || d.day || d.created_at;
                    if (dateKey) {
                        // Normalize date to YYYY-MM-DD format for matching
                        const dateObj = new Date(dateKey);
                        const normalizedDate = dateObj.toISOString().split('T')[0];
                        dataMap[normalizedDate] = {
                            activities: parseInt(d.activities || 0),
                            reports: parseInt(d.reports || d.report_count || 0)
                        };
                    }
                });
                
                // Map labels to data (labels are in format "Jan 12" or "Jan 12, 2026")
                const activities = labels.map(label => {
                    try {
                        // Parse label date and normalize
                        const labelDate = new Date(label);
                        const normalizedLabel = labelDate.toISOString().split('T')[0];
                        return dataMap[normalizedLabel] ? dataMap[normalizedLabel].activities : 0;
                    } catch (e) {
                        return 0;
                    }
                });
                
                const reports = labels.map(label => {
                    try {
                        const labelDate = new Date(label);
                        const normalizedLabel = labelDate.toISOString().split('T')[0];
                        return dataMap[normalizedLabel] ? dataMap[normalizedLabel].reports : 0;
                    } catch (e) {
                        return 0;
                    }
                });
                
                // Validate returned data
                if (activities.length === labels.length && reports.length === labels.length) {
                    activityTrendsController = null; // Clear controller on success
                    return { activities, reports };
                }
            }
        } else {
            // Only log warning for non-404 errors (404 means endpoint doesn't exist, which is OK)
            if (response.status !== 404) {
                console.warn('Activity trends API returned non-OK status:', response.status);
            }
        }
        activityTrendsController = null;
    } catch (error) {
        // Ignore abort errors (expected when cancelling)
        if (error.name !== 'AbortError') {
            // Only log if it's not a network/404 error
            if (error.message && !error.message.includes('404') && !error.message.includes('Failed to fetch')) {
                console.warn('Could not fetch activity trends from API:', error);
            }
        }
        activityTrendsController = null;
        // Will fall through to sample data generation
    }
    
    // Fallback: generate sample data based on current stats
    const stats = {
        reports: {
            today: parseInt(document.getElementById('todayReports')?.textContent || 0)
        }
    };
    
    const generateSampleData = (base, variance) => {
        return labels.map(() => Math.max(0, Math.floor(base + (Math.random() * variance * 2) - variance)));
    };
    
    return {
        activities: generateSampleData(stats.reports.today || 10, 5),
        reports: generateSampleData(stats.reports.today || 5, 3)
    };
}

// Charts will be initialized after stats load (see DOMContentLoaded above)

// Reinitialize charts on window resize (only resize, don't recreate)
let resizeTimer;
let resizeHandler = null;

// Setup resize handler (only once)
if (!window.dashboardResizeHandlerSetup) {
    window.dashboardResizeHandlerSetup = true;
    resizeHandler = function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Only resize existing charts, don't recreate them
            Object.values(dashboardCharts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }, 250);
    };
    window.addEventListener('resize', resizeHandler);
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (resizeHandler) {
            window.removeEventListener('resize', resizeHandler);
        }
        if (resizeTimer) {
            clearTimeout(resizeTimer);
        }
    });
}

// Export function to refresh charts manually (useful for stats updates)
window.refreshDashboardCharts = function() {
    initializeDashboardCharts();
};

// ========================================
// CHART ENHANCEMENTS: EXPORT, REFRESH, CLICK NAVIGATION
// ========================================

// Export chart as PNG image
function exportChart(chartId, chartName) {
    // Get chart instance
    let chart = null;
    if (chartId === 'systemOverviewChart') {
        chart = dashboardCharts.systemOverview;
    } else if (chartId === 'statusDistributionChart') {
        chart = dashboardCharts.statusDistribution;
    } else if (chartId === 'casesChart') {
        chart = dashboardCharts.cases;
    } else if (chartId === 'activityTrendsChart') {
        chart = dashboardCharts.activityTrends;
    }
    
    if (!chart) {
        console.warn(`Chart ${chartId} not found`);
        return;
    }
    
    // Get canvas element
    const canvas = document.getElementById(chartId);
    if (!canvas) {
        console.warn(`Canvas element ${chartId} not found`);
        return;
    }
    
    // Create download link
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = `${chartName || 'chart'}_${new Date().toISOString().split('T')[0]}.png`;
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Export all charts as ZIP (or individual downloads)
function exportAllCharts() {
    const chartNames = {
        'systemOverviewChart': 'System_Overview',
        'statusDistributionChart': 'Status_Distribution',
        'casesChart': 'Cases_Breakdown',
        'activityTrendsChart': 'Activity_Trends'
    };
    
    // Export each chart with a small delay to avoid browser blocking
    let delay = 0;
    Object.keys(chartNames).forEach((chartId, index) => {
        setTimeout(() => {
            exportChart(chartId, chartNames[chartId]);
        }, delay);
        delay += 300; // 300ms delay between each export
    });
}

// Setup chart action buttons
// Store handlers to prevent duplicate listeners
let chartActionsSetup = false;
let refreshAllHandler = null;
let exportAllHandler = null;
function setupChartActions() {
    // Prevent duplicate setup
    if (chartActionsSetup) {
        return;
    }
    chartActionsSetup = true;
    
    // Refresh all charts button
    const refreshAllBtn = document.getElementById('refreshChartsBtn');
    if (refreshAllBtn && !refreshAllBtn.dataset.listenerAdded) {
        refreshAllHandler = function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.add('fa-spin');
            }
            this.disabled = true;
            
            // Refresh stats first, then charts
            loadDashboardStats().then(() => {
                setTimeout(() => {
                    initializeDashboardCharts();
                    if (icon) {
                        icon.classList.remove('fa-spin');
                    }
                    this.disabled = false;
                }, 300);
            });
        };
        refreshAllBtn.addEventListener('click', refreshAllHandler);
        refreshAllBtn.dataset.listenerAdded = 'true';
    }
    
    // Export all charts button
    const exportAllBtn = document.getElementById('exportAllChartsBtn');
    if (exportAllBtn && !exportAllBtn.dataset.listenerAdded) {
        exportAllHandler = function() {
            exportAllCharts();
        };
        exportAllBtn.addEventListener('click', exportAllHandler);
        exportAllBtn.dataset.listenerAdded = 'true';
    }
    
    // Individual chart action buttons (use event delegation to prevent duplicates)
    const chartActionsContainer = document.querySelector('.charts-section');
    if (chartActionsContainer && !chartActionsContainer.dataset.delegationAdded) {
        chartActionsContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.chart-action-btn');
            if (!btn) return;
            e.stopPropagation();
            const chartId = btn.getAttribute('data-chart');
            const action = btn.getAttribute('data-action');
            const chartCard = btn.closest('.chart-card');
            const chartName = chartCard?.querySelector('h3')?.textContent?.trim() || 'Chart';
            
            if (action === 'export') {
                exportChart(chartId, chartName);
            } else if (action === 'fullscreen') {
                toggleChartFullscreen(chartId, chartName);
            } else if (action === 'print') {
                printChart(chartId, chartName);
            } else if (action === 'refresh') {
                // Prevent rapid clicks (debounce)
                if (btn.disabled) return;
                
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.add('fa-spin');
                }
                btn.disabled = true;
                
                // Refresh stats first, then specific chart
                loadDashboardStats().then(() => {
                    setTimeout(() => {
                        // Reinitialize the specific chart
                        if (chartId === 'systemOverviewChart' || 
                            chartId === 'statusDistributionChart' || 
                            chartId === 'casesChart' || 
                            chartId === 'activityTrendsChart') {
                            initializeDashboardCharts();
                        }
                        if (icon) {
                            icon.classList.remove('fa-spin');
                        }
                        btn.disabled = false;
                    }, 300);
                }).catch(() => {
                    // Handle errors
                    if (icon) {
                        icon.classList.remove('fa-spin');
                    }
                    btn.disabled = false;
                });
            }
        });
        chartActionsContainer.dataset.delegationAdded = 'true';
    }
}

// Add click navigation to chart elements
function setupChartClickNavigation() {
    // System Overview Chart - navigate to respective pages
    if (dashboardCharts.systemOverview) {
        dashboardCharts.systemOverview.options.onClick = function(event, elements) {
            if (elements.length > 0) {
                const element = elements[0];
                const index = element.index;
                const pages = {
                    0: 'agent.php',
                    1: 'subagent.php',
                    2: 'worker.php',
                    3: 'hr.php',
                    4: 'contact.php'
                };
                
                if (pages[index]) {
                    const baseUrl = getBaseUrl() || window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
                    window.location.href = baseUrl + '/pages/' + pages[index];
                }
            }
        };
    }
    
    // Cases Chart - navigate to cases page (matches dashboard card href)
    if (dashboardCharts.cases) {
        dashboardCharts.cases.options.onClick = function(event, elements) {
            if (elements.length > 0) {
                const baseUrl = getBaseUrl() || window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
                window.location.href = baseUrl + '/pages/cases/cases-table.php';
            }
        };
    }
}

// Toggle chart fullscreen mode
function toggleChartFullscreen(chartId, chartName) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    const chartCard = canvas.closest('.chart-card');
    if (!chartCard) return;
    
    // Check if already in fullscreen
    if (chartCard.classList.contains('chart-fullscreen')) {
        // Exit fullscreen
        chartCard.classList.remove('chart-fullscreen');
        document.body.classList.remove('body-no-scroll');
        // Resize chart
        const chart = chartId === 'systemOverviewChart' ? dashboardCharts.systemOverview :
                     chartId === 'statusDistributionChart' ? dashboardCharts.statusDistribution :
                     chartId === 'casesChart' ? dashboardCharts.cases :
                     chartId === 'activityTrendsChart' ? dashboardCharts.activityTrends : null;
        if (chart && typeof chart.resize === 'function') {
            setTimeout(() => chart.resize(), 100);
        }
    } else {
        // Enter fullscreen
        chartCard.classList.add('chart-fullscreen');
        document.body.classList.add('body-no-scroll');
        // Resize chart
        setTimeout(() => {
            const chart = chartId === 'systemOverviewChart' ? dashboardCharts.systemOverview :
                         chartId === 'statusDistributionChart' ? dashboardCharts.statusDistribution :
                         chartId === 'casesChart' ? dashboardCharts.cases :
                         chartId === 'activityTrendsChart' ? dashboardCharts.activityTrends : null;
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        }, 100);
    }
}

// Print chart
function printChart(chartId, chartName) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Please allow popups to print charts');
        return;
    }
    
    const chartCard = canvas.closest('.chart-card');
    const chartTitle = chartCard?.querySelector('h3')?.textContent?.trim() || chartName;
    
    // Get chart image
    const chartImage = canvas.toDataURL('image/png');
    
    // Create print HTML (CSS moved to dashboard.css)
    const baseUrl = getBaseUrl() || window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${chartTitle}</title>
            <link rel="stylesheet" href="${baseUrl}/css/dashboard.css">
        </head>
        <body class="print-body">
            <div class="print-header">
                <h1>${chartTitle}</h1>
                <p>Generated on ${new Date().toLocaleString()}</p>
            </div>
            <div class="print-chart">
                <img src="${chartImage}" alt="${chartTitle}">
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for image to load, then print
    printWindow.addEventListener('load', function() {
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    });
}

// Setup date range selector for Activity Trends
function setupActivityTrendsDateRange() {
    const rangeSelect = document.getElementById('activityTrendsRange');
    if (!rangeSelect) return;
    
    rangeSelect.addEventListener('change', function() {
        const parsed = parseInt(this.value);
        const days = (!isNaN(parsed) && parsed >= 1 && parsed <= 365) ? parsed : 30;
        updateActivityTrendsChart(days);
    });
}

// Update Activity Trends chart with new date range
// Add debouncing to prevent rapid updates
let updateActivityTrendsTimer = null;
async function updateActivityTrendsChart(days = 7) {
    // Validate days parameter
    if (isNaN(days) || days < 1 || days > 365) {
        days = 30; // Default to 30 if invalid
    }
    
    // Debounce rapid calls
    if (updateActivityTrendsTimer) {
        clearTimeout(updateActivityTrendsTimer);
    }
    
    updateActivityTrendsTimer = setTimeout(async () => {
        await performActivityTrendsUpdate(days);
        updateActivityTrendsTimer = null;
    }, 300);
}

// Actual update function
async function performActivityTrendsUpdate(days = 7) {
    // Validate days parameter again
    if (isNaN(days) || days < 1 || days > 365) {
        days = 30;
    }
    
    const activityTrendsCtx = document.getElementById('activityTrendsChart');
    if (!activityTrendsCtx) return;
    
    // Show loading state
    const container = activityTrendsCtx.parentElement;
    const originalContent = container.innerHTML;
    
    // Destroy existing chart BEFORE replacing innerHTML
    if (dashboardCharts.activityTrends) {
        try {
            dashboardCharts.activityTrends.destroy();
        } catch (e) {
            console.warn('Error destroying chart before update:', e);
        }
        dashboardCharts.activityTrends = null;
    }
    
    // Replace innerHTML after destroying chart
    container.innerHTML = '<div class="chart-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading chart...</p></div>';
    
    try {
        
        // Generate labels for selected days
        const labels = [];
        for (let i = days - 1; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        }
        
        // Fetch activity data
        let activityData;
        try {
            activityData = await fetchActivityTrendsData(labels, days);
            // Validate data structure
            if (!activityData || !Array.isArray(activityData.activities) || !Array.isArray(activityData.reports)) {
                throw new Error('Invalid data structure');
            }
            if (activityData.activities.length !== labels.length || activityData.reports.length !== labels.length) {
                throw new Error('Data length mismatch');
            }
        } catch (error) {
            console.warn('Error fetching activity trends data, using sample data:', error);
            // Generate sample data as fallback
            const generateSampleData = (base, variance) => {
                return labels.map(() => Math.max(0, Math.floor(base + (Math.random() * variance * 2) - variance)));
            };
            activityData = {
                activities: generateSampleData(10, 5),
                reports: generateSampleData(5, 3)
            };
        }
        
        // Restore canvas
        container.innerHTML = originalContent;
        const newCanvas = document.getElementById('activityTrendsChart');
        if (!newCanvas) {
            console.error('Canvas element not found after restore');
            return;
        }
        
        // Ensure canvas is not already in use by checking Chart.js registry
        if (typeof Chart !== 'undefined' && Chart.getChart(newCanvas)) {
            try {
                Chart.getChart(newCanvas).destroy();
            } catch (e) {
                console.warn('Error destroying existing chart from registry:', e);
            }
        }
        
        // Create new chart
        const colors = {
            primary: '#667eea',
            success: '#28a745'
        };
        
        dashboardCharts.activityTrends = new Chart(newCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Activities',
                    data: Array.isArray(activityData.activities) ? activityData.activities.map(v => Math.max(0, Number(v) || 0)) : [],
                    borderColor: colors.primary,
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: colors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Reports',
                    data: Array.isArray(activityData.reports) ? activityData.reports.map(v => Math.max(0, Number(v) || 0)) : [],
                    borderColor: colors.success,
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: colors.success,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#fff',
                            font: { size: 12 },
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: colors.primary,
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8,
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#ccc', font: { size: 11 } },
                        grid: { color: 'rgba(255, 255, 255, 0.1)', display: false }
                    },
                    y: {
                        ticks: { color: '#ccc', font: { size: 11 } },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        beginAtZero: true
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
        
        // Re-setup click navigation
        setupChartClickNavigation();
    } catch (error) {
        console.error('Error updating activity trends chart:', error);
        if (container) {
            container.innerHTML = '<div class="chart-error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading chart</p></div>';
        }
    }
}

// Initialize chart enhancements after DOM is ready
// This will be called after charts are initialized (called from initializeDashboardCharts)
function initializeChartEnhancements() {
    // Only set up if not already initialized (prevent duplicate handlers)
    if (window.chartEnhancementsInitialized) {
        return;
    }
    window.chartEnhancementsInitialized = true;
    
    setupChartActions();
    setupChartClickNavigation();
    setupActivityTrendsDateRange();
}

// Also call on DOM ready as fallback (in case charts initialize before DOM ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Charts will call initializeChartEnhancements themselves, but this is a fallback
        setTimeout(() => {
            if (!window.chartEnhancementsInitialized && typeof initializeChartEnhancements === 'function') {
                initializeChartEnhancements();
            }
        }, 2000);
    });
} else {
    // Fallback for already loaded DOM
    setTimeout(() => {
        if (!window.chartEnhancementsInitialized && typeof initializeChartEnhancements === 'function') {
            initializeChartEnhancements();
        }
    }, 2000);
}
