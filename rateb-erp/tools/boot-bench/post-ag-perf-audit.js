/**
 * Post-AG final performance audit — browser navigation (evidence only).
 * Based on phase-ad-nav-audit.js; adds POS. No app code changes.
 *
 *   RATEB_ERP_PASS='...' node post-ag-perf-audit.js
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const USER = process.env.RATEB_ERP_USER || 'admin@rateb.sa';
const PASS = process.env.RATEB_ERP_PASS;
const OUT_DIR = path.join(__dirname, 'reports');

if (!PASS) {
  console.error('RATEB_ERP_PASS required');
  process.exit(1);
}

const NAV = [
  { id: 'dashboard', path: '/admin/' },
  { id: 'hr', path: '/admin/hr' },
  { id: 'crm', path: '/admin/crm' },
  { id: 'inventory', path: '/admin/ops/inventory' },
  { id: 'accounting', path: '/admin/ops/accounting' },
  { id: 'procurement', path: '/admin/ops/purchase-requests' },
  { id: 'pos', path: '/admin/ops/pos' },
];

function shortUrl(u) {
  try {
    const x = new URL(u);
    return x.pathname + x.search;
  } catch {
    return String(u).slice(0, 180);
  }
}

async function measureNavigation(page, label, targetUrl) {
  const client = await page.context().newCDPSession(page);
  await client.send('Network.enable');
  await client.send('Performance.enable').catch(() => {});

  const wallStart = Date.now();
  let navError = null;
  try {
    await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 90000 });
  } catch (e) {
    navError = String(e.message || e);
  }

  const usableDeadline = Date.now() + 60000;
  let usableAt = null;
  let lastNet = Date.now();
  const onReq = () => {
    lastNet = Date.now();
  };
  page.on('request', onReq);
  page.on('response', onReq);

  while (Date.now() < usableDeadline) {
    const snap = await page.evaluate(() => {
      const spinSel =
        '.spinner-border, .fa-spinner, .fa-spin, .is-loading, #rateb-offline-warm-progress, [aria-busy="true"]';
      const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
        const st = getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
      });
      const sidebar = !!document.querySelector('#rateb-sidebar, .rateb-sidebar, aside.rateb-sidebar');
      const main = document.querySelector('main, .rateb-main, #content, .content-wrapper, .pos-app, #pos-root');
      const mainText = main ? (main.innerText || '').trim().length : 0;
      const err = /تعذّر|error/i.test(document.title || '');
      return {
        spins: spins.length,
        sidebar,
        mainText,
        readyState: document.readyState,
        href: location.href,
        title: document.title,
        err,
      };
    });
    const netQuiet = Date.now() - lastNet > 800;
    if (
      (snap.sidebar || snap.mainText > 20 || snap.err) &&
      snap.spins === 0 &&
      snap.readyState === 'complete' &&
      (netQuiet || Date.now() - wallStart > 4000)
    ) {
      usableAt = Date.now() - wallStart;
      break;
    }
    await new Promise((r) => setTimeout(r, 200));
  }
  page.off('request', onReq);
  page.off('response', onReq);

  const metrics = await page.evaluate(() => {
    const navs = performance.getEntriesByType('navigation');
    const nav = navs.length ? navs[navs.length - 1] : null;
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = Math.round(p.startTime * 10) / 10;
    });
    let lcp = null;
    try {
      const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
      if (lcpEntries.length) {
        lcp = Math.round(lcpEntries[lcpEntries.length - 1].startTime * 10) / 10;
      }
    } catch (_) {}

    const resources = performance.getEntriesByType('resource').map((r) => {
      const dns = Math.max(0, (r.domainLookupEnd || 0) - (r.domainLookupStart || 0));
      return {
        name: r.name.replace(location.origin, ''),
        full: r.name,
        initiatorType: r.initiatorType,
        duration: Math.round(r.duration),
        dns_ms: Math.round(dns * 10) / 10,
        ttfb_ms:
          r.responseStart && r.requestStart
            ? Math.round((r.responseStart - r.requestStart) * 10) / 10
            : null,
      };
    });

    const docDns = nav ? Math.max(0, nav.domainLookupEnd - nav.domainLookupStart) : 0;
    const ttfb = nav ? Math.max(0, nav.responseStart - nav.requestStart) : null;
    const htmlDl =
      nav && nav.responseEnd && nav.responseStart
        ? Math.max(0, nav.responseEnd - nav.responseStart)
        : null;

    const scripts = resources.filter((r) => r.initiatorType === 'script');
    const css = resources.filter((r) => r.initiatorType === 'link' || r.name.includes('.css'));
    const ajax = resources.filter(
      (r) => r.initiatorType === 'fetch' || r.initiatorType === 'xmlhttprequest'
    );

    let sw = null;
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
      sw = navigator.serviceWorker.controller.scriptURL;
    }

    return {
      href: location.href,
      title: document.title,
      navTiming: nav
        ? {
            protocol: nav.nextHopProtocol || null,
            transferSize: nav.transferSize || 0,
            dns_ms: Math.round(docDns * 10) / 10,
            ttfb_ms: ttfb != null ? Math.round(ttfb * 10) / 10 : null,
            html_download_ms: htmlDl != null ? Math.round(htmlDl * 10) / 10 : null,
            dom_content_loaded_ms: Math.round(nav.domContentLoadedEventEnd * 10) / 10,
            load_event_ms: Math.round(nav.loadEventEnd * 10) / 10,
          }
        : null,
      paints,
      lcp_ms: lcp,
      script_count: scripts.length,
      script_total_duration_ms: scripts.reduce((a, r) => a + (r.duration || 0), 0),
      css_count: css.length,
      css_total_duration_ms: css.reduce((a, r) => a + (r.duration || 0), 0),
      ajax_count: ajax.length,
      ajax_total_duration_ms: ajax.reduce((a, r) => a + (r.duration || 0), 0),
      ajax_top: ajax
        .slice()
        .sort((a, b) => (b.ttfb_ms || b.duration || 0) - (a.ttfb_ms || a.duration || 0))
        .slice(0, 20),
      top_resources: resources
        .slice()
        .sort((a, b) => b.duration - a.duration)
        .slice(0, 20),
      serviceWorker: sw,
    };
  });

  await client.detach().catch(() => {});

  return {
    id: label,
    targetUrl,
    wall_to_usable_ms: usableAt,
    nav_error: navError,
    dns_document_ms: metrics.navTiming?.dns_ms ?? null,
    ...metrics,
  };
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
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

  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('#password-form', { timeout: 30000 });
  await page.fill('#email', USER);
  await page.fill('#password', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    page.click('#password-form button[type="submit"]'),
  ]);

  if (/\/login/i.test(page.url())) {
    const out = path.join(OUT_DIR, `phase-post-ag-LOGIN-FAILED-${Date.now()}.json`);
    fs.writeFileSync(out, JSON.stringify({ ok: false, url: page.url() }, null, 2));
    await browser.close();
    process.exit(2);
  }

  const COMPANY_Q = 'company_id=22';
  const results = [];
  for (const step of NAV) {
    let url = BASE + step.path;
    if (
      step.path.includes('/ops/') ||
      step.path.includes('/hr') ||
      step.path.includes('/crm') ||
      step.path.includes('/pos')
    ) {
      url += (url.includes('?') ? '&' : '?') + COMPANY_Q;
    }
    const row = await measureNavigation(page, step.id, url);
    results.push(row);
    console.log(
      JSON.stringify({
        id: row.id,
        usable_ms: row.wall_to_usable_ms,
        ttfb: row.navTiming?.ttfb_ms,
        fcp: row.paints?.['first-contentful-paint'],
        lcp: row.lcp_ms,
        dcl: row.navTiming?.dom_content_loaded_ms,
        load: row.navTiming?.load_event_ms,
        js: row.script_total_duration_ms,
        css: row.css_total_duration_ms,
        ajax: row.ajax_total_duration_ms,
        title: row.title,
      })
    );
  }

  const sum = (arr, fn) => arr.reduce((a, x) => a + (fn(x) || 0), 0);
  const avg = (arr, fn) => (arr.length ? sum(arr, fn) / arr.length : 0);

  // Aggregate top HTTP endpoints by document TTFB + ajax TTFB
  const httpEndpoints = [];
  for (const r of results) {
    httpEndpoints.push({
      url: shortUrl(r.href || r.targetUrl),
      kind: 'document',
      ttfb_ms: r.navTiming?.ttfb_ms,
      duration_ms: r.navTiming?.load_event_ms,
      page: r.id,
    });
    for (const a of r.ajax_top || []) {
      httpEndpoints.push({
        url: shortUrl(a.full || a.name),
        kind: 'ajax',
        ttfb_ms: a.ttfb_ms,
        duration_ms: a.duration,
        page: r.id,
      });
    }
  }
  httpEndpoints.sort((a, b) => (b.ttfb_ms || b.duration_ms || 0) - (a.ttfb_ms || a.duration_ms || 0));

  const report = {
    phase: 'POST_AG_PERF_AUDIT',
    ok: true,
    measured_at: new Date().toISOString(),
    base: BASE,
    user: USER,
    navigations: results,
    averages: {
      ttfb_ms: Math.round(avg(results, (r) => r.navTiming?.ttfb_ms) * 10) / 10,
      fcp_ms: Math.round(avg(results, (r) => r.paints?.['first-contentful-paint']) * 10) / 10,
      lcp_ms: Math.round(avg(results, (r) => r.lcp_ms) * 10) / 10,
      dcl_ms: Math.round(avg(results, (r) => r.navTiming?.dom_content_loaded_ms) * 10) / 10,
      load_ms: Math.round(avg(results, (r) => r.navTiming?.load_event_ms) * 10) / 10,
      js_ms: Math.round(avg(results, (r) => r.script_total_duration_ms)),
      css_ms: Math.round(avg(results, (r) => r.css_total_duration_ms)),
      ajax_ms: Math.round(avg(results, (r) => r.ajax_total_duration_ms)),
      usable_ms: Math.round(avg(results, (r) => r.wall_to_usable_ms)),
    },
    top_20_http_endpoints: httpEndpoints.slice(0, 20),
  };

  const stamp = Date.now();
  const outJson = path.join(OUT_DIR, `phase-post-ag-nav-${stamp}.json`);
  fs.writeFileSync(outJson, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-post-ag-nav-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out: outJson, averages: report.averages }, null, 2));
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
