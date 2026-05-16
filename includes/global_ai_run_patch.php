<?php
declare(strict_types=1);
/** Inline patch: bypass old global-ai-action.js submit → api/global-ai-run.php */
$patchApiBase = htmlspecialchars(rtrim((string) (function_exists('getBaseUrl') ? getBaseUrl() : ''), '/') . '/api', ENT_QUOTES, 'UTF-8');
?>
<script>
(function () {
    var API_BASE = '<?php echo $patchApiBase; ?>';
    function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function renderExec(data) {
        var el = document.getElementById('globalAiExecutionResult');
        if (!el || !data) return;
        el.innerHTML = '<div class="global-ai-exec-card"><div class="global-ai-exec-title">Execution Result</div>'
            + '<div class="global-ai-result-row"><span>Workflow</span><strong>' + (data.workflow_ok ? 'OK' : 'Failed') + '</strong></div>'
            + '<div class="global-ai-result-row"><span>Tracking</span><strong>' + (data.tracking_ok ? 'OK' : 'Failed') + '</strong></div>'
            + '<div class="global-ai-result-row"><span>Workflow ID</span><strong>' + esc(data.workflow_id || '-') + '</strong></div>'
            + '<div class="global-ai-result-row"><span>Worker ID</span><strong>' + esc(data.worker_id || '-') + '</strong></div>'
            + '<div class="global-ai-result-row"><span>Tenant ID</span><strong>' + esc(data.tenant_id || '-') + '</strong></div>'
            + '<div class="global-ai-result-row"><span>Device ID</span><strong>' + esc(data.device_id || '-') + '</strong></div>'
            + (data.workflow_message ? '<div class="global-ai-result-sub"><strong>Workflow:</strong> ' + esc(data.workflow_message) + '</div>' : '')
            + '</div>';
    }
    function patchSubmit() {
        if (!window.GlobalAIAction || typeof window.GlobalAIAction.submit !== 'function' || window.GlobalAIAction.__ratibRunV5) return;
        var legacy = window.GlobalAIAction.submit.bind(window.GlobalAIAction);
        window.GlobalAIAction.submit = async function (payloadOverride) {
            var runBtn = document.getElementById('globalAiRunBtn');
            var payload = payloadOverride;
            if (!payload && window.GlobalAIAction.buildPayload) {
                try { payload = window.GlobalAIAction.buildPayload(); } catch (e) { return legacy(payloadOverride); }
            }
            if (!payload || !payload.worker_id) return legacy(payloadOverride);
            if (runBtn) { runBtn.disabled = true; runBtn.textContent = 'Running...'; }
            try {
                var res = await fetch(API_BASE + '/workers/ai-lookup.php?action=global_ai_run', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                var data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.success) {
                    var msg = data.message || res.statusText || 'AI workflow failed';
                    if (window.showNotification) window.showNotification(msg, 'warning'); else alert(msg);
                    renderExec({ workflow_ok: false, tracking_ok: !!data.tracking_ok, workflow_id: data.workflow_id || '', worker_id: payload.worker_id, tenant_id: data.tenant_id || '', device_id: data.device_id || '', workflow_message: msg });
                    return null;
                }
                if (window.showNotification) window.showNotification('AI workflow completed successfully.', 'success');
                renderExec(data);
                return data;
            } catch (err) {
                return legacy(payloadOverride);
            } finally {
                if (runBtn) { runBtn.disabled = false; runBtn.textContent = 'Run AI Workflow'; }
            }
        };
        window.GlobalAIAction.__ratibRunV5 = true;
    }
    document.addEventListener('DOMContentLoaded', patchSubmit);
    setTimeout(patchSubmit, 300);
    setTimeout(patchSubmit, 1500);
})();
</script>
