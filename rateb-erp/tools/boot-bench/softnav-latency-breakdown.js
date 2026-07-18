/**
 * Soft-nav latency breakdown: cache match / fetch / scripts / paint.
 */
'use strict';
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'navlat-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
    serviceWorkers: 'allow',
  });
  await ctx.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  const page = await ctx.newPage();
  const timings = [];
  page.on('console', (msg) => {
    const t = msg.text();
    if (t.includes('[RATEB NAV]') || t.includes('NAVPROF')) timings.push(t);
  });

  await page.goto(BASE + '/admin/ops/notifications?company_id=22&_t=' + Date.now(), {
    waitUntil: 'networkidle',
    timeout: 90000,
  });
  await page.waitForTimeout(1500);

  // Instrument fetchHtml path via monkeypatch
  const result = await page.evaluate(async () => {
    const out = { steps: [] };
    const origFetch = window.fetch.bind(window);
    const fetches = [];
    window.fetch = function (url, opts) {
      const u = String(url);
      const t0 = performance.now();
      return origFetch(url, opts).then((res) => {
        fetches.push({ u: u.slice(0, 120), ms: Math.round(performance.now() - t0), ok: res.ok, status: res.status });
        return res;
      });
    };

    // Open purchases group and click a link cold (clear cache match by using unique query? can't)
    // Navigate to several pages cold then warm
    async function nav(href, label) {
      fetches.length = 0;
      const t0 = performance.now();
      const marks = { t0 };
      const p = window.RatebNavInstant.navigate(href);
      // observe main content change
      const main = document.querySelector('#rateb-main-content, main.rateb-content');
      const before = main ? main.innerHTML.length : 0;
      await p;
      const t1 = performance.now();
      await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
      const t2 = performance.now();
      out.steps.push({
        label,
        href: href.split('/admin/')[1],
        navigateMs: Math.round(t1 - t0),
        paintMs: Math.round(t2 - t0),
        fetches: fetches.slice(),
        mainDelta: (main ? main.innerHTML.length : 0) - before,
        final: location.pathname,
      });
    }

    const base = location.origin + '/rateb-erp/public/admin/';
    await nav(base + 'ops/purchase-requests?company_id=22', 'cold-pr');
    await nav(base + 'ops/notifications?company_id=22', 'cold-notif');
    await nav(base + 'ops/purchase-requests?company_id=22', 'warm-pr');
    await nav(base + 'executive-dashboard', 'dash');
    await nav(base + 'ops/inventory-items?company_id=22', 'inv');

    // Cache keys count
    let cacheInfo = null;
    try {
      const keys = await caches.keys();
      const ops = keys.filter((k) => k.includes('ops') || k.includes('coexist'));
      cacheInfo = { all: keys.length, ops: ops.length, names: ops.slice(0, 8) };
    } catch (e) {
      cacheInfo = { err: String(e) };
    }
    out.cacheInfo = cacheInfo;
    return out;
  });

  console.log(JSON.stringify({ result, console: timings }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
