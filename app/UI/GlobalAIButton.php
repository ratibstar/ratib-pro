<?php
declare(strict_types=1);

namespace App\UI;

final class GlobalAIButton
{
    /**
     * @param 'float'|'header' $placement float = bottom-right above chat; header = inline in control panel bar
     */
    public static function renderButton(string $baseUrl, string $placement = 'float'): string
    {
        $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        $placement = $placement === 'header' ? 'header' : 'float';
        $class = 'global-ai-action-btn global-ai-action-btn--' . $placement;

        return <<<HTML
<button
    id="globalAiActionBtn"
    class="{$class}"
    type="button"
    data-base-url="{$safeBaseUrl}"
    data-permission="view_workers"
    aria-label="Open Global AI worker workflow">
    <i class="fas fa-robot" aria-hidden="true"></i>
    <span>AI</span>
</button>
HTML;
    }

    public static function renderModalAndScript(string $baseUrl): string
    {
        return self::modalMarkup() . self::submitPatchScript();
    }

    public static function render(string $baseUrl, string $placement = 'float'): string
    {
        return self::renderButton($baseUrl, $placement) . self::renderModalAndScript($baseUrl);
    }

    private static function modalMarkup(): string
    {
        return <<<'HTML'
<div id="globalAiModal" class="global-ai-modal" aria-hidden="true">
    <div class="global-ai-modal-card">
        <div class="global-ai-modal-head">
            <h3>Global AI Onboarding</h3>
            <button id="globalAiModalClose" type="button" class="global-ai-modal-close" aria-label="Dismiss Global AI dialog">&times;</button>
        </div>
        <div class="global-ai-modal-body">
            <label class="global-ai-label" for="globalAiIdentity">Identity Number</label>
            <input id="globalAiIdentity" class="global-ai-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" placeholder="Identity number">

            <label class="global-ai-label" for="globalAiPassport">Passport Number</label>
            <input id="globalAiPassport" class="global-ai-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" placeholder="Passport number">

            <button id="globalAiSearchBtn" type="button" class="global-ai-btn global-ai-btn-cancel">Search Worker</button>

            <div id="globalAiLookupResult" class="global-ai-lookup-result" aria-live="polite"></div>
            <div id="globalAiExecutionResult" class="global-ai-execution-result" aria-live="polite"></div>

            <label class="global-ai-label" for="globalAiEmail">Notification Email (optional)</label>
            <input id="globalAiEmail" class="global-ai-input" type="email" placeholder="ops@gov.local">
        </div>
        <div class="global-ai-modal-actions">
            <button id="globalAiCancelBtn" type="button" class="global-ai-btn global-ai-btn-cancel">Cancel</button>
            <button id="globalAiRunBtn" type="button" class="global-ai-btn global-ai-btn-run">Run AI Workflow</button>
        </div>
    </div>
</div>
HTML;
    }

    private static function submitPatchScript(): string
    {
        return <<<'HTML'
<script id="ratibGlobalAiV7">(function(){if(window.__ratibGlobalAiSubmitV7)return;window.__ratibGlobalAiSubmitV7=1;var cfg=document.getElementById('app-config');var base=(cfg&&cfg.getAttribute('data-api-base'))||'';if(!base){var btn=document.getElementById('globalAiActionBtn');var b=(btn&&btn.getAttribute('data-base-url'))||'';base=(b?b.replace(/\/control-panel\/?$/i,''):'')+'/api';}var RUN_URL=base.replace(/\/$/,'')+'/workers/global-ai-run.php';var origFetch=window.fetch;window.fetch=function(url,opts){var u=typeof url==='string'?url:(url&&url.url)||'';if(u.indexOf('worker-onboarding')!==-1){url=typeof url==='string'?RUN_URL:(typeof Request!=='undefined'?new Request(RUN_URL,url):RUN_URL);}return origFetch.call(this,url,opts);};function patchSubmit(){if(!window.GlobalAIAction||window.GlobalAIAction.__ratibGlobalAiV7)return;var legacy=window.GlobalAIAction.submit&&window.GlobalAIAction.submit.bind(window.GlobalAIAction);if(!legacy)return;window.GlobalAIAction.submit=async function(payloadOverride){var payload=payloadOverride;if(!payload&&window.GlobalAIAction.buildPayload){try{payload=window.GlobalAIAction.buildPayload();}catch(e){return legacy(payloadOverride);}}if(!payload||!payload.worker_id)return legacy(payloadOverride);var runBtn=document.getElementById('globalAiRunBtn');if(runBtn){runBtn.disabled=true;runBtn.textContent='Running...';}try{var res=await fetch(RUN_URL,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)});var data=await res.json().catch(function(){return{};});if(!res.ok||!data.success)return legacy(payloadOverride);if(window.showNotification)window.showNotification('AI workflow completed successfully.','success');return data;}catch(e){return legacy(payloadOverride);}finally{if(runBtn){runBtn.disabled=false;runBtn.textContent='Run AI Workflow';}}};window.GlobalAIAction.__ratibGlobalAiV7=true;}document.addEventListener('DOMContentLoaded',patchSubmit);setTimeout(patchSubmit,400);setTimeout(patchSubmit,2000);})();</script>
HTML;
    }
}
