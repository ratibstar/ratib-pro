<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}
require_once __DIR__ . '/core/ControlCenterAccess.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$isSuper = ControlCenterAccess::role() === ControlCenterAccess::SUPER_ADMIN;
$isGov = false;
if (!empty($_SESSION['control_logged_in'])) {
    $perms = $_SESSION['control_permissions'] ?? [];
    $isGov = $perms === '*'
        || (is_array($perms) && (in_array('gov_admin', $perms, true)
            || in_array('control_government', $perms, true)
            || in_array('view_control_government', $perms, true)));
}
if (!$isSuper && !$isGov) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Government Tracking (Read Only)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        body { background:#0b1020; color:#e2e8f0; }
        .wrap { max-width: 1400px; margin: 24px auto; padding: 0 12px; }
        .map { height: 520px; border-radius: 12px; overflow: hidden; border:1px solid rgba(148,163,184,.25); }
        .map-fullscreen { position: fixed !important; inset: 0; z-index: 9999; border-radius: 0 !important; height: 100vh !important; width: 100vw !important; }
        .panel { background: rgba(15,23,42,.8); border:1px solid rgba(148,163,184,.2); border-radius: 12px; padding: 12px; }
        .alert-list { max-height: 250px; overflow:auto; list-style:none; padding:0; margin:0; }
        .alert-list li { border-bottom:1px solid rgba(148,163,184,.15); padding:7px 0; font-size:.9rem; }
        .alert-list li:last-child { border-bottom:none; }
        .counter-chip { display:inline-block; margin-right:10px; font-size:.9rem; color:#cbd5e1; }
        .counter-chip strong { color:#f8fafc; margin-right:4px; }
        .critical-banner { background:#7f1d1d; color:#fee2e2; border:1px solid #ef4444; border-radius:8px; padding:6px 10px; margin-bottom:8px; display:none; }
    </style>
</head>
<body>
<div class="wrap">
    <h3 class="mb-3">Government Tracking (Read-only)</h3>
    <div class="row g-2 mb-2">
        <div class="col-md-2"><input id="fTenant" class="form-control form-control-sm" type="number" placeholder="Tenant ID"></div>
        <div class="col-md-2"><input id="fAgency" class="form-control form-control-sm" type="number" placeholder="Agency ID"></div>
        <div class="col-md-2"><input id="fCountry" class="form-control form-control-sm" type="number" placeholder="Country ID"></div>
        <div class="col-md-2"><button id="btnApply" class="btn btn-sm btn-primary">Apply</button></div>
        <div class="col-md-2"><button id="btnFullscreen" class="btn btn-sm btn-outline-light">Full-screen map</button></div>
        <div class="col-md-2">
            <label class="form-check mt-1">
                <input class="form-check-input" type="checkbox" id="criticalOnly">
                <span class="form-check-label">Critical only</span>
            </label>
        </div>
    </div>
    <div class="mb-2">
        <span class="counter-chip"><strong id="cTotal">0</strong>Total workers</span>
        <span class="counter-chip"><strong id="cActive">0</strong>Active</span>
        <span class="counter-chip"><strong id="cOffline">0</strong>Offline</span>
        <span class="counter-chip"><strong id="cAlerts">0</strong>Alerts</span>
    </div>
    <div id="criticalBanner" class="critical-banner">Critical worker emergency event received</div>
    <div id="map" class="map"></div>
    <div class="row g-3 mt-2">
        <div class="col-lg-8 panel">
            <h6>Latest positions</h6>
            <div class="table-responsive">
                <table class="table table-sm table-dark align-middle" id="tbl">
                    <thead><tr><th>Worker</th><th>Tenant</th><th>Agency</th><th>Last Seen</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4 panel">
            <h6>Critical alerts</h6>
            <div class="mb-2">
                <button class="btn btn-sm btn-outline-danger" type="button" disabled title="Future integration">Contact agency</button>
                <button class="btn btn-sm btn-outline-success" type="button" disabled title="Future integration">Mark resolved</button>
            </div>
            <ul id="alerts" class="alert-list"></ul>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-4 panel">
            <h6>🚨 Escape Risk Workers</h6>
            <ul id="highRiskList" class="alert-list"></ul>
        </div>
        <div class="col-lg-4 panel">
            <h6>🛰️ Geofence Violations</h6>
            <ul id="outsideGeoList" class="alert-list"></ul>
        </div>
        <div class="col-lg-4 panel">
            <h6>🔐 Spoofing Suspected</h6>
            <ul id="spoofList" class="alert-list"></ul>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
  const map = L.map('map').setView([20.5937, 78.9629], 4);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
  const markers = {};
  const tbody = document.querySelector('#tbl tbody');
  const alerts = document.getElementById('alerts');
  const criticalBanner = document.getElementById('criticalBanner');
  const highRiskList = document.getElementById('highRiskList');
  const outsideGeoList = document.getElementById('outsideGeoList');
  const spoofList = document.getElementById('spoofList');
  let refreshTimer = null;
  let audioCtx = null;

  const esc = s => { const d=document.createElement('div'); d.textContent = s==null?'':String(s); return d.innerHTML; };
  const st = r => (r.location_status || (r.status === 'lost' ? 'offline' : (r.status === 'inactive' ? 'idle' : 'moving')) || 'offline').toLowerCase();
  const color = s => s==='moving'?'#16a34a':(s==='idle'?'#eab308':(s==='alert'?'#ef4444':'#64748b'));

  function params() {
    const p = new URLSearchParams();
    const tenant = document.getElementById('fTenant').value;
    const agency = document.getElementById('fAgency').value;
    const country = document.getElementById('fCountry').value;
    if (tenant) p.set('tenant_id', tenant);
    if (agency) p.set('agency_id', agency);
    if (country) p.set('country', country);
    p.set('limit', '800');
    return p.toString();
  }

  function load() {
    fetch('api/tracking/government.php?action=latest&' + params(), {credentials:'same-origin'})
      .then(r => r.json())
      .then(j => {
        if (!j.success) return;
        const rows = j.data || [];
        tbody.innerHTML = rows.map(r => `<tr><td>#${esc(r.worker_id)}</td><td>${esc(r.tenant_id)}</td><td>${esc(r.agency_name || r.agency_id || '')}</td><td>${esc(r.last_seen || '')}</td><td><span class="badge" style="background:${color(st(r))}">${esc(st(r))}</span></td></tr>`).join('') || '<tr><td colspan="5">No data</td></tr>';
        const total = rows.length;
        const active = rows.filter(r => st(r) === 'moving').length;
        const offline = rows.filter(r => st(r) === 'offline').length;
        const alertsN = rows.filter(r => st(r) === 'alert').length;
        document.getElementById('cTotal').textContent = String(total);
        document.getElementById('cActive').textContent = String(active);
        document.getElementById('cOffline').textContent = String(offline);
        document.getElementById('cAlerts').textContent = String(alertsN);
        Object.keys(markers).forEach(k => { map.removeLayer(markers[k]); delete markers[k]; });
        const bounds = [];
        rows.forEach(r => {
          if (r.lat == null || r.lng == null) return;
          const lat = parseFloat(r.lat), lng = parseFloat(r.lng); if (!isFinite(lat)||!isFinite(lng)) return;
          const status = st(r);
          const m = L.circleMarker([lat,lng], {radius:8, color:color(status), fillColor:color(status), fillOpacity:.85}).addTo(map);
          m.bindPopup(`<strong>Worker #${esc(r.worker_id)}</strong><br>Tenant: ${esc(r.tenant_id)}<br>Agency: ${esc(r.agency_name || r.agency_id || '')}<br>Last: ${esc(r.last_seen || '')}<br>Status: ${esc(status)}`);
          markers[r.worker_id + ':' + r.tenant_id] = m;
          bounds.push([lat,lng]);
        });
        if (bounds.length) map.fitBounds(bounds, {padding:[30,30]});
      });
  }

  function pushAlert(ev){
    const criticalOnly = !!document.getElementById('criticalOnly').checked;
    const critical = ev.event_type === 'WORKER_SOS' || ev.event_type === 'WORKER_ANOMALY' || ev.event_type === 'WORKER_FAKE_GPS' || ev.level === 'critical';
    if (criticalOnly && !critical) return;
    const li = document.createElement('li');
    li.innerHTML = `<strong>${esc(ev.event_type || '')}</strong> — ${esc(ev.message || '')}<br><small>${esc(ev.created_at || '')}</small>`;
    alerts.prepend(li);
    while (alerts.children.length > 100) alerts.removeChild(alerts.lastChild);
    pushSpecial(ev);
  }

  function pushSpecial(ev) {
    const type = String(ev.event_type || '');
    const item = `<strong>${esc(type)}</strong> — ${esc(ev.message || '')}<br><small>${esc(ev.created_at || '')}</small>`;
    if (type === 'WORKER_ESCAPE_RISK' || type === 'WORKER_ESCAPE_HIGH_RISK' || type === 'WORKER_THREAT_ELEVATED' || type === 'WORKER_THREAT_HIGH' || type === 'WORKER_THREAT_CRITICAL') {
      highRiskList.prepend(htmlToLi(item));
      trimList(highRiskList, 60);
    }
    if (type === 'WORKER_GEOFENCE_EXIT' || type === 'WORKER_GEOFENCE_BREACH_PATTERN') {
      outsideGeoList.prepend(htmlToLi(item));
      trimList(outsideGeoList, 60);
    }
    if (type === 'WORKER_GPS_SPOOF_DETECTED' || type === 'WORKER_FAKE_GPS' || type === 'WORKER_SPOOF_SUSPECTED' || type === 'WORKER_GPS_SPOOF_CONFIRMED') {
      spoofList.prepend(htmlToLi(item));
      trimList(spoofList, 60);
    }
  }

  function htmlToLi(html) {
    const li = document.createElement('li');
    li.innerHTML = html;
    return li;
  }

  function trimList(list, maxLen) {
    while (list.children.length > maxLen) list.removeChild(list.lastChild);
  }

  function loadAlerts() {
    fetch('api/tracking/government.php?action=alerts&limit=40', {credentials:'same-origin'})
      .then(r => r.json())
      .then(j => {
        if (!j.success) return;
        alerts.innerHTML = '';
        highRiskList.innerHTML = '';
        outsideGeoList.innerHTML = '';
        spoofList.innerHTML = '';
        (j.data || []).forEach(pushAlert);
      });
  }

  const trackedTypes = ['WORKER_LOCATION_UPDATE','WORKER_SOS','WORKER_OFFLINE','WORKER_ANOMALY','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_SPOOF_SUSPECTED','WORKER_GPS_SPOOF_CONFIRMED','WORKER_ESCAPE_RISK','WORKER_ESCAPE_HIGH_RISK','WORKER_GEOFENCE_EXIT','WORKER_GEOFENCE_ENTER','WORKER_GEOFENCE_BREACH_PATTERN','WORKER_THREAT_ELEVATED','WORKER_THREAT_HIGH','WORKER_THREAT_CRITICAL','WORKER_RESPONSE_ACTION'].join(',');
  const es = new EventSource('events-stream.php?event_types=' + encodeURIComponent(trackedTypes));
  es.onmessage = (e) => {
    try {
      const ev = JSON.parse(e.data);
      if (!ev || !ev.event_type) return;
        if (ev.event_type.indexOf('WORKER_') === 0) {
        pushAlert(ev);
          if (ev.event_type === 'WORKER_SOS' || ev.event_type === 'WORKER_ESCAPE_HIGH_RISK' || ev.event_type === 'WORKER_GPS_SPOOF_CONFIRMED' || ev.event_type === 'WORKER_THREAT_CRITICAL') {
          criticalBanner.style.display = 'block';
          setTimeout(() => { criticalBanner.style.display = 'none'; }, 6000);
          beep();
          try {
            let m = ev.metadata;
            if (typeof m === 'string') m = JSON.parse(m);
            const wid = m && m.worker_id ? String(m.worker_id) : '';
            if (wid) {
              Object.keys(markers).forEach(k => {
                if (k.split(':')[0] === wid) {
                  const marker = markers[k];
                  map.panTo(marker.getLatLng(), {animate:true, duration:.8});
                  marker.setStyle({radius: 12, color: '#ef4444', fillColor:'#ef4444'});
                  setTimeout(() => { try { marker.setStyle({radius:8}); } catch(e) {} }, 1800);
                }
              });
            }
          } catch(e) {}
        }
        debounceLoad();
      }
    } catch (err) {}
  };

  document.getElementById('btnApply').addEventListener('click', load);
  document.getElementById('criticalOnly').addEventListener('change', loadAlerts);
  document.getElementById('btnFullscreen').addEventListener('click', () => {
    const mapEl = document.getElementById('map');
    mapEl.classList.toggle('map-fullscreen');
    setTimeout(() => map.invalidateSize(), 120);
  });

  function debounceLoad() {
    if (refreshTimer) clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => load(), 700);
  }

  function beep() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      const o = audioCtx.createOscillator();
      const g = audioCtx.createGain();
      o.connect(g); g.connect(audioCtx.destination);
      o.frequency.value = 880;
      g.gain.setValueAtTime(0.001, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.15, audioCtx.currentTime + 0.01);
      g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
      o.start(); o.stop(audioCtx.currentTime + 0.36);
    } catch (e) {}
  }
  load();
  loadAlerts();
})();
</script>
</body>
</html>
