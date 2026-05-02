/**
 * Partner agency detail page — load one agency, portal controls, CVs.
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
                return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

    /** @type {Record<string, unknown>|null} */
    let agencySnapshot = null;

    function collectPayloadFromSnapshot(extra) {
        if (!agencySnapshot) return null;
        const o = agencySnapshot;
        const emailStr = String(o.email ?? '').trim();
        const payload = {
            name: String(o.name ?? ''),
            agency_code: String(o.agency_code ?? '').trim(),
            country: String(o.country ?? ''),
            city: String(o.city ?? ''),
            contact_person: String(o.contact_person ?? ''),
            email: emailStr === '' ? null : emailStr,
            phone: String(o.phone ?? ''),
            phone2: String(o.phone2 ?? ''),
            fax: String(o.fax ?? ''),
            address_en: String(o.address_en ?? ''),
            license: String(o.license ?? ''),
            passport_no: String(o.passport_no ?? ''),
            passport_issue_place: String(o.passport_issue_place ?? ''),
            passport_issue_date: String(o.passport_issue_date ?? '').trim(),
            sending_bank: String(o.sending_bank ?? ''),
            account_number: String(o.account_number ?? ''),
            mobile: String(o.mobile ?? ''),
            license_owner: String(o.license_owner ?? ''),
            notes: String(o.notes ?? ''),
            status: String(o.status ?? 'active'),
        };
        return Object.assign(payload, extra || {});
    }

    async function putAgency(id, extra) {
        const payload = collectPayloadFromSnapshot(extra);
        if (!payload) return null;
        const res = await fetch(withContext(`../api/partnerships/partner-agencies.php?id=${encodeURIComponent(String(id))}`), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) {
            throw new Error(json.message || `Save failed (${res.status})`);
        }
        return json.data || {};
    }

    function applyPortalUi(agency) {
        const en = document.getElementById('portalEnabled');
        if (en) en.checked = !!agency.portal_enabled;
        const st = document.getElementById('portalTokenStatus');
        if (st) {
            const parts = [];
            if (agency.portal_has_token) parts.push('Access link is active.');
            else parts.push('No access link yet — enable portal and click “Generate new access link”.');
            if (agency.portal_has_password) parts.push('Portal password is set.');
            st.textContent = parts.join(' ');
        }
        const wrap = document.getElementById('portalMagicLinkWrap');
        const field = document.getElementById('portalMagicLinkField');
        if (wrap && field) {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
            field.value = '';
        }
    }

    async function loadWorkerSharesForAgency(agencyId) {
        const list = document.getElementById('workerShareList');
        const empty = document.getElementById('workerShareEmpty');
        const wSel = document.getElementById('shareWorkerSelect');
        const dSel = document.getElementById('shareDocTypeSelect');
        if (!list) return;
        try {
            const res = await fetch(
                withContext(
                    `../api/partnerships/partner-agency-worker-shares.php?partner_agency_id=${encodeURIComponent(String(agencyId))}`
                ),
                { credentials: 'same-origin' }
            );
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                list.innerHTML = '';
                return;
            }
            const d = json.data || {};
            const shares = Array.isArray(d.shares) ? d.shares : [];
            const workers = Array.isArray(d.deployment_workers) ? d.deployment_workers : [];
            const types = Array.isArray(d.document_types) ? d.document_types : [];
            const labels = d.document_labels && typeof d.document_labels === 'object' ? d.document_labels : {};

            if (wSel) {
                wSel.innerHTML = '<option value="">Select worker (deployments)…</option>';
                workers.forEach((w) => {
                    const o = document.createElement('option');
                    o.value = String(w.id);
                    const p = w.passport_number ? String(w.passport_number).trim() : '';
                    o.textContent = `${w.worker_name || 'Worker'} (#${w.id})${p ? ' · ' + p : ''}`;
                    wSel.appendChild(o);
                });
            }
            if (dSel) {
                dSel.innerHTML = '<option value="">Document type…</option>';
                types.forEach((t) => {
                    const o = document.createElement('option');
                    o.value = t;
                    o.textContent = labels[t] || t;
                    dSel.appendChild(o);
                });
            }

            if (shares.length === 0) {
                list.innerHTML = '';
                if (empty) empty.hidden = false;
                return;
            }
            if (empty) empty.hidden = true;
            const dlBase = `../api/partnerships/partner-shared-worker-doc-download.php`;
            list.innerHTML = shares
                .map((s) => {
                    const sid = s.id;
                    const name = displayValue(s.worker_name);
                    const docLab = displayValue(s.document_label || s.document_type);
                    const curType = String(s.document_type || '');
                    const hasFile = !!s.has_file;
                    const href = withContext(`${dlBase}?share_id=${encodeURIComponent(String(sid))}`);
                    const dl = hasFile
                        ? `<a class="muted-btn" href="${escapeHtml(href)}" target="_blank" rel="noopener">Download</a>`
                        : '<span class="agency-share-no-file">No file on worker</span>';

                    const opts = types
                        .map((t) => {
                            const lab = labels[t] || t;
                            const sel = t === curType ? ' selected' : '';

                            return `<option value="${escapeHtml(t)}"${sel}>${escapeHtml(lab)}</option>`;
                        })
                        .join('');

                    return `<li class="agency-worker-share-item" data-share-id="${String(sid)}">
                        <div class="agency-share-row-main">
                            <div>
                                <strong>${escapeHtml(name)}</strong>
                                <div class="agency-cv-admin-meta">${escapeHtml(docLab)} · ${escapeHtml(
                        formatCalendarDate(s.created_at)
                    )}</div>
                            </div>
                            <div class="agency-worker-share-actions">
                                ${dl}
                                <button type="button" class="muted-btn agency-worker-share-edit" data-share-id="${String(
                        sid
                    )}">Edit</button>
                                <button type="button" class="muted-btn agency-worker-share-remove" data-share-id="${String(
                        sid
                    )}">Remove</button>
                            </div>
                        </div>
                        <div class="agency-share-edit-panel" hidden>
                            <label class="agency-share-edit-label">Document type
                                <select class="agency-share-edit-select" aria-label="Document type">${opts}</select>
                            </label>
                            <button type="button" class="muted-btn agency-share-save" data-share-id="${String(
                        sid
                    )}">Save</button>
                            <button type="button" class="muted-btn agency-share-cancel" data-share-id="${String(
                        sid
                    )}">Cancel</button>
                        </div>
                    </li>`;
                })
                .join('');
        } catch (e) {
            list.innerHTML = '';
        }
    }

    function bindWorkerShareListActions() {
        const list = document.getElementById('workerShareList');
        if (!list || list.dataset.shareActionsBound === '1') return;
        list.dataset.shareActionsBound = '1';
        list.addEventListener('click', async (e) => {
            const aid =
                agencySnapshot && agencySnapshot.id != null ? Number(agencySnapshot.id) : 0;
            if (!aid) return;

            const editBtn = e.target.closest('.agency-worker-share-edit');
            if (editBtn) {
                const li = editBtn.closest('.agency-worker-share-item');
                if (!li) return;
                const main = li.querySelector('.agency-share-row-main');
                const panel = li.querySelector('.agency-share-edit-panel');
                if (main) main.hidden = true;
                if (panel) panel.hidden = false;
                return;
            }

            const cancelBtn = e.target.closest('.agency-share-cancel');
            if (cancelBtn) {
                const li = cancelBtn.closest('.agency-worker-share-item');
                if (!li) return;
                const main = li.querySelector('.agency-share-row-main');
                const panel = li.querySelector('.agency-share-edit-panel');
                if (main) main.hidden = false;
                if (panel) panel.hidden = true;
                return;
            }

            const saveBtn = e.target.closest('.agency-share-save');
            if (saveBtn) {
                const sid = saveBtn.getAttribute('data-share-id');
                const li = saveBtn.closest('.agency-worker-share-item');
                const sel = li ? li.querySelector('.agency-share-edit-select') : null;
                const docType = sel ? String(sel.value || '').trim() : '';
                if (!sid || !docType) return;
                try {
                    const res = await fetch(withContext('../api/partnerships/partner-agency-worker-shares.php'), {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: parseInt(sid, 10),
                            partner_agency_id: aid,
                            document_type: docType,
                        }),
                    });
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || !j.success) throw new Error(j.message || 'Update failed');
                    await loadWorkerSharesForAgency(aid);
                } catch (err) {
                    showError(err && err.message ? err.message : 'Could not update.');
                }
                return;
            }

            const removeBtn = e.target.closest('.agency-worker-share-remove');
            if (removeBtn) {
                const shareId = removeBtn.getAttribute('data-share-id');
                if (!shareId) return;
                if (!window.confirm('Remove this share? Partners will no longer see it.')) return;
                try {
                    const url = withContext(
                        `../api/partnerships/partner-agency-worker-shares.php?id=${encodeURIComponent(
                            shareId
                        )}&partner_agency_id=${encodeURIComponent(String(aid))}`
                    );
                    const r = await fetch(url, { method: 'DELETE', credentials: 'same-origin' });
                    const j = await r.json().catch(() => ({}));
                    if (!r.ok || !j.success) throw new Error(j.message || 'Remove failed');
                    await loadWorkerSharesForAgency(aid);
                } catch (err) {
                    showError(err && err.message ? err.message : 'Could not remove.');
                }
            }
        });
    }

    async function loadCvsForAgency(agencyId) {
        const list = document.getElementById('cvAdminList');
        const empty = document.getElementById('cvAdminEmpty');
        if (!list) return;
        try {
            const res = await fetch(
                withContext(`../api/partnerships/partner-agency-cvs.php?partner_agency_id=${encodeURIComponent(String(agencyId))}`),
                { credentials: 'same-origin' }
            );
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                list.innerHTML = '';
                return;
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            if (rows.length === 0) {
                list.innerHTML = '';
                if (empty) empty.hidden = false;
                return;
            }
            if (empty) empty.hidden = true;
            const dlBase = `../api/partnerships/partner-agency-cv-download.php`;
            list.innerHTML = rows
                .map((c) => {
                    const id = c.id;
                    const title = displayValue(c.title);
                    const fn = displayValue(c.original_filename);
                    const href = escapeHtml(withContext(`${dlBase}?id=${encodeURIComponent(String(id))}`));

                    return `<li class="agency-cv-admin-item">
                        <div>
                            <strong>${escapeHtml(title)}</strong>
                            <div class="agency-cv-admin-meta">${escapeHtml(fn)} · ${escapeHtml(formatCalendarDate(c.created_at))}</div>
                        </div>
                        <div>
                            <a class="muted-btn" href="${href}" target="_blank" rel="noopener">Download</a>
                            <button type="button" class="muted-btn agency-cv-delete" data-cv-id="${String(id)}">Delete</button>
                        </div>
                    </li>`;
                })
                .join('');

            list.querySelectorAll('.agency-cv-delete').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const cvId = btn.getAttribute('data-cv-id');
                    if (!cvId || !agencySnapshot?.id) return;
                    if (!window.confirm('Remove this document?')) return;
                    try {
                        const url = withContext(
                            `../api/partnerships/partner-agency-cvs.php?id=${encodeURIComponent(cvId)}&partner_agency_id=${encodeURIComponent(String(agencySnapshot.id))}`
                        );
                        const r = await fetch(url, { method: 'DELETE', credentials: 'same-origin' });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok || !j.success) throw new Error(j.message || 'Delete failed');
                        await loadCvsForAgency(Number(agencySnapshot.id));
                    } catch (err) {
                        showError(err && err.message ? err.message : 'Could not delete.');
                    }
                });
            });
        } catch (e) {
            list.innerHTML = '';
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
            ['Agency code', displayValue(agency.agency_code)],
            ['Country', displayValue(agency.country)],
            ['City', displayValue(agency.city)],
            ['Address', displayValue(agency.address_en)],
            ['Contact person', displayValue(agency.contact_person)],
            ['Record created', formatCalendarDate(agency.created_at)],
        ]);

        renderDl(document.getElementById('detailContactData'), [
            ['Email', displayValue(agency.email)],
            ['Phone 1', displayValue(agency.phone)],
            ['Phone 2', displayValue(agency.phone2)],
            ['Fax', displayValue(agency.fax)],
            ['Mobile', displayValue(agency.mobile)],
            ['Account number', displayValue(agency.account_number)],
        ]);

        renderDl(document.getElementById('detailAdminData'), [
            ['License', displayValue(agency.license)],
            ['License owner', displayValue(agency.license_owner)],
            ['Sending bank', displayValue(agency.sending_bank)],
            ['Passport no.', displayValue(agency.passport_no)],
            ['Passport issue', `${displayValue(agency.passport_issue_place)} · ${formatCalendarDate(agency.passport_issue_date)}`],
            ['Notes', displayValue(agency.notes)],
        ]);

        renderContracts(agency);

        agencySnapshot = {
            id: agency.id,
            name: agency.name ?? '',
            agency_code: agency.agency_code ?? '',
            country: agency.country ?? '',
            city: agency.city ?? '',
            contact_person: agency.contact_person ?? '',
            email: agency.email ?? '',
            phone: agency.phone ?? '',
            phone2: agency.phone2 ?? '',
            fax: agency.fax ?? '',
            address_en: agency.address_en ?? '',
            license: agency.license ?? '',
            passport_no: agency.passport_no ?? '',
            passport_issue_place: agency.passport_issue_place ?? '',
            passport_issue_date: agency.passport_issue_date
                ? String(agency.passport_issue_date).slice(0, 10)
                : '',
            sending_bank: agency.sending_bank ?? '',
            account_number: agency.account_number ?? '',
            mobile: agency.mobile ?? '',
            license_owner: agency.license_owner ?? '',
            notes: agency.notes ?? '',
            status: agency.status ?? 'active',
        };

        applyPortalUi(agency);
        if (agency.id != null) {
            const aid = Number(agency.id);
            loadCvsForAgency(aid);
            loadWorkerSharesForAgency(aid);
        }
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
            const tabWant = (params.get('tab') || '').toLowerCase();
            if (tabWant === 'attachments' || tabWant === 'account' || tabWant === 'basic') {
                setTabActive(tabWant);
                if (tabWant === 'attachments') {
                    requestAnimationFrame(() => {
                        const panel = document.getElementById('panel-attachments');
                        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
            }
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

    function initPortalControls() {
        const id = () => (agencySnapshot && agencySnapshot.id != null ? Number(agencySnapshot.id) : 0);

        const regen = document.getElementById('portalRegenBtn');
        if (regen) {
            regen.addEventListener('click', async () => {
                if (!id()) return;
                try {
                    const data = await putAgency(id(), { regenerate_portal_token: true });
                    const magic = data && data.portal_magic_link ? String(data.portal_magic_link) : '';
                    await load();
                    const wrap = document.getElementById('portalMagicLinkWrap');
                    const field = document.getElementById('portalMagicLinkField');
                    if (magic && wrap && field) {
                        field.value = magic;
                        wrap.classList.remove('is-hidden');
                        wrap.hidden = false;
                    }
                } catch (e) {
                    showError(e && e.message ? e.message : 'Could not generate link.');
                }
            });
        }

        const save = document.getElementById('portalSaveBtn');
        if (save) {
            save.addEventListener('click', async () => {
                if (!id()) return;
                const pw = document.getElementById('portalPasswordInput');
                const pe = document.getElementById('portalEnabled');
                const extra = { portal_enabled: !!(pe && pe.checked) };
                if (pw && String(pw.value).trim() !== '') {
                    extra.portal_password = String(pw.value);
                }
                try {
                    await putAgency(id(), extra);
                    await load();
                    if (pw) pw.value = '';
                } catch (e) {
                    showError(e && e.message ? e.message : 'Could not save.');
                }
            });
        }

        const clr = document.getElementById('portalPwClearBtn');
        if (clr) {
            clr.addEventListener('click', async () => {
                if (!id()) return;
                try {
                    const pe2 = document.getElementById('portalEnabled');
                    await putAgency(id(), {
                        portal_enabled: !!(pe2 && pe2.checked),
                        portal_password: '__CLEAR__',
                    });
                    await load();
                } catch (e) {
                    showError(e && e.message ? e.message : 'Could not clear password.');
                }
            });
        }

        const form = document.getElementById('cvUploadForm');
        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const aid = id();
                if (!aid) return;
                const titleEl = document.getElementById('cvTitle');
                const fileEl = document.getElementById('cvFile');
                const title = titleEl ? String(titleEl.value || '').trim() : '';
                const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
                if (!title || !file) return;
                const fd = new FormData();
                fd.append('partner_agency_id', String(aid));
                fd.append('title', title);
                fd.append('file', file);
                try {
                    const res = await fetch(withContext('../api/partnerships/partner-agency-cvs.php'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd,
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) throw new Error(json.message || 'Upload failed');
                    if (titleEl) titleEl.value = '';
                    if (fileEl) fileEl.value = '';
                    await loadCvsForAgency(aid);
                } catch (e) {
                    showError(e && e.message ? e.message : 'Upload failed.');
                }
            });
        }

        const shareAdd = document.getElementById('shareDocAddBtn');
        if (shareAdd) {
            shareAdd.addEventListener('click', async () => {
                const aid = id();
                if (!aid) return;
                const wSel = document.getElementById('shareWorkerSelect');
                const manual = document.getElementById('shareWorkerIdManual');
                const dSel = document.getElementById('shareDocTypeSelect');
                let wid = wSel && wSel.value ? parseInt(String(wSel.value), 10) : 0;
                if (!Number.isFinite(wid) || wid <= 0) {
                    const m = manual && String(manual.value || '').trim();
                    if (m) wid = parseInt(m, 10);
                }
                const docType = dSel ? String(dSel.value || '').trim() : '';
                if (!Number.isFinite(wid) || wid <= 0 || !docType) {
                    showError('Choose a worker (or enter worker ID) and a document type.');
                    return;
                }
                try {
                    const res = await fetch(withContext('../api/partnerships/partner-agency-worker-shares.php'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            partner_agency_id: aid,
                            worker_id: wid,
                            document_type: docType,
                        }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) throw new Error(json.message || 'Could not add share');
                    if (manual) manual.value = '';
                    await loadWorkerSharesForAgency(aid);
                } catch (e) {
                    showError(e && e.message ? e.message : 'Could not add share.');
                }
            });
        }
    }

    function init() {
        initTabs();
        bindWorkerShareListActions();
        initPortalControls();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
