/**
 * PERF-P1 validation bench — Online cold/warm + Offline PIN UI.
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
const OUT = path.join(__dirname, 'reports', 'perf-p1-validate-' + Date.now() + '.json');
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

(async () => {
  let php = null;
  try {
    php = JSON.parse(ssh('php /tmp/_perf_php_boot.php 2>/dev/null || php -r \'echo "null";\''));
  } catch (e) {
    try {
      scpBoot();
      php = JSON.parse(ssh('php /tmp/_perf_php_boot.php'));
    } catch (e2) {
      php = { error: String(e2.message || e2).slice(0, 200) };
    }
  }

  const mint = JSON.parse(
    ssh('php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint')
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'rateb-p1v-' + Date.now()), {
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

  const cold0 = Date.now();
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const coldDcl = Date.now() - cold0;
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar', { timeout: 60000 });
  const coldUsable = Date.now() - cold0;
  const coldNav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return n
      ? {
          ttfb: Math.round(n.responseStart * 10) / 10,
          dcl: Math.round(n.domContentLoadedEventEnd * 10) / 10,
          transfer: n.transferSize || 0,
        }
      : null;
  });
  await page.waitForTimeout(1500);

  const warm0 = Date.now();
  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('aside.rateb-sidebar, #rateb-sidebar', { timeout: 45000 });
  const warmUsable = Date.now() - warm0;
  const warmNav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return n ? { ttfb: Math.round(n.responseStart * 10) / 10 } : null;
  });

  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(3000);
  await ctx.setOffline(true);
  const off0 = Date.now();
  await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
  let offPin = null;
  try {
    await page.waitForFunction(
      () => {
        const msg = ((document.getElementById('msg') || {}).textContent || '');
        return /PIN|رمز/i.test(msg) || !!(document.getElementById('shell-root') && !document.getElementById('shell-root').hidden);
      },
      null,
      { timeout: 20000 }
    );
    offPin = Date.now() - off0;
  } catch (e) {
    offPin = { error: String(e.message || e).slice(0, 120), ms: Date.now() - off0 };
  }

  const report = {
    phase: 'PERF-P1-VALIDATE',
    at: new Date().toISOString(),
    php,
    online: {
      cold_dcl_ms: coldDcl,
      cold_usable_ms: coldUsable,
      cold_nav: coldNav,
      warm_usable_ms: warmUsable,
      warm_nav: warmNav,
    },
    offline: { pin_ui_ms: offPin },
    targets: {
      cold_usable_under_ms: 500,
      ttfb_under_ms: 300,
      warm_usable_under_ms: 200,
      offline_under_ms: 60,
    },
  };
  report.pass = {
    cold: coldUsable < 500,
    ttfb: coldNav && coldNav.ttfb < 300,
    warm: warmUsable < 200,
    offline: typeof offPin === 'number' && offPin < 60,
  };
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out: OUT, online: report.online, offline: report.offline, pass: report.pass, php }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});

function scpBoot() {
  execFileSync(
    'scp',
    [
      '-i',
      KEY,
      '-o',
      'StrictHostKeyChecking=no',
      path.join(__dirname, '_perf_php_boot.php'),
      HOST + ':/tmp/_perf_php_boot.php',
    ],
    { stdio: 'ignore' }
  );
}
