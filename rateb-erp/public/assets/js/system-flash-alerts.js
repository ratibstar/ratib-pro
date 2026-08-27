(function () {
    'use strict';

    if (window.__RATEB_SYSTEM_FLASH__) {
        return;
    }
    window.__RATEB_SYSTEM_FLASH__ = 1;

    var POLL_MS = 1000;
    var LIST_SOFT_REFRESH_MS = 1200;
    var pollTimer = null;
    var lastAlertCount = -1;
    var lastActivityToken = '';
    var lastTicketToken = '';
    var lastListRefreshAt = 0;
    var lastTicketsTable = [];
    var lastTicketsTableFp = '';
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
        var pulse = '';
        var icon = alert.icon || 'fa-bell';
        var url = alert.url || '#';
        var count = parseInt(alert.count, 10) || 0;
        var ticketIds = Array.isArray(alert.ticket_ids) ? alert.ticket_ids : [];
        var primaryTicketId = ticketIds.length ? (parseInt(ticketIds[0], 10) || 0) : 0;
        var badge = count > 0
            ? '<span class="rateb-system-flash-alert__badge">' + escapeHtml(String(count)) + '</span>'
            : '';
        var action = (url && url !== '#')
            ? '<a href="' + escapeHtml(url) + '" class="rateb-system-flash-alert__action btn btn-sm btn-light" data-rateb-full-nav="1">'
                + escapeHtml(alert.action_label || 'View') + '</a>'
            : '';
        var preview = renderPreviewItems(alert);
        var ticketAttr = primaryTicketId > 0
            ? ' data-ticket-id="' + escapeHtml(String(primaryTicketId)) + '"'
            : '';
        var idsAttr = ticketIds.length
            ? ' data-ticket-ids="' + escapeHtml(ticketIds.map(function (id) { return String(id); }).join(',')) + '"'
            : '';

        return ''
            + '<div class="rateb-system-flash-alert rateb-system-flash-alert--' + escapeHtml(severity) + pulse + '"'
            + ' data-alert-key="' + escapeHtml(alert.key) + '"'
            + ' data-alert-count="' + escapeHtml(String(count)) + '"'
            + ticketAttr + idsAttr
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
        if (window.__RATEB_SUPPORT_TICKETS_INDEX__ === 1) {
            return true;
        }
        if (document.querySelector('[data-support-tickets-index="1"]')) {
            return true;
        }
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
            var trigger = String(item.trigger_type || '');
            // Reply/open ticket notices are rendered as persistent flash cards — never toast+forget.
            if (trigger === 'support_ticket_reply' || trigger === 'support_ticket_open') {
                return;
            }
            seenNotifIds[String(id)] = 1;
            showToast(item.title || (window.__RATEB_LIVE_TOAST_TITLE__ || 'Notice'), item.message || '');
        });
        persistSeenNotifs();
    }

    function normalizeAlerts(payload) {
        if (payload && Array.isArray(payload.alerts)) {
            return payload.alerts.filter(function (a) { return a && a.key; });
        }
        if (payload && payload.alert && payload.alert.key) {
            return [payload.alert];
        }
        return [];
    }

    var lastAlertsFp = '';

    function applyAlertsStack(alerts) {
        var stack = stackEl();
        if (!stack) {
            return;
        }
        if (!alerts.length) {
            if (lastAlertsFp !== '') {
                stack.innerHTML = '';
                stack.classList.add('rateb-system-flash-stack--empty');
                lastAlertsFp = '';
            }
            return;
        }
        var fp = alerts.map(function (a) {
            return String(a.key || '') + ':' + String(a.count || 0) + ':' + String(a.title || '') + ':' + String(a.message || '');
        }).join('|');
        // Keep the card stable until content actually changes — no flashing rebuild every poll.
        if (fp === lastAlertsFp && stack.querySelector('.rateb-system-flash-alert')) {
            return;
        }
        lastAlertsFp = fp;
        stack.classList.remove('rateb-system-flash-stack--empty');
        var html = '';
        alerts.forEach(function (alert) {
            html += renderAlert(alert);
        });
        stack.innerHTML = html;
    }

    function markTicketIdsSeen(ticketIds) {
        var stack = stackEl();
        var url = stack ? (stack.getAttribute('data-rateb-system-flash-mark-seen') || '') : '';
        if (!url || !ticketIds || !ticketIds.length) {
            return;
        }
        ticketIds.forEach(function (tid) {
            var id = parseInt(tid, 10) || 0;
            if (id < 1) {
                return;
            }
            var body = new URLSearchParams();
            body.set('ticket_id', String(id));
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body.toString(),
            }).then(function (res) {
                return res.ok ? res.json() : null;
            }).then(function (data) {
                if (data && data.ok) {
                    applyPayload(data);
                }
            }).catch(function () { /* ignore */ });
        });
    }

    function stripReplyMarker(text) {
        return String(text == null ? '' : text).replace(/\n*\s*\[rateb_(?:agency|platform)_reply:\d+:\d+\]\s*$/u, '').trim();
    }

    function renderThreadMsg(opts) {
        var isStaff = !!opts.isStaff;
        var continued = !!opts.continued;
        var cls = 'support-ticket-thread__msg '
            + (isStaff ? 'support-ticket-thread__msg--staff' : 'support-ticket-thread__msg--client')
            + (continued ? ' support-ticket-thread__msg--continued' : '');
        var html = '<div class="' + cls + '" data-thread-msg="1" data-is-staff="' + (isStaff ? '1' : '0') + '"';
        if (opts.replyId) {
            html += ' data-reply-id="' + escapeHtml(String(opts.replyId)) + '"';
        }
        html += '><div class="support-ticket-thread__meta"><strong>' + escapeHtml(opts.title || '') + '</strong>';
        if (opts.userName) {
            html += '<span>' + escapeHtml(opts.userName) + '</span>';
        }
        if (opts.createdAt) {
            html += '<span class="text-muted support-ticket-thread__time">' + escapeHtml(opts.createdAt) + '</span>';
        }
        html += '</div><div class="support-ticket-thread__text">' + nl2br(stripReplyMarker(opts.body || '')) + '</div></div>';
        return html;
    }

    function renderThreadFromLive(ticket) {
        var root = document.querySelector('[data-rateb-ticket-live="1"]');
        var body = root ? root.querySelector('[data-rateb-ticket-thread-body="1"]') : null;
        if (!root || !body || !ticket) {
            return;
        }
        var wasExpanded = root.getAttribute('data-thread-expanded') === '1';
        var labels = ticket.labels || {};
        var html = '';
        var original = ticket.original || {};
        var prevStaff = null;
        html += renderThreadMsg({
            isStaff: false,
            continued: false,
            title: labels.original || 'Original',
            userName: original.user_name || '',
            createdAt: original.created_at || '',
            body: original.body || '',
        });
        prevStaff = false;

        (ticket.replies || []).forEach(function (reply) {
            var isStaff = !!reply.is_staff;
            html += renderThreadMsg({
                isStaff: isStaff,
                continued: prevStaff !== null && prevStaff === isStaff,
                title: isStaff ? (labels.staff || 'Staff') : (labels.client || 'Client'),
                userName: reply.user_name || '',
                createdAt: reply.created_at || '',
                body: reply.body || '',
                replyId: reply.id || 0,
            });
            prevStaff = isStaff;
        });

        body.innerHTML = html;
        root.setAttribute('data-activity-token', ticket.activity_token || '');
        root.setAttribute('data-status', ticket.status || '');
        root.setAttribute('data-priority', ticket.priority || '');
        if (!root.getAttribute('data-thread-visible-limit')) {
            root.setAttribute('data-thread-visible-limit', '3');
        }
        root.setAttribute('data-thread-expanded', wasExpanded ? '1' : '0');
        root.dataset.threadBound = '0';
        if (window.RatebSupportTicketUi && typeof window.RatebSupportTicketUi.refreshThread === 'function') {
            window.RatebSupportTicketUi.refreshThread(root);
        } else if (window.RatebSupportTicketUi && typeof window.RatebSupportTicketUi.applyThreadCollapse === 'function') {
            window.RatebSupportTicketUi.applyThreadCollapse(root, wasExpanded);
        }

        var statusField = document.querySelector('#f_status, select[name="status"]');
        if (statusField && ticket.status && String(statusField.value) !== String(ticket.status)) {
            statusField.value = ticket.status;
            try { statusField.dispatchEvent(new Event('change', { bubbles: true })); } catch (eSt) { /* ignore */ }
        }
        var priorityField = document.querySelector('#f_priority, select[name="priority"]');
        if (priorityField && ticket.priority && String(priorityField.value) !== String(ticket.priority)) {
            priorityField.value = ticket.priority;
            try { priorityField.dispatchEvent(new Event('change', { bubbles: true })); } catch (ePr) { /* ignore */ }
        }

        // Status pills in index/table if present on this page.
        document.querySelectorAll('[data-ticket-status-id="' + String(ticket.id || '') + '"]').forEach(function (el) {
            el.textContent = ticket.status || el.textContent;
        });

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
        // Index: badge patch only, unless poll reveals tickets missing from the DOM → hard reload.
        if (isOnSupportTicketsIndex()) {
            if (activityToken) {
                lastActivityToken = activityToken;
            }
            if (lastTicketsTable && lastTicketsTable.length) {
                if (indexMissingNewestTicket(lastTicketsTable)) {
                    reloadTicketsIndexOnce();
                    return;
                }
                applyTicketsTable(lastTicketsTable);
            }
            return;
        }
        if (!activityToken) {
            return;
        }
        var firstPoll = !lastActivityToken;
        if (!firstPoll && lastActivityToken === activityToken) {
            return;
        }
        var now = Date.now();
        if (!firstPoll && (now - lastListRefreshAt < LIST_SOFT_REFRESH_MS)) {
            return;
        }
        lastListRefreshAt = now;
        lastActivityToken = activityToken;
        doSoftRefreshList();
    }

    function maxDomTicketId() {
        var max = 0;
        document.querySelectorAll('tr[data-rateb-row-id]').forEach(function (tr) {
            var id = parseInt(tr.getAttribute('data-rateb-row-id'), 10) || 0;
            if (id > max) {
                max = id;
            }
        });
        return max;
    }

    function indexMissingNewestTicket(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            return false;
        }
        var newest = rows[0];
        if (!newest || findTicketRow(newest)) {
            return false;
        }
        var newestId = parseInt(newest.id, 10) || 0;
        var maxDom = maxDomTicketId();
        if (newestId > 0 && maxDom > 0 && newestId > maxDom) {
            return true;
        }
        return !findTicketRow(newest);
    }

    function reloadTicketsIndexOnce() {
        if (window.__RATEB_SUPPORT_TICKETS_RELOADING__) {
            return;
        }
        window.__RATEB_SUPPORT_TICKETS_RELOADING__ = 1;
        try {
            var url = String(location.href || '');
            var join = url.indexOf('?') >= 0 ? '&' : '?';
            location.replace(url.replace(/([?&])_live=\d+/g, '').replace(/[?&]$/, '') + join + '_live=' + String(Date.now()));
        } catch (e) {
            try { location.reload(); } catch (e2) { /* ignore */ }
        }
    }

    function bustUrl(url) {
        var u = String(url || '');
        var join = u.indexOf('?') >= 0 ? '&' : '?';
        return u + join + '_live=' + String(Date.now());
    }

    function doSoftRefreshList() {
        fetch(bustUrl(String(location.href)), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/html',
                'Cache-Control': 'no-cache',
                Pragma: 'no-cache',
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
                // Authoritative: re-paint status/priority from last poll JSON.
                applyTicketsTable(lastTicketsTable);
            })
            .catch(function () { /* ignore */ });
    }

    function ticketsTableFingerprint(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            return '';
        }
        return rows.map(function (r) {
            return String(r.id || '') + ':' + String(r.status || '') + ':' + String(r.priority || '');
        }).join('|');
    }

    function normalizeTicketNo(no) {
        var s = String(no || '').replace(/\s+/g, '').trim();
        // Platform mirrors use A34-ST-0002; agency uses ST-0002.
        return s.replace(/^A\d+-/i, '');
    }

    function findTicketRow(row) {
        var id = parseInt(row && row.id, 10) || 0;
        if (id > 0) {
            var byId = document.querySelector('tr[data-rateb-row-id="' + String(id) + '"]');
            if (byId) {
                return byId;
            }
        }
        var want = normalizeTicketNo(row && row.ticket_no);
        if (!want) {
            return null;
        }
        var trs = document.querySelectorAll('table tbody tr');
        for (var i = 0; i < trs.length; i++) {
            var tr = trs[i];
            var tds = tr.querySelectorAll('td');
            for (var j = 0; j < tds.length; j++) {
                var t = (tds[j].textContent || '').replace(/\s+/g, ' ').trim();
                if (normalizeTicketNo(t) === want || t === String(row.ticket_no || '')) {
                    return tr;
                }
            }
        }
        return null;
    }

    function findBadgeTd(tr, colName) {
        if (!tr || !colName) {
            return null;
        }
        var td = tr.querySelector('td[data-col-name="' + colName + '"]');
        if (td) {
            return td;
        }
        var hints = {
            status: ['الحالة', 'status'],
            priority: ['الأولوية', 'priority'],
        };
        var list = hints[colName] || [colName];
        var ths = document.querySelectorAll('table thead th');
        var idx = -1;
        for (var i = 0; i < ths.length; i++) {
            var ht = (ths[i].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            for (var h = 0; h < list.length; h++) {
                if (ht.indexOf(String(list[h]).toLowerCase()) !== -1) {
                    idx = i;
                    break;
                }
            }
            if (idx >= 0) {
                break;
            }
        }
        if (idx < 0) {
            return null;
        }
        return tr.children[idx] || null;
    }

    function applyTicketsTable(rows) {
        if (!isOnSupportTicketsIndex() || !Array.isArray(rows) || !rows.length) {
            return false;
        }
        var changed = false;
        var matched = 0;
        rows.forEach(function (row) {
            var tr = findTicketRow(row);
            if (!tr) {
                return;
            }
            matched++;
            if (patchBadgeCell(tr, 'status', row.status, row.status_label, row.status_badge)) {
                changed = true;
            }
            if (patchBadgeCell(tr, 'priority', row.priority, row.priority_label, row.priority_badge)) {
                changed = true;
            }
        });
        return changed || matched > 0;
    }

    function patchBadgeCell(tr, colName, value, label, badgeTone) {
        if (!tr || !colName || value == null || value === '') {
            return false;
        }
        var td = findBadgeTd(tr, colName);
        if (!td) {
            return false;
        }
        var prev = td.getAttribute('data-cell-value') || '';
        var span = td.querySelector('[data-rateb-live-badge-text="1"], .badge');
        var prevText = span ? String(span.textContent || '').trim() : String(td.textContent || '').trim();
        var text = String(label || value);
        if (String(prev) === String(value) && prevText === text) {
            return false;
        }
        td.setAttribute('data-col-name', colName);
        td.setAttribute('data-cell-value', String(value));
        var tone = badgeTone || 'secondary';
        if (!span) {
            span = document.createElement('span');
            span.setAttribute('data-rateb-live-badge-text', '1');
            td.textContent = '';
            td.appendChild(span);
        }
        span.setAttribute('data-rateb-live-badge-text', '1');
        span.className = 'badge bg-' + String(tone).replace(/[^a-z0-9_-]/gi, '');
        span.textContent = text;
        td.setAttribute('title', text);
        return true;
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
        var alerts = normalizeAlerts(payload);

        applyAlertsStack(alerts);
        if (count > 0) {
            refreshTicketsListIfStale(count);
        }
        lastAlertCount = count;

        applyNotifications(payload);

        var rows = payload && Array.isArray(payload.tickets_table) ? payload.tickets_table : [];
        lastTicketsTable = rows;
        var fp = ticketsTableFingerprint(rows);
        var tableChanged = fp !== '' && fp !== lastTicketsTableFp;
        // Instant table update from JSON (source of truth after agency→platform sync).
        applyTicketsTable(rows);
        if (tableChanged) {
            lastTicketsTableFp = fp;
        }

        if (payload && payload.ticket && payload.ticket.activity_token) {
            var liveRoot = document.querySelector('[data-rateb-ticket-live="1"]');
            var domToken = liveRoot ? (liveRoot.getAttribute('data-activity-token') || '') : '';
            var nextToken = String(payload.ticket.activity_token || '');
            if (nextToken && (nextToken !== lastTicketToken || (domToken && domToken !== nextToken))) {
                renderThreadFromLive(payload.ticket);
            }
            lastTicketToken = nextToken;
        }

        if (payload && payload.activity_token) {
            softRefreshMainIfNeeded(payload.activity_token);
        }

        document.dispatchEvent(new CustomEvent('rateb:support-ticket-alert', {
            detail: {
                count: count,
                activity_token: payload ? payload.activity_token : '',
                ticket: payload ? payload.ticket : null,
                tickets_table: rows,
                alerts: alerts,
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
        var action = e.target && e.target.closest ? e.target.closest('.rateb-system-flash-alert__action') : null;
        if (action) {
            var box = action.closest('.rateb-system-flash-alert');
            if (box) {
                var key = String(box.getAttribute('data-alert-key') || '');
                // Only mark read when opening a specific reply alert (edit URL).
                // Aggregate open-tickets banner should stay until each ticket is opened.
                if (key.indexOf('support_ticket_reply_') === 0) {
                    var idsAttr = box.getAttribute('data-ticket-ids') || box.getAttribute('data-ticket-id') || '';
                    var ids = String(idsAttr).split(',').map(function (s) { return parseInt(s, 10) || 0; }).filter(Boolean);
                    if (ids.length) {
                        markTicketIdsSeen(ids);
                    }
                }
            }
            return;
        }
        var btn = e.target && e.target.closest ? e.target.closest('.rateb-system-flash-alert__close') : null;
        if (!btn) {
            return;
        }
        var closeBox = btn.closest('.rateb-system-flash-alert');
        if (!closeBox) {
            return;
        }
        var closeKey = String(closeBox.getAttribute('data-alert-key') || '');
        var closeIdsAttr = closeBox.getAttribute('data-ticket-ids') || closeBox.getAttribute('data-ticket-id') || '';
        var closeIds = String(closeIdsAttr).split(',').map(function (s) { return parseInt(s, 10) || 0; }).filter(Boolean);
        closeBox.remove();
        if (closeKey.indexOf('support_ticket_reply_') === 0 && closeIds.length) {
            markTicketIdsSeen(closeIds);
        }
    });

    document.addEventListener('DOMContentLoaded', schedulePoll);
    document.addEventListener('rateb:soft-nav:afterEnter', function () {
        lastTicketToken = '';
        lastActivityToken = '';
        lastTicketsTableFp = '';
        lastAlertsFp = '';
        schedulePoll();
        pollOnce();
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && isOnSupportTicketsIndex()) {
            pollOnce();
        }
    });
    window.addEventListener('focus', function () {
        if (isOnSupportTicketsIndex()) {
            pollOnce();
        }
    });

    if (document.readyState !== 'loading') {
        schedulePoll();
    }
})();
