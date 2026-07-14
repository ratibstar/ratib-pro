/**
 * REAL user startup investigation — production RATIB ERP only.
 * Login click → dashboard usable. No synthetic benches.
 *
 *   node real-user-startup-trace.js
 *   RATEB_ERP_USER=... RATEB_ERP_PASS=... node real-user-startup-trace.js
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const USER = process.env.RATEB_ERP_USER || 'admin@rateb.sa';
const PASS = process.env.RATEB_ERP_PASS || 'password';
const OUT_DIR = path.join(__dirname, 'reports');

function nowMs(t0) {
  return Math.round((Date.now() - t0) * 10) / 10;
}

function shortUrl(u) {
  try {
    const x = new URL(u);
    return x.pathname + x.search;
  } catch {
    return String(u).slice(0, 180);
  }
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const events = [];
  const t0Ref = { t0: Date.now() };
  const push = (type, detail) => {
    events.push({ t: nowMs(t0Ref.t0), type, ...detail });
  };

  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
  });
  const context = await browser.newContext({
    viewport: { width: 1365, height: 900 },
    locale: 'ar-SA',
  });
  const page = await context.newPage();

  page.on('console', (msg) => {
    push('console', { level: msg.type(), text: String(msg.text()).slice(0, 300) });
  });
  page.on('pageerror', (err) => {
    push('pageerror', { message: String(err.message || err).slice(0, 300) });
  });
  page.on('framenavigated', (frame) => {
    if (frame === page.mainFrame()) {
      push('navigate', { url: shortUrl(frame.url()), full: frame.url() });
    }
  });
  page.on('request', (req) => {
    const rt = req.resourceType();
    if (['document', 'xhr', 'fetch', 'script', 'stylesheet', 'other'].includes(rt) || rt === 'websocket') {
      push('request', {
        method: req.method(),
        resourceType: rt,
        url: shortUrl(req.url()),
        full: req.url().slice(0, 300),
      });
    }
  });
  page.on('response', async (res) => {
    const req = res.request();
    const rt = req.resourceType();
    if (!['document', 'xhr', 'fetch', 'script', 'stylesheet'].includes(rt)) return;
    let cl = null;
    try {
      cl = res.headers()['content-length'] || null;
    } catch (_) {}
    push('response', {
      status: res.status(),
      resourceType: rt,
      url: shortUrl(res.url()),
      contentLength: cl,
      fromServiceWorker: res.fromServiceWorker(),
    });
  });

  // --- Phase A: open login ---
  t0Ref.t0 = Date.now();
  push('marker', { name: 'GOTO_LOGIN_START' });
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 90000 });
  push('marker', { name: 'LOGIN_DOMCONTENTLOADED', title: await page.title() });
  await page.waitForSelector('#password-form', { timeout: 30000 });
  push('marker', { name: 'LOGIN_FORM_READY' });

  // Clear event buffer noise before click? Keep all — user asked complete. Reset t0 at login click.
  const preLoginEvents = events.splice(0, events.length);

  // --- Phase B: click login ---
  t0Ref.t0 = Date.now();
  push('marker', { name: 'LOGIN_CLICK', user: USER });

  await page.fill('#email', USER);
  await page.fill('#password', PASS);

  // Observe DOM for spinners / overlays after submit
  await page.exposeBinding('__ratebTracePush', (_s, payload) => {
    push(payload.type || 'dom', payload);
  });

  await page.evaluate(() => {
    const spinSel = [
      '.spinner-border',
      '.fa-spinner',
      '.fa-spin',
      '[data-loading]',
      '.is-loading',
      '.rateb-loading',
      '#rateb-offline-warm-progress',
      '.loading',
      '[aria-busy="true"]',
    ].join(',');
    const mo = new MutationObserver(() => {
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && el.offsetParent !== null;
      });
      if (spins.length) {
        window.__ratebTracePush({
          type: 'spinner_visible',
          count: spins.length,
          sample: spins.slice(0, 3).map((el) => el.className || el.id || el.tagName),
        });
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true });
    window.__ratebSpinMo = mo;

    // Patch timers / fetch lightly for tracing (non-destructive log only)
    const _fetch = window.fetch;
    if (_fetch) {
      window.fetch = function (...args) {
        try {
          const u = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url) || '';
          window.__ratebTracePush({ type: 'fetch_call', url: String(u).slice(0, 240) });
        } catch (_) {}
        return _fetch.apply(this, args).then(
          (r) => {
            try {
              window.__ratebTracePush({
                type: 'fetch_done',
                url: String(r.url || '').slice(0, 240),
                status: r.status,
              });
            } catch (_) {}
            return r;
          },
          (err) => {
            window.__ratebTracePush({ type: 'fetch_fail', message: String(err && err.message) });
            throw err;
          }
        );
      };
    }
    const _setTimeout = window.setTimeout;
    window.setTimeout = function (fn, ms, ...rest) {
      if (typeof ms === 'number' && ms >= 200) {
        try {
          window.__ratebTracePush({ type: 'setTimeout', ms, stack: new Error().stack.split('\n').slice(0, 4).join(' | ') });
        } catch (_) {}
      }
      return _setTimeout.call(this, fn, ms, ...rest);
    };
    const _setInterval = window.setInterval;
    window.setInterval = function (fn, ms, ...rest) {
      try {
        window.__ratebTracePush({ type: 'setInterval', ms });
      } catch (_) {}
      return _setInterval.call(this, fn, ms, ...rest);
    };
  });

  const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }).catch((e) => {
    push('marker', { name: 'NAV_AFTER_LOGIN_TIMEOUT', error: String(e.message || e) });
    return null;
  });

  await Promise.all([
    navPromise,
    page.click('#password-form button[type="submit"]'),
  ]);

  push('marker', {
    name: 'AFTER_LOGIN_NAV',
    url: page.url(),
    title: await page.title(),
  });

  // Detect login failure
  const loginFailed = await page.evaluate(() => {
    const body = document.body ? document.body.innerText : '';
    const stillLogin = /\/login/i.test(location.pathname);
    const alert = document.querySelector('.alert-danger, .invalid-feedback, .rateb-flash-error');
    return {
      stillLogin,
      alert: alert ? alert.textContent.trim().slice(0, 200) : null,
      bodyHint: stillLogin ? body.slice(0, 200) : null,
    };
  });
  push('marker', { name: 'LOGIN_RESULT', ...loginFailed });

  if (loginFailed.stillLogin) {
    // Auth failed — cannot trace dashboard. Export and exit.
    const report = {
      ok: false,
      reason: 'LOGIN_FAILED_ON_REAL_APP',
      userTried: USER,
      loginFailed,
      preLoginEvents,
      events,
      generatedAt: new Date().toISOString(),
    };
    const out = path.join(OUT_DIR, `real-user-startup-LOGIN-FAILED-${Date.now()}.json`);
    fs.writeFileSync(out, JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ out, loginFailed, eventCount: events.length }, null, 2));
    await browser.close();
    process.exit(2);
  }

  // --- Phase C: wait until dashboard usable ---
  // Heuristics: main content visible, no fullscreen spinner, sidebar present, network quiet
  const usableDeadline = Date.now() + 120000;
  let usableAt = null;
  let lastNet = Date.now();

  page.on('request', () => {
    lastNet = Date.now();
  });
  page.on('response', () => {
    lastNet = Date.now();
  });

  while (Date.now() < usableDeadline) {
    const snap = await page.evaluate(() => {
      const spinSel = '.spinner-border, .fa-spinner, .fa-spin, .is-loading, #rateb-offline-warm-progress, [aria-busy="true"]';
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
      });
      const sidebar = !!document.querySelector('#rateb-sidebar, .rateb-sidebar, aside.rateb-sidebar');
      const main = document.querySelector('main, .rateb-main, #rateb-boot-bench-root');
      const mainText = main ? (main.innerText || '').trim().length : 0;
      const bodyText = (document.body && document.body.innerText) || '';
      const hasDashboardSignal =
        /لوحة|dashboard|admin|مرحبا|الإدارة|الطلبات|المخزون/i.test(bodyText) || mainText > 40;
      const overlays = [...document.querySelectorAll('.modal.show, .offcanvas.show')].length;
      const sw = !!(navigator.serviceWorker && navigator.serviceWorker.controller);
      const boot = window.__RATEB_BOOT__ || null;
      const offlineCfg = window.__RATEB_ERP_SHELL_OFFLINE__ ? true : false;
      return {
        spins: spins.length,
        spinSample: spins.slice(0, 3).map((e) => e.className || e.id),
        sidebar,
        mainText,
        hasDashboardSignal,
        overlays,
        sw,
        boot,
        offlineCfg,
        href: location.href,
        readyState: document.readyState,
      };
    });

    push('poll_usable', snap);

    const netQuiet = Date.now() - lastNet > 1500;
    if (
      snap.sidebar &&
      snap.hasDashboardSignal &&
      snap.spins === 0 &&
      snap.readyState === 'complete' &&
      netQuiet
    ) {
      usableAt = nowMs(t0Ref.t0);
      push('marker', { name: 'DASHBOARD_USABLE', t: usableAt, snap });
      break;
    }
    await new Promise((r) => setTimeout(r, 250));
  }

  if (usableAt == null) {
    push('marker', { name: 'DASHBOARD_USABLE_TIMEOUT', url: page.url() });
  }

  // Final resource & SW inventory
  const finalState = await page.evaluate(async () => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = p.startTime;
    });
    let regs = [];
    if (navigator.serviceWorker) {
      try {
        regs = (await navigator.serviceWorker.getRegistrations()).map((r) => ({
          scope: r.scope,
          active: r.active && r.active.scriptURL,
          waiting: !!(r.waiting),
          installing: !!(r.installing),
        }));
      } catch (_) {}
    }
    const resources = performance.getEntriesByType('resource').map((r) => ({
      name: r.name.replace(location.origin, ''),
      initiatorType: r.initiatorType,
      duration: Math.round(r.duration),
      transferSize: r.transferSize,
      startTime: Math.round(r.startTime),
      responseEnd: Math.round(r.responseEnd),
    }));
    resources.sort((a, b) => b.duration - a.duration);
    return {
      url: location.href,
      title: document.title,
      paints,
      nav: {
        responseStart: nav.responseStart,
        domContentLoadedEventEnd: nav.domContentLoadedEventEnd,
        loadEventEnd: nav.loadEventEnd,
      },
      serviceWorkers: regs,
      topSlowResources: resources.slice(0, 25),
      allResourceCount: resources.length,
      boot: window.__RATEB_BOOT__ || null,
    };
  });
  push('marker', { name: 'FINAL_STATE', finalState });

  // Find longest gaps between events
  const gaps = [];
  for (let i = 1; i < events.length; i++) {
    const dt = events[i].t - events[i - 1].t;
    if (dt >= 200) {
      gaps.push({
        dt,
        from: { t: events[i - 1].t, type: events[i - 1].type, name: events[i - 1].name, url: events[i - 1].url },
        to: { t: events[i].t, type: events[i].type, name: events[i].name, url: events[i].url },
      });
    }
  }
  gaps.sort((a, b) => b.dt - a.dt);

  const report = {
    ok: usableAt != null,
    generatedAt: new Date().toISOString(),
    app: BASE,
    user: USER,
    usableAtMs: usableAt,
    preLoginEventCount: preLoginEvents.length,
    eventCount: events.length,
    topGapsMs: gaps.slice(0, 15),
    timeline: events,
    preLoginEvents,
    finalState,
  };

  const out = path.join(OUT_DIR, `real-user-startup-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));

  // Human timeline excerpt
  const lines = [];
  lines.push('REAL USER STARTUP TIMELINE (ms from Login click)');
  lines.push('='.repeat(60));
  for (const e of events) {
    if (
      e.type === 'marker' ||
      e.type === 'navigate' ||
      e.type === 'spinner_visible' ||
      e.type === 'fetch_call' ||
      e.type === 'setTimeout' ||
      e.type === 'setInterval' ||
      (e.type === 'request' && (e.resourceType === 'document' || e.resourceType === 'xhr' || e.resourceType === 'fetch')) ||
      (e.type === 'response' && (e.resourceType === 'document' || e.resourceType === 'xhr' || e.resourceType === 'fetch'))
    ) {
      const bits = [e.type];
      if (e.name) bits.push(e.name);
      if (e.method) bits.push(e.method);
      if (e.status != null) bits.push('HTTP ' + e.status);
      if (e.url) bits.push(e.url);
      if (e.ms != null) bits.push('ms=' + e.ms);
      if (e.full && e.type === 'navigate') bits.push(e.full);
      lines.push(String(e.t).padStart(8, '0') + '  ' + bits.join(' | '));
    }
  }
  lines.push('');
  lines.push('TOP GAPS (>=200ms)');
  for (const g of gaps.slice(0, 10)) {
    lines.push(
      `  +${g.dt}ms  after ${g.from.type}/${g.from.name || g.from.url || ''} → ${g.to.type}/${g.to.name || g.to.url || ''}`
    );
  }
  const txt = path.join(OUT_DIR, path.basename(out).replace(/\.json$/, '.txt'));
  fs.writeFileSync(txt, lines.join('\n'));
  console.log(lines.join('\n'));
  console.log('\nREPORT:', out);
  console.log('TEXT:', txt);

  await browser.close();
  process.exit(usableAt != null ? 0 : 3);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
