/**
 * Phase AD — Repeated ERP navigation audit (evidence only).
 *
 *   RATEB_ERP_USER=admin@rateb.sa RATEB_ERP_PASS='...' node phase-ad-nav-audit.js
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

/** Path suffixes under public/ (leading slash). Ops company id query when needed. */
const NAV = [
  { id: 'dashboard', path: '/admin/' },
  { id: 'hr', path: '/admin/hr' },
  { id: 'crm', path: '/admin/crm' },
  { id: 'inventory', path: '/admin/ops/inventory' },
  { id: 'accounting', path: '/admin/ops/accounting' },
  { id: 'procurement', path: '/admin/ops/purchase-requests' },
  { id: 'dashboard_return', path: '/admin/' },
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

  const net = {
    dnsLookups: [],
    documents: [],
    ajax: [],
    resources: [],
  };

  const onRequest = (req) => {
    /* filled via CDP */
  };
  void onRequest;

  const cdpHandler = (params) => {
    const t = params.type || '';
    const timing = params.timing || null;
    const url = params.response?.url || params.request?.url || '';
    if (!url || !url.includes('rateb.sa')) return;
  };

  // Capture via PerformanceObserver injected + Performance API after load
  const wallStart = Date.now();
  let navError = null;

  try {
    await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 90000 });
  } catch (e) {
    navError = String(e.message || e);
  }

  // Wait until usable-ish: sidebar + main content, short quiet
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
      const main = document.querySelector('main, .rateb-main, #content, .content-wrapper');
      const mainText = main ? (main.innerText || '').trim().length : 0;
      return {
        spins: spins.length,
        sidebar,
        mainText,
        readyState: document.readyState,
        href: location.href,
        title: document.title,
      };
    });
    const netQuiet = Date.now() - lastNet > 800;
    if (snap.sidebar && snap.mainText > 20 && snap.spins === 0 && snap.readyState === 'complete' && netQuiet) {
      usableAt = Date.now() - wallStart;
      break;
    }
    // Soft ready: complete + sidebar + content even if network still busy after 4s
    if (
      snap.sidebar &&
      snap.mainText > 20 &&
      snap.spins === 0 &&
      snap.readyState === 'complete' &&
      Date.now() - wallStart > 4000
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
      const tcp = Math.max(0, (r.connectEnd || 0) - (r.connectStart || 0));
      const tls =
        r.secureConnectionStart > 0
          ? Math.max(0, (r.connectEnd || 0) - (r.secureConnectionStart || 0))
          : 0;
      return {
        name: r.name.replace(location.origin, ''),
        initiatorType: r.initiatorType,
        startTime: Math.round(r.startTime),
        duration: Math.round(r.duration),
        transferSize: r.transferSize || 0,
        encodedBodySize: r.encodedBodySize || 0,
        dns_ms: Math.round(dns * 10) / 10,
        tcp_ms: Math.round(tcp * 10) / 10,
        tls_ms: Math.round(tls * 10) / 10,
        ttfb_ms:
          r.responseStart && r.requestStart
            ? Math.round((r.responseStart - r.requestStart) * 10) / 10
            : null,
        download_ms:
          r.responseEnd && r.responseStart
            ? Math.round((r.responseEnd - r.responseStart) * 10) / 10
            : null,
      };
    });

    const docDns = nav ? Math.max(0, nav.domainLookupEnd - nav.domainLookupStart) : 0;
    const docTcp = nav ? Math.max(0, nav.connectEnd - nav.connectStart) : 0;
    const docTls =
      nav && nav.secureConnectionStart > 0
        ? Math.max(0, nav.connectEnd - nav.secureConnectionStart)
        : 0;
    const ttfb = nav ? Math.max(0, nav.responseStart - nav.requestStart) : null;
    const htmlDl =
      nav && nav.responseEnd && nav.responseStart
        ? Math.max(0, nav.responseEnd - nav.responseStart)
        : null;

    // JS long tasks approximation via script resource durations
    const scripts = resources.filter((r) => r.initiatorType === 'script');
    const css = resources.filter((r) => r.initiatorType === 'link' || r.name.includes('.css'));
    const ajax = resources.filter(
      (r) => r.initiatorType === 'fetch' || r.initiatorType === 'xmlhttprequest'
    );

    // nextHopProtocol / transferSize 0 often means disk cache / SW / memory cache
    const docs = resources.filter((r) => r.initiatorType === 'navigation' || r.name.includes('/admin'));

    let sw = null;
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
      sw = navigator.serviceWorker.controller.scriptURL;
    }

    return {
      href: location.href,
      title: document.title,
      navTiming: nav
        ? {
            type: nav.type,
            protocol: nav.nextHopProtocol || null,
            transferSize: nav.transferSize || 0,
            encodedBodySize: nav.encodedBodySize || 0,
            decodedBodySize: nav.decodedBodySize || 0,
            redirectCount: nav.redirectCount,
            startTime: nav.startTime,
            domainLookupStart: nav.domainLookupStart,
            domainLookupEnd: nav.domainLookupEnd,
            connectStart: nav.connectStart,
            connectEnd: nav.connectEnd,
            secureConnectionStart: nav.secureConnectionStart,
            requestStart: nav.requestStart,
            responseStart: nav.responseStart,
            responseEnd: nav.responseEnd,
            domContentLoadedEventEnd: nav.domContentLoadedEventEnd,
            loadEventEnd: nav.loadEventEnd,
            dns_ms: Math.round(docDns * 10) / 10,
            tcp_ms: Math.round(docTcp * 10) / 10,
            tls_ms: Math.round(docTls * 10) / 10,
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
        .sort((a, b) => b.duration - a.duration)
        .slice(0, 8),
      top_resources: resources
        .slice()
        .sort((a, b) => b.duration - a.duration)
        .slice(0, 12),
      dns_on_any_resource_ms: resources.reduce((a, r) => a + (r.dns_ms || 0), 0),
      resources_with_dns: resources.filter((r) => (r.dns_ms || 0) > 0.5).map((r) => ({
        name: r.name.slice(0, 100),
        dns_ms: r.dns_ms,
      })),
      serviceWorker: sw,
      resourceCount: resources.length,
    };
  });

  await client.detach().catch(() => {});

  return {
    id: label,
    targetUrl,
    wall_to_usable_ms: usableAt,
    nav_error: navError,
    dns_performed_on_document: (metrics.navTiming?.dns_ms || 0) > 0.5,
    dns_document_ms: metrics.navTiming?.dns_ms ?? null,
    dns_any_resource: (metrics.resources_with_dns || []).length > 0,
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

  // Login
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('#password-form', { timeout: 30000 });
  await page.fill('#email', USER);
  await page.fill('#password', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    page.click('#password-form button[type="submit"]'),
  ]);

  const stillLogin = /\/login/i.test(page.url());
  if (stillLogin) {
    const out = path.join(OUT_DIR, `phase-ad-LOGIN-FAILED-${Date.now()}.json`);
    fs.writeFileSync(
      out,
      JSON.stringify({ ok: false, url: page.url(), reason: 'login_failed' }, null, 2)
    );
    console.log(JSON.stringify({ out, ok: false }, null, 2));
    await browser.close();
    process.exit(2);
  }

  // Ensure company context for ops routes (super-admin may need ?company_id=)
  // First land on admin; adopt company via cookie/session already set by login for company users.
  // For platform SA, click or set company_id=22 in URL for ops pages.
  const COMPANY_Q = 'company_id=22';

  const results = [];
  for (const step of NAV) {
    let url = BASE + step.path;
    if (step.path.includes('/ops/') || step.path.includes('/hr') || step.path.includes('/crm')) {
      url += (url.includes('?') ? '&' : '?') + COMPANY_Q;
    }
    // clear resource timings isolation: each goto creates new navigation entry
    const row = await measureNavigation(page, step.id, url);
    results.push(row);
    console.log(
      JSON.stringify({
        id: row.id,
        usable_ms: row.wall_to_usable_ms,
        dns_doc: row.dns_document_ms,
        ttfb: row.navTiming?.ttfb_ms,
        dcl: row.navTiming?.dom_content_loaded_ms,
        load: row.navTiming?.load_event_ms,
        fcp: row.paints?.['first-contentful-paint'],
        href: shortUrl(row.href || ''),
        title: row.title,
      })
    );
  }

  // Aggregate bottleneck classification for navigations after first (repeated)
  const repeated = results.slice(1); // exclude initial dashboard cold-ish after login
  const sum = (arr, fn) => arr.reduce((a, x) => a + (fn(x) || 0), 0);
  const avg = (arr, fn) => (arr.length ? sum(arr, fn) / arr.length : 0);

  const analysis = {
    nav_count: results.length,
    repeated_count: repeated.length,
    avg_usable_ms_all: Math.round(avg(results, (r) => r.wall_to_usable_ms)),
    avg_usable_ms_repeated: Math.round(avg(repeated, (r) => r.wall_to_usable_ms)),
    avg_dns_document_ms_repeated: Math.round(avg(repeated, (r) => r.dns_document_ms) * 10) / 10,
    avg_ttfb_ms_repeated: Math.round(avg(repeated, (r) => r.navTiming?.ttfb_ms) * 10) / 10,
    avg_dcl_ms_repeated: Math.round(avg(repeated, (r) => r.navTiming?.dom_content_loaded_ms) * 10) / 10,
    avg_load_ms_repeated: Math.round(avg(repeated, (r) => r.navTiming?.load_event_ms) * 10) / 10,
    avg_fcp_ms_repeated: Math.round(
      avg(repeated, (r) => r.paints?.['first-contentful-paint']) * 10
    ) / 10,
    avg_lcp_ms_repeated: Math.round(avg(repeated, (r) => r.lcp_ms) * 10) / 10,
    avg_js_script_duration_ms_repeated: Math.round(
      avg(repeated, (r) => r.script_total_duration_ms)
    ),
    avg_css_duration_ms_repeated: Math.round(avg(repeated, (r) => r.css_total_duration_ms)),
    avg_ajax_duration_ms_repeated: Math.round(avg(repeated, (r) => r.ajax_total_duration_ms)),
    dns_performed_again_on_any_repeated: repeated.some((r) => r.dns_performed_on_document),
    dns_on_resources_repeated: repeated.some((r) => r.dns_any_resource),
  };

  // Classify bottleneck from averages of repeated navs
  const buckets = {
    DNS: analysis.avg_dns_document_ms_repeated,
    Server_TTFB_excl_dns: Math.max(
      0,
      analysis.avg_ttfb_ms_repeated - analysis.avg_dns_document_ms_repeated
    ),
    // Rough split: TTFB after DNS ≈ server/PHP/network first byte
    HTML_download: avg(repeated, (r) => r.navTiming?.html_download_ms),
    JS_resources: analysis.avg_js_script_duration_ms_repeated,
    CSS_resources: analysis.avg_css_duration_ms_repeated,
    AJAX: analysis.avg_ajax_duration_ms_repeated,
    // time from responseStart to FCP approximates parse/render
    Render_after_response: avg(
      repeated,
      (r) =>
        (r.paints?.['first-contentful-paint'] || 0) - (r.navTiming?.ttfb_ms || 0) - (r.navTiming?.html_download_ms || 0)
    ),
  };

  const ranked = Object.entries(buckets)
    .map(([k, v]) => ({ component: k, avg_ms: Math.round((v || 0) * 10) / 10 }))
    .sort((a, b) => b.avg_ms - a.avg_ms);

  const report = {
    phase: 'AD',
    ok: true,
    measured_at: new Date().toISOString(),
    base: BASE,
    user: USER,
    final_url: page.url(),
    navigations: results,
    analysis,
    bottleneck_ranking_avg_ms: ranked,
    single_biggest_repeated_nav_bottleneck: ranked[0] || null,
  };

  const stamp = Date.now();
  const outJson = path.join(OUT_DIR, `phase-ad-nav-audit-${stamp}.json`);
  fs.writeFileSync(outJson, JSON.stringify(report, null, 2));
  const latest = path.join(OUT_DIR, 'phase-ad-nav-audit-latest.json');
  fs.writeFileSync(latest, JSON.stringify(report, null, 2));

  // Text summary
  let txt = 'PHASE AD — REPEATED NAVIGATION AUDIT\n';
  txt += '=====================================\n';
  for (const r of results) {
    txt += `\n[${r.id}] ${shortUrl(r.href || r.targetUrl)}\n`;
    txt += `  usable=${r.wall_to_usable_ms}ms dns_doc=${r.dns_document_ms}ms dns_again=${r.dns_performed_on_document}\n`;
    txt += `  ttfb=${r.navTiming?.ttfb_ms} html_dl=${r.navTiming?.html_download_ms} dcl=${r.navTiming?.dom_content_loaded_ms} load=${r.navTiming?.load_event_ms}\n`;
    txt += `  fp=${r.paints?.['first-paint']} fcp=${r.paints?.['first-contentful-paint']} lcp=${r.lcp_ms}\n`;
    txt += `  js_res=${r.script_total_duration_ms}ms css_res=${r.css_total_duration_ms}ms ajax=${r.ajax_total_duration_ms}ms (n=${r.ajax_count})\n`;
    txt += `  transferSize=${r.navTiming?.transferSize} protocol=${r.navTiming?.protocol} sw=${r.serviceWorker || 'none'}\n`;
  }
  txt += `\nANALYSIS (repeated navigations after first dashboard)\n`;
  txt += JSON.stringify(analysis, null, 2) + '\n';
  txt += `\nBOTTLENECK RANKING\n`;
  for (const b of ranked) {
    txt += `  ${b.avg_ms}ms  ${b.component}\n`;
  }
  const outTxt = path.join(OUT_DIR, `phase-ad-nav-audit-${stamp}.txt`);
  fs.writeFileSync(outTxt, txt);

  console.log(txt);
  console.log(JSON.stringify({ outJson, latest, bottleneck: ranked[0], analysis }, null, 2));

  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
