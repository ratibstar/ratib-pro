/**
 * Phase PC — acceptance: SW startup + first/warm POS nav after update.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

function waitForBuild(page, buildId, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  return (async () => {
    while (Date.now() < deadline) {
      const text = await page.evaluate(async (url) => {
        try {
          const r = await fetch(url + '?t=' + Date.now(), { cache: 'no-store' });
          return await r.text();
        } catch (e) {
          return '';
        }
      }, BASE + '/pos-sw.js');
      if (text.indexOf(buildId) !== -1) return true;
      await page.waitForTimeout(5000);
    }
    return false;
  })();
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-pc-accept-' + Date.now());
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

  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
  const live = await waitForBuild(page, 'phase-pc-v63', 180000);
  if (!live) {
    console.log(JSON.stringify({ ok: false, error: 'SW_BUILD_NOT_LIVE' }, null, 2));
    await context.close();
    process.exit(2);
  }

  // Fresh SW cycle
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  });
  await page.waitForTimeout(300);

  const lifecycle = await page.evaluate(async (base) => {
    const t0 = performance.now();
    const marks = [];
    const mark = (n) => marks.push({ name: n, t: Math.round(performance.now() - t0) });
    mark('register_start');
    const reg = await navigator.serviceWorker.register(base + '/pos-sw.js?v=pc-' + Date.now(), {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    mark('register_returned');
    const sw = reg.installing || reg.waiting || reg.active;
    if (sw) {
      mark('state_' + sw.state);
      await new Promise((resolve) => {
        if (sw.state === 'activated') {
          resolve();
          return;
        }
        sw.addEventListener('statechange', () => {
          marks.push({ name: 'state_' + sw.state, t: Math.round(performance.now() - t0) });
          if (sw.state === 'activated' || sw.state === 'redundant') resolve();
        });
      });
    }
    mark('activated');
    await navigator.serviceWorker.ready;
    mark('ready');
    let controllerMs = null;
    const deadline = Date.now() + 10000;
    while (Date.now() < deadline) {
      if (navigator.serviceWorker.controller) {
        controllerMs = Math.round(performance.now() - t0);
        break;
      }
      await new Promise((r) => setTimeout(r, 20));
    }
    return {
      marks,
      register_to_activated_ms: marks.find((m) => m.name === 'activated')?.t,
      register_to_ready_ms: marks.find((m) => m.name === 'ready')?.t,
      controller_ms: controllerMs,
      build_probe: true,
    };
  }, BASE);

  async function navOnce(label) {
    const t0 = Date.now();
    const resp = await page.goto(BASE + '/admin/ops/pos/register?company_id=22', {
      waitUntil: 'domcontentloaded',
      timeout: 90000,
    });
    const timing = await page.evaluate(() => {
      const n = performance.getEntriesByType('navigation')[0];
      const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
      return {
        workerStart: n.workerStart,
        responseStart: Math.round(n.responseStart * 10) / 10,
        ttfb: r(n.requestStart, n.responseStart),
        download: r(n.responseStart, n.responseEnd),
        protocol: n.nextHopProtocol,
      };
    });
    return {
      label,
      wall_ms: Date.now() - t0,
      status: resp?.status(),
      fromSW: resp?.fromServiceWorker(),
      register: await page.evaluate(() => !!document.querySelector('[data-pos-register]')),
      timing,
    };
  }

  const first = await navOnce('first_after_sw_update');
  const warm1 = await navOnce('warm1');
  const warm2 = await navOnce('warm2');

  // Protected cache eventually present
  await page.waitForTimeout(8000);
  const protectedStatus = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    const ctrl = navigator.serviceWorker.controller || (reg && reg.active);
    if (!ctrl) return { ok: false, error: 'no_controller' };
    return new Promise((resolve) => {
      const ch = new MessageChannel();
      const timer = setTimeout(() => resolve({ ok: false, error: 'timeout' }), 30000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(timer);
        resolve(ev.data || {});
      };
      ctrl.postMessage({ type: 'PROTECTED_OFFLINE_CACHE_STATUS' }, [ch.port2]);
    });
  });

  const report = {
    phase: 'PC',
    measuredAt: new Date().toISOString(),
    build: '20260715-phase-pc-v63',
    before: {
      register_to_activated_ms: 21131,
      ensure_protected_ms: 6183,
      nav_with_sw_responseStart_ms: 5313,
    },
    lifecycle,
    navigations: { first, warm1, warm2 },
    protectedStatus,
    acceptance: {
      sw_startup_lt_200: (lifecycle.register_to_activated_ms || 9999) < 200,
      first_nav_lt_700: (first.timing?.responseStart || 9999) < 700,
      warm_nav_lt_300: (warm2.timing?.responseStart || 9999) < 300,
      protected_ok: !!(protectedStatus && protectedStatus.ok),
      register_html: !!(first.register && warm2.register),
    },
  };
  report.acceptance.pass =
    report.acceptance.sw_startup_lt_200 &&
    report.acceptance.first_nav_lt_700 &&
    report.acceptance.warm_nav_lt_300 &&
    report.acceptance.register_html;

  const out = path.join(OUT_DIR, `phase-pc-accept-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pc-accept-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, acceptance: report.acceptance, lifecycle, first: first.timing, warm2: warm2.timing, protected: protectedStatus && protectedStatus.ok }, null, 2));
  await context.close();
  process.exit(report.acceptance.pass ? 0 : 1);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
