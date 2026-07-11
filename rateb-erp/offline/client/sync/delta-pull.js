/**
 * RATEB Offline — Delta pull (Phase 3).
 * Inventory catalog delta is live when Tier-1 flag is on; other entities remain stub-friendly.
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
            var params = [];
            if (options.cursor) {
                params.push('cursor=' + encodeURIComponent(options.cursor));
            }
            if (options.branch_id) {
                params.push('branch_id=' + encodeURIComponent(String(options.branch_id)));
            }
            if (params.length) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
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
