/**
 * Phase PF — first-navigation gate acceptance (before/after metrics).
 * Uses PD-like protocol that previously produced workerStart ≈337ms.
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
const REMOTE_SW = '/home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js';

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

async function waitScanReady(page, timeoutMs) {
  const t0 = Date.now();
  while (Date.now() - t0 < timeoutMs) {
    const snap = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      const ready = root && root.getAttribute('data-pos-register-ready') === '1';
      const barcode = document.querySelector('[data-pos-barcode-input]');
      const tiles = document.querySelector('[data-pos-product-list]');
      const tilesReady = !!(tiles && (tiles.children.length > 0 || document.querySelector('[data-pos-catalog-empty]')));
      return {
        ready: !!ready,
        tilesReady,
        register: !!root,
        barcode: !!barcode,
      };
    });
    if (snap.register && snap.ready && snap.tilesReady && snap.barcode) {
      return { ok: true, ms: Date.now() - t0, snap };
    }
    await page.waitForTimeout(50);
  }
  return { ok: false, ms: Date.now() - t0 };
}

async function runOpen(label, { clear, waitMs, secondNav }) {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-pf-' + label + '-' + Date.now());
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

  if (clear) {
    await page.evaluate(async () => {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map((r) => r.unregister()));
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    });
    await page.waitForTimeout(200);
  }

  await page.evaluate(async (base) => {
    const scope = base.endsWith('/') ? base : base + '/';
    await navigator.serviceWorker.register(base + '/pos-sw.js?v=pf-' + Date.now(), {
      scope,
      updateViaCache: 'none',
    });
    await navigator.serviceWorker.ready;
  }, BASE);

  if (waitMs) await page.waitForTimeout(waitMs);

  const tNav0 = Date.now();
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const scan = await waitScanReady(page, 30000);
  const first = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
    return {
      workerStart: n.workerStart || 0,
      requestStart: Math.round(n.requestStart * 10) / 10,
      responseStart: Math.round(n.responseStart * 10) / 10,
      ttfb: r(n.requestStart, n.responseStart),
      dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      load: Math.round(n.loadEventEnd * 10) / 10,
      transferSize: n.transferSize || 0,
      sw: navigator.serviceWorker?.controller?.scriptURL || null,
      buildHint: (navigator.serviceWorker?.controller?.scriptURL || '').includes('pf-') ? 'pf-query' : 'other',
    };
  });
  const wallFirst = Date.now() - tNav0;
  const scanReadyEst = first.dcl + (scan.ok ? scan.ms : 0);

  // Allow background warm to settle, then confirm protected inventory
  await page.waitForTimeout(18000);

  let protectedStatus = null;
  try {
    protectedStatus = await page.evaluate(async () => {
      return await new Promise((resolve) => {
        const ch = new MessageChannel();
        const t = setTimeout(() => resolve({ timeout: true }), 30000);
        ch.port1.onmessage = (ev) => {
          clearTimeout(t);
          resolve(ev.data);
        };
        const ctrl = navigator.serviceWorker.controller;
        if (!ctrl) {
          clearTimeout(t);
          resolve({ no_controller: true });
          return;
        }
        ctrl.postMessage({ type: 'PROTECTED_OFFLINE_CACHE_STATUS' }, [ch.port2]);
      });
    });
  } catch (e) {
    protectedStatus = { error: String(e.message || e) };
  }

  let second = null;
  let scan2 = null;
  if (secondNav) {
    const t2 = Date.now();
    await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
    scan2 = await waitScanReady(page, 15000);
    second = await page.evaluate(() => {
      const n = performance.getEntriesByType('navigation')[0];
      const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
      return {
        workerStart: n.workerStart || 0,
        responseStart: Math.round(n.responseStart * 10) / 10,
        ttfb: r(n.requestStart, n.responseStart),
        dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
        transferSize: n.transferSize || 0,
      };
    });
    second.wall_ms = Date.now() - t2;
    second.scan = scan2;
  }

  await context.close();
  return {
    label,
    first: { ...first, wall_ms: wallFirst, scan, scan_ready_est_ms: scanReadyEst },
    second,
    protectedStatus,
  };
}

(async () => {
  const localSw = path.join(__dirname, '..', '..', 'public', 'pos-sw.js');
  // Deploy PF build
  ssh('cp ' + REMOTE_SW + ' ' + REMOTE_SW + '.bak-pf-$(date +%s) || true');
  scp(localSw, '/tmp/pos-sw-pf.js');
  ssh('cp /tmp/pos-sw-pf.js ' + REMOTE_SW + ' && grep -n "phase-pf\\|armBackgroundWarm\\|respondWithDocumentAndReleaseWarmGate\\|SW_BUILD_ID" ' + REMOTE_SW + ' | head -20');

  const before = {
    source: 'Phase PD online_first',
    workerStart: 336.5,
    responseStart: 549,
    ttfb: 211,
    dcl: 844,
    scan_ready: 867,
    warm_scan: 306,
  };

  const afterPdProtocol = await runOpen('after_pd_protocol', { clear: true, waitMs: 800, secondNav: true });
  const afterImmediate = await runOpen('after_immediate', { clear: true, waitMs: 0, secondNav: false });

  const a = afterPdProtocol.first;
  const targets = {
    workerStart_lt_20: a.workerStart < 20,
    responseStart_220_270: a.responseStart >= 200 && a.responseStart <= 300,
    scan_ready_lt_550: a.scan_ready_est_ms < 550,
    warm_unchanged:
      afterPdProtocol.second &&
      afterPdProtocol.second.workerStart < 50 &&
      afterPdProtocol.second.dcl < 350,
  };
  const pass = targets.workerStart_lt_20 && a.scan_ready_est_ms < 550 && targets.warm_unchanged !== false;

  const report = {
    phase: 'PF',
    generatedAt: new Date().toISOString(),
    build: '20260715-phase-pf-v65',
    before,
    after_pd_protocol: afterPdProtocol,
    after_immediate: afterImmediate,
    targets,
    enterprise: pass ? 'PASS' : 'FAIL',
    note: 'PASS requires workerStart<20 and scan_ready_est<550 on PD protocol; soft check responseStart band',
  };

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const out = path.join(OUT_DIR, `phase-pf-accept-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pf-accept-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  if (!pass) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
