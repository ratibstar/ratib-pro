/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/permissions.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/permissions.js`.
 */
/**
 * Frontend Permission Checking System
 * Automatically hides UI elements based on user permissions
 */

// Global permissions object
window.UserPermissions = {
    permissions: [],
    isAdmin: false,
    loaded: false,
    
    /**
     * Load user permissions from API
     */
    async load() {
        const el = document.getElementById('app-config');
        const isControl = el && el.getAttribute('data-control') === '1';
        try {
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '') || '';
            const apiPath = isControl ? '/api/control/get-current-user-permissions.php' : '/api/get-current-user-permissions.php';
            const response = await fetch(baseUrl + apiPath, { credentials: 'include' });
            const data = await response.json();
            
            if (data.success) {
                this.permissions = data.permissions || [];
                this.isAdmin = data.is_admin || false;
                this.loaded = true;
                
                // Apply permission filtering to page
                this.applyPermissions();
                
                
                // Re-apply after a short delay to catch any dynamically loaded content
                setTimeout(() => {
                    this.applyPermissions();
                }, 500);
                
                // Also re-apply when DOM changes
                const observer = new MutationObserver(() => {
                    this.applyPermissions();
                });
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                this.permissions = [];
                this.isAdmin = false;
                this.loaded = true;
            }
        } catch (error) {
            this.permissions = isControl ? ['*'] : [];
            this.isAdmin = isControl;
            this.loaded = true;
        }
    },
    
    /**
     * Check if user has a specific permission
     * @param {string} permission - Permission ID to check
     * @returns {boolean}
     */
    has(permission) {
        if (!this.loaded) {
            return false;
        }
        
        // Control panel: hide_dashboard_* are only granted when explicitly in the list (never by * or isAdmin)
        const el = document.getElementById('app-config');
        const isControl = el && el.getAttribute('data-control') === '1';
        if (isControl && typeof permission === 'string' && permission.indexOf('hide_dashboard_') === 0) {
            return this.permissions.includes(permission);
        }
        
        // Admin has all permissions - ALWAYS grant access (except hide_dashboard_* above)
        if (this.isAdmin || this.permissions.includes('*')) {
            return true;
        }
        
        // Regular users: Check if permission exists in array
        // Empty array = no permissions = see nothing
        return this.permissions.includes(permission);
    },
    
    /**
     * Check if user has any of the specified permissions
     * @param {string[]} permissions - Array of permission IDs
     * @returns {boolean}
     */
    hasAny(permissions) {
        if (!Array.isArray(permissions)) {
            return false;
        }
        
        return permissions.some(perm => this.has(perm));
    },
    
    /**
     * Check if user has all of the specified permissions
     * @param {string[]} permissions - Array of permission IDs
     * @returns {boolean}
     */
    hasAll(permissions) {
        if (!Array.isArray(permissions)) {
            return false;
        }
        
        return permissions.every(perm => this.has(perm));
    },
    
    /**
     * Apply permissions to page - hide elements without required permissions
     * Admin: Shows everything
     * Regular users: Shows NOTHING unless they have explicit permissions
     */
    applyPermissions() {
        if (!this.loaded) {
            return;
        }
        
        // Find all elements with data-permission attribute
        const elements = document.querySelectorAll('[data-permission]');
        let hiddenCount = 0;
        let shownCount = 0;
        
        elements.forEach(element => {
            const requiredPerms = element.getAttribute('data-permission');
            
            if (!requiredPerms) {
                return;
            }
            
            // Support multiple permissions (comma-separated, OR logic)
            const perms = requiredPerms.split(',').map(p => p.trim());
            const hasAccess = this.hasAny(perms);
            
            if (!hasAccess) {
                // Hide the element - user doesn't have permission
                element.style.display = 'none';
                element.style.visibility = 'hidden';
                element.style.opacity = '0';
                element.style.pointerEvents = 'none';
                element.classList.add('permission-denied');
                element.setAttribute('aria-hidden', 'true');
                hiddenCount++;
            } else {
                // Show the element - user has permission (or is admin)
                // Remove all hiding styles and classes
                element.style.display = '';
                element.style.visibility = '';
                element.style.opacity = '';
                element.style.pointerEvents = '';
                element.classList.remove('permission-denied');
                element.removeAttribute('aria-hidden');
                shownCount++;
            }
        });
        
        // Find elements with data-permission-all (requires ALL permissions)
        const allElements = document.querySelectorAll('[data-permission-all]');
        
        allElements.forEach(element => {
            const requiredPerms = element.getAttribute('data-permission-all');
            
            if (!requiredPerms) {
                return;
            }
            
            const perms = requiredPerms.split(',').map(p => p.trim());
            const hasAccess = this.hasAll(perms);
            
            if (!hasAccess) {
                element.style.display = 'none';
                element.classList.add('permission-denied');
                element.setAttribute('aria-hidden', 'true');
                hiddenCount++;
            } else {
                element.style.display = '';
                element.classList.remove('permission-denied');
                element.removeAttribute('aria-hidden');
                shownCount++;
            }
        });
        
    }
};

// Auto-load permissions when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.UserPermissions.load();
    });
} else {
    window.UserPermissions.load();
}

