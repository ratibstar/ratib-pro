(function () {
    'use strict';

    if (window.__RATEB_SYSTEM_FLASH__) {
        return;
    }
    window.__RATEB_SYSTEM_FLASH__ = 1;

    var POLL_MS = 2000;
    var pollTimer = null;
    var lastAlertCount = -1;

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

    function isOnSupportTicketsPage() {
        try {
            return /\/support-tickets(\/|$)/i.test(String(location.pathname || ''));
        } catch (e) {
            return false;
        }
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

    /** When flash sees a new ticket but the list is still empty (stale soft-nav), force a full reload. */
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
            document.dispatchEvent(new CustomEvent('rateb:support-ticket-alert', { detail: { count: 0 } }));
            return;
        }

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
        document.dispatchEvent(new CustomEvent('rateb:support-ticket-alert', { detail: { count: count } }));
    }

    function pollUrl() {
        var stack = stackEl();
        if (!stack) {
            return '';
        }
        return stack.getAttribute('data-rateb-system-flash-poll') || '';
    }

    function pollOnce() {
        var url = pollUrl();
        if (!url) {
            return;
        }
        fetch(url, {
            credentials: 'same-origin',
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
        schedulePoll();
        pollOnce();
    });

    if (document.readyState !== 'loading') {
        schedulePoll();
    }
})();
