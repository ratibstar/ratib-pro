/**
 * Measure click → is-open / nav swap latency (toggle + soft-nav).
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
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'lat-' + Date.now()), {
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
  await page.addInitScript(() => {
    try {
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) {
          if (e.duration >= 40) {
            (window.__LT = window.__LT || []).push({ d: Math.round(e.duration), t: Math.round(e.startTime) });
          }
        }
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  });

  await page.goto(BASE + '/admin/ops/notifications?company_id=22&_t=' + Date.now(), {
    waitUntil: 'networkidle',
    timeout: 90000,
  });
  await page.waitForTimeout(800);

  const toggleLat = await page.evaluate(async () => {
    const side = document.getElementById('rateb-sidebar');
    const groups = [...side.querySelectorAll('[data-nav-group]')].filter(
      (g) =>
        g.querySelector(':scope > [data-nav-group-toggle]') &&
        !g.classList.contains('rateb-nav-subgroup') &&
        !g.classList.contains('is-open')
    );
    const out = [];
    for (const g of groups.slice(0, 5)) {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      const label = (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 28);
      const ltBefore = (window.__LT || []).length;
      const t0 = performance.now();
      btn.click();
      const t1 = performance.now();
      // wait a frame for paint
      await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
      const t2 = performance.now();
      const open = g.classList.contains('is-open');
      const bodyH = Math.round(g.querySelector('.rateb-nav-group-body')?.getBoundingClientRect().height || 0);
      const links = g.querySelectorAll('a[href]').length;
      const newLt = (window.__LT || []).slice(ltBefore);
      out.push({
        label,
        open,
        clickMs: Math.round(t1 - t0),
        paintMs: Math.round(t2 - t0),
        bodyH,
        links,
        longTasks: newLt,
      });
      await new Promise((r) => setTimeout(r, 50));
    }
    return {
      delegated: side.getAttribute('data-rateb-nav-delegated'),
      build: document.querySelector('script[src*="erp-nav-instant"]')?.src,
      toggles: out,
    };
  });

  // Soft-nav: click a real link
  const soft = await page.evaluate(async () => {
    const a =
      document.querySelector('#rateb-sidebar a[href*="purchase"]') ||
      document.querySelector('#rateb-sidebar a.rateb-nav-link[href*="/admin/"]');
    if (!a) return { err: 'no link' };
    // ensure parent open
    const g = a.closest('[data-nav-group]');
    if (g && !g.classList.contains('is-open')) {
      g.querySelector(':scope > [data-nav-group-toggle]')?.click();
    }
    const href = a.href;
    const t0 = performance.now();
    let navMark = null;
    const onLog = () => {};
    a.click();
    // poll until URL changes or 5s
    const start = performance.now();
    while (performance.now() - start < 5000) {
      if (location.href !== href && !location.href.includes('notifications')) {
        // may already be on target
      }
      if (location.href.split('#')[0] === href.split('#')[0] || location.pathname !== '/rateb-erp/public/admin/ops/notifications') {
        // soft nav may keep path change
        if (!location.href.includes('notifications') || location.href === href) {
          navMark = Math.round(performance.now() - t0);
          break;
        }
      }
      await new Promise((r) => setTimeout(r, 20));
    }
    return {
      href,
      final: location.href,
      ms: navMark ?? Math.round(performance.now() - t0),
      timedOut: navMark == null,
    };
  });

  // Real Playwright click timing on toggle
  const real = [];
  const locs = page.locator('#rateb-sidebar [data-nav-group] > [data-nav-group-toggle]');
  const n = Math.min(await locs.count(), 4);
  // close all first
  await page.evaluate(() => {
    document.querySelectorAll('#rateb-sidebar [data-nav-group].is-open').forEach((g) => {
      g.classList.remove('is-open');
      g.querySelector(':scope > [data-nav-group-toggle]')?.setAttribute('aria-expanded', 'false');
    });
  });
  for (let i = 0; i < n; i++) {
    const loc = locs.nth(i);
    const t0 = Date.now();
    await loc.click({ timeout: 5000 });
    await page.waitForFunction(
      (idx) => {
        const btn = document.querySelectorAll('#rateb-sidebar [data-nav-group] > [data-nav-group-toggle]')[idx];
        return btn && btn.closest('[data-nav-group]').classList.contains('is-open');
      },
      i,
      { timeout: 5000 }
    ).catch(() => null);
    real.push({ i, ms: Date.now() - t0, text: (await loc.textContent())?.replace(/\s+/g, ' ').trim().slice(0, 24) });
  }

  console.log(JSON.stringify({ toggleLat, soft, real }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
