<?php
declare(strict_types=1);

namespace App\UI;

final class GlobalAIButton
{
    public static function render(string $baseUrl): string
    {
        $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<button
    id="globalAiActionBtn"
    class="global-ai-action-btn"
    type="button"
    data-base-url="{$safeBaseUrl}"
    data-permission="view_workers"
    title="Open AI workflow"
    aria-label="Open AI workflow">
    <i class="fas fa-robot"></i>
    <span>AI</span>
</button>

<div id="globalAiModal" class="global-ai-modal" aria-hidden="true">
    <div class="global-ai-modal-card">
        <div class="global-ai-modal-head">
            <h3>Global AI Onboarding</h3>
            <button id="globalAiModalClose" type="button" class="global-ai-modal-close" aria-label="Close AI modal">&times;</button>
        </div>
        <div class="global-ai-modal-body">
            <label class="global-ai-label" for="globalAiIdentity">Identity Number</label>
            <input id="globalAiIdentity" class="global-ai-input" type="text" placeholder="Identity number">

            <label class="global-ai-label" for="globalAiPassport">Passport Number</label>
            <input id="globalAiPassport" class="global-ai-input" type="text" placeholder="Passport number">

            <button id="globalAiSearchBtn" type="button" class="global-ai-btn global-ai-btn-cancel">Search Worker</button>

            <div id="globalAiLookupResult" class="global-ai-lookup-result" aria-live="polite"></div>

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
}
