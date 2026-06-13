(function () {
    'use strict';

    var cfg = window.RATEB_QR_SCAN || {};
    var pairToken = cfg.pairToken || '';
    var apiQr = cfg.apiQr || '/api/qr-login';
    var statusEl = document.getElementById('qr-scan-status');
    var startBtn = document.getElementById('qr-scan-start');
    var stopBtn = document.getElementById('qr-scan-stop');
    var scanner = null;
    var scanComplete = false;

    function setStatus(message, type) {
        if (!statusEl || scanComplete) return;
        statusEl.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
        statusEl.textContent = message;
        statusEl.classList.remove('d-none');
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function lockSuccessUi(message, username) {
        scanComplete = true;
        if (scanner) scanner.stop();
        var shell = document.querySelector('.qr-scan-shell');
        if (!shell) return;
        Array.prototype.forEach.call(shell.children, function (el) {
            if (el.id !== 'qr-scan-success') el.classList.add('d-none');
        });
        var card = document.getElementById('qr-scan-success');
        if (!card) {
            card = document.createElement('div');
            card.id = 'qr-scan-success';
            card.className = 'qr-scan-success';
            shell.appendChild(card);
        }
        card.classList.remove('d-none');
        var who = username ? '<p class="qr-scan-success-user">' + escapeHtml(username) + '</p>' : '';
        card.innerHTML =
            '<div class="qr-scan-success-icon"><i class="fas fa-circle-check"></i></div>'
            + '<h2 class="qr-scan-success-title">Logged in successfully</h2>'
            + who
            + '<p class="qr-scan-success-msg">' + escapeHtml(message) + '</p>';
    }

    function isPairingQr(v) {
        if (/login[-/]scan/i.test(v)) return true;
        if (!/^https?:\/\//i.test(v)) return false;
        try {
            var u = new URL(v);
            if (u.searchParams.get('token') && /login/i.test(u.pathname || '')) return true;
            if (/login[-/]scan/i.test(u.pathname || '')) return true;
        } catch (e) { /* ignore */ }
        return false;
    }

    function classifyScan(raw) {
        var v = String(raw || '').trim();
        if (!v) return { kind: 'empty', payload: '' };
        if (/^https?:\/\//i.test(v)) {
            try {
                var u = new URL(v);
                var d = u.searchParams.get('d') || u.searchParams.get('badge');
                if (d) return classifyScan(d);
                if (/\/login\/badge/i.test(u.pathname || '')) {
                    return { kind: 'badge_url_empty', payload: v };
                }
                if (isPairingQr(v)) return { kind: 'pairing', payload: v };
            } catch (e) { /* fall through */ }
        }
        if (isPairingQr(v)) return { kind: 'pairing', payload: v };
        if (/^RATEBERP:/i.test(v)) return { kind: 'badge', payload: v };
        if (/^ERP\d{5,}/i.test(v)) return { kind: 'badge', payload: v };
        return { kind: 'badge', payload: v };
    }

    function alertWrongQr() {
        if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
        var banner = document.getElementById('qr-scan-wrong-banner');
        if (banner) banner.classList.remove('d-none');
    }

    function submitPayload(raw) {
        setStatus('QR detected — checking…', 'loading');
        var classified = classifyScan(raw);
        if (classified.kind === 'empty') {
            setStatus('Could not read that QR. Try again.', 'error');
            return;
        }
        if (classified.kind === 'pairing') {
            alertWrongQr();
            setStatus('Wrong QR — scan your user badge from Profile or Admin → Users.', 'error');
            if (scanner) {
                scanner.resetSubmit();
                scanner.lastScan = 0;
            }
            return;
        }
        if (classified.kind === 'badge_url_empty') {
            setStatus('Badge link empty. Regenerate the badge in admin.', 'error');
            if (scanner) scanner.resetSubmit();
            return;
        }
        if (scanner) scanner.lock();

        fetch(apiQr, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                action: 'validate',
                qr_payload: classified.payload,
                pair_token: pairToken
            })
        }).then(function (r) { return r.json(); })
            .then(function (json) {
                if (json && json.success) {
                    var username = json.username || '';
                    if (pairToken) {
                        lockSuccessUi('ERP is opening on your computer. You can switch back to the laptop now.', username);
                    } else if (json.redirect) {
                        lockSuccessUi('Signed in. Redirecting…', username);
                        window.location.href = json.redirect;
                    } else {
                        lockSuccessUi('Signed in.', username);
                    }
                    return;
                }
                if (scanner) scanner.resetSubmit();
                setStatus((json && json.message) || 'Scan failed.', 'error');
            })
            .catch(function () {
                if (scanner) scanner.resetSubmit();
                setStatus('Network error. Check connection and try again.', 'error');
            });
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
            startBtn.classList.add('d-none');
            if (stopBtn) stopBtn.classList.remove('d-none');
            scanner.start().catch(function () {
                startBtn.classList.remove('d-none');
                if (stopBtn) stopBtn.classList.add('d-none');
            });
        });
    }
    if (stopBtn) {
        stopBtn.addEventListener('click', function () {
            scanner.stop();
            stopBtn.classList.add('d-none');
            if (startBtn) startBtn.classList.remove('d-none');
            setStatus('Tap Start camera and allow access when prompted.', 'info');
        });
    }

    if (cfg.autoBadge) {
        setTimeout(function () { submitPayload(cfg.autoBadge); }, 400);
    }
})();
