/**
 * Reproduce: first nav group opens, later groups / logout dead.
 *   node sidebar-click-block-repro.js
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
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'blk-' + Date.now()), {
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
  await page.goto(BASE + '/admin/companies?company_id=22&_blk=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.waitForTimeout(500);

  const result = await page.evaluate(async () => {
    const out = { steps: [] };
    function hit(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + r.height / 2;
      const top = document.elementFromPoint(x, y);
      return {
        x, y,
        targetTag: top && top.tagName,
        targetCls: top && String(top.className || '').slice(0, 80),
        targetText: top && (top.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40),
        isToggle: !!(top && top.closest && top.closest('[data-nav-group-toggle]')),
        coveredByOther: top && el !== top && !el.contains(top),
        covering: top && top !== el && !el.contains(top) ? {
          tag: top.tagName,
          id: top.id,
          cls: String(top.className || '').slice(0, 100),
          pe: getComputedStyle(top).pointerEvents,
          z: getComputedStyle(top).zIndex,
          pos: getComputedStyle(top).position,
        } : null,
      };
    }

    const groups = [...document.querySelectorAll('[data-nav-group]')].filter((g) =>
      g.querySelector(':scope > [data-nav-group-toggle]')
    );
    const labels = groups.map((g) =>
      (g.querySelector('[data-nav-group-toggle]')?.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40)
    );

    // Find monitoring + follow-up + purchasing by label
    const find = (re) => groups.find((g) => re.test(g.querySelector('[data-nav-group-toggle]')?.textContent || ''));
    const A = find(/مراقبة|oversight|monitor/i) || groups.find((g) => g.classList.contains('is-open')) || groups[0];
    const B = find(/متابعة المنصة|platform/i) || groups.find((g) => g !== A && !g.classList.contains('is-open'));
    const C = find(/المشتريات|purchas/i) || groups.find((g) => g !== A && g !== B && !g.classList.contains('is-open'));

    function clickToggle(g, name) {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      const before = {
        isOpen: g.classList.contains('is-open'),
        listeners: btn.getAttribute('data-rca') || (btn.onclick ? 'onclick' : null),
        hit: hit(btn),
      };
      btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      const after = {
        isOpen: g.classList.contains('is-open'),
        aria: btn.getAttribute('aria-expanded'),
        hit: hit(btn),
      };
      out.steps.push({ name, label: (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40), before, after, opened: after.isOpen && !before.isOpen });
    }

    // Ensure A is open first (like user)
    if (A && !A.classList.contains('is-open')) {
      clickToggle(A, 'open_A');
    } else {
      out.steps.push({ name: 'A_already_open', label: (A?.querySelector('[data-nav-group-toggle]')?.textContent || '').trim().slice(0, 40) });
    }

    // Hit-test all collapsed toggles after A open
    out.afterA_hits = groups.map((g) => {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      return {
        label: (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40),
        isOpen: g.classList.contains('is-open'),
        hit: hit(btn),
      };
    });

    if (B) clickToggle(B, 'open_B');
    if (C) clickToggle(C, 'open_C');

    // Logout
    const logout = document.querySelector('a.rateb-topbar-logout');
    out.logout = {
      href: logout && logout.href,
      hit: hit(logout),
      pe: logout && getComputedStyle(logout).pointerEvents,
    };
    if (logout) {
      let prevented = false;
      const onClick = (ev) => {
        const a = ev.target && ev.target.closest && ev.target.closest('a.rateb-topbar-logout');
        if (a && ev.defaultPrevented) prevented = true;
      };
      document.addEventListener('click', onClick, true);
      // bubble listener after capture interceptors
      let preventedAtBubble = false;
      document.addEventListener('click', function (ev) {
        const a = ev.target && ev.target.closest && ev.target.closest('a.rateb-topbar-logout');
        if (a) preventedAtBubble = ev.defaultPrevented;
      }, false);
      logout.click();
      document.removeEventListener('click', onClick, true);
      out.logout.defaultPreventedCapture = prevented;
      out.logout.defaultPreventedBubble = preventedAtBubble;
      out.logout.hasFullNavAttr = logout.getAttribute('data-rateb-full-nav');
      out.logout.sidebarDelegated = document.getElementById('rateb-sidebar') && document.getElementById('rateb-sidebar').getAttribute('data-rateb-nav-delegated');
    }

    // Overlays
    out.overlays = [...document.querySelectorAll('body *')].filter((el) => {
      const st = getComputedStyle(el);
      if (st.position !== 'fixed' && st.position !== 'absolute') return false;
      if (st.pointerEvents === 'none' || st.display === 'none' || st.visibility === 'hidden') return false;
      const r = el.getBoundingClientRect();
      return r.width > 100 && r.height > 100 && parseInt(st.zIndex || '0', 10) >= 900;
    }).slice(0, 15).map((el) => ({
      tag: el.tagName,
      id: el.id,
      cls: String(el.className || '').slice(0, 80),
      z: getComputedStyle(el).zIndex,
      pe: getComputedStyle(el).pointerEvents,
      r: el.getBoundingClientRect(),
    }));

    out.labels = labels;
    out.listenerCounts = [...document.querySelectorAll('[data-nav-group-toggle]')].slice(0, 8).map((b) => {
      // getEventListeners only in console
      return { text: (b.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30) };
    });
    return out;
  });

  console.log(JSON.stringify(result, null, 2));
  fs.writeFileSync(path.join(__dirname, 'reports', 'SIDEBAR-CLICK-BLOCK-REPRO.json'), JSON.stringify(result, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
