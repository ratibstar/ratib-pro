/**
 * Workforce QR identity admin panel (System Settings → Users).
 */
(function (global) {
    'use strict';

    var STORAGE_PREFIX = 'ratib_wf_qr_';

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

    function storageKey(userId) {
        return STORAGE_PREFIX + String(userId);
    }

    function tenantContext() {
        const cfg = global.RATIB_WORKFORCE_CTX || {};
        const params = new URLSearchParams(global.location.search || '');
        return {
            agencyId: parseInt(cfg.agencyId, 10) || parseInt(params.get('agency_id'), 10) || 0,
            countryId: parseInt(cfg.countryId, 10) || parseInt(params.get('country_id'), 10) || 0,
            countrySlug: (cfg.countrySlug || params.get('country_slug') || '').trim()
        };
    }

    function payloadToScanValue(payload) {
        if (!payload) {
            return '';
        }
        if (/^https?:\/\//i.test(payload)) {
            return payload;
        }
        if (/^RATIBLOGIN:/i.test(payload) && global.location && global.location.origin) {
            const q = new URLSearchParams();
            q.set('d', payload);
            const tenant = tenantContext();
            if (tenant.agencyId > 0) {
                q.set('agency_id', String(tenant.agencyId));
            }
            if (tenant.countryId > 0) {
                q.set('country_id', String(tenant.countryId));
            }
            if (tenant.countrySlug) {
                q.set('country_slug', tenant.countrySlug);
            }
            return global.location.origin + '/login/badge?' + q.toString();
        }
        return payload;
    }

    function renderQr(qrHost, payload, options) {
        if (!qrHost) {
            return;
        }
        const opts = options || {};
        qrHost.classList.add('ratib-qr-host--readable');
        if (!payload) {
            qrHost.classList.remove('ratib-qr-host--readable');
            if (opts.activeCredential) {
                qrHost.innerHTML = '<p class="wf-meta mb-0">Credential is active. Click <strong>Regenerate</strong> to display a new QR, or use <strong>Print badge</strong>.</p>';
            } else {
                qrHost.innerHTML = '<p class="wf-meta mb-0">No QR yet. Click <strong>Generate QR</strong>.</p>';
            }
            return;
        }
        const scanValue = payloadToScanValue(payload);
        let size = opts.fullscreen ? 260 : 190;
        if (opts.fullscreen && opts.qrSize) {
            size = opts.qrSize;
        }
        if (typeof global.ratibRenderQrImage === 'function') {
            global.ratibRenderQrImage(qrHost, scanValue, size);
        } else {
            qrHost.innerHTML = '<img class="ratib-qr-image" src="https://api.qrserver.com/v1/create-qr-code/?size='
                + size + 'x' + size + '&margin=18&ecc=H&color=000000&bgcolor=ffffff&data='
                + encodeURIComponent(scanValue) + '" width="' + size + '" height="' + size + '" alt="QR">';
        }
        if (!opts.fullscreen) {
            var hint = document.createElement('p');
            hint.className = 'wf-meta mb-0 mt-2';
            hint.textContent = 'Same style as login QR — scan with phone camera or copy badge link.';
            qrHost.appendChild(hint);
        }
    }

    async function loadLibs() {
        return Promise.resolve();
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
        return wf;
    }

    const WorkforceAccess = {
        userId: 0,
        username: '',
        displayedPayload: '',

        rememberPayload(payload) {
            if (!payload || !this.userId) {
                return;
            }
            this.displayedPayload = payload;
            try {
                sessionStorage.setItem(storageKey(this.userId), payload);
            } catch (e) {
                /* ignore */
            }
        },

        loadStoredPayload() {
            if (this.displayedPayload) {
                return this.displayedPayload;
            }
            try {
                return sessionStorage.getItem(storageKey(this.userId)) || '';
            } catch (e) {
                return '';
            }
        },

        clearStoredPayload() {
            this.displayedPayload = '';
            try {
                sessionStorage.removeItem(storageKey(this.userId));
            } catch (e) {
                /* ignore */
            }
        },

        applyQrDisplay(wf) {
            const qrHost = el('wf-qr-host');
            const status = (wf && wf.qr_status) ? wf.qr_status : 'none';
            const stored = this.loadStoredPayload();
            if (status === 'revoked' || status === 'none') {
                this.clearStoredPayload();
                renderQr(qrHost, null, { activeCredential: false });
                return;
            }
            if (stored) {
                renderQr(qrHost, stored, { activeCredential: status === 'active' });
                return;
            }
            renderQr(qrHost, null, { activeCredential: status === 'active' });
        },

        async open(userId, username) {
            this.userId = userId;
            this.username = username || '';
            this.displayedPayload = this.loadStoredPayload();
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
            await this.refresh(false);
        },

        close() {
            const modal = el('workforceAccessModal');
            if (modal) {
                modal.classList.add('modal-hidden');
                modal.classList.remove('show');
            }
        },

        async refresh(clearQr) {
            const qrHost = el('wf-qr-host');
            const keepQr = clearQr !== true;
            if (qrHost && !keepQr) {
                qrHost.innerHTML = '<p class="wf-meta">Loading…</p>';
            }
            try {
                const res = await apiCall('workforce_qr_status', this.userId);
                const data = res.data || {};
                const wf = renderStatus(data);
                if (!keepQr && qrHost) {
                    qrHost.innerHTML = '<p class="wf-meta">Loading…</p>';
                }
                this.applyQrDisplay(wf);
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
            const wf = renderStatus(data.workforce || data);
            const payload = data.qr_payload || null;
            if (payload) {
                this.rememberPayload(payload);
                renderQr(el('wf-qr-host'), payload);
            } else {
                this.applyQrDisplay(wf);
            }
        },

        async regenerate() {
            if (!global.confirm('Regenerate will invalidate the previous badge. Continue?')) {
                return;
            }
            await loadLibs();
            const res = await apiCall('workforce_qr_regenerate', this.userId);
            const data = res.data || {};
            const wf = renderStatus(data.workforce || {});
            const payload = data.qr_payload || null;
            if (payload) {
                this.rememberPayload(payload);
                renderQr(el('wf-qr-host'), payload);
            } else {
                this.applyQrDisplay(wf);
            }
        },

        async revoke() {
            if (!global.confirm('Revoke workforce QR access for this user?')) {
                return;
            }
            const res = await apiCall('workforce_qr_revoke', this.userId);
            this.clearStoredPayload();
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
            await this.refreshStatusOnly();
        },

        async saveEnabled() {
            const enabled = el('wf-qr-enabled') && el('wf-qr-enabled').checked;
            await apiCall('workforce_qr_set_enabled', this.userId, { enabled: enabled });
            await this.refreshStatusOnly();
        },

        async refreshStatusOnly() {
            try {
                const res = await apiCall('workforce_qr_status', this.userId);
                const wf = renderStatus(res.data || {});
                this.applyQrDisplay(wf);
            } catch (e) {
                /* keep QR visible */
            }
        },

        openBadge() {
            const base = typeof pageUrl === 'function'
                ? pageUrl('workforce-badge.php')
                : '/pages/workforce-badge.php';
            global.open(base + '?user_id=' + encodeURIComponent(String(this.userId)), '_blank');
        },

        downloadPng() {
            const img = document.querySelector('#wf-qr-host .ratib-qr-image');
            if (!img || !img.src) {
                global.alert('Generate QR first.');
                return;
            }
            const a = document.createElement('a');
            a.download = 'rateb-workforce-badge-' + this.userId + '.png';
            a.href = img.src;
            a.target = '_blank';
            a.rel = 'noopener';
            a.click();
        },

        printBadge() {
            this.openBadge();
        },

        computeFullscreenQrSize() {
            const vh = global.innerHeight || 700;
            const vw = global.innerWidth || 400;
            const reserved = vh < 720 ? 200 : 280;
            const byHeight = vh - reserved;
            const byWidth = vw - 48;
            return Math.max(200, Math.min(320, Math.floor(Math.min(byHeight, byWidth))));
        },

        async showFullscreenQr() {
            const payload = this.loadStoredPayload();
            if (!payload) {
                global.alert('Generate or Regenerate QR first.');
                return;
            }
            await loadLibs();
            const host = el('wf-qr-fullscreen-host');
            const overlay = el('wfQrFullscreen');
            const panel = overlay ? overlay.querySelector('.wf-qr-fullscreen-panel') : null;
            const userEl = el('wf-qr-fullscreen-user');
            if (!host || !overlay) {
                return;
            }
            const qrSize = this.computeFullscreenQrSize();
            if (panel) {
                panel.style.setProperty('--wf-qr-size', qrSize + 'px');
            }
            if (userEl) {
                userEl.innerHTML = this.username
                    ? 'Employee: <strong>' + this.username.replace(/</g, '&lt;') + '</strong>'
                    : '';
            }
            host.innerHTML = '';
            renderQr(host, payload, { fullscreen: true, qrSize: qrSize });
            overlay.classList.remove('d-none');
            document.body.classList.add('wf-qr-fs-open');
        },

        closeFullscreenQr() {
            const overlay = el('wfQrFullscreen');
            if (overlay) {
                overlay.classList.add('d-none');
            }
            document.body.classList.remove('wf-qr-fs-open');
        },

        downloadFullscreenPng() {
            const img = document.querySelector('#wf-qr-fullscreen-host .ratib-qr-image');
            if (!img || !img.src) {
                global.alert('Generate QR first.');
                return;
            }
            const a = document.createElement('a');
            a.download = 'rateb-workforce-badge-' + this.userId + '.png';
            a.href = img.src;
            a.target = '_blank';
            a.rel = 'noopener';
            a.click();
        },

        copyBadgeLink() {
            const payload = this.loadStoredPayload();
            if (!payload) {
                global.alert('Generate or Regenerate QR first.');
                return;
            }
            const url = payloadToScanValue(payload);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    global.alert('Badge link copied. On your phone: paste in Safari (after you opened the scan page from the login QR).');
                }).catch(function () {
                    global.prompt('Copy this link and open on your phone:', url);
                });
            } else {
                global.prompt('Copy this link and open on your phone:', url);
            }
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
            } else if (act === 'fullscreen-qr') {
                WorkforceAccess.showFullscreenQr();
            } else if (act === 'close-fullscreen') {
                WorkforceAccess.closeFullscreenQr();
            } else if (act === 'download-png-fs') {
                WorkforceAccess.downloadFullscreenPng();
            } else if (act === 'copy-badge-link') {
                WorkforceAccess.copyBadgeLink();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                WorkforceAccess.closeFullscreenQr();
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
                    return WorkforceAccess.refreshStatusOnly();
                }).catch(function (err) {
                    global.alert(err.message || 'Failed');
                });
            });
        }
    });

    global.WorkforceAccess = WorkforceAccess;
})(window);
