/**
 * EN: Implements frontend interaction behavior in `js/utils/cache-clear.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/cache-clear.js`.
 */
// Cache clearing utility
(function() {
    // Clear all caches and force reload
    if ('caches' in window) {
        caches.keys().then(function(names) {
            for (let name of names) {
                caches.delete(name);
            }
        });
    }

    // Force reload if this is a cached version
    if (performance.navigation.type === 2) {
        window.location.reload(true);
    }
})();
