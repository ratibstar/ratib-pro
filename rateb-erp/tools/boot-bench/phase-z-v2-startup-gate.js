/**
 * Phase Z — Offline V2 production startup gate (fresh profile).
 * Measures time to Shell Ready on https://rateb.sa/.../v2/index.html
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const URL = process.env.RATEB_V2_URL || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = path.join(__dirname, 'reports', 'phase-z-v2-startup-' + Date.now() + '.json');

(async () => {
  const userDataDir = path.join(__dirname, '.chrome-user-data', 'phase-z-' + Date.now());
  fs.mkdirSync(userDataDir, { recursive: true });

  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });
  const page = context.pages()[0] || await context.newPage();

  const failed = [];
  page.on('response', (res) => {
    const u = res.url();
    if (u.indexOf('/v2/') !== -1 && res.status() >= 400) {
      failed.push({ url: u, status: res.status() });
    }
  });

  const t0 = Date.now();
  await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });

  let shellReadyMs = null;
  try {
    await page.waitForFunction(() => {
      return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
        || /Shell Ready/i.test((document.getElementById('boot-status') || {}).textContent || '');
    }, { timeout: 20000 });
    shellReadyMs = Date.now() - t0;
  } catch (e) {
    shellReadyMs = null;
  }

  // Allow SW install/activate to settle
  await page.waitForTimeout(1500);
  await page.evaluate(async () => {
    if (navigator.serviceWorker) {
      await navigator.serviceWorker.ready;
    }
  }).catch(() => null);

  const marks = await page.evaluate(() => {
    try {
      return (performance.getEntriesByType('mark') || [])
        .filter((m) => /^rateb-v2-/.test(m.name))
        .map((m) => ({ name: m.name, startTime: Math.round(m.startTime) }));
    } catch (e) {
      return [];
    }
  });

  const checks = await page.evaluate(() => {
    const ids = ['boot-status','pm-selftest','db-selftest','rt-selftest','router-selftest','shell-selftest','sw','sync-selftest','sdk-selftest','bm-selftest','id-selftest','inv-selftest','proc-selftest','sales-selftest','acct-selftest','crm-selftest','hr-selftest','mfg-selftest'];
    const out = {};
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) out[id] = el.textContent;
    });
    return out;
  });
  const bootStatus = checks['boot-status'] || '';
  const dbPass = checks['db-selftest'] || '';
  const swPass = checks['sw'] || '';
  const cacheKeys = await page.evaluate(async () => {
    try {
      return await caches.keys();
    } catch (e) {
      return [];
    }
  });

  // Warm reload online so controlling SW is active, then offline
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForFunction(() => {
    return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
      || /Shell Ready/i.test((document.getElementById('boot-status') || {}).textContent || '');
  }, { timeout: 20000 }).catch(() => null);
  await page.waitForTimeout(1500);

  await context.setOffline(true);
  const tOff = Date.now();
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch((e) => e.message);
  let offlineShellMs = null;
  try {
    await page.waitForFunction(() => {
      return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
        || /Shell Ready/i.test((document.getElementById('boot-status') || {}).textContent || '');
    }, { timeout: 15000 });
    offlineShellMs = Date.now() - tOff;
  } catch (e) {
    offlineShellMs = null;
  }
  const offlineBoot = await page.evaluate(() => (document.getElementById('boot-status') || {}).textContent || '');
  const offlineChecks = await page.evaluate(() => {
    const ids = ['pm-selftest','db-selftest','rt-selftest','router-selftest','shell-selftest','sw'];
    const out = {};
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) out[id] = el.textContent;
    });
    return out;
  });
  await context.setOffline(false);

  const swRegs = await page.evaluate(async () => {
    if (!navigator.serviceWorker) return [];
    const regs = await navigator.serviceWorker.getRegistrations();
    return regs.map((r) => ({
      scope: r.scope,
      active: !!(r.active && r.active.scriptURL),
      scriptURL: r.active ? r.active.scriptURL : null,
    }));
  });

  const out = {
    phase: 'Z',
    url: URL,
    shell_ready_ms: shellReadyMs,
    shell_ready_lt_3000: shellReadyMs !== null && shellReadyMs < 3000,
    boot_status: bootStatus,
    db_selftest: dbPass,
    sw_check: swPass,
    checks: checks,
    http_4xx_under_v2: failed,
    performance_marks: marks,
    cache_keys: cacheKeys,
    offline_shell_ready_ms: offlineShellMs,
    offline_boot_status: offlineBoot,
    offline_checks: offlineChecks,
    sw_registrations: swRegs,
    enterprise_production_startup:
      shellReadyMs !== null &&
      shellReadyMs < 3000 &&
      /Shell Ready/i.test(bootStatus) &&
      /PASS/i.test(dbPass) &&
      failed.length === 0 &&
      offlineShellMs !== null
        ? 'PASS'
        : 'FAIL',
  };

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await context.close();
  process.exit(out.enterprise_production_startup === 'PASS' ? 0 : 1);
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
