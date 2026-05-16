/**
 * EN: Handles reusable global AI modal actions.
 * AR: يدير إجراءات نافذة الذكاء الاصطناعي العامة القابلة لإعادة الاستخدام.
 */
(function () {
    const state = {
        selectedWorker: null,
        executionPayload: null
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

    function resolveWorkerNameFromRecord(worker) {
        if (!worker || typeof worker !== 'object') {
            return '';
        }
        const raw = String(
            worker.worker_name
            || worker.full_name
            || worker.name
            || worker.fullname
            || worker.english_name
            || worker.worker_full_name
            || ''
        ).trim();
        if (raw) {
            return raw;
        }
        const fid = String(worker.formatted_id || '').trim();
        if (fid) {
            return fid;
        }
        const wid = Number(worker.id || 0);
        return wid > 0 ? `Worker ${wid}` : '';
    }

    function resolvePassportDigitsFromRecord(worker, fieldDigits) {
        const fromFields = onlyDigits(fieldDigits || '');
        if (fromFields) {
            return fromFields;
        }
        return onlyDigits(String(worker.passport_number || worker.passport || ''));
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

    /**
     * Absolute URL prefix for control-panel pages (tracking map, onboarding).
     * When the AI button lives inside the control panel, data-base-url already ends with /control-panel — do not append it again.
     */
    function resolveControlPanelRoot(buttonBase, publicBase) {
        const btn = String(buttonBase || '').replace(/\/+$/, '');
        if (/\/control-panel$/i.test(btn)) {
            return btn;
        }
        const pub = String(publicBase || '').replace(/\/+$/, '');
        if (/\/control-panel$/i.test(pub)) {
            return pub;
        }
        const root = pub || (typeof window !== 'undefined' && window.location ? window.location.origin : '');
        return `${root}/control-panel`.replace(/([^:]\/)\/+/g, '$1');
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        let parsed = null;
        try {
            parsed = text ? JSON.parse(text) : {};
        } catch (error) {
            parsed = null;
        }
        return { text, parsed };
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
        const w = state.selectedWorker;
        const passportDigits = resolvePassportDigitsFromRecord(w, passport);
        if (!passportDigits) {
            throw new Error('Passport number is required for the AI workflow. Enter it in the modal or fix the worker record.');
        }
        const worker = {
            worker_id: workerId,
            id: workerId,
            name: resolveWorkerNameFromRecord(w),
            passport_number: passportDigits,
            identity_number: identity || onlyDigits(String(w.identity_number || ''))
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

    function renderLookupResult(container, result, urls) {
        if (!container) return;
        if (!result || !result.worker) {
            container.innerHTML = '';
            return;
        }
        const worker = result.worker;
        const workerName = escapeHtml(resolveWorkerNameFromRecord(worker) || 'Unknown');
        const workerId = escapeHtml(worker.id || '-');
        const identity = escapeHtml(worker.identity_number || '-');
        const passport = escapeHtml(worker.passport_number || '-');
        const casesCount = Number(result.cases_count || 0);
        const ordersCount = Number(result.orders_count || 0);
        const cases = Array.isArray(result.cases) ? result.cases : [];
        const orders = Array.isArray(result.orders) ? result.orders : [];
        const siteRoot = String(urls?.publicBase || '').replace(/\/+$/, '')
            || (typeof window !== 'undefined' && window.location ? window.location.origin : '');
        const caseLinks = cases.slice(0, 3).map((item) => {
            const caseId = Number(item.id || 0);
            const label = escapeHtml(item.case_number || `Case #${caseId}`);
            if (caseId > 0) {
                return `<a href="${siteRoot}/pages/cases/cases-table.php?view=${caseId}" target="_blank" rel="noopener noreferrer">${label}</a>`;
            }
            return `<span>${label}</span>`;
        }).join(' | ');
        const orderLabels = orders.slice(0, 3).map((item) => {
            const orderId = Number(item.id || 0);
            return orderId > 0 ? `#${orderId}` : '-';
        }).join(' | ');

        const workerDetailsUrl = `${siteRoot}/pages/Worker.php?view=${encodeURIComponent(String(workerId))}`;
        const casesUrl = `${siteRoot}/pages/cases/cases-table.php`;
        const cpRoot = resolveControlPanelRoot(urls?.buttonBase, urls?.publicBase);
        const trackingMapUrl = `${cpRoot}/pages/control/tracking-map.php?control=1&standalone=1&map_only=1`;
        const onboardingUrl = `${cpRoot}/pages/control/tracking-onboarding.php?control=1&standalone=1`;

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

    function renderExecutionResult(container, data) {
        if (!container) return;
        if (!data) {
            state.executionPayload = null;
            container.innerHTML = '';
            return;
        }
        state.executionPayload = data;
        const trackingOk = Boolean(data.tracking_ok);
        const workflowOk = Boolean(data.workflow_ok);
        const workflowId = escapeHtml(data.workflow_id || '-');
        const workerId = escapeHtml(data.worker_id || '-');
        const tenantId = escapeHtml(data.tenant_id || '-');
        const deviceId = escapeHtml(data.device_id || '-');
        const trackingMessage = escapeHtml(data.tracking_message || '');
        const workflowMessage = escapeHtml(data.workflow_message || '');

        container.innerHTML = [
            '<div class="global-ai-exec-card">',
            '  <div class="global-ai-exec-title">Execution Result</div>',
            `  <div class="global-ai-result-row"><span>Workflow</span><strong>${workflowOk ? 'OK' : 'Failed'}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Tracking</span><strong>${trackingOk ? 'OK' : 'Failed'}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Workflow ID</span><strong>${workflowId}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Worker ID</span><strong>${workerId}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Tenant ID</span><strong>${tenantId}</strong></div>`,
            `  <div class="global-ai-result-row"><span>Device ID</span><strong>${deviceId}</strong></div>`,
            trackingMessage ? `  <div class="global-ai-result-sub"><strong>Tracking:</strong> ${trackingMessage}</div>` : '',
            workflowMessage ? `  <div class="global-ai-result-sub"><strong>Workflow:</strong> ${workflowMessage}</div>` : '',
            '  <div class="global-ai-result-actions">',
            '    <button type="button" id="globalAiCopyResultBtn" class="global-ai-btn global-ai-btn-cancel">Copy Result JSON</button>',
            '  </div>',
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
        const executionResult = document.getElementById('globalAiExecutionResult');
        const fields = {
            identity: document.getElementById('globalAiIdentity'),
            passport: document.getElementById('globalAiPassport'),
            email: document.getElementById('globalAiEmail')
        };

        const api = {
            open: function (prefill) {
                state.selectedWorker = null;
                renderLookupResult(lookupResult, null, null);
                renderExecutionResult(executionResult, null);
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
                    const lookupUrl = `${urls.apiBase}/workers/ai-lookup.php?${query.toString()}`;
                    const response = await fetch(lookupUrl, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    const payload = await parseJsonResponse(response);
                    const result = payload.parsed;
                    if (!response.ok || !result.success || !result.data || !result.data.worker) {
                        const hint = result && result.message ? result.message : `${response.status} ${response.statusText}`;
                        throw new Error(`Worker lookup failed: ${hint}`);
                    }
                    state.selectedWorker = result.data.worker;
                    renderLookupResult(lookupResult, result.data, urls);
                    renderExecutionResult(executionResult, null);
                    if (runBtn) runBtn.disabled = false;
                    notify('Worker found. You can run AI workflow now.', 'success');
                    return result.data;
                } catch (error) {
                    state.selectedWorker = null;
                    renderLookupResult(lookupResult, null, urls);
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
                // Prefer api/ path (same deploy as workers/ai-lookup); fallback to public/ URL for older bookmarks.
                const workflowUrls = [
                    `${urls.apiBase}/workflows/worker-onboarding.php`,
                    `${urls.publicBase}/public/workflows/worker-onboarding/index.php`
                ];

                if (!runBtn) return;
                runBtn.disabled = true;
                runBtn.textContent = 'Running...';
                try {
                    let trackingResult = null;
                    let workflowResult = null;
                    let trackingError = '';
                    let workflowError = '';

                    if (hasWorkerId) {
                        const trackingUrl = `${urls.controlApiPath}/worker-tracking-onboarding.php`;
                        const trackingResponse = await fetch(trackingUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const trackingPayload = await parseJsonResponse(trackingResponse);
                        trackingResult = trackingPayload.parsed || {};
                        if (!trackingResponse.ok || !trackingResult.success) {
                            trackingError = trackingResult.message || `${trackingResponse.status} ${trackingResponse.statusText}` || 'Tracking onboarding failed.';
                        }
                    }

                    for (const workflowUrl of workflowUrls) {
                        const workflowResponse = await fetch(workflowUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const workflowPayload = await parseJsonResponse(workflowResponse);
                        workflowResult = workflowPayload.parsed || {};
                        if (workflowResponse.ok && workflowResult.success) {
                            workflowError = '';
                            break;
                        }
                        workflowError = workflowResult.message || `${workflowResponse.status} ${workflowResponse.statusText}` || 'Workflow onboarding failed.';
                    }

                    if (workflowError) {
                        const combined = trackingError ? `Tracking: ${trackingError} | Workflow: ${workflowError}` : workflowError;
                        notify(combined, 'warning');
                        renderExecutionResult(executionResult, {
                            tracking_ok: !trackingError,
                            workflow_ok: false,
                            workflow_id: '',
                            worker_id: payload.worker_id || '',
                            tenant_id: trackingResult?.data?.tenant_id || '',
                            device_id: trackingResult?.data?.device_id || '',
                            tracking_message: trackingError,
                            workflow_message: workflowError
                        });
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

                    renderExecutionResult(executionResult, {
                        tracking_ok: !trackingError,
                        workflow_ok: true,
                        workflow_id: workflowResult?.workflow_id || '',
                        worker_id: workflowResult?.worker_id || trackingResult?.data?.worker_id || payload.worker_id || '',
                        tenant_id: trackingResult?.data?.tenant_id || '',
                        device_id: trackingResult?.data?.device_id || '',
                        tracking_message: trackingError || 'Tracking onboarding completed.',
                        workflow_message: 'Worker onboarding workflow completed.'
                    });
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
        if (executionResult) {
            executionResult.addEventListener('click', async function (event) {
                const copyBtn = event.target.closest('#globalAiCopyResultBtn');
                if (!copyBtn) return;
                if (!state.executionPayload) {
                    notify('No execution result to copy yet.', 'warning');
                    return;
                }
                try {
                    const text = JSON.stringify(state.executionPayload, null, 2);
                    await navigator.clipboard.writeText(text);
                    notify('Execution result copied.', 'success');
                } catch (copyError) {
                    notify('Copy failed. Please copy manually.', 'warning');
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
