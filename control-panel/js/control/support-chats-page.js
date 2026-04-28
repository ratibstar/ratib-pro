/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/support-chats-page.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/support-chats-page.js`.
 */
(function() {
    var body = document.body;
    var apiBase = (body && body.getAttribute('data-api-base')) || '';
    var status = (body && body.getAttribute('data-status')) || 'open';
    var page = parseInt((body && body.getAttribute('data-page')) || '1', 10);
    var limit = parseInt((body && body.getAttribute('data-limit')) || '20', 10);
    var countryId = parseInt((body && body.getAttribute('data-country-id')) || '0', 10);
    if (isNaN(countryId)) countryId = 0;
    var canReply = (body && body.getAttribute('data-can-reply')) === '1';
    var canBulkSelect = (body && body.getAttribute('data-can-bulk-select')) === '1';
    var canMarkClosed = (body && body.getAttribute('data-can-mark-closed')) === '1';
    var canMarkOpen = (body && body.getAttribute('data-can-mark-open')) === '1';
    var canDeleteChat = (body && body.getAttribute('data-can-delete-chat')) === '1';

    var currentChatId = null;
    var replyModal = null;
    var modalPollTimer = null;
    var MODAL_POLL_MS = 2000;
    var lastModalMessagesSig = '';
    var lastUnreadCount = 0;

    function messagesSignature(msgs) {
        return (msgs || []).map(function(m) { return String(m.id != null ? m.id : ''); }).join(',');
    }

    function isReplyModalOpen() {
        var el = document.getElementById('replyModal');
        if (!el) return false;
        if (el.classList.contains('show')) return true;
        if (document.body && document.body.classList.contains('modal-open')) return true;
        return false;
    }
    var countryScopeMode = 'all';
    var selectAllMode = false;

    function escapeHtml(s) {
        if (s == null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function countryQs() {
        if (countryId === 0) return '';
        return '&country_id=' + encodeURIComponent(countryId);
    }

    function applyCountryFilter(cid) {
        countryId = parseInt(cid, 10);
        if (isNaN(countryId)) countryId = 0;
        page = 1;
        if (body) body.setAttribute('data-country-id', String(countryId));
        try {
            var u = new URL(window.location.href);
            if (countryId === 0) u.searchParams.delete('country_id');
            else u.searchParams.set('country_id', String(countryId));
            u.searchParams.set('page', '1');
            history.replaceState({}, '', u);
        } catch (e) { /* ignore */ }
        var sel = document.getElementById('supportChatCountryFilter');
        if (sel) sel.value = String(countryId);
        loadChatList();
    }

    function populateCountrySelect(countries) {
        var sel = document.getElementById('supportChatCountryFilter');
        if (!sel || !countries) return;
        var keepUnscoped = (countryScopeMode === 'all');
        sel.options[0].text = keepUnscoped ? 'All countries' : 'My countries';
        if (!keepUnscoped && countryId === -1) {
            countryId = 0;
        }
        if (sel.options.length > 1) {
            sel.options[1].style.display = keepUnscoped ? '' : 'none';
        }
        while (sel.options.length > (keepUnscoped ? 2 : 1)) {
            sel.remove(keepUnscoped ? 2 : 1);
        }
        countries.forEach(function(c) {
            var id = parseInt(c.id, 10);
            if (!id) return;
            var opt = document.createElement('option');
            opt.value = String(id);
            opt.textContent = c.name || ('Country #' + id);
            sel.appendChild(opt);
        });
        sel.value = String(countryId);
    }

    function renderCountryChips(rows) {
        var el = document.getElementById('supportChatCountryChips');
        if (!el || !rows || !rows.length) {
            if (el) el.innerHTML = '';
            return;
        }
        el.innerHTML = rows.map(function(r) {
            var cid = parseInt(r.country_id, 10);
            if (isNaN(cid)) cid = 0;
            var filterVal = cid <= 0 ? -1 : cid;
            var n = parseInt(r.unread_chats, 10) || 0;
            if (n <= 0) return '';
            var isActive = countryId === filterVal;
            var active = isActive ? 'btn-primary' : 'btn-outline-info';
            return '<button type="button" class="btn btn-sm ' + active + ' country-chip" data-cid="' + filterVal + '">' +
                escapeHtml(r.country_label || ('#' + cid)) + ' <span class="badge bg-danger">' + n + '</span></button>';
        }).filter(Boolean).join('');
        el.querySelectorAll('.country-chip').forEach(function(btn) {
            btn.addEventListener('click', function() {
                applyCountryFilter(btn.getAttribute('data-cid') || '0');
            });
        });
    }

    function loadChatList() {
        var empty = document.getElementById('chatListEmpty');
        var list = document.getElementById('chatList');
        var pag = document.getElementById('chatPagination');
        if (!empty || !list || !pag) return;

        var url = apiBase + '/support-chats.php?status=' + encodeURIComponent(status) + '&page=' + page + '&limit=' + limit;
        if (countryId !== 0) url += '&country_id=' + encodeURIComponent(countryId);

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    empty.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to load') + '</span>';
                    return;
                }
                countryScopeMode = data.country_scope_mode || 'all';
                populateCountrySelect(data.countries || []);
                renderCountryChips(data.unread_by_country || []);

                var sel = document.getElementById('supportChatCountryFilter');
                if (sel) sel.value = String(countryId);

                var items = data.list || [];
                var unread = data.unread_total || 0;
                var badge = document.getElementById('chatBadge');
                if (badge) {
                    if (unread > 0) {
                        badge.textContent = unread > 99 ? '99+' : unread;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                if (unread > lastUnreadCount && lastUnreadCount > 0) {
                    if ('Notification' in window && Notification.permission === 'granted') {
                        try { new Notification('New Support Chat', { body: 'A visitor requested live support (check all countries).' }); } catch (n) {}
                    }
                }
                lastUnreadCount = unread;

                if (items.length === 0) {
                    empty.style.display = 'block';
                    var hintExtra = '';
                    if (data.support_scope_hint) {
                        hintExtra = '<p class="small text-warning mt-2">' + escapeHtml(data.support_scope_hint) + '</p>';
                    } else if (data.scope_blocks_all) {
                        hintExtra = '<p class="small text-warning mt-2">No country access for this account — select a country in the control panel or contact an admin.</p>';
                    }
                    empty.innerHTML = '<i class="fas fa-inbox fa-2x mb-2"></i><p>No ' + escapeHtml(status) + ' chats' +
                        (countryId !== 0 ? ' for this filter' : '') + '.</p>' +
                        '<p class="small text-muted">Only <strong>live support</strong> chats appear here (visitor must click &quot;Talk to support&quot; or type e.g. &quot;I need to talk to support&quot;). Normal AI replies in the widget are <strong>not</strong> sent to this list.</p>' +
                        hintExtra;
                    list.style.display = 'none';
                    pag.style.display = 'none';
                    updateBulkButtons();
                } else {
                    empty.style.display = 'none';
                    list.style.display = 'block';
                    list.innerHTML = items.map(function(c) {
                        var cId = Math.max(1, parseInt(c.id, 10) || 0);
                        var unreadCnt = Math.max(0, parseInt(c.unread_count || 0));
                        var last = escapeHtml((c.last_message || '').substring(0, 60));
                        var created = (c.created_at || '').substring(0, 16).replace('T', ' ');
                        var src = escapeHtml(c.source_page || 'Unknown');
                        var email = escapeHtml(c.visitor_email || '');
                        var origin = '';
                        if (c.country_name || c.agency_name) {
                            origin = '<div class="small text-info">' + escapeHtml([c.country_name, c.agency_name].filter(Boolean).join(' · ')) + '</div>';
                        } else if (c.country_id || c.agency_id) {
                            origin = '<div class="small text-muted">Country #' + escapeHtml(String(c.country_id || '—')) + ' · Agency #' + escapeHtml(String(c.agency_id || '—')) + '</div>';
                        }
                        var statusVal = (c.status === 'closed' ? 'closed' : 'open');
                        var rowCheck = canBulkSelect
                            ? ' <input type="checkbox" class="chat-row-check ms-2" data-id="' + cId + '" aria-label="Select chat #' + cId + '">'
                            : '';
                        return '<div class="chat-item ' + (unreadCnt > 0 ? 'unread' : '') + '" data-id="' + cId + '">' +
                            '<div class="chat-preview">' +
                            '<strong>#' + cId + '</strong> ' + src + (email ? ' — ' + email : '') + origin +
                            '<div class="last-msg">' + (last || '(No messages)') + '</div></div>' +
                            '<span class="chat-meta">' + escapeHtml(created) + '</span>' +
                            '<span class="status-badge ' + statusVal + '">' + statusVal + '</span>' +
                            (unreadCnt > 0 ? ' <span class="chat-badge">' + unreadCnt + '</span>' : '') +
                            rowCheck +
                            '</div>';
                    }).join('');
                    list.querySelectorAll('.chat-item').forEach(function(el) {
                        var cb = el.querySelector('.chat-row-check');
                        if (cb && selectAllMode) cb.checked = true;
                        var id = parseInt(el.getAttribute('data-id'), 10);
                        if (id >= 1) {
                            el.addEventListener('click', function(e) {
                                if (e.target && e.target.classList && e.target.classList.contains('chat-row-check')) return;
                                openChat(id);
                            });
                        }
                    });
                    updateBulkButtons();

                    var pg = data.pagination || {};
                    if (pg.pages > 1) {
                        pag.style.display = 'block';
                        var html = 'Page ' + pg.page + ' of ' + pg.pages + ' ';
                        var baseQs = 'control=1&embedded=1&status=' + encodeURIComponent(status) + '&limit=' + limit;
                        if (countryId !== 0) baseQs += '&country_id=' + encodeURIComponent(countryId);
                        if (pg.page > 1) html += '<a href="?' + baseQs + '&page=' + (pg.page - 1) + '" class="btn btn-sm btn-outline-secondary me-1">Previous</a>';
                        if (pg.page < pg.pages) html += '<a href="?' + baseQs + '&page=' + (pg.page + 1) + '" class="btn btn-sm btn-outline-secondary">Next</a>';
                        pag.innerHTML = html;
                    } else {
                        pag.style.display = 'none';
                    }
                }
            })
            .catch(function() {
                var el = document.getElementById('chatListEmpty');
                if (el) el.innerHTML = '<span class="text-danger">Failed to load chats.</span>';
            });
    }

    function selectedIds() {
        return Array.from(document.querySelectorAll('.chat-row-check:checked'))
            .map(function(cb) { return parseInt(cb.getAttribute('data-id'), 10); })
            .filter(function(v) { return v > 0; });
    }

    function updateBulkButtons() {
        var count = canBulkSelect ? selectedIds().length : 0;
        if (canMarkClosed) {
            var b1 = document.getElementById('btnBulkCloseChats');
            if (b1) b1.disabled = count === 0;
        }
        if (canMarkOpen) {
            var b2 = document.getElementById('btnBulkOpenChats');
            if (b2) b2.disabled = count === 0;
        }
        if (canDeleteChat) {
            var b3 = document.getElementById('btnBulkDeleteChats');
            if (b3) b3.disabled = count === 0;
        }
    }

    function bulkPatch(action, ids) {
        return fetch(apiBase + '/support-chats.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: action, ids: ids })
        }).then(function(r) { return r.json(); });
    }

    function bulkDelete(ids) {
        return fetch(apiBase + '/support-chats.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ids: ids })
        }).then(function(r) { return r.json(); });
    }

    var countrySel = document.getElementById('supportChatCountryFilter');
    if (countrySel) {
        countrySel.addEventListener('change', function() {
            applyCountryFilter(countrySel.value);
        });
        countrySel.value = String(countryId);
    }

    document.addEventListener('change', function(e) {
        if (!canBulkSelect) return;
        if (e.target && e.target.classList && e.target.classList.contains('chat-row-check')) {
            updateBulkButtons();
        }
    });

    var btnSelectAll = document.getElementById('btnSelectAllChats');
    if (btnSelectAll && canBulkSelect) {
        btnSelectAll.addEventListener('click', function() {
            selectAllMode = !selectAllMode;
            document.querySelectorAll('.chat-row-check').forEach(function(cb) { cb.checked = selectAllMode; });
            btnSelectAll.innerHTML = selectAllMode
                ? '<i class="far fa-minus-square me-1"></i>Unselect All'
                : '<i class="far fa-check-square me-1"></i>Select All';
            updateBulkButtons();
        });
    }

    var btnBulkClose = document.getElementById('btnBulkCloseChats');
    if (btnBulkClose && canMarkClosed) {
        btnBulkClose.addEventListener('click', function() {
            var ids = canBulkSelect ? selectedIds() : [];
            if (!ids.length) return;
            bulkPatch('close', ids).then(function(data) {
                if (!data.success) { alert(data.message || 'Bulk close failed'); return; }
                loadChatList();
            }).catch(function() { alert('Bulk close failed'); });
        });
    }

    var btnBulkOpen = document.getElementById('btnBulkOpenChats');
    if (btnBulkOpen && canMarkOpen) {
        btnBulkOpen.addEventListener('click', function() {
            var ids = canBulkSelect ? selectedIds() : [];
            if (!ids.length) return;
            bulkPatch('open', ids).then(function(data) {
                if (!data.success) { alert(data.message || 'Bulk open failed'); return; }
                loadChatList();
            }).catch(function() { alert('Bulk open failed'); });
        });
    }

    var btnBulkDelete = document.getElementById('btnBulkDeleteChats');
    if (btnBulkDelete && canDeleteChat) {
        btnBulkDelete.addEventListener('click', function() {
            var ids = canBulkSelect ? selectedIds() : [];
            if (!ids.length) return;
            if (!confirm('Delete selected chats permanently?')) return;
            bulkDelete(ids).then(function(data) {
                if (!data.success) { alert(data.message || 'Bulk delete failed'); return; }
                loadChatList();
            }).catch(function() { alert('Bulk delete failed'); });
        });
    }

    function renderModalMessageHtml(m) {
        var cls = m.sender === 'user' ? 'user' : 'support';
        var safe = escapeHtml(m.message || '').replace(/\n/g, '<br>');
        var mid = Math.max(0, parseInt(m.id, 10) || 0);
        return '<div class="chat-msg ' + cls + '" data-msg-id="' + mid + '"><div>' + safe + '</div><div class="msg-time">' + (m.created_at || '').substring(0, 19).replace('T', ' ') + '</div></div>';
    }

    /** mode 'replace' = full load; 'append' = only new ids (for live polling while modal open) */
    function applyMessagesToModal(msgs, mode) {
        var modalMsgEl = document.getElementById('modalMessages');
        if (!modalMsgEl) return;
        msgs = (msgs || []).slice().sort(function(a, b) {
            return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0);
        });
        if (mode === 'replace') {
            modalMsgEl.innerHTML = msgs.map(renderModalMessageHtml).join('');
            modalMsgEl.scrollTop = modalMsgEl.scrollHeight;
            return false;
        }
        var maxId = 0;
        modalMsgEl.querySelectorAll('.chat-msg[data-msg-id]').forEach(function(n) {
            var id = parseInt(n.getAttribute('data-msg-id'), 10) || 0;
            if (id > maxId) maxId = id;
        });
        var added = false;
        msgs.forEach(function(m) {
            var mid = parseInt(m.id, 10) || 0;
            if (mid > maxId) {
                modalMsgEl.insertAdjacentHTML('beforeend', renderModalMessageHtml(m));
                maxId = mid;
                added = true;
            }
        });
        if (added) {
            modalMsgEl.scrollTop = modalMsgEl.scrollHeight;
        }
        return added;
    }

    function stopModalPoll() {
        if (modalPollTimer) {
            clearInterval(modalPollTimer);
            modalPollTimer = null;
        }
    }

    function startModalPoll() {
        stopModalPoll();
        if (!currentChatId) return;
        function tick() {
            if (!currentChatId || !isReplyModalOpen()) {
                stopModalPoll();
                return;
            }
            refreshOpenChatModal();
        }
        tick();
        modalPollTimer = setInterval(tick, MODAL_POLL_MS);
    }

    /** Refresh modal transcript while it is open (full replace when server has new rows). */
    function refreshOpenChatModal() {
        if (!currentChatId || !isReplyModalOpen()) return;
        fetch(apiBase + '/support-chats.php?chat_id=' + currentChatId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !Array.isArray(data.messages)) return;
                var msgs = data.messages;
                var sig = messagesSignature(msgs);
                if (sig === lastModalMessagesSig) return;
                lastModalMessagesSig = sig;
                applyMessagesToModal(msgs, 'replace');
                loadChatList();
            })
            .catch(function() { /* ignore transient poll errors */ });
    }

    function openChat(chatId) {
        chatId = parseInt(chatId, 10);
        if (!chatId || chatId < 1) return;
        currentChatId = chatId;
        var modalChatId = document.getElementById('modalChatId');
        var modalSource = document.getElementById('modalSource');
        var modalMessages = document.getElementById('modalMessages');
        var replyText = document.getElementById('replyText');
        if (modalChatId) modalChatId.textContent = '#' + chatId;
        if (modalSource) modalSource.textContent = 'Loading...';
        if (modalMessages) modalMessages.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';
        if (replyText) replyText.value = '';

        fetch(apiBase + '/support-chats.php?chat_id=' + chatId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var modalMsgEl = document.getElementById('modalMessages');
                var modalSrcEl = document.getElementById('modalSource');
                if (!data.success) {
                    if (modalMsgEl) modalMsgEl.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to load') + '</span>';
                    if (replyModal) replyModal.show();
                    return;
                }
                var msgs = data.messages || [];
                if (modalSrcEl) modalSrcEl.textContent = 'Chat #' + chatId;
                applyMessagesToModal(msgs, 'replace');
                lastModalMessagesSig = messagesSignature(msgs);
                if (replyModal) {
                    replyModal.show();
                } else {
                    startModalPoll();
                }
            })
            .catch(function() {
                var modalMsgEl = document.getElementById('modalMessages');
                if (modalMsgEl) modalMsgEl.innerHTML = '<span class="text-danger">Failed to load.</span>';
                if (replyModal) replyModal.show();
            });
    }

    var btnSendReply = document.getElementById('btnSendReply');
    var btnCloseChat = document.getElementById('btnCloseChat');
    var replyModalEl = document.getElementById('replyModal');
    if (btnSendReply) btnSendReply.addEventListener('click', function() {
        if (!canReply) return;
        var replyTextEl = document.getElementById('replyText');
        var text = replyTextEl ? replyTextEl.value.trim() : '';
        if (!text || !currentChatId) return;
        fetch(apiBase + '/support-chats.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ chat_id: currentChatId, message: text })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                if (replyTextEl) replyTextEl.value = '';
                openChat(currentChatId);
                loadChatList();
            } else {
                alert(data.message || 'Failed to send');
            }
        }).catch(function() { alert('Failed to send'); });
    });
    if (btnCloseChat) btnCloseChat.addEventListener('click', function() {
        if (!canMarkClosed) return;
        if (!currentChatId) return;
        if (!confirm('Mark this chat as closed?')) return;
        fetch(apiBase + '/support-chats.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ chat_id: currentChatId, action: 'close' })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                if (replyModal) replyModal.hide();
                loadChatList();
            } else {
                alert(data.message || 'Failed to close chat');
            }
        }).catch(function() { alert('Failed to close chat'); });
    });

    var modalDeleteBtn = document.getElementById('btnDeleteChat');
    if (modalDeleteBtn) modalDeleteBtn.addEventListener('click', function() {
        if (!canDeleteChat || !currentChatId) return;
        if (!confirm('Delete this chat permanently?')) return;
        bulkDelete([currentChatId]).then(function(data) {
            if (!data.success) { alert(data.message || 'Delete failed'); return; }
            if (replyModal) replyModal.hide();
            loadChatList();
        }).catch(function() { alert('Delete failed'); });
    });

    replyModal = (replyModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) ? new bootstrap.Modal(replyModalEl) : null;
    if (replyModalEl) {
        replyModalEl.addEventListener('shown.bs.modal', function() {
            if (currentChatId) {
                startModalPoll();
            }
        });
        replyModalEl.addEventListener('hidden.bs.modal', function() {
            stopModalPoll();
            lastModalMessagesSig = '';
            currentChatId = null;
        });
    }
    loadChatList();
    setInterval(loadChatList, 8000);

    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
})();
