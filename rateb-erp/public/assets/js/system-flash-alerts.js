(function () {
    'use strict';

    if (window.__RATEB_SYSTEM_FLASH__) {
        return;
    }
    window.__RATEB_SYSTEM_FLASH__ = 1;

    var POLL_MS = 1500;
    var LIST_SOFT_REFRESH_MS = 4000;
    var pollTimer = null;
    var lastAlertCount = -1;
    var lastActivityToken = '';
    var lastTicketToken = '';
    var lastListRefreshAt = 0;
    var seenNotifIds = {};
    var pollInFlight = false;

    try {
        var raw = sessionStorage.getItem('rateb_seen_notif_ids');
        if (raw) {
            seenNotifIds = JSON.parse(raw) || {};
        }
    } catch (eSeen) {
        seenNotifIds = {};
    }

    function stackEl() {
        return document.querySelector('[data-rateb-system-flash="1"]');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function nl2br(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    function renderPreviewItems(alert) {
        var items = Array.isArray(alert.preview_items) ? alert.preview_items : [];
        if (!items.length) {
            return alert.message
                ? '<div class="rateb-system-flash-alert__message">' + nl2br(alert.message) + '</div>'
                : '';
        }
        var html = '<ul class="rateb-system-flash-tickets">';
        html += '<li class="rateb-system-flash-tickets__head" aria-hidden="true">'
            + '<span class="rateb-system-flash-tickets__no">' + escapeHtml(window.__RATEB_FLASH_COL_TICKET__ || 'Ticket') + '</span>'
            + '<span class="rateb-system-flash-tickets__company">' + escapeHtml(window.__RATEB_FLASH_COL_COMPANY__ || 'Company') + '</span>'
            + '<span class="rateb-system-flash-tickets__subject">' + escapeHtml(window.__RATEB_FLASH_COL_SUBJECT__ || 'Subject') + '</span>'
            + '</li>';
        items.forEach(function (row) {
            html += '<li class="rateb-system-flash-tickets__item">'
                + '<span class="rateb-system-flash-tickets__no">' + escapeHtml(row.ticket_no || '') + '</span>'
                + '<span class="rateb-system-flash-tickets__company">' + escapeHtml(row.company || '') + '</span>'
                + '<span class="rateb-system-flash-tickets__subject">' + escapeHtml(row.subject || '') + '</span>'
                + '</li>';
        });
        var more = parseInt(alert.more_count, 10) || 0;
        if (more > 0) {
            html += '<li class="rateb-system-flash-tickets__more">+' + escapeHtml(String(more)) + '</li>';
        }
        html += '</ul>';
        return html;
    }

    function renderAlert(alert) {
        if (!alert || !alert.key) {
            return '';
        }
        var severity = alert.severity || 'info';
        var pulse = alert.pulse ? ' rateb-system-flash-alert--pulse' : '';
        var icon = alert.icon || 'fa-bell';
        var url = alert.url || '#';
        var count = parseInt(alert.count, 10) || 0;
        var badge = count > 0
            ? '<span class="rateb-system-flash-alert__badge">' + escapeHtml(String(count)) + '</span>'
            : '';
        var action = (url && url !== '#')
            ? '<a href="' + escapeHtml(url) + '" class="rateb-system-flash-alert__action btn btn-sm btn-light" data-rateb-full-nav="1">'
                + escapeHtml(alert.action_label || 'View') + '</a>'
            : '';
        var preview = renderPreviewItems(alert);

        return ''
            + '<div class="rateb-system-flash-alert rateb-system-flash-alert--' + escapeHtml(severity) + pulse + '"'
            + ' data-alert-key="' + escapeHtml(alert.key) + '"'
            + ' data-alert-count="' + escapeHtml(String(count)) + '"'
            + ' role="alert">'
            + '<div class="rateb-system-flash-alert__icon" aria-hidden="true">'
            + '<i class="fas ' + escapeHtml(icon) + '"></i>' + badge
            + '</div>'
            + '<div class="rateb-system-flash-alert__body">'
            + '<div class="rateb-system-flash-alert__title">' + escapeHtml(alert.title || '') + '</div>'
            + preview
            + '</div>'
            + action
            + '</div>';
    }

    function currentTicketId() {
        var live = document.querySelector('[data-rateb-ticket-live="1"]');
        if (!live) {
            return 0;
        }
        return parseInt(live.getAttribute('data-ticket-id') || '0', 10) || 0;
    }

    function isOnSupportTicketsIndex() {
        try {
            var p = String(location.pathname || '').replace(/\/+$/, '');
            return /\/support-tickets$/i.test(p);
        } catch (e) {
            return false;
        }
    }

    function isOnSupportTicketsPage() {
        try {
            return /\/support-tickets(\/|$)/i.test(String(location.pathname || ''));
        } catch (e) {
            return false;
        }
    }

    function showToast(title, message) {
        var stack = document.getElementById('rateb-live-toast-stack');
        if (!stack) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'rateb-live-toast';
        el.innerHTML = '<div class="rateb-live-toast__title">' + escapeHtml(title || (window.__RATEB_LIVE_TOAST_TITLE__ || 'Notice')) + '</div>'
            + '<div class="rateb-live-toast__msg">' + escapeHtml(message || '') + '</div>';
        stack.appendChild(el);
        setTimeout(function () {
            try { el.remove(); } catch (eRm) { /* ignore */ }
        }, 7000);
    }

    function persistSeenNotifs() {
        try {
            sessionStorage.setItem('rateb_seen_notif_ids', JSON.stringify(seenNotifIds));
        } catch (ePers) { /* ignore */ }
    }

    function applyNotifications(payload) {
        var pack = payload && payload.notifications ? payload.notifications : null;
        if (!pack || !Array.isArray(pack.items)) {
            return;
        }
        pack.items.forEach(function (item) {
            var id = parseInt(item.id, 10) || 0;
            if (id < 1 || seenNotifIds[String(id)]) {
                return;
            }
            seenNotifIds[String(id)] = 1;
            showToast(item.title || (window.__RATEB_LIVE_TOAST_TITLE__ || 'Notice'), item.message || '');
        });
        persistSeenNotifs();
    }

    function renderThreadFromLive(ticket) {
        var root = document.querySelector('[data-rateb-ticket-live="1"]');
        var body = root ? root.querySelector('[data-rateb-ticket-thread-body="1"]') : null;
        if (!root || !body || !ticket) {
            return;
        }
        var labels = ticket.labels || {};
        var html = '';
        var original = ticket.original || {};
        html += '<div class="support-ticket-thread__msg support-ticket-thread__msg--client">'
            + '<div class="support-ticket-thread__meta"><strong>' + escapeHtml(labels.original || 'Original') + '</strong>';
        if (original.user_name) {
            html += '<span> — ' + escapeHtml(original.user_name) + '</span>';
        }
        if (original.created_at) {
            html += '<span class="text-muted small"> ' + escapeHtml(original.created_at) + '</span>';
        }
        html += '</div><div class="support-ticket-thread__text">' + nl2br(original.body || '') + '</div></div>';

        (ticket.replies || []).forEach(function (reply) {
            var isStaff = !!reply.is_staff;
            html += '<div class="support-ticket-thread__msg ' + (isStaff ? 'support-ticket-thread__msg--staff' : 'support-ticket-thread__msg--client') + '" data-reply-id="' + escapeHtml(String(reply.id || 0)) + '">'
                + '<div class="support-ticket-thread__meta"><strong>' + escapeHtml(isStaff ? (labels.staff || 'Staff') : (labels.client || 'Client')) + '</strong>';
            if (reply.user_name) {
                html += '<span> — ' + escapeHtml(reply.user_name) + '</span>';
            }
            if (reply.created_at) {
                html += '<span class="text-muted small"> ' + escapeHtml(reply.created_at) + '</span>';
            }
            html += '</div><div class="support-ticket-thread__text">' + nl2br(reply.body || '') + '</div></div>';
        });

        body.innerHTML = html;
        root.setAttribute('data-activity-token', ticket.activity_token || '');
        root.setAttribute('data-status', ticket.status || '');
        root.setAttribute('data-priority', ticket.priority || '');

        var statusField = document.querySelector('select[name="status"], #status');
        if (statusField && ticket.status) {
            statusField.value = ticket.status;
        }
        var priorityField = document.querySelector('select[name="priority"], #priority');
        if (priorityField && ticket.priority) {
            priorityField.value = ticket.priority;
        }

        var badge = root.querySelector('[data-rateb-live-badge="1"]');
        if (badge) {
            badge.classList.remove('d-none');
            badge.textContent = window.__RATEB_LIVE_UPDATED__ || 'Live update';
            setTimeout(function () {
                try { badge.classList.add('d-none'); } catch (eB) { /* ignore */ }
            }, 2500);
        }

        try {
            body.scrollTop = body.scrollHeight;
        } catch (eScroll) { /* ignore */ }
    }

    function softRefreshMainIfNeeded(activityToken) {
        if (!isOnSupportTicketsIndex() || !activityToken) {
            return;
        }
        if (!lastActivityToken) {
            lastActivityToken = activityToken;
            return;
        }
        if (lastActivityToken === activityToken) {
            return;
        }
        var now = Date.now();
        if (now - lastListRefreshAt < LIST_SOFT_REFRESH_MS) {
            return;
        }
        lastListRefreshAt = now;
        lastActivityToken = activityToken;
        fetch(String(location.href), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/html',
                'X-Rateb-Nav-Swap': '1',
            },
        })
            .then(function (res) { return res.ok ? res.text() : ''; })
            .then(function (html) {
                if (!html || html.length < 200) {
                    return;
                }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var next = doc.querySelector('#rateb-main-content, main.rateb-content');
                var cur = document.querySelector('#rateb-main-content, main.rateb-content');
                if (next && cur) {
                    cur.innerHTML = next.innerHTML;
                }
            })
            .catch(function () { /* ignore */ });
    }

    function supportTicketsTableLooksEmpty() {
        var main = document.querySelector('#rateb-main-content, main.rateb-content');
        if (!main) {
            return false;
        }
        var emptyRow = main.querySelector('td.text-muted, .text-center.text-muted');
        if (!emptyRow) {
            return false;
        }
        var text = (emptyRow.textContent || '').replace(/\s+/g, ' ').trim();
        return text.length > 0 && main.querySelectorAll('table tbody tr').length <= 1;
    }

    function refreshTicketsListIfStale(count) {
        if (count < 1 || !isOnSupportTicketsPage() || !supportTicketsTableLooksEmpty()) {
            return;
        }
        if (lastAlertCount >= 0 && count <= lastAlertCount) {
            return;
        }
        try {
            if (window.__RATEB_SUPPORT_TICKETS_RELOADING__) {
                return;
            }
            window.__RATEB_SUPPORT_TICKETS_RELOADING__ = 1;
            var url = String(location.href || '');
            if (url.indexOf('company_id=') === -1) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'company_id=0';
            }
            location.href = url;
        } catch (eReload) {
            try { location.reload(); } catch (e2) { /* ignore */ }
        }
    }

    function applyPayload(payload) {
        var stack = stackEl();
        if (!stack) {
            return;
        }
        var count = payload && typeof payload.count === 'number' ? payload.count : 0;
        var alert = payload ? payload.alert : null;

        if (count < 1 || !alert) {
            stack.innerHTML = '';
            stack.classList.add('rateb-system-flash-stack--empty');
            lastAlertCount = 0;
        } else {
            stack.classList.remove('rateb-system-flash-stack--empty');
            var existing = stack.querySelector('[data-alert-key="' + alert.key + '"]');
            var html = renderAlert(alert);
            if (existing) {
                existing.outerHTML = html;
            } else {
                stack.innerHTML = html;
            }
            refreshTicketsListIfStale(count);
            lastAlertCount = count;
        }

        applyNotifications(payload);

        if (payload && payload.ticket && payload.ticket.activity_token) {
            if (lastTicketToken && lastTicketToken !== payload.ticket.activity_token) {
                renderThreadFromLive(payload.ticket);
            } else if (!lastTicketToken) {
                lastTicketToken = payload.ticket.activity_token;
                var liveRoot = document.querySelector('[data-rateb-ticket-live="1"]');
                var current = liveRoot ? (liveRoot.getAttribute('data-activity-token') || '') : '';
                if (current && current !== payload.ticket.activity_token) {
                    renderThreadFromLive(payload.ticket);
                }
            }
            lastTicketToken = payload.ticket.activity_token;
        }

        if (payload && payload.activity_token) {
            softRefreshMainIfNeeded(payload.activity_token);
        }

        document.dispatchEvent(new CustomEvent('rateb:support-ticket-alert', {
            detail: {
                count: count,
                activity_token: payload ? payload.activity_token : '',
                ticket: payload ? payload.ticket : null,
            },
        }));
    }

    function pollUrl() {
        var stack = stackEl();
        if (!stack) {
            return '';
        }
        var base = stack.getAttribute('data-rateb-system-flash-poll') || '';
        if (!base) {
            return '';
        }
        var tid = currentTicketId();
        if (tid > 0) {
            return base + (base.indexOf('?') >= 0 ? '&' : '?') + 'ticket_id=' + encodeURIComponent(String(tid));
        }
        return base;
    }

    function pollOnce() {
        var url = pollUrl();
        if (!url || pollInFlight) {
            return;
        }
        pollInFlight = true;
        fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) {
                return res.ok ? res.json() : null;
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                applyPayload(data);
            })
            .catch(function () {
                /* best-effort */
            })
            .finally(function () {
                pollInFlight = false;
            });
    }

    function schedulePoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        var stack = stackEl();
        if (!stack || stack.getAttribute('data-rateb-system-flash-enabled') !== '1') {
            return;
        }
        pollOnce();
        pollTimer = setInterval(pollOnce, POLL_MS);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.rateb-system-flash-alert__close') : null;
        if (!btn) {
            return;
        }
        var box = btn.closest('.rateb-system-flash-alert');
        if (box) {
            box.remove();
        }
    });

    document.addEventListener('DOMContentLoaded', schedulePoll);
    document.addEventListener('rateb:soft-nav:afterEnter', function () {
        lastTicketToken = '';
        lastActivityToken = '';
        schedulePoll();
        pollOnce();
    });

    if (document.readyState !== 'loading') {
        schedulePoll();
    }
})();
