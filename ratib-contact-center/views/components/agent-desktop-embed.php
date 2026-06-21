<?php
declare(strict_types=1);

/**
 * Agent desktop markup only — CSS/JS loaded by Control Panel layout.
 *
 * @var int $tenantId
 * @var int $agentId
 * @var string $inboxApiBase
 * @var string $softphoneApiBase
 * @var string $wsUrl
 * @var string $assistantApiBase
 */
$tenantId = (int) ($tenantId ?? 1);
$agentId = (int) ($agentId ?? 1);
$inboxApiBase = (string) ($inboxApiBase ?? '');
$softphoneApiBase = (string) ($softphoneApiBase ?? '');
$rtMode = function_exists('control_contact_center_realtime_mode')
    ? control_contact_center_realtime_mode()
    : 'polling';
$wsUrl = (string) ($wsUrl ?? '');
if ($wsUrl === '' || $rtMode === 'polling') {
    $wsUrl = 'polling';
}
$apiBase = $softphoneApiBase;
$userId = null;

if (!function_exists('__')) {
    function __(string $s): string { return $s; }
}
?>
<p class="mb-3 rcc-agent-desktop-wrap">
    <a href="<?php echo htmlspecialchars(function_exists('control_contact_center_hub_page_url') ? control_contact_center_hub_page_url() : '#', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Contact Center Hub
    </a>
</p>

<div class="rcc-agent-desktop" id="rcc-agent-desktop"
     data-tenant="<?php echo $tenantId; ?>"
     data-agent="<?php echo $agentId; ?>"
     data-inbox-api="<?php echo htmlspecialchars($inboxApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-assistant-api="<?php echo htmlspecialchars($assistantApiBase ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-realtime-mode="<?php echo htmlspecialchars($rtMode, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="rcc-agent-desktop__top">
        <div class="rcc-softphone" id="rcc-softphone-panel"
             data-tenant="<?php echo $tenantId; ?>"
             data-agent="<?php echo $agentId; ?>"
             data-user=""
             data-api="<?php echo htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8'); ?>"
             data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="rcc-softphone__header">
                <span><span class="rcc-softphone__status-dot rcc-softphone__status-dot--offline" id="rcc-sp-status-dot"></span><span id="rcc-sp-status-label"><?php echo htmlspecialchars(__('Agent Offline'), ENT_QUOTES, 'UTF-8'); ?></span></span>
                <span id="rcc-sp-queue-name" class="rcc-softphone__meta"></span>
            </div>
            <div class="rcc-softphone__body">
                <div class="rcc-softphone__number" id="rcc-sp-number">—</div>
                <div class="rcc-softphone__meta" id="rcc-sp-direction"></div>
                <div class="rcc-softphone__timer" id="rcc-sp-timer">00:00</div>
                <div class="rcc-softphone__erp rcc-softphone__hidden" id="rcc-sp-erp"></div>
                <div class="rcc-softphone__actions">
                    <button type="button" class="rcc-softphone__btn rcc-softphone__btn--answer" id="rcc-sp-answer" disabled><?php echo htmlspecialchars(__('Answer'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rcc-softphone__btn" id="rcc-sp-hold" disabled><?php echo htmlspecialchars(__('Hold'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rcc-softphone__btn rcc-softphone__btn--hangup" id="rcc-sp-hangup" disabled><?php echo htmlspecialchars(__('Hang up'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rcc-softphone__btn" id="rcc-sp-mute" disabled><?php echo htmlspecialchars(__('Mute'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rcc-softphone__btn" id="rcc-sp-transfer" disabled><?php echo htmlspecialchars(__('Transfer'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rcc-softphone__btn" id="rcc-sp-dial" disabled><?php echo htmlspecialchars(__('Dial'), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
        <audio id="rcc-sp-remote-audio" autoplay playsinline class="rcc-softphone__hidden"></audio>
        <div class="rcc-softphone__popup d-none" id="rcc-sp-incoming-popup">
            <div class="rcc-softphone__popup-card">
                <div class="rcc-softphone__meta"><?php echo htmlspecialchars(__('Incoming call'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="rcc-softphone__number" id="rcc-sp-popup-number">—</div>
                <button type="button" class="rcc-softphone__btn rcc-softphone__btn--answer" id="rcc-sp-popup-answer"><?php echo htmlspecialchars(__('Answer'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
    <div class="rcc-agent-desktop__col rcc-agent-desktop__col--list">
        <div class="rcc-inbox__list-header">Conversations</div>
        <div class="rcc-inbox__list" id="rcc-inbox-list"></div>
    </div>
    <div class="rcc-agent-desktop__col rcc-agent-desktop__col--thread">
        <div class="rcc-inbox__thread" id="rcc-inbox-thread">
            <div class="rcc-inbox__empty"><span class="rcc-inbox__empty-icon">💬</span>Select a conversation to view messages</div>
        </div>
        <div class="rcc-inbox__composer">
            <input type="text" id="rcc-inbox-reply" placeholder="Reply…" autocomplete="off" dir="auto">
            <button type="button" id="rcc-inbox-send">Send</button>
        </div>
    </div>
    <div class="rcc-agent-desktop__col rcc-agent-desktop__col--ai" id="rcc-ai-panel">
        <div class="rcc-ai-copilot" id="rcc-ai-copilot">
            <div class="rcc-ai-copilot__header">
                <i class="fas fa-robot"></i> AI Copilot
                <span class="rcc-ai-copilot__badge" id="rcc-ai-advisory">Advisory</span>
            </div>
            <div class="rcc-ai-copilot__section rcc-ai-copilot__insights">
                <h4>Live insights</h4>
                <div class="rcc-ai-copilot__mood" id="rcc-ai-mood">😐 neutral</div>
                <div class="rcc-ai-copilot__row"><span>Intent</span><strong id="rcc-ai-intent">—</strong></div>
                <div class="rcc-ai-copilot__row"><span>Risk</span><strong id="rcc-ai-risk">—</strong></div>
                <p class="rcc-ai-copilot__summary" id="rcc-ai-summary" dir="auto">Select a conversation for AI insights.</p>
            </div>
            <div class="rcc-ai-copilot__section">
                <h4>Suggested reply</h4>
                <textarea id="rcc-ai-reply" rows="4" readonly placeholder="AI reply suggestion…" dir="auto"></textarea>
                <div class="rcc-ai-copilot__btn-row">
                    <button type="button" class="rcc-ai-copilot__btn" id="rcc-ai-send-as-is">Send as-is</button>
                    <button type="button" class="rcc-ai-copilot__btn rcc-ai-copilot__btn--ghost" id="rcc-ai-edit-send">Edit before send</button>
                </div>
            </div>
            <div class="rcc-ai-copilot__section">
                <h4>Actions</h4>
                <div class="rcc-ai-copilot__actions" id="rcc-ai-actions"></div>
            </div>
            <div class="rcc-ai-copilot__section rcc-ai-copilot__erp" id="rcc-inbox-erp">
                <h4>Customer</h4>
                <p class="rcc-inbox__empty">Select a conversation</p>
            </div>
        </div>
    </div>
</div>
