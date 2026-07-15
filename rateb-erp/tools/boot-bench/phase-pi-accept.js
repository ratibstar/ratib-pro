/**
 * Phase PI — auth-lock off first-scan critical path (accept).
 * Natural SW · production · measure responseStart → Scan Ready.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const ADMIN = BASE + '/admin/';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const REMOTE = '/home/admin/domains/rateb.sa/public_html/rateb-erp/modules/pos/views/layouts/pos-shell.php';
const LOCAL = path.join(__dirname, '..', '..', 'modules', 'pos', 'views', 'layouts', 'pos-shell.php');
const OUT_DIR = path.join(__dirname, 'reports');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

function scp(local, remote) {
  execFileSync('scp', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', local, HOST + ':' + remote], {
    stdio: 'inherit',
  });
}

async function waitScanReady(page, timeoutMs) {
  const t0 = Date.now();
  while (Date.now() - t0 < timeoutMs) {
    const snap = await page.evaluate(() => {
      const root = document.querySelector('[data-pos-register]');
      const ready = root && root.getAttribute('data-pos-register-ready') === '1';
      const barcode = document.querySelector('[data-pos-barcode-input]');
      const tiles = document.querySelector('[data-pos-product-list]');
      const tilesReady = !!(tiles && (tiles.children.length > 0 || document.querySelector('[data-pos-catalog-empty]')));
      return { ready: !!ready, barcode: !!barcode, tilesReady, register: !!root, t: performance.now() };
    });
    if (snap.register && snap.ready && snap.tilesReady && snap.barcode) {
      return { ok: true, ms: Date.now() - t0, snap };
    }
    await page.waitForTimeout(25);
  }
  return { ok: false, ms: Date.now() - t0 };
}

(async () => {
  ssh('cp ' + REMOTE + ' ' + REMOTE + '.bak-pi-$(date +%s)');
  scp(LOCAL, '/tmp/pos-shell-pi.php');
  ssh('cp /tmp/pos-shell-pi.php ' + REMOTE + ' && grep -n "Phase PI\\|posAuthLockIdle\\|pos-auth-lock" ' + REMOTE + ' | head -25');

  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profileDir = path.join(os.tmpdir(), 'rateb-pi-' + Date.now());
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
      name: mint.session_name,
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);

  const page = context.pages()[0] || (await context.newPage());
  await page.goto(ADMIN, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(4000);

  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  const scan = await waitScanReady(page, 15000);

  // Capture scan clock BEFORE waiting for idle auth-lock
  const atScan = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return {
      responseStart: Math.round((n.responseStart || 0) * 10) / 10,
      dcl: Math.round((n.domContentLoadedEventEnd || 0) * 10) / 10,
      now: Math.round(performance.now() * 10) / 10,
    };
  });
  const afterRsScan = Math.max(0, Math.round((atScan.now - atScan.responseStart) * 10) / 10);
  const dclAfterRs = Math.max(0, Math.round((atScan.dcl - atScan.responseStart) * 10) / 10);

  // Wait for idle auth-lock (must still execute)
  let authApi = false;
  for (let i = 0; i < 50; i++) {
    authApi = await page.evaluate(() => !!(window.RatebPosAuthLock && window.RatebPosAuthLock.initOnRegister));
    if (authApi) break;
    await page.waitForTimeout(100);
  }

  const metrics = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const rs = n.responseStart || 0;
    const dcl = n.domContentLoadedEventEnd || 0;
    const resources = performance.getEntriesByType('resource').map((x) => ({
      name: x.name.replace(location.origin, ''),
      start: Math.round(x.startTime * 10) / 10,
      duration: Math.round(x.duration * 10) / 10,
      end: Math.round(x.responseEnd * 10) / 10,
      end_after_rs: Math.round((x.responseEnd - rs) * 10) / 10,
      transferSize: x.transferSize || 0,
      decodedBodySize: x.decodedBodySize || 0,
    }));
    const scripts = resources.filter((x) => /\.js(\?|$)/i.test(x.name));
    const auth = scripts.find((s) => /pos-auth-lock\.js/.test(s.name));
    const peers = scripts.filter((s) => /pos-register\.js|pos-keyboard|pos-module|pos-biometric|theme\.js/.test(s.name));
    const peerMaxEnd = peers.reduce((m, s) => Math.max(m, s.end_after_rs || 0), 0);
    const authOnCritical = !!auth && auth.end <= dcl + 5;

    return {
      responseStart: Math.round(rs * 10) / 10,
      dcl: Math.round(dcl * 10) / 10,
      auth,
      peerMaxEnd,
      authOnCritical,
      authLoadedAfterDcl: !auth || auth.end > dcl - 1,
      authHttpOk: !!(auth && (auth.decodedBodySize || 0) > 1000),
      scripts: scripts
        .slice()
        .sort((a, b) => a.start - b.start)
        .map((s) => ({
          leaf: s.name.split('/').pop().split('?')[0],
          end_after_rs: s.end_after_rs,
          duration: s.duration,
          transferSize: s.transferSize,
          decodedBodySize: s.decodedBodySize,
        })),
      hasLockApi: {
        initOnRegister: !!(window.RatebPosAuthLock && window.RatebPosAuthLock.initOnRegister),
        isUnlocked: !!(window.RatebPosAuthLock && window.RatebPosAuthLock.isUnlocked),
        lock: !!(window.RatebPosAuthLock && window.RatebPosAuthLock.lock),
        unlockWithPin: !!(window.RatebPosAuthLock && window.RatebPosAuthLock.unlockWithPin),
      },
    };
  });

  metrics.authApi = authApi;
  metrics.dcl_after_rs = dclAfterRs;
  metrics.after_rs_scan = afterRsScan;
  metrics.scan_t = atScan.now;

  const before = {
    after_rs_scan: 540.5,
    dcl_after_rs: 501.1,
    auth_stall_ms: 294.7,
  };

  const targets = {
    after_rs_scan_le_250: afterRsScan <= 250,
    dcl_not_waiting_auth: !metrics.authOnCritical && metrics.authLoadedAfterDcl === true,
    auth_still_loads: metrics.authApi === true && metrics.hasLockApi.initOnRegister === true,
    auth_http_ok: metrics.authHttpOk === true,
    scan_ok: scan.ok === true,
  };

  const pass =
    targets.after_rs_scan_le_250 &&
    targets.dcl_not_waiting_auth &&
    targets.auth_still_loads &&
    targets.auth_http_ok &&
    targets.scan_ok;

  const report = {
    phase: 'PI',
    generatedAt: new Date().toISOString(),
    before,
    after: {
      after_rs_scan: afterRsScan,
      dcl_after_rs: metrics.dcl_after_rs,
      responseStart: metrics.responseStart,
      dcl: metrics.dcl,
      scan,
    },
    metrics,
    targets,
    gain_ms: Math.round((before.after_rs_scan - afterRsScan) * 10) / 10,
    enterprise: pass ? 'PASS' : 'FAIL',
    note: pass
      ? 'Auth-lock idle after DCL; scan path ≤250ms after responseStart'
      : 'Acceptance not met — see targets',
  };

  fs.mkdirSync(OUT_DIR, { recursive: true });
  fs.writeFileSync(path.join(OUT_DIR, `phase-pi-accept-${Date.now()}.json`), JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pi-accept-latest.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));

  await context.close();
  if (!pass) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
