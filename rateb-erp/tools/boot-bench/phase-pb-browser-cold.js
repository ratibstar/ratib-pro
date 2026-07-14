/**
 * Phase PB — cold browser path after FPM idle (SW blocked).
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';
const OUT_DIR = path.join(__dirname, 'reports');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 120000,
  });
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));

  // Force FPM idle kill on server
  console.error('[PB] waiting 25s for FPM ondemand idle kill...');
  ssh('sleep 25; echo workers=$(ps -eo args= | grep -c "[p]hp-fpm: pool" || true)');

  const profile = path.join(os.tmpdir(), 'rateb-pb-cold-' + Date.now());
  const context = await chromium.launchPersistentContext(profile, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage', '--disable-http-cache'],
    viewport: { width: 1365, height: 900 },
    locale: 'ar-SA',
    serviceWorkers: 'block',
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  const collect = async (label) => {
    const wall0 = Date.now();
    const resp = await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 120000 });
    const wall = Date.now() - wall0;
    const timing = await page.evaluate(() => {
      const n = performance.getEntriesByType('navigation')[0];
      const r = (a, b) => Math.max(0, Math.round((b - a) * 10) / 10);
      return {
        protocol: n.nextHopProtocol,
        transferSize: n.transferSize,
        encodedBodySize: n.encodedBodySize,
        decodedBodySize: n.decodedBodySize,
        dns_ms: r(n.domainLookupStart, n.domainLookupEnd),
        tcp_ms: r(n.connectStart, n.connectEnd),
        tls_ms: n.secureConnectionStart > 0 ? r(n.secureConnectionStart, n.connectEnd) : 0,
        request_start_ms: Math.round(n.requestStart * 10) / 10,
        response_start_ms: Math.round(n.responseStart * 10) / 10,
        ttfb_ms: r(n.requestStart, n.responseStart),
        ttfb_from_start_ms: Math.round(n.responseStart * 10) / 10,
        download_ms: r(n.responseStart, n.responseEnd),
        stall_before_request_ms: r(n.fetchStart, n.requestStart),
        dcl_ms: Math.round(n.domContentLoadedEventEnd * 10) / 10,
      };
    });
    return {
      label,
      wall_ms: wall,
      status: resp?.status(),
      fromSW: resp?.fromServiceWorker(),
      timing,
      title: await page.title(),
      register: await page.evaluate(() => !!document.querySelector('[data-pos-register]')),
    };
  };

  const cold = await collect('cold_after_fpm_idle');
  console.error('[PB] cold', JSON.stringify(cold.timing));
  const warm1 = await collect('warm1');
  console.error('[PB] warm1', JSON.stringify(warm1.timing));
  const warm2 = await collect('warm2');
  console.error('[PB] warm2', JSON.stringify(warm2.timing));

  await context.close();

  const server = JSON.parse(ssh('cat /tmp/phase-pb2/summary.json 2>/dev/null || echo {}'));
  let isolate = null;
  try {
    isolate = {
      cold: ssh('cat /tmp/phase-pb3/cold.txt 2>/dev/null || true').trim(),
      warm: ssh('cat /tmp/phase-pb3/warm.txt 2>/dev/null || true').trim(),
      warm2: ssh('cat /tmp/phase-pb3/warm2.txt 2>/dev/null || true').trim(),
      opc: JSON.parse(ssh('cat /tmp/phase-pb3/opc.json 2>/dev/null || echo null')),
      dnsPhp: JSON.parse(ssh('cat /tmp/phase-pb3/dns-php.json 2>/dev/null || echo null')),
      dnsCurl: ssh('cat /tmp/phase-pb3/dns-curl.txt 2>/dev/null || true').trim(),
    };
  } catch (_) {}

  const report = {
    phase: 'PB',
    generatedAt: new Date().toISOString(),
    note: 'SW blocked; real Chrome; production',
    browser: { cold, warm1, warm2 },
    server_cold_warm: server,
    isolate,
  };

  // Build definitive stage table using cold browser + isolate
  const t = cold.timing;
  const warmTtfb = warm2.timing.ttfb_ms;
  const fpmWarm = server.fpm_bodies?.[2]?.total_ms || server.fpm_bodies?.[0]?.total_ms;
  const stages = {
    dns: t.dns_ms,
    tcp_excluding_tls: Math.max(0, t.tcp_ms - t.tls_ms),
    tls: t.tls_ms,
    waiting_ttfb_after_request: t.ttfb_ms,
    html_download: t.download_ms,
    total_to_first_byte: t.ttfb_from_start_ms,
  };

  // Parse isolate cold_no_dns for FPM spawn
  let coldNoDnsTtfb = null;
  if (isolate?.cold) {
    const m = isolate.cold.match(/ttfb=([0-9.]+)/);
    if (m) coldNoDnsTtfb = Math.round(parseFloat(m[1]) * 1000);
  }
  let warmNoDnsTtfb = null;
  if (isolate?.warm2) {
    const m = isolate.warm2.match(/ttfb=([0-9.]+)/);
    if (m) warmNoDnsTtfb = Math.round(parseFloat(m[1]) * 1000);
  }

  report.lifecycle = {
    browser_cold: stages,
    browser_warm_ttfb_ms: warmTtfb,
    server_fpm_internal_warm_ms: fpmWarm,
    server_cold_no_dns_ttfb_ms: coldNoDnsTtfb,
    server_warm_no_dns_ttfb_ms: warmNoDnsTtfb,
    fpm_spawn_delta_ms:
      coldNoDnsTtfb != null && warmNoDnsTtfb != null ? coldNoDnsTtfb - warmNoDnsTtfb : null,
    opcache: isolate?.opc,
  };

  // Single biggest for cold browser navigation
  const candidates = [
    { id: 'dns', wall: t.dns_ms, file: 'OS / resolver', fn: 'DNS lookup rateb.sa' },
    { id: 'tcp', wall: Math.max(0, t.tcp_ms - t.tls_ms), file: 'TCP stack', fn: 'TCP connect' },
    { id: 'tls', wall: t.tls_ms, file: 'TLS handshake', fn: 'TLS ClientHello→Finished' },
    {
      id: 'server_wait',
      wall: t.ttfb_ms,
      file: 'Apache→PHP-FPM→Bootstrap path',
      fn: 'requestStart→responseStart (RTT + server)',
    },
    { id: 'download', wall: t.download_ms, file: 'HTML body', fn: 'responseStart→responseEnd' },
  ];
  if (coldNoDnsTtfb != null && warmNoDnsTtfb != null) {
    candidates.push({
      id: 'fpm_ondemand_spawn',
      wall: coldNoDnsTtfb - warmNoDnsTtfb,
      file: '/usr/local/directadmin/data/users/admin/php/php-fpm83.conf',
      fn: 'pm=ondemand worker spawn after idle timeout',
    });
  }
  candidates.sort((a, b) => (b.wall || 0) - (a.wall || 0));
  report.top_candidates_cold_browser = candidates;
  report.single_biggest_bottleneck = candidates[0];

  const out = path.join(OUT_DIR, `phase-pb-http-path-latest.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ out, lifecycle: report.lifecycle, biggest: report.single_biggest_bottleneck, cold: cold.timing, warm2: warm2.timing }, null, 2));
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
