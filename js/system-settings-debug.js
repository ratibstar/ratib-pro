/**
 * EN: Implements frontend interaction behavior in `js/system-settings-debug.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/system-settings-debug.js`.
 */
/**
 * System Settings Debug Panel - Embed only
 * Add ?debug=1 to URL to enable
 */
(function() {
    'use strict';
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('debug') !== '1') return;

    var log = [];
    function addLog(msg, data) {
        var entry = { t: Date.now(), msg: msg, data: data || null };
        log.push(entry);
        if (typeof console !== 'undefined' && console.log) {
            console.log('[SysSettings]', msg, data || '');
        }
        updatePanel();
    }

    // Intercept fetch
    var _fetch = window.fetch;
    window.fetch = function(url, opts) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        addLog('fetch', { url: urlStr.substring(0, 80) });
        return _fetch.apply(this, arguments).then(function(r) {
            addLog('fetch response', { url: urlStr.substring(0, 60), status: r.status });
            if (r.status === 401) addLog('FETCH 401 - may cause redirect!', { url: urlStr });
            return r;
        }).catch(function(e) {
            addLog('fetch error', { url: urlStr.substring(0, 60), err: String(e) });
            throw e;
        });
    };

    // Intercept jQuery.ajax
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxComplete(function(e, xhr, settings) {
            addLog('ajax complete', { url: (settings.url || '').substring(0, 60), status: xhr.status });
            if (xhr.status === 401) addLog('AJAX 401 - may cause redirect!', { url: settings.url });
        });
    }

    function getElementInfo(id) {
        var el = document.getElementById(id);
        if (!el) return { exists: false };
        var style = window.getComputedStyle(el);
        var rect = el.getBoundingClientRect();
        return {
            exists: true,
            display: style.display,
            visibility: style.visibility,
            opacity: style.opacity,
            height: rect.height,
            width: rect.width,
            top: rect.top,
            childCount: el.children ? el.children.length : 0
        };
    }

    function updatePanel() {
        var panel = document.getElementById('sys-settings-debug-panel');
        if (!panel) return;
        var body = panel.querySelector('.debug-panel-body');
        if (!body) return;

        var isIframe = window.self !== window.top;
        var content = document.getElementById('systemSettingsContent');
        var settingsGrid = document.querySelector('.settings-grid');
        var cards = document.querySelectorAll('.setting-card');

        var html = '<div class="debug-section"><strong>Context</strong><br>';
        html += 'URL: ' + window.location.href + '<br>';
        html += 'In iframe: ' + isIframe + '<br>';
        html += 'Body class: ' + (document.body.className || '') + '<br>';
        html += '</div>';

        html += '<div class="debug-section"><strong>DOM State</strong><br>';
        html += 'systemSettingsContent: ' + JSON.stringify(getElementInfo('systemSettingsContent')) + '<br>';
        html += 'settings-grid exists: ' + !!settingsGrid + '<br>';
        if (settingsGrid) {
            var s = window.getComputedStyle(settingsGrid);
            html += 'settings-grid display: ' + s.display + ', visibility: ' + s.visibility + '<br>';
            html += 'setting-card count: ' + cards.length + '<br>';
        }
        html += '</div>';

        html += '<div class="debug-section"><strong>Log (last 15)</strong><br>';
        var recent = log.slice(-15);
        recent.forEach(function(entry) {
            html += '<span class="debug-log">[' + (entry.t % 100000) + '] ' + entry.msg;
            if (entry.data) html += ' ' + JSON.stringify(entry.data).substring(0, 100);
            html += '</span><br>';
        });
        html += '</div>';

        body.innerHTML = html;
    }

    function init() {
        addLog('Debug panel loaded');
        addLog('URL params', Object.fromEntries(urlParams));

        var panel = document.createElement('div');
        panel.id = 'sys-settings-debug-panel';
        panel.innerHTML = '<div class="debug-panel-header">' +
            '<button type="button" id="debug-toggle">Debug ▼</button>' +
            '<button type="button" id="debug-refresh">Refresh</button>' +
            '</div>' +
            '<div class="debug-panel-body"></div>';
        document.body.appendChild(panel);

        var style = document.createElement('style');
        style.textContent = '#sys-settings-debug-panel{position:fixed;bottom:10px;right:10px;' +
            'width:380px;max-height:70vh;background:#1a1f2e;border:2px solid #6366f1;' +
            'border-radius:8px;z-index:99999;overflow:hidden;font:11px monospace;color:#e5e7eb;}' +
            '#sys-settings-debug-panel.collapsed{max-height:36px;}.debug-panel-body{overflow-y:auto;max-height:60vh;padding:8px;}' +
            '.debug-panel-header{display:flex;gap:6px;padding:6px;background:#0f172a;border-bottom:1px solid #334155;}' +
            '#sys-settings-debug-panel button{padding:4px 10px;background:#6366f1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;}' +
            '#sys-settings-debug-panel button:hover{background:#5155ec;}.debug-section{margin-bottom:10px;padding:6px;background:#0b1220;border-radius:4px;word-break:break-all;}' +
            '.debug-log{color:#94a3b8;font-size:10px;}';
        document.head.appendChild(style);

        document.getElementById('debug-toggle').onclick = function() {
            panel.classList.toggle('collapsed');
            this.textContent = panel.classList.contains('collapsed') ? 'Debug ▲' : 'Debug ▼';
        };
        document.getElementById('debug-refresh').onclick = updatePanel;

        setInterval(updatePanel, 2000);
        updatePanel();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
