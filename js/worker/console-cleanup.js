/**
 * EN: Implements frontend interaction behavior in `js/worker/console-cleanup.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/console-cleanup.js`.
 */
/**
 * Console Cleanup Script
 * Suppresses console warnings/errors in production mode
 * Set window.DEBUG_MODE = true to enable all console output
 */

(function() {
    'use strict';
    
    // Default DEBUG_MODE to false for production (clean console)
    if (window.DEBUG_MODE === undefined) {
        window.DEBUG_MODE = false;
    }
    
    // Only suppress console if DEBUG_MODE is explicitly false
    if (window.DEBUG_MODE === false) {
        // Store original console methods
        const originalConsole = {
            log: console.log,
            warn: console.warn,
            error: console.error,
            info: console.info,
            debug: console.debug
        };
        
        // Override console methods to be no-ops in production
        console.log = function() {};
        console.warn = function() {};
        console.error = function() {};
        console.info = function() {};
        console.debug = function() {};
        
        // Restore console for critical errors only
        window.addEventListener('error', function(e) {
            originalConsole.error('Critical Error:', e.message, e.filename, e.lineno);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            originalConsole.error('Unhandled Promise Rejection:', e.reason);
        });
    }
    
    // Suppress common browser warnings that don't affect functionality
    const suppressWarnings = [
        'favicon',
        'Mixed Content',
        'deprecated',
        'non-passive',
        'third-party',
        'cookie',
        'SameSite',
        'DevTools',
        'extension',
        'source map',
        'Failed to load resource',
        'net::ERR_',
        'CORS',
        'Cross-Origin'
    ];
    
    // Override console.warn to filter out common warnings
    const originalWarn = console.warn;
    console.warn = function(...args) {
        if (window.DEBUG_MODE === false) {
            const message = args.join(' ').toLowerCase();
            const shouldSuppress = suppressWarnings.some(warning => message.includes(warning.toLowerCase()));
            if (shouldSuppress) {
                return; // Suppress this warning
            }
        }
        originalWarn.apply(console, args);
    };
    
    // Also suppress console.error for non-critical errors
    const originalError = console.error;
    console.error = function(...args) {
        if (window.DEBUG_MODE === false) {
            const message = args.join(' ').toLowerCase();
            const shouldSuppress = suppressWarnings.some(warning => message.includes(warning.toLowerCase()));
            if (shouldSuppress) {
                return; // Suppress this error
            }
        }
        originalError.apply(console, args);
    };
})();
