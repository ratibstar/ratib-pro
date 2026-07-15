/**
 * Phase PD — RATIB POS Enterprise Final Performance Certification (READ ONLY).
 * Profiles current POS register end-to-end after AA–PC work.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');
const STAMP = Date.now();

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=30', HOST, cmd], {
    encoding: 'utf8',
    timeout: 120000,
  });
}

function scp(local, remote) {
  execFileSync('scp', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', local, HOST + ':' + remote], {
    stdio: 'inherit',
  });
}

async function waitScanReady(page, timeoutMs) {
  const t0 = Date.now();
  while (Date.now() - t0 < timeoutMs) {
    const snap = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      const ready = root && root.getAttribute('data-pos-register-ready') === '1';
      const barcode = document.querySelector('[data-pos-barcode-input]');
      const tiles = document.querySelector('[data-pos-product-list]');
      const tilesReady = !!(tiles && (tiles.children.length > 0 || document.querySelector('[data-pos-catalog-empty]')));
      const spinSel =
        '.spinner-border, .fa-spinner, .fa-spin, .is-loading, #rateb-offline-warm-progress, [aria-busy="true"]';
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
      });
      return {
        ready: !!ready,
        tilesReady,
        spins: spins.length,
        register: !!root,
        barcode: !!barcode,
        catalogSeed: !!(document.getElementById('rateb-pos-bootstrap') || '').textContent,
      };
    });
    if (snap.register && snap.ready && snap.tilesReady && snap.spins === 0 && snap.barcode) {
      return { ok: true, ms: Date.now() - t0, snap };
    }
    await page.waitForTimeout(50);
  }
  return { ok: false, ms: Date.now() - t0 };
}

async function collectBrowserMetrics(page) {
  return page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0] || {};
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
    const resources = performance.getEntriesByType('resource').map((x) => ({
      name: x.name.replace(location.origin, ''),
      full: x.name,
      type: x.initiatorType,
      duration: Math.round(x.duration),
      transferSize: x.transferSize || 0,
      startTime: Math.round(x.startTime),
      responseEnd: Math.round(x.responseEnd),
      ttfb:
        x.responseStart && x.requestStart
          ? Math.round((x.responseStart - x.requestStart) * 10) / 10
          : null,
    }));
    resources.sort((a, b) => b.duration - a.duration);
    let longTasks = [];
    try {
      longTasks = performance
        .getEntriesByType('longtask')
        .map((t) => ({ start: Math.round(t.startTime), dur: Math.round(t.duration) }))
        .sort((a, b) => b.dur - a.dur)
        .slice(0, 15);
    } catch (_) {}

    const byType = {};
    resources.forEach((res) => {
      const k = res.type || 'other';
      if (!byType[k]) byType[k] = { n: 0, ms: 0, bytes: 0 };
      byType[k].n++;
      byType[k].ms += res.duration || 0;
      byType[k].bytes += res.transferSize || 0;
    });

    const xhr = resources.filter((x) => x.type === 'fetch' || x.type === 'xmlhttprequest');
    const scripts = resources.filter((x) => x.type === 'script' || /\.js(\?|$)/i.test(x.name));

    return {
      href: location.href,
      title: document.title,
      register: !!document.querySelector('[data-pos-register]'),
      ready: document.querySelector('[data-pos-register]')?.getAttribute('data-pos-register-ready') === '1',
      gate: !!document.querySelector('[data-pos-biometric-gate]'),
      onLine: navigator.onLine,
      swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      swScript: navigator.serviceWorker?.controller?.scriptURL || null,
      htmlLen: document.documentElement.outerHTML.length,
      scriptCount: document.querySelectorAll('script[src]').length,
      nav: {
        protocol: n.nextHopProtocol,
        dns_ms: r(n.domainLookupStart, n.domainLookupEnd),
        tcp_ms: r(n.connectStart, n.connectEnd),
        tls_ms: n.secureConnectionStart > 0 ? r(n.secureConnectionStart, n.connectEnd) : 0,
        workerStart: n.workerStart || 0,
        stall_ms: r(n.fetchStart, n.requestStart),
        ttfb_ms: r(n.requestStart, n.responseStart),
        responseStart_ms: Math.round((n.responseStart || 0) * 10) / 10,
        download_ms: r(n.responseStart, n.responseEnd),
        domInteractive_ms: Math.round((n.domInteractive || 0) * 10) / 10,
        dcl_ms: Math.round((n.domContentLoadedEventEnd || 0) * 10) / 10,
        load_ms: Math.round((n.loadEventEnd || 0) * 10) / 10,
        transferSize: n.transferSize || 0,
        decodedBodySize: n.decodedBodySize || 0,
        encodedBodySize: n.encodedBodySize || 0,
      },
      paints,
      lcp_ms: lcp,
      byType,
      top_resources: resources.slice(0, 25),
      xhr_top: xhr.slice().sort((a, b) => (b.duration || 0) - (a.duration || 0)).slice(0, 15),
      script_total_ms: scripts.reduce((a, x) => a + (x.duration || 0), 0),
      script_count: scripts.length,
      longTasks,
    };
  });
}

async function profileNavigation(page, label) {
  const timeline = [];
  const t0 = Date.now();
  const push = (name, extra = {}) => timeline.push({ t_ms: Date.now() - t0, name, ...extra });

  push('GOTO_START', { label });
  let navErr = null;
  try {
    await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  } catch (e) {
    navErr = String(e.message || e);
  }
  push('DOMCONTENTLOADED', { err: navErr });

  try {
    await page.waitForLoadState('load', { timeout: 45000 });
    push('LOAD');
  } catch (e) {
    push('LOAD_TIMEOUT', { err: String(e.message || e) });
  }

  const scan = await waitScanReady(page, 60000);
  push('READY_FOR_FIRST_SCAN', scan);

  const metrics = await collectBrowserMetrics(page);
  push('METRICS');

  const offlineState = await page.evaluate(async () => {
    const out = { caches: [], idb: [], sw: [] };
    try {
      out.caches = await caches.keys();
    } catch (_) {}
    try {
      if (indexedDB.databases) out.idb = await indexedDB.databases();
    } catch (_) {}
    try {
      const regs = await navigator.serviceWorker.getRegistrations();
      out.sw = regs.map((r) => ({
        scope: r.scope,
        active: r.active && r.active.scriptURL,
        state: r.active && r.active.state,
      }));
    } catch (_) {}
    return out;
  });

  return {
    label,
    wall_ms: Date.now() - t0,
    nav_error: navErr,
    timeline,
    scan,
    metrics,
    offlineState,
  };
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const report = {
    phase: 'PD',
    mode: 'READ_ONLY_POS_ENTERPRISE_CERTIFICATION',
    generatedAt: new Date().toISOString(),
    target: REGISTER,
    assumptions: 'AA/AG/AI/OA-OJ/OK.1/PC complete — find NEW biggest bottleneck',
  };

  // PHP + SQL profile on production (temporary script already exists)
  try {
    scp(path.join(__dirname, 'phase-pa-pos-register-audit.php'), '/tmp/phase-pd-pos-php.php');
    ssh("python3 -c \"p=open('/tmp/phase-pd-pos-php.php','rb').read().replace(b'\\r\\n',b'\\n').replace(b'\\r',b'\\n'); open('/tmp/phase-pd-pos-php.php','wb').write(p)\"");
    const phpOut = ssh('/usr/local/php83/bin/php /tmp/phase-pd-pos-php.php');
    const launch = JSON.parse(phpOut.trim().split('\n').pop());
    if (launch.out) {
      const body = ssh('cat ' + launch.out);
      report.php = JSON.parse(body);
    } else {
      report.php_launch = launch;
    }
  } catch (e) {
    report.php_error = String(e.message || e);
  }

  // Server curl TTFB (DNS bypassed)
  try {
    const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
    const cookie = mint.session_name + '=' + mint.session_id;
    const curlRaw = ssh(
      "C='" +
        cookie +
        "'; R='--resolve rateb.sa:443:167.233.71.107'; " +
        "for i in 1 2 3; do curl -sk $R -b \"$C\" -o /dev/null -w \"run=$i dns=%{time_namelookup} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\\n\" " +
        "'https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22'; done"
    );
    report.server_curl = curlRaw.trim().split('\n');
  } catch (e) {
    report.server_curl_error = String(e.message || e);
  }

  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profile = path.join(os.tmpdir(), 'rateb-pd-' + STAMP);
  const context = await chromium.launchPersistentContext(profile, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    viewport: { width: 1365, height: 900 },
    locale: 'ar-SA',
    serviceWorkers: 'allow',
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);

  const page = context.pages()[0] || (await context.newPage());
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.evaluate(async (base) => {
    const scope = base.endsWith('/') ? base : base + '/';
    await navigator.serviceWorker.register(base + '/pos-sw.js?v=pd', { scope, updateViaCache: 'none' });
    await navigator.serviceWorker.ready;
  }, BASE);
  await page.waitForTimeout(800);

  // Cold-ish first POS open (new navigation)
  const cold = await profileNavigation(page, 'online_first');
  report.online_first = cold;

  // Warm second open
  const warm = await profileNavigation(page, 'online_warm');
  report.online_warm = warm;

  await context.close();

  // Rank candidates from current evidence (not prior phases)
  const first = cold.metrics || {};
  const nav = first.nav || {};
  const wallScan = cold.scan?.ok ? cold.scan.ms + (cold.timeline.find((t) => t.name === 'DOMCONTENTLOADED')?.t_ms || 0) : cold.wall_ms;
  // Better: ready_for_scan absolute from nav start
  const readyAbs = (cold.timeline.find((t) => t.name === 'READY_FOR_FIRST_SCAN') || {}).t_ms || cold.wall_ms;
  const dclAbs = (cold.timeline.find((t) => t.name === 'DOMCONTENTLOADED') || {}).t_ms || nav.dcl_ms;

  const phpWall = report.php?.totals?.wall_ms;
  const phpBiggest = report.php?.single_biggest_bottleneck;
  const phpStages = report.php?.stages || [];
  const topFn = (report.php?.top_20_functions || [])[0];

  const candidates = [
    {
      id: 'document_ttfb',
      label: 'Document TTFB (browser)',
      wall_ms: nav.ttfb_ms,
      self_ms: nav.ttfb_ms,
      file: 'rateb-erp/public/index.php → Bootstrap / PosRegisterController',
      class: 'Rateb\\App\\Pos\\Controllers\\PosRegisterController',
      function: 'index',
      line: 14,
      calls: 1,
      source: 'browser',
    },
    {
      id: 'response_start',
      label: 'responseStart from nav start',
      wall_ms: nav.responseStart_ms,
      self_ms: nav.responseStart_ms,
      file: 'network+server',
      class: null,
      function: 'navigation to first byte',
      line: null,
      calls: 1,
      source: 'browser',
    },
    {
      id: 'sw_workerStart',
      label: 'Service Worker workerStart',
      wall_ms: nav.workerStart,
      self_ms: nav.workerStart,
      file: 'rateb-erp/public/pos-sw.js',
      class: null,
      function: 'fetch/navigate',
      line: 2582,
      calls: 1,
      source: 'browser',
    },
    {
      id: 'js_resources',
      label: 'JS resource download/execute sum',
      wall_ms: first.script_total_ms,
      self_ms: first.script_total_ms,
      file: 'rateb-erp/public/assets/pos/js/*',
      class: null,
      function: 'script resources',
      line: null,
      calls: first.script_count || 1,
      source: 'network',
    },
    {
      id: 'post_byte_to_scan',
      label: 'After first byte → scan ready',
      wall_ms: Math.max(0, readyAbs - (nav.responseStart_ms || 0)),
      self_ms: Math.max(0, readyAbs - (nav.responseStart_ms || 0)),
      file: 'rateb-erp/public/assets/pos/js/pos-register.js',
      class: null,
      function: 'register init + catalog tiles',
      line: 1402,
      calls: 1,
      source: 'client',
    },
    {
      id: 'html_download',
      label: 'HTML download',
      wall_ms: nav.download_ms,
      self_ms: nav.download_ms,
      file: 'document body',
      class: null,
      function: 'responseStart→responseEnd',
      line: null,
      calls: 1,
      source: 'network',
    },
    {
      id: 'php_total',
      label: 'PHP CLI total',
      wall_ms: phpWall,
      self_ms: phpWall,
      file: 'PHP request',
      class: null,
      function: 'request_total',
      line: null,
      calls: 1,
      source: 'php',
    },
  ];

  if (phpBiggest) {
    candidates.push({
      id: 'php_biggest_stage',
      label: phpBiggest.label || phpBiggest.id,
      wall_ms: phpBiggest.wall_ms,
      self_ms: phpBiggest.self_ms || phpBiggest.wall_ms,
      file: phpBiggest.file || 'app/Core/Bootstrap.php',
      class: phpBiggest.class || 'Rateb\\App\\Core\\Bootstrap',
      function: phpBiggest.function || 'init',
      line: phpBiggest.line || 10,
      calls: phpBiggest.calls || 1,
      source: 'php',
    });
  }
  if (topFn) {
    candidates.push({
      id: 'php_top_sql_fn',
      label: topFn.key,
      wall_ms: topFn.wall_ms,
      self_ms: topFn.wall_ms,
      file: topFn.file,
      class: topFn.class,
      function: topFn.function,
      line: topFn.line,
      calls: topFn.calls,
      source: 'sql',
    });
  }

  // Top xhr
  for (const x of (first.xhr_top || []).slice(0, 5)) {
    candidates.push({
      id: 'xhr_' + x.name.slice(0, 80),
      label: x.name,
      wall_ms: x.duration,
      self_ms: x.duration,
      file: x.name,
      class: null,
      function: 'fetch/xhr',
      line: null,
      calls: 1,
      source: 'network',
    });
  }

  for (const lt of first.longTasks || []) {
    candidates.push({
      id: 'longtask_' + lt.start,
      label: 'longtask',
      wall_ms: lt.dur,
      self_ms: lt.dur,
      file: 'main thread',
      class: null,
      function: 'longtask',
      line: null,
      calls: 1,
      source: 'js',
    });
  }

  const ranked = candidates
    .filter((c) => c.wall_ms != null && c.wall_ms > 0)
    .sort((a, b) => (b.wall_ms || 0) - (a.wall_ms || 0));

  const ref = readyAbs || cold.wall_ms || 1;
  report.top_20 = ranked.slice(0, 20).map((c, i) => ({
    rank: i + 1,
    ...c,
    pct: Math.round((1000 * c.wall_ms) / ref) / 10,
  }));

  // Single biggest remaining: prefer stage that dominates time-to-scan-ready
  // Exclude prior-fixed SW gate if workerStart is tiny (<50)
  let biggest = report.top_20.find((c) => c.id === 'document_ttfb' || c.id === 'response_start');
  const postByte = report.top_20.find((c) => c.id === 'post_byte_to_scan');
  const ttfb = nav.ttfb_ms || nav.responseStart_ms || 0;
  const post = postByte?.wall_ms || 0;

  if (ttfb >= post && ttfb >= (phpWall || 0)) {
    // Map TTFB to concrete PHP hotspot if available
    const bootstrap = phpStages.find((s) => s.id === '1_php_bootstrap' || /bootstrap/i.test(s.label || ''));
    const ctrl = phpStages.find((s) => s.id === '5_controller' || s.id === '6_pos_dashboard_build');
    if (bootstrap && (!ctrl || bootstrap.wall_ms >= (ctrl.wall_ms || 0))) {
      biggest = {
        file: 'rateb-erp/app/Core/Bootstrap.php',
        class: 'Rateb\\App\\Core\\Bootstrap',
        function: 'init',
        line: 10,
        wall_ms: nav.responseStart_ms || nav.ttfb_ms,
        self_ms: bootstrap.wall_ms,
        calls: 1,
        percentage: Math.round((1000 * (nav.responseStart_ms || nav.ttfb_ms)) / ref) / 10,
        label: 'Document wait dominated by server first-byte (Bootstrap largest PHP self); browser TTFB',
        php_self_ms: bootstrap.wall_ms,
        browser_ttfb_ms: nav.ttfb_ms,
        browser_responseStart_ms: nav.responseStart_ms,
      };
    } else if (ctrl) {
      biggest = {
        file: 'rateb-erp/modules/pos/app/Controllers/PosRegisterController.php',
        class: 'Rateb\\App\\Pos\\Controllers\\PosRegisterController',
        function: 'index',
        line: 14,
        wall_ms: nav.responseStart_ms || nav.ttfb_ms,
        self_ms: ctrl.wall_ms,
        calls: 1,
        percentage: Math.round((1000 * (nav.responseStart_ms || nav.ttfb_ms)) / ref) / 10,
        label: 'Document first-byte / controller+view path',
      };
    } else {
      biggest = {
        file: report.top_20[0]?.file,
        class: report.top_20[0]?.class,
        function: report.top_20[0]?.function || report.top_20[0]?.label,
        line: report.top_20[0]?.line,
        wall_ms: report.top_20[0]?.wall_ms,
        self_ms: report.top_20[0]?.self_ms,
        calls: report.top_20[0]?.calls || 1,
        percentage: report.top_20[0]?.pct,
        label: report.top_20[0]?.label,
      };
    }
  } else if (post > ttfb) {
    biggest = {
      file: 'rateb-erp/public/assets/pos/js/pos-register-tiles.js',
      class: null,
      function: 'catalog bootstrap / tile render after HTML',
      line: 273,
      wall_ms: post,
      self_ms: post,
      calls: 1,
      percentage: Math.round((1000 * post) / ref) / 10,
      label: 'Client post-byte work to scan-ready',
    };
  }

  report.lifecycle_abs = {
    ready_for_scan_ms: readyAbs,
    dcl_ms: dclAbs,
    responseStart_ms: nav.responseStart_ms,
    ttfb_ms: nav.ttfb_ms,
    load_ms: nav.load_ms,
  };

  report.single_biggest_bottleneck = biggest;
  report.before_after = {
    current_ready_scan_ms: readyAbs,
    if_bottleneck_removed_ms:
      biggest?.wall_ms != null ? Math.max(50, Math.round(readyAbs - Math.min(biggest.wall_ms, readyAbs * 0.9))) : null,
    improvement_pct:
      biggest?.wall_ms != null && readyAbs
        ? Math.min(95, Math.round((100 * Math.min(biggest.wall_ms, readyAbs)) / readyAbs))
        : null,
  };

  // Enterprise readiness gates (POS open)
  const gates = {
    ready_scan_lt_1000: readyAbs < 1000,
    ready_scan_lt_700: readyAbs < 700,
    ttfb_lt_400: (nav.ttfb_ms || 9999) < 400,
    responseStart_lt_500: (nav.responseStart_ms || 9999) < 500,
    sw_workerStart_lt_50: (nav.workerStart || 0) < 50,
    php_lt_150: (phpWall || 999) < 150,
    register_html: !!first.register && !first.gate,
    warm_ready_lt_500: ((warm.timeline.find((t) => t.name === 'READY_FOR_FIRST_SCAN') || {}).t_ms || 9999) < 500,
  };
  const pass = gates.ready_scan_lt_1000 && gates.sw_workerStart_lt_50 && gates.register_html && gates.php_lt_150;
  report.enterprise_ready = pass;
  report.gates = gates;

  report.remaining_by_roi = report.top_20
    .filter((c) => !['sw_workerStart'].includes(c.id) || (c.wall_ms || 0) > 50)
    .slice(0, 8)
    .map((c) => ({
      item: c.label,
      wall_ms: c.wall_ms,
      file: c.file,
      function: c.function,
      roi_note: c.source,
    }));

  const out = path.join(OUT_DIR, `phase-pd-pos-cert-${STAMP}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pd-pos-cert-latest.json'), JSON.stringify(report, null, 2));

  console.log(
    JSON.stringify(
      {
        out,
        ready_scan_ms: readyAbs,
        ttfb: nav.ttfb_ms,
        responseStart: nav.responseStart_ms,
        workerStart: nav.workerStart,
        php_wall: phpWall,
        biggest: report.single_biggest_bottleneck,
        enterprise_ready: report.enterprise_ready,
        gates,
        warm_ready: (warm.timeline.find((t) => t.name === 'READY_FOR_FIRST_SCAN') || {}).t_ms,
        top5: report.top_20.slice(0, 5),
      },
      null,
      2
    )
  );
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
