/**
 * RATEB ERP Android app: tell the user when a newer APK is published.
 * Runs only inside the Capacitor app; the web ERP is unaffected.
 */
(function () {
    'use strict';

    var cap = window.Capacitor;
    if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) {
        return;
    }
    var appPlugin = cap.Plugins && cap.Plugins.App;
    var holder = document.getElementById('rateb-app-update');
    if (!appPlugin || typeof appPlugin.getInfo !== 'function' || !holder) {
        return;
    }
    var latest = parseInt(holder.getAttribute('data-latest') || '0', 10) || 0;
    var url = holder.getAttribute('data-url') || '';
    var dismissKey = 'rateb_app_update_dismissed';
    if (latest < 1 || url.indexOf('https://') !== 0 || document.getElementById('rateb-app-update-bar')) {
        return;
    }
    try {
        if (sessionStorage.getItem(dismissKey) === String(latest)) {
            return;
        }
    } catch (e) { /* storage unavailable */ }

    appPlugin.getInfo().then(function (info) {
        var current = parseInt((info && info.build) || '0', 10) || 0;
        if (current < 1 || current >= latest) {
            return;
        }
        var bar = document.createElement('div');
        bar.id = 'rateb-app-update-bar';
        bar.setAttribute('role', 'alert');
        bar.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:3000;display:flex;'
            + 'align-items:center;gap:10px;padding:12px 14px;border-radius:12px;background:#0f766e;color:#fff;'
            + 'box-shadow:0 8px 24px rgba(0,0,0,.35);font-size:14px';
        var text = document.createElement('span');
        text.style.flex = '1';
        text.textContent = holder.getAttribute('data-text') || '';
        var now = document.createElement('button');
        now.type = 'button';
        now.textContent = holder.getAttribute('data-now') || 'Update';
        now.style.cssText = 'background:#fff;color:#0f766e;font-weight:700;padding:6px 12px;border-radius:8px;border:0';
        now.addEventListener('click', function () {
            window.location.href = url;
        });
        var later = document.createElement('button');
        later.type = 'button';
        later.textContent = holder.getAttribute('data-later') || 'Later';
        later.style.cssText = 'background:transparent;border:0;color:#fff;opacity:.85';
        later.addEventListener('click', function () {
            try { sessionStorage.setItem(dismissKey, String(latest)); } catch (e) { /* ignore */ }
            bar.remove();
        });
        bar.appendChild(text);
        bar.appendChild(now);
        bar.appendChild(later);
        document.body.appendChild(bar);
    }).catch(function () { /* no update prompt */ });
})();
