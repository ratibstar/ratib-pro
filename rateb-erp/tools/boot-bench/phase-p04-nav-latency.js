/**
 * PERF-P0.4 — Navigation latency root cause (EVIDENCE ONLY).
 * No production changes. Measures ONLINE + OFFLINE cold vs warm per module.
 *
 * Usage: node phase-p04-nav-latency.js
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
const COMPANY_Q = 'company_id=22';

const MODULES = [
  { id: 'dashboard', path: '/admin/' },
  { id: 'hr', path: '/admin/hr/attendance' },
  { id: 'crm', path: '/admin/crm' },
  { id: 'inventory', path: '/admin/ops/inventory' },
  { id: 'accounting', path: '/admin/ops/accounting' },
  { id: 'procurement', path: '/admin/ops/purchase-requests' },
  { id: 'warehouse', path: '/admin/ops/warehouses' },
  { id: 'pos', path: '/admin/ops/pos/register' },
  { id: 'finance', path: '/admin/ops/accounting/platform' },
  { id: 'payroll', path: '/admin/hr/payroll' },
  { id: 'support', path: '/admin/oversight/approvals' },
  { id: 'projects', path: '/admin/ops/projects' },
  { id: 'documents', path: '/admin/ops/documents' },
  { id: 'assets', path: '/admin/ops/assets' },
  { id: 'website', path: '/admin/ops/website' },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function moduleUrl(mod) {
  let u = BASE + mod.path;
  if (!/\/admin\/?$/i.test(mod.path)) {
    u += (u.includes('?') ? '&' : '?') + COMPANY_Q;
  }
  return u;
}

async function waitUsable(page, wallStart, maxMs) {
  const deadline = Date.now() + maxMs;
  let lastNet = Date.now();
  const bump = () => {
    lastNet = Date.now();
  };
  page.on('request', bump);
  page.on('response', bump);
  let usableAt = null;
  let lastSnap = null;
  while (Date.now() < deadline) {
    lastSnap = await page.evaluate(() => {
      const spinSel =
        '.spinner-border, .fa-spinner, .fa-spin, .is-loading, #rateb-offline-warm-progress, [aria-busy="true"]';
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
      });
      const sidebar = !!document.querySelector('#rateb-sidebar, .rateb-sidebar, aside.rateb-sidebar');
      const main = document.querySelector('main, .rateb-main, #content, .content-wrapper, [data-rateb-uncached-page]');
      const mainText = main ? (main.innerText || '').trim().length : 0;
      const uncached = !!document.querySelector('[data-rateb-uncached-page]');
      return {
        spins: spins.length,
        sidebar,
        mainText,
        uncached,
        readyState: document.readyState,
        title: document.title || '',
        href: location.href,
      };
    });
    const quiet = Date.now() - lastNet > 600;
    if (
      lastSnap.readyState === 'complete' &&
      lastSnap.spins === 0 &&
      ((lastSnap.sidebar && lastSnap.mainText > 20) || lastSnap.uncached) &&
      (quiet || Date.now() - wallStart > 3500)
    ) {
      usableAt = Date.now() - wallStart;
      break;
    }
    await page.waitForTimeout(150);
  }
  page.off('request', bump);
  page.off('response', bump);
  return { usableAt, snap: lastSnap };
}

async function captureMetrics(page) {
  return page.evaluate(() => {
    const navs = performance.getEntriesByType('navigation');
    const nav = navs.length ? navs[navs.length - 1] : null;
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = Math.round(p.startTime * 10) / 10;
    });
    const resources = performance.getEntriesByType('resource').map((r) => {
      const name = r.name.replace(location.origin, '');
      return {
        name: name.slice(0, 160),
        type: r.initiatorType,
        duration: Math.round(r.duration),
        transferSize: r.transferSize || 0,
        encodedBodySize: r.encodedBodySize || 0,
        decodedBodySize: r.decodedBodySize || 0,
        ttfb_ms:
          r.responseStart && r.requestStart
            ? Math.round((r.responseStart - r.requestStart) * 10) / 10
            : null,
        delivery:
          (r.transferSize || 0) === 0 && (r.decodedBodySize || 0) > 0
            ? 'cache_or_sw'
            : (r.transferSize || 0) > 0
              ? 'network'
              : 'empty_or_fail',
      };
    });

    const scripts = resources.filter((r) => r.type === 'script');
    const css = resources.filter((r) => r.type === 'link' || /\.css(\?|$)/i.test(r.name));
    const ajax = resources.filter((r) => r.type === 'fetch' || r.type === 'xmlhttprequest');

    // Duplicate detection by pathname (ignore query)
    function dupCounts(list) {
      const map = {};
      list.forEach((r) => {
        const key = r.name.split('?')[0];
        map[key] = (map[key] || 0) + 1;
      });
      return Object.entries(map)
        .filter(([, n]) => n > 1)
        .map(([k, n]) => ({ name: k, count: n }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 15);
    }

    let idbOpens = 0;
    let ensureCalls = 0;
    try {
      const marks = performance.getEntriesByType('mark') || [];
      idbOpens = marks.filter((m) => /idb|indexeddb|openDatabase/i.test(m.name)).length;
      ensureCalls = marks.filter((m) => /ensure/i.test(m.name)).length;
    } catch (e) { /* ignore */ }

    const longTasks = [];
    try {
      performance.getEntriesByType('longtask').forEach((t) => {
        longTasks.push({ start: Math.round(t.startTime), dur: Math.round(t.duration) });
      });
    } catch (e2) { /* ignore */ }

    return {
      href: location.href,
      title: document.title || '',
      htmlLen: document.documentElement ? document.documentElement.outerHTML.length : 0,
      bodyLen: (document.body && document.body.innerText || '').length,
      uncached: !!document.querySelector('[data-rateb-uncached-page]'),
      hasSidebar: !!document.querySelector('#rateb-sidebar, .rateb-sidebar'),
      onLine: navigator.onLine,
      sw: navigator.serviceWorker && navigator.serviceWorker.controller
        ? navigator.serviceWorker.controller.scriptURL
        : null,
      nav: nav
        ? {
            type: nav.type,
            protocol: nav.nextHopProtocol || null,
            transferSize: nav.transferSize || 0,
            decodedBodySize: nav.decodedBodySize || 0,
            dns_ms: Math.round(Math.max(0, nav.domainLookupEnd - nav.domainLookupStart) * 10) / 10,
            tcp_ms: Math.round(Math.max(0, nav.connectEnd - nav.connectStart) * 10) / 10,
            ttfb_ms: Math.round(Math.max(0, nav.responseStart - nav.requestStart) * 10) / 10,
            html_dl_ms: Math.round(Math.max(0, nav.responseEnd - nav.responseStart) * 10) / 10,
            dom_interactive_ms: Math.round(nav.domInteractive * 10) / 10,
            dcl_ms: Math.round(nav.domContentLoadedEventEnd * 10) / 10,
            load_ms: Math.round(nav.loadEventEnd * 10) / 10,
            worker_start_ms: nav.workerStart > 0 ? Math.round(nav.workerStart * 10) / 10 : 0,
          }
        : null,
      paints,
      scripts: {
        count: scripts.length,
        network: scripts.filter((r) => r.delivery === 'network').length,
        cached: scripts.filter((r) => r.delivery === 'cache_or_sw').length,
        total_ms: scripts.reduce((a, r) => a + r.duration, 0),
        dups: dupCounts(scripts),
        top: scripts.slice().sort((a, b) => b.duration - a.duration).slice(0, 8),
      },
      css: {
        count: css.length,
        network: css.filter((r) => r.delivery === 'network').length,
        cached: css.filter((r) => r.delivery === 'cache_or_sw').length,
        total_ms: css.reduce((a, r) => a + r.duration, 0),
        dups: dupCounts(css),
      },
      ajax: {
        count: ajax.length,
        total_ms: ajax.reduce((a, r) => a + r.duration, 0),
        top: ajax.slice().sort((a, b) => b.duration - a.duration).slice(0, 8),
        dups: dupCounts(ajax),
      },
      resource_dups: dupCounts(resources),
      longTasks,
      idb_marks: idbOpens,
      ensure_marks: ensureCalls,
      mem_mb: performance.memory
        ? Math.round((performance.memory.usedJSHeapSize / 1048576) * 10) / 10
        : null,
    };
  });
}

async function navigateOnce(page, label, url, mode) {
  const wallStart = Date.now();
  let navError = null;
  const docHeaders = {};
  const responsePromise = page.waitForResponse(
    (r) => r.request().isNavigationRequest() && r.url().includes('/admin'),
    { timeout: 90000 }
  ).catch(() => null);

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  } catch (e) {
    navError = String(e.message || e).slice(0, 200);
  }
  const docResp = await responsePromise;
  if (docResp) {
    const h = await docResp.headers();
    docHeaders['x-rateb-offline'] = h['x-rateb-offline'] || null;
    docHeaders['x-rateb-uncached-page'] = h['x-rateb-uncached-page'] || null;
    docHeaders['x-rateb-soft-offline-nav'] = h['x-rateb-soft-offline-nav'] || null;
    docHeaders['cache-control'] = h['cache-control'] || null;
    docHeaders['content-type'] = h['content-type'] || null;
    docHeaders.status = docResp.status();
    docHeaders.fromServiceWorker = docResp.fromServiceWorker();
  }

  const usable = await waitUsable(page, wallStart, 25000);
  const metrics = await captureMetrics(page);
  const wall = Date.now() - wallStart;

  // Stage decomposition (browser-observable)
  const n = metrics.nav || {};
  const stages = {
    dns_ms: n.dns_ms || 0,
    tcp_ms: n.tcp_ms || 0,
    sw_worker_start_ms: n.worker_start_ms || 0,
    server_ttfb_ms: n.ttfb_ms, // PHP bootstrap+controller+view opaque bucket
    html_download_ms: n.html_dl_ms || 0,
    parse_to_interactive_ms:
      n.dom_interactive_ms != null && n.ttfb_ms != null
        ? Math.round((n.dom_interactive_ms - (n.responseEnd || n.ttfb_ms + (n.html_dl_ms || 0))) * 10) / 10
        : null,
    dom_interactive_ms: n.dom_interactive_ms,
    dcl_ms: n.dcl_ms,
    css_resources_ms: metrics.css.total_ms,
    js_resources_ms: metrics.scripts.total_ms,
    ajax_ms: metrics.ajax.total_ms,
    paint_fcp_ms: metrics.paints['first-contentful-paint'] || null,
    usable_ms: usable.usableAt,
    wall_ms: wall,
  };

  return {
    id: label,
    mode,
    url,
    nav_error: navError,
    docHeaders,
    stages,
    usable_snap: usable.snap,
    metrics,
  };
}

function summarizePair(cold, warm) {
  const c = cold.stages || {};
  const w = warm.stages || {};
  return {
    cold_usable_ms: c.usable_ms,
    warm_usable_ms: w.usable_ms,
    delta_usable_ms:
      c.usable_ms != null && w.usable_ms != null ? c.usable_ms - w.usable_ms : null,
    cold_ttfb_ms: c.server_ttfb_ms,
    warm_ttfb_ms: w.server_ttfb_ms,
    delta_ttfb_ms:
      c.server_ttfb_ms != null && w.server_ttfb_ms != null
        ? Math.round((c.server_ttfb_ms - w.server_ttfb_ms) * 10) / 10
        : null,
    cold_js_network: cold.metrics?.scripts?.network,
    warm_js_network: warm.metrics?.scripts?.network,
    cold_css_network: cold.metrics?.css?.network,
    warm_css_network: warm.metrics?.css?.network,
    cold_transfer: cold.metrics?.nav?.transferSize,
    warm_transfer: warm.metrics?.nav?.transferSize,
    cold_html: cold.metrics?.htmlLen,
    warm_html: warm.metrics?.htmlLen,
    cold_uncached: cold.metrics?.uncached,
    warm_uncached: warm.metrics?.uncached,
    cold_sidebar: cold.metrics?.hasSidebar,
    warm_sidebar: warm.metrics?.hasSidebar,
  };
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const tReport = Date.now();
  let mint;
  try {
    mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  } catch (e1) {
    mint = JSON.parse(
      ssh(
        'php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint 2>/dev/null || php /tmp/remote-auth.php mint'
      )
    );
  }

  const profileDir = path.join(os.tmpdir(), 'rateb-p04-' + tReport);
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1365, height: 900 },
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || mint.cookie_name || 'rateb_erp',
      value: mint.session_id || mint.cookie_value || mint.value,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  // Establish controlled document (not counted as module cold)
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(2000);
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    if (reg && reg.active) {
      reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true });
    }
  });
  await page.waitForTimeout(8000);

  const online = {};
  for (const mod of MODULES) {
    const url = moduleUrl(mod);
    console.error('[online-cold]', mod.id);
    const cold = await navigateOnce(page, mod.id, url, 'online_cold');
    console.error('[online-warm]', mod.id);
    const warm = await navigateOnce(page, mod.id, url, 'online_warm');
    online[mod.id] = { cold, warm, diff: summarizePair(cold, warm) };
  }

  // Offline block: ensure ops warm, then offline
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.ready;
    if (!reg.active) return { ok: false };
    return await new Promise((resolve) => {
      const ch = new MessageChannel();
      const t = setTimeout(() => resolve({ ok: false, reason: 'timeout' }), 120000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(t);
        resolve(ev.data || {});
      };
      reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
    });
  });
  await context.setOffline(true);

  const offline = {};
  for (const mod of MODULES) {
    const url = moduleUrl(mod);
    console.error('[offline-cold]', mod.id);
    const cold = await navigateOnce(page, mod.id, url, 'offline_cold');
    console.error('[offline-warm]', mod.id);
    const warm = await navigateOnce(page, mod.id, url, 'offline_warm');
    offline[mod.id] = { cold, warm, diff: summarizePair(cold, warm) };
  }

  // Top-20 candidates from all stage numbers
  const ops = [];
  function pushOp(row) {
    ops.push(row);
  }
  for (const mode of ['online', 'offline']) {
    const bag = mode === 'online' ? online : offline;
    for (const [id, pair] of Object.entries(bag)) {
      for (const pass of ['cold', 'warm']) {
        const row = pair[pass];
        const s = row.stages || {};
        const m = row.metrics || {};
        pushOp({
          kind: 'server_ttfb',
          module: id,
          mode: mode + '_' + pass,
          ms: s.server_ttfb_ms,
          detail: 'Document TTFB (PHP+DB+render opaque)',
          file: 'public/index.php → router → controller → views/layouts/main.php',
          call_count: 1,
        });
        pushOp({
          kind: 'usable',
          module: id,
          mode: mode + '_' + pass,
          ms: s.usable_ms,
          detail: 'Click→usable sidebar+main',
          file: 'browser navigation',
          call_count: 1,
        });
        pushOp({
          kind: 'js_resources',
          module: id,
          mode: mode + '_' + pass,
          ms: s.js_resources_ms,
          detail: `scripts network=${m.scripts?.network} cached=${m.scripts?.cached}`,
          file: 'assets/js + offline assets',
          call_count: m.scripts?.count || 0,
        });
        pushOp({
          kind: 'css_resources',
          module: id,
          mode: mode + '_' + pass,
          ms: s.css_resources_ms,
          detail: `css network=${m.css?.network} cached=${m.css?.cached}`,
          file: 'assets/css',
          call_count: m.css?.count || 0,
        });
        pushOp({
          kind: 'ajax',
          module: id,
          mode: mode + '_' + pass,
          ms: s.ajax_ms,
          detail: `ajax n=${m.ajax?.count}`,
          file: 'admin/api/* + probes',
          call_count: m.ajax?.count || 0,
        });
        (m.ajax?.top || []).slice(0, 3).forEach((a) => {
          pushOp({
            kind: 'ajax_one',
            module: id,
            mode: mode + '_' + pass,
            ms: a.duration,
            detail: a.name,
            file: a.name,
            call_count: 1,
          });
        });
        (m.scripts?.dups || []).forEach((d) => {
          pushOp({
            kind: 'dup_script',
            module: id,
            mode: mode + '_' + pass,
            ms: null,
            detail: d.name,
            file: d.name,
            call_count: d.count,
          });
        });
        (m.css?.dups || []).forEach((d) => {
          pushOp({
            kind: 'dup_css',
            module: id,
            mode: mode + '_' + pass,
            ms: null,
            detail: d.name,
            file: d.name,
            call_count: d.count,
          });
        });
      }
      pushOp({
        kind: 'cold_warm_delta',
        module: id,
        mode: mode,
        ms: pair.diff.delta_usable_ms,
        detail: `cold ${pair.diff.cold_usable_ms} → warm ${pair.diff.warm_usable_ms}; ttfb Δ ${pair.diff.delta_ttfb_ms}; js_net ${pair.diff.cold_js_network}→${pair.diff.warm_js_network}`,
        file: 'cold vs warm same URL',
        call_count: 2,
      });
    }
  }

  const top20 = ops
    .filter((o) => typeof o.ms === 'number' && o.ms > 0)
    .sort((a, b) => b.ms - a.ms)
    .slice(0, 20);

  // Aggregate root-cause signals
  const onlineDeltas = Object.entries(online).map(([id, p]) => ({
    id,
    ...p.diff,
  }));
  const offlineDeltas = Object.entries(offline).map(([id, p]) => ({
    id,
    ...p.diff,
  }));

  const avg = (arr, key) => {
    const xs = arr.map((x) => x[key]).filter((v) => typeof v === 'number');
    if (!xs.length) return null;
    return Math.round((xs.reduce((a, b) => a + b, 0) / xs.length) * 10) / 10;
  };

  const report = {
    phase: 'PERF-P0.4',
    mode: 'EVIDENCE_ONLY_NO_FIXES',
    at: new Date().toISOString(),
    base: BASE,
    note_php_stages:
      'No Server-Timing headers in production. PHP bootstrap/controller/RBAC/menu/sidebar are NOT individually timed in-browser; they collapse into document TTFB. Split requires instrumentation (out of scope for evidence-only).',
    online_summary: {
      avg_cold_usable_ms: avg(onlineDeltas, 'cold_usable_ms'),
      avg_warm_usable_ms: avg(onlineDeltas, 'warm_usable_ms'),
      avg_delta_usable_ms: avg(onlineDeltas, 'delta_usable_ms'),
      avg_cold_ttfb_ms: avg(onlineDeltas, 'cold_ttfb_ms'),
      avg_warm_ttfb_ms: avg(onlineDeltas, 'warm_ttfb_ms'),
      avg_delta_ttfb_ms: avg(onlineDeltas, 'delta_ttfb_ms'),
      modules: onlineDeltas,
    },
    offline_summary: {
      avg_cold_usable_ms: avg(offlineDeltas, 'cold_usable_ms'),
      avg_warm_usable_ms: avg(offlineDeltas, 'warm_usable_ms'),
      avg_delta_usable_ms: avg(offlineDeltas, 'delta_usable_ms'),
      avg_cold_ttfb_ms: avg(offlineDeltas, 'cold_ttfb_ms'),
      avg_warm_ttfb_ms: avg(offlineDeltas, 'warm_ttfb_ms'),
      modules: offlineDeltas,
    },
    top20,
    online,
    offline,
    elapsed_ms: Date.now() - tReport,
  };

  const outJson = path.join(OUT_DIR, `phase-p04-nav-latency-${tReport}.json`);
  const outLatest = path.join(OUT_DIR, 'phase-p04-nav-latency-latest.json');
  fs.writeFileSync(outJson, JSON.stringify(report, null, 2));
  fs.writeFileSync(outLatest, JSON.stringify(report, null, 2));

  // Compact stdout
  console.log(
    JSON.stringify(
      {
        out: outJson,
        online_summary: report.online_summary,
        offline_summary: report.offline_summary,
        top20: report.top20,
      },
      null,
      2
    )
  );

  await context.close();
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
