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
            return res.json().then(function (data) {
                if (!res.ok || data.ok === false) {
                    throw new Error((data && data.error) ? data.error : t('pos_biometric_failed', 'Verification failed'));
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
                var attestationObject = bufToB64(cred.response.attestationObject);
                return fetchJson(api.registerFinish, {
                    body: {
                        credentialId: bufToB64(cred.rawId),
                        publicKey: attestationObject,
                        attestationObject: attestationObject
                    }
                });
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
                return fetchJson(api.finish, {
                    body: {
                        credentialId: bufToB64(cred.rawId),
                        id: bufToB64(cred.rawId),
                        type: cred.type
                    }
                });
            })
            .then(function () {
                setStatus(t('pos_biometric_success', 'Verified'));
                window.location.href = (config.urls && config.urls.register) || '/pos/register';
            })
            .catch(function (err) {
                setStatus(err.message || t('pos_biometric_failed', 'Verification failed'), true);
            });
    }

    function runFace() {
        setStatus(t('pos_biometric_face', 'Face recognition'));
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus(t('pos_biometric_failed', 'Camera not available'), true);
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function (stream) {
                stream.getTracks().forEach(function (tr) { tr.stop(); });
                var template = 'face-capture-' + Date.now();
                return fetchJson(api.face, { body: { faceTemplate: template, faceImageData: template } });
            })
            .then(function () {
                setStatus(t('pos_biometric_success', 'Verified'));
                window.location.href = (config.urls && config.urls.register) || '/pos/register';
            })
            .catch(function (err) {
                setStatus(err.message || t('pos_biometric_failed', 'Verification failed'), true);
            });
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', runRegister);
    }
    if (fpBtn) {
        fpBtn.addEventListener('click', runFingerprint);
    }
    if (faceBtn) {
        faceBtn.addEventListener('click', runFace);
    }
})();
