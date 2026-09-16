/**
 * RATEB AI — local conversation history (search / clear / edit / new chat).
 * Persists per company+user in localStorage. Soft-nav safe.
 */
(function (root, doc) {
    'use strict';

    var MAX_SESSIONS = 40;
    var MAX_MSGS = 80;

    function t(rootEl, key, fallback) {
        if (!rootEl) return fallback;
        return rootEl.getAttribute('data-i18n-' + key) || fallback;
    }

    function uid() {
        return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    }

    function storageKey(rootEl) {
        var c = (rootEl && rootEl.getAttribute('data-company-id')) || '0';
        var u = (rootEl && rootEl.getAttribute('data-user-id')) || '0';
        return 'rateb_ai_chats_v1_' + c + '_' + u;
    }

    function loadState(rootEl) {
        try {
            var raw = localStorage.getItem(storageKey(rootEl));
            var data = raw ? JSON.parse(raw) : null;
            if (!data || !Array.isArray(data.sessions)) {
                return { currentId: null, sessions: [] };
            }
            return data;
        } catch (e) {
            return { currentId: null, sessions: [] };
        }
    }

    function saveState(rootEl, state) {
        try {
            if (state.sessions.length > MAX_SESSIONS) {
                state.sessions = state.sessions
                    .slice()
                    .sort(function (a, b) { return (b.updatedAt || '').localeCompare(a.updatedAt || ''); })
                    .slice(0, MAX_SESSIONS);
            }
            localStorage.setItem(storageKey(rootEl), JSON.stringify(state));
        } catch (e) { /* quota / private mode */ }
    }

    function ensureCurrent(rootEl, state) {
        var cur = null;
        if (state.currentId) {
            for (var i = 0; i < state.sessions.length; i++) {
                if (state.sessions[i].id === state.currentId) {
                    cur = state.sessions[i];
                    break;
                }
            }
        }
        if (!cur) {
            cur = { id: uid(), title: '', updatedAt: new Date().toISOString(), messages: [] };
            state.sessions.unshift(cur);
            state.currentId = cur.id;
            saveState(rootEl, state);
        }
        return cur;
    }

    function esc(text) {
        return String(text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
    }

    function formatWhen(iso) {
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) return '';
            return d.toLocaleString();
        } catch (e) {
            return '';
        }
    }

    var api = {
        root: null,
        state: null,

        init: function () {
            this.root = doc.getElementById('ratebAiRoot');
            if (!this.root) return;
            this.state = loadState(this.root);
            ensureCurrent(this.root, this.state);
            this.bindUi();
            this.renderSessionList();
            this.restoreCurrentIntoDom(false);
        },

        bindUi: function () {
            var self = this;
            var btnNew = doc.getElementById('aiHistNew');
            var btnToggle = doc.getElementById('aiHistToggle');
            var btnClear = doc.getElementById('aiHistClear');
            var btnClearAll = doc.getElementById('aiHistClearAll');
            var search = doc.getElementById('aiHistSearch');
            var panel = doc.getElementById('aiHistPanel');

            if (btnNew && btnNew.getAttribute('data-bound') !== '1') {
                btnNew.setAttribute('data-bound', '1');
                btnNew.addEventListener('click', function () { self.newChat(); });
            }
            if (btnToggle && btnToggle.getAttribute('data-bound') !== '1') {
                btnToggle.setAttribute('data-bound', '1');
                btnToggle.addEventListener('click', function () {
                    if (!panel) return;
                    panel.hidden = !panel.hidden;
                    btnToggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                    if (!panel.hidden) self.renderSessionList();
                });
            }
            if (btnClear && btnClear.getAttribute('data-bound') !== '1') {
                btnClear.setAttribute('data-bound', '1');
                btnClear.addEventListener('click', function () { self.clearCurrent(); });
            }
            if (btnClearAll && btnClearAll.getAttribute('data-bound') !== '1') {
                btnClearAll.setAttribute('data-bound', '1');
                btnClearAll.addEventListener('click', function () { self.clearAll(); });
            }
            if (search && search.getAttribute('data-bound') !== '1') {
                search.setAttribute('data-bound', '1');
                search.addEventListener('input', function () { self.renderSessionList(); });
            }

            var box = doc.getElementById('aiMessages');
            if (box && box.getAttribute('data-hist-click') !== '1') {
                box.setAttribute('data-hist-click', '1');
                box.addEventListener('click', function (e) {
                    var editBtn = e.target && e.target.closest ? e.target.closest('[data-ai-edit]') : null;
                    var delBtn = e.target && e.target.closest ? e.target.closest('[data-ai-delete]') : null;
                    if (editBtn) {
                        e.preventDefault();
                        self.editMessage(parseInt(editBtn.getAttribute('data-ai-edit'), 10));
                        return;
                    }
                    if (delBtn) {
                        e.preventDefault();
                        self.deleteMessage(parseInt(delBtn.getAttribute('data-ai-delete'), 10));
                    }
                });
            }
        },

        getApiHistory: function () {
            var cur = ensureCurrent(this.root, this.state);
            return (cur.messages || []).slice(-16).map(function (m) {
                return { role: m.role, content: m.content };
            });
        },

        appendMessage: function (role, content) {
            if (!this.root) this.init();
            var cur = ensureCurrent(this.root, this.state);
            var text = String(content || '').trim();
            if (!text) return;
            cur.messages.push({ role: role, content: text, at: new Date().toISOString() });
            if (cur.messages.length > MAX_MSGS) {
                cur.messages = cur.messages.slice(cur.messages.length - MAX_MSGS);
            }
            if (role === 'user' && !cur.title) {
                cur.title = text.length > 48 ? text.slice(0, 48) + '…' : text;
            }
            cur.updatedAt = new Date().toISOString();
            saveState(this.root, this.state);
            this.renderSessionList();
            this.decorateDomActions();
        },

        replaceMessagesFromDom: function () {
            /* After edit/delete we rebuild from state; this syncs if needed. */
            saveState(this.root, this.state);
        },

        newChat: function () {
            if (!this.root) return;
            var cur = {
                id: uid(),
                title: t(this.root, 'new-chat', 'New chat'),
                updatedAt: new Date().toISOString(),
                messages: []
            };
            this.state.sessions.unshift(cur);
            this.state.currentId = cur.id;
            saveState(this.root, this.state);
            this.clearDom(true);
            this.renderSessionList();
            try {
                var input = doc.getElementById('aiInput');
                if (input) input.focus();
            } catch (e) {}
        },

        openSession: function (id) {
            this.state.currentId = id;
            saveState(this.root, this.state);
            this.restoreCurrentIntoDom(true);
            this.renderSessionList();
            var panel = doc.getElementById('aiHistPanel');
            if (panel && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
                panel.hidden = true;
            }
        },

        clearCurrent: function () {
            var msg = t(this.root, 'confirm-clear', 'Clear this chat?');
            if (!window.confirm(msg)) return;
            var cur = ensureCurrent(this.root, this.state);
            cur.messages = [];
            cur.title = t(this.root, 'new-chat', 'New chat');
            cur.updatedAt = new Date().toISOString();
            saveState(this.root, this.state);
            this.clearDom(true);
            this.renderSessionList();
        },

        clearAll: function () {
            var msg = t(this.root, 'confirm-clear-all', 'Delete all saved chats?');
            if (!window.confirm(msg)) return;
            this.state = { currentId: null, sessions: [] };
            saveState(this.root, this.state);
            this.newChat();
        },

        deleteSession: function (id) {
            this.state.sessions = this.state.sessions.filter(function (s) { return s.id !== id; });
            if (this.state.currentId === id) {
                this.state.currentId = this.state.sessions[0] ? this.state.sessions[0].id : null;
                if (!this.state.currentId) {
                    this.newChat();
                    return;
                }
                this.restoreCurrentIntoDom(true);
            }
            saveState(this.root, this.state);
            this.renderSessionList();
        },

        ensureLive: function () {
            var live = doc.getElementById('ratebAiRoot');
            if (!live) return false;
            if (this.root !== live || !this.state) this.init();
            return !!(this.root && this.state);
        },

        editMessage: function (index) {
            if (this._acting) return;
            if (!this.ensureLive()) return;
            var cur = ensureCurrent(this.root, this.state);
            index = parseInt(index, 10);
            if (isNaN(index) || !cur.messages[index] || cur.messages[index].role !== 'user') return;
            this._acting = true;
            try {
                var text = cur.messages[index].content;
                cur.messages = cur.messages.slice(0, index);
                cur.updatedAt = new Date().toISOString();
                saveState(this.root, this.state);
                this.restoreCurrentIntoDom(true);
                var input = doc.getElementById('aiInput');
                if (input) {
                    input.value = text;
                    input.focus();
                    if (root.ratebAi && root.ratebAi.syncSendBtn) root.ratebAi.syncSendBtn();
                }
            } finally {
                this._acting = false;
            }
        },

        deleteMessage: function (index) {
            if (this._acting) return;
            if (!this.ensureLive()) return;
            var cur = ensureCurrent(this.root, this.state);
            index = parseInt(index, 10);
            if (isNaN(index) || !cur.messages[index]) return;
            this._acting = true;
            try {
                cur.messages.splice(index, 1);
                cur.updatedAt = new Date().toISOString();
                saveState(this.root, this.state);
                this.restoreCurrentIntoDom(true);
            } finally {
                this._acting = false;
            }
        },

        clearDom: function (showWelcome) {
            var box = doc.getElementById('aiMessages');
            if (!box) return;
            var welcome = doc.getElementById('aiWelcome');
            box.querySelectorAll('.rateb-ai-message').forEach(function (el) {
                el.parentNode && el.parentNode.removeChild(el);
            });
            if (welcome) {
                welcome.style.display = showWelcome ? '' : 'none';
                if (showWelcome && !welcome.parentNode) {
                    box.insertBefore(welcome, box.firstChild);
                }
            }
        },

        restoreCurrentIntoDom: function (force) {
            var cur = ensureCurrent(this.root, this.state);
            var box = doc.getElementById('aiMessages');
            if (!box) return;
            var existing = box.querySelectorAll('.rateb-ai-message:not(.rateb-ai-typing-container)');
            if (!force && existing.length > 0) {
                this.decorateDomActions();
                return;
            }
            this.clearDom(cur.messages.length === 0);
            var welcome = doc.getElementById('aiWelcome');
            if (cur.messages.length && welcome) welcome.style.display = 'none';
            for (var i = 0; i < cur.messages.length; i++) {
                this.renderMessageEl(cur.messages[i].role, cur.messages[i].content, i);
            }
            box.scrollTop = box.scrollHeight;
        },

        renderMessageEl: function (role, content, index) {
            var box = doc.getElementById('aiMessages');
            if (!box) return;
            var div = doc.createElement('div');
            div.className = 'rateb-ai-message ' + role;
            div.setAttribute('data-msg-index', String(index));
            var actions = '';
            if (role === 'user') {
                actions = '<div class="rateb-ai-msg-actions">' +
                    '<button type="button" class="rateb-ai-msg-action" data-ai-edit="' + index + '" title="' + esc(t(this.root, 'edit', 'Edit')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.editMessage(' + index + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="rateb-ai-msg-action" data-ai-delete="' + index + '" title="' + esc(t(this.root, 'delete', 'Delete')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.deleteMessage(' + index + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-trash"></i></button>' +
                    '</div>';
            } else {
                actions = '<div class="rateb-ai-msg-actions">' +
                    '<button type="button" class="rateb-ai-msg-action" data-ai-delete="' + index + '" title="' + esc(t(this.root, 'delete', 'Delete')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.deleteMessage(' + index + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-trash"></i></button>' +
                    '</div>';
            }
            div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-' +
                (role === 'user' ? 'user' : 'robot') + '"></i></div>' +
                '<div class="rateb-ai-message-body">' +
                '<div class="rateb-ai-message-content">' + esc(content) + '</div>' +
                actions +
                '</div>';
            box.appendChild(div);
        },

        decorateDomActions: function () {
            /* Re-index action buttons after live addMsg from chat send. */
            var cur = ensureCurrent(this.root, this.state);
            var box = doc.getElementById('aiMessages');
            if (!box) return;
            var nodes = box.querySelectorAll('.rateb-ai-message:not(.rateb-ai-typing-container):not(.rateb-ai-confirm-chrome)');
            var mi = 0;
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                var role = el.classList.contains('user') ? 'user' : 'assistant';
                if (!cur.messages[mi] || cur.messages[mi].role !== role) {
                    /* fall through — still assign index sequentially from state length mismatch */
                }
                var idx = mi;
                mi++;
                el.setAttribute('data-msg-index', String(idx));
                var body = el.querySelector('.rateb-ai-message-body');
                if (!body) {
                    var content = el.querySelector('.rateb-ai-message-content');
                    if (!content) continue;
                    body = doc.createElement('div');
                    body.className = 'rateb-ai-message-body';
                    content.parentNode.insertBefore(body, content);
                    body.appendChild(content);
                }
                var actions = body.querySelector('.rateb-ai-msg-actions');
                if (!actions) {
                    actions = doc.createElement('div');
                    actions.className = 'rateb-ai-msg-actions';
                    body.appendChild(actions);
                }
                if (role === 'user') {
                    actions.innerHTML =
                        '<button type="button" class="rateb-ai-msg-action" data-ai-edit="' + idx + '" title="' + esc(t(this.root, 'edit', 'Edit')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.editMessage(' + idx + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-pen"></i></button>' +
                        '<button type="button" class="rateb-ai-msg-action" data-ai-delete="' + idx + '" title="' + esc(t(this.root, 'delete', 'Delete')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.deleteMessage(' + idx + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    actions.innerHTML =
                        '<button type="button" class="rateb-ai-msg-action" data-ai-delete="' + idx + '" title="' + esc(t(this.root, 'delete', 'Delete')) + '" onclick="try{var H=window.RatebAiHistory;if(H){H.deleteMessage(' + idx + ');}return false;}catch(e){return false;}"><i class="fa-solid fa-trash"></i></button>';
                }
            }
        },

        renderSessionList: function () {
            var list = doc.getElementById('aiHistList');
            if (!list || !this.root) return;
            var q = '';
            var search = doc.getElementById('aiHistSearch');
            if (search) q = String(search.value || '').trim().toLowerCase();
            var sessions = this.state.sessions.slice().sort(function (a, b) {
                return (b.updatedAt || '').localeCompare(a.updatedAt || '');
            });
            list.innerHTML = '';
            var self = this;
            var shown = 0;
            sessions.forEach(function (s) {
                var hay = ((s.title || '') + ' ' + (s.messages || []).map(function (m) { return m.content; }).join(' ')).toLowerCase();
                if (q && hay.indexOf(q) === -1) return;
                shown++;
                var li = doc.createElement('li');
                li.className = 'rateb-ai-hist-item' + (s.id === self.state.currentId ? ' is-active' : '');
                li.innerHTML =
                    '<button type="button" class="rateb-ai-hist-open" data-id="' + esc(s.id) + '">' +
                    '<span class="rateb-ai-hist-title">' + esc(s.title || t(self.root, 'new-chat', 'New chat')) + '</span>' +
                    '<span class="rateb-ai-hist-meta">' + esc(formatWhen(s.updatedAt)) + ' · ' + (s.messages ? s.messages.length : 0) + '</span>' +
                    '</button>' +
                    '<button type="button" class="rateb-ai-hist-del" data-del="' + esc(s.id) + '" title="' + esc(t(self.root, 'delete', 'Delete')) + '"><i class="fa-solid fa-xmark"></i></button>';
                list.appendChild(li);
            });
            if (!shown) {
                var empty = doc.createElement('li');
                empty.className = 'rateb-ai-hist-empty';
                empty.textContent = t(this.root, 'hist-empty', 'No chats yet');
                list.appendChild(empty);
            }
            if (list.getAttribute('data-bound') !== '1') {
                list.setAttribute('data-bound', '1');
                list.addEventListener('click', function (e) {
                    var open = e.target.closest ? e.target.closest('.rateb-ai-hist-open') : null;
                    var del = e.target.closest ? e.target.closest('.rateb-ai-hist-del') : null;
                    if (del) {
                        e.preventDefault();
                        self.deleteSession(del.getAttribute('data-del'));
                        return;
                    }
                    if (open) {
                        e.preventDefault();
                        self.openSession(open.getAttribute('data-id'));
                    }
                });
            }
        }
    };

    root.RatebAiHistory = api;

    /* Soft-nav safe: one document-level listener — works even when deferred scripts
     * are not re-executed and per-button data-bound flags go stale.
     * V2 adds message edit/delete; bump key so sessions that already bound V1 re-bind. */
    if (!root.__ratebAiHistClickV2) {
        root.__ratebAiHistClickV2 = true;
        doc.addEventListener('click', function (e) {
            if (!doc.getElementById('ratebAiRoot')) return;

            /* Message edit / delete (pen & trash on bubbles) */
            var editBtn = e.target && e.target.closest ? e.target.closest('[data-ai-edit]') : null;
            var delBtn = e.target && e.target.closest ? e.target.closest('[data-ai-delete]') : null;
            if (editBtn || delBtn) {
                e.preventDefault();
                try { e.stopPropagation(); } catch (eStopMsg) {}
                api.ensureLive();
                if (editBtn) {
                    var editIdx = parseInt(editBtn.getAttribute('data-ai-edit'), 10);
                    if (isNaN(editIdx)) {
                        var msgEl = editBtn.closest('.rateb-ai-message');
                        editIdx = msgEl ? parseInt(msgEl.getAttribute('data-msg-index'), 10) : NaN;
                    }
                    if (!isNaN(editIdx)) api.editMessage(editIdx);
                    return;
                }
                if (delBtn) {
                    var delIdx = parseInt(delBtn.getAttribute('data-ai-delete'), 10);
                    if (isNaN(delIdx)) {
                        var msgElDel = delBtn.closest('.rateb-ai-message');
                        delIdx = msgElDel ? parseInt(msgElDel.getAttribute('data-msg-index'), 10) : NaN;
                    }
                    if (!isNaN(delIdx)) api.deleteMessage(delIdx);
                    return;
                }
            }

            var t = e.target && e.target.closest ? e.target.closest(
                '#aiHistNew, #aiHistToggle, #aiHistClear, #aiHistClearAll'
            ) : null;
            if (!t) return;
            e.preventDefault();
            try { e.stopPropagation(); } catch (eStop) {}
            api.ensureLive();
            if (t.id === 'aiHistNew') {
                api.newChat();
                return;
            }
            if (t.id === 'aiHistToggle') {
                var panel = doc.getElementById('aiHistPanel');
                var btnToggle = doc.getElementById('aiHistToggle');
                if (!panel) return;
                panel.hidden = !panel.hidden;
                if (btnToggle) btnToggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                if (!panel.hidden) api.renderSessionList();
                return;
            }
            if (t.id === 'aiHistClear') {
                api.clearCurrent();
                return;
            }
            if (t.id === 'aiHistClearAll') {
                api.clearAll();
            }
        }, true);
    }

    function boot() {
        if (!doc.getElementById('ratebAiRoot')) return;
        /* Soft-nav replaces DOM — always allow re-bind on the new buttons. */
        ['aiHistNew', 'aiHistToggle', 'aiHistClear', 'aiHistClearAll', 'aiHistSearch'].forEach(function (id) {
            var el = doc.getElementById(id);
            if (el) el.removeAttribute('data-bound');
        });
        var list = doc.getElementById('aiHistList');
        if (list) list.removeAttribute('data-bound');
        var box = doc.getElementById('aiMessages');
        if (box) box.removeAttribute('data-hist-click');
        api.init();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    doc.addEventListener('rateb:nav:afterEnter', boot);
    doc.addEventListener('rateb:soft-nav:afterEnter', boot);
})(window, document);
