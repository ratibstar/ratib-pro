/**
 * Partner portal dashboard (scoped session).
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

    function statusClassForDeployment(status) {
        const st = String(status || 'processing').toLowerCase();
        if (st === 'issue' || st === 'returned' || st === 'processing') {
            return `agency-contract-status--${st}`;
        }
        return '';
    }

    function renderDl(target, rows) {
        if (!target) return;
        target.innerHTML = rows
            .map(
                ([label, value]) =>
                    `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`
            )
            .join('');
    }

    function downloadHref(cvId) {
        return `../api/partnerships/partner-agency-cv-download.php?id=${encodeURIComponent(String(cvId))}`;
    }

    async function load() {
        const errEl = document.getElementById('ppError');
        try {
            const res = await fetch('../api/partnerships/partner-portal-me.php', { credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                if (errEl) {
                    errEl.textContent = json.message || `Error ${res.status}`;
                    errEl.hidden = false;
                    errEl.classList.remove('is-hidden');
                }
                return;
            }
            const data = json.data || {};
            const agency = data.agency || {};
            const cvs = Array.isArray(data.cvs) ? data.cvs : [];

            const title = document.getElementById('ppAgencyName');
            if (title) title.textContent = displayValue(agency.name);

            const st = document.getElementById('ppStatus');
            if (st) {
                const status = String(agency.status || 'inactive').toLowerCase();
                st.textContent = status;
                st.className = `status-pill status-${status}`;
                st.hidden = false;
            }

            renderDl(document.getElementById('ppAgencyDl'), [
                ['Country', displayValue(agency.country)],
                ['City', displayValue(agency.city)],
                ['Contact', displayValue(agency.contact_person)],
                ['Email', displayValue(agency.email)],
                ['Phone', displayValue(agency.phone)],
            ]);

            const sent = Array.isArray(agency.sent_workers) ? agency.sent_workers : [];
            const countEl = document.getElementById('ppContractCount');
            if (countEl) countEl.textContent = String(sent.length);
            const list = document.getElementById('ppContracts');
            const emptyC = document.getElementById('ppContractsEmpty');
            if (sent.length === 0) {
                if (list) list.innerHTML = '';
                if (emptyC) emptyC.hidden = false;
            } else {
                if (emptyC) emptyC.hidden = true;
                if (list) {
                    list.innerHTML = sent
                        .map((w) => {
                            const depId = w.deployment_id != null ? w.deployment_id : '';
                            const workerName = displayValue(w.worker_name);
                            const st = String(w.status || 'processing');
                            const statusExtra = statusClassForDeployment(st);
                            const salaryRaw =
                                w.salary != null && String(w.salary).trim() !== '' ? String(w.salary) : '';
                            const salary = salaryRaw !== '' ? `${salaryRaw} SAR` : '—';
                            const start = formatCalendarDate(w.contract_start);
                            const job = displayValue(w.job_title);
                            const country = displayValue(w.country);

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
                                </div>
                            </article>`;
                        })
                        .join('');
                }
            }

            const cvList = document.getElementById('ppCvList');
            const cvEmpty = document.getElementById('ppCvEmpty');
            if (cvs.length === 0) {
                if (cvList) cvList.innerHTML = '';
                if (cvEmpty) cvEmpty.hidden = false;
            } else {
                if (cvEmpty) cvEmpty.hidden = true;
                if (cvList) {
                    cvList.innerHTML = cvs
                        .map((c) => {
                            const id = c.id;
                            const title = displayValue(c.title);
                            const fn = displayValue(c.original_filename);
                            const href = escapeHtml(downloadHref(id));

                            return `<li class="partner-portal-cv-item">
                                <div>
                                    <strong>${escapeHtml(title)}</strong>
                                    <div class="partner-portal-cv-meta">${escapeHtml(fn)} · ${formatCalendarDate(c.created_at)}</div>
                                </div>
                                <a class="neon-btn partner-portal-dl-btn" href="${href}">Download</a>
                            </li>`;
                        })
                        .join('');
                }
            }
        } catch (e) {
            if (errEl) {
                errEl.textContent = e && e.message ? e.message : 'Failed to load.';
                errEl.hidden = false;
                errEl.classList.remove('is-hidden');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
