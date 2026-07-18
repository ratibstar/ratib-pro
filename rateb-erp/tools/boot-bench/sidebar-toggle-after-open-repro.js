/**
 * Repro: after opening one nav group, can other groups still open?
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
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'tog-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
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
  const page = await ctx.newPage();
  const longTasks = [];
  await page.addInitScript(() => {
    try {
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) {
          if (e.duration >= 50) {
            (window.__LT = window.__LT || []).push({ d: e.duration, t: e.startTime, n: e.name });
          }
        }
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  });

  await page.goto(BASE + '/admin/ops/notifications?company_id=22&_t=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForTimeout(1500);

  const result = await page.evaluate(async () => {
    const side = document.getElementById('rateb-sidebar');
    const out = {
      delegated: side && side.getAttribute('data-rateb-nav-delegated'),
      build: document.querySelector('script[src*="erp-nav-instant"]')?.src,
      steps: [],
    };

    function topLevelGroups() {
      return [...side.querySelectorAll(':scope > nav > [data-nav-group], :scope nav > [data-nav-group]')].filter(
        (g) => g.querySelector(':scope > [data-nav-group-toggle]')
      );
    }

    // Prefer: all [data-nav-group] that are direct children of nav or section wrappers
    let groups = [...side.querySelectorAll('[data-nav-group]')].filter((g) => {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      return !!btn && !g.classList.contains('rateb-nav-subgroup');
    });
    // If empty use any with toggle
    if (groups.length < 2) {
      groups = [...side.querySelectorAll('[data-nav-group]')].filter((g) =>
        g.querySelector(':scope > [data-nav-group-toggle]')
      );
    }

    function label(g) {
      return (g.querySelector(':scope > [data-nav-group-toggle]')?.textContent || '')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 40);
    }

    function hit(btn) {
      const r = btn.getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + Math.min(10, r.height / 2);
      const top = document.elementFromPoint(x, y);
      return {
        y,
        h: r.height,
        topTag: top && top.tagName,
        topCls: top && String(top.className || '').slice(0, 60),
        topText: top && (top.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30),
        hitsToggle: !!(top && (top === btn || btn.contains(top) || top.closest?.('[data-nav-group-toggle]') === btn)),
        covered: top && !btn.contains(top) && top !== btn,
      };
    }

    async function clickBtn(btn) {
      btn.scrollIntoView({ block: 'center' });
      await new Promise((r) => setTimeout(r, 50));
      const beforeHit = hit(btn);
      const before = btn.closest('[data-nav-group]').classList.contains('is-open');
      btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      await new Promise((r) => setTimeout(r, 30));
      const after = btn.closest('[data-nav-group]').classList.contains('is-open');
      return { before, after, changed: before !== after, beforeHit, afterHit: hit(btn) };
    }

    const collapsed = groups.filter((g) => !g.classList.contains('is-open'));
    const g1 = collapsed[0] || groups[0];
    const g2 = collapsed.find((g) => g !== g1) || groups.find((g) => g !== g1);
    const g3 = groups.find((g) => g !== g1 && g !== g2);

    const b1 = g1.querySelector(':scope > [data-nav-group-toggle]');
    out.steps.push({ which: 'first', label: label(g1), ...(await clickBtn(b1)) });

    if (g2) {
      const b2 = g2.querySelector(':scope > [data-nav-group-toggle]');
      out.steps.push({ which: 'second', label: label(g2), ...(await clickBtn(b2)) });
    }
    if (g3) {
      const b3 = g3.querySelector(':scope > [data-nav-group-toggle]');
      out.steps.push({ which: 'third', label: label(g3), ...(await clickBtn(b3)) });
    }

    out.openCount = side.querySelectorAll('[data-nav-group].is-open').length;
    out.longTasks = (window.__LT || []).slice(-10);
    return out;
  });

  // Also real Playwright clicks
  const real = await page.evaluate(() => {
    const side = document.getElementById('rateb-sidebar');
    // close all first
    side.querySelectorAll('[data-nav-group].is-open').forEach((g) => {
      g.classList.remove('is-open');
      const b = g.querySelector(':scope > [data-nav-group-toggle]');
      if (b) b.setAttribute('aria-expanded', 'false');
    });
    return true;
  });

  const toggles = page.locator('#rateb-sidebar > nav [data-nav-group-toggle], #rateb-sidebar nav > .rateb-nav-group > [data-nav-group-toggle]');
  const count = await page.locator('#rateb-sidebar [data-nav-group] > [data-nav-group-toggle]').count();
  const realSteps = [];
  for (let i = 0; i < Math.min(count, 5); i++) {
    const loc = page.locator('#rateb-sidebar [data-nav-group] > [data-nav-group-toggle]').nth(i);
    const text = (await loc.textContent())?.replace(/\s+/g, ' ').trim().slice(0, 30);
    const before = await loc.evaluate((el) => el.closest('[data-nav-group]').classList.contains('is-open'));
    await loc.scrollIntoViewIfNeeded();
    await loc.click({ timeout: 5000 }).catch((e) => ({ err: e.message }));
    await page.waitForTimeout(100);
    const after = await loc.evaluate((el) => el.closest('[data-nav-group]').classList.contains('is-open'));
    realSteps.push({ i, text, before, after, changed: before !== after });
  }

  console.log(JSON.stringify({ result, realSteps }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
