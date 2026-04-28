/**
 * EN: Implements frontend interaction behavior in `js/worker/modal-handlers.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/modal-handlers.js`.
 */
/* ========================================
   MODAL HANDLERS - SEPARATED FROM INLINE JAVASCRIPT
   ======================================== */

// Debug Configuration - Set to false for production
window.DEBUG_MODE = window.DEBUG_MODE !== undefined ? window.DEBUG_MODE : false;
const debugModal = {
    log: (...args) => window.DEBUG_MODE && console.log('[Modal-Handlers]', ...args),
    error: (...args) => window.DEBUG_MODE && console.error('[Modal-Handlers]', ...args),
    warn: (...args) => window.DEBUG_MODE && console.warn('[Modal-Handlers]', ...args),
    info: (...args) => window.DEBUG_MODE && console.info('[Modal-Handlers]', ...args)
};

// File upload handlers
function setupFileUploadHandlers() {
    // Handle all upload buttons with data-target attribute
    const uploadButtons = document.querySelectorAll('.upload-btn[data-target]');
    uploadButtons.forEach(button => {
        const targetId = button.getAttribute('data-target');
        button.addEventListener('click', () => {
            const fileInput = document.getElementById(targetId);
            if (fileInput) {
                fileInput.click();
            }
        });
    });
}

// Form handlers
function setupFormHandlers() {
    // Close worker form buttons
    const closeWorkerBtns = document.querySelectorAll('#workerFormContainer .close-btn, .btn-cancel');
    closeWorkerBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Check if closeWorkerForm is available, otherwise use fallback
            if (typeof window.closeWorkerForm === 'function') {
                window.closeWorkerForm();
            } else if (typeof closeWorkerForm === 'function') {
                closeWorkerForm();
            } else {
                // Fallback: hide the form container
                const formContainer = document.getElementById('workerFormContainer');
                if (formContainer) {
                    formContainer.classList.remove('show');
                }
            }
        });
    });

    // Save worker form
    const saveWorkerBtn = document.querySelector('.btn-save');
    if (saveWorkerBtn) {
        saveWorkerBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            
            if (typeof window.saveWorker === 'function') {
                window.saveWorker(event);
            } else {
                // Use SimpleAlert if available, otherwise use console error (no alert)
                if (typeof window.SimpleAlert !== 'undefined' && window.SimpleAlert.show) {
                    window.SimpleAlert.show('Error', 'Save function not available. Please refresh the page.', 'danger', { notification: true });
                } else {
                    debugModal.error('Error: Save function not available. Please refresh the page.');
                }
            }
        });
    }
}

// Modal handlers
function setupModalHandlers() {
    // Delete modal handlers
    const deleteModalCancelBtns = document.querySelectorAll('#deleteConfirmModal .btn-cancel, #deleteConfirmModal .close-btn');
    deleteModalCancelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (typeof window.closeDeleteModal === 'function') {
                window.closeDeleteModal();
            } else if (typeof closeDeleteModal === 'function') {
                closeDeleteModal();
            } else {
                const modal = document.getElementById('deleteConfirmModal');
                if (modal) modal.classList.remove('modal-visible');
            }
        });
    });

    const confirmDeleteBtn = document.querySelector('#deleteConfirmModal .btn-delete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', () => {
            if (typeof window.confirmDelete === 'function') {
                window.confirmDelete();
            } else if (typeof confirmDelete === 'function') {
                confirmDelete();
            }
        });
    }

    // Documents modal handlers
    const closeDocumentsBtns = document.querySelectorAll('#documentsModal .close-btn');
    closeDocumentsBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (typeof window.closeDocumentsModal === 'function') {
                window.closeDocumentsModal();
            } else if (typeof closeDocumentsModal === 'function') {
                closeDocumentsModal();
            } else {
                const modal = document.getElementById('documentsModal');
                if (modal) modal.classList.remove('modal-visible', 'show');
            }
        });
    });
    
    // Documents modal close button
    const closeDocumentsModalBtn = document.getElementById('closeDocumentsModal');
    if (closeDocumentsModalBtn) {
        closeDocumentsModalBtn.addEventListener('click', () => {
            if (typeof window.closeDocumentsModal === 'function') {
                window.closeDocumentsModal();
            } else if (typeof closeDocumentsModal === 'function') {
                closeDocumentsModal();
            } else {
                const modal = document.getElementById('documentsModal');
                if (modal) modal.classList.remove('modal-visible', 'show');
            }
        });
    }
}

// Overlay click handlers - using same logic as close/cancel buttons
function setupOverlayHandlers() {
    // Scope to worker form container only
    const workerFormContainer = document.getElementById('workerFormContainer');
    if (!workerFormContainer) {
        // Retry after a delay in case form container isn't ready yet
        setTimeout(setupOverlayHandlers, 500);
        return;
    }
    
    const overlay = workerFormContainer.querySelector('.form-overlay');
    const formWrapper = workerFormContainer.querySelector('.form-wrapper');
    
    // Prevent clicks on form-wrapper from closing the form
    if (formWrapper) {
        formWrapper.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent clicks inside form from bubbling to container
        });
    }
    
    if (overlay) {
        overlay.removeAttribute('onclick');
        overlay.addEventListener('click', async function(e) {
            e.stopPropagation(); // Prevent event bubbling
            
            debugModal.log('[Modal Handlers] Overlay clicked, calling closeWorkerForm');
            
            // Use the same logic as close/cancel buttons - call closeForm directly
            // This ensures consistent behavior - same confirmation logic connected to form
            if (typeof window.closeWorkerForm === 'function') {
                await window.closeWorkerForm();
            } else {
                debugModal.warn('[Modal Handlers] closeWorkerForm not available, using fallback');
                // Fallback: trigger the close button click
                const closeBtn = workerFormContainer.querySelector('.close-btn');
                if (closeBtn) {
                    closeBtn.click();
                } else {
                    // Final fallback: hide the form container
                    workerFormContainer.classList.remove('show');
                }
            }
        });
    } else {
        debugModal.warn('[Modal Handlers] Overlay not found, retrying...');
        // Retry after a delay in case overlay isn't ready yet
        setTimeout(setupOverlayHandlers, 500);
    }
}

// Initialize all handlers when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupFileUploadHandlers();
    // Delay form handlers setup to ensure worker-consolidated.js and worker-form.js are loaded
    setTimeout(() => {
    setupFormHandlers();
        setupOverlayHandlers();
    }, 100);
    setupModalHandlers();
});

// Clean up any remaining inline onclick handlers
function cleanupInlineHandlers() {
    const elementsWithOnclick = document.querySelectorAll('[onclick]');
    elementsWithOnclick.forEach(element => {
        // Silently remove inline onclick handlers without logging
        element.removeAttribute('onclick');
    });
}

// Run cleanup after a short delay to ensure all elements are loaded
setTimeout(cleanupInlineHandlers, 1000);
