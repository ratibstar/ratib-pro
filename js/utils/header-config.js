/**
 * EN: Implements frontend interaction behavior in `js/utils/header-config.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/header-config.js`.
 */
/**
 * Header Configuration and Utilities
 * Handles APP_CONFIG setup and notification badge loading
 */

/**
 * Force Western numerals and English dates globally - NO ARABIC
 * Override Intl.NumberFormat BEFORE any library loads
 */
(function() {
    'use strict';
    
    // Override Intl.NumberFormat BEFORE any library loads
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        var OriginalNumberFormat = Intl.NumberFormat;
        Intl.NumberFormat = function(locales, options) {
            return new OriginalNumberFormat('en-US', options);
        };
        Intl.NumberFormat.prototype = OriginalNumberFormat.prototype;
    }
    
    // Override Number.toLocaleString
    if (typeof Number.prototype.toLocaleString === 'function' && !Number.prototype._hrToLocaleString) {
        Number.prototype._hrToLocaleString = Number.prototype.toLocaleString;
        Number.prototype.toLocaleString = function(locales, options) {
            return this._hrToLocaleString('en-US', options);
        };
    }
    
    // Force all date formatting to English (no Arabic)
    if (typeof Date.prototype.toLocaleDateString === 'function' && !Date.prototype._origToLocaleDateString) {
        Date.prototype._origToLocaleDateString = Date.prototype.toLocaleDateString;
        Date.prototype.toLocaleDateString = function(locales, options) {
            return this._origToLocaleDateString('en-US', options || {});
        };
    }
    
    if (typeof Date.prototype.toLocaleString === 'function' && !Date.prototype._origToLocaleString) {
        Date.prototype._origToLocaleString = Date.prototype.toLocaleString;
        Date.prototype.toLocaleString = function(locales, options) {
            return this._origToLocaleString('en-US', options || {});
        };
    }
})();

/**
 * Header Configuration and Utilities
 * Handles APP_CONFIG setup and notification badge loading
 */
(function() {
    'use strict';
    
    // Set APP_CONFIG from data attributes or window if already set, otherwise set defaults
    if (typeof window.APP_CONFIG === 'undefined') {
        const appConfigEl = document.getElementById('app-config');
        if (appConfigEl) {
            window.APP_CONFIG = {
                baseUrl: appConfigEl.getAttribute('data-base-url') || appConfigEl.getAttribute('data-base-path') || window.BASE_PATH || '',
                apiBase: appConfigEl.getAttribute('data-api-base') || (window.BASE_PATH || '') + '/api',
                siteUrl: appConfigEl.getAttribute('data-site-url') || window.SITE_URL || ''
            };
        } else {
            window.APP_CONFIG = {
                baseUrl: window.BASE_PATH || '',
                apiBase: (window.BASE_PATH || '') + '/api',
                siteUrl: window.SITE_URL || ''
            };
        }
    }
    
    // Set BASE_PATH for backward compatibility (used by worker-consolidated.js)
    if (typeof window.BASE_PATH === 'undefined') {
        const appConfigEl = document.getElementById('app-config');
        if (appConfigEl) {
            window.BASE_PATH = appConfigEl.getAttribute('data-base-path') || appConfigEl.getAttribute('data-base-url') || window.APP_CONFIG.baseUrl || '';
        } else {
            window.BASE_PATH = window.APP_CONFIG.baseUrl || '';
        }
    }
    
    // Set global BASE_PATH for backward compatibility
    window.BASE_PATH = window.APP_CONFIG.baseUrl || '';
    window.API_BASE = window.APP_CONFIG.apiBase || '';

    /** Country-aware logout URL (matches PHP rateb_logout_url()). */
    window.ratebLogoutUrl = function() {
        var appCfgEl = document.getElementById('app-config');
        var fromCfg = appCfgEl && appCfgEl.getAttribute('data-logout-url');
        if (fromCfg) {
            return fromCfg;
        }
        var path = window.location.pathname || '';
        var m = path.match(/^\/([a-z0-9_-]+)(?:\/|$)/i);
        var reserved = /^(control-panel|rateb-erp|pages|api|public|designed|js|css|assets|tap-payments|home|profile|admin|login|logout)$/i;
        if (m && m[1] && !reserved.test(m[1])) {
            var slug = m[1].toLowerCase();
            var isControl = (appCfgEl && appCfgEl.getAttribute('data-control-pro-bridge') === '1')
                || (window.location.search || '').indexOf('control=1') !== -1;
            return window.location.origin + '/' + encodeURIComponent(slug) + '/logout'
                + (isControl ? '?control=1' : '');
        }
        return window.ratebPageUrl('logout.php');
    };

    /** Build extensionless /pages/ URL (matches PHP pageUrl()). */
    window.ratebPageUrl = function(page, extraQuery) {
        var p = String(page || '').replace(/^\/+/, '').replace(/\.php$/i, '');
        var base = String((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '').replace(/\/+$/, '');
        var url = (base || '') + '/pages/' + p;
        if (extraQuery) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + String(extraQuery).replace(/^\?/, '');
        }
        return url;
    };

    /** Strip .php from user-facing page links (not /api/, assets, or Designed). */
    window.ratebCleanPageHref = function(href) {
        if (!href || typeof href !== 'string') return href;
        if (href.indexOf('/api/') !== -1 || href.indexOf('/Designed/') !== -1) return href;
        if (/\/pages\/[^?#]+\.php/i.test(href) || /\/control-panel\/pages\/[^?#]+\.php/i.test(href)) {
            return href.replace(/\.php(?=[?#]|$)/i, '');
        }
        if (/\/pages\/home\.php/i.test(href)) {
            return href.replace(/\/pages\/home\.php/i, '/home');
        }
        return href;
    };

    function ratebApplyCleanPageLinks(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('a[href*=".php"]').forEach(function(a) {
            var h = a.getAttribute('href');
            if (!h) return;
            var cleaned = window.ratebCleanPageHref(h);
            if (cleaned !== h) a.setAttribute('href', cleaned);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { ratebApplyCleanPageLinks(document); });
    } else {
        ratebApplyCleanPageLinks(document);
    }

    if (typeof MutationObserver !== 'undefined') {
        var cleanLinkObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('a[href*=".php"]')) {
                        var h = node.getAttribute('href');
                        if (h) node.setAttribute('href', window.ratebCleanPageHref(h));
                    }
                    ratebApplyCleanPageLinks(node);
                });
            });
        });
        cleanLinkObserver.observe(document.documentElement, { childList: true, subtree: true });
    }

    // If user landed on a legacy .php page URL, normalize the address bar after redirect rules.
    (function() {
        var path = window.location.pathname || '';
        if (/\/pages\/home\.php$/i.test(path)) {
            var next = path.replace(/\/pages\/home\.php$/i, '/home') + (window.location.search || '') + (window.location.hash || '');
            try { window.history.replaceState(null, '', next); } catch (e) {}
            return;
        }
        if ((/\/pages\/[^/]+\.php$/i.test(path) || /\/pages\/[^/]+\/[^/]+\.php$/i.test(path)) && path.indexOf('/api/') === -1) {
            var cleanPath = path.replace(/\.php$/i, '');
            var bar = cleanPath + (window.location.search || '') + (window.location.hash || '');
            try { window.history.replaceState(null, '', bar); } catch (e) {}
        }
    })();
    
    // Global 401 handler: redirect to logout when session expires (applies to all program pages)
    (function() {
        var redirectInProgress = false;

        function redirectToLogout() {
            if (redirectInProgress) return;
            var isLoginPage = window.location.pathname.indexOf('login') !== -1;
            var isLogoutPage = window.location.pathname.indexOf('logout') !== -1;
            var isInIframe = (typeof window !== 'undefined' && window.self !== window.top);
            if (isLoginPage || isLogoutPage || isInIframe) return;
            redirectInProgress = true;
            var base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '';
            var sep = (base && base.slice(-1) !== '/') ? '/' : '';
            var appCfgEl = document.getElementById('app-config');
            var isControlProBridge = appCfgEl && appCfgEl.getAttribute('data-control-pro-bridge') === '1';
            var isControl = window.location.pathname.indexOf('/control/') !== -1 || (window.location.search || '').indexOf('control=1') !== -1 ||
                (appCfgEl && appCfgEl.getAttribute('data-control') === '1') || isControlProBridge;
            window.location.href = window.ratebLogoutUrl();
        }

        function isOurApiUrl(urlStr) {
            return typeof urlStr === 'string' && urlStr.indexOf('/api/') !== -1;
        }

        function shouldRedirectOn401(urlStr) {
            if (!isOurApiUrl(urlStr)) return false;
            // On control panel pages: do NOT auto-redirect on 401 - session/auth flow differs, avoids false logouts
            var path = (window.location.pathname || '');
            var qs = (window.location.search || '');
            var appCfgEl401 = document.getElementById('app-config');
            var isControlProBridge401 = appCfgEl401 && appCfgEl401.getAttribute('data-control-pro-bridge') === '1';
            var isControlPage = path.indexOf('/control/') !== -1 || qs.indexOf('control=1') !== -1 || isControlProBridge401;
            if (isControlPage) return false;
            // Only auth/session endpoints should trigger forced logout.
            // Many feature APIs can legitimately return 401/403 without meaning the whole session is expired.
            var auth401Endpoints = [
                '/api/get-current-user-permissions.php',
                '/api/settings/get_permissions_groups.php'
            ];
            return auth401Endpoints.some(function(ep) { return urlStr.indexOf(ep) !== -1; });
        }

        // Intercept fetch (used by accounting, workers, etc.)
        // On 401: redirect and return never-resolving promise to prevent caller from logging errors
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
            // Backward compatibility for stale bundles that call ?typ=all by mistake.
            // Normalize to ?type=all before request leaves the browser.
            if (urlStr && /(?:\?|&)typ=all(?:&|$)/.test(urlStr)) {
                var fixed = urlStr.replace(/([?&])typ=all([&]?)/g, function(_, sep, tail) {
                    return sep + 'type=all' + (tail || '');
                });
                if (typeof url === 'string') {
                    url = fixed;
                } else if (url && typeof Request !== 'undefined' && url instanceof Request) {
                    url = new Request(fixed, url);
                } else {
                    urlStr = fixed;
                }
                urlStr = fixed;
            }
            if (urlStr && (urlStr.indexOf('/public/workflows/worker-onboarding') !== -1 || urlStr.indexOf('global_ai_workflow') !== -1)) {
                var cfg = document.getElementById('app-config');
                var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
                if (!apiBase) {
                    var origin = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
                    apiBase = origin ? origin + '/api' : '/api';
                }
                var runUrl = apiBase.replace(/\/$/, '') + '/workers/global-ai-run.php';
                if (typeof url === 'string') {
                    url = runUrl;
                } else if (url && typeof Request !== 'undefined' && url instanceof Request) {
                    url = new Request(runUrl, url);
                }
                urlStr = runUrl;
            }
            return originalFetch.call(this, url, options).then(function(response) {
                if (response.status === 401 && shouldRedirectOn401(urlStr)) {
                    redirectToLogout();
                    return new Promise(function() {}); // Never resolves - prevents caller error handling
                }
                return response;
            });
        };

        // Intercept jQuery.ajax (used by contact, communications, notifications, etc.)
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ajaxComplete(function(event, xhr, settings) {
                if (xhr && xhr.status === 401 && shouldRedirectOn401(settings.url || '')) {
                    redirectToLogout();
                }
            });
        }
    })();
    
    // Load notification badge
    function loadHeaderNotificationBadge() {
        if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
            // jQuery not loaded yet, try again later
            setTimeout(loadHeaderNotificationBadge, 100);
            return;
        }
        // Skip on control panel pages / control sessions / control-pro-bridge - notifications API returns 403 for control sessions.
        var appConfigEl = document.getElementById('app-config');
        var isControlSession = appConfigEl && (appConfigEl.getAttribute('data-control') === '1' || appConfigEl.getAttribute('data-control-pro-bridge') === '1');
        var isControlPage = (window.location.pathname || '').indexOf('/control/') !== -1 || (window.location.search || '').indexOf('control=1') !== -1;
        if (isControlSession || isControlPage || !jQuery('#headerNotificationBadge').length) {
            return;
        }
        // Skip if the user does not have notification-view permission to avoid 403 console errors.
        if (window.UserPermissions) {
            if (window.UserPermissions.loaded) {
                if (!window.UserPermissions.has('view_notifications')) {
                    return;
                }
            } else if (window.UserPermissions.load) {
                // Wait for permissions and recheck; otherwise the badge request will retry on next page.
                window.UserPermissions.load().then(function() {
                    if (window.UserPermissions.has('view_notifications')) {
                        loadHeaderNotificationBadge();
                    }
                }).catch(function() {});
                return;
            }
        }
        // Use APP_CONFIG.apiBase if available, otherwise construct from baseUrl
        const apiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) 
            ? window.APP_CONFIG.apiBase 
            : ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) ? window.APP_CONFIG.baseUrl + '/api' : '/api');
        
        jQuery.ajax({
            url: apiBase + '/notifications/notifications-api.php?action=get_notifications&status=pending',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    const badge = jQuery('#headerNotificationBadge');
                    if (badge.length) {
                        badge.text(response.data.length);
                        badge.show();
                    }
                }
            },
            error: function() {
                // Silently fail for badge loading
            }
        });
    }
    
    // Handle deferred CSS loading (replaces inline onload handlers)
    function handleDeferredCSS() {
        // Find all stylesheet links with media="print" that need to be activated
        const deferredLinks = document.querySelectorAll('link[rel="stylesheet"][media="print"][data-defer-css]');
        deferredLinks.forEach(function(link) {
            // Set up onload handler via JavaScript instead of inline
            if (link.addEventListener) {
                link.addEventListener('load', function() {
                    this.media = 'all';
                });
            } else if (link.attachEvent) {
                link.attachEvent('onload', function() {
                    this.media = 'all';
                });
            }
            
            // Fallback: check if stylesheet is already loaded
            if (link.sheet || link.styleSheet) {
                link.media = 'all';
            } else {
                // Polling fallback for browsers that don't fire onload reliably
                var checkInterval = setInterval(function() {
                    if (link.sheet || link.styleSheet) {
                        link.media = 'all';
                        clearInterval(checkInterval);
                    }
                }, 50);
                
                // Stop polling after 5 seconds
                setTimeout(function() {
                    clearInterval(checkInterval);
                    // Force activate after timeout as fallback
                    link.media = 'all';
                }, 5000);
            }
        });
    }
    
    // Initialize deferred CSS handling immediately (runs before DOMContentLoaded)
    handleDeferredCSS();
    
    // Also handle after DOM is ready as fallback
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            handleDeferredCSS();
            setTimeout(loadHeaderNotificationBadge, 100);
        });
    } else {
        handleDeferredCSS();
        setTimeout(loadHeaderNotificationBadge, 100);
    }
})();

/**
 * Script loader with CDN fallback
 * Handles script loading with automatic fallback to alternative CDN
 */
(function() {
    'use strict';
    
    // Function to load script with fallback
    window.loadScriptWithFallback = function(src, fallbackSrc, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.addEventListener('error', function() {
            this.src = fallbackSrc;
            if (callback) callback();
        });
        script.addEventListener('load', function() {
            if (callback) callback();
        });
        document.head.appendChild(script);
    };
    
    // Auto-setup scripts with fallback (for flatpickr and others)
    function setupScriptFallbacks() {
        const scriptsWithFallback = document.querySelectorAll('script[data-fallback]');
        scriptsWithFallback.forEach(function(script) {
            const fallback = script.getAttribute('data-fallback');
            if (fallback && !script.dataset.fallbackSetup) {
                script.dataset.fallbackSetup = 'true';
                script.addEventListener('error', function() {
                    this.src = fallback;
                });
            }
        });
    }
    
    // Setup fallbacks immediately if DOM is ready, otherwise wait
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupScriptFallbacks);
    } else {
        setupScriptFallbacks();
    }
    
    // Also setup fallbacks for dynamically added scripts
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.tagName === 'SCRIPT' && node.hasAttribute('data-fallback')) {
                        const fallback = node.getAttribute('data-fallback');
                        if (fallback) {
                            node.addEventListener('error', function() {
                                this.src = fallback;
                            });
                        }
                    }
                });
            });
        });
        observer.observe(document.head, { childList: true, subtree: true });
    }
})();
