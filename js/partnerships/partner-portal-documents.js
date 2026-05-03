/**
 * Partner portal — Documents & CVs full table (search, sort, paginate, download / open).
 */
(function () {
    const DATE_LOCALE = 'en-US';

    /** Set in init() after inline config in the page body (page JS loads in head). */
    let staffCfg = null;
    let staffMode = false;

    function refreshStaffMode() {
        staffCfg =
            typeof window !== 'undefined' && window.RATIB_PARTNER_DOCS_STAFF && window.RATIB_PARTNER_DOCS_STAFF.partner_agency_id
                ? window.RATIB_PARTNER_DOCS_STAFF
                : null;
        staffMode = !!staffCfg;
    }

    const state = {
        rows: [],
        filtered: [],
        sortKey: 'created_at',
        sortDir: 'desc',
        page: 1,
        pageSize: 25,
        agencyName: '',
        staffHighlightActive: false,
    };

    /** Staff bulk selection (document row keys: `worker_share|id` or `agency_cv|id`). */
    let selectedDocKeys = new Set();

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

    function formatBytes(n) {
        if (n == null || n === '') return '—';
        const x = Number(n);
        if (!Number.isFinite(x) || x < 0) return '—';
        if (x === 0) return '0 B';
        if (x < 1024) return `${x} B`;
        if (x < 1024 * 1024) return `${(x / 1024).toFixed(1)} KB`;
        return `${(x / (1024 * 1024)).toFixed(2)} MB`;
    }

    /** Short label for MIME in table Type column */
    function formatMimeTypeDisplay(mime) {
        const m = mime != null ? String(mime).trim() : '';
        if (m === '') return '—';
        const lower = m.toLowerCase();
        if (lower === 'application/pdf') return 'PDF';
        if (lower === 'image/jpeg' || lower === 'image/jpg') return 'JPEG';
        if (lower === 'image/png') return 'PNG';
        if (lower === 'image/webp') return 'WebP';
        if (lower.indexOf('image/') === 0) return 'Image';
        if (lower.indexOf('video/') === 0) return 'Video';
        const cut = m.length > 36 ? `${m.slice(0, 33)}…` : m;
        return cut;
    }

    const PORTAL_STATUS_LABELS = {
        waiting: 'Waiting',
        processing: 'Processing',
        ready: 'Ready',
        issues: 'Issues',
        returned: 'Returned',
        transferred: 'Transferred',
    };

    const PORTAL_STATUS_CARD_ORDER = ['waiting', 'processing', 'ready', 'issues', 'returned', 'transferred'];

    /** Shown when a worker share has no file on the server yet (honest placeholders, never blank). */
    const WORKER_SHARE_NO_FILE = {
        fileLabel: 'Awaiting upload',
        typeLabel: 'Pending',
        sizeLabel: 'N/A',
    };

    function workerShareNoFilePathHint(r) {
        const docTypeHint = String(r._document_type || '').trim().toLowerCase() || 'document';
        return (
            'Upload in Workers so the real file name, type, and size appear. Expected folder: uploads/workers/' +
            String(r._worker_id || '') +
            '/documents/' +
            docTypeHint +
            '/'
        );
    }

    function normalizePortalStatusSlug(raw, fallback) {
        let s = raw != null ? String(raw).trim().toLowerCase() : '';
        if (s === 'issue') {
            s = 'issues';
        }
        if (s === 'deployed') {
            s = 'ready';
        }
        if (s && Object.prototype.hasOwnProperty.call(PORTAL_STATUS_LABELS, s)) {
            return s;
        }
        return fallback != null && String(fallback).trim() !== '' ? String(fallback).trim().toLowerCase() : 'waiting';
    }

    function formatPortalStatusLabel(slug) {
        const k = normalizePortalStatusSlug(slug, 'waiting');
        return PORTAL_STATUS_LABELS[k] || (k ? k.charAt(0).toUpperCase() + k.slice(1) : '—');
    }

    function countRowsByPortalStatus(rows) {
        const m = {};
        PORTAL_STATUS_CARD_ORDER.forEach((k) => {
            m[k] = 0;
        });
        (rows || []).forEach((r) => {
            const slug = normalizePortalStatusSlug(
                r.portal_status,
                r._kind === 'worker_share' ? (r._hasFile ? 'processing' : 'waiting') : 'ready'
            );
            if (m[slug] !== undefined) {
                m[slug] += 1;
            }
        });
        return m;
    }

    function updateStatusCards() {
        const wrap = $('ppDocsStatusCards');
        const inner = $('ppDocsStatusCardsInner');
        if (!wrap || !inner) {
            return;
        }
        const rows = Array.isArray(state.filtered) ? state.filtered : [];
        const counts = countRowsByPortalStatus(rows);
        wrap.setAttribute('aria-label', 'Document counts for current search results');
        inner.innerHTML = PORTAL_STATUS_CARD_ORDER.map((slug) => {
            const n = counts[slug] || 0;
            const label = formatPortalStatusLabel(slug);
            return (
                '<div class="pp-docs-status-card pp-docs-status-card--' +
                escapeHtml(slug) +
                '" title="' +
                escapeHtml(label) +
                ' — in current results (search filter)">' +
                '<span class="pp-docs-status-card__n">' +
                String(n) +
                '</span>' +
                '<span class="pp-docs-status-card__lbl">' +
                escapeHtml(label) +
                '</span>' +
                '</div>'
            );
        }).join('');
        wrap.hidden = false;
    }

    function formatDate(s) {
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

    function downloadHref(r) {
        if (r && r._kind === 'worker_share') {
            const sid = r._shareId != null ? r._shareId : r.id;
            return `../api/partnerships/partner-shared-worker-doc-download.php?share_id=${encodeURIComponent(String(sid))}`;
        }
        const id = r && r.id != null ? r.id : r;
        return `../api/partnerships/partner-agency-cv-download.php?id=${encodeURIComponent(String(id))}`;
    }

    function workerProfileHref(workerId) {
        const wid = workerId != null ? String(workerId).trim() : '';
        if (!wid) return 'Worker.php';
        const base = `Worker.php?view=${encodeURIComponent(wid)}`;
        const x = staffCfg && staffCfg.worker_profile_extra_query ? String(staffCfg.worker_profile_extra_query) : '';
        return x ? `${base}&${x}` : base;
    }

    function workerEditHref(workerId) {
        const wid = workerId != null ? String(workerId).trim() : '';
        if (!wid) return 'Worker.php';
        const base = `Worker.php?edit=${encodeURIComponent(wid)}`;
        const x = staffCfg && staffCfg.worker_profile_extra_query ? String(staffCfg.worker_profile_extra_query) : '';
        return x ? `${base}&${x}` : base;
    }

    /** Same-origin URL loaded in the CV iframe (staff: Worker.php embed; partner: portal CV page). */
    function workerCvIframeUrl(workerId) {
        const wid = workerId != null ? String(workerId).trim() : '';
        if (!wid) return '';
        if (staffMode) {
            let base = workerProfileHref(workerId);
            base += (base.indexOf('?') !== -1 ? '&' : '?') + 'embed_cv=1';
            return base;
        }
        return `partner-portal-worker-cv.php?worker_id=${encodeURIComponent(wid)}`;
    }

    function closeCvModal() {
        const modal = $('ppCvModal');
        const frame = $('ppCvFrame');
        if (frame) {
            frame.src = 'about:blank';
        }
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        if (!$('ppDocModal') || !$('ppDocModal').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }

    function ensureCvModal() {
        if ($('ppCvModal')) {
            return;
        }
        const wrap = document.createElement('div');
        wrap.id = 'ppCvModal';
        wrap.className = 'modal-wrap partner-portal-modal partner-portal-modal--cv';
        wrap.setAttribute('aria-hidden', 'true');
        wrap.innerHTML =
            '<div class="modal-card glass-card partner-portal-modal-card partner-portal-modal-card--cv" role="dialog" aria-modal="true" aria-labelledby="ppCvModalTitle">' +
            '<div class="partner-portal-modal-head">' +
            '<h3 id="ppCvModalTitle" class="partner-portal-modal-title">Worker CV</h3>' +
            '<button type="button" class="icon-btn" id="ppCvModalCloseX" aria-label="Close">×</button>' +
            '</div>' +
            '<div class="partner-portal-cv-frame-shell">' +
            '<iframe id="ppCvFrame" class="partner-portal-cv-frame" title="Worker CV preview"></iframe>' +
            '</div>' +
            '<div class="partner-portal-modal-footer partner-portal-modal-footer--cv">' +
            '<button type="button" class="muted-btn" id="ppCvModalCloseBtn">Close</button>' +
            '</div>' +
            '</div>';
        document.body.appendChild(wrap);
        const closeCv = () => closeCvModal();
        const bx = $('ppCvModalCloseX');
        const bc = $('ppCvModalCloseBtn');
        if (bx) bx.addEventListener('click', closeCv);
        if (bc) bc.addEventListener('click', closeCv);
        wrap.addEventListener('click', (e) => {
            if (e.target === wrap) closeCv();
        });
    }

    function openCvModal(workerId) {
        closeDocModal();
        ensureCvModal();
        const modal = $('ppCvModal');
        const frame = $('ppCvFrame');
        const url = workerCvIframeUrl(workerId);
        if (!modal || !frame || !url) return;
        frame.src = url;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function docRowKey(r) {
        if (!r || !r._kind) return '';
        return `${r._kind}|${r.id}`;
    }

    /** CV ref: larger number = newer upload; top row when sorted by date desc shows the newest ref. */
    function formatCvTableId(idx) {
        const n = Math.max(0, Math.floor(Number(idx) || 0));
        return 'CV' + String(n).padStart(5, '0');
    }

    function findRowByKey(key) {
        if (!key) return null;
        const pipe = String(key).indexOf('|');
        if (pipe === -1) return null;
        const kind = String(key).slice(0, pipe);
        const id = String(key).slice(pipe + 1);
        return state.rows.find((row) => row._kind === kind && String(row.id) === id) || null;
    }

    function staffContextSuffix() {
        if (!staffMode) return '';
        const u = new URLSearchParams(window.location.search || '');
        const qs = new URLSearchParams();
        if (u.get('control')) qs.set('control', u.get('control'));
        if (u.get('agency_id')) qs.set('agency_id', u.get('agency_id'));
        const s = qs.toString();
        return s ? `&${s}` : '';
    }

    function staffApiUrlWithContext(basePath) {
        const suf = staffContextSuffix();
        if (!suf) return basePath;
        return basePath + (basePath.indexOf('?') === -1 ? '?' : '&') + suf.slice(1);
    }

    /** CSS theme class suffix; manual override uses display_status, else effective portal_status. */
    function selectThemeSlugForRow(r) {
        if (r.display_status != null && String(r.display_status).trim() !== '') {
            return normalizePortalStatusSlug(r.display_status, PORTAL_STATUS_CARD_ORDER[0]);
        }
        return normalizePortalStatusSlug(
            r.portal_status,
            r._kind === 'worker_share' ? (r._hasFile ? 'processing' : 'waiting') : 'ready'
        );
    }

    /** While the row <select> is open / value changed, theme follows the current value (then server after load). */
    function selectVisualSlugFromControl(row, selectEl) {
        const raw = selectEl.value != null ? String(selectEl.value).trim() : '';
        if (raw !== '') {
            return normalizePortalStatusSlug(raw, PORTAL_STATUS_CARD_ORDER[0]);
        }
        return normalizePortalStatusSlug(
            row.portal_status,
            row._kind === 'worker_share' ? (row._hasFile ? 'processing' : 'waiting') : 'ready'
        );
    }

    /** Updates themed <select> and the colored status chip in the same cell (staff table). */
    function syncSoloStatusRowUi(selectEl, row) {
        if (!selectEl || !row || !selectEl.classList || !selectEl.classList.contains('pp-docs-status-select')) return;
        const slug = selectVisualSlugFromControl(row, selectEl);
        selectEl.className =
            'partner-portal-input pp-docs-status-select pp-doc-status-select-solo pp-doc-status-select-solo--' + slug;
        const cell = selectEl.closest('.pp-docs-status-cell');
        if (!cell) return;
        const chip = cell.querySelector('[data-pp-doc-status-chip]');
        if (!chip) return;
        chip.textContent = formatPortalStatusLabel(slug);
        chip.className = 'pp-doc-status pp-doc-status--' + slug;
    }

    function buildPortalStatusSelectOptions(displayStatusField) {
        const selVal =
            displayStatusField != null && String(displayStatusField).trim() !== ''
                ? normalizePortalStatusSlug(displayStatusField, PORTAL_STATUS_CARD_ORDER[0])
                : '';
        const autoSel = selVal === '' ? ' selected' : '';
        let html = `<option value=""${autoSel}>${escapeHtml('Auto (file + assignment)')}</option>`;
        PORTAL_STATUS_CARD_ORDER.forEach((slug) => {
            const lab = formatPortalStatusLabel(slug);
            const selected = slug === selVal ? ' selected' : '';
            html += `<option value="${escapeHtml(slug)}"${selected}>${escapeHtml(lab)}</option>`;
        });
        return html;
    }

    function initBulkStatusSelect() {
        const sel = $('ppDocsBulkStatusSelect');
        if (!sel || !staffMode) return;
        const parts = [
            '<option value="" selected>Set for selected rows…</option>',
            `<option value="__auto__">${escapeHtml('Auto (file + assignment)')}</option>`,
        ];
        PORTAL_STATUS_CARD_ORDER.forEach((slug) => {
            parts.push(
                `<option value="${escapeHtml(slug)}">${escapeHtml(formatPortalStatusLabel(slug))}</option>`
            );
        });
        sel.innerHTML = parts.join('');
    }

    async function patchDocumentDisplayStatus(row, displayStatusForApi) {
        if (!staffMode || !staffCfg || !row || !row._kind) return false;
        const pid = parseInt(String(staffCfg.partner_agency_id || ''), 10);
        if (!Number.isFinite(pid) || pid <= 0) return false;
        let base;
        const body = {
            partner_agency_id: pid,
            display_status:
                displayStatusForApi === '' || displayStatusForApi === undefined || displayStatusForApi === null
                    ? null
                    : displayStatusForApi,
        };
        if (row._kind === 'worker_share') {
            const sid = row._shareId != null ? row._shareId : row.id;
            if (!Number.isFinite(Number(sid)) || Number(sid) <= 0) return false;
            body.id = Number(sid);
            base = '../api/partnerships/partner-agency-worker-shares.php';
        } else if (row._kind === 'agency_cv') {
            if (!Number.isFinite(Number(row.id)) || Number(row.id) <= 0) return false;
            body.id = Number(row.id);
            base = '../api/partnerships/partner-agency-cvs.php';
        } else {
            return false;
        }
        const url = staffApiUrlWithContext(base);
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const json = await res.json().catch(() => ({}));
            return !!(res.ok && json.success);
        } catch (_e) {
            return false;
        }
    }

    function updateBulkDeleteUi() {
        const btn = $('ppDocsDeleteSelected');
        const cnt = $('ppDocsSelectedCount');
        const bulkSel = $('ppDocsBulkStatusSelect');
        if (!staffMode) return;
        const n = selectedDocKeys.size;
        if (btn) {
            btn.hidden = n === 0;
            btn.disabled = n === 0;
        }
        if (bulkSel) {
            bulkSel.disabled = n === 0;
            if (n === 0) {
                bulkSel.selectedIndex = 0;
            }
        }
        const bulkBtn = $('ppDocsApplyStatusSelected');
        if (bulkBtn) {
            bulkBtn.disabled = n === 0;
        }
        if (cnt) {
            cnt.hidden = n === 0;
            cnt.textContent = n > 0 ? `${n} selected` : '';
        }
    }

    function syncSelectAllCheckbox(slice) {
        const el = $('ppDocsSelectAll');
        if (!el || !staffMode || !Array.isArray(slice)) return;
        const keys = slice.map((r) => docRowKey(r)).filter(Boolean);
        const nSel = keys.filter((k) => selectedDocKeys.has(k)).length;
        el.checked = keys.length > 0 && nSel === keys.length;
        el.indeterminate = nSel > 0 && nSel < keys.length;
    }

    async function deleteDocumentRow(r) {
        if (!staffMode || !staffCfg || !r || !r._kind) return false;
        const pid = parseInt(String(staffCfg.partner_agency_id || ''), 10);
        if (!Number.isFinite(pid) || pid <= 0) return false;
        const suf = staffContextSuffix();
        let url = '';
        if (r._kind === 'worker_share') {
            const sid = r._shareId != null ? r._shareId : r.id;
            if (!Number.isFinite(Number(sid)) || Number(sid) <= 0) return false;
            url = `../api/partnerships/partner-agency-worker-shares.php?id=${encodeURIComponent(String(sid))}&partner_agency_id=${encodeURIComponent(String(pid))}${suf}`;
        } else if (r._kind === 'agency_cv') {
            const cid = r.id;
            if (!Number.isFinite(Number(cid)) || Number(cid) <= 0) return false;
            url = `../api/partnerships/partner-agency-cvs.php?id=${encodeURIComponent(String(cid))}&partner_agency_id=${encodeURIComponent(String(pid))}${suf}`;
        } else {
            return false;
        }
        try {
            const res = await fetch(url, { method: 'DELETE', credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            return !!(res.ok && json.success);
        } catch (_e) {
            return false;
        }
    }

    async function handleDeleteRow(row) {
        const ok = await deleteDocumentRow(row);
        if (ok) {
            selectedDocKeys.delete(docRowKey(row));
            closeDocModal();
            await load();
            updateBulkDeleteUi();
        } else if (staffMode) {
            setError('Could not delete this item. Check permissions or try again.');
        }
        return ok;
    }

    function closeDocModal() {
        const modal = $('ppDocModal');
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    function openDocModal(r) {
        const modal = $('ppDocModal');
        const titleEl = $('ppDocModalTitle');
        const lead = $('ppDocModalLead');
        const dl = $('ppDocModalDl');
        const links = $('ppDocModalFileLinks');
        if (!modal || !titleEl || !dl) return;

        const docKey = docRowKey(r);
        const docKeyEsc = escapeHtml(docKey);
        const isWorker = r._kind === 'worker_share';
        titleEl.textContent = isWorker ? 'Shared worker document' : 'Agency document';
        if (lead) {
            lead.textContent = isWorker
                ? staffMode
                    ? 'This file is shared with the partner portal for this worker.'
                    : 'Your office shared this worker file with your portal.'
                : staffMode
                  ? 'Agency file visible on the partner portal.'
                  : 'File uploaded for your agency by your office.';
        }

        const rows = [];
        rows.push([
            'Source',
            isWorker ? 'Worker document (shared by your office)' : 'Agency file (uploaded for you)',
        ]);
        (function () {
            const st = formatPortalStatusLabel(r.portal_status);
            if (staffMode) {
                const manual = r.display_status != null && String(r.display_status).trim() !== '';
                rows.push(['Status', manual ? `${st} (manual)` : `${st} (automatic)`]);
            } else {
                rows.push(['Status', st]);
            }
        })();
        rows.push(['Title', r.title && String(r.title).trim() !== '' ? String(r.title) : '—']);
        if (isWorker) {
            rows.push(['Worker', r._worker_name && String(r._worker_name).trim() !== '' ? r._worker_name : '—']);
            const ppn = r._passport && String(r._passport).trim() !== '' && r._passport !== '—' ? r._passport : '—';
            rows.push(['Passport', ppn]);
            rows.push(['Document slot', r._document_label && String(r._document_label).trim() !== '' ? r._document_label : '—']);
            const fnDisp =
                r._hasFile && r.original_filename && String(r.original_filename).trim() !== '' && r.original_filename !== '—'
                    ? String(r.original_filename)
                    : WORKER_SHARE_NO_FILE.fileLabel;
            rows.push(['File name', fnDisp]);
            rows.push([
                'Type (MIME)',
                r._hasFile && r.mime_type && String(r.mime_type).trim() !== '' && r.mime_type !== '—'
                    ? String(r.mime_type)
                    : `${WORKER_SHARE_NO_FILE.typeLabel} (not on server yet)`,
            ]);
            rows.push(['Size', r._hasFile ? formatBytes(r.file_size) : WORKER_SHARE_NO_FILE.sizeLabel]);
            rows.push(['File on record', r._hasFile ? 'Yes — you can open or download' : 'No — ask your office if you need the file']);
        } else {
            rows.push(['File name', r.original_filename && String(r.original_filename).trim() !== '' ? r.original_filename : '—']);
            rows.push(['Type (MIME)', r.mime_type && String(r.mime_type).trim() !== '' ? r.mime_type : '—']);
            rows.push(['Size', formatBytes(r.file_size)]);
        }
        rows.push(['Uploaded', formatDate(r.created_at)]);

        dl.innerHTML = rows
            .map(([dt, dd]) => `<div><dt>${escapeHtml(dt)}</dt><dd>${escapeHtml(dd)}</dd></div>`)
            .join('');

        if (links) {
            const canDl = !isWorker || r._hasFile;
            const cvOpenBtn =
                isWorker && r._worker_id
                    ? `<button type="button" class="neon-btn partner-portal-docs-action" data-pp-cv-worker="${String(r._worker_id)}">View CV</button>`
                    : '';
            const fullPageLink =
                staffMode && isWorker && r._worker_id
                    ? `<a class="muted-btn partner-portal-docs-action" href="${escapeHtml(workerProfileHref(r._worker_id))}">Open in Workers</a>`
                    : '';
            const editWorkerLink =
                staffMode && isWorker && r._worker_id
                    ? `<a class="muted-btn partner-portal-docs-action" href="${escapeHtml(workerEditHref(r._worker_id))}">Edit</a>`
                    : '';
            const modalDeleteBtn = staffMode
                ? `<button type="button" class="muted-btn partner-portal-docs-action pp-docs-delete-one" data-pp-doc-delete="${docKeyEsc}">Delete</button>`
                : '';
            if (canDl) {
                const href = escapeHtml(downloadHref(r));
                links.innerHTML = `<span class="partner-portal-docs-modal-links-btns">${cvOpenBtn}${fullPageLink}${editWorkerLink}${modalDeleteBtn}</span><a class="neon-btn partner-portal-docs-action" href="${href}">Open file</a><a class="muted-btn partner-portal-docs-action" href="${href}" download>Download</a>`;
            } else {
                links.innerHTML = `<span class="partner-portal-docs-modal-links-btns">${cvOpenBtn}${fullPageLink}${editWorkerLink}${modalDeleteBtn}</span>`;
            }
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function applyFilter() {
        const q = String(($('ppDocsSearch') && $('ppDocsSearch').value) || '')
            .toLowerCase()
            .trim();
        if (!q) {
            state.filtered = state.rows.slice();
            return;
        }
        state.filtered = state.rows.filter((r) => {
            const title = String(r.title || '').toLowerCase();
            const fn = String(r.original_filename || '').toLowerCase();
            const src = String(r.source || '').toLowerCase();
            const mime = String(r.mime_type || '').toLowerCase();
            const widStr =
                r._worker_id != null && String(r._worker_id).trim() !== ''
                    ? String(r._worker_id).toLowerCase()
                    : '';
            const extra = [r._worker_name, r._document_label, r._passport]
                .filter((x) => x != null && String(x).trim() !== '')
                .join(' ')
                .toLowerCase();
            const stSlug = String(r.portal_status || '').toLowerCase();
            const stLabel = formatPortalStatusLabel(r.portal_status).toLowerCase();
            const cvRef = formatCvTableId(r.__idx).toLowerCase();
            const wt = String(r.worker_type || '').toLowerCase();
            const pendingTxt =
                r._kind === 'worker_share' && !r._hasFile
                    ? `${WORKER_SHARE_NO_FILE.fileLabel} ${WORKER_SHARE_NO_FILE.typeLabel} ${WORKER_SHARE_NO_FILE.sizeLabel}`.toLowerCase()
                    : '';
            return (
                title.indexOf(q) !== -1 ||
                fn.indexOf(q) !== -1 ||
                src.indexOf(q) !== -1 ||
                mime.indexOf(q) !== -1 ||
                (widStr && widStr.indexOf(q) !== -1) ||
                extra.indexOf(q) !== -1 ||
                (stSlug && stSlug.indexOf(q) !== -1) ||
                stLabel.indexOf(q) !== -1 ||
                (wt && wt.indexOf(q) !== -1) ||
                cvRef.indexOf(q) !== -1 ||
                (pendingTxt && pendingTxt.indexOf(q) !== -1)
            );
        });
    }

    function sortFiltered() {
        const key = state.sortKey;
        const dir = state.sortDir === 'asc' ? 1 : -1;
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        state.filtered.sort((a, b) => {
            if (key === 'idx') {
                return dir * ((Number(a.__idx) || 0) - (Number(b.__idx) || 0));
            }
            let va = a[key];
            let vb = b[key];
            if (key === 'created_at') {
                va = String(va || '');
                vb = String(vb || '');
                return dir * va.localeCompare(vb);
            }
            if (key === 'file_size') {
                const na = va == null || !Number.isFinite(Number(va)) ? -1 : Number(va);
                const nb = vb == null || !Number.isFinite(Number(vb)) ? -1 : Number(vb);
                return dir * (na - nb);
            }
            va = va != null ? String(va) : '';
            vb = vb != null ? String(vb) : '';
            return dir * collator.compare(va, vb);
        });
    }

    function getPageSlice() {
        const total = state.filtered.length;
        const size = Math.max(1, state.pageSize);
        const totalPages = Math.max(1, Math.ceil(total / size));
        state.page = Math.min(Math.max(1, state.page), totalPages);
        const start = (state.page - 1) * size;
        return { total, totalPages, slice: state.filtered.slice(start, start + size) };
    }

    function updateSortIndicators() {
        document.querySelectorAll('.partner-portal-sort-btn').forEach((btn) => {
            const k = btn.getAttribute('data-sort');
            btn.classList.remove('is-sorted-asc', 'is-sorted-desc');
            if (k === state.sortKey) {
                btn.classList.add(state.sortDir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            }
        });
    }

    function renderTable() {
        applyFilter();
        updateStatusCards();
        sortFiltered();
        const body = $('ppDocsBody');
        const empty = $('ppDocsEmpty');
        const info = $('ppDocsPageInfo');
        const prev = $('ppDocsPrev');
        const next = $('ppDocsNext');
        if (!body) return;

        const { total, totalPages, slice } = getPageSlice();
        updateSortIndicators();

        if (total === 0) {
            body.innerHTML = '';
            if (empty) {
                empty.hidden = false;
                empty.textContent =
                    state.rows.length > 0
                        ? 'No documents match your search.'
                        : state.staffHighlightActive
                          ? 'No shared worker documents for the selected workers yet. Ensure files exist for allowed document types, then use Send workers here again.'
                          : 'No documents yet. Agency uploads and worker files shared by your office will appear here.';
            }
            if (info) info.textContent = state.rows.length > 0 ? '0 matches' : '0 documents';
            if (prev) prev.disabled = true;
            if (next) next.disabled = true;
            if (staffMode) {
                const sa = $('ppDocsSelectAll');
                if (sa) {
                    sa.checked = false;
                    sa.indeterminate = false;
                }
                updateBulkDeleteUi();
            }
            return;
        }
        if (empty) empty.hidden = true;

        body.innerHTML = slice
            .map((r, i) => {
                const key = docRowKey(r);
                const dkey = escapeHtml(key);
                const chk = selectedDocKeys.has(key) ? ' checked' : '';
                const selectCell = staffMode
                    ? `<td class="pp-docs-select-col"><input type="checkbox" class="pp-docs-row-check" data-pp-doc-key="${dkey}" aria-label="Select row"${chk}></td>`
                    : '';
                const refId = escapeHtml(formatCvTableId(r.__idx));
                const dl = escapeHtml(downloadHref(r));
                const isWorkerRow = r._kind === 'worker_share';
                const title = escapeHtml(r.title || '—');
                const when = escapeHtml(formatDate(r.created_at));
                const workerTypeCell = escapeHtml(String(r.worker_type != null ? r.worker_type : '—'));
                const statusSlug = selectThemeSlugForRow(r);
                const statusLabel = escapeHtml(formatPortalStatusLabel(statusSlug));
                const statusInner = staffMode
                    ? `<div class="pp-docs-status-cell"><span class="pp-doc-status pp-doc-status--${escapeHtml(
                          statusSlug
                      )}" data-pp-doc-status-chip="${dkey}" role="status">${statusLabel}</span><select class="partner-portal-input pp-docs-status-select pp-doc-status-select-solo pp-doc-status-select-solo--${escapeHtml(
                          statusSlug
                      )}" data-pp-doc-key="${dkey}" aria-label="Portal status shown to partner" title="Status shown on partner portal">${buildPortalStatusSelectOptions(
                          r.display_status
                      )}</select></div>`
                    : `<span class="pp-doc-status pp-doc-status--${escapeHtml(statusSlug)}">${statusLabel}</span>`;
                const statusTd = `<td class="col-status">${statusInner}</td>`;
                const noFile = r._kind === 'worker_share' && !r._hasFile;
                const cvBtn =
                    r._kind === 'worker_share' && r._worker_id
                        ? `<button type="button" class="neon-btn partner-portal-docs-action" data-pp-cv-worker="${String(r._worker_id)}">View CV</button>`
                        : '';
                const viewBtn = `<button type="button" class="muted-btn partner-portal-docs-action" data-pp-doc-key="${dkey}" data-pp-doc-action="view">View</button>`;
                const editBtn =
                    staffMode && r._kind === 'worker_share' && r._worker_id
                        ? `<a class="muted-btn partner-portal-docs-action" href="${escapeHtml(workerEditHref(r._worker_id))}">Edit</a>`
                        : '';
                const deleteBtn = staffMode
                    ? `<button type="button" class="muted-btn partner-portal-docs-action pp-docs-delete-one" data-pp-doc-delete="${dkey}">Delete</button>`
                    : '';
                const fileActions = noFile
                    ? ''
                    : `<a class="muted-btn partner-portal-docs-action" href="${dl}">Open</a><a class="neon-btn partner-portal-docs-action" href="${dl}" download>Download</a>`;
                const actions = `<span class="partner-portal-docs-actions-btns">${cvBtn}${viewBtn}${editBtn}${deleteBtn}${fileActions}</span>`;

                return `<tr>
                    ${selectCell}
                    <td class="col-num">${refId}</td>
                    ${statusTd}
                    <td>${title}</td>
                    <td class="col-worker-type">${workerTypeCell}</td>
                    <td>${when}</td>
                    <td class="col-actions partner-portal-docs-actions">${actions}</td>
                </tr>`;
            })
            .join('');

        if (staffMode) {
            syncSelectAllCheckbox(slice);
            updateBulkDeleteUi();
        }

        if (info) info.textContent = `Page ${state.page} / ${totalPages} · ${total} document${total === 1 ? '' : 's'}`;
        if (prev) prev.disabled = state.page <= 1;
        if (next) next.disabled = state.page >= totalPages;
    }

    function setError(msg) {
        const el = $('ppDocsError');
        if (!el) return;
        if (!msg) {
            el.hidden = true;
            el.textContent = '';
            el.classList.add('is-hidden');
            return;
        }
        el.hidden = false;
        el.textContent = msg;
        el.classList.remove('is-hidden');
    }

    function applyDocumentsData(data) {
        selectedDocKeys.clear();
        const agency = data.agency || {};
        state.agencyName = String(agency.name || '').trim() || 'Partner agency';
        const cvs = Array.isArray(data.cvs) ? data.cvs : [];
        const shares = Array.isArray(data.shared_worker_documents) ? data.shared_worker_documents : [];

        const agencyRows = cvs.map((row) =>
            Object.assign({}, row, {
                _kind: 'agency_cv',
                source: 'Agency file',
                worker_type: 'Agency',
                portal_status: normalizePortalStatusSlug(row.portal_status, 'ready'),
                display_status:
                    row.display_status != null && String(row.display_status).trim() !== ''
                        ? normalizePortalStatusSlug(row.display_status, 'ready')
                        : null,
            })
        );

        const workerRows = shares.map((s) => {
            const shareId = parseInt(String(s.id || 0), 10) || 0;
            const workerId = parseInt(String(s.worker_id || 0), 10) || 0;
            const wname = String(s.worker_name || '').trim() || 'Worker';
            const dtype = String(s.document_type || '').trim().toLowerCase();
            const dlabel = String(s.document_label || s.document_type || 'Document').trim() || 'Document';
            const ppn = String(s.passport_number || '').trim();
            const wtype =
                s.worker_type != null && String(s.worker_type).trim() !== ''
                    ? String(s.worker_type).trim()
                    : '—';
            const title = `${dlabel} — ${wname}`;
            const hasFile = !!s.has_file;
            const storageFn = s.storage_filename != null ? String(s.storage_filename).trim() : '';
            const mimeFull = s.mime_type != null ? String(s.mime_type).trim() : '';
            const fs = s.file_size;
            const fileSizeNum = fs != null && Number.isFinite(Number(fs)) ? Number(fs) : null;
            return {
                id: shareId,
                _kind: 'worker_share',
                _shareId: shareId,
                _worker_id: workerId,
                source: 'Worker document',
                title,
                original_filename: hasFile && storageFn ? storageFn : '—',
                mime_type: hasFile && mimeFull ? mimeFull : '',
                file_size: fileSizeNum,
                created_at: s.created_at != null ? s.created_at : '',
                portal_status: normalizePortalStatusSlug(s.portal_status, hasFile ? 'processing' : 'waiting'),
                display_status:
                    s.display_status != null && String(s.display_status).trim() !== ''
                        ? normalizePortalStatusSlug(s.display_status, hasFile ? 'processing' : 'waiting')
                        : null,
                _hasFile: hasFile,
                _worker_name: wname,
                _document_label: dlabel,
                _document_type: dtype,
                _passport: ppn,
                worker_type: wtype,
            };
        });

        let merged = agencyRows.concat(workerRows);
        state.staffHighlightActive = false;
        if (
            staffCfg &&
            Array.isArray(staffCfg.highlight_worker_ids) &&
            staffCfg.highlight_worker_ids.length > 0
        ) {
            const hs = new Set(staffCfg.highlight_worker_ids.map((x) => Number(x)));
            merged = merged.filter(
                (row) => row._kind !== 'worker_share' || hs.has(Number(row._worker_id))
            );
            state.staffHighlightActive = true;
        }
        merged.sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
        const nMerge = merged.length;
        merged.forEach((row, i) => {
            row.__idx = nMerge - i;
        });
        state.rows = merged;
        state.page = 1;

        const sub = $('ppDocsAgencySub');
        if (sub) {
            const name = state.agencyName;
            const idPart = agency.id != null ? ` · ID ${agency.id}` : '';
            const nAg = merged.filter((r) => r._kind === 'agency_cv').length;
            const nW = merged.filter((r) => r._kind === 'worker_share').length;
            const total = nAg + nW;
            const selNote =
                staffCfg &&
                Array.isArray(staffCfg.highlight_worker_ids) &&
                staffCfg.highlight_worker_ids.length > 0
                    ? ' · Showing workers you sent'
                    : '';
            const tail =
                total === 0 ? ' · No documents yet' : ` · ${nAg} agency · ${nW} worker`;
            sub.textContent = name + idPart + tail + selNote;
        }

        renderTable();
    }

    async function load() {
        setError('');
        const refreshBtn = $('ppDocsRefresh');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.setAttribute('aria-busy', 'true');
        }
        try {
            let res;
            if (staffMode && staffCfg) {
                const u = new URL('../api/partnerships/partner-documents-staff.php', window.location.href);
                u.searchParams.set('partner_agency_id', String(staffCfg.partner_agency_id));
                res = await fetch(u.pathname + u.search, { credentials: 'same-origin' });
            } else {
                const urlDocs = '../api/partnerships/partner-portal-documents.php';
                const urlMe = '../api/partnerships/partner-portal-me.php';
                res = await fetch(urlDocs, { credentials: 'same-origin' });
                if (res.status === 404) {
                    res = await fetch(urlMe, { credentials: 'same-origin' });
                }
            }
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                setError(json.message || `Could not load (${res.status})`);
                return;
            }
            applyDocumentsData(json.data || {});
        } catch (e) {
            setError(e && e.message ? e.message : 'Failed to load.');
        } finally {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.removeAttribute('aria-busy');
            }
        }
    }

    function bindEvents() {
        const search = $('ppDocsSearch');
        if (search) {
            search.addEventListener('input', () => {
                state.page = 1;
                renderTable();
            });
        }
        const sizeSel = $('ppDocsPageSize');
        if (sizeSel) {
            sizeSel.addEventListener('change', () => {
                state.pageSize = parseInt(String(sizeSel.value || '25'), 10) || 25;
                state.page = 1;
                renderTable();
            });
        }
        const refresh = $('ppDocsRefresh');
        if (refresh) refresh.addEventListener('click', () => load());

        document.querySelectorAll('.partner-portal-sort-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const k = btn.getAttribute('data-sort');
                if (!k) return;
                if (state.sortKey === k) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortKey = k;
                    state.sortDir =
                        k === 'created_at' || k === 'file_size' || k === 'idx' ? 'desc' : 'asc';
                }
                renderTable();
            });
        });

        const prev = $('ppDocsPrev');
        const next = $('ppDocsNext');
        if (prev) prev.addEventListener('click', () => {
            state.page = Math.max(1, state.page - 1);
            renderTable();
        });
        if (next) next.addEventListener('click', () => {
            state.page += 1;
            renderTable();
        });

        document.body.addEventListener('click', async (e) => {
            const delOne = e.target && e.target.closest ? e.target.closest('[data-pp-doc-delete]') : null;
            if (delOne && staffMode && delOne.getAttribute('data-pp-doc-delete')) {
                e.preventDefault();
                const key = delOne.getAttribute('data-pp-doc-delete');
                if (!key || !window.confirm('Remove this item from the partner portal?')) return;
                const row = findRowByKey(key);
                if (row) await handleDeleteRow(row);
                return;
            }
            const cvBtn = e.target && e.target.closest ? e.target.closest('[data-pp-cv-worker]') : null;
            if (cvBtn && cvBtn.getAttribute('data-pp-cv-worker')) {
                e.preventDefault();
                const wid = parseInt(String(cvBtn.getAttribute('data-pp-cv-worker') || ''), 10);
                if (Number.isFinite(wid) && wid > 0) {
                    openCvModal(wid);
                }
            }
        });

        const tbody = $('ppDocsBody');
        if (tbody) {
            if (staffMode) {
                tbody.addEventListener('change', async (e) => {
                    const t = e.target;
                    if (!t || !t.classList) return;
                    if (t.classList.contains('pp-docs-status-select')) {
                        const key = t.getAttribute('data-pp-doc-key');
                        const row = findRowByKey(key);
                        if (!row) return;
                        const prev =
                            row.display_status != null && String(row.display_status).trim() !== ''
                                ? normalizePortalStatusSlug(row.display_status, PORTAL_STATUS_CARD_ORDER[0])
                                : '';
                        if (String(t.value || '') === prev) return;
                        syncSoloStatusRowUi(t, row);
                        t.disabled = true;
                        const ok = await patchDocumentDisplayStatus(row, t.value);
                        t.disabled = false;
                        if (ok) {
                            setError('');
                            await load();
                        } else {
                            setError('Could not update status. Check permissions and try again.');
                            t.value = prev;
                            syncSoloStatusRowUi(t, row);
                        }
                        return;
                    }
                    if (!t.classList.contains('pp-docs-row-check')) return;
                    const key = t.getAttribute('data-pp-doc-key');
                    if (!key) return;
                    if (t.checked) {
                        selectedDocKeys.add(key);
                    } else {
                        selectedDocKeys.delete(key);
                    }
                    updateBulkDeleteUi();
                    syncSelectAllCheckbox(getPageSlice().slice);
                });
                tbody.addEventListener('input', (e) => {
                    const t = e.target;
                    if (!t || !t.classList || !t.classList.contains('pp-docs-status-select')) return;
                    const key = t.getAttribute('data-pp-doc-key');
                    const row = findRowByKey(key);
                    if (row) syncSoloStatusRowUi(t, row);
                });
            }
            tbody.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('[data-pp-doc-action="view"]') : null;
                if (!btn || btn.getAttribute('data-pp-doc-action') !== 'view') return;
                const key = btn.getAttribute('data-pp-doc-key');
                const row = findRowByKey(key);
                if (row) openDocModal(row);
            });
        }

        const selAll = $('ppDocsSelectAll');
        if (selAll && staffMode) {
            selAll.addEventListener('change', () => {
                const { slice } = getPageSlice();
                slice.forEach((r) => {
                    const k = docRowKey(r);
                    if (!k) return;
                    if (selAll.checked) {
                        selectedDocKeys.add(k);
                    } else {
                        selectedDocKeys.delete(k);
                    }
                });
                renderTable();
            });
        }

        const staffCvForm = $('ppStaffAgencyCvForm');
        if (staffCvForm && staffMode && staffCfg) {
            staffCvForm.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const titleEl = $('ppStaffAgencyCvTitle');
                const fileEl = $('ppStaffAgencyCvFile');
                const title = titleEl ? String(titleEl.value || '').trim() : '';
                const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
                if (!title || !file) return;
                const fd = new FormData();
                fd.append('partner_agency_id', String(staffCfg.partner_agency_id));
                fd.append('title', title);
                fd.append('file', file);
                const subBtn = $('ppStaffAgencyCvSubmit');
                try {
                    if (subBtn) subBtn.disabled = true;
                    const res = await fetch('../api/partnerships/partner-agency-cvs.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd,
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        setError(json.message || 'Upload failed.');
                        return;
                    }
                    setError('');
                    if (titleEl) titleEl.value = '';
                    if (fileEl) fileEl.value = '';
                    await load();
                } catch (e) {
                    setError(e && e.message ? e.message : 'Upload failed.');
                } finally {
                    if (subBtn) subBtn.disabled = false;
                }
            });
        }

        const bulkApply = $('ppDocsApplyStatusSelected');
        const bulkStatusSel = $('ppDocsBulkStatusSelect');
        if (bulkApply && bulkStatusSel && staffMode) {
            bulkApply.addEventListener('click', async () => {
                const keys = Array.from(selectedDocKeys);
                if (keys.length === 0) {
                    setError('Select at least one row first.');
                    return;
                }
                const raw = String(bulkStatusSel.value || '');
                if (raw === '') {
                    setError('Choose a status (or Auto) before applying.');
                    return;
                }
                const apiVal = raw === '__auto__' ? '' : raw;
                bulkApply.disabled = true;
                bulkStatusSel.disabled = true;
                let failed = 0;
                for (let i = 0; i < keys.length; i++) {
                    const row = findRowByKey(keys[i]);
                    if (!row || !(await patchDocumentDisplayStatus(row, apiVal))) {
                        failed++;
                    }
                }
                bulkApply.disabled = false;
                bulkStatusSel.disabled = false;
                bulkStatusSel.selectedIndex = 0;
                if (failed > 0) {
                    setError(
                        `${failed} of ${keys.length} row(s) could not be updated. Check permissions and try again.`
                    );
                } else {
                    setError('');
                }
                await load();
                updateBulkDeleteUi();
            });
        }

        const bulkDel = $('ppDocsDeleteSelected');
        if (bulkDel && staffMode) {
            bulkDel.addEventListener('click', async () => {
                const keys = Array.from(selectedDocKeys);
                if (keys.length === 0) return;
                if (!window.confirm(`Remove ${keys.length} item(s) from this partner portal? They will disappear for the partner.`)) {
                    return;
                }
                let failed = 0;
                for (let i = 0; i < keys.length; i++) {
                    const row = findRowByKey(keys[i]);
                    if (!row || !(await deleteDocumentRow(row))) {
                        failed++;
                    } else {
                        selectedDocKeys.delete(keys[i]);
                    }
                }
                if (failed > 0) {
                    setError(`${failed} item(s) could not be removed. Check permissions.`);
                } else {
                    setError('');
                }
                await load();
                updateBulkDeleteUi();
            });
        }

        const docModal = $('ppDocModal');
        const closeDoc = () => closeDocModal();
        const bx = $('ppDocModalCloseX');
        const bc = $('ppDocModalCloseBtn');
        if (bx) bx.addEventListener('click', closeDoc);
        if (bc) bc.addEventListener('click', closeDoc);
        if (docModal) {
            docModal.addEventListener('click', (e) => {
                if (e.target === docModal) closeDoc();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const cvModal = $('ppCvModal');
            if (cvModal && cvModal.classList.contains('open')) {
                e.preventDefault();
                closeCvModal();
                return;
            }
            if (docModal && docModal.classList.contains('open')) {
                e.preventDefault();
                closeDoc();
            }
        });
    }

    function init() {
        refreshStaffMode();
        initBulkStatusSelect();
        bindEvents();
        updateBulkDeleteUi();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
