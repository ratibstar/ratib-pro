/**
 * Partner portal dashboard (scoped session) — aligned with staff agency detail fields.
 * Upload/delete for agency files is staff-only; partners see download-only lists.
 */
(function () {
    const DATE_LOCALE = 'en-US';
    let lastAgency = null;

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

    function formatMoneyAmount(n) {
        const x = Number(n);
        if (Number.isNaN(x)) return '—';
        return `${x.toLocaleString(DATE_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR`;
    }

    /** @type {{ destroy?: () => void } | null} */
    let ppOvLedgerChartInst = null;

    function ppOvDestroyLedgerChartInstanceOnly() {
        if (ppOvLedgerChartInst && typeof ppOvLedgerChartInst.destroy === 'function') {
            ppOvLedgerChartInst.destroy();
        }
        ppOvLedgerChartInst = null;
    }

    function ppOvDestroyLedgerChart() {
        ppOvDestroyLedgerChartInstanceOnly();
        const wrap = document.getElementById('ppOvAcctChartWrap');
        if (wrap) {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
        }
    }

    function ppOvRenderLedgerChart(monthRows) {
        const wrap = document.getElementById('ppOvAcctChartWrap');
        const canvas = document.getElementById('ppOvAcctChart');
        if (!wrap || !canvas) return;
        if (typeof Chart === 'undefined') {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
            return;
        }
        const rows = Array.isArray(monthRows) ? monthRows : [];
        if (rows.length === 0) {
            ppOvDestroyLedgerChart();
            return;
        }
        wrap.classList.remove('is-hidden');
        wrap.hidden = false;
        ppOvDestroyLedgerChartInstanceOnly();
        const labels = rows.map((r) => (r && r.label) || (r && r.key) || '');
        const debits = rows.map((r) => (Number(r && r.debit) ? Number(r.debit) : 0));
        const credits = rows.map((r) => (Number(r && r.credit) ? Number(r.credit) : 0));
        ppOvLedgerChartInst = new Chart(canvas.getContext('2d'), {
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
                        text: 'Posted activity by month',
                        color: '#e2e8f0',
                        font: { size: 13 },
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

    function initOverviewLedgerDefaultDates() {
        const startEl = document.getElementById('ppOvAcctStart');
        const endEl = document.getElementById('ppOvAcctEnd');
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

    async function loadOverviewLedger() {
        const sum = document.getElementById('ppOvAcctSummary');
        const filters = document.getElementById('ppOvAcctFilters');
        const balances = document.getElementById('ppOvAcctBalances');
        const wrap = document.getElementById('ppOvAcctTableWrap');
        const tbody = document.getElementById('ppOvAcctTbody');
        const hintEl = document.getElementById('ppOvAcctHint');

        if (hintEl) {
            hintEl.textContent = '';
            hintEl.classList.add('is-hidden');
            hintEl.hidden = true;
        }

        const startEl = document.getElementById('ppOvAcctStart');
        const endEl = document.getElementById('ppOvAcctEnd');
        const qs = new URLSearchParams();
        if (startEl && startEl.value) qs.set('start_date', startEl.value);
        if (endEl && endEl.value) qs.set('end_date', endEl.value);

        try {
            const res = await fetch(`../api/partnerships/partner-portal-account-statement.php?${qs.toString()}`, {
                credentials: 'same-origin',
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) {
                throw new Error(json.message || `Statement failed (${res.status})`);
            }

            if (!json.linked) {
                ppOvDestroyLedgerChart();
                if (filters) filters.hidden = true;
                if (balances) {
                    balances.classList.add('is-hidden');
                    balances.hidden = true;
                }
                if (wrap) {
                    wrap.classList.add('is-hidden');
                    wrap.hidden = true;
                }
                if (sum) {
                    sum.textContent =
                        json.message ||
                        'Not linked yet. Ask your office to connect this agency to the chart of accounts.';
                }
                if (hintEl && json.message) {
                    hintEl.textContent = json.message;
                    hintEl.classList.remove('is-hidden');
                    hintEl.hidden = false;
                }
                return;
            }

            const code = json.account_code || '';
            const aname = json.account_name || '';
            if (sum) {
                sum.textContent =
                    code && aname
                        ? `Linked to chart account ${code} — ${aname}.`
                        : 'Linked to your office chart of accounts.';
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
                        const d = escapeHtml(String(row.date || ''));
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

            ppOvRenderLedgerChart(json.chart_by_month);
        } catch (e) {
            ppOvDestroyLedgerChart();
            if (sum) {
                sum.textContent = e && e.message ? e.message : 'Could not load account statement.';
            }
        }
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
            .map((w, idx) => {
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
                    <div class="agency-contract-meta agency-contract-meta--with-action">
                        <span class="agency-contract-salary">${escapeHtml(salary)}</span>
                        <button type="button" class="muted-btn partner-portal-contract-view-btn" data-deployment-index="${idx}">View</button>
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
        const line = document.getElementById('ppCvTeaserLine');
        if (!line) return;
        const n = Array.isArray(cvs) ? cvs.length : 0;
        if (n === 0) {
            line.textContent = 'No agency documents uploaded yet.';
            return;
        }
        line.textContent = `${n} document${n === 1 ? '' : 's'} on file — open the full table to search, sort, and download.`;
    }

    function setDashText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    /** Deep links (e.g. from main nav) — re-run after async render so layout matches scroll. */
    function scrollToPartnerPortalHash() {
        const raw = window.location.hash || '';
        if (raw.length < 2) return;
        const id = raw.slice(1);
        if (!/^[a-zA-Z][a-zA-Z0-9_-]*$/.test(id)) return;
        const el = document.getElementById(id);
        if (!el) return;
        requestAnimationFrame(() => {
            el.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
    }

    const PP_NAV_SPY_ORDER = [
        ['dashboard', 'partner-portal-dashboard'],
        ['overview', 'partner-portal-section-overview'],
        ['documents', 'partner-portal-section-documents'],
        ['worker-docs', 'partner-portal-section-worker-docs'],
    ];

    /** Highlight main nav from scroll position (partner home only; sections must exist). */
    function syncPartnerNavSpy() {
        const nav = document.querySelector('.partner-portal-main-nav');
        if (!nav) return;
        const links = nav.querySelectorAll('a[data-pp-nav-spy]');
        if (!links.length) return;
        const dash = document.getElementById('partner-portal-dashboard');
        if (!dash) return;

        const activationY = Math.min(120, Math.max(72, (nav.offsetHeight || 56) + 24));
        let activeSpy = 'dashboard';
        for (const [spy, domId] of PP_NAV_SPY_ORDER) {
            const el = document.getElementById(domId);
            if (!el) continue;
            if (el.getBoundingClientRect().top <= activationY) activeSpy = spy;
        }

        links.forEach((a) => {
            if (a.getAttribute('data-pp-nav-spy') === activeSpy) a.classList.add('is-active');
            else a.classList.remove('is-active');
        });
    }

    let partnerNavSpyRaf = 0;
    function schedulePartnerNavSpy() {
        if (partnerNavSpyRaf) return;
        partnerNavSpyRaf = requestAnimationFrame(() => {
            partnerNavSpyRaf = 0;
            syncPartnerNavSpy();
        });
    }

    function initPartnerNavSpy() {
        const nav = document.querySelector('.partner-portal-main-nav');
        if (!nav || !document.getElementById('partner-portal-dashboard')) return;
        window.addEventListener('scroll', schedulePartnerNavSpy, { passive: true });
        window.addEventListener('resize', schedulePartnerNavSpy);
        syncPartnerNavSpy();
        schedulePartnerNavSpy();
    }

    function updateDashboard(agency, cvs, shared) {
        const sent = Array.isArray(agency && agency.sent_workers) ? agency.sent_workers : [];
        const nDep = sent.length;
        let nProcessing = 0;
        let nDeployed = 0;
        let nReturned = 0;
        let nIssue = 0;
        let nTransferred = 0;
        sent.forEach((w) => {
            const s = String(w.status || 'processing').toLowerCase();
            if (s === 'processing') nProcessing++;
            else if (s === 'deployed') nDeployed++;
            else if (s === 'returned') nReturned++;
            else if (s === 'issue') nIssue++;
            else if (s === 'transferred') nTransferred++;
        });
        setDashText('ppDashDeployments', String(nDep));
        const depHint = document.getElementById('ppDashDeploymentsHint');
        if (depHint) {
            if (nDep === 0) {
                depHint.textContent = 'No deployment rows yet — your office adds placements here.';
            } else {
                const parts = [];
                if (nProcessing) parts.push(`${nProcessing} processing`);
                if (nDeployed) parts.push(`${nDeployed} deployed`);
                if (nReturned) parts.push(`${nReturned} returned`);
                if (nIssue) parts.push(`${nIssue} issue`);
                if (nTransferred) parts.push(`${nTransferred} transferred`);
                depHint.textContent = parts.length ? parts.join(' · ') : 'Status mix — see list below';
            }
        }

        const nCv = Array.isArray(cvs) ? cvs.length : 0;
        const nSh = Array.isArray(shared) ? shared.length : 0;
        const nLib = nCv + nSh;
        setDashText('ppDashDocTotal', String(nLib));
        const docHint = document.getElementById('ppDashDocHint');
        if (docHint) {
            docHint.textContent =
                nLib === 0
                    ? 'Nothing on file yet — your office will upload or share documents.'
                    : `${nCv} agency file${nCv === 1 ? '' : 's'} · ${nSh} worker row${nSh === 1 ? '' : 's'}`;
        }

        setDashText('ppDashWorkerShares', String(nSh));
        const wHint = document.getElementById('ppDashWorkerHint');
        if (wHint) {
            const ready = Array.isArray(shared) ? shared.filter((r) => r && r.has_file).length : 0;
            wHint.textContent =
                nSh === 0
                    ? 'When your office shares worker files, they appear here and in the table.'
                    : `${ready} with downloadable file${ready === 1 ? '' : 's'}`;
        }

        const st = String((agency && agency.status) || 'inactive').toLowerCase();
        const stLabel = st.charAt(0).toUpperCase() + st.slice(1);
        setDashText('ppDashAgencyStatus', stLabel);
        const agHint = document.getElementById('ppDashAgencyHint');
        if (agHint) {
            agHint.textContent =
                st === 'active'
                    ? 'You can use all portal sections while this partnership is active.'
                    : 'Contact your office if you need this partnership reactivated.';
        }
    }

    function profileViewSectionsHtml(agency) {
        const blocks = [
            [
                '🏢 Agency data',
                [
                    ['Agency name', displayValue(agency.name)],
                    ['Agency code', displayValue(agency.agency_code)],
                    ['Country', displayValue(agency.country)],
                    ['City', displayValue(agency.city)],
                    ['Address', displayValue(agency.address_en)],
                    ['Contact person', displayValue(agency.contact_person)],
                    ['Record created', formatCalendarDate(agency.created_at)],
                ],
            ],
            [
                '📞 Contact information',
                [
                    ['Email', displayValue(agency.email)],
                    ['Phone 1', displayValue(agency.phone)],
                    ['Phone 2', displayValue(agency.phone2)],
                    ['Fax', displayValue(agency.fax)],
                    ['Mobile', displayValue(agency.mobile)],
                    ['Account number', displayValue(agency.account_number)],
                ],
            ],
            [
                '📋 Administrative & financial',
                [
                    ['License', displayValue(agency.license)],
                    ['License owner', displayValue(agency.license_owner)],
                    ['Sending bank', displayValue(agency.sending_bank)],
                    ['Passport no.', displayValue(agency.passport_no)],
                    [
                        'Passport issue',
                        `${displayValue(agency.passport_issue_place)} · ${formatCalendarDate(agency.passport_issue_date)}`,
                    ],
                    ['Notes', displayValue(agency.notes)],
                ],
            ],
        ];
        return blocks
            .map(
                ([title, rows]) =>
                    `<section class="partner-portal-modal-section"><h4 class="partner-portal-modal-section-title">${escapeHtml(title)}</h4><dl class="agency-detail-dl">${rows
                        .map(([dt, dd]) => `<div><dt>${escapeHtml(dt)}</dt><dd>${escapeHtml(dd)}</dd></div>`)
                        .join('')}</dl></section>`
            )
            .join('');
    }

    function fillProfileEditForm(agency) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val != null ? String(val) : '';
        };
        set('ppEditContactPerson', agency.contact_person);
        set('ppEditEmail', agency.email);
        set('ppEditPhone', agency.phone);
        set('ppEditPhone2', agency.phone2);
        set('ppEditFax', agency.fax);
        set('ppEditMobile', agency.mobile);
        set('ppEditAddressEn', agency.address_en);
        set('ppEditAddressAr', agency.address_ar);
    }

    function openProfileModal(mode) {
        const modal = document.getElementById('ppProfileModal');
        const viewPanel = document.getElementById('ppProfileViewPanel');
        const viewFooter = document.getElementById('ppProfileViewFooter');
        const form = document.getElementById('ppProfileEditForm');
        const lead = document.getElementById('ppProfileModalLead');
        const title = document.getElementById('ppProfileModalTitle');
        const msg = document.getElementById('ppProfileFormMsg');
        if (!modal || !lastAgency) return;
        if (msg) {
            msg.hidden = true;
            msg.textContent = '';
        }
        if (title) title.textContent = mode === 'edit' ? 'Edit contact details' : 'Your profile';
        if (lead) {
            lead.textContent =
                mode === 'edit'
                    ? 'Edit contact person, phones, email, and address. Your office manages agency name, codes, license, banking, contracts, and deployments.'
                    : 'Information shared by your office. Contact them if something needs correcting beyond what you can edit.';
        }
        if (mode === 'edit') {
            if (viewPanel) viewPanel.innerHTML = '';
            if (viewPanel) viewPanel.hidden = true;
            if (viewFooter) viewFooter.hidden = true;
            if (form) {
                fillProfileEditForm(lastAgency);
                form.hidden = false;
            }
        } else {
            if (viewPanel) {
                viewPanel.hidden = false;
                viewPanel.innerHTML = profileViewSectionsHtml(lastAgency);
            }
            if (viewFooter) viewFooter.hidden = false;
            if (form) form.hidden = true;
        }
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeProfileModal() {
        const modal = document.getElementById('ppProfileModal');
        const form = document.getElementById('ppProfileEditForm');
        if (form) form.hidden = true;
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    function openContractModal(index) {
        const modal = document.getElementById('ppContractModal');
        const body = document.getElementById('ppContractModalBody');
        if (!modal || !body || !lastAgency) return;
        const sent = Array.isArray(lastAgency.sent_workers) ? lastAgency.sent_workers : [];
        const w = sent[index];
        if (!w) return;
        const rows = [
            ['Deployment #', displayValue(w.deployment_id)],
            ['Worker', displayValue(w.worker_name)],
            ['Passport', displayValue(w.passport_number)],
            ['Worker ID', displayValue(w.worker_id)],
            ['Status', displayValue(w.status)],
            ['Contract start', formatCalendarDate(w.contract_start)],
            ['Contract end', formatCalendarDate(w.contract_end)],
            ['Job title', displayValue(w.job_title)],
            ['Country', displayValue(w.country)],
            ['Salary (SAR)', w.salary != null && String(w.salary).trim() !== '' ? String(w.salary) : '—'],
            ['Partner agency', displayValue(w.partner_agency_name)],
        ];
        body.innerHTML = rows.map(([dt, dd]) => `<div><dt>${escapeHtml(dt)}</dt><dd>${escapeHtml(dd)}</dd></div>`).join('');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeContractModal() {
        const modal = document.getElementById('ppContractModal');
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
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
            lastAgency = agency;
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
            updateDashboard(agency, cvs, shared);
            await loadOverviewLedger();
            scrollToPartnerPortalHash();
            schedulePartnerNavSpy();
        } catch (e) {
            if (errEl) {
                errEl.textContent = e && e.message ? e.message : 'Failed to load.';
                errEl.hidden = false;
                errEl.classList.remove('is-hidden');
            }
        }
    }

    function bindProfileAndContractUi() {
        const viewBtns = [
            'ppBtnViewProfile',
            'ppBtnViewContact',
            'ppBtnViewAdmin',
            'ppBtnViewContractsCard',
            'ppBtnViewDocs',
            'ppBtnViewWorkerDocs',
        ];
        viewBtns.forEach((id) => {
            const b = document.getElementById(id);
            if (b) b.addEventListener('click', () => openProfileModal('view'));
        });
        const dashProfile = document.getElementById('ppDashOpenProfile');
        if (dashProfile) dashProfile.addEventListener('click', () => openProfileModal('view'));
        const editBtns = [
            'ppBtnEditAgency',
            'ppBtnEditContact',
            'ppBtnEditAdmin',
            'ppBtnEditContractsCard',
            'ppBtnEditDocs',
            'ppBtnEditWorkerDocs',
        ];
        editBtns.forEach((id) => {
            const b = document.getElementById(id);
            if (b) b.addEventListener('click', () => openProfileModal('edit'));
        });

        ['ppProfileModalClose', 'ppProfileCloseBtn'].forEach((id) => {
            const b = document.getElementById(id);
            if (b) b.addEventListener('click', () => closeProfileModal());
        });
        const cancelBtn = document.getElementById('ppProfileCancelBtn');
        if (cancelBtn) cancelBtn.addEventListener('click', () => closeProfileModal());

        const profileModal = document.getElementById('ppProfileModal');
        if (profileModal) {
            profileModal.addEventListener('click', (e) => {
                if (e.target === profileModal) closeProfileModal();
            });
        }

        const form = document.getElementById('ppProfileEditForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const msg = document.getElementById('ppProfileFormMsg');
                const fd = new FormData(form);
                const payload = {};
                ['contact_person', 'email', 'phone', 'phone2', 'fax', 'mobile', 'address_en', 'address_ar'].forEach((k) => {
                    if (fd.has(k)) payload[k] = String(fd.get(k) || '').trim();
                });
                try {
                    const res = await fetch('../api/partnerships/partner-portal-profile-update.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (res.ok && json.success) {
                        closeProfileModal();
                        await load();
                        return;
                    }
                    if (msg) {
                        msg.textContent = json.message || 'Could not save.';
                        msg.hidden = false;
                    }
                } catch (err) {
                    if (msg) {
                        msg.textContent = err && err.message ? err.message : 'Network error.';
                        msg.hidden = false;
                    }
                }
            });
        }

        const contracts = document.getElementById('ppContracts');
        if (contracts) {
            contracts.addEventListener('click', (e) => {
                const btn = e.target.closest('.partner-portal-contract-view-btn');
                if (!btn) return;
                const ix = parseInt(String(btn.getAttribute('data-deployment-index') || ''), 10);
                if (Number.isFinite(ix)) openContractModal(ix);
            });
        }

        ['ppContractModalClose', 'ppContractCloseBtn'].forEach((id) => {
            const b = document.getElementById(id);
            if (b) b.addEventListener('click', () => closeContractModal());
        });
        const contractModal = document.getElementById('ppContractModal');
        if (contractModal) {
            contractModal.addEventListener('click', (e) => {
                if (e.target === contractModal) closeContractModal();
            });
        }

        const ovAcctRefresh = document.getElementById('ppOvAcctRefreshBtn');
        if (ovAcctRefresh) {
            ovAcctRefresh.addEventListener('click', () => loadOverviewLedger());
        }

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (profileModal && profileModal.classList.contains('open')) closeProfileModal();
            if (contractModal && contractModal.classList.contains('open')) closeContractModal();
        });
    }

    function init() {
        initOverviewLedgerDefaultDates();
        bindProfileAndContractUi();
        window.addEventListener('hashchange', () => {
            scrollToPartnerPortalHash();
            schedulePartnerNavSpy();
        });
        initPartnerNavSpy();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
