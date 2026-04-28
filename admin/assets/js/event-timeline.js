/**
 * EN: Implements system administration/observability module behavior in `admin/assets/js/event-timeline.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/assets/js/event-timeline.js`.
 */
(() => {
    const liveCheckbox = document.getElementById('liveMode');
    const timelineHost = document.getElementById('timelineHost');
    const streamStatus = document.getElementById('streamStatus');
    let eventSource = null;
    let latestId = Number((timelineHost && timelineHost.getAttribute('data-last-id')) || '0');

    if (!timelineHost) {
        return;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function isAnomaly(ev) {
        return ev.event_type === 'ANOMALY_DETECTED' || ev.level === 'critical';
    }

    function parseDuration(ev) {
        try {
            const m = typeof ev.metadata === 'string' ? JSON.parse(ev.metadata) : ev.metadata;
            if (m && m.duration_ms != null) return Number(m.duration_ms);
        } catch (e) {}
        return null;
    }

    function findBundle(key) {
        const all = timelineHost.querySelectorAll('details.req-bundle');
        for (const el of all) {
            if (el.dataset.req === key) {
                return el;
            }
        }
        return null;
    }

    function ensureBundle(rid) {
        const key = rid || '—';
        let d = findBundle(key);
        if (!d) {
            d = document.createElement('details');
            d.className = 'req-bundle';
            d.open = true;
            d.dataset.req = key;
            const sum = document.createElement('summary');
            sum.innerHTML = '<span>req <code>' + esc(key) + '</code></span> <span class="pill">0 events</span> ';
            if (key !== '—') {
                sum.innerHTML += '<a href="event-flow.php?request_id=' + encodeURIComponent(key) + '" class="etl-link js-stop-propagation">flow</a> ';
            }
            d.appendChild(sum);
            const inner = document.createElement('div');
            inner.className = 'bundle-inner';
            d.appendChild(inner);
            timelineHost.insertBefore(d, timelineHost.firstChild);
        }
        return d;
    }

    function appendEventToTimeline(ev) {
        const id = Number(ev.id || 0);
        if (id && document.querySelector('[data-event-id="' + id + '"]')) {
            return;
        }
        const rid = ev.request_id || '—';
        const d = ensureBundle(rid);
        const inner = d.querySelector('.bundle-inner');
        if (!inner) return;

        const dms = parseDuration(ev);
        const lvl = String(ev.level || 'info').toLowerCase().replace(/[^a-z]/g, '') || 'info';
        let cls = 'timeline-item lvl-' + lvl;
        if (isAnomaly(ev)) cls += ' anomaly';
        const durHtml = dms != null ? '<span>' + esc(String(dms)) + ' ms</span>' : '';
        const html = '<div class="' + cls + '" data-event-id="' + esc(String(id)) + '">' +
            '<div class="meta">' +
            '<span>' + esc(ev.created_at) + '</span>' +
            '<span>' + esc(ev.event_type) + '</span>' +
            '<span>level=' + esc(ev.level) + '</span>' +
            durHtml +
            '<span>tenant=' + esc(ev.tenant_id || 0) + '</span>' +
            '</div>' +
            '<div>' + esc(ev.message) + '</div>' +
            '<div class="muted">' + esc(String(ev.metadata || '').slice(0, 320)) + '</div>' +
            '</div>';
        inner.insertAdjacentHTML('beforeend', html);

        const pill = d.querySelector('summary .pill');
        if (pill) {
            const n = inner.querySelectorAll('.timeline-item').length;
            pill.textContent = n + ' events';
        }
        latestId = Math.max(latestId, id);
        timelineHost.setAttribute('data-last-id', String(latestId));
    }

    function stopStream() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        if (streamStatus) {
            streamStatus.textContent = 'stream off';
            streamStatus.classList.add('off');
        }
    }

    function startStream() {
        stopStream();
        const url = 'events-stream.php?last_id=' + encodeURIComponent(String(latestId));
        eventSource = new EventSource(url);
        if (streamStatus) {
            streamStatus.textContent = 'SSE connected';
            streamStatus.classList.remove('off');
        }
        eventSource.onmessage = function (e) {
            try {
                const ev = JSON.parse(e.data);
                if (ev.error) return;
                appendEventToTimeline(ev);
            } catch (err) {}
        };
        eventSource.onerror = function () {
            if (streamStatus) {
                streamStatus.textContent = 'SSE reconnecting…';
                streamStatus.classList.add('off');
            }
        };
    }

    if (liveCheckbox) {
        liveCheckbox.addEventListener('change', () => {
            if (liveCheckbox.checked) {
                startStream();
            } else {
                stopStream();
            }
        });
    }

    timelineHost.addEventListener('click', function (ev) {
        const target = ev.target;
        if (target && target.classList && target.classList.contains('js-stop-propagation')) {
            ev.stopPropagation();
        }
    });
})();
