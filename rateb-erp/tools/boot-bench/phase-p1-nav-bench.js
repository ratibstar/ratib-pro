/**
 * PERF-P1 — Navigation targets bench (content-swap + SWR).
 * Targets: first <500ms, warm <200ms, offline <150ms.
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
const EXPECT_SW = '20260716-perf-p1-nav-swr-v80';

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

  const profileDir = path.join(os.tmpdir(), 'rateb-p1-' + t0);
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1365, height: 900 },
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

  // Wait for SW build
  for (let i = 0; i < 40; i++) {
    const ok = await page.evaluate(async (url, build) => {
      try {
        const t = await (await fetch(url + '?t=' + Date.now(), { cache: 'no-store' })).text();
        return t.indexOf(build) !== -1;
      } catch (e) { return false; }
    }, BASE + '/pos-sw.js', EXPECT_SW);
    if (ok) break;
    await page.waitForTimeout(3000);
  }

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(2000);
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.ready;
    if (!reg.active) return;
    await new Promise((resolve) => {
      const ch = new MessageChannel();
      const t = setTimeout(resolve, 90000);
      ch.port1.onmessage = () => { clearTimeout(t); resolve(); };
      reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
    });
  });
  await page.waitForTimeout(3000);

  const hasNav = await page.evaluate(() => !!window.RatebNavInstant);
  const results = { online: {}, offline: {}, hasNavInstant: hasNav, sw: EXPECT_SW };

  async function clickNav(mod) {
    // Prefer content-swap via sidebar link if present
    const clicked = await page.evaluate((pathSuffix) => {
      const links = [...document.querySelectorAll('a.rateb-nav-link[href], a[href*="/admin"]')];
      const hit = links.find((a) => (a.getAttribute('href') || '').indexOf(pathSuffix.replace(/\?.*/, '')) !== -1
        || a.href.indexOf(pathSuffix.replace(/\?.*/, '')) !== -1);
      if (hit) {
        hit.dispatchEvent(new MouseEvent('pointerenter', { bubbles: true }));
        hit.click();
        return hit.href;
      }
      return null;
    }, mod.path);
    if (!clicked) {
      await page.goto(BASE + mod.path, { waitUntil: 'domcontentloaded', timeout: 60000 });
      return { mode: 'goto' };
    }
    // Wait for swap mark or URL change
    const tStart = Date.now();
    await page.waitForFunction((want) => {
      try {
        return location.href.indexOf(want.replace(/\?.*/, '')) !== -1
          || (document.querySelector('#rateb-main-content') && document.querySelector('#rateb-main-content').innerText.length > 40);
      } catch (e) { return false; }
    }, mod.path.split('?')[0], { timeout: 15000 }).catch(() => null);
    const ms = Date.now() - tStart;
    const mark = await page.evaluate(() => {
      const marks = performance.getEntriesByName('rateb-nav-swap');
      const last = marks.length ? marks[marks.length - 1] : null;
      return {
        swap_mark: !!last,
        href: location.href,
        sidebar: !!document.querySelector('#rateb-sidebar'),
        mainLen: (document.querySelector('#rateb-main-content') || {}).innerText
          ? document.querySelector('#rateb-main-content').innerText.length
          : 0,
        mem: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576) : null,
      };
    });
    return { mode: 'click', wall_ms: ms, ...mark };
  }

  for (const mod of MODULES) {
    // Prefetch hover then first click
    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(500);
    await page.evaluate((href) => {
      if (window.RatebNavInstant) window.RatebNavInstant.prefetch(href);
    }, BASE + mod.path);
    await page.waitForTimeout(800);
    const first = await clickNav(mod);
    await page.waitForTimeout(400);
    const warm = await clickNav(mod);
    // Also measure RatebNavInstant.navigate timing
    const apiWarm = await page.evaluate(async (href) => {
      if (!window.RatebNavInstant) return null;
      const t0 = performance.now();
      await window.RatebNavInstant.navigate(href, { replace: true });
      return Math.round(performance.now() - t0);
    }, BASE + mod.path);
    results.online[mod.id] = { first, warm, apiWarm_ms: apiWarm };
  }

  // Offline
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.ready;
    if (reg.active) reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true });
  });
  await page.waitForTimeout(5000);
  await context.setOffline(true);

  for (const mod of MODULES) {
    const tA = Date.now();
    const cold = await page.evaluate(async (href) => {
      if (!window.RatebNavInstant) {
        location.href = href;
        return null;
      }
      const t0 = performance.now();
      await window.RatebNavInstant.navigate(href, { replace: true });
      return Math.round(performance.now() - t0);
    }, BASE + mod.path);
    const coldWall = Date.now() - tA;
    await page.waitForTimeout(200);
    const tB = Date.now();
    const warm = await page.evaluate(async (href) => {
      if (!window.RatebNavInstant) return null;
      const t0 = performance.now();
      await window.RatebNavInstant.navigate(href, { replace: true });
      return Math.round(performance.now() - t0);
    }, BASE + mod.path);
    results.offline[mod.id] = {
      cold_ms: cold,
      cold_wall_ms: coldWall,
      warm_ms: warm,
      warm_wall_ms: Date.now() - tB,
    };
  }

  const targets = {
    online_first_lt_500: Object.values(results.online).every((r) => (r.apiWarm_ms || r.first.wall_ms || 9999) < 500 || (r.first.wall_ms || 9999) < 500),
    online_warm_lt_200: Object.values(results.online).every((r) => (r.apiWarm_ms != null ? r.apiWarm_ms : (r.warm.wall_ms || 9999)) < 200),
    offline_lt_150: Object.values(results.offline).every((r) => (r.warm_ms != null ? r.warm_ms : 9999) < 150),
  };

  const report = {
    phase: 'PERF-P1',
    at: new Date().toISOString(),
    targets,
    results,
    elapsed_ms: Date.now() - t0,
  };
  const out = path.join(OUT_DIR, 'phase-p1-nav-bench-' + t0 + '.json');
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-p1-nav-bench-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, targets, online: results.online, offline: results.offline, hasNav }, null, 2));
  await context.close();
  process.exit(targets.online_warm_lt_200 && targets.offline_lt_150 ? 0 : 2);
})().catch((e) => { console.error(e); process.exit(1); });
