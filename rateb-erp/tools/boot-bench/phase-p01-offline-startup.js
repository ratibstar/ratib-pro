/**
 * PERF-P0.1 — Offline startup recovery benchmark (read/measure only).
 * Compares against baseline gap 21251 ms (OC) / shell fail 265 ms (OC V2).
 *
 * Flow: mint session → warm admin (online) → ensure protected cache →
 * go offline → open offline-shell → measure unlock / dashboard path.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');
const EXPECT_BUILD = '20260716-perf-p03c-oa-shell-v73';
const BEFORE_GAP_MS = 21251;
const TARGET_MS = 3000;

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function waitForBuild(page, buildId, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  return (async () => {
    while (Date.now() < deadline) {
      const text = await page.evaluate(async (url) => {
        try {
          const r = await fetch(url + '?t=' + Date.now(), { cache: 'no-store' });
          return await r.text();
        } catch (e) {
          return '';
        }
      }, BASE + '/pos-sw.js');
      if (text.indexOf(buildId) !== -1) return { ok: true, snippet: true };
      await page.waitForTimeout(4000);
    }
    return { ok: false };
  })();
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const tReport = Date.now();
  let mint;
  try {
    mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  } catch (eMint) {
    try {
      mint = JSON.parse(ssh('php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint 2>/dev/null || php /tmp/remote-auth.php mint'));
    } catch (e2) {
      console.log(JSON.stringify({ ok: false, error: 'mint_failed', detail: String(eMint) }, null, 2));
      process.exit(2);
    }
  }

  const profileDir = path.join(os.tmpdir(), 'rateb-p01-' + tReport);
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || mint.cookie_name || 'rateb_erp',
      value: mint.session_id || mint.cookie_value || mint.value,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  const marks = [];
  const mark = (name, extra) => {
    marks.push(Object.assign({ name, t: Date.now() - tReport }, extra || {}));
  };

  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
  const live = await waitForBuild(page, EXPECT_BUILD, 120000);
  if (!live.ok) {
    const out = {
      ok: false,
      error: 'SW_BUILD_NOT_LIVE',
      expect: EXPECT_BUILD,
      note: 'Deploy PERF-P0.1 before measuring after numbers',
      before_baseline_ms: BEFORE_GAP_MS,
    };
    fs.writeFileSync(path.join(OUT_DIR, 'phase-p01-offline-startup-' + tReport + '.json'), JSON.stringify(out, null, 2));
    console.log(JSON.stringify(out, null, 2));
    await context.close();
    process.exit(3);
  }
  mark('sw_build_live');

  // Fresh SW + warm online
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.evaluate(async () => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  });
  await page.waitForTimeout(400);

  const swStart = Date.now();
  await page.evaluate(async (base) => {
    const reg = await navigator.serviceWorker.register(base + '/pos-sw.js?v=p01-' + Date.now(), {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    const sw = reg.installing || reg.waiting || reg.active;
    if (sw) {
      await new Promise((resolve) => {
        if (sw.state === 'activated') return resolve();
        sw.addEventListener('statechange', () => {
          if (sw.state === 'activated' || sw.state === 'redundant') resolve();
        });
        setTimeout(resolve, 20000);
      });
    }
    await navigator.serviceWorker.ready;
  }, BASE);
  const swStartupMs = Date.now() - swStart;
  mark('sw_activated', { swStartupMs });

  // Navigate admin so first document releases warm gate; force protected ensure
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const ensure = await page.evaluate(async () => {
    const t0 = performance.now();
    const reg = await navigator.serviceWorker.ready;
    return await new Promise((resolve) => {
      const timer = setTimeout(() => resolve({ ok: false, error: 'ensure_timeout', ms: Math.round(performance.now() - t0) }), 90000);
      const onMsg = (ev) => {
        const d = ev.data || {};
        if (d.type === 'PROTECTED_OFFLINE_CACHE_RESULT' || d.type === 'WARM_ERP_OFFLINE_SHELL_RESULT') {
          clearTimeout(timer);
          navigator.serviceWorker.removeEventListener('message', onMsg);
          resolve(Object.assign({ ms: Math.round(performance.now() - t0) }, d));
        }
      };
      navigator.serviceWorker.addEventListener('message', onMsg);
      try {
        reg.active.postMessage({ type: 'ENSURE_PROTECTED_OFFLINE_CACHE', force: true });
      } catch (e) {
        clearTimeout(timer);
        resolve({ ok: false, error: String(e), ms: Math.round(performance.now() - t0) });
      }
    });
  });
  mark('ensure_protected', ensure);

  // Cache lookup probe (online then will re-check offline)
  const cacheLookupOnline = await page.evaluate(async (base) => {
    const t0 = performance.now();
    const urls = [
      base + '/assets/offline/rateb-offline.js',
      base + '/assets/offline/rateb-offline.js?v=oid-20260713-lean',
      base + '/assets/offline/offline-bootstrap.js',
      base + '/assets/offline/erp-offline-tenant-context.js',
    ];
    const rows = [];
    for (const u of urls) {
      const r = await fetch(u, { cache: 'reload' }).catch(() => null);
      const text = r ? await r.text().catch(() => '') : '';
      rows.push({
        url: u.replace(base, ''),
        status: r ? r.status : 0,
        len: text.length,
        ms: Math.round(performance.now() - t0),
      });
    }
    return { ms: Math.round(performance.now() - t0), rows };
  }, BASE);
  mark('cache_lookup_online', cacheLookupOnline);

  // Offline
  await context.setOffline(true);
  mark('network_offline');

  const cacheLookupOffline = await page.evaluate(async (base) => {
    const t0 = performance.now();
    const urls = [
      base + '/assets/offline/rateb-offline.js?v=oid-20260713-lean',
      base + '/assets/offline/rateb-offline.js',
      base + '/assets/offline/offline-bootstrap.js',
      base + '/assets/offline/erp-offline-tenant-context.js',
    ];
    const rows = [];
    for (const u of urls) {
      const r = await fetch(u).catch(() => null);
      const text = r ? await r.text().catch(() => '') : '';
      rows.push({
        url: u.replace(base, ''),
        status: r ? r.status : 0,
        len: text.length,
        ok_body: text.length >= 1000 && !/identity missing|offline stub/i.test(text),
        ms: Math.round(performance.now() - t0),
      });
    }
    return { ms: Math.round(performance.now() - t0), rows };
  }, BASE);
  mark('cache_lookup_offline', cacheLookupOffline);

  const shellT0 = Date.now();
  let shellErr = null;
  try {
    await page.goto(BASE + '/offline-shell.html', { waitUntil: 'domcontentloaded', timeout: 15000 });
  } catch (eNav) {
    shellErr = String(eNav && eNav.message ? eNav.message : eNav);
  }
  const shellDclMs = Date.now() - shellT0;
  mark('shell_dcl', { shellDclMs, shellErr });

  // Wait for unlock hosts or explicit shell error — cap 8s (must not approach 21s)
  const unlock = await page.evaluate(async () => {
    const t0 = performance.now();
    const deadline = t0 + 8000;
    while (performance.now() < deadline) {
      const status = (document.getElementById('offline-status') || {}).textContent || '';
      const hasLock = !!(window.RatebOfflineAuthLock && window.RatebOfflineAuthLock.requireUnlockIfNeeded);
      const hasSdk = !!(window.RatebOffline && window.RatebOffline.ensure);
      const err = /load_failed|missing from cache|Unable to load/i.test(status);
      if (hasLock || hasSdk || err) {
        return {
          ms: Math.round(performance.now() - t0),
          hasLock,
          hasSdk,
          err,
          status: String(status).slice(0, 220),
        };
      }
      await new Promise((r) => setTimeout(r, 50));
    }
    return {
      ms: Math.round(performance.now() - t0),
      hasLock: false,
      hasSdk: !!(window.RatebOffline && window.RatebOffline.ensure),
      err: false,
      status: 'timeout_8s',
    };
  });
  mark('unlock', unlock);

  const dashT0 = Date.now();
  let dashErr = null;
  try {
    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 12000 });
  } catch (eD) {
    dashErr = String(eD && eD.message ? eD.message : eD);
  }
  const dashboardMs = Date.now() - dashT0;
  const dashSnap = await page.evaluate(() => ({
    href: location.href,
    title: document.title || '',
    bodyLen: (document.body && document.body.innerText || '').length,
    onLine: navigator.onLine,
  }));
  mark('dashboard', { dashboardMs, dashErr, dashSnap });

  const offlineStartupMs = shellDclMs + (unlock.ms || 0) + dashboardMs;
  const identityHits = (cacheLookupOffline.rows || []).filter((r) => r.ok_body).length;
  const identityTotal = (cacheLookupOffline.rows || []).length;
  const improved = offlineStartupMs < BEFORE_GAP_MS * 0.5 && offlineStartupMs < 15000;
  const pass = improved && identityHits >= 2 && !(unlock.err && !unlock.hasSdk);

  const report = {
    phase: 'PERF-P0.1',
    ok: pass,
    measured_at: new Date().toISOString(),
    sw_build: EXPECT_BUILD,
    before: {
      offline_startup_ms: BEFORE_GAP_MS,
      source: 'phase-oc-offline-profile-1784064232069 (NAV_OFFLINE_SHELL_DCL → DASHBOARD_NAV_LAST)',
      unlock_fail_ms: 265,
      source_v2: 'phase-oc-verdict / 1784064339776',
    },
    after: {
      offline_startup_ms: offlineStartupMs,
      unlock_ms: unlock.ms,
      sw_startup_ms: swStartupMs,
      cache_lookup_offline_ms: cacheLookupOffline.ms,
      shell_dcl_ms: shellDclMs,
      dashboard_load_ms: dashboardMs,
      identity_cache_hits: identityHits + '/' + identityTotal,
      unlock,
      ensure,
      cache_lookup_offline: cacheLookupOffline,
    },
    total_improvement_ms: BEFORE_GAP_MS - offlineStartupMs,
    target_ms: TARGET_MS,
    target_met: offlineStartupMs < TARGET_MS,
    pass,
    marks,
  };

  const outPath = path.join(OUT_DIR, 'phase-p01-offline-startup-' + tReport + '.json');
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await context.close();
  process.exit(pass ? 0 : 1);
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
