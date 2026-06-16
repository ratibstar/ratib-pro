window.RATEBClientActions = window.RATEBClientActions || {};

(function (svc) {
    var ACTIONS_URL = '';

    svc.configure = function (apiBaseUrl) {
        ACTIONS_URL =
            apiBaseUrl.replace(/\/+$/, '') + '/client-dashboard/actions.php';
    };

    /**
     * @param {string} action
     * @param {{ targetId?: string }} ctx
     * @returns {Promise<any>}
     */
    svc.dispatch = async function dispatch(action, ctx) {
        if (!ACTIONS_URL) {
            var cfg = document.getElementById('app-config');
            var b = cfg && cfg.getAttribute('data-api-base');
            if (b) {
                svc.configure(b);
            }
        }
        if (!ACTIONS_URL) {
            return { ok: false, message: 'no_api_base_configured' };
        }

        try {
            return await RATEBClientDashboardData.fetchJson(ACTIONS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    target_id: (ctx && ctx.targetId) || '',
                }),
            });
        } catch (e) {
            console.warn('RATEBClientActions', e); // eslint-disable-line no-console
            return {
                ok: false,
                message: 'offline_or_unauthorized_stub',
            };
        }
    };

    svc.renew = function (id) {
        return svc.dispatch('renew', { targetId: id });
    };
    svc.suspend = function (id) {
        return svc.dispatch('suspend', { targetId: id });
    };
    svc.restart = function (id) {
        return svc.dispatch('restart', { targetId: id });
    };
    svc.cancel = function (id) {
        return svc.dispatch('cancel', { targetId: id });
    };
    svc.upgrade = function (id) {
        return svc.dispatch('upgrade', { targetId: id });
    };
    svc.retryPayment = function (id) {
        return svc.dispatch('retry_payment', { targetId: id });
    };
    svc.openTicket = function () {
        return svc.dispatch('open_ticket', {});
    };

    svc.installDefaultQuickActions = function (rootSelector) {
        var root = document.querySelector(rootSelector || '');
        if (!root) return;

        root.addEventListener('click', function (e) {
            var el = e.target.closest('[data-rcp-action]');
            if (!el) return;

            var act = String(el.getAttribute('data-rcp-action') || '');
            var id = String(el.getAttribute('data-rcp-target-id') || '');

            if (act === 'open_ticket') {
                svc.openTicket().then(function () {
                    /* UX hook */
                });
            } else if (act === 'renew') {
                svc.renew(id);
            } else if (act === 'retry_payment') {
                svc.retryPayment(id);
            } else if (act === 'upgrade') {
                svc.upgrade(id);
            }
        });
    };
    (function autoConfigure() {
        var cfg = document.getElementById('app-config');
        var b = cfg && cfg.getAttribute('data-api-base');
        if (b) {
            svc.configure(b);
        }
    })();
})(window.RATEBClientActions);
