/* Rateb Enterprise Offline SW source stub — production SWs live at:
 *   public/pos-sw.js (authoritative coexist)
 *   public/rateb-offline-sw.js (ERP-only fallback)
 * Background Sync hooks are implemented in those public files.
 */
'use strict';

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

/* Passthrough — do not register caching here (avoids competing with pos-sw.js). */
self.addEventListener('fetch', function () { /* passthrough */ });
