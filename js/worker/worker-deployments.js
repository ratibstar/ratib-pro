/**
 * EN: Implements frontend interaction behavior in `js/worker/worker-deployments.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/worker-deployments.js`.
 */
(function () {
    let modal;
    let form;
    let agencySelect;
    let workerIdField;

    const apiAgencies = '../api/partnerships/partner-agencies.php';
    const apiDeployments = '../api/partnerships/deployments.php';
    const workersApi = '../api/workers/core/get.php';
    let agenciesCache = [];

    async function loadAgencies() {
        const res = await fetch(apiAgencies);
        const json = await res.json();
        const rows = (json.success ? json.data : []).filter((a) => a.status === 'active');
        agenciesCache = rows;
        agencySelect.innerHTML = '<option value="">Select Partner Agency</option>' +
            rows.map((a) => `<option value="${a.id}">${a.name} (${a.country})</option>`).join('');
    }

    function applyAgencyDefaults(agencyId) {
        const selected = agenciesCache.find((a) => String(a.id) === String(agencyId));
        if (!selected) {
            return;
        }

        const countryField = document.getElementById('deploymentCountry');
        const notesField = document.getElementById('deploymentNotes');
        if (countryField && !countryField.value.trim()) {
            countryField.value = selected.country || '';
        }

        if (notesField && !notesField.value.trim()) {
            const details = [];
            if (selected.city) details.push(`City: ${selected.city}`);
            if (selected.contact_person) details.push(`Contact: ${selected.contact_person}`);
            if (selected.email) details.push(`Email: ${selected.email}`);
            if (selected.phone) details.push(`Phone: ${selected.phone}`);
            if (details.length > 0) {
                notesField.value = `Agency details - ${details.join(' | ')}`;
            }
        }

        const salaryField = document.getElementById('deploymentSalary');
        if (salaryField && !salaryField.value.trim()) {
            salaryField.value = suggestSalaryByCountry(selected.country || '');
        }
    }

    function formatDateISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function applyDefaultDates() {
        const startField = document.getElementById('deploymentContractStart');
        const endField = document.getElementById('deploymentContractEnd');
        if (!startField || !endField) {
            return;
        }

        if (!startField.value) {
            startField.value = formatDateISO(new Date());
        }

        if (!endField.value) {
            const end = new Date(startField.value || new Date());
            end.setFullYear(end.getFullYear() + 2);
            endField.value = formatDateISO(end);
        }
    }

    function suggestSalaryByCountry(country) {
        const key = String(country || '').trim().toLowerCase();
        const map = {
            'saudi arabia': '1500.00',
            'uae': '1800.00',
            'qatar': '1600.00',
            'oman': '1400.00',
            'kuwait': '1700.00',
            'bahrain': '1500.00'
        };
        return map[key] || '1500.00';
    }

    function normalizeWesternNumber(value) {
        const arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        const easternArabicIndic = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        let normalized = String(value || '');

        arabicIndic.forEach((digit, index) => {
            normalized = normalized.split(digit).join(String(index));
        });
        easternArabicIndic.forEach((digit, index) => {
            normalized = normalized.split(digit).join(String(index));
        });

        normalized = normalized.replace('٫', '.').replace('،', '.');
        normalized = normalized.replace(/[^0-9.]/g, '');

        const firstDot = normalized.indexOf('.');
        if (firstDot !== -1) {
            normalized = normalized.slice(0, firstDot + 1) + normalized.slice(firstDot + 1).replace(/\./g, '');
        }

        return normalized;
    }

    async function populateWorkerDefaults(workerId) {
        const jobField = document.getElementById('deploymentJobTitle');
        const salaryField = document.getElementById('deploymentSalary');
        const countryField = document.getElementById('deploymentCountry');

        try {
            const res = await fetch(`${workersApi}?id=${encodeURIComponent(workerId)}`);
            const json = await res.json();
            const worker = json?.data?.workers?.[0];

            if (jobField && !jobField.value.trim()) {
                const raw = String(worker?.job_title || '').trim();
                const primary = raw.includes(',') ? raw.split(',')[0].trim() : raw;
                jobField.value = primary || 'Domestic Worker';
            }

            if (salaryField && !salaryField.value.trim()) {
                salaryField.value = suggestSalaryByCountry(countryField?.value || worker?.country || '');
            }
        } catch (e) {
            if (jobField && !jobField.value.trim()) {
                jobField.value = 'Domestic Worker';
            }
            if (salaryField && !salaryField.value.trim()) {
                salaryField.value = '1500.00';
            }
        }
    }

    function open(workerId) {
        workerIdField.value = workerId;
        form.reset();
        workerIdField.value = workerId;
        applyDefaultDates();
        populateWorkerDefaults(workerId);
        modal.classList.add('open');
    }

    function close() {
        modal.classList.remove('open');
    }

    // Keep globally available even before full init.
    window.openDeploymentModal = async function (workerId) {
        if (!modal || !form || !agencySelect || !workerIdField) {
            return;
        }
        await loadAgencies();
        open(workerId);
    };

    function init() {
        modal = document.getElementById('workerDeploymentModal');
        form = document.getElementById('workerDeploymentForm');
        agencySelect = document.getElementById('deploymentAgency');
        workerIdField = document.getElementById('deploymentWorkerId');

        if (!modal || !form || !agencySelect || !workerIdField) {
            return;
        }

        const startDateInput = document.getElementById('deploymentContractStart');
        const endDateInput = document.getElementById('deploymentContractEnd');
        if (typeof window.flatpickr === 'function') {
            if (startDateInput) {
                window.flatpickr(startDateInput, {
                    dateFormat: 'Y-m-d',
                    allowInput: true
                });
            }
            if (endDateInput) {
                window.flatpickr(endDateInput, {
                    dateFormat: 'Y-m-d',
                    allowInput: true
                });
            }
        }

        const salaryInput = document.getElementById('deploymentSalary');
        if (salaryInput) {
            salaryInput.addEventListener('input', function () {
                const normalized = normalizeWesternNumber(this.value);
                if (this.value !== normalized) {
                    this.value = normalized;
                }
            });
            salaryInput.addEventListener('blur', function () {
                const normalized = normalizeWesternNumber(this.value);
                this.value = normalized;
            });
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const salaryField = document.getElementById('deploymentSalary');
            const normalizedSalary = normalizeWesternNumber(salaryField?.value || '');
            const payload = {
                worker_id: Number(workerIdField.value),
                partner_agency_id: Number(agencySelect.value),
                country: document.getElementById('deploymentCountry').value.trim(),
                job_title: document.getElementById('deploymentJobTitle').value.trim(),
                salary: normalizedSalary,
                contract_start: document.getElementById('deploymentContractStart').value,
                contract_end: document.getElementById('deploymentContractEnd').value,
                notes: document.getElementById('deploymentNotes').value.trim()
            };

            const res = await fetch(apiDeployments, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (!json.success) {
                alert(json.message || 'Unable to save deployment');
                return;
            }
            close();
            alert('Worker deployment saved successfully.');
        });

        document.getElementById('closeDeploymentModal')?.addEventListener('click', close);
        document.getElementById('cancelDeploymentBtn')?.addEventListener('click', close);
        agencySelect.addEventListener('change', function () {
            applyAgencyDefaults(this.value);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

