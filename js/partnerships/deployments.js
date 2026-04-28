/**
 * EN: Implements frontend interaction behavior in `js/partnerships/deployments.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/partnerships/deployments.js`.
 */
(function () {
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `partnership-toast ${type || 'success'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 220);
        }, 2400);
    }

    const parseCalendarDate = (s) => {
        if (s == null || s === '' || s === '-') return null;
        const str = String(s).trim();
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            const y = parseInt(m[1], 10);
            const mo = parseInt(m[2], 10) - 1;
            const d = parseInt(m[3], 10);
            if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null;
            return new Date(y, mo, d);
        }
        const t = Date.parse(str);
        return Number.isFinite(t) ? new Date(t) : null;
    };

    const startOfLocalDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

    const contractPlacementHtml = (contractStart, contractEnd) => {
        const start = parseCalendarDate(contractStart);
        const end = parseCalendarDate(contractEnd);
        const nowDay = startOfLocalDay(new Date());
        const parts = [];
        if (start) {
            const sDay = startOfLocalDay(start);
            const abroad = Math.floor((nowDay - sDay) / 86400000);
            if (abroad >= 0) {
                parts.push(`<span class="placement-line">${abroad}d on assignment</span>`);
            } else {
                parts.push(`<span class="placement-line placement-line--muted">Starts in ${Math.abs(abroad)}d</span>`);
            }
        }
        if (!end) {
            parts.push('<span class="contract-health contract-health--muted">No contract end set</span>');
        } else {
            const eDay = startOfLocalDay(end);
            const daysLeft = Math.round((eDay - nowDay) / 86400000);
            if (daysLeft < 0) {
                parts.push(`<span class="contract-health contract-health--ended">Ended ${Math.abs(daysLeft)}d ago</span>`);
            } else if (daysLeft <= 30) {
                parts.push(`<span class="contract-health contract-health--urgent">${daysLeft}d left on contract</span>`);
            } else if (daysLeft <= 90) {
                parts.push(`<span class="contract-health contract-health--warn">${daysLeft}d left on contract</span>`);
            } else {
                parts.push(`<span class="contract-health contract-health--ok">${daysLeft}d left on contract</span>`);
            }
        }
        if (!parts.length) return '';
        return `<div class="contract-cell-stack">${parts.join('')}</div>`;
    };

    function init() {
        const api = '../api/partnerships/deployments.php';
        const tableBody = document.getElementById('deploymentsTableBody');
        if (!tableBody) return;

        const pageQs = new URLSearchParams(window.location.search || '');
        let stickyWorkerId = parseInt(String(pageQs.get('worker_id') || ''), 10);
        if (!Number.isFinite(stickyWorkerId) || stickyWorkerId <= 0) {
            stickyWorkerId = 0;
        }

        const contextEl = document.getElementById('deploymentsPageContext');
        const clearWorkerBtn = document.getElementById('clearDeploymentWorkerFilter');
        const presetEl = document.getElementById('deploymentQuickPreset');

        const syncContextBanner = () => {
            if (!contextEl) return;
            const ctrl = pageQs.get('control');
            const aid = pageQs.get('agency_id');
            if (ctrl === '1' && aid) {
                contextEl.hidden = false;
                contextEl.textContent = 'Office context: deployments respect your control scope and linked office id.';
            } else {
                contextEl.hidden = true;
                contextEl.textContent = '';
            }
        };

        const syncWorkerFilterUi = () => {
            if (clearWorkerBtn) {
                clearWorkerBtn.hidden = stickyWorkerId <= 0;
            }
        };

        const applyPresetToQuery = (qs) => {
            const preset = presetEl?.value || '';
            qs.delete('active_abroad');
            qs.delete('status');
            qs.delete('expiring_within_days');
            if (preset === 'active_abroad') {
                qs.set('active_abroad', '1');
            } else if (preset === 'processing') {
                qs.set('status', 'processing');
            } else if (preset === 'expiring90') {
                qs.set('expiring_within_days', '90');
            } else if (preset === 'issue') {
                qs.set('status', 'issue');
            }
        };

        const readPresetFromUrl = () => {
            if (!presetEl) return;
            if (pageQs.get('active_abroad') === '1') {
                presetEl.value = 'active_abroad';
                return;
            }
            const st = (pageQs.get('status') || '').toLowerCase();
            if (st === 'processing') {
                presetEl.value = 'processing';
                return;
            }
            if (st === 'issue') {
                presetEl.value = 'issue';
                return;
            }
            const ex = parseInt(String(pageQs.get('expiring_within_days') || ''), 10);
            if (ex === 90) {
                presetEl.value = 'expiring90';
                return;
            }
            presetEl.value = '';
        };

        const badge = (status) => `<span class="status-pill status-${status}">${status}</span>`;

        const load = async () => {
            const search = document.getElementById('deploymentSearch')?.value.trim() || '';
            const country = document.getElementById('deploymentCountryFilter')?.value.trim() || '';
            const qs = new URLSearchParams();
            if (search) qs.set('search', search);
            if (country) qs.set('country', country);
            if (stickyWorkerId > 0) {
                qs.set('worker_id', String(stickyWorkerId));
            }
            applyPresetToQuery(qs);
            const res = await fetch(`${api}?${qs.toString()}`);
            const json = await res.json();
            if (!json.success) {
                tableBody.innerHTML = '<tr><td colspan="8">Unable to load deployments.</td></tr>';
                return;
            }
            if (!Array.isArray(json.data) || json.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8">No deployments found.</td></tr>';
                return;
            }
            tableBody.innerHTML = (json.data || []).map((d) => `
            <tr>
                <td>${d.worker_name || ''}</td>
                <td>${d.country || ''}</td>
                <td>${d.partner_agency_name || ''}</td>
                <td>${badge(d.status || 'processing')}</td>
                <td class="contract-timeline-cell">
                    <div class="contract-main-line">${d.contract_start || ''} → ${d.contract_end || ''}</div>
                    ${contractPlacementHtml(d.contract_start, d.contract_end)}
                </td>
                <td>${d.job_title || ''}</td>
                <td>${d.salary || ''}</td>
                <td>
                    <select class="status-select" data-id="${d.id}">
                        ${['processing', 'deployed', 'returned', 'issue', 'transferred'].map(s => `<option value="${s}" ${s === d.status ? 'selected' : ''}>${s}</option>`).join('')}
                    </select>
                </td>
            </tr>
        `).join('');
        };

        const pushUrlState = () => {
            const u = new URL(window.location.href);
            const search = document.getElementById('deploymentSearch')?.value.trim() || '';
            const country = document.getElementById('deploymentCountryFilter')?.value.trim() || '';
            if (search) u.searchParams.set('search', search); else u.searchParams.delete('search');
            if (country) u.searchParams.set('country', country); else u.searchParams.delete('country');
            if (stickyWorkerId > 0) u.searchParams.set('worker_id', String(stickyWorkerId));
            else u.searchParams.delete('worker_id');
            u.searchParams.delete('active_abroad');
            u.searchParams.delete('status');
            u.searchParams.delete('expiring_within_days');
            const preset = presetEl?.value || '';
            if (preset === 'active_abroad') u.searchParams.set('active_abroad', '1');
            else if (preset === 'processing') u.searchParams.set('status', 'processing');
            else if (preset === 'expiring90') u.searchParams.set('expiring_within_days', '90');
            else if (preset === 'issue') u.searchParams.set('status', 'issue');
            window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? `?${u.searchParams.toString()}` : '') + u.hash);
        };

        tableBody.addEventListener('change', async (e) => {
            const select = e.target.closest('.status-select');
            if (!select) return;
            const res = await fetch(`${api}?id=${encodeURIComponent(select.dataset.id)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: select.value })
            });
            const json = await res.json();
            if (!json.success) {
                showToast(json.message || 'Unable to update deployment status', 'error');
                return;
            }
            showToast('Deployment status updated.', 'success');
            load();
        });

        document.getElementById('applyDeploymentFilters')?.addEventListener('click', () => {
            pushUrlState();
            load();
        });

        presetEl?.addEventListener('change', () => {
            pushUrlState();
            load();
        });

        clearWorkerBtn?.addEventListener('click', () => {
            stickyWorkerId = 0;
            const u = new URL(window.location.href);
            u.searchParams.delete('worker_id');
            window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? `?${u.searchParams.toString()}` : '') + u.hash);
            syncWorkerFilterUi();
            load();
        });

        document.getElementById('exportDeploymentsCsv')?.addEventListener('click', () => {
            const search = document.getElementById('deploymentSearch')?.value.trim() || '';
            const country = document.getElementById('deploymentCountryFilter')?.value.trim() || '';
            const qs = new URLSearchParams({ export: 'csv' });
            if (search) qs.set('search', search);
            if (country) qs.set('country', country);
            if (stickyWorkerId > 0) qs.set('worker_id', String(stickyWorkerId));
            applyPresetToQuery(qs);
            window.location.href = `${api}?${qs.toString()}`;
        });

        const searchInput = document.getElementById('deploymentSearch');
        const countryInput = document.getElementById('deploymentCountryFilter');
        if (searchInput && pageQs.get('search')) {
            searchInput.value = pageQs.get('search');
        }
        if (countryInput && pageQs.get('country')) {
            countryInput.value = pageQs.get('country');
        }
        readPresetFromUrl();
        syncContextBanner();
        syncWorkerFilterUi();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
