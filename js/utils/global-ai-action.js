/**
 * EN: Handles reusable global AI modal actions.
 * AR: يدير إجراءات نافذة الذكاء الاصطناعي العامة القابلة لإعادة الاستخدام.
 */
(function () {
    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function notify(message, type) {
        if (window.showNotification) {
            window.showNotification(message, type || 'info');
            return;
        }
        window.alert(message);
    }

    function buildPayloadFromModal(fields) {
        const workerIdText = (fields.workerId?.value || '').trim();
        const fullName = (fields.fullName?.value || '').trim();
        const passport = (fields.passport?.value || '').trim();
        const employerIdText = (fields.employer?.value || '').trim();
        const notifyTo = (fields.email?.value || '').trim();

        if (!workerIdText && (!fullName || !passport)) {
            throw new Error('Worker ID or (Full Name + Passport Number) is required.');
        }

        const workerId = Number(workerIdText);
        const worker = { name: fullName, passport_number: passport };
        if (workerIdText && Number.isFinite(workerId) && workerId > 0) {
            worker.worker_id = workerId;
        }
        if (employerIdText && Number.isFinite(Number(employerIdText))) {
            worker.employer_id = Number(employerIdText);
        }

        return {
            worker_id: worker.worker_id || undefined,
            worker: worker,
            tracking: {
                latitude: 24.7136,
                longitude: 46.6753,
                location_name: 'Global AI onboarding'
            },
            notify_to: notifyTo || 'ops@gov.local'
        };
    }

    onReady(function () {
        const button = document.getElementById('globalAiActionBtn');
        const modal = document.getElementById('globalAiModal');
        if (!button || !modal) return;

        const closeBtn = document.getElementById('globalAiModalClose');
        const cancelBtn = document.getElementById('globalAiCancelBtn');
        const runBtn = document.getElementById('globalAiRunBtn');
        const fields = {
            workerId: document.getElementById('globalAiWorkerId'),
            fullName: document.getElementById('globalAiFullName'),
            passport: document.getElementById('globalAiPassport'),
            employer: document.getElementById('globalAiEmployerId'),
            email: document.getElementById('globalAiEmail')
        };

        const api = {
            open: function (prefill) {
                if (prefill && typeof prefill === 'object') {
                    if (fields.workerId) fields.workerId.value = prefill.workerId || '';
                    if (fields.fullName) fields.fullName.value = prefill.fullName || '';
                    if (fields.passport) fields.passport.value = prefill.passportNumber || '';
                    if (fields.employer) fields.employer.value = prefill.employerId || '';
                    if (fields.email) fields.email.value = prefill.notifyTo || '';
                }
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                if (fields.fullName) fields.fullName.focus();
            },
            close: function () {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            },
            submit: async function (payloadOverride) {
                const baseUrl = button.getAttribute('data-base-url') || '';
                const payload = payloadOverride || buildPayloadFromModal(fields);
                const hasWorkerId = Number.isFinite(Number(payload.worker_id)) && Number(payload.worker_id) > 0;
                const endpoints = hasWorkerId
                    ? [
                        `${baseUrl}/api/control/worker-tracking-onboarding.php`,
                        `${baseUrl}/workflows/worker-onboarding`
                    ]
                    : [`${baseUrl}/workflows/worker-onboarding`];

                if (!runBtn) return;
                runBtn.disabled = true;
                runBtn.textContent = 'Running...';
                try {
                    let lastErrorMessage = 'Failed to execute AI workflow.';
                    for (const endpoint of endpoints) {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const responseText = await response.text();
                        let result = {};
                        try {
                            result = responseText ? JSON.parse(responseText) : {};
                        } catch (parseError) {
                            result = {};
                        }
                        if (!response.ok || !result.success) {
                            lastErrorMessage = result.message || `${response.status} ${response.statusText}` || lastErrorMessage;
                            continue;
                        }
                        const workflowId = result?.workflow_id || result?.data?.worker_id;
                        notify(workflowId ? `AI workflow completed (ID: ${workflowId}).` : 'AI workflow completed.', 'success');
                        api.close();
                        return result;
                    }
                    notify(lastErrorMessage, 'warning');
                    return null;
                } catch (error) {
                    notify(error.message || 'AI workflow failed.', 'warning');
                    return null;
                } finally {
                    runBtn.disabled = false;
                    runBtn.textContent = 'Run AI Workflow';
                }
            }
        };

        window.GlobalAIAction = api;

        button.addEventListener('click', function () {
            api.open();
        });
        if (closeBtn) closeBtn.addEventListener('click', api.close);
        if (cancelBtn) cancelBtn.addEventListener('click', api.close);
        if (runBtn) {
            runBtn.addEventListener('click', async function () {
                try {
                    await api.submit();
                } catch (error) {
                    // handled by submit()
                }
            });
        }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) api.close();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') api.close();
        });
    });
})();
