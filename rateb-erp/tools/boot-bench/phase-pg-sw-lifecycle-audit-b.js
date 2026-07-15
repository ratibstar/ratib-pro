/**
 * Phase PG-B — fix log continuity across navigations; attribute ~197ms.
 * READ ONLY · production · real Chrome · no product code changes.
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

const PG_HOOK = `(() => {
  window.__PG_SW_LOG__ = window.__PG_SW_LOG__ || [];
  if (window.__PG_HOOKED__) return;
  window.__PG_HOOKED__ = true;
  const t0 = performance.now();
  const wall0 = Date.now();
  function log(type, detail) {
    window.__PG_SW_LOG__.push(Object.assign({
      t_ms: Math.round((performance.now() - t0) * 10) / 10,
      wall: Date.now(),
      wall_rel: Date.now() - wall0,
      type,
      href: (location.pathname + location.search).slice(0, 140),
    }, detail || {}));
  }
  window.__PG_SW_LOG_MARK__ = function (type, detail) { log(type, detail); };
  function stackBrief() {
    try {
      return String(new Error().stack || '').split('\\n').slice(2, 7).map((l) => l.trim()).join(' || ');
    } catch (_) { return ''; }
  }
  function wireReg(reg, source) {
    if (!reg || reg.__pg_wired) return reg;
    reg.__pg_wired = true;
    try {
      const ou = reg.update.bind(reg);
      reg.update = function () {
        log('update_call', { source, active: reg.active && reg.active.scriptURL, stack: stackBrief() });
        return Promise.resolve(ou()).then((r) => {
          log('update_resolved', {
            active: reg.active && reg.active.scriptURL,
            waiting: reg.waiting && reg.waiting.scriptURL,
            installing: reg.installing && reg.installing.scriptURL,
          });
          return r;
        });
      };
    } catch (_) {}
    try {
      reg.addEventListener('updatefound', () => {
        const sw = reg.installing;
        log('updatefound', { installing: sw && sw.scriptURL, active: reg.active && reg.active.scriptURL });
        if (sw) {
          sw.addEventListener('statechange', () => {
            log('worker_statechange', { state: sw.state, scriptURL: sw.scriptURL });
          });
        }
      });
    } catch (_) {}
    return reg;
  }
  if (!('serviceWorker' in navigator)) return;
  const orig = navigator.serviceWorker.register.bind(navigator.serviceWorker);
  navigator.serviceWorker.register = function (url, opts) {
    log('register_call', { url: String(url), scope: opts && opts.scope, stack: stackBrief() });
    return orig(url, opts).then((reg) => {
      log('register_resolved', {
        scope: reg.scope,
        active: reg.active && reg.active.scriptURL,
        installing: reg.installing && reg.installing.scriptURL,
        waiting: reg.waiting && reg.waiting.scriptURL,
      });
      if (reg.installing) {
        reg.installing.addEventListener('statechange', () => {
          log('worker_statechange', { state: reg.installing && reg.installing.state, scriptURL: reg.installing && reg.installing.scriptURL });
        });
      }
      return wireReg(reg, 'register');
    });
  };
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    log('controllerchange', { controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL });
  });
  navigator.serviceWorker.ready.then((reg) => {
    log('ready', { active: reg.active && reg.active.scriptURL, controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL });
    wireReg(reg, 'ready');
  }).catch(() => {});
})();`;

async function mint(context) {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  await context.clearCookies();
  await context.addCookies([{
    name: mint.session_name, value: mint.session_id, domain: 'rateb.sa', path: '/',
    httpOnly: true, secure: true, sameSite: 'Lax',
  }]);
}

async function snapLog(page) {
  return page.evaluate(() => (window.__PG_SW_LOG__ || []).slice());
}

async function runOne(name, { mode }) {
  const profileDir = path.join(os.tmpdir(), 'rateb-pgb-' + name + '-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await mint(context);
  await context.addInitScript({ content: PG_HOOK });
  const page = context.pages()[0] || (await context.newPage());

  const cdpLog = [];
  const cdp = await context.newCDPSession(page);
  try {
    await cdp.send('ServiceWorker.enable');
    cdp.on('ServiceWorker.workerVersionUpdated', (payload) => {
      cdpLog.push({ wall: Date.now(), type: 'version', versions: (payload.versions || []).map((v) => ({
        versionId: v.versionId,
        registrationId: v.registrationId,
        scriptURL: v.scriptURL,
        runningStatus: v.runningStatus,
        status: v.status,
      })) });
    });
    cdp.on('ServiceWorker.workerRegistrationUpdated', (payload) => {
      cdpLog.push({ wall: Date.now(), type: 'registration', registrations: payload.registrations });
    });
  } catch (e) {
    cdpLog.push({ type: 'cdp_fail', err: String(e.message || e) });
  }

  const timeline = [];
  const mark = (label, extra) => timeline.push({ wall: Date.now(), label, ...(extra || {}) });

  await page.goto(ADMIN, { waitUntil: 'domcontentloaded', timeout: 90000 });
  mark('admin_loaded');
  await page.waitForTimeout(400);

  // clear
  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
    try { localStorage.removeItem('rateb_sw_build'); } catch (_) {}
  });
  mark('cleared');
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 90000 });
  mark('admin_reloaded');
  // wait for layout SW register (idle up to 3500)
  await page.waitForTimeout(4000);
  mark('after_idle_4s');
  let logAccum = await snapLog(page);

  let synthUrl = null;
  if (mode === 'synth_wait800' || mode === 'synth_immediate') {
    synthUrl = await page.evaluate(async (base) => {
      const scope = base.endsWith('/') ? base : base + '/';
      const url = base + '/pos-sw.js?v=pg-synth-' + Date.now();
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('synth_register_begin', { url });
      const reg = await navigator.serviceWorker.register(url, { scope, updateViaCache: 'none' });
      await navigator.serviceWorker.ready;
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('synth_register_ready', {
        active: reg.active && reg.active.scriptURL,
        controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL,
      });
      return url;
    }, BASE);
    mark('synth_registered', { synthUrl });
    logAccum = logAccum.concat(await snapLog(page));
  }

  if (mode === 'natural_wait800' || mode === 'synth_wait800') {
    await page.evaluate(() => { if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('wait_begin', { ms: 800 }); });
    mark('wait800_begin');
    await page.waitForTimeout(800);
    await page.evaluate(() => { if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('wait_end', {}); });
    mark('wait800_end');
    logAccum = logAccum.concat(await snapLog(page));
  }

  const before = await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    return {
      controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL,
      regs: (regs || []).map((r) => ({
        active: r.active && r.active.scriptURL,
        waiting: r.waiting && r.waiting.scriptURL,
        installing: r.installing && r.installing.scriptURL,
      })),
    };
  });
  mark('before_pos', before);

  // CDP: request worker inspection
  let workerTarget = null;
  try {
    const targets = await cdp.send('Target.getTargets');
    workerTarget = (targets.targetInfos || []).find((t) => t.type === 'service_worker');
  } catch (_) {}

  mark('pos_goto_begin');
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  mark('pos_goto_dcl');

  const metrics = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return {
      workerStart: n.workerStart || 0,
      requestStart: Math.round(n.requestStart * 10) / 10,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: Math.max(0, Math.round((n.responseStart - n.requestStart) * 10) / 10),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      controller: navigator.serviceWorker?.controller?.scriptURL || null,
    };
  });
  const posLog = await snapLog(page);
  logAccum = logAccum.concat(posLog.map((e) => ({ ...e, _page: 'pos' })));

  // dedupe by wall+type+url
  const seen = new Set();
  const log = [];
  for (const e of logAccum) {
    const k = e.wall + '|' + e.type + '|' + (e.url || e.scriptURL || e.controller || '');
    if (seen.has(k)) continue;
    seen.add(k);
    log.push(e);
  }

  await context.close();
  return { name, mode, synthUrl, before, metrics, timeline, log, cdpLog, workerTarget };
}

(async () => {
  const modes = ['natural_wait800', 'natural_immediate', 'synth_wait800', 'synth_immediate'];
  // natural_immediate = wait 0 after idle register
  const results = [];
  results.push(await runOne('natural_wait800', { mode: 'natural_wait800' }));
  results.push(await runOne('natural_immediate', { mode: 'natural_immediate' }));
  results.push(await runOne('synth_wait800', { mode: 'synth_wait800' }));
  results.push(await runOne('synth_immediate', { mode: 'synth_immediate' }));

  const summarize = (r) => {
    const reg = r.log.filter((e) => e.type === 'register_call');
    const uf = r.log.filter((e) => e.type === 'updatefound');
    const sc = r.log.filter((e) => e.type === 'worker_statechange');
    const up = r.log.filter((e) => e.type === 'update_call');
    const cc = r.log.filter((e) => e.type === 'controllerchange');
    const running = [];
    for (const ev of r.cdpLog) {
      if (ev.type !== 'version') continue;
      for (const v of ev.versions || []) {
        running.push({
          wall: ev.wall,
          scriptURL: v.scriptURL,
          runningStatus: v.runningStatus,
          status: v.status,
        });
      }
    }
    return {
      workerStart: r.metrics.workerStart,
      responseStart: r.metrics.responseStart,
      controller_after: r.metrics.controller,
      controller_before: r.before.controller,
      register_calls: reg.map((e) => e.url),
      update_calls: up.length,
      updatefound: uf.length,
      statechanges: sc.map((e) => ({ state: e.state, url: e.scriptURL })),
      controllerchanges: cc.length,
      cdp_running_tail: running.slice(-15),
      has_force_sw: /force-sw-v52/.test(r.before.controller || '') || /force-sw-v52/.test(r.metrics.controller || ''),
      has_synth: /pg-synth/.test(r.before.controller || '') || /pg-synth/.test(r.metrics.controller || ''),
    };
  };

  const summaries = results.map((r) => ({ name: r.name, ...summarize(r) }));

  // Attribution
  const nat800 = summaries.find((s) => s.name === 'natural_wait800');
  const syn800 = summaries.find((s) => s.name === 'synth_wait800');
  const synImm = summaries.find((s) => s.name === 'synth_immediate');
  const natImm = summaries.find((s) => s.name === 'natural_immediate');

  let who = null;
  if (nat800 && syn800 && nat800.workerStart < 20 && syn800.workerStart > 100) {
    who = {
      actor: 'Synthetic Service Worker re-registration with unique ?v= query (bench protocol)',
      mechanism:
        'navigator.serviceWorker.register(pos-sw.js?v=pg-synth-<timestamp>) creates a new SW script URL → install/activate. ' +
        'Natural shell uses a stable URL pos-sw.js?v=20260714-force-sw-v52 and does NOT reproduce ~197ms.',
      not_actor: 'force-sw-v52 stable registration itself (natural_wait800 workerStart≈' + nat800.workerStart + 'ms)',
      evidence: {
        natural_wait800_ws: nat800.workerStart,
        synth_wait800_ws: syn800.workerStart,
        synth_immediate_ws: synImm && synImm.workerStart,
        natural_controller: nat800.controller_before,
        synth_controller: syn800.controller_before,
        synth_registers: syn800.register_calls,
      },
    };
  }

  const report = {
    phase: 'PG',
    mode: 'READ_ONLY',
    generatedAt: new Date().toISOString(),
    production_sw: ssh("grep SW_BUILD_ID /home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js | head -1").trim(),
    who_causes_197ms: who,
    summaries,
    results: results.map((r) => ({
      name: r.name,
      metrics: r.metrics,
      before: r.before,
      synthUrl: r.synthUrl,
      timeline: r.timeline,
      log: r.log,
      cdp_tail: r.cdpLog.slice(-40),
    })),
    enterprise: who
      ? 'PASS — identified WHO causes ~197ms workerStart'
      : 'FAIL — attribution incomplete',
  };

  fs.mkdirSync(OUT_DIR, { recursive: true });
  fs.writeFileSync(path.join(OUT_DIR, `phase-pg-sw-lifecycle-${Date.now()}.json`), JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pg-sw-lifecycle-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    enterprise: report.enterprise,
    who_causes_197ms: who,
    summaries,
  }, null, 2));
})().catch((e) => { console.error(e); process.exit(1); });
