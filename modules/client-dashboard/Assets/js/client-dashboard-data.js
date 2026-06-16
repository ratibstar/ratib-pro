window.RATEBClientDashboardData = window.RATEBClientDashboardData || {};

(function (api) {
    /**
     * @param {string} url
     * @param {{ method?: string, headers?: HeadersInit, body?: string }} opts
     * @returns {Promise<any>}
     */
    api.fetchJson = async function fetchJson(url, opts) {
        var res = await fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        }, opts || {}));
        var ct = res.headers.get('content-type') || '';
        var text = await res.text();
        if (!ct.includes('application/json')) {
            throw new Error('non_json_response');
        }
        try {
            var data = JSON.parse(text);
            if (!res.ok) {
                var err = new Error('request_failed_' + String(res.status));
                err.details = data;
                throw err;
            }
            return data;
        } catch (err) {
            if (err.details) throw err;
            throw new Error('bad_json_body');
        }
    };

    /**
     * Merge remote widgets with deterministic placeholder shape.
     * @param {Record<string, any>} remote
     * @param {Record<string, any>} base
     * @returns {Record<string, any>}
     */
    api.mergeWidgets = function mergeWidgets(remote, base) {
        var out = Object.assign({}, base || {}, remote || {});
        ['recent_orders', 'security_alerts', 'domain_expiry_alerts'].forEach(function (k) {
            if (!(out[k] instanceof Array)) {
                out[k] = Array.isArray(base[k]) ? base[k] : [];
            }
        });
        if (!out.billing_summary || typeof out.billing_summary !== 'object') {
            out.billing_summary =
                base && base.billing_summary ? base.billing_summary : {};
        }
        return out;
    };
})(window.RATEBClientDashboardData);
