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

    function findRowByKey(key) {
        if (!key) return null;
        const pipe = String(key).indexOf('|');
        if (pipe === -1) return null;
        const kind = String(key).slice(0, pipe);
        const id = String(key).slice(pipe + 1);
        return state.rows.find((row) => row._kind === kind && String(row.id) === id) || null;
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
        rows.push(['Title', r.title && String(r.title).trim() !== '' ? String(r.title) : '—']);
        if (isWorker) {
            rows.push(['Worker', r._worker_name && String(r._worker_name).trim() !== '' ? r._worker_name : '—']);
            const ppn = r._passport && String(r._passport).trim() !== '' && r._passport !== '—' ? r._passport : '—';
            rows.push(['Passport', ppn]);
            rows.push(['Document slot', r._document_label && String(r._document_label).trim() !== '' ? r._document_label : '—']);
            const fnDisp =
                r._hasFile && r.original_filename && String(r.original_filename).trim() !== '' && r.original_filename !== '—'
                    ? String(r.original_filename)
                    : '—';
            rows.push(['File name', fnDisp]);
            rows.push([
                'Type (MIME)',
                r._hasFile && r.mime_type && String(r.mime_type).trim() !== '' && r.mime_type !== '—'
                    ? String(r.mime_type)
                    : '—',
            ]);
            rows.push(['Size', formatBytes(r.file_size)]);
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
                    ? `<a class="muted-btn partner-portal-docs-action" href="${escapeHtml(workerProfileHref(r._worker_id))}" target="_blank" rel="noopener">Open in Workers</a>`
                    : '';
            if (canDl) {
                const href = escapeHtml(downloadHref(r));
                links.innerHTML = `<span class="partner-portal-docs-modal-links-btns">${cvOpenBtn}${fullPageLink}</span><a class="neon-btn partner-portal-docs-action" href="${href}" target="_blank" rel="noopener">Open file</a><a class="muted-btn partner-portal-docs-action" href="${href}" download>Download</a>`;
            } else {
                links.innerHTML = `<span class="partner-portal-docs-modal-links-btns">${cvOpenBtn}${fullPageLink}</span>`;
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
            return (
                title.indexOf(q) !== -1 ||
                fn.indexOf(q) !== -1 ||
                src.indexOf(q) !== -1 ||
                mime.indexOf(q) !== -1 ||
                (widStr && widStr.indexOf(q) !== -1) ||
                extra.indexOf(q) !== -1
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
            return;
        }
        if (empty) empty.hidden = true;

        body.innerHTML = slice
            .map((r, i) => {
                const globalIdx = (state.page - 1) * state.pageSize + i + 1;
                const dl = escapeHtml(downloadHref(r));
                const isWorkerRow = r._kind === 'worker_share';
                const mimeRaw = String(r.mime_type || '').trim();
                const mimeShort = escapeHtml(formatMimeTypeDisplay(mimeRaw));
                const mimeTitle = escapeHtml(mimeRaw);
                const title = escapeHtml(r.title || '—');
                let fnCell = '—';
                if (isWorkerRow) {
                    if (r._hasFile && r.original_filename && String(r.original_filename).trim() !== '') {
                        fnCell = escapeHtml(String(r.original_filename).trim());
                    }
                } else if (r.original_filename && String(r.original_filename).trim() !== '') {
                    fnCell = escapeHtml(String(r.original_filename).trim());
                } else {
                    fnCell = '—';
                }
                const sz = formatBytes(r.file_size);
                const when = escapeHtml(formatDate(r.created_at));
                const srcTag =
                    r._kind === 'worker_share'
                        ? `<span class="table-tag tag-muted" title="Shared by your office">Worker</span>`
                        : `<span class="table-tag tag-muted" title="Agency upload">Agency</span>`;
                const noFile = r._kind === 'worker_share' && !r._hasFile;
                const dkey = escapeHtml(docRowKey(r));
                const cvBtn =
                    r._kind === 'worker_share' && r._worker_id
                        ? `<button type="button" class="neon-btn partner-portal-docs-action" data-pp-cv-worker="${String(r._worker_id)}">View CV</button>`
                        : '';
                const viewBtn = `<button type="button" class="muted-btn partner-portal-docs-action" data-pp-doc-key="${dkey}" data-pp-doc-action="view">View</button>`;
                const fileActions = noFile
                    ? `<span class="partner-portal-docs-no-file" title="No file is stored for this document on our side yet. Ask your office if you need the file.">No file</span>`
                    : `<a class="muted-btn partner-portal-docs-action" href="${dl}" target="_blank" rel="noopener">Open</a><a class="neon-btn partner-portal-docs-action" href="${dl}" download>Download</a>`;
                const btnRow = `<span class="partner-portal-docs-actions-btns">${cvBtn}${viewBtn}${noFile ? '' : fileActions}</span>`;
                const actions = noFile ? `${btnRow}${fileActions}` : btnRow;

                return `<tr>
                    <td class="col-num">${globalIdx}</td>
                    <td class="col-source">${srcTag}</td>
                    <td>${title}</td>
                    <td>${fnCell}</td>
                    <td><span class="table-tag tag-muted" title="${mimeTitle}">${mimeShort}</span></td>
                    <td>${escapeHtml(sz)}</td>
                    <td>${when}</td>
                    <td class="col-actions partner-portal-docs-actions">${actions}</td>
                </tr>`;
            })
            .join('');

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
        const agency = data.agency || {};
        state.agencyName = String(agency.name || '').trim() || 'Partner agency';
        const cvs = Array.isArray(data.cvs) ? data.cvs : [];
        const shares = Array.isArray(data.shared_worker_documents) ? data.shared_worker_documents : [];

        const agencyRows = cvs.map((row) =>
            Object.assign({}, row, {
                _kind: 'agency_cv',
                source: 'Agency file',
            })
        );

        const workerRows = shares.map((s) => {
            const shareId = parseInt(String(s.id || 0), 10) || 0;
            const workerId = parseInt(String(s.worker_id || 0), 10) || 0;
            const wname = String(s.worker_name || '').trim() || 'Worker';
            const dlabel = String(s.document_label || s.document_type || 'Document').trim() || 'Document';
            const ppn = String(s.passport_number || '').trim();
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
                _hasFile: hasFile,
                _worker_name: wname,
                _document_label: dlabel,
                _passport: ppn,
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
        merged.forEach((row, i) => {
            row.__idx = i + 1;
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
                    state.sortDir = k === 'created_at' || k === 'file_size' || k === 'idx' ? 'desc' : 'asc';
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

        document.body.addEventListener('click', (e) => {
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
            tbody.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('[data-pp-doc-action="view"]') : null;
                if (!btn || btn.getAttribute('data-pp-doc-action') !== 'view') return;
                const key = btn.getAttribute('data-pp-doc-key');
                const row = findRowByKey(key);
                if (row) openDocModal(row);
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
        bindEvents();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
