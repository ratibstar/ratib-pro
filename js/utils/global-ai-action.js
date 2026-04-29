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

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getRuntimeUrls(button) {
        const appConfig = document.getElementById('app-config');
        const buttonBase = (button?.getAttribute('data-base-url') || '').replace(/\/+$/, '');
        const apiBase = (appConfig?.getAttribute('data-api-base') || `${buttonBase}/api`).replace(/\/+$/, '');
        const controlApiPath = (appConfig?.getAttribute('data-control-api-path') || `${buttonBase}/api/control`).replace(/\/+$/, '');
        const publicBase = apiBase.replace(/\/api$/i, '').replace(/\/+$/, '');
        return {
            buttonBase,
            apiBase,
            controlApiPath,
            publicBase
        };
    }

    function buildPayloadFromModal(fields) {
        const identity = onlyDigits(fields.identity?.value || '');
        const passport = onlyDigits(fields.passport?.value || '');
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

    function renderLookupResult(container, result, baseUrl) {
        if (!container) return;
        if (!result || !result.worker) {
            container.innerHTML = '';
            return;
        }
        const worker = result.worker;
        const workerName = escapeHtml(worker.worker_name || worker.full_name || 'Unknown');
        const workerId = escapeHtml(worker.id || '-');
        const identity = escapeHtml(worker.identity_number || '-');
        const passport = escapeHtml(worker.passport_number || '-');
        const casesCount = Number(result.cases_count || 0);
        const ordersCount = Number(result.orders_count || 0);
        const cases = Array.isArray(result.cases) ? result.cases : [];
        const orders = Array.isArray(result.orders) ? result.orders : [];
        const caseLinks = cases.slice(0, 3).map((item) => {
            const caseId = Number(item.id || 0);
            const label = escapeHtml(item.case_number || `Case #${caseId}`);
            if (caseId > 0) {
                return `<a href="${baseUrl}/pages/cases/cases-table.php?view=${caseId}" target="_blank" rel="noopener noreferrer">${label}</a>`;
            }
            return `<span>${label}</span>`;
        }).join(' | ');
        const orderLabels = orders.slice(0, 3).map((item) => {
            const orderId = Number(item.id || 0);
            return orderId > 0 ? `#${orderId}` : '-';
        }).join(' | ');

        const workerDetailsUrl = `${baseUrl}/pages/Worker.php?view=${encodeURIComponent(String(workerId))}`;
        const casesUrl = `${baseUrl}/pages/cases/cases-table.php`;
        const trackingMapUrl = `${baseUrl}/control-panel/pages/control/tracking-map.php?control=1`;
        const onboardingUrl = `${baseUrl}/control-panel/pages/control/tracking-onboarding.php?control=1`;

        container.innerHTML = [
            '<div class="global-ai-result-card">',
            '  <div class="global-ai-result-title">Worker Details</div>',
            `  <div class="global-ai-result-row"><span>ID</span><strong>${workerId}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Name</span><strong>${workerName}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Identity</span><strong>${identity}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Passport</span><strong>${passport}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Cases</span><strong>${casesCount}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Orders</span><strong>${ordersCount}</strong></div>`,
            '  <div class="global-ai-result-links">',
            `    <a href="${workerDetailsUrl}" target="_blank" rel="noopener noreferrer">Open Worker</a>`,
            `    <a href="${casesUrl}" target="_blank" rel="noopener noreferrer">Open Cases</a>`,
            `    <a href="${trackingMapUrl}" target="_blank" rel="noopener noreferrer">Tracking Map</a>`,
            `    <a href="${onboardingUrl}" target="_blank" rel="noopener noreferrer">Mobile Onboarding</a>`,
            '  </div>',
            caseLinks ? `  <div class="global-ai-result-sub"><strong>Latest Cases:</strong> ${caseLinks}</div>` : '',
            orderLabels ? `  <div class="global-ai-result-sub"><strong>Latest Orders:</strong> ${orderLabels}</div>` : '',
            '</div>'
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
                renderLookupResult(lookupResult, null, '');
                if (runBtn) runBtn.disabled = true;
                if (prefill && typeof prefill === 'object') {
                    if (fields.identity) fields.identity.value = onlyDigits(prefill.identityNumber || '');
                    if (fields.passport) fields.passport.value = onlyDigits(prefill.passportNumber || '');
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
                const urls = getRuntimeUrls(button);
                const identity = onlyDigits(fields.identity?.value || '');
                const passport = onlyDigits(fields.passport?.value || '');
                if (fields.identity) fields.identity.value = identity;
                if (fields.passport) fields.passport.value = passport;
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
                    const response = await fetch(`${urls.apiBase}/workers/ai-lookup.php?${query.toString()}`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success || !result.data || !result.data.worker) {
                        throw new Error(result.message || 'Worker not found.');
                    }
                    state.selectedWorker = result.data.worker;
                    renderLookupResult(lookupResult, result.data, urls.publicBase);
                    if (runBtn) runBtn.disabled = false;
                    notify('Worker found. You can run AI workflow now.', 'success');
                    return result.data;
                } catch (error) {
                    state.selectedWorker = null;
                    renderLookupResult(lookupResult, null, urls.publicBase);
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
                const urls = getRuntimeUrls(button);
                const payload = payloadOverride || buildPayloadFromModal(fields);
                const hasWorkerId = Number.isFinite(Number(payload.worker_id)) && Number(payload.worker_id) > 0;

                if (!runBtn) return;
                runBtn.disabled = true;
                runBtn.textContent = 'Running...';
                try {
                    let trackingResult = null;
                    let workflowResult = null;
                    let trackingError = '';
                    let workflowError = '';

                    if (hasWorkerId) {
                        const trackingResponse = await fetch(`${urls.controlApiPath}/worker-tracking-onboarding.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const trackingText = await trackingResponse.text();
                        try {
                            trackingResult = trackingText ? JSON.parse(trackingText) : {};
                        } catch (parseError) {
                            trackingResult = {};
                        }
                        if (!trackingResponse.ok || !trackingResult.success) {
                            trackingError = trackingResult.message || `${trackingResponse.status} ${trackingResponse.statusText}` || 'Tracking onboarding failed.';
                        }
                    }

                    const workflowResponse = await fetch(`${urls.publicBase}/workflows/worker-onboarding`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const workflowText = await workflowResponse.text();
                    try {
                        workflowResult = workflowText ? JSON.parse(workflowText) : {};
                    } catch (parseError) {
                        workflowResult = {};
                    }
                    if (!workflowResponse.ok || !workflowResult.success) {
                        workflowError = workflowResult.message || `${workflowResponse.status} ${workflowResponse.statusText}` || 'Workflow onboarding failed.';
                    }

                    if (workflowError) {
                        const combined = trackingError ? `Tracking: ${trackingError} | Workflow: ${workflowError}` : workflowError;
                        notify(combined, 'warning');
                        return null;
                    }

                    if (trackingError) {
                        notify(`Worker onboarding completed, but tracking setup failed: ${trackingError}`, 'warning');
                    } else if (hasWorkerId) {
                        notify('AI workflow + tracking onboarding completed successfully.', 'success');
                    } else {
                        const workflowId = workflowResult?.workflow_id || workflowResult?.data?.worker_id;
                        notify(workflowId ? `AI workflow completed (ID: ${workflowId}).` : 'AI workflow completed.', 'success');
                    }

                    api.close();
                    return {
                        success: true,
                        tracking: trackingResult,
                        workflow: workflowResult
                    };
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
        if (fields.identity) {
            fields.identity.addEventListener('input', function () {
                fields.identity.value = onlyDigits(fields.identity.value);
            });
        }
        if (fields.passport) {
            fields.passport.addEventListener('input', function () {
                fields.passport.value = onlyDigits(fields.passport.value);
            });
        }
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
