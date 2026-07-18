/**
 * FULL FRONTEND PERFORMANCE AUDIT — Admin ERP (measure only).
 * Playwright + Performance API + Lighthouse + CDP.
 * Does NOT modify ERP architecture.
 *
 *   node frontend-perf-full-audit.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const lighthouseMod = require('lighthouse');
const lighthouse = lighthouseMod.default || lighthouseMod;

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT_DIR = path.join(__dirname, 'reports');
const MIN_MS = 5;

function ssh(cmd, timeoutMs) {
  return execFileSync(
    'ssh',
    ['-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15', HOST, cmd],
    { encoding: 'utf8', timeout: timeoutMs || 120000 }
  );
}

function shortUrl(u) {
  try {
    const x = new URL(u);
    return x.pathname + x.search;
  } catch {
    return String(u).slice(0, 180);
  }
}

function r1(n) {
  return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
}

function mintSession() {
  return JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );
}

async function waitUsable(page, timeoutMs) {
  const t0 = Date.now();
  const deadline = t0 + timeoutMs;
  while (Date.now() < deadline) {
    const ok = await page.evaluate(() => {
      const sidebar = !!document.querySelector('aside.rateb-sidebar, #rateb-sidebar');
      const main = document.querySelector('main, .rateb-main, #content, .content-wrapper, .rateb-content');
      const text = main ? (main.innerText || '').trim().length : 0;
      return sidebar && text > 20 && document.readyState === 'complete';
    });
    if (ok) return Date.now() - t0;
    await page.waitForTimeout(100);
  }
  return Date.now() - t0;
}

async function installObservers(page) {
  await page.addInitScript(() => {
    const S = (window.__FE_AUDIT__ = {
      longTasks: [],
      layoutShifts: [],
      idb: [],
      sw: { registerMs: null, readyMs: null, controllerAt: null, scriptURL: null },
      fonts: [],
      fetchAfterFp: [],
      fpAt: null,
      listenersAdded: 0,
      memorySamples: [],
      marks: [],
      scriptEval: [],
      deferredScripts: [],
      iconNodes: 0,
      images: [],
      sidebarAt: null,
      dashboardAt: null,
      offlineBoot: [],
    });

    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          S.longTasks.push({
            start: Math.round(e.startTime * 10) / 10,
            dur: Math.round(e.duration * 10) / 10,
            name: e.name || 'longtask',
          });
        });
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}

    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          if (e.hadRecentInput) return;
          S.layoutShifts.push({
            start: Math.round(e.startTime * 10) / 10,
            value: Math.round(e.value * 10000) / 10000,
          });
        });
      }).observe({ type: 'layout-shift', buffered: true });
    } catch (e2) {}

    try {
      const oOpen = indexedDB.open.bind(indexedDB);
      indexedDB.open = function (name, version) {
        const t0 = performance.now();
        const req = oOpen(name, version);
        const rec = { name: String(name), version: version || null, start: Math.round(t0 * 10) / 10, ms: null };
        S.idb.push(rec);
        req.addEventListener('success', () => {
          rec.ms = Math.round((performance.now() - t0) * 10) / 10;
        });
        req.addEventListener('error', () => {
          rec.ms = Math.round((performance.now() - t0) * 10) / 10;
          rec.error = true;
        });
        return req;
      };
    } catch (e3) {}

    try {
      const origAdd = EventTarget.prototype.addEventListener;
      EventTarget.prototype.addEventListener = function () {
        S.listenersAdded += 1;
        return origAdd.apply(this, arguments);
      };
    } catch (e4) {}

    try {
      const origFetch = window.fetch.bind(window);
      window.fetch = function () {
        const url = String(arguments[0] && arguments[0].url ? arguments[0].url : arguments[0]);
        const t0 = performance.now();
        const p = origFetch.apply(this, arguments);
        p.then(
          () => {
            if (S.fpAt != null && t0 >= S.fpAt) {
              S.fetchAfterFp.push({
                url: url.replace(/^https?:\/\/[^/]+/, '').slice(0, 160),
                start: Math.round(t0 * 10) / 10,
                ms: Math.round((performance.now() - t0) * 10) / 10,
              });
            }
          },
          () => {}
        );
        return p;
      };
    } catch (e5) {}

    // Capture FP once paint arrives
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          if (e.name === 'first-paint' || e.name === 'first-contentful-paint') {
            if (S.fpAt == null) S.fpAt = e.startTime;
          }
        });
      }).observe({ type: 'paint', buffered: true });
    } catch (e6) {}

    // SW timing
    if (navigator.serviceWorker) {
      const swT0 = performance.now();
      navigator.serviceWorker.ready
        .then((reg) => {
          S.sw.readyMs = Math.round((performance.now() - swT0) * 10) / 10;
          S.sw.scriptURL = (reg && reg.active && reg.active.scriptURL) || null;
        })
        .catch(() => {});
      const checkCtrl = () => {
        if (navigator.serviceWorker.controller && S.sw.controllerAt == null) {
          S.sw.controllerAt = Math.round(performance.now() * 10) / 10;
          S.sw.scriptURL = navigator.serviceWorker.controller.scriptURL;
        }
      };
      checkCtrl();
      navigator.serviceWorker.addEventListener('controllerchange', checkCtrl);
    }

    // Font loading
    if (document.fonts && document.fonts.ready) {
      const ft0 = performance.now();
      document.fonts.ready.then(() => {
        S.fonts.push({
          event: 'fonts.ready',
          ms: Math.round((performance.now() - ft0) * 10) / 10,
          size: document.fonts.size,
        });
      });
    }

    // Sidebar / dashboard paint markers via MutationObserver
    const markDom = () => {
      if (S.sidebarAt == null) {
        const sb = document.querySelector('aside.rateb-sidebar, #rateb-sidebar');
        if (sb && sb.offsetHeight > 0) S.sidebarAt = Math.round(performance.now() * 10) / 10;
      }
      if (S.dashboardAt == null) {
        const dash = document.querySelector(
          '.dashboard, #dashboard, [data-page="dashboard"], .rateb-dashboard, main .card, main .stat-card, .content-wrapper'
        );
        if (dash && (dash.innerText || '').trim().length > 40) {
          S.dashboardAt = Math.round(performance.now() * 10) / 10;
        }
      }
    };
    try {
      new MutationObserver(markDom).observe(document.documentElement, { childList: true, subtree: true });
    } catch (e7) {}
    document.addEventListener('DOMContentLoaded', markDom);
    window.addEventListener('load', markDom);
  });
}

async function collectDeep(page) {
  return page.evaluate(() => {
    const S = window.__FE_AUDIT__ || {};
    const nav = performance.getEntriesByType('navigation')[0] || null;
    const paint = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paint[p.name] = Math.round(p.startTime * 10) / 10;
    });
    let lcp = null;
    let lcpSize = null;
    try {
      const l = performance.getEntriesByType('largest-contentful-paint');
      if (l.length) {
        const last = l[l.length - 1];
        lcp = Math.round(last.startTime * 10) / 10;
        lcpSize = last.size || null;
      }
    } catch (e) {}

    const resources = (performance.getEntriesByType('resource') || []).map((r) => {
      const short = (() => {
        try {
          const u = new URL(r.name);
          return u.pathname + u.search;
        } catch (e2) {
          return String(r.name).slice(0, 160);
        }
      })();
      return {
        short,
        type: r.initiatorType,
        start: Math.round(r.startTime * 10) / 10,
        dur: Math.round(r.duration * 10) / 10,
        ttfb: Math.round(((r.responseStart || 0) - (r.requestStart || 0)) * 10) / 10,
        download: Math.round(((r.responseEnd || 0) - (r.responseStart || 0)) * 10) / 10,
        transfer: r.transferSize || 0,
        encoded: r.encodedBodySize || 0,
        decoded: r.decodedBodySize || 0,
        protocol: r.nextHopProtocol || '',
        renderBlocking: r.renderBlockingStatus || null,
      };
    });

    const scripts = resources.filter((r) => r.type === 'script' || /\.js(\?|$)/i.test(r.short));
    const css = resources.filter((r) => r.type === 'link' || /\.css(\?|$)/i.test(r.short));
    const fonts = resources.filter((r) => r.type === 'css' || /\.(woff2?|ttf|otf)(\?|$)/i.test(r.short) || /font/i.test(r.short));
    const images = resources.filter((r) => r.type === 'img' || /\.(png|jpe?g|gif|webp|svg)(\?|$)/i.test(r.short));
    const xhr = resources.filter((r) => r.type === 'fetch' || r.type === 'xmlhttprequest');

    // Blocking CSS: renderBlockingStatus === 'blocking' OR classic stylesheet without media=print trick
    const blockingCss = css.filter((r) => r.renderBlocking === 'blocking' || (r.dur >= 5 && !/print/i.test(r.short)));

    // Deferred script tags
    const deferredScripts = Array.from(document.querySelectorAll('script[src]')).map((s) => ({
      src: (s.getAttribute('src') || '').replace(/^https?:\/\/[^/]+/, ''),
      defer: !!s.defer,
      async: !!s.async,
      type: s.type || 'text/javascript',
    }));

    // Icon / FA nodes
    const iconNodes = document.querySelectorAll('i.fa, i.fas, i.far, i.fab, i.fal, [class*="fa-"], svg.icon, .bi').length;

    // DOM size / render cost proxy
    const domNodes = document.querySelectorAll('*').length;
    const sidebarHtml = (document.querySelector('aside.rateb-sidebar, #rateb-sidebar') || {}).innerHTML || '';
    const sidebarBytes = sidebarHtml.length;
    const mainEl = document.querySelector('main, .rateb-main, #content, .content-wrapper');
    const mainBytes = mainEl ? (mainEl.innerHTML || '').length : 0;

    // Event listeners (Chrome-only approximate via getEventListeners if unavailable → use hook count)
    let listenerBreakdown = null;
    try {
      if (typeof getEventListeners === 'function') {
        const sample = [document, document.body, window].filter(Boolean);
        listenerBreakdown = sample.map((el) => {
          const map = getEventListeners(el);
          let n = 0;
          Object.keys(map).forEach((k) => {
            n += map[k].length;
          });
          return { target: el === window ? 'window' : el.nodeName, count: n };
        });
      }
    } catch (e3) {}

    let memory = null;
    try {
      if (performance.memory) {
        memory = {
          usedMB: Math.round((performance.memory.usedJSHeapSize / 1048576) * 10) / 10,
          totalMB: Math.round((performance.memory.totalJSHeapSize / 1048576) * 10) / 10,
          limitMB: Math.round((performance.memory.jsHeapSizeLimit / 1048576) * 10) / 10,
        };
      }
    } catch (e4) {}

    // Offline bootstrap globals
    const offline = {
      hasErpShell: typeof window.RatebErpShell !== 'undefined' || typeof window.RatebOfflineV2Runtime !== 'undefined',
      hasOfflineBoot: !!document.querySelector('script[src*="erp-shell-bootstrap"], script[src*="offline"]'),
      swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
    };

    const cls = (S.layoutShifts || []).reduce((a, x) => a + (x.value || 0), 0);
    const longTaskSum = (S.longTasks || []).reduce((a, x) => a + (x.dur || 0), 0);

    return {
      href: location.href,
      title: document.title,
      nav: nav
        ? {
            dns: r1(nav.domainLookupEnd - nav.domainLookupStart),
            connect: r1(nav.connectEnd - nav.connectStart),
            tls: r1(nav.secureConnectionStart > 0 ? nav.connectEnd - nav.secureConnectionStart : 0),
            ttfb: r1(nav.responseStart - nav.requestStart),
            responseStart: r1(nav.responseStart),
            download: r1(nav.responseEnd - nav.responseStart),
            domInteractive: r1(nav.domInteractive),
            dcl: r1(nav.domContentLoadedEventEnd),
            load: r1(nav.loadEventEnd),
            transfer: nav.transferSize || 0,
            encoded: nav.encodedBodySize || 0,
            type: nav.type,
          }
        : null,
      paint,
      lcp,
      lcpSize,
      cls: Math.round(cls * 10000) / 10000,
      resources,
      scripts,
      css,
      fonts,
      images,
      xhr,
      blockingCss,
      deferredScripts,
      iconNodes,
      domNodes,
      sidebarBytes,
      mainBytes,
      listenerBreakdown,
      listenersAdded: S.listenersAdded || 0,
      longTasks: (S.longTasks || []).slice().sort((a, b) => b.dur - a.dur),
      longTaskSum: Math.round(longTaskSum * 10) / 10,
      layoutShifts: S.layoutShifts || [],
      idb: S.idb || [],
      sw: S.sw || {},
      fontsReady: S.fonts || [],
      fetchAfterFp: (S.fetchAfterFp || []).slice().sort((a, b) => b.ms - a.ms),
      sidebarAt: S.sidebarAt,
      dashboardAt: S.dashboardAt,
      memory,
      offline,
      fpAt: S.fpAt,
    };

    function r1(n) {
      return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
    }
  });
}

function mapFile(urlOrLabel) {
  const u = String(urlOrLabel || '');
  const rules = [
    [/erp-nav-instant\.js/, { file: 'public/assets/js/erp-nav-instant.js', fn: 'idlePrefetchVisible / navigate / afterEnter' }],
    [/app\.js/, { file: 'public/assets/js/app.js', fn: 'RatebApp.init / reinit' }],
    [/connectivity-indicator\.js/, { file: 'public/assets/js/connectivity-indicator.js', fn: 'probe / applied' }],
    [/erp-shell-bootstrap\.js/, { file: 'public/assets/offline/erp-shell-bootstrap.js', fn: 'bootstrap / registerSW' }],
    [/erp-pwa-install\.js/, { file: 'public/assets/offline/erp-pwa-install.js', fn: 'install prompt bind' }],
    [/bootstrap\.bundle/, { file: 'public/assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js', fn: 'Bootstrap bundle parse/eval' }],
    [/fontawesome|fa-/, { file: 'views/layouts/main.php + Font Awesome CSS/webfonts', fn: 'rateb_fontawesome_css()' }],
    [/tajawal|fonts\.google/, { file: 'views/layouts/main.php', fn: 'rateb_tajawal_font_css()' }],
    [/main\.css/, { file: 'public/assets/css/main.css', fn: 'render-blocking stylesheet' }],
    [/variables\.css/, { file: 'public/assets/css/variables.css', fn: 'render-blocking stylesheet' }],
    [/components\.css/, { file: 'public/assets/css/components.css', fn: 'render-blocking stylesheet' }],
    [/rtl\.css/, { file: 'public/assets/css/rtl.css', fn: 'render-blocking stylesheet' }],
    [/dashboard\.css/, { file: 'public/assets/css/dashboard.css', fn: 'deferred stylesheet (media=print onload)' }],
    [/pos-sw|service-worker|sw\.js/, { file: 'public/admin pos-sw / offline SW', fn: 'serviceWorker.register / activate' }],
    [/module-page-stats/, { file: 'public/assets/js/module-page-stats.js', fn: 'boot on afterEnter' }],
    [/rateb-confirm\.js/, { file: 'public/assets/js/rateb-confirm.js', fn: 'confirm helpers' }],
    [/rateb-modal\.js/, { file: 'public/assets/js/rateb-modal.js', fn: 'modal helpers' }],
    [/\/admin\/?(\?|$)/, { file: 'views/layouts/main.php + dashboard view', fn: 'layout render / sidebar HTML' }],
    [/sidebar|rateb-sidebar/, { file: 'views/layouts/main.php', fn: 'sidebar nav markup loop' }],
  ];
  for (const [re, m] of rules) {
    if (re.test(u)) return m;
  }
  return { file: u.slice(0, 120) || 'unknown', fn: 'n/a' };
}

function pushBn(list, item) {
  if (!item || item.ms == null || item.ms < MIN_MS) return;
  list.push(item);
}

function buildBottlenecks(report) {
  const bn = [];
  const cold = report.cold || {};
  const deep = cold.deep || {};
  const nav = deep.nav || {};
  const lh = report.lighthouse || {};

  // 1 Navigation timing pieces (frontend-visible; skip origin TTFB if tiny)
  pushBn(bn, {
    id: 'nav_dcl',
    area: 1,
    label: 'DOMContentLoaded (cold Admin)',
    ms: nav.dcl,
    savings_ms: nav.dcl && nav.ttfb != null ? Math.max(0, r1(nav.dcl - nav.ttfb - (nav.download || 0))) : null,
    ...mapFile('/admin/'),
    evidence: 'PerformanceNavigationTiming.domContentLoadedEventEnd',
  });
  pushBn(bn, {
    id: 'nav_load',
    area: 1,
    label: 'window.load (cold Admin)',
    ms: nav.load,
    savings_ms: nav.load && nav.dcl ? r1(nav.load - nav.dcl) : null,
    ...mapFile('/admin/'),
    evidence: 'PerformanceNavigationTiming.loadEventEnd',
  });
  pushBn(bn, {
    id: 'nav_usable',
    area: 1,
    label: 'Usable wall (sidebar+main complete)',
    ms: cold.usable_ms,
    savings_ms: cold.usable_ms && nav.dcl ? Math.max(0, r1(cold.usable_ms - nav.dcl)) : null,
    file: 'views/layouts/main.php + public/assets/js/*',
    fn: 'layout + deferred scripts settle',
    evidence: 'Playwright usable wait',
  });

  // 2 Resource waterfall — top scripts/css
  (deep.scripts || [])
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 8)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'script_waterfall_' + i,
        area: 2,
        label: 'Script resource: ' + r.short.split('?')[0].split('/').pop(),
        ms: r.dur,
        savings_ms: r.dur,
        ...mapFile(r.short),
        evidence: 'ResourceTiming duration start=' + r.start,
      });
    });
  (deep.css || [])
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 6)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'css_waterfall_' + i,
        area: 4,
        label: 'CSS resource: ' + r.short.split('?')[0].split('/').pop(),
        ms: r.dur,
        savings_ms: r.renderBlocking === 'blocking' ? r.dur : r1((r.dur || 0) * 0.5),
        ...mapFile(r.short),
        evidence: 'ResourceTiming renderBlocking=' + (r.renderBlocking || 'n/a'),
      });
    });

  // 3 JS execution — long tasks + script decoded as proxy for parse/compile
  const ltSum = deep.longTaskSum || 0;
  pushBn(bn, {
    id: 'js_longtasks_sum',
    area: 3,
    label: 'JS long tasks total (main-thread)',
    ms: ltSum,
    savings_ms: ltSum,
    file: 'public/assets/js/* (see largest longtask)',
    fn: 'main-thread script work',
    evidence: 'PerformanceObserver longtask sum',
  });
  (deep.longTasks || []).slice(0, 5).forEach((t, i) => {
    pushBn(bn, {
      id: 'longtask_' + i,
      area: 6,
      label: 'Long task #' + (i + 1) + ' @' + t.start + 'ms',
      ms: t.dur,
      savings_ms: Math.max(0, r1(t.dur - 50)),
      file: 'public/assets/js (attribution via timeline)',
      fn: 'longtask attribution',
      evidence: 'longtask start=' + t.start,
    });
  });

  // 5 CLS — convert to ms-equivalent only if we have shift windows; else report shift cost via LH TBT interaction
  if ((deep.layoutShifts || []).length) {
    const shiftWindow = (deep.layoutShifts || []).reduce((a, s) => a + 16, 0); // ~1 frame each
    pushBn(bn, {
      id: 'cls_frames',
      area: 5,
      label: 'Layout shifts (approx frame cost), CLS=' + deep.cls,
      ms: shiftWindow,
      savings_ms: shiftWindow,
      file: 'views/layouts/main.php + CSS/fonts/icons',
      fn: 'late font/icon/CSS apply',
      evidence: 'layout-shift count=' + deep.layoutShifts.length + ' cls=' + deep.cls,
    });
  }

  // 7 DOM render
  pushBn(bn, {
    id: 'dom_nodes',
    area: 7,
    label: 'DOM size cost proxy (nodes=' + deep.domNodes + ')',
    ms: deep.sidebarAt && deep.nav ? Math.max(MIN_MS, r1((deep.sidebarAt || 0) - (deep.nav.responseStart || 0))) : null,
    savings_ms: deep.sidebarBytes > 40000 ? 40 : 15,
    file: 'views/layouts/main.php',
    fn: 'sidebar nav markup loop',
    evidence: 'domNodes=' + deep.domNodes + ' sidebarBytes=' + deep.sidebarBytes,
  });

  // 8 Event listeners
  pushBn(bn, {
    id: 'listeners',
    area: 8,
    label: 'addEventListener calls during boot (' + deep.listenersAdded + ')',
    ms: deep.listenersAdded > 200 ? r1(deep.listenersAdded * 0.05) : r1((deep.listenersAdded || 0) * 0.03),
    savings_ms: deep.listenersAdded > 200 ? r1(deep.listenersAdded * 0.03) : null,
    file: 'public/assets/js/erp-nav-instant.js + app.js',
    fn: 'bindPrefetch / RatebApp.init listeners',
    evidence: 'hooked EventTarget.addEventListener count',
  });

  // 9 Sidebar
  pushBn(bn, {
    id: 'sidebar_render',
    area: 9,
    label: 'Sidebar first visible',
    ms: deep.sidebarAt,
    savings_ms: deep.sidebarAt && nav.ttfb != null ? Math.max(0, r1(deep.sidebarAt - nav.responseStart)) : null,
    file: 'views/layouts/main.php',
    fn: 'aside.rateb-sidebar render',
    evidence: 'MutationObserver sidebarAt',
  });

  // 10 Dashboard
  pushBn(bn, {
    id: 'dashboard_render',
    area: 10,
    label: 'Dashboard main content visible',
    ms: deep.dashboardAt,
    savings_ms: deep.dashboardAt && deep.sidebarAt ? Math.max(0, r1(deep.dashboardAt - deep.sidebarAt)) : null,
    file: 'views/admin/dashboard (or home) + main.php',
    fn: 'dashboard view render',
    evidence: 'MutationObserver dashboardAt',
  });

  // 11 API after FP
  const fetchSum = (deep.fetchAfterFp || []).reduce((a, x) => a + (x.ms || 0), 0);
  pushBn(bn, {
    id: 'api_after_fp_sum',
    area: 11,
    label: 'Fetch/XHR after first paint (sum)',
    ms: fetchSum,
    savings_ms: fetchSum,
    file: 'public/assets/js/* + module APIs',
    fn: 'post-paint fetch',
    evidence: 'count=' + (deep.fetchAfterFp || []).length,
  });
  (deep.fetchAfterFp || []).slice(0, 5).forEach((f, i) => {
    pushBn(bn, {
      id: 'api_after_fp_' + i,
      area: 11,
      label: 'Post-FP fetch: ' + f.url.split('?')[0].split('/').slice(-2).join('/'),
      ms: f.ms,
      savings_ms: f.ms,
      ...mapFile(f.url),
      evidence: 'start=' + f.start,
    });
  });
  // Also XHR from ResourceTiming after FP
  const fp = deep.fpAt || deep.paint?.['first-contentful-paint'] || 0;
  (deep.xhr || [])
    .filter((r) => r.start >= fp)
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 5)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'xhr_rt_' + i,
        area: 11,
        label: 'Post-FP XHR RT: ' + r.short.split('?')[0].split('/').slice(-2).join('/'),
        ms: r.dur,
        savings_ms: r.dur,
        ...mapFile(r.short),
        evidence: 'ResourceTiming after FP',
      });
    });

  // 12 Fonts
  (deep.fonts || [])
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 4)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'font_' + i,
        area: 12,
        label: 'Font/CSS font resource: ' + r.short.split('?')[0].split('/').pop(),
        ms: r.dur,
        savings_ms: r.dur,
        ...mapFile(r.short),
        evidence: 'ResourceTiming font',
      });
    });
  (deep.fontsReady || []).forEach((f, i) => {
    pushBn(bn, {
      id: 'fonts_ready_' + i,
      area: 12,
      label: 'document.fonts.ready',
      ms: f.ms,
      savings_ms: f.ms,
      file: 'views/layouts/main.php',
      fn: 'rateb_tajawal_font_css()',
      evidence: 'document.fonts.ready size=' + f.size,
    });
  });

  // 13 Icons
  pushBn(bn, {
    id: 'icons',
    area: 13,
    label: 'Icon nodes in DOM (' + deep.iconNodes + ' FA/icon els)',
    ms: deep.iconNodes > 100 ? r1(deep.iconNodes * 0.15) : r1((deep.iconNodes || 0) * 0.08),
    savings_ms: deep.iconNodes > 80 ? 30 : 10,
    file: 'views/layouts/main.php',
    fn: 'sidebar <i class="fas fa-*"> loop + Font Awesome CSS',
    evidence: 'querySelectorAll icon nodes',
  });

  // 14 Images
  (deep.images || [])
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 4)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'img_' + i,
        area: 14,
        label: 'Image: ' + r.short.split('?')[0].split('/').pop(),
        ms: r.dur,
        savings_ms: r.dur,
        file: r.short.split('?')[0],
        fn: 'img load',
        evidence: 'ResourceTiming image',
      });
    });

  // 15 Deferred scripts — cost of many deferred parallel downloads
  const deferred = (deep.deferredScripts || []).filter((s) => s.defer);
  const deferScriptDur = (deep.scripts || [])
    .filter((r) => deferred.some((d) => r.short.includes((d.src || '').split('?')[0].split('/').pop() || '___')))
    .reduce((a, r) => Math.max(a, r.dur || 0), 0);
  pushBn(bn, {
    id: 'deferred_scripts',
    area: 15,
    label: 'Deferred scripts critical-path max (' + deferred.length + ' tags)',
    ms: deferScriptDur || (deep.scripts || []).slice().sort((a, b) => b.dur - a.dur)[0]?.dur,
    savings_ms: deferred.length > 8 ? 80 : 30,
    file: 'views/layouts/main.php',
    fn: 'script defer tags at layout bottom',
    evidence: 'deferred count=' + deferred.length,
  });

  // 16 IndexedDB
  (deep.idb || []).forEach((d, i) => {
    pushBn(bn, {
      id: 'idb_' + i,
      area: 16,
      label: 'IndexedDB open: ' + d.name,
      ms: d.ms,
      savings_ms: d.ms,
      file: 'public/assets/offline/*',
      fn: 'indexedDB.open(' + d.name + ')',
      evidence: 'start=' + d.start,
    });
  });

  // 17 SW
  pushBn(bn, {
    id: 'sw_ready',
    area: 17,
    label: 'Service Worker ready',
    ms: deep.sw?.readyMs,
    savings_ms: deep.sw?.readyMs,
    file: deep.sw?.scriptURL ? shortUrl(deep.sw.scriptURL) : 'public/assets/offline/erp-shell-bootstrap.js',
    fn: 'navigator.serviceWorker.ready / register',
    evidence: 'controllerAt=' + deep.sw?.controllerAt,
  });
  pushBn(bn, {
    id: 'sw_controller',
    area: 17,
    label: 'SW controller present',
    ms: deep.sw?.controllerAt,
    savings_ms: null,
    file: deep.sw?.scriptURL ? shortUrl(deep.sw.scriptURL) : 'SW',
    fn: 'controllerchange',
    evidence: 'scriptURL',
  });

  // 18 Offline bootstrap
  const offlineScripts = (deep.scripts || []).filter((r) => /offline|erp-shell|pwa|sqlite|wasm/i.test(r.short));
  offlineScripts
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 4)
    .forEach((r, i) => {
      pushBn(bn, {
        id: 'offline_boot_' + i,
        area: 18,
        label: 'Offline bootstrap asset: ' + r.short.split('?')[0].split('/').pop(),
        ms: r.dur,
        savings_ms: r.dur,
        ...mapFile(r.short),
        evidence: 'offline script ResourceTiming',
      });
    });

  // 19 Main thread blocking — Lighthouse TBT
  const tbt = lh.metrics?.totalBlockingTime?.numericValue;
  pushBn(bn, {
    id: 'lh_tbt',
    area: 19,
    label: 'Lighthouse Total Blocking Time',
    ms: tbt != null ? r1(tbt) : null,
    savings_ms: tbt != null ? r1(tbt) : null,
    file: 'public/assets/js/*',
    fn: 'main-thread blocking (LH)',
    evidence: 'Lighthouse TBT',
  });
  pushBn(bn, {
    id: 'lh_lcp',
    area: 7,
    label: 'Lighthouse LCP',
    ms: lh.metrics?.largestContentfulPaint?.numericValue != null ? r1(lh.metrics.largestContentfulPaint.numericValue) : deep.lcp,
    savings_ms: null,
    file: 'views/layouts/main.php',
    fn: 'LCP element',
    evidence: 'Lighthouse / Performance LCP',
  });
  pushBn(bn, {
    id: 'lh_fcp',
    area: 1,
    label: 'Lighthouse FCP',
    ms: lh.metrics?.firstContentfulPaint?.numericValue != null ? r1(lh.metrics.firstContentfulPaint.numericValue) : deep.paint?.['first-contentful-paint'],
    savings_ms: null,
    file: 'views/layouts/main.php',
    fn: 'first contentful paint',
    evidence: 'Lighthouse FCP',
  });

  // 20 Memory
  if (deep.memory) {
    pushBn(bn, {
      id: 'memory_heap',
      area: 20,
      label: 'JS heap used ' + deep.memory.usedMB + ' MB (alloc pressure proxy)',
      ms: deep.memory.usedMB > 40 ? r1((deep.memory.usedMB - 30) * 2) : 8,
      savings_ms: deep.memory.usedMB > 50 ? 40 : 10,
      file: 'public/assets/js + offline runtime',
      fn: 'heap allocations during boot',
      evidence: 'performance.memory.usedJSHeapSize',
    });
  }
  if (cold.memDeltaMB != null && cold.memDeltaMB > 0) {
    pushBn(bn, {
      id: 'memory_delta',
      area: 20,
      label: 'Heap delta during settle (+' + cold.memDeltaMB + ' MB)',
      ms: r1(cold.memDeltaMB * 3),
      savings_ms: r1(cold.memDeltaMB * 2),
      file: 'public/assets/js/*',
      fn: 'post-DCL allocations',
      evidence: 'heap before/after settle',
    });
  }

  // Prefetch storm (known historical bottleneck) — measure post-paint admin document fetches
  const prefetchDocs = (deep.resources || []).filter(
    (r) => (r.type === 'fetch' || r.type === 'xmlhttprequest') && /\/admin\//.test(r.short) && r.dur >= MIN_MS
  );
  const prefetchSum = prefetchDocs.reduce((a, r) => a + (r.dur || 0), 0);
  pushBn(bn, {
    id: 'nav_prefetch',
    area: 11,
    label: 'Admin HTML prefetch/XHR after load (sum)',
    ms: prefetchSum,
    savings_ms: prefetchSum,
    file: 'public/assets/js/erp-nav-instant.js',
    fn: 'idlePrefetchVisible / prefetchUrl / runPrefetchQueue',
    evidence: 'admin fetch count=' + prefetchDocs.length,
  });

  // Sidebar click cold (if measured)
  if (report.sidebarNav) {
    (report.sidebarNav.clicks || []).forEach((c) => {
      pushBn(bn, {
        id: 'sidebar_click_' + c.id,
        area: 9,
        label: 'Sidebar click → usable: ' + c.id + (c.warm ? ' (warm)' : ' (cold)'),
        ms: c.usable_ms,
        savings_ms: c.usable_ms,
        file: 'public/assets/js/erp-nav-instant.js',
        fn: 'navigate / afterEnter',
        evidence: c.fromCache != null ? 'fromCache=' + c.fromCache : 'click trace',
      });
    });
  }

  // Deduplicate by id, sort by ms
  const seen = new Set();
  const ranked = bn
    .filter((x) => x.ms != null && x.ms >= MIN_MS)
    .sort((a, b) => b.ms - a.ms)
    .filter((x) => {
      const k = x.id;
      if (seen.has(k)) return false;
      seen.add(k);
      return true;
    });

  // Patch priority: P0 >200ms, P1 50-200, P2 5-50 (but top wall items escalate)
  ranked.forEach((x, idx) => {
    if (x.ms >= 500 || idx < 3) x.priority = 'P0';
    else if (x.ms >= 100 || idx < 10) x.priority = 'P1';
    else x.priority = 'P2';
    x.rank = idx + 1;
    x.estimated_savings_ms = x.savings_ms != null ? x.savings_ms : r1(x.ms * 0.5);
  });

  return ranked;
}

async function runLighthouseWithCookies(url, cookies) {
  const chromeLauncher = await import('chrome-launcher');
  const userDataDir = path.join(os.tmpdir(), 'rateb-fe-lh-' + Date.now());
  fs.mkdirSync(userDataDir, { recursive: true });
  const cookieHeader = cookies.map((c) => c.name + '=' + c.value).join('; ');

  const chrome = await chromeLauncher.launch({
    chromePath: CHROME,
    chromeFlags: ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
    userDataDir,
  });

  try {
    const result = await lighthouse(url, {
      port: chrome.port,
      output: 'json',
      logLevel: 'error',
      onlyCategories: ['performance'],
      formFactor: 'desktop',
      screenEmulation: { disabled: true },
      throttlingMethod: 'provided',
      disableStorageReset: true,
      extraHeaders: { Cookie: cookieHeader },
    });
    const a = result.lhr.audits;
    const pick = (id) => {
      const x = a[id];
      if (!x) return null;
      return {
        numericValue: x.numericValue ?? null,
        displayValue: x.displayValue ?? null,
        score: x.score ?? null,
      };
    };
    return {
      performanceScore: result.lhr.categories.performance?.score ?? null,
      lighthouseVersion: result.lhr.lighthouseVersion,
      metrics: {
        firstContentfulPaint: pick('first-contentful-paint'),
        largestContentfulPaint: pick('largest-contentful-paint'),
        speedIndex: pick('speed-index'),
        totalBlockingTime: pick('total-blocking-time'),
        cumulativeLayoutShift: pick('cumulative-layout-shift'),
        interactive: pick('interactive'),
        maxPotentialFid: pick('max-potential-fid'),
        serverResponseTime: pick('server-response-time'),
      },
      audits: {
        unusedJavascript: pick('unused-javascript'),
        unusedCss: pick('unused-css-rules'),
        renderBlocking: pick('render-blocking-resources'),
        bootupTime: pick('bootup-time'),
        mainthreadWork: pick('mainthread-work-breakdown'),
        fontDisplay: pick('font-display'),
        usesLongCacheTtl: pick('uses-long-cache-ttl'),
        domSize: pick('dom-size'),
      },
    };
  } finally {
    try {
      await chrome.kill();
    } catch (e) {}
  }
}

async function measureSidebarClicks(page) {
  const targets = [
    { id: 'hr', match: /hr/i, hrefIncludes: '/admin/hr' },
    { id: 'inventory', match: /inventory|مخزون/i, hrefIncludes: '/admin/ops/inventory' },
    { id: 'accounting', match: /accounting|محاسب/i, hrefIncludes: '/admin/ops/accounting' },
  ];
  const clicks = [];

  for (const t of targets) {
    // Cold: clear Cache API for that URL if possible, then click
    await page.evaluate(async (hrefPart) => {
      try {
        if (!caches) return;
        const keys = await caches.keys();
        for (const k of keys) {
          const c = await caches.open(k);
          const reqs = await c.keys();
          for (const r of reqs) {
            if (String(r.url).includes(hrefPart)) await c.delete(r);
          }
        }
      } catch (e) {}
    }, t.hrefIncludes);

    const link = await page.$(`a.rateb-nav-link[href*="${t.hrefIncludes}"], a[href*="${t.hrefIncludes}"]`);
    if (!link) {
      clicks.push({ id: t.id, error: 'link_not_found', usable_ms: null });
      continue;
    }

    const t0 = Date.now();
    await link.click();
    let usable = null;
    const deadline = Date.now() + 45000;
    while (Date.now() < deadline) {
      const snap = await page.evaluate((hrefPart) => {
        const okPath = location.href.includes(hrefPart);
        const main = document.querySelector('main, .rateb-main, #content, .content-wrapper');
        const text = main ? (main.innerText || '').trim().length : 0;
        const spins = document.querySelectorAll('.spinner-border:not([style*="display: none"]), .fa-spin').length;
        return { okPath, text, spins, href: location.href };
      }, t.hrefIncludes);
      if (snap.okPath && snap.text > 20 && snap.spins === 0) {
        usable = Date.now() - t0;
        break;
      }
      await page.waitForTimeout(50);
    }
    if (usable == null) usable = Date.now() - t0;

    const fromCache = await page.evaluate(() => {
      try {
        const entries = performance.getEntriesByType('resource').slice(-20);
        const hit = entries.reverse().find((r) => /\/admin\//.test(r.name) && (r.initiatorType === 'fetch' || r.initiatorType === 'xmlhttprequest'));
        if (!hit) return null;
        return hit.transferSize === 0 && hit.decodedBodySize > 0;
      } catch (e) {
        return null;
      }
    });

    clicks.push({ id: t.id, warm: false, usable_ms: usable, fromCache });

    // Warm second click (return dashboard then back)
    const dash = await page.$('a.rateb-nav-link[href$="/admin/"], a.rateb-nav-link[href$="/admin"], a[href*="/admin/"]');
    if (dash) {
      await dash.click().catch(() => null);
      await page.waitForTimeout(400);
    } else {
      await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    }
    const link2 = await page.$(`a.rateb-nav-link[href*="${t.hrefIncludes}"], a[href*="${t.hrefIncludes}"]`);
    if (link2) {
      const w0 = Date.now();
      await link2.click();
      let wUsable = null;
      const wdead = Date.now() + 20000;
      while (Date.now() < wdead) {
        const snap = await page.evaluate((hrefPart) => {
          const okPath = location.href.includes(hrefPart);
          const main = document.querySelector('main, .rateb-main, #content, .content-wrapper');
          const text = main ? (main.innerText || '').trim().length : 0;
          return okPath && text > 20;
        }, t.hrefIncludes);
        if (snap) {
          wUsable = Date.now() - w0;
          break;
        }
        await page.waitForTimeout(40);
      }
      clicks.push({ id: t.id, warm: true, usable_ms: wUsable || Date.now() - w0, fromCache: true });
    }

    // Back to dashboard for next cold
    await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await waitUsable(page, 30000);
  }

  return { clicks };
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const report = {
    phase: 'FULL-FRONTEND-PERF-AUDIT',
    at: new Date().toISOString(),
    base: BASE,
    note: 'Frontend-only. Backend TTFB noted for context if measured; not recommended unless >5ms contribution on critical path.',
    cold: {},
    warm: {},
    sidebarNav: null,
    lighthouse: null,
    areas: {},
    top20: [],
    all_bottlenecks: [],
  };

  console.log('[fe-audit] mint session…');
  const mint = mintSession();
  const cookies = [
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
    },
  ];

  const profileDir = path.join(os.tmpdir(), 'rateb-fe-audit-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage', '--enable-precise-memory-info'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1400, height: 900 },
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: cookies[0].name,
      value: cookies[0].value,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);

  const page = context.pages()[0] || (await context.newPage());
  await installObservers(page);

  const netLog = [];
  const wall0 = Date.now();
  page.on('response', (res) => {
    const rt = res.request().resourceType();
    if (!['document', 'script', 'stylesheet', 'xhr', 'fetch', 'font', 'image'].includes(rt)) return;
    netLog.push({
      t: Date.now() - wall0,
      type: rt,
      status: res.status(),
      url: shortUrl(res.url()),
      fromSW: res.fromServiceWorker(),
    });
  });

  console.log('[fe-audit] cold navigate /admin…');
  const cold0 = Date.now();
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const coldDcl = Date.now() - cold0;
  const usable = await waitUsable(page, 60000);
  const memBefore = await page.evaluate(() => (performance.memory ? performance.memory.usedJSHeapSize : null));
  await page.waitForTimeout(3500);
  const memAfter = await page.evaluate(() => (performance.memory ? performance.memory.usedJSHeapSize : null));
  const deep = await collectDeep(page);

  report.cold = {
    dcl_wall_ms: coldDcl,
    usable_ms: usable,
    memDeltaMB:
      memBefore != null && memAfter != null ? Math.round(((memAfter - memBefore) / 1048576) * 10) / 10 : null,
    deep,
    netLogTop: netLog.slice(0, 80),
  };

  console.log('[fe-audit] warm navigate /admin…');
  const warm0 = Date.now();
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  const warmUsable = await waitUsable(page, 45000);
  await page.waitForTimeout(1500);
  const warmDeep = await collectDeep(page);
  report.warm = {
    usable_ms: warmUsable,
    wall_ms: Date.now() - warm0,
    deep: {
      nav: warmDeep.nav,
      paint: warmDeep.paint,
      lcp: warmDeep.lcp,
      longTaskSum: warmDeep.longTaskSum,
      memory: warmDeep.memory,
    },
  };

  console.log('[fe-audit] sidebar click traces…');
  try {
    report.sidebarNav = await measureSidebarClicks(page);
  } catch (eClick) {
    report.sidebarNav = { error: String(eClick.message || eClick).slice(0, 300) };
  }

  await context.close().catch(() => null);

  console.log('[fe-audit] Lighthouse…');
  try {
    // Fresh mint for LH
    const mint2 = mintSession();
    report.lighthouse = await runLighthouseWithCookies(BASE + '/admin/?company_id=22', [
      { name: mint2.session_name || 'rateb_erp', value: mint2.session_id },
    ]);
  } catch (eLh) {
    report.lighthouse = { error: String(eLh.message || eLh).slice(0, 500) };
  }

  // Area snapshots
  const d = report.cold.deep || {};
  report.areas = {
    '1_navigation_timing': d.nav,
    '2_resource_waterfall_top15': (d.resources || []).slice().sort((a, b) => b.dur - a.dur).slice(0, 15),
    '3_js_execution_longtasks': { sum: d.longTaskSum, top: (d.longTasks || []).slice(0, 10) },
    '4_css_blocking': d.blockingCss,
    '5_layout_shifts': { cls: d.cls, shifts: d.layoutShifts },
    '6_long_tasks_gt50': (d.longTasks || []).filter((t) => t.dur > 50),
    '7_dom_render': {
      nodes: d.domNodes,
      sidebarBytes: d.sidebarBytes,
      mainBytes: d.mainBytes,
      sidebarAt: d.sidebarAt,
      dashboardAt: d.dashboardAt,
      lcp: d.lcp,
    },
    '8_event_listeners': { added: d.listenersAdded, breakdown: d.listenerBreakdown },
    '9_sidebar_rendering': { sidebarAt: d.sidebarAt, clicks: report.sidebarNav },
    '10_dashboard_rendering': { dashboardAt: d.dashboardAt, paint: d.paint },
    '11_api_after_fp': d.fetchAfterFp,
    '12_font_loading': { resources: d.fonts, ready: d.fontsReady },
    '13_icon_loading': { iconNodes: d.iconNodes },
    '14_images': d.images,
    '15_deferred_scripts': d.deferredScripts,
    '16_indexeddb': d.idb,
    '17_service_worker': d.sw,
    '18_offline_bootstrap': d.offline,
    '19_main_thread_blocking': {
      longTaskSum: d.longTaskSum,
      lh_tbt: report.lighthouse?.metrics?.totalBlockingTime || null,
    },
    '20_memory': { final: d.memory, deltaMB: report.cold.memDeltaMB },
  };

  const ranked = buildBottlenecks(report);
  report.all_bottlenecks = ranked;
  report.top20 = ranked.slice(0, 20).map((x) => ({
    rank: x.rank,
    ms: x.ms,
    estimated_savings_ms: x.estimated_savings_ms,
    priority: x.priority,
    area: x.area,
    label: x.label,
    file: x.file,
    function: x.fn,
    evidence: x.evidence,
  }));

  // Human text report
  let txt = '';
  txt += 'FULL FRONTEND PERFORMANCE AUDIT\n';
  txt += '===============================\n';
  txt += 'At: ' + report.at + '\n';
  txt += 'URL: ' + BASE + '/admin/\n\n';
  txt += 'COLD: usable=' + report.cold.usable_ms + 'ms dcl_wall=' + report.cold.dcl_wall_ms + 'ms\n';
  if (d.nav) {
    txt +=
      'NavTiming: TTFB=' +
      d.nav.ttfb +
      ' DCL=' +
      d.nav.dcl +
      ' Load=' +
      d.nav.load +
      ' FCP=' +
      (d.paint?.['first-contentful-paint'] || '?') +
      ' LCP=' +
      d.lcp +
      ' CLS=' +
      d.cls +
      '\n';
  }
  txt += 'WARM usable=' + report.warm.usable_ms + 'ms\n';
  if (report.lighthouse && !report.lighthouse.error) {
    txt +=
      'Lighthouse score=' +
      report.lighthouse.performanceScore +
      ' TBT=' +
      report.lighthouse.metrics?.totalBlockingTime?.displayValue +
      ' LCP=' +
      report.lighthouse.metrics?.largestContentfulPaint?.displayValue +
      '\n';
  }
  txt += '\nTOP 20 BOTTLENECKS (>=5ms)\n';
  txt += '-------------------------\n';
  report.top20.forEach((x) => {
    txt +=
      '#' +
      x.rank +
      ' [' +
      x.priority +
      '] ' +
      x.ms +
      'ms (save~' +
      x.estimated_savings_ms +
      'ms) area=' +
      x.area +
      '\n  ' +
      x.label +
      '\n  file: ' +
      x.file +
      '\n  fn:   ' +
      x.function +
      '\n  evid: ' +
      x.evidence +
      '\n\n';
  });

  const outJson = path.join(OUT_DIR, 'frontend-perf-full-audit-' + Date.now() + '.json');
  const outTxt = outJson.replace(/\.json$/, '.txt');
  fs.writeFileSync(outJson, JSON.stringify(report, null, 2));
  fs.writeFileSync(outTxt, txt);
  console.log(txt);
  console.log('[fe-audit] wrote', outJson);
  console.log('[fe-audit] wrote', outTxt);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
