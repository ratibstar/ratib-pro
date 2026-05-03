/**
 * Partner portal — Documents & CVs full table (search, sort, paginate, download / open).
 */
(function () {
    const DATE_LOCALE = 'en-US';

    const state = {
        rows: [],
        filtered: [],
        sortKey: 'created_at',
        sortDir: 'desc',
        page: 1,
        pageSize: 25,
        agencyName: '',
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
        const x = Number(n);
        if (!Number.isFinite(x) || x < 0) return '—';
        if (x < 1024) return `${x} B`;
        if (x < 1024 * 1024) return `${(x / 1024).toFixed(1)} KB`;
        return `${(x / (1024 * 1024)).toFixed(2)} MB`;
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
                ? 'Your office shared this worker file with your portal.'
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
            rows.push(['Document', r._document_label && String(r._document_label).trim() !== '' ? r._document_label : '—']);
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
            if (canDl) {
                const href = escapeHtml(downloadHref(r));
                links.innerHTML = `<a class="neon-btn partner-portal-docs-action" href="${href}" target="_blank" rel="noopener">Open</a><a class="muted-btn partner-portal-docs-action" href="${href}" download>Download</a>`;
            } else {
                links.innerHTML = '';
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
            const extra = [r._worker_name, r._document_label, r._passport]
                .filter((x) => x != null && String(x).trim() !== '')
                .join(' ')
                .toLowerCase();
            return (
                title.indexOf(q) !== -1 ||
                fn.indexOf(q) !== -1 ||
                src.indexOf(q) !== -1 ||
                mime.indexOf(q) !== -1 ||
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
                return dir * ((Number(va) || 0) - (Number(vb) || 0));
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
                const mime = escapeHtml(r.mime_type || '—');
                const title = escapeHtml(r.title || '—');
                const fn = escapeHtml(r.original_filename || '—');
                const sz = formatBytes(r.file_size);
                const when = escapeHtml(formatDate(r.created_at));
                const srcTag =
                    r._kind === 'worker_share'
                        ? `<span class="table-tag tag-muted" title="Shared by your office">Worker</span>`
                        : `<span class="table-tag tag-muted" title="Agency upload">Agency</span>`;
                const noFile = r._kind === 'worker_share' && !r._hasFile;
                const dkey = escapeHtml(docRowKey(r));
                const viewBtn = `<button type="button" class="muted-btn partner-portal-docs-action" data-pp-doc-key="${dkey}" data-pp-doc-action="view">View</button>`;
                const fileActions = noFile
                    ? `<span class="partner-portal-docs-no-file" title="No file is stored for this document on our side yet. Ask your office if you need the file.">No file</span>`
                    : `<a class="muted-btn partner-portal-docs-action" href="${dl}" target="_blank" rel="noopener">Open</a><a class="neon-btn partner-portal-docs-action" href="${dl}" download>Download</a>`;
                const actions = `${viewBtn}${fileActions}`;

                return `<tr>
                    <td class="col-num">${globalIdx}</td>
                    <td class="col-source">${srcTag}</td>
                    <td>${title}</td>
                    <td>${fn}</td>
                    <td><span class="table-tag tag-muted">${mime}</span></td>
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

    async function load() {
        setError('');
        const refreshBtn = $('ppDocsRefresh');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.setAttribute('aria-busy', 'true');
        }
        try {
            const res = await fetch('../api/partnerships/partner-portal-documents.php', { credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                setError(json.message || `Could not load (${res.status})`);
                return;
            }
            const data = json.data || {};
            const agency = data.agency || {};
            state.agencyName = String(agency.name || '').trim() || 'Partner agency';
            const cvs = Array.isArray(data.cvs) ? data.cvs : [];
            const shares = Array.isArray(data.shared_worker_documents) ? data.shared_worker_documents : [];
            const nAg = cvs.length;
            const nW = shares.length;
            const sub = $('ppDocsAgencySub');
            if (sub) {
                const name = state.agencyName;
                const idPart = agency.id != null ? ` · ID ${agency.id}` : '';
                const total = nAg + nW;
                const tail =
                    total === 0
                        ? ' · No documents yet'
                        : ` · ${nAg} agency · ${nW} worker`;
                sub.textContent = name + idPart + tail;
            }

            const agencyRows = cvs.map((row) =>
                Object.assign({}, row, {
                    _kind: 'agency_cv',
                    source: 'Agency file',
                })
            );

            const workerRows = shares.map((s) => {
                const shareId = parseInt(String(s.id || 0), 10) || 0;
                const wname = String(s.worker_name || '').trim() || 'Worker';
                const dlabel = String(s.document_label || s.document_type || 'Document').trim() || 'Document';
                const ppn = String(s.passport_number || '').trim();
                const title = `${dlabel} — ${wname}`;
                const fnHint = ppn && ppn !== '—' ? `Passport ${ppn}` : String(s.document_type || 'document');
                const hasFile = !!s.has_file;
                return {
                    id: shareId,
                    _kind: 'worker_share',
                    _shareId: shareId,
                    source: 'Worker document',
                    title,
                    original_filename: fnHint,
                    mime_type: dlabel,
                    file_size: null,
                    created_at: s.created_at != null ? s.created_at : '',
                    _hasFile: hasFile,
                    _worker_name: wname,
                    _document_label: dlabel,
                    _passport: ppn,
                };
            });

            const merged = agencyRows.concat(workerRows);
            merged.sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
            merged.forEach((row, i) => {
                row.__idx = i + 1;
            });
            state.rows = merged;
            state.page = 1;
            renderTable();
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
            if (docModal && docModal.classList.contains('open')) {
                e.preventDefault();
                closeDoc();
            }
        });
    }

    function init() {
        bindEvents();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
