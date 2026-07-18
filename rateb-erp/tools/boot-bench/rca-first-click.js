/**
 * RCA ONLY — first sidebar click ignored / needs multiple clicks.
 * Measure-only. No production changes.
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
const OUT = path.join(__dirname, 'reports', 'rca-first-click-' + Date.now() + '.json');

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

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'rca-click-' + Date.now()), {
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

  // Instrument BEFORE any navigation
  await page.addInitScript(() => {
    const T = (window.__RCA_CLICK__ = {
      t0: performance.now(),
      events: [],
      scriptReady: null,
      bootAt: null,
      navInstantPresent: false,
    });
    const push = (type, detail) => {
      T.events.push(Object.assign({ t: Math.round((performance.now() - T.t0) * 10) / 10, type }, detail || {}));
    };
    T.push = push;

    // Watch for RatebNavInstant
    const watch = setInterval(() => {
      if (window.RatebNavInstant && !T.navInstantPresent) {
        T.navInstantPresent = true;
        T.scriptReady = Math.round((performance.now() - T.t0) * 10) / 10;
        push('RatebNavInstant_ready', {});
        clearInterval(watch);
      }
    }, 10);

    document.addEventListener('rateb-critical-js-ready', () => {
      push('rateb-critical-js-ready', {});
    });

    // Patch swapTo / onClick after script appears
    const patch = setInterval(() => {
      const api = window.RatebNavInstant;
      if (!api || api.__rcaPatched) return;
      api.__rcaPatched = true;
      const origNav = api.navigate;
      api.navigate = function (href, opts) {
        push('swapTo_call', { href: String(href).slice(0, 120), opts: opts || null });
        const p = origNav.apply(this, arguments);
        return Promise.resolve(p).then(
          (ok) => {
            push('swapTo_done', { ok: !!ok, href: String(href).slice(0, 120) });
            return ok;
          },
          (err) => {
            push('swapTo_fail', { err: String(err && err.message ? err.message : err).slice(0, 200) });
            throw err;
          }
        );
      };
      push('swapTo_patched', {});
      clearInterval(patch);
    }, 10);

    // Capture all clicks in capture phase (before and after handlers)
    document.addEventListener(
      'click',
      (ev) => {
        const a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        const btn = ev.target && ev.target.closest ? ev.target.closest('[data-nav-group-toggle]') : null;
        push('click_capture', {
          tag: (ev.target && ev.target.tagName) || null,
          href: a ? a.href : null,
          isNavLink: !!(a && a.classList && a.classList.contains('rateb-nav-link')),
          isGroupToggle: !!btn,
          defaultPrevented: ev.defaultPrevented,
          navInstant: !!window.RatebNavInstant,
          inTemplate: !!(a && a.closest && a.closest('template')),
        });
      },
      true
    );
    document.addEventListener(
      'click',
      (ev) => {
        const a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        push('click_bubble', {
          href: a ? a.href : null,
          defaultPrevented: ev.defaultPrevented,
        });
      },
      false
    );

    // Observe navigating lock by peeking closure is hard — wrap after boot via periodic check of console
    const origInfo = console.info;
    console.info = function () {
      try {
        if (arguments[0] === '[RATEB NAV]') {
          push('rateb_nav_log', { ms: arguments[1], mode: arguments[2], url: String(arguments[3] || '').slice(0, 120) });
        }
      } catch (e) {}
      return origInfo.apply(this, arguments);
    };
    const origWarn = console.warn;
    console.warn = function () {
      try {
        if (String(arguments[0] || '').indexOf('[RATEB NAV]') === 0) {
          push('rateb_nav_warn', { msg: String(arguments[1] || arguments[0]).slice(0, 200) });
        }
      } catch (e2) {}
      return origWarn.apply(this, arguments);
    };
  });

  const wall0 = Date.now();
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForSelector('#rateb-sidebar a.rateb-nav-link, #rateb-sidebar [data-nav-group-toggle]', {
    timeout: 60000,
  });

  // Snapshot readiness BEFORE first intentional click
  const pre = await page.evaluate(() => {
    const T = window.__RCA_CLICK__ || {};
    const links = [...document.querySelectorAll('#rateb-sidebar a.rateb-nav-link')].map((a) => ({
      href: a.href,
      text: (a.innerText || '').trim().slice(0, 40),
      visible: !!(a.offsetWidth || a.offsetHeight),
      inOpenGroup: !!(a.closest('.rateb-nav-group.is-open, .rateb-nav-subgroup.is-open') || !a.closest('.rateb-nav-group, .rateb-nav-subgroup')),
    }));
    const toggles = document.querySelectorAll('#rateb-sidebar [data-nav-group-toggle]').length;
    const lazyTpl = document.querySelectorAll('template[data-rateb-nav-lazy]').length;
    return {
      t: Math.round(performance.now() * 10) / 10,
      navInstant: !!window.RatebNavInstant,
      scriptReady: T.scriptReady,
      eventsSoFar: (T.events || []).length,
      visibleLinks: links.filter((l) => l.visible).length,
      totalLinksInDom: links.length,
      toggles,
      lazyTpl,
      sampleVisible: links.filter((l) => l.visible).slice(0, 8),
      criticalReadyFired: (T.events || []).some((e) => e.type === 'rateb-critical-js-ready'),
    };
  });

  // CASE A: click a VISIBLE top-level module link ASAP (may race script load)
  const targetHref = await page.evaluate(() => {
    const candidates = [...document.querySelectorAll('#rateb-sidebar a.rateb-nav-link')].filter((a) => {
      if (!(a.offsetWidth || a.offsetHeight)) return false;
      const href = a.getAttribute('href') || '';
      if (/\/admin\/?$/.test(href.replace(/\/+$/, '')) || href.endsWith('/admin')) return false;
      return /\/admin\//.test(href);
    });
    return candidates[0] ? candidates[0].href : null;
  });

  const results = { pre, targetHref, cases: [] };

  async function clickAndWatch(label, href, doubleClickGapMs) {
    const tClick = Date.now() - wall0;
    const beforeHref = page.url();
    if (href) {
      await page.evaluate((h) => {
        const a = [...document.querySelectorAll('a.rateb-nav-link')].find((x) => x.href === h || (x.getAttribute('href') || '').includes(h));
        if (a) a.scrollIntoView({ block: 'center' });
      }, href);
    }
    const locator = href
      ? page.locator(`a.rateb-nav-link[href="${href}"], a.rateb-nav-link[href*="${new URL(href).pathname}"]`).first()
      : page.locator('#rateb-sidebar a.rateb-nav-link').first();

    const click1 = Date.now();
    await locator.click({ force: true, timeout: 10000 }).catch((e) => ({ error: String(e) }));
    if (doubleClickGapMs != null) {
      await page.waitForTimeout(doubleClickGapMs);
      await locator.click({ force: true, timeout: 5000 }).catch(() => null);
    }

    // Wait up to 8s for URL change or swap log
    const deadline = Date.now() + 8000;
    let changed = false;
    while (Date.now() < deadline) {
      const cur = page.url();
      if (cur !== beforeHref) {
        changed = true;
        break;
      }
      const swapped = await page.evaluate(() => {
        const T = window.__RCA_CLICK__;
        return (T.events || []).some((e) => e.type === 'swapTo_done' || e.type === 'rateb_nav_log' || e.type === 'rateb_nav_warn');
      });
      if (swapped) break;
      await page.waitForTimeout(50);
    }

    const snap = await page.evaluate((lab) => {
      const T = window.__RCA_CLICK__ || { events: [] };
      return {
        label: lab,
        href: location.href,
        navInstant: !!window.RatebNavInstant,
        events: T.events.slice(),
        scriptReady: T.scriptReady,
      };
    }, label);

    results.cases.push({
      label,
      wallClickMs: tClick,
      clickDurationMs: Date.now() - click1,
      beforeHref,
      afterHref: page.url(),
      urlChanged: changed || page.url() !== beforeHref,
      doubleClickGapMs,
      snap,
    });
  }

  // Immediate first click (race window)
  if (targetHref) {
    await clickAndWatch('A_first_visible_link_asap', targetHref, null);
  } else {
    // Open a closed group then click — still first module nav
    await page.locator('#rateb-sidebar [data-nav-group-toggle]').first().click({ force: true });
    await page.waitForTimeout(100);
    const href2 = await page.evaluate(() => {
      const a = document.querySelector('#rateb-sidebar .rateb-nav-group.is-open a.rateb-nav-link, #rateb-sidebar a.rateb-nav-link');
      return a ? a.href : null;
    });
    await clickAndWatch('A_after_open_group', href2, null);
  }

  // Return dashboard
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('#rateb-sidebar', { timeout: 30000 });
  // Wait until nav instant ready
  await page.waitForFunction(() => !!window.RatebNavInstant, { timeout: 30000 });
  await page.waitForTimeout(300);

  const hrefB = await page.evaluate(() => {
    const a = [...document.querySelectorAll('#rateb-sidebar a.rateb-nav-link')].find(
      (x) => (x.offsetWidth || x.offsetHeight) && /\/admin\//.test(x.href) && !/\/admin\/?$/.test(x.pathname.replace(/\/+$/, ''))
    );
    return a ? a.href : null;
  });

  // CASE B: double-click quickly while first nav in flight (navigating mutex)
  await clickAndWatch('B_double_click_80ms', hrefB, 80);

  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForFunction(() => !!window.RatebNavInstant, { timeout: 30000 });
  await page.waitForTimeout(500);

  // CASE C: single click after ready — then second navigation (warm)
  const hrefC = hrefB;
  await clickAndWatch('C1_single_after_ready', hrefC, null);
  await page.waitForTimeout(200);
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForFunction(() => !!window.RatebNavInstant, { timeout: 30000 });
  await page.waitForTimeout(200);
  await clickAndWatch('C2_second_nav_warm', hrefC, null);

  // Probe navigating lock by evaluating source behavior: fire two navigate() calls
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForFunction(() => !!window.RatebNavInstant, { timeout: 30000 });
  const mutexProbe = await page.evaluate(async (h) => {
    const api = window.RatebNavInstant;
    const t0 = performance.now();
    const p1 = api.navigate(h);
    const p2 = api.navigate(h); // should be ignored if navigating lock holds
    const r1 = await p1;
    const r2 = await p2;
    return {
      ms: Math.round(performance.now() - t0),
      firstOk: r1,
      secondOk: r2,
      note: 'secondOk===false while first in-flight proves mutex swallows click',
    };
  }, hrefC || BASE + '/admin/hr');

  results.mutexProbe = mutexProbe;
  results.finalEvents = await page.evaluate(() => (window.__RCA_CLICK__ || {}).events || []);

  await ctx.close();
  fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
  console.log(JSON.stringify({ out: OUT, pre: results.pre, mutexProbe, cases: results.cases.map((c) => ({
    label: c.label,
    urlChanged: c.urlChanged,
    clickMs: c.clickDurationMs,
    before: c.beforeHref,
    after: c.afterHref,
    eventTypes: (c.snap.events || []).map((e) => e.type + '@' + e.t),
  })) }, null, 2));
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
