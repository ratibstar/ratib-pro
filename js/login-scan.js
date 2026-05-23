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

    function setStatus(message, type) {
        if (!statusEl) {
            return;
        }
        statusEl.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
        statusEl.textContent = message;
        statusEl.classList.remove('d-none');
    }

    function showPinPanel(show) {
        if (pinPanel) {
            pinPanel.classList.toggle('d-none', !show);
        }
    }

    function trustPayload() {
        return {
            trust_device: !!(trustCb && trustCb.checked),
            device_label: 'Mobile scanner'
        };
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
                if (/\/login\/badge/i.test(u.pathname)) {
                    return { kind: 'empty', payload: '' };
                }
                if (u.searchParams.get('token') && /login[-/]scan|login-scan/i.test(v)) {
                    return { kind: 'pairing', payload: v };
                }
            } catch (e) {
                /* fall through */
            }
        }
        if (/^RATIBLOGIN:/i.test(v)) {
            return { kind: 'badge', payload: v };
        }
        if (/^https?:\/\//i.test(v) || /login[-/]scan|login-scan\.php/i.test(v)) {
            return { kind: 'pairing', payload: v };
        }
        if (/^R\d{5,}/i.test(v) && v.length <= 32) {
            return { kind: 'badge', payload: v };
        }
        if (v.indexOf('RATIBLOGIN') >= 0) {
            return { kind: 'badge', payload: v };
        }
        return { kind: 'unknown', payload: v };
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

    function handleSuccess(json) {
        if (scanner) {
            scanner.stop();
        }
        if (pairToken) {
            setStatus('Success! RATEB is opening on your computer. You can close this page.', 'success');
            showPinPanel(false);
            return;
        }
        setStatus('Signed in. Redirecting…', 'success');
        showPinPanel(false);
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
                handleSuccess(json);
                return;
            }
            setStatus(mapErrorCode(json.code, json.message), 'error');
        } catch (e) {
            setStatus('Network error.', 'error');
        }
    }

    async function submitPayload(raw) {
        var classified = classifyScan(raw);
        if (classified.kind === 'empty') {
            return;
        }
        if (classified.kind === 'pairing') {
            if (scanner) {
                scanner.resetSubmit();
            }
            setStatus('That is the computer pairing QR. Scan your workforce badge instead.', 'error');
            return;
        }

        setStatus('Validating badge…', 'loading');
        pendingChallenge = '';

        try {
            var body = Object.assign({
                action: 'validate',
                qr_payload: classified.payload,
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
                handleSuccess(json);
                return;
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
        throttleMs: 3000,
        onScan: submitPayload,
        onStatus: setStatus
    });

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            startBtn.classList.add('d-none');
            if (stopBtn) {
                stopBtn.classList.remove('d-none');
            }
            setStatus('Point at your workforce badge QR.', 'info');
            scanner.start();
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

    if (cfg.autoBadge && (pairToken || !pairToken)) {
        submitPayload(cfg.autoBadge);
    }
});
