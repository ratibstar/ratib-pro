/**
 * Partner portal dashboard (scoped session) — aligned with staff agency detail fields.
 */
(function () {
    const DATE_LOCALE = 'en-US';

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
                return d.toLocaleDateString(DATE_LOCALE, { year: 'numeric', month: 'short', day: 'numeric' });
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

    function renderContracts(agency) {
        const list = document.getElementById('ppContracts');
        const empty = document.getElementById('ppContractsEmpty');
        const countEl = document.getElementById('ppContractCount');
        const sent = Array.isArray(agency.sent_workers) ? agency.sent_workers : [];
        if (countEl) countEl.textContent = String(sent.length);
        if (sent.length === 0) {
            if (list) list.innerHTML = '';
            if (empty) empty.hidden = false;
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

    function workerShareDownloadHref(shareId) {
        return `../api/partnerships/partner-shared-worker-doc-download.php?share_id=${encodeURIComponent(String(shareId))}`;
    }

    function renderSharedWorkerDocs(rows) {
        const list = document.getElementById('ppWorkerShareList');
        const empty = document.getElementById('ppWorkerShareEmpty');
        if (!list) return;
        if (!rows.length) {
            list.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        list.innerHTML = rows
            .map((r) => {
                const sid = r.id;
                const name = displayValue(r.worker_name);
                const docLab = displayValue(r.document_label || r.document_type);
                const passport = displayValue(r.passport_number);
                const hasFile = !!r.has_file;
                const dl = workerShareDownloadHref(sid);
                const dlBtn = hasFile
                    ? `<a class="neon-btn partner-portal-dl-btn" href="${escapeHtml(dl)}">Download</a>`
                    : `<span class="partner-portal-no-file muted-label">No file uploaded yet</span>`;

                return `<li class="partner-portal-worker-share-item">
                    <div>
                        <strong>${escapeHtml(name)}</strong>
                        <div class="partner-portal-cv-meta">${escapeHtml(docLab)} · Passport: ${escapeHtml(passport)} · Added ${escapeHtml(formatCalendarDate(r.created_at))}</div>
                    </div>
                    ${dlBtn}
                </li>`;
            })
            .join('');
    }

    function renderCvList(cvs) {
        const cvList = document.getElementById('ppCvList');
        const cvEmpty = document.getElementById('ppCvEmpty');
        if (cvs.length === 0) {
            if (cvList) cvList.innerHTML = '';
            if (cvEmpty) cvEmpty.hidden = false;
            return;
        }
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
                            <div class="partner-portal-cv-meta">${escapeHtml(fn)} · ${escapeHtml(formatCalendarDate(c.created_at))}</div>
                        </div>
                        <div class="partner-portal-cv-actions">
                            <a class="neon-btn partner-portal-dl-btn" href="${href}">Download</a>
                            <button type="button" class="muted-btn partner-portal-cv-delete" data-cv-id="${escapeHtml(String(id))}">Delete</button>
                        </div>
                    </li>`;
                })
                .join('');
        }
    }

    function setUploadMessage(text, isError) {
        const el = document.getElementById('ppCvUploadMsg');
        if (!el) return;
        el.textContent = text || '';
        el.hidden = !text;
        el.classList.toggle('partner-portal-upload-msg--err', !!isError);
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
            if (errEl) {
                errEl.textContent = '';
                errEl.hidden = true;
                errEl.classList.add('is-hidden');
            }

            const data = json.data || {};
            const agency = data.agency || {};
            const cvs = Array.isArray(data.cvs) ? data.cvs : [];

            const title = document.getElementById('ppAgencyName');
            if (title) title.textContent = displayValue(agency.name);

            const idBadge = document.getElementById('ppAgencyIdBadge');
            if (idBadge && agency.id != null) {
                idBadge.textContent = `ID ${agency.id}`;
                idBadge.hidden = false;
            }

            const st = document.getElementById('ppStatus');
            if (st) {
                const status = String(agency.status || 'inactive').toLowerCase();
                st.textContent = status;
                st.className = `status-pill status-${status}`;
                st.hidden = false;
            }

            renderDl(document.getElementById('ppAgencyData'), [
                ['Agency name', displayValue(agency.name)],
                ['Agency code', displayValue(agency.agency_code)],
                ['Country', displayValue(agency.country)],
                ['City', displayValue(agency.city)],
                ['Address', displayValue(agency.address_en)],
                ['Contact person', displayValue(agency.contact_person)],
                ['Record created', formatCalendarDate(agency.created_at)],
            ]);

            renderDl(document.getElementById('ppContactData'), [
                ['Email', displayValue(agency.email)],
                ['Phone 1', displayValue(agency.phone)],
                ['Phone 2', displayValue(agency.phone2)],
                ['Fax', displayValue(agency.fax)],
                ['Mobile', displayValue(agency.mobile)],
                ['Account number', displayValue(agency.account_number)],
            ]);

            renderDl(document.getElementById('ppAdminData'), [
                ['License', displayValue(agency.license)],
                ['License owner', displayValue(agency.license_owner)],
                ['Sending bank', displayValue(agency.sending_bank)],
                ['Passport no.', displayValue(agency.passport_no)],
                [
                    'Passport issue',
                    `${displayValue(agency.passport_issue_place)} · ${formatCalendarDate(agency.passport_issue_date)}`,
                ],
                ['Notes', displayValue(agency.notes)],
            ]);

            renderContracts(agency);
            renderCvList(cvs);

            const shared = Array.isArray(data.shared_worker_documents) ? data.shared_worker_documents : [];
            renderSharedWorkerDocs(shared);
        } catch (e) {
            if (errEl) {
                errEl.textContent = e && e.message ? e.message : 'Failed to load.';
                errEl.hidden = false;
                errEl.classList.remove('is-hidden');
            }
        }
    }

    function initCvDeleteDelegation() {
        const list = document.getElementById('ppCvList');
        if (!list || list.dataset.cvDeleteBound === '1') return;
        list.dataset.cvDeleteBound = '1';
        list.addEventListener('click', async (e) => {
            const btn = e.target.closest('.partner-portal-cv-delete');
            if (!btn) return;
            const cvId = btn.getAttribute('data-cv-id');
            if (!cvId) return;
            if (!window.confirm('Remove this document?')) return;
            try {
                const url = `../api/partnerships/partner-agency-cvs.php?id=${encodeURIComponent(cvId)}`;
                const res = await fetch(url, { method: 'DELETE', credentials: 'same-origin' });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Could not delete.');
                }
                setUploadMessage('Document removed.', false);
                await load();
            } catch (err) {
                setUploadMessage(err && err.message ? err.message : 'Delete failed.', true);
            }
        });
    }

    function initUpload() {
        const form = document.getElementById('ppCvUploadForm');
        if (!form) return;
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const titleEl = document.getElementById('ppCvTitle');
            const fileEl = document.getElementById('ppCvFile');
            const btn = document.getElementById('ppCvUploadBtn');
            const title = titleEl ? String(titleEl.value || '').trim() : '';
            const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
            if (!title || !file) {
                setUploadMessage('Enter a title and choose a file.', true);
                return;
            }
            setUploadMessage('');
            const fd = new FormData();
            fd.append('title', title);
            fd.append('file', file);
            if (btn) btn.disabled = true;
            try {
                const res = await fetch('../api/partnerships/partner-agency-cvs.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    throw new Error(json.message || `Upload failed (${res.status})`);
                }
                if (titleEl) titleEl.value = '';
                if (fileEl) fileEl.value = '';
                setUploadMessage('Uploaded successfully.', false);
                await load();
            } catch (e) {
                setUploadMessage(e && e.message ? e.message : 'Upload failed.', true);
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    function init() {
        initCvDeleteDelegation();
        initUpload();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
