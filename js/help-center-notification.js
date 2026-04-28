/**
 * EN: Implements frontend interaction behavior in `js/help-center-notification.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/help-center-notification.js`.
 */
// Help Center Notification - Global script for all pages
// Shows notification popup and handles navigation to help center in the same page
// Shows on every page load/navigation and when clicking navbar items
(function() {
    'use strict';
    
    // Prevent multiple initializations if script loads twice
    if (window.helpCenterNotificationLoaded) {
        return;
    }
    window.helpCenterNotificationLoaded = true;
    
    var hasShown = false;
    
    function showHelpCenterNotification() {
        try {
            // Prevent showing multiple times on same page load
            if (hasShown) {
                return;
            }
            
            // Wait for body to be ready
            if (!document.body) {
                setTimeout(showHelpCenterNotification, 50);
                return;
            }
            
            // Check if notification already exists on this page (prevent duplicates)
            const existingNotification = document.querySelector('.help-center-notification');
            if (existingNotification) {
                hasShown = true;
                return; // Already showing on this page
            }
            
            // Get help center URL - use proper path construction
            let helpCenterUrl = '';
            try {
                // Try to get from app-config data attribute
                const appConfig = document.getElementById('app-config');
                if (appConfig) {
                    const basePath = appConfig.getAttribute('data-base-path') || '';
                    helpCenterUrl = basePath + '/pages/help-center.php';
                }
            } catch(e) {}
            
            // Fallback: construct from current location
            if (!helpCenterUrl) {
                const currentPath = window.location.pathname;
                // Remove filename and get base path
                const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                // If we're in pages/, go up one level, otherwise stay
                if (basePath.endsWith('/pages')) {
                    helpCenterUrl = basePath + '/help-center.php';
                } else {
                    helpCenterUrl = basePath + '/pages/help-center.php';
                }
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'help-center-notification';
            notification.innerHTML = 
                '<div class="help-center-notification-content">' +
                    '<div class="help-center-notification-icon">' +
                        '<i class="fas fa-question-circle"></i>' +
                    '</div>' +
                    '<div class="help-center-notification-text">' +
                        '<h4>📚 Help & Learning Center Available!</h4>' +
                        '<p>Master the system with step-by-step guides, interactive tutorials, and expert tips.</p>' +
                    '</div>' +
                    '<div class="help-center-notification-actions">' +
                        '<button class="help-center-notification-btn" data-help-url="' + helpCenterUrl + '">Explore Now</button>' +
                        '<button class="help-center-notification-close" aria-label="Close notification">' +
                            '<i class="fas fa-times"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            
            // Add to page
            document.body.appendChild(notification);
            hasShown = true;
            
            // Force a reflow to ensure element is in DOM
            void notification.offsetHeight;
            
            // Show notification with animation
            setTimeout(function() {
                notification.classList.add('show');
            }, 100);
            
            // Explore Now button handler - navigate in same page
            const exploreBtn = notification.querySelector('.help-center-notification-btn');
            if (exploreBtn) {
                exploreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = exploreBtn.getAttribute('data-help-url');
                    if (url) {
                        // Navigate in the same page
                        window.location.href = url;
                    }
                });
            }
            
            // Close button handler
            const closeBtn = notification.querySelector('.help-center-notification-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    notification.classList.remove('show');
                    notification.classList.add('hide');
                    setTimeout(function() {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                });
            }
            
            // Auto-close after 10 seconds
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.classList.remove('show');
                    notification.classList.add('hide');
                    setTimeout(function() {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }, 10000);
        } catch(e) {
            console.error('Error showing help center notification:', e);
        }
    }
    
    // Expose function globally
    window.showHelpCenterNotification = showHelpCenterNotification;
    
    // Reset hasShown flag when page loads (for navigation)
    function resetState() {
        hasShown = false;
    }
    
    // Listen for navbar clicks and show notification after navigation
    function setupNavbarListeners() {
        // Wait for nav to be ready
        if (!document.body) {
            setTimeout(setupNavbarListeners, 100);
            return;
        }
        
        // Find all navbar links
        const navLinks = document.querySelectorAll('.nav-link, .main-nav a, nav a[href]');
        
        navLinks.forEach(function(link) {
            // Skip if it's the help center link itself or logout
            const href = link.getAttribute('href') || '';
            if (href.includes('help-center') || href.includes('logout')) {
                return;
            }
            
            // Add click listener
            link.addEventListener('click', function() {
                // Reset state so notification shows on new page
                resetState();
                // Store flag in sessionStorage to show after page loads
                if (typeof Storage !== 'undefined') {
                    sessionStorage.setItem('showHelpNotification', 'true');
                }
            });
        });
    }
    
    // Initialize - run immediately and with multiple fallbacks
    function init() {
        // Reset state on page load
        resetState();
        
        // Check if we should show (from navbar click)
        var shouldShow = false;
        if (typeof Storage !== 'undefined') {
            shouldShow = sessionStorage.getItem('showHelpNotification') === 'true';
            if (shouldShow) {
                sessionStorage.removeItem('showHelpNotification');
            }
        }
        
        // Check if body is ready
        if (document.body && document.body.parentNode) {
            // Small delay to ensure page is rendered
            setTimeout(function() {
                if (shouldShow || !hasShown) {
                    showHelpCenterNotification();
                }
            }, 500);
        } else {
            // Keep trying until body is ready
            var attempts = 0;
            var maxAttempts = 30; // 30 * 100ms = 3 seconds
            var checkInterval = setInterval(function() {
                attempts++;
                if (document.body && document.body.parentNode) {
                    clearInterval(checkInterval);
                    setTimeout(function() {
                        if (shouldShow || !hasShown) {
                            showHelpCenterNotification();
                        }
                    }, 500);
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                }
            }, 100);
        }
        
        // Setup navbar listeners
        setupNavbarListeners();
    }
    
    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already ready, run immediately
        init();
    }
    
    // Also run on window load as backup
    window.addEventListener('load', function() {
        if (!hasShown) {
            var shouldShow = false;
            if (typeof Storage !== 'undefined') {
                shouldShow = sessionStorage.getItem('showHelpNotification') === 'true';
                if (shouldShow) {
                    sessionStorage.removeItem('showHelpNotification');
                }
            }
            if (shouldShow || !hasShown) {
                setTimeout(showHelpCenterNotification, 500);
            }
        }
    }, { once: true });
    
    // Final fallback - run after 2 seconds
    setTimeout(function() {
        if (!hasShown && document.body) {
            var shouldShow = false;
            if (typeof Storage !== 'undefined') {
                shouldShow = sessionStorage.getItem('showHelpNotification') === 'true';
                if (shouldShow) {
                    sessionStorage.removeItem('showHelpNotification');
                }
            }
            if (shouldShow || !hasShown) {
                showHelpCenterNotification();
            }
        }
    }, 2000);
})();
