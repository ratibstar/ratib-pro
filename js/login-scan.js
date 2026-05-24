/**
 * Login / workforce QR scan page — RatibQrScanner + qr-login API + PIN.
 */
document.addEventListener('DOMContentLoaded', function () {
    var cfg = window.RATIB_QR_SCAN || {};
    var pairToken = cfg.pairToken || '';
    var apiQr = cfg.apiQr || '/api/qr-login.php';
    var statusEl = document.getElementById('qr-scan-status');
    var startBtn = document.getElementById('qr-scan-start');
    var stopBtn = document.getElementById('qr-scan-stop');
    var pinPanel = document.getElementById('qr-scan-pin-panel');
    var pinInput = document.getElementById('qr-scan-pin');
    var pinSubmit = document.getElementById('qr-scan-pin-submit');
    var trustCb = document.getElementById('qr-scan-trust');
    var scanner = null;
    var pendingChallenge = '';
    var scanComplete = false;
    var hintTimer = null;
    var mobileStore = global.RatibMobileBadgeStore || null;
    var savedBadgeUsed = false;
    var skipAutoSaved = false;

    function storeCtx() {
        return {
            agencyId: cfg.agencyId || 0,
            countryId: cfg.countryId || 0,
            countrySlug: cfg.countrySlug || ''
        };
    }

    function saveBadgeOnPhone(payload, scanValue, extra) {
        if (!mobileStore || !payload) {
            return;
        }
        mobileStore.save(storeCtx(), payload, scanValue, extra || {});
    }

    function clearSavedBadge() {
        if (mobileStore) {
            mobileStore.clear(storeCtx());
        }
    }

    function showSavedPanel(entry) {
        var panel = document.getElementById('qr-scan-saved-panel');
        var meta = document.getElementById('qr-scan-saved-meta');
        if (!panel) {
            return;
        }
        panel.classList.remove('d-none');
        if (meta) {
            var label = entry.username ? ('User: ' + entry.username) : 'Your workforce badge';
            meta.textContent = label + ' is stored on this phone for this office.';
        }
    }

    function hideSavedPanel() {
        var panel = document.getElementById('qr-scan-saved-panel');
        if (panel) {
            panel.classList.add('d-none');
        }
    }

    function hideCameraForSaved() {
        var viewport = document.getElementById('qr-scan-viewport');
        if (viewport) {
            viewport.classList.add('d-none');
        }
        if (startBtn) {
            startBtn.classList.add('d-none');
        }
        if (stopBtn) {
            stopBtn.classList.add('d-none');
        }
    }

    function clearHintTimer() {
        if (hintTimer) {
            clearTimeout(hintTimer);
            hintTimer = null;
        }
    }

    function setStatus(message, type) {
        if (!statusEl || scanComplete) {
            return;
        }
        statusEl.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
        statusEl.textContent = message;
        statusEl.classList.remove('d-none');
    }

    function lockSuccessUi(message) {
        scanComplete = true;
        clearHintTimer();
        if (scanner) {
            scanner.stop();
        }
        var viewport = document.getElementById('qr-scan-viewport');
        if (viewport) {
            viewport.classList.add('qr-scan-viewport--done');
        }
        if (startBtn) {
            startBtn.classList.add('d-none');
        }
        if (stopBtn) {
            stopBtn.classList.add('d-none');
        }
        if (statusEl) {
            statusEl.className = 'qr-scan-status qr-scan-status--success qr-scan-status--final';
            statusEl.textContent = message;
            statusEl.classList.remove('d-none');
        }
    }

    function showPinPanel(show) {
        if (pinPanel) {
            pinPanel.classList.toggle('d-none', !show);
        }
    }

    function trustPayload() {
        var mainTrust = document.getElementById('qr-scan-trust');
        var pinTrust = document.getElementById('qr-scan-trust-pin');
        var trusted = (mainTrust && mainTrust.checked) || (pinTrust && pinTrust.checked);
        return {
            trust_device: !!trusted,
            device_label: pairToken ? 'Desktop workstation' : 'Mobile scanner'
        };
    }

    function isPairingQr(v) {
        if (/login[-/]scan|login-scan\.php/i.test(v)) {
            return true;
        }
        if (!/^https?:\/\//i.test(v)) {
            return false;
        }
        try {
            var u = new URL(v);
            if (u.searchParams.get('token') && /login/i.test(u.pathname || '')) {
                return true;
            }
            if (/login[-/]scan/i.test(u.pathname || '')) {
                return true;
            }
        } catch (e) {
            /* ignore */
        }
        return false;
    }

    function classifyScan(raw) {
        var v = String(raw || '').trim();
        if (!v) {
            return { kind: 'empty', payload: '' };
        }
        if (/^https?:\/\//i.test(v)) {
            try {
                var u = new URL(v);
                var d = u.searchParams.get('d') || u.searchParams.get('badge') || u.searchParams.get('p');
                if (d) {
                    return classifyScan(d);
                }
                if (/\/login\/badge/i.test(u.pathname || '')) {
                    return { kind: 'badge_url_empty', payload: v };
                }
                if (isPairingQr(v)) {
                    return { kind: 'pairing', payload: v };
                }
            } catch (e) {
                /* fall through */
            }
        }
        if (isPairingQr(v)) {
            return { kind: 'pairing', payload: v };
        }
        if (/^RATIBLOGIN:/i.test(v)) {
            return { kind: 'badge', payload: v };
        }
        if (/^R\d{5,}/i.test(v) && v.length <= 32) {
            return { kind: 'badge', payload: v };
        }
        if (v.indexOf('RATIBLOGIN') >= 0) {
            return { kind: 'badge', payload: v };
        }
        return { kind: 'badge', payload: v };
    }

    function alertWrongQr() {
        if (navigator.vibrate) {
            navigator.vibrate([120, 60, 120]);
        }
        var banner = document.getElementById('qr-scan-wrong-banner');
        if (banner) {
            banner.classList.remove('d-none');
        }
    }

    function hideWrongBanner() {
        var banner = document.getElementById('qr-scan-wrong-banner');
        if (banner) {
            banner.classList.add('d-none');
        }
    }

    function mapErrorCode(code, fallback) {
        var map = {
            invalid: 'Badge not recognized. Ask admin to refresh workforce access.',
            pairing_qr: 'That is the computer QR (step 1). Scan your workforce badge instead.',
            expired: 'This badge has expired. Ask admin to regenerate.',
            revoked: 'This badge has been revoked.',
            disabled: 'Workforce QR access is disabled for this user.',
            replay: 'Please wait a moment, then scan again.',
            rate_limit: 'Too many attempts. Wait a moment.',
            inactive: 'Account is not active.',
            pair_failed: 'Could not sign in on your computer. On the PC, choose Barcode again.',
            pin_invalid: 'Incorrect PIN. Try again.',
            needs_pin: 'Enter your PIN.'
        };
        return map[code] || fallback || 'Scan failed.';
    }

    function handleSuccess(json, payload, scanValue) {
        if (payload && /^RATIBLOGIN:/i.test(String(payload))) {
            saveBadgeOnPhone(payload, scanValue, { username: json && json.username ? json.username : '' });
        }
        showPinPanel(false);
        if (pairToken) {
            lockSuccessUi(
                'Success! RATEB is opening on your computer. Badge saved on this phone for next time. '
                + 'If the laptop is still waiting, switch back to it — login should finish in a few seconds.'
            );
            return;
        }
        lockSuccessUi('Signed in. Redirecting…');
        window.location.href = json.redirect || '/pages/dashboard.php';
    }

    async function submitPin() {
        if (!pendingChallenge) {
            return;
        }
        var pin = pinInput ? pinInput.value : '';
        setStatus('Verifying PIN…', 'loading');
        try {
            var body = Object.assign({
                action: 'validate_pin',
                challenge_token: pendingChallenge,
                pin: pin,
                pair_token: pairToken,
                country_id: cfg.countryId || 0,
                agency_id: cfg.agencyId || 0
            }, trustPayload());
            var res = await fetch(apiQr, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(body)
            });
            var json = await res.json();
            if (json.success) {
                var pinPayload = pendingChallenge ? '' : '';
                handleSuccess(json, pinPayload, pinPayload);
                return;
            }
            setStatus(mapErrorCode(json.code, json.message), 'error');
        } catch (e) {
            setStatus('Network error.', 'error');
        }
    }

    var lastBadgePayload = '';
    var lastBadgeScanValue = '';

    async function submitPayload(raw) {
        setStatus('QR detected — checking…', 'loading');

        var classified = classifyScan(raw);
        if (classified.kind === 'empty') {
            setStatus('Could not read that QR. Try again.', 'error');
            return;
        }
        if (classified.kind === 'pairing') {
            alertWrongQr();
            setStatus(
                'WRONG QR — That is the computer screen (step 1). Turn to System Settings → Users → Barcode and scan THAT QR instead.',
                'error'
            );
            if (scanner) {
                scanner.resetSubmit();
                scanner.lastScan = 0;
                scanner.throttleMs = 800;
            }
            return;
        }
        if (classified.kind === 'badge_url_empty') {
            setStatus('Badge link empty. In admin: Users → Workforce access → Regenerate, then scan the new QR.', 'error');
            if (scanner) {
                scanner.resetSubmit();
            }
            return;
        }

        hideWrongBanner();
        clearHintTimer();
        if (scanner) {
            scanner.lock();
        }
        lastBadgePayload = classified.payload;
        lastBadgeScanValue = raw;
        setStatus('Workforce badge recognized — signing in…', 'loading');
        pendingChallenge = '';

        try {
            var body = Object.assign({
                action: 'validate',
                qr_payload: classified.payload,
                pair_token: pairToken,
                country_id: cfg.countryId || 0,
                agency_id: cfg.agencyId || 0,
                country_slug: cfg.countrySlug || ''
            }, trustPayload());
            var res = await fetch(apiQr, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(body)
            });
            var json = await res.json();
            if (json.needs_pin && json.challenge_token) {
                pendingChallenge = json.challenge_token;
                showPinPanel(true);
                if (pinInput) {
                    pinInput.value = '';
                    pinInput.focus();
                }
                setStatus('Enter your 4-digit PIN.', 'info');
                return;
            }
            if (json.success) {
                handleSuccess(json, lastBadgePayload, lastBadgeScanValue);
                return;
            }
            if (json.code === 'invalid' || json.code === 'revoked' || json.code === 'expired') {
                clearSavedBadge();
            }
            if (scanner) {
                scanner.resetSubmit();
            }
            if (startBtn) {
                startBtn.classList.remove('d-none');
            }
            if (stopBtn) {
                stopBtn.classList.add('d-none');
            }
            hideSavedPanel();
            setStatus(mapErrorCode(json.code, json.message), 'error');
        } catch (e) {
            if (scanner) {
                scanner.resetSubmit();
            }
            if (startBtn) {
                startBtn.classList.remove('d-none');
            }
            setStatus('Network error. Check connection and try again.', 'error');
        }
    }

    if (typeof RatibQrScanner === 'undefined') {
        setStatus('Scanner failed to load. Refresh the page.', 'error');
        return;
    }

    scanner = new RatibQrScanner({
        elementId: 'qr-scan-viewport',
        throttleMs: 1200,
        onScan: submitPayload,
        onStatus: setStatus
    });

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            if (scanComplete) {
                return;
            }
            clearHintTimer();
            startBtn.classList.add('d-none');
            if (stopBtn) {
                stopBtn.classList.remove('d-none');
            }
            var banner = document.getElementById('qr-scan-wrong-banner');
            if (banner) {
                banner.classList.add('d-none');
            }
            var viewport = document.getElementById('qr-scan-viewport');
            if (viewport) {
                viewport.classList.remove('qr-scan-viewport--done');
            }
            setStatus(
                'Point at Users → Barcode on the admin screen — NOT the computer login QR.',
                'info'
            );
            scanner.throttleMs = 1200;
            scanner.resetSubmit();
            scanner.start();
            hintTimer = setTimeout(function () {
                if (!scanComplete) {
                    setStatus(
                        'No QR detected yet? Use the badge from System Settings → Users → Workforce access. Laptop screens are hard to scan — try Print badge or hold phone closer.',
                        'info'
                    );
                }
            }, 14000);
        });
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', async function () {
            await scanner.stop();
            stopBtn.classList.add('d-none');
            if (startBtn) {
                startBtn.classList.remove('d-none');
            }
            scanner.resetSubmit();
            setStatus('Camera stopped. Tap Start camera when ready.', 'info');
        });
    }

    if (pinSubmit) {
        pinSubmit.addEventListener('click', submitPin);
    }
    if (pinInput) {
        pinInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                submitPin();
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden && scanner) {
            scanner.stop();
        }
    });

    window.addEventListener('pagehide', function () {
        if (scanner) {
            scanner.stop();
        }
    });

    function tryAutoUseSavedBadge() {
        if (!pairToken || !mobileStore || skipAutoSaved || savedBadgeUsed) {
            return;
        }
        var saved = mobileStore.load(storeCtx());
        if (!saved || !saved.payload) {
            return;
        }
        savedBadgeUsed = true;
        showSavedPanel(saved);
        hideCameraForSaved();
        setStatus('Using access badge saved on this phone…', 'loading');
        submitPayload(saved.scanValue || saved.badgeUrl || saved.payload);
    }

    var useSavedBtn = document.getElementById('qr-scan-use-saved');
    var scanNewBtn = document.getElementById('qr-scan-scan-new');
    if (useSavedBtn) {
        useSavedBtn.addEventListener('click', function () {
            if (!mobileStore) {
                return;
            }
            var saved = mobileStore.load(storeCtx());
            if (!saved) {
                setStatus('No saved badge. Scan your badge from Users → Access once.', 'error');
                return;
            }
            savedBadgeUsed = true;
            setStatus('Signing in computer with saved badge…', 'loading');
            submitPayload(saved.scanValue || saved.badgeUrl || saved.payload);
        });
    }
    if (scanNewBtn) {
        scanNewBtn.addEventListener('click', function () {
            skipAutoSaved = true;
            savedBadgeUsed = false;
            hideSavedPanel();
            var viewport = document.getElementById('qr-scan-viewport');
            if (viewport) {
                viewport.classList.remove('d-none');
            }
            if (startBtn) {
                startBtn.classList.remove('d-none');
            }
            setStatus('Scan your badge from Users → Access on the laptop.', 'info');
        });
    }

    if (cfg.autoBadge && (pairToken || !pairToken)) {
        submitPayload(cfg.autoBadge);
    } else if (pairToken && mobileStore) {
        var existing = mobileStore.load(storeCtx());
        if (existing && existing.payload) {
            showSavedPanel(existing);
            tryAutoUseSavedBadge();
        }
    }
});
