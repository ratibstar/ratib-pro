/**
 * Offline Bootstrap regression.
 *
 * Fresh profile -> install Service Worker/PWA assets -> immediately offline ->
 * launch manifest start URL -> verify full local platform and module bootstrap.
 */
'use strict';

const fs = require('fs');
const http = require('http');
const os = require('os');
const path = require('path');
const { chromium } = require('playwright');

const PROJECT_ROOT = path.resolve(__dirname, '..', '..');
const PUBLIC_ROOT = path.join(PROJECT_ROOT, 'public');
const URL_PREFIX = '/rateb-erp/public/';
const MODULE_IDS = [
  'identity',
  'inventory',
  'procurement',
  'sales',
  'accounting',
  'crm',
  'hr',
  'mfg',
];

const MIME = {
  '.css': 'text/css; charset=utf-8',
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.png': 'image/png',
  '.wasm': 'application/wasm',
  '.webmanifest': 'application/manifest+json; charset=utf-8',
};

function startServer() {
  const server = http.createServer((req, res) => {
    const pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
    if (!pathname.startsWith(URL_PREFIX)) {
      res.writeHead(404);
      res.end('not found');
      return;
    }
    const relative = pathname.slice(URL_PREFIX.length);
    const file = path.resolve(PUBLIC_ROOT, relative);
    if (!file.startsWith(PUBLIC_ROOT + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
      res.writeHead(404);
      res.end('not found');
      return;
    }
    const headers = {
      'Cache-Control': 'no-store',
      'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream',
    };
    if (pathname.endsWith('/v2/sw.js')) {
      headers['Service-Worker-Allowed'] = '/rateb-erp/public/v2/';
    }
    res.writeHead(200, headers);
    fs.createReadStream(file).pipe(res);
  });

  return new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      resolve({
        server,
        origin: `http://127.0.0.1:${server.address().port}`,
      });
    });
  });
}

async function snapshot(page) {
  return page.evaluate((moduleIds) => {
    const runtime = window.RatebOfflineV2Runtime;
    const framework = window.RatebOfflineV2ActiveBusiness;
    const router = window.RatebOfflineV2AppShell
      && window.RatebOfflineV2AppShell.getRouter
      && window.RatebOfflineV2AppShell.getRouter();
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const modules = {};

    moduleIds.forEach((id) => {
      const record = framework && framework.getModule ? framework.getModule(id) : null;
      modules[id] = {
        present: !!record,
        state: record ? record.state : null,
      };
    });

    return {
      online: navigator.onLine,
      shellReady: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
      precacheReady: document.documentElement.getAttribute('data-rateb-v2-precache-ready'),
      offlineReady: document.documentElement.getAttribute('data-rateb-v2-offline-ready'),
      modulesReady: document.documentElement.getAttribute('data-rateb-v2-business-modules-ready'),
      activeModule: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      outletRoute: outlet ? outlet.getAttribute('data-route') : null,
      bootStatus: (document.getElementById('boot-status') || {}).textContent || '',
      serviceWorkerControlled: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      runtimeReady: !!(runtime && runtime.getState && runtime.getState() === 'ready'),
      routerReady: !!router,
      identityUnlockApi: !!(
        runtime
        && runtime.services
        && runtime.services.has('module.identity.unlock')
      ),
      sqliteOpen: !!(
        window.RatebOfflineV2DB
        && window.RatebOfflineV2DB.isOpen
        && window.RatebOfflineV2DB.isOpen()
      ),
      modules,
    };
  }, MODULE_IDS);
}

function assertPass(result) {
  const failures = [];
  if (result.online !== false) failures.push('browser_not_offline');
  if (!result.serviceWorkerControlled) failures.push('service_worker_not_controlling');
  if (result.shellReady !== '1') failures.push('shell_not_ready');
  if (result.offlineReady !== '1') failures.push('offline_bootstrap_not_ready');
  if (result.modulesReady !== '1') failures.push('business_modules_not_ready');
  if (!result.runtimeReady) failures.push('runtime_not_ready');
  if (!result.routerReady) failures.push('router_not_ready');
  if (!result.identityUnlockApi) failures.push('identity_unlock_api_missing');
  if (!result.sqliteOpen) failures.push('sqlite_not_open');
  MODULE_IDS.forEach((id) => {
    if (!result.modules[id] || !result.modules[id].present || result.modules[id].state !== 'active') {
      failures.push(`module_not_active:${id}`);
    }
  });
  return failures;
}

async function verifyOfflineRoutes(page) {
  return page.evaluate(async (moduleIds) => {
    const router = window.RatebOfflineV2AppShell
      && window.RatebOfflineV2AppShell.getRouter
      && window.RatebOfflineV2AppShell.getRouter();
    if (!router) return { ok: false, error: 'router_missing', routes: [] };
    const routes = [];
    for (const id of moduleIds) {
      const route = `/${id}`;
      const result = await router.navigate(route, { replace: true });
      routes.push({
        route,
        ok: !!(result && result.ok),
        reason: result && result.reason ? result.reason : null,
      });
    }
    await router.navigate('/identity', { replace: true });
    return {
      ok: routes.every((row) => row.ok),
      routes,
    };
  }, MODULE_IDS);
}

async function verifyOfflineIdentityUnlock(page) {
  return page.evaluate(async () => {
    const framework = window.RatebOfflineV2ActiveBusiness;
    const record = framework && framework.getModule && framework.getModule('identity');
    const identity = record && record.module;
    if (!identity) return { ok: false, error: 'identity_module_missing' };

    // Test-only authority payload; contains no credential or authentication secret.
    const enrollment = {
      schema: 'rateb-offline-v2-identity-enroll/1',
      claims: {
        user_id: 1001,
        company_id: 2001,
        branch_id: 1,
        display_name: 'Offline Bootstrap Test',
        email_hint: 'o***@example.test',
        enrolled_at: new Date().toISOString(),
      },
      sealed: {
        envelope_version: 1,
        payload: {
          claim_fingerprint: 'offline-bootstrap-test-fixture',
          issued_by: 'online_erp',
        },
      },
      rbac: {
        version: 1,
        permissions: ['dashboard.view'],
        roles: ['employee'],
      },
      device: {
        device_id: 'offline-bootstrap-test-device',
        status: 'ACTIVE',
        company_id: 2001,
        label: 'Offline Bootstrap Test',
      },
      session_policy: {
        unlock_ttl_sec: 3600,
        idle_ttl_sec: 900,
        max_offline_sec: 86400,
      },
    };

    await identity.applyEnrollmentPackage(enrollment);
    await identity.setLocalUnlockPin('2468');
    await identity.lock();
    const unlocked = await window.RatebOfflineV2Business.invokePublished(
      'identity',
      'unlock',
      '2468'
    );
    return {
      ok: !!(
        unlocked
        && unlocked.ok
        && unlocked.session
        && unlocked.session.unlocked
        && unlocked.session.has_server_credentials === false
      ),
      unlocked: !!(unlocked && unlocked.session && unlocked.session.unlocked),
      authority: unlocked && unlocked.session && unlocked.session.authority,
      hasServerCredentials: unlocked && unlocked.session
        ? unlocked.session.has_server_credentials
        : null,
    };
  });
}

(async () => {
  const { server, origin } = await startServer();
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'rateb-offline-bootstrap-'));
  const startUrl = `${origin}/rateb-erp/public/v2/index.html#/identity`;
  const report = {
    phase: 'OFFLINE_BOOTSTRAP',
    profile,
    startUrl,
    install: null,
    offlineLaunch: null,
    failures: [],
    status: 'FAIL',
  };

  let context;
  try {
    context = await chromium.launchPersistentContext(profile, {
      headless: true,
      serviceWorkers: 'allow',
    });

    // Installation visit: stop at the atomic Service Worker precache contract.
    // Do not wait for online Runtime, SQLite, Identity, or module warming.
    const installPage = context.pages()[0] || await context.newPage();
    await installPage.goto(startUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await installPage.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-v2-precache-ready') === '1',
      null,
      { timeout: 60000 }
    );
    report.install = await snapshot(installPage);
    const installability = await context.newCDPSession(installPage);
    report.installabilityErrors = await installability.send('Page.getInstallabilityErrors');
    await installPage.close();

    // Regression requirement: no warm navigation; disconnect immediately and launch.
    await context.setOffline(true);
    const offlinePage = await context.newPage();
    const offlineRequestFailures = [];
    offlinePage.on('requestfailed', (request) => {
      offlineRequestFailures.push({
        url: request.url(),
        error: request.failure() && request.failure().errorText,
      });
    });
    await offlinePage.goto(startUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await offlinePage.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-v2-offline-ready') === '1'
        || document.documentElement.getAttribute('data-rateb-v2-offline-ready') === '0',
      null,
      { timeout: 60000 }
    );
    report.offlineLaunch = await snapshot(offlinePage);
    report.offlineLaunch.routes = await verifyOfflineRoutes(offlinePage);
    report.offlineLaunch.identityUnlock = await verifyOfflineIdentityUnlock(offlinePage);
    report.offlineLaunch.requestFailures = offlineRequestFailures;
    report.failures = assertPass(report.offlineLaunch);
    if (!report.offlineLaunch.routes.ok) {
      report.failures.push('offline_routes_failed');
    }
    if (!report.offlineLaunch.identityUnlock.ok) {
      report.failures.push('offline_identity_unlock_failed');
    }
    if (offlineRequestFailures.length) {
      report.failures.push('offline_network_request_failed');
    }
    report.status = report.failures.length === 0 ? 'PASS' : 'FAIL';
  } catch (error) {
    report.failures.push(String(error && error.stack ? error.stack : error));
  } finally {
    if (context) await context.close().catch(() => null);
    await new Promise((resolve) => server.close(resolve));
  }

  const out = path.join(
    __dirname,
    'reports',
    `offline-bootstrap-regression-${Date.now()}.json`
  );
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, status: report.status, failures: report.failures }, null, 2));
  process.exitCode = report.status === 'PASS' ? 0 : 1;
})();
