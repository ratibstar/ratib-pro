/**
 * Real browser boot benchmark — Playwright + Lighthouse (Chrome).
 * Measures Navigation Start, FP, FCP, LCP, DCL, TTI, TBT, Speed Index, CLS.
 *
 * Usage:
 *   node run-bench.js --url http://127.0.0.1:8765/after-shell.html --label after
 *   node run-bench.js --url http://127.0.0.1:8766/before-shell.html --label before
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const lighthouseMod = require('lighthouse');
const lighthouse = lighthouseMod.default || lighthouseMod;

function arg(name, fallback = null) {
  const i = process.argv.indexOf('--' + name);
  if (i >= 0 && process.argv[i + 1]) return process.argv[i + 1];
  return fallback;
}

async function measureWithPlaywright(url, label) {
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage'],
  });
  const context = await browser.newContext({ viewport: { width: 1365, height: 900 } });
  const page = await context.newPage();
  await page.addInitScript(() => {
    performance.mark('rateb-bench-nav-intent');
  });

  const started = Date.now();
  const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 120000 });
  const status = response ? response.status() : 0;

  // Collect paint + navigation + LCP via Performance APIs (real browser).
  const perf = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = p.startTime;
    });
    let lcp = null;
    try {
      const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
      if (lcpEntries && lcpEntries.length) {
        lcp = lcpEntries[lcpEntries.length - 1].startTime;
      }
    } catch (e) {}
    let cls = 0;
    try {
      performance.getEntriesByType('layout-shift').forEach((e) => {
        if (!e.hadRecentInput) cls += e.value;
      });
    } catch (e2) {}
    return {
      navigationStart: 0,
      responseStart: nav.responseStart || null,
      domContentLoadedEventEnd: nav.domContentLoadedEventEnd || null,
      loadEventEnd: nav.loadEventEnd || null,
      firstPaint: paints['first-paint'] ?? null,
      firstContentfulPaint: paints['first-contentful-paint'] ?? null,
      largestContentfulPaint: lcp,
      cumulativeLayoutShift: cls,
      transferSize: nav.transferSize || null,
      encodedBodySize: nav.encodedBodySize || null,
      ttfb: nav.responseStart || null,
      bootMarks: window.__RATEB_BOOT__ || null,
    };
  });

  // Wait briefly for late LCP / layout shifts.
  await new Promise((r) => setTimeout(r, 2000));
  const perf2 = await page.evaluate(() => {
    let lcp = null;
    const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
    if (lcpEntries && lcpEntries.length) {
      lcp = lcpEntries[lcpEntries.length - 1].startTime;
    }
    let cls = 0;
    performance.getEntriesByType('layout-shift').forEach((e) => {
      if (!e.hadRecentInput) cls += e.value;
    });
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => {
      paints[p.name] = p.startTime;
    });
    const nav = performance.getEntriesByType('navigation')[0] || {};
    return {
      firstPaint: paints['first-paint'] ?? null,
      firstContentfulPaint: paints['first-contentful-paint'] ?? null,
      largestContentfulPaint: lcp,
      cumulativeLayoutShift: cls,
      domContentLoadedEventEnd: nav.domContentLoadedEventEnd || null,
      loadEventEnd: nav.loadEventEnd || null,
      ttfb: nav.responseStart || null,
    };
  });

  await browser.close();
  return {
    label,
    url,
    httpStatus: status,
    wallClockMs: Date.now() - started,
    playwright: { ...perf, ...perf2 },
  };
}

async function measureWithLighthouse(url, label) {
  // Lighthouse drives its own Chrome; collect TTI, TBT, SI, FCP, LCP, CLS.
  const chromeLauncher = await import('chrome-launcher');
  const userDataDir = path.join(__dirname, '.chrome-user-data', label + '-' + Date.now());
  fs.mkdirSync(userDataDir, { recursive: true });
  const chrome = await chromeLauncher.launch({
    chromePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    chromeFlags: [
      '--headless=new',
      '--disable-gpu',
      '--no-sandbox',
      '--disable-dev-shm-usage',
      '--allow-insecure-localhost',
    ],
    userDataDir,
  });
  let result;
  try {
    result = await lighthouse(url, {
      port: chrome.port,
      output: 'json',
      logLevel: 'error',
      onlyCategories: ['performance'],
      formFactor: 'desktop',
      screenEmulation: { disabled: true },
      throttlingMethod: 'provided', // no network throttle — real local timings
    });
  } finally {
    try {
      await chrome.kill();
    } catch (e) {
      console.error('[bench] chrome.kill ignored:', e && e.message ? e.message : e);
    }
  }
  const a = result.lhr.audits;
  const pick = (id) => {
    const x = a[id];
    if (!x) return null;
    return {
      id,
      numericValue: x.numericValue ?? null,
      displayValue: x.displayValue ?? null,
      score: x.score ?? null,
    };
  };
  return {
    label,
    url,
    lighthouseVersion: result.lhr.lighthouseVersion,
    fetchTime: result.lhr.fetchTime,
    performanceScore: result.lhr.categories.performance?.score ?? null,
    metrics: {
      firstContentfulPaint: pick('first-contentful-paint'),
      largestContentfulPaint: pick('largest-contentful-paint'),
      speedIndex: pick('speed-index'),
      totalBlockingTime: pick('total-blocking-time'),
      cumulativeLayoutShift: pick('cumulative-layout-shift'),
      interactive: pick('interactive'), // TTI
      maxPotentialFid: pick('max-potential-fid'),
      serverResponseTime: pick('server-response-time'),
      observedNavigationStart: a['metrics']?.details?.items?.[0] || null,
    },
    rawMetricsItem: a['metrics']?.details?.items?.[0] || null,
    lhrPath: null,
  };
}

async function main() {
  const url = arg('url');
  const label = arg('label', 'run');
  const outDir = arg('out', path.join(__dirname, 'reports'));
  if (!url) {
    console.error('Missing --url');
    process.exit(2);
  }
  fs.mkdirSync(outDir, { recursive: true });

  console.error(`[bench] Playwright measuring ${label} ${url}`);
  const pw = await measureWithPlaywright(url, label);
  console.error(`[bench] Lighthouse measuring ${label} ${url}`);
  const lh = await measureWithLighthouse(url, label);

  const report = {
    generatedAt: new Date().toISOString(),
    label,
    url,
    method: 'Playwright Chromium/Chrome Performance API + Lighthouse (throttlingMethod=provided)',
    playwright: pw,
    lighthouse: lh,
    summaryMs: {
      navigationStart: 0,
      firstPaint: pw.playwright.firstPaint,
      firstContentfulPaint:
        lh.metrics.firstContentfulPaint?.numericValue ?? pw.playwright.firstContentfulPaint,
      largestContentfulPaint:
        lh.metrics.largestContentfulPaint?.numericValue ?? pw.playwright.largestContentfulPaint,
      domContentLoaded: pw.playwright.domContentLoadedEventEnd,
      timeToInteractive: lh.metrics.interactive?.numericValue ?? null,
      totalBlockingTime: lh.metrics.totalBlockingTime?.numericValue ?? null,
      speedIndex: lh.metrics.speedIndex?.numericValue ?? null,
      cumulativeLayoutShift:
        lh.metrics.cumulativeLayoutShift?.numericValue ?? pw.playwright.cumulativeLayoutShift,
      ttfb: pw.playwright.ttfb,
      loadEventEnd: pw.playwright.loadEventEnd,
    },
  };

  const outFile = path.join(outDir, `${label}-${Date.now()}.json`);
  fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ outFile, summaryMs: report.summaryMs }, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
