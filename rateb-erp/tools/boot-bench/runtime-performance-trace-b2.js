'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const os = require('os');

const BASE = 'https://rateb.sa/rateb-erp/public/v2/index.html';

(async () => {
  const profileDir = path.join(os.tmpdir(), 'rateb-rpt-b2-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
  });

  let page = context.pages()[0] || await context.newPage();
  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  );
  await page.evaluate(async () => {
    if (navigator.serviceWorker) await navigator.serviceWorker.ready;
  });
  await page.waitForTimeout(1000);
  await page.close();

  await context.setOffline(true);
  page = await context.newPage();
  const errors = [];
  const cons = [];
  const failed = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => {
    if (m.type() === 'error') cons.push(String(m.text()).slice(0, 300));
  });
  page.on('response', (r) => {
    if (r.status() >= 400) {
      failed.push({
        status: r.status(),
        url: r.url().replace(/^https?:\/\/[^/]+/, ''),
        fromSW: r.fromServiceWorker(),
      });
    }
  });

  let gotoErr = null;
  try {
    await page.goto(BASE + '#/inventory', { waitUntil: 'domcontentloaded', timeout: 60000 });
  } catch (e) {
    gotoErr = String(e.message || e);
  }
  await page.waitForTimeout(4500);

  const snap = await page.evaluate(() => {
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const marks = (performance.getEntriesByType('mark') || []).map((m) => ({
      name: m.name,
      at: Math.round(m.startTime * 10) / 10,
    }));
    return {
      href: location.href,
      onLine: navigator.onLine,
      boot: (document.getElementById('boot-status') || {}).textContent || null,
      shell: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
      route: document.documentElement.getAttribute('data-rateb-v2-route-ready'),
      active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      outlet: outlet ? outlet.getAttribute('data-route') : null,
      text: outlet ? String(outlet.textContent || '').trim().slice(0, 220) : null,
      marks,
      globals: {
        runtime: !!window.RatebOfflineV2Runtime,
        db: !!window.RatebOfflineV2DB,
        dbOpen: !!(window.RatebOfflineV2DB && window.RatebOfflineV2DB.isOpen && window.RatebOfflineV2DB.isOpen()),
        identity: !!window.RatebOfflineV2Identity,
        inventory: !!window.RatebOfflineV2Inventory,
        business: !!window.RatebOfflineV2ActiveBusiness,
        sync: !!window.RatebOfflineV2ActiveSync,
        controller: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      },
    };
  });

  // Soft-nav attempt while offline if still on home
  const softOffline = await page.evaluate(async () => {
    const shell = window.RatebOfflineV2AppShell;
    const router = shell && shell.getRouter && shell.getRouter();
    if (!router) return { error: 'no_router' };
    const start = performance.now();
    const res = await router.navigate('/inventory');
    return {
      ms: Math.round((performance.now() - start) * 10) / 10,
      ok: !!(res && res.ok),
      reason: res && res.reason ? res.reason : null,
      active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      outlet: (document.getElementById('rateb-v2-shell-outlet') || {}).getAttribute
        ? document.getElementById('rateb-v2-shell-outlet').getAttribute('data-route')
        : null,
      identity: !!window.RatebOfflineV2Identity,
      inventory: !!window.RatebOfflineV2Inventory,
      business: !!window.RatebOfflineV2ActiveBusiness,
    };
  });

  // Problem A confirmation: document leave
  await context.setOffline(false);
  const pageA = await context.newPage();
  await pageA.goto(BASE + '#/inventory', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageA.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-active-module') === 'inventory',
    null,
    { timeout: 90000 }
  ).catch(() => null);
  await pageA.waitForTimeout(2000);
  const soft = await pageA.evaluate(async () => {
    const r = window.RatebOfflineV2AppShell.getRouter();
    await r.navigate('/');
    const s = performance.now();
    await r.navigate('/inventory');
    return Math.round((performance.now() - s) * 10) / 10;
  });
  await pageA.goto('about:blank');
  const tHard = Date.now();
  await pageA.goto(BASE + '#/inventory', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageA.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  );
  await pageA.waitForTimeout(2500);
  const hardMs = Date.now() - tHard;
  const hardSnap = await pageA.evaluate(() => {
    const marks = (performance.getEntriesByType('mark') || []);
    const at = (n) => {
      const m = marks.find((x) => x.name === n);
      return m ? Math.round(m.startTime * 10) / 10 : null;
    };
    return {
      active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      shellReady: at('rateb-v2-shell-ready'),
      dbReady: at('rateb-v2-db-ready'),
      activeReady: at('rateb-v2-active-module-ready'),
      backgroundStart: at('rateb-v2-background-start'),
    };
  });

  const out = {
    gotoErr,
    errors,
    cons: cons.slice(0, 20),
    failed: failed.slice(0, 30),
    snap,
    softOffline,
    soft_ms: soft,
    hard_leave_return_ms: hardMs,
    hardSnap,
  };
  const outPath = path.join(__dirname, 'reports', 'runtime-performance-trace-b2-' + Date.now() + '.json');
  fs.writeFileSync(outPath, JSON.stringify(out, null, 2));
  console.log(JSON.stringify({
    outPath,
    gotoErr,
    boot: snap.boot,
    active: snap.active,
    outlet: snap.outlet,
    text: snap.text,
    globals: snap.globals,
    failed: failed.slice(0, 20),
    marks: snap.marks,
    softOffline,
    soft_ms: soft,
    hard_ms: hardMs,
    hardSnap,
    errors,
    cons: cons.slice(0, 15),
  }, null, 2));
  await context.close();
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
