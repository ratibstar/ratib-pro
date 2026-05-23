/**
 * Login / workforce QR scan page — uses RatibQrScanner + qr-login API.
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

    function mapErrorCode(code, fallback) {
        var map = {
            invalid: 'Invalid QR code.',
            expired: 'This QR code has expired.',
            revoked: 'This badge has been revoked.',
            replay: 'Duplicate scan — please wait.',
            rate_limit: 'Too many attempts. Wait a moment.',
            inactive: 'Account is not active.',
            pair_failed: 'Could not sign in on your computer. Try again.'
        };
        return map[code] || fallback || 'Scan failed.';
    }

    async function submitPayload(payload) {
        setStatus('Validating…', 'loading');
        if (scanner) {
            await scanner.stop();
        }
        if (startBtn) {
            startBtn.classList.add('d-none');
        }
        try {
            var res = await fetch(apiQr, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'validate',
                    qr_payload: payload,
                    pair_token: pairToken,
                    country_id: cfg.countryId || 0,
                    agency_id: cfg.agencyId || 0
                })
            });
            var json = await res.json();
            if (json.success) {
                if (pairToken) {
                    setStatus('Success. You can close this page — RATEB is opening on your computer.', 'success');
                } else {
                    setStatus('Signed in. Redirecting…', 'success');
                    window.location.href = (typeof pageUrl === 'function')
                        ? pageUrl('dashboard.php')
                        : '/pages/dashboard.php';
                }
                return;
            }
            if (scanner) {
                scanner.resetSubmit();
            }
            if (startBtn) {
                startBtn.classList.remove('d-none');
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
        throttleMs: 2800,
        onScan: submitPayload,
        onStatus: setStatus
    });

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            startBtn.classList.add('d-none');
            if (stopBtn) {
                stopBtn.classList.remove('d-none');
            }
            scanner.start();
        });
        // Mobile: one tap to open scanner (required for camera permission on iOS)
        if (window.matchMedia('(max-width: 820px)').matches) {
            setTimeout(function () {
                if (startBtn && !startBtn.classList.contains('d-none')) {
                    setStatus('Tap Start camera to scan your badge.', 'info');
                }
            }, 400);
        }
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', async function () {
            await scanner.stop();
            stopBtn.classList.add('d-none');
            if (startBtn) {
                startBtn.classList.remove('d-none');
            }
            scanner.resetSubmit();
            setStatus('Camera stopped. Tap Start camera to scan again.', 'info');
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
});
