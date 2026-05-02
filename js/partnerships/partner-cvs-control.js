/**
 * Partner CVs bulk control table:
 * - List all worker document slots for one partner agency
 * - Bulk add/remove/edit shares
 */
(function () {
    const state = {
        partnerId: 0,
        rows: [],
        filtered: [],
        selectedKeys: new Set(),
        documentTypes: [],
        documentLabels: {},
        page: 1,
        pageSize: 25,
        /** Full-CV wizard */
        readyWorkers: [],
        selectedReadyWorkerIds: new Set(),
        readySearch: '',
    };

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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

    function setNotice(message, type) {
        const el = $('cvsControlNotice');
        if (!el) return;
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            el.classList.remove('is-error', 'is-success');
            return;
        }
        el.hidden = false;
        el.textContent = message;
        el.classList.toggle('is-error', type === 'error');
        el.classList.toggle('is-success', type === 'success');
        try {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            /* ignore */
        }
    }

    function showTableMessage(message) {
        const body = $('cvsControlBody');
        if (!body) return;
        body.innerHTML = `<tr><td colspan="7">${escapeHtml(message)}</td></tr>`;
    }

    function csvEscapeField(v) {
        const s = v == null ? '' : String(v);
        if (/[",\n\r]/.test(s)) {
            return `"${s.replace(/"/g, '""')}"`;
        }
        return s;
    }

    function exportFilteredCsv() {
        const rows = state.filtered;
        if (!rows.length || state.partnerId <= 0) return;
        const header = [
            'partner_agency_id',
            'worker_id',
            'worker_name',
            'passport',
            'document_type',
            'document_label',
            'has_file',
            'shared_on_portal',
            'share_id',
        ];
        const lines = [header.join(',')];
        rows.forEach((r) => {
            lines.push(
                [
                    state.partnerId,
                    r.worker_id,
                    r.worker_name,
                    r.passport_number || '',
                    r.document_type,
                    r.document_label,
                    r.has_file ? '1' : '0',
                    r.shared_on_portal ? '1' : '0',
                    r.share_id != null ? String(r.share_id) : '',
                ]
                    .map(csvEscapeField)
                    .join(',')
            );
        });
        const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `partner-cvs-${state.partnerId}-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
        setNotice(`Exported ${rows.length} rows.`, 'success');
    }

    function updateFilteredToolbar() {
        const n = state.filtered.length;
        const total = state.rows.length;
        const info = $('cvsFilteredInfo');
        if (info) {
            info.textContent =
                state.partnerId > 0 ? `${n} filtered · ${total} total` : 'Choose a partner in the wizard above for details';
        }
        const hasPartner = state.partnerId > 0;
        const can = hasPartner && n > 0;
        ['cvsSelectFilteredBtn', 'cvsExportCsvBtn'].forEach((id) => {
            const b = $(id);
            if (b) b.disabled = !can;
        });
    }

    function getFilteredReadyWorkers() {
        const q = String(state.readySearch || '')
            .toLowerCase()
            .trim();
        if (!q) return state.readyWorkers;
        return state.readyWorkers.filter((w) => {
            const hay = `${w.worker_name || ''} ${w.passport_number || ''} ${w.id}`.toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    }

    /** @returns {Set<number>|null} null = any partner allowed; empty = none */
    function validPartnerIntersection() {
        const ids = [...state.selectedReadyWorkerIds];
        if (ids.length === 0) return null;
        let inter = null;
        for (let i = 0; i < ids.length; i += 1) {
            const w = state.readyWorkers.find((x) => x.id === ids[i]);
            if (!w) {
                return new Set();
            }
            const pids = Array.isArray(w.partner_agency_ids) ? w.partner_agency_ids.map(Number) : [];
            const s = new Set(pids);
            if (pids.length === 0) {
                return new Set();
            }
            if (inter === null) {
                inter = s;
            } else {
                inter = new Set([...inter].filter((x) => s.has(x)));
            }
            if (inter.size === 0) return new Set();
        }
        return inter;
    }

    function applyPartnerOptionAvailability() {
        const sel = $('cvsPartnerSelect');
        if (!sel) return;
        const valid = validPartnerIntersection();
        const restrict = state.selectedReadyWorkerIds.size > 0;
        Array.from(sel.querySelectorAll('option')).forEach((opt) => {
            const v = String(opt.value || '').trim();
            if (v === '') {
                opt.disabled = false;
                return;
            }
            const id = parseInt(v, 10);
            if (!restrict || valid === null) {
                opt.disabled = false;
            } else if (valid.size === 0) {
                opt.disabled = true;
            } else {
                opt.disabled = !valid.has(id);
            }
        });
    }

    function enforcePartnerStillValid() {
        const sel = $('cvsPartnerSelect');
        if (!sel) return;
        const pid = parseInt(String(sel.value || '0'), 10) || 0;
        if (state.selectedReadyWorkerIds.size === 0 || !pid) return;
        const valid = validPartnerIntersection();
        if (valid && !valid.has(pid)) {
            sel.value = '';
            state.partnerId = 0;
            void loadPartnerRows(0);
        }
    }

    function updateReadyWizardUi() {
        enforcePartnerStillValid();
        const label = $('cvsReadySelectionLabel');
        if (label) {
            const n = state.selectedReadyWorkerIds.size;
            label.textContent = `${n} worker${n === 1 ? '' : 's'} selected`;
        }
        const valid = validPartnerIntersection();
        updateSendButtonState();

        const hint = $('cvsPartnerWizardHint');
        if (hint) {
            const ns = state.selectedReadyWorkerIds.size;
            if (ns === 0) {
                hint.hidden = true;
                hint.textContent = '';
            } else if (valid && valid.size === 0) {
                hint.hidden = false;
                hint.textContent =
                    'No partner has all selected workers on a deployment. Add deployments first, or pick workers who share the same partner.';
                hint.classList.remove('is-ok');
            } else if (valid && valid.size > 0) {
                hint.hidden = false;
                hint.textContent = `${valid.size} partner agency(ies) available for this selection (dropdown options are limited accordingly).`;
                hint.classList.add('is-ok');
            } else {
                hint.hidden = true;
            }
        }
        applyPartnerOptionAvailability();
    }

    function renderReadyWorkerList() {
        const box = $('cvsReadyWorkerList');
        if (!box) return;
        const rows = getFilteredReadyWorkers();
        if (!rows.length) {
            box.innerHTML =
                '<p class="cvs-ready-empty">No ready workers found yet. Upload worker document files first, then refresh.</p>';
            updateReadyWizardUi();
            return;
        }
        box.innerHTML = rows
            .map((w) => {
                const wid = w.id;
                const pids = Array.isArray(w.partner_agency_ids) ? w.partner_agency_ids : [];
                const depHint =
                    pids.length === 0
                        ? 'Not deployed to any partner - cannot send until added to a deployment.'
                        : `Ready docs: ${Number(w.ready_docs_count || 0)}/${Number(w.total_docs || 0)} - Deployed to ${pids.length} partner(s)`;
                const checked = state.selectedReadyWorkerIds.has(wid) ? ' checked' : '';
                const disabled = pids.length === 0 ? ' disabled' : '';
                const passport = String(w.passport_number || '').trim() || '-';

                return `<label class="cvs-ready-worker-row">
                    <input type="checkbox" class="cvs-ready-worker-cb" data-worker-id="${escapeHtml(String(wid))}"${checked}${disabled}>
                    <span>
                        <strong>${escapeHtml(w.worker_name || `Worker #${wid}`)}</strong>
                        <span class="cvs-ready-worker-meta">#${escapeHtml(String(wid))} - Passport: ${escapeHtml(passport)} - ${escapeHtml(depHint)}</span>
                    </span>
                </label>`;
            })
            .join('');
        updateReadyWizardUi();
    }

    async function loadReadyWorkers() {
        try {
            const json = await fetchJson('../api/partnerships/partner-cvs-ready-workers.php', {
                credentials: 'same-origin',
            });
            const workers = json.data && Array.isArray(json.data.workers) ? json.data.workers : [];
            state.readyWorkers = workers;
            renderReadyWorkerList();
        } catch (e) {
            state.readyWorkers = [];
            state.selectedReadyWorkerIds.clear();
            const box = $('cvsReadyWorkerList');
            if (box) {
                box.innerHTML = `<p class="cvs-ready-empty">${escapeHtml(e && e.message ? e.message : 'Could not load ready workers.')}</p>`;
            }
            updateReadyWizardUi();
        }
    }

    function labelForType(type) {
        return state.documentLabels[type] || type;
    }

    function buildWorkerProfileHref(workerId) {
        const qs = new URLSearchParams(window.location.search || '');
        qs.delete('id');
        qs.delete('edit');
        qs.set('view', String(workerId));
        if (state.partnerId > 0) {
            qs.set('return_partner_agency', String(state.partnerId));
        }
        return `Worker.php?${qs.toString()}`;
    }

    function openWorkerCvModal(workerId) {
        const modal = $('cvsWorkerModal');
        const iframe = $('cvsWorkerIframe');
        const title = $('cvsWorkerModalTitle');
        if (!modal || !iframe) return;
        const wid = Number(workerId);
        const rowForName = state.rows.find((r) => r.worker_id === wid);
        const fromReady = state.readyWorkers.find((w) => w.id === wid);
        const name = (rowForName && rowForName.worker_name) || (fromReady && fromReady.worker_name) || `Worker #${wid}`;
        iframe.src = withContext(buildWorkerProfileHref(wid));
        if (title) title.textContent = String(name).trim() || `Worker #${wid}`;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeWorkerCvModal() {
        const modal = $('cvsWorkerModal');
        const iframe = $('cvsWorkerIframe');
        if (iframe) iframe.src = 'about:blank';
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    function populateWorkerQuickSelect() {
        const sel = $('cvsWorkerQuickSelect');
        if (!sel) return;
        const map = new Map();
        state.rows.forEach((r) => {
            if (!map.has(r.worker_id)) {
                map.set(r.worker_id, r.worker_name || `Worker #${r.worker_id}`);
            }
        });
        const sorted = [...map.entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])));
        sel.innerHTML =
            '<option value="">Select worker (all their CV rows)…</option>' +
            sorted
                .map(
                    ([id, name]) =>
                        `<option value="${escapeHtml(String(id))}">${escapeHtml(String(name))} (#${escapeHtml(String(id))})</option>`
                )
                .join('');
    }

    async function fetchJson(url, options) {
        const res = await fetch(withContext(url), options || { credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success !== true) {
            throw new Error(json.message || `Request failed (${res.status})`);
        }
        return json;
    }

    function flattenProfileRows(profileWorkers) {
        const out = [];
        (Array.isArray(profileWorkers) ? profileWorkers : []).forEach((w) => {
            const workerId = Number(w.id || 0);
            const workerName = String(w.worker_name || `Worker #${workerId}`);
            const passport = String(w.passport_number || '');
            const docs = Array.isArray(w.documents) ? w.documents : [];
            docs.forEach((d) => {
                const type = String(d.type || '');
                if (!type) return;
                out.push({
                    key: `${workerId}|${type}`,
                    worker_id: workerId,
                    worker_name: workerName,
                    passport_number: passport,
                    document_type: type,
                    document_label: String(d.label || labelForType(type)),
                    has_file: !!d.has_file,
                    shared_on_portal: !!d.shared_on_portal,
                    share_id: d.share_id != null ? Number(d.share_id) : null,
                });
            });
        });
        return out;
    }

    function renderDocumentTypeOptions() {
        const docFilter = $('cvsDocFilter');
        const bulkEdit = $('cvsBulkEditType');
        const options = state.documentTypes
            .map((t) => `<option value="${escapeHtml(t)}">${escapeHtml(labelForType(t))}</option>`)
            .join('');
        if (docFilter) {
            const cur = docFilter.value;
            docFilter.innerHTML = `<option value="">All document types</option>${options}`;
            if (cur) docFilter.value = cur;
        }
        if (bulkEdit) {
            const cur = bulkEdit.value;
            bulkEdit.innerHTML = `<option value="">Edit type to…</option>${options}`;
            if (cur) bulkEdit.value = cur;
        }
    }

    function getCurrentPageRows() {
        const start = (state.page - 1) * state.pageSize;
        return state.filtered.slice(start, start + state.pageSize);
    }

    function selectedWorkerIdsFromTableSelection() {
        const ids = new Set();
        state.rows.forEach((r) => {
            if (state.selectedKeys.has(r.key) && Number(r.worker_id) > 0) {
                ids.add(Number(r.worker_id));
            }
        });
        return [...ids];
    }

    function getWorkerIdsForSendAction() {
        if (state.selectedReadyWorkerIds.size > 0) {
            return [...state.selectedReadyWorkerIds];
        }
        return selectedWorkerIdsFromTableSelection();
    }

    function updateSendButtonState() {
        const sendBtn = $('cvsSendToPartnerBtn');
        if (!sendBtn) return;
        const partnerOk = state.partnerId > 0;
        const hasReadySelection = state.selectedReadyWorkerIds.size > 0;
        const tableWorkerIds = selectedWorkerIdsFromTableSelection();
        const hasTableSelection = tableWorkerIds.length > 0;
        const valid = validPartnerIntersection();
        const readySelectionOk =
            hasReadySelection &&
            (valid === null || (valid && valid.size > 0 && valid.has(state.partnerId)));
        sendBtn.disabled = !(partnerOk && (readySelectionOk || (!hasReadySelection && hasTableSelection)));
        const count = hasReadySelection ? state.selectedReadyWorkerIds.size : tableWorkerIds.length;
        sendBtn.textContent =
            count > 0
                ? `Send ${count} selected CV${count === 1 ? '' : 's'} to this partner`
                : 'Send selected CVs to this partner';
    }

    function syncSelectionUi() {
        const selectedCount = state.selectedKeys.size;
        const label = $('cvsSelectionLabel');
        const bShare = $('cvsBulkShareBtn');
        const bRemove = $('cvsBulkRemoveBtn');
        const bEdit = $('cvsBulkEditBtn');
        const bClear = $('cvsClearSelectionBtn');
        if (label) label.textContent = `${selectedCount} selected`;
        if (bShare) bShare.disabled = selectedCount === 0 || state.partnerId <= 0;
        if (bRemove) bRemove.disabled = selectedCount === 0 || state.partnerId <= 0;
        if (bEdit) bEdit.disabled = selectedCount === 0 || state.partnerId <= 0;
        if (bClear) bClear.disabled = selectedCount === 0;
        updateSendButtonState();

        const selectAll = $('cvsSelectAll');
        if (!selectAll) return;
        const pageRows = getCurrentPageRows();
        if (!pageRows.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            selectAll.disabled = true;
            return;
        }
        selectAll.disabled = false;
        const selectedOnPage = pageRows.filter((r) => state.selectedKeys.has(r.key)).length;
        if (selectedOnPage === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (selectedOnPage === pageRows.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    function renderTable() {
        updateFilteredToolbar();
        const body = $('cvsControlBody');
        const info = $('cvsPageInfo');
        const prev = $('cvsPrevPage');
        const next = $('cvsNextPage');
        if (!body) return;

        const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
        state.page = Math.min(state.page, totalPages);
        const rows = getCurrentPageRows();
        if (!rows.length) {
            showTableMessage(state.partnerId > 0 ? 'No rows match your filters.' : 'Select a partner agency first.');
        } else {
            body.innerHTML = rows
                .map((r) => {
                    const hasFileText = r.has_file ? 'Yes' : 'No';
                    const hasFileClass = r.has_file ? 'tag-ok' : 'tag-muted';
                    const shareText = r.shared_on_portal ? 'Shared' : 'Not shared';
                    const shareClass = r.shared_on_portal ? 'tag-ok' : 'tag-muted';
                    const typeOptions = state.documentTypes
                        .map((t) => {
                            const sel = t === r.document_type ? ' selected' : '';
                            return `<option value="${escapeHtml(t)}"${sel}>${escapeHtml(labelForType(t))}</option>`;
                        })
                        .join('');
                    const actionButton = r.shared_on_portal
                        ? `<button type="button" class="muted-btn" data-action="row-remove" data-key="${escapeHtml(r.key)}">Remove</button>`
                        : `<button type="button" class="muted-btn" data-action="row-share" data-key="${escapeHtml(r.key)}" ${r.has_file ? '' : 'disabled'}>Add</button>`;

                    return `<tr data-key="${escapeHtml(r.key)}">
                        <td class="col-select">
                            <input type="checkbox" class="cvs-row-check" data-key="${escapeHtml(r.key)}" ${state.selectedKeys.has(r.key) ? 'checked' : ''}>
                        </td>
                        <td>${escapeHtml(r.worker_name)} <div class="table-mini">#${escapeHtml(String(r.worker_id))}</div></td>
                        <td>${escapeHtml(r.passport_number || '—')}</td>
                        <td>${escapeHtml(r.document_label)}</td>
                        <td><span class="table-tag ${hasFileClass}">${hasFileText}</span></td>
                        <td><span class="table-tag ${shareClass}">${shareText}</span></td>
                        <td class="actions-cell">
                            <button type="button" class="muted-btn" data-action="open-worker-profile" data-worker-id="${escapeHtml(String(r.worker_id))}">Profile</button>
                            ${actionButton}
                            <label class="cvs-row-edit-wrap">
                                <select class="cvs-row-type-select">${typeOptions}</select>
                                <button type="button" class="muted-btn" data-action="row-edit" data-key="${escapeHtml(r.key)}" ${r.shared_on_portal ? '' : 'disabled'}>Edit</button>
                            </label>
                        </td>
                    </tr>`;
                })
                .join('');
        }
        if (info) info.textContent = `Page ${state.page} / ${totalPages}`;
        if (prev) prev.disabled = state.page <= 1;
        if (next) next.disabled = state.page >= totalPages;
        syncSelectionUi();
    }

    function applyFilters() {
        const search = String(($('cvsSearch') && $('cvsSearch').value) || '')
            .toLowerCase()
            .trim();
        const sharedFilter = String(($('cvsSharedFilter') && $('cvsSharedFilter').value) || '').trim();
        const fileFilter = String(($('cvsFileFilter') && $('cvsFileFilter').value) || '').trim();
        const docFilter = String(($('cvsDocFilter') && $('cvsDocFilter').value) || '').trim();

        state.filtered = state.rows.filter((r) => {
            const hay = `${r.worker_name} ${r.passport_number} ${r.document_label} ${r.document_type}`
                .toLowerCase();
            if (search && hay.indexOf(search) === -1) return false;
            if (sharedFilter === 'shared' && !r.shared_on_portal) return false;
            if (sharedFilter === 'not_shared' && r.shared_on_portal) return false;
            if (fileFilter === 'has_file' && !r.has_file) return false;
            if (fileFilter === 'missing_file' && r.has_file) return false;
            if (docFilter && r.document_type !== docFilter) return false;
            return true;
        });
        state.page = 1;
        renderTable();
    }

    async function loadPartners() {
        const sel = $('cvsPartnerSelect');
        if (!sel) return;
        setNotice('Loading partner agencies…', 'success');
        try {
            const json = await fetchJson('../api/partnerships/partner-agencies.php', {
                credentials: 'same-origin',
            });
            const rows = Array.isArray(json.data) ? json.data : [];
            sel.innerHTML = '<option value="">Select partner agency…</option>';
            rows.forEach((r) => {
                const id = Number(r.id || 0);
                if (!id) return;
                const o = document.createElement('option');
                o.value = String(id);
                o.textContent = String(r.name || `Agency #${id}`);
                sel.appendChild(o);
            });
            const q = new URLSearchParams(window.location.search || '');
            const initialId = parseInt(String(q.get('partner_agency_id') || ''), 10);
            if (Number.isFinite(initialId) && initialId > 0) {
                sel.value = String(initialId);
            }
            state.partnerId = parseInt(String(sel.value || '0'), 10) || 0;
            applyPartnerOptionAvailability();
            updateReadyWizardUi();
            setNotice('', '');
            if (state.partnerId > 0) {
                await loadPartnerRows(state.partnerId);
            } else {
                state.rows = [];
                state.filtered = [];
                state.selectedKeys.clear();
                renderTable();
            }
        } catch (e) {
            setNotice(e && e.message ? e.message : 'Could not load partner agencies.', 'error');
        }
    }

    async function loadPartnerRows(partnerAgencyId) {
        state.partnerId = Number(partnerAgencyId || 0);
        state.selectedKeys.clear();
        if (!state.partnerId) {
            state.rows = [];
            state.filtered = [];
            populateWorkerQuickSelect();
            renderTable();
            return;
        }
        showTableMessage('Loading…');
        setNotice('', '');
        try {
            const json = await fetchJson(
                `../api/partnerships/partner-agency-worker-shares.php?partner_agency_id=${encodeURIComponent(
                    String(state.partnerId)
                )}`,
                { credentials: 'same-origin' }
            );
            const d = json.data || {};
            state.documentTypes = Array.isArray(d.document_types) ? d.document_types : [];
            state.documentLabels =
                d.document_labels && typeof d.document_labels === 'object' ? d.document_labels : {};
            state.rows = flattenProfileRows(d.workers_profile_documents);
            populateWorkerQuickSelect();
            renderDocumentTypeOptions();
            applyFilters();
            setNotice(`Loaded ${state.rows.length} rows.`, 'success');
        } catch (e) {
            state.rows = [];
            state.filtered = [];
            populateWorkerQuickSelect();
            renderTable();
            setNotice(e && e.message ? e.message : 'Could not load rows.', 'error');
        }
    }

    function selectedRows() {
        return state.rows.filter((r) => state.selectedKeys.has(r.key));
    }

    async function bulkShare(rows) {
        let added = 0;
        let skipped = 0;
        let failed = 0;
        for (let i = 0; i < rows.length; i += 1) {
            const r = rows[i];
            if (r.shared_on_portal || !r.has_file) {
                skipped += 1;
                continue;
            }
            try {
                await fetchJson('../api/partnerships/partner-agency-worker-shares.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        partner_agency_id: state.partnerId,
                        worker_id: r.worker_id,
                        document_type: r.document_type,
                    }),
                });
                added += 1;
            } catch (e) {
                failed += 1;
            }
        }
        return { added, skipped, failed };
    }

    async function bulkRemove(rows) {
        let removed = 0;
        let skipped = 0;
        let failed = 0;
        for (let i = 0; i < rows.length; i += 1) {
            const r = rows[i];
            if (!r.shared_on_portal || !r.share_id) {
                skipped += 1;
                continue;
            }
            try {
                await fetchJson(
                    `../api/partnerships/partner-agency-worker-shares.php?id=${encodeURIComponent(
                        String(r.share_id)
                    )}&partner_agency_id=${encodeURIComponent(String(state.partnerId))}`,
                    { method: 'DELETE', credentials: 'same-origin' }
                );
                removed += 1;
            } catch (e) {
                failed += 1;
            }
        }
        return { removed, skipped, failed };
    }

    async function bulkEditType(rows, toType) {
        let edited = 0;
        let skipped = 0;
        let failed = 0;
        for (let i = 0; i < rows.length; i += 1) {
            const r = rows[i];
            if (!r.shared_on_portal || !r.share_id || r.document_type === toType) {
                skipped += 1;
                continue;
            }
            try {
                await fetchJson('../api/partnerships/partner-agency-worker-shares.php', {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: r.share_id,
                        partner_agency_id: state.partnerId,
                        document_type: toType,
                    }),
                });
                edited += 1;
            } catch (e) {
                failed += 1;
            }
        }
        return { edited, skipped, failed };
    }

    function bindEvents() {
        const partnerSel = $('cvsPartnerSelect');
        const search = $('cvsSearch');
        const shared = $('cvsSharedFilter');
        const file = $('cvsFileFilter');
        const doc = $('cvsDocFilter');
        const sizeSel = $('cvsPageSize');
        const reload = $('cvsReloadBtn');
        const prev = $('cvsPrevPage');
        const next = $('cvsNextPage');
        const selectAll = $('cvsSelectAll');
        const body = $('cvsControlBody');
        const clearBtn = $('cvsClearSelectionBtn');
        const bulkShareBtn = $('cvsBulkShareBtn');
        const bulkRemoveBtn = $('cvsBulkRemoveBtn');
        const bulkEditBtn = $('cvsBulkEditBtn');
        const bulkEditType = $('cvsBulkEditType');
        const selectFilteredBtn = $('cvsSelectFilteredBtn');
        const exportCsvBtn = $('cvsExportCsvBtn');
        const workerQuick = $('cvsWorkerQuickSelect');
        const readySearch = $('cvsReadySearch');
        const selectAllReadyBtn = $('cvsSelectAllReadyBtn');
        const clearReadyBtn = $('cvsClearReadyBtn');
        const sendToPartnerBtn = $('cvsSendToPartnerBtn');
        const readyList = $('cvsReadyWorkerList');

        if (partnerSel) {
            partnerSel.addEventListener('change', async () => {
                const pid = parseInt(String(partnerSel.value || '0'), 10) || 0;
                await loadPartnerRows(pid);
                updateReadyWizardUi();
            });
        }
        if (readySearch) {
            readySearch.addEventListener('input', () => {
                state.readySearch = String(readySearch.value || '');
                renderReadyWorkerList();
            });
        }
        if (selectAllReadyBtn) {
            selectAllReadyBtn.addEventListener('click', () => {
                getFilteredReadyWorkers().forEach((w) => {
                    if (Array.isArray(w.partner_agency_ids) && w.partner_agency_ids.length > 0) {
                        state.selectedReadyWorkerIds.add(w.id);
                    }
                });
                updateReadyWizardUi();
                renderReadyWorkerList();
            });
        }
        if (clearReadyBtn) {
            clearReadyBtn.addEventListener('click', () => {
                state.selectedReadyWorkerIds.clear();
                updateReadyWizardUi();
                renderReadyWorkerList();
            });
        }
        if (sendToPartnerBtn) {
            sendToPartnerBtn.addEventListener('click', async () => {
                const workerIds = getWorkerIdsForSendAction();
                if (workerIds.length === 0 || state.partnerId <= 0) {
                    setNotice('Select workers first (top checklist or selected table rows).', 'error');
                    return;
                }
                const n = workerIds.length;
                if (
                    !window.confirm(
                        `Send all uploaded document files for ${n} worker(s) to this partner portal? (Already shared documents are skipped.)`
                    )
                ) {
                    return;
                }
                try {
                    setNotice('Sending...', 'success');
                    const json = await fetchJson('../api/partnerships/partner-cvs-send-to-partner.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            partner_agency_id: state.partnerId,
                            worker_ids: workerIds,
                        }),
                    });
                    const d = (json && json.data) || {};
                    setNotice(
                        `Done: ${d.added != null ? d.added : 0} added, ${d.skipped != null ? d.skipped : 0} skipped (already shared), ${d.not_deployed != null ? d.not_deployed : 0} not deployed to this partner, ${d.failed != null ? d.failed : 0} failed.`,
                        d.failed > 0 ? 'error' : 'success'
                    );
                    state.selectedReadyWorkerIds.clear();
                    state.selectedKeys.clear();
                    await loadReadyWorkers();
                    await loadPartnerRows(state.partnerId);
                    updateReadyWizardUi();
                } catch (e) {
                    setNotice(e && e.message ? e.message : 'Send failed.', 'error');
                }
            });
        }
        if (readyList) {
            readyList.addEventListener('change', (e) => {
                const cb = e.target.closest('.cvs-ready-worker-cb');
                if (!cb || cb.disabled) return;
                const id = parseInt(String(cb.getAttribute('data-worker-id') || ''), 10);
                if (!Number.isFinite(id) || id <= 0) return;
                if (cb.checked) state.selectedReadyWorkerIds.add(id);
                else state.selectedReadyWorkerIds.delete(id);
                updateReadyWizardUi();
            });
        }
        if (workerQuick) {
            workerQuick.addEventListener('change', () => {
                const wid = parseInt(String(workerQuick.value || ''), 10);
                workerQuick.value = '';
                if (!Number.isFinite(wid) || wid <= 0 || state.rows.length === 0) return;
                state.selectedKeys.clear();
                const picked = state.rows.filter((r) => r.worker_id === wid);
                picked.forEach((r) => state.selectedKeys.add(r.key));
                renderTable();
                setNotice(`Selected ${picked.length} document row(s) for worker #${wid}. Use Bulk Add to share with this partner.`, 'success');
            });
        }

        [search, shared, file, doc].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });
        if (sizeSel) {
            sizeSel.addEventListener('change', () => {
                state.pageSize = parseInt(String(sizeSel.value || '25'), 10) || 25;
                state.page = 1;
                renderTable();
            });
        }
        if (reload) {
            reload.addEventListener('click', async () => {
                await loadPartnerRows(state.partnerId);
            });
        }
        if (prev) {
            prev.addEventListener('click', () => {
                state.page = Math.max(1, state.page - 1);
                renderTable();
            });
        }
        if (next) {
            next.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
                state.page = Math.min(totalPages, state.page + 1);
                renderTable();
            });
        }
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                const rows = getCurrentPageRows();
                rows.forEach((r) => {
                    if (selectAll.checked) state.selectedKeys.add(r.key);
                    else state.selectedKeys.delete(r.key);
                });
                renderTable();
            });
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                state.selectedKeys.clear();
                renderTable();
            });
        }
        if (bulkShareBtn) {
            bulkShareBtn.addEventListener('click', async () => {
                const rows = selectedRows();
                if (!rows.length) return;
                setNotice('Running bulk add…', 'success');
                const res = await bulkShare(rows);
                setNotice(
                    `Bulk add done: ${res.added} added, ${res.skipped} skipped, ${res.failed} failed.`,
                    res.failed > 0 ? 'error' : 'success'
                );
                await loadPartnerRows(state.partnerId);
            });
        }
        if (bulkRemoveBtn) {
            bulkRemoveBtn.addEventListener('click', async () => {
                const rows = selectedRows();
                if (!rows.length) return;
                if (!window.confirm('Remove selected shares from partner portal?')) return;
                setNotice('Running bulk remove…', 'success');
                const res = await bulkRemove(rows);
                setNotice(
                    `Bulk remove done: ${res.removed} removed, ${res.skipped} skipped, ${res.failed} failed.`,
                    res.failed > 0 ? 'error' : 'success'
                );
                await loadPartnerRows(state.partnerId);
            });
        }
        if (bulkEditBtn) {
            bulkEditBtn.addEventListener('click', async () => {
                const toType = String((bulkEditType && bulkEditType.value) || '').trim();
                if (!toType) {
                    setNotice('Choose a document type for bulk edit.', 'error');
                    return;
                }
                const rows = selectedRows();
                if (!rows.length) return;
                setNotice('Running bulk edit…', 'success');
                const res = await bulkEditType(rows, toType);
                setNotice(
                    `Bulk edit done: ${res.edited} edited, ${res.skipped} skipped, ${res.failed} failed.`,
                    res.failed > 0 ? 'error' : 'success'
                );
                await loadPartnerRows(state.partnerId);
            });
        }
        if (selectFilteredBtn) {
            selectFilteredBtn.addEventListener('click', () => {
                const n = state.filtered.length;
                if (!n || state.partnerId <= 0) return;
                state.filtered.forEach((r) => state.selectedKeys.add(r.key));
                renderTable();
                setNotice(`Selected ${n} filtered rows (all pages).`, 'success');
            });
        }
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => exportFilteredCsv());
        }

        const advBtn = $('cvsToggleAdvancedBtn');
        const advPanel = $('cvsAdvancedPanel');
        const ADV_STORAGE = 'partnerCvsAdvancedOpen';
        function setAdvancedOpen(open) {
            if (!advBtn || !advPanel) return;
            if (open) {
                advPanel.removeAttribute('hidden');
                advBtn.setAttribute('aria-expanded', 'true');
                advBtn.textContent = 'Hide advanced';
            } else {
                advPanel.setAttribute('hidden', '');
                advBtn.setAttribute('aria-expanded', 'false');
                advBtn.textContent = 'Show advanced';
            }
            try {
                localStorage.setItem(ADV_STORAGE, open ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
        }
        if (advBtn && advPanel) {
            let initialOpen = false;
            try {
                initialOpen = localStorage.getItem(ADV_STORAGE) === '1';
            } catch (e) {
                initialOpen = false;
            }
            setAdvancedOpen(initialOpen);
            advBtn.addEventListener('click', () => {
                const next = advPanel.hasAttribute('hidden');
                setAdvancedOpen(next);
            });
        }

        const modalClose = $('cvsWorkerModalClose');
        const workerModal = $('cvsWorkerModal');
        if (modalClose) {
            modalClose.addEventListener('click', () => closeWorkerCvModal());
        }
        if (workerModal) {
            workerModal.addEventListener('click', (e) => {
                if (e.target === workerModal) closeWorkerCvModal();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && workerModal && workerModal.classList.contains('open')) {
                closeWorkerCvModal();
            }
        });

        if (body) {
            body.addEventListener('change', (e) => {
                const check = e.target.closest('.cvs-row-check');
                if (check) {
                    const key = check.getAttribute('data-key') || '';
                    if (!key) return;
                    if (check.checked) state.selectedKeys.add(key);
                    else state.selectedKeys.delete(key);
                    syncSelectionUi();
                }
            });

            body.addEventListener('click', async (e) => {
                const profBtn = e.target.closest('button[data-action="open-worker-profile"]');
                if (profBtn) {
                    e.preventDefault();
                    const wid = parseInt(String(profBtn.getAttribute('data-worker-id') || ''), 10);
                    if (Number.isFinite(wid) && wid > 0) openWorkerCvModal(wid);
                    return;
                }

                const btn = e.target.closest('button[data-action]');
                if (!btn) return;
                const action = btn.getAttribute('data-action') || '';
                const key = btn.getAttribute('data-key') || '';
                const row = state.rows.find((r) => r.key === key);
                if (!row) return;

                if (action === 'row-share') {
                    try {
                        await fetchJson('../api/partnerships/partner-agency-worker-shares.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                partner_agency_id: state.partnerId,
                                worker_id: row.worker_id,
                                document_type: row.document_type,
                            }),
                        });
                        setNotice('Share added.', 'success');
                        await loadPartnerRows(state.partnerId);
                    } catch (err) {
                        setNotice(err && err.message ? err.message : 'Could not add share.', 'error');
                    }
                    return;
                }

                if (action === 'row-remove') {
                    if (!row.share_id) return;
                    if (!window.confirm('Remove this share?')) return;
                    try {
                        await fetchJson(
                            `../api/partnerships/partner-agency-worker-shares.php?id=${encodeURIComponent(
                                String(row.share_id)
                            )}&partner_agency_id=${encodeURIComponent(String(state.partnerId))}`,
                            { method: 'DELETE', credentials: 'same-origin' }
                        );
                        setNotice('Share removed.', 'success');
                        await loadPartnerRows(state.partnerId);
                    } catch (err) {
                        setNotice(err && err.message ? err.message : 'Could not remove share.', 'error');
                    }
                    return;
                }

                if (action === 'row-edit') {
                    if (!row.share_id) return;
                    const tr = btn.closest('tr');
                    const sel = tr ? tr.querySelector('.cvs-row-type-select') : null;
                    const toType = String((sel && sel.value) || '').trim();
                    if (!toType) return;
                    try {
                        await fetchJson('../api/partnerships/partner-agency-worker-shares.php', {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: row.share_id,
                                partner_agency_id: state.partnerId,
                                document_type: toType,
                            }),
                        });
                        setNotice('Share updated.', 'success');
                        await loadPartnerRows(state.partnerId);
                    } catch (err) {
                        setNotice(err && err.message ? err.message : 'Could not edit share.', 'error');
                    }
                }
            });
        }
    }

    function init() {
        bindEvents();
        void Promise.all([loadReadyWorkers(), loadPartners()]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
