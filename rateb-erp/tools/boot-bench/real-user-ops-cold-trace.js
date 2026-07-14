/**
 * Real user cold path: Login → /admin/ops (full offline client) → usable.
 * Clears SW/caches first. Restores nothing about password (caller restores).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://rateb.sa/rateb-erp/public';
const USER = process.env.RATEB_ERP_USER;
const PASS = process.env.RATEB_ERP_PASS;
const OUT = path.join(__dirname, 'reports');

function tms(t0) {
  return Math.round((Date.now() - t0) * 10) / 10;
}

(async () => {
  if (!USER || !PASS) throw new Error('RATEB_ERP_USER/PASS required');
  fs.mkdirSync(OUT, { recursive: true });
  const events = [];
  let t0 = Date.now();
  const push = (type, d = {}) => events.push({ t: tms(t0), type, ...d });

  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
  });
  const context = await browser.newContext({ viewport: { width: 1365, height: 900 }, locale: 'ar-SA' });
  const page = await context.newPage();

  page.on('framenavigated', (f) => {
    if (f === page.mainFrame()) push('navigate', { url: f.url() });
  });
  page.on('request', (req) => {
    const rt = req.resourceType();
    if (['document', 'xhr', 'fetch', 'script'].includes(rt)) {
      push('request', { method: req.method(), rt, url: req.url().replace('https://rateb.sa', '') });
    }
  });
  page.on('response', (res) => {
    const rt = res.request().resourceType();
    if (['document', 'xhr', 'fetch', 'script'].includes(rt)) {
      push('response', {
        status: res.status(),
        rt,
        url: res.url().replace('https://rateb.sa', ''),
        fromSW: res.fromServiceWorker(),
      });
    }
  });
  page.on('console', (m) => {
    const text = m.text();
    if (/RATIB|offline|service.?worker|reload|error|fail/i.test(text)) {
      push('console', { level: m.type(), text: text.slice(0, 250) });
    }
  });

  // Warm login page
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 90000 });

  // Cold SW/caches
  await page.evaluate(async () => {
    if (navigator.serviceWorker) {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map((r) => r.unregister()));
    }
    if (window.caches) {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    }
  });
  push('marker', { name: 'SW_AND_CACHES_CLEARED' });

  // Reset clock at login click
  t0 = Date.now();
  events.length = 0;
  push('marker', { name: 'LOGIN_CLICK' });

  await page.fill('#email', USER);
  await page.fill('#password', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    page.click('#password-form button[type="submit"]'),
  ]);
  push('marker', { name: 'POST_LOGIN_LANDING', url: page.url() });

  // Go to ops (full offline SDK) — typical daily work surface
  const opsUrl = BASE + '/admin/ops/?company_id=22';
  push('marker', { name: 'GOTO_OPS_START', url: opsUrl });
  await page.goto(opsUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
  push('marker', { name: 'OPS_DOMCONTENTLOADED', url: page.url(), title: await page.title() });

  // Wait for usable: sidebar + main content + no hard spinner; allow SDK lazy but track it
  let usable = null;
  const deadline = Date.now() + 180000;
  let lastNet = Date.now();
  page.on('request', () => {
    lastNet = Date.now();
  });

  while (Date.now() < deadline) {
    const snap = await page.evaluate(() => {
      const spins = [...document.querySelectorAll('.spinner-border,.fa-spin,.is-loading,#rateb-offline-warm-progress')].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && el.offsetWidth + el.offsetHeight > 0;
      });
      const scripts = [...document.scripts].map((s) => s.src).filter(Boolean);
      return {
        href: location.href,
        ready: document.readyState,
        sidebar: !!document.querySelector('.rateb-sidebar,#rateb-sidebar'),
        mainLen: ((document.querySelector('main,.rateb-main') || {}).innerText || '').trim().length,
        spins: spins.length,
        hasOfflineSdk: scripts.some((s) => /rateb-offline\.js/i.test(s)),
        hasShellBoot: scripts.some((s) => /erp-shell-bootstrap/i.test(s)),
        swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
        boot: window.__RATEB_BOOT__ || null,
        lite: !!(window.__RATEB_ERP_SHELL_OFFLINE__ && window.__RATEB_ERP_SHELL_OFFLINE__.lite),
        cfg: !!(window.__RATEB_ERP_SHELL_OFFLINE__),
      };
    });
    push('poll', snap);
    if (snap.sidebar && snap.mainLen > 80 && snap.spins === 0 && snap.ready === 'complete' && Date.now() - lastNet > 2000) {
      usable = tms(t0);
      push('marker', { name: 'DASHBOARD_USABLE', snap });
      break;
    }
    await new Promise((r) => setTimeout(r, 300));
  }

  const finalState = await page.evaluate(async () => {
    const resources = performance.getEntriesByType('resource').map((r) => ({
      name: r.name.replace(location.origin, ''),
      dur: Math.round(r.duration),
      start: Math.round(r.startTime),
      end: Math.round(r.responseEnd),
      size: r.transferSize,
      type: r.initiatorType,
    }));
    resources.sort((a, b) => b.dur - a.dur);
    const nav = performance.getEntriesByType('navigation')[0] || {};
    let regs = [];
    try {
      regs = (await navigator.serviceWorker.getRegistrations()).map((r) => ({
        scope: r.scope,
        active: r.active && r.active.scriptURL,
      }));
    } catch (_) {}
    return {
      nav: {
        ttfb: nav.responseStart,
        dcl: nav.domContentLoadedEventEnd,
        load: nav.loadEventEnd,
      },
      topSlow: resources.slice(0, 20),
      sw: regs,
      boot: window.__RATEB_BOOT__ || null,
    };
  });

  // Gaps
  const gaps = [];
  for (let i = 1; i < events.length; i++) {
    const dt = events[i].t - events[i - 1].t;
    if (dt >= 250) {
      gaps.push({
        dt,
        from: events[i - 1],
        to: events[i],
      });
    }
  }
  gaps.sort((a, b) => b.dt - a.dt);

  const report = {
    generatedAt: new Date().toISOString(),
    usableAtMs: usable,
    events,
    gaps: gaps.slice(0, 20),
    finalState,
  };
  const file = path.join(OUT, `real-user-ops-cold-${Date.now()}.json`);
  fs.writeFileSync(file, JSON.stringify(report, null, 2));

  const lines = ['COLD LOGIN → /admin/ops TIMELINE (ms)', '='.repeat(50)];
  for (const e of events) {
    if (e.type === 'poll' && e.spins === 0 && !e.hasOfflineSdk) continue;
    if (e.type === 'poll' && events.filter((x) => x.type === 'poll').indexOf(e) % 4 !== 0) continue;
    const parts = [String(Math.round(e.t)).padStart(8, '0'), e.type];
    if (e.name) parts.push(e.name);
    if (e.method) parts.push(e.method);
    if (e.status != null) parts.push('HTTP' + e.status);
    if (e.url) parts.push(String(e.url).slice(0, 120));
    if (e.fromSW) parts.push('fromSW');
    if (e.hasOfflineSdk != null) parts.push('sdk=' + e.hasOfflineSdk);
    if (e.swController != null) parts.push('swCtrl=' + e.swController);
    if (e.mainLen != null) parts.push('mainLen=' + e.mainLen);
    lines.push(parts.join(' | '));
  }
  lines.push('', 'TOP GAPS');
  for (const g of gaps.slice(0, 12)) {
    lines.push(
      `+${Math.round(g.dt)}ms  ${g.from.type}/${g.from.name || g.from.url || ''} → ${g.to.type}/${g.to.name || g.to.url || ''}`
    );
  }
  const txt = file.replace(/\.json$/, '.txt');
  fs.writeFileSync(txt, lines.join('\n'));
  console.log(lines.join('\n'));
  console.log('REPORT', file);
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
