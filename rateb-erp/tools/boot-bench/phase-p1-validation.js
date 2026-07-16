/**
 * PERF-P1 VALIDATION — Real user sidebar click experience (EVIDENCE ONLY).
 * No production changes. Compares RatebNavInstant.navigate() API vs real <a> clicks.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');

const FLOW = [
  { id: 'dashboard', match: /\/admin\/?(\?|$)/, preferHref: '/admin/' },
  { id: 'hr', match: /\/admin\/hr/, preferHref: '/admin/hr' },
  { id: 'inventory', match: /\/admin\/ops\/inventory/, preferHref: '/admin/ops/inventory' },
  { id: 'accounting', match: /\/admin\/ops\/accounting/, preferHref: '/admin/ops/accounting' },
  { id: 'procurement', match: /\/admin\/ops\/purchase/, preferHref: '/admin/ops/purchase-requests' },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const tReport = Date.now();
  let mint;
  try {
    mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  } catch (e) {
    mint = JSON.parse(ssh('php /tmp/remote-auth.php mint'));
  }

  const context = await chromium.launchPersistentContext(
    path.join(os.tmpdir(), 'rateb-p1v-' + tReport),
    {
      headless: true,
      executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      args: ['--disable-dev-shm-usage'],
      serviceWorkers: 'allow',
      locale: 'ar-SA',
      viewport: { width: 1365, height: 900 },
    }
  );
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  // Instrument BEFORE first load
  await page.addInitScript(() => {
    window.__P1V = {
      navType: null, // 'content_swap' | 'full_reload'
      pushStateCalls: 0,
      locationAssign: 0,
      beforeLeave: 0,
      afterEnter: 0,
      reinit: 0,
      swapMarks: [],
      consoleNav: [],
      lastClickAt: 0,
      events: [],
    };

    // Detect full document load
    window.addEventListener('DOMContentLoaded', () => {
      window.__P1V.events.push({ t: performance.now(), type: 'DOMContentLoaded', href: location.href });
    });
    window.addEventListener('load', () => {
      window.__P1V.events.push({ t: performance.now(), type: 'window.load', href: location.href });
      // If we got a full load after a click without afterEnter, mark full_reload
      if (window.__P1V.lastClickAt && performance.now() - window.__P1V.lastClickAt < 15000) {
        if (!window.__P1V.afterEnter || window.__P1V.navType !== 'content_swap') {
          window.__P1V.navType = window.__P1V.navType || 'full_reload';
        }
      }
    });

    document.addEventListener('rateb:nav:beforeLeave', () => {
      window.__P1V.beforeLeave++;
      window.__P1V.events.push({ t: performance.now(), type: 'beforeLeave' });
    });
    document.addEventListener('rateb:nav:afterEnter', (ev) => {
      window.__P1V.afterEnter++;
      window.__P1V.navType = 'content_swap';
      window.__P1V.events.push({
        t: performance.now(),
        type: 'afterEnter',
        detail: ev && ev.detail ? ev.detail : null,
      });
    });

    const wrap = (obj, method, flag) => {
      if (!obj || !obj[method]) return;
      const orig = obj[method].bind(obj);
      obj[method] = function (...args) {
        window.__P1V[flag]++;
        window.__P1V.events.push({ t: performance.now(), type: flag, args0: String(args[2] || args[0] || '').slice(0, 120) });
        return orig(...args);
      };
    };
    wrap(history, 'pushState', 'pushStateCalls');
    wrap(history, 'replaceState', 'pushStateCalls');

    // Patch RatebApp.reinit after it appears
    const watchReinit = () => {
      if (window.RatebApp && typeof window.RatebApp.reinit === 'function' && !window.RatebApp.__p1vWrapped) {
        const orig = window.RatebApp.reinit.bind(window.RatebApp);
        window.RatebApp.reinit = function () {
          window.__P1V.reinit++;
          window.__P1V.events.push({ t: performance.now(), type: 'RatebApp.reinit' });
          return orig();
        };
        window.RatebApp.__p1vWrapped = true;
      }
    };
    setInterval(watchReinit, 200);

    // Capture console [RATEB NAV]
    const cinfo = console.info.bind(console);
    console.info = function (...args) {
      try {
        if (String(args[0] || '').indexOf('[RATEB NAV]') !== -1) {
          window.__P1V.consoleNav.push(args.map(String).join(' '));
          window.__P1V.navType = 'content_swap';
        }
      } catch (e) { /* ignore */ }
      return cinfo(...args);
    };
  });

  // Network capture
  const netLog = [];
  page.on('request', (req) => {
    if (!req.url().includes('rateb.sa')) return;
    netLog.push({
      id: req.url() + '|' + Date.now() + '|' + Math.random(),
      url: req.url(),
      method: req.method(),
      resourceType: req.resourceType(),
      isNav: req.isNavigationRequest(),
      t: Date.now(),
    });
  });
  page.on('response', async (res) => {
    const req = res.request();
    if (!req.url().includes('rateb.sa')) return;
    let fromSW = false;
    try {
      fromSW = res.fromServiceWorker();
    } catch (e) { /* ignore */ }
    const headers = res.headers();
    netLog.push({
      kind: 'response',
      url: res.url(),
      status: res.status(),
      fromServiceWorker: fromSW,
      xOffline: headers['x-rateb-offline'] || null,
      xOps: headers['x-rateb-ops-page'] || null,
      xSoft: headers['x-rateb-soft-offline-nav'] || null,
      cacheControl: headers['cache-control'] || null,
      serverTiming: headers['server-timing'] || null,
      resourceType: req.resourceType(),
      isNav: req.isNavigationRequest(),
      t: Date.now(),
    });
  });

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 180000 });
  await page.waitForTimeout(4000);

  // Force SW claim + warm
  const swBefore = await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    const reg = regs[0] || null;
    let build = null;
    try {
      const t = await (await fetch('/rateb-erp/public/pos-sw.js?t=' + Date.now(), { cache: 'no-store' })).text();
      const m = t.match(/SW_BUILD_ID\s*=\s*['"]([^'"]+)/);
      build = m ? m[1] : null;
    } catch (e) { /* ignore */ }
    if (reg && reg.waiting) {
      try {
        reg.waiting.postMessage({ type: 'SKIP_WAITING' });
      } catch (e2) { /* ignore */ }
    }
    if (reg && reg.active) {
      await new Promise((resolve) => {
        const ch = new MessageChannel();
        const t = setTimeout(resolve, 45000);
        ch.port1.onmessage = () => {
          clearTimeout(t);
          resolve();
        };
        reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
      });
    }
    return {
      registrations: regs.length,
      active: reg && reg.active ? reg.active.scriptURL : null,
      waiting: reg && reg.waiting ? reg.waiting.scriptURL : null,
      installing: reg && reg.installing ? reg.installing.scriptURL : null,
      controller: navigator.serviceWorker.controller
        ? navigator.serviceWorker.controller.scriptURL
        : null,
      build_from_network: build,
      hasNavInstant: !!window.RatebNavInstant,
      hasSidebar: !!document.querySelector('#rateb-sidebar, .rateb-sidebar'),
      mainId: !!document.querySelector('#rateb-main-content'),
    };
  });

  await page.waitForTimeout(2000);
  // Reload once so controlling worker is current (if waiting activated)
  if (swBefore.waiting || (swBefore.active && !swBefore.controller)) {
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(2500);
  }

  const swAfter = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    let cachesInfo = [];
    try {
      const names = await caches.keys();
      for (const n of names) {
        if (String(n).indexOf('rateb-erp-ops') === -1 && String(n).indexOf('coexist') === -1) continue;
        const c = await caches.open(n);
        const keys = await c.keys();
        cachesInfo.push({ name: n, keys: keys.length });
      }
    } catch (e) {
      cachesInfo = [{ error: String(e) }];
    }
    return {
      controller: navigator.serviceWorker.controller
        ? navigator.serviceWorker.controller.scriptURL
        : null,
      active: reg && reg.active ? reg.active.scriptURL : null,
      waiting: reg && reg.waiting ? reg.waiting.scriptURL : null,
      hasNavInstant: !!window.RatebNavInstant,
      scriptTagPresent: !!document.querySelector('script[src*="erp-nav-instant"]'),
      cachesInfo,
    };
  });

  // --- A) API bench (what P1 claimed) ---
  const apiBench = await page.evaluate(async (href) => {
    if (!window.RatebNavInstant) return { ok: false, reason: 'no_RatebNavInstant' };
    window.RatebNavInstant.prefetch(href);
    await new Promise((r) => setTimeout(r, 1500));
    const t0 = performance.now();
    const ok = await window.RatebNavInstant.navigate(href, { replace: true });
    return { ok, ms: Math.round(performance.now() - t0), href: location.href };
  }, BASE + '/admin/hr/attendance?company_id=22');

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1500);

  // Reset counters
  await page.evaluate(() => {
    window.__P1V = Object.assign(window.__P1V || {}, {
      navType: null,
      pushStateCalls: 0,
      beforeLeave: 0,
      afterEnter: 0,
      reinit: 0,
      consoleNav: [],
      events: [],
      lastClickAt: 0,
    });
    performance.clearMarks();
    performance.clearResourceTimings();
    performance.clearMeasures();
  });

  const clickTrace = [];

  async function realSidebarClick(step) {
    const beforeHref = page.url();
    const beforeMain = await page.evaluate(() => {
      const m = document.querySelector('#rateb-main-content, main.rateb-content');
      return {
        htmlLen: m ? m.innerHTML.length : 0,
        textLen: m ? (m.innerText || '').length : 0,
        snippet: m ? (m.innerText || '').slice(0, 40) : '',
      };
    });

    await page.evaluate(() => {
      window.__P1V.lastClickAt = performance.now();
      window.__P1V.navType = null;
      performance.clearResourceTimings();
    });

    // Find sidebar link
    const linkInfo = await page.evaluate(({ matchSource, preferHref }) => {
      const re = new RegExp(matchSource);
      const links = [...document.querySelectorAll('#rateb-sidebar a[href], .rateb-sidebar a[href], a.rateb-nav-link[href]')];
      let hit =
        links.find((a) => (a.getAttribute('href') || '').indexOf(preferHref) !== -1) ||
        links.find((a) => re.test(a.href));
      if (!hit) return { found: false, count: links.length };
      hit.setAttribute('data-p1v-target', '1');
      return {
        found: true,
        href: hit.href,
        text: (hit.innerText || '').trim().slice(0, 40),
        classes: hit.className,
      };
    }, { matchSource: step.match.source, preferHref: step.preferHref });

    if (!linkInfo.found) {
      return { id: step.id, error: 'link_not_found', linkInfo };
    }

    // Expand collapsed nav groups so links are visible (or force-click)
    await page.evaluate(() => {
      document.querySelectorAll('[data-nav-group-toggle], .rateb-nav-group-toggle, button[aria-expanded="false"]').forEach((btn) => {
        try {
          btn.click();
        } catch (e) { /* ignore */ }
      });
      document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
        el.style.display = 'block';
        el.hidden = false;
      });
    });
    await page.waitForTimeout(200);

    const wall0 = Date.now();
    const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 8000 }).catch(() => null);

    // REAL click event (force if in collapsed group — still fires DOM click → capture listener)
    await page.click('a[data-p1v-target="1"]', { timeout: 8000, force: true });
    const navResult = await navPromise;
    const wallMs = Date.now() - wall0;

    // Wait briefly for content-swap path (no navigation)
    await page.waitForTimeout(800);

    const after = await page.evaluate((beforeHrefEval) => {
      const m = document.querySelector('#rateb-main-content, main.rateb-content');
      const navs = performance.getEntriesByType('navigation');
      const nav = navs.length ? navs[navs.length - 1] : null;
      const paints = {};
      performance.getEntriesByType('paint').forEach((p) => {
        paints[p.name] = Math.round(p.startTime * 10) / 10;
      });
      let lcp = null;
      try {
        const L = performance.getEntriesByType('largest-contentful-paint');
        if (L.length) lcp = Math.round(L[L.length - 1].startTime * 10) / 10;
      } catch (e) { /* ignore */ }

      const resources = performance.getEntriesByType('resource').map((r) => {
        const delivery =
          (r.transferSize || 0) === 0 && (r.decodedBodySize || 0) > 0
            ? 'cache_or_sw_or_memory'
            : (r.transferSize || 0) > 0
              ? 'network'
              : 'empty';
        return {
          name: r.name.replace(location.origin, '').slice(0, 140),
          type: r.initiatorType,
          duration: Math.round(r.duration),
          transferSize: r.transferSize || 0,
          decodedBodySize: r.decodedBodySize || 0,
          delivery,
          ttfb:
            r.responseStart && r.requestStart
              ? Math.round((r.responseStart - r.requestStart) * 10) / 10
              : null,
        };
      });

      const p1 = window.__P1V || {};
      const fullReload =
        p1.navType === 'full_reload' ||
        (nav && nav.type === 'navigate' && (nav.transferSize || 0) > 1000 && p1.afterEnter === 0) ||
        (!!nav && p1.afterEnter === 0 && location.href !== beforeHrefEval && (nav.domContentLoadedEventEnd || 0) > 0);

      // Heuristic: content swap if afterEnter fired or main changed without navigation timing spike
      const contentSwap = p1.afterEnter > 0 || p1.consoleNav.length > 0 || p1.navType === 'content_swap';

      return {
        href: location.href,
        hrefChanged: location.href !== beforeHrefEval,
        mainHtmlLen: m ? m.innerHTML.length : 0,
        mainTextLen: m ? (m.innerText || '').length : 0,
        mainSnippet: m ? (m.innerText || '').trim().slice(0, 50) : '',
        p1v: {
          navType: p1.navType,
          beforeLeave: p1.beforeLeave,
          afterEnter: p1.afterEnter,
          reinit: p1.reinit,
          pushStateCalls: p1.pushStateCalls,
          consoleNav: p1.consoleNav.slice(-3),
          events: (p1.events || []).slice(-20),
        },
        contentSwap,
        fullReloadGuess: fullReload && !contentSwap,
        navTiming: nav
          ? {
              type: nav.type,
              transferSize: nav.transferSize || 0,
              decodedBodySize: nav.decodedBodySize || 0,
              workerStart: nav.workerStart || 0,
              startTime: nav.startTime,
              requestStart: nav.requestStart,
              responseStart: nav.responseStart,
              responseEnd: nav.responseEnd,
              domContentLoadedEventEnd: nav.domContentLoadedEventEnd,
              loadEventEnd: nav.loadEventEnd,
              ttfb_ms: Math.round(Math.max(0, nav.responseStart - nav.requestStart) * 10) / 10,
              dcl_ms: Math.round(nav.domContentLoadedEventEnd * 10) / 10,
              load_ms: Math.round(nav.loadEventEnd * 10) / 10,
            }
          : null,
        paints,
        lcp_ms: lcp,
        resources_top: resources.sort((a, b) => b.duration - a.duration).slice(0, 15),
        resources_network: resources.filter((r) => r.delivery === 'network').length,
        resources_cached: resources.filter((r) => r.delivery === 'cache_or_sw_or_memory').length,
        hasNavInstant: !!window.RatebNavInstant,
      };
    }, beforeHref);

    // Clear marker
    await page.evaluate(() => {
      document.querySelectorAll('[data-p1v-target]').forEach((el) => el.removeAttribute('data-p1v-target'));
      // reset per-click counters for next
      window.__P1V.beforeLeave = 0;
      window.__P1V.afterEnter = 0;
      window.__P1V.reinit = 0;
      window.__P1V.pushStateCalls = 0;
      window.__P1V.consoleNav = [];
      window.__P1V.events = [];
      window.__P1V.navType = null;
    });

    return {
      id: step.id,
      wall_ms: wallMs,
      linkInfo,
      beforeHref,
      beforeMain,
      navigationEventFired: !!navResult,
      after,
      classification:
        after.contentSwap && !after.fullReloadGuess
          ? 'content_swap'
          : after.fullReloadGuess || after.navigationEventFired
            ? 'full_document_navigation'
            : after.hrefChanged
              ? 'unknown_href_changed'
              : 'no_change_or_same_page',
    };
  }

  // Start from dashboard
  if (!/\/admin\/?(\?|$)/.test(page.url())) {
    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(1000);
  }

  for (const step of FLOW) {
    // skip clicking dashboard if already there for first step
    if (step.id === 'dashboard' && /\/admin\/?(\?|$)/.test(new URL(page.url()).pathname + (new URL(page.url()).pathname.endsWith('/') ? '' : ''))) {
      // still record baseline
      const snap = await page.evaluate(() => ({
        href: location.href,
        hasNav: !!window.RatebNavInstant,
        script: !!document.querySelector('script[src*="erp-nav-instant"]'),
      }));
      clickTrace.push({ id: 'dashboard', note: 'already_on_dashboard', snap });
      continue;
    }
    console.error('[click]', step.id);
    const row = await realSidebarClick(step);
    clickTrace.push(row);
    await page.waitForTimeout(600);
  }

  // Offline real click sample
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1000);
  await context.setOffline(true);
  const offlineClick = await realSidebarClick(FLOW[1]); // HR
  await context.setOffline(false);

  // Compare table
  const realClicks = clickTrace.filter((r) => r.wall_ms != null);
  const avgReal = realClicks.length
    ? Math.round(realClicks.reduce((a, b) => a + b.wall_ms, 0) / realClicks.length)
    : null;
  const swapCount = realClicks.filter((r) => r.classification === 'content_swap').length;
  const fullCount = realClicks.filter((r) => r.classification === 'full_document_navigation').length;

  // Where time goes on a typical full nav
  const sampleFull = realClicks.find((r) => r.classification === 'full_document_navigation') || realClicks[0];

  const comparison = [
    {
      metric: 'Navigation method',
      benchmark: 'RatebNavInstant.navigate() API',
      real_browser: realClicks.map((r) => r.classification).join(', ') || 'n/a',
      difference: swapCount === realClicks.length ? 'same path' : 'REAL clicks often ≠ API path',
      reason:
        swapCount < realClicks.length
          ? 'Sidebar <a> click may full-navigate (guard intercept, preventDefault fail, or RatebNavInstant not binding)'
          : 'Content-swap used on real clicks',
      file: 'public/assets/js/erp-nav-instant.js',
      line: 'onClick / shouldIntercept',
    },
    {
      metric: 'Online HR latency',
      benchmark: '16ms avg (API navigate after prefetch)',
      real_browser: (realClicks.find((r) => r.id === 'hr') || {}).wall_ms + 'ms wall',
      difference: 'orders of magnitude if full reload',
      reason: 'API measures in-page swap after cache warm; real click may trigger document navigation + DCL',
      file: 'tools/boot-bench/phase-p1-nav-bench.js',
      line: 'page.evaluate RatebNavInstant.navigate',
    },
    {
      metric: 'Performance navigation entry',
      benchmark: 'none (no document nav)',
      real_browser: sampleFull && sampleFull.after && sampleFull.after.navTiming
        ? JSON.stringify(sampleFull.after.navTiming)
        : 'n/a',
      difference: sampleFull && sampleFull.after && sampleFull.after.navTiming ? 'document Navigation Timing present' : 'none',
      reason: 'Navigation Timing only updates on full document loads',
      file: 'browser PerformanceNavigationTiming',
      line: 'n/a',
    },
    {
      metric: 'beforeLeave / afterEnter',
      benchmark: 'fired inside navigate()',
      real_browser: realClicks
        .map((r) => `${r.id}: bl=${r.after?.p1v?.beforeLeave} ae=${r.after?.p1v?.afterEnter}`)
        .join(' | '),
      difference: 'If ae=0 on click → swap path NOT used',
      reason: 'Lifecycle only runs in swapTo()',
      file: 'erp-nav-instant.js',
      line: 'runLifecycle',
    },
    {
      metric: 'RatebApp.reinit',
      benchmark: 'on afterEnter',
      real_browser: realClicks.map((r) => `${r.id}:${r.after?.p1v?.reinit}`).join(', '),
      difference: '0 means swap/reinit path skipped',
      reason: 'reinit wired to rateb:nav:afterEnter',
      file: 'public/assets/js/app.js',
      line: 'rateb:nav:afterEnter listener',
    },
    {
      metric: 'Service Worker controlling',
      benchmark: 'assumed v80',
      real_browser: swAfter.controller || 'NULL — page not controlled',
      difference: !swAfter.controller ? 'NOT CONTROLLING' : 'ok',
      reason: !swAfter.controller
        ? 'Old session / waiting worker / need reload after activate'
        : 'controller present',
      file: 'pos-sw.js',
      line: 'clients.claim',
    },
    {
      metric: 'erp-nav-instant loaded',
      benchmark: 'required',
      real_browser: `scriptTag=${swAfter.scriptTagPresent} global=${swAfter.hasNavInstant}`,
      difference: !swAfter.hasNavInstant ? 'BYPASSED — full reloads only' : 'present',
      reason: !swAfter.hasNavInstant ? 'Script missing or error before boot' : 'ok',
      file: 'views/layouts/main.php',
      line: 'erp-nav-instant.js script tag',
    },
    {
      metric: 'Offline real click',
      benchmark: '14ms API',
      real_browser: `${offlineClick.wall_ms}ms · ${offlineClick.classification}`,
      difference: 'API≠click if classification full_document_navigation or ok false',
      reason: 'Offline fetch/cache path or hard navigation',
      file: 'erp-nav-instant.js',
      line: 'fetchHtml / sameShell',
    },
  ];

  // Remaining time breakdown for first full/slow click
  const slow = realClicks.slice().sort((a, b) => (b.wall_ms || 0) - (a.wall_ms || 0))[0];
  let remainingTime = null;
  if (slow && slow.after) {
    const nt = slow.after.navTiming;
    remainingTime = {
      slowest_click: slow.id,
      wall_ms: slow.wall_ms,
      classification: slow.classification,
      breakdown: nt
        ? {
            ttfb_ms: nt.ttfb_ms,
            html_download_approx_ms: Math.round((nt.responseEnd - nt.responseStart) * 10) / 10,
            parse_to_dcl_ms: Math.round((nt.dcl_ms - (nt.responseEnd || nt.ttfb_ms)) * 10) / 10,
            dcl_ms: nt.dcl_ms,
            load_ms: nt.load_ms,
            fcp_ms: slow.after.paints['first-contentful-paint'] || null,
            transferSize: nt.transferSize,
          }
        : {
            note: 'No Navigation Timing — likely content-swap or same-document',
            afterEnter_detail: slow.after.p1v,
            resources_network: slow.after.resources_network,
            resources_cached: slow.after.resources_cached,
            top_resources: slow.after.resources_top,
          },
    };
  }

  const docNavResponses = netLog.filter(
    (n) => n.kind === 'response' && n.isNav && /\/admin/i.test(n.url || '')
  );

  const report = {
    phase: 'PERF-P1-VALIDATION',
    mode: 'EVIDENCE_ONLY_NO_FIXES',
    at: new Date().toISOString(),
    apiBench,
    swBefore,
    swAfter,
    clickTrace,
    offlineClick,
    comparison,
    remainingTime,
    network_summary: {
      total_events: netLog.length,
      document_navigations: docNavResponses.length,
      document_nav_samples: docNavResponses.slice(0, 10),
      from_sw_admin_docs: docNavResponses.filter((d) => d.fromServiceWorker).length,
    },
    verdict: {
      benchmark_measures: 'In-page RatebNavInstant.navigate() after prefetch (not sidebar click)',
      real_user_path:
        fullCount > 0
          ? 'At least one FULL document navigation on sidebar click'
          : swapCount === realClicks.length
            ? 'All measured clicks used content-swap'
            : 'Mixed / unclear',
      swap_clicks: swapCount,
      full_reload_clicks: fullCount,
      avg_real_click_wall_ms: avgReal,
      api_bench_ms: apiBench.ms,
      gap_ms: avgReal != null && apiBench.ms != null ? avgReal - apiBench.ms : null,
    },
    elapsed_ms: Date.now() - tReport,
  };

  const out = path.join(OUT_DIR, `phase-p1-validation-${tReport}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-p1-validation-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, verdict: report.verdict, comparison, remainingTime, swAfter, apiBench }, null, 2));
  await context.close();
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
