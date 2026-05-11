(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = document.getElementById('app-config');
        var apiBase =
            (cfg && cfg.getAttribute('data-api-base')) ||
            '';

        var url =
            apiBase.replace(/\/+$/, '') +
            '/infrastructure-marketplace/dashboard.php';

        var status = document.getElementById('rcp-infra-json');
        var q = document.getElementById('rcp-infra-queue');
        var p = document.getElementById('rcp-infra-providers');
        var d = document.getElementById('rcp-infra-diag');

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                return res.json();
            })
            .then(function (payload) {
                if (status) status.textContent = 'Live probe OK';

                var queue = payload && payload.queue;
                if (q && queue) {
                    q.textContent =
                        'Driver: ' +
                        String(queue.driver || '—') +
                        ' · depth ' +
                        String(queue.depth != null ? queue.depth : '—');
                }

                var prov = payload && payload.providers;
                if (p && prov) {
                    p.textContent = String(
                        prov.status || 'unknown'
                    );
                }

                var diag = payload && payload.diagnostics;
                if (d && diag) {
                    d.textContent = String(
                        diag.status || JSON.stringify(diag).slice(0, 120)
                    );
                }
            })
            .catch(function () {
                if (status) {
                    status.textContent =
                        'Probe unavailable — control plane may restrict this context.';
                }
            });
    });
})();
