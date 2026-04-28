(function () {
    'use strict';

    var STORAGE_KEYS = {
        state: 'worker_tracking_mobile_state_v2',
        telemetry: 'worker_tracking_local_telemetry_v1',
        selected: 'admin_dashboard_selected_worker_v1',
        focus: 'admin_dashboard_focus_mode_v1'
    };

    function safeGetStorage(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function safeSetStorage(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            // Storage may be unavailable in restricted contexts.
        }
    }

    var state = {
        workers: [],
        selectedWorker: safeGetStorage(STORAGE_KEYS.selected) || null,
        alerts: [],
        stats: {
            total: 0,
            goodPct: 0,
            limitedPct: 0,
            poorPct: 0,
            recoveries: 0,
            predictions: 0,
            threat: 'LOW'
        },
        ui: {
            search: '',
            filter: 'ALL',
            platform: 'ALL',
            focusMode: safeGetStorage(STORAGE_KEYS.focus) === '1'
        }
    };

    var dom = {
        list: null,
        details: null,
        alerts: null,
        stamp: null,
        search: null,
        filter: null,
        platformAll: null,
        platformAndroid: null,
        platformIOS: null,
        focus: null,
        stats: {
            total: null,
            good: null,
            limited: null,
            poor: null,
            recoveries: null,
            predictions: null,
            threat: null
        }
    };

    var mapCtx = {
        map: null,
        layer: null,
        markers: {},
        markerState: {},
        initialized: false
    };

    var diffCache = {
        workerList: '',
        selectedDetails: '',
        alerts: '',
        stats: ''
    };

    var renderQueued = false;
    var booted = false;
    var initAttempts = 0;
    var liveSnapshot = null;
    var liveFetchInFlight = false;
    var liveFetchAttempted = false;
    var liveFetchSucceeded = false;
    var preferLiveApiOnly = false;
    var liveWorkerMetaCache = {};
    var liveSourceStats = { gov: 0, core: 0, coreRaw: 0, legacy: 0, sessions: 0 };
    var workerProfileCache = {};
    var workerProfileInFlight = {};
    window.SOCDashboardControls = {
        setPlatform: function () {},
        toggleFocus: function () {}
    };

    function hydrateDomRefs() {
        dom.list = document.getElementById('workersList');
        dom.details = document.getElementById('workerDetails');
        dom.alerts = document.getElementById('alertsList');
        dom.stamp = document.getElementById('workersStamp');
        dom.search = document.getElementById('workerSearch');
        dom.filter = document.getElementById('statusFilter');
        dom.platformAll = document.getElementById('platformAll');
        dom.platformAndroid = document.getElementById('platformAndroid');
        dom.platformIOS = document.getElementById('platformIOS');
        dom.focus = document.getElementById('focusToggle');
        dom.stats.total = document.getElementById('sTotal');
        dom.stats.good = document.getElementById('sGood');
        dom.stats.limited = document.getElementById('sLimited');
        dom.stats.poor = document.getElementById('sPoor');
        dom.stats.recoveries = document.getElementById('sRecoveries');
        dom.stats.predictions = document.getElementById('sPredictions');
        dom.stats.threat = document.getElementById('sThreat');
    }

    function readJson(key, fallback) {
        try {
            var raw = safeGetStorage(key);
            if (!raw) return fallback;
            var parsed = JSON.parse(raw);
            return (parsed === null || parsed === undefined) ? fallback : parsed;
        } catch (e) {
            return fallback;
        }
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function qualityClass(q) {
        if (q === 'GOOD') return 'q-good';
        if (q === 'POOR') return 'q-poor';
        return 'q-limited';
    }

    function markerClass(q) {
        if (q === 'GOOD') return 'm-good';
        if (q === 'POOR') return 'm-poor';
        return 'm-limited';
    }

    function formatAgo(ts) {
        if (!ts) return '-';
        var secs = Math.max(0, Math.floor((Date.now() - ts) / 1000));
        if (secs < 60) return secs + 's ago';
        if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
        return Math.floor(secs / 3600) + 'h ago';
    }

    function severityForEvent(evt) {
        if (!evt || !evt.k) return 'INFO';
        if (evt.k === 'sync_delays') return 'WARNING';
        if (evt.k === 'prediction_triggers') return 'WARNING';
        if (evt.k === 'gps_weak_events') return 'WARNING';
        if (evt.k === 'recovery_count') return 'INFO';
        return 'CRITICAL';
    }

    function threatPrediction(counters, recentAlerts) {
        var p = counters.prediction_triggers || 0;
        var r = counters.recovery_count || 0;
        var s = counters.sync_delays || 0;
        var g = counters.gps_weak_events || 0;
        var score = (p * 2) + (s * 3) + (g * 2) + Math.max(0, (r - 2));
        var criticalRecent = recentAlerts.filter(function (x) { return x.severity === 'CRITICAL'; }).length;
        score += criticalRecent * 4;
        if (score >= 18) return 'HIGH';
        if (score >= 8) return 'MEDIUM';
        return 'LOW';
    }

    function normalizeQuality(raw) {
        var q = String(raw || '').toUpperCase();
        if (q === 'GOOD' || q === 'LIMITED' || q === 'POOR') return q;
        return 'LIMITED';
    }

    function normalizeWorkerInput(worker, fallbackIndex, tenantId) {
        var idx = typeof fallbackIndex === 'number' ? fallbackIndex : 0;
        var now = state._baseTime || (state._baseTime = Date.now());
        var id = String(worker.id || worker.worker_id || ('worker_' + (idx + 1)));
        var name = String(worker.name || worker.worker_name || ('Worker ' + (idx + 1)));
        var lat = Number(worker.lat != null ? worker.lat : worker.latitude);
        var lng = Number(worker.lng != null ? worker.lng : worker.longitude);
        if (!isFinite(lat)) lat = 24.7136 + ((idx % 5) - 2) * 0.018;
        if (!isFinite(lng)) lng = 46.6753 + (Math.floor(idx / 5) - 0.5) * 0.03;
        var lastSyncTs = Number(worker.lastSyncTs || worker.last_sync_ts || worker.last_sync_at || 0);
        var lastUpdateTs = Number(worker.lastUpdateTs || worker.last_update_ts || worker.last_location_ts || 0);
        if (!lastSyncTs) lastSyncTs = now - (idx * 40000);
        if (!lastUpdateTs) lastUpdateTs = now - (idx * 50000);
        var platform = String(worker.platform || worker.device_platform || worker.os || '').toLowerCase();
        if (platform !== 'android' && platform !== 'ios') {
            platform = (idx % 2 === 0) ? 'android' : 'ios';
        }
        return {
            id: id,
            name: name,
            tenantId: tenantId || String(worker.tenant_id || worker.tenantId || 'default'),
            lat: lat,
            lng: lng,
            lastSyncTs: lastSyncTs,
            lastUpdateTs: lastUpdateTs,
            quality: normalizeQuality(worker.quality),
            confidenceReasons: Array.isArray(worker.confidenceReasons) ? worker.confidenceReasons : [],
            predictionState: String(worker.predictionState || worker.prediction_state || 'NONE'),
            recoveryState: String(worker.recoveryState || worker.recovery_state || 'stable'),
            telemetryCounters: worker.telemetryCounters || {},
            platform: platform,
            rawWorkerId: Number(worker.rawWorkerId || worker.worker_numeric_id || worker.worker_id || 0) || 0,
            formattedId: String(worker.formattedId || worker.formatted_id || ''),
            country: String(worker.country || worker.worker_country || ''),
            agencyId: Number(worker.agencyId || worker.agency_id || 0) || 0,
            agencyName: String(worker.agencyName || worker.agency_name || ''),
            sessionStatus: String(worker.sessionStatus || worker.session_status || worker.status || ''),
            locationStatus: String(worker.locationStatus || worker.location_status || ''),
            speed: Number(worker.speed || worker.last_speed || 0) || 0,
            battery: Number(worker.battery || worker.last_battery || 0) || 0,
            source: String(worker.source || worker.last_source || ''),
            profile: worker.profile && typeof worker.profile === 'object' ? worker.profile : null,
            profileUpdatedAt: Number(worker.profileUpdatedAt || 0) || 0
        };
    }

    function normalizeAlertInput(evt, fallbackIndex) {
        var t = Number(evt.t || evt.timestamp || Date.now() - fallbackIndex * 1000);
        var k = String(evt.k || evt.type || 'event');
        return {
            id: String(evt.id || (t + '_' + k + '_' + fallbackIndex)),
            t: t,
            type: k,
            message: String(evt.message || k),
            severity: String(evt.severity || severityForEvent({ k: k })).toUpperCase()
        };
    }

    function parseTs(value) {
        if (!value) return 0;
        if (typeof value === 'number') return value;
        var t = Date.parse(String(value));
        return isFinite(t) ? t : 0;
    }

    function detectPlatformFromSource(source, fallbackIndex) {
        var s = String(source || '').toLowerCase();
        if (s.indexOf('ios') !== -1 || s.indexOf('iphone') !== -1) return 'ios';
        if (s.indexOf('android') !== -1) return 'android';
        return (fallbackIndex % 2 === 0) ? 'android' : 'ios';
    }

    function qualityFromLiveWorker(worker, nowTs) {
        var status = String(worker.status || worker.session_status || '').toLowerCase();
        var lastSeenTs = parseTs(worker.last_seen);
        var age = lastSeenTs ? (nowTs - lastSeenTs) : 999999;
        if (status === 'lost' || status === 'inactive' || age > 180000) return 'POOR';
        if (status === 'active' && age <= 90000) return 'GOOD';
        return 'LIMITED';
    }

    function mapLiveWorker(worker, idx, nowTs, lookup) {
        var idNum = Number(worker.worker_id || worker.id || 0);
        var workerId = idNum > 0 ? ('worker_' + idNum) : ('worker_' + (idx + 1));
        var lookupInfo = (lookup && idNum > 0 && lookup[idNum]) ? lookup[idNum] : {};
        rememberWorkerMeta(idNum, lookupInfo);
        var info = idNum > 0 ? (liveWorkerMetaCache[idNum] || lookupInfo || {}) : lookupInfo;
        var lastSeenTs = parseTs(worker.last_seen);
        var quality = qualityFromLiveWorker(worker, nowTs);
        var status = String(worker.status || worker.session_status || '').toLowerCase();
        var workerName = String(info.worker_name || info.name || ('Worker ' + (idNum > 0 ? idNum : (idx + 1))));
        return normalizeWorkerInput({
            id: workerId,
            worker_id: workerId,
            rawWorkerId: idNum,
            name: workerName,
            tenant_id: worker.tenant_id || info.tenant_id || 'default',
            lat: worker.lat,
            lng: worker.lng,
            last_sync_ts: lastSeenTs,
            last_update_ts: lastSeenTs,
            session_status: worker.status || worker.session_status || '',
            location_status: worker.location_status || '',
            speed: worker.speed,
            battery: worker.battery,
            source: worker.source,
            agency_id: worker.agency_id || info.agency_id || 0,
            agency_name: worker.agency_name || info.agency_name || '',
            worker_country: info.worker_country || info.country || '',
            formatted_id: info.formatted_id || '',
            quality: quality,
            confidenceReasons: quality === 'GOOD'
                ? ['Live session active', 'Recent location received']
                : (quality === 'POOR'
                    ? ['Worker offline/stale', 'Immediate follow-up needed']
                    : ['Session degraded', 'Location update delayed']),
            prediction_state: quality === 'POOR' ? 'SYNC_RISK' : (quality === 'LIMITED' ? 'GPS_RISK' : 'NONE'),
            recovery_state: status === 'active' ? 'stable' : 'monitoring',
            device_platform: detectPlatformFromSource(worker.source, idx)
        }, idx, String(worker.tenant_id || 'default'));
    }

    function mapLiveAlert(evt, idx) {
        var severity = 'INFO';
        var priority = String(evt.priority || evt.level || '').toLowerCase();
        if (priority === 'critical' || priority === 'high') severity = 'CRITICAL';
        else if (priority === 'medium' || priority === 'warning') severity = 'WARNING';
        return normalizeAlertInput({
            id: evt.id || ('live_alert_' + idx),
            t: parseTs(evt.created_at) || Date.now() - (idx * 1000),
            type: evt.event_type || 'event',
            message: evt.message || evt.event_type || 'event',
            severity: severity
        }, idx);
    }

    function getControlApiBase() {
        var controlConfig = document.getElementById('control-config');
        if (controlConfig && controlConfig.dataset && controlConfig.dataset.apiBase) {
            return String(controlConfig.dataset.apiBase).replace(/\/+$/, '');
        }
        var appConfig = document.getElementById('app-config');
        if (appConfig && appConfig.dataset && appConfig.dataset.controlApiPath) {
            return String(appConfig.dataset.controlApiPath).replace(/\/+$/, '');
        }
        return '/api/control';
    }

    function getMainApiBase() {
        var appConfig = document.getElementById('app-config');
        if (appConfig && appConfig.dataset && appConfig.dataset.apiBase) {
            return String(appConfig.dataset.apiBase).replace(/\/+$/, '');
        }
        return '/api';
    }

    function normalizeWorkerLookup(rows) {
        var out = {};
        if (!Array.isArray(rows)) return out;
        rows.forEach(function (r) {
            var idNum = Number(r && r.worker_id);
            if (!idNum) return;
            out[idNum] = r || {};
        });
        return out;
    }

    function parseWorkersListPayload(payload) {
        if (!payload || typeof payload !== 'object') return [];
        if (Array.isArray(payload.data)) return payload.data;
        if (payload.data && typeof payload.data === 'object') {
            if (Array.isArray(payload.data.workers)) return payload.data.workers;
            if (payload.data.data && Array.isArray(payload.data.data.workers)) return payload.data.data.workers;
        }
        if (payload.workers && Array.isArray(payload.workers)) return payload.workers;
        return [];
    }

    function buildWorkersCoreUrl(base) {
        var qp = new URLSearchParams();
        qp.set('page', '1');
        qp.set('limit', '500');
        var pageQp = new URLSearchParams(window.location.search || '');
        ['control', 'agency_id', 'country', 'country_id', 'tenant_id'].forEach(function (k) {
            var v = pageQp.get(k);
            if (v !== null && v !== '') qp.set(k, v);
        });
        return base + '/workers/core/get.php?' + qp.toString();
    }

    function buildWorkersCoreRawUrl(base) {
        return base + '/workers/core/get.php?page=1&limit=500';
    }

    function rememberWorkerMeta(idNum, info) {
        if (!idNum || !info || typeof info !== 'object') return;
        var prev = liveWorkerMetaCache[idNum] || {};
        liveWorkerMetaCache[idNum] = {
            worker_name: String(info.worker_name || prev.worker_name || ''),
            formatted_id: String(info.formatted_id || prev.formatted_id || ''),
            worker_country: String(info.worker_country || info.country || prev.worker_country || ''),
            agency_id: Number(info.agency_id || prev.agency_id || 0) || 0,
            agency_name: String(info.agency_name || prev.agency_name || '')
        };
    }

    function fetchLiveSnapshot() {
        if (liveFetchInFlight) return;
        liveFetchInFlight = true;
        liveFetchAttempted = true;
        var base = getControlApiBase();
        var mainApiBase = getMainApiBase();
        var latestUrl = base + '/worker-tracking.php?action=latest&limit=200';
        var alertsUrl = base + '/worker-tracking.php?action=alerts&limit=60';
        var trackingLookupUrl = base + '/government.php?action=tracking';
        var workersCoreUrl = buildWorkersCoreUrl(mainApiBase);
        var workersCoreRawUrl = buildWorkersCoreRawUrl(mainApiBase);
        var workersGovUrl = base + '/government.php?action=workers';
        var workersListUrl = mainApiBase + '/workers/get.php?limit=300&page=1';

        Promise.all([
            fetch(latestUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(alertsUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(trackingLookupUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(workersGovUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(workersCoreUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(workersCoreRawUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; }),
            fetch(workersListUrl, { credentials: 'include' }).then(function (r) { return r.json(); }).catch(function () { return null; })
        ]).then(function (res) {
            var latest = res[0];
            var alerts = res[1];
            var tracking = res[2];
            var workersGovPayload = res[3];
            var workersCorePayload = res[4];
            var workersCoreRawPayload = res[5];
            var workersPayload = res[6];
            if (!latest || latest.success !== true || !Array.isArray(latest.data)) return;
            liveFetchSucceeded = true;
            var nowTs = Date.now();
            var lookup = (tracking && tracking.success === true) ? normalizeWorkerLookup(tracking.data) : {};
            var sessionRows = latest.data;
            liveSourceStats.sessions = sessionRows.length;
            var workersByNumericId = {};
            var workers = sessionRows.map(function (w, idx) {
                var mapped = mapLiveWorker(w || {}, idx, nowTs, lookup);
                if (mapped.rawWorkerId) workersByNumericId[mapped.rawWorkerId] = mapped;
                return mapped;
            });

            var fromGov = parseWorkersListPayload(workersGovPayload);
            var fromCore = parseWorkersListPayload(workersCorePayload);
            var fromCoreRaw = parseWorkersListPayload(workersCoreRawPayload);
            var fromLegacy = parseWorkersListPayload(workersPayload);
            liveSourceStats.gov = fromGov.length;
            liveSourceStats.core = fromCore.length;
            liveSourceStats.coreRaw = fromCoreRaw.length;
            liveSourceStats.legacy = fromLegacy.length;
            var allWorkers = fromGov;
            if (fromCore.length > allWorkers.length) allWorkers = fromCore;
            if (fromCoreRaw.length > allWorkers.length) allWorkers = fromCoreRaw;
            if (fromLegacy.length > allWorkers.length) allWorkers = fromLegacy;
            allWorkers.forEach(function (w, idx) {
                var idNum = Number((w && w.id) || 0);
                if (!idNum || workersByNumericId[idNum]) return;
                var fallbackQuality = 'POOR';
                var fallbackTs = nowTs - ((idx + 1) * 60000);
                var normalized = normalizeWorkerInput({
                    id: 'worker_' + idNum,
                    worker_id: 'worker_' + idNum,
                    rawWorkerId: idNum,
                    name: w.worker_name || ('Worker ' + idNum),
                    formatted_id: w.formatted_id || '',
                    country: w.country || '',
                    status: w.status || 'inactive',
                    session_status: 'inactive',
                    last_sync_ts: fallbackTs,
                    last_update_ts: fallbackTs,
                    quality: fallbackQuality,
                    confidenceReasons: ['No active tracking session'],
                    prediction_state: 'SYNC_RISK',
                    recovery_state: 'monitoring',
                    device_platform: 'android'
                }, workers.length + idx, String(w.tenant_id || 'default'));
                workers.push(normalized);
            });
            var liveAlerts = (alerts && alerts.success === true && Array.isArray(alerts.data))
                ? alerts.data.map(function (a, idx) { return mapLiveAlert(a || {}, idx); })
                : [];
            liveSnapshot = {
                workers: workers,
                alerts: liveAlerts,
                counters: readJson(STORAGE_KEYS.telemetry, { counters: {} }).counters || {}
            };
            scheduleRender();
        }).catch(function () {
            // Silent fallback to local providers.
        }).finally(function () {
            liveFetchInFlight = false;
        });
    }

    function maybeFetchWorkerProfile(worker) {
        if (!worker || !worker.rawWorkerId) return;
        var rawId = Number(worker.rawWorkerId || 0);
        if (!rawId) return;
        if (workerProfileCache[rawId] || workerProfileInFlight[rawId]) return;
        workerProfileInFlight[rawId] = true;
        var apiBase = getMainApiBase();
        var url = apiBase + '/workers/get-single.php?id=' + encodeURIComponent(String(rawId));
        fetch(url, { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || payload.success !== true || !payload.data || typeof payload.data !== 'object') return;
                workerProfileCache[rawId] = payload.data;
                state.workers = state.workers.map(function (w) {
                    if (Number(w.rawWorkerId || 0) !== rawId) return w;
                    var merged = {};
                    Object.keys(w).forEach(function (k) { merged[k] = w[k]; });
                    merged.profile = payload.data;
                    merged.profileUpdatedAt = Date.now();
                    if ((!merged.name || /^Worker\s+\d+$/i.test(String(merged.name || ''))) && payload.data.worker_name) {
                        merged.name = String(payload.data.worker_name);
                    }
                    if (!merged.formattedId && payload.data.formatted_id) {
                        merged.formattedId = String(payload.data.formatted_id);
                    }
                    if (!merged.country && payload.data.country) {
                        merged.country = String(payload.data.country);
                    }
                    return merged;
                });
                scheduleRender();
            })
            .catch(function () {
                // Keep UI stable without profile details.
            })
            .finally(function () {
                delete workerProfileInFlight[rawId];
            });
    }

    function flattenWorkersFromPayload(payload) {
        if (!payload || typeof payload !== 'object') return [];
        if (Array.isArray(payload.workers)) {
            return payload.workers.map(function (w, idx) { return normalizeWorkerInput(w || {}, idx, null); });
        }
        if (Array.isArray(payload.tenants)) {
            var merged = [];
            payload.tenants.forEach(function (tenant, tenantIdx) {
                var tid = String((tenant && (tenant.id || tenant.tenant_id)) || ('tenant_' + (tenantIdx + 1)));
                var list = Array.isArray(tenant && tenant.workers) ? tenant.workers : [];
                list.forEach(function (w, idx) {
                    merged.push(normalizeWorkerInput(w || {}, merged.length + idx, tid));
                });
            });
            return merged;
        }
        return [];
    }

    function createWorkersFromLocal() {
        var baseState = readJson(STORAGE_KEYS.state, {});
        var telemetry = readJson(STORAGE_KEYS.telemetry, { counters: {}, events: [] });
        baseState = (baseState && typeof baseState === 'object') ? baseState : {};
        telemetry = (telemetry && typeof telemetry === 'object') ? telemetry : { counters: {}, events: [] };
        var telemetryCounters = (telemetry.counters && typeof telemetry.counters === 'object') ? telemetry.counters : {};
        var seedLat = 24.7136;
        var seedLng = 46.6753;
        var baseTime = state._baseTime || (state._baseTime = Date.now());
        var total = 10;
        var workers = [];

        for (var i = 0; i < total; i += 1) {
            var jitterLat = ((i % 5) - 2) * 0.018;
            var jitterLng = (Math.floor(i / 5) - 0.5) * 0.03;
            var lastUpdate = baseState.lastRealGpsTs ? (baseState.lastRealGpsTs - i * 35000) : (baseTime - (i * 50000));
            var lastSync = baseState.lastSyncTs ? (baseState.lastSyncTs - i * 25000) : (baseTime - (i * 40000));
            var age = Math.max(0, baseTime - lastUpdate);
            var quality = 'LIMITED';
            if (age <= 90000 && i % 4 !== 3) quality = 'GOOD';
            else if (age > 180000 || i % 5 === 4) quality = 'POOR';

            workers.push(normalizeWorkerInput({
                id: 'worker_' + (i + 1),
                name: 'Worker ' + (i + 1),
                lat: seedLat + jitterLat,
                lng: seedLng + jitterLng,
                lastSyncTs: lastSync,
                lastUpdateTs: lastUpdate,
                quality: quality,
                confidenceReasons: quality === 'GOOD'
                    ? ['Stable GPS stream', 'Recent successful sync']
                    : (quality === 'POOR'
                        ? ['High GPS staleness', 'Sync path unhealthy']
                        : ['GPS weak', 'Update delay detected']),
                predictionState: quality === 'POOR' ? 'SYNC_RISK' : (quality === 'LIMITED' ? 'GPS_RISK' : 'NONE'),
                recoveryState: quality === 'POOR' ? 'boost_pending' : (quality === 'LIMITED' ? 'monitoring' : 'stable'),
                telemetryCounters: telemetryCounters
            }, i, 'default'));
        }

        return workers;
    }

    function createAlertsFromTelemetry() {
        var telemetry = readJson(STORAGE_KEYS.telemetry, { events: [], counters: {} });
        telemetry = (telemetry && typeof telemetry === 'object') ? telemetry : { events: [], counters: {} };
        var events = Array.isArray(telemetry.events) ? telemetry.events.slice(-24) : [];
        var labelByKey = {
            gps_weak_events: 'GPS weak signal observed',
            sync_delays: 'Sync delay threshold reached',
            prediction_triggers: 'Prediction risk triggered',
            recovery_count: 'Recovery routine executed'
        };
        return events.reverse().map(function (evt, idx) {
            var normalized = normalizeAlertInput(evt || {}, idx);
            if (!evt || !evt.message) {
                normalized.message = labelByKey[normalized.type] || normalized.type || 'Unknown event';
            }
            if (!evt || !evt.severity) {
                normalized.severity = severityForEvent({ k: normalized.type });
            }
            return normalized;
        });
    }

    /**
     * Compatibility adapter contract (future API/WebSocket/multi-tenant):
     * window.SOCDashboardAdapter = {
     *   getSnapshot: function (ctx) {
     *     return {
     *       workers: [{ id, name, tenant_id, lat, lng, quality, last_sync_ts, last_update_ts, prediction_state, recovery_state, confidenceReasons: [] }],
     *       // OR tenants: [{ id, workers: [...] }]
     *       alerts: [{ id, t, k|type, message, severity }],
     *       telemetry: { counters: { recovery_count, prediction_triggers, gps_weak_events, sync_delays } }
     *     };
     *   }
     * };
     */
    function resolveDataSnapshot() {
        if (liveSnapshot && Array.isArray(liveSnapshot.workers) && liveSnapshot.workers.length) {
            return {
                workers: liveSnapshot.workers,
                alerts: Array.isArray(liveSnapshot.alerts) ? liveSnapshot.alerts : [],
                counters: liveSnapshot.counters || {}
            };
        }
        if (preferLiveApiOnly && (liveFetchAttempted || liveFetchSucceeded)) {
            return {
                workers: (liveSnapshot && Array.isArray(liveSnapshot.workers)) ? liveSnapshot.workers : [],
                alerts: (liveSnapshot && Array.isArray(liveSnapshot.alerts)) ? liveSnapshot.alerts : [],
                counters: (liveSnapshot && liveSnapshot.counters) ? liveSnapshot.counters : {}
            };
        }
        var external = window.SOCDashboardAdapter;
        if (external && typeof external.getSnapshot === 'function') {
            try {
                var payload = external.getSnapshot({
                    storageKeys: STORAGE_KEYS,
                    readJson: readJson
                });
                if (payload && typeof payload === 'object') {
                    var workers = flattenWorkersFromPayload(payload);
                    var alerts = Array.isArray(payload.alerts)
                        ? payload.alerts.map(function (a, idx) { return normalizeAlertInput(a || {}, idx); })
                        : createAlertsFromTelemetry();
                    var localTelemetry = readJson(STORAGE_KEYS.telemetry, { counters: {} });
                    var localCounters = (localTelemetry && typeof localTelemetry === 'object' && localTelemetry.counters && typeof localTelemetry.counters === 'object')
                        ? localTelemetry.counters
                        : {};
                    var counters = (payload.telemetry && payload.telemetry.counters) || localCounters;
                    return {
                        workers: workers.length ? workers : createWorkersFromLocal(),
                        alerts: alerts,
                        counters: counters
                    };
                }
            } catch (e) {
                // External adapter failed; local adapter remains source of truth.
            }
        }
        return {
            workers: createWorkersFromLocal(),
            alerts: createAlertsFromTelemetry(),
            counters: (function () {
                var localTelemetry = readJson(STORAGE_KEYS.telemetry, { counters: {} });
                return (localTelemetry && typeof localTelemetry === 'object' && localTelemetry.counters && typeof localTelemetry.counters === 'object')
                    ? localTelemetry.counters
                    : {};
            })()
        };
    }

    function deriveStats(workers, alerts, counters) {
        counters = counters || readJson(STORAGE_KEYS.telemetry, { counters: {} }).counters || {};
        var total = workers.length || 1;
        var good = workers.filter(function (w) { return w.quality === 'GOOD'; }).length;
        var limited = workers.filter(function (w) { return w.quality === 'LIMITED'; }).length;
        var poor = workers.filter(function (w) { return w.quality === 'POOR'; }).length;
        return {
            total: workers.length,
            goodPct: Math.round((good / total) * 100),
            limitedPct: Math.round((limited / total) * 100),
            poorPct: Math.round((poor / total) * 100),
            recoveries: counters.recovery_count || 0,
            predictions: counters.prediction_triggers || 0,
            threat: threatPrediction(counters, alerts)
        };
    }

    function filteredWorkers() {
        var q = state.ui.search.trim().toLowerCase();
        return state.workers.filter(function (w) {
            if (state.ui.filter !== 'ALL' && w.quality !== state.ui.filter) return false;
            if (state.ui.platform !== 'ALL' && String(w.platform || '').toLowerCase() !== state.ui.platform.toLowerCase()) return false;
            if (!q) return true;
            var hay = (w.name + ' ' + w.id).toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    }

    function initializeMapIfNeeded() {
        if (mapCtx.initialized) return;
        if (!window.L || typeof window.L.map !== 'function') return;
        mapCtx.map = L.map('map', { zoomControl: true }).setView([24.7136, 46.6753], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapCtx.map);
        mapCtx.layer = L.layerGroup().addTo(mapCtx.map);
        mapCtx.initialized = true;
    }

    function syncState() {
        var snapshot = resolveDataSnapshot();
        state.workers = snapshot.workers;
        state.alerts = snapshot.alerts;
        state.stats = deriveStats(state.workers, state.alerts, snapshot.counters);
        if (!state.selectedWorker && state.workers.length) {
            state.selectedWorker = state.workers[0].id;
        }
        if (state.selectedWorker && !state.workers.some(function (w) { return w.id === state.selectedWorker; })) {
            state.selectedWorker = state.workers.length ? state.workers[0].id : null;
        }
    }

    function renderStats() {
        var s = state.stats;
        var key = JSON.stringify(s);
        if (diffCache.stats === key) return;
        diffCache.stats = key;
        dom.stats.total.textContent = String(s.total);
        dom.stats.good.textContent = s.goodPct + '%';
        dom.stats.limited.textContent = s.limitedPct + '%';
        dom.stats.poor.textContent = s.poorPct + '%';
        dom.stats.recoveries.textContent = String(s.recoveries);
        dom.stats.predictions.textContent = String(s.predictions);
        dom.stats.threat.textContent = s.threat;
        dom.stats.threat.className = s.threat === 'HIGH' ? 'risk-high' : (s.threat === 'MEDIUM' ? 'risk-medium' : 'risk-low');
    }

    function renderWorkerList() {
        var list = filteredWorkers();
        var key = list.map(function (w) { return [w.id, w.quality, w.lastSyncTs, state.selectedWorker === w.id ? 1 : 0].join('|'); }).join('~');
        if (diffCache.workerList !== key) {
            diffCache.workerList = key;
            dom.list.innerHTML = list.map(function (w) {
                var active = state.selectedWorker === w.id ? ' active' : '';
                var safeName = escapeHtml(w.name);
                var safeId = escapeHtml(w.id);
                var safeTenant = escapeHtml(w.tenantId || 'default');
                var safePrediction = escapeHtml(w.predictionState);
                var safePlatform = escapeHtml((w.platform || 'unknown').toUpperCase());
                return '<div class="worker-item' + active + '" data-worker-id="' + safeId + '">' +
                    '<div class="worker-row"><strong>' + safeName + '</strong><span class="pill ' + qualityClass(w.quality) + '">' + escapeHtml(w.quality) + '</span></div>' +
                    '<div class="small">' + safeId + ' · ' + safeTenant + ' · ' + safePlatform + '</div>' +
                    '<div class="small">Sync: ' + formatAgo(w.lastSyncTs) + ' · Pred: ' + safePrediction + '</div>' +
                    '</div>';
            }).join('');
        }
        var stampText = 'Updated ' + new Date().toLocaleTimeString();
        if (preferLiveApiOnly) {
            stampText += ' · S:' + liveSourceStats.sessions + ' G:' + liveSourceStats.gov + ' C:' + liveSourceStats.core + ' CR:' + liveSourceStats.coreRaw + ' L:' + liveSourceStats.legacy;
        }
        dom.stamp.textContent = stampText;
    }

    function renderMapMarkers() {
        initializeMapIfNeeded();
        if (!mapCtx.initialized || !mapCtx.map || !mapCtx.layer) return;
        var list = filteredWorkers();
        var activeIds = {};
        var bounds = [];

        list.forEach(function (w) {
            activeIds[w.id] = true;
            var marker = mapCtx.markers[w.id];
            var markerPrev = mapCtx.markerState[w.id];
            var html = '<div class="marker-dot ' + markerClass(w.quality) + '"></div>';
            if (!marker) {
                marker = L.marker([w.lat, w.lng], {
                    icon: L.divIcon({ className: '', html: html, iconSize: [14, 14], iconAnchor: [7, 7] })
                });
                marker.on('click', function () {
                    state.selectedWorker = w.id;
                    safeSetStorage(STORAGE_KEYS.selected, w.id);
                    scheduleRender();
                });
                marker.addTo(mapCtx.layer);
                mapCtx.markers[w.id] = marker;
                mapCtx.markerState[w.id] = { quality: w.quality, lat: w.lat, lng: w.lng };
            } else {
                var moved = !markerPrev || markerPrev.lat !== w.lat || markerPrev.lng !== w.lng;
                var qualityChanged = !markerPrev || markerPrev.quality !== w.quality;
                if (moved) {
                    marker.setLatLng([w.lat, w.lng]);
                }
                if (qualityChanged) {
                    marker.setIcon(L.divIcon({ className: '', html: html, iconSize: [14, 14], iconAnchor: [7, 7] }));
                }
                mapCtx.markerState[w.id] = { quality: w.quality, lat: w.lat, lng: w.lng };
            }
            marker.bindTooltip(escapeHtml(w.name) + ' · ' + escapeHtml(w.quality));
            bounds.push([w.lat, w.lng]);
        });

        Object.keys(mapCtx.markers).forEach(function (id) {
            if (activeIds[id]) return;
            mapCtx.layer.removeLayer(mapCtx.markers[id]);
            delete mapCtx.markers[id];
            delete mapCtx.markerState[id];
        });

        if (!state.ui.focusMode && bounds.length) {
            mapCtx.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });
        } else if (state.ui.focusMode && state.selectedWorker && mapCtx.markers[state.selectedWorker]) {
            mapCtx.map.panTo(mapCtx.markers[state.selectedWorker].getLatLng());
        }
    }

    function renderWorkerDetails() {
        var worker = state.workers.find(function (w) { return w.id === state.selectedWorker; }) || null;
        var key = worker ? JSON.stringify({
            id: worker.id,
            q: worker.quality,
            p: worker.predictionState,
            r: worker.recoveryState,
            l: worker.lastUpdateTs,
            u: worker.profileUpdatedAt || 0
        }) : 'none';
        if (diffCache.selectedDetails === key) return;
        diffCache.selectedDetails = key;

        if (!worker) {
            dom.details.innerHTML = '<div class="small">Select worker from map/list.</div>';
            return;
        }
        maybeFetchWorkerProfile(worker);

        var detailsRows = '';
        if (worker.profile && typeof worker.profile === 'object') {
            var skip = {
                id: true,
                worker_name: true,
                formatted_id: true,
                country: true
            };
            var keys = Object.keys(worker.profile).filter(function (k) {
                if (skip[k]) return false;
                var v = worker.profile[k];
                var t = typeof v;
                return t === 'string' || t === 'number' || t === 'boolean';
            }).slice(0, 24);
            detailsRows = keys.map(function (k) {
                return '<div class="box"><div class="small">' + escapeHtml(k.replace(/_/g, ' ')) + '</div><strong>' + escapeHtml(String(worker.profile[k])) + '</strong></div>';
            }).join('');
        }

        dom.details.innerHTML =
            '<div class="details-grid">' +
            '<div class="box"><div class="small">Name / ID</div><strong>' + escapeHtml(worker.name) + ' · ' + escapeHtml(worker.id) + '</strong></div>' +
            '<div class="box"><div class="small">Formatted ID</div><strong>' + escapeHtml(worker.formattedId || '-') + '</strong></div>' +
            '<div class="box"><div class="small">Tenant</div><strong>' + escapeHtml(worker.tenantId || 'default') + '</strong></div>' +
            '<div class="box"><div class="small">Country</div><strong>' + escapeHtml(worker.country || '-') + '</strong></div>' +
            '<div class="box"><div class="small">Platform</div><strong>' + escapeHtml((worker.platform || 'unknown').toUpperCase()) + '</strong></div>' +
            '<div class="box"><div class="small">Session status</div><strong>' + escapeHtml(worker.sessionStatus || '-') + '</strong></div>' +
            '<div class="box"><div class="small">Last update</div><strong>' + formatAgo(worker.lastUpdateTs) + '</strong></div>' +
            '<div class="box"><div class="small">Speed</div><strong>' + escapeHtml(worker.speed ? String(worker.speed) : '-') + '</strong></div>' +
            '<div class="box"><div class="small">Battery</div><strong>' + escapeHtml(worker.battery ? String(worker.battery) + '%' : '-') + '</strong></div>' +
            '<div class="box"><div class="small">Source</div><strong>' + escapeHtml(worker.source || '-') + '</strong></div>' +
            '<div class="box"><div class="small">Tracking quality</div><strong><span class="pill ' + qualityClass(worker.quality) + '">' + escapeHtml(worker.quality) + '</span></strong></div>' +
            '<div class="box"><div class="small">Prediction state</div><strong>' + escapeHtml(worker.predictionState) + '</strong></div>' +
            '<div class="box"><div class="small">Recovery state</div><strong>' + escapeHtml(worker.recoveryState) + '</strong></div>' +
            '<div class="box"><div class="small">Confidence reasons</div><strong>' + worker.confidenceReasons.map(function (r) { return escapeHtml(r); }).join(' · ') + '</strong></div>' +
            detailsRows +
            '</div>';
    }

    function renderAlerts() {
        var key = state.alerts.map(function (a) { return [a.id, a.severity].join('|'); }).join('~');
        if (diffCache.alerts === key) return;
        var wasAtBottom = dom.alerts.scrollTop + dom.alerts.clientHeight >= dom.alerts.scrollHeight - 20;
        diffCache.alerts = key;

        if (!state.alerts.length) {
            dom.alerts.innerHTML = '<li class="alert-item"><span class="sev sev-info">INFO</span><span>No telemetry alerts yet.</span><span class="small">-</span></li>';
            return;
        }

        dom.alerts.innerHTML = state.alerts.map(function (a) {
            var sevClass = a.severity === 'CRITICAL' ? 'sev-critical' : (a.severity === 'WARNING' ? 'sev-warning' : 'sev-info');
            var isFresh = Date.now() - a.t < 12000 ? ' new' : '';
            var safeSeverity = escapeHtml(a.severity);
            var safeMessage = escapeHtml(a.message);
            return '<li class="alert-item' + isFresh + '">' +
                '<span class="sev ' + sevClass + '">' + safeSeverity + '</span>' +
                '<span>' + safeMessage + '</span>' +
                '<span class="small">' + new Date(a.t).toLocaleTimeString() + '</span>' +
                '</li>';
        }).join('');

        if (wasAtBottom) {
            dom.alerts.scrollTop = dom.alerts.scrollHeight;
        }
    }

    function bindEvents() {
        dom.search.addEventListener('input', function () {
            state.ui.search = dom.search.value || '';
            scheduleRender();
        });
        dom.filter.addEventListener('change', function () {
            state.ui.filter = dom.filter.value || 'ALL';
            scheduleRender();
        });
        if (dom.platformAll) {
            dom.platformAll.addEventListener('click', function () {
                setPlatformFilter('ALL');
            });
        }
        if (dom.platformAndroid) {
            dom.platformAndroid.addEventListener('click', function () {
                setPlatformFilter('ANDROID');
            });
        }
        if (dom.platformIOS) {
            dom.platformIOS.addEventListener('click', function () {
                setPlatformFilter('IOS');
            });
        }
        dom.focus.addEventListener('click', function () {
            toggleFocusMode();
        });
        dom.list.addEventListener('click', function (ev) {
            var row = ev.target.closest('[data-worker-id]');
            if (!row) return;
            var id = row.getAttribute('data-worker-id');
            if (!id) return;
            state.selectedWorker = id;
            safeSetStorage(STORAGE_KEYS.selected, id);
            scheduleRender();
        });
    }

    function setPlatformFilter(platform) {
        state.ui.platform = String(platform || 'ALL').toUpperCase();
        if (state.ui.platform !== 'ALL' && state.ui.platform !== 'ANDROID' && state.ui.platform !== 'IOS') {
            state.ui.platform = 'ALL';
        }
        syncPlatformButtons();
        scheduleRender();
    }

    function toggleFocusMode() {
        state.ui.focusMode = !state.ui.focusMode;
        safeSetStorage(STORAGE_KEYS.focus, state.ui.focusMode ? '1' : '0');
        if (dom.focus) {
            dom.focus.textContent = state.ui.focusMode ? 'Focus: ON' : 'Focus: OFF';
            dom.focus.classList.toggle('focus-on', state.ui.focusMode);
        }
        scheduleRender();
    }

    function syncPlatformButtons() {
        if (!dom.platformAll || !dom.platformAndroid || !dom.platformIOS) return;
        dom.platformAll.classList.toggle('platform-on', state.ui.platform === 'ALL');
        dom.platformAndroid.classList.toggle('platform-on', state.ui.platform === 'ANDROID');
        dom.platformIOS.classList.toggle('platform-on', state.ui.platform === 'IOS');
    }

    function renderFrame() {
        renderQueued = false;
        try {
            renderStats();
            renderWorkerList();
            renderMapMarkers();
            renderWorkerDetails();
            renderAlerts();
        } catch (e) {
            // Keep dashboard interactive even if one renderer fails.
        }
    }

    function scheduleRender() {
        if (renderQueued) return;
        renderQueued = true;
        window.requestAnimationFrame(renderFrame);
    }

    function refreshLoop() {
        fetchLiveSnapshot();
        syncState();
        scheduleRender();
    }

    function init() {
        if (booted) return;
        hydrateDomRefs();
        if (!dom.list || !dom.search || !dom.filter || !dom.focus || !dom.details || !dom.alerts) {
            if (initAttempts < 30) {
                initAttempts += 1;
                window.setTimeout(init, 100);
            }
            return;
        }
        booted = true;
        preferLiveApiOnly = !!document.getElementById('control-config');
        bindEvents();
        dom.search.value = state.ui.search;
        dom.filter.value = state.ui.filter;
        syncPlatformButtons();
        dom.focus.textContent = state.ui.focusMode ? 'Focus: ON' : 'Focus: OFF';
        dom.focus.classList.toggle('focus-on', state.ui.focusMode);
        refreshLoop();
        window.setInterval(refreshLoop, 5000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.SOCDashboardControls = {
        setPlatform: setPlatformFilter,
        toggleFocus: toggleFocusMode
    };
})();
