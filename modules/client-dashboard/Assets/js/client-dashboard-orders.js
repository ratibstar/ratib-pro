(function () {
    function statusClass(st) {
        var s = String(st || '').toLowerCase();
        var map = {
            pending: 'rateb-status--pending',
            processing: 'rateb-status--processing',
            active: 'rateb-status--active',
            suspended: 'rateb-status--suspended',
            failed: 'rateb-status--failed',
            paid: 'rateb-status--active',
            unpaid: 'rateb-status--pending',
            cancelled: 'rateb-status--cancelled',
            canceled: 'rateb-status--cancelled',
        };
        return map[s] || 'rateb-status--neutral';
    }

    function encodeHtml(txt) {
        return String(txt)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function qs(id) {
        return document.getElementById(id);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rcp-orders-page');
        if (!root) return;

        var apiBaseEl = document.getElementById('app-config');
        var apiBase =
            (apiBaseEl && apiBaseEl.getAttribute('data-api-base')) || '';

        if (typeof RATEBClientActions.configure === 'function') {
            RATEBClientActions.configure(apiBase);
        }

        var tbody = qs('rcp-orders-tbody');

        function state() {
            return {
                q: (qs('rcp-filter-q').value || '').trim(),
                status: qs('rcp-filter-status').value,
                payment: qs('rcp-filter-pay').value,
                page: parseInt(root.getAttribute('data-page'), 10) || 1,
            };
        }

        function reload() {
            var s = state();
            root.setAttribute('data-loading', '1');
            tbody.innerHTML =
                '<tr><td colspan="8"><div class="rateb-cp-skeleton">&nbsp;</div></td></tr>';

            var qstr = new URLSearchParams();
            qstr.set('q', s.q);
            qstr.set('status', s.status);
            qstr.set('payment_status', s.payment);
            qstr.set('page', String(s.page));
            qstr.set('per_page', '8');

            RATEBClientDashboardData.fetchJson(
                apiBase + '/client-dashboard/orders.php?' + qstr.toString()
            )
                .then(function (payload) {
                    render(payload.rows || []);
                    pager(payload.pagination || {});
                    root.removeAttribute('data-loading');
                })
                .catch(function () {
                    tbody.innerHTML =
                        '<tr><td colspan="8" class="rateb-cp-empty">Temporary service issue — retry shortly.</td></tr>';
                    root.removeAttribute('data-loading');
                });
        }

        function render(rows) {
            tbody.innerHTML = '';
            if (!rows.length) {
                tbody.innerHTML =
                    '<tr><td colspan="8" class="rateb-cp-empty">No orders matched.</td></tr>';
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');

                var id = encodeHtml(String(r.id || ''));

                var product = encodeHtml(String(r.product || ''));

                var stRaw = String(r.status || '');

                var pstRaw = String(r.payment_status || '');

                var cAt = encodeHtml(String(r.created_at || ''));

                var rAt = encodeHtml(String(r.renewal_at || ''));

                var targetIdEsc = encodeHtml(String(r.id || ''));

                var cbTd = document.createElement('td');
                cbTd.innerHTML =
                    '<input type="checkbox" class="rcp-bulk" value="' +
                    id +
                    '" aria-label="Select order">';
                tr.appendChild(cbTd);

                var tdId = document.createElement('td');
                tdId.textContent = String(r.id || '');
                tr.appendChild(tdId);

                var tdPr = document.createElement('td');
                tdPr.textContent = String(r.product || '');
                tr.appendChild(tdPr);

                var tdSt = document.createElement('td');
                tdSt.innerHTML =
                    '<span class="rateb-status ' +
                    statusClass(stRaw) +
                    '">' +
                    encodeHtml(stRaw) +
                    '</span>';
                tr.appendChild(tdSt);

                var tdPay = document.createElement('td');
                tdPay.innerHTML =
                    '<span class="rateb-status ' +
                    statusClass(pstRaw) +
                    '">' +
                    encodeHtml(pstRaw) +
                    '</span>';
                tr.appendChild(tdPay);

                var tdC = document.createElement('td');
                tdC.textContent = String(r.created_at || '');
                tr.appendChild(tdC);

                var tdRen = document.createElement('td');
                tdRen.textContent = String(r.renewal_at || '');
                tr.appendChild(tdRen);

                var actionsTd = document.createElement('td');
                actionsTd.innerHTML =
                    '<details class="rateb-cp-actions">' +
                    '<summary>⋯</summary>' +
                    '<div class="rateb-cp-actions__menu" role="menu">' +
                    '<button type="button" data-rcp-act="renew" data-rcp-id="' +
                    targetIdEsc +
                    '">Renew</button>' +
                    '<button type="button" data-rcp-act="suspend" data-rcp-id="' +
                    targetIdEsc +
                    '">Suspend</button>' +
                    '<button type="button" data-rcp-act="restart" data-rcp-id="' +
                    targetIdEsc +
                    '">Restart</button>' +
                    '<button type="button" data-rcp-act="cancel" data-rcp-id="' +
                    targetIdEsc +
                    '">Cancel</button>' +
                    '<button type="button" data-rcp-act="upgrade" data-rcp-id="' +
                    targetIdEsc +
                    '">Upgrade</button>' +
                    '<button type="button" data-rcp-act="retry_payment" data-rcp-id="' +
                    targetIdEsc +
                    '">Retry payment</button>' +
                    '</div></details>';
                tr.appendChild(actionsTd);

                actionsTd.querySelectorAll('button[data-rcp-act]').forEach(
                    function (btn) {
                        btn.addEventListener('click', function () {
                            RATEBClientActions.dispatch(
                                String(btn.getAttribute('data-rcp-act') || ''),
                                {
                                    targetId:
                                        btn.getAttribute('data-rcp-id') ||
                                        '',
                                }
                            );
                        });
                    }
                );

                tbody.appendChild(tr);
            });
        }

        function pager(info) {
            var label = qs('rcp-pager-meta');
            if (label)
                label.textContent =
                    'Page ' +
                    String(info.page || 1) +
                    ' · ' +
                    String(info.total || 0);

            root.setAttribute('data-page', String(info.page || 1));
        }

        qs('rcp-run-filter').addEventListener('click', function () {
            root.setAttribute('data-page', '1');
            reload();
        });

        qs('rcp-reset-filter').addEventListener('click', function () {
            qs('rcp-filter-q').value = '';
            qs('rcp-filter-status').value = '';
            qs('rcp-filter-pay').value = '';
            root.setAttribute('data-page', '1');
            reload();
        });

        qs('rcp-prev').addEventListener('click', function () {
            var p = state().page;
            root.setAttribute('data-page', String(Math.max(1, p - 1)));
            reload();
        });

        qs('rcp-next').addEventListener('click', function () {
            var p = state().page;
            root.setAttribute('data-page', String(p + 1));
            reload();
        });

        qs('rcp-bulk-apply').addEventListener('click', function () {
            var chk = tbody.querySelectorAll(
                '.rcp-bulk:checked'
            ).length;

            RATEBClientActions.dispatch('suspend', {

                targetId: 'bulk:' + String(chk),
            });
        });

        reload();
    });
})();
