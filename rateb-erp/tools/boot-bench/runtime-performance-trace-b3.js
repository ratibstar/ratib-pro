'use strict';
/**
 * Problem B focus: where offline boot first blocks when ERP "does not open".
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const os = require('os');

const BASE = 'https://rateb.sa/rateb-erp/public/v2/index.html';
const CLASSIC = 'https://rateb.sa/rateb-erp/public/offline-shell.html';

async function tryOpen(label, url, { prewarm, offlineFirst }) {
  const profileDir = path.join(os.tmpdir(), 'rateb-rpt-b3-' + label + '-' + Date.now());
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
  });
  const failed = [];
  const errors = [];
  const cons = [];
  let page = context.pages()[0] || await context.newPage();
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => {
    if (m.type() === 'error') cons.push(String(m.text()).slice(0, 240));
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
  page.on('requestfailed', (req) => {
    failed.push({
      status: 'failed',
      url: req.url().replace(/^https?:\/\/[^/]+/, ''),
      error: req.failure() && req.failure().errorText,
    });
  });

  if (prewarm) {
    await context.setOffline(false);
    await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
    await page.waitForTimeout(2000);
    await page.evaluate(async () => {
      if (navigator.serviceWorker) await navigator.serviceWorker.ready;
    }).catch(() => null);
    await page.close();
    page = await context.newPage();
    page.on('pageerror', (e) => errors.push(String(e.message || e)));
    page.on('response', (r) => {
      if (r.status() >= 400) {
        failed.push({
          status: r.status(),
          url: r.url().replace(/^https?:\/\/[^/]+/, ''),
          fromSW: r.fromServiceWorker(),
        });
      }
    });
    page.on('requestfailed', (req) => {
      failed.push({
        status: 'failed',
        url: req.url().replace(/^https?:\/\/[^/]+/, ''),
        error: req.failure() && req.failure().errorText,
      });
    });
  }

  if (offlineFirst) {
    await context.setOffline(true);
  }

  let gotoErr = null;
  const t0 = Date.now();
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  } catch (e) {
    gotoErr = String(e.message || e);
  }
  await page.waitForTimeout(3500);

  const snap = await page.evaluate(() => {
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const boot = document.getElementById('boot-status');
    return {
      href: location.href,
      title: document.title,
      readyState: document.readyState,
      bodyText: String((document.body && document.body.innerText) || '').trim().slice(0, 400),
      boot: boot ? boot.textContent : null,
      shell: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
      active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      outlet: outlet ? outlet.getAttribute('data-route') : null,
      outletText: outlet ? String(outlet.textContent || '').trim().slice(0, 200) : null,
      marks: (performance.getEntriesByType('mark') || []).map((m) => m.name),
      onLine: navigator.onLine,
      hasSW: !!navigator.serviceWorker,
      controller: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      globals: {
        runtime: typeof window.RatebOfflineV2Runtime !== 'undefined',
        db: typeof window.RatebOfflineV2DB !== 'undefined',
        identity: typeof window.RatebOfflineV2Identity !== 'undefined',
        business: typeof window.RatebOfflineV2ActiveBusiness !== 'undefined',
        offlineAuth: typeof window.RatebOfflineAuthLock !== 'undefined',
        offlineSdk: typeof window.RatebOffline !== 'undefined',
      },
    };
  }).catch((e) => ({ evaluate_error: String(e.message || e) }));

  // If home-only V2, try clicking/navigating to inventory
  let navAttempt = null;
  if (snap && snap.shell === '1' && !snap.active) {
    navAttempt = await page.evaluate(async () => {
      const shell = window.RatebOfflineV2AppShell;
      const router = shell && shell.getRouter && shell.getRouter();
      if (!router) return { error: 'no_router' };
      const res = await router.navigate('/inventory');
      return {
        ok: !!(res && res.ok),
        reason: res && res.reason ? res.reason : null,
        active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
        outlet: (document.getElementById('rateb-v2-shell-outlet') || {}).getAttribute
          ? document.getElementById('rateb-v2-shell-outlet').getAttribute('data-route')
          : null,
        identityLoaded: !!window.RatebOfflineV2Identity,
        inventoryLoaded: !!window.RatebOfflineV2Inventory,
        business: !!window.RatebOfflineV2ActiveBusiness,
      };
    }).catch((e) => ({ error: String(e.message || e) }));
  }

  await context.close();
  return {
    label,
    url,
    prewarm: !!prewarm,
    offlineFirst: !!offlineFirst,
    wall_ms: Date.now() - t0,
    gotoErr,
    errors: errors.slice(0, 20),
    cons: cons.slice(0, 20),
    failed: failed.slice(0, 40),
    snap,
    navAttempt,
  };
}

(async () => {
  const cases = [];
  // Never-visited profile, offline first — true cold offline
  cases.push(await tryOpen('v2-home-cold-offline', BASE + '#/', { prewarm: false, offlineFirst: true }));
  cases.push(await tryOpen('v2-inv-cold-offline', BASE + '#/inventory', { prewarm: false, offlineFirst: true }));
  cases.push(await tryOpen('classic-offline-cold', CLASSIC, { prewarm: false, offlineFirst: true }));
  // Prewarmed then offline home — modules never activate
  cases.push(await tryOpen('v2-home-warmSW-offline', BASE + '#/', { prewarm: true, offlineFirst: true }));
  cases.push(await tryOpen('classic-warmSW-offline', CLASSIC, { prewarm: true, offlineFirst: true }));

  const outPath = path.join(__dirname, 'reports', 'runtime-performance-trace-b3-' + Date.now() + '.json');
  fs.writeFileSync(outPath, JSON.stringify({ generated_at: new Date().toISOString(), cases }, null, 2));
  console.log(JSON.stringify({
    outPath,
    cases: cases.map((c) => ({
      label: c.label,
      gotoErr: c.gotoErr,
      boot: c.snap && c.snap.boot,
      shell: c.snap && c.snap.shell,
      active: c.snap && c.snap.active,
      outlet: c.snap && c.snap.outlet,
      controller: c.snap && c.snap.controller,
      body: c.snap && c.snap.bodyText,
      marks: c.snap && c.snap.marks,
      globals: c.snap && c.snap.globals,
      navAttempt: c.navAttempt,
      failed: (c.failed || []).slice(0, 12),
      errors: c.errors,
    })),
  }, null, 2));
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
