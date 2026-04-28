/**
 * EN: Implements frontend interaction behavior in `js/hr/hr-page.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/hr/hr-page.js`.
 */
// HR Page Initialization
// Check for edit/view parameters and open modals automatically
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    if (editId) {
        // Wait for HR functions to be ready, then open edit modal
        setTimeout(() => {
            if (window.editEmployee) {
                window.editEmployee(parseInt(editId));
            }
        }, 1000);
    } else if (viewId) {
        // Wait for HR functions to be ready, then open view modal
        setTimeout(() => {
            if (window.showHRMessage) {
                window.showHRMessage('View functionality for employee ID: ' + viewId, 'info');
            }
        }, 1000);
    }
});
