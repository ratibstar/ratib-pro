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
$approvalsOversightJs = $erpRoute !== '' && (
    str_starts_with($erpRoute, 'admin/oversight/approvals')
    || str_starts_with($erpRoute, 'admin/oversight/companies-approvals')
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
          keys.push(u.href, u.origin + u.pathname);
        } catch (e1) {}
        return caches.keys().then(function (names) {
          var chain = Promise.resolve(null);
          (names || []).forEach(function (name) {
            if (!/^rateb-/i.test(String(name || ''))) return;
            chain = chain.then(function (hit) {
              if (hit) return hit;
              return caches.open(name).then(function (c) {
                var inner = Promise.resolve(null);
                keys.forEach(function (k) {
                  inner = inner.then(function (h) {
                    return h || c.match(k).then(function (m) {
                      return m || c.match(k, { ignoreSearch: true }).catch(function () { return null; });
                    });
                  });
                });
                return inner;
              });
            });
          });
          return chain;
        }).catch(function () { return null; });
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
      /* Head: claim only when offline — never register here (single register owns boot). */
      if ('serviceWorker' in navigator) {
        try {
          var scope = location.origin + publicBase();
          if (navigator.onLine === false) {
            navigator.serviceWorker.getRegistration(scope).then(function (reg) {
              if (!reg) return;
              try {
                if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                if (reg.active) reg.active.postMessage({ type: 'CLIENTS_CLAIM' });
              } catch (eClaim) {}
            }).catch(function () {});
          }
        } catch (e3) {}
      }
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
    <?php /* PERF-P3: console-quiet after DCL — defer in <head> delays DOMContentLoaded */ ?>
    <script>
    (function () {
      function loadQuiet() {
        var s = document.createElement('script');
        s.src = <?php echo json_encode(rateb_asset('js/rateb-console-quiet.js'), JSON_UNESCAPED_SLASHES); ?>;
        document.head.appendChild(s);
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadQuiet, { once: true });
      } else {
        loadQuiet();
      }
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
    /* PERF-P3: one tiny blocking shell stylesheet (cached); everything else preload→swap. */
    $ratebThemeDarkCss = rateb_asset('css/dark.css');
    $ratebThemeLightCss = rateb_asset('css/light.css');
    $ratebAsyncStyles = [
        rateb_tajawal_font_css(),
        rateb_bootstrap_css(),
        rateb_asset('css/variables.css'),
        rateb_asset('css/main.css'),
        rateb_asset('css/components.css'),
        $ratebThemeDarkCss,
        rateb_asset('css/rtl.css'),
        rateb_fontawesome_css(),
    ];
    if (!empty($loadModulePageStatsCss) || !empty($layoutAssets['charts'])) {
        $ratebAsyncStyles[] = rateb_asset('css/dashboard.css');
    }
    if ($dir === 'rtl') {
        $ratebAsyncStyles[] = rateb_asset('css/ar-typography.css');
    }
    $ratebAsyncStyles[] = rateb_tajawal_font_rest_css();
    ?>
    <link id="rateb-critical-shell" href="<?php echo rateb_asset('css/critical-shell.css'); ?>" rel="stylesheet">
    <link rel="preload" href="<?php echo rateb_vendor_asset('fonts/tajawal/tajawal-400.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <script>
    (function () {
      /* PERF-P3: preload → stylesheet swap (non-blocking). */
      var sheets = <?php echo json_encode(array_values(array_filter($ratebAsyncStyles, static function ($h) use ($ratebThemeDarkCss, $ratebThemeLightCss) {
          return $h !== $ratebThemeDarkCss && $h !== $ratebThemeLightCss;
      })), JSON_UNESCAPED_SLASHES); ?>;
      var themeDark = <?php echo json_encode($ratebThemeDarkCss, JSON_UNESCAPED_SLASHES); ?>;
      var themeLight = <?php echo json_encode($ratebThemeLightCss, JSON_UNESCAPED_SLASHES); ?>;
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
        setTimeout(function () {
          if (link.rel === 'preload') {
            link.rel = 'stylesheet';
          }
        }, 3000);
      }
      swapIn(bs === 'light' ? themeLight : themeDark, 'rateb-theme-css');
      sheets.forEach(function (href) { swapIn(href); });
    })();
    </script>
    <noscript>
      <link href="<?php echo rateb_tajawal_font_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
      <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
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
            <a href="<?php echo rateb_url('admin'); ?>" class="rateb-nav-link<?php echo $navActive('admin') && !$accountingActive ? ' active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span>
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
                        ['admin/oversight/approvals', 'approvals_oversight', 'fa-check-double', 'workflows.view'],
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
            <?php if (rateb_is_super_admin()) { ?>
            <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
            <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-nav-link<?php echo $navActive('admin/executive-dashboard') ? ' active' : ''; ?>">
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
            $accessControlLinks[] = ['admin/sms-templates', 'sms_templates', 'fa-sms', 'settings.manage'];
            $adminSection(__('access_control'), $accessControlLinks, 'fa-key');
            ?>
            <?php } ?>
        </nav>
    </aside>
<script>
/* Sidebar toggles: bind immediately after aside parse — before app.js (fixes first-click race). */
(function () {
  function hydrateNavLazy(group) {
    if (!group) return;
    var body = group.querySelector('.rateb-nav-group-body, .rateb-nav-subgroup-body');
    if (!body) return;
    var tpl = null;
    var kids = body.children;
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
    try {
      if (window.RatebNavInstant && typeof window.RatebNavInstant.bindPrefetch === 'function') {
        window.RatebNavInstant.bindPrefetch(body);
      }
    } catch (eBind) { /* ignore */ }
  }
  function bindSidebarNavGroups() {
    var side = document.getElementById('rateb-sidebar');
    if (!side || side.getAttribute('data-rateb-nav-delegated') === '1') return;
    side.setAttribute('data-rateb-nav-delegated', '1');
    side.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-nav-group-toggle]') : null;
      if (!btn || !side.contains(btn)) return;
      var group = btn.closest('[data-nav-group]');
      if (!group) return;
      var willOpen = !group.classList.contains('is-open');
      if (willOpen) hydrateNavLazy(group);
      var open = group.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  bindSidebarNavGroups();
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
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('theme_dark'); ?>">
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" title="<?php echo __('theme_light'); ?>"><i class="fas fa-sun"></i></button>
                    <button type="button" class="btn btn-outline-secondary active" data-theme-choice="dark" title="<?php echo __('theme_dark'); ?>"><i class="fas fa-moon"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="auto" title="<?php echo __('theme_auto'); ?>"><i class="fas fa-circle-half-stroke"></i></button>
                </div>
                <a href="<?php echo rateb_url('admin/logout'); ?>" class="btn btn-outline-danger btn-sm rateb-topbar-logout" data-rateb-full-nav="1" title="<?php echo __('logout'); ?>">
                    <i class="fas fa-sign-out-alt"></i><span class="d-none d-md-inline ms-1"><?php echo __('logout'); ?></span>
                </a>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('language'); ?>">
                    <a href="<?php echo rateb_url('locale/en'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>" data-locale="en">EN</a>
                    <a href="<?php echo rateb_url('locale/ar'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>" data-locale="ar">عربي</a>
                </div>
            </div>
        </header>
        <main class="rateb-content" id="rateb-main-content">
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
            <?php if (function_exists('rateb_branch_access_all') && rateb_branch_access_all() && !rateb_is_portal_branch_session() && rateb_company_branches_nav_enabled()) {
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
            <?php } ?>
            <?php
            $showOpsCompanyPicker = rateb_is_super_admin()
                && rateb_is_platform_oversight_host()
                && (
                rateb_is_ops_route($erpRoute)
                || strpos($currentPath, '/admin/ops/') !== false
            );
            if ($showOpsCompanyPicker) {
                Rateb\App\Core\View::partial('ops-company-select');
            }
            if ($deferModulePageMetrics) {
                Rateb\App\Core\View::partial('module-page-stats', [
                    'async' => true,
                    'metricsRoute' => $erpRoute,
                    'metricsUrl' => rateb_url('admin/api/module-metrics') . '?route=' . rawurlencode($erpRoute),
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
/* PERF-P3: critical-path scripts only before paint settles; rest after interaction/idle. */
$ratebCriticalScripts = [
    rateb_asset('js/theme.js'),
    rateb_asset('js/app.js'),
    rateb_asset('js/erp-nav-instant.js'),
    // PERF-P4: metrics listener must be present before soft-nav afterEnter (not idle).
    rateb_asset('js/module-page-stats.js'),
];
$ratebIdleScripts = [
    rateb_bootstrap_js(),
    rateb_asset('js/connectivity-indicator.js'),
    rateb_asset('js/lang.js'),
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
if ($approvalsOversightJs) {
    $ratebIdleScripts[] = rateb_asset('js/approvals-oversight.js');
}
if ($navActive('admin/agency-updates')) {
    $ratebIdleScripts[] = rateb_asset('js/agency-updates.js');
}
foreach ($ratebCriticalScripts as $ratebCritSrc) {
    /* listed for post-DCL inject — do not emit defer (defer delays DCL). */
}
$deferAssetScripts = [];
// Chart.js + charts.js strictly after first paint / idle (dashboard widgets).
if (!empty($layoutAssets['charts'])) {
    $deferAssetScripts[] = rateb_chartjs('4.4.3');
}
foreach ($layoutAssets['defer'] ?? [] as $deferFile) {
    $deferAssetScripts[] = rateb_asset('js/' . $deferFile);
}
?>
<script>
(function () {
  /* PERF-P3: critical JS AFTER DOMContentLoaded so DCL is not blocked by defer scripts. */
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
  function loadCritical() {
    chain(critical, 0, function () {
      try { window.dispatchEvent(new Event('rateb-critical-js-ready')); } catch (e) {}
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadCritical, { once: true });
  } else {
    loadCritical();
  }
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
        window.requestIdleCallback(go, { timeout: 4000 });
      } else {
        setTimeout(go, 1200);
      }
    };
    if (document.readyState === 'complete') idleStart();
    else window.addEventListener('load', idleStart, { once: true });
  }
  afterInteraction(function () {
    chain(idleQueue, 0, function () {
      if (chartQueue.length) {
        if (window.requestIdleCallback) {
          window.requestIdleCallback(function () { chain(chartQueue, 0); }, { timeout: 6000 });
        } else {
          setTimeout(function () { chain(chartQueue, 0); }, 800);
        }
      }
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
        'rateb_erp_full_warm_assets_v13'
      ].forEach(function (k) {
        try { localStorage.removeItem(k); } catch (eR) {}
      });
      sessionStorage.removeItem('rateb_sw_reloaded');
      sessionStorage.removeItem('rateb_sw_shell_warm_v46');
    } catch (e1) {}
  }
  window.__RATEB_ASSET_BUILD__ = NEED;
  // Stale SW: soft update only — never forced reload (startup spin loops).
  try {
    if ('serviceWorker' in navigator && navigator.onLine !== false) {
      navigator.serviceWorker.getRegistrations().then(function (regs) {
        (regs || []).forEach(function (reg) {
          var script = '';
          try {
            script = (reg.active && reg.active.scriptURL) || (reg.waiting && reg.waiting.scriptURL) || '';
          } catch (eS) { script = ''; }
          if (script && script.indexOf(NEED) === -1) {
            try {
              if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
              if (typeof reg.update === 'function') reg.update();
            } catch (eU) {}
          }
        });
      }).catch(function () {});
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
          var getReg = scope
            ? navigator.serviceWorker.getRegistration(scope)
            : navigator.serviceWorker.getRegistration();
          return getReg.then(claim).catch(function () { return null; });
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
    var run = function () {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(function () {
          window.__ratebErpRegisterSwOnce(swUrl, scope);
        }, { timeout: 3500 });
      } else {
        setTimeout(function () {
          window.__ratebErpRegisterSwOnce(swUrl, scope);
        }, 800);
      }
    };
    if (document.readyState === 'complete') {
      run();
    } else {
      window.addEventListener('load', run, { once: true });
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
<script src="<?php echo rateb_asset('offline/erp-offline-tenant-context.js'); ?>" defer></script>
<?php
        $ratebOfflineLazyScripts = [];
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
    s.src = urls[i];
    s.async = false;
    s.onload = function () { loadChain(i + 1); };
    s.onerror = function () { loadChain(i + 1); };
    (document.body || document.documentElement).appendChild(s);
  }
  function startSdk() {
    mark('sdkStart');
    loadChain(0);
  }
  function afterInteractive(fn) {
    var kick = function () {
      try {
        if (navigator.onLine === false) {
          /* Offline: still post-paint but ASAP for SDK. */
          setTimeout(fn, 50);
          return;
        }
      } catch (e0) {}
      /* PERF-P3: do not start Offline SDK warm-path assets on first paint.
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
<script src="<?php echo rateb_asset('offline/erp-offline-tenant-context.js'); ?>" defer></script>
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
    if (a) {
      purgeErpAuthCache();
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
      var html = '<!DOCTYPE html>\n' + document.documentElement.outerHTML;
      if (html.length < 500 || html.length > 2500000) return;
      var cacheNames = [
        (window.RatebOfflineFullWarm && window.RatebOfflineFullWarm.cacheName) || 'rateb-erp-ops-pages-v34',
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
        }, 4000);
      }
    } catch (eCache) { /* ignore */ }
  }
  function scheduleCacheLiveAdminPage() {
    ratebIdle(function () { cacheLiveAdminPage(); }, 3000);
  }
  // PERF-P0.3-A — start after load+2s so charts/probe quiet (~1.5s) can clear first.
  if (document.readyState === 'complete') {
    setTimeout(scheduleCacheLiveAdminPage, 2000);
  } else {
    window.addEventListener('load', function () { setTimeout(scheduleCacheLiveAdminPage, 2000); }, { once: true });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      ratebIdle(function () { cacheLiveAdminPage(); }, 4000);
    }
  });
  try {
    if (navigator.onLine === false && isAdminPath(location.pathname) && !isOfflineShellUi()) {
      var note = document.createElement('div');
      note.setAttribute('role', 'status');
      note.style.cssText = 'position:fixed;bottom:12px;right:12px;z-index:99998;max-width:18rem;padding:8px 12px;'
        + 'background:#7f1d1d;color:#fee2e2;font:12px/1.4 system-ui,sans-serif;border-radius:8px';
      note.textContent = 'أوفلاين: تظهر آخر نسخة محفوظة من الصفحة (الجداول من وقت آخر زيارة متصلة).';
      document.body.appendChild(note);
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
<script src="<?php echo rateb_asset('offline/erp-pwa-install.js'); ?>" defer></script>
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
  /* PERF-P3: do NOT load full-warm during first online page.
   * Inject only after: requestIdleCallback AND min 20s AND user still active.
   * nav-guard can load earlier (idle) — it does not warm assets. */
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
    /* nav-guard: idle, not on critical path */
    if (window.requestIdleCallback) {
      window.requestIdleCallback(loadGuard, { timeout: 10000 });
    } else {
      setTimeout(loadGuard, 3000);
    }
    /* full-warm: idle + 20s + still active */
    var scheduleWarm = function () {
      setTimeout(function () {
        if (window.requestIdleCallback) {
          window.requestIdleCallback(loadWarm, { timeout: 30000 });
        } else {
          loadWarm();
        }
      }, 20000);
    };
    if (window.requestIdleCallback) {
      window.requestIdleCallback(scheduleWarm, { timeout: 60000 });
    } else {
      setTimeout(scheduleWarm, 5000);
    }
  });
})();
</script>
<?php } ?>

</body>
</html>
