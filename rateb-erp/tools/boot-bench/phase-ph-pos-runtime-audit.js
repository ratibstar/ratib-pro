/**
 * Phase PH — POS Runtime Enterprise Audit (READ ONLY).
 * Window of interest: responseStart → FIRST BARCODE SCAN READY.
 * Natural production SW path only (no synthetic SW register).
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const ADMIN = BASE + '/admin/';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

const OBSERVER_HOOK = `
(() => {
  window.__PH_LT__ = [];
  window.__PH_MARKS__ = [];
  try {
    const po = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        window.__PH_LT__.push({
          name: e.name || 'longtask',
          start: Math.round(e.startTime * 10) / 10,
          dur: Math.round(e.duration * 10) / 10,
          attribution: (e.attribution || []).map((a) => ({
            name: a.name,
            containerType: a.containerType,
            containerSrc: a.containerSrc,
            containerId: a.containerId,
            containerName: a.containerName,
          })),
        });
      }
    });
    po.observe({ type: 'longtask', buffered: true });
  } catch (_) {}
  try {
    const po2 = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        window.__PH_MARKS__.push({
          entryType: e.entryType,
          name: e.name,
          start: Math.round(e.startTime * 10) / 10,
          dur: Math.round((e.duration || 0) * 10) / 10,
        });
      }
    });
    po2.observe({ type: 'measure', buffered: true });
    po2.observe({ type: 'mark', buffered: true });
  } catch (_) {}
})();`;

function mapScriptToModule(url) {
  const u = String(url || '');
  const leaf = u.split('/').pop().split('?')[0];
  const map = {
    'pos-register.js': {
      file: 'public/assets/pos/js/pos-register.js',
      cls: 'IIFE register bootstrap',
      fn: 'bindEvents / loadSession / ready',
      lines: '1–1461; ready@1402',
      role: 'POS runtime + barcode + shortcuts',
    },
    'pos-register-tiles.js': {
      file: 'public/assets/pos/js/pos-register-tiles.js',
      cls: 'IIFE tiles/catalog',
      fn: 'catalog seed + virtual grid',
      lines: '1–990+',
      role: 'catalog / tiles (scan path dependency)',
    },
    'pos-keyboard.js': {
      file: 'public/assets/pos/js/pos-keyboard.js',
      cls: 'keyboard shortcuts',
      fn: 'shortcut bind',
      role: 'keyboard shortcuts',
    },
    'pos-register-checkout.js': {
      file: 'public/assets/pos/js/pos-register-checkout.js',
      cls: 'checkout',
      fn: 'checkout UI init',
      role: 'receipt/checkout',
    },
    'pos-register-ops.js': {
      file: 'public/assets/pos/js/pos-register-ops.js',
      cls: 'ops',
      fn: 'ops panels',
      role: 'ops (often post-ready)',
    },
    'pos-auth-lock.js': {
      file: 'public/assets/pos/js/pos-auth-lock.js',
      cls: 'auth lock',
      fn: 'auth init',
      role: 'authentication',
    },
    'pos-biometric-gate.js': {
      file: 'public/assets/pos/js/pos-biometric-gate.js',
      cls: 'biometric',
      fn: 'gate init',
      role: 'authentication/biometric',
    },
    'pos-module.js': {
      file: 'public/assets/pos/js/pos-module.js',
      cls: 'pos module',
      fn: 'module bootstrap',
      role: 'module init',
    },
    'pos-offline-sync.js': {
      file: 'public/assets/pos/js/pos-offline-sync.js',
      cls: 'offline sync',
      fn: 'sync init',
      role: 'offline (post critical?)',
    },
    'pos-offline-print.js': {
      file: 'public/assets/pos/js/pos-offline-print.js',
      cls: 'print',
      fn: 'print init',
      role: 'receipt/print',
    },
    'pos-capabilities.js': {
      file: 'public/assets/pos/js/pos-capabilities.js',
      cls: 'capabilities',
      fn: 'cap bootstrap',
      role: 'module init',
    },
    'pos-supervisor-approval.js': {
      file: 'public/assets/pos/js/pos-supervisor-approval.js',
      cls: 'supervisor',
      fn: 'approval init',
      role: 'auth/approval',
    },
    'theme.js': {
      file: 'public/assets/js/theme.js',
      cls: 'theme',
      fn: 'theme apply',
      role: 'shell chrome',
    },
  };
  return Object.assign({ leaf, url: u, file: leaf, cls: 'script', fn: 'parse+eval', lines: 'n/a', role: 'script' }, map[leaf] || {});
}

async function waitScanReady(page, timeoutMs) {
  const t0 = Date.now();
  const snaps = [];
  while (Date.now() - t0 < timeoutMs) {
    const snap = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      const ready = root && root.getAttribute('data-pos-register-ready') === '1';
      const barcode = document.querySelector('[data-pos-barcode-input]');
      const tiles = document.querySelector('[data-pos-product-list]');
      const tilesReady = !!(tiles && (tiles.children.length > 0 || document.querySelector('[data-pos-catalog-empty]')));
      const n = performance.getEntriesByType('navigation')[0];
      return {
        t_nav: Math.round(performance.now() * 10) / 10,
        responseStart: n ? Math.round(n.responseStart * 10) / 10 : null,
        ready: !!ready,
        barcode: !!barcode,
        tilesReady,
        register: !!root,
        focused: document.activeElement && document.activeElement.getAttribute
          ? document.activeElement.getAttribute('data-pos-barcode-input') !== null ||
            document.activeElement.matches?.('[data-pos-barcode-input]')
          : false,
      };
    });
    snaps.push({ wall: Date.now() - t0, ...snap });
    if (snap.register && snap.ready && snap.tilesReady && snap.barcode) {
      return { ok: true, ms: Date.now() - t0, snap, snaps };
    }
    await page.waitForTimeout(25);
  }
  return { ok: false, ms: Date.now() - t0, snaps };
}

(async () => {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-ph-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage', '--enable-precise-memory-info'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name,
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  await context.addInitScript({ content: OBSERVER_HOOK });

  const page = context.pages()[0] || (await context.newPage());

  // Natural SW settle on admin (stable force-sw-v52)
  await page.goto(ADMIN, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(4500);

  const cdp = await context.newCDPSession(page);
  await cdp.send('Profiler.enable');
  await cdp.send('Profiler.setSamplingInterval', { interval: 100 }); // 100µs → denser
  // Actually interval is in microseconds in Chrome? Docs say microseconds. 100 = 0.1ms
  try {
    await page.coverage.startJSCoverage({ resetOnNavigation: true, reportAnonymousScripts: false });
  } catch (_) {}

  await cdp.send('Profiler.start');
  const tGoto = Date.now();
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const scan = await waitScanReady(page, 20000);
  const scanWall = Date.now() - tGoto;

  const profilerStop = await cdp.send('Profiler.stop');
  let coverage = [];
  try {
    coverage = await page.coverage.stopJSCoverage();
  } catch (_) {}

  const runtime = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = Math.round(p.startTime * 10) / 10;
    });
    let lcp = null;
    try {
      const l = performance.getEntriesByType('largest-contentful-paint');
      if (l.length) lcp = Math.round(l[l.length - 1].startTime * 10) / 10;
    } catch (_) {}

    const rs = n.responseStart || 0;
    const resources = performance.getEntriesByType('resource').map((x) => ({
      name: x.name.replace(location.origin, ''),
      full: x.name,
      type: x.initiatorType,
      start: Math.round(x.startTime * 10) / 10,
      start_after_rs: Math.round((x.startTime - rs) * 10) / 10,
      duration: Math.round(x.duration * 10) / 10,
      responseEnd: Math.round(x.responseEnd * 10) / 10,
      end_after_rs: Math.round((x.responseEnd - rs) * 10) / 10,
      transferSize: x.transferSize || 0,
      encodedBodySize: x.encodedBodySize || 0,
      decodedBodySize: x.decodedBodySize || 0,
    }));

    const scripts = resources
      .filter((x) => x.type === 'script' || /\.js(\?|$)/i.test(x.name))
      .slice()
      .sort((a, b) => b.duration - a.duration);

    // CSS / layout proxies
    const css = resources.filter((x) => /\.css(\?|$)/i.test(x.name) || x.type === 'link');

    // Gap analysis: idle gaps between sorted responseEnd of scripts before dcl/load
    const chain = scripts
      .slice()
      .sort((a, b) => a.start - b.start)
      .map((s) => ({ name: s.name, start: s.start, end: s.responseEnd, dur: s.duration }));

    let largestIdle = { gap: 0, after: null, before: null };
    for (let i = 1; i < chain.length; i++) {
      const gap = chain[i].start - chain[i - 1].end;
      if (gap > largestIdle.gap) {
        largestIdle = { gap: Math.round(gap * 10) / 10, after: chain[i - 1].name, before: chain[i].name };
      }
    }

    const longtasks = (window.__PH_LT__ || []).slice().sort((a, b) => b.dur - a.dur);
    const afterRsLt = longtasks.filter((t) => t.start >= rs - 1);

    return {
      nav: {
        workerStart: n.workerStart || 0,
        responseStart: Math.round(rs * 10) / 10,
        responseEnd: Math.round((n.responseEnd || 0) * 10) / 10,
        html_download_ms: r(n.responseStart, n.responseEnd),
        domInteractive: Math.round((n.domInteractive || 0) * 10) / 10,
        dcl: Math.round((n.domContentLoadedEventEnd || 0) * 10) / 10,
        load: Math.round((n.loadEventEnd || 0) * 10) / 10,
        transferSize: n.transferSize || 0,
        decodedBodySize: n.decodedBodySize || 0,
      },
      relative_to_responseStart: {
        html_download: r(rs, n.responseEnd),
        to_domInteractive: r(rs, n.domInteractive),
        to_dcl: r(rs, n.domContentLoadedEventEnd),
        to_load: r(rs, n.loadEventEnd),
        to_fp: paints['first-paint'] != null ? r(rs, paints['first-paint']) : null,
        to_fcp: paints['first-contentful-paint'] != null ? r(rs, paints['first-contentful-paint']) : null,
        to_lcp: lcp != null ? r(rs, lcp) : null,
      },
      paints,
      lcp,
      scripts,
      css,
      top_resources: resources.slice().sort((a, b) => b.duration - a.duration).slice(0, 40),
      script_chain: chain,
      largestIdle,
      longtasks: afterRsLt,
      longtasks_all: longtasks,
      htmlLen: document.documentElement.outerHTML.length,
      scriptTags: [...document.querySelectorAll('script[src]')].map((s) => s.src.replace(location.origin, '')),
      inlineJsonBytes: {
        config: (document.getElementById('rateb-pos-register-config') || {}).textContent?.length || 0,
        bootstrap: (document.getElementById('rateb-pos-bootstrap') || {}).textContent?.length || 0,
      },
      sw: navigator.serviceWorker?.controller?.scriptURL || null,
      ready: document.querySelector('[data-pos-register]')?.getAttribute('data-pos-register-ready') === '1',
    };
  });

  // Attribute scan ready relative to responseStart
  const scanReadyAbs = runtime.nav.dcl + (scan.ok ? scan.ms : 0);
  // Better: use last snap t_nav
  const scanReadyNav = scan.snap?.t_nav || scanReadyAbs;
  const rs = runtime.nav.responseStart;
  const afterRsWindow = Math.max(0, Math.round((scanReadyNav - rs) * 10) / 10);

  // Process profiler nodes → top self time
  const profile = profilerStop.profile || {};
  const nodes = profile.nodes || [];
  const samples = profile.samples || [];
  const timeDeltas = profile.timeDeltas || [];
  const selfHits = new Map();
  for (let i = 0; i < samples.length; i++) {
    const id = samples[i];
    const dt = timeDeltas[i] || 0;
    selfHits.set(id, (selfHits.get(id) || 0) + dt);
  }
  const nodeById = new Map(nodes.map((n) => [n.id, n]));
  const topSelf = [...selfHits.entries()]
    .map(([id, us]) => {
      const n = nodeById.get(id) || {};
      const f = n.callFrame || {};
      return {
        self_us: us,
        self_ms: Math.round((us / 1000) * 10) / 10,
        functionName: f.functionName || '(anonymous)',
        url: (f.url || '').replace(BASE, '').replace('https://rateb.sa', ''),
        line: f.lineNumber != null ? f.lineNumber + 1 : null,
        column: f.columnNumber != null ? f.columnNumber + 1 : null,
      };
    })
    .filter((x) => x.self_ms >= 0.3)
    .sort((a, b) => b.self_ms - a.self_ms)
    .slice(0, 40);

  // Coverage: unused bytes per script
  const cov = coverage
    .map((e) => {
      const used = (e.functions || []).reduce((a, fn) => a + (fn.ranges || []).reduce((b, r) => b + (r.count ? r.endOffset - r.startOffset : 0), 0), 0);
      // simpler: text length vs used ranges
      const textLen = (e.source || e.text || '').length || 0;
      let usedBytes = 0;
      (e.functions || []).forEach((fn) => {
        (fn.ranges || []).forEach((rg) => {
          if (rg.count > 0) usedBytes += rg.endOffset - rg.startOffset;
        });
      });
      return {
        url: (e.url || '').replace(BASE, '').replace('https://rateb.sa', ''),
        textLen: textLen || e.functions?.length,
        usedBytes,
        unusedApprox: textLen ? Math.max(0, textLen - usedBytes) : null,
      };
    })
    .filter((x) => /pos-|theme\.js/i.test(x.url))
    .sort((a, b) => (b.unusedApprox || 0) - (a.unusedApprox || 0));

  // Build top 30 ops after responseStart
  const ops = [];
  ops.push({
    name: 'HTML document download',
    wall_ms: runtime.nav.html_download_ms,
    self_ms: runtime.nav.html_download_ms,
    pct: null,
    phase: 'parse-prep',
  });
  ops.push({
    name: 'responseStart → DOMContentLoaded',
    wall_ms: runtime.relative_to_responseStart.to_dcl,
    self_ms: runtime.relative_to_responseStart.to_dcl,
    phase: 'critical',
  });
  ops.push({
    name: 'responseStart → Scan Ready (nav clock)',
    wall_ms: afterRsWindow,
    self_ms: afterRsWindow,
    phase: 'critical',
  });
  runtime.scripts.forEach((s) => {
    const mod = mapScriptToModule(s.full || s.name);
    ops.push({
      name: 'script:' + mod.leaf,
      wall_ms: s.duration,
      self_ms: s.duration,
      start_after_rs: s.start_after_rs,
      end_after_rs: s.end_after_rs,
      transferSize: s.transferSize,
      decodedBodySize: s.decodedBodySize,
      ...mod,
      phase: 'js',
    });
  });
  (runtime.longtasks || []).slice(0, 15).forEach((t, i) => {
    ops.push({
      name: 'longtask#' + (i + 1),
      wall_ms: t.dur,
      self_ms: t.dur,
      start_after_rs: Math.round((t.start - rs) * 10) / 10,
      attribution: t.attribution,
      phase: 'event-loop',
    });
  });
  topSelf.slice(0, 15).forEach((t) => {
    ops.push({
      name: 'cpu:' + (t.functionName || '?') + '@' + (t.url || '').split('/').pop(),
      wall_ms: t.self_ms,
      self_ms: t.self_ms,
      file: t.url,
      lines: String(t.line),
      fn: t.functionName,
      phase: 'cpu-sample',
    });
  });

  ops.sort((a, b) => (b.wall_ms || 0) - (a.wall_ms || 0));
  const top30 = ops.slice(0, 30).map((o, i) => ({
    rank: i + 1,
    ...o,
    percentage_of_after_rs: afterRsWindow > 0 ? Math.round(((o.wall_ms || 0) / afterRsWindow) * 1000) / 10 : null,
  }));

  // Critical path phases (ms after responseStart)
  const timeline = [
    { t: 0, name: 'responseStart', ms_after_rs: 0 },
    { t: runtime.nav.html_download_ms, name: 'HTML download end', ms_after_rs: runtime.relative_to_responseStart.html_download },
    {
      t: runtime.relative_to_responseStart.to_domInteractive,
      name: 'domInteractive (parse)',
      ms_after_rs: runtime.relative_to_responseStart.to_domInteractive,
    },
    { t: runtime.relative_to_responseStart.to_dcl, name: 'DOMContentLoaded', ms_after_rs: runtime.relative_to_responseStart.to_dcl },
    {
      t: runtime.relative_to_responseStart.to_fcp,
      name: 'FCP',
      ms_after_rs: runtime.relative_to_responseStart.to_fcp,
    },
    { t: runtime.relative_to_responseStart.to_load, name: 'load', ms_after_rs: runtime.relative_to_responseStart.to_load },
    { t: afterRsWindow, name: 'FIRST SCAN READY', ms_after_rs: afterRsWindow },
  ].filter((x) => x.ms_after_rs != null);

  // Largest script by size / duration
  const largestJsByBytes = runtime.scripts.slice().sort((a, b) => (b.decodedBodySize || b.transferSize || 0) - (a.decodedBodySize || a.transferSize || 0))[0];
  const largestJsByDur = runtime.scripts[0];
  const largestLt = (runtime.longtasks || [])[0] || null;
  const largestCpu = topSelf[0] || null;

  // POS-critical scripts only for ROI
  const posCriticalLeaves = new Set([
    'pos-register.js',
    'pos-register-tiles.js',
    'pos-keyboard.js',
    'pos-module.js',
    'pos-auth-lock.js',
    'pos-biometric-gate.js',
    'pos-register-checkout.js',
  ]);
  const posScripts = runtime.scripts.filter((s) => posCriticalLeaves.has((s.name || '').split('/').pop().split('?')[0]));
  const serialScriptSum = posScripts.reduce((a, s) => a + (s.duration || 0), 0);

  // Waterfall blocking: last script responseEnd after rs vs scan
  const lastPosScriptEnd = posScripts.reduce((m, s) => Math.max(m, s.responseEnd || 0), 0);
  const scriptWaterfallAfterRs = Math.max(0, Math.round((lastPosScriptEnd - rs) * 10) / 10);

  // Single-optimization ROI: pick largest longtask OR largest serial script download on critical path
  let oneShot = null;
  if (largestLt && largestLt.dur >= 40) {
    const mod = (largestLt.attribution && largestLt.attribution[0] && largestLt.attribution[0].containerSrc) || '';
    const mapped = mapScriptToModule(mod || 'pos-register.js');
    oneShot = {
      target: 'Largest longtask (event loop block)',
      file: mapped.file,
      class: mapped.cls,
      function: mapped.fn,
      lines: mapped.lines,
      wall_ms: largestLt.dur,
      self_ms: largestLt.dur,
      calls: 1,
      percentage: afterRsWindow ? Math.round((largestLt.dur / afterRsWindow) * 1000) / 10 : null,
      before_after_rs_ms: afterRsWindow,
      expected_after_rs_ms: Math.max(0, Math.round((afterRsWindow - largestLt.dur) * 10) / 10),
      gain_ms: largestLt.dur,
      note: 'If this longtask is fully eliminated from critical path',
    };
  }
  // Prefer largest script wall if bigger than longtask as single ROI
  const candScript = largestJsByDur && posCriticalLeaves.has((largestJsByDur.name || '').split('/').pop().split('?')[0])
    ? largestJsByDur
    : runtime.scripts.find((s) => /pos-register\.js/.test(s.name));
  if (candScript && (!oneShot || candScript.duration > (oneShot.wall_ms || 0))) {
    const mapped = mapScriptToModule(candScript.full || candScript.name);
    oneShot = {
      target: 'Largest POS JS download+compile on waterfall',
      file: mapped.file,
      class: mapped.cls,
      function: mapped.fn + ' (parse/compile/eval cost in Resource Timing duration)',
      lines: mapped.lines,
      wall_ms: candScript.duration,
      self_ms: candScript.duration,
      calls: 1,
      percentage: afterRsWindow ? Math.round((candScript.duration / afterRsWindow) * 1000) / 10 : null,
      before_after_rs_ms: afterRsWindow,
      expected_after_rs_ms: Math.max(0, Math.round((afterRsWindow - candScript.duration * 0.7) * 10) / 10),
      gain_ms: Math.round(candScript.duration * 0.7 * 10) / 10,
      note: 'Optimistic: remove ~70% of this script from critical path (split/defer/cache hit). Full elimination not always possible.',
    };
  }
  // If DCL−responseStart dominates and post-DCL is tiny, ROI is script waterfall until DCL
  const postDcl = Math.max(0, afterRsWindow - (runtime.relative_to_responseStart.to_dcl || 0));

  const answers = {
    largest_sync_task: largestLt || largestCpu,
    largest_async_task: largestJsByDur,
    largest_js_file: largestJsByBytes,
    largest_parse: {
      name: 'HTML→domInteractive',
      wall_ms: runtime.relative_to_responseStart.to_domInteractive,
    },
    largest_execute: largestCpu,
    largest_layout: runtime.css.slice().sort((a, b) => b.duration - a.duration)[0] || null,
    largest_paint: { fcp: runtime.paints['first-contentful-paint'], lcp: runtime.lcp },
    largest_idle_gap: runtime.largestIdle,
    largest_promise_chain: {
      note: 'pos-register.js loadSession/fetchJson chains post-ready; scan-ready itself is sync IIFE end@1402',
      file: 'public/assets/pos/js/pos-register.js',
      lines: '1400–1402',
    },
    largest_event_loop_block: largestLt,
    largest_render_block: largestLt,
    largest_module_init: runtime.scripts.find((s) => /pos-module\.js/.test(s.name)) || null,
    largest_pos_service_init: runtime.scripts.find((s) => /pos-register\.js/.test(s.name)) || null,
    largest_barcode_init: {
      file: 'public/assets/pos/js/pos-register.js',
      fn: 'bindEvents barcode keydown + focus handlers',
      lines: '1156–1160, 1380–1384',
      note: 'Embedded in register IIFE; not a separate network task',
    },
    largest_auth_init: runtime.scripts.find((s) => /pos-auth-lock|pos-biometric/.test(s.name)) || null,
    largest_unnecessary_init: runtime.scripts.find((s) => /pos-register-ops\.js|pos-supervisor|pos-offline-sync/.test(s.name)) || null,
  };

  const flame = {
    root: 'responseStart → Scan Ready',
    wall_ms: afterRsWindow,
    children: [
      { name: 'HTML download', wall_ms: runtime.nav.html_download_ms },
      {
        name: 'Parse → DCL',
        wall_ms: runtime.relative_to_responseStart.to_dcl,
        children: runtime.scripts.slice(0, 12).map((s) => ({
          name: (s.name || '').split('/').pop(),
          wall_ms: s.duration,
          start_after_rs: s.start_after_rs,
        })),
      },
      { name: 'Post-DCL → Scan Ready', wall_ms: postDcl },
      {
        name: 'Longtasks after responseStart',
        wall_ms: (runtime.longtasks || []).reduce((a, t) => a + t.dur, 0),
        children: (runtime.longtasks || []).slice(0, 8).map((t) => ({ name: 'longtask', wall_ms: t.dur })),
      },
    ],
  };

  const report = {
    phase: 'PH',
    mode: 'READ_ONLY_POS_RUNTIME_AFTER_RESPONSESTART',
    generatedAt: new Date().toISOString(),
    target: REGISTER,
    sw: runtime.sw,
    scan,
    scan_wall_goto_ms: scanWall,
    responseStart_ms: rs,
    scan_ready_nav_ms: scanReadyNav,
    after_responseStart_to_scan_ready_ms: afterRsWindow,
    abs: {
      responseStart: rs,
      dcl: runtime.nav.dcl,
      load: runtime.nav.load,
      scan_ready_est: scanReadyNav,
    },
    timeline,
    flame,
    top30,
    critical_path: {
      phases_ms_after_rs: timeline,
      script_waterfall_end_after_rs: scriptWaterfallAfterRs,
      post_dcl_to_scan_ms: postDcl,
      pos_scripts_serial_duration_sum: serialScriptSum,
      dominant:
        (runtime.relative_to_responseStart.to_dcl || 0) >= postDcl
          ? 'JS/CSS waterfall + parse through DCL'
          : 'Post-DCL POS init',
    },
    answers,
    one_optimization_roi: oneShot,
    profiler_top_self: topSelf.slice(0, 20),
    coverage_pos: cov.slice(0, 15),
    runtime,
    enterprise:
      scan.ok && afterRsWindow > 0 && oneShot
        ? 'PASS — runtime after responseStart profiled; single-task ROI identified'
        : 'FAIL — incomplete profile or scan not ready',
  };

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const out = path.join(OUT_DIR, `phase-ph-pos-runtime-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-ph-pos-runtime-latest.json'), JSON.stringify(report, null, 2));

  console.log(
    JSON.stringify(
      {
        enterprise: report.enterprise,
        responseStart: rs,
        dcl: runtime.nav.dcl,
        scan_ready_nav: scanReadyNav,
        after_rs_to_scan: afterRsWindow,
        critical_path_dominant: report.critical_path.dominant,
        top10: top30.slice(0, 10).map((t) => ({
          rank: t.rank,
          name: t.name,
          wall: t.wall_ms,
          pct: t.percentage_of_after_rs,
        })),
        one_optimization_roi: oneShot,
        largest_longtask: largestLt,
        largest_js: largestJsByDur && {
          name: largestJsByDur.name,
          dur: largestJsByDur.duration,
          bytes: largestJsByDur.decodedBodySize || largestJsByDur.transferSize,
        },
        sw: runtime.sw,
      },
      null,
      2
    )
  );

  await context.close();
  if (!report.enterprise.startsWith('PASS')) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
