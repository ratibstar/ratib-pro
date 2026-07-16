/**
 * Focused cold-vs-warm REAL sidebar click (mousedown→usable).
 * Clears Cache API + disables further idle by exhausting prefetchSeen via... we clear caches only.
 * No production code changes.
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

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

const TARGETS = [
  { id: 'hr', prefer: '/admin/hr', match: /\/admin\/hr(\/|\?|$)/ },
  { id: 'inventory', prefer: '/admin/ops/inventory', match: /\/admin\/ops\/inventory/ },
  { id: 'accounting', prefer: '/admin/ops/accounting', match: /\/admin\/ops\/accounting/ },
];

(async () => {
  const mint = JSON.parse(ssh('php /tmp/remote-auth.php mint'));
  const context = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'rateb-cold-' + Date.now()), {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1400, height: 900 },
  });
  await context.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  await page.addInitScript(() => {
    window.__C = { click: null, pref: [], reinit: [] };
    const of = window.fetch.bind(window);
    window.fetch = function (input, init) {
      const url = typeof input === 'string' ? input : (input && input.url) || '';
      const headers = (init && init.headers) || {};
      const getH = (k) =>
        typeof headers.get === 'function' ? headers.get(k) || '' : headers[k] || headers[k.toLowerCase()] || '';
      const isPrefetch = String(getH('X-Rateb-Prefetch') || '') === '1';
      const isSwap = String(getH('X-Rateb-Nav-Swap') || '') === '1';
      const t0 = performance.now();
      const stack = (new Error().stack || '').split('\n').slice(0, 8).map((s) => s.trim());
      return of(input, init).then((res) => {
        const row = {
          url: String(url).slice(0, 180),
          isPrefetch,
          isSwap,
          status: res.status,
          ms: Math.round(performance.now() - t0),
          stack,
        };
        if (isPrefetch) window.__C.pref.push(row);
        if (window.__C.click) {
          window.__C.click.fetches = window.__C.click.fetches || [];
          window.__C.click.fetches.push(row);
        }
        return res;
      });
    };
    const wrap = () => {
      if (!window.RatebApp || !window.RatebApp.reinit || window.RatebApp.__cw) return;
      const o = window.RatebApp.reinit.bind(window.RatebApp);
      window.RatebApp.reinit = function () {
        const t0 = performance.now();
        const stack = (new Error().stack || '').split('\n').slice(0, 10).map((s) => s.trim());
        const r = o();
        const call = { ms: Math.round(performance.now() - t0), stack };
        window.__C.reinit.push(call);
        if (window.__C.click) {
          window.__C.click.reinit = window.__C.click.reinit || [];
          window.__C.click.reinit.push(call);
        }
        return r;
      };
      window.RatebApp.__cw = true;
    };
    setInterval(wrap, 40);
    document.addEventListener(
      'mousedown',
      (ev) => {
        const a = ev.target && ev.target.closest && ev.target.closest('a.rateb-nav-link, #rateb-sidebar a[href]');
        if (!a) return;
        window.__C.click = {
          t0: performance.now(),
          href: a.href,
          stages: [{ name: 'mousedown', t: 0 }],
          fetches: [],
          reinit: [],
        };
      },
      true
    );
    document.addEventListener(
      'click',
      () => {
        if (!window.__C.click) return;
        window.__C.click.stages.push({
          name: 'click',
          t: Math.round((performance.now() - window.__C.click.t0) * 10) / 10,
        });
      },
      true
    );
    document.addEventListener('rateb:nav:beforeLeave', () => {
      if (!window.__C.click) return;
      window.__C.click.stages.push({
        name: 'beforeLeave',
        t: Math.round((performance.now() - window.__C.click.t0) * 10) / 10,
      });
    });
    const ps = history.pushState.bind(history);
    history.pushState = function (...a) {
      if (window.__C.click) {
        window.__C.click.stages.push({
          name: 'pushState',
          t: Math.round((performance.now() - window.__C.click.t0) * 10) / 10,
        });
      }
      return ps(...a);
    };
    document.addEventListener('rateb:nav:afterEnter', (ev) => {
      if (!window.__C.click) return;
      const t = Math.round((performance.now() - window.__C.click.t0) * 10) / 10;
      window.__C.click.afterEnter_t = t;
      window.__C.click.detail = ev.detail || null;
      window.__C.click.stages.push({ name: 'afterEnter', t, detail: ev.detail });
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (!window.__C.click || window.__C.click.usable_t != null) return;
          window.__C.click.usable_t = Math.round((performance.now() - window.__C.click.t0) * 10) / 10;
          window.__C.click.stages.push({ name: 'usable', t: window.__C.click.usable_t });
        });
      });
    });
  });

  async function expandNav() {
    await page.evaluate(() => {
      document.querySelectorAll('[data-nav-group-toggle], .rateb-nav-group-toggle').forEach((b) => {
        try {
          b.click();
        } catch (e) {}
      });
      document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
        el.style.display = 'block';
        el.hidden = false;
      });
    });
  }

  async function clearOpsCaches() {
    return page.evaluate(async () => {
      if (!caches) return { cleared: 0 };
      const keys = await caches.keys();
      let n = 0;
      for (const k of keys) {
        if (/ops-pages|erp-ops|rateb-erp/i.test(k)) {
          await caches.delete(k);
          n++;
        }
      }
      // Also delete individual matches in remaining
      for (const k of await caches.keys()) {
        const c = await caches.open(k);
        const reqs = await c.keys();
        for (const req of reqs) {
          if (/\/admin\//.test(req.url)) {
            await c.delete(req);
            n++;
          }
        }
      }
      return { cleared: n, remaining: await caches.keys() };
    });
  }

  async function clickTarget(mod) {
    await page.evaluate(() => {
      window.__C.click = null;
      window.__C.pref = [];
    });
    const found = await page.evaluate(({ prefer, src }) => {
      document.querySelectorAll('[data-rca]').forEach((el) => el.removeAttribute('data-rca'));
      const re = new RegExp(src);
      const links = [...document.querySelectorAll('#rateb-sidebar a[href], a.rateb-nav-link[href]')];
      const hit =
        links.find((a) => (a.getAttribute('href') || '').indexOf(prefer) !== -1) ||
        links.find((a) => re.test(a.href));
      if (!hit) return { ok: false, n: links.length };
      hit.setAttribute('data-rca', '1');
      return { ok: true, href: hit.href, text: (hit.innerText || '').trim().slice(0, 40) };
    }, { prefer: mod.prefer, src: mod.match.source });
    if (!found.ok) return { error: 'link_not_found', found };
    const wall0 = Date.now();
    const loc = page.locator('a[data-rca="1"]').first();
    await loc.scrollIntoViewIfNeeded().catch(() => {});
    await loc.dispatchEvent('mousedown');
    await loc.click({ force: true });
    await page
      .waitForFunction(() => window.__C && window.__C.click && window.__C.click.afterEnter_t != null, null, {
        timeout: 45000,
      })
      .catch(() => null);
    await page.waitForTimeout(500);
    const snap = await page.evaluate(() => ({
      click: window.__C.click,
      pref: window.__C.pref.slice(),
      href: location.href,
    }));
    await page.evaluate(() => document.querySelectorAll('[data-rca]').forEach((el) => el.removeAttribute('data-rca')));
    return { wall_ms: Date.now() - wall0, link: found, ...snap };
  }

  const results = {};

  // Load dashboard WITHOUT SW warm; clear caches; wait briefly (idle prefetch may start — record it)
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 180000 });
  await expandNav();
  const clear1 = await clearOpsCaches();
  await page.waitForTimeout(800);

  for (const mod of TARGETS) {
    // Ensure on dashboard (different page) before cold click
    if (!/\/admin\/?(\?|$)/.test(new URL(page.url()).pathname)) {
      await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
      await expandNav();
    }
    await clearOpsCaches();
    await page.waitForTimeout(300);

    console.error('[cold]', mod.id);
    const cold = await clickTarget(mod);

    // Leave module then return for warm second click (cache should now hold HTML)
    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await expandNav();
    await page.waitForTimeout(400);
    // Do NOT clear caches before warm
    console.error('[warm]', mod.id);
    const warm = await clickTarget(mod);

    results[mod.id] = {
      cold,
      warm,
      cold_ae: cold.click && cold.click.afterEnter_t,
      warm_ae: warm.click && warm.click.afterEnter_t,
      cold_usable: cold.click && cold.click.usable_t,
      warm_usable: warm.click && warm.click.usable_t,
      cold_fromCache: cold.click && cold.click.detail && cold.click.detail.fromCache,
      warm_fromCache: warm.click && warm.click.detail && warm.click.detail.fromCache,
      cold_swap_ms: cold.click && cold.click.detail && cold.click.detail.ms,
      warm_swap_ms: warm.click && warm.click.detail && warm.click.detail.ms,
      cold_prefetch_during: ((cold.click && cold.click.fetches) || [])
        .filter((f) => f.isPrefetch)
        .sort((a, b) => b.ms - a.ms)
        .slice(0, 10),
      cold_swap_fetch: ((cold.click && cold.click.fetches) || []).filter((f) => f.isSwap),
      warm_swap_fetch: ((warm.click && warm.click.fetches) || []).filter((f) => f.isSwap),
      cold_reinit: cold.click && cold.click.reinit,
      warm_reinit: warm.click && warm.click.reinit,
    };
  }

  // Offline after warm HR exists
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await expandNav();
  await clickTarget(TARGETS[0]); // ensure HR cached
  await context.setOffline(true);
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
  await expandNav();
  const off1 = await clickTarget(TARGETS[0]);
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
  await expandNav();
  const off2 = await clickTarget(TARGETS[0]);
  await context.setOffline(false);

  const out = {
    at: new Date().toISOString(),
    clear1,
    cold_vs_warm: results,
    offline: {
      first: {
        wall: off1.wall_ms,
        ae: off1.click && off1.click.afterEnter_t,
        usable: off1.click && off1.click.usable_t,
        fromCache: off1.click && off1.click.detail && off1.click.detail.fromCache,
        swap_ms: off1.click && off1.click.detail && off1.click.detail.ms,
        stages: off1.click && off1.click.stages,
        reinit: off1.click && off1.click.reinit,
      },
      second: {
        wall: off2.wall_ms,
        ae: off2.click && off2.click.afterEnter_t,
        usable: off2.click && off2.click.usable_t,
        fromCache: off2.click && off2.click.detail && off2.click.detail.fromCache,
        swap_ms: off2.click && off2.click.detail && off2.click.detail.ms,
      },
    },
  };
  const p = path.join(__dirname, 'reports', 'enterprise-rca-cold-warm.json');
  fs.writeFileSync(p, JSON.stringify(out, null, 2));
  console.log(
    JSON.stringify(
      {
        out: p,
        cold_vs_warm: Object.fromEntries(
          Object.entries(results).map(([k, v]) => [
            k,
            {
              cold_ae: v.cold_ae,
              warm_ae: v.warm_ae,
              cold_usable: v.cold_usable,
              warm_usable: v.warm_usable,
              cold_cache: v.cold_fromCache,
              warm_cache: v.warm_fromCache,
              cold_swap: v.cold_swap_ms,
              warm_swap: v.warm_swap_ms,
              cold_wall: v.cold.wall_ms,
              warm_wall: v.warm.wall_ms,
              cold_prefetch0: v.cold_prefetch_during[0],
              cold_swap_fetch: v.cold_swap_fetch,
              warm_swap_fetch: v.warm_swap_fetch,
              cold_reinit_n: (v.cold_reinit || []).length,
            },
          ])
        ),
        offline: out.offline,
      },
      null,
      2
    )
  );
  await context.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
