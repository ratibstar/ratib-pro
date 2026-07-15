/**
 * Phase PE — SW thread profiler (temporary measure-only instrumentation loader).
 * Patches production pos-sw.js copy with timing hooks, deploys, measures, restores.
 * DOES NOT implement PE optimizations.
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
const SW_SRC = path.join(__dirname, '../../public/pos-sw.js');
const OUT_DIR = path.join(__dirname, 'reports');
const STAMP = Date.now();
const REMOTE_SW = '/home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js';
const REMOTE_BAK = '/tmp/pos-sw.js.pe-bak';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 120000,
  });
}
function scp(local, remote) {
  execFileSync('scp', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', local, HOST + ':' + remote], {
    stdio: 'inherit',
  });
}

/** Inject PE profiler into SW source (measure-only). */
function instrumentSw(src) {
  if (src.indexOf('__PE_PROF__') !== -1) {
    throw new Error('SW already instrumented');
  }
  const header = `
/* ===== PHASE PE PROFILER (TEMP) ===== */
var __PE_PROF__ = { t0: 0, events: [], spans: {}, seq: 0 };
function __peNow(){ return Date.now(); }
function __pePush(type, name, extra){
  try {
    __PE_PROF__.events.push(Object.assign({
      t: __peNow() - (__PE_PROF__.t0 || __peNow()),
      type: type,
      name: name
    }, extra || {}));
  } catch(e){}
}
function __peBegin(name){
  var id = name + '#' + (++__PE_PROF__.seq);
  __PE_PROF__.spans[id] = { name: name, start: __peNow() };
  __pePush('begin', name, { id: id });
  return id;
}
function __peEnd(id, extra){
  var s = __PE_PROF__.spans[id];
  if (!s) return;
  var wall = __peNow() - s.start;
  __pePush('end', s.name, Object.assign({ id: id, wall_ms: wall }, extra || {}));
  delete __PE_PROF__.spans[id];
  return wall;
}
function __peWrapPromise(name, factory){
  var id = __peBegin(name);
  return Promise.resolve().then(factory).then(function(v){
    __peEnd(id, { ok: true });
    return v;
  }, function(err){
    __peEnd(id, { ok: false, error: String(err && err.message ? err.message : err) });
    throw err;
  });
}
function __peBroadcast(){
  try {
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(list){
      (list||[]).forEach(function(c){
        try { c.postMessage({ type: 'PE_SW_PROFILE', payload: {
          events: __PE_PROF__.events.slice(),
          t0: __PE_PROF__.t0,
          at: Date.now(),
          build: (typeof SW_BUILD_ID !== 'undefined' ? SW_BUILD_ID : null)
        }}); } catch(e1){}
      });
    });
  } catch(e2){}
}
/* ===== END PE PROFILER HEADER ===== */
`;

  let out = src;
  // bump build id marker for verification
  out = out.replace(
    /var SW_BUILD_ID = '[^']+';/,
    "var SW_BUILD_ID = '20260715-phase-pe-profile';"
  );

  // Wrap cacheOneProtectedAsset
  out = out.replace(
    'function cacheOneProtectedAsset(cache, base, relPath) {\n    var rel = String(relPath || \'\').replace(/^\\//, \'\');\n    var abs = /^https?:/i.test(rel) ? rel : (base + rel);\n    var bare = abs.split(\'?\')[0];\n    return fetch(abs, {',
    `function cacheOneProtectedAsset(cache, base, relPath) {
    var rel = String(relPath || '').replace(/^\\//, '');
    var abs = /^https?:/i.test(rel) ? rel : (base + rel);
    var bare = abs.split('?')[0];
    var peId = __peBegin('cacheOneProtectedAsset:' + rel);
    return fetch(abs, {`
  );
  // Add end on success path - find return { rel: rel, ok: true
  out = out.replace(
    'return { rel: rel, ok: true, len: body.length, url: bare };',
    '__peEnd(peId, { rel: rel, len: body.length, kind: \"fetch+put\" });\n                return { rel: rel, ok: true, len: body.length, url: bare };'
  );
  // catch path for cacheOneProtectedAsset - the function throws before return; wrap outer return fetch
  out = out.replace(
    `function cacheOneProtectedAssetWithRetry(cache, base, relPath, retries) {
    var left = retries == null ? 2 : retries;
    function attempt() {
        return cacheOneProtectedAsset(cache, base, relPath).catch(function (err) {`,
    `function cacheOneProtectedAssetWithRetry(cache, base, relPath, retries) {
    var left = retries == null ? 2 : retries;
    function attempt() {
        return cacheOneProtectedAsset(cache, base, relPath).catch(function (err) {
            try { /* pe ends inside cacheOne only on success; failures may lack peId */ } catch(ePe){}`
  );

  // verifyProtectedOfflineCache - wrap whole function body start
  out = out.replace(
    'function verifyProtectedOfflineCache() {\n    var base = publicBaseUrl();\n    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {',
    `function verifyProtectedOfflineCache() {
    var base = publicBaseUrl();
    var peV = __peBegin('verifyProtectedOfflineCache');
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
      __pePush('mark', 'verify_cache_opened');`
  );
  out = out.replace(
    `return {
                ok: missing.length === 0,
                missing: missing,
                inventory: inventory,
                cache: ERP_COEXIST_CACHE,
                build: SW_BUILD_ID,
                at: Date.now()
            };`,
    `__peEnd(peV, { missing: missing.length, inventory: inventory.length });
            return {
                ok: missing.length === 0,
                missing: missing,
                inventory: inventory,
                cache: ERP_COEXIST_CACHE,
                build: SW_BUILD_ID,
                at: Date.now()
            };`
  );

  // ensureProtectedOfflineCache wrap
  out = out.replace(
    'function ensureProtectedOfflineCache(opts) {\n    opts = opts || {};',
    `function ensureProtectedOfflineCache(opts) {
    opts = opts || {};
    var peE = __peBegin('ensureProtectedOfflineCache:force=' + (!!opts.force));`
  );
  out = out.replace(
    'function finalize(results) {\n        return verifyProtectedOfflineCache().then(function (v) {\n            v.cached = results || [];\n            LAST_PROTECTED_CACHE_RESULT = v;\n            if (!v.ok) {\n                throw new Error(\'protected_cache_verify_failed:\' + JSON.stringify(v.missing || []));\n            }\n            return v;\n        });\n    }\n    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {\n        if (!opts.force) {\n            return verifyProtectedOfflineCache().then(function (v) {\n                if (v.ok) {\n                    LAST_PROTECTED_CACHE_RESULT = v;\n                    return v;\n                }',
    `function finalize(results) {
        return verifyProtectedOfflineCache().then(function (v) {
            v.cached = results || [];
            LAST_PROTECTED_CACHE_RESULT = v;
            if (!v.ok) {
                throw new Error('protected_cache_verify_failed:' + JSON.stringify(v.missing || []));
            }
            __peEnd(peE, { phase: 'finalize', cached: (results||[]).length, ok: v.ok });
            return v;
        });
    }
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        __pePush('mark', 'ensure_cache_opened', { force: !!opts.force });
        if (!opts.force) {
            return verifyProtectedOfflineCache().then(function (v) {
                if (v.ok) {
                    LAST_PROTECTED_CACHE_RESULT = v;
                    __peEnd(peE, { phase: 'verify_hit', ok: true });
                    return v;
                }`
  );

  // populateQueue mark each
  out = out.replace(
    `function populateQueue(cache, queue) {
        var results = [];
        return queue.reduce(function (chain, rel) {
            return chain.then(function () {
                return cacheOneProtectedAssetWithRetry(cache, base, rel, 2).then(function (row) {
                    results.push(row);
                    return null;
                });
            });
        }, Promise.resolve()).then(function () {
            return results;
        });
    }`,
    `function populateQueue(cache, queue) {
        var results = [];
        var peQ = __peBegin('populateQueue:n=' + queue.length);
        return queue.reduce(function (chain, rel) {
            return chain.then(function () {
                var peItem = __peBegin('populate_item:' + rel);
                return cacheOneProtectedAssetWithRetry(cache, base, rel, 2).then(function (row) {
                    __peEnd(peItem, { len: row && row.len });
                    results.push(row);
                    return null;
                });
            });
        }, Promise.resolve()).then(function () {
            __peEnd(peQ, { n: results.length });
            return results;
        });
    }`
  );

  // migrateActivateCaches
  out = out.replace(
    'function migrateActivateCaches() {\n    return caches.keys().then(function (keys) {',
    `function migrateActivateCaches() {
    var peM = __peBegin('migrateActivateCaches');
    return caches.keys().then(function (keys) {
      __pePush('mark', 'migrate_keys', { n: keys.length });`
  );
  // close migrate - hard. Wrap return of outer then chain by patching function to always end via finally
  // Add after migrate function's closing - instead wrap callers in runBackgroundWarm

  // runBackgroundWarm
  out = out.replace(
    `function runBackgroundWarm(opts) {
    opts = opts || {};
    var t0 = Date.now();
    LAST_BACKGROUND_WARM = { started_at: t0, reason: opts.reason || 'idle', build: SW_BUILD_ID };
    return migrateActivateCaches().catch(function () {
        return null;
    }).then(function () {
        // Verify-first unless explicit force (preserves integrity without blocking nav).
        return ensureProtectedOfflineCache({ force: !!opts.force }).catch(function (err) {`,
    `function runBackgroundWarm(opts) {
    opts = opts || {};
    var t0 = Date.now();
    __PE_PROF__.t0 = t0;
    __PE_PROF__.events = [];
    var peR = __peBegin('runBackgroundWarm');
    LAST_BACKGROUND_WARM = { started_at: t0, reason: opts.reason || 'idle', build: SW_BUILD_ID };
    return __peWrapPromise('migrateActivateCaches_call', function(){ return migrateActivateCaches(); }).catch(function () {
        return null;
    }).then(function () {
        // Verify-first unless explicit force (preserves integrity without blocking nav).
        return ensureProtectedOfflineCache({ force: !!opts.force }).catch(function (err) {`
  );

  out = out.replace(
    `}).then(function (protectedResult) {
        LAST_BACKGROUND_WARM.protected = protectedResult;
        return warmOptionalChromeAssets().then(function () {
            return warmErpOfflineShell({ force: true }).catch(function () { return null; });
        }).then(function () {
            LAST_BACKGROUND_WARM.finished_at = Date.now();
            LAST_BACKGROUND_WARM.wall_ms = LAST_BACKGROUND_WARM.finished_at - t0;
            return LAST_BACKGROUND_WARM;
        });
    });
}`,
    `}).then(function (protectedResult) {
        LAST_BACKGROUND_WARM.protected = protectedResult;
        __pePush('mark', 'protected_done', { ok: !!(protectedResult && protectedResult.ok) });
        return __peWrapPromise('warmOptionalChromeAssets', function(){ return warmOptionalChromeAssets(); }).then(function () {
            return __peWrapPromise('warmErpOfflineShell', function(){
              return warmErpOfflineShell({ force: true }).catch(function () { return null; });
            });
        }).then(function () {
            LAST_BACKGROUND_WARM.finished_at = Date.now();
            LAST_BACKGROUND_WARM.wall_ms = LAST_BACKGROUND_WARM.finished_at - t0;
            __peEnd(peR, { wall_ms: LAST_BACKGROUND_WARM.wall_ms });
            __peBroadcast();
            return LAST_BACKGROUND_WARM;
        });
    });
}`
  );

  // activate: mark claim
  out = out.replace(
    `self.addEventListener('activate', function (event) {
    // Phase PC — claim FIRST so navigation is never gated on allowlist/network.
    event.waitUntil(
        self.clients.claim().then(function () {
            scheduleBackgroundWarm({ reason: 'activate', force: false });
            loadErpOpsAllowlist().catch(function () { return null; });
            return null;
        }).catch(function () {
            scheduleBackgroundWarm({ reason: 'activate_fallback', force: false });
            return null;
        })
    );
});`,
    `self.addEventListener('activate', function (event) {
    // Phase PC — claim FIRST so navigation is never gated on allowlist/network.
    event.waitUntil(
        self.clients.claim().then(function () {
            __PE_PROF__.t0 = Date.now();
            __pePush('mark', 'activate_claimed');
            scheduleBackgroundWarm({ reason: 'activate', force: false });
            loadErpOpsAllowlist().catch(function () { return null; });
            return null;
        }).catch(function () {
            scheduleBackgroundWarm({ reason: 'activate_fallback', force: false });
            return null;
        })
    );
});`
  );

  // fetch navigate - mark
  out = out.replace(
    `self.addEventListener('fetch', function (event) {
    var url;
    try {
        url = new URL(event.request.url);
    } catch (eUrl) {
        return;
    }`,
    `self.addEventListener('fetch', function (event) {
    var url;
    try {
        url = new URL(event.request.url);
    } catch (eUrl) {
        return;
    }
    if (event.request.mode === 'navigate') {
      __pePush('fetch_navigate', url.pathname, { at_sw: Date.now(), pe_t: __PE_PROF__.t0 ? (Date.now()-__PE_PROF__.t0) : null });
      __peBroadcast();
    }`
  );

  // message dump
  out = out.replace(
    `if (data.type === 'PROTECTED_OFFLINE_CACHE_STATUS') {`,
    `if (data.type === 'PE_DUMP_PROFILE') {
        try {
          if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
              type: 'PE_SW_PROFILE',
              payload: { events: __PE_PROF__.events.slice(), t0: __PE_PROF__.t0, at: Date.now(), build: SW_BUILD_ID }
            });
          }
        } catch (ePeDump) {}
        return;
    }
    if (data.type === 'PROTECTED_OFFLINE_CACHE_STATUS') {`
  );

  return header + out;
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const original = fs.readFileSync(SW_SRC, 'utf8');
  const instrumented = instrumentSw(original);
  const tmpSw = path.join(os.tmpdir(), 'pos-sw-pe-' + STAMP + '.js');
  fs.writeFileSync(tmpSw, instrumented);

  // Backup + deploy instrumented
  ssh('cp ' + REMOTE_SW + ' ' + REMOTE_BAK);
  scp(tmpSw, REMOTE_SW);

  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-pe-' + STAMP);
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
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
  const page = context.pages()[0] || (await context.newPage());
  const swEvents = [];
  page.on('console', (msg) => {
    if (String(msg.text()).indexOf('PE') === 0) swEvents.push({ t: Date.now(), text: msg.text() });
  });

  await page.exposeBinding('__peCollect', (_s, payload) => {
    swEvents.push({ t: Date.now(), payload });
  });

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  // Wait for PE profile SW live
  for (let i = 0; i < 20; i++) {
    const live = await page.evaluate(async (url) => {
      const r = await fetch(url + '?t=' + Date.now(), { cache: 'no-store' });
      const t = await r.text();
      return t.indexOf('phase-pe-profile') !== -1 && t.indexOf('__PE_PROF__') !== -1;
    }, BASE + '/pos-sw.js');
    if (live) break;
    await page.waitForTimeout(1000);
  }

  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  });
  await page.waitForTimeout(200);

  await page.evaluate(() => {
    navigator.serviceWorker.addEventListener('message', (ev) => {
      if (ev.data && ev.data.type === 'PE_SW_PROFILE') {
        window.__peCollect(ev.data.payload);
      }
    });
  });

  // Register PE SW and navigate IMMEDIATELY after activated (race background warm)
  const lifecycle = await page.evaluate(async (base) => {
    const t0 = performance.now();
    const marks = [];
    const reg = await navigator.serviceWorker.register(base + '/pos-sw.js?v=pe-' + Date.now(), {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    marks.push({ name: 'registered', t: Math.round(performance.now() - t0) });
    const sw = reg.installing || reg.waiting || reg.active;
    await new Promise((resolve) => {
      if (!sw || sw.state === 'activated') return resolve();
      sw.addEventListener('statechange', () => {
        marks.push({ name: 'state_' + sw.state, t: Math.round(performance.now() - t0) });
        if (sw.state === 'activated') resolve();
      });
    });
    await navigator.serviceWorker.ready;
    marks.push({ name: 'ready', t: Math.round(performance.now() - t0) });
    return { marks, activated_ms: marks.find((m) => m.name === 'ready')?.t };
  }, BASE);

  // Immediately first POS open (overlap with background warm)
  const navT0 = Date.now();
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const nav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    return {
      workerStart: n.workerStart || 0,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      load: Math.round(n.loadEventEnd * 10) / 10,
    };
  });
  const wallNav = Date.now() - navT0;

  // Wait a bit for warm to progress / finish, dump profile
  await page.waitForTimeout(3000);
  const dump = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    const ctrl = navigator.serviceWorker.controller || (reg && reg.active);
    if (!ctrl) return { error: 'no_controller' };
    return new Promise((resolve) => {
      const ch = new MessageChannel();
      const timer = setTimeout(() => resolve({ error: 'timeout', collected: window.__peLast || null }), 10000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(timer);
        resolve(ev.data);
      };
      ctrl.postMessage({ type: 'PE_DUMP_PROFILE' }, [ch.port2]);
    });
  });

  await page.waitForTimeout(8000);
  const dump2 = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    const ctrl = navigator.serviceWorker.controller || (reg && reg.active);
    if (!ctrl) return { error: 'no_controller' };
    return new Promise((resolve) => {
      const ch = new MessageChannel();
      const timer = setTimeout(() => resolve({ error: 'timeout' }), 10000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(timer);
        resolve(ev.data);
      };
      ctrl.postMessage({ type: 'PE_DUMP_PROFILE' }, [ch.port2]);
    });
  });

  // Warm navigation after warm completes
  const warmT0 = Date.now();
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const warmNav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    return {
      workerStart: n.workerStart || 0,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
    };
  });

  await context.close();

  // Restore production SW
  try {
    ssh('cp ' + REMOTE_BAK + ' ' + REMOTE_SW + ' && rm -f ' + REMOTE_BAK);
  } catch (e) {
    // restore from local original
    const restorePath = path.join(os.tmpdir(), 'pos-sw-restore.js');
    fs.writeFileSync(restorePath, original);
    scp(restorePath, REMOTE_SW);
  }

  // Analyze events
  const payload = (dump2 && dump2.payload) || (dump && dump.payload) || {};
  const events = payload.events || [];
  const ends = events.filter((e) => e.type === 'end');
  ends.sort((a, b) => (b.wall_ms || 0) - (a.wall_ms || 0));

  const fetchNav = events.filter((e) => e.type === 'fetch_navigate');
  const marks = events.filter((e) => e.type === 'mark');

  const report = {
    phase: 'PE',
    mode: 'SW_THREAD_PROFILE_READ_ONLY',
    generatedAt: new Date().toISOString(),
    note: 'Temporary instrumentation deployed then restored',
    lifecycle,
    first_nav: { wall_ms: wallNav, ...nav },
    warm_nav: { wall_ms: Date.now() - warmT0, ...warmNav },
    sw_events_collected: swEvents.length,
    dump_early: dump && dump.payload ? { event_count: (dump.payload.events || []).length, build: dump.payload.build } : dump,
    dump_late: dump2 && dump2.payload ? { event_count: (dump2.payload.events || []).length, build: dump2.payload.build } : dump2,
    timeline_events: events,
    top_30: ends.slice(0, 30).map((e, i) => ({
      rank: i + 1,
      name: e.name,
      wall_ms: e.wall_ms,
      self_ms: e.wall_ms,
      calls: 1,
      extra: e,
    })),
    fetch_navigate_marks: fetchNav,
    marks,
  };

  // Build flame from ends
  const byPrefix = {};
  ends.forEach((e) => {
    const key = String(e.name || '').split(':')[0];
    if (!byPrefix[key]) byPrefix[key] = { name: key, wall_ms: 0, calls: 0 };
    byPrefix[key].wall_ms += e.wall_ms || 0;
    byPrefix[key].calls += 1;
  });
  report.aggregated = Object.values(byPrefix).sort((a, b) => b.wall_ms - a.wall_ms);

  const out = path.join(OUT_DIR, `phase-pe-sw-profile-${STAMP}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pe-sw-profile-latest.json'), JSON.stringify(report, null, 2));
  console.log(
    JSON.stringify(
      {
        out,
        first: report.first_nav,
        warm: report.warm_nav,
        top10: report.top_30.slice(0, 10),
        aggregated: report.aggregated.slice(0, 15),
        fetch_nav_at: fetchNav,
        restored: true,
      },
      null,
      2
    )
  );
})().catch(async (e) => {
  console.error(e);
  try {
    ssh('test -f ' + REMOTE_BAK + ' && cp ' + REMOTE_BAK + ' ' + REMOTE_SW);
  } catch (_) {}
  process.exit(1);
});
