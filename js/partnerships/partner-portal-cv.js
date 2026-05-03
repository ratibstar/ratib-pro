/**
 * Partner portal — read-only worker CV (same layout as staff Worker CV preview).
 */
(function () {
    const PRINT_STYLES = `
        @page{margin:11mm}
        body{margin:0;background:#fff;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .page.cv-page{max-width:920px;margin:0 auto;background:#f1f5f9;border-radius:16px;overflow:hidden}
        .cv-agency-bar{text-align:center;font-size:10px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#334155;background:#fff;border-bottom:1px solid #e2e8f0;padding:10px 16px}
        .cv-hero{position:relative}
        .cv-hero-bg{position:absolute;inset:0;background:linear-gradient(120deg,#020617 0%,#0f172a 40%,#164e63 100%)}
        .cv-hero-inner.cv-hero-layout{position:relative;z-index:1;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px;padding:22px 26px}
        .cv-name{margin:0;font-size:22px;font-weight:700;color:#f8fafc;font-family:Georgia,serif}
        .cv-headline{margin:10px 0 0;font-size:11px;color:#a5f3fc}
        .photo.photo--hero{width:148px;height:148px;border-radius:50%;border:5px solid rgba(255,255,255,.28);overflow:hidden;display:flex;align-items:center;justify-content:center;background:#475569;color:#e2e8f0;font-size:10px}
        .photo.photo--hero img{width:100%;height:100%;object-fit:cover;border-radius:50%}
        .cv-body-grid{display:grid;grid-template-columns:minmax(0,280px) 1fr;background:#fff}
        .cv-sidebar{padding:16px;border-right:1px solid #e2e8f0;background:#fafafa}
        .cv-sidebar-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px;margin-bottom:10px}
        .cv-section-heading{margin:0 0 10px;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b}
        .cv-field.cv-field-row{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;padding-bottom:9px;border-bottom:1px solid #f1f5f9}
        .cv-field-label{font-size:9px;font-weight:600;text-transform:uppercase;color:#94a3b8}
        .cv-field-value,.cv-value{font-size:12px;color:#0f172a}
        .cv-main{padding:16px}
        .cv-surface{border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;background:#fff}
        .cv-surface--soft{background:#f8fafc}
        .cv-block-title{margin:0 0 12px;font-size:14px;font-weight:700;border-bottom:1px solid #f1f5f9;padding-bottom:8px}
        .cv-field--inline{flex-direction:row;justify-content:space-between;align-items:baseline;border-bottom:1px dashed #e2e8f0}
        .cv-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
        .cv-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px;text-align:center}
        .cv-stat-num{display:block;font-size:1.2rem;font-weight:700;margin-bottom:4px}
        .cv-stat-hint{font-size:9px;text-transform:uppercase;color:#64748b}
        .cv-pills{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .cv-pill{padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fafafa}
        .cv-pill-label{font-size:9px;font-weight:700;text-transform:uppercase;color:#64748b}
        .cv-pill-val{font-size:12px;margin-top:4px}
        .cv-compliance-grid{display:grid;gap:8px}
        .note{font-size:10px;color:#94a3b8;text-align:center;margin-top:12px}
        .missing-value{color:#dc2626;font-weight:600}
        @media print{body{background:#fff}.page.cv-page{box-shadow:none}}
    `;

    function $(id) {
        return document.getElementById(id);
    }

    function buildReadOnlyCvHtml(worker, companyName) {
        const esc = (value) =>
            String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        const pick = (...keys) => {
            for (const key of keys) {
                const val = worker && Object.prototype.hasOwnProperty.call(worker, key) ? worker[key] : undefined;
                if (val !== undefined && val !== null && String(val).trim() !== '') {
                    return String(val).trim();
                }
            }
            return '';
        };
        const line = (value, fallback = 'Not provided') => {
            const clean = String(value ?? '').trim();
            return clean ? esc(clean) : fallback;
        };
        const stripTemplatePlaceholder = (v) => {
            const s = String(v ?? '').trim();
            if (!s) return '';
            if (/^not provided$/i.test(s) || /^n\/?a$/i.test(s) || /^not specified$/i.test(s)) return '';
            return s;
        };
        const show = (value) => line(value, '-');
        const markMissing = (value) => {
            const raw = String(value || '').trim();
            if (!raw || raw === '-' || raw.toLowerCase() === 'not provided') {
                return '<span class="missing-value">Missing</span>';
            }
            return esc(raw);
        };

        const fullName = line(pick('worker_name', 'full_name'));
        const nationality = line(stripTemplatePlaceholder(pick('nationality', 'country')), '');
        const identity = line(pick('identity_number'));
        const passport = line(pick('passport_number'));
        const job = line(pick('job_title', 'occupation', 'specialization'), 'DOMESTIC WORKER');
        const dob = line(pick('date_of_birth', 'birth_date'));
        const placeOfBirth = line(pick('place_of_birth'));
        const phone = line(pick('phone', 'contact_number', 'contact', 'mobile'));
        const email = line(pick('email'));
        const address = line(pick('address', 'city', 'country'));
        const maritalStatus = line(pick('marital_status'));
        const language = line(pick('language', 'language_level'));
        const languageLevel = line(pick('language_level'));
        const educationLevel = line(pick('education_level', 'qualification'));
        const workExperience = line(pick('work_experience', 'local_experience', 'abroad_experience'));
        const skills = line(pick('skills'));
        const localExperience = line(pick('local_experience'));
        const abroadExperience = line(pick('abroad_experience'));
        const qualification = line(pick('qualification'));
        const trainingNotes = line(stripTemplatePlaceholder(pick('training_notes')), '');
        const contractDuration = line(stripTemplatePlaceholder(pick('contract_duration')), '');
        const workingHours = line(stripTemplatePlaceholder(pick('working_hours')), '');
        const salary = line(stripTemplatePlaceholder(pick('salary')), '');
        const gender = show(pick('gender'));
        const age = show(pick('age'));
        const city = show(pick('city'));
        const country = show(pick('country'));
        const workerStatus = show(pick('status'));
        const passportExpiry = show(pick('passport_expiry', 'passport_expiry_date'));
        const medicalNumber = show(pick('medical_number'));

        const photoUrl = pick('personal_photo_url');
        const bar = esc(String(companyName || 'Ratib Program').trim() || 'Ratib Program');

        const imgStyle = photoUrl ? 'width:100%;height:100%;object-fit:cover;display:block' : 'display:none';
        const phStyle = photoUrl ? 'display:none' : 'flex';

        return `
        <div class="page cv-page">
            <div class="cv-agency-bar">${bar}</div>
            <header class="cv-hero">
                <div class="cv-hero-bg"></div>
                <div class="cv-hero-inner cv-hero-layout">
                    <div class="cv-hero-text">
                        <h1 class="cv-name">${fullName}</h1>
                        <p class="cv-headline">${job}</p>
                    </div>
                    <div class="cv-hero-photo">
                        <div class="photo photo--hero">
                            <img src="${photoUrl ? esc(photoUrl) : ''}" alt="" style="${imgStyle}">
                            <span style="${phStyle};align-items:center;justify-content:center;width:100%;height:100%">Photo</span>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cv-body-grid">
                <aside class="cv-sidebar">
                    <section class="cv-sidebar-card">
                        <h3 class="cv-section-heading">Contact</h3>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Phone</span><span class="cv-field-value">${phone}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Email</span><span class="cv-field-value">${email}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Address</span><span class="cv-field-value">${address}</span></div>
                    </section>
                    <section class="cv-sidebar-card">
                        <h3 class="cv-section-heading">Profile</h3>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Date of birth</span><span class="cv-field-value">${dob}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Place of birth</span><span class="cv-field-value">${placeOfBirth}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Nationality</span><span class="cv-field-value">${nationality}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Gender</span><span class="cv-field-value">${markMissing(gender)}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Age</span><span class="cv-field-value">${markMissing(age)}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Marital status</span><span class="cv-field-value">${maritalStatus}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Passport</span><span class="cv-field-value">${passport}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Identity</span><span class="cv-field-value">${identity}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Language</span><span class="cv-field-value">${language}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Language level</span><span class="cv-field-value">${languageLevel}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">City</span><span class="cv-field-value">${markMissing(city)}</span></div>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Country</span><span class="cv-field-value">${markMissing(country)}</span></div>
                    </section>
                    <section class="cv-sidebar-card">
                        <h3 class="cv-section-heading">Education</h3>
                        <div class="cv-field cv-field-row"><span class="cv-field-label">Highest qualification</span><span class="cv-field-value">${educationLevel}</span></div>
                    </section>
                </aside>
                <main class="cv-main">
                    <section class="cv-surface">
                        <h3 class="cv-block-title">Summary</h3>
                        <div class="cv-field cv-field-row cv-field--inline"><span class="cv-field-label">Qualification</span><span class="cv-field-value">${qualification}</span></div>
                        <div class="cv-field cv-field-row cv-field--inline"><span class="cv-field-label">Skills</span><span class="cv-field-value">${skills}</span></div>
                    </section>
                    <section class="cv-surface">
                        <h3 class="cv-block-title">Experience</h3>
                        <div class="cv-stats">
                            <div class="cv-stat"><span class="cv-stat-num">${workExperience}</span><span class="cv-stat-hint">Years total</span></div>
                            <div class="cv-stat"><span class="cv-stat-num">${localExperience}</span><span class="cv-stat-hint">Local</span></div>
                            <div class="cv-stat"><span class="cv-stat-num">${abroadExperience}</span><span class="cv-stat-hint">Abroad</span></div>
                        </div>
                    </section>
                    <section class="cv-surface">
                        <h3 class="cv-block-title">Employment</h3>
                        <div class="cv-pills">
                            <div class="cv-pill"><span class="cv-pill-label">Training & duties</span><span class="cv-pill-val">${trainingNotes}</span></div>
                            <div class="cv-pill"><span class="cv-pill-label">Contract</span><span class="cv-pill-val">${contractDuration}</span></div>
                            <div class="cv-pill"><span class="cv-pill-label">Hours</span><span class="cv-pill-val">${workingHours}</span></div>
                            <div class="cv-pill"><span class="cv-pill-label">Salary</span><span class="cv-pill-val">${salary}</span></div>
                        </div>
                    </section>
                    <section class="cv-surface cv-surface--soft">
                        <h3 class="cv-block-title">Compliance</h3>
                        <div class="cv-compliance-grid">
                            <div class="cv-field cv-field-row cv-field--inline"><span class="cv-field-label">Status</span><span class="cv-field-value">${markMissing(workerStatus)}</span></div>
                            <div class="cv-field cv-field-row cv-field--inline"><span class="cv-field-label">Passport expiry</span><span class="cv-field-value">${markMissing(passportExpiry)}</span></div>
                            <div class="cv-field cv-field-row cv-field--inline"><span class="cv-field-label">Medical ref.</span><span class="cv-field-value">${markMissing(medicalNumber)}</span></div>
                        </div>
                    </section>
                    <p class="note">Partner view · read-only · contact your office to request changes</p>
                </main>
            </div>
        </div>`;
    }

    function printCv() {
        const sheet = $('partnerCvSheet');
        if (!sheet) return;
        const printWindow = window.open('', '_blank', 'width=900,height=1200');
        if (!printWindow) return;
        printWindow.document.write(
            `<!doctype html><html><head><meta charset="utf-8"><title>Worker CV</title><style>${PRINT_STYLES}</style></head><body>${sheet.innerHTML}</body></html>`
        );
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    }

    async function loadCv() {
        const params = new URLSearchParams(window.location.search || '');
        const wid = parseInt(String(params.get('worker_id') || ''), 10) || 0;
        const errEl = $('partnerCvError');
        const sheet = $('partnerCvSheet');
        if (!sheet) return;
        if (wid <= 0) {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = 'Missing worker.';
            }
            return;
        }
        try {
            const res = await fetch(
                `../api/partnerships/partner-portal-worker-cv-data.php?worker_id=${encodeURIComponent(String(wid))}`,
                { credentials: 'same-origin' }
            );
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                if (errEl) {
                    errEl.hidden = false;
                    errEl.textContent = json.message || `Could not load CV (${res.status})`;
                }
                const printBtnErr = $('partnerCvPrint');
                if (printBtnErr) printBtnErr.disabled = true;
                return;
            }
            const d = json.data || {};
            const worker = d.worker || {};
            const company = String(d.company_display_name || '').trim() || 'Ratib Program';
            sheet.innerHTML = buildReadOnlyCvHtml(worker, company);
            const printBtn = $('partnerCvPrint');
            if (printBtn) printBtn.disabled = false;
            if (errEl) {
                errEl.hidden = true;
                errEl.textContent = '';
            }
        } catch (e) {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = e && e.message ? e.message : 'Failed to load.';
            }
            const printBtnCatch = $('partnerCvPrint');
            if (printBtnCatch) printBtnCatch.disabled = true;
        }
    }

    function init() {
        const printBtn = $('partnerCvPrint');
        if (printBtn) printBtn.addEventListener('click', () => printCv());
        loadCv();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
