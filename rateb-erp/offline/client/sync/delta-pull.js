/**
 * RATEB Offline — Delta pull (Phase 13.1).
 * Supports client cursor, branch_id, and optional device_id for master-data gates.
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
            if (options.device_id) {
                params.push('device_id=' + encodeURIComponent(String(options.device_id)));
            }
            if (params.length) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
            }
            var headers = { Accept: 'application/json' };
            if (options.device_id) {
                headers['X-Rateb-Device-Id'] = String(options.device_id);
            }
            return fetch(url, {
                credentials: 'same-origin',
                headers: headers
            }).then(function (res) {
                return res.json();
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
