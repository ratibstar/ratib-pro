(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root) {
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
    var modal = root.querySelector('[data-pos-supervisor-modal]');
    var scanBtn = root.querySelector('[data-pos-supervisor-scan]');
    var hintEl = root.querySelector('[data-pos-supervisor-hint]');
    var stockModal = root.querySelector('[data-pos-stock-modal]');
    var offlineBlock = root.querySelector('[data-pos-supervisor-offline]');
    var pinInput = root.querySelector('[data-pos-supervisor-pin]');
    var pinSubmit = root.querySelector('[data-pos-supervisor-pin-submit]');
    var pending = null;
    var offlineNoPin = false;

    /** Actions that only mutate local UI and can be approved offline. */
    var OFFLINE_LOCAL_ACTIONS = {
        cancel_invoice: true
    };

    var arFallback = {
        pos_supervisor_offline_hint: 'وضع عدم الاتصال: أكّد الإلغاء برمز PIN المحلي، أو أكّد مباشرة إن لم يُعيَّن رمز.',
        pos_supervisor_offline_confirm: 'تأكيد الإلغاء أوفلاين',
        pos_supervisor_offline_blocked: 'هذا الإجراء يحتاج اتصالاً لاعتماد المشرف.',
        pos_lock_pin: 'رمز PIN',
        pos_lock_pin_required: 'أدخل رمز PIN',
        pos_lock_pin_invalid: 'رمز PIN غير صحيح',
        pos_supervisor_scan_hint: 'يُرجى مسح بصمة المشرف للمتابعة'
    };

    function t(key, fb) {
        if (i18n[key]) {
            return i18n[key];
        }
        var locale = String(config.locale || document.documentElement.lang || '').toLowerCase();
        if ((locale.indexOf('ar') === 0 || document.documentElement.dir === 'rtl') && arFallback[key]) {
            return arFallback[key];
        }
        return fb || key;
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function isOffline() {
        if (window.RatebPosNet && typeof window.RatebPosNet.isOnline === 'function') {
            return !window.RatebPosNet.isOnline();
        }
        if (window.RatebPosConnectivity && typeof window.RatebPosConnectivity.isOnline === 'function') {
            return !window.RatebPosConnectivity.isOnline();
        }
        return navigator.onLine === false;
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
        }
    }

    function ensureOfflineControls() {
        if (offlineBlock && pinSubmit) {
            return;
        }
        var scanHost = root.querySelector('.rateb-pos__supervisor-scan');
        if (!scanHost) {
            return;
        }
        if (!offlineBlock) {
            offlineBlock = document.createElement('div');
            offlineBlock.className = 'rateb-pos__supervisor-offline';
            offlineBlock.setAttribute('data-pos-supervisor-offline', '');
            offlineBlock.hidden = true;
            offlineBlock.innerHTML =
                '<label class="rateb-pos__field-label" for="rateb-pos-supervisor-pin-dyn">' + t('pos_lock_pin', 'PIN') + '</label>' +
                '<input type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="12" ' +
                'class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-supervisor-pin-dyn" data-pos-supervisor-pin />' +
                '<button type="button" class="rateb-pos__biometric-btn" data-pos-supervisor-pin-submit>' +
                t('pos_supervisor_offline_confirm', 'Confirm offline cancel') + '</button>';
            scanHost.appendChild(offlineBlock);
            pinInput = offlineBlock.querySelector('[data-pos-supervisor-pin]');
            pinSubmit = offlineBlock.querySelector('[data-pos-supervisor-pin-submit]');
            if (pinSubmit) {
                pinSubmit.addEventListener('click', function () {
                    completeOfflineApproval();
                });
            }
            if (pinInput) {
                pinInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        completeOfflineApproval();
                    }
                });
            }
        }
    }

    function setOfflineUi(showOffline) {
        ensureOfflineControls();
        if (scanBtn) {
            scanBtn.hidden = !!showOffline;
        }
        if (offlineBlock) {
            offlineBlock.hidden = !showOffline;
        }
        if (pinInput) {
            pinInput.value = '';
            pinInput.hidden = !!offlineNoPin;
            var label = offlineBlock ? offlineBlock.querySelector('label') : null;
            if (label) {
                label.hidden = !!offlineNoPin;
            }
        }
        if (pinSubmit) {
            pinSubmit.textContent = t('pos_supervisor_offline_confirm', 'Confirm offline cancel');
        }
    }

    function modalOpen(show, offlineMode) {
        if (!modal) {
            return;
        }
        setOfflineUi(!!offlineMode);
        modal.hidden = !show;
        if (show && offlineMode && pinInput) {
            setTimeout(function () {
                try {
                    pinInput.focus();
                } catch (e) { /* ignore */ }
            }, 50);
        }
    }

    function finishGranted(token, requestId) {
        var cb = pending && pending.onGranted;
        modalOpen(false, false);
        pending = null;
        if (typeof cb === 'function') {
            cb(token || '', requestId || 0);
        }
    }

    function fetchJson(url, options) {
        options = options || {};
        // Never hit the network while offline — SW returns 503 for JSON POSTs and spams the console.
        if (isOffline() && !options.allowOffline) {
            return Promise.reject(new Error('offline'));
        }
        var headers = options.headers || {};
        if (options.json) {
            headers['Content-Type'] = 'application/json';
        }
        headers.Accept = 'application/json';
        headers['X-CSRF-Token'] = csrf();
        if (options.extraHeaders) {
            Object.keys(options.extraHeaders).forEach(function (k) {
                headers[k] = options.extraHeaders[k];
            });
        }
        return fetch(url, {
            method: options.method || 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: options.body || null
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || data.ok === false) {
                    var err = data && data.error;
                    if (err && typeof err === 'object') {
                        err = err.message || err.code || '';
                    }
                    if (res.status === 503 || (data && data.offline)) {
                        throw new Error('offline');
                    }
                    throw new Error(err || t('invalid_request', 'Failed'));
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

    function supervisorScan() {
        if (!api.biometricStart || !api.approvalGrant) {
            notify(t('invalid_request', 'Not configured'), true);
            return Promise.reject();
        }
        return fetchJson(api.biometricStart, {
            json: true,
            body: JSON.stringify({ supervisor: true })
        }).then(function (data) {
            var pk = (data.options && data.options.publicKey) || data.publicKey;
            if (!pk || !window.PublicKeyCredential) {
                throw new Error(t('pos_biometric_failed', 'WebAuthn not supported'));
            }
            pk.challenge = b64ToBuf(pk.challenge);
            if (pk.allowCredentials) {
                pk.allowCredentials = pk.allowCredentials.map(function (c) {
                    return { type: c.type, id: b64ToBuf(c.id), transports: c.transports };
                });
            }
            return navigator.credentials.get({ publicKey: pk });
        }).then(function (cred) {
            if (!cred || !pending) {
                throw new Error(t('pos_biometric_failed', 'Verification failed'));
            }
            return fetchJson(api.approvalGrant, {
                json: true,
                body: JSON.stringify({
                    approval_request_id: pending.requestId,
                    credentialId: bufToB64(cred.rawId),
                    id: bufToB64(cred.rawId),
                    method: 'webauthn',
                    clientDataJSON: bufToB64(cred.response.clientDataJSON),
                    authenticatorData: bufToB64(cred.response.authenticatorData),
                    signature: bufToB64(cred.response.signature),
                    userHandle: cred.response.userHandle ? bufToB64(cred.response.userHandle) : null
                })
            });
        }).then(function (grant) {
            finishGranted(grant.approval_token, pending && pending.requestId);
        });
    }

    function completeOfflineApproval() {
        if (!pending || !pending.offline) {
            return;
        }
        var lock = window.RatebPosAuthLock;
        var pin = pinInput ? String(pinInput.value || '') : '';

        function grantOffline() {
            finishGranted('offline:' + (pending.actionType || 'local'), 0);
        }

        if (offlineNoPin || !lock || typeof lock.hasPinEnrolled !== 'function') {
            grantOffline();
            return;
        }

        lock.hasPinEnrolled().then(function (hasPin) {
            if (!hasPin) {
                grantOffline();
                return;
            }
            if (!pin) {
                notify(t('pos_lock_pin_required', 'Enter PIN'), true);
                return;
            }
            return lock.verifyPinOnly(pin).then(function () {
                grantOffline();
            });
        }).catch(function (err) {
            notify((err && err.message) || t('pos_lock_pin_invalid', 'Incorrect PIN'), true);
        });
    }

    function requireApprovalOffline(actionType, payload, onGranted) {
        if (!OFFLINE_LOCAL_ACTIONS[actionType]) {
            notify(t('pos_supervisor_offline_blocked', 'This action needs a network connection for supervisor approval.'), true);
            return;
        }
        pending = {
            requestId: 0,
            actionType: actionType,
            onGranted: onGranted,
            offline: true
        };
        offlineNoPin = false;
        if (hintEl) {
            hintEl.textContent = t('pos_supervisor_offline_hint', 'Offline: confirm with local PIN or confirm directly if no PIN is set.');
        }
        ensureOfflineControls();

        function openWithPinState(hasPin) {
            offlineNoPin = !hasPin;
            if (hintEl && !hasPin) {
                hintEl.textContent = t('pos_supervisor_offline_confirm', 'Confirm offline cancel');
            }
            if (pinSubmit) {
                pinSubmit.textContent = t('pos_supervisor_offline_confirm', 'Confirm offline cancel');
            }
            modalOpen(true, true);
        }

        var lock = window.RatebPosAuthLock;
        if (lock && typeof lock.hasPinEnrolled === 'function') {
            lock.hasPinEnrolled().then(function (hasPin) {
                openWithPinState(!!hasPin);
            }).catch(function () {
                openWithPinState(false);
            });
            return;
        }
        openWithPinState(false);
    }

    function requireApproval(actionType, payload, onGranted) {
        if (!api.approvalRequest) {
            if (typeof onGranted === 'function') {
                onGranted('', 0);
            }
            return;
        }
        if (isOffline()) {
            requireApprovalOffline(actionType, payload, onGranted);
            return;
        }
        fetchJson(api.approvalRequest, {
            json: true,
            body: JSON.stringify({ action_type: actionType, payload: payload || {} })
        }).then(function (data) {
            pending = {
                requestId: data.approval_request_id,
                actionType: actionType,
                onGranted: onGranted,
                offline: false
            };
            if (hintEl) {
                hintEl.textContent = t('pos_supervisor_scan_hint', 'Supervisor fingerprint required');
            }
            modalOpen(true, false);
        }).catch(function (err) {
            var msg = (err && err.message) || '';
            if (isOffline() || /Failed to fetch|NetworkError|ERR_INTERNET|ERR_FAILED|offline/i.test(msg)) {
                requireApprovalOffline(actionType, payload, onGranted);
                return;
            }
            notify(msg || t('invalid_request', 'Failed'), true);
        });
    }

    window.RatebPosRequireApproval = requireApproval;
    window.RatebPosApprovalHeaders = function (token) {
        return token ? { 'X-Pos-Approval-Token': token } : {};
    };

    if (scanBtn) {
        scanBtn.addEventListener('click', function () {
            supervisorScan().catch(function (err) {
                notify(err.message, true);
            });
        });
    }

    if (pinSubmit) {
        pinSubmit.addEventListener('click', function () {
            completeOfflineApproval();
        });
    }
    if (pinInput) {
        pinInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                completeOfflineApproval();
            }
        });
    }

    root.querySelectorAll('[data-pos-supervisor-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modalOpen(false, false);
            pending = null;
        });
    });

    function bindStockModal() {
        var openBtns = root.querySelectorAll('[data-pos-stock-open]');
        var productEl = root.querySelector('[data-pos-stock-product]');
        var invIdEl = root.querySelector('[data-pos-stock-inventory-id]');
        var deltaEl = root.querySelector('[data-pos-stock-delta]');
        var reasonEl = root.querySelector('[data-pos-stock-reason]');
        var saveBtn = root.querySelector('[data-pos-stock-save]');

        function openStock() {
            var st = window.RatebPosRegisterState || { selectedLineId: null, lines: [] };
            var line = (st.lines || []).find(function (l) { return l.id === st.selectedLineId; });
            if (!line && st.lines && st.lines.length) {
                line = st.lines[st.lines.length - 1];
            }
            if (productEl) {
                productEl.value = line ? (line.item_name || '') : '';
            }
            if (invIdEl) {
                invIdEl.value = line ? String(line.product_id || '') : '';
            }
            if (deltaEl) {
                deltaEl.value = '0';
            }
            if (stockModal) {
                stockModal.hidden = false;
            }
        }

        openBtns.forEach(function (btn) {
            btn.addEventListener('click', openStock);
        });

        root.querySelectorAll('[data-pos-stock-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (stockModal) {
                    stockModal.hidden = true;
                }
            });
        });

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var inventoryId = invIdEl ? Number(invIdEl.value || 0) : 0;
                var delta = deltaEl ? Number(deltaEl.value || 0) : 0;
                var reason = reasonEl ? String(reasonEl.value || '') : '';
                if (inventoryId < 1 || !delta) {
                    notify(t('invalid_request', 'Invalid'), true);
                    return;
                }
                requireApproval('stock_adjustment', { inventory_id: inventoryId, delta: delta, reason: reason }, function (token, requestId) {
                    if (!api.inventoryAdjust) {
                        notify(t('saved', 'Saved'));
                        if (stockModal) {
                            stockModal.hidden = true;
                        }
                        return;
                    }
                    fetch(api.inventoryAdjust, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf(),
                            'X-Pos-Approval-Token': token
                        },
                        body: JSON.stringify({
                            inventory_id: inventoryId,
                            delta: delta,
                            quantity_delta: delta,
                            reason: reason
                        })
                    }).then(function (res) {
                        return res.json().then(function (data) {
                            if (!res.ok || data.ok === false) {
                                throw new Error((data && data.error) ? data.error : t('invalid_request', 'Failed'));
                            }
                            return data;
                        });
                    }).then(function () {
                        notify(t('saved', 'Saved'));
                        if (stockModal) {
                            stockModal.hidden = true;
                        }
                    }).catch(function (err) {
                        notify(err.message, true);
                    });
                });
            });
        }
    }

    bindStockModal();

    var drawerForm = root.querySelector('[data-pos-drawer-event-form]');
    if (drawerForm) {
        drawerForm.addEventListener('submit', function (e) {
            var typeEl = root.querySelector('[data-pos-drawer-event-type]');
            if (!typeEl || typeEl.value !== 'pay_out') {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            requireApproval('drawer_pay_out', { amount: root.querySelector('[data-pos-drawer-event-amount]')?.value }, function () {
                drawerForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            });
        }, true);
    }
})();
