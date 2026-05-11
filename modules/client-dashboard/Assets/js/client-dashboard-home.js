(function () {
    function statusClass(st) {
        var s = String(st || '').toLowerCase();
        var map = {
            pending: 'ratib-status--pending',
            processing: 'ratib-status--processing',
            active: 'ratib-status--active',
            suspended: 'ratib-status--suspended',
            failed: 'ratib-status--failed',
            cancelled: 'ratib-status--cancelled',
            canceled: 'ratib-status--cancelled',
        };
        return map[s] || 'ratib-status--neutral';
    }

    function el(id) {
        return document.getElementById(id);
    }

    function fillEl(nodeId, text) {
        var n = el(nodeId);
        if (n) {
            n.textContent = text;
        }
    }

    function encodeHtml(txt) {
        return String(txt)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function renderOrdersRewritten(rows) {
        var ul = el('rcp-recent-orders');
        if (!ul) {
            return;
        }

        ul.innerHTML = '';

        var list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            var empty = document.createElement('div');
            empty.className = 'ratib-cp-empty';
            empty.textContent = 'No recent orders.';
            ul.appendChild(empty);
            return;
        }

        list.forEach(function (r) {
            var holder = document.createElement('article');
            holder.className =
                'd-flex flex-wrap gap-3 align-items-center justify-content-between py-3';
            holder.style.borderBottom =
                '1px solid rgba(255,255,255,0.08)';

            var left = document.createElement('div');
            left.innerHTML =
                '<strong class="d-block">' +
                encodeHtml(String(r.product || '')) +
                '</strong><span class="rcp-muted-span">' +
                encodeHtml(String(r.id || '') + ' · ' + String(r.created_at || '')) +
                ' · <span>' +
                encodeHtml(String(r.payment_status || '')) +
                '</span></span>';

            var right = document.createElement('span');
            right.className =
                'ratib-status ' + statusClass(String(r.status));

            right.textContent = String(r.status || '');

            holder.appendChild(left);
            holder.appendChild(right);
            ul.appendChild(holder);
        });
    }

    function renderFeeds(activity, securityAlerts) {
        var actEl = el('rcp-activity-feed');
        if (actEl) {
            actEl.innerHTML = '';

            var slice = Array.isArray(activity) ? activity : [];
            slice.forEach(function (a) {
                var li = document.createElement('li');

                var t = encodeHtml(String(a.title || ''));

                var tm = encodeHtml(String(a.at || '').slice(0, 19));

                li.innerHTML =
                    '<span aria-hidden="true">⚡ </span>' +
                    '<span><strong>' +
                    t +
                    '</strong><br><small class="rcp-muted-span">' +
                    tm +
                    '</small></span>';
                actEl.appendChild(li);
            });
            if (!slice.length) {
                var fallbackLi = document.createElement('li');
                fallbackLi.textContent = 'Connecting activity feeds…';
                actEl.appendChild(fallbackLi);
            }
        }

        var secEl = el('rcp-security-mini');
        if (!secEl) {
            return;
        }
        secEl.innerHTML = '';
        var alerts = Array.isArray(securityAlerts)
            ? securityAlerts
            : [];
        alerts.forEach(function (x) {
            var d = document.createElement('div');
            var msg =
                typeof x === 'string'
                    ? x
                    : String(x.title || x.message || '');
            d.textContent = msg;
            secEl.appendChild(d);
        });
        if (!alerts.length) {
            secEl.innerHTML =
                '<span class="rcp-muted-span">No live alerts detected.</span>';
        }
    }

    function stripSkeleton() {
        document.querySelectorAll('.ratib-cp-skeleton').forEach(function (n) {
            n.classList.add('d-none');
        });
        fillEl('rcp-loading-state', '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var cfg = document.getElementById('rcp-home-config');
        if (!cfg) {
            return;
        }

        var apiBaseEl = document.getElementById('app-config');
        var apiBase =
            (apiBaseEl && apiBaseEl.getAttribute('data-api-base')) || '';

        if (typeof RatibClientActions.configure === 'function') {
            RatibClientActions.configure(apiBase);
        }

        if (typeof RatibClientActions.installDefaultQuickActions === 'function') {
            RatibClientActions.installDefaultQuickActions('#rcp-quick-actions');
        }

        RatibClientDashboardData.fetchJson(
            apiBase + '/client-dashboard/snapshot.php'
        )
            .then(function (data) {
                var w = (data && data.widgets) || {};
                apply(w);
                stripSkeleton();
            })
            .catch(function () {
                apply({});
                stripSkeleton();
            });

        function apply(widgets) {
            renderOrdersRewritten(widgets.recent_orders || []);

            var inv =
                widgets.billing_summary &&
                widgets.billing_summary.invoice_count;

            fillEl(
                'rcp-inv-count',
                inv != null ? String(inv) : '—'
            );

            var subLbl =
                widgets.subscription_health &&
                String(widgets.subscription_health.label || '');

            fillEl('rcp-sub-health', subLbl ? subLbl : 'No subscription data');

            var infraLbl =
                (widgets.infra_status &&
                    widgets.infra_status.control_plane) ||
                'Operational snapshot';

            fillEl('rcp-infra', String(infraLbl));

            renderFeeds(widgets.activity_feed, widgets.security_alerts);
        }
    });

})();
