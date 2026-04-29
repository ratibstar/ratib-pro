(function () {
    var STORAGE_KEY = 'worker_tracking_mobile_config_v2';
    var STATE_KEY = 'worker_tracking_mobile_state_v2';
    var DB_NAME = 'workerTrackerDB';
    var DB_VERSION = 1;
    var STORE_QUEUE = 'locationQueue';
    var STORE_META = 'meta';
    var MAX_BATCH = 100;

    var cfg = loadConfig();
    var state = loadState();
    var watchId = null;
    var scanner = null;
    var flushTimer = null;
    var lastPoint = null;
    var lastSentTs = state.lastSentTs || 0;
    var deferredInstallPrompt = null;
    var isSending = false;
    var netStatus = { label: '🔴 Offline', color: '#ef4444', quality: 'offline' };
    var healthTimer = null;
    var syncUiTimer = null;
    var heartbeatTimer = null;
    var weakTrackingTimer = null;
    var fallbackSyncTimer = null;
    var hiddenSinceTs = document.visibilityState === 'hidden' ? Date.now() : 0;
    var lastHiddenQueuedTs = 0;
    var hiddenGpsStopTimer = null;
    var isIosSafari = /iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    var nativeGeoWatchId = null;
    var backgroundGeoWatcherId = null;
    var uiHeartbeatTimer = null;
    var previousQueueCount = 0;
    var feedbackTimer = null;
    var debugTapCount = 0;
    var debugTapTimer = null;
    var debugHoldTimer = null;
    var confidenceExpanded = false;
    var debugUnlocked = false;
    var latestConfidenceReasons = [];
    var latestConfidenceRaw = { last_sync_ms: null, queue_count: 0, gps_accuracy: null };
    var lastTrackingQualityState = '';
    var lastPermissionState = 'unknown';
    var lastPermissionCheckTs = 0;
    var permissionCheckInFlight = false;
    var qualityTooltipEl = null;
    var TELEMETRY_KEY = 'worker_tracking_local_telemetry_v1';
    var TRACKING_LOCK_KEY = 'worker_tracking_lock_mode_v1';
    var telemetry = null;
    var telemetryDirty = false;
    var lastTelemetryPersistTs = 0;
    var lastGpsWeakTelemetryTs = 0;
    var lastSyncDelayTelemetryTs = 0;
    var lastPredictionTelemetryKind = 'NONE';
    var trackingLockMode = false;
    var settingsReturnValidationPending = false;
    var settingsReturnAwaitHidden = false;
    var settingsValidationBaseline = null;
    var lastSettingsValidationFeedbackTs = 0;
    var lastPredictionToastTs = 0;
    var lastPredictionKind = 'NONE';
    var predictionCoolDownMs = 150000;
    var lastPredictionUiTs = 0;
    var currentFlushIntervalMs = 0;
    var watchdogLastRestartAt = 0;
    var watchdogQueueSnapshot = -1;
    var watchdogQueueStagnantSince = 0;
    var watchdogArmedAt = 0;
    var EngineRegistry = [];
    var latestEngineResults = {
        ConfidenceEngine: null,
        PredictionEngine: null,
        RecoveryEngine: null
    };
    var INSTALL_REMINDER_KEY = 'worker_tracking_install_reminder_day_v1';
    var recoveryState = {
        boostUntil: 0,
        windowStartedAt: 0,
        attemptsInWindow: 0,
        cooldownUntil: 0,
        lastAttemptTs: 0,
        pending: false,
        lastSuccessToastTs: 0,
        lastFailureToastTs: 0,
        lastGpsRecoveryTs: 0
    };
    var MetricsStore = (function () {
        var data = {
            lastSyncTs: 0,
            queueCount: 0,
            gpsAccuracy: null,
            batteryLevel: null,
            isVisible: document.visibilityState !== 'hidden',
            isOnline: !!navigator.onLine,
            hiddenSinceMs: 0,
            realGpsAgeMs: 999999,
            predictionWindow: [],
            recoveryState: recoveryState,
            predictionState: 'NONE',
            confidenceScore: 100
        };
        return {
            get: function () { return data; },
            snapshot: function () {
                return Object.freeze({
                    lastSyncTs: data.lastSyncTs,
                    queueCount: data.queueCount,
                    gpsAccuracy: data.gpsAccuracy,
                    batteryLevel: data.batteryLevel,
                    isVisible: data.isVisible,
                    isOnline: data.isOnline,
                    hiddenSinceMs: data.hiddenSinceMs,
                    realGpsAgeMs: data.realGpsAgeMs,
                    predictionWindow: data.predictionWindow,
                    recoveryState: data.recoveryState,
                    predictionState: data.predictionState,
                    confidenceScore: data.confidenceScore
                });
            },
            patch: function (next) {
                if (!next || typeof next !== 'object') return data;
                Object.keys(next).forEach(function (k) { data[k] = next[k]; });
                return data;
            }
        };
    })();
    function getMetrics() { return MetricsStore.get(); }
    function patchMetrics(next) { return MetricsStore.patch(next); }
    function getRecoveryState() { return getMetrics().recoveryState || recoveryState; }
    function getBatteryLevel() { return getMetrics().batteryLevel; }
    function registerEngine(engine) {
        if (!engine || !engine.name || typeof engine.evaluate !== 'function') return;
        EngineRegistry.push(engine);
    }
    var ConfidenceEngine = {
        name: 'ConfidenceEngine',
        evaluate: function (metrics) {
            var minsSinceSync = metrics.lastSyncTs ? ((Date.now() - metrics.lastSyncTs) / 60000) : 999;
            var queueSize = metrics.queueCount || 0;
            var acc = typeof metrics.gpsAccuracy === 'number' ? metrics.gpsAccuracy : 999;
            var battery = metrics.batteryLevel;
            var score = 100;
            if (minsSinceSync > 2) score -= 25;
            if (minsSinceSync > 5) score -= 30;
            if (queueSize > 30) score -= 20;
            if (queueSize > 80) score -= 20;
            if (acc > 30) score -= 15;
            if (acc > 60) score -= 15;
            return {
                score: Math.max(0, Math.min(100, score)),
                reasons: buildConfidenceReasons(minsSinceSync, queueSize, acc, battery).slice(0, 3)
            };
        }
    };
    var PredictionEngine = {
        name: 'PredictionEngine',
        evaluate: function (metrics) {
            return evaluatePrediction(metrics);
        }
    };
    var RecoveryEngine = {
        name: 'RecoveryEngine',
        evaluate: function (metrics) {
            var syncStale = !metrics.lastSyncTs || (Date.now() - metrics.lastSyncTs) > 60000;
            var queueHigh = (metrics.queueCount || 0) > 50;
            var actions = [];
            var batteryCritical = metrics.batteryLevel != null && metrics.batteryLevel < 15;
            if ((syncStale || queueHigh) && !batteryCritical) {
                actions.push({ type: 'SYNC_BOOST', reason: syncStale ? 'stale_sync' : 'queue_high', durationMs: 15000 });
            }
            actions.push({ type: 'GPS_RECOVERY_CHECK' });
            if (metrics.predictionResult && metrics.predictionResult.shouldAct) {
                metrics.predictionResult.softActions.forEach(function (a) { actions.push(a); });
            }
            return { actions: actions };
        },
        sideEffects: function (result, metrics) {
            if (!result || !Array.isArray(result.actions)) return;
            result.actions.forEach(function (a) {
                if (!a || !a.type) return;
                if (a.type === 'SYNC_BOOST') {
                    startRecoveryBoost(a.reason, a.durationMs || 15000);
                    return;
                }
                if (a.type === 'GPS_RECOVERY_CHECK') {
                    autoRecoverGpsIfNeeded();
                    return;
                }
                if (a.type === 'PRE_FLUSH') {
                    flushQueueNow();
                    return;
                }
                if (a.type === 'PRE_GPS_CHECK') {
                    autoRecoverGpsIfNeeded();
                    return;
                }
                if (a.type === 'PRE_HEARTBEAT') {
                    queueHeartbeatFromLastKnown().then(function () {
                        flushQueueNow();
                    });
                }
            });
        }
    };
    registerEngine(PredictionEngine);
    registerEngine(RecoveryEngine);
    registerEngine(ConfidenceEngine);
    var dbPromise = initDb();
    telemetry = loadTelemetry();
    trackingLockMode = localStorage.getItem(TRACKING_LOCK_KEY) === '1';

    var el = {
        btnStartScan: byId('btnStartScan'),
        btnStopScan: byId('btnStopScan'),
        onboardInput: byId('onboardInput'),
        btnApplyOnboard: byId('btnApplyOnboard'),
        qrReader: byId('qrReader'),
        cfgApiUrl: byId('cfgApiUrl'),
        cfgWorkerId: byId('cfgWorkerId'),
        cfgTenantId: byId('cfgTenantId'),
        cfgDeviceId: byId('cfgDeviceId'),
        cfgApiToken: byId('cfgApiToken'),
        btnSaveConfig: byId('btnSaveConfig'),
        btnClearConfig: byId('btnClearConfig'),
        cfgStatus: byId('cfgStatus'),
        btnStartTracking: byId('btnStartTracking'),
        btnStopTracking: byId('btnStopTracking'),
        btnFlushNow: byId('btnFlushNow'),
        btnResetDevice: byId('btnResetDevice'),
        advancedControls: byId('advancedControls'),
        autoStartToggle: byId('autoStartToggle'),
        trackingLockToggle: byId('trackingLockToggle'),
        trackingState: byId('trackingState'),
        netState: byId('netState'),
        queueCount: byId('queueCount'),
        lastSentAt: byId('lastSentAt'),
        lastSyncAt: byId('lastSyncAt'),
        lastAccuracy: byId('lastAccuracy'),
        batteryLevel: byId('batteryLevel'),
        btnSOS: byId('btnSOS'),
        sosStatus: byId('sosStatus'),
        logBox: byId('logBox'),
        offlineModePill: byId('offlineModePill'),
        btnInstallApp: byId('btnInstallApp'),
        btnDismissInstall: byId('btnDismissInstall'),
        installReminder: byId('installReminder'),
        quickStatus: byId('quickStatus'),
        silentModePill: byId('silentModePill'),
        onboardingSection: byId('onboardingSection'),
        debugSection: byId('debugSection'),
        threatWrap: byId('threatWrap'),
        threatLevel: byId('threatLevel'),
        responseWrap: byId('responseWrap'),
        responseState: byId('responseState'),
        trackingWarning: byId('trackingWarning'),
        predictionHint: byId('predictionHint'),
        lastUpdateAgo: byId('lastUpdateAgo'),
        lastSuccessText: byId('lastSuccessText'),
        trackingQuality: byId('trackingQuality'),
        trackingConfidence: byId('trackingConfidence'),
        microFeedback: byId('microFeedback'),
        confidenceCard: byId('confidenceCard'),
        confidencePanel: byId('confidencePanel'),
        confidenceReasons: byId('confidenceReasons'),
        confidenceRaw: byId('confidenceRaw'),
        debugPrediction: byId('debugPrediction'),
        debugRecovery: byId('debugRecovery')
    };

    wireEvents();
    registerServiceWorker();
    initBattery();
    applyOnboardingLink();
    syncUi();
    refreshQueueCount();
    tryAutofillOnboardFromClipboard();
    if (isConfigReady() && state.autoStart !== false && !state.isTracking) {
        startTracking();
    } else if (state.isTracking) {
        resetWatchdogTrackingState();
        startTrackingInternal();
    }
    scheduleHealthChecks();
    updateNetState();
    scheduleHeartbeat();
    scheduleWeakTrackingWatchdog();
    scheduleFallbackSync();
    scheduleUiHeartbeat();
    checkAndroidBatteryOptimizationSilently();
    maybeShowOemBatteryGuidanceOnce();
    showInstallReminderOncePerDay();

    function byId(id) { return document.getElementById(id); }

    function getCapacitorGeolocationPlugin() {
        try {
            if (!window.Capacitor || !window.Capacitor.Plugins) return null;
            return window.Capacitor.Plugins.Geolocation || null;
        } catch (e) {
            return null;
        }
    }

    function getBackgroundGeolocationPlugin() {
        try {
            if (!window.Capacitor || !window.Capacitor.Plugins) return null;
            return window.Capacitor.Plugins.BackgroundGeolocation || null;
        } catch (e) {
            return null;
        }
    }

    var BATTERY_OPT_PROMPT_KEY = 'worker_tracking_battery_opt_prompted_v1';
    var OEM_GUIDE_PREFIX = 'worker_tracking_oem_guide_seen_';
    var SETTINGS_VALIDATION_FEEDBACK_COOLDOWN_MS = 60000;

    function getCapacitorPlatform() {
        try {
            if (!window.Capacitor || typeof window.Capacitor.getPlatform !== 'function') return null;
            return window.Capacitor.getPlatform();
        } catch (e) {
            return null;
        }
    }

    function getTrackingForegroundPlugin() {
        try {
            if (!window.Capacitor || !window.Capacitor.Plugins) return null;
            return window.Capacitor.Plugins.TrackingForeground || null;
        } catch (e) {
            return null;
        }
    }

    function nativeForegroundTrackingStart() {
        if (getCapacitorPlatform() !== 'android') return;
        var p = getTrackingForegroundPlugin();
        if (!p || typeof p.start !== 'function') return;
        p.start().catch(function () {});
    }

    function nativeForegroundTrackingStop() {
        if (getCapacitorPlatform() !== 'android') return;
        var p = getTrackingForegroundPlugin();
        if (!p || typeof p.stop !== 'function') return;
        p.stop().catch(function () {});
    }

    function maybePromptAndroidBattery() {
        if (getCapacitorPlatform() !== 'android') return;
        if (localStorage.getItem(BATTERY_OPT_PROMPT_KEY) === '1') return;
        var p = getTrackingForegroundPlugin();
        if (!p || typeof p.getBatteryStatus !== 'function') return;
        p.getBatteryStatus().then(function (res) {
            if (res && res.isIgnoringBatteryOptimizations) {
                localStorage.setItem(BATTERY_OPT_PROMPT_KEY, '1');
                return;
            }
            if (!window.confirm('For reliable tracking, allow this app to run without battery restrictions. Open settings?')) {
                localStorage.setItem(BATTERY_OPT_PROMPT_KEY, '1');
                return;
            }
            localStorage.setItem(BATTERY_OPT_PROMPT_KEY, '1');
            if (p.openAppBatterySettings && typeof p.openAppBatterySettings === 'function') {
                p.openAppBatterySettings().catch(function () {});
            }
        }).catch(function () {});
    }

    function checkAndroidBatteryOptimizationSilently() {
        if (getCapacitorPlatform() !== 'android') return;
        var p = getTrackingForegroundPlugin();
        if (!p || typeof p.getBatteryStatus !== 'function') return;
        p.getBatteryStatus().catch(function () {});
    }

    function normalizeOemManufacturerName(raw) {
        var m = String(raw || '').toLowerCase();
        if (m.indexOf('xiaomi') !== -1 || m.indexOf('redmi') !== -1 || m.indexOf('poco') !== -1) return 'xiaomi';
        if (m.indexOf('huawei') !== -1 || m.indexOf('honor') !== -1) return 'huawei';
        if (m.indexOf('oppo') !== -1 || m.indexOf('realme') !== -1 || m.indexOf('oneplus') !== -1) return 'oppo';
        if (m.indexOf('samsung') !== -1) return 'samsung';
        return '';
    }

    function oemBatteryGuidanceText(manufacturer) {
        if (manufacturer === 'xiaomi') {
            return 'Xiaomi:\n- فعّل Auto-start\n- عطّل Battery saver لهذا التطبيق';
        }
        if (manufacturer === 'huawei') {
            return 'Huawei:\n- اجعل التطبيق Protected داخل إعدادات البطارية';
        }
        if (manufacturer === 'oppo') {
            return 'Oppo:\n- فعّل Background activity لهذا التطبيق';
        }
        if (manufacturer === 'samsung') {
            return 'Samsung:\n- استثنِ التطبيق من Sleeping apps / battery restrictions';
        }
        return '';
    }

    function showOemGuidanceModal(message) {
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.inset = '0';
            overlay.style.background = 'rgba(15,23,42,0.6)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '10000';
            overlay.dir = 'rtl';

            var card = document.createElement('div');
            card.style.width = 'min(92vw, 360px)';
            card.style.background = '#ffffff';
            card.style.borderRadius = '14px';
            card.style.boxShadow = '0 16px 40px rgba(2,6,23,0.25)';
            card.style.padding = '16px';
            card.style.fontFamily = 'inherit';

            var title = document.createElement('h3');
            title.textContent = 'تحسين التتبع';
            title.style.margin = '0 0 10px 0';
            title.style.fontSize = '18px';
            title.style.color = '#0f172a';

            var desc = document.createElement('p');
            desc.textContent = message;
            desc.style.whiteSpace = 'pre-line';
            desc.style.margin = '0 0 14px 0';
            desc.style.color = '#334155';
            desc.style.fontSize = '14px';
            desc.style.lineHeight = '1.6';

            var actions = document.createElement('div');
            actions.style.display = 'flex';
            actions.style.gap = '8px';
            actions.style.justifyContent = 'flex-end';

            var laterBtn = document.createElement('button');
            laterBtn.type = 'button';
            laterBtn.textContent = 'لاحقًا';
            laterBtn.style.border = '1px solid #cbd5e1';
            laterBtn.style.background = '#ffffff';
            laterBtn.style.color = '#334155';
            laterBtn.style.borderRadius = '10px';
            laterBtn.style.padding = '8px 12px';

            var settingsBtn = document.createElement('button');
            settingsBtn.type = 'button';
            settingsBtn.textContent = 'فتح الإعدادات';
            settingsBtn.style.border = '0';
            settingsBtn.style.background = '#2563eb';
            settingsBtn.style.color = '#ffffff';
            settingsBtn.style.borderRadius = '10px';
            settingsBtn.style.padding = '8px 12px';

            var done = false;
            function close(result) {
                if (done) return;
                done = true;
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                resolve(result);
            }

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) close(false);
            });
            laterBtn.addEventListener('click', function () { close(false); });
            settingsBtn.addEventListener('click', function () { close(true); });

            actions.appendChild(laterBtn);
            actions.appendChild(settingsBtn);
            card.appendChild(title);
            card.appendChild(desc);
            card.appendChild(actions);
            overlay.appendChild(card);
            document.body.appendChild(overlay);
        });
    }

    function openOemSettingsByManufacturer(manufacturer) {
        var p = getTrackingForegroundPlugin();
        if (!p) return;
        settingsReturnAwaitHidden = true;
        settingsReturnValidationPending = true;
        settingsValidationBaseline = {
            queueCount: (getMetrics().queueCount || previousQueueCount || 0),
            lastSyncTs: state.lastSyncTs || 0,
            lastRealGpsTs: state.lastRealGpsTs || 0,
            requestedAt: Date.now()
        };
        var openPromise = null;
        if ((manufacturer === 'xiaomi' || manufacturer === 'huawei' || manufacturer === 'oppo') && typeof p.openAutoStartSettings === 'function') {
            openPromise = p.openAutoStartSettings();
        } else if (typeof p.openBatteryOptimizationSettings === 'function') {
            openPromise = p.openBatteryOptimizationSettings();
        } else if (typeof p.openAppSettings === 'function') {
            openPromise = p.openAppSettings();
        } else if (typeof p.openAppBatterySettings === 'function') {
            openPromise = p.openAppBatterySettings();
        }
        if (openPromise && typeof openPromise.catch === 'function') {
            openPromise.catch(function () {
                if (p.openAppSettings && typeof p.openAppSettings === 'function') {
                    p.openAppSettings().catch(function () {});
                } else if (p.openAppBatterySettings && typeof p.openAppBatterySettings === 'function') {
                    p.openAppBatterySettings().catch(function () {});
                }
            });
        }
    }

    function maybeShowSettingsReturnFeedback(success) {
        var now = Date.now();
        if ((now - lastSettingsValidationFeedbackTs) < SETTINGS_VALIDATION_FEEDBACK_COOLDOWN_MS) return;
        lastSettingsValidationFeedbackTs = now;
        if (success) {
            showMicroFeedback('✅ تم تحسين إعدادات التتبع');
        } else {
            showMicroFeedback('⚠️ تأكد من تعطيل قيود البطارية للتتبع المستمر');
        }
    }

    function validatePostSettingsReturn() {
        if (!settingsReturnValidationPending) return;
        settingsReturnValidationPending = false;
        var baseline = settingsValidationBaseline || {
            queueCount: 0,
            lastSyncTs: 0,
            lastRealGpsTs: 0,
            requestedAt: Date.now()
        };
        syncMetricsStore();
        var m = getMetrics();
        var currentQueue = (m.queueCount || previousQueueCount || 0);
        var queueMoved = currentQueue !== (baseline.queueCount || 0);
        var syncImproved = (state.lastSyncTs || 0) > (baseline.lastSyncTs || 0);
        var gpsImproved = false;
        if (state.lastRealGpsTs && state.lastRealGpsTs > (baseline.lastRealGpsTs || 0)) {
            gpsImproved = true;
        } else if (m.realGpsAgeMs && m.realGpsAgeMs <= 120000) {
            gpsImproved = true;
        }
        var localImprovement = queueMoved || syncImproved || gpsImproved;
        var plugin = getTrackingForegroundPlugin();
        if (!plugin || typeof plugin.getBatteryStatus !== 'function') {
            maybeShowSettingsReturnFeedback(localImprovement);
            return;
        }
        plugin.getBatteryStatus().then(function (res) {
            var batteryImproved = !!(res && res.isIgnoringBatteryOptimizations);
            maybeShowSettingsReturnFeedback(localImprovement || batteryImproved);
        }).catch(function () {
            maybeShowSettingsReturnFeedback(localImprovement);
        });
    }

    function maybeShowOemBatteryGuidanceOnce() {
        if (getCapacitorPlatform() !== 'android') return;
        var p = getTrackingForegroundPlugin();
        if (!p || typeof p.getDeviceManufacturer !== 'function') return;
        p.getDeviceManufacturer().then(function (res) {
            var manufacturer = normalizeOemManufacturerName(res && res.manufacturer);
            if (!manufacturer) return;
            var seenKey = OEM_GUIDE_PREFIX + manufacturer;
            if (localStorage.getItem(seenKey) === '1') return;
            var message = oemBatteryGuidanceText(manufacturer);
            if (!message) return;
            localStorage.setItem(seenKey, '1');
            showOemGuidanceModal(message).then(function (openSettings) {
                if (!openSettings) return;
                openOemSettingsByManufacturer(manufacturer);
            });
        }).catch(function () {});
    }

    function canUseBrowserGeolocation() {
        return !!(navigator.geolocation && typeof navigator.geolocation.watchPosition === 'function');
    }

    async function requestGeoPermissionGracefully() {
        var plugin = getCapacitorGeolocationPlugin();
        if (!plugin || typeof plugin.requestPermissions !== 'function') return;
        try {
            await plugin.requestPermissions();
        } catch (e) {}
    }

    function normalizeCapacitorPosition(pos) {
        if (!pos || !pos.coords) return null;
        return {
            coords: {
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
                speed: pos.coords.speed
            },
            timestamp: pos.timestamp || Date.now()
        };
    }

    async function getCurrentGeoPosition(options) {
        if (canUseBrowserGeolocation()) {
            return new Promise(function (resolve, reject) {
                navigator.geolocation.getCurrentPosition(resolve, reject, options);
            });
        }
        var plugin = getCapacitorGeolocationPlugin();
        if (!plugin || typeof plugin.getCurrentPosition !== 'function') {
            throw new Error('Geolocation unavailable');
        }
        var p = await plugin.getCurrentPosition({
            enableHighAccuracy: !!(options && options.enableHighAccuracy),
            timeout: options && options.timeout ? options.timeout : 15000,
            maximumAge: options && options.maximumAge ? options.maximumAge : 0
        });
        var normalized = normalizeCapacitorPosition(p);
        if (!normalized) throw new Error('Invalid geolocation payload');
        return normalized;
    }

    async function startGeoWatch(onSuccess, onError, options) {
        if (canUseBrowserGeolocation()) {
            watchId = navigator.geolocation.watchPosition(onSuccess, onError, options);
            nativeGeoWatchId = null;
            return;
        }
        var plugin = getCapacitorGeolocationPlugin();
        if (!plugin || typeof plugin.watchPosition !== 'function') {
            throw new Error('Geolocation unavailable');
        }
        nativeGeoWatchId = await plugin.watchPosition({
            enableHighAccuracy: !!(options && options.enableHighAccuracy),
            timeout: options && options.timeout ? options.timeout : 20000,
            maximumAge: options && options.maximumAge ? options.maximumAge : 10000
        }, function (position, err) {
            if (err) {
                onError(err);
                return;
            }
            var normalized = normalizeCapacitorPosition(position);
            if (!normalized) return;
            onSuccess(normalized);
        });
        watchId = null;
    }

    async function stopGeoWatch() {
        if (watchId != null && navigator.geolocation && typeof navigator.geolocation.clearWatch === 'function') {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        if (nativeGeoWatchId != null) {
            var plugin = getCapacitorGeolocationPlugin();
            if (plugin && typeof plugin.clearWatch === 'function') {
                try {
                    await plugin.clearWatch({ id: nativeGeoWatchId });
                } catch (e) {}
            }
            nativeGeoWatchId = null;
        }
    }

    function ingestGeoPosition(pos) {
        if (!pos || !pos.coords) return;
        if (document.visibilityState === 'hidden' && hiddenSinceTs > 0 && (Date.now() - hiddenSinceTs) > (8 * 60 * 1000)) {
            if ((Date.now() - lastHiddenQueuedTs) < 180000) {
                return;
            }
            lastHiddenQueuedTs = Date.now();
        }
        var b = getBatteryLevel();
        var point = {
            id: createPointId(pos),
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
            speed: typeof pos.coords.speed === 'number' ? pos.coords.speed : null,
            battery: b != null ? Math.round(b) : null,
            timestamp: new Date(pos.timestamp || Date.now()).toISOString(),
            source: 'gps',
            status: 'location'
        };
        point.is_estimated = false;
        state.lastRealGpsTs = Date.now();
        saveState();
        patchMetrics({
            realGpsAgeMs: 0,
            gpsAccuracy: typeof point.accuracy === 'number' ? point.accuracy : getMetrics().gpsAccuracy
        });
        if (point.accuracy != null && el.lastAccuracy) {
            el.lastAccuracy.textContent = Math.round(point.accuracy) + 'm';
        }
        enqueuePoint(point).then(function () {
            refreshQueueCount();
            maybeAutoFlush(point);
        });
        showMicroFeedback('📍 Updated');
        markRecoverySuccess();
    }

    async function startBackgroundGeoWatcher() {
        var plugin = getBackgroundGeolocationPlugin();
        if (!plugin || typeof plugin.addWatcher !== 'function') return;
        if (backgroundGeoWatcherId) return;
        try {
            backgroundGeoWatcherId = await plugin.addWatcher({
                backgroundMessage: 'Tracking active',
                backgroundTitle: 'Tracking active',
                requestPermissions: true,
                stale: false,
                distanceFilter: 15
            }, function (location, error) {
                if (error) {
                    log('Background GPS error: ' + (error.message || error.code || 'unknown'));
                    return;
                }
                if (!location) return;
                ingestGeoPosition({
                    coords: {
                        latitude: location.latitude,
                        longitude: location.longitude,
                        accuracy: location.accuracy,
                        speed: location.speed
                    },
                    timestamp: location.time || Date.now()
                });
            });
            log('Background tracking watcher started.');
        } catch (e) {
            log('Background watcher failed: ' + (e && e.message ? e.message : 'unknown'));
        }
    }

    async function stopBackgroundGeoWatcher() {
        var plugin = getBackgroundGeolocationPlugin();
        if (!plugin || typeof plugin.removeWatcher !== 'function') return;
        if (!backgroundGeoWatcherId) return;
        try {
            await plugin.removeWatcher({ id: backgroundGeoWatcherId });
        } catch (e) {}
        backgroundGeoWatcherId = null;
        log('Background tracking watcher stopped.');
    }

    function log(msg) {
        var line = '[' + new Date().toLocaleTimeString() + '] ' + msg;
        if (el.logBox) {
            el.logBox.textContent = line + '\n' + el.logBox.textContent;
            el.logBox.textContent = el.logBox.textContent.slice(0, 7000);
        }
        console.log('[WorkerMobile]', msg);
    }

    function loadConfig() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveConfig(next) {
        cfg = next || {};
        localStorage.setItem(STORAGE_KEY, JSON.stringify(cfg));
    }

    function clearConfig() {
        cfg = {};
        localStorage.removeItem(STORAGE_KEY);
    }

    function defaultTelemetry() {
        return {
            v: 1,
            updated_at: 0,
            counters: {
                recovery_count: 0,
                prediction_triggers: 0,
                gps_weak_events: 0,
                sync_delays: 0
            },
            events: []
        };
    }

    function loadTelemetry() {
        try {
            var raw = localStorage.getItem(TELEMETRY_KEY);
            if (!raw) return defaultTelemetry();
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return defaultTelemetry();
            var base = defaultTelemetry();
            base.updated_at = parsed.updated_at || 0;
            base.counters = Object.assign(base.counters, parsed.counters || {});
            base.events = Array.isArray(parsed.events) ? parsed.events.slice(-120) : [];
            return base;
        } catch (e) {
            return defaultTelemetry();
        }
    }

    function persistTelemetry(force) {
        if (!telemetry || !telemetryDirty) return;
        var now = Date.now();
        if (!force && (now - lastTelemetryPersistTs) < 15000) return;
        telemetry.updated_at = now;
        if (Array.isArray(telemetry.events) && telemetry.events.length > 120) {
            telemetry.events = telemetry.events.slice(-120);
        }
        try {
            localStorage.setItem(TELEMETRY_KEY, JSON.stringify(telemetry));
            telemetryDirty = false;
            lastTelemetryPersistTs = now;
        } catch (e) {
            // Keep in-memory data and retry later.
        }
    }

    function bumpTelemetryCounter(counterKey, meta) {
        if (!telemetry) telemetry = defaultTelemetry();
        if (!telemetry.counters) telemetry.counters = defaultTelemetry().counters;
        if (typeof telemetry.counters[counterKey] !== 'number') telemetry.counters[counterKey] = 0;
        telemetry.counters[counterKey] += 1;
        if (!Array.isArray(telemetry.events)) telemetry.events = [];
        telemetry.events.push({
            t: Date.now(),
            k: counterKey,
            m: meta || null
        });
        telemetryDirty = true;
        persistTelemetry(false);
    }

    function notePredictionTrigger(kind) {
        if (!kind || kind === 'NONE') {
            lastPredictionTelemetryKind = 'NONE';
            return;
        }
        if (lastPredictionTelemetryKind !== kind) {
            bumpTelemetryCounter('prediction_triggers', { risk: kind });
            lastPredictionTelemetryKind = kind;
        }
    }

    function loadState() {
        try {
            var raw = localStorage.getItem(STATE_KEY);
            return raw ? JSON.parse(raw) : { isTracking: false, lastSentTs: 0, lastSyncTs: 0, autoStart: true };
        } catch (e) {
            return { isTracking: false, lastSentTs: 0, lastSyncTs: 0, autoStart: true };
        }
    }

    function saveState() {
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    }

    function applyOnboardingLink() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            var onboardRaw = (params.get('onboard') || '').trim();
            if (!onboardRaw) return;
            var parsed = parseOnboardPayload(onboardRaw);
            if (!parsed || !parsed.api_url || !parsed.worker_id || !parsed.device_id || !parsed.api_token) {
                log('Onboarding link payload invalid.');
                return;
            }
            saveConfig({
                api_url: String(parsed.api_url).replace(/\/$/, ''),
                worker_id: parseInt(parsed.worker_id, 10),
                tenant_id: parseInt(parsed.tenant_id || '0', 10) || null,
                device_id: String(parsed.device_id),
                api_token: String(parsed.api_token)
            });
            log('Onboarding applied from URL.');
            params.delete('onboard');
            var clean = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            window.history.replaceState({}, '', clean);
            if (!state.isTracking) {
                startTracking();
            }
        } catch (e) {
            log('Onboarding URL parse failed.');
        }
    }

    function parseOnboardPayload(raw) {
        try {
            var b64 = raw.replace(/-/g, '+').replace(/_/g, '/');
            while (b64.length % 4 !== 0) b64 += '=';
            var json = atob(b64);
            return JSON.parse(json);
        } catch (e) {
            try { return JSON.parse(raw); } catch (e2) { return null; }
        }
    }

    function isConfigReady() {
        return !!(cfg.api_url && cfg.worker_id && cfg.device_id && cfg.api_token && cfg.tenant_id);
    }

    function syncUiSoon() {
        if (syncUiTimer) return;
        syncUiTimer = setTimeout(function () {
            syncUiTimer = null;
            syncUi();
        }, 100);
    }

    function syncUi() {
        var m = getMetrics();
        if (el.cfgApiUrl) el.cfgApiUrl.value = cfg.api_url || '';
        if (el.cfgWorkerId) el.cfgWorkerId.value = cfg.worker_id || '';
        if (el.cfgTenantId) el.cfgTenantId.value = cfg.tenant_id || '';
        if (el.cfgDeviceId) el.cfgDeviceId.value = cfg.device_id || '';
        if (el.cfgApiToken) el.cfgApiToken.value = cfg.api_token || '';
        if (el.autoStartToggle) el.autoStartToggle.checked = state.autoStart !== false;
        if (el.trackingLockToggle) el.trackingLockToggle.checked = !!trackingLockMode;

        if (el.cfgStatus) {
            el.cfgStatus.textContent = isConfigReady() ? 'Status: Ready' : 'Status: Not ready';
            el.cfgStatus.style.borderColor = isConfigReady() ? '#166534' : '#7f1d1d';
        }

        if (el.lastSentAt) el.lastSentAt.textContent = lastSentTs ? new Date(lastSentTs).toLocaleTimeString() : '-';
        if (el.lastSyncAt) el.lastSyncAt.textContent = state.lastSyncTs ? new Date(state.lastSyncTs).toLocaleTimeString() : '-';
        if (el.batteryLevel) el.batteryLevel.textContent = m.batteryLevel == null ? 'N/A' : (Math.round(m.batteryLevel) + '%');
        if (el.trackingState) {
            el.trackingState.textContent = state.isTracking ? 'ON' : 'OFF';
            el.trackingState.classList.toggle('tracking-on', !!state.isTracking);
            el.trackingState.classList.toggle('tracking-off', !state.isTracking);
        }
        if (el.quickStatus) {
            el.quickStatus.textContent = state.isTracking ? '🟢 Live now' : '🔴 Stopped';
            el.quickStatus.classList.toggle('status-on', !!state.isTracking);
            el.quickStatus.classList.toggle('status-off', !state.isTracking);
        }
        if (el.offlineModePill) el.offlineModePill.textContent = 'Offline Mode: ' + (navigator.onLine ? 'Off' : 'On');
        if (el.netState) {
            el.netState.textContent = netStatus.label;
            el.netState.style.color = netStatus.color;
        }
        if (el.silentModePill) {
            var hiddenMode = document.visibilityState === 'hidden';
            el.silentModePill.textContent = hiddenMode ? '🛰️ Silent Mode: ACTIVE' : '🟢 Live Tracking';
            el.silentModePill.classList.toggle('silent-active', hiddenMode);
            el.silentModePill.classList.toggle('silent-live', !hiddenMode);
        }
        if (el.onboardingSection) el.onboardingSection.classList.toggle('hidden', isConfigReady());

        if (el.btnStartTracking) el.btnStartTracking.disabled = state.isTracking || !isConfigReady();
        if (el.btnStopTracking) {
            var stopLocked = !!trackingLockMode && !!state.isTracking;
            el.btnStopTracking.disabled = !state.isTracking || stopLocked;
            el.btnStopTracking.title = stopLocked ? 'لا يمكن إيقاف التتبع حالياً' : '';
        }
        if (el.btnFlushNow) el.btnFlushNow.disabled = !isConfigReady();
        if (el.lastSuccessText) {
            if (!state.lastSyncTs) {
                el.lastSuccessText.textContent = '-';
            } else {
                var secsAgo = Math.max(0, Math.floor((Date.now() - state.lastSyncTs) / 1000));
                el.lastSuccessText.textContent = secsAgo + ' seconds ago';
            }
        }
    }

    async function refreshQueueCount() {
        var oldQ = getMetrics().queueCount || 0;
        var c = await queueCount();
        if (el.queueCount) {
            el.queueCount.textContent = String(c) + ' points';
        }
        if (c > 0 && c < oldQ) {
            showMicroFeedback('⬇ Syncing...');
        } else if (c > 0 && !navigator.onLine) {
            showMicroFeedback('📦 Saving...');
        }
        previousQueueCount = c;
        patchMetrics({ queueCount: c });
    }

    function wireEvents() {
        if (el.btnSaveConfig) {
            el.btnSaveConfig.addEventListener('click', function () {
                var next = {
                    api_url: (el.cfgApiUrl.value || '').trim().replace(/\/$/, ''),
                    worker_id: parseInt(el.cfgWorkerId.value || '0', 10),
                    tenant_id: parseInt(el.cfgTenantId.value || '0', 10) || null,
                    device_id: (el.cfgDeviceId.value || '').trim(),
                    api_token: (el.cfgApiToken.value || '').trim()
                };
                if (!next.api_url || !next.worker_id || !next.tenant_id || !next.device_id || !next.api_token) {
                    log('Manual config is incomplete.');
                    return;
                }
                saveConfig(next);
                syncUiSoon();
                log('Config saved.');
            });
        }
        if (el.btnClearConfig) {
            el.btnClearConfig.addEventListener('click', function () {
                clearConfig();
                state.isTracking = false;
                saveState();
                stopTracking();
                syncUiSoon();
                log('Config cleared.');
            });
        }
        if (el.btnStartScan) el.btnStartScan.addEventListener('click', startQrScan);
        if (el.btnStopScan) el.btnStopScan.addEventListener('click', stopQrScan);
        if (el.btnApplyOnboard) {
            el.btnApplyOnboard.addEventListener('click', function () {
                var raw = (el.onboardInput && el.onboardInput.value) ? String(el.onboardInput.value).trim() : '';
                if (!raw) {
                    showMicroFeedback('Paste onboarding URL/code first');
                    return;
                }
                tryApplyQr(raw);
            });
        }
        if (el.btnStartTracking) el.btnStartTracking.addEventListener('click', startTracking);
        if (el.btnStopTracking) {
            el.btnStopTracking.addEventListener('click', function () {
                if (trackingLockMode && state.isTracking) {
                    showMicroFeedback('لا يمكن إيقاف التتبع حالياً');
                    return;
                }
                stopTracking();
            });
        }
        if (el.btnFlushNow) {
            el.btnFlushNow.addEventListener('click', manualSyncNow);
        }
        if (el.btnSOS) el.btnSOS.addEventListener('click', sendSOS);
        if (el.btnResetDevice) {
            el.btnResetDevice.addEventListener('click', function () {
                stopTracking();
                clearConfig();
                state = { isTracking: false, lastSentTs: 0, lastSyncTs: 0, autoStart: true };
                lastSentTs = 0;
                saveState();
                setSosStatus('');
                syncUiSoon();
                log('Device reset completed.');
            });
        }
        if (el.autoStartToggle) {
            el.autoStartToggle.addEventListener('change', function () {
                state.autoStart = !!el.autoStartToggle.checked;
                saveState();
                syncUiSoon();
            });
        }
        if (el.trackingLockToggle) {
            el.trackingLockToggle.addEventListener('change', function () {
                trackingLockMode = !!el.trackingLockToggle.checked;
                localStorage.setItem(TRACKING_LOCK_KEY, trackingLockMode ? '1' : '0');
                showMicroFeedback(trackingLockMode ? '🔒 Tracking Lock Mode enabled' : '🔓 Tracking Lock Mode disabled');
                syncUiSoon();
            });
        }
        if (el.confidenceCard) {
            el.confidenceCard.addEventListener('click', toggleConfidencePanel);
            el.confidenceCard.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    toggleConfidencePanel();
                }
            });
        }
        if (el.trackingQuality) {
            el.trackingQuality.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                toggleTrackingQualityTooltip();
            });
        }
        document.addEventListener('click', function (ev) {
            if (!qualityTooltipEl) return;
            var t = ev.target;
            if (t === el.trackingQuality || qualityTooltipEl.contains(t)) return;
            hideTrackingQualityTooltip();
        });
        window.addEventListener('beforeunload', function (e) {
            if (!(trackingLockMode && state.isTracking)) return;
            var msg = 'لا يمكن إيقاف التتبع حالياً';
            e.preventDefault();
            e.returnValue = msg;
            showMicroFeedback(msg);
            return msg;
        });
        var titleNode = document.querySelector('h1');
        if (titleNode) {
            titleNode.addEventListener('click', function () {
                debugTapCount += 1;
                if (debugTapTimer) clearTimeout(debugTapTimer);
                debugTapTimer = setTimeout(function () { debugTapCount = 0; }, 1400);
                if (debugTapCount >= 5) {
                    debugTapCount = 0;
                    revealDebugPanel();
                }
            });
            titleNode.addEventListener('touchstart', function () {
                if (debugHoldTimer) clearTimeout(debugHoldTimer);
                debugHoldTimer = setTimeout(revealDebugPanel, 1200);
            }, { passive: true });
            titleNode.addEventListener('touchend', function () {
                if (debugHoldTimer) clearTimeout(debugHoldTimer);
            }, { passive: true });
        }
        if (el.btnInstallApp) {
            el.btnInstallApp.addEventListener('click', function () {
                if (!deferredInstallPrompt) return;
                deferredInstallPrompt.prompt();
                deferredInstallPrompt.userChoice.finally(function () {
                    deferredInstallPrompt = null;
                    el.btnInstallApp.classList.add('hidden');
                });
            });
        }
        if (el.btnDismissInstall) {
            el.btnDismissInstall.addEventListener('click', function () {
                if (el.installReminder) el.installReminder.classList.add('hidden');
                try {
                    localStorage.setItem(INSTALL_REMINDER_KEY, (new Date()).toISOString().slice(0, 10));
                } catch (e) {}
            });
        }

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredInstallPrompt = e;
            if (el.btnInstallApp) el.btnInstallApp.classList.remove('hidden');
        });

        window.addEventListener('online', function () {
            updateNetState();
            scheduleBackgroundSync();
            startRecoveryBoost('online', 18000);
            requestImmediatePositionAndSync();
            flushQueueNow();
            notifyRecoverySuccess();
        });
        window.addEventListener('offline', updateNetState);
        if (navigator.connection && typeof navigator.connection.addEventListener === 'function') {
            navigator.connection.addEventListener('change', function () {
                updateNetState();
                if (navigator.onLine) flushQueueNow();
            });
        }
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', function (event) {
                var data = event && event.data ? event.data : {};
                if (data && data.type === 'SW_SYNC_REQUEST') {
                    flushQueueNow();
                }
            });
        }
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                hiddenSinceTs = Date.now();
                persistTelemetry(true);
                if (settingsReturnAwaitHidden) {
                    settingsReturnAwaitHidden = false;
                }
                scheduleHiddenGpsStop();
            } else {
                hiddenSinceTs = 0;
                if (hiddenGpsStopTimer) {
                    clearTimeout(hiddenGpsStopTimer);
                    hiddenGpsStopTimer = null;
                }
                if (state.isTracking && watchId == null) {
                    startTrackingInternal();
                }
                startRecoveryBoost('visible', 12000);
                requestImmediatePositionAndSync();
                if (!settingsReturnAwaitHidden && settingsReturnValidationPending) {
                    validatePostSettingsReturn();
                }
                syncUiSoon();
            }
            resetFlushTimer();
        });
    }

    function scheduleHealthChecks() {
        if (healthTimer) clearInterval(healthTimer);
        healthTimer = setInterval(updateNetState, 20000);
    }

    function scheduleFallbackSync() {
        if (fallbackSyncTimer) clearInterval(fallbackSyncTimer);
        fallbackSyncTimer = setInterval(function () {
            try {
                if (navigator.onLine) {
                    flushQueueNow();
                }
            } catch (e) {
                log('Fallback sync error: ' + (e && e.message ? e.message : 'unknown'));
            }
        }, 60000);
    }

    function syncMetricsStore() {
        var m = getMetrics();
        var currentWindow = m.predictionWindow || [];
        var trendSample = {
            t: Date.now(),
            syncAgeMs: state.lastSyncTs ? (Date.now() - state.lastSyncTs) : 999999,
            queue: m.queueCount || previousQueueCount || 0,
            gpsAcc: typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : 999,
            hidden: document.visibilityState === 'hidden' ? 1 : 0,
            realGpsAgeMs: m.realGpsAgeMs || 999999
        };
        currentWindow.push(trendSample);
        var cutoff = Date.now() - (5 * 60 * 1000);
        while (currentWindow.length > 0 && currentWindow[0].t < cutoff) {
            currentWindow.shift();
        }
        patchMetrics({
            lastSyncTs: state.lastSyncTs || 0,
            queueCount: m.queueCount || previousQueueCount || 0,
            gpsAccuracy: typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : null,
            batteryLevel: m.batteryLevel,
            isVisible: document.visibilityState !== 'hidden',
            isOnline: !!navigator.onLine,
            hiddenSinceMs: hiddenSinceTs > 0 ? (Date.now() - hiddenSinceTs) : 0,
            realGpsAgeMs: state.lastRealGpsTs ? (Date.now() - state.lastRealGpsTs) : 999999,
            predictionWindow: currentWindow,
            recoveryState: getRecoveryState(),
            predictionState: lastPredictionKind || 'NONE'
        });
    }

    function heartbeatOrchestratorTick() {
        if (!state.isTracking || !isConfigReady()) return;
        syncMetricsStore();
        var metrics = MetricsStore.snapshot();
        var results = {};
        EngineRegistry.forEach(function (engine) {
            if (!engine || typeof engine.evaluate !== 'function') return;
            var inputMetrics = metrics;
            if (engine.name === 'RecoveryEngine') {
                inputMetrics = Object.assign({}, metrics, { predictionResult: results.PredictionEngine || null });
            }
            results[engine.name] = engine.evaluate(inputMetrics);
        });
        latestEngineResults = results;
        if (results.PredictionEngine) {
            MetricsStore.patch({ predictionState: results.PredictionEngine.risk || 'NONE' });
        }
        EngineRegistry.forEach(function (engine) {
            if (!engine || typeof engine.sideEffects !== 'function') return;
            var result = results[engine.name];
            engine.sideEffects(result, metrics);
        });
        if (results.PredictionEngine) {
            applyPredictionUiAndState(results.PredictionEngine, metrics);
        }
        if (results.ConfidenceEngine) {
            MetricsStore.patch({ confidenceScore: results.ConfidenceEngine.score });
            updateConfidenceUi(metrics.queueCount, results.ConfidenceEngine);
        }
        resetFlushTimer();
    }

    function isBatteryCriticalRecoveryMode() {
        var b = getBatteryLevel();
        return b != null && b < 15;
    }

    function inRecoveryBoost() {
        var rs = getRecoveryState();
        return !isBatteryCriticalRecoveryMode() && Date.now() < rs.boostUntil;
    }

    function getTargetFlushIntervalMs() {
        if (inRecoveryBoost()) return 12000;
        if (document.hidden) return 120000;
        return 30000;
    }

    function resetFlushTimer() {
        if (!state.isTracking) return;
        var nextMs = getTargetFlushIntervalMs();
        if (flushTimer && currentFlushIntervalMs === nextMs) return;
        if (flushTimer) clearInterval(flushTimer);
        currentFlushIntervalMs = nextMs;
        flushTimer = setInterval(flushQueueNow, nextMs);
    }

    function canStartRecoveryAttempt() {
        var now = Date.now();
        var rs = getRecoveryState();
        if (isBatteryCriticalRecoveryMode()) return false;
        if (rs.lastAttemptTs > 0 && (now - rs.lastAttemptTs) < 90000) return false;
        if (now < rs.cooldownUntil) return false;
        if (!rs.windowStartedAt || (now - rs.windowStartedAt) > 120000) {
            rs.windowStartedAt = now;
            rs.attemptsInWindow = 0;
        }
        if (rs.attemptsInWindow >= 2) {
            rs.cooldownUntil = now + 90000;
            return false;
        }
        rs.attemptsInWindow += 1;
        rs.lastAttemptTs = now;
        rs.pending = true;
        return true;
    }

    function startRecoveryBoost(reason, durationMs) {
        if (!canStartRecoveryAttempt()) return false;
        var rs = getRecoveryState();
        rs.boostUntil = Math.max(rs.boostUntil, Date.now() + (durationMs || 15000));
        resetFlushTimer();
        flushQueueNow();
        bumpTelemetryCounter('recovery_count', { reason: reason || 'unknown' });
        log('Auto recovery boost started: ' + reason);
        return true;
    }

    function markRecoverySuccess() {
        var rs = getRecoveryState();
        if (!rs.pending) return;
        rs.pending = false;
        rs.attemptsInWindow = 0;
        rs.windowStartedAt = Date.now();
        notifyRecoverySuccess();
    }

    function markRecoveryFailed() {
        var rs = getRecoveryState();
        if (!rs.pending) return;
        notifyRecoveryFailure();
        rs.pending = false;
    }

    function notifyRecoverySuccess() {
        var rs = getRecoveryState();
        if ((Date.now() - rs.lastSuccessToastTs) > 60000) {
            rs.lastSuccessToastTs = Date.now();
            showMicroFeedback('Tracking improved');
        }
    }

    function notifyRecoveryFailure() {
        var rs = getRecoveryState();
        if (!navigator.onLine) return;
        if ((Date.now() - rs.lastFailureToastTs) > 60000) {
            rs.lastFailureToastTs = Date.now();
            showMicroFeedback('Sync issue detected - app will retry automatically');
        }
    }

    function autoRecoverGpsIfNeeded() {
        if (!state.isTracking || !isConfigReady()) return;
        if (isBatteryCriticalRecoveryMode()) return;
        var m = getMetrics();
        var rs = getRecoveryState();
        var now = Date.now();
        var gpsStale = !m.realGpsAgeMs || m.realGpsAgeMs > 60000;
        var gpsWeak = (typeof m.gpsAccuracy === 'number' && m.gpsAccuracy > 60);
        if (gpsWeak && (now - lastGpsWeakTelemetryTs) > 60000) {
            lastGpsWeakTelemetryTs = now;
            bumpTelemetryCounter('gps_weak_events', { accuracy: Math.round(m.gpsAccuracy || 0) });
        }
        if (!gpsStale && !gpsWeak) return;
        if ((now - rs.lastGpsRecoveryTs) < 60000) return;
        if (!canStartRecoveryAttempt()) return;
        rs.lastGpsRecoveryTs = now;
        requestImmediatePositionAndSync({
            retries: 2,
            onFailure: function () {
                patchMetrics({ gpsAccuracy: 999 });
                markRecoveryFailed();
            }
        });
    }

    function runAutoRecoveryCheck() { heartbeatOrchestratorTick(); }

    function isIncreasing(series, pickFn) {
        if (series.length < 3) return false;
        var a = pickFn(series[series.length - 3]);
        var b = pickFn(series[series.length - 2]);
        var c = pickFn(series[series.length - 1]);
        return a < b && b < c;
    }

    function evaluatePrediction(metrics) {
        var trend = metrics.predictionWindow || [];
        if (trend.length < 3) {
            return { risk: 'NONE', shouldAct: false, softActions: [], hiddenRisk: false, shouldWarn: false };
        }
        var now = Date.now();
        var recent = trend.slice(-5);
        var prediction = 'NONE';
        var confidence = 0;
        var hiddenRisk = false;

        if (isIncreasing(recent, function (s) { return s.syncAgeMs; }) && recent[recent.length - 1].syncAgeMs > 45000) {
            prediction = 'SYNC_RISK';
            confidence = 0.72;
        } else if (isIncreasing(recent, function (s) { return s.queue; }) && recent[recent.length - 1].queue > 20) {
            prediction = 'QUEUE_RISK';
            confidence = 0.74;
        } else if (isIncreasing(recent, function (s) { return s.gpsAcc; }) && recent[recent.length - 1].gpsAcc > 35) {
            prediction = 'GPS_RISK';
            confidence = 0.7;
        } else {
            var hiddenLong = (metrics.hiddenSinceMs || 0) > (3 * 60 * 1000);
            var noRealGps = (metrics.realGpsAgeMs || 999999) > 90000;
            if (hiddenLong && noRealGps) {
                prediction = 'SYNC_RISK';
                confidence = 0.68;
                hiddenRisk = true;
            }
        }
        var shouldWarn = confidence >= 0.66 && prediction !== 'NONE';
        var softActions = [];
        if (hiddenRisk) {
            softActions.push({ type: 'PRE_HEARTBEAT' });
        } else if (prediction === 'SYNC_RISK') {
            softActions.push({ type: 'PRE_FLUSH' });
        } else if (prediction === 'QUEUE_RISK') {
            softActions.push({ type: 'SYNC_BOOST', reason: 'pred_queue_risk', durationMs: 10000 });
        } else if (prediction === 'GPS_RISK') {
            softActions.push({ type: 'PRE_GPS_CHECK' });
        }
        return {
            risk: prediction,
            shouldAct: prediction !== 'NONE',
            softActions: softActions,
            hiddenRisk: hiddenRisk,
            shouldWarn: shouldWarn
        };
    }

    function setPredictionState(kind) {
        lastPredictionKind = kind;
        notePredictionTrigger(kind);
        if (el.debugPrediction) {
            el.debugPrediction.textContent = 'Prediction: ' + kind;
        }
        updateDebugRecoveryState();
    }

    function applyPredictionUiAndState(predictionResult) {
        if (!predictionResult) {
            setPredictionState('NONE');
            if (el.predictionHint) el.predictionHint.classList.add('hidden');
            return;
        }
        var risk = predictionResult.risk || 'NONE';
        setPredictionState(risk);
        if (el.predictionHint && predictionResult.shouldWarn && (Date.now() - lastPredictionToastTs) > predictionCoolDownMs) {
            if (el.predictionHint) el.predictionHint.classList.remove('hidden');
            showMicroFeedback('⚠️ Tracking may weaken soon');
            lastPredictionToastTs = Date.now();
            lastPredictionUiTs = Date.now();
        } else if (risk === 'NONE' && el.predictionHint) {
            el.predictionHint.classList.add('hidden');
        } else if (el.predictionHint && (Date.now() - lastPredictionUiTs) > predictionCoolDownMs) {
            el.predictionHint.classList.add('hidden');
        }
    }

    async function updateNetState() {
        if (!navigator.onLine) {
            netStatus = { label: '🔴 Offline', color: '#ef4444', quality: 'offline' };
            syncUiSoon();
            return;
        }
        if (!isConfigReady()) {
            netStatus = { label: '🟡 Setup needed', color: '#f59e0b', quality: 'setup' };
            syncUiSoon();
            return;
        }
        var health = await pingServer();
        netStatus = health;
        syncUiSoon();
    }

    async function pingServer() {
        // Avoid noisy 404 probes in production variants; track reachability by browser network state.
        if (!navigator.onLine) {
            return { label: '🔴 Offline', color: '#ef4444', quality: 'offline' };
        }
        return { label: '🟢 Online', color: '#22c55e', quality: 'ok' };
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.register('/mobile-app/sw.js?v=16')
            .then(function () {
                log('Service worker registered.');
                scheduleBackgroundSync();
            })
            .catch(function (err) {
                log('Service worker register failed: ' + err.message);
            });
    }

    function scheduleBackgroundSync() {
        if (isIosSafari) return;
        if (!('serviceWorker' in navigator) || !('SyncManager' in window)) return;
        navigator.serviceWorker.ready.then(function (registration) {
            return registration.sync.register('worker-location-sync');
        }).catch(function () {
            // fallback handled by normal timers
        });
    }

    function scheduleHiddenGpsStop() {
        if (hiddenGpsStopTimer) clearTimeout(hiddenGpsStopTimer);
        hiddenGpsStopTimer = setTimeout(function () {
            if (!state.isTracking) return;
            if (document.visibilityState !== 'hidden') return;
            if (watchId != null || nativeGeoWatchId != null) {
                stopGeoWatch();
                log('Hidden mode: active GPS paused to save battery.');
            }
        }, 4 * 60 * 1000);
    }

    function startQrScan() {
        if (!window.Html5Qrcode) {
            log('QR scanner library not loaded.');
            showMicroFeedback('QR scanner unavailable on this device');
            return;
        }
        if (scanner) return;
        el.qrReader.classList.remove('hidden');
        scanner = new Html5Qrcode('qrReader');
        var config = { fps: 10, qrbox: { width: 240, height: 240 } };
        scanner.start(
            { facingMode: 'environment' },
            config,
            function (decodedText) { tryApplyQr(decodedText); },
            function () {}
        ).then(function () {
            el.btnStartScan.disabled = true;
            el.btnStopScan.disabled = false;
            log('QR scanner started.');
        }).catch(function (err) {
            log('QR start failed: ' + err);
            showMicroFeedback('Camera blocked. Paste onboarding code instead.');
            stopQrScan();
        });
    }

    function stopQrScan() {
        if (!scanner) return;
        scanner.stop().then(function () {
            scanner.clear();
            scanner = null;
            el.qrReader.classList.add('hidden');
            el.btnStartScan.disabled = false;
            el.btnStopScan.disabled = true;
            log('QR scanner stopped.');
        }).catch(function () {
            scanner = null;
            el.qrReader.classList.add('hidden');
            el.btnStartScan.disabled = false;
            el.btnStopScan.disabled = true;
        });
    }

    function tryApplyQr(text) {
        if (!text) return;
        var parsed = null;
        try { parsed = JSON.parse(text); } catch (e) {
            try {
                var asUrl = new URL(text);
                var onboardFromUrl = asUrl.searchParams.get('onboard');
                if (onboardFromUrl) parsed = parseOnboardPayload(onboardFromUrl);
            } catch (e2) {}
            if (!parsed) {
                showMicroFeedback('Invalid onboarding code');
                return;
            }
        }
        if (!parsed || !parsed.api_url || !parsed.worker_id || !parsed.device_id || !parsed.api_token) {
            showMicroFeedback('Invalid onboarding data');
            return;
        }

        saveConfig({
            api_url: String(parsed.api_url).replace(/\/$/, ''),
            worker_id: parseInt(parsed.worker_id, 10),
            tenant_id: parseInt(parsed.tenant_id || '0', 10) || null,
            device_id: String(parsed.device_id),
            api_token: String(parsed.api_token)
        });
        syncUiSoon();
        if (el.onboardInput) el.onboardInput.value = '';
        showMicroFeedback('Setup complete');
        log('QR onboarding complete.');
        stopQrScan();
        startTracking();
    }

    function looksLikeOnboardPayload(raw) {
        if (!raw) return false;
        var t = String(raw || '').trim();
        if (!t) return false;
        if (t.indexOf('onboard=') !== -1) return true;
        if (t.indexOf('{') === 0 && t.indexOf('api_url') !== -1 && t.indexOf('api_token') !== -1) return true;
        if (t.length > 40 && /^[A-Za-z0-9\-_]+=*$/.test(t) && t.indexOf('.') === -1) return true;
        return false;
    }

    function tryAutofillOnboardFromClipboard() {
        if (isConfigReady()) return;
        if (!navigator.clipboard || typeof navigator.clipboard.readText !== 'function') return;
        // Clipboard access usually requires secure context + user gesture on some browsers.
        navigator.clipboard.readText().then(function (clip) {
            var txt = (clip || '').trim();
            if (!looksLikeOnboardPayload(txt)) return;
            if (el.onboardInput && !el.onboardInput.value) {
                el.onboardInput.value = txt;
            }
            showMicroFeedback('Onboarding code detected from clipboard');
            tryApplyQr(txt);
        }).catch(function () {
            // Silent fail; manual paste/scan remains available.
        });
    }

    function startTracking() {
        if (!isConfigReady()) {
            log('Setup missing. Please scan the onboarding QR code.');
            showMicroFeedback('Please scan QR first');
            return;
        }
        requestGeoPermissionGracefully();
        state.isTracking = true;
        saveState();
        resetWatchdogTrackingState();
        startTrackingInternal();
        startBackgroundGeoWatcher();
        nativeForegroundTrackingStart();
        maybePromptAndroidBattery();
        syncUiSoon();
    }

    function startTrackingInternal() {
        if (!navigator.geolocation && !getCapacitorGeolocationPlugin()) {
            log('Geolocation not supported.');
            return;
        }
        if (watchId != null || nativeGeoWatchId != null) return;

        startGeoWatch(
            function (pos) {
                ingestGeoPosition(pos);
            },
            function (err) {
                log('GPS error: ' + err.message);
                queueEstimatedPointFromCache();
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 10000 }
        ).catch(function (err) {
            log('GPS watch start failed: ' + (err && err.message ? err.message : 'unknown'));
        });

        resetFlushTimer();
        log('Tracking started.');
    }

    function stopTracking() {
        state.isTracking = false;
        saveState();
        resetWatchdogTrackingState();
        stopGeoWatch();
        stopBackgroundGeoWatcher();
        if (flushTimer) {
            clearInterval(flushTimer);
            flushTimer = null;
            currentFlushIntervalMs = 0;
        }
        nativeForegroundTrackingStop();
        syncUiSoon();
        log('Tracking stopped.');
    }

    function detectMovement(curr) {
        if (!lastPoint) {
            lastPoint = curr;
            return 'moving';
        }
        var d = haversineKm(lastPoint.lat, lastPoint.lng, curr.lat, curr.lng);
        lastPoint = curr;
        if ((curr.speed != null && curr.speed > 1.2) || d > 0.02) return 'moving';
        return 'idle';
    }

    function currentSendIntervalSecs(curr) {
        var movement = detectMovement(curr);
        var base = movement === 'moving' ? 10 : 30;
        if (document.visibilityState === 'hidden') base = Math.max(base, 60);
        if (document.visibilityState === 'hidden' && hiddenSinceTs > 0 && (Date.now() - hiddenSinceTs) > (5 * 60 * 1000)) base = Math.max(base, 180);
        var b = getBatteryLevel();
        if (b != null && b < 20) base = Math.max(base, 180);
        return base;
    }

    function maybeAutoFlush(currPoint) {
        var now = Date.now();
        var minSecs = currentSendIntervalSecs(currPoint);
        if ((now - lastSentTs) >= minSecs * 1000) flushQueueNow();
    }

    function buildHeaders() {
        var headers = {
            'Content-Type': 'application/json',
            'X-Tracking-Token': cfg.api_token
        };
        if (cfg.tenant_id) headers['X-Tenant-ID'] = String(cfg.tenant_id);
        return headers;
    }

    async function flushQueueNow() {
        if (!isConfigReady() || !navigator.onLine || isSending) return false;
        var items = await readQueueBatch(MAX_BATCH);
        if (!items.length) return false;

        isSending = true;
        var url = cfg.api_url.replace(/\/$/, '') + '/update-location.php';
        var payload = items.length > 1
            ? {
                worker_id: cfg.worker_id,
                tenant_id: cfg.tenant_id || undefined,
                device_id: cfg.device_id,
                is_offline_batch: true,
                locations: items.map(mapPoint)
            }
            : Object.assign({
                worker_id: cfg.worker_id,
                tenant_id: cfg.tenant_id || undefined,
                device_id: cfg.device_id
            }, mapPoint(items[0]));

        try {
            var json = await sendWithRetry(url, payload, 3);
            await removeQueueItems(items.map(function (x) { return x.id; }));
            lastSentTs = Date.now();
            state.lastSentTs = lastSentTs;
            state.lastSyncTs = Date.now();
            saveState();
            syncUiSoon();
            refreshQueueCount();
            log('Location synced. Remaining queue updated.');
            showMicroFeedback('☁️ Synced');
            markRecoverySuccess();
            if (json && json.data) {
                if (json.data.threat_level && el.threatWrap && el.threatLevel) {
                    el.threatWrap.classList.remove('hidden');
                    el.threatLevel.textContent = String(json.data.threat_level);
                }
                if (json.data.response_action && el.responseWrap && el.responseState) {
                    el.responseWrap.classList.remove('hidden');
                    el.responseState.textContent = String(json.data.response_action);
                }
            }
            return true;
        } catch (err) {
            log('Sync failed: ' + normalizeSyncError(err && err.message ? err.message : 'Unknown error'));
            scheduleBackgroundSync();
            if (inRecoveryBoost()) {
                markRecoveryFailed();
            }
            return false;
        } finally {
            isSending = false;
        }
    }

    async function manualSyncNow() {
        if (!isConfigReady()) {
            showMicroFeedback('Please scan QR first');
            return;
        }
        if (!navigator.onLine) {
            showMicroFeedback('Offline now - data will sync automatically');
            return;
        }
        if (isSending) {
            showMicroFeedback('Sync already running...');
            return;
        }
        var pending = await queueCount();
        if (!pending) {
            showMicroFeedback('No pending data to sync');
            return;
        }
        showMicroFeedback('Syncing now...');
        var ok = await flushQueueNow();
        if (!ok) {
            showMicroFeedback('Sync failed - will retry automatically');
        }
    }

    async function sendWithRetry(url, payload, maxTry) {
        var b = getBatteryLevel();
        if (b != null && b < 20) {
            maxTry = Math.min(maxTry, 2);
        }
        var attempt = 0;
        var delay = 1000;
        while (attempt < maxTry) {
            attempt += 1;
            var res = await fetch(url, {
                method: 'POST',
                headers: buildHeaders(),
                body: JSON.stringify(payload)
            });
            var json = await safeJson(res);
            if (res.ok && json && json.success) return json;
            if (attempt >= maxTry) {
                throw new Error((json && (json.message || json.error)) || 'Tracking API rejected request');
            }
            await sleep(delay);
            delay *= 2;
            if (b != null && b < 20) {
                delay = Math.min(delay, 3000);
            }
        }
        throw new Error('Unexpected retry state');
    }

    async function sendSOS() {
        if (!isConfigReady()) {
            log('Config missing for SOS.');
            showMicroFeedback('Please scan QR first');
            return;
        }
        var p = await readLastPoint() || lastPoint;
        if (!p) {
            log('No location yet. Waiting for first GPS fix.');
            showMicroFeedback('Waiting for GPS fix...');
            return;
        }
        var url = cfg.api_url.replace(/\/$/, '') + '/sos.php';
        var body = {
            worker_id: cfg.worker_id,
            tenant_id: cfg.tenant_id || undefined,
            device_id: cfg.device_id,
            lat: p.lat,
            lng: p.lng,
            battery: p.battery,
            message: 'SOS triggered from worker mobile app'
        };
        setSosStatus('Sending emergency...');
        if (el.btnSOS) {
            el.btnSOS.disabled = true;
            el.btnSOS.textContent = '🆘 Sending...';
        }
        try {
            await sendWithRetry(url, body, 2);
            setSosStatus('✅ Emergency sent');
            log('SOS sent successfully.');
        } catch (e) {
            setSosStatus('❌ Failed to send - retrying');
            log('SOS error: ' + e.message);
            try {
                await sendWithRetry(url, body, 2);
                setSosStatus('✅ Emergency sent');
            } catch (e2) {
                setSosStatus('❌ Failed to send');
            }
        } finally {
            if (el.btnSOS) {
                el.btnSOS.disabled = false;
                el.btnSOS.textContent = '🆘 Emergency';
            }
        }
    }

    function setSosStatus(text) {
        if (el.sosStatus) el.sosStatus.textContent = text || '';
    }

    function normalizeSyncError(message) {
        var m = String(message || '').toLowerCase();
        if (m.indexOf('tenant') !== -1) return 'Server error (Tenant missing)';
        if (m.indexOf('token') !== -1 || m.indexOf('auth') !== -1 || m.indexOf('unregistered device') !== -1) return 'Server error (Auth failed)';
        if (m.indexOf('network') !== -1 || m.indexOf('fetch') !== -1 || m.indexOf('offline') !== -1) return 'Network error';
        return 'Server error (' + message + ')';
    }

    function scheduleHeartbeat() {
        if (heartbeatTimer) clearInterval(heartbeatTimer);
        heartbeatTimer = setInterval(function () {
            if (!state.isTracking || !isConfigReady()) return;
            var m = getMetrics();
            var minsSinceRealGps = (m.realGpsAgeMs || 999999) / 60000;
            var thresholdMins = isIosSafari ? 2 : 3;
            if (m.batteryLevel != null && m.batteryLevel < 20) {
                thresholdMins = Math.max(thresholdMins, 5);
            }
            if (minsSinceRealGps < thresholdMins) return;
            queueHeartbeatFromLastKnown().then(function () {
                flushQueueNow();
            });
        }, isIosSafari ? 120000 : 180000);
    }

    function scheduleWeakTrackingWatchdog() {
        if (weakTrackingTimer) clearInterval(weakTrackingTimer);
        weakTrackingTimer = setInterval(function () {
            if (!el.trackingWarning) return;
            if (!state.isTracking) {
                el.trackingWarning.classList.add('hidden');
                return;
            }
            if (!navigator.onLine) {
                el.trackingWarning.classList.remove('hidden');
                el.trackingWarning.textContent = '⚠️ Offline now - tracking is saved and will sync automatically';
                el.trackingWarning.style.color = '#fbbf24';
                return;
            }
            var lastOk = state.lastSyncTs || state.lastSentTs || 0;
            if (!lastOk) {
                el.trackingWarning.classList.remove('hidden');
                el.trackingWarning.textContent = '⚠️ Waiting for first successful sync';
                el.trackingWarning.style.color = '#fbbf24';
                return;
            }
            var driftMs = Date.now() - lastOk;
            if (driftMs > (10 * 60 * 1000)) {
                el.trackingWarning.classList.remove('hidden');
                el.trackingWarning.textContent = '🔴 Tracking stopped';
                el.trackingWarning.style.color = '#ef4444';
            } else if (driftMs > (5 * 60 * 1000)) {
                if ((Date.now() - lastSyncDelayTelemetryTs) > 120000) {
                    lastSyncDelayTelemetryTs = Date.now();
                    bumpTelemetryCounter('sync_delays', { drift_ms: Math.round(driftMs) });
                }
                el.trackingWarning.classList.remove('hidden');
                el.trackingWarning.textContent = '⚠️ Sync delayed - keep app open for better reliability';
                el.trackingWarning.style.color = '#fbbf24';
            } else {
                el.trackingWarning.classList.add('hidden');
            }
        }, 30000);
    }

    async function queueHeartbeatFromLastKnown() {
        var p = await readLastPoint() || lastPoint;
        if (!p || typeof p.lat !== 'number' || typeof p.lng !== 'number') return;
        var b = getBatteryLevel();
        var heartbeat = {
            id: 'hb_' + Date.now(),
            lat: p.lat,
            lng: p.lng,
            accuracy: p.accuracy || null,
            speed: 0,
            battery: b != null ? Math.round(b) : p.battery,
            timestamp: new Date().toISOString(),
            source: 'cached',
            status: 'heartbeat',
            is_estimated: true
        };
        await enqueuePoint(heartbeat);
        refreshQueueCount();
    }

    async function queueEstimatedPointFromCache() {
        var p = await readLastPoint();
        if (!p || typeof p.lat !== 'number' || typeof p.lng !== 'number') return;
        var b = getBatteryLevel();
        var estimated = {
            id: 'est_' + Date.now(),
            lat: p.lat,
            lng: p.lng,
            accuracy: p.accuracy || null,
            speed: 0,
            battery: b != null ? Math.round(b) : p.battery,
            timestamp: new Date().toISOString(),
            source: 'cached',
            status: 'location',
            is_estimated: true
        };
        await enqueuePoint(estimated);
        refreshQueueCount();
    }

    function requestImmediatePositionAndSync(options) {
        options = options || {};
        if (!state.isTracking) return;
        var retriesLeft = Math.max(0, parseInt(options.retries || 0, 10));
        var b = getBatteryLevel();
        var run = function () {
            getCurrentGeoPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }).then(function (pos) {
            var point = {
                id: createPointId(pos),
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
                speed: typeof pos.coords.speed === 'number' ? pos.coords.speed : null,
                battery: b != null ? Math.round(b) : null,
                timestamp: new Date(pos.timestamp || Date.now()).toISOString(),
                source: 'gps',
                status: 'location'
            };
            point.is_estimated = false;
            state.lastRealGpsTs = Date.now();
            saveState();
            patchMetrics({
                realGpsAgeMs: 0,
                gpsAccuracy: typeof point.accuracy === 'number' ? point.accuracy : getMetrics().gpsAccuracy
            });
            enqueuePoint(point).then(function () {
                refreshQueueCount();
                flushQueueNow();
            });
            markRecoverySuccess();
        }).catch(function () {
            if (retriesLeft > 0) {
                retriesLeft -= 1;
                setTimeout(run, 1200);
                return;
            }
            flushQueueNow();
            if (typeof options.onFailure === 'function') {
                options.onFailure();
            }
        });
        };
        run();
    }

    var WATCHDOG_STALE_MS = 90000;
    var WATCHDOG_COOLDOWN_MS = 120000;

    function resetWatchdogTrackingState() {
        watchdogQueueSnapshot = -1;
        watchdogQueueStagnantSince = Date.now();
        watchdogArmedAt = Date.now();
    }

    function runTrackingWatchdog() {
        if (!state.isTracking || !isConfigReady()) return;
        if (Date.now() - watchdogLastRestartAt < WATCHDOG_COOLDOWN_MS) return;
        var now = Date.now();
        if (now - watchdogArmedAt < WATCHDOG_STALE_MS) return;
        syncMetricsStore();
        var m = getMetrics();
        var q = typeof m.queueCount === 'number' ? m.queueCount : (previousQueueCount || 0);
        if (watchdogQueueSnapshot !== q) {
            watchdogQueueSnapshot = q;
            watchdogQueueStagnantSince = now;
        }
        var gpsAge = state.lastRealGpsTs ? (now - state.lastRealGpsTs) : 999999;
        var gpsStale = gpsAge > WATCHDOG_STALE_MS;
        var queueStuck = (q > 0) && ((now - watchdogQueueStagnantSince) > WATCHDOG_STALE_MS);
        if (!gpsStale && !queueStuck) return;
        if (debugUnlocked) {
            log('Watchdog: recover gpsAgeMs=' + Math.round(gpsAge)
                + ' queue=' + q
                + ' queueStagnantMs=' + (q > 0 ? Math.round(now - watchdogQueueStagnantSince) : 0)
                + ' lastSyncTs=' + (state.lastSyncTs || 0)
                + ' lastSentTs=' + (state.lastSentTs || 0));
        }
        watchdogLastRestartAt = now;
        stopTracking();
        startTracking();
    }

    async function resolveLocationPermissionState() {
        var now = Date.now();
        if ((now - lastPermissionCheckTs) < 30000 && lastPermissionState !== 'unknown') {
            return lastPermissionState;
        }
        if (permissionCheckInFlight) {
            return lastPermissionState;
        }
        permissionCheckInFlight = true;
        try {
            if (navigator.permissions && typeof navigator.permissions.query === 'function') {
                try {
                    var status = await navigator.permissions.query({ name: 'geolocation' });
                    if (status && status.state) {
                        lastPermissionState = status.state === 'granted'
                            ? 'granted'
                            : (status.state === 'denied' ? 'denied' : 'prompt');
                        lastPermissionCheckTs = Date.now();
                        return lastPermissionState;
                    }
                } catch (e) {}
            }
            var plugin = getCapacitorGeolocationPlugin();
            if (plugin && typeof plugin.checkPermissions === 'function') {
                try {
                    var perms = await plugin.checkPermissions();
                    var p = (perms && (perms.location || perms.coarseLocation)) || 'prompt';
                    lastPermissionState = p === 'granted' ? 'granted' : (p === 'denied' ? 'denied' : 'prompt');
                    lastPermissionCheckTs = Date.now();
                    return lastPermissionState;
                } catch (e2) {}
            }
        } finally {
            permissionCheckInFlight = false;
        }
        lastPermissionCheckTs = Date.now();
        return lastPermissionState;
    }

    async function updateTrackingQualityIndicator() {
        if (!el.trackingQuality) return;
        var perm = await resolveLocationPermissionState();
        var m = getMetrics();
        var gpsAgeMs = state.lastRealGpsTs ? (Date.now() - state.lastRealGpsTs) : (m.realGpsAgeMs || 999999);
        var acc = typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : 999;
        var next = { state: 'LIMITED', cls: 'quality-limited' };

        if (perm === 'denied') {
            next = { state: 'POOR', cls: 'quality-poor' };
        } else if (!state.isTracking) {
            next = { state: 'LIMITED', cls: 'quality-limited' };
        } else if (perm === 'granted' && gpsAgeMs <= 90000 && acc <= 50) {
            next = { state: 'GOOD', cls: 'quality-good' };
        } else if (gpsAgeMs > 180000 || acc > 120) {
            next = { state: 'POOR', cls: 'quality-poor' };
        } else {
            next = { state: 'LIMITED', cls: 'quality-limited' };
        }

        if (lastTrackingQualityState === next.state && el.trackingQuality.className.indexOf(next.cls) !== -1) {
            return;
        }
        lastTrackingQualityState = next.state;
        el.trackingQuality.textContent = next.state;
        el.trackingQuality.className = 'quality-pill ' + next.cls;
    }

    function qualityReasonFromConfidence() {
        if (!latestConfidenceReasons || !latestConfidenceReasons.length) return '';
        var first = latestConfidenceReasons[0] || {};
        var text = String(first.problem || '').toLowerCase();
        if (!text) return '';
        if (text.indexOf('sync') !== -1) return 'تأخير في التحديث';
        if (text.indexOf('accuracy') !== -1 || text.indexOf('gps') !== -1) return 'GPS ضعيف';
        if (text.indexOf('queue') !== -1) return 'تأخير في التحديث';
        return '';
    }

    function buildTrackingQualityTooltipText() {
        var stateText = lastTrackingQualityState || 'LIMITED';
        if (stateText === 'GOOD') {
            return 'التتبع يعمل بشكل ممتاز';
        }
        if (stateText === 'POOR') {
            return 'التتبع متوقف أو غير دقيق\n🔄 افتح التطبيق وتأكد من الإعدادات';
        }
        var reason = qualityReasonFromConfidence();
        if (!reason) {
            var m = getMetrics();
            var age = state.lastRealGpsTs ? (Date.now() - state.lastRealGpsTs) : (m.realGpsAgeMs || 999999);
            var acc = typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : 999;
            if (acc > 80) reason = 'GPS ضعيف';
            else if (age > 120000) reason = 'تأخير في التحديث';
            else reason = 'GPS ضعيف';
        }
        var extra = reason ? ('\nالسبب المرجح: ' + reason) : '';
        return 'GPS ضعيف\nتأخير في التحديث' + extra + '\n📍 حاول الانتقال لمكان مفتوح';
    }

    function hideTrackingQualityTooltip() {
        if (!qualityTooltipEl) return;
        if (qualityTooltipEl.parentNode) qualityTooltipEl.parentNode.removeChild(qualityTooltipEl);
        qualityTooltipEl = null;
    }

    function toggleTrackingQualityTooltip() {
        if (!el.trackingQuality) return;
        if (qualityTooltipEl) {
            hideTrackingQualityTooltip();
            return;
        }
        qualityTooltipEl = document.createElement('div');
        qualityTooltipEl.className = 'quality-tooltip';
        qualityTooltipEl.textContent = buildTrackingQualityTooltipText();
        document.body.appendChild(qualityTooltipEl);
        var rect = el.trackingQuality.getBoundingClientRect();
        var top = Math.max(8, rect.top - qualityTooltipEl.offsetHeight - 10);
        var left = Math.max(8, Math.min(window.innerWidth - qualityTooltipEl.offsetWidth - 8, rect.left));
        qualityTooltipEl.style.top = top + 'px';
        qualityTooltipEl.style.left = left + 'px';
    }

    function scheduleUiHeartbeat() {
        if (uiHeartbeatTimer) clearInterval(uiHeartbeatTimer);
        var tick = 0;
        uiHeartbeatTimer = setInterval(function () {
            tick += 1;
            updateLastUpdateUi();
            if (tick % 5 === 0) {
                persistTelemetry(false);
                updateTrackingQualityIndicator();
                runTrackingWatchdog();
                heartbeatOrchestratorTick();
                updateDebugRecoveryState();
            }
        }, 1000);
    }

    function updateLastUpdateUi() {
        if (!el.lastUpdateAgo) return;
        var m = getMetrics();
        if (!state.lastRealGpsTs) {
            el.lastUpdateAgo.textContent = '-';
            el.lastUpdateAgo.className = '';
            return;
        }
        var secs = Math.max(0, Math.floor((m.realGpsAgeMs || 0) / 1000));
        el.lastUpdateAgo.textContent = secs + ' sec ago';
        el.lastUpdateAgo.className = secs > 120 ? 'bad' : (secs > 60 ? 'warn' : 'fresh');
    }

    function updateConfidenceUi(queueCountValue, engineOutput) {
        if (!el.trackingConfidence) return;
        var m = getMetrics();
        var queueSize = typeof queueCountValue === 'number' ? queueCountValue : (m.queueCount || previousQueueCount);
        var minsSinceSync = state.lastSyncTs ? ((Date.now() - state.lastSyncTs) / 60000) : 999;
        var acc = typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : 999;
        var score = engineOutput && typeof engineOutput.score === 'number' ? engineOutput.score : 100;
        var level = '🟢 High';
        var cls = 'high';
        if (score < 70) {
            level = '🟡 Medium';
            cls = 'medium';
        }
        if (score < 40) {
            level = '🔴 Low';
            cls = 'low';
        }
        el.trackingConfidence.textContent = level;
        el.trackingConfidence.className = cls;
        latestConfidenceRaw = {
            last_sync_ms: state.lastSyncTs ? (Date.now() - state.lastSyncTs) : null,
            queue_count: queueSize,
            gps_accuracy: isFinite(acc) ? acc : null
        };
        latestConfidenceReasons = engineOutput && Array.isArray(engineOutput.reasons)
            ? engineOutput.reasons
            : buildConfidenceReasons(minsSinceSync, queueSize, acc, m.batteryLevel);
        if (confidenceExpanded) {
            renderConfidencePanel();
        }
    }

    function showMicroFeedback(text) {
        if (!el.microFeedback) return;
        el.microFeedback.textContent = text;
        el.microFeedback.classList.remove('hidden');
        if (feedbackTimer) clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(function () {
            if (el.microFeedback) el.microFeedback.classList.add('hidden');
        }, 2300);
    }

    function revealDebugPanel() {
        if (!el.debugSection) return;
        debugUnlocked = true;
        el.debugSection.classList.remove('hidden');
        if (el.advancedControls) {
            el.advancedControls.classList.remove('hidden');
        }
        if (confidenceExpanded) renderConfidencePanel();
        updateDebugRecoveryState();
        showMicroFeedback('🛠️ Debug panel unlocked');
    }

    function toggleConfidencePanel() {
        var weak = el.trackingConfidence && (el.trackingConfidence.className === 'low');
        if (!weak && !debugUnlocked) {
            if (confidenceExpanded && el.confidencePanel) {
                confidenceExpanded = false;
                el.confidencePanel.classList.add('hidden');
                if (el.confidenceCard) el.confidenceCard.setAttribute('aria-expanded', 'false');
            }
            return;
        }
        confidenceExpanded = !confidenceExpanded;
        if (el.confidenceCard) {
            el.confidenceCard.setAttribute('aria-expanded', confidenceExpanded ? 'true' : 'false');
        }
        if (!el.confidencePanel) return;
        if (!confidenceExpanded) {
            el.confidencePanel.classList.add('hidden');
            return;
        }
        renderConfidencePanel();
        el.confidencePanel.classList.remove('hidden');
    }

    function showInstallReminderOncePerDay() {
        if (!el.installReminder) return;
        var today = (new Date()).toISOString().slice(0, 10);
        try {
            var seen = localStorage.getItem(INSTALL_REMINDER_KEY) || '';
            if (seen === today) {
                el.installReminder.classList.add('hidden');
                return;
            }
        } catch (e) {}
        el.installReminder.classList.remove('hidden');
    }

    function renderConfidencePanel() {
        if (!el.confidenceReasons) return;
        var reasons = latestConfidenceReasons.slice(0, 3);
        if (reasons.length === 0) {
            reasons = [{
                icon: '✅',
                problem: 'Tracking looks stable',
                hint: 'Keep opening the app periodically to confirm tracking'
            }];
        }
        var html = reasons.map(function (r) {
            return '<div class="confidence-reason">'
                + '<div class="confidence-reason-main">' + escapeHtml(r.icon + ' ' + r.problem) + '</div>'
                + '<div class="confidence-reason-hint">→ ' + escapeHtml(r.hint) + '</div>'
                + '</div>';
        }).join('');
        el.confidenceReasons.innerHTML = html;
        if (el.confidenceRaw) {
            if (debugUnlocked) {
                el.confidenceRaw.classList.remove('hidden');
                el.confidenceRaw.textContent = 'last_sync_ms=' + (latestConfidenceRaw.last_sync_ms == null ? 'null' : latestConfidenceRaw.last_sync_ms)
                    + ' | queue_count=' + latestConfidenceRaw.queue_count
                    + ' | gps_accuracy=' + (latestConfidenceRaw.gps_accuracy == null ? 'null' : latestConfidenceRaw.gps_accuracy);
            } else {
                el.confidenceRaw.classList.add('hidden');
                el.confidenceRaw.textContent = '';
            }
        }
    }

    function buildConfidenceReasons(minsSinceSync, queueSize, accuracy, battery) {
        var candidates = [];
        if (minsSinceSync > 5) {
            candidates.push({
                severity: 100,
                icon: '⏱',
                problem: 'Last sync was ' + Math.round(minsSinceSync) + ' minutes ago',
                hint: '🔄 Open the app to refresh location'
            });
        }
        if (accuracy > 60) {
            candidates.push({
                severity: 90,
                icon: '📡',
                problem: 'Weak GPS signal',
                hint: '📍 Move to an open area'
            });
        } else if (accuracy > 30) {
            candidates.push({
                severity: 70,
                icon: '📡',
                problem: 'GPS accuracy is moderate',
                hint: '📍 Move to an open area'
            });
        }
        if (queueSize > 80) {
            candidates.push({
                severity: 80,
                icon: '📦',
                problem: 'Queue is high (' + queueSize + ' pending)',
                hint: '📶 Check internet connection'
            });
        } else if (queueSize > 30) {
            candidates.push({
                severity: 60,
                icon: '📦',
                problem: 'Pending sync is growing (' + queueSize + ' pending)',
                hint: '📶 Check internet connection'
            });
        }
        if (battery != null && battery < 20) {
            candidates.push({
                severity: 50,
                icon: '🔋',
                problem: 'Battery saving mode active',
                hint: '🔋 Charge the device'
            });
        }
        candidates.sort(function (a, b) { return b.severity - a.severity; });
        return candidates;
    }

    function updateDebugRecoveryState() {
        if (!el.debugRecovery || !debugUnlocked) return;
        var m = MetricsStore.get();
        var now = Date.now();
        var cooldownLeft = Math.max(0, Math.ceil((recoveryState.cooldownUntil - now) / 1000));
        var boostLeft = Math.max(0, Math.ceil((recoveryState.boostUntil - now) / 1000));
        el.debugRecovery.textContent = 'Recovery: boost=' + boostLeft + 's'
            + ' | cooldown=' + cooldownLeft + 's'
            + ' | attempts=' + recoveryState.attemptsInWindow
            + ' | pending=' + (recoveryState.pending ? '1' : '0')
            + ' | last_sync_ms=' + (m.lastSyncTs ? (now - m.lastSyncTs) : 'null')
            + ' | queue_count=' + m.queueCount
            + ' | gps_accuracy=' + (typeof m.gpsAccuracy === 'number' ? m.gpsAccuracy : 'null')
            + ' | prediction=' + m.predictionState
            + ' | confidence=' + m.confidenceScore
            + ' | engine_conf=' + (latestEngineResults.ConfidenceEngine ? latestEngineResults.ConfidenceEngine.score : 'null')
            + ' | engine_pred=' + (latestEngineResults.PredictionEngine ? latestEngineResults.PredictionEngine.risk : 'NONE')
            + ' | engine_rec_actions=' + (latestEngineResults.RecoveryEngine && latestEngineResults.RecoveryEngine.actions ? latestEngineResults.RecoveryEngine.actions.length : 0);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function initBattery() {
        if (!navigator.getBattery) {
            patchMetrics({ batteryLevel: null });
            syncUi();
            return;
        }
        navigator.getBattery().then(function (b) {
            patchMetrics({ batteryLevel: Math.round((b.level || 0) * 100) });
            syncUi();
            b.addEventListener('levelchange', function () {
                patchMetrics({ batteryLevel: Math.round((b.level || 0) * 100) });
                syncUi();
            });
        }).catch(function () {});
    }

    function createPointId(pos) {
        return [Date.now(), Math.round(pos.coords.latitude * 1000000), Math.round(pos.coords.longitude * 1000000)].join('_');
    }

    function mapPoint(p) {
        return {
            lat: p.lat,
            lng: p.lng,
            accuracy: p.accuracy,
            speed: p.speed,
            battery: p.battery,
            timestamp: p.timestamp,
            source: p.source || 'cached',
            status: p.status || 'location',
            is_estimated: !!p.is_estimated
        };
    }

    function safeJson(response) {
        return response.text().then(function (txt) {
            try { return JSON.parse(txt); } catch (e) { return null; }
        });
    }

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    function initDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB unavailable'));
                return;
            }
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_QUEUE)) {
                    db.createObjectStore(STORE_QUEUE, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORE_META)) {
                    db.createObjectStore(STORE_META, { keyPath: 'key' });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error || new Error('IndexedDB open failed')); };
        });
    }

    async function enqueuePoint(point) {
        var db = await dbPromise;
        await txRun(db, STORE_QUEUE, 'readwrite', function (store) { store.put(point); });
        await txRun(db, STORE_META, 'readwrite', function (store) { store.put({ key: 'last_point', value: point }); });
    }

    async function readQueueBatch(limit) {
        var db = await dbPromise;
        return txRead(db, STORE_QUEUE, function (store, done) {
            var out = [];
            var req = store.openCursor();
            req.onsuccess = function (e) {
                var cursor = e.target.result;
                if (!cursor || out.length >= limit) return done(out);
                out.push(cursor.value);
                cursor.continue();
            };
            req.onerror = function () { done([]); };
        });
    }

    async function readLastPoint() {
        var db = await dbPromise;
        return txRead(db, STORE_META, function (store, done) {
            var req = store.get('last_point');
            req.onsuccess = function () { done(req.result ? req.result.value : null); };
            req.onerror = function () { done(null); };
        });
    }

    async function removeQueueItems(ids) {
        if (!ids || !ids.length) return;
        var db = await dbPromise;
        await txRun(db, STORE_QUEUE, 'readwrite', function (store) {
            ids.forEach(function (id) { store.delete(id); });
        });
    }

    async function queueCount() {
        var db = await dbPromise;
        return txRead(db, STORE_QUEUE, function (store, done) {
            var req = store.count();
            req.onsuccess = function () { done(req.result || 0); };
            req.onerror = function () { done(0); };
        });
    }

    function txRun(db, storeName, mode, work) {
        return new Promise(function (resolve, reject) {
            var tx = db.transaction(storeName, mode);
            var store = tx.objectStore(storeName);
            work(store);
            tx.oncomplete = function () { resolve(); };
            tx.onerror = function () { reject(tx.error || new Error('IDB transaction failed')); };
        });
    }

    function txRead(db, storeName, work) {
        return new Promise(function (resolve) {
            var tx = db.transaction(storeName, 'readonly');
            var store = tx.objectStore(storeName);
            work(store, resolve);
        });
    }
})();
