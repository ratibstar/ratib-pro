/**
 * PERF-P1 — focused nav timing (RatebNavInstant.navigate API).
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

const MODULES = [
  { id: 'hr', path: '/admin/hr/attendance?company_id=22' },
  { id: 'inventory', path: '/admin/ops/inventory?company_id=22' },
  { id: 'accounting', path: '/admin/ops/accounting?company_id=22' },
  { id: 'dashboard', path: '/admin/' },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const t0 = Date.now();
  let mint;
  try {
    mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  } catch (e) {
    mint = JSON.parse(ssh('php /tmp/remote-auth.php mint'));
  }

  const context = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'rateb-p1b-' + t0), {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await context.clearCookies();
  await context.addCookies([{
    name: mint.session_name || 'rateb_erp',
    value: mint.session_id,
    domain: 'rateb.sa',
    path: '/',
    httpOnly: true,
    secure: true,
  }]);
  const page = context.pages()[0] || await context.newPage();

  await page.goto(BASE + '/admin/', { waitUntil: 'networkidle', timeout: 180000 }).catch(async () => {
    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  });
  await page.waitForTimeout(2500);

  const boot = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.ready;
    if (reg.active) {
      await new Promise((resolve) => {
        const ch = new MessageChannel();
        const t = setTimeout(resolve, 60000);
        ch.port1.onmessage = () => { clearTimeout(t); resolve(); };
        reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
      });
    }
    return {
      hasNav: !!window.RatebNavInstant,
      hasSidebar: !!document.querySelector('#rateb-sidebar'),
      company: (window.__RATEB_ERP_SHELL_OFFLINE__ || {}).company_id || null,
      sw: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
    };
  });

  // Prefetch all module URLs
  await page.evaluate(async (urls) => {
    if (!window.RatebNavInstant) return;
    for (const u of urls) {
      window.RatebNavInstant.prefetch(u);
    }
    await new Promise((r) => setTimeout(r, 2500));
  }, MODULES.map((m) => BASE + m.path));

  const online = {};
  for (const mod of MODULES) {
    const href = BASE + mod.path;
    // First navigation (may warm cache)
    const first = await page.evaluate(async (h) => {
      const t0 = performance.now();
      let ok = false;
      let err = null;
      try {
        if (!window.RatebNavInstant) throw new Error('no_nav');
        ok = await window.RatebNavInstant.navigate(h, { replace: true });
      } catch (e) {
        err = String(e && e.message ? e.message : e);
      }
      return {
        ms: Math.round(performance.now() - t0),
        ok,
        err,
        href: location.href,
        mainLen: (document.querySelector('#rateb-main-content') || {}).innerText
          ? document.querySelector('#rateb-main-content').innerText.length
          : 0,
        mem: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576 * 10) / 10 : null,
      };
    }, href);
    await page.waitForTimeout(300);
    const warm = await page.evaluate(async (h) => {
      const t0 = performance.now();
      let ok = false;
      try {
        ok = await window.RatebNavInstant.navigate(h, { replace: true });
      } catch (e) {
        return { ms: Math.round(performance.now() - t0), ok: false, err: String(e.message || e) };
      }
      return {
        ms: Math.round(performance.now() - t0),
        ok,
        href: location.href,
        mainLen: (document.querySelector('#rateb-main-content') || {}).innerText
          ? document.querySelector('#rateb-main-content').innerText.length
          : 0,
        mem: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576 * 10) / 10 : null,
      };
    }, href);
    online[mod.id] = { first, warm };
  }

  // Offline
  await context.setOffline(true);
  const offline = {};
  for (const mod of MODULES) {
    const href = BASE + mod.path;
    const cold = await page.evaluate(async (h) => {
      const t0 = performance.now();
      let ok = false;
      let err = null;
      try {
        ok = await window.RatebNavInstant.navigate(h, { replace: true });
      } catch (e) {
        err = String(e.message || e);
      }
      return {
        ms: Math.round(performance.now() - t0),
        ok,
        err,
        uncached: !!document.querySelector('[data-rateb-uncached-page]'),
        mainLen: (document.querySelector('#rateb-main-content') || {}).innerText
          ? document.querySelector('#rateb-main-content').innerText.length
          : 0,
      };
    }, href);
    await page.waitForTimeout(200);
    const warm = await page.evaluate(async (h) => {
      const t0 = performance.now();
      const ok = await window.RatebNavInstant.navigate(h, { replace: true });
      return { ms: Math.round(performance.now() - t0), ok };
    }, href);
    offline[mod.id] = { cold, warm };
  }

  const firstMs = Object.values(online).map((r) => r.first.ms);
  const warmMs = Object.values(online).map((r) => r.warm.ms);
  const offWarm = Object.values(offline).map((r) => r.warm.ms);
  const avg = (a) => Math.round(a.reduce((x, y) => x + y, 0) / a.length);

  const targets = {
    online_first_lt_500: firstMs.every((m) => m < 500),
    online_warm_lt_200: warmMs.every((m) => m < 200),
    offline_warm_lt_150: offWarm.every((m) => m < 150),
    avg_online_first_ms: avg(firstMs),
    avg_online_warm_ms: avg(warmMs),
    avg_offline_warm_ms: avg(offWarm),
  };

  const report = {
    phase: 'PERF-P1',
    at: new Date().toISOString(),
    boot,
    targets,
    online,
    offline,
    elapsed_ms: Date.now() - t0,
  };
  const out = path.join(OUT_DIR, 'phase-p1-nav-bench-' + t0 + '.json');
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-p1-nav-bench-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, boot, targets, online, offline }, null, 2));
  await context.close();
  const pass = targets.online_first_lt_500 && targets.online_warm_lt_200 && targets.offline_warm_lt_150;
  process.exit(pass ? 0 : 2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
