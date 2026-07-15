const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const os = require('os');
const path = require('path');

const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const ssh = (c) =>
  execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, c], {
    encoding: 'utf8',
    timeout: 60000,
  });

(async () => {
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'csrf2-' + Date.now()), {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
  });
  await ctx.clearCookies();
  await ctx.addCookies([
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
  const page = ctx.pages()[0] || (await ctx.newPage());
  const bad = [];
  page.on('response', (r) => {
    if (r.request().method() === 'POST' && /\/pos\/api\//i.test(r.url()) && r.status() === 419) {
      bad.push(r.url());
    }
  });
  await page.goto('https://rateb.sa/rateb-erp/public/admin/', {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  });
  await page.waitForTimeout(1500);
  await page.goto('https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22', {
    waitUntil: 'commit',
    timeout: 60000,
  });
  await page.waitForSelector('[data-pos-register]', { timeout: 30000 });
  await page.waitForTimeout(2000);
  await page.goto('https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22', {
    waitUntil: 'commit',
    timeout: 60000,
  });
  await page.waitForSelector('[data-pos-register]', { timeout: 30000 });
  await page.waitForTimeout(2500);

  const out = await page.evaluate(async () => {
    const cfg = JSON.parse((document.getElementById('rateb-pos-register-config') || {}).textContent || '{}');
    const csrf = cfg.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    const api = cfg.api || {};
    const posts = [];
    async function post(label, url, extra) {
      const b = new URLSearchParams();
      b.set('_csrf', csrf);
      b.set('lines', '[]');
      if (extra) Object.keys(extra).forEach((k) => b.set(k, extra[k]));
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
        body: b,
      });
      posts.push({ label, status: res.status });
    }
    if (api.sessionSave) await post('sessionSave', api.sessionSave, { customer: 'null' });
    if (api.pricing) await post('pricing', api.pricing);
    return {
      csrfPrefix: csrf.slice(0, 8),
      sw: navigator.serviceWorker?.controller?.scriptURL || null,
      posts,
      xfer: performance.getEntriesByType('navigation')[0]?.transferSize,
    };
  });

  await ctx.close();
  const pass = bad.length === 0 && out.posts.every((p) => p.status !== 419);
  console.log(JSON.stringify({ out, bad419: bad, pass }, null, 2));
  if (!pass) process.exit(2);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
