/* Rateb Enterprise Offline SW stub (Phase 2A) — additive; does not replace pos-sw.js */
'use strict';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

/* Phase 2A: no global caching — POS SW remains authoritative for POS shell. */
self.addEventListener('fetch', function () {
    /* passthrough */
});
