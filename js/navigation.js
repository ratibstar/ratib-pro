/**
 * EN: Implements frontend interaction behavior in `js/navigation.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/navigation.js`.
 */
// Navigation JavaScript Functions - Global for all pages
// Prevent VoiceNarration conflicts from cached scripts
(function() {
    'use strict';
    // Remove VoiceNarration if it exists (from cached scripts)
    if (typeof window.VoiceNarration !== 'undefined') {
        try {
            delete window.VoiceNarration;
        } catch(e) {}
    }
    // Block VoiceNarration declaration to prevent errors
    try {
        Object.defineProperty(window, 'VoiceNarration', {
            get: function() { return undefined; },
            set: function() { return; },
            configurable: true
        });
    } catch(e) {}
})();

document.addEventListener('DOMContentLoaded', function() {
    // Desktop Navigation toggle
    const navTriggerArea = document.querySelector('.nav-trigger-area');
    const mainNav = document.querySelector('.main-nav') || document.getElementById('mainNav');
    
    if (navTriggerArea && mainNav) {
        navTriggerArea.addEventListener('mouseenter', function() {
            mainNav.classList.add('nav-expanded');
        });
        
        mainNav.addEventListener('mouseleave', function() {
            mainNav.classList.remove('nav-expanded');
        });
    }
    
    // Setup mobile navigation for all pages
    setupGlobalMobileNavigation();
    
    // Retry setup if elements not found (for dynamically loaded content)
    setTimeout(function() {
        const toggle = document.getElementById('mobileNavToggle');
        if (toggle && !toggle.hasAttribute('data-global-nav-setup')) {
            setupGlobalMobileNavigation();
        }
    }, 500);
    
    // Handle logo error fallback
    const mainLogo = document.getElementById('mainLogo');
    if (mainLogo) {
        mainLogo.addEventListener('error', function() {
            const fallbackSvg = this.getAttribute('data-fallback-svg');
            if (fallbackSvg) {
                this.src = fallbackSvg;
                this.onerror = null; // Prevent infinite loop
            }
        });
    }
});

// Global Mobile Navigation Setup - Works on all pages
function setupGlobalMobileNavigation() {
    const mobileNavToggle = document.getElementById('mobileNavToggle') || document.querySelector('.nav-toggle');
    const mainNav = document.getElementById('mainNav') || document.querySelector('.main-nav');
    const overlay = document.getElementById('mobileNavOverlay') || document.querySelector('.nav-overlay');
    
    if (!mobileNavToggle || !mainNav || !overlay) {
        setTimeout(setupGlobalMobileNavigation, 100);
        return;
    }
    
    // Prevent double-setup
    if (mobileNavToggle.hasAttribute('data-global-nav-setup')) {
        return;
    }
    mobileNavToggle.setAttribute('data-global-nav-setup', 'true');
    
    // Force styles for clickability
    mobileNavToggle.style.cssText += `
        pointer-events: auto !important;
        z-index: 10001 !important;
        cursor: pointer !important;
        touch-action: manipulation !important;
    `;
    
    // Prevent double-toggle with flag
    let isToggling = false;
    
    // Single toggle handler
    const handleToggle = function(e) {
        if (isToggling) {
            return false;
        }
        
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        isToggling = true;
        toggleMobileNav();
        
        setTimeout(() => {
            isToggling = false;
        }, 400);
        
        return false;
    };
    
    // Direct event handler - no cloning to avoid issues
    // Remove any existing listeners first
    const newToggle = mobileNavToggle.cloneNode(true);
    mobileNavToggle.parentNode.replaceChild(newToggle, mobileNavToggle);
    newToggle.setAttribute('data-global-nav-setup', 'true');
    
    // Ensure button is always clickable
    newToggle.style.pointerEvents = 'auto';
    newToggle.style.cursor = 'pointer';
    newToggle.style.zIndex = '10001';
    newToggle.style.touchAction = 'manipulation';
    newToggle.style.position = 'fixed';
    
    // Add inline onclick as fallback - direct call
    newToggle.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        toggleMobileNav();
        return false;
    };
    
    // Use click event (works on both desktop and mobile)
    newToggle.addEventListener('click', handleToggle, true);
    
    // Also handle touchstart for better mobile support
    newToggle.addEventListener('touchstart', function(e) {
        e.preventDefault();
        e.stopPropagation();
        handleToggle(e);
    }, { passive: false, capture: true });
    
    // Also handle mousedown as backup
    newToggle.addEventListener('mousedown', function(e) {
        e.preventDefault();
        e.stopPropagation();
        handleToggle(e);
    }, true);
    
    // Overlay click handler with delay-ready check
    const newOverlay = overlay.cloneNode(true);
    overlay.parentNode.replaceChild(newOverlay, overlay);
    
    newOverlay.addEventListener('click', function(e) {
        // Only close if clicking overlay itself AND delay-ready class is present
        if (e.target === newOverlay && newOverlay.classList.contains('delay-ready')) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileNav();
        }
    }, true);
    
    // Prevent nav clicks from bubbling to overlay
    mainNav.addEventListener('click', function(e) {
        e.stopPropagation();
    }, true);
    
    mainNav.addEventListener('mousedown', function(e) {
        e.stopPropagation();
    }, true);
    
    // Close mobile nav when clicking on nav links
    const navLinks = document.querySelectorAll('.main-nav .nav-link, .nav-item');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Handle accounting info link
            if (this.classList.contains('accounting-info-link') || this.dataset.action === 'show-accounting-info') {
                e.preventDefault();
                e.stopPropagation();
                alert('Accounting is now available via modals in each entity page.\n\nClick the "Accounting" button on any of these pages:\n• Agents\n• Workers\n• Subagents\n• HR\n\nThis allows you to manage financial transactions specific to each entity.');
                return false;
            }
            
            // Only close on mobile devices
            if (window.innerWidth <= 768) {
                closeMobileNav();
            }
        });
    });
    
    // Also handle data-action for accounting info
    document.addEventListener('click', function(e) {
        const actionLink = e.target.closest('[data-action="show-accounting-info"]');
        if (actionLink) {
            e.preventDefault();
            e.stopPropagation();
            alert('Accounting is now available via modals in each entity page.\n\nClick the "Accounting" button on any of these pages:\n• Agents\n• Workers\n• Subagents\n• HR\n\nThis allows you to manage financial transactions specific to each entity.');
        }
    });
    
}

// Mobile Navigation Functions - Global for all pages
function toggleMobileNav() {
    const mainNav = document.getElementById('mainNav') || document.querySelector('.main-nav');
    const overlay = document.getElementById('mobileNavOverlay') || document.querySelector('.nav-overlay');
    const toggleBtn = document.getElementById('mobileNavToggle') || document.querySelector('.nav-toggle');
    
    if (mainNav && overlay) {
        const isOpening = !mainNav.classList.contains('open');
        mainNav.classList.toggle('open');
        overlay.classList.toggle('active');
        
        // Ensure overlay is visible
        overlay.style.display = 'block';
        overlay.style.visibility = 'visible';
        
        // Prevent body scroll when nav is open
        if (isOpening) {
            document.body.classList.add('nav-open');
            // Delay overlay clickability to prevent immediate closing
            overlay.classList.remove('delay-ready');
            setTimeout(() => {
                overlay.classList.add('delay-ready');
            }, 300);
        } else {
            document.body.classList.remove('nav-open');
            overlay.classList.remove('delay-ready');
        }
        
        // Change hamburger icon
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                if (mainNav.classList.contains('open')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            }
        }
    } else {
        console.error('Navigation elements not found', { mainNav: !!mainNav, overlay: !!overlay });
    }
}

function closeMobileNav() {
    const mainNav = document.getElementById('mainNav') || document.querySelector('.main-nav');
    const overlay = document.getElementById('mobileNavOverlay') || document.querySelector('.nav-overlay');
    const toggleBtn = document.getElementById('mobileNavToggle') || document.querySelector('.nav-toggle');
    
    if (mainNav && overlay) {
        mainNav.classList.remove('open');
        overlay.classList.remove('active');
        overlay.classList.remove('delay-ready');
        document.body.classList.remove('nav-open');
        
        // Reset hamburger icon
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-bars';
            }
        }
    }
}

// Make functions globally available for inline onclick handlers
window.toggleMobileNav = toggleMobileNav;
window.closeMobileNav = closeMobileNav;

// Close mobile nav on window resize to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeMobileNav();
    }
});

// Close mobile nav on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileNav();
    }
});

