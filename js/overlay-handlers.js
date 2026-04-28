/**
 * EN: Implements frontend interaction behavior in `js/overlay-handlers.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/overlay-handlers.js`.
 */
// Overlay Close Handlers
// Handles closing of overlay modals using data attributes instead of inline onclick handlers

document.addEventListener('DOMContentLoaded', function() {
    // Handle overlay close buttons with data-overlay-close attribute
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-overlay-close]');
        if (closeBtn) {
            const overlayId = closeBtn.getAttribute('data-overlay-close');
            const overlay = document.getElementById(overlayId);
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        // Handle warning close buttons with data-warning-close attribute
        const warningBtn = e.target.closest('[data-warning-close]');
        if (warningBtn) {
            const warningOverlay = warningBtn.closest('.warning-overlay');
            if (warningOverlay) {
                warningOverlay.style.display = 'none';
            }
        }
    });
});

