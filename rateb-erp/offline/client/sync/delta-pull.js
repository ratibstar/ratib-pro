/**
 * RATEB Offline — Delta pull stub (Phase 2A).
 * Inventory/HR/ERP delta sync is intentionally not implemented yet.
 */
(function (root) {
    'use strict';

    root.RatebOfflineDeltaPull = {
        pull: function (entity, options) {
            options = options || {};
            var base = options.apiBase || '';
            if (!base || !entity) {
                return Promise.resolve({ entity: entity || '', items: [], cursor: null, stub: true });
            }
            var url = String(base).replace(/\/$/, '') + '/delta/' + encodeURIComponent(entity);
            if (options.cursor) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'cursor=' + encodeURIComponent(options.cursor);
            }
            return fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (res) {
                return res.json();
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
