/**
 * Offline V2 — Refresh hang investigation (F5 after Shell Ready).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const URL = process.env.RATEB_V2_URL || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = path.join(__dirname, 'reports', 'phase-refresh-hang-' + Date.now() + '.json');

(async () => {
  const userDataDir = path.join(__dirname, '.chrome-user-data', 'refresh-hang-' + Date.now());
  fs.mkdirSync(userDataDir, { recursive: true });
  const context = await chromium.launchPersistentContext(userDataDir, { headless: true });
  const page = context.pages()[0] || await context.newPage();

  const consoleLogs = [];
  const pageErrors = [];
  page.on('console', (m) => consoleLogs.push({ type: m.type(), text: m.text() }));
  page.on('pageerror', (e) => pageErrors.push(String(e && e.message ? e.message : e)));

  async function snap(label) {
    return page.evaluate((lab) => {
      const boot = (document.getElementById('boot-status') || {}).textContent || '';
      const marks = (performance.getEntriesByType('mark') || [])
        .filter((m) => /^rateb-v2-/.test(m.name))
        .map((m) => ({ name: m.name, t: Math.round(m.startTime) }));
      const nav = (performance.getEntriesByType('navigation')[0] || {});
      return {
        label: lab,
        boot,
        shellAttr: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
        hasDB: !!window.RatebOfflineV2DB,
        hasHCI: !!window.RatebOfflineV2HCI,
        hasPM: !!window.RatebOfflineV2PM,
        hasRT: !!window.RatebOfflineV2Runtime,
        hasRouter: !!window.RatebOfflineV2Router,
        hasShell: !!window.RatebOfflineV2Shell,
        readyState: document.readyState,
        navType: nav.type || null,
        marks,
        dbMode: (document.getElementById('db-selftest') || {}).textContent || '',
        layoutEnsure: (document.getElementById('layout-ensure') || {}).textContent || '',
        bodyTextLen: (document.body && document.body.innerText || '').length,
      };
    }, label);
  }

  const t0 = Date.now();
  await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  let firstShell = null;
  try {
    await page.waitForFunction(() => {
      return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
        || /Shell Ready/i.test((document.getElementById('boot-status') || {}).textContent || '');
    }, { timeout: 20000 });
    firstShell = Date.now() - t0;
  } catch (e) {
    firstShell = null;
  }
  const afterFirst = await snap('after_first_load');

  // Give SW time to control
  await page.waitForTimeout(2000);

  const tReload = Date.now();
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
  let refreshShell = null;
  let refreshSnapEarly = null;
  try {
    refreshSnapEarly = await snap('reload_domcontentloaded');
    await page.waitForFunction(() => {
      return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
        || /Shell Ready/i.test((document.getElementById('boot-status') || {}).textContent || '');
    }, { timeout: 20000 });
    refreshShell = Date.now() - tReload;
  } catch (e) {
    refreshShell = null;
  }
  // If hung, sample at 3s / 8s
  const mid = await snap('reload_mid_or_done');
  if (refreshShell === null) {
    await page.waitForTimeout(5000);
  }
  const late = await snap('reload_late');

  const pending = await page.evaluate(() => {
    try {
      return performance.getEntriesByType('resource')
        .filter((r) => r.responseEnd === 0 || (r.duration === 0 && r.transferSize === 0))
        .slice(0, 30)
        .map((r) => ({ name: r.name, initiatorType: r.initiatorType }));
    } catch (e) {
      return [];
    }
  });

  const out = {
    url: URL,
    first_shell_ms: firstShell,
    refresh_shell_ms: refreshShell,
    refresh_hang: refreshShell === null,
    afterFirst,
    refreshSnapEarly,
    mid,
    late,
    pageErrors,
    consoleLogs: consoleLogs.slice(-40),
    pendingish_resources: pending,
  };
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await context.close();
  process.exit(refreshShell !== null ? 0 : 1);
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
