(function () {
    'use strict';

    function boot() {
        var cfg = window.RATEB_LOGIN_BARCODE || {};
        var passwordForm = document.getElementById('password-form');
        var barcodeForm = document.getElementById('barcode-form');
        var barcodeDesktopPanel = document.getElementById('barcode-desktop-panel');
        var barcodeMobileHint = document.getElementById('barcode-mobile-hint');
        var barcodePairQr = document.getElementById('barcode-pair-qr');
        var barcodePairWaiting = document.getElementById('barcode-pair-waiting');
        var barcodeInput = document.getElementById('barcode-input');
        var barcodeLoginForm = document.getElementById('barcode-login-form');
        var webcamStartBtn = document.getElementById('barcode-webcam-start');
        var webcamViewport = document.getElementById('barcode-webcam-viewport');
        var statusEl = document.getElementById('barcode-status');
        var pairPollTimer = null;
        var pairToken = null;
        var deviceScanner = null;
        var scriptsLoading = null;

        var isPhoneDevice = (function () {
            var ua = navigator.userAgent || '';
            var mobileUa = /Android|iPhone|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua);
            var narrow = window.matchMedia('(max-width: 820px)').matches;
            var touch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            return mobileUa || (narrow && touch);
        })();

        function showStatus(message, type) {
            if (!statusEl) return;
            statusEl.classList.remove('d-none', 'info-message', 'error-message');
            statusEl.classList.add('d-block', 'barcode-status', type === 'error' ? 'error-message' : 'info-message');
            statusEl.textContent = message;
        }

        function stopPairPolling() {
            if (pairPollTimer) {
                clearInterval(pairPollTimer);
                pairPollTimer = null;
            }
        }

        function clearPairSession() {
            stopPairPolling();
            pairToken = null;
        }

        function hideAllForms() {
            [passwordForm, barcodeForm].forEach(function (form) {
                if (form) {
                    form.classList.add('d-none');
                    form.classList.remove('d-block');
                }
            });
            clearPairSession();
            stopDeviceScanner();
        }

        function showForm(form) {
            if (form) {
                form.classList.remove('d-none');
                form.classList.add('d-block');
            }
        }

        function extractBadgeCode(raw) {
            var v = String(raw || '').trim();
            if (!v) return '';
            if (/^https?:\/\//i.test(v)) {
                try {
                    var u = new URL(v);
                    var d = u.searchParams.get('d') || u.searchParams.get('badge');
                    if (d) {
                        return extractBadgeCode(d);
                    }
                    if (/login[-\/]scan/i.test(u.pathname || '')) {
                        return '';
                    }
                } catch (e) { /* fall through */ }
            }
            if (/login[-\/]scan/i.test(v)) {
                return '';
            }
            if (/^RATEBERP:/i.test(v)) {
                v = v.replace(/^RATEBERP:/i, '');
            }
            return (v.replace(/[^A-Za-z0-9]/g, '') || '').toUpperCase();
        }

        function submitThisDevice(raw) {
            var code = extractBadgeCode(raw);
            if (!code) {
                showStatus('Enter the badge code or scan the user badge QR.', 'error');
                return;
            }
            if (barcodeInput) {
                barcodeInput.value = code;
            }
            if (barcodeLoginForm) {
                barcodeLoginForm.submit();
            }
        }

        function renderPairQr(scanUrl) {
            if (!barcodePairQr) return;
            barcodePairQr.innerHTML = '';
            var img = document.createElement('img');
            var pairSize = 190;
            var qrBase = (cfg.qrImageBase || '/scan/qr').replace(/\/$/, '');
            img.src = qrBase + '?data=' + encodeURIComponent(scanUrl) + '&size=' + pairSize;
            img.alt = 'Open phone scanner';
            img.width = pairSize;
            img.height = pairSize;
            img.className = 'barcode-login-url-qr-img';
            barcodePairQr.appendChild(img);
        }

        function completePairOnDesktop(token) {
            stopPairPolling();
            if (barcodePairWaiting) {
                barcodePairWaiting.innerHTML = '<i class="fas fa-check-circle text-success"></i> Signed in — opening…';
            }
            showStatus('Login successful. Opening your workspace…', 'info');
            var u = new URL(window.location.href);
            u.searchParams.set('barcode_pair', token);
            window.location.href = u.toString();
        }

        function apiUrl(path) {
            var p = String(path || '').trim();
            if (p.indexOf('http') === 0) return p;
            if (p.indexOf('/') === 0) return window.location.origin + p;
            return window.location.origin + '/' + p.replace(/^\//, '');
        }

        function apiPost(path, body) {
            return fetch(apiUrl(path), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(body)
            }).then(function (r) { return r.json(); });
        }

        function pollPairToken(token) {
            var apiPair = cfg.apiPair || '/api/login-barcode-pair';
            apiPost(apiPair, { action: 'poll', token: token }).then(function (json) {
                if (json && json.success && json.status === 'approved') {
                    completePairOnDesktop(token);
                } else if (json && json.status === 'expired') {
                    stopPairPolling();
                    showStatus('Session expired. Choose Barcode again.', 'error');
                }
            }).catch(function () {});
        }

        function startDesktopBarcodePair() {
            var apiPair = cfg.apiPair || '/api/login-barcode-pair';
            var scanBase = cfg.scanPage || 'login/scan';
            if (scanBase.indexOf('http') !== 0) {
                scanBase = window.location.origin + (scanBase.indexOf('/') === 0 ? scanBase : '/' + scanBase);
            }

            if (barcodePairWaiting) {
                barcodePairWaiting.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Waiting for phone scan…';
                barcodePairWaiting.classList.remove('d-none');
            }

            apiPost(apiPair, { action: 'create' }).then(function (json) {
                if (!json || !json.success || !json.token) {
                    showStatus((json && json.message) || 'Could not start barcode login.', 'error');
                    return;
                }
                pairToken = json.token;
                var scanUrl = scanBase + (scanBase.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(pairToken);
                renderPairQr(scanUrl);
                stopPairPolling();
                var activeToken = pairToken;
                pairPollTimer = setInterval(function () {
                    pollPairToken(activeToken);
                }, 1500);
                pollPairToken(activeToken);
            }).catch(function () {
                showStatus('Could not reach the server. Refresh and try again.', 'error');
            });
        }

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                if (!src) {
                    resolve();
                    return;
                }
                var existing = document.querySelector('script[src="' + src.replace(/"/g, '\\"') + '"]');
                if (existing) {
                    resolve();
                    return;
                }
                var s = document.createElement('script');
                s.src = src;
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('script')); };
                document.head.appendChild(s);
            });
        }

        function ensureScannerLibs() {
            if (typeof Html5Qrcode !== 'undefined' && typeof RATEBQrScanner !== 'undefined') {
                return Promise.resolve();
            }
            if (scriptsLoading) {
                return scriptsLoading;
            }
            scriptsLoading = loadScript(cfg.html5Qr).then(function () {
                return loadScript(cfg.scannerJs);
            });
            return scriptsLoading;
        }

        function stopDeviceScanner() {
            if (deviceScanner) {
                deviceScanner.stop();
                deviceScanner = null;
            }
            if (webcamViewport) {
                webcamViewport.classList.add('d-none');
                webcamViewport.innerHTML = '';
            }
        }

        function startDeviceCamera() {
            if (!webcamViewport) return;
            webcamViewport.classList.remove('d-none');
            showStatus('Allow camera access, then point it at the user badge.', 'info');
            ensureScannerLibs().then(function () {
                if (typeof RATEBQrScanner === 'undefined') {
                    showStatus('Camera scanner failed to load. Type the badge code instead.', 'error');
                    return;
                }
                stopDeviceScanner();
                webcamViewport.classList.remove('d-none');
                deviceScanner = new RATEBQrScanner({
                    elementId: 'barcode-webcam-viewport',
                    throttleMs: 1200,
                    onScan: function (raw) {
                        var code = extractBadgeCode(raw);
                        if (!code) {
                            showStatus('Wrong QR — scan the user badge, not the computer pairing code.', 'error');
                            if (deviceScanner) deviceScanner.resetSubmit();
                            return;
                        }
                        if (deviceScanner) deviceScanner.lock();
                        stopDeviceScanner();
                        submitThisDevice(code);
                    },
                    onStatus: showStatus
                });
                deviceScanner.start().catch(function () {
                    showStatus('Could not start the camera. Type the badge code instead.', 'error');
                });
            }).catch(function () {
                showStatus('Camera scanner failed to load. Type the badge code instead.', 'error');
            });
        }

        function focusBadgeInput() {
            if (!barcodeInput) return;
            window.setTimeout(function () {
                barcodeInput.focus();
                barcodeInput.select();
            }, 50);
        }

        function showBarcodeLoginPanel() {
            if (barcodeLoginForm) {
                barcodeLoginForm.classList.remove('d-none');
                barcodeLoginForm.removeAttribute('aria-hidden');
            }
            focusBadgeInput();
            if (isPhoneDevice) {
                if (barcodeDesktopPanel) barcodeDesktopPanel.classList.add('d-none');
                if (barcodeMobileHint) {
                    barcodeMobileHint.classList.remove('d-none');
                    barcodeMobileHint.classList.add('d-block');
                }
                var scanLink = document.getElementById('barcode-mobile-scan-link');
                if (scanLink) {
                    var scanBase = cfg.scanPage || '/login/scan';
                    if (scanBase.indexOf('http') !== 0) {
                        scanBase = window.location.origin + (scanBase.indexOf('/') === 0 ? scanBase : '/' + scanBase);
                    }
                    scanLink.href = scanBase;
                }
                clearPairSession();
                return;
            }
            if (barcodeMobileHint) barcodeMobileHint.classList.add('d-none');
            if (barcodeDesktopPanel) {
                barcodeDesktopPanel.classList.remove('d-none');
                barcodeDesktopPanel.classList.add('d-block');
            }
            startDesktopBarcodePair();
        }

        function applyLoginMethod(method) {
            hideAllForms();
            if (method === 'barcode') {
                showForm(barcodeForm);
                showBarcodeLoginPanel();
            } else {
                showForm(passwordForm);
            }
        }

        if (barcodeLoginForm) {
            barcodeLoginForm.addEventListener('submit', function () {
                if (!barcodeInput) return;
                var code = extractBadgeCode(barcodeInput.value);
                if (code) {
                    barcodeInput.value = code;
                }
            });
        }
        if (webcamStartBtn) {
            webcamStartBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                startDeviceCamera();
            });
        }

        var loginMethodButtons = Array.prototype.slice.call(document.querySelectorAll('.login-method-btn'));
        if (!loginMethodButtons.length) {
            return;
        }

        loginMethodButtons.forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                var method = btn.getAttribute('data-method') || 'password';
                loginMethodButtons.forEach(function (other) {
                    var isActive = other === btn;
                    other.classList.toggle('active', isActive);
                    other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                applyLoginMethod(method);
            });
        });

        applyLoginMethod('password');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
