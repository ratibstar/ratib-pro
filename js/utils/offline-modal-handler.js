/**
 * EN: Implements frontend interaction behavior in `js/utils/offline-modal-handler.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/offline-modal-handler.js`.
 */
/**
 * Offline Modal Handler
 * Handles hiding closing alert modal when offline
 * Runs immediately before any other scripts to prevent flash
 */
(function() {
    'use strict';
    
    // CRITICAL: Add offline class to html/body immediately for CSS to hide modal
    if (!navigator.onLine) {
        document.documentElement.classList.add('offline-mode');
        if (document.body) {
            document.body.classList.add('offline-mode');
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('offline-mode');
            });
        }
    }
    
    // Block UniversalClosingAlerts.showClosingAlert BEFORE it can be called
    if (!navigator.onLine) {
        // Override the function immediately
        window.UniversalClosingAlerts = window.UniversalClosingAlerts || {};
        window.UniversalClosingAlerts.showClosingAlert = function() {
            return Promise.resolve(true); // Auto-confirm
        };
        
        // Also hide modal immediately if it exists - use CSS class instead of inline styles
        function hideModal() {
            const modal = document.getElementById('closingAlertModal');
            if (modal) {
                modal.setAttribute('data-offline-hidden', 'true');
                modal.classList.add('offline-hidden');
            }
        }
        
        // Try to hide immediately
        if (document.body) {
            hideModal();
        } else {
            document.addEventListener('DOMContentLoaded', hideModal);
        }
        
        // Watch for offline event
        window.addEventListener('offline', function() {
            document.documentElement.classList.add('offline-mode');
            if (document.body) document.body.classList.add('offline-mode');
            hideModal();
        });
        
        // Also hide on any mutation
        if (document.body) {
            const observer = new MutationObserver(function() {
                hideModal();
            });
            const modal = document.getElementById('closingAlertModal');
            if (modal) {
                observer.observe(modal, {
                    attributes: true,
                    attributeFilter: ['style', 'class'],
                    childList: false,
                    subtree: false
                });
            } else {
                // Wait for modal to be added
                const bodyObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.id === 'closingAlertModal' || (node.querySelector && node.querySelector('#closingAlertModal'))) {
                                hideModal();
                            }
                        });
                    });
                });
                bodyObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }
    }
})();
