/**
 * After opening largest group: are sibling toggles covered at their natural positions?
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
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'cover-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
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
  await page.goto(BASE + '/admin/ops/notifications?company_id=22&_t=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForTimeout(1500);

  // Force-show PWA banner to verify it no longer covers sidebar toggles
  await page.evaluate(() => {
    localStorage.removeItem('rateb_erp_pwa_install_dismissed');
    const old = document.getElementById('rateb-erp-pwa-install');
    if (old) old.remove();
    const bar = document.createElement('div');
    bar.id = 'rateb-erp-pwa-install';
    bar.className = 'rateb-erp-pwa-install';
    bar.style.cssText =
      'position:fixed;z-index:900;inset-inline-start:calc(268px + 1rem);inset-inline-end:1rem;bottom:1rem;max-width:22rem;' +
      'padding:0.85rem 1rem;border-radius:0.75rem;background:#161b22;color:#e8eaed;';
    bar.textContent = 'تثبيت RATEB ERP كتطبيق';
    document.body.appendChild(bar);
  });

  const out = await page.evaluate(() => {
    const side = document.getElementById('rateb-sidebar');
    // Open HR (largest)
    const groups = [...side.querySelectorAll('[data-nav-group]')].filter(
      (g) => g.querySelector(':scope > [data-nav-group-toggle]') && !g.classList.contains('rateb-nav-subgroup')
    );
    const hr = groups.find((g) => (g.textContent || '').includes('الموارد البشرية')) || groups[0];
    hr.querySelector(':scope > [data-nav-group-toggle]').click();

    const siblings = groups.filter((g) => g !== hr);
    const hits = siblings.map((g) => {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      const r = btn.getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + Math.min(12, r.height / 2);
      const top = document.elementFromPoint(x, y);
      const inView = r.top >= 0 && r.bottom <= window.innerHeight && r.height > 0;
      const isPwa = !!(top && top.closest && top.closest('#rateb-erp-pwa-install'));
      return {
        label: (btn.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 28),
        y: Math.round(r.top),
        inView,
        hitsToggle: !!(top && (top === btn || btn.contains(top))),
        coveredByPwa: isPwa,
      };
    });

    const banner = document.getElementById('rateb-erp-pwa-install');
    const br = banner ? banner.getBoundingClientRect() : null;
    const sr = side.getBoundingClientRect();
    const overlapX = br && !(br.right <= sr.left || br.left >= sr.right);
    const overlapY = br && !(br.bottom <= sr.top || br.top >= sr.bottom);

    return {
      delegated: side.getAttribute('data-rateb-nav-delegated'),
      hrOpen: hr.classList.contains('is-open'),
      openCount: side.querySelectorAll('[data-nav-group].is-open').length,
      bannerRect: br && { l: Math.round(br.left), r: Math.round(br.right), t: Math.round(br.top), b: Math.round(br.bottom) },
      sideRect: { l: Math.round(sr.left), r: Math.round(sr.right) },
      overlapSidebar: !!(overlapX && overlapY),
      coveredByPwa: hits.filter((h) => h.coveredByPwa).length,
      missedToggle: hits.filter((h) => h.inView && !h.hitsToggle).length,
      hits: hits.filter((h) => h.inView).slice(-4),
    };
  });

  console.log(JSON.stringify(out, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
