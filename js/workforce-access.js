/**
 * Workforce QR identity admin panel (System Settings → Users).
 */
(function (global) {
    'use strict';

    function apiPath() {
        if (typeof getSettingsApiPathModernForms === 'function') {
            return getSettingsApiPathModernForms();
        }
        return '/api/settings/settings-api.php';
    }

    async function apiCall(action, userId, extra) {
        const payload = Object.assign({ action: action, table: 'users', id: userId }, extra || {});
        const res = await fetch(apiPath(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            cache: 'no-store',
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success === false) {
            throw new Error(json.message || 'Request failed');
        }
        return json;
    }

    function el(id) {
        return document.getElementById(id);
    }

    function statusClass(s) {
        const map = { active: 'active', revoked: 'revoked', expired: 'expired' };
        return 'wf-status-badge wf-status-badge--' + (map[s] || 'none');
    }

    function renderQr(qrHost, payload) {
        if (!qrHost) {
            return;
        }
        qrHost.innerHTML = '';
        if (!payload) {
            qrHost.innerHTML = '<p class="wf-meta mb-0">No QR displayed. Generate or regenerate to show credential once.</p>';
            return;
        }
        let scanValue = payload;
        if (/^RATIBLOGIN:/i.test(payload) && global.location && global.location.origin) {
            scanValue = global.location.origin + '/login/badge?d=' + encodeURIComponent(payload);
        }
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrHost, {
                text: scanValue,
                width: 220,
                height: 220,
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    }

    async function loadLibs() {
        if (typeof QRCode !== 'undefined') {
            return;
        }
        await new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function renderStatus(data) {
        const wf = data.workforce || data;
        const statusEl = el('wf-qr-status-badge');
        const lastEl = el('wf-qr-last-used');
        const expEl = el('wf-qr-expires');
        const pinToggle = el('wf-pin-enabled');
        const qrEnabled = el('wf-qr-enabled');
        if (statusEl) {
            statusEl.className = statusClass(wf.qr_status || 'none');
            statusEl.textContent = (wf.qr_status || 'none').replace('_', ' ');
        }
        if (lastEl) {
            lastEl.textContent = wf.last_used_at || '—';
        }
        if (expEl) {
            const ex = wf.expires_at || '';
            expEl.textContent = ex.indexOf('2099') === 0 ? 'Persistent (no expiry)' : (ex || '—');
        }
        if (pinToggle) {
            pinToggle.checked = !!wf.qr_pin_enabled;
        }
        if (qrEnabled) {
            qrEnabled.checked = wf.qr_login_enabled !== false;
        }
        const devList = el('wf-device-list');
        if (devList) {
            const devices = wf.trusted_devices || [];
            if (!devices.length) {
                devList.innerHTML = '<li class="wf-meta">No trusted devices</li>';
            } else {
                devList.innerHTML = devices.map(function (d) {
                    const label = d.label || d.ip || 'Device';
                    const state = d.active ? 'Active' : 'Expired';
                    return '<li><span>' + label + ' · ' + state + '</span>' +
                        (d.active ? '<button type="button" class="modern-btn modern-btn-sm modern-btn-secondary" data-device-id="' + d.id + '">Revoke</button>' : '') +
                        '</li>';
                }).join('');
            }
        }
        const auditEl = el('wf-audit-list');
        if (auditEl) {
            const rows = wf.audit_recent || [];
            auditEl.innerHTML = rows.length
                ? rows.map(function (r) {
                    return '<div>' + (r.created_at || '') + ' · ' + (r.event_type || '') + ' · ' + (r.outcome || '') + '</div>';
                }).join('')
                : '<div class="wf-meta">No recent events</div>';
        }
    }

    const WorkforceAccess = {
        userId: 0,
        username: '',

        async open(userId, username) {
            this.userId = userId;
            this.username = username || '';
            const modal = el('workforceAccessModal');
            if (!modal) {
                return;
            }
            const label = el('wf-access-user-label');
            if (label) {
                label.textContent = username ? ('User: ' + username) : '';
            }
            modal.classList.remove('modal-hidden');
            modal.classList.add('show');
            await this.refresh();
        },

        close() {
            const modal = el('workforceAccessModal');
            if (modal) {
                modal.classList.add('modal-hidden');
                modal.classList.remove('show');
            }
        },

        async refresh() {
            const qrHost = el('wf-qr-host');
            if (qrHost) {
                qrHost.innerHTML = '<p class="wf-meta">Loading…</p>';
            }
            try {
                const res = await apiCall('workforce_qr_status', this.userId);
                const data = res.data || {};
                renderStatus(data);
                renderQr(qrHost, null);
            } catch (e) {
                if (qrHost) {
                    qrHost.innerHTML = '<p class="text-danger">' + (e.message || 'Load failed') + '</p>';
                }
            }
        },

        async generate() {
            await loadLibs();
            const res = await apiCall('workforce_qr_generate', this.userId);
            const data = res.data || {};
            renderStatus(data.workforce || {});
            renderQr(el('wf-qr-host'), data.qr_payload || null);
        },

        async regenerate() {
            if (!global.confirm('Regenerate will invalidate the previous badge. Continue?')) {
                return;
            }
            await loadLibs();
            const res = await apiCall('workforce_qr_regenerate', this.userId);
            const data = res.data || {};
            renderStatus(data.workforce || {});
            renderQr(el('wf-qr-host'), data.qr_payload || null);
        },

        async revoke() {
            if (!global.confirm('Revoke workforce QR access for this user?')) {
                return;
            }
            const res = await apiCall('workforce_qr_revoke', this.userId);
            renderStatus(res.data || {});
            renderQr(el('wf-qr-host'), null);
        },

        async savePin() {
            const enabled = el('wf-pin-enabled') && el('wf-pin-enabled').checked;
            const pinInput = el('wf-pin-value');
            const pin = pinInput ? pinInput.value : '';
            await apiCall('workforce_qr_set_pin', this.userId, { enabled: enabled, pin: pin });
            if (pinInput) {
                pinInput.value = '';
            }
            await this.refresh();
        },

        async saveEnabled() {
            const enabled = el('wf-qr-enabled') && el('wf-qr-enabled').checked;
            await apiCall('workforce_qr_set_enabled', this.userId, { enabled: enabled });
            await this.refresh();
        },

        openBadge() {
            const base = typeof pageUrl === 'function'
                ? pageUrl('workforce-badge.php')
                : '/pages/workforce-badge.php';
            global.open(base + '?user_id=' + encodeURIComponent(String(this.userId)), '_blank');
        },

        downloadPng() {
            const canvas = document.querySelector('#wf-qr-host canvas');
            if (!canvas) {
                global.alert('Generate QR first.');
                return;
            }
            const a = document.createElement('a');
            a.download = 'rateb-workforce-badge-' + this.userId + '.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
        },

        printBadge() {
            this.openBadge();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const modal = el('workforceAccessModal');
        if (!modal) {
            return;
        }
        modal.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-wf-action]');
            if (!btn) {
                if (e.target === modal) {
                    WorkforceAccess.close();
                }
                return;
            }
            const act = btn.getAttribute('data-wf-action');
            if (act === 'close') {
                WorkforceAccess.close();
            } else if (act === 'generate') {
                WorkforceAccess.generate();
            } else if (act === 'regenerate') {
                WorkforceAccess.regenerate();
            } else if (act === 'revoke') {
                WorkforceAccess.revoke();
            } else if (act === 'save-pin') {
                WorkforceAccess.savePin();
            } else if (act === 'save-enabled') {
                WorkforceAccess.saveEnabled();
            } else if (act === 'open-badge') {
                WorkforceAccess.openBadge();
            } else if (act === 'download-png') {
                WorkforceAccess.downloadPng();
            } else if (act === 'print') {
                WorkforceAccess.printBadge();
            }
        });
        const devList = el('wf-device-list');
        if (devList) {
            devList.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-device-id]');
                if (!btn) {
                    return;
                }
                apiCall('workforce_revoke_device', WorkforceAccess.userId, {
                    device_id: parseInt(btn.getAttribute('data-device-id'), 10)
                }).then(function () {
                    return WorkforceAccess.refresh();
                }).catch(function (err) {
                    global.alert(err.message || 'Failed');
                });
            });
        }
    });

    global.WorkforceAccess = WorkforceAccess;
})(window);
