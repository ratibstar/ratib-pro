/**
 * PERF-P4 verify — metrics skeleton hide vs afterEnter (evidence).
 *   node perf-p4-metrics-verify.js
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
const OUT = path.join(__dirname, 'reports', 'PERF-P4-METRICS-VERIFY.md');
const OUT_JSON = path.join(__dirname, 'reports', 'PERF-P4-METRICS-VERIFY.json');

const ROUTES = [
  { id: 'Inventory', match: /\/admin\/ops\/inventory(\/|$)/i, group: /المخزون|inventory/i },
  { id: 'Purchasing', match: /\/admin\/ops\/purchase-requests(\/|$)/i, group: /المشتريات|procurement|purchas/i },
  { id: 'HR', match: /\/admin\/hr(\/|$)/i, group: /الموارد البشرية|\bhr\b/i },
  { id: 'Companies', match: /\/admin\/companies(\/|$)/i, group: /شركات|companies/i },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function r1(n) {
  if (n == null || Number.isNaN(Number(n))) return null;
  return Math.round(Number(n) * 10) / 10;
}

async function goDash(page) {
  await page.goto(BASE + '/admin/?company_id=22&_p4=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.waitForTimeout(400);
}

async function resolveHref(page, route) {
  return page.evaluate((spec) => {
    const re = new RegExp(spec.match.source, spec.match.flags);
    const gre = new RegExp(spec.group.source, spec.group.flags);
    for (const b of document.querySelectorAll('[data-nav-group-toggle]')) {
      if (gre.test(b.textContent || '')) b.click();
    }
    const a = [...document.querySelectorAll('a[href]')].find((el) => {
      try {
        return re.test(new URL(el.href).pathname);
      } catch (e) {
        return false;
      }
    });
    return a ? a.href : null;
  }, {
    match: { source: route.match.source, flags: route.match.flags },
    group: { source: route.group.source, flags: route.group.flags },
  });
}

async function measure(page, href) {
  return page.evaluate(async (h) => {
    const t0 = performance.now();
    let afterEnterAt = null;
    const fetches = [];
    const origFetch = window.fetch;
    window.fetch = function (input, init) {
      const u = typeof input === 'string' ? input : input && input.url;
      const p = origFetch.apply(this, arguments);
      if (u && /module-metrics/i.test(String(u))) {
        const started = performance.now() - t0;
        fetches.push({ url: String(u), started });
        p.then(
          () => {
            fetches[fetches.length - 1].ended = performance.now() - t0;
          },
          () => {
            fetches[fetches.length - 1].ended = performance.now() - t0;
            fetches[fetches.length - 1].err = true;
          }
        );
      }
      return p;
    };
    document.addEventListener(
      'rateb:nav:afterEnter',
      () => {
        afterEnterAt = performance.now() - t0;
      },
      { once: true }
    );

    const skelSel = '.cm--page-stats.is-loading, [data-module-metrics-async].is-loading';
    const mainSel = '#rateb-main-content, main.rateb-content';

    await window.RatebNavInstant.navigate(h);

    let usableAt = null;
    let skelGoneAt = null;
    let skelFirst = null;
    let metricsReadyAt = null;
    const deadline = performance.now() + 8000;

    while (performance.now() < deadline) {
      const t = performance.now() - t0;
      const main = document.querySelector(mainSel);
      const skel = document.querySelector(skelSel);
      const ready = document.querySelector('[data-rateb-metrics-ready="1"], [data-metrics-placeholder="1"]');
      if (skel && skelFirst == null) skelFirst = t;
      if (!skel && skelFirst != null && skelGoneAt == null) skelGoneAt = t;
      if (!skel && skelFirst == null && skelGoneAt == null && t > (afterEnterAt || 0) + 50) {
        // never showed or cleared before first sample
        skelGoneAt = t;
      }
      if (ready && metricsReadyAt == null) metricsReadyAt = t;
      const textLen = main ? (main.innerText || '').trim().length : 0;
      const controls = main
        ? [...main.querySelectorAll('a[href], button, input, select')].filter((el) => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && !el.disabled;
          }).length
        : 0;
      if (usableAt == null && textLen > 80 && controls >= 2) usableAt = t;
      if (usableAt != null && skelGoneAt != null) break;
      await new Promise((r) => setTimeout(r, 40));
    }

    window.fetch = origFetch;
    const build = document.querySelector('script[src*="module-page-stats"]');
    const buildSrc = build ? build.src : null;
    return {
      afterEnterAt,
      usableAt,
      skelFirst,
      skelGoneAt,
      metricsReadyAt,
      skelGoneAfterEnter:
        afterEnterAt != null && skelGoneAt != null ? skelGoneAt - afterEnterAt : null,
      fetches,
      buildSrc,
      assetBuild: (window.RATEB_ASSET_BUILD || document.documentElement.getAttribute('data-asset-build') || null),
      href: location.href,
    };
  }, href);
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'p4-' + Date.now()), {
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
  const page = ctx.pages()[0] || (await ctx.newPage());
  const runs = [];
  for (const route of ROUTES) {
    await goDash(page);
    const href = await resolveHref(page, route);
    if (!href) {
      runs.push({ id: route.id, error: 'link_not_found' });
      continue;
    }
    try {
      const result = await measure(page, href);
      runs.push({ id: route.id, href, result });
    } catch (e) {
      runs.push({ id: route.id, href, error: String(e.message || e) });
    }
  }

  const lines = [];
  lines.push('# PERF-P4 Metrics UX Verify');
  lines.push('');
  lines.push('**Date:** ' + new Date().toISOString());
  lines.push('');
  lines.push('| Route | afterEnter | usable | skel gone | skel−afterEnter | metrics fetch start | Pass usable<400 | Pass skel<500 afterEnter |');
  lines.push('|-------|------------|--------|-----------|-----------------|---------------------|-----------------|--------------------------|');
  for (const run of runs) {
    if (run.error || !run.result) {
      lines.push('| ' + run.id + ' | ERROR |');
      continue;
    }
    const R = run.result;
    const fetchStart = R.fetches && R.fetches[0] ? r1(R.fetches[0].started) : null;
    const passU = R.usableAt != null && R.usableAt < 400;
    const passS = R.skelGoneAfterEnter != null && R.skelGoneAfterEnter < 500;
    lines.push(
      '| ' +
        run.id +
        ' | ' +
        r1(R.afterEnterAt) +
        ' | ' +
        r1(R.usableAt) +
        ' | ' +
        r1(R.skelGoneAt) +
        ' | ' +
        r1(R.skelGoneAfterEnter) +
        ' | ' +
        fetchStart +
        ' | ' +
        passU +
        ' | ' +
        passS +
        ' |'
    );
  }
  lines.push('');
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, lines.join('\n'));
  fs.writeFileSync(OUT_JSON, JSON.stringify({ generatedAt: new Date().toISOString(), runs }, null, 2));
  console.log(OUT);
  console.log(JSON.stringify(runs, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
