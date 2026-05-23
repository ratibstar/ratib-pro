/**
 * Login / workforce QR scan page — RatibQrScanner + qr-login API.
 */
document.addEventListener('DOMContentLoaded', function () {
    var cfg = window.RATIB_QR_SCAN || {};
    var pairToken = cfg.pairToken || '';
    var apiQr = cfg.apiQr || '/api/qr-login.php';
    var statusEl = document.getElementById('qr-scan-status');
    var startBtn = document.getElementById('qr-scan-start');
    var stopBtn = document.getElementById('qr-scan-stop');
    var scanner = null;

    function setStatus(message, type) {
        if (!statusEl) {
            return;
        }
        statusEl.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
        statusEl.textContent = message;
        statusEl.classList.remove('d-none');
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
            invalid: 'Badge not recognized. In Users → Barcode, tap the user again to refresh the QR.',
            pairing_qr: 'That is the computer QR (step 1). Scan the employee badge from Users → Barcode.',
            expired: 'This badge has expired. Refresh Barcode in Users.',
            revoked: 'This badge has been revoked.',
            replay: 'Please wait a moment, then scan again.',
            rate_limit: 'Too many attempts. Wait a moment.',
            inactive: 'Account is not active.',
            pair_failed: 'Could not sign in on your computer. On the PC, choose Barcode again.'
        };
        return map[code] || fallback || 'Scan failed.';
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
            setStatus(
                'Stop — that is the computer QR (step 1). Point the camera at Users → Barcode on the admin screen instead.',
                'error'
            );
            return;
        }

        setStatus('Validating badge…', 'loading');

        try {
            var res = await fetch(apiQr, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify({
                    action: 'validate',
                    qr_payload: classified.payload,
                    pair_token: pairToken,
                    country_id: cfg.countryId || 0,
                    agency_id: cfg.agencyId || 0
                })
            });
            var json = await res.json();
            if (json.success) {
                if (scanner) {
                    await scanner.stop();
                }
                if (pairToken) {
                    setStatus('Success! RATEB is opening on your computer. You can close this page.', 'success');
                } else {
                    setStatus('Signed in. Redirecting…', 'success');
                    window.location.href = '/pages/dashboard.php';
                }
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
            setStatus('Point at Users → Barcode QR — not the computer login screen.', 'info');
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

    if (cfg.autoBadge && pairToken) {
        submitPayload(cfg.autoBadge);
    }
});
