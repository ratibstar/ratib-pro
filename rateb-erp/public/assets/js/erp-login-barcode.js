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
        var statusEl = document.getElementById('barcode-status');
        var pairPollTimer = null;
        var pairToken = null;

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
        }

        function showForm(form) {
            if (form) {
                form.classList.remove('d-none');
                form.classList.add('d-block');
            }
        }

        function renderPairQr(scanUrl) {
            if (!barcodePairQr) return;
            barcodePairQr.innerHTML = '';
            var img = document.createElement('img');
            var pairSize = 190;
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + pairSize + 'x' + pairSize
                + '&margin=18&ecc=H&color=000000&bgcolor=ffffff&data=' + encodeURIComponent(scanUrl);
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

            showStatus('Preparing phone scanner…', 'info');
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
                showStatus('Scan the QR with your phone, then scan your user badge.', 'info');
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

        function showBarcodeLoginPanel() {
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
