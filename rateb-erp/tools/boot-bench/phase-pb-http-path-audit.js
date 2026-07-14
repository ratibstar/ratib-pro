/**
 * Phase PB — browser HTTP path lifecycle (READ ONLY).
 * Uses PerformanceNavigationTiming + resource timing against production POS register.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const REGISTER = BASE + '/admin/ops/pos/register?company_id=22';
const OUT_DIR = path.join(__dirname, 'reports');
const STAMP = Date.now();
const KEY = 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = 'admin@167.233.71.107';

const log = (...a) => console.error('[PB]', ...a);

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=30', HOST, cmd], {
    encoding: 'utf8',
    timeout: 180000,
  });
}

function scp(local, remote) {
  execFileSync('scp', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', local, HOST + ':' + remote], {
    stdio: 'inherit',
  });
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const report = {
    phase: 'PB',
    mode: 'READ_ONLY_HTTP_PATH_AUDIT',
    generatedAt: new Date().toISOString(),
    target: REGISTER,
  };

  // Upload + run server audit
  scp(path.join(__dirname, 'phase-pb-http-path-audit.sh'), '/tmp/phase-pb-http-path-audit.sh');
  scp(path.join(__dirname, 'remote-auth.php'), '/tmp/remote-auth-pa.php');
  ssh("sed -i 's/\\r$//' /tmp/phase-pb-http-path-audit.sh /tmp/remote-auth-pa.php && bash /tmp/phase-pb-http-path-audit.sh");
  const serverJson = ssh('cat /tmp/phase-pb-http-path.json');
  report.server = JSON.parse(serverJson);
  log('server loopback ttfb', report.server?.curl_loopback?.avg?.ttfb);

  // Browser measurement
  const mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  const profile = path.join(os.tmpdir(), 'rateb-pb-' + STAMP);
  const context = await chromium.launchPersistentContext(profile, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
    viewport: { width: 1365, height: 900 },
    locale: 'ar-SA',
    serviceWorkers: 'block', // measure real HTTP path, not SW cache
  });
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || mint.cookie || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);

  const page = context.pages()[0] || (await context.newPage());
  const runs = [];

  for (let i = 0; i < 3; i++) {
    const wall0 = Date.now();
    const resp = await page.goto(REGISTER, { waitUntil: 'domcontentloaded', timeout: 120000 });
    const wall = Date.now() - wall0;
    const timing = await page.evaluate(() => {
      const n = performance.getEntriesByType('navigation')[0];
      if (!n) return null;
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
        // Alternative TTFB from nav start (includes DNS/TCP/TLS)
        ttfb_from_start_ms: Math.round(n.responseStart * 10) / 10,
        download_ms: r(n.responseStart, n.responseEnd),
        dom_interactive_ms: Math.round(n.domInteractive * 10) / 10,
        dcl_ms: Math.round(n.domContentLoadedEventEnd * 10) / 10,
        load_ms: Math.round(n.loadEventEnd * 10) / 10,
        redirect_ms: r(n.redirectStart, n.redirectEnd),
        worker_ms: n.workerStart > 0 ? r(n.workerStart, n.fetchStart) : 0,
        stall_before_request_ms: r(n.fetchStart, n.requestStart),
      };
    });
    runs.push({
      run: i + 1,
      wall_ms: wall,
      status: resp ? resp.status() : null,
      fromServiceWorker: resp ? resp.fromServiceWorker() : null,
      timing,
      title: await page.title(),
      hasRegister: await page.evaluate(() => !!document.querySelector('[data-pos-register]')),
    });
    log('browser run', i + 1, timing);
  }

  report.browser = { serviceWorkers: 'blocked', runs };
  report.browser_avg = {
    ttfb_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.ttfb_ms || 0), 0) / runs.length) * 10) / 10,
    ttfb_from_start_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.ttfb_from_start_ms || 0), 0) / runs.length) * 10) / 10,
    dns_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.dns_ms || 0), 0) / runs.length) * 10) / 10,
    tcp_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.tcp_ms || 0), 0) / runs.length) * 10) / 10,
    tls_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.tls_ms || 0), 0) / runs.length) * 10) / 10,
    download_ms: Math.round((runs.reduce((a, r) => a + (r.timing?.download_ms || 0), 0) / runs.length) * 10) / 10,
  };

  await context.close();

  // Compute stage tree + bottleneck
  const b = report.browser_avg;
  const loop = report.server?.curl_loopback?.avg || {};
  const fpmBodies = report.server?.fpm_probe_bodies || [];
  const fpmAvg = (key) => {
    const vals = fpmBodies.map((x) => x[key]).filter((v) => typeof v === 'number');
    return vals.length ? Math.round((vals.reduce((a, v) => a + v, 0) / vals.length) * 10) / 10 : null;
  };
  const fpmStageAvg = (stage) => {
    const vals = fpmBodies.map((x) => x.stage_ms?.[stage]).filter((v) => typeof v === 'number');
    return vals.length ? Math.round((vals.reduce((a, v) => a + v, 0) / vals.length) * 10) / 10 : null;
  };

  const browserTtfb = b.ttfb_from_start_ms || b.ttfb_ms;
  const serverTtfb = loop.ttfb; // loopback — excludes client DNS to remote
  const fpmInternal = fpmAvg('total_ms');
  const fpmHttpTtfb = report.server?.fpm_probe_http?.avg?.ttfb;

  // Queue wait estimate: HTTP TTFB to FPM probe minus internal FPM work
  const queueWaitEstimate =
    fpmHttpTtfb != null && fpmInternal != null ? Math.max(0, Math.round((fpmHttpTtfb * 1000 - fpmInternal) * 10) / 10) : null;

  const stages = [
    { id: '1_dns', label: 'DNS', wall_ms: b.dns_ms, source: 'browser' },
    { id: '2_tcp', label: 'TCP', wall_ms: Math.max(0, (b.tcp_ms || 0) - (b.tls_ms || 0)), source: 'browser' },
    { id: '3_tls', label: 'TLS', wall_ms: b.tls_ms, source: 'browser' },
    { id: '4_http_accept_proxy', label: 'HTTP Accept → Apache/LiteSpeed (loopback TTFB - FPM internal)', wall_ms: serverTtfb != null && fpmInternal != null ? Math.max(0, Math.round(serverTtfb * 1000 - fpmInternal)) : null, source: 'derived' },
    { id: '5_fpm_queue_wait', label: 'PHP-FPM queue wait (HTTP probe TTFB - internal)', wall_ms: queueWaitEstimate, source: 'derived' },
    { id: '6_bootstrap_require', label: 'require Bootstrap.php', wall_ms: fpmStageAvg('after_bootstrap_require'), source: 'fpm' },
    { id: '7_bootstrap_init', label: 'Bootstrap::init', wall_ms: fpmAvg('bootstrap_init_ms'), source: 'fpm' },
    { id: '8_pos_module', label: 'PosModule::init', wall_ms: fpmStageAvg('after_pos_module'), source: 'fpm' },
    { id: '9_offline_module', label: 'OfflineModule::init', wall_ms: fpmStageAvg('after_offline_module'), source: 'fpm' },
    { id: '10_auth', label: 'Auth::bootstrapFromSession', wall_ms: fpmAvg('auth_ms'), source: 'fpm' },
    { id: '11_routes', label: 'Route load', wall_ms: fpmAvg('routes_load_ms'), source: 'fpm' },
    { id: '12_middleware', label: 'ErpAuthMiddleware', wall_ms: fpmAvg('middleware_ms'), source: 'fpm' },
    { id: '13_controller_view', label: 'Router::dispatch (controller+view)', wall_ms: fpmAvg('dispatch_ms'), source: 'fpm' },
    { id: '14_html_download', label: 'HTML download after first byte', wall_ms: b.download_ms, source: 'browser' },
    { id: 'browser_ttfb_total', label: 'Browser TTFB (to responseStart)', wall_ms: browserTtfb, source: 'browser' },
    { id: 'server_loopback_ttfb', label: 'Server loopback curl TTFB', wall_ms: serverTtfb != null ? Math.round(serverTtfb * 1000) : null, source: 'curl' },
    { id: 'fpm_internal_total', label: 'FPM probe internal PHP wall', wall_ms: fpmInternal, source: 'fpm' },
  ];

  const measurable = stages.filter((s) => s.wall_ms != null && s.wall_ms > 0 && !['browser_ttfb_total', 'server_loopback_ttfb', 'fpm_internal_total'].includes(s.id));
  measurable.sort((a, b2) => (b2.wall_ms || 0) - (a.wall_ms || 0));
  const refWall = browserTtfb || serverTtfb * 1000 || 1;
  report.stages = stages.map((s) => ({
    ...s,
    pct: s.wall_ms != null ? Math.round((1000 * s.wall_ms) / refWall) / 10 : null,
    calls: 1,
    self_ms: s.wall_ms,
  }));
  report.top_20 = measurable.slice(0, 20).map((s, i) => ({
    rank: i + 1,
    ...s,
    pct: Math.round((1000 * s.wall_ms) / refWall) / 10,
    self_ms: s.wall_ms,
    calls: 1,
  }));

  // Single biggest: prefer explained server delay
  const browserMinusServer =
    browserTtfb != null && serverTtfb != null ? Math.round(browserTtfb - serverTtfb * 1000) : null;
  const serverMinusFpm =
    serverTtfb != null && fpmInternal != null ? Math.round(serverTtfb * 1000 - fpmInternal) : null;

  let biggest;
  if (browserMinusServer != null && browserMinusServer > (serverMinusFpm || 0) && browserMinusServer > (fpmInternal || 0)) {
    biggest = {
      layer: 'client_to_edge_network',
      file: 'N/A (network path Browser → Internet → rateb.sa:443)',
      class: null,
      function: 'DNS+TCP+TLS+WAN RTT before requestStart / server processing',
      line: null,
      wall_ms: browserMinusServer,
      self_ms: browserMinusServer,
      calls: 1,
      percentage: Math.round((1000 * browserMinusServer) / refWall) / 10,
      evidence: {
        browser_ttfb_ms: browserTtfb,
        server_loopback_ttfb_ms: Math.round(serverTtfb * 1000),
        gap_ms: browserMinusServer,
      },
    };
  } else if (serverMinusFpm != null && serverMinusFpm > (fpmInternal || 0)) {
    biggest = {
      layer: 'apache_fastcgi_fpm_queue',
      file: 'PHP-FPM / Apache FastCGI path (infra)',
      class: null,
      function: 'queue wait / FastCGI accept / worker handoff',
      line: null,
      wall_ms: serverMinusFpm,
      self_ms: serverMinusFpm,
      calls: 1,
      percentage: Math.round((1000 * serverMinusFpm) / refWall) / 10,
      evidence: {
        loopback_ttfb_ms: Math.round(serverTtfb * 1000),
        fpm_internal_ms: fpmInternal,
        gap_ms: serverMinusFpm,
      },
    };
  } else {
    const top = report.top_20[0];
    biggest = {
      layer: 'php_application',
      file: top?.id === '7_bootstrap_init' ? 'rateb-erp/app/Core/Bootstrap.php' : 'see stage',
      class: top?.id === '7_bootstrap_init' ? 'Rateb\\App\\Core\\Bootstrap' : null,
      function: top?.label || 'unknown',
      line: top?.id === '7_bootstrap_init' ? 10 : null,
      wall_ms: top?.wall_ms,
      self_ms: top?.wall_ms,
      calls: 1,
      percentage: top?.pct,
    };
  }

  // If OPcache disabled is the root of high bootstrap within FPM, note it but bottleneck selection stays evidence-driven
  const opc = fpmBodies[0]?.opcache;
  report.opcache = opc;
  report.single_biggest_bottleneck = biggest;
  report.before_after = {
    current_browser_ttfb_ms: browserTtfb,
    if_bottleneck_removed_ms:
      biggest.wall_ms != null ? Math.max(50, Math.round(browserTtfb - biggest.wall_ms)) : null,
    improvement_pct:
      biggest.wall_ms != null && browserTtfb
        ? Math.min(99, Math.round((100 * biggest.wall_ms) / browserTtfb))
        : null,
  };

  const out = path.join(OUT_DIR, `phase-pb-http-path-${STAMP}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'phase-pb-http-path-latest.json'), JSON.stringify(report, null, 2));
  console.log(
    JSON.stringify(
      {
        out,
        browser_avg: report.browser_avg,
        server_loopback_ttfb_ms: Math.round((loop.ttfb || 0) * 1000),
        fpm_internal_ms: fpmInternal,
        biggest: report.single_biggest_bottleneck,
        top5: report.top_20.slice(0, 5),
      },
      null,
      2
    )
  );
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
