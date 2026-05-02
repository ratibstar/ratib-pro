/**
 * EN: Partner agency detail page — load one agency, render English dark UI.
 * AR: صفحة تفاصيل وكيل الشريك.
 */
(function () {
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function displayValue(v) {
        const t = v == null ? '' : String(v).trim();
        return t === '' ? '—' : t;
    }

    function formatCalendarDate(s) {
        if (s == null || s === '') return '—';
        const str = String(s).trim();
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            const d = new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
            if (!Number.isNaN(d.getTime())) {
                return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            }
        }
        return str;
    }

    function initialsFromName(name) {
        const w = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (w.length === 0) return 'PA';
        if (w.length === 1) return w[0].slice(0, 2).toUpperCase();
        return (w[0][0] + w[w.length - 1][0]).toUpperCase();
    }

    function buildWorkerProfileHref(workerId) {
        const qs = new URLSearchParams(window.location.search);
        qs.delete('id');
        qs.delete('edit');
        qs.set('view', String(workerId));
        return `Worker.php?${qs.toString()}`;
    }

    function statusClassForDeployment(status) {
        const s = String(status || 'processing').toLowerCase();
        if (s === 'issue' || s === 'returned' || s === 'processing') {
            return `agency-contract-status--${s}`;
        }
        return '';
    }

    function renderDl(target, rows) {
        if (!target) return;
        target.innerHTML = rows
            .map(
                ([label, value]) => `
            <div>
                <dt>${escapeHtml(label)}</dt>
                <dd>${escapeHtml(value)}</dd>
            </div>`
            )
            .join('');
    }

    function setTabActive(name) {
        document.querySelectorAll('.agency-detail-tab').forEach((btn) => {
            const on = btn.getAttribute('data-tab') === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        ['basic', 'attachments', 'account'].forEach((id) => {
            const panel = document.getElementById(`panel-${id}`);
            if (!panel) return;
            const on = id === name;
            panel.classList.toggle('is-hidden', !on);
            panel.hidden = !on;
        });
    }

    function withContext(url) {
        const pageQuery = new URLSearchParams(window.location.search || '');
        const control = pageQuery.get('control');
        const pageAgencyId = pageQuery.get('agency_id');
        const qs = new URLSearchParams();
        if (control) qs.set('control', control);
        if (pageAgencyId) qs.set('agency_id', pageAgencyId);
        if (!qs.toString()) return url;
        return `${url}${url.indexOf('?') !== -1 ? '&' : '?'}${qs.toString()}`;
    }

    function showError(msg) {
        const el = document.getElementById('agencyDetailError');
        if (el) {
            el.textContent = msg;
            el.classList.remove('is-hidden');
            el.hidden = false;
        }
    }

    function renderContracts(agency) {
        const list = document.getElementById('contractsList');
        const empty = document.getElementById('contractsEmpty');
        const countEl = document.getElementById('contractsCount');
        const sent = Array.isArray(agency.sent_workers) ? agency.sent_workers : [];
        if (countEl) countEl.textContent = String(sent.length);
        if (sent.length === 0) {
            if (list) list.innerHTML = '';
            if (empty) {
                empty.hidden = false;
            }
            return;
        }
        if (empty) empty.hidden = true;
        if (!list) return;

        list.innerHTML = sent
            .map((w) => {
                const depId = w.deployment_id != null ? w.deployment_id : '';
                const workerName = displayValue(w.worker_name);
                const st = String(w.status || 'processing');
                const statusExtra = statusClassForDeployment(st);
                const salaryRaw = w.salary != null && String(w.salary).trim() !== '' ? String(w.salary) : '';
                const salary =
                    salaryRaw !== ''
                        ? `${salaryRaw} SAR`
                        : '—';
                const start = formatCalendarDate(w.contract_start);
                const job = displayValue(w.job_title);
                const country = displayValue(w.country);
                const wid = parseInt(String(w.worker_id || '0'), 10);
                const profileHref = wid > 0 ? escapeHtml(withContext(buildWorkerProfileHref(wid))) : '#';

                return `
                <article class="agency-contract-card">
                    <div class="agency-contract-card-top">
                        <span class="agency-contract-id">#${escapeHtml(depId)}</span>
                        <span class="agency-contract-status ${escapeHtml(statusExtra)}">${escapeHtml(st)}</span>
                    </div>
                    <div class="agency-contract-body">
                        <div><strong>${workerName}</strong></div>
                        <div>${escapeHtml(start)} · ${escapeHtml(job)} · ${escapeHtml(country)}</div>
                    </div>
                    <div class="agency-contract-meta">
                        <span class="agency-contract-salary">${escapeHtml(salary)}</span>
                        ${
                            wid > 0
                                ? `<a class="agency-contract-view" href="${profileHref}">View 👁</a>`
                                : '<span class="agency-contract-view" style="opacity:0.5">View</span>'
                        }
                    </div>
                </article>`;
            })
            .join('');
    }

    function applyAgency(agency) {
        const name = displayValue(agency.name);
        document.title = `${name} · Partner Agency · Ratib`;

        const titleEl = document.getElementById('detailPageTitle');
        if (titleEl) titleEl.textContent = name;

        const bc = document.getElementById('breadcrumbAgencyName');
        if (bc) bc.textContent = name;

        const av = document.getElementById('agencyDetailAvatar');
        if (av) av.textContent = initialsFromName(agency.name);

        const st = document.getElementById('detailStatus');
        if (st) {
            const status = String(agency.status || 'inactive').toLowerCase();
            st.textContent = status;
            st.className = `status-pill status-${status}`;
            st.hidden = false;
        }

        const idBadge = document.getElementById('detailAgencyId');
        if (idBadge && agency.id != null) {
            idBadge.textContent = `ID ${agency.id}`;
            idBadge.hidden = false;
        }

        renderDl(document.getElementById('detailAgencyData'), [
            ['Agency name', displayValue(agency.name)],
            ['Country', displayValue(agency.country)],
            ['City', displayValue(agency.city)],
            ['Contact person', displayValue(agency.contact_person)],
            ['Record created', formatCalendarDate(agency.created_at)],
        ]);

        renderDl(document.getElementById('detailContactData'), [
            ['Email', displayValue(agency.email)],
            ['Phone', displayValue(agency.phone)],
            ['Secondary phone', '—'],
            ['Fax', '—'],
            ['Account number', '—'],
        ]);

        renderDl(document.getElementById('detailAdminData'), [
            ['License', '—'],
            ['Responsible person', displayValue(agency.contact_person)],
            ['Sending bank', '—'],
            ['License owner', '—'],
            ['Notes', '—'],
        ]);

        renderContracts(agency);
    }

    async function load() {
        const params = new URLSearchParams(window.location.search || '');
        const id = parseInt(String(params.get('id') || '0'), 10);
        if (!Number.isFinite(id) || id <= 0) {
            showError('Missing or invalid agency id. Open this page from Partner Agencies.');
            return;
        }

        const apiBase = '../api/partnerships/partner-agencies.php';
        const api = withContext(`${apiBase}?id=${id}`);

        try {
            const res = await fetch(api, { credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                const msg = json.message || `Could not load agency (${res.status}).`;
                showError(msg);
                return;
            }
            applyAgency(json.data || {});
        } catch (e) {
            showError(e && e.message ? e.message : 'Network error loading agency.');
        }
    }

    function initTabs() {
        document.querySelectorAll('.agency-detail-tab').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                if (tab) setTabActive(tab);
            });
        });
    }

    function init() {
        initTabs();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
