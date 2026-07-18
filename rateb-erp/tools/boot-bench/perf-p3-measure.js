/**
 * PERF-P3 before/after measure — Admin cold/warm + sidebar.
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
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT = path.join(__dirname, 'reports', 'perf-p3-frontend-' + Date.now() + '.json');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function r1(n) {
  return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'p3-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage', '--enable-precise-memory-info'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
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
  const page = ctx.pages()[0] || (await ctx.newPage());
  const cdp = await ctx.newCDPSession(page);
  await page.addInitScript(() => {
    window.__LT__ = [];
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          window.__LT__.push({ start: Math.round(e.startTime * 10) / 10, dur: Math.round(e.duration * 10) / 10 });
        });
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  });

  async function snap(label) {
    await page.waitForTimeout(800);
    return page.evaluate((lab) => {
      const R = (n) => (Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null);
      const nav = performance.getEntriesByType('navigation')[0];
      const paint = {};
      performance.getEntriesByType('paint').forEach((p) => (paint[p.name] = R(p.startTime)));
      let lcp = null;
      try {
        const l = performance.getEntriesByType('largest-contentful-paint');
        if (l.length) lcp = R(l[l.length - 1].startTime);
      } catch (e) {}
      let cls = 0;
      try {
        performance.getEntriesByType('layout-shift').forEach((e) => {
          if (!e.hadRecentInput) cls += e.value;
        });
      } catch (e2) {}
      const resources = performance.getEntriesByType('resource') || [];
      const blockingCss = resources.filter((r) => r.renderBlockingStatus === 'blocking');
      const scripts = resources.filter((r) => r.initiatorType === 'script' || /\.js(\?|$)/i.test(r.name));
      const jsParseProxy = scripts.reduce((a, r) => a + (r.decodedBodySize || 0), 0);
      return {
        label: lab,
        href: location.href,
        assetBuild: window.__RATEB_ASSET_BUILD__ || null,
        nav: nav
          ? {
              dns: R(nav.domainLookupEnd - nav.domainLookupStart),
              connect: R(nav.connectEnd - nav.connectStart),
              ttfb: R(nav.responseStart - nav.requestStart),
              responseStart: R(nav.responseStart),
              dcl: R(nav.domContentLoadedEventEnd),
              load: R(nav.loadEventEnd),
              transfer: nav.transferSize || 0,
            }
          : null,
        paint,
        lcp,
        cls: Math.round(cls * 10000) / 10000,
        longTasks: window.__LT__ || [],
        longTaskSum: R((window.__LT__ || []).reduce((a, t) => a + t.dur, 0)),
        blockingCssCount: blockingCss.length,
        blockingCssMs: R(blockingCss.reduce((a, r) => a + r.duration, 0)),
        jsDecodedKB: Math.round(jsParseProxy / 102.4) / 10,
        domNodes: document.querySelectorAll('*').length,
        sidebarBytes: ((document.querySelector('#rateb-sidebar, aside.rateb-sidebar') || {}).innerHTML || '').length,
        sidebarAt: (function () {
          const sb = document.querySelector('#rateb-sidebar, aside.rateb-sidebar');
          return sb && sb.offsetHeight > 40 ? true : false;
        })(),
        criticalInline: !!document.getElementById('rateb-critical-shell'),
      };
    }, label);
  }

  // COLD — cache disabled
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForSelector('#rateb-sidebar, aside.rateb-sidebar', { timeout: 60000 });
  const cold = await snap('cold');

  // WARM — cache on, same connection
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: false });
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('#rateb-sidebar, aside.rateb-sidebar', { timeout: 45000 });
  const warm = await snap('warm');

  // Sidebar clicks
  async function clickNav(hrefPart) {
    await page.evaluate(() => {
      document.querySelectorAll('.rateb-nav-group, .rateb-nav-subgroup').forEach((g) => {
        g.classList.add('is-open');
        const body = g.querySelector('.rateb-nav-group-body, .rateb-nav-subgroup-body');
        if (!body) return;
        const tpl = body.querySelector('template[data-rateb-nav-lazy]');
        if (tpl) {
          body.appendChild(tpl.content.cloneNode(true));
          tpl.remove();
        }
      });
    });
    const link = await page.$(`a.rateb-nav-link[href*="${hrefPart}"]`);
    if (!link) return { hrefPart, error: 'missing' };
    await link.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    const t0 = Date.now();
    await link.click({ force: true });
    const dead = Date.now() + 30000;
    while (Date.now() < dead) {
      const ok = await page.evaluate((hp) => {
        if (!location.href.includes(hp)) return false;
        const main = document.querySelector('main, .rateb-content');
        return main && (main.innerText || '').trim().length > 20;
      }, hrefPart);
      if (ok) return { hrefPart, ms: Date.now() - t0 };
      await page.waitForTimeout(40);
    }
    return { hrefPart, ms: Date.now() - t0, timeout: true };
  }

  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const navHr = await clickNav('/admin/hr');
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const navInv = await clickNav('/admin/ops/inventory');
  // warm sidebar
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await clickNav('/admin/hr');
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const navHrWarm = await clickNav('/admin/hr');

  await ctx.close();

  const fe = (s) => {
    if (!s || !s.nav) return null;
    return {
      dcl_fe: r1(s.nav.dcl - (s.nav.dns || 0) - (s.nav.connect || 0)),
      fcp_fe: s.paint['first-contentful-paint'] != null ? r1(s.paint['first-contentful-paint'] - (s.nav.dns || 0) - (s.nav.connect || 0)) : null,
      after_response_dcl: r1(s.nav.dcl - s.nav.responseStart),
      after_response_fcp:
        s.paint['first-contentful-paint'] != null ? r1(s.paint['first-contentful-paint'] - s.nav.responseStart) : null,
    };
  };

  const report = {
    at: new Date().toISOString(),
    phase: 'PERF-P3-FRONTEND',
    cold,
    warm,
    frontend_adjusted: { cold: fe(cold), warm: fe(warm) },
    sidebar: { hr_cold: navHr, inventory_cold: navInv, hr_warm: navHrWarm },
    criteria: {
      cold_dcl_lt_500: cold.nav && cold.nav.dcl < 500,
      cold_fcp_lt_550: cold.paint && cold.paint['first-contentful-paint'] < 550,
      warm_dcl_lt_180: warm.nav && warm.nav.dcl < 180,
      warm_fcp_lt_180: warm.paint && warm.paint['first-contentful-paint'] < 180,
      sidebar_lt_100: navHrWarm.ms != null && navHrWarm.ms < 100,
    },
  };

  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  const lines = [];
  lines.push('PERF-P3 MEASURE');
  lines.push('assetBuild=' + (cold.assetBuild || '?'));
  lines.push('METRIC\tCOLD\tWARM\tTARGET');
  lines.push('DCL\t' + cold.nav.dcl + '\t' + warm.nav.dcl + '\t<500 / <180');
  lines.push('FCP\t' + (cold.paint['first-contentful-paint'] || '?') + '\t' + (warm.paint['first-contentful-paint'] || '?') + '\t<550 / <180');
  lines.push('LCP\t' + (cold.lcp || '?') + '\t' + (warm.lcp || '?') + '\t—');
  lines.push('CLS\t' + cold.cls + '\t' + warm.cls + '\t—');
  lines.push('LongTasks\t' + cold.longTaskSum + '\t' + warm.longTaskSum + '\t—');
  lines.push('JS decoded KB\t' + cold.jsDecodedKB + '\t' + warm.jsDecodedKB + '\t—');
  lines.push('CSS blocking count/ms\t' + cold.blockingCssCount + '/' + cold.blockingCssMs + '\t' + warm.blockingCssCount + '/' + warm.blockingCssMs + '\t0 blocking ideal');
  lines.push('DOM nodes\t' + cold.domNodes + '\t' + warm.domNodes + '\t—');
  lines.push('Sidebar HTML bytes\t' + cold.sidebarBytes + '\t' + warm.sidebarBytes + '\t—');
  lines.push('Sidebar HR ms\tcold=' + (navHr.ms || navHr.error) + '\twarm=' + (navHrWarm.ms || '?') + '\t<100 warm');
  lines.push('FE DCL (excl dns+connect)\t' + report.frontend_adjusted.cold.dcl_fe + '\t' + report.frontend_adjusted.warm.dcl_fe);
  lines.push('DCL after responseStart\t' + report.frontend_adjusted.cold.after_response_dcl + '\t' + report.frontend_adjusted.warm.after_response_dcl);
  lines.push('criteria\t' + JSON.stringify(report.criteria));
  const txt = lines.join('\n');
  fs.writeFileSync(OUT.replace(/\.json$/, '.txt'), txt);
  console.log(txt);
  console.log('wrote', OUT);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
