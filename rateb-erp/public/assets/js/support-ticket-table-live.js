/**
 * Support tickets index — live status/priority badge sync (platform ↔ agency).
 * Loaded from the tickets list page itself so it cannot be skipped by stale shell JS.
 */
(function () {
    'use strict';
    if (window.__RATEB_ST_TABLE_LIVE__) {
        return;
    }
    window.__RATEB_ST_TABLE_LIVE__ = 1;

    var POLL_MS = 1000;
    var pollUrl = String(window.__RATEB_SUPPORT_TICKETS_POLL__ || '');
    var timer = null;
    var inFlight = false;
    var lastFp = '';
    var lastReloadAt = 0;

    function onIndex() {
        if (window.__RATEB_SUPPORT_TICKETS_INDEX__ === 1) {
            return true;
        }
        if (document.querySelector('[data-support-tickets-index="1"]')) {
            return true;
        }
        try {
            return /\/support-tickets$/i.test(String(location.pathname || '').replace(/\/+$/, ''));
        } catch (e) {
            return false;
        }
    }

    function normalizeTicketNo(no) {
        return String(no || '').replace(/\s+/g, '').trim().replace(/^A\d+-/i, '');
    }

    function fingerprint(rows) {
        if (!Array.isArray(rows)) {
            return '';
        }
        return rows.map(function (r) {
            return String(r.id || '') + ':' + String(r.status || '') + ':' + String(r.priority || '');
        }).join('|');
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

    function findRow(row) {
        var id = parseInt(row && row.id, 10) || 0;
        if (id > 0) {
            var byId = document.querySelector('tr[data-rateb-row-id="' + id + '"]');
            if (byId) {
                return byId;
            }
        }
        var want = normalizeTicketNo(row && row.ticket_no);
        if (!want) {
            return null;
        }
        var trs = document.querySelectorAll('.rateb-table tbody tr, table.table tbody tr');
        for (var i = 0; i < trs.length; i++) {
            var cells = trs[i].querySelectorAll('td');
            for (var j = 0; j < cells.length; j++) {
                var t = (cells[j].textContent || '').replace(/\s+/g, ' ').trim();
                if (normalizeTicketNo(t) === want || t === String(row.ticket_no || '')) {
                    return trs[i];
                }
            }
        }
        return null;
    }

    /** New tickets arrived in DB but not yet painted in the list HTML. */
    function needsFullReload(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            return false;
        }
        var newest = rows[0];
        if (!newest) {
            return false;
        }
        if (findRow(newest)) {
            return false;
        }
        var newestId = parseInt(newest.id, 10) || 0;
        var maxDom = maxDomTicketId();
        if (newestId > 0 && maxDom > 0 && newestId > maxDom) {
            return true;
        }
        // No row ids in DOM — still reload if newest ticket_no is absent.
        return !findRow(newest);
    }

    function reloadIndexOnce() {
        var now = Date.now();
        if (window.__RATEB_SUPPORT_TICKETS_RELOADING__ || (now - lastReloadAt < 8000)) {
            return;
        }
        window.__RATEB_SUPPORT_TICKETS_RELOADING__ = 1;
        lastReloadAt = now;
        try {
            var url = String(location.href || '');
            var join = url.indexOf('?') >= 0 ? '&' : '?';
            location.replace(url.replace(/([?&])_live=\d+/g, '').replace(/[?&]$/, '') + join + '_live=' + String(now));
        } catch (e) {
            try { location.reload(); } catch (e2) { /* ignore */ }
        }
    }

    function colIndex(name) {
        var hints = {
            status: ['الحالة', 'status'],
            priority: ['الأولوية', 'priority'],
            subject: ['الموضوع', 'subject'],
        };
        var list = hints[name] || [name];
        var ths = document.querySelectorAll('.rateb-table thead th, table.table thead th');
        for (var i = 0; i < ths.length; i++) {
            var ht = (ths[i].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            for (var h = 0; h < list.length; h++) {
                if (ht.indexOf(String(list[h]).toLowerCase()) !== -1) {
                    return i;
                }
            }
        }
        return -1;
    }

    function findTd(tr, name) {
        var td = tr.querySelector('td[data-col-name="' + name + '"]');
        if (td) {
            return td;
        }
        var idx = colIndex(name);
        if (idx < 0) {
            return null;
        }
        var cells = tr.querySelectorAll('td');
        return cells[idx] || null;
    }

    function paintBadge(td, value, label, tone) {
        if (!td) {
            return false;
        }
        var text = String(label || value || '');
        var prev = td.getAttribute('data-cell-value') || '';
        var span = td.querySelector('.badge');
        var prevText = span ? String(span.textContent || '').trim() : String(td.textContent || '').trim();
        if (prev === String(value) && prevText === text) {
            return false;
        }
        td.setAttribute('data-cell-value', String(value));
        if (!span) {
            span = document.createElement('span');
            td.textContent = '';
            td.appendChild(span);
        }
        span.className = 'badge bg-' + String(tone || 'secondary').replace(/[^a-z0-9_-]/gi, '');
        span.textContent = text;
        td.setAttribute('title', text);
        td.style.outline = '1px solid rgba(56,189,248,.55)';
        setTimeout(function () {
            try { td.style.outline = ''; } catch (e) { /* ignore */ }
        }, 900);
        return true;
    }

    function applyRows(rows) {
        if (!onIndex() || !Array.isArray(rows)) {
            return 0;
        }
        var changed = 0;
        rows.forEach(function (row) {
            var tr = findRow(row);
            if (!tr) {
                return;
            }
            if (paintBadge(findTd(tr, 'status'), row.status, row.status_label, row.status_badge)) {
                changed++;
            }
            if (paintBadge(findTd(tr, 'priority'), row.priority, row.priority_label, row.priority_badge)) {
                changed++;
            }
        });
        return changed;
    }

    function poll() {
        if (!onIndex() || !pollUrl || inFlight) {
            return;
        }
        inFlight = true;
        var url = pollUrl + (pollUrl.indexOf('?') >= 0 ? '&' : '?') + '_t=' + Date.now();
        fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'Cache-Control': 'no-cache' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !Array.isArray(data.tickets_table)) {
                    return;
                }
                if (needsFullReload(data.tickets_table)) {
                    reloadIndexOnce();
                    return;
                }
                var fp = fingerprint(data.tickets_table);
                applyRows(data.tickets_table);
                lastFp = fp;
            })
            .catch(function () { /* ignore */ })
            .finally(function () { inFlight = false; });
    }

    function start() {
        if (!onIndex() || !pollUrl) {
            return;
        }
        poll();
        if (timer) {
            clearInterval(timer);
        }
        timer = setInterval(poll, POLL_MS);
    }

    document.addEventListener('DOMContentLoaded', start);
    document.addEventListener('rateb:soft-nav:afterEnter', function () {
        lastFp = '';
        start();
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });
    if (document.readyState !== 'loading') {
        start();
    }
})();
