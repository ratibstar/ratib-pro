/**
 * Tracking map for control panel + government mode.
 */
(function () {
    var root = document.getElementById('tracking-map-page');
    if (!root) return;
    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase || !window.L) return;

    var map = L.map('trackingMapCanvas').setView([20.5937, 78.9629], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    var markers = {};
    var latestRows = [];
    var alertsEl = document.getElementById('trackingAlertsList');
    var tableBody = document.querySelector('#trackingLatestTable tbody');
    var flashEl = document.getElementById('trackingFlash');
    var playbackLayer = null;
    var playbackMarker = null;
    var latestReloadTimer = null;
    var geofenceLayers = [];
    var geofences = [];
    var threatBadgeEl = document.getElementById('trackingThreatBadge');
    var responseBadgeEl = document.getElementById('trackingResponseBadge');
    var threatState = { level: 'NORMAL', score: 0 };

    function flash(msg, ok) {
        if (!flashEl) return;
        flashEl.textContent = msg;
        flashEl.className = 'alert mt-2 ' + (ok ? 'alert-success' : 'alert-danger');
        flashEl.classList.remove('d-none');
        setTimeout(function () { flashEl.classList.add('d-none'); }, 5000);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function statusColor(status) {
        if (status === 'moving') return '#16a34a';
        if (status === 'idle') return '#eab308';
        if (status === 'offline' || status === 'lost') return '#64748b';
        if (status === 'alert') return '#ef4444';
        return '#64748b';
    }

    function normalizedStatus(row) {
        var ls = (row.location_status || '').toLowerCase();
        if (ls) return ls;
        var ss = (row.status || '').toLowerCase();
        if (ss === 'active') return 'moving';
        if (ss === 'inactive') return 'idle';
        if (ss === 'lost') return 'offline';
        return 'offline';
    }

    function queryParams() {
        return {
            tenant_id: (document.getElementById('trackingFilterTenant') || {}).value || '',
            agency_id: (document.getElementById('trackingFilterAgency') || {}).value || '',
            country: (document.getElementById('trackingFilterCountry') || {}).value || '',
            status: (document.getElementById('trackingFilterStatus') || {}).value || '',
            limit: 500
        };
    }

    function toQs(obj) {
        var p = new URLSearchParams();
        Object.keys(obj).forEach(function (k) {
            if (obj[k] != null && String(obj[k]) !== '') p.set(k, String(obj[k]));
        });
        return p.toString();
    }

    function loadLatest() {
        var url = apiBase + '/worker-tracking.php?action=latest&' + toQs(queryParams());
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Failed');
                latestRows = Array.isArray(res.data) ? res.data : [];
                renderTable();
                renderMap();
            })
            .catch(function (e) { flash(e.message || 'Latest load failed', false); });
    }

    function loadGeofences() {
        var url = apiBase + '/worker-tracking.php?action=geofences&' + toQs(queryParams());
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Failed geofences');
                geofences = Array.isArray(res.data) ? res.data : [];
                renderGeofences();
            })
            .catch(function (e) { flash(e.message || 'Geofence load failed', false); });
    }

    function renderTable() {
        if (!tableBody) return;
        tableBody.innerHTML = latestRows.map(function (row) {
            var s = normalizedStatus(row);
            return '<tr>' +
                '<td>#' + esc(row.worker_id) + '</td>' +
                '<td>' + esc(row.tenant_id) + '</td>' +
                '<td>' + esc(row.agency_name || row.agency_id || '') + '</td>' +
                '<td>' + esc(row.last_seen || '') + '</td>' +
                '<td>' + esc(row.battery == null ? '' : row.battery) + '</td>' +
                '<td><span class="badge" style="background:' + statusColor(s) + ';color:#fff;">' + esc(s) + '</span></td>' +
            '</tr>';
        }).join('') || '<tr><td colspan="6" class="text-muted">No workers found</td></tr>';
    }

    function threatColor(level) {
        if (level === 'CRITICAL') return '#ef4444';
        if (level === 'HIGH') return '#f97316';
        if (level === 'ELEVATED') return '#eab308';
        return '#16a34a';
    }

    function setThreatBadge(level, score) {
        level = (level || 'NORMAL').toUpperCase();
        score = parseInt(score || '0', 10);
        if (!threatBadgeEl) return;
        if (!isFinite(score)) score = 0;
        threatState.level = level;
        threatState.score = score;
        threatBadgeEl.style.background = threatColor(level);
        threatBadgeEl.textContent = '🔥 Threat Level: ' + level + (score > 0 ? (' (' + score + ')') : '');
    }

    function responseColor(actionType) {
        if (actionType === 'EMERGENCY') return '#ef4444';
        if (actionType === 'ALERT_CONTROL' || actionType === 'ALERT') return '#f97316';
        if (actionType === 'MONITOR') return '#2563eb';
        return '#6b7280';
    }

    function setResponseBadge(actionType) {
        actionType = (actionType || 'NONE').toUpperCase();
        if (!responseBadgeEl) return;
        responseBadgeEl.style.background = responseColor(actionType);
        responseBadgeEl.textContent = '⚡ Response State: ' + (actionType === 'ALERT_CONTROL' ? 'ALERT' : actionType);
    }

    function renderMap() {
        var bounds = [];
        Object.keys(markers).forEach(function (k) {
            map.removeLayer(markers[k]);
            delete markers[k];
        });
        latestRows.forEach(function (row) {
            if (row.lat == null || row.lng == null) return;
            var lat = parseFloat(row.lat);
            var lng = parseFloat(row.lng);
            if (!isFinite(lat) || !isFinite(lng)) return;
            var s = normalizedStatus(row);
            var marker = L.circleMarker([lat, lng], {
                radius: 8,
                color: statusColor(s),
                fillColor: statusColor(s),
                fillOpacity: 0.85,
                weight: 2
            }).addTo(map);
            marker.bindPopup(
                '<strong>Worker #' + esc(row.worker_id) + '</strong><br>' +
                'Tenant: ' + esc(row.tenant_id) + '<br>' +
                'Agency: ' + esc(row.agency_name || row.agency_id || '') + '<br>' +
                'Last seen: ' + esc(row.last_seen || '') + '<br>' +
                'Battery: ' + esc(row.battery == null ? 'N/A' : row.battery) + '<br>' +
                'Status: ' + esc(s)
            );
            markers[row.worker_id + ':' + row.tenant_id] = marker;
            bounds.push([lat, lng]);
        });
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }

    function renderGeofences() {
        geofenceLayers.forEach(function (layer) { map.removeLayer(layer); });
        geofenceLayers = [];
        var show = !!(document.getElementById('trackingShowGeofences') && document.getElementById('trackingShowGeofences').checked);
        if (!show) return;
        geofences.forEach(function (g) {
            var lat = parseFloat(g.center_lat);
            var lng = parseFloat(g.center_lng);
            var radius = parseInt(g.radius_m, 10);
            if (!isFinite(lat) || !isFinite(lng) || !isFinite(radius) || radius <= 0) return;
            var outside = parseInt(g.outside_count || '0', 10) > 0;
            var color = outside ? '#ef4444' : '#22c55e';
            var circle = L.circle([lat, lng], {
                radius: radius,
                color: color,
                fillColor: color,
                fillOpacity: 0.12,
                weight: 2
            }).addTo(map);
            circle.bindPopup(
                '<strong>' + esc(g.name || ('Geofence #' + g.id)) + '</strong><br>' +
                'Radius: ' + esc(radius) + 'm<br>' +
                'Outside workers: ' + esc(g.outside_count || 0)
            );
            geofenceLayers.push(circle);
        });
    }

    function prependAlert(ev) {
        if (!alertsEl) return;
        var li = document.createElement('li');
        li.innerHTML = '<strong>' + esc(ev.event_type || 'WORKER_EVENT') + '</strong> — ' + esc(ev.message || '') +
            '<br><small>' + esc(ev.created_at || '') + '</small>';
        alertsEl.prepend(li);
        while (alertsEl.children.length > 80) {
            alertsEl.removeChild(alertsEl.lastChild);
        }
    }

    function loadAlerts() {
        fetch(apiBase + '/worker-tracking.php?action=alerts&limit=40', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !alertsEl) return;
                alertsEl.innerHTML = '';
                var criticalOnly = !!(document.getElementById('trackingCriticalOnly') && document.getElementById('trackingCriticalOnly').checked);
                (res.data || []).forEach(function (ev) {
                    if (criticalOnly && !(ev.event_type === 'WORKER_SOS' || ev.event_type === 'WORKER_ANOMALY' || ev.event_type === 'WORKER_FAKE_GPS' || ev.event_type === 'WORKER_GPS_SPOOF_DETECTED' || ev.event_type === 'WORKER_ESCAPE_HIGH_RISK' || ev.event_type === 'WORKER_THREAT_HIGH' || ev.event_type === 'WORKER_THREAT_CRITICAL' || ev.event_type === 'WORKER_RESPONSE_ACTION')) {
                        return;
                    }
                    prependAlert(ev);
                });
            });
    }

    function scheduleLatestReload() {
        if (latestReloadTimer) {
            clearTimeout(latestReloadTimer);
        }
        latestReloadTimer = setTimeout(function () {
            loadLatest();
        }, 700);
    }

    function runPlayback() {
        var workerId = parseInt((document.getElementById('playWorkerId') || {}).value || '0', 10);
        if (!workerId) {
            flash('Worker ID required for playback', false);
            return;
        }
        var fromVal = (document.getElementById('playFrom') || {}).value || '';
        var toVal = (document.getElementById('playTo') || {}).value || '';
        var qp = new URLSearchParams();
        qp.set('worker_id', String(workerId));
        if (fromVal) qp.set('from', fromVal);
        if (toVal) qp.set('to', toVal);
        qp.set('limit', '5000');
        fetch(((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/worker-tracking/history.php?' + qp.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data || !Array.isArray(res.data.path)) {
                    throw new Error((res && res.message) || 'Playback load failed');
                }
                var path = res.data.path.filter(function (p) { return p.lat != null && p.lng != null; });
                if (path.length < 2) {
                    flash('Not enough points for playback', false);
                    return;
                }
                if (playbackLayer) map.removeLayer(playbackLayer);
                if (playbackMarker) map.removeLayer(playbackMarker);
                var latlngs = path.map(function (p) { return [parseFloat(p.lat), parseFloat(p.lng)]; });
                playbackLayer = L.polyline(latlngs, { color: '#38bdf8', weight: 4, opacity: 0.9 }).addTo(map);
                playbackMarker = L.circleMarker(latlngs[0], { radius: 9, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 0.9 }).addTo(map);
                map.fitBounds(playbackLayer.getBounds(), { padding: [30, 30] });

                var idx = 0;
                var timer = setInterval(function () {
                    idx++;
                    if (!playbackMarker || idx >= latlngs.length) {
                        clearInterval(timer);
                        return;
                    }
                    playbackMarker.setLatLng(latlngs[idx]);
                }, 350);
                flash('Playback started for worker #' + workerId, true);
            })
            .catch(function (e) {
                flash(e.message || 'Playback failed', false);
            });
    }

    function startStream() {
        var evTypes = [
            'WORKER_LOCATION_UPDATE',
            'WORKER_SOS',
            'WORKER_OFFLINE',
            'WORKER_ANOMALY',
            'WORKER_FAKE_GPS',
            'WORKER_GPS_SPOOF_DETECTED',
            'WORKER_SPOOF_SUSPECTED',
            'WORKER_GPS_SPOOF_CONFIRMED',
            'WORKER_ESCAPE_RISK',
            'WORKER_ESCAPE_HIGH_RISK',
            'WORKER_GEOFENCE_EXIT',
            'WORKER_GEOFENCE_ENTER',
            'WORKER_GEOFENCE_BREACH_PATTERN',
            'WORKER_THREAT_ELEVATED',
            'WORKER_THREAT_HIGH',
            'WORKER_THREAT_CRITICAL',
            'WORKER_RESPONSE_ACTION'
        ].join(',');
        var src = new EventSource((window.location.origin || '') + '/admin/events-stream.php?event_types=' + encodeURIComponent(evTypes));
        src.onmessage = function (e) {
            try {
                var ev = JSON.parse(e.data);
                if (!ev || !ev.event_type) return;
                if (ev.event_type === 'WORKER_LOCATION_UPDATE'
                    || ev.event_type === 'WORKER_OFFLINE'
                    || ev.event_type === 'WORKER_IDLE_ALERT'
                    || ev.event_type === 'WORKER_ANOMALY'
                    || ev.event_type === 'WORKER_SOS'
                    || ev.event_type === 'WORKER_FAKE_GPS'
                    || ev.event_type === 'WORKER_GPS_SPOOF_DETECTED'
                    || ev.event_type === 'WORKER_SPOOF_SUSPECTED'
                    || ev.event_type === 'WORKER_GPS_SPOOF_CONFIRMED'
                    || ev.event_type === 'WORKER_ESCAPE_RISK'
                    || ev.event_type === 'WORKER_ESCAPE_HIGH_RISK'
                    || ev.event_type === 'WORKER_GEOFENCE_EXIT'
                    || ev.event_type === 'WORKER_GEOFENCE_ENTER'
                    || ev.event_type === 'WORKER_GEOFENCE_BREACH_PATTERN'
                    || ev.event_type === 'WORKER_THREAT_ELEVATED'
                    || ev.event_type === 'WORKER_THREAT_HIGH'
                    || ev.event_type === 'WORKER_THREAT_CRITICAL'
                    || ev.event_type === 'WORKER_RESPONSE_ACTION') {
                    prependAlert(ev);
                    if (ev.event_type === 'WORKER_THREAT_ELEVATED' || ev.event_type === 'WORKER_THREAT_HIGH' || ev.event_type === 'WORKER_THREAT_CRITICAL') {
                        try {
                            var tm = ev.metadata;
                            if (typeof tm === 'string') tm = JSON.parse(tm);
                            setThreatBadge((tm && tm.threat_level) || ev.event_type.replace('WORKER_THREAT_', ''), (tm && tm.final_threat_score) || 0);
                        } catch (thErr) {}
                    }
                    if (ev.event_type === 'WORKER_RESPONSE_ACTION') {
                        try {
                            var rm = ev.metadata;
                            if (typeof rm === 'string') rm = JSON.parse(rm);
                            setResponseBadge((rm && rm.response_action) || 'NONE');
                        } catch (rspErr) {}
                    }
                    if (ev.event_type === 'WORKER_SOS' || ev.event_type === 'WORKER_THREAT_HIGH' || ev.event_type === 'WORKER_THREAT_CRITICAL') {
                        flash(ev.event_type === 'WORKER_SOS' ? 'SOS emergency received' : 'Worker threat escalation received', false);
                        try {
                            var meta = ev.metadata;
                            if (typeof meta === 'string') meta = JSON.parse(meta);
                            var wid = meta && meta.worker_id ? String(meta.worker_id) : '';
                            if (wid) {
                                Object.keys(markers).forEach(function (k) {
                                    if (k.split(':')[0] === wid) {
                                        var mk = markers[k];
                                        map.panTo(mk.getLatLng(), { animate: true, duration: 0.8 });
                                        mk.setStyle({ color: '#ef4444', fillColor: '#ef4444', radius: 11 });
                                        setTimeout(function () {
                                            try { mk.setStyle({ radius: 8 }); } catch (e) {}
                                        }, 1800);
                                    }
                                });
                            }
                        } catch (e) {}
                    }
                    scheduleLatestReload();
                    loadGeofences();
                }
            } catch (err) {
                // ignore
            }
        };
        src.onerror = function () {
            // best effort, keep browser auto-reconnect behavior
        };
    }

    var applyBtn = document.getElementById('trackingApplyFilters');
    if (applyBtn) applyBtn.addEventListener('click', loadLatest);
    var playBtn = document.getElementById('playHistoryBtn');
    if (playBtn) playBtn.addEventListener('click', runPlayback);
    var criticalToggle = document.getElementById('trackingCriticalOnly');
    if (criticalToggle) criticalToggle.addEventListener('change', function () {
        loadAlerts();
    });
    var geofenceToggle = document.getElementById('trackingShowGeofences');
    if (geofenceToggle) geofenceToggle.addEventListener('change', renderGeofences);

    var createBtn = document.getElementById('createGeofenceBtn');
    if (createBtn) createBtn.addEventListener('click', function () {
        var body = {
            name: (document.getElementById('geoName') || {}).value || '',
            center_lat: parseFloat((document.getElementById('geoCenterLat') || {}).value || '0'),
            center_lng: parseFloat((document.getElementById('geoCenterLng') || {}).value || '0'),
            radius_m: parseInt((document.getElementById('geoRadiusM') || {}).value || '0', 10),
            tenant_id: parseInt((document.getElementById('trackingFilterTenant') || {}).value || '0', 10) || 0,
            agency_id: parseInt((document.getElementById('trackingFilterAgency') || {}).value || '0', 10) || 0
        };
        fetch(apiBase + '/worker-tracking.php?action=create_geofence', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.success) throw new Error(res.message || 'Create geofence failed');
            flash('Geofence created', true);
            loadGeofences();
        }).catch(function (e) { flash(e.message || 'Create geofence failed', false); });
    });

    loadLatest();
    loadAlerts();
    loadGeofences();
    setThreatBadge('NORMAL', 0);
    setResponseBadge('NONE');
    startStream();
})();
