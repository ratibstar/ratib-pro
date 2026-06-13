(function () {
    'use strict';

    function boot() {
        var cfg = window.RATEB_QR_SCAN || {};
        var pairToken = cfg.pairToken || '';
        var apiQr = cfg.apiQr || '/api/qr-login';
        var statusEl = document.getElementById('qr-scan-status');
        var startBtn = document.getElementById('qr-scan-start');
        var stopBtn = document.getElementById('qr-scan-stop');
        var manualForm = document.getElementById('qr-scan-manual-form');
        var manualInput = document.getElementById('qr-scan-manual-input');
        var mobileStore = (typeof window !== 'undefined' && window.RatebErpMobileBadgeStore) || null;
        var scanner = null;
        var scanComplete = false;
        var savedBadgeUsed = false;
        var skipAutoSaved = false;

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
                + '<h2 class="qr-scan-success-title">' + escapeHtml(cfg.successTitle || 'Logged in successfully') + '</h2>'
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

        function saveBadgeOnPhone(payload, scanValue, username) {
            if (mobileStore && payload) {
                mobileStore.save(payload, scanValue, { username: username || '' });
            }
        }

        function submitPayload(raw) {
            setStatus(cfg.checkingMsg || 'QR detected — checking…', 'loading');
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
                        saveBadgeOnPhone(classified.payload, raw, username);
                        if (pairToken) {
                            lockSuccessUi(cfg.pairedMsg || 'ERP is opening on your computer. You can switch back to the laptop now.', username);
                        } else if (json.redirect) {
                            lockSuccessUi('Signed in. Redirecting…', username);
                            window.location.href = json.redirect;
                        } else {
                            lockSuccessUi('Signed in.', username);
                        }
                        return;
                    }
                    if (json && (json.code === 'invalid' || json.code === 'revoked' || json.code === 'expired')) {
                        if (mobileStore) mobileStore.clear();
                    }
                    if (scanner) scanner.resetSubmit();
                    setStatus((json && json.message) || 'Scan failed.', 'error');
                })
                .catch(function () {
                    if (scanner) scanner.resetSubmit();
                    setStatus('Network error. Check connection and try again.', 'error');
                });
        }

        function tryAutoUseSavedBadge() {
            if (!pairToken || !mobileStore || skipAutoSaved || savedBadgeUsed) return;
            var saved = mobileStore.load();
            if (!saved || !saved.payload) return;
            savedBadgeUsed = true;
            var banner = document.getElementById('qr-scan-saved-banner');
            var firstSteps = document.getElementById('qr-scan-first-steps');
            if (banner) banner.classList.remove('d-none');
            if (firstSteps) firstSteps.classList.add('d-none');
            var meta = document.getElementById('qr-scan-saved-meta');
            if (meta && saved.username) {
                meta.textContent = saved.username;
            }
            setStatus(cfg.savedSigningIn || 'Signing in with saved badge…', 'loading');
            submitPayload(saved.scanValue || saved.payload);
        }

        function startCamera() {
            if (typeof Html5Qrcode === 'undefined') {
                setStatus('Scanner library failed to load. Use manual entry below or refresh.', 'error');
                return;
            }
            if (typeof RatibQrScanner === 'undefined') {
                setStatus('Scanner script missing. Use manual entry below or refresh.', 'error');
                return;
            }
            if (!scanner) {
                scanner = new RatibQrScanner({
                    elementId: 'qr-scan-viewport',
                    throttleMs: 1200,
                    onScan: submitPayload,
                    onStatus: setStatus
                });
            }
            if (startBtn) startBtn.classList.add('d-none');
            if (stopBtn) stopBtn.classList.remove('d-none');
            scanner.start().catch(function () {
                if (startBtn) startBtn.classList.remove('d-none');
                if (stopBtn) stopBtn.classList.add('d-none');
            });
        }

        if (startBtn) {
            startBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                skipAutoSaved = true;
                startCamera();
            });
        }
        if (stopBtn) {
            stopBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (scanner) scanner.stop();
                stopBtn.classList.add('d-none');
                if (startBtn) startBtn.classList.remove('d-none');
                setStatus(cfg.cameraPrompt || 'Tap Start camera and allow access when prompted.', 'info');
            });
        }
        if (manualForm) {
            manualForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                skipAutoSaved = true;
                var val = manualInput ? manualInput.value.trim() : '';
                if (!val) return;
                submitPayload(val);
            });
        }
        if (manualInput) {
            manualInput.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' && manualForm) {
                    ev.preventDefault();
                    manualForm.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        var useSavedBtn = document.getElementById('qr-scan-use-saved');
        if (useSavedBtn) {
            useSavedBtn.addEventListener('click', function () {
                if (!mobileStore) return;
                var saved = mobileStore.load();
                if (!saved) {
                    setStatus(cfg.noSavedBadge || 'No saved badge. Scan your badge once first.', 'error');
                    return;
                }
                savedBadgeUsed = true;
                setStatus(cfg.savedSigningIn || 'Signing in with saved badge…', 'loading');
                submitPayload(saved.scanValue || saved.payload);
            });
        }

        if (cfg.autoBadge) {
            setTimeout(function () { submitPayload(cfg.autoBadge); }, 300);
        } else if (pairToken && mobileStore && mobileStore.load()) {
            tryAutoUseSavedBadge();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
