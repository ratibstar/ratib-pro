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
    var pending = null;

    function t(key, fb) {
        return i18n[key] || fb || key;
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
        }
    }

    function modalOpen(show) {
        if (!modal) {
            return;
        }
        modal.hidden = !show;
    }

    function fetchJson(url, options) {
        options = options || {};
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
                    throw new Error((data && data.error) ? data.error : t('invalid_request', 'Failed'));
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
                    method: 'webauthn'
                })
            });
        }).then(function (grant) {
            pending.token = grant.approval_token;
            modalOpen(false);
            if (typeof pending.onGranted === 'function') {
                pending.onGranted(pending.token, pending.requestId);
            }
            pending = null;
        });
    }

    function requireApproval(actionType, payload, onGranted) {
        if (!api.approvalRequest) {
            if (typeof onGranted === 'function') {
                onGranted('', 0);
            }
            return;
        }
        fetchJson(api.approvalRequest, {
            json: true,
            body: JSON.stringify({ action_type: actionType, payload: payload || {} })
        }).then(function (data) {
            pending = {
                requestId: data.approval_request_id,
                onGranted: onGranted
            };
            if (hintEl) {
                hintEl.textContent = t('pos_supervisor_scan_hint', 'Supervisor fingerprint required');
            }
            modalOpen(true);
        }).catch(function (err) {
            notify(err.message, true);
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

    root.querySelectorAll('[data-pos-supervisor-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modalOpen(false);
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
