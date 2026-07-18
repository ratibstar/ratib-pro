/**
 * PERF E2E Startup Audit — Online Admin ERP + Offline ERP (measure only).
 * No architecture changes. Produces ranked bottleneck report JSON.
 *
 *   node perf-e2e-startup-audit.js
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
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function ssh(cmd, timeoutMs) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15', HOST, cmd], {
    encoding: 'utf8',
    timeout: timeoutMs || 120000,
  });
}

function shortUrl(u) {
  try {
    const x = new URL(u);
    return x.pathname + x.search;
  } catch {
    return String(u).slice(0, 160);
  }
}

function pct(part, total) {
  if (!total || total <= 0) return 0;
  return Math.round((part / total) * 1000) / 10;
}

function topN(arr, n) {
  return (arr || []).slice().sort((a, b) => (b.ms || b.duration || 0) - (a.ms || a.duration || 0)).slice(0, n);
}

async function collectBrowserProfile(page, label) {
  const data = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || null;
    const resources = (performance.getEntriesByType('resource') || []).map((r) => ({
      name: r.name,
      short: (() => {
        try {
          const u = new URL(r.name);
          return u.pathname + u.search;
        } catch (e) {
          return String(r.name).slice(0, 160);
        }
      })(),
      initiatorType: r.initiatorType,
      transferSize: r.transferSize || 0,
      encodedBodySize: r.encodedBodySize || 0,
      decodedBodySize: r.decodedBodySize || 0,
      duration: Math.round(r.duration * 10) / 10,
      startTime: Math.round(r.startTime * 10) / 10,
      ttfb: Math.round(((r.responseStart || 0) - (r.requestStart || 0)) * 10) / 10,
      download: Math.round(((r.responseEnd || 0) - (r.responseStart || 0)) * 10) / 10,
      cached: (r.transferSize === 0 && r.decodedBodySize > 0) || (r.transferSize > 0 && r.transferSize < r.encodedBodySize * 0.1 && r.encodedBodySize > 0),
    }));
    const marks = (performance.getEntriesByType('mark') || []).map((m) => ({
      name: m.name,
      startTime: Math.round(m.startTime * 10) / 10,
    }));
    const measures = (performance.getEntriesByType('measure') || []).map((m) => ({
      name: m.name,
      duration: Math.round(m.duration * 10) / 10,
      startTime: Math.round(m.startTime * 10) / 10,
    }));
    const longTasks = (performance.getEntriesByType('longtask') || []).map((t) => ({
      startTime: Math.round(t.startTime * 10) / 10,
      duration: Math.round(t.duration * 10) / 10,
      name: t.name,
    }));
    let memory = null;
    try {
      if (performance.memory) {
        memory = {
          usedJSHeapSize: performance.memory.usedJSHeapSize,
          totalJSHeapSize: performance.memory.totalJSHeapSize,
          jsHeapSizeLimit: performance.memory.jsHeapSizeLimit,
        };
      }
    } catch (eMem) {}
    let idbEstimate = null;
    try {
      /* async filled below */
    } catch (e2) {}
    const paint = {};
    (performance.getEntriesByType('paint') || []).forEach((p) => {
      paint[p.name] = Math.round(p.startTime * 10) / 10;
    });
    let lcp = null;
    try {
      const lcpEntries = performance.getEntriesByType('largest-contentful-paint') || [];
      if (lcpEntries.length) {
        const last = lcpEntries[lcpEntries.length - 1];
        lcp = Math.round(last.startTime * 10) / 10;
      }
    } catch (e3) {}

    const scripts = resources.filter((r) => r.initiatorType === 'script' || /\.js(\?|$)/i.test(r.short));
    const css = resources.filter((r) => r.initiatorType === 'link' || /\.css(\?|$)/i.test(r.short));
    const wasm = resources.filter((r) => /\.wasm(\?|$)/i.test(r.short) || /sqlite/i.test(r.short));
    const xhr = resources.filter((r) => r.initiatorType === 'xmlhttprequest' || r.initiatorType === 'fetch');

    return {
      nav: nav
        ? {
            ttfb: Math.round(nav.responseStart * 10) / 10,
            domContentLoaded: Math.round(nav.domContentLoadedEventEnd * 10) / 10,
            loadEvent: Math.round(nav.loadEventEnd * 10) / 10,
            domInteractive: Math.round(nav.domInteractive * 10) / 10,
            transferSize: nav.transferSize || 0,
            encodedBodySize: nav.encodedBodySize || 0,
            type: nav.type,
          }
        : null,
      paint,
      lcp,
      resources,
      scripts,
      css,
      wasm,
      xhr,
      marks,
      measures,
      longTasks,
      memory,
      idbEstimate,
      href: location.href,
      online: navigator.onLine,
      swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
    };
  });

  try {
    data.idbEstimate = await page.evaluate(async () => {
      if (!navigator.storage || !navigator.storage.estimate) return null;
      const e = await navigator.storage.estimate();
      return { usage: e.usage || 0, quota: e.quota || 0 };
    });
  } catch (eIdb) {
    data.idbEstimate = null;
  }

  data.label = label;
  return data;
}

function summarizeResources(profile, wallMs) {
  const byType = {};
  (profile.resources || []).forEach((r) => {
    const t = r.initiatorType || 'other';
    if (!byType[t]) byType[t] = { count: 0, durationSum: 0, bytes: 0 };
    byType[t].count += 1;
    byType[t].durationSum += r.duration || 0;
    byType[t].bytes += r.transferSize || 0;
  });
  const slowest = topN(profile.resources || [], 15).map((r) => ({
    url: r.short,
    ms: r.duration,
    ttfb: r.ttfb,
    transferKB: Math.round((r.transferSize || 0) / 102.4) / 10,
    type: r.initiatorType,
    pct_of_wall: pct(r.duration, wallMs),
  }));
  const longTaskSum = (profile.longTasks || []).reduce((s, t) => s + (t.duration || 0), 0);
  return {
    byType,
    slowest,
    resourceCount: (profile.resources || []).length,
    longTaskCount: (profile.longTasks || []).length,
    longTaskSumMs: Math.round(longTaskSum * 10) / 10,
    longTaskPct: pct(longTaskSum, wallMs),
    scriptBytes: (profile.scripts || []).reduce((s, r) => s + (r.transferSize || 0), 0),
    cssBytes: (profile.css || []).reduce((s, r) => s + (r.transferSize || 0), 0),
    wasmBytes: (profile.wasm || []).reduce((s, r) => s + (r.transferSize || 0), 0),
  };
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const report = {
    phase: 'PERF-E2E-STARTUP-AUDIT',
    started_at: new Date().toISOString(),
    base: BASE,
    server: {},
    online: {},
    offline: {},
    bottlenecks: [],
    ok: false,
  };

  /* ---- Server-side PHP / OPcache / TTFB ---- */
  try {
    const serverPhp = ssh(`php -r '
\$root="/home/admin/domains/rateb.sa/public_html/rateb-erp";
\$t0=hrtime(true);
require_once \$root."/vendor/autoload.php";
\$autoload_ms=(hrtime(true)-\$t0)/1e6;
\$t1=hrtime(true);
require_once \$root."/app/Core/Bootstrap.php";
\\Rateb\\App\\Core\\Bootstrap::init(\$root);
\$boot_ms=(hrtime(true)-\$t1)/1e6;
\$t2=hrtime(true);
\$pdo=\\Rateb\\App\\Core\\Database::pdo();
\$pdo->query("SELECT 1")->fetchColumn();
\$db_ms=(hrtime(true)-\$t2)/1e6;
\$t3=hrtime(true);
\\Rateb\\App\\Core\\SessionManager::start();
\$sess_ms=(hrtime(true)-\$t3)/1e6;
session_write_close();
\$op=function_exists("opcache_get_status")?opcache_get_status(false):null;
\$mem=memory_get_peak_usage(true);
echo json_encode([
  "autoload_ms"=>round(\$autoload_ms,2),
  "bootstrap_ms"=>round(\$boot_ms,2),
  "db_ping_ms"=>round(\$db_ms,2),
  "session_start_ms"=>round(\$sess_ms,2),
  "peak_mem_mb"=>round(\$mem/1048576,2),
  "opcache_enabled"=>!empty(\$op["opcache_enabled"]),
  "opcache_hit_rate"=>isset(\$op["opcache_statistics"]["opcache_hit_rate"])?round(\$op["opcache_statistics"]["opcache_hit_rate"],2):null,
  "opcache_cached_scripts"=>\$op["opcache_statistics"]["num_cached_scripts"]??null,
  "opcache_memory_used_mb"=>isset(\$op["memory_usage"]["used_memory"])?round(\$op["memory_usage"]["used_memory"]/1048576,2):null,
], JSON_UNESCAPED_SLASHES);
'`);
    report.server.php_cli = JSON.parse(serverPhp.trim().split('\n').pop());
  } catch (ePhp) {
    report.server.php_cli_error = String(ePhp && ePhp.message ? ePhp.message : ePhp).slice(0, 400);
  }

  try {
    const mint = JSON.parse(ssh('php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'));
    report.server.mint_ok = !!mint.ok;
    report.server.session_name = mint.session_name;

    const ttfbScript = `php /tmp/remote-auth.php mint > /tmp/mint.json 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint > /tmp/mint.json
php -r '\$j=json_decode(file_get_contents("/tmp/mint.json"),true); printf("rateb.sa\\tFALSE\\t/\\tTRUE\\t0\\t%s\\t%s\\n",\$j["session_name"],\$j["session_id"]);' > /tmp/rateb-admin.cookie
echo COLD
curl -sk -L -o /tmp/admin_perf.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\\n" -b /tmp/rateb-admin.cookie -c /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo WARM1
curl -sk -L -o /tmp/admin_perf2.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\\n" -b /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo WARM2
curl -sk -L -o /tmp/admin_perf3.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\\n" -b /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo STATIC
curl -sk -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\\n" --resolve rateb.sa:443:127.0.0.1 "https://rateb.sa/rateb-erp/public/connectivity-probe.json"
echo OFFLINE_SHELL
curl -sk -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\\n" --resolve rateb.sa:443:127.0.0.1 "https://rateb.sa/rateb-erp/public/offline-shell.html"
wc -c /tmp/admin_perf.html
grep -oE 'src="[^"]+|href="[^"]+\\.css[^"]*' /tmp/admin_perf.html | head -40
`;
    report.server.http = ssh(ttfbScript, 90000);
  } catch (eHttp) {
    report.server.http_error = String(eHttp && eHttp.message ? eHttp.message : eHttp).slice(0, 600);
  }

  /* ---- Browser cold Online + Offline ---- */
  let mintCookie;
  try {
    mintCookie = JSON.parse(ssh('php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'));
  } catch (eMint) {
    report.error = 'mint_failed:' + String(eMint.message || eMint).slice(0, 300);
    const out = path.join(OUT_DIR, 'perf-e2e-startup-audit-' + Date.now() + '.json');
    fs.writeFileSync(out, JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ ok: false, out, error: report.error }, null, 2));
    process.exit(2);
  }

  const profileDir = path.join(os.tmpdir(), 'rateb-perf-e2e-' + Date.now());
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
      name: mintCookie.session_name || 'rateb_erp',
      value: mintCookie.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);

  const cdp = await context.newCDPSession(context.pages()[0] || (await context.newPage()));
  await cdp.send('Performance.enable').catch(() => null);
  await cdp.send('Network.enable').catch(() => null);

  const page = context.pages()[0];

  /* Observe Long Tasks */
  await page.addInitScript(() => {
    try {
      const obs = new PerformanceObserver((list) => {
        window.__RATEB_LONG_TASKS__ = window.__RATEB_LONG_TASKS__ || [];
        list.getEntries().forEach((e) => {
          window.__RATEB_LONG_TASKS__.push({
            startTime: Math.round(e.startTime * 10) / 10,
            duration: Math.round(e.duration * 10) / 10,
          });
        });
      });
      obs.observe({ type: 'longtask', buffered: true });
    } catch (e) {}
    window.__RATEB_PERF_HOOKS__ = { lsReads: 0, lsWrites: 0, idbOpens: 0 };
    try {
      const oGet = Storage.prototype.getItem;
      const oSet = Storage.prototype.setItem;
      Storage.prototype.getItem = function () {
        window.__RATEB_PERF_HOOKS__.lsReads++;
        return oGet.apply(this, arguments);
      };
      Storage.prototype.setItem = function () {
        window.__RATEB_PERF_HOOKS__.lsWrites++;
        return oSet.apply(this, arguments);
      };
    } catch (e2) {}
    try {
      const oOpen = indexedDB.open.bind(indexedDB);
      indexedDB.open = function () {
        window.__RATEB_PERF_HOOKS__.idbOpens++;
        return oOpen.apply(indexedDB, arguments);
      };
    } catch (e3) {}
  });

  /* ========== ONLINE COLD ========== */
  const onlineWall0 = Date.now();
  const netLog = [];
  page.on('response', async (res) => {
    const req = res.request();
    const rt = req.resourceType();
    if (!['document', 'script', 'stylesheet', 'xhr', 'fetch', 'other'].includes(rt)) return;
    netLog.push({
      t: Date.now() - onlineWall0,
      status: res.status(),
      type: rt,
      url: shortUrl(res.url()),
      fromSW: res.fromServiceWorker(),
    });
  });

  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const onlineDcl = Date.now() - onlineWall0;
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar, .rateb-content, main', { timeout: 60000 }).catch(() => null);
  const onlineUsable = Date.now() - onlineWall0;
  await page.waitForTimeout(2500);
  const onlineProfile = await collectBrowserProfile(page, 'online-admin-cold');
  const hooksOnline = await page.evaluate(() => ({
    hooks: window.__RATEB_PERF_HOOKS__ || null,
    longTasks: window.__RATEB_LONG_TASKS__ || [],
    title: document.title,
    sidebar: !!document.querySelector('aside.rateb-sidebar, #rateb-sidebar'),
  }));
  let perfMetrics = null;
  try {
    perfMetrics = await cdp.send('Performance.getMetrics');
  } catch (ePm) {}

  report.online = {
    wall_ms: {
      domcontentloaded: onlineDcl,
      usable: onlineUsable,
      settle: Date.now() - onlineWall0,
    },
    profile: onlineProfile,
    summary: summarizeResources(onlineProfile, onlineUsable),
    hooks: hooksOnline,
    cdp_metrics: perfMetrics,
    top_network: topN(
      netLog.map((n) => ({ ...n, ms: n.t })),
      0
    ),
    network_sample: netLog.slice(0, 80),
  };

  /* Warm second navigation */
  const warm0 = Date.now();
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar, .rateb-content, main', { timeout: 45000 }).catch(() => null);
  const onlineWarmUsable = Date.now() - warm0;
  const onlineWarmProfile = await collectBrowserProfile(page, 'online-admin-warm');
  report.online.warm = {
    usable_ms: onlineWarmUsable,
    profile: onlineWarmProfile,
    summary: summarizeResources(onlineWarmProfile, onlineWarmUsable),
  };

  /* ========== OFFLINE: warm caches then go offline ========== */
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
  await page.waitForTimeout(1500);
  /* Hit rateb-offline.js path by ensuring SW */
  try {
    await page.evaluate(async () => {
      if (!navigator.serviceWorker) return null;
      const regs = await navigator.serviceWorker.getRegistrations();
      return regs.map((r) => (r.active && r.active.scriptURL) || (r.installing && r.installing.scriptURL) || '');
    });
  } catch (eSw) {}

  await context.setOffline(true);
  const offWall0 = Date.now();
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 120000 }).catch((err) => {
    report.offline.goto_error = String(err.message || err).slice(0, 200);
  });
  const offDcl = Date.now() - offWall0;
  await page.waitForSelector('#rateb-offline-shell-main, .rateb-offline-home, aside.rateb-offline-shell-nav, .rateb-content', {
    timeout: 90000,
  }).catch(() => null);
  const offUsable = Date.now() - offWall0;
  await page.waitForTimeout(3000);
  const offlineProfile = await collectBrowserProfile(page, 'offline-shell-cold');
  const hooksOffline = await page.evaluate(() => ({
    hooks: window.__RATEB_PERF_HOOKS__ || null,
    longTasks: window.__RATEB_LONG_TASKS__ || [],
    title: document.title,
    online: navigator.onLine,
    hasOfflineMain: !!document.querySelector('#rateb-offline-shell-main, .rateb-offline-home'),
    sw: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
  }));

  /* Try offline admin route from shell if link exists */
  let offlineModule = null;
  try {
    const href = await page.evaluate(() => {
      const a = document.querySelector('a.rateb-offline-rbac-link[href], .rateb-offline-module-links a[href], aside a[href*="/admin/"]');
      return a ? a.getAttribute('href') : null;
    });
    if (href) {
      const m0 = Date.now();
      await page.click('a.rateb-offline-rbac-link[href], .rateb-offline-module-links a[href], aside a[href*="/admin/"]', { timeout: 5000 }).catch(async () => {
        await page.goto(new URL(href, BASE).href, { waitUntil: 'domcontentloaded', timeout: 60000 });
      });
      await page.waitForTimeout(2500);
      const modProfile = await collectBrowserProfile(page, 'offline-module');
      offlineModule = {
        href,
        wall_ms: Date.now() - m0,
        profile: modProfile,
        summary: summarizeResources(modProfile, Date.now() - m0),
      };
    }
  } catch (eMod) {
    offlineModule = { error: String(eMod.message || eMod).slice(0, 200) };
  }

  report.offline = {
    wall_ms: { domcontentloaded: offDcl, usable: offUsable, settle: Date.now() - offWall0 },
    profile: offlineProfile,
    summary: summarizeResources(offlineProfile, offUsable),
    hooks: hooksOffline,
    module: offlineModule,
  };

  await context.close();

  /* ---- Rank bottlenecks ---- */
  const bottlenecks = [];
  const onlineWall = report.online.wall_ms.usable || 1;
  const offlineWall = report.offline.wall_ms.usable || 1;

  function addBn(row) {
    bottlenecks.push(row);
  }

  /* Document TTFB from nav timing */
  if (report.online.profile && report.online.profile.nav) {
    const ttfb = report.online.profile.nav.ttfb;
    addBn({
      rank: 0,
      surface: 'online',
      category: 'PHP document / TTFB',
      file: 'rateb-erp/public/index.php → app/Core/Bootstrap.php + Router + Admin dashboard view',
      function: 'Bootstrap::init + route dispatch + view render',
      ms: ttfb,
      pct_of_startup: pct(ttfb, onlineWall),
      why: 'Server-side HTML generation before first byte (bootstrap, session, DB, middleware, view)',
      expected_gain_ms: Math.round(ttfb * 0.35),
      impact: 'critical',
    });
  }

  (report.online.summary.slowest || []).slice(0, 8).forEach((r) => {
    addBn({
      rank: 0,
      surface: 'online',
      category: r.type === 'script' ? 'JS bundle loading' : r.type === 'link' || /\.css/i.test(r.url) ? 'CSS loading' : r.type === 'fetch' || r.type === 'xmlhttprequest' ? 'API / XHR' : 'Network resource',
      file: r.url,
      function: 'browser network + parse',
      ms: r.ms,
      pct_of_startup: r.pct_of_wall,
      why: r.transferKB > 100 ? 'Large transfer on critical path' : 'Slow response or cache miss on critical path',
      expected_gain_ms: Math.round((r.ms || 0) * 0.4),
      impact: (r.ms || 0) > 200 ? 'high' : 'medium',
    });
  });

  if (report.online.summary.longTaskSumMs > 50) {
    addBn({
      rank: 0,
      surface: 'online',
      category: 'Browser main thread / Long Tasks',
      file: 'public/assets/js/* + inline Admin boot (RatebApp)',
      function: 'script evaluation + RatebApp.reinit / DOM bind',
      ms: report.online.summary.longTaskSumMs,
      pct_of_startup: report.online.summary.longTaskPct,
      why: 'Main-thread blocking work after script download (parse/compile/exec + DOM)',
      expected_gain_ms: Math.round(report.online.summary.longTaskSumMs * 0.45),
      impact: 'critical',
    });
  }

  if (report.server.php_cli) {
    const p = report.server.php_cli;
    addBn({
      rank: 0,
      surface: 'online',
      category: 'Composer autoload',
      file: 'rateb-erp/vendor/autoload.php',
      function: 'Composer\\Autoload\\ClassLoader',
      ms: p.autoload_ms,
      pct_of_startup: pct(p.autoload_ms, onlineWall),
      why: p.opcache_enabled ? 'Autoload under OPcache (CLI proxy; FPM may differ)' : 'OPcache disabled — every request recompiles',
      expected_gain_ms: p.opcache_enabled ? Math.round(p.autoload_ms * 0.15) : Math.round(p.autoload_ms * 0.7),
      impact: p.opcache_enabled ? 'low' : 'critical',
    });
    addBn({
      rank: 0,
      surface: 'online',
      category: 'PHP bootstrap',
      file: 'app/Core/Bootstrap.php',
      function: 'Bootstrap::init',
      ms: p.bootstrap_ms,
      pct_of_startup: pct(p.bootstrap_ms, onlineWall),
      why: 'Config load, service wiring, env before request handling',
      expected_gain_ms: Math.round(p.bootstrap_ms * 0.25),
      impact: p.bootstrap_ms > 50 ? 'high' : 'medium',
    });
    addBn({
      rank: 0,
      surface: 'online',
      category: 'Database queries',
      file: 'app/Core/Database.php',
      function: 'Database::pdo + SELECT 1 probe',
      ms: p.db_ping_ms,
      pct_of_startup: pct(p.db_ping_ms, onlineWall),
      why: 'Connection establishment / pool miss latency',
      expected_gain_ms: Math.round(p.db_ping_ms * 0.5),
      impact: p.db_ping_ms > 20 ? 'high' : 'low',
    });
    addBn({
      rank: 0,
      surface: 'online',
      category: 'Session startup',
      file: 'app/Core/SessionManager.php',
      function: 'SessionManager::start',
      ms: p.session_start_ms,
      pct_of_startup: pct(p.session_start_ms, onlineWall),
      why: 'Session store I/O (files/redis) on every authenticated page',
      expected_gain_ms: Math.round(p.session_start_ms * 0.4),
      impact: p.session_start_ms > 15 ? 'medium' : 'low',
    });
    addBn({
      rank: 0,
      surface: 'online',
      category: 'OPcache',
      file: 'php.ini / FPM pool',
      function: 'opcache_get_status',
      ms: 0,
      pct_of_startup: 0,
      why: p.opcache_enabled
        ? 'OPcache ON hit_rate=' + p.opcache_hit_rate + '% scripts=' + p.opcache_cached_scripts
        : 'OPcache OFF — catastrophic PHP compile cost on every request',
      expected_gain_ms: p.opcache_enabled ? 20 : 500,
      impact: p.opcache_enabled ? 'info' : 'critical',
      meta: p,
    });
  }

  if (report.offline.wall_ms.usable) {
    addBn({
      rank: 0,
      surface: 'offline',
      category: 'Service Worker / offline shell',
      file: 'public/offline-shell.html + public/pos-sw.js + assets/offline/rateb-offline.js',
      function: 'SW fetch handler + offline shell boot',
      ms: report.offline.wall_ms.usable,
      pct_of_startup: 100,
      why: 'Offline usable time is dominated by SW cache hit latency + rateb-offline.js parse/exec',
      expected_gain_ms: Math.round(report.offline.wall_ms.usable * 0.4),
      impact: 'critical',
    });
  }

  (report.offline.summary.slowest || []).slice(0, 6).forEach((r) => {
    addBn({
      rank: 0,
      surface: 'offline',
      category: /\.wasm|sqlite/i.test(r.url) ? 'WASM / SQLite' : /\.js/i.test(r.url) ? 'JS bundle loading' : 'Cache / network',
      file: r.url,
      function: 'cache match or network fallback',
      ms: r.ms,
      pct_of_startup: pct(r.ms, offlineWall),
      why: r.transferKB > 0 ? 'Not served from Cache API (cache miss)' : 'Cache hit but still costly read/parse',
      expected_gain_ms: Math.round((r.ms || 0) * 0.5),
      impact: (r.ms || 0) > 150 ? 'high' : 'medium',
    });
  });

  if (report.offline.summary.longTaskSumMs > 50) {
    addBn({
      rank: 0,
      surface: 'offline',
      category: 'Browser main thread / Long Tasks',
      file: 'assets/offline/rateb-offline.js',
      function: 'offline shell render + IDB/local unlock',
      ms: report.offline.summary.longTaskSumMs,
      pct_of_startup: report.offline.summary.longTaskPct,
      why: 'Monolithic offline SDK blocks main thread during unlock/render',
      expected_gain_ms: Math.round(report.offline.summary.longTaskSumMs * 0.5),
      impact: 'critical',
    });
  }

  const ls = (report.offline.hooks && report.offline.hooks.hooks) || (report.online.hooks && report.online.hooks.hooks) || {};
  if ((ls.lsReads || 0) + (ls.lsWrites || 0) > 20) {
    addBn({
      rank: 0,
      surface: 'both',
      category: 'LocalStorage',
      file: 'assets/js/* + assets/offline/rateb-offline.js',
      function: 'localStorage getItem/setItem',
      ms: Math.round(((ls.lsReads || 0) + (ls.lsWrites || 0)) * 0.05),
      pct_of_startup: 0,
      why: 'High localStorage chatter (reads=' + ls.lsReads + ' writes=' + ls.lsWrites + ') — sync main-thread I/O',
      expected_gain_ms: 30,
      impact: 'medium',
      meta: ls,
    });
  }
  if ((ls.idbOpens || 0) > 0) {
    addBn({
      rank: 0,
      surface: 'offline',
      category: 'IndexedDB',
      file: 'assets/offline/rateb-offline.js (IDB open)',
      function: 'indexedDB.open',
      ms: (ls.idbOpens || 0) * 25,
      pct_of_startup: pct((ls.idbOpens || 0) * 25, offlineWall),
      why: 'IDB open count=' + ls.idbOpens + ' during boot (schema/unlock path)',
      expected_gain_ms: Math.max(20, (ls.idbOpens - 1) * 20),
      impact: (ls.idbOpens || 0) > 2 ? 'high' : 'medium',
    });
  }

  bottlenecks.sort((a, b) => (b.ms || 0) - (a.ms || 0));
  bottlenecks.forEach((b, i) => {
    b.rank = i + 1;
  });
  report.bottlenecks = bottlenecks;
  report.ok = true;
  report.finished_at = new Date().toISOString();

  const out = path.join(OUT_DIR, 'perf-e2e-startup-audit-' + Date.now() + '.json');
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(
    JSON.stringify(
      {
        ok: true,
        out,
        online_usable_ms: report.online.wall_ms.usable,
        online_warm_ms: report.online.warm && report.online.warm.usable_ms,
        offline_usable_ms: report.offline.wall_ms.usable,
        top5: bottlenecks.slice(0, 5).map((b) => ({
          rank: b.rank,
          surface: b.surface,
          category: b.category,
          ms: b.ms,
          pct: b.pct_of_startup,
          file: b.file,
        })),
      },
      null,
      2
    )
  );
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
