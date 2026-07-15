/**
 * Phase PC — profile SW install/activate/ensureProtectedOfflineCache (READ measure).
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT = path.join(__dirname, 'reports', `phase-pc-profile-${Date.now()}.json`);

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

(async () => {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-pc-profile-' + Date.now());
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

  // Fresh SW: unregister all, clear caches, then register and time readiness
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  });
  await page.waitForTimeout(500);

  const timing = await page.evaluate(async (base) => {
    const marks = [];
    const t0 = performance.now();
    const mark = (name) => marks.push({ name, t: Math.round(performance.now() - t0) });

    mark('register_start');
    const reg = await navigator.serviceWorker.register(base + '/pos-sw.js?v=pc-profile-' + Date.now(), {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    mark('register_returned');

    // Wait for installing → installed → activating → activated
    const sw = reg.installing || reg.waiting || reg.active;
    if (sw) {
      mark('sw_state_' + sw.state);
      await new Promise((resolve) => {
        if (sw.state === 'activated') {
          resolve();
          return;
        }
        sw.addEventListener('statechange', () => {
          marks.push({ name: 'sw_state_' + sw.state, t: Math.round(performance.now() - t0) });
          if (sw.state === 'activated' || sw.state === 'redundant') resolve();
        });
      });
    }
    mark('after_state_activated');

    await navigator.serviceWorker.ready;
    mark('navigator_ready');

    // Controllers.claim implies controller may appear
    let controllerAt = null;
    const deadline = Date.now() + 30000;
    while (Date.now() < deadline) {
      if (navigator.serviceWorker.controller) {
        controllerAt = Math.round(performance.now() - t0);
        break;
      }
      await new Promise((r) => setTimeout(r, 50));
    }
    mark('controller_' + (controllerAt != null ? controllerAt : 'timeout'));

    // Measure ensureProtectedOfflineCache via message
    const warmStart = performance.now();
    const warmResult = await new Promise((resolve) => {
      const ch = new MessageChannel();
      const timer = setTimeout(() => resolve({ ok: false, error: 'timeout', ms: Math.round(performance.now() - warmStart) }), 120000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(timer);
        resolve(Object.assign({ ms: Math.round(performance.now() - warmStart) }, ev.data || {}));
      };
      const ctrl = navigator.serviceWorker.controller || reg.active;
      if (!ctrl) {
        clearTimeout(timer);
        resolve({ ok: false, error: 'no_controller' });
        return;
      }
      ctrl.postMessage({ type: 'ENSURE_PROTECTED_OFFLINE_CACHE' }, [ch.port2]);
    });
    mark('after_ensure_protected');

    // Navigate timing with SW controlling
    const nav0 = performance.now();
    await new Promise((resolve) => {
      // use location assign from page context in parent — just mark, parent will goto
      resolve();
    });

    return {
      marks,
      register_to_activated_ms: marks.find((m) => m.name === 'after_state_activated')?.t,
      register_to_ready_ms: marks.find((m) => m.name === 'navigator_ready')?.t,
      controller_ms: controllerAt,
      ensure_protected: warmResult,
      protected_rel_count_hint: warmResult?.inventory?.length || warmResult?.missing?.length,
    };
  }, BASE);

  // Cold navigation after SW activated
  const navT0 = Date.now();
  const resp = await page.goto(BASE + '/admin/ops/pos/register?company_id=22', {
    waitUntil: 'domcontentloaded',
    timeout: 120000,
  });
  const navTiming = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    return {
      workerStart: n.workerStart,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      fromSW: !!(n.workerStart > 0),
    };
  });

  const report = {
    phase: 'PC_PROFILE',
    measuredAt: new Date().toISOString(),
    sw_lifecycle: timing,
    navigation: {
      wall_ms: Date.now() - navT0,
      status: resp?.status(),
      fromSW: resp?.fromServiceWorker(),
      ...navTiming,
    },
    bottleneck: {
      what: 'install/activate waitUntil → ensureProtectedOfflineCache serial fetch of protected assets',
      evidence_ms: {
        register_to_activated: timing.register_to_activated_ms,
        ensure_protected_alone: timing.ensure_protected?.ms,
        workerStart_on_nav: navTiming.workerStart,
        responseStart: navTiming.responseStart,
      },
    },
  };

  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(__dirname, 'reports', 'phase-pc-profile-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await context.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
