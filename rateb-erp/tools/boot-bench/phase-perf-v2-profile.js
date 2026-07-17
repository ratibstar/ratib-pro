/**
 * Phase PERF — Offline V2 perception profile.
 * Captures paint, interactivity, Shell/Route Ready, background readiness,
 * long tasks, and top resource costs across cold/warm/offline reloads.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const URL = process.env.RATEB_V2_URL
  || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = process.env.RATEB_PERF_OUT
  || path.join(__dirname, 'reports', `phase-perf-v2-${Date.now()}.json`);

function round(value) {
  return Number.isFinite(Number(value)) ? Math.round(Number(value) * 10) / 10 : null;
}

async function collect(page, label, startedAt) {
  return page.evaluate(({ runLabel, wallStart }) => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const paints = performance.getEntriesByType('paint') || [];
    const marks = (performance.getEntriesByType('mark') || [])
      .filter((m) => /^rateb-v2-/.test(m.name))
      .map((m) => ({ name: m.name, start_ms: Math.round(m.startTime * 10) / 10 }));
    const resources = (performance.getEntriesByType('resource') || [])
      .map((r) => ({
        name: r.name,
        type: r.initiatorType,
        start_ms: Math.round(r.startTime * 10) / 10,
        duration_ms: Math.round(r.duration * 10) / 10,
        transfer_bytes: r.transferSize || 0,
        decoded_bytes: r.decodedBodySize || 0,
      }))
      .sort((a, b) => b.duration_ms - a.duration_ms);
    const markValue = (name) => {
      const hit = marks.find((m) => m.name === name);
      return hit ? hit.start_ms : null;
    };
    const paintValue = (name) => {
      const hit = paints.find((p) => p.name === name);
      return hit ? Math.round(hit.startTime * 10) / 10 : null;
    };
    return {
      label: runLabel,
      wall_ms: Date.now() - wallStart,
      navigation: {
        type: nav.type || null,
        ttfb_ms: nav.responseStart ? Math.round(nav.responseStart * 10) / 10 : null,
        dom_content_loaded_ms: nav.domContentLoadedEventEnd
          ? Math.round(nav.domContentLoadedEventEnd * 10) / 10 : null,
        load_ms: nav.loadEventEnd ? Math.round(nav.loadEventEnd * 10) / 10 : null,
      },
      first_paint_ms: paintValue('first-paint'),
      first_contentful_paint_ms: paintValue('first-contentful-paint'),
      interactive_ms: markValue('rateb-v2-interactive-ready'),
      shell_ready_ms: markValue('rateb-v2-shell-ready'),
      route_ready_ms: markValue('rateb-v2-route-ready'),
      db_ready_ms: markValue('rateb-v2-db-ready'),
      background_ready_ms: markValue('rateb-v2-background-ready'),
      shell_ready: document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
      route_ready: document.documentElement.getAttribute('data-rateb-v2-route-ready') === '1',
      active_route: (document.getElementById('rateb-v2-shell-outlet') || {}).getAttribute
        ? document.getElementById('rateb-v2-shell-outlet').getAttribute('data-route')
        : null,
      marks,
      top_resources: resources.slice(0, 20),
      resource_count: resources.length,
      long_tasks: (window.__ratebPerfLongTasks || []).slice(),
    };
  }, { runLabel: label, wallStart: startedAt });
}

async function waitFor(page, attribute, timeout) {
  await page.waitForFunction((attr) => {
    return document.documentElement.getAttribute(attr) === '1';
  }, attribute, { timeout });
}

(async () => {
  const userDataDir = path.join(
    __dirname,
    '.chrome-user-data',
    `phase-perf-${Date.now()}`
  );
  fs.mkdirSync(userDataDir, { recursive: true });

  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });
  const page = context.pages()[0] || await context.newPage();
  const httpFailures = [];
  const pageErrors = [];

  await page.addInitScript(() => {
    window.__ratebPerfLongTasks = [];
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
          window.__ratebPerfLongTasks.push({
            start_ms: Math.round(entry.startTime * 10) / 10,
            duration_ms: Math.round(entry.duration * 10) / 10,
          });
        });
      }).observe({ type: 'longtask', buffered: true });
    } catch (_) {
      // Long Task API is optional.
    }
  });
  page.on('response', (res) => {
    if (res.url().includes('/v2/') && res.status() >= 400) {
      httpFailures.push({ url: res.url(), status: res.status() });
    }
  });
  page.on('pageerror', (err) => pageErrors.push(String(err.message || err)));

  const coldStart = Date.now();
  await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await waitFor(page, 'data-rateb-v2-shell-ready', 20000);
  await waitFor(page, 'data-rateb-v2-route-ready', 20000);
  await page.waitForFunction(() => {
    return performance.getEntriesByName('rateb-v2-background-ready').length > 0;
  }, { timeout: 20000 }).catch(() => null);
  const cold = await collect(page, 'cold', coldStart);

  const warmStart = Date.now();
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await waitFor(page, 'data-rateb-v2-shell-ready', 10000);
  await waitFor(page, 'data-rateb-v2-route-ready', 10000);
  const warm = await collect(page, 'warm_reload', warmStart);

  await page.waitForTimeout(1000);
  await context.setOffline(true);
  const offlineStart = Date.now();
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await waitFor(page, 'data-rateb-v2-shell-ready', 10000);
  await waitFor(page, 'data-rateb-v2-route-ready', 10000);
  const offline = await collect(page, 'offline_reload', offlineStart);
  await context.setOffline(false);

  const target = {
    first_paint_lt_200: warm.first_paint_ms !== null && warm.first_paint_ms < 200,
    interactive_lt_500: warm.interactive_ms !== null && warm.interactive_ms < 500,
    shell_ready_lt_800: warm.shell_ready_ms !== null && warm.shell_ready_ms < 800,
    route_ready_lt_1000: warm.route_ready_ms !== null && warm.route_ready_ms < 1000,
  };

  const report = {
    phase: 'PERF',
    url: URL,
    generated_at: new Date().toISOString(),
    cold,
    warm,
    offline,
    target,
    http_failures: httpFailures,
    page_errors: pageErrors,
    enterprise_validation: Object.values(target).every(Boolean)
      && httpFailures.length === 0
      && pageErrors.length === 0
      ? 'PASS'
      : 'FAIL',
  };

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await context.close();
  process.exit(report.enterprise_validation === 'PASS' ? 0 : 1);
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
