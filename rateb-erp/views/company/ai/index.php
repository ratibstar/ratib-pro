<?php
/**
 * RATEB AI — General AI Assistant for RATEB ERP
 * Chat interface connected to backend agents
 */
$chatEndpoint = $chatEndpoint ?? rateb_url(rateb_app_route('ai/chat'));
$csrf = $csrf ?? \Rateb\App\Core\Csrf::token();
?>
<div
    class="rateb-ai-container"
    id="ratebAiRoot"
    data-endpoint="<?php echo htmlspecialchars($chatEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="rateb-ai-header">
        <div class="rateb-ai-brand">
            <i class="fa-solid fa-robot"></i>
            <span class="rateb-ai-title"><?php echo htmlspecialchars(__('rateb_ai'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="rateb-ai-status">
            <span class="rateb-ai-status-dot" id="aiStatusDot"></span>
            <span
                class="rateb-ai-status-text"
                id="aiStatusText"
                data-ready="<?php echo htmlspecialchars(__('ai_ready'), ENT_QUOTES, 'UTF-8'); ?>"
                data-thinking="<?php echo htmlspecialchars(__('ai_thinking'), ENT_QUOTES, 'UTF-8'); ?>"
            ><?php echo htmlspecialchars(__('ai_ready'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <div class="rateb-ai-messages" id="aiMessages" role="log" aria-live="polite">
        <div class="rateb-ai-welcome" id="aiWelcome">
            <div class="rateb-ai-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="rateb-ai-welcome-content">
                <h3><?php echo htmlspecialchars(__('rateb_ai'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(__('ai_welcome_message'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rateb-ai-suggestions">
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="<?php echo htmlspecialchars(__('ai_suggest_list_pr'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('ai_suggest_list_pr'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="<?php echo htmlspecialchars(__('ai_suggest_search_suppliers'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('ai_suggest_search_suppliers'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="<?php echo htmlspecialchars(__('ai_suggest_pending_approvals'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('ai_suggest_pending_approvals'), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <form class="rateb-ai-input-form" id="aiInputForm" novalidate>
        <div class="rateb-ai-input-wrapper">
            <textarea
                class="rateb-ai-input"
                id="aiInput"
                name="message"
                placeholder="<?php echo htmlspecialchars(__('ai_input_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"
                rows="1"
                aria-label="<?php echo htmlspecialchars(__('ai_input_label'), ENT_QUOTES, 'UTF-8'); ?>"
                required></textarea>
            <div class="rateb-ai-input-actions">
                <button type="submit" class="rateb-ai-send-btn" id="aiSendBtn" disabled aria-label="<?php echo htmlspecialchars(__('ai_send'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    </form>
</div>

<style>
:root {
    --ai-bg: #ffffff;
    --ai-text: #212529;
    --ai-text-muted: #6c757d;
    --ai-border: #dee2e6;
    --ai-primary: #1a5fb4;
    --ai-primary-hover: #155099;
    --ai-user-bg: #1a5fb4;
    --ai-user-text: #ffffff;
    --ai-assistant-bg: #f8f9fa;
    --ai-assistant-border: #e9ecef;
    --ai-input-bg: #ffffff;
    --ai-input-border: #ced4da;
    --ai-input-focus: #1a5fb4;
    --ai-status-online: #198754;
    --ai-suggestion-bg: #f8f9fa;
    --ai-suggestion-hover: #e9ecef;
    --ai-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

[data-bs-theme="dark"] {
    --ai-bg: #1e1e2e;
    --ai-text: #e4e4e7;
    --ai-text-muted: #a1a1aa;
    --ai-border: #3f3f46;
    --ai-primary: #3b82f6;
    --ai-primary-hover: #2563eb;
    --ai-user-bg: #3b82f6;
    --ai-user-text: #ffffff;
    --ai-assistant-bg: #27272a;
    --ai-assistant-border: #3f3f46;
    --ai-input-bg: #18181b;
    --ai-input-border: #3f3f46;
    --ai-input-focus: #3b82f6;
    --ai-status-online: #22c55e;
    --ai-suggestion-bg: #27272a;
    --ai-suggestion-hover: #3f3f46;
    --ai-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.rateb-ai-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 140px);
    min-height: 480px;
    max-height: calc(100vh - 100px);
    background: var(--ai-bg);
    border-radius: 12px;
    box-shadow: var(--ai-shadow);
    overflow: hidden;
    font-family: 'Tajawal', system-ui, -apple-system, sans-serif;
}

.rateb-ai-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--ai-border);
    background: var(--ai-bg);
    flex-shrink: 0;
}

.rateb-ai-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rateb-ai-brand i {
    font-size: 24px;
    color: var(--ai-primary);
}

.rateb-ai-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ai-text);
}

.rateb-ai-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--ai-text-muted);
}

.rateb-ai-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ai-status-online);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.rateb-ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: var(--ai-bg);
}

.rateb-ai-welcome {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 24px;
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    border-radius: 16px;
    max-width: 600px;
    margin: 0 auto;
}

.rateb-ai-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--ai-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.rateb-ai-welcome-content h3 {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: var(--ai-text);
}

.rateb-ai-welcome-content p {
    margin: 0 0 16px;
    color: var(--ai-text-muted);
    font-size: 14px;
    line-height: 1.5;
}

.rateb-ai-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rateb-ai-suggestion-btn {
    padding: 8px 14px;
    background: var(--ai-suggestion-bg);
    border: 1px solid var(--ai-border);
    border-radius: 20px;
    font-size: 13px;
    color: var(--ai-text);
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.rateb-ai-suggestion-btn:hover {
    background: var(--ai-suggestion-hover);
    border-color: var(--ai-primary);
    color: var(--ai-primary);
}

.rateb-ai-message {
    display: flex;
    gap: 12px;
    max-width: 720px;
}

.rateb-ai-message.user {
    margin-left: auto;
    flex-direction: row-reverse;
}

.rateb-ai-message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ai-primary);
    flex-shrink: 0;
}

.rateb-ai-message.user .rateb-ai-message-avatar {
    background: var(--ai-user-bg);
    border-color: var(--ai-user-bg);
    color: var(--ai-user-text);
}

.rateb-ai-message-content {
    padding: 12px 14px;
    border-radius: 14px;
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    color: var(--ai-text);
    font-size: 14px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.rateb-ai-message.user .rateb-ai-message-content {
    background: var(--ai-user-bg);
    border-color: var(--ai-user-bg);
    color: var(--ai-user-text);
}

.rateb-ai-input-form {
    padding: 16px 20px 20px;
    border-top: 1px solid var(--ai-border);
    background: var(--ai-bg);
    flex-shrink: 0;
}

.rateb-ai-input-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    background: var(--ai-input-bg);
    border: 1px solid var(--ai-input-border);
    border-radius: 14px;
    padding: 10px 12px;
}

.rateb-ai-input-wrapper:focus-within {
    border-color: var(--ai-input-focus);
}

.rateb-ai-input {
    flex: 1;
    border: 0;
    outline: none;
    resize: none;
    background: transparent;
    color: var(--ai-text);
    font-size: 14px;
    line-height: 1.45;
    max-height: 180px;
    font-family: inherit;
}

.rateb-ai-send-btn {
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 10px;
    background: var(--ai-primary);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.rateb-ai-send-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.rateb-ai-typing {
    display: flex;
    gap: 6px;
    padding: 14px 16px;
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    border-radius: 14px;
}

.rateb-ai-typing-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--ai-text-muted);
    animation: aiTyping 1.2s infinite ease-in-out;
}

.rateb-ai-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.rateb-ai-typing-dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes aiTyping {
    0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
    40% { opacity: 1; transform: translateY(-3px); }
}

.tool-call, .tool-result {
    font-size: 12px;
    margin-top: 6px;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .rateb-ai-container {
        height: calc(100vh - 120px);
        border-radius: 0;
    }
    .rateb-ai-welcome {
        padding: 16px;
    }
    .rateb-ai-messages {
        padding: 16px;
    }
}
</style>

<script src="<?php echo htmlspecialchars(rateb_asset('js/rateb-ai-page.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
