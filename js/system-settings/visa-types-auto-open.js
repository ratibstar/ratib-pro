/**
 * EN: Implements frontend interaction behavior in `js/system-settings/visa-types-auto-open.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/system-settings/visa-types-auto-open.js`.
 */
// Visa Types Auto-Open Script
// Moved from inline script in pages/system-settings.php

(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const setting = urlParams.get('setting');
    
    if (setting === 'visa_types') {
        // In embedded mode (control panel iframe), never hide content - modal may fail to open
        var isEmbedded = (typeof window !== 'undefined' && window.self !== window.top);
        if (!isEmbedded) {
            const content = document.getElementById('systemSettingsContent');
            if (content) {
                content.style.display = 'none';
                content.style.visibility = 'hidden';
            }
        }
        
        // Function to open visa types modal
        function openVisaTypes() {
            if (window.modernForms && typeof window.modernForms.openSettingModal === 'function') {
                try {
                    window.modernForms.openSettingModal('visa_types');
                    // Clean URL immediately
                    window.history.replaceState({}, document.title, window.location.pathname);
                    return true;
                } catch (e) {
                    console.error('Error opening modal:', e);
                    return false;
                }
            }
            return false;
        }
        
        // Try to click the button directly (fastest method)
        function clickVisaButton() {
            const btn = document.querySelector('[data-action="open-setting-modal"][data-setting="visa_types"]');
            if (btn) {
                try {
                    btn.click();
                    // Clean URL immediately
                    window.history.replaceState({}, document.title, window.location.pathname);
                    return true;
                } catch (e) {
                    console.error('Error clicking button:', e);
                    return false;
                }
            }
            return false;
        }
        
        // Check if modal is already open
        function isModalOpen() {
            const modal = document.getElementById('mainModal');
            return modal && modal.classList.contains('show');
        }
        
        // Open modal immediately
        function tryOpenModal() {
            if (isModalOpen()) {
                return true; // Already open
            }
            
            // Try button click first (fastest)
            if (clickVisaButton()) {
                return true;
            }
            
            // Fallback to API
            if (openVisaTypes()) {
                return true;
            }
            
            return false;
        }
        
        // Try immediately if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Try immediately
                if (!tryOpenModal()) {
                    // Retry with faster polling
                    const retry = setInterval(function() {
                        if (tryOpenModal()) {
                            clearInterval(retry);
                        }
                    }, 30);
                    setTimeout(function() { clearInterval(retry); }, 2000);
                }
            });
        } else {
            // DOM already loaded - try immediately
            if (!tryOpenModal()) {
                const retry = setInterval(function() {
                    if (tryOpenModal()) {
                        clearInterval(retry);
                    }
                }, 30);
                setTimeout(function() { clearInterval(retry); }, 2000);
            }
        }
        
        // Also try on window load as backup
        window.addEventListener('load', function() {
            if (!isModalOpen()) {
                tryOpenModal();
            }
        });
        
        // Show System Settings page when modal is closed
        function showSystemSettingsPage() {
            const content = document.getElementById('systemSettingsContent');
            if (content) {
                content.style.display = '';
                content.style.visibility = '';
            }
        }
        
        // Monitor modal close events
        const modal = document.getElementById('mainModal');
        if (modal) {
            // Use MutationObserver to watch for class changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const modal = document.getElementById('mainModal');
                        if (modal && !modal.classList.contains('show')) {
                            // Modal was closed
                            showSystemSettingsPage();
                        }
                    }
                });
            });
            
            observer.observe(modal, {
                attributes: true,
                attributeFilter: ['class']
            });
            
            // Also listen for click events on close buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="close-modal"]') || 
                    e.target.closest('.modal-close') ||
                    (e.target.classList.contains('modern-modal') && !e.target.closest('.modern-modal-content'))) {
                    // Modal is being closed
                    setTimeout(function() {
                        if (!isModalOpen()) {
                            showSystemSettingsPage();
                        }
                    }, 100);
                }
            });
            
            // Listen for Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isModalOpen()) {
                    setTimeout(function() {
                        if (!isModalOpen()) {
                            showSystemSettingsPage();
                        }
                    }, 100);
                }
            });
        }
    }
})();

