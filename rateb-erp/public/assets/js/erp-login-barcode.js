(function () {
    'use strict';

    var cfg = window.RATEB_LOGIN_BARCODE || {};
    var methodSelect = document.getElementById('login-method');
    var passwordForm = document.getElementById('password-form');
    var barcodeForm = document.getElementById('barcode-form');
    var pairQr = document.getElementById('barcode-pair-qr');
    var pairWaiting = document.getElementById('barcode-pair-waiting');
    var statusEl = document.getElementById('barcode-status');
    var pairPollTimer = null;
    var pairToken = null;

    function showStatus(msg, type) {
        if (!statusEl) return;
        statusEl.classList.remove('d-none', 'text-danger', 'text-success', 'text-muted');
        statusEl.classList.add(type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted'));
        statusEl.textContent = msg;
    }

    function stopPolling() {
        if (pairPollTimer) {
            clearInterval(pairPollTimer);
            pairPollTimer = null;
        }
    }

    function renderQr(scanUrl) {
        if (!pairQr) return;
        pairQr.innerHTML = '';
        var img = document.createElement('img');
        img.className = 'rateb-login-qr-img';
        img.alt = 'QR';
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(scanUrl);
        pairQr.appendChild(img);
    }

    function apiPost(body) {
        return fetch(cfg.apiPair || '/api/login-barcode-pair', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function startPair() {
        stopPolling();
        pairToken = null;
        if (pairWaiting) pairWaiting.classList.add('d-none');
        apiPost({ action: 'create' }).then(function (json) {
            if (!json || !json.success || !json.token) {
                showStatus((json && json.message) || 'Could not start barcode login.', 'error');
                return;
            }
            pairToken = json.token;
            var scanBase = cfg.scanPage || 'login/scan';
            var scanUrl = scanBase + (scanBase.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(pairToken);
            renderQr(scanUrl);
            if (pairWaiting) pairWaiting.classList.remove('d-none');
            pairPollTimer = setInterval(function () {
                apiPost({ action: 'poll', token: pairToken }).then(function (poll) {
                    if (!poll || poll.status === 'expired') {
                        stopPolling();
                        showStatus('Session expired. Choose Barcode again.', 'error');
                        return;
                    }
                    if (poll.status === 'complete' && poll.redirect) {
                        stopPolling();
                        window.location.href = poll.redirect;
                    }
                }).catch(function () {});
            }, 2000);
        }).catch(function () {
            showStatus('Network error.', 'error');
        });
    }

    function togglePanels() {
        var method = methodSelect ? methodSelect.value : 'password';
        if (passwordForm) {
            passwordForm.classList.toggle('d-none', method !== 'password');
        }
        if (barcodeForm) {
            barcodeForm.classList.toggle('d-none', method !== 'barcode');
        }
        if (method === 'barcode') {
            startPair();
            var input = document.getElementById('barcode-input');
            if (input) setTimeout(function () { input.focus(); }, 100);
        } else {
            stopPolling();
        }
    }

    if (methodSelect) {
        methodSelect.addEventListener('change', togglePanels);
    }
    togglePanels();
})();
