/**
 * Phase PE B — isolate workerStart cause vs background warm (no SW source changes).
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

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

async function runScenario(name, opts) {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-peb-' + name + '-' + Date.now());
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
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });

  if (opts.clear) {
    await page.evaluate(async () => {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map((r) => r.unregister()));
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    });
    await page.waitForTimeout(200);
  }

  if (opts.register) {
    await page.evaluate(async (base) => {
      const scope = base.endsWith('/') ? base : base + '/';
      await navigator.serviceWorker.register(base + '/pos-sw.js?v=peb-' + Date.now(), {
        scope,
        updateViaCache: 'none',
      });
      await navigator.serviceWorker.ready;
    }, BASE);
  }

  if (opts.waitMs) await page.waitForTimeout(opts.waitMs);

  // Optional: force warm busy via message before nav
  if (opts.forceWarm) {
    await page.evaluate(async () => {
      const reg = await navigator.serviceWorker.getRegistration();
      const ctrl = navigator.serviceWorker.controller || (reg && reg.active);
      if (!ctrl) return;
      ctrl.postMessage({ type: 'ENSURE_PROTECTED_OFFLINE_CACHE' });
    });
    await page.waitForTimeout(opts.forceWarmDelayMs || 100);
  }

  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const metrics = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    const ready = document.querySelector('[data-pos-register]')?.getAttribute('data-pos-register-ready') === '1';
    return {
      workerStart: n.workerStart || 0,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      load: Math.round(n.loadEventEnd * 10) / 10,
      transferSize: n.transferSize || 0,
      sw: navigator.serviceWorker?.controller?.scriptURL || null,
      ready,
      register: !!document.querySelector('[data-pos-register]'),
    };
  });

  // Scan ready poll
  const tScan0 = Date.now();
  let scanMs = null;
  for (let i = 0; i < 80; i++) {
    const ok = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      return !!(root && root.getAttribute('data-pos-register-ready') === '1' && document.querySelector('[data-pos-barcode-input]'));
    });
    if (ok) {
      scanMs = Date.now() - tScan0;
      break;
    }
    await page.waitForTimeout(50);
  }

  await context.close();
  return { name, opts, metrics, scan_after_dcl_ms: scanMs, scan_ready_est_ms: metrics.dcl + (scanMs || 0) };
}

(async () => {
  const scenarios = [];
  scenarios.push(await runScenario('A_clear_register_nav_immediate', { clear: true, register: true, waitMs: 0 }));
  scenarios.push(await runScenario('B_clear_register_wait800_nav', { clear: true, register: true, waitMs: 800 }));
  scenarios.push(await runScenario('C_clear_register_forceWarm_nav', { clear: true, register: true, waitMs: 50, forceWarm: true, forceWarmDelayMs: 50 }));
  scenarios.push(await runScenario('D_reuse_no_clear_wait800', { clear: false, register: true, waitMs: 800 }));
  scenarios.push(await runScenario('E_warm_second_nav', { clear: false, register: true, waitMs: 2000 }));
  // second nav of E as warm
  // redo E as two-step
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-peb-double-' + Date.now());
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
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  });
  await page.evaluate(async (base) => {
    await navigator.serviceWorker.register(base + '/pos-sw.js?v=peb2-' + Date.now(), {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    await navigator.serviceWorker.ready;
  }, BASE);
  await page.waitForTimeout(800);
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const first = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return { workerStart: n.workerStart || 0, responseStart: Math.round(n.responseStart * 10) / 10, ttfb: Math.max(0, Math.round((n.responseStart - n.requestStart) * 10) / 10), sw: navigator.serviceWorker?.controller?.scriptURL };
  });
  await page.waitForTimeout(15000); // let warm finish
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const second = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return { workerStart: n.workerStart || 0, responseStart: Math.round(n.responseStart * 10) / 10, ttfb: Math.max(0, Math.round((n.responseStart - n.requestStart) * 10) / 10), transferSize: n.transferSize, dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10 };
  });
  await context.close();
  scenarios.push({ name: 'F_pd_protocol_first_then_warm', first, second });

  const report = { phase: 'PE_B', generatedAt: new Date().toISOString(), scenarios };
  const out = path.join(OUT_DIR, `phase-pe-isolate-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pe-isolate-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
