<?php
if (class_exists(\Rateb\App\Core\ServerTiming::class)) {
    \Rateb\App\Core\ServerTiming::end('controller');
    \Rateb\App\Core\ServerTiming::mark('view', 'layout+sidebar+content');
}
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$erpRoute = rateb_current_erp_route();
$layoutAssets = class_exists(\Rateb\App\Support\ErpLayoutAssets::class)
    ? \Rateb\App\Support\ErpLayoutAssets::resolve($erpRoute)
    : [
        'charts' => true,
        'lineItems' => true,
        'formHybrid' => true,
        'fiscalYear' => true,
        'inventoryBatch' => true,
        'contractRenewal' => true,
        'cmsAdmin' => true,
        'entityDocuments' => true,
        'defer' => [],
    ];
$accountingActive = $erpRoute !== '' && preg_match('#(accounting|chart-of-accounts|coa-tree|journal-entries|entry-approval|voucher-approval|cash-vouchers|fiscal-periods|bank-accounts|cost-centers|cost-of-sales|trial-balance|journal-register|account-statement|partners-subsidiary-ledger|invoices|payments|subscriptions|reports/cost-analysis|reports/inventory-valuation|asset-depreciation)#', $erpRoute);
$modulePageMetrics = [];
$deferModulePageMetrics = false;
if (empty($hideModulePageStats) && $erpRoute !== '' && class_exists(\Rateb\App\Services\ModulePageStatsService::class)) {
    $deferModulePageMetrics = (new \Rateb\App\Services\ModulePageStatsService())->routeSupportsMetrics($erpRoute);
    if (!$deferModulePageMetrics && function_exists('rateb_module_page_metrics')) {
        $modulePageMetrics = rateb_module_page_metrics($erpRoute);
    }
}
$loadModulePageStatsCss = $deferModulePageMetrics || $modulePageMetrics !== [];
$navActive = static function (string $route) use ($erpRoute, $currentPath): bool {
    if ($erpRoute !== '') {
        if ($route === 'admin') {
            return $erpRoute === 'admin';
        }
        return $erpRoute === $route || strpos($erpRoute, $route . '/') === 0;
    }
    if ($route === 'admin') {
        return preg_match('#/admin/?$#', $currentPath) === 1;
    }
    return strpos($currentPath, $route) !== false;
};
if (isset($_GET['dismiss_approvals_alert']) && rateb_is_super_admin()) {
    \Rateb\App\Core\SessionManager::set('rateb_oversight_approvals_seen', rateb_oversight_pending_approvals_count());
}
if (isset($_GET['dismiss_subscription_alert'])
    && class_exists(\Rateb\App\Subscription\SubscriptionAlertService::class)) {
    (new \Rateb\App\Subscription\SubscriptionAlertService())->handleDismissRequest();
}
$approvalsOversightJs = $erpRoute !== '' && (
    str_starts_with($erpRoute, 'admin/oversight/approvals')
    || str_starts_with($erpRoute, 'admin/oversight/companies-approvals')
    || str_starts_with($erpRoute, 'admin/oversight/hr-approvals')
);
if ($approvalsOversightJs && rateb_is_super_admin()) {
    \Rateb\App\Core\SessionManager::set('rateb_oversight_approvals_seen', rateb_oversight_pending_approvals_count());
}
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="erp" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo Rateb\App\Core\View::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <style id="rateb-click-guard">
      /* Inline + !important so stale SW-cached CSS cannot freeze Admin clicks. */
      .rateb-content.is-nav-busy { pointer-events: auto !important; opacity: 0.92; }
    </style>
    <script>
    (function () {
      /* Watchdog: strip stuck is-nav-busy every 1.5s (multi-tab soft-nav hang). */
      setInterval(function () {
        try {
          document.querySelectorAll('.is-nav-busy').forEach(function (el) {
            el.classList.remove('is-nav-busy');
            el.removeAttribute('aria-busy');
          });
        } catch (e) { /* ignore */ }
      }, 1500);
    })();
    </script>
    <script>
    (function () {
        /* Block hard refresh / reload while offline — Ctrl+F5 bypasses SW and blacks the page. */
        function ratebOfflineNow() {
            try {
                if (navigator.onLine === false) {
                    return true;
                }
            } catch (e0) { /* ignore */ }
            try {
                var badge = document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
                if (badge && badge.classList.contains('is-offline')) {
                    return true;
                }
            } catch (e1) { /* ignore */ }
            return false;
        }
        function ratebBlockOfflineReloadToast() {
            try {
                var id = 'rateb-offline-reload-block-toast';
                var el = document.getElementById(id);
                if (!el) {
                    el = document.createElement('div');
                    el.id = id;
                    el.setAttribute('role', 'status');
                    el.style.cssText = 'position:fixed;bottom:14px;left:50%;transform:translateX(-50%);z-index:100000;'
                        + 'max-width:min(22rem,92vw);padding:10px 14px;background:#7f1d1d;color:#fee2e2;'
                        + 'font:13px/1.45 Tajawal,system-ui,sans-serif;border-radius:10px;text-align:center;'
                        + 'box-shadow:0 8px 24px rgba(0,0,0,.35)';
                    (document.body || document.documentElement).appendChild(el);
                }
                el.textContent = 'التحديث غير متاح دون اتصال — تبقى على النسخة المحفوظة.';
                el.hidden = false;
                clearTimeout(ratebBlockOfflineReloadToast._t);
                ratebBlockOfflineReloadToast._t = setTimeout(function () { el.hidden = true; }, 3200);
            } catch (eT) { /* ignore */ }
        }
        function ratebIsReloadKey(ev) {
            var key = String(ev.key || ev.code || '');
            var isF5 = key === 'F5' || key === 'f5';
            var isR = key === 'r' || key === 'R' || key === 'KeyR';
            return isF5 || ((ev.ctrlKey || ev.metaKey) && isR);
        }
        window.addEventListener('keydown', function (ev) {
            if (!ratebOfflineNow() || !ratebIsReloadKey(ev)) return;
            ev.preventDefault();
            ev.stopPropagation();
            if (typeof ev.stopImmediatePropagation === 'function') {
                ev.stopImmediatePropagation();
            }
            // Re-arm SW soft-offline latch before any accidental navigation.
            try {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'RATEB_CLOUD_OFFLINE' });
                }
            } catch (eSw) { /* ignore */ }
            ratebBlockOfflineReloadToast();
        }, true);
        try {
            var _reload = window.location.reload.bind(window.location);
            window.location.reload = function () {
                if (ratebOfflineNow()) {
                    try {
                        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                            navigator.serviceWorker.controller.postMessage({ type: 'RATEB_CLOUD_OFFLINE' });
                        }
                    } catch (eSw2) { /* ignore */ }
                    ratebBlockOfflineReloadToast();
                    return;
                }
                return _reload.apply(window.location, arguments);
            };
        } catch (eRel) { /* ignore */ }
        try {
            window.addEventListener('pagehide', function () {
                if (!ratebOfflineNow()) return;
                try {
                    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                        navigator.serviceWorker.controller.postMessage({ type: 'RATEB_CLOUD_OFFLINE' });
                    }
                } catch (ePh) { /* ignore */ }
            });
        } catch (ePh2) { /* ignore */ }
        // No beforeunload trap — would block offline sidebar / soft-nav.
    })();
    </script>
    <script>
    (function () {
        /* EARLY: hold Admin nav clicks until erp-nav-instant boots.
         * Without this, clicks before soft-nav loads do full navigation → black tab for minutes
         * while F5 paints from SW cache. */
        window.__RATEB_PENDING_NAV__ = window.__RATEB_PENDING_NAV__ || '';
        window.__RATEB_NAV_READY__ = false;
        window.__ratebGoPosRegister = function (a, ev) {
            if (!a) {
                return false;
            }
            if (ev && (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey)) {
                return false;
            }
            var raw = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
            var label = String(a.textContent || '').replace(/\s+/g, ' ');
            var path = '';
            try {
                path = new URL(raw, location.href).pathname.replace(/\/+$/, '');
            } catch (ePath) {
                path = String(raw || '');
            }
            var isReg = a.getAttribute('data-pos-open-register') === '1'
                || /شاشة البيع/.test(label)
                || /\/(?:admin\/ops\/)?pos(?:\/register|\/biometric)?$/.test(path);
            if (!isReg) {
                return false;
            }
            if (ev) {
                ev.preventDefault();
                try { ev.stopImmediatePropagation(); } catch (eSip) { ev.stopPropagation(); }
            }
            var pub = (location.pathname.match(/^(.*\/public)/i) || [null, '/rateb-erp/public'])[1];
            var q = '';
            try {
                var src = new URL(raw, location.href);
                q = src.search || location.search || '';
            } catch (eQ) {
                q = location.search || '';
            }
            var dest = location.origin + pub + '/admin/ops/pos/register' + q;
            try {
                var du = new URL(dest);
                du.searchParams.set('rateb_live', '1');
                du.searchParams.set('_nav', String(Date.now()));
                dest = du.href;
            } catch (eLive) { /* keep dest */ }
            try {
                window.top.location.assign(dest);
            } catch (eTop) {
                location.assign(dest);
            }
            return true;
        };
        document.addEventListener('click', function (ev) {
            try {
                var aEarly = ev.target && ev.target.closest ? ev.target.closest('a[href], a[data-rateb-href]') : null;
                if (aEarly && window.__ratebGoPosRegister(aEarly, ev)) {
                    return;
                }
                if (window.__RATEB_NAV_READY__) {
                    return;
                }
                if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
                    return;
                }
                var a = aEarly;
                if (!a) {
                    return;
                }
                if (a.target && a.target !== '' && a.target !== '_self') {
                    return;
                }
                if (a.hasAttribute('download')) {
                    return;
                }
                if (a.getAttribute('data-rateb-full-nav') === '1') {
                    var fullRaw = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
                    if (fullRaw && fullRaw !== '#') {
                        ev.preventDefault();
                        try { ev.stopImmediatePropagation(); } catch (eSipFn) { ev.stopPropagation(); }
                        location.href = new URL(fullRaw, location.href).href;
                    }
                    return;
                }
                var raw = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
                if (!raw || raw === '#' || String(raw).indexOf('javascript:') === 0) {
                    return;
                }
                var u = new URL(raw, location.href);
                if (u.origin !== location.origin) {
                    return;
                }
                if (/\/rateb-platform-catalog\//i.test(u.pathname) || PLATFORM_CATALOG_SSO_RE.test(u.pathname)) {
                    ev.preventDefault();
                    try { ev.stopImmediatePropagation(); } catch (eSipCat) { ev.stopPropagation(); }
                    location.href = u.href;
                    return;
                }
                if (!/\/admin(\/|$)/i.test(u.pathname)) {
                    return;
                }
                // Full document nav only for selling shell / logout / explicit flag.
                // Other POS admin pages soft-nav and keep the sidebar.
                if (a.getAttribute('data-rateb-full-nav') === '1'
                    || /\/(?:admin\/ops\/)?pos(?:\/register)?\/?(?:$|\?)/i.test(u.pathname)
                    || /\/(logout|login|password)(\/|$)/i.test(u.pathname)) {
                    ev.preventDefault();
                    try { ev.stopImmediatePropagation(); } catch (eSip) { ev.stopPropagation(); }
                    location.href = u.href;
                    return;
                }
                var cur = location.pathname.replace(/\/+$/, '');
                var next = u.pathname.replace(/\/+$/, '');
                if (cur === next && u.search === location.search) {
                    return;
                }
                ev.preventDefault();
                ev.stopPropagation();
                window.__RATEB_PENDING_NAV__ = u.href;
                try {
                    document.querySelectorAll('a.rateb-nav-link').forEach(function (link) {
                        link.classList.remove('active', 'is-nav-pending');
                    });
                    a.classList.add('active', 'is-nav-pending');
                    var main = document.querySelector('#rateb-main-content, main.rateb-content');
                    if (main) {
                        main.classList.add('is-nav-busy');
                        // HARD deadline — early path used to leave pointer-events:none forever
                        // when erp-nav-instant was slow/stuck (blank UI, can't click tabs).
                        clearTimeout(window.__RATEB_EARLY_BUSY_T__);
                        window.__RATEB_EARLY_BUSY_T__ = setTimeout(function () {
                            try {
                                main.classList.remove('is-nav-busy');
                                main.removeAttribute('aria-busy');
                                if (!window.__RATEB_NAV_READY__ && window.__RATEB_PENDING_NAV__) {
                                    var go = window.__RATEB_PENDING_NAV__;
                                    window.__RATEB_PENDING_NAV__ = '';
                                    location.href = go;
                                }
                            } catch (eClear) { /* ignore */ }
                        }, 2000);
                    }
                } catch (eUi) { /* ignore */ }
            } catch (eEarly) { /* ignore */ }
        }, true);
    })();
    </script>
    <script>
    (function () {
        /* Critical offline Save — runs even if deferred JS fails to load (fixes Save→dashboard). */
        var KEY = 'rateb_deferred_http_forms_v2';
        function offlineNow() {
            /* Queue only when browser has no network — ignore soft offline badge. */
            try {
                return navigator.onLine === false;
            } catch (e) {
                return false;
            }
        }
        function readList() {
            try {
                var list = JSON.parse(localStorage.getItem(KEY) || '[]');
                return Array.isArray(list) ? list : [];
            } catch (e2) { return []; }
        }
        function writeList(list) {
            try { localStorage.setItem(KEY, JSON.stringify(list || [])); } catch (e3) {}
        }
        function serialize(form) {
            var out = {};
            if (!form || !form.elements) return out;
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el || !el.name || el.disabled) return;
                var n = String(el.name);
                if (/^_csrf$/i.test(n)) return;
                var t = String(el.type || '').toLowerCase();
                if (t === 'file' || t === 'submit' || t === 'button' || t === 'password') return;
                if ((t === 'checkbox' || t === 'radio') && !el.checked) return;
                if (Object.prototype.hasOwnProperty.call(out, n)) {
                    if (!Array.isArray(out[n])) out[n] = [out[n]];
                    out[n].push(el.value);
                } else out[n] = el.value;
            });
            return out;
        }
        function toast(msg) {
            try {
                var el = document.getElementById('rateb-offline-nav-toast');
                if (!el) {
                    el = document.createElement('div');
                    el.id = 'rateb-offline-nav-toast';
                    el.style.cssText = 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;background:#14532d;color:#bbf7d0;padding:.65rem 1rem;border-radius:8px;font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center';
                    (document.body || document.documentElement).appendChild(el);
                }
                el.textContent = msg;
                el.hidden = false;
                clearTimeout(el.__h);
                el.__h = setTimeout(function () { try { el.hidden = true; } catch (e) {} }, 5000);
            } catch (eT) {}
        }
        document.addEventListener('submit', function (ev) {
            if (!offlineNow()) return;
            var form = ev.target;
            if (!form || form.tagName !== 'FORM') return;
            if (String(form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            var action = String(form.getAttribute('action') || location.pathname || '');
            if (/\/(wipe|export|pdf|excel|csv|close[-_]?period|gl[-_]?post|journal[-_]?post)(\/|$|\?)/i.test(action + ' ' + location.pathname)) {
                ev.preventDefault();
                toast('هذا الإجراء يحتاج إنترنت.');
                return;
            }
            // If full nav-guard is present it will handle — avoid double queue.
            if (window.RatebOfflineNavGuard && window.RatebOfflineNavGuard.build) return;
            ev.preventDefault();
            ev.stopPropagation();
            try { ev.stopImmediatePropagation(); } catch (eS) {}
            try {
                var fields = serialize(form);
                if (!Object.keys(fields).length) {
                    toast('تعذر حفظ النموذج أوفلاين.');
                    return;
                }
                var url;
                try { url = new URL(action || location.href, location.href).href; } catch (eU) { url = location.href; }
                var list = readList();
                list.push({
                    id: 'inl-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
                    url: url,
                    path: location.pathname || '',
                    fields: fields,
                    created_at: Date.now(),
                    via: 'inline-critical'
                });
                writeList(list);
                toast('تم الحفظ بنجاح — بانتظار المزامنة (' + list.length + ')');
                try {
                    var flash = document.createElement('div');
                    flash.className = 'alert alert-success rateb-flash';
                    flash.setAttribute('role', 'alert');
                    flash.textContent = 'تم الحفظ بنجاح — بانتظار المزامنة';
                    var host = document.querySelector('.rateb-main, main, .rateb-content') || document.body;
                    if (host) host.insertBefore(flash, host.firstChild);
                } catch (eF) {}
            } catch (eSave) {
                toast('تعذر الحفظ أوفلاين.');
            }
        }, true);
    })();
    </script>
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_erp_theme') || localStorage.getItem('rateb_theme') || 'dark';
            var bs = mode === 'auto'
                ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : (mode === 'light' ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
            window.__RATEB_ERP_THEME_BS__ = bs;
        } catch (e) {}
    })();
    </script>
    <?php if (!(function_exists('rateb_is_local_appliance_host') && rateb_is_local_appliance_host())) { ?>
    <script>
    (function () {
      /* Head-early: keep SW alive + rescue CSS from Cache API when offline / uncontrolled. */
      var build = <?php echo json_encode(defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1'); ?>;
      function publicBase() {
        try {
          var m = String(location.pathname || '').match(/^(.*\/public\/)/i);
          if (m && m[1]) return m[1];
        } catch (e0) {}
        return '/rateb-erp/public/';
      }
      function matchAnyCache(url) {
        if (!window.caches) return Promise.resolve(null);
        var keys = [url];
        try {
          var u = new URL(url, location.href);
          keys.push(u.origin + u.pathname, u.href);
        } catch (e1) {}
        // Fixed buckets + first-hit parallel (sequential chains stalled offline paint).
        var names = [
          'rateb-erp-coexist-v34',
          'rateb-erp-ops-pages-v36',
          'rateb-erp-ops-pages-v35',
          'rateb-erp-ops-pages-v34',
          'rateb-pos-assets-v8'
        ];
        var attempts = [];
        names.forEach(function (name) {
          attempts.push(
            caches.open(name).then(function (c) {
              var inner = Promise.resolve(null);
              keys.forEach(function (k) {
                inner = inner.then(function (h) {
                  return h || c.match(k).then(function (m) {
                    return m || c.match(k, { ignoreSearch: true }).catch(function () { return null; });
                  });
                });
              });
              return inner;
            }).catch(function () { return null; })
          );
        });
        return new Promise(function (resolve) {
          var pending = attempts.length;
          var done = false;
          if (!pending) { resolve(null); return; }
          attempts.forEach(function (p) {
            p.then(function (hit) {
              if (done) return;
              if (hit) { done = true; resolve(hit); return; }
              pending -= 1;
              if (pending === 0) resolve(null);
            });
          });
        });
      }
      window.__RATEB_MATCH_ANY_CACHE__ = matchAnyCache;
      function rewriteCssUrls(css, cssHref) {
        var baseHref = cssHref;
        try {
          baseHref = new URL(cssHref, location.href).href;
        } catch (eB) {}
        return String(css || '').replace(/url\(\s*(['"]?)([^)'"]+)\1\s*\)/gi, function (all, q, raw) {
          var path = String(raw || '').trim();
          if (!path || /^data:/i.test(path) || /^https?:\/\//i.test(path) || path.charAt(0) === '/') {
            return all;
          }
          try {
            var abs = new URL(path, baseHref).href;
            return 'url(' + q + abs + q + ')';
          } catch (eU) {
            return all;
          }
        });
      }
      function rescueStyles() {
        try { if (navigator.onLine !== false) return; } catch (e2) { return; }
        Array.prototype.forEach.call(document.querySelectorAll('link[rel="stylesheet"][href]'), function (link) {
          if (link.getAttribute('data-rateb-rescue') === '1') return;
          var href = link.getAttribute('href');
          if (!href) return;
          matchAnyCache(href).then(function (res) {
            if (!res) return null;
            return res.text().then(function (css) {
              if (!css || css.length < 40) return;
              link.setAttribute('data-rateb-rescue', '1');
              var style = document.createElement('style');
              style.textContent = rewriteCssUrls(css, href);
              document.head.appendChild(style);
            });
          }).catch(function () {});
        });
      }
      window.__RATEB_RESCUE_STYLES__ = rescueStyles;
      /* Head: never SKIP_WAITING/CLIENTS_CLAIM on offline paint — activates waiting SW mid-click. */
      document.addEventListener('click', function (ev) {
        try {
          // Only help when SW is missing — never hijack edit/create (broke table buttons).
          if (navigator.serviceWorker && navigator.serviceWorker.controller) return;
          if (navigator.onLine !== false) {
            var badge = document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (!badge || badge.classList.contains('is-online')) return;
          }
          var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
          if (!a) return;
          var u = new URL(a.href, location.href);
          if (u.origin !== location.origin) return;
          if (!/\/admin(\/|$)/i.test(u.pathname) && !/\/pos(\/|$)/i.test(u.pathname)) return;
          // Create/edit with SW missing: try Cache API snapshot instead of Chrome interstitial.
          ev.preventDefault();
          ev.stopPropagation();
          var keys = [u.href, u.origin + u.pathname, u.origin + u.pathname.replace(/\/+$/, '')];
          if (/\/admin\/ops\//i.test(u.pathname)) keys.push(u.origin + u.pathname.replace(/\/admin\/ops\//i, '/admin/'));
          else if (/\/admin\//i.test(u.pathname)) keys.push(u.origin + u.pathname.replace(/\/admin\//i, '/admin/ops/'));
          // For /id/edit prefer parent list cache.
          if (/\/\d+\/(edit|show|view)(\/|$)/i.test(u.pathname)) {
            var parent = u.pathname.replace(/\/\d+\/(edit|show|view).*$/i, '').replace(/\/+$/, '');
            if (parent) keys.unshift(u.origin + parent);
          }
          var p = Promise.resolve(null);
          keys.forEach(function (k) { p = p.then(function (h) { return h || matchAnyCache(k); }); });
          p.then(function (res) {
            if (!res) { alert('الصفحة غير محفوظة أوفلاين — وصّل النت وافتحها مرة.'); return; }
            return res.text().then(function (html) {
              if (!html || html.length < 400) { alert('الصفحة غير محفوظة أوفلاين.'); return; }
              document.open(); document.write(html); document.close();
              setTimeout(rescueStyles, 30);
            });
          }).catch(function () { alert('الصفحة غير محفوظة أوفلاين.'); });
        } catch (e4) {}
      }, true);
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rescueStyles);
      else setTimeout(rescueStyles, 0);
      window.addEventListener('pageshow', rescueStyles);
      setTimeout(rescueStyles, 800);
    })();
    </script>
    <?php } ?>
    <?php /* PERF Fix2: console-quiet after load idle — not on critical/DCL path */ ?>
    <script>
    (function () {
      function loadQuiet() {
        var s = document.createElement('script');
        s.src = <?php echo json_encode(rateb_asset('js/rateb-console-quiet.js'), JSON_UNESCAPED_SLASHES); ?>;
        document.head.appendChild(s);
      }
      function schedule() {
        if (window.requestIdleCallback) {
          window.requestIdleCallback(loadQuiet, { timeout: 5000 });
        } else {
          setTimeout(loadQuiet, 1500);
        }
      }
      if (document.readyState === 'complete') schedule();
      else window.addEventListener('load', schedule, { once: true });
    })();
    </script>
    <title><?php echo Rateb\App\Core\View::escape($title ?? RATEB_APP_NAME); ?> | <?php echo __('rateb_erp'); ?></title>
    <link rel="icon" href="<?php echo rateb_public_url('favicon.ico'); ?>" type="image/svg+xml">
    <link rel="manifest" href="<?php echo rateb_public_url('manifest.webmanifest'); ?>">
    <meta name="theme-color" content="#0f1117">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RATEB ERP">
    <link rel="apple-touch-icon" href="<?php echo rateb_public_url('assets/pwa/erp-icon-192.png'); ?>">
    <?php
    /* PERF-P3 / Fix5–7 / Fix11: one tiny blocking shell stylesheet; async CSS via preload→swap.
     * Tajawal 400 inlined (font-display:optional + preload) — no late text swap.
     * FA shell inlined; solid woff2 preloaded; glyph pack (no @font-face) after interaction.
     * Bootstrap (~233KB): preload only; promote after paint. */
    $ratebThemeDarkCss = rateb_asset('css/dark.css');
    $ratebThemeLightCss = rateb_asset('css/light.css');
    $ratebTajawalRestCss = rateb_tajawal_font_rest_css();
    $ratebTajawal400Woff = rateb_vendor_asset('fonts/tajawal/tajawal-400.woff2');
    $ratebFaShellCss = rateb_fontawesome_css();
    /* Fix11: glyphs only — all.min.css re-registers faces with font-display:block + brands/regular. */
    $ratebFaFullCss = rateb_vendor_asset('fontawesome/6.5.2/css/glyphs.min.css');
    $ratebFaSolidWoff = rateb_vendor_asset('fontawesome/6.5.2/webfonts/fa-solid-900.woff2');
    $ratebBootstrapCss = rateb_bootstrap_css();
    $ratebFaShellInline = '';
    $ratebFaShellPath = RATEB_ROOT . '/public/assets/vendor/fontawesome/6.5.2/css/shell.min.css';
    if (is_readable($ratebFaShellPath)) {
        $ratebFaShellInline = (string) file_get_contents($ratebFaShellPath);
        $ratebFaShellInline = str_replace(
            'url("../webfonts/fa-solid-900.woff2")',
            'url("' . $ratebFaSolidWoff . '")',
            $ratebFaShellInline
        );
    }
    $ratebAsyncStyles = [
        /* Fix7: Bootstrap removed from this wave — see post-paint promote below. */
        rateb_asset('css/variables.css'),
        rateb_asset('css/main.css'),
        rateb_asset('css/components.css'),
        $ratebThemeDarkCss,
        rateb_asset('css/rtl.css'),
    ];
    if (!empty($loadModulePageStatsCss) || !empty($layoutAssets['charts'])) {
        $ratebAsyncStyles[] = rateb_asset('css/dashboard.css');
    }
    if ($dir === 'rtl') {
        $ratebAsyncStyles[] = rateb_asset('css/ar-typography.css');
    }
    // Soft-nav cannot reliably apply <link> tags from swapped HTML — keep in shell when already on route.
    // Other pages inject via erp-nav-instant ensureAgentAppsCss (see __RATEB_MODULE_CSS__).
    if ($erpRoute !== '' && (
        str_starts_with($erpRoute, 'admin/agent-apps')
        || str_starts_with($erpRoute, 'admin/mobile-apps')
        || str_starts_with($erpRoute, 'admin/ops/agent-apps')
        || str_starts_with($erpRoute, 'admin/ops/mobile-apps')
    )) {
        $ratebAsyncStyles[] = rateb_asset('css/agent-apps.css');
    }
    ?>
    <link id="rateb-critical-shell" href="<?php echo rateb_asset('css/critical-shell.css'); ?>" rel="stylesheet">
    <?php /* Preload body + icon fonts only — rest weights must not compete with first paint. */ ?>
    <link rel="preload" href="<?php echo Rateb\App\Core\View::escape($ratebTajawal400Woff); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo Rateb\App\Core\View::escape($ratebFaSolidWoff); ?>" as="font" type="font/woff2" crossorigin>
    <?php /* Fix7: start Bootstrap download immediately; do NOT promote until after first paint. */ ?>
    <link rel="preload" href="<?php echo Rateb\App\Core\View::escape($ratebBootstrapCss); ?>" as="style" id="rateb-bootstrap-css">
    <style id="rateb-tajawal-critical-face">
    /* Inline @font-face — optional + preload: apply if ready, never late-swap Arabic body text. */
    @font-face {
      font-family: 'Tajawal';
      font-style: normal;
      font-weight: 400;
      font-display: optional;
      src: url('<?php echo Rateb\App\Core\View::escape($ratebTajawal400Woff); ?>') format('woff2');
    }
    </style>
    <?php if ($ratebFaShellInline !== '') { ?>
    <style id="rateb-fa-shell"><?php echo $ratebFaShellInline; ?></style>
    <?php } else { ?>
    <link rel="preload" href="<?php echo Rateb\App\Core\View::escape($ratebFaShellCss); ?>" as="style" id="rateb-fa-shell" onload="this.onload=null;this.rel='stylesheet'">
    <?php } ?>
    <script>
    (function () {
      /* PERF-P3 / Fix5–7 / Fix11: preload → stylesheet swap (non-blocking). */
      var sheets = <?php echo json_encode(array_values(array_filter($ratebAsyncStyles, static function ($h) use ($ratebThemeDarkCss, $ratebThemeLightCss) {
          return $h !== $ratebThemeDarkCss && $h !== $ratebThemeLightCss;
      })), JSON_UNESCAPED_SLASHES); ?>;
      var themeDark = <?php echo json_encode($ratebThemeDarkCss, JSON_UNESCAPED_SLASHES); ?>;
      var themeLight = <?php echo json_encode($ratebThemeLightCss, JSON_UNESCAPED_SLASHES); ?>;
      var tajawalRest = <?php echo json_encode($ratebTajawalRestCss, JSON_UNESCAPED_SLASHES); ?>;
      var faShell = <?php echo json_encode($ratebFaShellCss, JSON_UNESCAPED_SLASHES); ?>;
      var faFull = <?php echo json_encode($ratebFaFullCss, JSON_UNESCAPED_SLASHES); ?>;
      var bootstrapHref = <?php echo json_encode($ratebBootstrapCss, JSON_UNESCAPED_SLASHES); ?>;
      var bs = window.__RATEB_ERP_THEME_BS__ || 'dark';
      function swapIn(href, id) {
        if (!href) return;
        var link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'style';
        link.href = href;
        if (id) {
          link.id = id;
          link.setAttribute('data-dark-href', themeDark);
          link.setAttribute('data-light-href', themeLight);
        }
        link.onload = function () {
          link.onload = null;
          link.rel = 'stylesheet';
        };
        document.head.appendChild(link);
        // Offline: do not wait 3s for onload (hanging preload stalls first paint).
        var swapMs = 3000;
        try { if (navigator.onLine === false) swapMs = 0; } catch (eOff) { swapMs = 0; }
        setTimeout(function () {
          if (link.rel === 'preload') {
            link.rel = 'stylesheet';
          }
        }, swapMs);
      }
      /* Fix6/11: promote FA shell link fallback if not inlined. */
      (function ensureFaShell() {
        var el = document.getElementById('rateb-fa-shell');
        if (el && el.tagName === 'LINK' && el.rel === 'preload') {
          var promote = function () { if (el.rel === 'preload') el.rel = 'stylesheet'; };
          el.addEventListener('load', function () { el.onload = null; promote(); });
          setTimeout(promote, 3000);
        } else if (!el && faShell) {
          swapIn(faShell, 'rateb-fa-shell');
        }
      })();
      /* Fix7: apply Bootstrap only after first paint (or offline / safety timeout). */
      function promoteBootstrap() {
        var el = document.getElementById('rateb-bootstrap-css');
        if (!el) {
          if (!bootstrapHref) return;
          el = document.createElement('link');
          el.id = 'rateb-bootstrap-css';
          el.rel = 'stylesheet';
          el.href = bootstrapHref;
          document.head.appendChild(el);
          return;
        }
        if (el.rel !== 'stylesheet') {
          el.rel = 'stylesheet';
        }
      }
      function scheduleBootstrap() {
        var offline = false;
        try { offline = navigator.onLine === false; } catch (eOff) { offline = false; }
        if (offline) {
          promoteBootstrap();
          return;
        }
        if (window.requestAnimationFrame) {
          window.requestAnimationFrame(function () {
            window.requestAnimationFrame(promoteBootstrap);
          });
        } else {
          setTimeout(promoteBootstrap, 0);
        }
        setTimeout(promoteBootstrap, 2500);
      }
      scheduleBootstrap();
      swapIn(bs === 'light' ? themeLight : themeDark, 'rateb-theme-css');
      sheets.forEach(function (href) { swapIn(href); });
      /* Fix5/11: non-critical Tajawal after interaction or long idle — do not inflate fonts.ready. */
      function loadTajawalRest() {
        if (!tajawalRest || document.getElementById('rateb-tajawal-rest')) {
          return;
        }
        var link = document.createElement('link');
        link.id = 'rateb-tajawal-rest';
        link.rel = 'stylesheet';
        link.href = tajawalRest;
        document.head.appendChild(link);
      }
      /* Fix6/11: FA glyphs (no new @font-face) after interaction — shell icons already live. */
      function loadFaFull() {
        if (!faFull || document.getElementById('rateb-fa-full')) {
          return;
        }
        var link = document.createElement('link');
        link.id = 'rateb-fa-full';
        link.rel = 'stylesheet';
        link.href = faFull;
        document.head.appendChild(link);
      }
      function scheduleNonCriticalFonts() {
        var ran = false;
        var go = function () {
          if (ran) return;
          ran = true;
          if (window.requestIdleCallback) {
            window.requestIdleCallback(function () {
              loadTajawalRest();
              loadFaFull();
            }, { timeout: 8000 });
          } else {
            setTimeout(function () {
              loadTajawalRest();
              loadFaFull();
            }, 2500);
          }
        };
        ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (ev) {
          window.addEventListener(ev, go, { once: true, passive: true });
        });
        window.addEventListener('rateb:nav:afterEnter', go, { once: true });
        var idleStart = function () {
          if (window.requestIdleCallback) {
            window.requestIdleCallback(go, { timeout: 10000 });
          } else {
            setTimeout(go, 4000);
          }
        };
        if (document.readyState === 'complete') idleStart();
        else window.addEventListener('load', idleStart, { once: true });
      }
      scheduleNonCriticalFonts();
    })();
    </script>
    <noscript>
      <link href="<?php echo rateb_tajawal_font_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_tajawal_font_rest_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_vendor_asset('fontawesome/6.5.2/css/glyphs.min.css'); ?>" rel="stylesheet">
      <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
      <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
      <link href="<?php echo rateb_asset('css/components.css'); ?>" rel="stylesheet">
      <link href="<?php echo $ratebThemeDarkCss; ?>" rel="stylesheet">
      <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
    </noscript>
    <?php if ($dir === 'rtl') { ?>
    <style id="rateb-rtl-ar-fix">html[dir="rtl"] .rateb-app,html[dir="rtl"] .rateb-app *,html[dir="rtl"] body.rateb-app *{text-transform:none!important;letter-spacing:normal!important;font-feature-settings:normal!important}</style>
    <?php } ?>
    <script>
    /* Boot marks — First Paint / TTI proxies (no network). */
    window.__RATEB_BOOT__ = { t0: (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now() };
    window.__RATEB_MODULE_CSS__ = {
        agentApps: <?php echo json_encode(rateb_asset('css/agent-apps.css'), JSON_UNESCAPED_SLASHES); ?>
    };
    </script>
</head>
<?php
?>
<body class="rateb-app<?php echo $dir === 'rtl' ? ' rateb-rtl' : ''; ?>"
    data-rateb-media-json="<?php echo Rateb\App\Core\View::escape(rateb_url('admin/cms/media/json')); ?>"
    data-rateb-tinymce-upload="<?php echo Rateb\App\Core\View::escape(rateb_url('admin/cms/media/tinymce-upload')); ?>"
    data-rateb-cms-media="<?php echo Rateb\App\Core\View::escape(__('cms_media')); ?>"
    data-rateb-cms-no-images="<?php echo Rateb\App\Core\View::escape(__('cms_no_images')); ?>"
    data-rateb-cms-pick-image="<?php echo Rateb\App\Core\View::escape(__('cms_pick_image')); ?>"
    data-rateb-cms-media-failed="<?php echo Rateb\App\Core\View::escape(__('cms_media_load_failed')); ?>"
    data-rateb-date-hint-date="<?php echo Rateb\App\Core\View::escape(__('date_format_hint')); ?>"
    data-rateb-date-hint-datetime="<?php echo Rateb\App\Core\View::escape(__('datetime_format_hint')); ?>"
    data-rateb-date-hint-time="<?php echo Rateb\App\Core\View::escape(__('time_format_hint')); ?>"
    data-rateb-date-hint-month="<?php echo Rateb\App\Core\View::escape(__('month_format_hint')); ?>"
    data-rateb-date-hint-week="<?php echo Rateb\App\Core\View::escape(__('week_format_hint')); ?>">
<div class="rateb-wrapper">
    <aside class="rateb-sidebar" id="rateb-sidebar">
        <div class="rateb-sidebar-brand">
            <i class="fas fa-hospital"></i>
            <span><?php echo __('rateb_erp'); ?></span>
        </div>
        <nav>
            <?php require RATEB_ROOT . '/views/partials/sidebar-nav.php'; ?>
            <?php if (rateb_nav_can('dashboard.view', 'dashboard')) { ?>
            <button type="button"
                class="rateb-nav-link<?php echo $navActive('admin') && !$accountingActive ? ' active' : ''; ?>"
                data-rateb-href="<?php echo rateb_url('admin'); ?>"
                data-rateb-soft-nav="1"
                data-rateb-dashboard-nav="1">
                <i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span>
            </button>
            <?php } ?>
            <?php if (function_exists('rateb_hr_mobile_console_accessible') && rateb_hr_mobile_console_accessible()) { ?>
            <a href="<?php echo rateb_url('admin/hr-mobile'); ?>" data-rateb-href="<?php echo rateb_url('admin/hr-mobile'); ?>" class="rateb-nav-link<?php echo $navActive('admin/hr-mobile') ? ' active' : ''; ?>" onclick="return false;">
                <i class="fas fa-mobile-screen-button"></i><span><?php echo __('hr_mobile_nav'); ?></span>
            </a>
            <?php } ?>
            <?php
            $platformCatalogNavPartial = RATEB_ROOT . '/views/partials/platform-catalog-nav-link.php';
            if (is_file($platformCatalogNavPartial)) {
                require $platformCatalogNavPartial;
            }
            ?>
            <?php if (rateb_is_super_admin() && rateb_is_platform_oversight_host()) { ?>
            <?php
            $oversightCounts = rateb_oversight_menu_counts();
            $oversightLinkBadges = [
                'admin/oversight/companies-approvals' => rateb_nav_can('companies.view') ? (int) (($oversightCounts['company_pending'] ?? 0)) : 0,
                'admin/oversight/approvals' => rateb_nav_can('workflows.view') ? (int) ($oversightCounts['approvals'] ?? 0) : 0,
                'admin/oversight/hr-approvals' => rateb_nav_can('workflows.view') ? (int) ($oversightCounts['hr'] ?? 0) : 0,
                'admin/oversight/procurement' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['procurement'] ?? 0) : 0,
                'admin/oversight/rfq' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['rfq'] ?? 0) : 0,
                'admin/oversight/inventory' => rateb_nav_can('inventory.manage') ? (int) ($oversightCounts['inventory'] ?? 0) : 0,
                'admin/oversight/supplier-evaluations' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['supplier_evaluations'] ?? 0) : 0,
            ];
            $adminSection(__('admin_oversight_section'), [
                ['type' => 'link', 'link' => ['admin/companies', 'companies', 'fa-building', 'companies.view']],
                ['type' => 'link', 'link' => ['admin/company-permissions', 'company_permissions', 'fa-toggle-on', 'companies.view']],
                ['type' => 'link', 'link' => ['admin/agency-updates', 'agency_erp_push_title', 'fa-cloud-upload-alt', 'companies.manage']],
                ['type' => 'link', 'link' => ['admin/oversight/companies-approvals', 'companies_approvals_oversight', 'fa-building-circle-check', 'companies.view']],
                [
                    'type' => 'subgroup',
                    'label' => __('branches'),
                    'icon' => 'fa-code-branch',
                    'gate' => ['branches.view', 'branches'],
                    'links' => [
                        ['admin/ops/branch-dashboard', 'branch_dashboard', 'fa-code-branch', 'branch.dashboard.view', 'branches'],
                        ['admin/ops/branch-financial', 'branch_financial_reports', 'fa-file-invoice-dollar', 'branch.financial.pl', 'accounting'],
                        ['admin/ops/branch-dashboard/compare', 'branch_comparison', 'fa-scale-balanced', 'branch.dashboard.compare', 'branches'],
                        ['admin/ops/branch-dashboard/reports', 'branch_reports', 'fa-chart-column', 'branch.reports.view', 'branches'],
                        ['admin/ops/branch-transfers', 'branch_transfers', 'fa-shuffle', 'branch.transfers.view', 'branches'],
                    ],
                ],
                [
                    'type' => 'subgroup',
                    'label' => __('admin_oversight_monitoring'),
                    'icon' => 'fa-eye',
                    'links' => [
                        ['admin/subscriptions', 'subscriptions', 'fa-credit-card', 'subscriptions.manage'],
                        ['admin/subscription-engine', 'subscription_engine_admin', 'fa-heartbeat', 'subscriptions.view'],
                        ['admin/oversight/approvals', 'approvals_oversight', 'fa-check-double', 'workflows.view'],
                        ['admin/oversight/hr-approvals', 'hr_approvals_oversight', 'fa-user-check', 'workflows.view'],
                        ['admin/oversight/procurement', 'procurement_oversight', 'fa-chart-column', 'procurement.manage'],
                        ['admin/oversight/rfq', 'rfq_oversight', 'fa-chart-column', 'procurement.manage'],
                        ['admin/oversight/inventory', 'inventory_oversight', 'fa-chart-column', 'inventory.manage'],
                        ['admin/oversight/supplier-evaluations', 'supplier_evaluations_oversight', 'fa-star-half-stroke', 'procurement.manage'],
                        ['admin/oversight/workflows', 'workflow_definitions', 'fa-diagram-project', 'workflows.view'],
                        ['admin/reports', 'reports', 'fa-chart-pie', 'reports.view'],
                        ['admin/settings', 'settings', 'fa-gear', 'settings.manage'],
                    ],
                ],
            ], 'fa-shield-halved', (int) ($oversightCounts['total'] ?? 0), $oversightLinkBadges, 'rateb-nav-badge--pending');
            ?>
            <?php } ?>
            <?php require RATEB_ROOT . '/views/partials/sidebar-ops-nav.php'; ?>
            <?php
            // Agent Apps routes are platform SuperAdmin-only (rateb_admin_mw).
            // Do not show tenant links that would 403 — SuperAdmin gets the group below.
            ?>
            <?php if (rateb_is_super_admin()) { ?>
            <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
            <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" data-rateb-href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-nav-link<?php echo $navActive('admin/executive-dashboard') ? ' active' : ''; ?>" onclick="return false;">
                <i class="fas fa-gauge-high"></i><span><?php echo __('executive_dashboard'); ?></span>
            </a>
            <?php } ?>
            <?php
            if (rateb_is_platform_oversight_host() && rateb_nav_can('cms.view')) {
                $cmsNewLeads = rateb_nav_can('cms.leads', 'cms') ? rateb_cms_new_leads_count() : 0;
                $cmsLeadBadges = $cmsNewLeads > 0 ? ['admin/cms/leads' => $cmsNewLeads] : [];
                $adminSection(__('cms_section'), [
                    ['admin/cms', 'cms_dashboard', 'fa-globe', 'cms.view'],
                    ['admin/cms/pages', 'cms_pages', 'fa-file-lines', 'cms.manage'],
                    ['admin/cms/page-builder', 'cms_page_builder', 'fa-sitemap', 'cms.manage'],
                    ['admin/cms/leads', 'cms_leads', 'fa-user-plus', 'cms.leads'],
                    ['admin/cms/blog-articles', 'cms_blog', 'fa-newspaper', 'cms.manage'],
                    ['admin/cms/newsletter', 'cms_newsletter', 'fa-envelope-open-text', 'cms.manage'],
                    ['admin/cms/media', 'cms_media', 'fa-images', 'cms.media'],
                    ['admin/cms/seo', 'cms_seo', 'fa-magnifying-glass', 'cms.seo'],
                    ['admin/cms/faqs', 'cms_faqs', 'fa-circle-question', 'cms.manage'],
                    ['admin/cms/testimonials', 'cms_testimonials', 'fa-star', 'cms.manage'],
                    ['admin/cms/theme', 'cms_theme', 'fa-palette', 'cms.manage'],
                    ['admin/cms/about', 'cms_about', 'fa-building', 'cms.manage'],
                ], 'fa-globe', $cmsNewLeads, $cmsLeadBadges, '', 'rateb-nav-badge--pending', 'cms_leads_new');
            }
            require RATEB_ROOT . '/views/partials/sidebar-agent-apps-nav.php';
            $accessControlLinks = [
                ['admin/access-control', 'access_control', 'fa-shield-halved', 'access.manage'],
                ['admin/access-control/matrix', 'permission_matrix', 'fa-table-cells', 'access.manage'],
                ['admin/users', 'users', 'fa-users', 'access.manage'],
                ['admin/roles', 'roles', 'fa-user-shield', 'access.manage'],
                ['admin/permissions', 'permissions', 'fa-key', 'access.manage'],
            ];
            if (rateb_is_platform_oversight_host()) {
                $accessControlLinks[] = ['admin/plans', 'plans', 'fa-layer-group', 'plans.manage'];
            }
            $accessControlLinks[] = ['admin/audit-logs', 'audit_logs', 'fa-clipboard-list', 'settings.manage'];
            $accessControlLinks[] = ['admin/support-tickets', 'support_tickets', 'fa-life-ring', 'settings.manage'];
            $accessControlLinks[] = ['admin/email-templates', 'email_templates', 'fa-envelope', 'settings.manage'];
            if (function_exists('rateb_email_diagnostics_accessible') && rateb_email_diagnostics_accessible()) {
                $accessControlLinks[] = ['admin/email-diagnostics', 'email_diagnostics', 'fa-stethoscope', 'settings.manage'];
            }
            $accessControlLinks[] = ['admin/sms-templates', 'sms_templates', 'fa-sms', 'settings.manage'];
            $adminSection(__('access_control'), $accessControlLinks, 'fa-key');
            ?>
            <?php } ?>
        </nav>
    </aside>
<script>
/* Sidebar toggles: single delegated binder (stable vs double-bind / late app.js). */
(function () {
  function schedulePrefetchBind(body) {
    var run = function () {
      try {
        if (window.RatebNavInstant && typeof window.RatebNavInstant.bindPrefetch === 'function') {
          window.RatebNavInstant.bindPrefetch(body);
        }
      } catch (eBind) { /* ignore */ }
    };
    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(run, { timeout: 1200 });
    } else {
      setTimeout(run, 0);
    }
  }

  function hydrateNavLazy(group) {
    if (!group) return;
    var body = group.querySelector('.rateb-nav-group-body, .rateb-nav-subgroup-body');
    if (!body) return;
    var kids = body.children;
    var tpl = null;
    for (var i = 0; i < kids.length; i++) {
      if (kids[i].tagName === 'TEMPLATE' && kids[i].getAttribute('data-rateb-nav-lazy') !== null) {
        tpl = kids[i];
        break;
      }
    }
    if (!tpl) return;
    try {
      body.appendChild(tpl.content.cloneNode(true));
      tpl.remove();
    } catch (eHydrate) { /* ignore */ }
    schedulePrefetchBind(body);
  }

  function closeSiblingGroups(group) {
    var parent = group.parentElement;
    if (!parent) return;
    var isSub = group.classList.contains('rateb-nav-subgroup');
    var kids = parent.children;
    for (var i = 0; i < kids.length; i++) {
      var sib = kids[i];
      if (sib === group || !sib.getAttribute || sib.getAttribute('data-nav-group') === null) continue;
      if (isSub !== sib.classList.contains('rateb-nav-subgroup')) continue;
      if (!sib.classList.contains('is-open')) continue;
      sib.classList.remove('is-open');
      var t = sib.querySelector(':scope > [data-nav-group-toggle]');
      if (t) t.setAttribute('aria-expanded', 'false');
    }
  }

  function onSidebarClick(ev) {
    // Soft-nav ONLY for Admin links / dashboard button — never browser full navigation.
    var a = ev.target && ev.target.closest
      ? ev.target.closest('a[href], a[data-rateb-href], button[data-rateb-href], [data-rateb-dashboard-nav]')
      : null;
    if (a) {
      try {
        if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
          return;
        }
        if (a.hasAttribute('download')) {
          return;
        }
        if (window.__ratebGoPosRegister && window.__ratebGoPosRegister(a, ev)) {
          return;
        }
        if (a.getAttribute('data-rateb-full-nav') === '1') {
          ev.preventDefault();
          try { ev.stopImmediatePropagation(); } catch (eF) { ev.stopPropagation(); }
          location.href = (a.getAttribute('data-rateb-href') || a.getAttribute('href') || a.href);
          return;
        }
        if (a.target && a.target !== '' && a.target !== '_self') {
          return;
        }
        var raw = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
        if (!raw) {
          return;
        }
        var u = new URL(raw, location.href);
        if (u.origin !== location.origin || !/\/admin(\/|$)/i.test(u.pathname)) {
          return;
        }
        if (/\/(?:admin\/ops\/)?pos(?:\/register)?\/?(?:$|\?)/i.test(u.pathname)
            || /\/(logout|login|password)(\/|$)/i.test(u.pathname)) {
          ev.preventDefault();
          try { ev.stopImmediatePropagation(); } catch (eP) { ev.stopPropagation(); }
          location.href = u.href;
          return;
        }
        ev.preventDefault();
        try { ev.stopPropagation(); } catch (eSp) { /* ignore */ }
        // Always drive navigation here — do not rely on document-capture listener
        // order (another capture handler can swallow the event before soft-nav).
        if (window.RatebNavInstant && typeof window.RatebNavInstant.navigate === 'function'
            && window.__RATEB_NAV_READY__) {
          window.RatebNavInstant.navigate(u.href);
        } else {
          window.__RATEB_PENDING_NAV__ = u.href;
        }
      } catch (eNav) { /* ignore */ }
      return;
    }
    // One toggle per event — survives duplicate listeners (old app.js + inline).
    if (ev.__ratebNavToggleHandled) return;
    var side = document.getElementById('rateb-sidebar');
    if (!side) return;
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-nav-group-toggle]') : null;
    if (!btn || !side.contains(btn)) return;
    var group = btn.closest('[data-nav-group]');
    if (!group) return;
    ev.__ratebNavToggleHandled = true;
    // Capture + stop: prevent legacy per-button handlers from toggling twice (open→close).
    try { ev.stopImmediatePropagation(); } catch (eStop) { /* ignore */ }
    var willOpen = !group.classList.contains('is-open');
    if (willOpen) {
      closeSiblingGroups(group);
      group.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      hydrateNavLazy(group);
    } else {
      group.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }
  }

  function ensure() {
    var side = document.getElementById('rateb-sidebar');
    if (!side) return false;
    // v3 = accordion siblings + deferred prefetch bind (rebind if upgrading from v2)
    if (side.getAttribute('data-rateb-nav-delegated') === '3') return true;
    if (window.__RATEB_SIDEBAR_CLICK__) {
      try { side.removeEventListener('click', window.__RATEB_SIDEBAR_CLICK__, true); } catch (eRm) { /* ignore */ }
    }
    window.__RATEB_SIDEBAR_CLICK__ = onSidebarClick;
    side.setAttribute('data-rateb-nav-delegated', '3');
    side.addEventListener('click', onSidebarClick, true);
    return true;
  }

  window.RatebSidebarNav = {
    ensure: ensure,
    hydrate: hydrateNavLazy
  };
  ensure();
  document.addEventListener('rateb:nav:afterEnter', function () {
    try { ensure(); } catch (e) { /* ignore */ }
  });
})();
</script>
    <div class="rateb-main">
        <header class="rateb-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="rateb-sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1 class="h5 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="rateb-connection-indicator is-online"
                      id="rateb-connection-indicator"
                      data-rateb-connection-status
                      data-label-online="<?php echo Rateb\App\Core\View::escape(__('connection_online')); ?>"
                      data-label-offline="<?php echo Rateb\App\Core\View::escape(__('connection_offline')); ?>"
                      role="status"
                      aria-live="polite"
                      title="<?php echo Rateb\App\Core\View::escape(__('connection_online')); ?>">
                    <span class="rateb-connection-indicator__dot" aria-hidden="true"></span>
                    <span class="rateb-connection-indicator__label"><?php echo Rateb\App\Core\View::escape(__('connection_online')); ?></span>
                </span>
                <script>
                (function () {
                  /* Sync badge immediately when browser is offline (cached HTML often says متصل). */
                  function syncBadge() {
                    try {
                      var node = document.getElementById('rateb-connection-indicator');
                      if (!node) return;
                      var offline = navigator.onLine === false;
                      var labelOn = node.getAttribute('data-label-online') || 'متصل';
                      var labelOff = node.getAttribute('data-label-offline') || 'غير متصل';
                      var label = offline ? labelOff : labelOn;
                      node.classList.toggle('is-online', !offline);
                      node.classList.toggle('is-offline', offline);
                      node.setAttribute('title', label);
                      node.setAttribute('aria-label', label);
                      var text = node.querySelector('.rateb-connection-indicator__label');
                      if (text) text.textContent = label;
                    } catch (e) {}
                  }
                  syncBadge();
                  window.addEventListener('offline', syncBadge);
                  window.addEventListener('online', syncBadge);
                })();
                </script>
                <?php
                $topbarUserName = trim((string) (\Rateb\App\Core\SessionManager::get('rateb_user_display', '') ?? ''));
                if ($topbarUserName === '') {
                    $topbarUser = \Rateb\App\Core\Auth::user();
                    $topbarUserName = trim((string) ($topbarUser['name'] ?? $topbarUser['email'] ?? ''));
                }
                if ($topbarUserName !== '') { ?>
                <span class="rateb-topbar-user small d-inline-flex align-items-center gap-1" title="<?php echo Rateb\App\Core\View::escape($topbarUserName); ?>">
                    <i class="fas fa-user-circle" aria-hidden="true"></i>
                    <span class="rateb-topbar-user__name"><?php echo Rateb\App\Core\View::escape($topbarUserName); ?></span>
                </span>
                <?php } ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('theme_dark'); ?>">
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" title="<?php echo __('theme_light'); ?>" aria-pressed="false"><i class="fas fa-sun"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="dark" title="<?php echo __('theme_dark'); ?>" aria-pressed="false"><i class="fas fa-moon"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="auto" title="<?php echo __('theme_auto'); ?>" aria-pressed="false"><i class="fas fa-circle-half-stroke"></i></button>
                </div>
                <a href="<?php echo rateb_url('admin/logout'); ?>" class="btn btn-outline-danger btn-sm rateb-topbar-logout" data-rateb-full-nav="1" title="<?php echo __('logout'); ?>">
                    <i class="fas fa-sign-out-alt"></i><span class="d-none d-md-inline ms-1"><?php echo __('logout'); ?></span>
                </a>
<script>
(function () {
  /* Always-on logout: full navigation (stable vs stale soft-nav in memory). */
  if (window.__RATEB_LOGOUT_FULL_NAV__) return;
  window.__RATEB_LOGOUT_FULL_NAV__ = 1;
  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a.rateb-topbar-logout') : null;
    if (!a) return;
    ev.preventDefault();
    ev.stopImmediatePropagation();
    try {
      if (navigator.serviceWorker && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'PURGE_ERP_AUTH_CACHE' });
      }
    } catch (ePurge) { /* ignore */ }
    try { window.location.assign(a.href); } catch (eGo) { window.location.href = a.href; }
  }, true);
})();
</script>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('language'); ?>">
                    <a href="<?php echo htmlspecialchars(rateb_locale_switch_url('en'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>"
                       data-locale="en"
                       data-locale-base="<?php echo htmlspecialchars(rateb_erp_locale_base_url('en'), ENT_QUOTES, 'UTF-8'); ?>"
                       data-rateb-full-nav="1">EN</a>
                    <a href="<?php echo htmlspecialchars(rateb_locale_switch_url('ar'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>"
                       data-locale="ar"
                       data-locale-base="<?php echo htmlspecialchars(rateb_erp_locale_base_url('ar'), ENT_QUOTES, 'UTF-8'); ?>"
                       data-rateb-full-nav="1">عربي</a>
                </div>
            </div>
        </header>
        <main class="rateb-content" id="rateb-main-content">
            <?php
            // Subscription toast first (top of page), then flash.
            $subscriptionAlertPartial = RATEB_VIEWS_PATH . '/partials/subscription-alert.php';
            $subscriptionAlertBanner = RATEB_ROOT . '/modules/subscription/views/alert-banner.php';
            if (is_file($subscriptionAlertPartial)) {
                include $subscriptionAlertPartial;
            } elseif (is_file($subscriptionAlertBanner)) {
                include $subscriptionAlertBanner;
            }
            ?>
            <?php Rateb\App\Core\View::partial('flash'); ?>
            <?php if (function_exists('rateb_is_portal_branch_session') && rateb_is_portal_branch_session()) {
                $branchLabel = function_exists('rateb_portal_branch_label') ? rateb_portal_branch_label() : '';
                if ($branchLabel !== '') { ?>
            <div class="alert alert-info py-2 mb-3 d-flex align-items-center gap-2">
                <i class="fas fa-store"></i>
                <span><?php echo Rateb\App\Core\View::escape(__('branch_portal_active_banner', ['branch' => $branchLabel])); ?></span>
            </div>
            <?php }
            } ?>
            <?php
            $platformAccountsUi = function_exists('rateb_is_platform_accounts_ui')
                && rateb_is_platform_accounts_ui(isset($erpRoute) ? (string) $erpRoute : null);
            // Platform SA/staff screens: no company context — hide branch filter + ops picker.
            if (!$platformAccountsUi && function_exists('rateb_branch_access_all') && rateb_branch_access_all() && !rateb_is_portal_branch_session() && rateb_company_branches_nav_enabled()) {
                $hoCompanyId = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? rateb_resolve_ops_company_id());
                $hoBranches = function_exists('rateb_company_branches_cached')
                    ? rateb_company_branches_cached($hoCompanyId)
                    : (new \Rateb\App\Services\BranchService())->listForCompany($hoCompanyId);
                if ($hoBranches !== []) {
                    Rateb\App\Core\View::partial('branch-filter-switcher', [
                        'branches' => $hoBranches,
                        'activeFilter' => function_exists('rateb_active_branch_filter_id') ? rateb_active_branch_filter_id() : 0,
                    ]);
                }
            ?>
            <div class="alert alert-secondary py-2 mb-3">
                <?php if (function_exists('rateb_active_branch_filter_id') && rateb_active_branch_filter_id() > 0) { ?>
                <i class="fas fa-filter"></i> <?php echo Rateb\App\Core\View::escape(__('branch_filter')); ?>: <strong><?php echo Rateb\App\Core\View::escape(function_exists('rateb_branch_filter_label') ? rateb_branch_filter_label() : ''); ?></strong>
                <?php } else { ?>
                <i class="fas fa-building"></i> <?php echo Rateb\App\Core\View::escape(__('branch_filter_all')); ?>
                <?php } ?>
            </div>
            <?php }
            $showOpsCompanyPicker = !$platformAccountsUi
                && rateb_is_super_admin()
                && rateb_is_platform_oversight_host()
                && (
                rateb_is_ops_route($erpRoute)
                || strpos($currentPath, '/admin/ops/') !== false
            );
            if ($platformAccountsUi && rateb_is_super_admin()) {
                Rateb\App\Core\View::partial('platform-accounts-banner');
            } elseif ($showOpsCompanyPicker) {
                Rateb\App\Core\View::partial('ops-company-select');
            }
            if ($deferModulePageMetrics) {
                $metricsQs = 'route=' . rawurlencode($erpRoute);
                $metricsCompanyId = 0;
                if (str_starts_with($erpRoute, 'admin/oversight/')) {
                    $metricsCompanyId = max(0, (int) ($_GET['company_id'] ?? 0));
                } else {
                    $metricsCompanyId = function_exists('rateb_resolve_ops_company_id')
                        ? (int) rateb_resolve_ops_company_id()
                        : 0;
                }
                if ($metricsCompanyId > 0) {
                    $metricsQs .= '&company_id=' . $metricsCompanyId;
                }
                Rateb\App\Core\View::partial('module-page-stats', [
                    'async' => true,
                    'metricsRoute' => $erpRoute,
                    'metricsUrl' => rateb_url('admin/api/module-metrics') . '?' . $metricsQs,
                ]);
            } elseif ($modulePageMetrics !== []) {
                Rateb\App\Core\View::partial('module-page-stats', ['metrics' => $modulePageMetrics]);
            }
            ?>
            <?php echo $pageContent; ?>
        </main>
    </div>
</div>
<?php Rateb\App\Core\View::partial('entity-documents-modal-shell'); ?>
<?php Rateb\App\Core\View::partial('rateb-confirm-modal'); ?>
<?php
/* PERF-P3 / Fix2: critical-path scripts only before paint settles; rest after interaction/idle.
 * Membership unchanged — loading order/waves optimized in the injector below. */
$ratebCriticalScripts = [
    // FIRST — soft-nav must bind before theme/app so لوحة التحكم never full-navigates to black.
    rateb_asset('js/erp-nav-instant.js'),
    rateb_asset('js/theme.js'),
    rateb_asset('js/lang.js'),
    rateb_asset('js/app.js'),
    // PERF-P4: metrics listener must be present before soft-nav afterEnter (not idle).
    rateb_asset('js/module-page-stats.js'),
    // Approvals actions must survive soft-nav (idle/one-shot bind left buttons dead).
    rateb_asset('js/approvals-oversight.js'),
];
/* Idle order: bootstrap → modal/confirm deps → page tools → connectivity last (network probe). */
$ratebIdleScripts = [
    rateb_bootstrap_js(),
    rateb_asset('js/rateb-modal.js'),
    rateb_asset('js/rateb-confirm.js'),
];
if (!empty($layoutAssets['bulkDelete'])) {
    $ratebIdleScripts[] = rateb_asset('js/rateb-bulk-delete.js');
}
if (!empty($layoutAssets['tableTools'])) {
    $ratebIdleScripts[] = rateb_asset('js/table-tools.js');
}
if (!empty($layoutAssets['dateInputs'])) {
    $ratebIdleScripts[] = rateb_asset('js/rateb-date-inputs.js');
}
if (!empty($layoutAssets['formHybrid'])) {
    $ratebIdleScripts[] = rateb_asset('js/form-hybrid.js');
}
if (!empty($layoutAssets['fiscalYear'])) {
    $ratebIdleScripts[] = rateb_asset('js/form-fiscal-year.js');
}
if (!empty($layoutAssets['lineItems'])) {
    $ratebIdleScripts[] = rateb_asset('js/line-items.js');
}
if (!empty($layoutAssets['inventoryBatch'])) {
    $ratebIdleScripts[] = rateb_asset('js/inventory-batch-form.js');
}
if (!empty($layoutAssets['contractRenewal'])) {
    $ratebIdleScripts[] = rateb_asset('js/contract-renewal-form.js');
}
if (!empty($layoutAssets['cmsAdmin'])) {
    $ratebIdleScripts[] = rateb_asset('js/cms-admin.js');
}
if ($navActive('admin/agency-updates')) {
    $ratebIdleScripts[] = rateb_asset('js/agency-updates.js');
}
$ratebIdleScripts[] = rateb_asset('js/connectivity-indicator.js');
$deferAssetScripts = [];
/* Fix8: Chart.js only when route opts in; runtime also DOM-gates before inject.
 * dashboard-charts-defer boots API hydrate on admin dashboard (no content <script defer>). */
if (!empty($layoutAssets['charts'])) {
    $deferAssetScripts[] = rateb_chartjs('4.4.3');
}
foreach ($layoutAssets['defer'] ?? [] as $deferFile) {
    $deferAssetScripts[] = rateb_asset('js/' . $deferFile);
}
if (!empty($layoutAssets['charts']) && ($erpRoute === 'admin' || $erpRoute === 'admin/executive-dashboard')) {
    $deferAssetScripts[] = rateb_asset('js/dashboard-charts-defer.js');
}
/* PERF Fix2: preload critical scripts so downloads overlap; injector still controls exec order. */
foreach ($ratebCriticalScripts as $ratebCritSrc) {
    echo '<link rel="preload" href="' . htmlspecialchars((string) $ratebCritSrc, ENT_QUOTES, 'UTF-8') . '" as="script">' . "\n";
}
?>
<script>
(function () {
  /* PERF Fix2: shorten critical/idle waterfalls — parallel waves, preserve required order. */
  var critical = <?php echo json_encode(array_values($ratebCriticalScripts), JSON_UNESCAPED_SLASHES); ?>;
  var idleQueue = <?php echo json_encode(array_values($ratebIdleScripts), JSON_UNESCAPED_SLASHES); ?>;
  var chartQueue = <?php echo json_encode(array_values($deferAssetScripts), JSON_UNESCAPED_SLASHES); ?>;
  function inject(src, next) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = s.onerror = function () { if (next) next(); };
    document.head.appendChild(s);
  }
  function chain(list, i, done) {
    if (i >= list.length) { if (done) done(); return; }
    inject(list[i], function () { chain(list, i + 1, done); });
  }
  /** Load all URLs in parallel; done when every script settles (order of exec ≈ race). */
  function parallel(list, done) {
    if (!list || !list.length) { if (done) done(); return; }
    var left = list.length;
    var tick = function () {
      left -= 1;
      if (left <= 0 && done) done();
    };
    list.forEach(function (src) { inject(src, tick); });
  }
  function isModalSrc(src) {
    return /\/rateb-modal\.js(\?|$)/.test(String(src));
  }
  function isConfirmSrc(src) {
    return /\/rateb-confirm\.js(\?|$)/.test(String(src));
  }
  function loadCritical() {
    if (!critical.length) {
      try { window.dispatchEvent(new Event('rateb-critical-js-ready')); } catch (e0) {}
      return;
    }
    /* Wave 1: soft-nav FIRST (required). Wave 2: theme/app/metrics/approvals in parallel. */
    inject(critical[0], function () {
      parallel(critical.slice(1), function () {
        try { window.dispatchEvent(new Event('rateb-critical-js-ready')); } catch (e1) {}
      });
    });
  }
  /* Start NOW (script is after sidebar in body). Waiting for DCL delayed soft-nav
   * until other head defer scripts finished — clicks escaped to full black navigation. */
  loadCritical();
  function afterInteraction(fn) {
    var ran = false;
    var go = function () {
      if (ran) return;
      ran = true;
      fn();
    };
    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (ev) {
      window.addEventListener(ev, go, { once: true, passive: true });
    });
    var idleStart = function () {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(go, { timeout: 2500 });
      } else {
        setTimeout(go, 900);
      }
    };
    if (document.readyState === 'complete') idleStart();
    else window.addEventListener('load', idleStart, { once: true });
  }
  /* Fix8: Chart.js after first paint + idle — never on DCL critical path.
   * DOM gate: skip entirely when page has no chart containers (even if route flagged).
   * Cancel if user soft-navs away before start. Soft-nav into charts uses erp-nav-instant. */
  function pageHasChartContainers() {
    try {
      return !!(document.querySelector(
        'canvas[id^="chart-"], canvas[id^="acc-chart-"], [data-chart-slot], [data-cm-dash][data-rateb-chartjs]'
      ));
    } catch (eHas) {
      return false;
    }
  }
  function bootChartsNow() {
    try {
      if (typeof window.ratebChartsBoot === 'function') {
        window.ratebChartsBoot();
      }
      if (typeof window.ratebDashboardChartsBoot === 'function') {
        window.ratebDashboardChartsBoot();
      }
    } catch (eBoot) { /* ignore */ }
  }
  if (chartQueue.length) {
    var chartsCancelled = false;
    var chartsStarted = false;
    var startCharts = function () {
      if (chartsCancelled || chartsStarted) return;
      if (!pageHasChartContainers()) return;
      chartsStarted = true;
      chain(chartQueue, 0, bootChartsNow);
    };
    var scheduleCharts = function () {
      var go = function () {
        if (chartsCancelled) return;
        if (window.requestIdleCallback) {
          window.requestIdleCallback(startCharts, { timeout: 3000 });
        } else {
          setTimeout(startCharts, 400);
        }
      };
      if (window.requestAnimationFrame) {
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(go);
        });
      } else {
        setTimeout(go, 0);
      }
    };
    try {
      document.addEventListener('rateb:nav:beforeLeave', function () {
        chartsCancelled = true;
      });
      document.addEventListener('rateb:nav:afterEnter', function () {
        chartsCancelled = false;
        if (!pageHasChartContainers()) {
          return;
        }
        // Soft-nav into dashboard from a non-chart page never ran chartQueue — start now.
        if (chartQueue.length && !chartsStarted) {
          startCharts();
        }
        if (typeof window.ratebDashboardChartsBoot === 'function') {
          try { window.ratebDashboardChartsBoot(); } catch (eRe) { /* ignore */ }
        }
      });
    } catch (eNav) { /* ignore */ }
    if (document.readyState === 'complete') {
      scheduleCharts();
    } else {
      window.addEventListener('load', scheduleCharts, { once: true });
    }
  }
  afterInteraction(function () {
    if (!idleQueue.length) return;
    /* Wave A: bootstrap first (rateb-modal / confirm require it). */
    inject(idleQueue[0], function () {
      var rest = idleQueue.slice(1);
      var modalSrc = null;
      var confirmSrc = null;
      var others = [];
      rest.forEach(function (src) {
        if (isModalSrc(src)) modalSrc = src;
        else if (isConfirmSrc(src)) confirmSrc = src;
        else others.push(src);
      });
      /* Wave B: modal + independent idle scripts in parallel. */
      var waveB = others.slice();
      if (modalSrc) waveB.unshift(modalSrc);
      parallel(waveB, function () {
        /* Wave C: confirm after modal (uses ratebModalPrepare + bootstrap.Modal). */
        if (confirmSrc) inject(confirmSrc);
      });
    });
  });
})();
</script><?php
$ratebOfflineFlagSvc = class_exists(\Rateb\App\Offline\Services\OfflineFeatureFlagService::class)
    ? new \Rateb\App\Offline\Services\OfflineFeatureFlagService()
    : null;
$ratebOfflineReadCache = $ratebOfflineFlagSvc && $ratebOfflineFlagSvc->isReadCacheEnabled();
$ratebOfflineAuthUnlock = $ratebOfflineFlagSvc && $ratebOfflineFlagSvc->isAuthUnlockEnabled();
// Full offline SDK on daily-ops + platform companies/oversight (create/save queue).
$ratebOfflineFullClient = $ratebOfflineReadCache && (
    !empty($_GET['rateb_offline'])
    || !empty($_GET['rateb_offline_debug'])
    || ($erpRoute !== '' && (bool) preg_match(
        '#^(admin/ops(?:/|$)|admin/hr(?:/|$)|admin/recruitment(?:/|$)|admin/eproc(?:/|$)|admin/companies(?:/|$)|admin/oversight(?:/|$)|company/(?:ops|hr|procurement|inventory)(?:/|$))#',
        $erpRoute
    ))
);
// Always register pos-sw so https://rateb.sa/.../admin works offline (same URL).
// Local Branch Appliance (127.0.0.1): never register SW — PHP serves the app; SW caused
// blank/spinning pages when Wi‑Fi was off (treated local as "dead network").
$ratebLocalAppliance = function_exists('rateb_is_local_appliance_host') && rateb_is_local_appliance_host();
$ratebOfflineSw = '';
if (!$ratebLocalAppliance) {
    $ratebOfflineSw = rateb_public_url('pos-sw.js');
    $ratebOfflineSw .= (str_contains($ratebOfflineSw, '?') ? '&' : '?')
        . 'v=' . rawurlencode(defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1');
}
$ratebOfflineSwScope = (!$ratebLocalAppliance && function_exists('rateb_site_origin') && function_exists('rateb_erp_app_prefix'))
    ? (rateb_site_origin() . rtrim(rateb_erp_app_prefix(), '/') . '/')
    : '';
if (!$ratebLocalAppliance) {
    ?>
<script>
(function () {
  /* Soft build bump only — NEVER unregister cloud SW (caused unstyled/Chrome interstitial offline). */
  var NEED = <?php echo json_encode(defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1'); ?>;
  var KEY = 'rateb_sw_build';
  var prev = null;
  try {
    prev = localStorage.getItem(KEY);
  } catch (e0) {}
  if (prev !== NEED) {
    try {
      localStorage.setItem(KEY, NEED);
      [
        'rateb_erp_full_warm_at', 'rateb_erp_full_warm_ok',
        'rateb_erp_full_warm_at_v3', 'rateb_erp_full_warm_ok_v3',
        'rateb_erp_full_warm_at_v4', 'rateb_erp_full_warm_ok_v4',
        'rateb_erp_full_warm_at_v5', 'rateb_erp_full_warm_ok_v5',
        'rateb_erp_full_warm_at_v6', 'rateb_erp_full_warm_ok_v6',
        'rateb_erp_full_warm_at_v7', 'rateb_erp_full_warm_ok_v7',
        'rateb_erp_full_warm_assets_v7',
        'rateb_erp_full_warm_at_v8', 'rateb_erp_full_warm_ok_v8',
        'rateb_erp_full_warm_assets_v8',
        'rateb_erp_full_warm_at_v9', 'rateb_erp_full_warm_ok_v9',
        'rateb_erp_full_warm_assets_v9',
        'rateb_erp_full_warm_at_v12', 'rateb_erp_full_warm_ok_v12',
        'rateb_erp_full_warm_assets_v12',
        'rateb_erp_full_warm_at_v13', 'rateb_erp_full_warm_ok_v13',
        'rateb_erp_full_warm_assets_v13',
        'rateb_erp_full_warm_at_v18', 'rateb_erp_full_warm_ok_v18',
        'rateb_erp_full_warm_assets_v18',
        'rateb_erp_full_warm_at_v19', 'rateb_erp_full_warm_ok_v19',
        'rateb_erp_full_warm_assets_v19'
      ].forEach(function (k) {
        try { localStorage.removeItem(k); } catch (eR) {}
      });
      sessionStorage.removeItem('rateb_sw_reloaded');
      sessionStorage.removeItem('rateb_sw_shell_warm_v46');
      // After build bump: start offline warm ASAP while online so /admin is not empty offline.
      try {
        if (navigator.onLine !== false) {
          setTimeout(function () {
            try {
              if (window.RatebOfflineFullWarm && typeof window.RatebOfflineFullWarm.start === 'function') {
                window.RatebOfflineFullWarm.start({ force: true });
              }
            } catch (eWarm) { /* ignore */ }
          }, 2500);
        }
      } catch (eKick) { /* ignore */ }
    } catch (e1) {}
    /* Do NOT location.reload here — that blanked the tab for minutes after F5
     * while SW negotiate a fresh document (user saw black after refresh / لوحة التحكم). */
  }
  window.__RATEB_ASSET_BUILD__ = NEED;
  // Stale SW: activate new worker + one safe reload so online /admin bypass takes effect.
  try {
    if ('serviceWorker' in navigator && navigator.onLine !== false) {
      navigator.serviceWorker.getRegistrations().then(function (regs) {
        (regs || []).forEach(function (reg) {
          try {
            if (typeof reg.update === 'function') {
              reg.update();
            }
            if (reg.waiting) {
              reg.waiting.postMessage({ type: 'SKIP_WAITING' });
            }
            if (reg.active) {
              reg.active.postMessage({ type: 'CLIENTS_CLAIM' });
              reg.active.postMessage({ type: 'RATEB_CLOUD_ONLINE' });
            }
          } catch (eU) {}
        });
      }).catch(function () {});
      var reloadKey = 'rateb_sw_bypass_reload_' + NEED;
      try {
        if (!sessionStorage.getItem(reloadKey) && navigator.serviceWorker.controller) {
          var ctrlUrl = '';
          try {
            ctrlUrl = String(navigator.serviceWorker.controller.scriptURL || '');
          } catch (eUrl) { ctrlUrl = ''; }
          if (ctrlUrl && ctrlUrl.indexOf('v110') === -1 && ctrlUrl.indexOf(NEED) === -1) {
            sessionStorage.setItem(reloadKey, '1');
            navigator.serviceWorker.addEventListener('controllerchange', function () {
              try {
                location.reload();
              } catch (eRel) { /* ignore */ }
            }, { once: true });
            navigator.serviceWorker.getRegistration().then(function (reg) {
              if (reg && reg.waiting) {
                reg.waiting.postMessage({ type: 'SKIP_WAITING' });
              }
            }).catch(function () {});
          }
        }
      } catch (eReload) { /* ignore */ }
    }
  } catch (eForce) {}
  window.__RATEB_SW_READY_GATE__ = Promise.resolve({ reload: false, bump: prev !== NEED });
  /**
   * Single ERP Service Worker register (layout owns it).
   * No controllerchange → location.reload.
   */
  window.__ratebErpRegisterSwOnce = function (swUrl, scope) {
    if (window.__RATEB_SW_REGISTER_PROMISE__) {
      return window.__RATEB_SW_REGISTER_PROMISE__;
    }
    if (!('serviceWorker' in navigator) || !swUrl) {
      window.__RATEB_SW_REGISTERED__ = true;
      return Promise.resolve(null);
    }
    window.__RATEB_SW_REGISTERED__ = true;
    function claim(reg) {
      try {
        if (reg && reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        if (reg && reg.active) reg.active.postMessage({ type: 'CLIENTS_CLAIM' });
      } catch (eC) {}
      return reg;
    }
    function doRegister() {
      try {
        if (navigator.onLine === false) {
          // Offline: keep current controller — do not SKIP_WAITING (waiting update freezes UI).
          var getReg = scope
            ? navigator.serviceWorker.getRegistration(scope)
            : navigator.serviceWorker.getRegistration();
          return getReg.catch(function () { return null; });
        }
      } catch (eOff) { /* continue */ }
      return navigator.serviceWorker.register(String(swUrl), scope
          ? { scope: String(scope), updateViaCache: 'none' }
          : { updateViaCache: 'none' })
        .then(function (reg) {
          try {
            if (reg && typeof reg.update === 'function' && navigator.onLine !== false) {
              reg.update();
            }
          } catch (eUp) {}
          return claim(reg);
        })
        .catch(function () {
          try {
            var getReg2 = scope
              ? navigator.serviceWorker.getRegistration(scope)
              : navigator.serviceWorker.getRegistration();
            return getReg2.then(claim).catch(function () { return null; });
          } catch (eG) {
            return null;
          }
        });
    }
    window.__RATEB_SW_REGISTER_PROMISE__ = doRegister();
    return window.__RATEB_SW_REGISTER_PROMISE__;
  };
  window.__ratebErpScheduleSwRegister = function (swUrl, scope) {
    // Register immediately — deferred idle left a window where offline refresh
    // had no controller → Chrome «لا يتوفر اتصال بالإنترنت».
    var run = function () {
      window.__ratebErpRegisterSwOnce(swUrl, scope);
    };
    try {
      run();
    } catch (eRun) {
      setTimeout(run, 0);
    }
  };
})();
</script>
<?php
}
if ($ratebLocalAppliance) {
    ?>
<script>
(function () {
  /* Tear down any leftover SW from earlier builds so local PHP is never intercepted. */
  if (!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.getRegistrations().then(function (regs) {
    (regs || []).forEach(function (reg) {
      try { reg.unregister(); } catch (e) { /* ignore */ }
    });
  }).catch(function () { /* ignore */ });
  if (window.caches && typeof caches.keys === 'function') {
    caches.keys().then(function (keys) {
      (keys || []).forEach(function (k) {
        if (/^rateb-/i.test(String(k || ''))) {
          caches.delete(k);
        }
      });
    }).catch(function () { /* ignore */ });
  }
})();
</script>
<script>
(function () {
  /* Customer UX: same URL as cloud. Local appliance is sync-only when online. */
  try {
    if (/[?&]stay_local=1(?:&|$)/.test(String(location.search || ''))) return;
    if (navigator.onLine === false) return;
    var cloud = 'https://rateb.sa/rateb-erp/public/admin/';
    var path = String(location.pathname || '');
    var rest = path.replace(/^\/admin\/?/, '');
    var target = cloud;
    if (rest && rest !== 'admin') {
      target = cloud.replace(/\/?$/, '/') + rest.replace(/^\//, '');
    }
    if (location.search) {
      target += (target.indexOf('?') >= 0 ? '&' : '?') + String(location.search).replace(/^\?/, '');
    }
    if (String(location.href).indexOf('rateb.sa') === -1) {
      location.replace(target);
    }
  } catch (eRedir) { /* ignore */ }
})();
</script>
<?php
}$ratebOfflineApiBase = rateb_url('api/v1/offline');
$ratebOfflineCompanyId = 0;
if (function_exists('rateb_resolve_erp_shell_company_id')) {
    $ratebOfflineCompanyId = (int) rateb_resolve_erp_shell_company_id();
} else {
    $ratebOfflineCompanyId = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
    if ($ratebOfflineCompanyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
        $ratebOfflineCompanyId = (int) rateb_resolve_ops_company_id();
    }
}
$ratebOfflineBranchId = 0;
if (function_exists('rateb_portal_branch_id')) {
    $ratebOfflineBranchId = (int) rateb_portal_branch_id();
}
if ($ratebOfflineBranchId < 1 && function_exists('rateb_active_branch_filter_id')) {
    $ratebOfflineBranchId = (int) rateb_active_branch_filter_id();
}
$ratebOfflineUserId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id', 0) ?? 0);
$ratebOfflineAllowlistUrl = rateb_public_url('assets/offline/ops-page-allowlist.json');
$ratebConnectivityProbeUrl = rateb_public_url('connectivity-probe.json');

if ($ratebOfflineFullClient) {
        $ratebOfflineFlags = $ratebOfflineFlagSvc->snapshot();
        $ratebOfflineSyncPolicy = class_exists(\Rateb\App\Offline\OfflineModule::class)
            ? \Rateb\App\Offline\OfflineModule::syncPolicy()
            : [];
        $ratebOfflineOpsAllowlist = class_exists(\Rateb\App\Offline\OfflineModule::class)
            ? \Rateb\App\Offline\OfflineModule::opsPageAllowlist()
            : [];
        ?>
<script>
window.__RATEB_ERP_SHELL_OFFLINE__ = <?php echo json_encode([
    'serviceWorker' => $ratebOfflineSw,
    'serviceWorkerScope' => $ratebOfflineSwScope,
    'apiBase' => $ratebOfflineApiBase,
    'probeUrl' => $ratebConnectivityProbeUrl,
    'allowlistUrl' => $ratebOfflineAllowlistUrl,
    'flags' => $ratebOfflineFlags,
    'startConnectivity' => false,
    'startScheduler' => false,
    'lazyBoot' => true,
    'company_id' => $ratebOfflineCompanyId,
    'tenant_id' => $ratebOfflineCompanyId,
    'branch_id' => $ratebOfflineBranchId,
    'user_id' => $ratebOfflineUserId,
    'is_super_admin' => (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin'),
    'logout_vault_policy' => class_exists(\Rateb\App\Offline\Services\ErpOfflineAuthPolicy::class)
        ? (new \Rateb\App\Offline\Services\ErpOfflineAuthPolicy())->logoutVaultPolicy()
        : 'clear_vault',
    'session_policy' => class_exists(\Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy::class)
        ? (new \Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy())->snapshot()
        : [],
    'client_queue_max' => (int) ($ratebOfflineSyncPolicy['client_queue_max'] ?? 500),
    'ops_page_paths' => [],
    'ops_page_routes' => (object) [],
    'ops_form_hooks' => array_values($ratebOfflineOpsAllowlist['form_hooks'] ?? []),
    'pilot_ops_pages' => $ratebOfflineFlagSvc->isPilotOpsPagesEnabled(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php
        $ratebOfflineLazyScripts = [];
        /* Fix9: tenant-context first in idle SDK chain — not a parse-time <script defer>. */
        $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-offline-tenant-context.js');
        if (!empty($_GET['rateb_offline_debug'])) {
            $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-offline-debug.js');
        }
        /* Phase OA — critical path is offline-bootstrap (<20KB); modules load on demand. */
        $ratebOfflineLazyScripts[] = rateb_asset('offline/offline-bootstrap.js');
        $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-shell-bootstrap.js');
        if (!empty($ratebOfflineAuthUnlock) && $ratebOfflineFlagSvc && $ratebOfflineFlagSvc->isAuthUnlockEnabled()) {
            $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-auth-bootstrap.js');
        }
        if ($ratebOfflineFlagSvc->isRbacCacheEnabled()) {
            $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-rbac-bootstrap.js');
        }
        $ratebOfflineOpsForms = $ratebOfflineFlagSvc->isAnyTier1WriteEnabled()
            || $ratebOfflineFlagSvc->isMasterDataEnabled()
            || $ratebOfflineFlagSvc->isPilotOpsPagesEnabled();
        if ($ratebOfflineOpsForms) {
            $ratebOfflineLazyScripts[] = rateb_asset('offline/erp-ops-forms-bootstrap.js');
        }
        ?>
<script>
(function () {
  /* Lazy Offline SDK — after first paint / interactive. Offline: still post-paint, ASAP. */
  var urls = <?php echo json_encode(array_values($ratebOfflineLazyScripts), JSON_UNESCAPED_SLASHES); ?>;
  var swUrl = <?php echo json_encode($ratebOfflineSw, JSON_UNESCAPED_SLASHES); ?>;
  var swScope = <?php echo json_encode($ratebOfflineSwScope, JSON_UNESCAPED_SLASHES); ?>;
  function mark(k) {
    try {
      var b = window.__RATEB_BOOT__ || (window.__RATEB_BOOT__ = {});
      b[k] = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    } catch (e) {}
  }
  function loadChain(i) {
    if (i >= urls.length) {
      mark('sdkReady');
      try { window.dispatchEvent(new Event('rateb-offline-sdk-ready')); } catch (eE) {}
      return;
    }
    var s = document.createElement('script');
    var advanced = false;
    var next = function () {
      if (advanced) return;
      advanced = true;
      loadChain(i + 1);
    };
    s.src = urls[i];
    s.async = false;
    s.onload = next;
    s.onerror = next;
    // Soft-offline: hanging script must not freeze the sequential SDK chain.
    setTimeout(next, (navigator.onLine === false) ? 900 : 2500);
    (document.body || document.documentElement).appendChild(s);
  }
  function startSdk() {
    mark('sdkStart');
    loadChain(0);
  }
  function afterInteractive(fn) {
    var kick = function () {
      try {
        if (navigator.onLine === false || /(?:\?|&)rateb_offline(?:=|&|$)/.test(String(location.search || ''))) {
          /* Offline / explicit offline mode: still post-paint but ASAP for SDK. */
          setTimeout(fn, 50);
          return;
        }
      } catch (e0) {}
      /* PERF-P3 / Fix9: do not start Offline SDK warm-path assets on first paint.
       * Delay online SDK boot until idle after load (timeout 8s). */
      if (window.requestIdleCallback) {
        window.requestIdleCallback(fn, { timeout: 8000 });
      } else {
        setTimeout(fn, 2500);
      }
    };
    if (document.readyState === 'complete') {
      kick();
    } else {
      window.addEventListener('load', kick, { once: true });
    }
  }
  afterInteractive(startSdk);
  if (swUrl && typeof window.__ratebErpScheduleSwRegister === 'function') {
    window.__ratebErpScheduleSwRegister(swUrl, swScope || undefined);
  }
  try {
    if (document.readyState === 'complete') {
      mark('fp');
    } else {
      window.addEventListener('load', function () { mark('fp'); }, { once: true });
    }
    setTimeout(function () { mark('ttiProxy'); }, 0);
  } catch (eM) {}
})();
</script>
<?php
} else {
        // Always-on lite SW: same rateb.sa URL works offline after one online visit.
        ?>
<script>
window.__RATEB_ERP_SHELL_OFFLINE__ = <?php echo json_encode([
    'lite' => true,
    'serviceWorker' => $ratebOfflineSw,
    'serviceWorkerScope' => $ratebOfflineSwScope,
    'apiBase' => $ratebOfflineApiBase,
    'probeUrl' => $ratebConnectivityProbeUrl,
    'company_id' => $ratebOfflineCompanyId,
    'tenant_id' => $ratebOfflineCompanyId,
    'branch_id' => $ratebOfflineBranchId,
    'user_id' => $ratebOfflineUserId,
    'flags' => [
        'offline.enabled' => true,
        'offline.read_cache' => true,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
(function () {
  /* Fix9: lite tenant-context after Online shell ready — not parse-time defer. */
  var tenantUrl = <?php echo json_encode(rateb_asset('offline/erp-offline-tenant-context.js'), JSON_UNESCAPED_SLASHES); ?>;
  function injectTenant() {
    if (!tenantUrl || document.querySelector('script[data-rateb-offline-tenant]')) return;
    var s = document.createElement('script');
    s.src = tenantUrl;
    s.async = true;
    s.setAttribute('data-rateb-offline-tenant', '1');
    (document.body || document.documentElement).appendChild(s);
  }
  function scheduleTenant() {
    try {
      if (navigator.onLine === false || /(?:\?|&)rateb_offline(?:=|&|$)/.test(String(location.search || ''))) {
        setTimeout(injectTenant, 50);
        return;
      }
    } catch (eOff) { /* ignore */ }
    var go = function () {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(injectTenant, { timeout: 5000 });
      } else {
        setTimeout(injectTenant, 2000);
      }
    };
    if (document.readyState === 'complete') go();
    else window.addEventListener('load', go, { once: true });
  }
  scheduleTenant();
})();
</script>
<script>
(function () {
  var cfg = window.__RATEB_ERP_SHELL_OFFLINE__ || {};
  if (!('serviceWorker' in navigator) || !cfg.serviceWorker) return;
  var swUrl = String(cfg.serviceWorker);
  var scope = cfg.serviceWorkerScope ? String(cfg.serviceWorkerScope) : undefined;
  function purgeErpAuthCache() {
    try {
      var msg = { type: 'PURGE_ERP_AUTH_CACHE' };
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage(msg);
      }
      navigator.serviceWorker.ready.then(function (reg) {
        if (reg.active) {
          reg.active.postMessage(msg);
        }
      }).catch(function () {});
    } catch (ePurge) { /* ignore */ }
  }
  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a.rateb-topbar-logout') : null;
    if (!a) return;
    // Always full navigation — defeats stale in-memory soft-nav from before deploy.
    purgeErpAuthCache();
    ev.preventDefault();
    ev.stopImmediatePropagation();
    try {
      window.location.assign(a.href);
    } catch (eGo) {
      window.location.href = a.href;
    }
  }, true);
  var warmed = false;
  function warm(reg) {
    // Phase OH — idle shell + leanOps HTML snapshot warm (certified modules).
    // Still skip when offline; force via ?rateb_warm=1 remains available.
    if (warmed) return;
    try {
      if (navigator.onLine === false) return;
    } catch (eOff) { return; }
    try {
      var w = reg && (reg.active || reg.waiting || reg.installing);
      if (!w) return;
      warmed = true;
      w.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL' });
    } catch (e) {}
  }
  function scheduleWarm(reg) {
    var run = function () { warm(reg); };
    if (document.readyState === 'complete') {
      setTimeout(run, 1500);
    } else {
      window.addEventListener('load', function () { setTimeout(run, 1500); }, { once: true });
    }
  }
  function isAdminPath(pathname) {
    return /\/admin(\/|$)/i.test(String(pathname || ''));
  }
  function pathKey(pathname) {
    return String(pathname || '').replace(/\/+$/, '').toLowerCase();
  }
  function isOfflineShellUi() {
    return !!(document.querySelector('.rateb-offline-home, #rateb-offline-shell-main, #offline-status, [data-rateb-uncached-page]'));
  }
  // Offline + already on a live page: same-link click must not reload into offline-shell.
  document.addEventListener('click', function (ev) {
    try {
      if (navigator.onLine !== false) return;
      if (isOfflineShellUi()) return;
      if (!isAdminPath(location.pathname)) return;
      var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
      if (!a || !a.href) return;
      var u = new URL(a.href, location.href);
      if (u.origin !== location.origin) return;
      if (!isAdminPath(u.pathname)) return;
      if (pathKey(u.pathname) !== pathKey(location.pathname)) return;
      ev.preventDefault();
      ev.stopPropagation();
      try { window.scrollTo(0, 0); } catch (eScroll) {}
    } catch (eClick) { /* ignore */ }
  }, true);
  // Cache every live Admin page so offline navigation keeps the same UI + rows.
  // PERF-P0.3-A — HTML put first; stylesheet fetch/put only on idle after first paint.
  function ratebIdle(fn, timeoutMs) {
    try {
      if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(function () { fn(); }, { timeout: timeoutMs || 5000 });
        return;
      }
    } catch (eIdle) { /* ignore */ }
    setTimeout(fn, Math.max(2000, (timeoutMs || 5000) / 2));
  }
  function cacheLiveAdminPage() {
    try {
      if (navigator.onLine === false) return;
      if (!isAdminPath(location.pathname)) return;
      if (isOfflineShellUi()) return;
      if (/\/login|\/logout|\/password\//i.test(location.pathname)) return;
      // Never cache SaaS entitlements pages — stale HTML hides saves.
      if (/\/admin\/company-permissions/i.test(location.pathname)) return;
      if (!window.caches) return;
      var main = document.querySelector('#rateb-main-content, main.rateb-content');
      var fp = location.href + '|' + (document.title || '') + '|' + (main ? main.innerHTML.length : 0);
      var now = Date.now();
      // Skip repeat full-document serialize (outerHTML is a main-thread hitch).
      if (cacheLiveAdminPage._fp === fp && (now - (cacheLiveAdminPage._at || 0)) < 600000) {
        return;
      }
      if (cacheLiveAdminPage._busy) return;
      cacheLiveAdminPage._busy = true;
      // Strip ephemeral offline toast so Cache API HTML never shows it while online later.
      try {
        Array.prototype.forEach.call(
          document.querySelectorAll('[data-rateb-ephemeral-offline-note]'),
          function (n) { if (n && n.parentNode) { n.parentNode.removeChild(n); } }
        );
      } catch (eScrub) { /* ignore */ }
      var html = '<!DOCTYPE html>\n' + document.documentElement.outerHTML;
      cacheLiveAdminPage._busy = false;
      if (html.length < 500 || html.length > 800000) return;
      cacheLiveAdminPage._fp = fp;
      cacheLiveAdminPage._at = now;
      var cacheNames = [
        (window.RatebOfflineFullWarm && window.RatebOfflineFullWarm.cacheName) || 'rateb-erp-ops-pages-v36',
        'rateb-erp-coexist-v34'
      ];
      var keys = [location.href, location.origin + location.pathname];
      var bare = location.pathname.replace(/\/+$/, '');
      keys.push(location.origin + bare);
      keys.push(location.origin + bare + '/');
      if (/\/admin\/ops\//i.test(location.pathname)) {
        keys.push(location.origin + location.pathname.replace(/\/admin\/ops\//i, '/admin/'));
      } else if (/\/admin\/(access-control|hr|users)/i.test(location.pathname)) {
        keys.push(location.origin + location.pathname.replace(/\/admin\//i, '/admin/ops/'));
      }
      var res = new Response(html, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Rateb-Offline': '1' }
      });
      var assetHrefs = [];
      try {
        Array.prototype.forEach.call(document.querySelectorAll('link[rel="stylesheet"][href]'), function (link) {
          var href = link.getAttribute('href') || '';
          if (/\/assets\//i.test(href)) {
            try { assetHrefs.push(new URL(href, location.href).href); } catch (eA) {}
          }
        });
      } catch (eLinks) {}
      // HTML put immediately (no network). CSS re-fetch batched much later so
      // login→usable (sidebar + 1.5s netQuiet) is not held by cache-warming XHR.
      cacheNames.forEach(function (cacheName) {
        caches.open(cacheName).then(function (cache) {
          return Promise.all(keys.map(function (k) {
            return cache.put(k, res.clone()).catch(function () { return null; });
          }));
        }).catch(function () {});
      });
      if (assetHrefs.length) {
        setTimeout(function () {
          ratebIdle(function () {
            cacheNames.forEach(function (cacheName) {
              caches.open(cacheName).then(function (cache) {
                return Promise.all(assetHrefs.map(function (ah) {
                  return fetch(ah, { credentials: 'same-origin', cache: 'force-cache' }).then(function (ar) {
                    if (!ar || !ar.ok) return null;
                    var bareA = ah;
                    try {
                      var au = new URL(ah);
                      bareA = au.origin + au.pathname;
                    } catch (eBare) {}
                    return Promise.all([
                      cache.put(ah, ar.clone()).catch(function () { return null; }),
                      cache.put(bareA, ar.clone()).catch(function () { return null; })
                    ]);
                  }).catch(function () { return null; });
                }));
              }).catch(function () {});
            });
          }, 3000);
        }, 8000);
      }
    } catch (eCache) {
      cacheLiveAdminPage._busy = false;
    }
  }
  function scheduleCacheLiveAdminPage() {
    ratebIdle(function () { cacheLiveAdminPage(); }, 5000);
  }
  // Start later so charts/probe/prefetch quiet first.
  if (document.readyState === 'complete') {
    setTimeout(scheduleCacheLiveAdminPage, 8000);
  } else {
    window.addEventListener('load', function () { setTimeout(scheduleCacheLiveAdminPage, 8000); }, { once: true });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      // Debounced: cacheLiveAdminPage itself no-ops within 10 min if fingerprint unchanged.
      ratebIdle(function () { cacheLiveAdminPage(); }, 8000);
    }
  });
  try {
    function removeEphemeralOfflineNote() {
      Array.prototype.forEach.call(
        document.querySelectorAll('[data-rateb-ephemeral-offline-note]'),
        function (n) { if (n && n.parentNode) { n.parentNode.removeChild(n); } }
      );
    }
    // Drop poisoned toast from cached HTML whenever we are (or become) online.
    if (navigator.onLine !== false) {
      removeEphemeralOfflineNote();
    }
    window.addEventListener('online', removeEphemeralOfflineNote);
    // Debounce: only show after we stay offline briefly (avoids flash during F5 reconnect).
    if (navigator.onLine === false && isAdminPath(location.pathname) && !isOfflineShellUi()) {
      setTimeout(function () {
        try {
          if (navigator.onLine !== false) {
            removeEphemeralOfflineNote();
            return;
          }
          if (document.querySelector('[data-rateb-ephemeral-offline-note]')) {
            return;
          }
          var note = document.createElement('div');
          note.setAttribute('role', 'status');
          note.setAttribute('data-rateb-ephemeral-offline-note', '1');
          note.style.cssText = 'position:fixed;bottom:12px;right:12px;z-index:99998;max-width:18rem;padding:8px 12px;'
            + 'background:#7f1d1d;color:#fee2e2;font:12px/1.4 system-ui,sans-serif;border-radius:8px';
          note.textContent = 'أوفلاين: تظهر آخر نسخة محفوظة من الصفحة (الجداول من وقت آخر زيارة متصلة).';
          document.body.appendChild(note);
        } catch (eShow) { /* ignore */ }
      }, 900);
    }
  } catch (eNote) {}
  try {
    if (document.querySelector('.rateb-offline-home, #rateb-offline-shell-main, #offline-status, [data-rateb-offline-ops-banner]')) {
      if (navigator.onLine !== false) {
        var probeBase = (function () {
          var p = String(location.pathname || '');
          var m = p.match(/^(.*\/public\/)/i);
          return (m && m[1]) ? m[1] : '/rateb-erp/public/';
        })();
        // PERF-P0.3-A — live-escape probe after idle (not on critical paint/usable path).
        ratebIdle(function () {
          fetch(probeBase + 'connectivity-probe.json?_rateb_probe=' + Date.now(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' }
          }).then(function (res) {
            if (!res || !res.ok) return;
            var u = new URL(location.href);
            var already = u.searchParams.get('rateb_live') || u.searchParams.get('rateb_force_live');
            if (already) {
              var done = function () {
                u.searchParams.delete('rateb_live');
                u.searchParams.set('rateb_force_live', String(Date.now()));
                location.replace(u.href);
              };
              var jobs = [];
              if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
                jobs.push(navigator.serviceWorker.getRegistrations().then(function (regs) {
                  return Promise.all((regs || []).map(function (r) { return r.unregister(); }));
                }));
              }
              if (window.caches && caches.keys) {
                jobs.push(caches.keys().then(function (keys) {
                  return Promise.all((keys || []).map(function (k) {
                    return /^rateb-/i.test(String(k || '')) ? caches.delete(k) : null;
                  }));
                }));
              }
              Promise.all(jobs).then(done).catch(done);
              return;
            }
            u.searchParams.set('rateb_live', String(Date.now()));
            location.replace(u.href);
          }).catch(function () { /* stay on cached UI */ });
        }, 3500);
      }
    }
  } catch (eEsc) {}
  // Single deferred SW register — no forced reload on controllerchange.
  if (typeof window.__ratebErpScheduleSwRegister === 'function') {
    window.__ratebErpScheduleSwRegister(swUrl, scope);
    (window.__RATEB_SW_REGISTER_PROMISE__ || Promise.resolve(null)).then(function (reg) {
      if (reg) scheduleWarm(reg);
    });
  }
})();
</script>
<?php
}
$ratebOfflineMasterData = $ratebOfflineFullClient
    && $ratebOfflineFlagSvc
    && $ratebOfflineFlagSvc->isMasterDataEnabled();
if ($ratebOfflineMasterData) {
    ?>
<script>
window.__RATEB_ERP_MASTER_DATA__ = window.__RATEB_ERP_SHELL_OFFLINE__ || {};
if (window.__RATEB_ERP_SHELL_OFFLINE__ && window.__RATEB_ERP_SHELL_OFFLINE__.flags) {
  window.__RATEB_ERP_MASTER_DATA__.flags = window.__RATEB_ERP_SHELL_OFFLINE__.flags;
  window.__RATEB_ERP_MASTER_DATA__.apiBase = window.__RATEB_ERP_SHELL_OFFLINE__.apiBase || window.__RATEB_ERP_MASTER_DATA__.apiBase;
}
(function () {
  /* Master-data bootstrap after Offline SDK (lazy chain). */
  function inject() {
    var s = document.createElement('script');
    s.src = <?php echo json_encode(rateb_asset('offline/erp-master-data-bootstrap.js'), JSON_UNESCAPED_SLASHES); ?>;
    s.async = false;
    (document.body || document.documentElement).appendChild(s);
  }
  if (window.RatebOffline) {
    inject();
  } else {
    window.addEventListener('rateb-offline-sdk-ready', inject, { once: true });
  }
})();
</script>
<?php
}
?>
<script>
(function () {
  /* Fix9: PWA install after Online shell ready — not parse-time defer (~367ms cold).
   * Capture beforeinstallprompt early so a late script load does not miss the event. */
  var pwaUrl = <?php echo json_encode(rateb_asset('offline/erp-pwa-install.js'), JSON_UNESCAPED_SLASHES); ?>;
  var pwaLoaded = false;
  window.addEventListener('beforeinstallprompt', function (e) {
    try { e.preventDefault(); } catch (ePrev) { /* ignore */ }
    window.__RATEB_PWA_DEFERRED_PROMPT__ = e;
    injectPwa();
  });
  function injectPwa() {
    if (pwaLoaded || !pwaUrl) return;
    pwaLoaded = true;
    var s = document.createElement('script');
    s.src = pwaUrl;
    s.async = true;
    s.setAttribute('data-rateb-pwa-install', '1');
    s.onload = function () {
      try {
        var ev = window.__RATEB_PWA_DEFERRED_PROMPT__;
        if (ev && window.RatebErpPwaInstall) {
          /* Re-dispatch path: install script binds its own listener; hand off stored event via custom hook. */
          window.dispatchEvent(new CustomEvent('rateb:pwa-deferred-prompt', { detail: ev }));
        }
      } catch (eHand) { /* ignore */ }
    };
    (document.body || document.documentElement).appendChild(s);
  }
  function schedulePwa() {
    try {
      if (navigator.onLine === false) {
        setTimeout(injectPwa, 100);
        return;
      }
    } catch (eOff) { /* ignore */ }
    var go = function () {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(injectPwa, { timeout: 5000 });
      } else {
        setTimeout(injectPwa, 2000);
      }
    };
    if (document.readyState === 'complete') go();
    else window.addEventListener('load', go, { once: true });
  }
  schedulePwa();
})();
</script>
<?php if (!$ratebLocalAppliance) { ?>
<script>
/* Legacy kill: stale nav-guards used toast+preventDefault on create/edit.
 * Must NOT stopImmediatePropagation — that blocked the real offline edit→list handler
 * and left Chrome «لا يتوفر اتصال» on /companies/25/edit. */
(function () {
  try {
    var stale = document.getElementById('rateb-offline-nav-toast');
    if (stale) stale.remove();
  } catch (e) {}
})();
</script>
<script>
(function () {
  /* PERF-P3 / Fix9: do NOT load full-warm / nav-guard during first online paint.
   * Inject after idle when Online shell is ready; ASAP when already offline. */
  var warmUrl = <?php echo json_encode(rateb_asset('offline/erp-offline-full-warm.js'), JSON_UNESCAPED_SLASHES); ?>;
  var guardUrl = <?php echo json_encode(rateb_asset('offline/erp-offline-nav-guard.js'), JSON_UNESCAPED_SLASHES); ?>;
  function inject(src) {
    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    (document.body || document.documentElement).appendChild(s);
  }
  function userActive() {
    try {
      if (document.visibilityState && document.visibilityState !== 'visible') return false;
      var last = window.__RATEB_LAST_USER_ACTIVITY__;
      if (typeof last === 'number' && (Date.now() - last) > 120000) return false;
    } catch (e) {}
    return true;
  }
  function bindActivity() {
    if (window.__RATEB_ACTIVITY_BOUND__) return;
    window.__RATEB_ACTIVITY_BOUND__ = true;
    var mark = function () { window.__RATEB_LAST_USER_ACTIVITY__ = Date.now(); };
    mark();
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
      document.addEventListener(ev, mark, { passive: true, capture: true });
    });
  }
  function loadGuard() {
    inject(guardUrl);
  }
  function loadWarm() {
    if (!userActive()) return;
    try {
      if (navigator.onLine === false) return;
    } catch (e0) {}
    inject(warmUrl);
  }
  bindActivity();
  function afterLoad(fn) {
    if (document.readyState === 'complete') fn();
    else window.addEventListener('load', fn, { once: true });
  }
  afterLoad(function () {
    var offlineNow = false;
    try { offlineNow = navigator.onLine === false; } catch (eN) { offlineNow = false; }
    if (offlineNow) {
      loadGuard();
      return;
    }
    /* Online: idle after shell ready — do not compete with first paint / soft-nav. */
    var armGuard = function () {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(loadGuard, { timeout: 5000 });
      } else {
        setTimeout(loadGuard, 2000);
      }
    };
    armGuard();
    /* full-warm: start ~4s after load so offline works without visiting each page. */
    setTimeout(function () {
      if (!userActive()) {
        return;
      }
      if (window.requestIdleCallback) {
        window.requestIdleCallback(loadWarm, { timeout: 8000 });
      } else {
        loadWarm();
      }
    }, 4000);
  });
})();
</script>
<?php } ?>

</body>
</html>
