/**
 * Smoke: POS register CSRF POST after SW network-first fix.
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const os = require('os');

const BASE = 'https://rateb.sa/rateb-erp/public';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 60000,
  });
}

(async () => {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const dir = path.join(os.tmpdir(), 'rateb-csrf-' + Date.now());
  const context = await chromium.launchPersistentContext(dir, {
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
  const failed = [];
  page.on('response', (res) => {
    if (res.request().method() === 'POST' && /\/pos\/api\//i.test(res.url()) && res.status() === 419) {
      failed.push({ url: res.url(), status: 419 });
    }
  });

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  // Force fresh SW
  await page.evaluate(async (base) => {
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => /rateb-pos-shell|rateb-pos-assets/.test(k)).map((k) => caches.delete(k)));
    await navigator.serviceWorker.register(base + '/pos-sw.js?v=csrf-nav-v66', {
      scope: base.endsWith('/') ? base : base + '/',
      updateViaCache: 'none',
    });
    await navigator.serviceWorker.ready;
  }, BASE);
  await page.waitForTimeout(500);

  // Warm: visit twice so second would have been cache-first before fix
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(800);
  await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(1500);

  // Trigger cart save / pricing like UI
  const result = await page.evaluate(async () => {
    const cfg = JSON.parse((document.getElementById('rateb-pos-register-config') || {}).textContent || '{}');
    const csrf = cfg.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    const api = cfg.api || {};
    const posts = [];
    async function post(url, body) {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
        body,
      });
      let data = null;
      try {
        data = await res.json();
      } catch (_) {}
      posts.push({ url: url.replace(location.origin, ''), status: res.status, ok: !!(data && data.ok !== false && res.ok) });
      return res.status;
    }
    const lines = JSON.stringify([
      {
        line_id: 'demo1',
        product_id: 0,
        name: 'Espresso (Demo)',
        qty: 1,
        unit_price: 12,
        line_total: 12,
      },
    ]);
    if (api.pricing) {
      const b = new URLSearchParams();
      b.set('_csrf', csrf);
      b.set('lines', lines);
      await post(api.pricing, b);
    }
    if (api.sessionSave) {
      const b = new URLSearchParams();
      b.set('_csrf', csrf);
      b.set('lines', lines);
      b.set('customer', 'null');
      await post(api.sessionSave, b);
    }
    return {
      csrfLen: csrf.length,
      sw: navigator.serviceWorker?.controller?.scriptURL || null,
      transfer: performance.getEntriesByType('navigation')[0]?.transferSize,
      posts,
    };
  });

  await context.close();
  const report = { result, failed419: failed, pass: failed.length === 0 && (result.posts || []).every((p) => p.status !== 419 && p.status < 500) };
  console.log(JSON.stringify(report, null, 2));
  if (!report.pass) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
