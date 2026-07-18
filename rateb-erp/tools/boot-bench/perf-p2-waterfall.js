/**
 * PERF-P2 — Browser waterfall + timing breakdown (infra focus).
 */
'use strict';
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = 'https://rateb.sa/rateb-erp/public';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT = path.join(__dirname, 'reports', 'perf-p2-waterfall-' + Date.now() + '.json');
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

(async () => {
  const mint = JSON.parse(
    ssh('php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint')
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'p2-' + Date.now()), {
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

  /* Warm FPM first (infra keepalive simulation) */
  await page.goto(BASE + '/erp-health.php', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(200);

  const t0 = Date.now();
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar', { timeout: 60000 });
  const usable = Date.now() - t0;

  const profile = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const resources = performance.getEntriesByType('resource').map((r) => {
      let short = r.name;
      try {
        short = new URL(r.name).pathname + new URL(r.name).search;
      } catch (e) {}
      return {
        short: short.slice(0, 120),
        type: r.initiatorType,
        start: Math.round(r.startTime),
        dur: Math.round(r.duration),
        wait: Math.round((r.responseStart || 0) - (r.requestStart || 0)),
        transfer: r.transferSize || 0,
        encoded: r.encodedBodySize || 0,
        decoded: r.decodedBodySize || 0,
        protocol: r.nextHopProtocol || '',
      };
    });
    resources.sort((a, b) => b.dur - a.dur);
    const paint = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paint[p.name] = Math.round(p.startTime);
    });
    return {
      nav: n
        ? {
            dns: Math.round(n.domainLookupEnd - n.domainLookupStart),
            connect: Math.round(n.connectEnd - n.connectStart),
            tls: Math.round(n.secureConnectionStart > 0 ? n.connectEnd - n.secureConnectionStart : 0),
            wait: Math.round(n.responseStart - n.requestStart),
            ttfb: Math.round(n.responseStart),
            download: Math.round(n.responseEnd - n.responseStart),
            dcl: Math.round(n.domContentLoadedEventEnd),
            transfer: n.transferSize || 0,
            protocol: n.nextHopProtocol || '',
            encoded: n.encodedBodySize || 0,
          }
        : null,
      paint,
      top: resources.slice(0, 20),
      resourceCount: resources.length,
      jsCount: resources.filter((r) => r.type === 'script').length,
      cssCount: resources.filter((r) => r.type === 'link' || /\.css/.test(r.short)).length,
      h2Count: resources.filter((r) => /h2/.test(r.protocol)).length,
      totalTransfer: resources.reduce((s, r) => s + (r.transfer || 0), 0),
    };
  });

  /* Second nav (warm connection + cache) */
  const w0 = Date.now();
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar', { timeout: 45000 });
  const warmUsable = Date.now() - w0;
  const warmNav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return n
      ? {
          dns: Math.round(n.domainLookupEnd - n.domainLookupStart),
          connect: Math.round(n.connectEnd - n.connectStart),
          wait: Math.round(n.responseStart - n.requestStart),
          ttfb: Math.round(n.responseStart),
          protocol: n.nextHopProtocol || '',
          transfer: n.transferSize || 0,
        }
      : null;
  });

  const report = {
    phase: 'PERF-P2-WATERFALL',
    at: new Date().toISOString(),
    cold_usable_ms: usable,
    cold: profile,
    warm_usable_ms: warmUsable,
    warm: warmNav,
  };
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
