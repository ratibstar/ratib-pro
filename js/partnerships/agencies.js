/**
 * EN: Implements frontend interaction behavior in `js/partnerships/agencies.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/partnerships/agencies.js`.
 */
(function () {
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `partnership-toast ${type || 'success'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 220);
        }, 2400);
    }

    function init() {
    const apiBase = '../api/partnerships/partner-agencies.php';
    const deploymentsApi = '../api/partnerships/deployments.php';
    const body = document.getElementById('agenciesTableBody');
    const modal = document.getElementById('agencyModal');
    const form = document.getElementById('agencyForm');
    const workersModal = document.getElementById('workersModal');
    const workersModalBody = document.getElementById('workersModalBody');
    const workersModalTitle = document.getElementById('workersModalTitle');

    if (!body || !form) return;

    const $ = (id) => document.getElementById(id);
    const pageQuery = new URLSearchParams(window.location.search || '');
    const control = pageQuery.get('control');
    const pageAgencyId = pageQuery.get('agency_id');
    // Workers modal uses ?partner_agency_id= (partner row), not ?agency_id= (control SSO office id).
    const withContext = (url) => {
        const qs = new URLSearchParams();
        if (control) qs.set('control', control);
        if (pageAgencyId) qs.set('agency_id', pageAgencyId);
        if (!qs.toString()) return url;
        return `${url}${url.includes('?') ? '&' : '?'}${qs.toString()}`;
    };
    const api = withContext(apiBase);

    const ensureControlsUi = () => {
        const toolbar = document.querySelector('.partnerships-toolbar .toolbar-actions');
        if (!toolbar) return;
        if ($('agencySearch')) return;
        toolbar.innerHTML = `
            <input id="agencySearch" type="text" placeholder="Search agency/contact/email">
            <input id="agencyCountryFilter" type="text" placeholder="Filter country">
            <select id="agencyStatusFilter">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select id="agencySort">
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
                <option value="workers_desc">Workers Sent High-Low</option>
                <option value="workers_asc">Workers Sent Low-High</option>
            </select>
            <select id="agencyPageSize">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <span id="bulkAgencySelectionLabel" class="bulk-agency-selection-label">0 selected</span>
            <button type="button" id="bulkAgencyActivate" class="bulk-agency-btn bulk-agency-btn--act" disabled title="Set selected to Active">Act</button>
            <button type="button" id="bulkAgencyDeactivate" class="bulk-agency-btn bulk-agency-btn--inact" disabled title="Set selected to Inactive">Inact</button>
            <button type="button" id="bulkAgencyClear" class="bulk-agency-btn bulk-agency-btn--clear" disabled title="Clear selection">Clear</button>
            <button id="addAgencyBtn" class="neon-btn">Add Agency</button>
        `;
    };

    const ensurePaginationUi = () => {
        if ($('agencyPrevPage') && $('agencyNextPage') && $('agencyPageInfo')) return;
        const tableShell = document.querySelector('.table-shell');
        if (!tableShell) return;
        const existing = tableShell.querySelector('.table-pagination');
        if (existing) return;
        const pagination = document.createElement('div');
        pagination.className = 'table-pagination';
        pagination.innerHTML = `
            <button id="agencyPrevPage" class="muted-btn" type="button">Prev</button>
            <span id="agencyPageInfo">Page 1</span>
            <button id="agencyNextPage" class="muted-btn" type="button">Next</button>
        `;
        tableShell.appendChild(pagination);
    };

    ensureControlsUi();
    ensurePaginationUi();

    let allRows = [];
    let filteredRows = [];
    let currentPage = 1;
    let pageSize = 10;
    const agencyTableColspan = 10;
    const selectedAgencyIds = new Set();

    const openModal = (agency = null) => {
        $('agencyModalTitle').textContent = agency ? 'Edit Agency' : 'Add Agency';
        $('agencyId').value = agency?.id || '';
        $('agencyName').value = agency?.name || '';
        $('agencyCountry').value = agency?.country || '';
        $('agencyCity').value = agency?.city || '';
        $('agencyContact').value = agency?.contact_person || '';
        $('agencyEmail').value = agency?.email || '';
        $('agencyPhone').value = agency?.phone || '';
        $('agencyStatus').value = agency?.status || 'active';
        modal.classList.add('open');
    };

    const closeModal = () => modal.classList.remove('open');

    const statusPill = (status) => `<span class="status-pill status-${status}">${status}</span>`;

    const syncBulkToolbar = () => {
        const n = selectedAgencyIds.size;
        const lab = $('bulkAgencySelectionLabel');
        if (lab) lab.textContent = `${n} selected`;
        ['bulkAgencyActivate', 'bulkAgencyDeactivate', 'bulkAgencyClear'].forEach((bid) => {
            const b = $(bid);
            if (b) b.disabled = n === 0;
        });
    };

    const syncSelectAllCheckbox = (pageRows) => {
        const sel = $('agencySelectAll');
        if (!sel) return;
        const ids = (pageRows || []).map((r) => String(r.id));
        if (ids.length === 0) {
            sel.checked = false;
            sel.indeterminate = false;
            sel.disabled = true;
            return;
        }
        sel.disabled = false;
        const onPage = ids.filter((id) => selectedAgencyIds.has(id)).length;
        if (onPage === 0) {
            sel.checked = false;
            sel.indeterminate = false;
        } else if (onPage === ids.length) {
            sel.checked = true;
            sel.indeterminate = false;
        } else {
            sel.checked = false;
            sel.indeterminate = true;
        }
    };

    const render = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = `<tr><td colspan="${agencyTableColspan}">No partner agencies found.</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => `
            <tr>
                <td>${r.name || ''}</td>
                <td>${r.country || ''}</td>
                <td>${r.city || ''}</td>
                <td>${r.contact_person || ''}</td>
                <td>${r.email || ''}</td>
                <td>${r.phone || ''}</td>
                <td>
                    <div class="workers-cell">
                        <div class="workers-preview" title="${(r.workers_sent_details || '').replace(/"/g, '&quot;')}">${r.workers_sent_details || '-'}</div>
                        <button type="button" class="workers-sent-chip workers-link-btn" data-action="view-workers" data-partner-agency-id="${String(r.id ?? '').replace(/"/g, '&quot;')}" data-name="${(r.name || '').replace(/"/g, '&quot;')}">${Number(r.workers_sent || 0)} View</button>
                    </div>
                </td>
                <td>${statusPill(r.status || 'inactive')}</td>
                <td class="col-select">
                    <input type="checkbox" class="agency-row-check" data-agency-id="${String(r.id ?? '').replace(/"/g, '&quot;')}" aria-label="Select row" ${selectedAgencyIds.has(String(r.id)) ? 'checked' : ''}>
                </td>
                <td>
                    <a class="muted-btn agency-details-link" href="${withContext(`partner-agency-detail.php?id=${encodeURIComponent(String(r.id))}`)}">Details</a>
                    <button class="muted-btn" data-action="edit" data-id="${r.id}">Edit</button>
                    <button class="muted-btn" data-action="delete" data-id="${r.id}">Delete</button>
                </td>
            </tr>
        `).join('');
    };

    const applyControls = () => {
        const searchInput = $('agencySearch');
        const countryFilter = $('agencyCountryFilter');
        const statusFilter = $('agencyStatusFilter');
        const sortSelect = $('agencySort');
        const pageSizeSelect = $('agencyPageSize');
        const prevBtn = $('agencyPrevPage');
        const nextBtn = $('agencyNextPage');
        const pageInfo = $('agencyPageInfo');

        const search = (searchInput?.value || '').toLowerCase().trim();
        const country = (countryFilter?.value || '').toLowerCase().trim();
        const status = (statusFilter?.value || '').trim();
        const sort = (sortSelect?.value || 'name_asc');
        pageSize = Number(pageSizeSelect?.value || 10);

        filteredRows = allRows.filter((r) => {
            const hay = `${r.name || ''} ${r.contact_person || ''} ${r.email || ''}`.toLowerCase();
            if (search && !hay.includes(search)) return false;
            if (country && !(String(r.country || '').toLowerCase().includes(country))) return false;
            if (status && r.status !== status) return false;
            return true;
        });

        filteredRows.sort((a, b) => {
            if (sort === 'name_asc') return String(a.name || '').localeCompare(String(b.name || ''));
            if (sort === 'name_desc') return String(b.name || '').localeCompare(String(a.name || ''));
            if (sort === 'workers_asc') return Number(a.workers_sent || 0) - Number(b.workers_sent || 0);
            if (sort === 'workers_desc') return Number(b.workers_sent || 0) - Number(a.workers_sent || 0);
            return 0;
        });

        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);
        render(pageRows);
        syncSelectAllCheckbox(pageRows);
        syncBulkToolbar();

        if (pageInfo) {
            pageInfo.textContent = `Page ${currentPage} / ${totalPages}`;
        }
        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
        }
    };

    const workersSentColspan = 9;
    const deploymentStatusOptions = ['processing', 'deployed', 'returned', 'issue', 'transferred'];

    const deploymentStatusSelectClass = (status) => {
        const s = deploymentStatusOptions.includes(String(status)) ? String(status) : 'processing';
        return `status-select workers-sent-status-select deployment-status deployment-status--${s}`;
    };

    const buildWorkerProfileHref = (workerId) => {
        const qs = new URLSearchParams(window.location.search);
        // Worker.php + worker-form.js open the full worker form in read-only mode via ?view=
        qs.delete('id');
        qs.delete('edit');
        qs.set('view', String(workerId));
        // Same-tab Profile: after closing the worker form, return here and reopen Workers Sent
        if (workersSentPartnerId > 0) {
            qs.set('return_partner_agency', String(workersSentPartnerId));
        }
        return `Worker.php?${qs.toString()}`;
    };

    const maybeOpenWorkersSentFromReturn = () => {
        const u = new URL(window.location.href);
        if (!u.searchParams.has('open_sent_workers')) return;
        const oid = u.searchParams.get('open_sent_workers');
        u.searchParams.delete('open_sent_workers');
        const newSearch = u.searchParams.toString();
        window.history.replaceState({}, '', u.pathname + (newSearch ? `?${newSearch}` : '') + u.hash);
        const pid = parseInt(String(oid), 10);
        if (!Number.isFinite(pid) || pid <= 0) return;
        const row = allRows.find((r) => String(r.id) === String(pid));
        openWorkersModal(pid, row?.name || 'Agency');
    };

    /** After Worker.php close via history.back() (bfcache-friendly) */
    const tryReopenWorkersSentFromSession = () => {
        try {
            const raw = sessionStorage.getItem('ratib_reopen_workers_sent');
            if (!raw) return;
            const o = JSON.parse(raw);
            if (!o || typeof o.partnerId === 'undefined') return;
            if (o.t && Date.now() - Number(o.t) > 120000) {
                sessionStorage.removeItem('ratib_reopen_workers_sent');
                return;
            }
            sessionStorage.removeItem('ratib_reopen_workers_sent');
            const pid = parseInt(String(o.partnerId), 10);
            if (!Number.isFinite(pid) || pid <= 0) return;
            const row = allRows.find((r) => String(r.id) === String(pid));
            openWorkersModal(pid, row?.name || o.name || 'Agency');
        } catch (e) {
            /* ignore */
        }
    };

    /** Parse YYYY-MM-DD as a local calendar date (avoids UTC shift from Date.parse). */
    const parseCalendarDate = (s) => {
        if (s == null || s === '' || s === '-') return null;
        const str = String(s).trim();
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            const y = parseInt(m[1], 10);
            const mo = parseInt(m[2], 10) - 1;
            const d = parseInt(m[3], 10);
            if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null;
            return new Date(y, mo, d);
        }
        const t = Date.parse(str);
        return Number.isFinite(t) ? new Date(t) : null;
    };

    const startOfLocalDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

    const contractPlacementHtml = (contractStart, contractEnd) => {
        const start = parseCalendarDate(contractStart);
        const end = parseCalendarDate(contractEnd);
        const nowDay = startOfLocalDay(new Date());
        const parts = [];
        if (start) {
            const sDay = startOfLocalDay(start);
            const abroad = Math.floor((nowDay - sDay) / 86400000);
            if (abroad >= 0) {
                parts.push(`<span class="placement-line">${abroad}d on assignment</span>`);
            } else {
                parts.push(`<span class="placement-line placement-line--muted">Starts in ${Math.abs(abroad)}d</span>`);
            }
        }
        if (!end) {
            parts.push('<span class="contract-health contract-health--muted">No contract end set</span>');
        } else {
            const eDay = startOfLocalDay(end);
            const daysLeft = Math.round((eDay - nowDay) / 86400000);
            if (daysLeft < 0) {
                parts.push(`<span class="contract-health contract-health--ended">Ended ${Math.abs(daysLeft)}d ago</span>`);
            } else if (daysLeft <= 30) {
                parts.push(`<span class="contract-health contract-health--urgent">${daysLeft}d left on contract</span>`);
            } else if (daysLeft <= 90) {
                parts.push(`<span class="contract-health contract-health--warn">${daysLeft}d left on contract</span>`);
            } else {
                parts.push(`<span class="contract-health contract-health--ok">${daysLeft}d left on contract</span>`);
            }
        }
        if (!parts.length) return '';
        return `<div class="contract-cell-stack">${parts.join('')}</div>`;
    };

    const csvEscape = (val) => {
        const t = String(val ?? '');
        if (/[",\r\n]/.test(t)) {
            return `"${t.replace(/"/g, '""')}"`;
        }
        return t;
    };

    let workersSentAll = [];
    let workersSentFiltered = [];
    let workersSentPage = 1;
    let workersSentSize = 10;
    let workersSentPartnerId = 0;

    const resetWorkersSentFilters = () => {
        const s = $('workersSentSearch');
        if (s) s.value = '';
        const f = $('workersSentStatusFilter');
        if (f) f.value = '';
        const so = $('workersSentSort');
        if (so) so.value = 'name_asc';
        const ps = $('workersSentPageSize');
        if (ps) ps.value = '10';
        workersSentPage = 1;
        workersSentSize = 10;
    };

    const contractEndTs = (w) => {
        const d = parseCalendarDate(w.contract_end);
        return d ? d.getTime() : 0;
    };

    const patchSentWorkerStatusEverywhere = (deploymentId, status) => {
        const depId = String(deploymentId);
        const apply = (arr) => {
            if (!Array.isArray(arr)) return;
            arr.forEach((w) => {
                if (String(w.deployment_id) === depId) {
                    w.status = status;
                }
            });
        };
        apply(workersSentAll);
        apply(workersSentFiltered);
        const ag = allRows.find((r) => String(r.id) === String(workersSentPartnerId));
        if (ag && Array.isArray(ag.sent_workers)) {
            apply(ag.sent_workers);
        }
    };

    const syncParentAgencyAfterDeploymentDelete = (deploymentId) => {
        const ag = allRows.find((r) => String(r.id) === String(workersSentPartnerId));
        if (!ag) return;
        const did = String(deploymentId);
        if (Array.isArray(ag.sent_workers)) {
            ag.sent_workers = ag.sent_workers.filter((sw) => String(sw.deployment_id) !== did);
        }
        ag.workers_sent = Array.isArray(ag.sent_workers) ? ag.sent_workers.length : 0;
        if (ag.workers_sent > 0 && Array.isArray(ag.sent_workers)) {
            ag.workers_sent_details = ag.sent_workers
                .map((sw) => `${sw.worker_name || '-'} (${sw.passport_number || '-'})`)
                .join(' | ');
        } else {
            ag.workers_sent_details = '';
        }
        applyControls();
    };

    const renderWorkersSentPageRows = (pageRows) => {
        if (!Array.isArray(pageRows) || pageRows.length === 0) {
            workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">No rows match your filters.</td></tr>`;
            return;
        }
        workersModalBody.innerHTML = pageRows.map((w) => {
            const wid = w.worker_id != null ? String(w.worker_id) : '';
            const depDbId = w.deployment_id != null ? String(w.deployment_id) : '';
            const profileHref = wid ? buildWorkerProfileHref(wid) : '#';
            const curSt = String(w.status || 'processing');
            const statusSelect = depDbId
                ? `<select class="${deploymentStatusSelectClass(curSt)}" data-id="${depDbId.replace(/"/g, '&quot;')}" aria-label="Deployment status">
                        ${deploymentStatusOptions.map((s) => `<option value="${s}"${s === curSt ? ' selected' : ''}>${s}</option>`).join('')}
                    </select>`
                : '<span class="text-muted">—</span>';
            return `
            <tr>
                <td>${w.worker_name || '-'}</td>
                <td>${w.passport_number || '-'}</td>
                <td>${w.country || '-'}</td>
                <td>${w.partner_agency_name || '-'}</td>
                <td>${statusSelect}</td>
                <td class="contract-timeline-cell">
                    <div class="contract-main-line">${w.contract_start || '-'} → ${w.contract_end || '-'}</div>
                    ${contractPlacementHtml(w.contract_start, w.contract_end)}
                </td>
                <td>${w.job_title || '-'}</td>
                <td>${w.salary != null && String(w.salary).trim() !== '' ? w.salary : '-'}</td>
                <td class="actions-cell">
                    ${wid ? `<a class="action-link" href="${profileHref}">Profile</a>` : ''}
                    ${depDbId ? `<button type="button" class="muted-btn workers-sent-delete" data-deployment-id="${depDbId.replace(/"/g, '&quot;')}">Delete</button>` : ''}
                </td>
            </tr>
        `;
        }).join('');
    };

    const applyWorkersSentControls = () => {
        const searchInput = $('workersSentSearch');
        const statusFilter = $('workersSentStatusFilter');
        const sortSelect = $('workersSentSort');
        const pageSizeSelect = $('workersSentPageSize');
        const prevBtn = $('workersSentPrevPage');
        const nextBtn = $('workersSentNextPage');
        const pageInfo = $('workersSentPageInfo');

        const q = (searchInput?.value || '').toLowerCase().trim();
        const st = (statusFilter?.value || '').trim();
        const sort = sortSelect?.value || 'name_asc';
        workersSentSize = Number(pageSizeSelect?.value || 10);

        workersSentFiltered = (workersSentAll || []).filter((w) => {
            const hay = `${w.worker_name || ''} ${w.passport_number || ''} ${w.country || ''} ${w.job_title || ''} ${w.partner_agency_name || ''}`.toLowerCase();
            if (q && !hay.includes(q)) return false;
            if (st && String(w.status || '') !== st) return false;
            return true;
        });

        workersSentFiltered.sort((a, b) => {
            if (sort === 'name_asc') return String(a.worker_name || '').localeCompare(String(b.worker_name || ''));
            if (sort === 'name_desc') return String(b.worker_name || '').localeCompare(String(a.worker_name || ''));
            if (sort === 'contract_desc') return contractEndTs(b) - contractEndTs(a);
            if (sort === 'contract_asc') return contractEndTs(a) - contractEndTs(b);
            return 0;
        });

        const totalPages = Math.max(1, Math.ceil(workersSentFiltered.length / workersSentSize));
        workersSentPage = Math.min(workersSentPage, totalPages);
        const start = (workersSentPage - 1) * workersSentSize;
        const slice = workersSentFiltered.slice(start, start + workersSentSize);
        renderWorkersSentPageRows(slice);

        if (pageInfo) {
            pageInfo.textContent = `Page ${workersSentPage} / ${totalPages}`;
        }
        if (prevBtn) prevBtn.disabled = workersSentPage <= 1;
        if (nextBtn) nextBtn.disabled = workersSentPage >= totalPages;
    };

    const resetWorkersSentPaginationOnly = () => {
        workersSentAll = [];
        workersSentFiltered = [];
        workersSentPage = 1;
        const pi = $('workersSentPageInfo');
        if (pi) pi.textContent = 'Page 1 / 1';
        const pr = $('workersSentPrevPage');
        const nx = $('workersSentNextPage');
        if (pr) pr.disabled = true;
        if (nx) nx.disabled = true;
    };

    const setWorkersSentDataset = (rows, partnerAgencyId) => {
        resetWorkersSentFilters();
        workersSentPartnerId = partnerAgencyId;
        workersSentAll = Array.isArray(rows) ? rows.map((r) => ({ ...r })) : [];
        applyWorkersSentControls();
    };

    const exportWorkersSentCsv = () => {
        if (!workersSentFiltered.length) {
            showToast('Nothing to export.', 'error');
            return;
        }
        const header = ['Worker Name', 'Passport', 'Country', 'Agency', 'Status', 'Contract Start', 'Contract End', 'Job Title', 'Salary', 'Deployment ID', 'Worker ID'];
        const lines = [header.join(',')];
        workersSentFiltered.forEach((w) => {
            lines.push([
                csvEscape(w.worker_name),
                csvEscape(w.passport_number),
                csvEscape(w.country),
                csvEscape(w.partner_agency_name),
                csvEscape(w.status),
                csvEscape(w.contract_start),
                csvEscape(w.contract_end),
                csvEscape(w.job_title),
                csvEscape(w.salary),
                csvEscape(w.deployment_id),
                csvEscape(w.worker_id)
            ].join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        const safeName = String(workersSentPartnerId || 'agency');
        a.href = URL.createObjectURL(blob);
        a.download = `workers-sent-partner-${safeName}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
        showToast('CSV exported.', 'success');
    };

    const openWorkersModal = async (partnerAgencyId, agencyName) => {
        workersModalTitle.textContent = `Deployments — ${agencyName || ''}`;
        workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">Loading...</td></tr>`;
        workersModal.classList.add('open');

        const row = allRows.find((r) => String(r.id) === String(partnerAgencyId));
        const embedded = row?.sent_workers;
        const countHint = Number(row?.workers_sent || 0);

        if (Array.isArray(embedded) && embedded.length > 0) {
            setWorkersSentDataset(embedded, partnerAgencyId);
            return;
        }
        if (!countHint) {
            resetWorkersSentFilters();
            resetWorkersSentPaginationOnly();
            workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">No workers sent yet.</td></tr>`;
            return;
        }

        try {
            const workersUrl = `${apiBase}?partner_agency_id=${encodeURIComponent(partnerAgencyId)}&workers=1`;
            const res = await fetch(withContext(workersUrl), { credentials: 'same-origin' });
            const raw = await res.text();
            let json;
            try {
                json = raw ? JSON.parse(raw) : {};
            } catch (parseErr) {
                json = { success: false, message: 'Server returned non-JSON (check login or PHP errors).' };
            }
            if (!res.ok || json.success !== true) {
                resetWorkersSentPaginationOnly();
                const msg = (json && json.message) ? String(json.message) : `Unable to load workers (${res.status}).`;
                workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">${msg}</td></tr>`;
                return;
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            if (rows.length === 0) {
                resetWorkersSentFilters();
                resetWorkersSentPaginationOnly();
                workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">No workers sent yet.</td></tr>`;
                return;
            }
            setWorkersSentDataset(rows, partnerAgencyId);
        } catch (e) {
            resetWorkersSentPaginationOnly();
            workersModalBody.innerHTML = `<tr><td colspan="${workersSentColspan}">Unable to load workers.</td></tr>`;
        }
    };

    const load = async () => {
        try {
            const res = await fetch(api);
            const json = await res.json();
            if (json.success) {
                allRows = Array.isArray(json.data) ? json.data : [];
                const valid = new Set(allRows.map((r) => String(r.id)));
                [...selectedAgencyIds].forEach((id) => {
                    if (!valid.has(id)) selectedAgencyIds.delete(id);
                });
                currentPage = 1;
                applyControls();
                maybeOpenWorkersSentFromReturn();
                tryReopenWorkersSentFromSession();
                return;
            }
            body.innerHTML = `<tr><td colspan="${agencyTableColspan}">${json.message || 'Unable to load agencies.'}</td></tr>`;
            syncBulkToolbar();
        } catch (error) {
            body.innerHTML = `<tr><td colspan="${agencyTableColspan}">Unable to load agencies right now.</td></tr>`;
            syncBulkToolbar();
        }
    };

    const bulkSetAgencyStatus = async (status) => {
        const ids = [...selectedAgencyIds]
            .map((s) => parseInt(String(s), 10))
            .filter((n) => Number.isFinite(n) && n > 0);
        if (ids.length === 0) {
            showToast('Select at least one agency.', 'error');
            return;
        }
        const verb = status === 'active' ? 'activate' : 'deactivate';
        if (!confirm(`${verb} ${ids.length} selected agenc${ids.length === 1 ? 'y' : 'ies'}?`)) return;
        try {
            const res = await fetch(api, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ ids, status })
            });
            const raw = await res.text();
            let json;
            try {
                json = raw ? JSON.parse(raw) : {};
            } catch (parseErr) {
                json = { success: false, message: 'Invalid server response.' };
            }
            if (!res.ok || json.success !== true) {
                showToast(json.message || 'Bulk update failed.', 'error');
                return;
            }
            ids.forEach((id) => {
                const row = allRows.find((r) => String(r.id) === String(id));
                if (row) row.status = status;
            });
            selectedAgencyIds.clear();
            applyControls();
            showToast(json.message || 'Updated.', 'success');
        } catch (e) {
            showToast('Bulk update failed.', 'error');
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = $('agencyId').value;
        const payload = {
            name: $('agencyName').value.trim(),
            country: $('agencyCountry').value.trim(),
            city: $('agencyCity').value.trim(),
            contact_person: $('agencyContact').value.trim(),
            email: $('agencyEmail').value.trim(),
            phone: $('agencyPhone').value.trim(),
            status: $('agencyStatus').value
        };
        const isEdit = Boolean(id);
        const res = await fetch(isEdit ? `${api}?id=${encodeURIComponent(id)}` : api, {
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            closeModal();
            await load();
            showToast(isEdit ? 'Agency updated successfully.' : 'Agency created successfully.', 'success');
        } else {
            showToast(json.message || 'Unable to save agency', 'error');
        }
    });

    body.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = btn.getAttribute('data-partner-agency-id') || btn.dataset.id;
        if (btn.dataset.action === 'view-workers') {
            const pid = parseInt(String(id || ''), 10);
            if (!Number.isFinite(pid) || pid <= 0) {
                showToast('Missing partner agency id for this row.', 'error');
                return;
            }
            openWorkersModal(pid, btn.dataset.name || 'Agency');
            return;
        }
        const rowId = btn.dataset.id;
        if (btn.dataset.action === 'delete') {
            if (!confirm('Delete this agency?')) return;
            const res = await fetch(`${api}?id=${encodeURIComponent(rowId)}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                await load();
                showToast('Agency deleted successfully.', 'success');
            } else {
                showToast(json.message || 'Unable to delete agency', 'error');
            }
            return;
        }
        if (btn.dataset.action === 'edit') {
            const row = btn.closest('tr');
            const cells = row.querySelectorAll('td');
            openModal({
                id: rowId,
                name: cells[0].textContent.trim(),
                country: cells[1].textContent.trim(),
                city: cells[2].textContent.trim(),
                contact_person: cells[3].textContent.trim(),
                email: cells[4].textContent.trim(),
                phone: cells[5].textContent.trim(),
                status: (cells[7].textContent || '').trim().toLowerCase() === 'active' ? 'active' : 'inactive'
            });
        }
    });

    body.addEventListener('change', (e) => {
        const cb = e.target.closest('.agency-row-check');
        if (!cb) return;
        const id = String(cb.getAttribute('data-agency-id') || '');
        if (!id) return;
        if (cb.checked) {
            selectedAgencyIds.add(id);
        } else {
            selectedAgencyIds.delete(id);
        }
        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);
        syncSelectAllCheckbox(pageRows);
        syncBulkToolbar();
    });

    $('agencySelectAll')?.addEventListener('change', (e) => {
        const sel = e.target;
        if (sel.id !== 'agencySelectAll') return;
        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);
        const ids = pageRows.map((r) => String(r.id));
        if (sel.checked) {
            ids.forEach((id) => selectedAgencyIds.add(id));
        } else {
            ids.forEach((id) => selectedAgencyIds.delete(id));
        }
        applyControls();
    });

    $('bulkAgencyActivate')?.addEventListener('click', () => bulkSetAgencyStatus('active'));
    $('bulkAgencyDeactivate')?.addEventListener('click', () => bulkSetAgencyStatus('inactive'));
    $('bulkAgencyClear')?.addEventListener('click', () => {
        if (selectedAgencyIds.size === 0) return;
        selectedAgencyIds.clear();
        applyControls();
    });

    $('addAgencyBtn').addEventListener('click', () => openModal());
    $('closeAgencyModal').addEventListener('click', closeModal);
    $('cancelAgencyBtn').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    $('agencySearch')?.addEventListener('input', () => { currentPage = 1; applyControls(); });
    $('agencyCountryFilter')?.addEventListener('input', () => { currentPage = 1; applyControls(); });
    $('agencyStatusFilter')?.addEventListener('change', () => { currentPage = 1; applyControls(); });
    $('agencySort')?.addEventListener('change', () => { currentPage = 1; applyControls(); });
    $('agencyPageSize')?.addEventListener('change', () => { currentPage = 1; applyControls(); });
    $('agencyPrevPage')?.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; applyControls(); } });
    $('agencyNextPage')?.addEventListener('click', () => { currentPage += 1; applyControls(); });
    $('closeWorkersModal')?.addEventListener('click', () => workersModal.classList.remove('open'));
    workersModal?.addEventListener('change', async (e) => {
        const select = e.target.closest('.workers-sent-status-select');
        if (!select) return;
        const depId = select.getAttribute('data-id');
        if (!depId) return;
        const prev = workersSentAll.find((w) => String(w.deployment_id) === String(depId));
        const prevStatus = prev ? String(prev.status || 'processing') : '';
        select.className = deploymentStatusSelectClass(select.value);
        try {
            const res = await fetch(withContext(`${deploymentsApi}?id=${encodeURIComponent(depId)}`), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: select.value }),
                credentials: 'same-origin'
            });
            const raw = await res.text();
            let json;
            try {
                json = raw ? JSON.parse(raw) : {};
            } catch (parseErr) {
                json = { success: false, message: 'Invalid server response.' };
            }
            if (!res.ok || json.success !== true) {
                if (prevStatus) {
                    select.value = prevStatus;
                    select.className = deploymentStatusSelectClass(prevStatus);
                }
                showToast(json.message || 'Unable to update deployment status.', 'error');
                return;
            }
            patchSentWorkerStatusEverywhere(depId, select.value);
            showToast('Deployment status updated.', 'success');
            applyWorkersSentControls();
        } catch (err) {
            if (prevStatus) {
                select.value = prevStatus;
                select.className = deploymentStatusSelectClass(prevStatus);
            }
            showToast('Unable to update deployment status.', 'error');
        }
    });

    workersModal?.addEventListener('click', async (e) => {
        if (e.target === workersModal) {
            workersModal.classList.remove('open');
            return;
        }
        const delBtn = e.target.closest('button.workers-sent-delete');
        if (!delBtn) return;
        const depId = delBtn.getAttribute('data-deployment-id');
        if (!depId) return;
        if (!confirm('Delete this deployment? The worker will no longer be linked to this partner agency.')) return;
        try {
            const res = await fetch(withContext(`${deploymentsApi}?id=${encodeURIComponent(depId)}`), {
                method: 'DELETE',
                credentials: 'same-origin'
            });
            const raw = await res.text();
            let json;
            try {
                json = raw ? JSON.parse(raw) : {};
            } catch (parseErr) {
                json = { success: false, message: 'Invalid server response.' };
            }
            if (!res.ok || json.success !== true) {
                showToast(json.message || 'Unable to delete deployment.', 'error');
                return;
            }
            workersSentAll = workersSentAll.filter((w) => String(w.deployment_id) !== String(depId));
            syncParentAgencyAfterDeploymentDelete(depId);
            showToast('Deployment deleted.', 'success');
            applyWorkersSentControls();
        } catch (err) {
            showToast('Unable to delete deployment.', 'error');
        }
    });

    $('workersSentSearch')?.addEventListener('input', () => { workersSentPage = 1; applyWorkersSentControls(); });
    $('workersSentStatusFilter')?.addEventListener('change', () => { workersSentPage = 1; applyWorkersSentControls(); });
    $('workersSentSort')?.addEventListener('change', () => { workersSentPage = 1; applyWorkersSentControls(); });
    $('workersSentPageSize')?.addEventListener('change', () => { workersSentPage = 1; applyWorkersSentControls(); });
    $('workersSentPrevPage')?.addEventListener('click', () => {
        if (workersSentPage > 1) {
            workersSentPage -= 1;
            applyWorkersSentControls();
        }
    });
    $('workersSentNextPage')?.addEventListener('click', () => {
        workersSentPage += 1;
        applyWorkersSentControls();
    });
    $('workersSentExportCsv')?.addEventListener('click', () => exportWorkersSentCsv());

    window.addEventListener('pageshow', (ev) => {
        if (!ev.persisted) return;
        if (!document.getElementById('agenciesTableBody')) return;
        tryReopenWorkersSentFromSession();
    });

    load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

