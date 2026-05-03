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

    function downloadHref(cvId) {
        return `../api/partnerships/partner-agency-cv-download.php?id=${encodeURIComponent(String(cvId))}`;
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
        const cvList = document.getElementById('ppCvList');
        const cvEmpty = document.getElementById('ppCvEmpty');
        if (cvs.length === 0) {
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
                    const href = escapeHtml(downloadHref(id));

                    return `<li class="partner-portal-cv-item">
                        <div>
                            <strong>${escapeHtml(title)}</strong>
                            <div class="partner-portal-cv-meta">${escapeHtml(fn)} · ${escapeHtml(formatCalendarDate(c.created_at))}</div>
                        </div>
                        <a class="neon-btn partner-portal-dl-btn" href="${href}">Download</a>
                    </li>`;
                })
                .join('');
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

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (profileModal && profileModal.classList.contains('open')) closeProfileModal();
            if (contractModal && contractModal.classList.contains('open')) closeContractModal();
        });
    }

    function init() {
        bindProfileAndContractUi();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
