/**
 * PERF-P0.3-D — Offline module open profile (read/measure).
 * Warm online → inspect ops cache → offline module navigations with timing.
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
const EXPECT_BUILD = '20260716-perf-p03d-ops-html-v75';
const MODULES = [
  { id: 'dashboard', path: '/admin/' },
  { id: 'hr', path: '/admin/hr/attendance' },
  { id: 'inventory', path: '/admin/ops/inventory' },
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
    mint = JSON.parse(ssh(
      'php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint 2>/dev/null || php /tmp/remote-auth.php mint'
    ));
  }

  const profileDir = path.join(os.tmpdir(), 'rateb-p03d-' + t0);
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await context.clearCookies();
  await context.addCookies([{
    name: mint.session_name || mint.cookie_name || 'rateb_erp',
    value: mint.session_id || mint.cookie_value || mint.value,
    domain: 'rateb.sa',
    path: '/',
    httpOnly: true,
    secure: true,
  }]);
  const page = context.pages()[0] || await context.newPage();

  // Wait for deployed SW build
  for (let i = 0; i < 30; i++) {
    const text = await page.evaluate(async (url) => {
      try {
        const r = await fetch(url + '?t=' + Date.now(), { cache: 'no-store' });
        return await r.text();
      } catch (e) { return ''; }
    }, BASE + '/pos-sw.js');
    if (text.indexOf(EXPECT_BUILD) !== -1) break;
    await page.waitForTimeout(3000);
  }

  // Online warm: admin + force shell warm message
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(2500);
  // Ensure SW controls the page, then force WARM (awaits leanOps after P0.3-D).
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    if (reg && reg.waiting) {
      reg.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
    await navigator.serviceWorker.ready;
  });
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1500);
  const warmMsg = await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    if (!reg || !reg.active) return { ok: false, reason: 'no_sw' };
    return await new Promise((resolve) => {
      const ch = new MessageChannel();
      const t = setTimeout(() => resolve({ ok: false, reason: 'timeout' }), 180000);
      ch.port1.onmessage = (ev) => {
        clearTimeout(t);
        resolve(ev.data || { ok: true, raw: true });
      };
      reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
    });
  });

  const cacheAudit = await page.evaluate(async (mods) => {
    const OPS = 'rateb-erp-ops-pages-v34';
    const out = { ops_keys: 0, hits: {} };
    try {
      const cache = await caches.open(OPS);
      const keys = await cache.keys();
      out.ops_keys = keys.length;
      for (const m of mods) {
        const url = location.origin + '/rateb-erp/public' + m.path;
        const hit = await cache.match(url) || await cache.match(url.replace(/\/$/, '')) ||
          await cache.match(url + (m.path.endsWith('/') ? '' : '/'));
        let len = 0;
        let uncached = false;
        if (hit) {
          const t = await hit.clone().text();
          len = t.length;
          uncached = /data-rateb-uncached-page/i.test(t);
        }
        out.hits[m.id] = { hit: !!hit, len, uncached };
      }
    } catch (e) {
      out.error = String(e);
    }
    return out;
  }, MODULES);

  // Offline
  await context.setOffline(true);
  const opens = [];
  for (const m of MODULES) {
    for (let pass = 1; pass <= 2; pass++) {
      const tNav = Date.now();
      await page.goto(BASE + m.path, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
      const wall = Date.now() - tNav;
      const detail = await page.evaluate(() => {
        const nav = performance.getEntriesByType('navigation')[0] || {};
        const paints = performance.getEntriesByType('paint') || [];
        const fcp = (paints.find((p) => p.name === 'first-contentful-paint') || {}).startTime || 0;
        const html = document.documentElement ? document.documentElement.outerHTML : '';
        return {
          href: location.href,
          bodyLen: (document.body && document.body.innerText || '').length,
          htmlLen: html.length,
          uncached: !!document.querySelector('[data-rateb-uncached-page]'),
          hasSidebar: !!document.querySelector('.rateb-sidebar, #rateb-sidebar, [data-rateb-app]'),
          title: document.title || '',
          ttfb: nav.responseStart || 0,
          responseEnd: nav.responseEnd || 0,
          domInteractive: nav.domInteractive || 0,
          dcl: nav.domContentLoadedEventEnd || 0,
          fcp,
          transferSize: nav.transferSize || 0,
          decodedBodySize: nav.decodedBodySize || 0,
          onLine: navigator.onLine,
        };
      });
      opens.push({ id: m.id, pass, wall_ms: wall, ...detail });
    }
  }

  const swBuild = await page.evaluate(async () => {
    try {
      const r = await fetch('/rateb-erp/public/pos-sw.js?t=' + Date.now(), { cache: 'no-store' });
      const t = await r.text();
      const m = t.match(/SW_BUILD_ID\s*=\s*['\"]([^'\"]+)/);
      return m ? m[1] : 'unknown';
    } catch (e) {
      return 'err';
    }
  }).catch(() => 'offline_skip');

  const report = {
    phase: 'P0.3-D',
    at: new Date().toISOString(),
    warmMsg,
    cacheAudit,
    opens,
    swBuild,
    elapsed_ms: Date.now() - t0,
  };
  const out = path.join(OUT_DIR, 'phase-p03d-module-profile-' + t0 + '.json');
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await context.close();
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
