/**
 * After soft-nav + rapid toggle clicks — still responsive?
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
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'soft-' + Date.now()), {
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
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(BASE + '/admin/ops/notifications?company_id=22&_t=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForTimeout(2000);

  const soft = await page.evaluate(async () => {
    const dash =
      document.querySelector('#rateb-sidebar a[href*="dashboard"]') ||
      document.querySelector('#rateb-sidebar a.rateb-nav-link[href*="/admin"]');
    const out = {
      dash: dash && dash.href,
      beforeDel: document.getElementById('rateb-sidebar')?.getAttribute('data-rateb-nav-delegated'),
    };
    if (dash && window.RatebNavInstant) {
      await window.RatebNavInstant.navigate(dash.href);
      await new Promise((r) => setTimeout(r, 800));
    }
    out.afterDel = document.getElementById('rateb-sidebar')?.getAttribute('data-rateb-nav-delegated');
    out.href = location.href;

    const groups = [...document.querySelectorAll('#rateb-sidebar [data-nav-group]')].filter(
      (g) =>
        g.querySelector(':scope > [data-nav-group-toggle]') &&
        !g.classList.contains('rateb-nav-subgroup') &&
        !g.classList.contains('is-open')
    );

    function clickNoScroll(btn) {
      const before = btn.closest('[data-nav-group]').classList.contains('is-open');
      btn.click();
      const after = btn.closest('[data-nav-group]').classList.contains('is-open');
      return {
        label: (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30),
        before,
        after,
        changed: before !== after,
      };
    }

    out.g1 = groups[0] ? clickNoScroll(groups[0].querySelector(':scope > [data-nav-group-toggle]')) : null;
    out.g2 = groups[1] ? clickNoScroll(groups[1].querySelector(':scope > [data-nav-group-toggle]')) : null;
    out.g3 = groups[2] ? clickNoScroll(groups[2].querySelector(':scope > [data-nav-group-toggle]')) : null;
    out.openCount = document.querySelectorAll('#rateb-sidebar [data-nav-group].is-open').length;
    return out;
  });

  const rapid = await page.evaluate(() => {
    const steps = [];
    const btns = [...document.querySelectorAll('#rateb-sidebar [data-nav-group] > [data-nav-group-toggle]')].slice(
      0,
      8
    );
    for (const btn of btns) {
      const g = btn.closest('[data-nav-group]');
      const b = g.classList.contains('is-open');
      btn.click();
      steps.push({
        t: (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 25),
        b,
        a: g.classList.contains('is-open'),
        ok: b !== g.classList.contains('is-open'),
      });
    }
    return steps;
  });

  console.log(JSON.stringify({ soft, rapid, errors }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
