/**
 * EN: Implements frontend interaction behavior in `js/visa.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/visa.js`.
 */
// Visa Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadVisaStats();
    setupVisaEventListeners();
});

function setupVisaEventListeners() {
    // Action buttons
    const addVisaApplicationBtn = document.getElementById('addVisaApplicationBtn');
    if (addVisaApplicationBtn) {
        addVisaApplicationBtn.addEventListener('click', addVisaApplication);
    }
    
    const viewAllApplicationsBtn = document.getElementById('viewAllApplicationsBtn');
    if (viewAllApplicationsBtn) {
        viewAllApplicationsBtn.addEventListener('click', viewAllApplications);
    }
    
    const manageVisaTypesBtn = document.getElementById('manageVisaTypesBtn');
    if (manageVisaTypesBtn) {
        manageVisaTypesBtn.addEventListener('click', manageVisaTypes);
    }
    
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', bulkApprove);
    }
    
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    if (bulkRejectBtn) {
        bulkRejectBtn.addEventListener('click', bulkReject);
    }
    
    const exportApplicationsBtn = document.getElementById('exportApplicationsBtn');
    if (exportApplicationsBtn) {
        exportApplicationsBtn.addEventListener('click', exportApplications);
    }
    
    // Close modal buttons
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            closeVisaModal(modalId);
        });
    });
    
    // Confirm delete button
    const confirmDeleteVisaBtn = document.getElementById('confirmDeleteVisaBtn');
    if (confirmDeleteVisaBtn) {
        confirmDeleteVisaBtn.addEventListener('click', confirmDeleteVisa);
    }
}

function closeVisaModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.classList.add('hidden', 'd-none');
    }
}

function addVisaApplication() {
    const modal = document.getElementById('addVisaModal');
    if (modal) {
        modal.classList.remove('hidden', 'd-none');
        modal.classList.add('show');
    }
}

function viewAllApplications() {
    // Implementation needed
    console.log('View all applications');
}

function manageVisaTypes() {
    // Implementation needed
    console.log('Manage visa types');
}

function bulkApprove() {
    // Implementation needed
    console.log('Bulk approve');
}

function bulkReject() {
    // Implementation needed
    console.log('Bulk reject');
}

function exportApplications() {
    // Implementation needed
    console.log('Export applications');
}

function confirmDeleteVisa() {
    // Implementation needed
    console.log('Confirm delete visa');
}

// Load visa statistics from API
async function loadVisaStats() {
    try {
        const apiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
        const response = await fetch(`${apiBase}/visa/stats.php`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const stats = result.data;
            document.getElementById('totalApplications').textContent = stats.total || 0;
            document.getElementById('pendingApplications').textContent = stats.pending || 0;
            document.getElementById('approvedApplications').textContent = stats.approved || 0;
            document.getElementById('rejectedApplications').textContent = stats.rejected || 0;
        } else {
            console.error('Failed to load visa stats:', result.message);
            document.getElementById('totalApplications').textContent = '0';
            document.getElementById('pendingApplications').textContent = '0';
            document.getElementById('approvedApplications').textContent = '0';
            document.getElementById('rejectedApplications').textContent = '0';
        }
    } catch (error) {
        console.error('Error loading visa stats:', error);
        document.getElementById('totalApplications').textContent = '0';
        document.getElementById('pendingApplications').textContent = '0';
        document.getElementById('approvedApplications').textContent = '0';
        document.getElementById('rejectedApplications').textContent = '0';
    }
}

