(function () {
    var root = document.getElementById('tracking-onboarding-page');
    if (!root) return;
    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase) return;

    var workerIdEl = document.getElementById('onbWorkerId');
    var tenantIdEl = document.getElementById('onbTenantId');
    var deviceIdEl = document.getElementById('onbDeviceId');
    var identityEl = document.getElementById('onbIdentity');
    var passwordEl = document.getElementById('onbPassword');
    var generateBtn = document.getElementById('onbGenerateBtn');
    var qrWrap = document.getElementById('onbQr');
    var flashEl = document.getElementById('onbFlash');

    function flash(msg, ok) {
        if (!flashEl) return;
        flashEl.textContent = msg;
        flashEl.className = 'alert mt-2 ' + (ok ? 'alert-success' : 'alert-danger');
        flashEl.classList.remove('d-none');
        setTimeout(function () { flashEl.classList.add('d-none'); }, 4000);
    }

    function renderPayload(data) {
        if (qrWrap) qrWrap.innerHTML = '';
        if (!qrWrap) return;
        var qrText = (data && data.onboarding_url) ? String(data.onboarding_url) : '';
        if (!qrText) {
            flash('Missing onboarding URL', false);
            return;
        }

        if (window.QRCode && typeof QRCode.toCanvas === 'function') {
            QRCode.toCanvas(qrText, { width: 380, margin: 2, errorCorrectionLevel: 'M' }, function (err, canvas) {
                if (err) {
                    renderQrFallback(qrText);
                    return;
                }
                qrWrap.appendChild(canvas);
                renderQrNote('Scan with phone camera or mobile app.');
            });
            return;
        }
        renderQrFallback(qrText);
    }

    function renderQrFallback(payload) {
        if (!qrWrap) return;
        var img = document.createElement('img');
        img.alt = 'Worker onboarding QR';
        img.src = 'https://quickchart.io/qr?size=420&ecLevel=M&text=' + encodeURIComponent(payload);
        img.loading = 'lazy';
        img.referrerPolicy = 'no-referrer';
        img.onerror = function () {
            renderQrNote('QR library/CDN blocked. Please allow CDN access and retry.');
        };
        qrWrap.appendChild(img);
        renderQrNote('If image does not load, check CDN access and try again.');
    }

    function renderQrNote(msg) {
        if (!qrWrap) return;
        var p = document.createElement('p');
        p.className = 'onb-qr-note';
        p.textContent = msg;
        qrWrap.appendChild(p);
    }

    generateBtn && generateBtn.addEventListener('click', function () {
        var workerRaw = ((workerIdEl && workerIdEl.value) || '').trim();
        var tenantId = parseInt((tenantIdEl && tenantIdEl.value) || '0', 10);
        var deviceId = (deviceIdEl && deviceIdEl.value) ? String(deviceIdEl.value).trim() : '';
        var identity = (identityEl && identityEl.value) ? String(identityEl.value).trim() : '';
        var password = (passwordEl && passwordEl.value) ? String(passwordEl.value).trim() : '';
        if (!workerRaw) {
            flash('worker_id required', false);
            return;
        }
        generateBtn.disabled = true;
        fetch(apiBase + '/worker-tracking-onboarding.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                worker_id: workerRaw,
                tenant_id: tenantId || undefined,
                device_id: deviceId || undefined,
                identity: identity || undefined,
                password: password || undefined
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success || !res.data) {
                throw new Error(res.message || 'Failed to generate');
            }
            renderPayload(res.data);
            flash('QR generated', true);
        })
        .catch(function (e) {
            flash(e.message || 'Error', false);
        })
        .finally(function () {
            generateBtn.disabled = false;
        });
    });

})();
