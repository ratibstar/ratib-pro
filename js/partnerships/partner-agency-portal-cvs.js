/**
 * Staff page: show partner portal CVs + shared worker docs only (read-only, partner-style).
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

    function downloadHref(cvId) {
        return `../api/partnerships/partner-agency-cv-download.php?id=${encodeURIComponent(String(cvId))}`;
    }

    function workerShareDownloadHref(shareId) {
        return `../api/partnerships/partner-shared-worker-doc-download.php?share_id=${encodeURIComponent(String(shareId))}`;
    }

    function renderCvList(cvs) {
        const cvList = document.getElementById('pacvCvList');
        const cvEmpty = document.getElementById('pacvCvEmpty');
        if (!cvs || cvs.length === 0) {
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
                    const href = escapeHtml(withContext(downloadHref(id)));

                    return `<li class="partner-portal-cv-item">
                        <div>
                            <strong>${escapeHtml(title)}</strong>
                            <div class="partner-portal-cv-meta">${escapeHtml(fn)} · ${escapeHtml(formatCalendarDate(c.created_at))}</div>
                        </div>
                        <a class="neon-btn partner-portal-dl-btn" href="${href}" target="_blank" rel="noopener">Download</a>
                    </li>`;
                })
                .join('');
        }
    }

    function renderSharedWorkerDocs(rows) {
        const list = document.getElementById('pacvWorkerShareList');
        const empty = document.getElementById('pacvWorkerShareEmpty');
        if (!list) return;
        if (!rows || !rows.length) {
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
                const dl = withContext(workerShareDownloadHref(sid));
                const dlBtn = hasFile
                    ? `<a class="neon-btn partner-portal-dl-btn" href="${escapeHtml(dl)}" target="_blank" rel="noopener">Download</a>`
                    : `<span class="partner-portal-no-file muted-label">No file uploaded yet</span>`;

                return `<li class="partner-portal-worker-share-item">
                    <div>
                        <strong>${escapeHtml(name)}</strong>
                        <div class="partner-portal-cv-meta">${escapeHtml(docLab)} · Passport: ${escapeHtml(passport)} · Added ${escapeHtml(
                    formatCalendarDate(r.created_at)
                )}</div>
                    </div>
                    ${dlBtn}
                </li>`;
            })
            .join('');
    }

    function showError(msg) {
        const errEl = document.getElementById('pacvError');
        if (errEl) {
            errEl.textContent = msg;
            errEl.hidden = false;
            errEl.classList.remove('is-hidden');
        }
    }

    function clearError() {
        const errEl = document.getElementById('pacvError');
        if (errEl) {
            errEl.textContent = '';
            errEl.hidden = true;
            errEl.classList.add('is-hidden');
        }
    }

    function setNavLinks(agencyId) {
        const id = String(agencyId);
        const manage = document.getElementById('pacvManageLink');
        const detail = document.getElementById('pacvDetailLink');
        const bcLink = document.getElementById('pacvBreadcrumbAgencyLink');
        if (manage) {
            manage.href = withContext(`partner-agency-detail.php?id=${encodeURIComponent(id)}&tab=attachments`);
        }
        if (detail) {
            detail.href = withContext(`partner-agency-detail.php?id=${encodeURIComponent(id)}`);
        }
        if (bcLink) {
            bcLink.href = withContext(`partner-agency-detail.php?id=${encodeURIComponent(id)}`);
        }
    }

    async function load() {
        const params = new URLSearchParams(window.location.search || '');
        const id = parseInt(String(params.get('id') || '0'), 10);
        if (!Number.isFinite(id) || id <= 0) {
            showError('Missing or invalid agency id. Open this page from Partner Agencies → CVs.');
            return;
        }

        setNavLinks(id);
        const bcName = document.getElementById('pacvBreadcrumbAgencyName');
        const titleEl = document.getElementById('pacvPageTitle');

        try {
            clearError();
            const [agRes, cvRes, shRes] = await Promise.all([
                fetch(withContext(`../api/partnerships/partner-agencies.php?id=${encodeURIComponent(String(id))}`), {
                    credentials: 'same-origin',
                }),
                fetch(
                    withContext(`../api/partnerships/partner-agency-cvs.php?partner_agency_id=${encodeURIComponent(String(id))}`),
                    { credentials: 'same-origin' }
                ),
                fetch(
                    withContext(`../api/partnerships/partner-agency-worker-shares.php?partner_agency_id=${encodeURIComponent(String(id))}`),
                    { credentials: 'same-origin' }
                ),
            ]);

            const agJson = await agRes.json().catch(() => ({}));
            if (!agRes.ok || !agJson.success) {
                showError(agJson.message || `Could not load agency (${agRes.status}).`);
                return;
            }
            const agency = agJson.data || {};
            const aname = displayValue(agency.name);
            if (bcName) bcName.textContent = aname;
            if (titleEl) titleEl.textContent = `Documents & CVs — ${aname}`;

            const errs = [];
            const cvJson = await cvRes.json().catch(() => ({}));
            if (!cvRes.ok || !cvJson.success) {
                renderCvList([]);
                errs.push(cvJson.message || `CV list (${cvRes.status})`);
            } else {
                const rows = Array.isArray(cvJson.data) ? cvJson.data : [];
                renderCvList(rows);
            }

            const shJson = await shRes.json().catch(() => ({}));
            if (!shRes.ok || !shJson.success) {
                renderSharedWorkerDocs([]);
                errs.push(shJson.message || `Worker shares (${shRes.status})`);
            } else {
                const d = shJson.data || {};
                const shares = Array.isArray(d.shares) ? d.shares : [];
                renderSharedWorkerDocs(shares);
            }

            if (errs.length) {
                showError(`Could not load: ${errs.join(' · ')}`);
            }
        } catch (e) {
            showError(e && e.message ? e.message : 'Failed to load.');
        }
    }

    function init() {
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
