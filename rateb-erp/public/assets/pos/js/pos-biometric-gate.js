(function () {
    'use strict';

    var gate = document.querySelector('[data-pos-biometric-gate]');
    if (!gate) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var config = {};
    try {
        config = JSON.parse((configEl && configEl.textContent) || '{}');
    } catch (e) {
        config = {};
    }

    var api = config.api || {};
    var i18n = config.i18n || {};
    var statusEl = gate.querySelector('[data-pos-bio-status]');
    var fpBtn = gate.querySelector('[data-pos-bio-fingerprint]');
    var faceBtn = gate.querySelector('[data-pos-bio-face]');
    var registerBtn = gate.querySelector('[data-pos-bio-register]');
    var pinInput = gate.querySelector('[data-pos-bio-pin]');
    var pinConfirm = gate.querySelector('[data-pos-bio-pin-confirm]');
    var lastCredentialId = null;

    function t(key, fb) {
        return i18n[key] || fb || key;
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function setStatus(msg, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = msg || '';
        statusEl.style.color = isError ? 'var(--pos-danger)' : '';
    }

    function readPinForEnroll() {
        var pin = (pinInput && pinInput.value) || '';
        var confirm = (pinConfirm && pinConfirm.value) || '';
        if (!pin) {
            return { ok: true, pin: null };
        }
        if (pin.length < 4) {
            return { ok: false, error: t('pos_lock_pin_too_short', 'PIN must be at least 4 digits') };
        }
        if (pinConfirm && pin !== confirm) {
            return { ok: false, error: t('pos_lock_pin_mismatch', 'PIN confirmation does not match') };
        }
        return { ok: true, pin: pin };
    }

    function writeVault(credentialId) {
        if (!window.RatebPosAuthLock || !window.RatebPosAuthLock.enrollFromGate) {
            return Promise.resolve();
        }
        var pinResult = readPinForEnroll();
        if (!pinResult.ok) {
            return Promise.reject(new Error(pinResult.error));
        }
        return window.RatebPosAuthLock.enrollFromGate({
            company_id: config.companyId,
            branch_id: config.branchId || 0,
            user_id: config.userId,
            display_name: config.displayName || '',
            credential_id: credentialId || lastCredentialId || null,
            pin: pinResult.pin
        });
    }

    function goRegister() {
        if (config.urls && config.urls.register) {
            window.location.href = config.urls.register;
            return;
        }
        try {
            var u = new URL(window.location.href);
            var next = u.pathname.replace(/\/biometric\/?$/i, '/register');
            if (!/\/register\/?$/i.test(next)) {
                next = next.replace(/\/?$/, '') + '/register';
            }
            u.pathname = next;
            window.location.href = u.href;
        } catch (eGo) {
            window.location.href = '/rateb-erp/public/admin/ops/pos/register';
        }
    }

    function fetchJson(url, options) {
        options = options || {};
        var headers = options.headers || { 'Content-Type': 'application/json', Accept: 'application/json' };
        headers['X-CSRF-Token'] = csrf();
        return fetch(url, {
            method: options.method || 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: options.body ? JSON.stringify(options.body) : null
        }).then(function (res) {
            var contentType = (res.headers.get('content-type') || '').toLowerCase();
            if (contentType.indexOf('application/json') === -1) {
                return res.text().then(function (body) {
                    var snippet = (body || '').replace(/\s+/g, ' ').trim().slice(0, 120);
                    throw new Error(
                        res.status >= 500
                            ? t('pos_biometric_failed', 'Registration failed') + ' (HTTP ' + res.status + ')'
                            : (snippet || t('pos_biometric_failed', 'Registration failed'))
                    );
                });
            }
            return res.json().then(function (data) {
                if (!res.ok || data.ok === false || data.success === false) {
                    var err = data && data.error;
                    if (err && typeof err === 'object') {
                        err = err.message || err.code || t('pos_biometric_failed', 'Verification failed');
                    }
                    throw new Error(err || t('pos_biometric_failed', 'Verification failed'));
                }
                return data;
            });
        });
    }

    function b64ToBuf(b64) {
        var bin = atob(b64.replace(/-/g, '+').replace(/_/g, '/'));
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function bufToB64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        bytes.forEach(function (b) { bin += String.fromCharCode(b); });
        return btoa(bin);
    }

    function runRegister() {
        if (!window.PublicKeyCredential || !api.registerStart || !api.registerFinish) {
            setStatus(t('pos_biometric_failed', 'WebAuthn not supported'), true);
            return;
        }
        var pinResult = readPinForEnroll();
        if (!pinResult.ok) {
            setStatus(pinResult.error, true);
            return;
        }
        setStatus(t('pos_biometric_register_loading', 'Preparing fingerprint scanner…'));
        if (registerBtn) {
            registerBtn.disabled = true;
        }
        fetchJson(api.registerStart, { body: {} })
            .then(function (data) {
                var pk = (data.options && data.options.publicKey) || data.publicKey;
                if (!pk) {
                    throw new Error(t('pos_biometric_failed', 'Registration failed'));
                }
                pk.challenge = b64ToBuf(pk.challenge);
                if (pk.user && pk.user.id) {
                    pk.user = {
                        id: b64ToBuf(pk.user.id),
                        name: pk.user.name,
                        displayName: pk.user.displayName
                    };
                }
                return navigator.credentials.create({ publicKey: pk });
            })
            .then(function (cred) {
                if (!cred) {
                    throw new Error(t('pos_biometric_failed', 'Registration failed'));
                }
                lastCredentialId = bufToB64(cred.rawId);
                var attestationObject = bufToB64(cred.response.attestationObject);
                return fetchJson(api.registerFinish, {
                    body: {
                        credentialId: lastCredentialId,
                        publicKey: attestationObject,
                        attestationObject: attestationObject,
                        clientDataJSON: bufToB64(cred.response.clientDataJSON)
                    }
                });
            })
            .then(function () {
                return writeVault(lastCredentialId);
            })
            .then(function () {
                setStatus(t('pos_biometric_register_success', 'Fingerprint registered'));
                window.location.reload();
            })
            .catch(function (err) {
                if (registerBtn) {
                    registerBtn.disabled = false;
                }
                setStatus(err.message || t('pos_biometric_failed', 'Registration failed'), true);
            });
    }

    function runFingerprint() {
        if (!window.PublicKeyCredential || !api.start || !api.finish) {
            setStatus(t('pos_biometric_failed', 'WebAuthn not supported'), true);
            return;
        }
        setStatus(t('pos_register_loading', 'Loading…'));
        fetchJson(api.start, { body: {} })
            .then(function (data) {
                var pk = (data.options && data.options.publicKey) || data.publicKey;
                if (!pk) {
                    throw new Error(t('pos_biometric_failed', 'Verification failed'));
                }
                pk.challenge = b64ToBuf(pk.challenge);
                if (pk.allowCredentials) {
                    pk.allowCredentials = pk.allowCredentials.map(function (c) {
                        return { type: c.type, id: b64ToBuf(c.id), transports: c.transports };
                    });
                }
                return navigator.credentials.get({ publicKey: pk });
            })
            .then(function (cred) {
                if (!cred) {
                    throw new Error(t('pos_biometric_failed', 'Verification failed'));
                }
                lastCredentialId = bufToB64(cred.rawId);
                return fetchJson(api.finish, {
                    body: {
                        credentialId: lastCredentialId,
                        id: lastCredentialId,
                        type: cred.type,
                        clientDataJSON: bufToB64(cred.response.clientDataJSON),
                        authenticatorData: bufToB64(cred.response.authenticatorData),
                        signature: bufToB64(cred.response.signature),
                        userHandle: cred.response.userHandle ? bufToB64(cred.response.userHandle) : null
                    }
                });
            })
            .then(function () {
                return writeVault(lastCredentialId);
            })
            .then(function () {
                setStatus(t('pos_biometric_success', 'Verified'));
                goRegister();
            })
            .catch(function (err) {
                setStatus(err.message || t('pos_biometric_failed', 'Verification failed'), true);
            });
    }

    function runFace() {
        // Face recognition is not enabled — never call the face API with stub templates.
        setStatus(
            t('pos_biometric_face_coming_soon', 'Face recognition coming soon (admin-enabled later)'),
            true
        );
    }

    // Offline: biometric page needs network — send cashiers to cached register + lock.
    if (navigator.onLine === false && window.RatebPosAuthLock) {
        window.RatebPosAuthLock.isEnrolled().then(function (enrolled) {
            if (enrolled) {
                goRegister();
            }
        });
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', runRegister);
    }
    if (fpBtn) {
        fpBtn.addEventListener('click', runFingerprint);
    }
    if (faceBtn) {
        faceBtn.disabled = true;
        faceBtn.setAttribute('aria-disabled', 'true');
        faceBtn.title = t('pos_biometric_face_coming_soon', 'Face recognition coming soon (admin-enabled later)');
        faceBtn.addEventListener('click', runFace);
    }
})();
