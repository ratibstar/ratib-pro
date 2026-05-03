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

    function downloadHref(cvId) {
        return `../api/partnerships/partner-agency-cv-download.php?id=${encodeURIComponent(String(cvId))}`;
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
            return title.indexOf(q) !== -1 || fn.indexOf(q) !== -1;
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
                    state.rows.length > 0 ? 'No documents match your search.' : 'No documents uploaded yet.';
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
                const id = r.id;
                const dl = escapeHtml(downloadHref(id));
                const mime = escapeHtml(r.mime_type || '—');
                const title = escapeHtml(r.title || '—');
                const fn = escapeHtml(r.original_filename || '—');
                const sz = formatBytes(r.file_size);
                const when = escapeHtml(formatDate(r.created_at));
                const openSameBrowser = `<a class="muted-btn partner-portal-docs-action" href="${dl}" target="_blank" rel="noopener">Open</a>`;
                const downloadBtn = `<a class="neon-btn partner-portal-docs-action" href="${dl}" download>Download</a>`;

                return `<tr>
                    <td class="col-num">${globalIdx}</td>
                    <td>${title}</td>
                    <td>${fn}</td>
                    <td><span class="table-tag tag-muted">${mime}</span></td>
                    <td>${escapeHtml(sz)}</td>
                    <td>${when}</td>
                    <td class="col-actions partner-portal-docs-actions">${openSameBrowser}${downloadBtn}</td>
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
        try {
            const res = await fetch('../api/partnerships/partner-portal-me.php', { credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                setError(json.message || `Could not load (${res.status})`);
                return;
            }
            const data = json.data || {};
            const agency = data.agency || {};
            state.agencyName = String(agency.name || '').trim() || 'Partner agency';
            const sub = $('ppDocsAgencySub');
            if (sub) {
                sub.textContent = state.agencyName + (agency.id != null ? ` · ID ${agency.id}` : '');
            }

            const cvs = Array.isArray(data.cvs) ? data.cvs : [];
            state.rows = cvs.map((r, i) => Object.assign({}, r, { __idx: i + 1 }));
            state.page = 1;
            renderTable();
        } catch (e) {
            setError(e && e.message ? e.message : 'Failed to load.');
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
