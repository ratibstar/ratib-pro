/**
 * Partner agency detail page — load one agency, portal controls, deployment summary.
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
        ['basic', 'account'].forEach((id) => {
            const panel = document.getElementById(`panel-${id}`);
            if (!panel) return;
            const on = id === name;
            panel.classList.toggle('is-hidden', !on);
            panel.hidden = !on;
        });
        if (name === 'account') {
            loadAccountingTabContent();
        }
    }

    function formatMoneyAmount(n) {
        const x = Number(n);
        if (Number.isNaN(x)) return '—';
        return `${x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR`;
    }

    /** @type {{ destroy?: () => void } | null} */
    let agencyAccountingChartInst = null;

    function destroyAccountingChartInstanceOnly() {
        if (agencyAccountingChartInst && typeof agencyAccountingChartInst.destroy === 'function') {
            agencyAccountingChartInst.destroy();
        }
        agencyAccountingChartInst = null;
    }

    function resetAgencyAccountingChartEmptyUi() {
        const emptyEl = document.getElementById('agencyAccountingChartEmpty');
        const canvas = document.getElementById('agencyAccountingChart');
        if (emptyEl) {
            emptyEl.textContent = '';
            emptyEl.hidden = true;
        }
        if (canvas) canvas.hidden = false;
    }

    function destroyAccountingChart() {
        destroyAccountingChartInstanceOnly();
        resetAgencyAccountingChartEmptyUi();
        const wrap = document.getElementById('agencyAccountingChartWrap');
        if (wrap) {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
        }
    }

    /**
     * English Chart.js bar chart — monthly debit vs credit from RATEB Pro GL.
     * @param {Array<{ label?: string, key?: string, debit?: number, credit?: number }>|undefined} monthRows
     */
    function renderAccountingChart(monthRows) {
        const wrap = document.getElementById('agencyAccountingChartWrap');
        const canvas = document.getElementById('agencyAccountingChart');
        const emptyEl = document.getElementById('agencyAccountingChartEmpty');
        if (!wrap || !canvas) return;
        if (typeof Chart === 'undefined') {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
            return;
        }
        const rows = Array.isArray(monthRows) ? monthRows : [];
        if (rows.length === 0) {
            destroyAccountingChartInstanceOnly();
            if (emptyEl) {
                emptyEl.textContent =
                    'No posted debit or credit by month in this range yet. When your office posts journal entries to this chart account (Accounting → Journal entries), monthly bars will appear here.';
                emptyEl.hidden = false;
            }
            canvas.hidden = true;
            wrap.classList.remove('is-hidden');
            wrap.hidden = false;
            return;
        }
        if (emptyEl) {
            emptyEl.textContent = '';
            emptyEl.hidden = true;
        }
        canvas.hidden = false;
        wrap.classList.remove('is-hidden');
        wrap.hidden = false;
        destroyAccountingChartInstanceOnly();
        const labels = rows.map((r) => (r && r.label) || (r && r.key) || '');
        const debits = rows.map((r) => (Number(r && r.debit) ? Number(r.debit) : 0));
        const credits = rows.map((r) => (Number(r && r.credit) ? Number(r.credit) : 0));
        agencyAccountingChartInst = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Debit (SAR)',
                        data: debits,
                        backgroundColor: 'rgba(45, 212, 191, 0.65)',
                        borderColor: 'rgba(45, 212, 191, 1)',
                        borderWidth: 1,
                    },
                    {
                        label: 'Credit (SAR)',
                        data: credits,
                        backgroundColor: 'rgba(99, 102, 241, 0.55)',
                        borderColor: 'rgba(165, 180, 252, 1)',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#cbd5e1' } },
                    title: {
                        display: true,
                        text: 'Partner ledger — monthly movement (English)',
                        color: '#e2e8f0',
                        font: { size: 14 },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(148, 163, 184, 0.12)' },
                    },
                    y: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(148, 163, 184, 0.12)' },
                    },
                },
            },
        });
    }

    function updateAccountingSummaryFromAgency(agency) {
        const sum = document.getElementById('agencyAccountingLinkSummary');
        const ensureBtn = document.getElementById('agencyAccountingEnsureBtn');
        const coa = document.getElementById('agencyAccountingOpenCoa');
        const panel = document.getElementById('panel-account');
        if (!sum || !panel) return;

        const canView = panel.getAttribute('data-can-view-chart') === '1';
        const canEnsure = panel.getAttribute('data-can-ensure') === '1';
        const linked = !!agency.accounting_linked;
        const code = agency.linked_account_code || '';
        const aname = agency.linked_account_name || '';

        if (linked && code) {
            sum.textContent = `Linked to chart account ${code} — ${aname}.`;
        } else if (linked) {
            sum.textContent = 'Linked to chart of accounts.';
        } else {
            sum.textContent =
                'Not linked yet. Create a ledger account to record journal activity against this partner.';
        }

        if (ensureBtn) {
            ensureBtn.hidden = !(canEnsure && !linked);
        }
        if (coa) {
            coa.hidden = !canView;
            coa.href = withContext('accounting.php');
        }
    }

    async function loadAccountingTabContent() {
        const panel = document.getElementById('panel-account');
        const errEl = document.getElementById('agencyAccountingError');
        const hintEl = document.getElementById('agencyAccountingHint');
        const filters = document.getElementById('agencyAccountingFilters');
        const balances = document.getElementById('agencyAccountingBalances');
        const wrap = document.getElementById('agencyAccountingTableWrap');
        const tbody = document.getElementById('agencyAccountingTbody');

        if (!panel || !agencySnapshot || agencySnapshot.id == null) return;

        if (errEl) {
            errEl.textContent = '';
            errEl.classList.add('is-hidden');
            errEl.hidden = true;
        }
        if (hintEl) {
            hintEl.classList.add('is-hidden');
            hintEl.hidden = true;
        }

        const foot = document.getElementById('agencyAccountingTableFootnote');
        const canView = panel.getAttribute('data-can-view-chart') === '1';
        if (!canView) {
            destroyAccountingChart();
            if (foot) {
                foot.classList.add('is-hidden');
                foot.hidden = true;
            }
            if (hintEl) {
                hintEl.textContent =
                    'You need chart-of-accounts permission to view this statement. Ask an administrator for “View Chart of Accounts”.';
                hintEl.classList.remove('is-hidden');
                hintEl.hidden = false;
            }
            if (filters) filters.hidden = true;
            if (balances) {
                balances.classList.add('is-hidden');
                balances.hidden = true;
            }
            if (wrap) {
                wrap.classList.add('is-hidden');
                wrap.hidden = true;
            }
            return;
        }

        const id = Number(agencySnapshot.id);
        if (!Number.isFinite(id) || id <= 0) return;

        const startEl = document.getElementById('agencyAccountingStart');
        const endEl = document.getElementById('agencyAccountingEnd');
        const qs = new URLSearchParams();
        qs.set('partner_agency_id', String(id));
        if (startEl && startEl.value) qs.set('start_date', startEl.value);
        if (endEl && endEl.value) qs.set('end_date', endEl.value);

        try {
            const res = await fetch(
                withContext(`../api/partnerships/partner-agency-account-statement.php?${qs.toString()}`),
                { credentials: 'same-origin' }
            );
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) {
                throw new Error(json.message || `Statement failed (${res.status})`);
            }

            if (!json.linked) {
                destroyAccountingChart();
                if (foot) {
                    foot.classList.add('is-hidden');
                    foot.hidden = true;
                }
                if (filters) filters.hidden = true;
                if (balances) {
                    balances.classList.add('is-hidden');
                    balances.hidden = true;
                }
                if (wrap) {
                    wrap.classList.add('is-hidden');
                    wrap.hidden = true;
                }
                if (hintEl && json.message) {
                    hintEl.textContent = json.message;
                    hintEl.classList.remove('is-hidden');
                    hintEl.hidden = false;
                }
                return;
            }

            if (hintEl) {
                hintEl.classList.add('is-hidden');
                hintEl.hidden = true;
            }
            if (filters) filters.hidden = false;

            if (balances) {
                balances.innerHTML = `
            <div><span>Opening</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.opening_balance))}</span></div>
            <div><span>Closing</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.closing_balance))}</span></div>
            <div><span>Period debits</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.total_debit))}</span></div>
            <div><span>Period credits</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.total_credit))}</span></div>
        `;
                balances.classList.remove('is-hidden');
                balances.hidden = false;
            }

            const lines = Array.isArray(json.lines) ? json.lines : [];
            if (tbody) {
                tbody.innerHTML = lines
                    .map((row) => {
                        const d = escapeHtml(formatCalendarDate(row.date || ''));
                        const ref = escapeHtml(String(row.reference || ''));
                        const desc = escapeHtml(String(row.description || ''));
                        const dr = escapeHtml(formatMoneyAmount(row.debit));
                        const cr = escapeHtml(formatMoneyAmount(row.credit));
                        const bal = escapeHtml(formatMoneyAmount(row.balance));
                        return `<tr><td>${d}</td><td>${ref}</td><td>${desc}</td><td class="num">${dr}</td><td class="num">${cr}</td><td class="num">${bal}</td></tr>`;
                    })
                    .join('');
            }
            if (wrap) {
                wrap.classList.remove('is-hidden');
                wrap.hidden = false;
            }
            if (foot) {
                foot.classList.remove('is-hidden');
                foot.hidden = false;
            }

            renderAccountingChart(json.chart_by_month);
        } catch (e) {
            destroyAccountingChart();
            const footCatch = document.getElementById('agencyAccountingTableFootnote');
            if (footCatch) {
                footCatch.classList.add('is-hidden');
                footCatch.hidden = true;
            }
            if (errEl) {
                errEl.textContent = e && e.message ? e.message : 'Could not load statement.';
                errEl.classList.remove('is-hidden');
                errEl.hidden = false;
            }
        }
    }

    function initAccountingTab() {
        const ensureBtn = document.getElementById('agencyAccountingEnsureBtn');
        const refreshBtn = document.getElementById('agencyAccountingRefreshBtn');
        const coa = document.getElementById('agencyAccountingOpenCoa');
        if (coa) coa.href = withContext('accounting.php');

        if (ensureBtn) {
            ensureBtn.addEventListener('click', async () => {
                const sid = agencySnapshot && agencySnapshot.id != null ? Number(agencySnapshot.id) : 0;
                if (!Number.isFinite(sid) || sid <= 0) return;
                ensureBtn.disabled = true;
                try {
                    const res = await fetch(withContext('../api/partnerships/partner-agency-account-link.php'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ partner_agency_id: sid }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        throw new Error(json.message || `Could not create link (${res.status})`);
                    }
                    await load();
                    loadAccountingTabContent();
                } catch (e) {
                    showError(e && e.message ? e.message : 'Could not create ledger account.');
                } finally {
                    ensureBtn.disabled = false;
                }
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadAccountingTabContent());
        }

        const startEl = document.getElementById('agencyAccountingStart');
        const endEl = document.getElementById('agencyAccountingEnd');
        if (startEl && !startEl.value) {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 11, 1);
            startEl.value = start.toISOString().slice(0, 10);
        }
        if (endEl && !endEl.value) {
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            endEl.value = end.toISOString().slice(0, 10);
        }
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


    function wirePartnerTableLinks(agencyId) {
        const aid = agencyId != null ? Number(agencyId) : 0;
        const docs = document.getElementById('agencyOpenDocsTable');
        const placements = document.getElementById('agencyOpenPlacementsTable');
        if (aid > 0) {
            if (docs) docs.href = withContext('partner-documents-staff.php?partner_agency_id=' + encodeURIComponent(String(aid)));
            if (placements) placements.href = withContext('partner-agencies.php?open_sent_workers=' + encodeURIComponent(String(aid)));
        } else {
            if (docs) docs.setAttribute('href', '#');
            if (placements) placements.setAttribute('href', '#');
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
        document.title = `${name} · Partner Agency · RATEB`;

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
        wirePartnerTableLinks(agency.id);
        updateAccountingSummaryFromAgency(agency);
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
            if (tabWant === 'account' || tabWant === 'basic') {
                setTabActive(tabWant);
            } else if (tabWant === 'attachments') {
                setTabActive('basic');
                requestAnimationFrame(() => {
                    const card = document.getElementById('agencyPartnerTablesCard');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
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

    }

    function init() {
        initTabs();
        initPortalControls();
        initAccountingTab();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
