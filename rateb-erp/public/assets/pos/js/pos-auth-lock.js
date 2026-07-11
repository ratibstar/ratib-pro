/**
 * POS cashier lock vault (IndexedDB) — offline unlock via WebAuthn or PIN.
 * Never stores raw passwords. Device UUID + cached active flag for offline gate.
 */
(function () {
    'use strict';

    var DB_NAME = 'rateb_pos_auth_lock';
    var DB_VERSION = 1;
    var STORE = 'vault';
    var VAULT_KEY = 'primary';
    var DEVICE_LS_KEY = 'rateb_pos_device_uuid';
    var DEVICE_STATUS_KEY = 'rateb_pos_device_status';
    var UNLOCK_SESSION_KEY = 'rateb_pos_unlock_until';
    var SESSION_REAUTH_KEY = 'rateb_pos_session_reauth';
    var UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    var PBKDF2_ITERATIONS = 120000;

    var dbPromise = null;
    var overlayEl = null;
    var initStarted = false;

    function config() {
        var el = document.getElementById('rateb-pos-register-config');
        try {
            return JSON.parse((el && el.textContent) || '{}');
        } catch (e) {
            return {};
        }
    }

    function t(key, fb) {
        var i18n = config().i18n || {};
        return i18n[key] || fb || key;
    }

    function csrf() {
        var cfg = config();
        return cfg.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function openDb() {
        if (dbPromise) {
            return dbPromise;
        }
        if (!window.indexedDB) {
            return Promise.reject(new Error('IndexedDB unavailable'));
        }
        dbPromise = new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'id' });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error || new Error('IDB open failed')); };
        });
        return dbPromise;
    }

    function idbGet() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, 'readonly');
                var req = tx.objectStore(STORE).get(VAULT_KEY);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function idbPut(record) {
        record = record || {};
        record.id = VAULT_KEY;
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, 'readwrite');
                tx.objectStore(STORE).put(record);
                tx.oncomplete = function () { resolve(record); };
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    function bufToB64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        bytes.forEach(function (b) { bin += String.fromCharCode(b); });
        return btoa(bin);
    }

    function b64ToBuf(b64) {
        var bin = atob(String(b64 || '').replace(/-/g, '+').replace(/_/g, '/'));
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function randomBytes(n) {
        var buf = new Uint8Array(n);
        crypto.getRandomValues(buf);
        return buf;
    }

    function uuid() {
        if (crypto.randomUUID) {
            return crypto.randomUUID();
        }
        var b = randomBytes(16);
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        var hex = [];
        for (var i = 0; i < b.length; i++) {
            hex.push(('0' + b[i].toString(16)).slice(-2));
        }
        return (
            hex.slice(0, 4).join('') + '-' +
            hex.slice(4, 6).join('') + '-' +
            hex.slice(6, 8).join('') + '-' +
            hex.slice(8, 10).join('') + '-' +
            hex.slice(10).join('')
        );
    }

    function getDeviceId() {
        try {
            var id = localStorage.getItem(DEVICE_LS_KEY);
            if (id && id.length >= 8) {
                return id;
            }
            id = uuid();
            localStorage.setItem(DEVICE_LS_KEY, id);
            return id;
        } catch (e) {
            return uuid();
        }
    }

    function getCachedDeviceStatus() {
        try {
            var raw = localStorage.getItem(DEVICE_STATUS_KEY);
            if (!raw) {
                return null;
            }
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function setCachedDeviceStatus(device) {
        if (!device || typeof device !== 'object') {
            return;
        }
        try {
            localStorage.setItem(DEVICE_STATUS_KEY, JSON.stringify({
                device_id: device.device_id || getDeviceId(),
                status: String(device.status || ''),
                is_active: !!device.is_active,
                company_id: device.company_id || null,
                branch_id: device.branch_id != null ? device.branch_id : null,
                updated_at: Date.now()
            }));
        } catch (e) { /* ignore */ }
    }

    /** Cached status gate: block only when we know device is not active. */
    function isDeviceAllowedOffline() {
        var cached = getCachedDeviceStatus();
        if (!cached || !cached.status) {
            // TODO: device-admin may still be wiring; allow unlock until status is known.
            return true;
        }
        return cached.is_active === true || cached.status === 'active';
    }

    function unlockUntil() {
        try {
            var v = parseInt(sessionStorage.getItem(UNLOCK_SESSION_KEY) || '0', 10);
            return isFinite(v) ? v : 0;
        } catch (e) {
            return 0;
        }
    }

    function setUnlockUntil(ts) {
        try {
            if (ts > 0) {
                sessionStorage.setItem(UNLOCK_SESSION_KEY, String(ts));
            } else {
                sessionStorage.removeItem(UNLOCK_SESSION_KEY);
            }
        } catch (e) { /* ignore */ }
    }

    function isUnlocked() {
        return unlockUntil() > Date.now();
    }

    function markUnlocked() {
        setUnlockUntil(Date.now() + UNLOCK_TTL_MS);
    }

    function clearUnlock() {
        setUnlockUntil(0);
    }

    function sessionNeedsReauth() {
        try {
            return sessionStorage.getItem(SESSION_REAUTH_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function ensureSessionBanner() {
        var root = document.querySelector('[data-pos-register]');
        if (!root) {
            return;
        }
        var banner = root.querySelector('[data-pos-session-reauth-banner]');
        if (!sessionNeedsReauth()) {
            if (banner) {
                banner.hidden = true;
            }
            return;
        }
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'rateb-pos__session-reauth-banner';
            banner.setAttribute('data-pos-session-reauth-banner', '');
            banner.setAttribute('role', 'status');
            root.insertBefore(banner, root.firstChild);
        }
        banner.hidden = false;
        banner.textContent = t(
            'pos_lock_session_renew',
            'Reconnect to renew session before sync. Selling can continue offline.'
        );
    }

    function markSessionNeedsReauth() {
        try {
            sessionStorage.setItem(SESSION_REAUTH_KEY, '1');
        } catch (e) { /* ignore */ }
        updateOverlayMessages();
        ensureSessionBanner();
    }

    function clearSessionNeedsReauth() {
        try {
            sessionStorage.removeItem(SESSION_REAUTH_KEY);
        } catch (e) { /* ignore */ }
        updateOverlayMessages();
        ensureSessionBanner();
    }

    function hashPin(pin, saltB64) {
        var enc = new TextEncoder();
        var salt = saltB64 ? new Uint8Array(b64ToBuf(saltB64)) : randomBytes(16);
        return crypto.subtle.importKey('raw', enc.encode(String(pin)), 'PBKDF2', false, ['deriveBits'])
            .then(function (key) {
                return crypto.subtle.deriveBits(
                    { name: 'PBKDF2', salt: salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
                    key,
                    256
                );
            })
            .then(function (bits) {
                return { hash: bufToB64(bits), salt: bufToB64(salt.buffer) };
            });
    }

    function verifyPin(pin, record) {
        if (!record || !record.pin_hash || !record.pin_salt) {
            return Promise.resolve(false);
        }
        return hashPin(pin, record.pin_salt).then(function (out) {
            return out.hash === record.pin_hash;
        });
    }

    function isEnrolled() {
        return idbGet().then(function (row) {
            return !!(row && (row.credential_id || row.pin_hash));
        }).catch(function () {
            return false;
        });
    }

    /**
     * Write / merge vault after online WebAuthn or PIN enroll.
     * @param {object} opts
     */
    function enroll(opts) {
        opts = opts || {};
        return idbGet().then(function (existing) {
            var next = existing || { id: VAULT_KEY };
            if (opts.company_id != null) {
                next.company_id = parseInt(opts.company_id, 10) || 0;
            }
            if (opts.branch_id != null) {
                next.branch_id = parseInt(opts.branch_id, 10) || 0;
            }
            if (opts.user_id != null) {
                next.user_id = parseInt(opts.user_id, 10) || 0;
            }
            if (opts.display_name) {
                next.display_name = String(opts.display_name);
            }
            if (opts.credential_id) {
                next.credential_id = String(opts.credential_id);
            }
            next.device_id = getDeviceId();
            next.updated_at = Date.now();
            next.unlock_ttl_ms = UNLOCK_TTL_MS;

            var pinPromise = Promise.resolve(null);
            if (opts.pin) {
                pinPromise = hashPin(String(opts.pin)).then(function (out) {
                    next.pin_hash = out.hash;
                    next.pin_salt = out.salt;
                });
            }
            return pinPromise.then(function () {
                return idbPut(next);
            });
        }).then(function (row) {
            markUnlocked();
            return row;
        });
    }

    function lock() {
        clearUnlock();
        return showOverlay();
    }

    function switchCashier() {
        return lock();
    }

    /** Clear unlock only — vault + device stay for next unlock. */
    function logoutLocal() {
        clearUnlock();
        return showOverlay();
    }

    function unlockWithPin(pin) {
        if (!isDeviceAllowedOffline()) {
            return Promise.reject(new Error(t('pos_lock_device_inactive', 'This device is not activated for offline use')));
        }
        return idbGet().then(function (row) {
            if (!row || !row.pin_hash) {
                throw new Error(t('pos_lock_pin_not_set', 'PIN not set'));
            }
            return verifyPin(pin, row).then(function (ok) {
                if (!ok) {
                    throw new Error(t('pos_lock_pin_invalid', 'Incorrect PIN'));
                }
                markUnlocked();
                hideOverlay();
                return true;
            });
        });
    }

    /** Offline WebAuthn: platform verifies user presence; we accept returned credential id match. */
    function unlockWithWebAuthn() {
        if (!isDeviceAllowedOffline()) {
            return Promise.reject(new Error(t('pos_lock_device_inactive', 'This device is not activated for offline use')));
        }
        if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.get) {
            return Promise.reject(new Error(t('pos_biometric_failed', 'WebAuthn not supported')));
        }
        return idbGet().then(function (row) {
            if (!row || !row.credential_id) {
                throw new Error(t('pos_biometric_not_enrolled', 'No biometric enrolled'));
            }
            var challenge = randomBytes(32);
            var allow = [{
                type: 'public-key',
                id: b64ToBuf(row.credential_id)
            }];
            return navigator.credentials.get({
                publicKey: {
                    challenge: challenge,
                    allowCredentials: allow,
                    userVerification: 'required',
                    timeout: 60000
                }
            }).then(function (cred) {
                if (!cred || !cred.rawId) {
                    throw new Error(t('pos_biometric_failed', 'Verification failed'));
                }
                var returned = bufToB64(cred.rawId);
                if (returned !== row.credential_id && returned.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') !==
                    String(row.credential_id).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')) {
                    throw new Error(t('pos_biometric_invalid_credential', 'Invalid credential'));
                }
                markUnlocked();
                hideOverlay();
                return true;
            });
        });
    }

    function fetchJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrf()
            },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().then(function (data) {
                if (res.status === 401 || res.status === 403 || res.status === 419) {
                    markSessionNeedsReauth();
                }
                if (!res.ok || data.ok === false) {
                    var err = (data && data.error) || t('invalid_request', 'Request failed');
                    throw new Error(typeof err === 'string' ? err : (err.message || 'Request failed'));
                }
                return data;
            });
        });
    }

    function registerDeviceOnline() {
        var cfg = config();
        var api = cfg.api || {};
        if (!api.deviceRegister || !navigator.onLine) {
            return Promise.resolve(null);
        }
        var scope = cfg.registerScope || {};
        return fetchJson(api.deviceRegister, {
            device_id: getDeviceId(),
            branch_id: scope.branch_id || cfg.branchId || 0,
            label: (navigator.platform || '') + ' POS',
            meta: {
                user_agent: navigator.userAgent || '',
                platform: navigator.platform || '',
                language: navigator.language || ''
            }
        }).then(function (data) {
            if (data.device) {
                setCachedDeviceStatus(data.device);
            }
            return data.device || null;
        }).catch(function () {
            return null;
        });
    }

    function heartbeatOnline() {
        var cfg = config();
        var api = cfg.api || {};
        if (!api.deviceHeartbeat || !navigator.onLine) {
            return Promise.resolve(null);
        }
        return fetchJson(api.deviceHeartbeat, {
            device_id: getDeviceId(),
            meta: { user_agent: navigator.userAgent || '' }
        }).then(function (data) {
            if (data.device) {
                setCachedDeviceStatus(data.device);
            }
            return data.device || null;
        }).catch(function (err) {
            var msg = String((err && err.message) || '');
            if (/not found|NOT_FOUND/i.test(msg)) {
                return registerDeviceOnline();
            }
            return null;
        });
    }

    function ensureOverlay() {
        if (overlayEl && document.body.contains(overlayEl)) {
            return overlayEl;
        }
        overlayEl = document.createElement('div');
        overlayEl.className = 'rateb-pos__auth-lock';
        overlayEl.setAttribute('data-pos-auth-lock', '');
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');
        overlayEl.innerHTML =
            '<div class="rateb-pos__auth-lock-card">' +
            '  <h1 class="rateb-pos__auth-lock-title" data-pos-lock-title></h1>' +
            '  <p class="rateb-pos__auth-lock-cashier" data-pos-lock-cashier></p>' +
            '  <p class="rateb-pos__hint rateb-pos__auth-lock-session" data-pos-lock-session hidden></p>' +
            '  <p class="rateb-pos__hint rateb-pos__auth-lock-device" data-pos-lock-device hidden></p>' +
            '  <div class="rateb-pos__biometric-actions">' +
            '    <button type="button" class="rateb-pos__biometric-btn" data-pos-lock-webauthn></button>' +
            '    <label class="rateb-pos__field-label" for="rateb-pos-lock-pin" data-pos-lock-pin-label></label>' +
            '    <input type="password" inputmode="numeric" autocomplete="current-password" maxlength="12" ' +
            '           class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-lock-pin" data-pos-lock-pin />' +
            '    <button type="button" class="rateb-pos__biometric-btn rateb-pos__biometric-btn--register" data-pos-lock-pin-submit></button>' +
            '  </div>' +
            '  <p class="rateb-pos__hint" data-pos-lock-status role="status" aria-live="polite"></p>' +
            '</div>';
        document.body.appendChild(overlayEl);

        var waBtn = overlayEl.querySelector('[data-pos-lock-webauthn]');
        var pinBtn = overlayEl.querySelector('[data-pos-lock-pin-submit]');
        var pinInput = overlayEl.querySelector('[data-pos-lock-pin]');

        if (waBtn) {
            waBtn.addEventListener('click', function () {
                setLockStatus(t('pos_register_loading', 'Loading…'));
                unlockWithWebAuthn()
                    .then(function () { setLockStatus(''); })
                    .catch(function (err) {
                        setLockStatus(err.message || t('pos_biometric_failed', 'Verification failed'), true);
                    });
            });
        }
        function submitPin() {
            var pin = (pinInput && pinInput.value) || '';
            if (!pin) {
                setLockStatus(t('pos_lock_pin_required', 'Enter PIN'), true);
                return;
            }
            setLockStatus(t('pos_register_loading', 'Loading…'));
            unlockWithPin(pin)
                .then(function () {
                    if (pinInput) {
                        pinInput.value = '';
                    }
                    setLockStatus('');
                })
                .catch(function (err) {
                    setLockStatus(err.message || t('pos_lock_pin_invalid', 'Incorrect PIN'), true);
                });
        }
        if (pinBtn) {
            pinBtn.addEventListener('click', submitPin);
        }
        if (pinInput) {
            pinInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitPin();
                }
            });
        }
        return overlayEl;
    }

    function setLockStatus(msg, isError) {
        var el = overlayEl && overlayEl.querySelector('[data-pos-lock-status]');
        if (!el) {
            return;
        }
        el.textContent = msg || '';
        el.style.color = isError ? 'var(--pos-danger)' : '';
    }

    function updateOverlayMessages() {
        if (!overlayEl) {
            return;
        }
        var title = overlayEl.querySelector('[data-pos-lock-title]');
        var cashier = overlayEl.querySelector('[data-pos-lock-cashier]');
        var sessionMsg = overlayEl.querySelector('[data-pos-lock-session]');
        var deviceMsg = overlayEl.querySelector('[data-pos-lock-device]');
        var waBtn = overlayEl.querySelector('[data-pos-lock-webauthn]');
        var pinLabel = overlayEl.querySelector('[data-pos-lock-pin-label]');
        var pinBtn = overlayEl.querySelector('[data-pos-lock-pin-submit]');

        if (title) {
            title.textContent = t('pos_lock_title', 'Cashier lock');
        }
        if (waBtn) {
            waBtn.textContent = t('pos_biometric_scan', 'Scan fingerprint');
        }
        if (pinLabel) {
            pinLabel.textContent = t('pos_lock_pin', 'PIN');
        }
        if (pinBtn) {
            pinBtn.textContent = t('pos_lock_unlock_pin', 'Unlock with PIN');
        }

        idbGet().then(function (row) {
            if (cashier) {
                cashier.textContent = (row && row.display_name) || t('pos_cashier', 'Cashier');
            }
            if (waBtn) {
                waBtn.hidden = !(row && row.credential_id);
            }
        }).catch(function () { /* ignore */ });

        if (sessionMsg) {
            if (sessionNeedsReauth() || (!navigator.onLine && !csrf())) {
                sessionMsg.hidden = false;
                sessionMsg.textContent = t(
                    'pos_lock_session_renew',
                    'Reconnect to renew session before sync. Selling can continue offline.'
                );
            } else if (!navigator.onLine) {
                sessionMsg.hidden = false;
                sessionMsg.textContent = t(
                    'pos_lock_session_renew',
                    'Reconnect to renew session before sync. Selling can continue offline.'
                );
            } else {
                sessionMsg.hidden = true;
            }
        }
        if (deviceMsg) {
            if (!isDeviceAllowedOffline()) {
                deviceMsg.hidden = false;
                deviceMsg.textContent = t(
                    'pos_lock_device_inactive',
                    'This device is not activated for offline use'
                );
            } else {
                deviceMsg.hidden = true;
            }
        }
    }

    function showOverlay() {
        var root = document.querySelector('[data-pos-register]');
        if (root) {
            root.classList.add('rateb-pos--auth-locked');
        }
        ensureOverlay();
        overlayEl.hidden = false;
        updateOverlayMessages();
        return Promise.resolve(true);
    }

    function hideOverlay() {
        var root = document.querySelector('[data-pos-register]');
        if (root) {
            root.classList.remove('rateb-pos--auth-locked');
        }
        if (overlayEl) {
            overlayEl.hidden = true;
        }
        return Promise.resolve(true);
    }

    function needsLockScreen() {
        return isEnrolled().then(function (enrolled) {
            if (!enrolled) {
                return false;
            }
            if (isUnlocked()) {
                return false;
            }
            return true;
        });
    }

    /**
     * Enroll vault from biometric gate after online WebAuthn/PIN success.
     * @param {object} opts
     */
    function enrollFromGate(opts) {
        opts = opts || {};
        var cfg = config();
        return enroll({
            company_id: opts.company_id != null ? opts.company_id : cfg.companyId,
            branch_id: opts.branch_id != null ? opts.branch_id : (cfg.branchId || (cfg.registerScope && cfg.registerScope.branch_id) || 0),
            user_id: opts.user_id != null ? opts.user_id : cfg.userId,
            display_name: opts.display_name || cfg.displayName || '',
            credential_id: opts.credential_id || null,
            pin: opts.pin || null
        }).then(function (row) {
            return registerDeviceOnline().then(function () {
                return row;
            });
        });
    }

    function bindHeaderActions() {
        document.addEventListener('click', function (e) {
            var lockBtn = e.target.closest('[data-pos-auth-lock-now]');
            var switchBtn = e.target.closest('[data-pos-auth-switch-cashier]');
            var logoutBtn = e.target.closest('[data-pos-auth-logout-local]');
            var bioLogout = e.target.closest('[data-pos-auth-logout-biometric]');
            if (lockBtn) {
                e.preventDefault();
                lock();
            } else if (switchBtn) {
                e.preventDefault();
                switchCashier();
            } else if (logoutBtn) {
                e.preventDefault();
                logoutLocal();
            } else if (bioLogout) {
                // Clear local unlock vault session, then follow href to biometric gate.
                try {
                    clearUnlock();
                } catch (err) { /* ignore */ }
            }
        });
    }

    function initOnRegister() {
        if (initStarted) {
            return Promise.resolve();
        }
        var root = document.querySelector('[data-pos-register]');
        if (!root) {
            return Promise.resolve();
        }
        initStarted = true;
        bindHeaderActions();
        ensureSessionBanner();

        var online = navigator.onLine !== false;
        if (online) {
            registerDeviceOnline().then(function () {
                return heartbeatOnline();
            });
            // Fresh server session — clear stale reauth flag when CSRF present.
            if (csrf()) {
                clearSessionNeedsReauth();
            }
        }

        return needsLockScreen().then(function (need) {
            if (need) {
                return showOverlay();
            }
            return null;
        }).catch(function () {
            return null;
        });
    }

    window.RatebPosAuthLock = {
        getDeviceId: getDeviceId,
        getCachedDeviceStatus: getCachedDeviceStatus,
        setCachedDeviceStatus: setCachedDeviceStatus,
        isDeviceAllowedOffline: isDeviceAllowedOffline,
        registerDeviceOnline: registerDeviceOnline,
        heartbeatOnline: heartbeatOnline,
        isEnrolled: isEnrolled,
        enroll: enroll,
        enrollFromGate: enrollFromGate,
        unlockWithPin: unlockWithPin,
        /** Verify offline PIN without changing lock overlay (supervisor gates). */
        verifyPinOnly: function (pin) {
            return idbGet().then(function (row) {
                if (!row || !row.pin_hash) {
                    return Promise.reject(new Error(t('pos_lock_pin_not_set', 'PIN not set')));
                }
                return verifyPin(pin, row).then(function (ok) {
                    if (!ok) {
                        throw new Error(t('pos_lock_pin_invalid', 'Incorrect PIN'));
                    }
                    return true;
                });
            });
        },
        hasPinEnrolled: function () {
            return idbGet().then(function (row) {
                return !!(row && row.pin_hash);
            }).catch(function () {
                return false;
            });
        },
        unlockWithWebAuthn: unlockWithWebAuthn,
        lock: lock,
        switchCashier: switchCashier,
        logoutLocal: logoutLocal,
        isUnlocked: isUnlocked,
        needsLockScreen: needsLockScreen,
        showOverlay: showOverlay,
        hideOverlay: hideOverlay,
        sessionNeedsReauth: sessionNeedsReauth,
        markSessionNeedsReauth: markSessionNeedsReauth,
        clearSessionNeedsReauth: clearSessionNeedsReauth,
        initOnRegister: initOnRegister,
        UNLOCK_TTL_MS: UNLOCK_TTL_MS
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initOnRegister();
        });
    } else {
        initOnRegister();
    }
})();
