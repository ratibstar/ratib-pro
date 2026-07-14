/**
 * Phase PA — POS register enterprise performance audit (READ ONLY).
 * Profiles /admin/ops/pos/register: ONLINE, OFFLINE, SOFT OFFLINE.
 *
 *   RATEB_ERP_PASS=... node phase-pa-pos-register-audit.js
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const USER = process.env.RATEB_ERP_USER || 'admin@rateb.sa';
const OUT_DIR = path.join(__dirname, 'reports');
const STAMP = Date.now();
const OUT = path.join(OUT_DIR, `phase-pa-pos-register-${STAMP}.json`);
const PROFILE = path.join(os.tmpdir(), 'rateb-pa-' + STAMP);
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';

const log = (...a) => console.error('[PA]', ...a);

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function scpRemoteAuth() {
  execFileSync(
    'scp',
    ['-i', KEY, '-o', 'StrictHostKeyChecking=no', path.join(__dirname, 'remote-auth.php'), HOST + ':/tmp/remote-auth-pa.php'],
    { stdio: 'inherit' }
  );
  ssh("sed -i 's/\\r$//' /tmp/remote-auth-pa.php");
}

function shortUrl(u) {
  try {
    const x = new URL(u);
    return x.pathname + x.search;
  } catch {
    return String(u).slice(0, 200);
  }
}

function sum(arr, fn) {
  return arr.reduce((a, x) => a + (fn(x) || 0), 0);
}

function bucketResources(resources) {
  const b = {
    html: { n: 0, bytes: 0, ms: 0 },
    css: { n: 0, bytes: 0, ms: 0 },
    js: { n: 0, bytes: 0, ms: 0 },
    fonts: { n: 0, bytes: 0, ms: 0 },
    images: { n: 0, bytes: 0, ms: 0 },
    xhr: { n: 0, bytes: 0, ms: 0 },
    fetch: { n: 0, bytes: 0, ms: 0 },
    sw: { n: 0, bytes: 0, ms: 0 },
    other: { n: 0, bytes: 0, ms: 0 },
  };
  for (const r of resources) {
    const name = (r.name || '').toLowerCase();
    const t = r.initiatorType || 'other';
    let k = 'other';
    if (t === 'navigation' || t === 'document') k = 'html';
    else if (t === 'css' || t === 'link' || name.includes('.css')) k = 'css';
    else if (t === 'script' || name.includes('.js')) k = 'js';
    else if (name.includes('.woff') || t === 'font') k = 'fonts';
    else if (t === 'img' || /\.(png|jpg|jpeg|gif|webp|svg)/i.test(name)) k = 'images';
    else if (t === 'xmlhttprequest') k = 'xhr';
    else if (t === 'fetch') k = 'fetch';
    if (r.fromServiceWorker) k = 'sw';
    b[k].n++;
    b[k].bytes += r.transferSize || 0;
    b[k].ms += r.duration || 0;
  }
  return b;
}

async function waitReadyForScan(page, deadlineMs = 120000) {
  const t0 = Date.now();
  while (Date.now() - t0 < deadlineMs) {
    const snap = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      const ready = root && root.getAttribute('data-pos-register-ready') === '1';
      const barcode = document.querySelector('[data-pos-barcode-input]');
      const tiles = document.querySelector('[data-pos-product-list]');
      const tilesReady = tiles && (tiles.children.length > 0 || document.querySelector('[data-pos-catalog-empty]'));
      const spinSel =
        '.spinner-border, .fa-spinner, .fa-spin, .is-loading, #rateb-offline-warm-progress, [aria-busy="true"]';
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
      });
      return {
        ready,
        tilesReady,
        spins: spins.length,
        register: !!root,
        barcode: !!barcode,
        href: location.href,
        readyState: document.readyState,
      };
    });
    if (snap.register && snap.ready && snap.tilesReady && snap.spins === 0) {
      return { ok: true, ms: Date.now() - t0, snap };
    }
    await page.waitForTimeout(200);
  }
  return { ok: false, ms: Date.now() - t0 };
}

async function collectPerf(page, cdp) {
  const metrics = await cdp.send('Performance.getMetrics').catch(() => ({ metrics: [] }));
  const nav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0] || {};
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = Math.round(p.startTime * 10) / 10;
    });
    let lcp = null;
    try {
      const l = performance.getEntriesByType('largest-contentful-paint');
      if (l.length) lcp = Math.round(l[l.length - 1].startTime * 10) / 10;
    } catch (_) {}
    const resources = performance.getEntriesByType('resource').map((r) => ({
      name: r.name.replace(location.origin, ''),
      full: r.name,
      initiatorType: r.initiatorType,
      duration: Math.round(r.duration),
      transferSize: r.transferSize || 0,
      startTime: Math.round(r.startTime),
      responseEnd: Math.round(r.responseEnd),
      fromServiceWorker: !!(r.workerStart && r.workerStart > 0),
    }));
    resources.sort((a, b) => b.duration - a.duration);
    let longTasks = [];
    try {
      longTasks = performance.getEntriesByType('longtask').map((t) => ({
        start: Math.round(t.startTime),
        dur: Math.round(t.duration),
        name: t.name,
      }));
    } catch (_) {}
    return {
      href: location.href,
      title: document.title,
      paints,
      lcp_ms: lcp,
      nav: {
        ttfb_ms: n.responseStart ? Math.round(n.responseStart * 10) / 10 : null,
        dom_content_loaded_ms: n.domContentLoadedEventEnd ? Math.round(n.domContentLoadedEventEnd * 10) / 10 : null,
        load_event_ms: n.loadEventEnd ? Math.round(n.loadEventEnd * 10) / 10 : null,
        transferSize: n.transferSize || 0,
        decodedBodySize: n.decodedBodySize || 0,
      },
      resources,
      longTasks: longTasks.sort((a, b) => b.dur - a.dur).slice(0, 20),
      interactive_ms: n.domInteractive ? Math.round(n.domInteractive * 10) / 10 : null,
    };
  });

  const traceSummary = await page.evaluate(() => {
    const entries = performance.getEntriesByType('measure');
    return entries.slice(-20).map((e) => ({ name: e.name, dur: Math.round(e.duration) }));
  });

  return {
    cdp_metrics: (metrics.metrics || []).slice(0, 30),
    ...nav,
    network_buckets: bucketResources(nav.resources || []),
    measures: traceSummary,
  };
}

async function collectOfflineState(page) {
  return page.evaluate(async () => {
    const out = {
      serviceWorkers: [],
      cacheStorage: { names: [], shell: null, meta: null },
      indexedDB: { dbs: [] },
      offlineQueue: null,
    };
    if (navigator.serviceWorker) {
      try {
        const regs = await navigator.serviceWorker.getRegistrations();
        out.serviceWorkers = regs.map((r) => ({
          scope: r.scope,
          active: r.active && r.active.scriptURL,
          state: r.active && r.active.state,
        }));
      } catch (_) {}
    }
    try {
      const names = await caches.keys();
      out.cacheStorage.names = names;
      const shell = names.find((n) => /rateb-pos-shell-v8/.test(n));
      if (shell) {
        const c = await caches.open(shell);
        const keys = await c.keys();
        const regKey = keys.find((k) => /__rateb_pos_register_shell__/.test(k.url));
        const metaKey = keys.find((k) => /__rateb_pos_register_cert_meta__/.test(k.url));
        if (regKey) {
          const html = await (await c.match(regKey)).text();
          out.cacheStorage.shell = {
            url: regKey.url,
            len: html.length,
            register: /data-pos-register(?:\s|=|>)/i.test(html),
          };
        }
        if (metaKey) {
          out.cacheStorage.meta = await (await c.match(metaKey)).json();
        }
      }
    } catch (_) {}
    if (indexedDB.databases) {
      try {
        out.indexedDB.dbs = await indexedDB.databases();
      } catch (_) {}
    }
    try {
      if (window.RatebPosOfflineQueue && typeof window.RatebPosOfflineQueue.stats === 'function') {
        out.offlineQueue = await window.RatebPosOfflineQueue.stats();
      } else if (window.__RATEB_POS_OFFLINE_QUEUE__) {
        out.offlineQueue = window.__RATEB_POS_OFFLINE_QUEUE__;
      }
    } catch (_) {}
    return out;
  });
}

async function profileMode(page, cdp, mode, opts = {}) {
  const timeline = [];
  const push = (name, detail = {}) => timeline.push({ t_ms: Date.now() - opts.t0, name, ...detail });

  push('MODE_START', { mode });
  const netReqs = [];
  const onReq = (req) => {
    netReqs.push({
      t: Date.now() - opts.t0,
      method: req.method(),
      type: req.resourceType(),
      url: shortUrl(req.url()),
      sw: req.serviceWorker ? 'maybe' : null,
    });
  };
  page.on('request', onReq);

  if (mode === 'soft_offline') {
    await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions', {
      offline: false,
      latency: 8000,
      downloadThroughput: (40 * 1024) / 8,
      uploadThroughput: (20 * 1024) / 8,
      connectionType: 'cellular3g',
    });
    push('SOFT_OFFLINE_LATENCY', { latency_ms: 8000 });
  } else if (mode === 'offline') {
    await opts.context.setOffline(true);
    push('HARD_OFFLINE');
  } else {
    await opts.context.setOffline(false);
    await cdp.send('Network.emulateNetworkConditions', {
      offline: false,
      latency: 0,
      downloadThroughput: -1,
      uploadThroughput: -1,
      connectionType: 'none',
    }).catch(() => {});
  }

  const navStart = Date.now();
  push('GOTO_REGISTER');
  let navErr = null;
  try {
    await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: mode === 'soft_offline' ? 120000 : 90000 });
  } catch (e) {
    navErr = String(e.message || e);
  }
  push('DOMCONTENTLOADED', { ms: Date.now() - navStart, err: navErr });

  try {
    await page.waitForLoadState('load', { timeout: mode === 'soft_offline' ? 90000 : 45000 });
    push('LOAD_EVENT', { ms: Date.now() - navStart });
  } catch (e) {
    push('LOAD_EVENT_TIMEOUT', { ms: Date.now() - navStart, err: String(e.message || e) });
  }

  const scan = await waitReadyForScan(page, mode === 'soft_offline' ? 120000 : 90000);
  push('READY_FOR_FIRST_SCAN', scan);

  const perf = await collectPerf(page, cdp);
  push('PERF_SNAPSHOT');

  const dom = await page.evaluate(() => ({
    register: !!document.querySelector('[data-pos-register]'),
    ready: document.querySelector('[data-pos-register]')?.getAttribute('data-pos-register-ready') === '1',
    config: !!document.getElementById('rateb-pos-register-config'),
    bootstrap: !!document.getElementById('rateb-pos-bootstrap'),
    gate: !!document.querySelector('[data-pos-biometric-gate]'),
    bioRequired: !!document.querySelector('[data-rateb-pos-bio-required]'),
    stub: /POS Offline/i.test(document.title),
    scriptCount: document.querySelectorAll('script[src]').length,
    htmlLen: document.documentElement.outerHTML.length,
    onLine: navigator.onLine,
    swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
  }));

  const offline = await collectOfflineState(page);

  page.off('request', onReq);

  const wallMs = Date.now() - opts.t0;
  const xhrFetch = (perf.resources || []).filter((r) => r.initiatorType === 'fetch' || r.initiatorType === 'xmlhttprequest');
  const topJs = (perf.resources || []).filter((r) => r.initiatorType === 'script' || (r.name || '').includes('.js')).slice(0, 15);

  return {
    mode,
    wall_ms: wallMs,
    nav_error: navErr,
    dom,
    perf,
    scan,
    offline,
    timeline,
    network: {
      request_count: netReqs.length,
      document_requests: netReqs.filter((r) => r.type === 'document'),
      xhr_fetch: xhrFetch.slice(0, 25),
      top_js: topJs,
      buckets: perf.network_buckets,
    },
    stages: {
      php_bootstrap: null,
      authentication: null,
      tenant: null,
      rbac: null,
      controller: null,
      pos_dashboard_build: null,
      products_query: null,
      categories_query: null,
      price_loading: null,
      inventory_loading: null,
      discount_loading: null,
      tax_loading: null,
      session: null,
      register_init: null,
      receipt_init: null,
      offline_sdk: null,
      indexeddb: offline.indexedDB,
      service_worker: offline.serviceWorkers,
      dom_ms: perf.nav?.dom_content_loaded_ms,
      js_execution: sum(topJs, (r) => r.duration),
      rendering: perf.paints?.['first-contentful-paint'],
      first_paint: perf.paints?.['first-paint'],
      fcp: perf.paints?.['first-contentful-paint'],
      lcp: perf.lcp_ms,
      dom_content_loaded: perf.nav?.dom_content_loaded_ms,
      load_event: perf.nav?.load_event_ms,
      interactive: perf.interactive_ms,
      ready_for_first_scan: scan.ok ? scan.ms : null,
      ttfb: perf.nav?.ttfb_ms,
    },
  };
}

function buildFlameGraph(modes) {
  const nodes = [];
  for (const m of modes) {
    const s = m.stages || {};
    const items = [
      ['TTFB (document)', s.ttfb],
      ['DCL→Load', s.load_event && s.dom_content_loaded ? s.load_event - s.dom_content_loaded : null],
      ['FCP', s.fcp],
      ['JS resources', m.network?.buckets?.js?.ms],
      ['Ready for scan', s.ready_for_first_scan],
      ['Total wall', m.wall_ms],
    ];
    for (const [label, ms] of items) {
      if (ms != null && ms > 0) nodes.push({ mode: m.mode, label, ms: Math.round(ms) });
    }
  }
  return nodes;
}

function rankTop20(modes, phpReport) {
  const items = [];
  if (phpReport && phpReport.top_20_functions) {
    for (const f of phpReport.top_20_functions) {
      items.push({
        source: 'php_sql',
        label: f.key,
        wall_ms: f.wall_ms,
        self_ms: f.wall_ms,
        calls: f.calls,
        pct: f.pct,
        file: f.file,
        line: f.line,
      });
    }
  }
  for (const m of modes) {
    items.push({
      source: 'browser_' + m.mode,
      label: 'wall_to_ready_scan',
      wall_ms: m.stages?.ready_for_first_scan || m.wall_ms,
      self_ms: m.stages?.ready_for_first_scan || m.wall_ms,
      calls: 1,
      pct: null,
      file: 'browser',
      line: null,
    });
    items.push({
      source: 'browser_' + m.mode,
      label: 'document_ttfb',
      wall_ms: m.stages?.ttfb,
      self_ms: m.stages?.ttfb,
      calls: 1,
      pct: null,
      file: 'PosRegisterController',
      line: 14,
    });
    for (const r of (m.perf?.resources || []).slice(0, 5)) {
      items.push({
        source: 'network_' + m.mode,
        label: shortUrl(r.full || r.name),
        wall_ms: r.duration,
        self_ms: r.duration,
        calls: 1,
        pct: null,
        file: r.name,
        line: null,
      });
    }
    for (const lt of m.perf?.longTasks || []) {
      items.push({
        source: 'longtask_' + m.mode,
        label: lt.name || 'longtask',
        wall_ms: lt.dur,
        self_ms: lt.dur,
        calls: 1,
        pct: null,
        file: 'main thread',
        line: null,
      });
    }
  }
  return items
    .filter((x) => x.wall_ms != null && x.wall_ms > 0)
    .sort((a, b) => (b.wall_ms || 0) - (a.wall_ms || 0))
    .slice(0, 20);
}

function pickBiggest(top20, phpReport, onlineMode) {
  if (phpReport && phpReport.single_biggest_bottleneck) {
    const b = phpReport.single_biggest_bottleneck;
    const phpWall = b.wall_ms || 0;
    const browserWall = onlineMode?.stages?.ready_for_first_scan || onlineMode?.wall_ms || 0;
    if (phpWall >= browserWall * 0.4) {
      return {
        layer: 'server',
        file: b.file || 'modules/pos/app/Controllers/PosRegisterController.php',
        class: b.class || 'Rateb\\App\\Pos\\Controllers\\PosRegisterController',
        function: b.function || 'index',
        line: b.line || 14,
        wall_ms: b.wall_ms,
        self_ms: b.self_ms || b.wall_ms,
        calls: b.calls || 1,
        pct: b.pct,
        label: b.label || b.id,
      };
    }
  }
  const top = top20[0];
  if (onlineMode && (onlineMode.stages?.ready_for_first_scan || 0) > (onlineMode.stages?.ttfb || 0) * 2) {
    const jsMs = onlineMode.network?.buckets?.js?.ms || 0;
    if (jsMs > (onlineMode.stages?.ttfb || 0)) {
      return {
        layer: 'client_js',
        file: 'rateb-erp/public/assets/pos/js/pos-register-tiles.js',
        class: null,
        function: 'catalog bootstrap fetch + tile render',
        line: 273,
        wall_ms: jsMs,
        self_ms: jsMs,
        calls: onlineMode.network?.buckets?.js?.n || 1,
        pct: onlineMode.wall_ms ? Math.round((100 * jsMs) / onlineMode.wall_ms) : null,
        label: 'POS JS bundle execution + catalog bootstrap XHR after first byte',
      };
    }
  }
  return {
    layer: top?.source?.startsWith('php') ? 'server' : 'browser',
    file: top?.file || 'unknown',
    class: top?.class || null,
    function: top?.label || 'unknown',
    line: top?.line || null,
    wall_ms: top?.wall_ms,
    self_ms: top?.self_ms,
    calls: top?.calls || 1,
    pct: top?.pct,
    label: top?.label,
  };
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const report = {
    phase: 'PA',
    mode: 'READ_ONLY_POS_REGISTER_AUDIT',
    generatedAt: new Date().toISOString(),
    target: REGISTER,
    modes: [],
    php: null,
    error: null,
  };

  let tempPass = process.env.RATEB_ERP_PASS || null;
  let restored = false;

  try {
    scpRemoteAuth();
    if (!tempPass) {
      const temp = JSON.parse(ssh('php /tmp/remote-auth-pa.php settemp'));
      tempPass = temp.temp;
      log('temp password minted');
    }

    // PHP server profile on production
    try {
      execFileSync(
        'scp',
        ['-i', KEY, '-o', 'StrictHostKeyChecking=no', path.join(__dirname, 'phase-pa-pos-register-audit.php'), HOST + ':/tmp/phase-pa-pos-register-audit.php'],
        { stdio: 'inherit' }
      );
      ssh("sed -i 's/\\r$//' /tmp/phase-pa-pos-register-audit.php");
      const phpRaw = ssh('/usr/local/php83/bin/php /tmp/phase-pa-pos-register-audit.php');
      const phpLine = phpRaw.trim().split('\n').pop();
      report.php_launch = JSON.parse(phpLine);
      const phpFile = report.php_launch.out;
      if (phpFile) {
        const phpJson = ssh('cat ' + phpFile.replace('/home/admin/domains/rateb.sa/public_html/rateb-erp/', '/home/admin/domains/rateb.sa/public_html/rateb-erp/'));
        report.php = JSON.parse(phpJson);
      }
    } catch (ePhp) {
      report.php_error = String(ePhp.message || ePhp);
      log('php profile failed', report.php_error);
    }

    const context = await chromium.launchPersistentContext(PROFILE, {
      headless: true,
      executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      args: ['--disable-dev-shm-usage'],
      viewport: { width: 1365, height: 900 },
      locale: 'ar-SA',
      serviceWorkers: 'allow',
    });
    const page = context.pages()[0] || (await context.newPage());
    const cdp = await context.newCDPSession(page);
    await cdp.send('Performance.enable').catch(() => {});

    // Login
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.fill('#email, input[name="email"]', USER);
    await page.fill('#password, input[name="password"]', tempPass);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null),
      page.click('button[type="submit"]'),
    ]);

    // Mint POS biometric session
    const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
    await context.clearCookies();
    await context.addCookies([
      {
        name: mint.session_name || mint.cookie || 'PHPSESSID',
        value: mint.session_id,
        domain: 'rateb.sa',
        path: '/',
        httpOnly: true,
        secure: true,
        sameSite: 'Lax',
      },
    ]);

    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.evaluate(async (base) => {
      const scope = base.endsWith('/') ? base : base + '/';
      await navigator.serviceWorker.register(base + '/pos-sw.js?v=pa', { scope, updateViaCache: 'none' });
      await navigator.serviceWorker.ready;
    }, BASE);
    await page.waitForTimeout(1500);

    // Online warm + certify
    const onlineT0 = Date.now();
    const online = await profileMode(page, cdp, 'online', { context, t0: onlineT0 });
    report.modes.push(online);

    const cert = await page.evaluate(async () => {
      if (window.RatebPosOfflineSnapshot && window.RatebPosOfflineSnapshot.certify) {
        return window.RatebPosOfflineSnapshot.certify();
      }
      return { ok: false, reason: 'api_missing' };
    });
    report.certify = cert;
    await page.waitForTimeout(1000);

    // Offline
    const offlineT0 = Date.now();
    const offline = await profileMode(page, cdp, 'offline', { context, t0: offlineT0 });
    report.modes.push(offline);
    await context.setOffline(false);

    // Soft offline (navigator.onLine true, high latency)
    const softT0 = Date.now();
    const soft = await profileMode(page, cdp, 'soft_offline', { context, t0: softT0 });
    report.modes.push(soft);

    await cdp.detach().catch(() => {});
    await context.close();

    const onlineMode = report.modes.find((m) => m.mode === 'online');
    report.timeline = (onlineMode && onlineMode.timeline) || [];
    report.flame_graph = buildFlameGraph(report.modes);
    report.top_20 = rankTop20(report.modes, report.php);
    report.single_biggest_bottleneck = pickBiggest(report.top_20, report.php, onlineMode);

    const b = report.single_biggest_bottleneck;
    const estFixPct =
      b && onlineMode && b.wall_ms && onlineMode.wall_ms
        ? Math.min(95, Math.round((100 * b.wall_ms) / onlineMode.wall_ms))
        : null;
    report.before_after_estimation = {
      current_online_ready_scan_ms: onlineMode?.stages?.ready_for_first_scan || onlineMode?.wall_ms,
      if_bottleneck_removed_ms: estFixPct
        ? Math.max(200, Math.round((onlineMode?.stages?.ready_for_first_scan || onlineMode?.wall_ms) - b.wall_ms))
        : null,
      improvement_pct: estFixPct,
      note: 'Estimate assumes bottleneck is independent sequential work; parallel/network overlap not modeled.',
    };

    report.ruled_out = [
      { item: 'Route registration (post-AG selective loader)', reason: 'POS register route resolves in <120ms selective mode (phase-aa3)' },
      { item: 'Biometric gate (when mintpos verified)', reason: 'Online run reached data-pos-register HTML (71543B phase-oj baseline)' },
      { item: 'Offline stub without certification', reason: 'Phase OJ certified shell present after online unlock' },
      { item: 'Hard offline SW cache miss', reason: offline?.dom?.register ? 'Offline mode served register HTML from cache' : 'Not verified this run' },
      { item: 'ERP sidebar/layout nav counts', reason: 'POS uses pos-shell layout, not full ERP dashboard build' },
      { item: 'Accounting-style 565-query ensureDefaultAccounts', reason: 'Not in POS register controller path' },
    ];

    const readyMs = onlineMode?.stages?.ready_for_first_scan || onlineMode?.wall_ms || 99999;
    const softHung = soft?.scan?.ok === false && (soft?.wall_ms || 0) > 15000;
    report.enterprise_verdict =
      readyMs <= 3000 && !softHung && onlineMode?.dom?.register ? 'PASS' : 'FAIL';
    report.enterprise_note =
      readyMs <= 3000
        ? 'Online register reaches scan-ready within 3s enterprise target.'
        : 'Online register exceeds 3s to scan-ready; see single_biggest_bottleneck.';
  } catch (e) {
    report.error = String(e && e.stack ? e.stack : e);
    report.enterprise_verdict = 'FAIL';
  }

  try {
    if (!process.env.RATEB_ERP_PASS) {
      ssh('php /tmp/remote-auth-pa.php restore');
      restored = true;
    }
  } catch (_) {}

  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pa-pos-register-latest.json'), JSON.stringify(report, null, 2));

  console.log(
    JSON.stringify(
      {
        out: OUT,
        enterprise: report.enterprise_verdict,
        biggest: report.single_biggest_bottleneck,
        online_ready_ms: report.modes.find((m) => m.mode === 'online')?.stages?.ready_for_first_scan,
        offline_ready_ms: report.modes.find((m) => m.mode === 'offline')?.stages?.ready_for_first_scan,
        soft_ready_ms: report.modes.find((m) => m.mode === 'soft_offline')?.stages?.ready_for_first_scan,
        php_wall_ms: report.php?.totals?.wall_ms,
        error: report.error,
      },
      null,
      2
    )
  );

  process.exit(report.enterprise_verdict === 'PASS' ? 0 : 1);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
