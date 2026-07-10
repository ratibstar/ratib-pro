/**
 * RATEB Offline — Event bus (Phase 2A).
 */
(function (root) {
    'use strict';

    var handlers = {};

    root.RatebOfflineEvents = {
        on: function (event, fn) {
            if (!handlers[event]) {
                handlers[event] = [];
            }
            handlers[event].push(fn);
            return function () {
                handlers[event] = (handlers[event] || []).filter(function (x) { return x !== fn; });
            };
        },
        emit: function (event, detail) {
            (handlers[event] || []).forEach(function (fn) {
                try { fn(detail); } catch (e) { /* ignore */ }
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
