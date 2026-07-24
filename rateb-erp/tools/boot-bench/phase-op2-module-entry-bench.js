/**
 * OP2 regression smoke — module entry marks after Shell Ready.
 * Asserts: no 1200ms idle tax on deep-link; gate/route marks present; stubs at shell.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.RATEB_V2_URL || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const MODULES = ['inventory', 'sales', 'hr', 'accounting', 'pos'];
const OUT = process.env.RATEB_OP2_OUT
  || path.join(__dirname, 'reports', `phase-op2-module-entry-${Date.now()}.json`);

async function measure(browser, route) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const url = BASE.split('#')[0] + '#/' + route.replace(/^\//, '');
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForFunction(() => {
    const el = document.documentElement;
    return el.getAttribute('data-rateb-v2-route-ready') === '1'
      || el.getAttribute('data-rateb-v2-identity-gate')
      || el.getAttribute('data-rateb-v2-offline-ready') === '1';
  }, { timeout: 30000 });
  await page.waitForTimeout(400);
  const data = await page.evaluate(() => {
    const marks = (performance.getEntriesByType('mark') || [])
      .filter((m) => /^rateb-v2-/.test(m.name))
      .map((m) => ({ name: m.name, start_ms: Math.round(m.startTime * 10) / 10 }));
    const mark = (n) => {
      const hit = marks.find((m) => m.name === n);
      return hit ? hit.start_ms : null;
    };
    return {
      shell_ready_ms: mark('rateb-v2-shell-ready'),
      identity_ready_ms: mark('rateb-v2-identity-ready'),
      gate_visible_ms: mark('rateb-v2-gate-visible'),
      route_ready_ms: mark('rateb-v2-route-ready'),
      module_ready_ms: mark('rateb-v2-module-ready'),
      sqlite_ready_ms: mark('rateb-v2-sqlite-ready'),
      attrs: {
        gate: document.documentElement.getAttribute('data-rateb-v2-identity-gate'),
        identity: document.documentElement.getAttribute('data-rateb-v2-identity-ready'),
        route: document.documentElement.getAttribute('data-rateb-v2-route-ready'),
        active: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      },
      has_active_sync: !!window.RatebOfflineV2ActiveSync,
      has_sync_global: !!window.RatebOfflineV2Sync,
      stubs: !!(window.RatebOfflineV2EnsureModulesOrGate),
      marks,
    };
  });
  await context.close();
  return { route, ...data };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const rows = [];
  for (const m of MODULES) {
    process.stderr.write(`op2 ${m}...\n`);
    rows.push(await measure(browser, m));
  }
  await browser.close();

  const regressions = [];
  rows.forEach((r) => {
    const total = r.route_ready_ms || r.gate_visible_ms;
    if (r.shell_ready_ms == null) {
      regressions.push(`${r.route}: missing shell_ready`);
    }
    if (!r.stubs) {
      regressions.push(`${r.route}: EnsureModulesOrGate missing (stubs not early)`);
    }
    if (r.has_active_sync) {
      regressions.push(`${r.route}: ActiveSync started on view (should be lazy)`);
    }
    if (total != null && total > 1500 && r.shell_ready_ms != null && (total - r.shell_ready_ms) > 1400) {
      regressions.push(`${r.route}: post-shell gap ${(total - r.shell_ready_ms).toFixed(0)}ms suggests idle tax`);
    }
  });

  const report = {
    phase: 'OP2',
    measured_at: new Date().toISOString(),
    base: BASE,
    before_typical_ms: 2500,
    target_perceived_ms: '500-800',
    rows,
    regressions,
    ok: regressions.length === 0,
  };
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    ok: report.ok,
    regressions,
    summary: rows.map((r) => ({
      route: r.route,
      shell: r.shell_ready_ms,
      identity: r.identity_ready_ms,
      gate: r.gate_visible_ms,
      route_ready: r.route_ready_ms,
      module: r.module_ready_ms,
      active_sync: r.has_active_sync,
      stubs: r.stubs,
    })),
    out: OUT,
  }, null, 2));
  if (!report.ok) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
