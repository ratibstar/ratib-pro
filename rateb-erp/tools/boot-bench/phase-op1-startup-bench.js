/**
 * Phase OP1 — Offline V2 critical-startup benchmark.
 * Measures First Paint / Shell Ready / Interactive / Runtime / SQLite / Identity.
 *
 * Usage:
 *   node phase-op1-startup-bench.js
 *   RATEB_V2_URL=https://rateb.sa/rateb-erp/public/v2/index.html node phase-op1-startup-bench.js
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.RATEB_V2_URL
  || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = process.env.RATEB_OP1_OUT
  || path.join(__dirname, 'reports', `phase-op1-startup-${Date.now()}.json`);

const BEFORE = {
  source: 'PX2 / PZ audit (pre-OP1)',
  first_paint_ms: 48,
  shell_ready_ms: 66,
  interactive_ms: 21,
  runtime_ready_ms: 66,
  sqlite_ready_ms: 121,
  identity_ready_ms: 121,
  route_ready_inventory_ms: 123,
  home_background_complete_ms: 353,
  notes: 'Warm Inventory path; Shell.mount awaited full Runtime.start'
};

function round(v) {
  return Number.isFinite(Number(v)) ? Math.round(Number(v) * 10) / 10 : null;
}

async function measure(page, label, url) {
  const started = Date.now();
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForFunction(() => {
    return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
      || document.documentElement.getAttribute('data-rateb-v2-interactive') === '1';
  }, { timeout: 30000 }).catch(() => null);

  // Allow deferred marks to settle briefly without blocking shell measurement.
  await page.waitForTimeout(800);

  return page.evaluate(({ runLabel, wallStart }) => {
    const marks = (performance.getEntriesByType('mark') || [])
      .filter((m) => /^rateb-v2-/.test(m.name))
      .map((m) => ({ name: m.name, start_ms: Math.round(m.startTime * 10) / 10 }));
    const paints = performance.getEntriesByType('paint') || [];
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
      first_paint_ms: paintValue('first-paint'),
      first_contentful_paint_ms: paintValue('first-contentful-paint'),
      shell_paint_ms: markValue('rateb-v2-shell-paint'),
      interactive_ms: markValue('rateb-v2-interactive-ready'),
      shell_ready_ms: markValue('rateb-v2-shell-ready'),
      runtime_ready_ms: markValue('rateb-v2-runtime-ready'),
      runtime_fully_ready_ms: markValue('rateb-v2-runtime-fully-ready'),
      sqlite_ready_ms: markValue('rateb-v2-sqlite-ready'),
      identity_ready_ms: markValue('rateb-v2-identity-ready'),
      route_ready_ms: markValue('rateb-v2-route-ready'),
      background_ready_ms: markValue('rateb-v2-background-ready'),
      attrs: {
        interactive: document.documentElement.getAttribute('data-rateb-v2-interactive'),
        shell_ready: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
        shell_painted: document.documentElement.getAttribute('data-rateb-v2-shell-painted'),
        identity_ready: document.documentElement.getAttribute('data-rateb-v2-identity-ready'),
        offline_ready: document.documentElement.getAttribute('data-rateb-v2-offline-ready'),
        route_ready: document.documentElement.getAttribute('data-rateb-v2-route-ready')
      },
      marks
    };
  }, { runLabel: label, wallStart: started });
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  const homeWarm = await measure(page, 'home-warm-1', BASE);
  const homeWarm2 = await measure(page, 'home-warm-2', BASE);
  const invUrl = BASE.includes('?')
    ? `${BASE}&r=/inventory`
    : `${BASE}?r=/inventory`;
  // Prefer hash deep-link
  const invHash = BASE.split('#')[0] + '#/inventory';
  const inv = await measure(page, 'inventory-warm', invHash);

  const report = {
    phase: 'OP1',
    measured_at: new Date().toISOString(),
    url: BASE,
    before: BEFORE,
    after: {
      home_warm_1: homeWarm,
      home_warm_2: homeWarm2,
      inventory_warm: inv
    },
    comparison: {
      shell_ready_before_ms: BEFORE.shell_ready_ms,
      shell_ready_after_ms: homeWarm2.shell_ready_ms || homeWarm.shell_ready_ms,
      interactive_before_ms: BEFORE.interactive_ms,
      interactive_after_ms: homeWarm2.interactive_ms || homeWarm.interactive_ms,
      runtime_ready_after_ms: homeWarm2.runtime_ready_ms || homeWarm.runtime_ready_ms,
      sqlite_on_home_after_ms: homeWarm2.sqlite_ready_ms || homeWarm.sqlite_ready_ms,
      identity_on_home_after_ms: homeWarm2.identity_ready_ms || homeWarm.identity_ready_ms
    }
  };

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report.comparison, null, 2));
  console.log('Wrote', OUT);
  await browser.close();
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
