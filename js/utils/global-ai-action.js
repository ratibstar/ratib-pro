/**
 * EN: Handles reusable global AI modal actions.
 * AR: يدير إجراءات نافذة الذكاء الاصطناعي العامة القابلة لإعادة الاستخدام.
 */
(function () {
    const state = {
        selectedWorker: null
    };

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
        const identity = (fields.identity?.value || '').trim();
        const passport = (fields.passport?.value || '').trim();
        const notifyTo = (fields.email?.value || '').trim();

        if (!identity && !passport) {
            throw new Error('Identity Number or Passport Number is required.');
        }

        if (!state.selectedWorker || !Number.isFinite(Number(state.selectedWorker.id))) {
            throw new Error('Search and select worker first.');
        }

        const workerId = Number(state.selectedWorker.id);
        const worker = {
            worker_id: workerId,
            name: state.selectedWorker.worker_name || '',
            passport_number: passport || state.selectedWorker.passport_number || '',
            identity_number: identity || state.selectedWorker.identity_number || ''
        };

        return {
            worker_id: workerId,
            worker: worker,
            tracking: {
                latitude: 24.7136,
                longitude: 46.6753,
                location_name: 'Global AI onboarding'
            },
            notify_to: notifyTo || 'ops@gov.local'
        };
    }

    function renderLookupResult(container, result) {
        if (!container) return;
        if (!result || !result.worker) {
            container.innerHTML = '';
            return;
        }
        const worker = result.worker;
        const workerName = worker.worker_name || worker.full_name || 'Unknown';
        const workerId = worker.id || '-';
        const identity = worker.identity_number || '-';
        const passport = worker.passport_number || '-';
        const casesCount = Number(result.cases_count || 0);
        const ordersCount = Number(result.orders_count || 0);

        container.innerHTML = [
            '<div class="global-ai-label" style="margin-top:6px;">Worker Details</div>',
            `<div><strong>ID:</strong> ${workerId}</div>`,
            `<div><strong>Name:</strong> ${workerName}</div>`,
            `<div><strong>Identity:</strong> ${identity}</div>`,
            `<div><strong>Passport:</strong> ${passport}</div>`,
            `<div><strong>Cases:</strong> ${casesCount} | <strong>Orders:</strong> ${ordersCount}</div>`
        ].join('');
    }

    onReady(function () {
        const button = document.getElementById('globalAiActionBtn');
        const modal = document.getElementById('globalAiModal');
        if (!button || !modal) return;

        const closeBtn = document.getElementById('globalAiModalClose');
        const cancelBtn = document.getElementById('globalAiCancelBtn');
        const searchBtn = document.getElementById('globalAiSearchBtn');
        const runBtn = document.getElementById('globalAiRunBtn');
        const lookupResult = document.getElementById('globalAiLookupResult');
        const fields = {
            identity: document.getElementById('globalAiIdentity'),
            passport: document.getElementById('globalAiPassport'),
            email: document.getElementById('globalAiEmail')
        };

        const api = {
            open: function (prefill) {
                state.selectedWorker = null;
                renderLookupResult(lookupResult, null);
                if (runBtn) runBtn.disabled = true;
                if (prefill && typeof prefill === 'object') {
                    if (fields.identity) fields.identity.value = prefill.identityNumber || '';
                    if (fields.passport) fields.passport.value = prefill.passportNumber || '';
                    if (fields.email) fields.email.value = prefill.notifyTo || '';
                }
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                if (fields.identity) fields.identity.focus();
            },
            close: function () {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            },
            lookupWorker: async function () {
                const baseUrl = button.getAttribute('data-base-url') || '';
                const identity = (fields.identity?.value || '').trim();
                const passport = (fields.passport?.value || '').trim();
                if (!identity && !passport) {
                    notify('Enter passport number or identity number first.', 'warning');
                    return null;
                }
                if (searchBtn) {
                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Searching...';
                }
                try {
                    const query = new URLSearchParams();
                    if (identity) query.set('identity_number', identity);
                    if (passport) query.set('passport_number', passport);
                    const response = await fetch(`${baseUrl}/api/workers/ai-lookup.php?${query.toString()}`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success || !result.data || !result.data.worker) {
                        throw new Error(result.message || 'Worker not found.');
                    }
                    state.selectedWorker = result.data.worker;
                    renderLookupResult(lookupResult, result.data);
                    if (runBtn) runBtn.disabled = false;
                    notify('Worker found. You can run AI workflow now.', 'success');
                    return result.data;
                } catch (error) {
                    state.selectedWorker = null;
                    renderLookupResult(lookupResult, null);
                    if (runBtn) runBtn.disabled = true;
                    notify(error.message || 'Worker search failed.', 'warning');
                    return null;
                } finally {
                    if (searchBtn) {
                        searchBtn.disabled = false;
                        searchBtn.textContent = 'Search Worker';
                    }
                }
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
        if (searchBtn) {
            searchBtn.addEventListener('click', async function () {
                await api.lookupWorker();
            });
        }
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
