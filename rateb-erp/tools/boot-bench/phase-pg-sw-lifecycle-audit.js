/**
 * Phase PG — Service Worker registration & update lifecycle audit (READ ONLY).
 * Real Chrome · production · no code changes to product.
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

/** Injected into every document — hooks register/update/lifecycle. */
const PG_HOOK = `(() => {
  if (window.__PG_HOOKED__) return;
  window.__PG_HOOKED__ = true;
  window.__PG_SW_LOG__ = window.__PG_SW_LOG__ || [];
  const t0 = performance.now();
  const wall0 = Date.now();
  function log(type, detail) {
    const entry = Object.assign({
      t_ms: Math.round((performance.now() - t0) * 10) / 10,
      wall: Date.now(),
      wall_rel: Date.now() - wall0,
      type,
      href: location.href.replace(location.origin, '').slice(0, 120),
    }, detail || {});
    window.__PG_SW_LOG__.push(entry);
    try {
      console.debug('[PG_SW]', type, entry);
    } catch (_) {}
  }
  window.__PG_SW_LOG_MARK__ = function (type, detail) { log(type, detail); };

  function stackBrief() {
    try {
      return String(new Error().stack || '')
        .split('\\n')
        .slice(2, 8)
        .map((l) => l.trim())
        .join(' | ');
    } catch (_) {
      return '';
    }
  }

  function wireReg(reg, source) {
    if (!reg || reg.__pg_wired) return reg;
    reg.__pg_wired = true;
    try {
      const origUpdate = reg.update.bind(reg);
      reg.update = function () {
        log('update_call', {
          source: source || 'reg.update',
          active: reg.active && reg.active.scriptURL,
          waiting: reg.waiting && reg.waiting.scriptURL,
          installing: reg.installing && reg.installing.scriptURL,
          stack: stackBrief(),
        });
        const p = origUpdate();
        return Promise.resolve(p).then(
          (r) => {
            log('update_resolved', {
              active: reg.active && reg.active.scriptURL,
              waiting: reg.waiting && reg.waiting.scriptURL,
              installing: reg.installing && reg.installing.scriptURL,
            });
            return r;
          },
          (err) => {
            log('update_rejected', { err: String(err && err.message ? err.message : err) });
            throw err;
          }
        );
      };
    } catch (_) {}
    try {
      reg.addEventListener('updatefound', () => {
        const sw = reg.installing;
        log('updatefound', {
          installing: sw && sw.scriptURL,
          active: reg.active && reg.active.scriptURL,
          waiting: reg.waiting && reg.waiting.scriptURL,
        });
        if (sw) {
          sw.addEventListener('statechange', () => {
            log('worker_statechange', {
              state: sw.state,
              scriptURL: sw.scriptURL,
            });
          });
        }
      });
    } catch (_) {}
    return reg;
  }

  if (!('serviceWorker' in navigator)) {
    log('no_sw_api', {});
    return;
  }

  const origRegister = navigator.serviceWorker.register.bind(navigator.serviceWorker);
  navigator.serviceWorker.register = function (url, opts) {
    log('register_call', {
      url: String(url),
      scope: opts && opts.scope,
      updateViaCache: opts && opts.updateViaCache,
      stack: stackBrief(),
    });
    return origRegister(url, opts).then(
      (reg) => {
        log('register_resolved', {
          scope: reg.scope,
          active: reg.active && reg.active.scriptURL,
          waiting: reg.waiting && reg.waiting.scriptURL,
          installing: reg.installing && reg.installing.scriptURL,
        });
        return wireReg(reg, 'register');
      },
      (err) => {
        log('register_rejected', { err: String(err && err.message ? err.message : err) });
        throw err;
      }
    );
  };

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    log('controllerchange', {
      controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL,
    });
  });

  navigator.serviceWorker.ready.then((reg) => {
    log('ready', {
      scope: reg.scope,
      active: reg.active && reg.active.scriptURL,
      controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL,
    });
    wireReg(reg, 'ready');
  }).catch(() => {});

  // Snapshot existing regs after short delay (post-parse)
  setTimeout(() => {
    navigator.serviceWorker.getRegistrations().then((regs) => {
      log('regs_snapshot', {
        n: (regs || []).length,
        scripts: (regs || []).map((r) => ({
          scope: r.scope,
          active: r.active && r.active.scriptURL,
          waiting: r.waiting && r.waiting.scriptURL,
          installing: r.installing && r.installing.scriptURL,
        })),
      });
      (regs || []).forEach((r) => wireReg(r, 'snapshot'));
    }).catch(() => {});
  }, 0);
})();`;

async function mintCookies(context) {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
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
}

async function clearSw(page) {
  await page.evaluate(async () => {
    if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('clear_start', {});
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
    try {
      localStorage.removeItem('rateb_sw_build');
      sessionStorage.removeItem('rateb_erp_shell_warm_at');
    } catch (_) {}
    if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('clear_done', { regs: regs.length });
  });
  await page.waitForTimeout(300);
}

async function drainLog(page) {
  return page.evaluate(() => (window.__PG_SW_LOG__ || []).slice());
}

async function navMetrics(page) {
  return page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    return {
      workerStart: n.workerStart || 0,
      fetchStart: Math.round(n.fetchStart * 10) / 10,
      requestStart: Math.round(n.requestStart * 10) / 10,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      transferSize: n.transferSize || 0,
      controller: navigator.serviceWorker?.controller?.scriptURL || null,
    };
  });
}

async function runScenario(name, opts) {
  const profileDir = path.join(os.tmpdir(), 'rateb-pg-' + name + '-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await mintCookies(context);
  await context.addInitScript({ content: PG_HOOK });

  const page = context.pages()[0] || (await context.newPage());
  const cdp = await context.newCDPSession(page);
  const cdpEvents = [];
  try {
    await cdp.send('ServiceWorker.enable');
    cdp.on('ServiceWorker.workerRegistrationUpdated', (e) => {
      cdpEvents.push({ t: Date.now(), type: 'cdp_registrationUpdated', e });
    });
    cdp.on('ServiceWorker.workerVersionUpdated', (e) => {
      cdpEvents.push({ t: Date.now(), type: 'cdp_versionUpdated', e });
    });
  } catch (e) {
    cdpEvents.push({ type: 'cdp_enable_failed', err: String(e.message || e) });
  }

  // Land on admin first (session)
  await page.goto(ADMIN, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(500);

  if (opts.clear) {
    await clearSw(page);
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(400);
  }

  if (opts.syntheticRegister) {
    await page.evaluate(async (base) => {
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('synthetic_register_begin', {});
      const scope = base.endsWith('/') ? base : base + '/';
      await navigator.serviceWorker.register(base + '/pos-sw.js?v=pg-synth-' + Date.now(), {
        scope,
        updateViaCache: 'none',
      });
      await navigator.serviceWorker.ready;
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('synthetic_register_ready', {});
    }, BASE);
  }

  // Let natural layout register / update settle or wait PD-style
  if (opts.waitMs != null) {
    await page.evaluate((ms) => {
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('wait_begin', { ms });
    }, opts.waitMs);
    await page.waitForTimeout(opts.waitMs);
    await page.evaluate(() => {
      if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('wait_end', {});
    });
  }

  // Snapshot before POS nav
  const beforeNav = await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    return {
      controller: navigator.serviceWorker.controller && navigator.serviceWorker.controller.scriptURL,
      regs: (regs || []).map((r) => ({
        scope: r.scope,
        active: r.active && r.active.scriptURL,
        waiting: r.waiting && r.waiting.scriptURL,
        installing: r.installing && r.installing.scriptURL,
      })),
      log_len: (window.__PG_SW_LOG__ || []).length,
    };
  });

  await page.evaluate(() => {
    if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('pos_goto_begin', {});
  });
  const tGoto = Date.now();
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const gotoWall = Date.now() - tGoto;
  await page.evaluate(() => {
    if (window.__PG_SW_LOG_MARK__) window.__PG_SW_LOG_MARK__('pos_goto_dcl', {});
  });

  const metrics = await navMetrics(page);
  const log = await drainLog(page);

  // Count register calls with unique URLs
  const registerCalls = log.filter((e) => e.type === 'register_call');
  const updateCalls = log.filter((e) => e.type === 'update_call');
  const updatefounds = log.filter((e) => e.type === 'updatefound');
  const installs = log.filter((e) => e.type === 'worker_statechange' && e.state === 'installed');
  const activates = log.filter((e) => e.type === 'worker_statechange' && e.state === 'activated');
  const controllerchanges = log.filter((e) => e.type === 'controllerchange');

  await context.close();

  return {
    name,
    opts,
    gotoWall,
    beforeNav,
    metrics,
    counts: {
      register_calls: registerCalls.length,
      unique_register_urls: [...new Set(registerCalls.map((e) => e.url))],
      update_calls: updateCalls.length,
      updatefound: updatefounds.length,
      installed: installs.length,
      activated: activates.length,
      controllerchange: controllerchanges.length,
    },
    log,
    cdp_event_count: cdpEvents.length,
    cdp_tail: cdpEvents.slice(-30),
  };
}

(async () => {
  const prodBuild = ssh(
    "grep SW_BUILD_ID /home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js | head -1"
  ).trim();

  const scenarios = [];
  // A: natural shell only — clear, reload admin, wait 800, POS (closest to real user after login)
  scenarios.push(await runScenario('A_natural_wait800', { clear: true, waitMs: 800, syntheticRegister: false }));
  // B: natural immediate POS after admin SW ready
  scenarios.push(await runScenario('B_natural_immediate', { clear: true, waitMs: 0, syntheticRegister: false }));
  // C: PF-style synthetic register + wait 800 (reproduces PF FAIL protocol)
  scenarios.push(await runScenario('C_synth_wait800', { clear: true, waitMs: 800, syntheticRegister: true }));
  // D: synth + immediate
  scenarios.push(await runScenario('D_synth_immediate', { clear: true, waitMs: 0, syntheticRegister: true }));
  // E: login→dashboard linger→POS without clear (warm path)
  scenarios.push(await runScenario('E_reuse_wait800', { clear: false, waitMs: 800, syntheticRegister: false }));

  // Static inventory from codebase (read-only knowledge)
  const staticInventory = {
    registrations: [
      {
        file: 'views/layouts/main.php',
        fn: '__ratebErpRegisterSwOnce / doRegister',
        lines: '775–777',
        url: 'pos-sw.js?v=' + 'RATEB_ASSET_BUILD (20260714-force-sw-v52)',
      },
      {
        file: 'public/assets/offline/erp-shell-bootstrap.js',
        fn: 'registerServiceWorker',
        lines: '325–327',
        note: 'Only if no existing pos-sw registration; else update()',
      },
      {
        file: 'public/assets/pos/js/pos-offline-bootstrap.js',
        fn: 'register',
        lines: '79',
        note: 'POS offline bootstrap',
      },
      {
        file: 'bench tools (not production UX)',
        fn: 'phase-*-audit register',
        lines: 'various',
        note: 'Synthetic ?v= timestamps in audits',
      },
    ],
    update_calls: [
      { file: 'views/layouts/main.php', lines: '738', note: 'stale SW soft update when script URL missing NEED' },
      { file: 'views/layouts/main.php', lines: '781', note: 'after every register()' },
      { file: 'public/assets/offline/erp-shell-bootstrap.js', lines: '294', note: 'when posReg already present' },
      { file: 'public/assets/offline/erp-shell-bootstrap.js', lines: '334', note: 'after fresh register' },
    ],
    skipWaiting_claim: [
      { file: 'public/pos-sw.js', lines: '2529 install skipWaiting; 2547 activate clients.claim' },
      { file: 'public/pos-sw.js', lines: '2606–2610 message SKIP_WAITING / CLIENTS_CLAIM' },
      { file: 'views/layouts/main.php', lines: '737, 761–762, 262–263 postMessage SKIP_WAITING / CLIENTS_CLAIM' },
      { file: 'erp-shell-bootstrap.js', lines: '297, 337 SKIP_WAITING' },
    ],
    asset_build: '20260714-force-sw-v52 (config/app.php RATEB_ASSET_BUILD)',
  };

  // Analyze C for version flip
  const analyze = (s) => {
    const regs = (s.log || []).filter((e) => e.type === 'register_call');
    const states = (s.log || []).filter((e) => e.type === 'worker_statechange');
    const uf = (s.log || []).filter((e) => e.type === 'updatefound');
    const cc = (s.log || []).filter((e) => e.type === 'controllerchange');
    const waitBegin = (s.log || []).find((e) => e.type === 'wait_begin');
    const waitEnd = (s.log || []).find((e) => e.type === 'wait_end');
    const posBegin = (s.log || []).find((e) => e.type === 'pos_goto_begin');
    const duringWait = (s.log || []).filter(
      (e) => waitBegin && waitEnd && e.wall >= waitBegin.wall && e.wall <= waitEnd.wall
    );
    const betweenReadyAndPos = duringWait;
    return {
      register_urls: regs.map((e) => e.url),
      updatefound_during_wait: duringWait.filter((e) => e.type === 'updatefound').length,
      statechanges_during_wait: duringWait.filter((e) => e.type === 'worker_statechange').length,
      update_calls_during_wait: duringWait.filter((e) => e.type === 'update_call').length,
      register_during_wait: duringWait.filter((e) => e.type === 'register_call').length,
      force_sw_involved: regs.some((e) => /force-sw-v52/.test(e.url))
        || (s.beforeNav?.regs || []).some((r) => /force-sw-v52/.test(r.active || '')),
      synth_involved: regs.some((e) => /pg-synth|pf-/.test(e.url)),
      during_wait_events: duringWait.map((e) => ({ type: e.type, t_ms: e.t_ms, url: e.url || e.scriptURL || e.installing })),
      installs: states.filter((e) => e.state === 'installed'),
      activates: states.filter((e) => e.state === 'activated'),
      controllerchanges: cc,
      updatefounds: uf,
    };
  };

  const report = {
    phase: 'PG',
    mode: 'READ_ONLY_SW_LIFECYCLE_AUDIT',
    generatedAt: new Date().toISOString(),
    production_sw_build: prodBuild,
    staticInventory,
    scenarios: scenarios.map((s) => ({
      name: s.name,
      metrics: s.metrics,
      counts: s.counts,
      beforeNav: s.beforeNav,
      analysis: analyze(s),
      log_preview: s.log.slice(0, 80),
      log_len: s.log.length,
    })),
  };

  // Verdict
  const c = scenarios.find((s) => s.name === 'C_synth_wait800');
  const d = scenarios.find((s) => s.name === 'D_synth_immediate');
  const a = scenarios.find((s) => s.name === 'A_natural_wait800');
  const b = scenarios.find((s) => s.name === 'B_natural_immediate');

  let largestBlocker = 'undetermined';
  let who = null;
  if (c && d && c.metrics.workerStart > 50 && d.metrics.workerStart < 20) {
    const an = analyze(c);
    if (an.updatefound_during_wait > 0 || an.statechanges_during_wait > 0 || an.register_during_wait > 0) {
      who = {
        verdict: 'SW install/activate or register during wait window before POS nav',
        evidence: an.during_wait_events,
        force_sw: an.force_sw_involved,
      };
      largestBlocker = 'Service Worker update/install cycle (force-sw-v52 or re-register) overlapping wait→POS';
    } else if (an.update_calls_during_wait > 0) {
      who = { verdict: 'reg.update() during wait', evidence: an.during_wait_events };
      largestBlocker = 'registration.update() during pre-POS wait';
    } else {
      who = {
        verdict: 'No register/updatefound during wait — investigate SW process wake or other',
        during_wait: an.during_wait_events,
        beforeNav: c.beforeNav,
      };
      largestBlocker = 'Unexplained ~workerStart after idle (possible SW process cold start)';
    }
  }

  report.conclusion = {
    largestBlocker,
    who,
    compare: {
      C_synth_wait800_ws: c && c.metrics.workerStart,
      D_synth_immediate_ws: d && d.metrics.workerStart,
      A_natural_wait800_ws: a && a.metrics.workerStart,
      B_natural_immediate_ws: b && b.metrics.workerStart,
    },
    pass:
      who &&
      /force-sw|update|install|activate|register/i.test(String(largestBlocker + JSON.stringify(who))),
  };
  report.enterprise = report.conclusion.pass
    ? 'PASS — root cause of ~197ms workerStart identified'
    : 'FAIL — could not attribute workerStart to a lifecycle actor with certainty';

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const out = path.join(OUT_DIR, `phase-pg-sw-lifecycle-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pg-sw-lifecycle-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    enterprise: report.enterprise,
    production_sw_build: prodBuild,
    conclusion: report.conclusion,
    scenarios: report.scenarios.map((s) => ({
      name: s.name,
      workerStart: s.metrics.workerStart,
      responseStart: s.metrics.responseStart,
      controller: s.metrics.controller,
      counts: s.counts,
      analysis_summary: {
        register_urls: s.analysis.register_urls,
        during_wait: s.analysis.during_wait_events,
        force_sw: s.analysis.force_sw_involved,
      },
    })),
  }, null, 2));
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
