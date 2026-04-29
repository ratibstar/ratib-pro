(function () {
    var root = document.getElementById('tracking-health-page');
    if (!root) return;
    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase) return;

    var statsWrap = document.getElementById('trackingHealthStats');
    var tableBody = document.querySelector('#trackingHealthTable tbody');
    var flashEl = document.getElementById('trackingHealthFlash');
    var refreshBtn = document.getElementById('trackingHealthRefreshBtn');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function flash(msg, ok) {
        if (!flashEl) return;
        flashEl.textContent = msg;
        flashEl.className = 'alert mt-2 ' + (ok ? 'alert-success' : 'alert-danger');
        flashEl.classList.remove('d-none');
        setTimeout(function () { flashEl.classList.add('d-none'); }, 4500);
    }

    function statCard(label, value, tone) {
        return '<div class="tracking-health-card tone-' + tone + '">' +
            '<div class="label">' + esc(label) + '</div>' +
            '<div class="value">' + esc(value) + '</div>' +
        '</div>';
    }

    function renderStats(s) {
        if (!statsWrap) return;
        statsWrap.innerHTML =
            statCard('Sessions total', s.sessions_total || 0, 'base') +
            statCard('Sessions active', s.sessions_active || 0, 'ok') +
            statCard('Sessions inactive', s.sessions_inactive || 0, 'warn') +
            statCard('Sessions lost', s.sessions_lost || 0, 'danger') +
            statCard('Devices total', s.devices_total || 0, 'base') +
            statCard('Devices active', s.devices_active || 0, 'ok') +
            statCard('Devices seen (24h)', s.devices_seen_24h || 0, 'ok') +
            statCard('Locations (24h)', s.locations_24h || 0, 'base') +
            statCard('SOS (24h)', s.sos_24h || 0, 'danger') +
            statCard('Anomalies (24h)', s.anomalies_24h || 0, 'warn');
    }

    function renderRows(rows) {
        if (!tableBody) return;
        tableBody.innerHTML = (rows || []).map(function (r) {
            var workerLabel = r.worker_name || r.formatted_id || ('#' + r.worker_id);
            var status = String(r.status || '').toLowerCase();
            var badgeClass = status === 'active' ? 'bg-success' : (status === 'inactive' ? 'bg-warning text-dark' : 'bg-danger');
            return '<tr>' +
                '<td>' + esc(workerLabel) + '</td>' +
                '<td>' + esc(r.worker_identity || '') + '</td>' +
                '<td>' + esc(r.device_id || '') + '</td>' +
                '<td>' + esc(r.tenant_id || '') + '</td>' +
                '<td>' + esc(r.agency_name || r.agency_id || '') + '</td>' +
                '<td>' + esc(r.last_seen || '') + '</td>' +
                '<td><span class="badge ' + badgeClass + '">' + esc(status || '-') + '</span></td>' +
            '</tr>';
        }).join('') || '<tr><td colspan="7" class="text-muted">No active sessions found</td></tr>';
    }

    function loadHealth() {
        if (refreshBtn) refreshBtn.disabled = true;
        fetch(apiBase + '/worker-tracking.php?action=health', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data) {
                    throw new Error(res.message || 'Load failed');
                }
                renderStats(res.data.summary || {});
                renderRows(res.data.latest || []);
            })
            .catch(function (e) {
                flash(e.message || 'Tracking health load failed', false);
            })
            .finally(function () {
                if (refreshBtn) refreshBtn.disabled = false;
            });
    }

    if (refreshBtn) refreshBtn.addEventListener('click', loadHealth);
    loadHealth();
    setInterval(loadHealth, 15000);
})();
