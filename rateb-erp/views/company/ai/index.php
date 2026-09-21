<?php
/**
 * RATEB AI — General AI Assistant for RATEB ERP
 * Chat interface connected to backend agents
 */
$chatEndpoint = $chatEndpoint ?? rateb_url(rateb_app_route('ai/chat'));
$towerEndpoint = $towerEndpoint ?? rateb_url(rateb_app_route('ai/tower'));
$controlTower = is_array($controlTower ?? null) ? $controlTower : [];
$csrf = $csrf ?? \Rateb\App\Core\Csrf::token();
$aiJs = rateb_asset('js/rateb-ai-page.js');
$aiHistJs = rateb_asset('js/rateb-ai-history.js');
$aiToolbarJs = rateb_asset('js/rateb-ai-toolbar.js');
$ctCss = rateb_asset('css/rateb-ai-control-tower.css');
$ctJs = rateb_asset('js/rateb-ai-control-tower.js');
/* Bust SW/browser cache for toolbar handlers (soft-nav + deferred scripts). */
$aiHistFile = RATEB_ROOT . '/public/assets/js/rateb-ai-history.js';
$aiPageFile = RATEB_ROOT . '/public/assets/js/rateb-ai-page.js';
$aiToolbarFile = RATEB_ROOT . '/public/assets/js/rateb-ai-toolbar.js';
$ctCssFile = RATEB_ROOT . '/public/assets/css/rateb-ai-control-tower.css';
$ctJsFile = RATEB_ROOT . '/public/assets/js/rateb-ai-control-tower.js';
$aiHistVer = is_file($aiHistFile) ? (string) filemtime($aiHistFile) : '1';
$aiPageVer = is_file($aiPageFile) ? (string) filemtime($aiPageFile) : '1';
$aiToolbarVer = is_file($aiToolbarFile) ? (string) filemtime($aiToolbarFile) : '1';
$ctCssVer = is_file($ctCssFile) ? (string) filemtime($ctCssFile) : '1';
$ctJsVer = is_file($ctJsFile) ? (string) filemtime($ctJsFile) : '1';
$aiHistJs .= (str_contains($aiHistJs, '?') ? '&' : '?') . 'aih=' . rawurlencode($aiHistVer);
$aiJs .= (str_contains($aiJs, '?') ? '&' : '?') . 'aip=' . rawurlencode($aiPageVer);
$aiToolbarJs .= (str_contains($aiToolbarJs, '?') ? '&' : '?') . 'ait=' . rawurlencode($aiToolbarVer);
$ctCss .= (str_contains($ctCss, '?') ? '&' : '?') . 'ctc=' . rawurlencode($ctCssVer);
$ctJs .= (str_contains($ctJs, '?') ? '&' : '?') . 'ctj=' . rawurlencode($ctJsVer);
$aiCompanyId = (int) ($aiCompanyId ?? 0);
$aiUserId = (int) ($aiUserId ?? 0);
$aiCapabilities = is_array($aiCapabilities ?? null) ? $aiCapabilities : [];
$aiToolLabels = is_array($aiToolLabels ?? null) ? $aiToolLabels : [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($ctCss, ENT_QUOTES, 'UTF-8'); ?>">
<script src="<?php echo htmlspecialchars($ctJs, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
/* Inline boot — must work even when SW/soft-nav delays or skips deferred external JS. */
(function () {
    try { if (document.body) document.body.setAttribute('data-rateb-hide-help-assistant', '1'); } catch (eHide) {}
    var API_VER = 9;
    var needsUpgrade = !(window.ratebAi && window.ratebAi.__apiVer === API_VER
        && window.ratebAi.__p0ChatHistory && typeof window.ratebAi.getHistory === 'function'
        && typeof window.ratebAi.clickSuggest === 'function');
    if (needsUpgrade) {
    var prev = window.ratebAi || {};
    window.ratebAi = {
    loading: false,
    lastMessage: prev.lastMessage || '',
    pendingConfirmKeys: Array.isArray(prev.pendingConfirmKeys) ? prev.pendingConfirmKeys : [],
    __apiVer: API_VER,
    __p0WriteConfirm: true,
    __p0SendFix: true,
    __p0LangFix: true,
    __p0I18nUi: true,
    __p0ToolLabels: true,
    __p0ChatHistory: true,
    __unifiedConfirmFix: true,
    isConfirmPhrase: function (message) {
        var m = String(message || '').trim();
        if (!m || m.length > 40) return false;
        return /^(نعم|موافق|تأكيد|أكد|اكد|أكمل|اكمل|نفذ|نفّذ|انشئ|أنشئ|انشاء|إنشاء|تنفيذ|confirm|yes|ok|okay|go|proceed|do\s*it|create|execute)!*$/i.test(m);
    },
    isRejectPhrase: function (message) {
        var m = String(message || '').trim();
        if (!m || m.length > 40) return false;
        return /^(لا|الغاء|إلغاء|توقف|ألغ|الغ|cancel|no|stop|abort|nevermind)!*$/i.test(m);
    },
    send: function (message, confirmedWrites) {
        var root = document.getElementById('ratebAiRoot');
        if (!root) return false;
        var messages = document.getElementById('aiMessages');
        var input = document.getElementById('aiInput');
        var sendBtn = document.getElementById('aiSendBtn');
        var welcome = document.getElementById('aiWelcome');
        var statusDot = document.getElementById('aiStatusDot');
        var statusText = document.getElementById('aiStatusText');
        var endpoint = root.getAttribute('data-endpoint') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        var t = function (key, fallback) {
            return root.getAttribute('data-i18n-' + key) || fallback;
        };
        var toolLabels = {};
        try {
            toolLabels = JSON.parse(root.getAttribute('data-tool-labels') || '{}') || {};
        } catch (eLabels) {
            toolLabels = {};
        }
        var toolLabel = function (name) {
            return (toolLabels && toolLabels[name]) ? toolLabels[name] : name;
        };
        message = String(message || '').trim();
        if (!message || !messages || !endpoint || this.loading) return false;
        confirmedWrites = Array.isArray(confirmedWrites) ? confirmedWrites : [];
        if (!confirmedWrites.length && this.isConfirmPhrase(message) && this.pendingConfirmKeys && this.pendingConfirmKeys.length) {
            confirmedWrites = this.pendingConfirmKeys.slice();
        }
        if (this.isRejectPhrase(message)) {
            this.pendingConfirmKeys = [];
        }

        function setStatus(state) {
            if (!statusText) return;
            if (state === 'thinking') {
                if (statusDot) statusDot.style.animation = 'none';
                statusText.textContent = statusText.getAttribute('data-thinking') || 'Thinking...';
            } else {
                if (statusDot) statusDot.style.animation = 'pulse 2s infinite';
                statusText.textContent = statusText.getAttribute('data-ready') || 'Ready';
            }
        }
        function esc(text) {
            return String(text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;')
                .replace(/\n/g, '<br>');
        }
        function addMsg(role, content) {
            var div = document.createElement('div');
            div.className = 'rateb-ai-message ' + role;
            div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-' +
                (role === 'user' ? 'user' : 'robot') + '"></i></div>' +
                '<div class="rateb-ai-message-content">' + esc(content) + '</div>';
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }
        function typing() {
            var div = document.createElement('div');
            div.className = 'rateb-ai-message assistant rateb-ai-typing-container';
            div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                '<div class="rateb-ai-typing"><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div></div>';
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }
        function showConfirm(pending, originalMessage) {
            var keys = [];
            var labels = [];
            var actionId = '';
            (pending || []).forEach(function (p) {
                if (p && p.confirm_key) keys.push(p.confirm_key);
                if (p && p.tool) labels.push(toolLabel(p.tool));
                if (p && p.action_id && !actionId) actionId = String(p.action_id);
            });
            if (!keys.length) return;
            var wrap = document.createElement('div');
            wrap.className = 'rateb-ai-message assistant rateb-ai-confirm-chrome';
            wrap.setAttribute('data-action-id', actionId || '');
            wrap.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                '<div class="rateb-ai-message-content">' +
                '<div>' + esc(labels.join(', ')) + '</div>' +
                '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">' +
                '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-confirm="1">' + esc(t('confirm', 'Confirm')) + '</button>' +
                '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-cancel="1">' + esc(t('cancel', 'Cancel')) + '</button>' +
                '</div></div>';
            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
            var confirmBtn = wrap.querySelector('[data-rateb-ai-confirm]');
            var cancelBtn = wrap.querySelector('[data-rateb-ai-cancel]');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    if (confirmBtn.disabled || window.ratebAi.loading) return;
                    confirmBtn.disabled = true;
                    if (cancelBtn) cancelBtn.disabled = true;
                    wrap.parentNode && wrap.parentNode.removeChild(wrap);
                    // Deterministic confirmation — never re-send the original create utterance
                    window.ratebAi.send(t('confirm', 'تأكيد'), keys.slice());
                });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    if (cancelBtn.disabled || window.ratebAi.loading) return;
                    cancelBtn.disabled = true;
                    if (confirmBtn) confirmBtn.disabled = true;
                    wrap.parentNode && wrap.parentNode.removeChild(wrap);
                    window.ratebAi.pendingConfirmKeys = [];
                    window.ratebAi.send(t('cancel', 'إلغاء'));
                });
            }
        }

        if (welcome) welcome.style.display = 'none';
        var history = (window.RatebAiHistory && window.RatebAiHistory.getApiHistory)
            ? window.RatebAiHistory.getApiHistory()
            : (this.getHistory ? this.getHistory() : []);
        if (!confirmedWrites.length || this.isConfirmPhrase(message) || this.isRejectPhrase(message)) {
            addMsg('user', message);
            this.lastMessage = message;
            try { window.RatebAiHistory && window.RatebAiHistory.appendMessage('user', message); } catch (eHistU) {}
        }
        if (input) {
            input.value = '';
            input.style.height = 'auto';
        }
        this.loading = true;
        this.syncSendBtn();
        setStatus('thinking');
        var tip = typing();
        var self = this;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                message: message,
                history: history,
                confirmed_writes: confirmedWrites
            })
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, message: t('request-failed', 'Request failed') + ' (' + res.status + ')' };
            }).then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (result) {
            if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
            var data = result.data || {};
            if (!result.ok || !data.success) {
                addMsg('assistant', t('error-prefix', 'Error: ') + (data.message || t('request-failed', 'Request failed')));
                return;
            }
            addMsg('assistant', (data.data && data.data.response) ? data.data.response : t('no-response', 'No response'));
            try {
                var replyText = (data.data && data.data.response) ? data.data.response : '';
                if (replyText && window.RatebAiHistory) window.RatebAiHistory.appendMessage('assistant', replyText);
                if (window.RatebAiHistory && window.RatebAiHistory.decorateDomActions) window.RatebAiHistory.decorateDomActions();
            } catch (eHistA) {}
            var pending = (data.data && data.data.pending_confirmations) ? data.data.pending_confirmations : [];
            if (pending.length) {
                var keys = [];
                pending.forEach(function (p) { if (p && p.confirm_key) keys.push(p.confirm_key); });
                self.pendingConfirmKeys = keys;
                showConfirm(pending, message);
            } else if (confirmedWrites.length) {
                self.pendingConfirmKeys = [];
            }
        }).catch(function (err) {
            if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
            addMsg('assistant', t('error-prefix', 'Error: ') + (err && err.message ? err.message : t('network-error', 'Network error')));
        }).then(function () {
            self.loading = false;
            setStatus('ready');
            self.syncSendBtn();
        });
        return false;
    },
    clickSuggest: function (btn) {
        try {
            var now = Date.now();
            if (this.__lastSuggestAt && (now - this.__lastSuggestAt) < 450) {
                return false;
            }
            this.__lastSuggestAt = now;
            var prompt = '';
            if (btn) {
                prompt = btn.getAttribute('data-prompt') || '';
                if (!String(prompt || '').trim()) {
                    prompt = btn.textContent || btn.innerText || '';
                }
            }
            prompt = String(prompt || '').trim();
            if (!prompt) return false;
            this.loading = false;
            return this.send(prompt);
        } catch (eSuggest) {
            try { console.warn('ratebAi.clickSuggest', eSuggest); } catch (eLog) {}
            return false;
        }
    },
    getHistory: function () {
        var messages = document.getElementById('aiMessages');
        if (!messages) return [];
        var out = [];
        var nodes = messages.querySelectorAll('.rateb-ai-message');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (el.classList.contains('rateb-ai-typing-container')) continue;
            if (el.classList.contains('rateb-ai-confirm-chrome')) continue;
            var role = el.classList.contains('user') ? 'user' : (el.classList.contains('assistant') ? 'assistant' : '');
            if (!role) continue;
            var contentEl = el.querySelector('.rateb-ai-message-content');
            var text = contentEl ? String(contentEl.innerText || contentEl.textContent || '').trim() : '';
            if (!text) continue;
            out.push({ role: role, content: text });
        }
        if (out.length > 16) out = out.slice(out.length - 16);
        return out;
    },
    sendFromInput: function () {
        this.loading = false;
        var input = document.getElementById('aiInput');
        var status = document.getElementById('aiVoiceStatus');
        var msg = input ? String(input.value || '').trim() : '';
        if (!msg) {
            if (status) {
                status.textContent = 'اكتب رسالة أو استخدم المايك أولاً';
                status.setAttribute('data-state', 'ready');
            }
            return false;
        }
        return this.send(msg);
    },
    syncSendBtn: function () {
        var input = document.getElementById('aiInput');
        var sendBtn = document.getElementById('aiSendBtn');
        if (!sendBtn) return;
        var empty = !(input && String(input.value || '').trim());
        /* Never leave the button HTML-disabled when idle — disabled swallows clicks
           (including inline onclick), which made the paper-plane appear broken. */
        sendBtn.disabled = !!this.loading;
        sendBtn.classList.toggle('is-empty', empty && !this.loading);
        sendBtn.setAttribute('aria-disabled', (empty || this.loading) ? 'true' : 'false');
    },
    /* Keep inline onclick intact — cloning used to strip it and leave dead chips. */
    bindSuggestButtons: function () {
        var root = document.getElementById('ratebAiRoot');
        if (!root) return;
        var nodes = root.querySelectorAll('.rateb-ai-suggestion-btn, .rateb-ai-capability-chip');
        Array.prototype.forEach.call(nodes, function (btn) {
            if (btn.getAttribute('data-rateb-ai-confirm') || btn.getAttribute('data-rateb-ai-cancel')) return;
            btn.setAttribute('data-suggest-v', '5');
        });
    },
    bind: function () {
        var root = document.getElementById('ratebAiRoot');
        if (!root) return;
        var form = document.getElementById('aiInputForm');
        var input = document.getElementById('aiInput');
        var sendBtn = document.getElementById('aiSendBtn');
        var self = this;
        if (form && form.getAttribute('data-rateb-ai-submit') !== '1') {
            form.setAttribute('data-rateb-ai-submit', '1');
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                self.sendFromInput();
            });
        }
        if (input && input.getAttribute('data-rateb-ai-input') !== '1') {
            input.setAttribute('data-rateb-ai-input', '1');
            var onType = function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 180) + 'px';
                self.syncSendBtn();
            };
            input.addEventListener('input', onType);
            input.addEventListener('keyup', onType);
            input.addEventListener('change', onType);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendFromInput();
                }
            });
        }
        if (sendBtn && sendBtn.getAttribute('data-rateb-ai-click') !== '1') {
            sendBtn.setAttribute('data-rateb-ai-click', '1');
            sendBtn.addEventListener('click', function (e) {
                e.preventDefault();
                self.sendFromInput();
            });
        }
        root.setAttribute('data-rateb-ai-bound', '1');
        this.loading = false;
        self.bindSuggestButtons();
        self.syncSendBtn();
        try { if (input) input.focus(); } catch (e) {}
    }
};
    } // end needsUpgrade
    if (!window.__ratebAiClickBoundV6) {
        window.__ratebAiClickBoundV6 = true;
        document.addEventListener('click', function (e) {
            if (!document.getElementById('ratebAiRoot') || !window.ratebAi) return;
            // page.js owns mic/headset/stop once its master binder is active.
            if (window.__ratebAiClickBoundV8) {
                var onlySuggest = e.target && e.target.closest
                    ? e.target.closest('.rateb-ai-suggestion-btn, .rateb-ai-capability-chip, #aiSendBtn')
                    : null;
                if (!onlySuggest) return;
                if (onlySuggest.getAttribute('data-rateb-ai-confirm') || onlySuggest.getAttribute('data-rateb-ai-cancel')) return;
                e.preventDefault();
                var apiS = window.ratebAi;
                if (onlySuggest.id === 'aiSendBtn') {
                    if (typeof apiS.sendFromInput === 'function') apiS.sendFromInput();
                    return;
                }
                if (typeof apiS.clickSuggest === 'function') apiS.clickSuggest(onlySuggest);
                return;
            }
            var t = e.target && e.target.closest
                ? e.target.closest('.rateb-ai-suggestion-btn, .rateb-ai-capability-chip, #aiSendBtn, #aiVoiceModeBtn, #aiVoiceInputBtn, #aiVoiceStopBtn')
                : null;
            if (!t) return;
            if (t.getAttribute('data-rateb-ai-confirm') || t.getAttribute('data-rateb-ai-cancel')) return;
            e.preventDefault();
            var api = window.ratebAi;
            if ((t.id === 'aiVoiceModeBtn' || t.id === 'aiVoiceInputBtn' || t.id === 'aiVoiceStopBtn')
                && typeof api.bindVoice === 'function') {
                try { api.bindVoice(); } catch (eB) {}
            }
            if (t.id === 'aiSendBtn') {
                if (typeof api.sendFromInput === 'function') api.sendFromInput();
                return;
            }
            if (t.id === 'aiVoiceModeBtn') {
                if (typeof api.toggleVoiceMode === 'function') api.toggleVoiceMode();
                return;
            }
            if (t.id === 'aiVoiceInputBtn') {
                if (typeof api.toggleVoiceInput === 'function') api.toggleVoiceInput();
                return;
            }
            if (t.id === 'aiVoiceStopBtn') {
                if (typeof api.stopVoice === 'function') api.stopVoice();
                return;
            }
            if (typeof api.clickSuggest === 'function') {
                api.clickSuggest(t);
            }
        }, true);
    }
    if (!window.__ratebAiNavBound) {
        window.__ratebAiNavBound = true;
        document.addEventListener('rateb:nav:afterEnter', function () {
            var root = document.getElementById('ratebAiRoot');
            if (!root || !window.ratebAi) return;
            root.removeAttribute('data-rateb-ai-bound');
            var form = document.getElementById('aiInputForm');
            var input = document.getElementById('aiInput');
            var sendBtn = document.getElementById('aiSendBtn');
            if (form) form.removeAttribute('data-rateb-ai-submit');
            if (input) input.removeAttribute('data-rateb-ai-input');
            if (sendBtn) sendBtn.removeAttribute('data-rateb-ai-click');
            window.ratebAi.bind();
        });
        document.addEventListener('rateb:soft-nav:afterEnter', function () {
            var root = document.getElementById('ratebAiRoot');
            if (!root || !window.ratebAi) return;
            root.removeAttribute('data-rateb-ai-bound');
            var form = document.getElementById('aiInputForm');
            var input = document.getElementById('aiInput');
            var sendBtn = document.getElementById('aiSendBtn');
            if (form) form.removeAttribute('data-rateb-ai-submit');
            if (input) input.removeAttribute('data-rateb-ai-input');
            if (sendBtn) sendBtn.removeAttribute('data-rateb-ai-click');
            window.ratebAi.bind();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.ratebAi && window.ratebAi.bind(); });
    } else {
        window.ratebAi && window.ratebAi.bind();
    }
})();
</script>
<?php
// Always render Control Tower chrome (platform + tenant) so the UI matches.
require __DIR__ . '/control-tower.php';
?>
<div
    class="rateb-ai-container"
    id="ratebAiRoot"
    data-endpoint="<?php echo htmlspecialchars($chatEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-company-id="<?php echo (int) $aiCompanyId; ?>"
    data-user-id="<?php echo (int) $aiUserId; ?>"
    data-i18n-confirm="<?php echo htmlspecialchars(__('ai_confirm'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-cancel="<?php echo htmlspecialchars(__('ai_cancel'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-error-prefix="<?php echo htmlspecialchars(__('ai_error_prefix'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-request-failed="<?php echo htmlspecialchars(__('ai_request_failed'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-no-response="<?php echo htmlspecialchars(__('ai_no_response'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-network-error="<?php echo htmlspecialchars(__('ai_network_error'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-new-chat="<?php echo htmlspecialchars(__('ai_new_chat'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-edit="<?php echo htmlspecialchars(__('ai_edit_message'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-delete="<?php echo htmlspecialchars(__('ai_delete'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-confirm-clear="<?php echo htmlspecialchars(__('ai_confirm_clear_chat'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-confirm-clear-all="<?php echo htmlspecialchars(__('ai_confirm_clear_all'), ENT_QUOTES, 'UTF-8'); ?>"
    data-i18n-hist-empty="<?php echo htmlspecialchars(__('ai_history_empty'), ENT_QUOTES, 'UTF-8'); ?>"
    data-tool-labels="<?php
        echo htmlspecialchars(json_encode($aiToolLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
    ?>"
>
    <?php if (!empty($aiNeedsCompany)): ?>
    <div class="alert alert-info mb-3" role="status" id="aiCompanyRequiredBanner">
        <i class="fas fa-building me-1"></i>
        <?php echo htmlspecialchars(__('ai_platform_mode_hint'), ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <div class="rateb-ai-header">
        <div class="rateb-ai-brand">
            <?php
            $aiBackUrl = rateb_url(rateb_app_route('purchase-requests'));
            ?>
            <a href="<?php echo htmlspecialchars($aiBackUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary rateb-ai-back-btn" data-rateb-full-nav="1" title="<?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-arrow-right"></i>
                <span><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <i class="fa-solid fa-robot"></i>
            <span class="rateb-ai-title"><?php echo htmlspecialchars(__('rateb_ai'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="rateb-ai-toolbar">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="aiHistNew" title="<?php echo htmlspecialchars(__('ai_new_chat'), ENT_QUOTES, 'UTF-8'); ?>" onclick="try{if(window.RatebAiHistory){if(!window.RatebAiHistory.root)window.RatebAiHistory.init();window.RatebAiHistory.newChat();}return false;}catch(e){return false;}">
                <i class="fa-solid fa-plus"></i>
                <span><?php echo htmlspecialchars(__('ai_new_chat'), ENT_QUOTES, 'UTF-8'); ?></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="aiHistToggle" aria-expanded="false" title="<?php echo htmlspecialchars(__('ai_history'), ENT_QUOTES, 'UTF-8'); ?>" onclick="try{var p=document.getElementById('aiHistPanel');var b=document.getElementById('aiHistToggle');if(!p)return false;p.hidden=!p.hidden;if(b)b.setAttribute('aria-expanded',p.hidden?'false':'true');if(!p.hidden&&window.RatebAiHistory){if(!window.RatebAiHistory.root)window.RatebAiHistory.init();window.RatebAiHistory.renderSessionList();}return false;}catch(e){return false;}">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span><?php echo htmlspecialchars(__('ai_history'), ENT_QUOTES, 'UTF-8'); ?></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="aiHistClear" title="<?php echo htmlspecialchars(__('ai_clear_chat'), ENT_QUOTES, 'UTF-8'); ?>" onclick="try{if(window.RatebAiHistory){if(!window.RatebAiHistory.root)window.RatebAiHistory.init();window.RatebAiHistory.clearCurrent();}return false;}catch(e){return false;}">
                <i class="fa-solid fa-eraser"></i>
                <span><?php echo htmlspecialchars(__('ai_clear_chat'), ENT_QUOTES, 'UTF-8'); ?></span>
            </button>
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
    </div>

    <div class="rateb-ai-main">
        <aside class="rateb-ai-hist-panel" id="aiHistPanel" hidden>
            <div class="rateb-ai-hist-head">
                <strong><?php echo htmlspecialchars(__('ai_history'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <button type="button" class="btn btn-sm btn-outline-danger" id="aiHistClearAll" onclick="try{if(window.RatebAiHistory){if(!window.RatebAiHistory.root)window.RatebAiHistory.init();window.RatebAiHistory.clearAll();}return false;}catch(e){return false;}"><?php echo htmlspecialchars(__('ai_clear_all_history'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <input type="search" class="rateb-ai-hist-search" id="aiHistSearch" placeholder="<?php echo htmlspecialchars(__('ai_history_search'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__('ai_history_search'), ENT_QUOTES, 'UTF-8'); ?>">
            <ul class="rateb-ai-hist-list" id="aiHistList"></ul>
        </aside>

        <div class="rateb-ai-chat-col">
    <div class="rateb-ai-messages" id="aiMessages" role="log" aria-live="polite">
        <div class="rateb-ai-welcome" id="aiWelcome">
            <div class="rateb-ai-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="rateb-ai-welcome-content">
                <h3><?php echo htmlspecialchars(__('rateb_ai'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(__('ai_welcome_message'), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($aiCapabilities !== []): ?>
                <div class="rateb-ai-capabilities" aria-label="<?php echo htmlspecialchars(__('ai_capabilities'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($aiCapabilities as $cap): if (!is_array($cap)) continue; ?>
                        <button type="button" class="rateb-ai-capability-chip" data-prompt="<?php echo htmlspecialchars((string) ($cap['prompt'] ?? $cap['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" onclick="return window.ratebAi && window.ratebAi.clickSuggest ? window.ratebAi.clickSuggest(this) : false;"><?php echo htmlspecialchars((string) ($cap['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="rateb-ai-suggestions">
                    <?php
                    $suggestCaps = array_slice($aiCapabilities, 0, 4);
                    if ($suggestCaps === []):
                    ?>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="<?php echo htmlspecialchars(__('ai_suggest_list_pr'), ENT_QUOTES, 'UTF-8'); ?>" onclick="return window.ratebAi && window.ratebAi.clickSuggest ? window.ratebAi.clickSuggest(this) : false;"><?php echo htmlspecialchars(__('ai_suggest_list_pr'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php else: foreach ($suggestCaps as $cap): if (!is_array($cap)) continue; ?>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="<?php echo htmlspecialchars((string) ($cap['prompt'] ?? $cap['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" onclick="return window.ratebAi && window.ratebAi.clickSuggest ? window.ratebAi.clickSuggest(this) : false;"><?php echo htmlspecialchars((string) ($cap['prompt'] ?? $cap['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <form class="rateb-ai-input-form" id="aiInputForm" novalidate onsubmit="event.preventDefault(); return window.ratebAi && window.ratebAi.sendFromInput ? window.ratebAi.sendFromInput() : false;">
        <div class="rateb-ai-input-wrapper" id="aiInputWrapper">
            <textarea
                class="rateb-ai-input"
                id="aiInput"
                name="message"
                placeholder="<?php echo htmlspecialchars(__('ai_input_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"
                rows="1"
                aria-label="<?php echo htmlspecialchars(__('ai_input_label'), ENT_QUOTES, 'UTF-8'); ?>"
                oninput="window.ratebAi && window.ratebAi.syncSendBtn && window.ratebAi.syncSendBtn();"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();window.ratebAi&&window.ratebAi.sendFromInput&&window.ratebAi.sendFromInput();}"
                required></textarea>
            <div class="rateb-ai-input-actions" role="group" aria-label="Voice and send">
                <select class="rateb-ai-voice-language" id="aiVoiceLanguage" aria-label="Voice language" title="Voice language">
                    <option value="ar-SA" selected>AR</option>
                    <option value="en-US">EN</option>
                </select>
                <button type="button" class="rateb-ai-voice-btn" id="aiVoiceModeBtn" aria-pressed="false" aria-label="Voice Mode" title="Voice Mode" onclick="return window.ratebAi && window.ratebAi.toggleVoiceMode ? window.ratebAi.toggleVoiceMode() : false;">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                </button>
                <button type="button" class="rateb-ai-voice-btn rateb-ai-voice-btn--mic" id="aiVoiceInputBtn" aria-label="مايك" title="مايك" onclick="return window.ratebAi && window.ratebAi.toggleVoiceInput ? window.ratebAi.toggleVoiceInput() : false;">
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                    <span>مايك</span>
                </button>
                <button type="button" class="rateb-ai-voice-btn rateb-ai-voice-btn--stop is-hidden" id="aiVoiceStopBtn" hidden aria-label="Stop" title="Stop" onclick="return window.ratebAi && window.ratebAi.stopVoice ? window.ratebAi.stopVoice() : false;">
                    <i class="fa-solid fa-stop" aria-hidden="true"></i>
                </button>
                <button type="button" class="rateb-ai-send-btn" id="aiSendBtn" aria-label="<?php echo htmlspecialchars(__('ai_send'), ENT_QUOTES, 'UTF-8'); ?>" onclick="return window.ratebAi && window.ratebAi.sendFromInput ? window.ratebAi.sendFromInput() : false;">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            </div>
            <div class="rateb-ai-voice-status" id="aiVoiceStatus" aria-live="polite" data-state="ready">Ready</div>
        </div>
        <div class="rateb-ai-voice-rec" id="aiVoiceRecBar" hidden aria-live="polite">
            <div class="rateb-ai-voice-rec__dots" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span>
            </div>
            <div class="rateb-ai-voice-rec__wave" id="aiVoiceWave" aria-hidden="true">
                <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
            </div>
            <div class="rateb-ai-voice-rec__actions">
                <button type="button" class="rateb-ai-voice-rec__btn rateb-ai-voice-rec__btn--cancel" id="aiVoiceRecCancel" aria-label="إلغاء" title="إلغاء" onclick="return window.ratebAi && window.ratebAi.cancelVoiceRecording ? window.ratebAi.cancelVoiceRecording() : false;">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <button type="button" class="rateb-ai-voice-rec__btn rateb-ai-voice-rec__btn--ok" id="aiVoiceRecOk" aria-label="إرسال" title="إرسال" onclick="return window.ratebAi && window.ratebAi.confirmVoiceRecording ? window.ratebAi.confirmVoiceRecording() : false;">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    </form>
        </div>
    </div>
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

html[data-theme="dark"],
html[data-bs-theme="dark"] {
    --ai-bg: var(--rateb-sidebar, #070d18);
    --ai-text: var(--rateb-text, #e2e8f0);
    --ai-text-muted: var(--rateb-text-muted, #94a3b8);
    --ai-border: var(--rateb-border, #2a3a52);
    --ai-primary: var(--rateb-primary, #3b82f6);
    --ai-primary-hover: var(--rateb-primary-dark, #2563eb);
    --ai-user-bg: #123a6c;
    --ai-user-text: #f5faff;
    --ai-assistant-bg: var(--rateb-sidebar, #070d18);
    --ai-assistant-border: var(--rateb-border, #2a3a52);
    --ai-input-bg: var(--rateb-surface-elevated, #1c2940);
    --ai-input-border: var(--rateb-border, #2a3a52);
    --ai-input-focus: var(--rateb-primary, #3b82f6);
    --ai-status-online: #22c55e;
    --ai-suggestion-bg: #0f1720;
    --ai-suggestion-hover: #1d2733;
    --ai-shadow: 0 2px 10px rgba(0,0,0,0.45);
}

html[data-theme="dark"] .rateb-ai-container,
html[data-bs-theme="dark"] .rateb-ai-container {
    background: var(--rateb-sidebar, #070d18);
}

html[data-theme="dark"] .rateb-ai-header,
html[data-theme="dark"] .rateb-ai-messages,
html[data-theme="dark"] .rateb-ai-input-form,
html[data-bs-theme="dark"] .rateb-ai-header,
html[data-bs-theme="dark"] .rateb-ai-messages,
html[data-bs-theme="dark"] .rateb-ai-input-form {
    background: var(--rateb-sidebar, #070d18);
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
    position: relative;
    z-index: 2;
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

.rateb-ai-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-inline-end: 4px;
    text-decoration: none;
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
    position: relative;
    z-index: 3;
}

.rateb-ai-capabilities {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    position: relative;
    z-index: 3;
}

.rateb-ai-capability-chip {
    border: 1px solid var(--ai-border);
    background: transparent;
    color: var(--ai-text-muted);
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    cursor: pointer;
    pointer-events: auto;
    position: relative;
    z-index: 4;
}

.rateb-ai-capability-chip:hover {
    color: var(--ai-text);
    border-color: var(--ai-primary, #0d6efd);
}

.rateb-ai-suggestion-btn {
    padding: 8px 14px;
    background: var(--ai-suggestion-bg);
    border: 1px solid var(--ai-border);
    border-radius: 20px;
    font-size: 13px;
    color: var(--ai-text);
    cursor: pointer;
    pointer-events: auto;
    position: relative;
    z-index: 4;
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
    flex-wrap: wrap;
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
    flex: 1 1 12rem;
    min-width: 0;
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

.rateb-ai-input-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    position: relative;
    z-index: 50;
    pointer-events: auto;
}

.rateb-ai-voice-language {
    min-width: 3.5rem;
    height: 40px;
    padding: 0 .55rem;
    border: 1px solid var(--ai-input-border);
    border-radius: 10px;
    background: var(--ai-assistant-bg);
    color: var(--ai-text);
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
}

.rateb-ai-voice-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--ai-input-border);
    border-radius: 10px;
    background: var(--ai-assistant-bg);
    color: var(--ai-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
    position: relative;
    z-index: 3;
    flex-shrink: 0;
}

.rateb-ai-voice-btn i {
    pointer-events: none;
    font-size: .95rem;
}

.rateb-ai-voice-btn:hover:not(:disabled),
.rateb-ai-voice-btn.is-active {
    border-color: var(--ai-primary);
    color: #fff;
    background: var(--ai-primary);
}

.rateb-ai-voice-btn--mic {
    width: auto;
    min-width: 40px;
    padding: 0 10px;
    gap: 6px;
    background: #1d4ed8;
    color: #fff;
    border-color: #1d4ed8;
    font-weight: 700;
}

.rateb-ai-voice-btn--mic span {
    font-size: 12px;
    pointer-events: none;
}
.rateb-ai-voice-btn[data-listening="1"] {
    border-color: #dc3545;
    background: #dc3545;
    color: #fff;
}

.rateb-ai-voice-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.rateb-ai-voice-btn.is-hidden,
.rateb-ai-voice-btn[hidden] {
    display: none !important;
}

.rateb-ai-voice-status {
    flex: 1 0 100%;
    min-height: 1.1rem;
    margin: 0;
    color: var(--ai-text-muted);
    font-size: .75rem;
    line-height: 1.2;
}

/* Recording capsule — shown while mic is active (matches voice-rec reference UI) */
.rateb-ai-voice-rec {
    display: none;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 52px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #1a1d24;
    border: 1px solid #2a303a;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
}

.rateb-ai-input-form.is-recording .rateb-ai-input-wrapper {
    display: none;
}

.rateb-ai-input-form.is-recording .rateb-ai-voice-rec {
    display: flex !important;
}

.rateb-ai-input-form.is-recording .rateb-ai-voice-rec[hidden] {
    display: flex !important;
}

/* Prevent mic→✕ ghost click while capsule replaces the mic under the cursor */
.rateb-ai-voice-rec.is-arming .rateb-ai-voice-rec__actions {
    pointer-events: none;
}

.rateb-ai-voice-rec__dots {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    padding-inline-start: 4px;
}

.rateb-ai-voice-rec__dots span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #8b93a7;
    opacity: .55;
    animation: rateb-ai-rec-dot 1.2s ease-in-out infinite;
}

.rateb-ai-voice-rec__dots span:nth-child(2) { animation-delay: .1s; }
.rateb-ai-voice-rec__dots span:nth-child(3) { animation-delay: .2s; }
.rateb-ai-voice-rec__dots span:nth-child(4) { animation-delay: .3s; }
.rateb-ai-voice-rec__dots span:nth-child(5) { animation-delay: .4s; }
.rateb-ai-voice-rec__dots span:nth-child(6) { animation-delay: .5s; }
.rateb-ai-voice-rec__dots span:nth-child(7) { animation-delay: .6s; }
.rateb-ai-voice-rec__dots span:nth-child(8) { animation-delay: .7s; }

@keyframes rateb-ai-rec-dot {
    0%, 100% { opacity: .35; transform: scale(.85); }
    50% { opacity: 1; transform: scale(1.15); }
}

.rateb-ai-voice-rec__wave {
    flex: 1 1 auto;
    min-width: 0;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    overflow: hidden;
}

.rateb-ai-voice-rec__wave i {
    display: block;
    width: 3px;
    border-radius: 2px;
    background: #9aa3b5;
    height: 8px;
    animation: rateb-ai-rec-wave 1s ease-in-out infinite;
}

.rateb-ai-voice-rec__wave i:nth-child(odd) { animation-duration: .85s; }
.rateb-ai-voice-rec__wave i:nth-child(3n) { animation-duration: 1.15s; }
.rateb-ai-voice-rec__wave i:nth-child(1) { animation-delay: 0s; }
.rateb-ai-voice-rec__wave i:nth-child(2) { animation-delay: .05s; }
.rateb-ai-voice-rec__wave i:nth-child(3) { animation-delay: .1s; }
.rateb-ai-voice-rec__wave i:nth-child(4) { animation-delay: .15s; }
.rateb-ai-voice-rec__wave i:nth-child(5) { animation-delay: .2s; }
.rateb-ai-voice-rec__wave i:nth-child(6) { animation-delay: .08s; }
.rateb-ai-voice-rec__wave i:nth-child(7) { animation-delay: .18s; }
.rateb-ai-voice-rec__wave i:nth-child(8) { animation-delay: .12s; }
.rateb-ai-voice-rec__wave i:nth-child(9) { animation-delay: .22s; }
.rateb-ai-voice-rec__wave i:nth-child(10) { animation-delay: .04s; }

@keyframes rateb-ai-rec-wave {
    0%, 100% { height: 6px; opacity: .45; }
    50% { height: 22px; opacity: 1; }
}

.rateb-ai-voice-rec__actions {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.rateb-ai-voice-rec__btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #e8eaed;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.rateb-ai-voice-rec__btn:hover {
    background: rgba(255,255,255,.08);
}

.rateb-ai-voice-rec__btn--ok {
    color: #7dd3a0;
}

.rateb-ai-voice-rec__btn--cancel {
    color: #c5cad6;
}

.rateb-ai-voice-status[data-state="listening"] { color: #f87171; }
.rateb-ai-voice-status[data-state="processing"] { color: #fbbf24; }
.rateb-ai-voice-status[data-state="speaking"] { color: #4ade80; }

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
    pointer-events: auto;
    position: relative;
    z-index: 3;
}

.rateb-ai-send-btn:disabled,
.rateb-ai-send-btn.is-empty,
.rateb-ai-send-btn[aria-disabled="true"] {
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

.rateb-ai-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--ai-border);
    flex-wrap: wrap;
}

.rateb-ai-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.rateb-ai-toolbar .btn span {
    margin-inline-start: 4px;
}

.rateb-ai-main {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.rateb-ai-chat-col {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    min-height: 0;
}

.rateb-ai-hist-panel {
    width: 280px;
    max-width: 40%;
    border-inline-end: 1px solid var(--ai-border);
    background: var(--ai-assistant-bg);
    display: flex;
    flex-direction: column;
    padding: 10px;
    gap: 8px;
    overflow: hidden;
}

.rateb-ai-hist-panel[hidden] {
    display: none !important;
}

.rateb-ai-hist-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.rateb-ai-hist-search {
    width: 100%;
    border: 1px solid var(--ai-border);
    border-radius: 8px;
    padding: 8px 10px;
    background: var(--ai-bg);
    color: var(--ai-text);
}

.rateb-ai-hist-list {
    list-style: none;
    margin: 0;
    padding: 0;
    overflow: auto;
    flex: 1;
}

.rateb-ai-hist-item {
    display: flex;
    align-items: stretch;
    gap: 4px;
    margin-bottom: 6px;
}

.rateb-ai-hist-item.is-active .rateb-ai-hist-open {
    border-color: var(--ai-primary);
    background: rgba(26, 95, 180, 0.12);
}

.rateb-ai-hist-open {
    flex: 1;
    text-align: start;
    border: 1px solid var(--ai-border);
    border-radius: 8px;
    background: var(--ai-bg);
    color: var(--ai-text);
    padding: 8px 10px;
    cursor: pointer;
}

.rateb-ai-hist-title {
    display: block;
    font-size: 13px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rateb-ai-hist-meta {
    display: block;
    font-size: 11px;
    color: var(--ai-text-muted);
    margin-top: 2px;
}

.rateb-ai-hist-del {
    border: 0;
    background: transparent;
    color: var(--ai-text-muted);
    cursor: pointer;
    padding: 0 6px;
}

.rateb-ai-hist-empty {
    color: var(--ai-text-muted);
    font-size: 13px;
    padding: 12px 4px;
}

.rateb-ai-message-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.rateb-ai-msg-actions {
    display: flex;
    gap: 4px;
    opacity: 0.35;
}

.rateb-ai-message:hover .rateb-ai-msg-actions {
    opacity: 1;
}

.rateb-ai-msg-action {
    border: 0;
    background: transparent;
    color: var(--ai-text-muted);
    cursor: pointer;
    font-size: 12px;
    padding: 2px 4px;
}

@media (max-width: 900px) {
    .rateb-ai-hist-panel {
        position: absolute;
        inset-inline-start: 0;
        top: 56px;
        bottom: 0;
        z-index: 5;
        max-width: 85%;
        box-shadow: 0 8px 24px rgba(0,0,0,.25);
    }
    .rateb-ai-toolbar .btn span { display: none; }
}
</style>

<script>
/* Re-bind after paint (root exists). Soft-nav strips earlier inline scripts from main. */
try { window.ratebAi && window.ratebAi.bind(); } catch (eBind) {}
try {
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'RATEB_HTML_CACHE_BUST' });
    }
} catch (eSw) {}
</script>
<?php /* History + page JS without defer — soft-nav + SW often skip re-running deferred scripts. */ ?>
<script src="<?php echo htmlspecialchars($aiHistJs, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($aiJs, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
try { window.ratebAi && window.ratebAi.bindVoice && window.ratebAi.bindVoice(); } catch (eVoice) {}
try {
  if (navigator.serviceWorker && navigator.serviceWorker.controller) {
    navigator.serviceWorker.controller.postMessage({ type: 'RATEB_PURGE_AI_ASSETS' });
  }
} catch (ePurge) {}
</script>
<script src="<?php echo htmlspecialchars($aiToolbarJs, ENT_QUOTES, 'UTF-8'); ?>"></script>
