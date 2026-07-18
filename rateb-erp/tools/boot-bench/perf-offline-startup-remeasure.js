/**
 * Offline ERP cold/warm startup after proper SW + IDB seed (measure only).
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
const OUT = path.join(__dirname, 'reports', 'perf-offline-startup-' + Date.now() + '.json');
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

(async () => {
  const mint = JSON.parse(
    ssh('php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint')
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'rateb-off-perf-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1400, height: 900 },
  });
  await ctx.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
    },
  ]);
  const page = ctx.pages()[0] || (await ctx.newPage());
  const marks = [];
  const mark = (name, extra) => marks.push(Object.assign({ name, t: Date.now() }, extra || {}));

  await page.addInitScript(() => {
    window.__P = { lsR: 0, lsW: 0, idb: 0, long: [] };
    try {
      const oG = Storage.prototype.getItem;
      const oS = Storage.prototype.setItem;
      Storage.prototype.getItem = function () {
        window.__P.lsR++;
        return oG.apply(this, arguments);
      };
      Storage.prototype.setItem = function () {
        window.__P.lsW++;
        return oS.apply(this, arguments);
      };
    } catch (e) {}
    try {
      const o = indexedDB.open.bind(indexedDB);
      indexedDB.open = function () {
        window.__P.idb++;
        return o.apply(indexedDB, arguments);
      };
    } catch (e2) {}
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) =>
          window.__P.long.push({ start: Math.round(e.startTime), dur: Math.round(e.duration) })
        );
      }).observe({ type: 'longtask', buffered: true });
    } catch (e3) {}
  });

  /* Seed online Admin so offline unlock has session/cache */
  mark('online_admin_start');
  await page.goto(BASE + '/admin', { waitUntil: 'networkidle', timeout: 120000 }).catch(() =>
    page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 })
  );
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar', { timeout: 60000 });
  mark('online_admin_ready');

  /* Wait for SW controlling */
  await page.evaluate(async () => {
    if (!navigator.serviceWorker) return false;
    const ready = await Promise.race([
      navigator.serviceWorker.ready,
      new Promise((r) => setTimeout(() => r(null), 20000)),
    ]);
    return !!(ready && navigator.serviceWorker.controller);
  });
  await page.waitForTimeout(4000);
  mark('sw_warmed');

  /* Visit offline-shell online first to seed unlock */
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(5000);
  mark('offline_shell_online_visit');

  /* Back to admin to ensure rateb-offline.js ran */
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(3000);

  const seedHooks = await page.evaluate(() => window.__P);

  /* COLD OFFLINE */
  await ctx.setOffline(true);
  const t0 = Date.now();
  mark('offline_goto');
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const dcl = Date.now() - t0;

  /* Wait for either shell unlock or status message change */
  let unlockMs = null;
  let unlockState = null;
  try {
    await page.waitForFunction(
      () => {
        const root = document.getElementById('shell-root');
        const status = document.getElementById('offline-status');
        const main = document.querySelector('#rateb-offline-shell-main, .rateb-offline-home, aside.rateb-offline-shell-nav');
        const msg = (document.getElementById('msg') || {}).textContent || '';
        if (root && !root.hidden && (main || root.innerHTML.length > 80)) return 'shell';
        if (/فشل|خطأ|غير|login|سجل|PIN|pin|unlock/i.test(msg) && !/جاري/.test(msg)) return 'status:' + msg.slice(0, 80);
        return false;
      },
      null,
      { timeout: 45000 }
    );
    unlockMs = Date.now() - t0;
    unlockState = await page.evaluate(() => {
      const root = document.getElementById('shell-root');
      return {
        shellHidden: !!(root && root.hidden),
        shellHtmlLen: root ? root.innerHTML.length : 0,
        statusText: ((document.getElementById('msg') || {}).textContent || '').slice(0, 160),
        title: ((document.getElementById('title') || {}).textContent || '').slice(0, 80),
        hasMain: !!document.querySelector('#rateb-offline-shell-main, .rateb-offline-home'),
      };
    });
  } catch (e) {
    unlockMs = Date.now() - t0;
    unlockState = {
      timeout: true,
      error: String(e.message || e).slice(0, 160),
      statusText: await page.evaluate(() => ((document.getElementById('msg') || {}).textContent || '').slice(0, 160)),
    };
  }

  await page.waitForTimeout(2000);
  const profile = await page.evaluate(() => {
    const resources = (performance.getEntriesByType('resource') || []).map((r) => {
      let short = r.name;
      try {
        short = new URL(r.name).pathname;
      } catch (e) {}
      return {
        short,
        type: r.initiatorType,
        ms: Math.round(r.duration * 10) / 10,
        transfer: r.transferSize || 0,
        start: Math.round(r.startTime * 10) / 10,
      };
    });
    resources.sort((a, b) => b.ms - a.ms);
    const nav = performance.getEntriesByType('navigation')[0];
    return {
      nav: nav
        ? {
            ttfb: Math.round(nav.responseStart * 10) / 10,
            dcl: Math.round(nav.domContentLoadedEventEnd * 10) / 10,
            load: Math.round(nav.loadEventEnd * 10) / 10,
          }
        : null,
      paint: Object.fromEntries(
        (performance.getEntriesByType('paint') || []).map((p) => [p.name, Math.round(p.startTime * 10) / 10])
      ),
      topResources: resources.slice(0, 20),
      resourceCount: resources.length,
      hooks: window.__P,
      sw: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      online: navigator.onLine,
    };
  });

  /* Second offline reload (warm caches) */
  const t1 = Date.now();
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
  let warmUnlock = null;
  try {
    await page.waitForFunction(
      () => {
        const root = document.getElementById('shell-root');
        const main = document.querySelector('#rateb-offline-shell-main, .rateb-offline-home');
        return !!(root && !root.hidden && (main || root.innerHTML.length > 80));
      },
      null,
      { timeout: 30000 }
    );
    warmUnlock = Date.now() - t1;
  } catch (e2) {
    warmUnlock = { timeout: true, ms: Date.now() - t1 };
  }

  const report = {
    ok: true,
    seedHooks,
    offline_cold: {
      dcl_ms: dcl,
      unlock_ms: unlockMs,
      unlockState,
      profile,
    },
    offline_warm_reload_ms: warmUnlock,
    marks: marks.map((m, i) => ({
      name: m.name,
      dt: i ? m.t - marks[0].t : 0,
    })),
  };
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out: OUT, cold_unlock_ms: unlockMs, warm: warmUnlock, unlockState }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
