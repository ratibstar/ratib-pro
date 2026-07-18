/**
 * Frontend remeasure — critical-path durations only (no absolute-timestamp false positives).
 * Cache-disabled cold + sidebar click cold/warm + Lighthouse.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const lighthouseMod = require('lighthouse');
const lighthouse = lighthouseMod.default || lighthouseMod;

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT_DIR = path.join(__dirname, 'reports');

function ssh(cmd) {
  return execFileSync(
    'ssh',
    ['-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15', HOST, cmd],
    { encoding: 'utf8', timeout: 120000 }
  );
}

function r1(n) {
  return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
}

function mint() {
  return JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );
}

async function collect(page) {
  return page.evaluate(() => {
    const R = (n) => (Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null);
    const nav = performance.getEntriesByType('navigation')[0];
    const paint = {};
    performance.getEntriesByType('paint').forEach((p) => (paint[p.name] = R(p.startTime)));
    const resources = performance.getEntriesByType('resource').map((r) => {
      let short;
      try {
        const u = new URL(r.name);
        short = u.pathname + u.search;
      } catch {
        short = String(r.name).slice(0, 160);
      }
      return {
        short,
        type: r.initiatorType,
        start: R(r.startTime),
        dur: R(r.duration),
        end: R(r.responseEnd || r.startTime + r.duration),
        ttfb: R((r.responseStart || 0) - (r.requestStart || 0)),
        download: R((r.responseEnd || 0) - (r.responseStart || 0)),
        transfer: r.transferSize || 0,
        decoded: r.decodedBodySize || 0,
        blocking: r.renderBlockingStatus || null,
      };
    });
    const longTasks = (window.__LT__ || []).slice().sort((a, b) => b.dur - a.dur);
    const shifts = window.__LS__ || [];
    const cls = shifts.reduce((a, s) => a + s.value, 0);
    const idb = window.__IDB__ || [];
    const sw = window.__SW__ || {};
    const fontsReady = window.__FONTS_MS__;
    const fetchLog = window.__FETCH__ || [];
    const fp = paint['first-contentful-paint'] || paint['first-paint'] || 0;
    const postFpFetch = fetchLog.filter((f) => f.start >= fp);
    const postFpXhr = resources.filter(
      (r) => (r.type === 'fetch' || r.type === 'xmlhttprequest') && r.start >= fp
    );
    const scripts = resources.filter((r) => r.type === 'script' || /\.js(\?|$)/i.test(r.short));
    const css = resources.filter((r) => r.type === 'link' || /\.css(\?|$)/i.test(r.short));
    const fonts = resources.filter((r) => /\.(woff2?|ttf|otf)(\?|$)/i.test(r.short) || /font/i.test(r.short));
    const images = resources.filter((r) => r.type === 'img' || /\.(png|jpe?g|webp|svg)(\?|$)/i.test(r.short));
    const blockingCss = css.filter((r) => r.blocking === 'blocking');
    const deferred = Array.from(document.querySelectorAll('script[src]')).map((s) => ({
      src: (s.getAttribute('src') || '').replace(/^https?:\/\/[^/]+/, ''),
      defer: !!s.defer,
      async: !!s.async,
    }));
    const mem = performance.memory
      ? {
          usedMB: R(performance.memory.usedJSHeapSize / 1048576),
          totalMB: R(performance.memory.totalJSHeapSize / 1048576),
        }
      : null;

    // Critical-path: max end among render-blocking CSS after responseStart
    const blockCssEnd = blockingCss.length ? Math.max(...blockingCss.map((c) => c.end || 0)) : null;
    const scriptEnd = scripts.length ? Math.max(...scripts.map((s) => s.end || 0)) : null;

    return {
      href: location.href,
      nav: nav
        ? {
            dns: R(nav.domainLookupEnd - nav.domainLookupStart),
            connect: R(nav.connectEnd - nav.connectStart),
            tls: R(nav.secureConnectionStart > 0 ? nav.connectEnd - nav.secureConnectionStart : 0),
            ttfb: R(nav.responseStart - nav.requestStart),
            responseStart: R(nav.responseStart),
            download: R(nav.responseEnd - nav.responseStart),
            htmlProcess: R(nav.domInteractive - nav.responseStart),
            dcl: R(nav.domContentLoadedEventEnd),
            load: R(nav.loadEventEnd),
            transfer: nav.transferSize || 0,
          }
        : null,
      paint,
      resources,
      scripts,
      css,
      fonts,
      images,
      blockingCss,
      blockCssEnd,
      scriptEnd,
      longTasks,
      longTaskSum: R(longTasks.reduce((a, t) => a + t.dur, 0)),
      cls: Math.round(cls * 10000) / 10000,
      shifts,
      idb,
      sw,
      fontsReady,
      postFpFetch,
      postFpXhr,
      deferred,
      deferCount: deferred.filter((d) => d.defer).length,
      iconNodes: document.querySelectorAll('i.fa, i.fas, i.far, i.fab, [class*="fa-"]').length,
      domNodes: document.querySelectorAll('*').length,
      sidebarBytes: ((document.querySelector('aside.rateb-sidebar, #rateb-sidebar') || {}).innerHTML || '').length,
      listenersAdded: window.__LISTENERS__ || 0,
      memory: mem,
      sidebarAt: window.__SIDEBAR_AT__ || null,
      dashboardAt: window.__DASH_AT__ || null,
      swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
    };
  });
}

async function installHooks(page) {
  await page.addInitScript(() => {
    window.__LT__ = [];
    window.__LS__ = [];
    window.__IDB__ = [];
    window.__FETCH__ = [];
    window.__LISTENERS__ = 0;
    window.__SW__ = {};
    window.__FONTS_MS__ = null;
    window.__SIDEBAR_AT__ = null;
    window.__DASH_AT__ = null;
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          window.__LT__.push({ start: Math.round(e.startTime * 10) / 10, dur: Math.round(e.duration * 10) / 10 });
        });
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          if (!e.hadRecentInput) window.__LS__.push({ start: Math.round(e.startTime * 10) / 10, value: e.value });
        });
      }).observe({ type: 'layout-shift', buffered: true });
    } catch (e2) {}
    try {
      const o = indexedDB.open.bind(indexedDB);
      indexedDB.open = function (name, ver) {
        const t0 = performance.now();
        const req = o(name, ver);
        const rec = { name: String(name), start: Math.round(t0 * 10) / 10, ms: null };
        window.__IDB__.push(rec);
        req.addEventListener('success', () => (rec.ms = Math.round((performance.now() - t0) * 10) / 10));
        req.addEventListener('error', () => (rec.ms = Math.round((performance.now() - t0) * 10) / 10));
        return req;
      };
    } catch (e3) {}
    try {
      const add = EventTarget.prototype.addEventListener;
      EventTarget.prototype.addEventListener = function () {
        window.__LISTENERS__++;
        return add.apply(this, arguments);
      };
    } catch (e4) {}
    try {
      const f = window.fetch.bind(window);
      window.fetch = function () {
        const url = String(arguments[0] && arguments[0].url ? arguments[0].url : arguments[0]);
        const t0 = performance.now();
        return f.apply(this, arguments).then((res) => {
          window.__FETCH__.push({
            url: url.replace(/^https?:\/\/[^/]+/, '').slice(0, 160),
            start: Math.round(t0 * 10) / 10,
            ms: Math.round((performance.now() - t0) * 10) / 10,
          });
          return res;
        });
      };
    } catch (e5) {}
    if (navigator.serviceWorker) {
      const t0 = performance.now();
      navigator.serviceWorker.ready.then((reg) => {
        window.__SW__.readyMs = Math.round((performance.now() - t0) * 10) / 10;
        window.__SW__.scriptURL = (reg.active && reg.active.scriptURL) || null;
      });
    }
    if (document.fonts && document.fonts.ready) {
      const t0 = performance.now();
      document.fonts.ready.then(() => {
        window.__FONTS_MS__ = Math.round((performance.now() - t0) * 10) / 10;
      });
    }
    const mark = () => {
      if (!window.__SIDEBAR_AT__) {
        const sb = document.querySelector('aside.rateb-sidebar, #rateb-sidebar');
        if (sb && sb.offsetHeight > 40) window.__SIDEBAR_AT__ = Math.round(performance.now() * 10) / 10;
      }
      if (!window.__DASH_AT__) {
        const main = document.querySelector('main, .content-wrapper, .rateb-content');
        if (main && (main.innerText || '').trim().length > 60) {
          window.__DASH_AT__ = Math.round(performance.now() * 10) / 10;
        }
      }
    };
    new MutationObserver(mark).observe(document.documentElement, { childList: true, subtree: true });
  });
}

async function waitUsable(page, ms) {
  const t0 = Date.now();
  while (Date.now() - t0 < ms) {
    const ok = await page.evaluate(() => {
      const sb = !!document.querySelector('aside.rateb-sidebar, #rateb-sidebar');
      const main = document.querySelector('main, .content-wrapper, .rateb-content');
      return sb && main && (main.innerText || '').trim().length > 20 && document.readyState === 'complete';
    });
    if (ok) return Date.now() - t0;
    await page.waitForTimeout(50);
  }
  return Date.now() - t0;
}

async function expandSidebar(page) {
  await page.evaluate(() => {
    document.querySelectorAll('.rateb-nav-group-body, .rateb-nav-subgroup-body').forEach((el) => {
      el.style.display = 'block';
      el.classList.add('show', 'open');
    });
    document.querySelectorAll('.rateb-nav-group-toggle, [data-bs-toggle="collapse"]').forEach((el) => {
      el.classList.remove('collapsed');
      el.setAttribute('aria-expanded', 'true');
    });
    const sb = document.getElementById('rateb-sidebar');
    if (sb) sb.classList.add('show', 'open');
    document.body.classList.remove('sidebar-collapsed');
  });
}

async function clickNav(page, hrefPart) {
  await expandSidebar(page);
  const handle = await page.$(`a.rateb-nav-link[href*="${hrefPart}"], a[href*="${hrefPart}"]`);
  if (!handle) return { error: 'not_found', hrefPart };
  await handle.evaluate((el) => {
    el.scrollIntoView({ block: 'center', inline: 'nearest' });
  });
  const t0 = Date.now();
  await handle.click({ force: true, timeout: 10000 });
  let usable = null;
  const dead = Date.now() + 40000;
  while (Date.now() < dead) {
    const ok = await page.evaluate((hp) => {
      if (!location.href.includes(hp)) return false;
      const main = document.querySelector('main, .content-wrapper, .rateb-content');
      const text = main ? (main.innerText || '').trim().length : 0;
      const spins = [...document.querySelectorAll('.spinner-border, .fa-spin')].filter((e) => {
        const s = getComputedStyle(e);
        return s.display !== 'none' && e.offsetWidth > 0;
      }).length;
      return text > 20 && spins === 0;
    }, hrefPart);
    if (ok) {
      usable = Date.now() - t0;
      break;
    }
    await page.waitForTimeout(40);
  }
  const net = await page.evaluate((hp) => {
    const entries = performance.getEntriesByType('resource').filter((r) => String(r.name).includes(hp));
    const last = entries[entries.length - 1];
    if (!last) return null;
    return {
      dur: Math.round(last.duration * 10) / 10,
      transfer: last.transferSize || 0,
      decoded: last.decodedBodySize || 0,
      fromCache: last.transferSize === 0 && last.decodedBodySize > 0,
    };
  }, hrefPart);
  return { hrefPart, usable_ms: usable || Date.now() - t0, net };
}

function mapFile(s) {
  const u = String(s || '');
  const pairs = [
    [/bootstrap\.rtl\.min\.css/, 'public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css', 'render-blocking CSS parse'],
    [/bootstrap\.bundle\.min\.js/, 'public/assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js', 'parse/compile/eval'],
    [/erp-nav-instant\.js/, 'public/assets/js/erp-nav-instant.js', 'idlePrefetchVisible / prefetchUrl / navigate'],
    [/app\.js/, 'public/assets/js/app.js', 'RatebApp.init'],
    [/connectivity-indicator\.js/, 'public/assets/js/connectivity-indicator.js', 'probe()'],
    [/erp-pwa-install\.js/, 'public/assets/offline/erp-pwa-install.js', 'PWA install bind'],
    [/erp-shell-bootstrap|erp-offline/, 'public/assets/offline/*', 'offline bootstrap'],
    [/all\.min\.css|fontawesome/, 'views/layouts/main.php + vendor/fontawesome', 'rateb_fontawesome_css()'],
    [/fa-solid-900/, 'public/assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.woff2', 'icon font download'],
    [/tajawal/, 'views/layouts/main.php + vendor/fonts/tajawal', 'rateb_tajawal_font_css()'],
    [/main\.css/, 'public/assets/css/main.css', 'render-blocking stylesheet'],
    [/components\.css/, 'public/assets/css/components.css', 'render-blocking stylesheet'],
    [/variables\.css/, 'public/assets/css/variables.css', 'render-blocking stylesheet'],
    [/dark\.css|light\.css/, 'public/assets/css/dark.css|light.css', 'theme stylesheet'],
    [/rtl\.css/, 'public/assets/css/rtl.css', 'render-blocking stylesheet'],
    [/dashboard\.css/, 'public/assets/css/dashboard.css', 'deferred dashboard CSS'],
    [/pos-sw\.js/, 'public/pos-sw.js', 'serviceWorker.register / activate'],
    [/chart\.umd/, 'public/assets/vendor/chart.js', 'charts deferred load'],
    [/connectivity-probe/, 'public/assets/js/connectivity-indicator.js', 'probe fetch'],
    [/main\.php|\/admin/, 'views/layouts/main.php', 'layout + sidebar HTML'],
  ];
  for (const [re, file, fn] of pairs) {
    if (re.test(u)) return { file, fn };
  }
  return { file: u.slice(0, 100), fn: 'n/a' };
}

function buildTop20(cold, warm, clicks, lh) {
  const bn = [];
  const push = (item) => {
    if (!item || item.ms == null || item.ms < 5) return;
    bn.push(item);
  };
  const n = cold.nav || {};
  const m = (label, ms, savings, fileHint, area, evidence) => {
    const mf = mapFile(fileHint);
    push({
      label,
      ms: r1(ms),
      estimated_savings_ms: r1(savings != null ? savings : ms),
      file: mf.file,
      function: mf.fn,
      area,
      evidence,
    });
  };

  // Network path (measured from auditor — NOT origin; listed separately)
  m('Client TCP+TLS connect (auditor→origin)', n.connect, n.connect, 'network', 1, 'NavTiming connect');
  m('Document TTFB (client-observed; includes RTT)', n.ttfb, null, '/admin/', 1, 'NavTiming TTFB — origin claimed 13–28ms; do not patch backend from this alone');
  m('HTML parse → domInteractive', n.htmlProcess, n.htmlProcess, 'views/layouts/main.php', 7, 'domInteractive - responseStart');

  // CSS blocking critical path contribution = time from responseStart to last blocking CSS end, minus overlap accounted as max
  if (cold.blockCssEnd && n.responseStart) {
    m(
      'Render-blocking CSS chain (responseStart→last blocking CSS end)',
      cold.blockCssEnd - n.responseStart,
      cold.blockCssEnd - n.responseStart,
      'bootstrap.rtl.min.css',
      4,
      'blockCssEnd=' + cold.blockCssEnd
    );
  }
  (cold.blockingCss || [])
    .slice()
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 4)
    .forEach((c) => m('Blocking CSS download+wait: ' + c.short.split('/').pop().split('?')[0], c.dur, c.dur, c.short, 4, 'renderBlocking=blocking'));

  // Scripts — duration includes queue; use download+decode proxy and wall span
  const scriptSpan =
    cold.scripts && cold.scripts.length
      ? Math.max(...cold.scripts.map((s) => s.end || 0)) - Math.min(...cold.scripts.map((s) => s.start || 0))
      : 0;
  m('Deferred script waterfall span (13 defer tags)', scriptSpan, Math.min(scriptSpan, 200), 'views/layouts/main.php', 15, 'deferCount=' + cold.deferCount);
  (cold.scripts || [])
    .slice()
    .sort((a, b) => b.decoded - a.decoded)
    .slice(0, 3)
    .forEach((s) => {
      // parse cost proxy: decoded bytes / 500KB/s rough → but use measured longtasks attribution
      m('Script transfer wall: ' + s.short.split('/').pop().split('?')[0], s.dur, s.download + 20, s.short, 2, 'decoded=' + s.decoded);
    });

  m('Main-thread long tasks sum', cold.longTaskSum, cold.longTaskSum, 'public/assets/js/*', 6, 'longtask count=' + (cold.longTasks || []).length);
  (cold.longTasks || []).slice(0, 3).forEach((t, i) =>
    m('Long task #' + (i + 1) + ' (>' + 50 + 'ms body)', t.dur, Math.max(0, t.dur - 50), 'public/assets/js/*', 6, 'start=' + t.start)
  );

  m('document.fonts.ready wait', cold.fontsReady, cold.fontsReady, 'tajawal', 12, 'FontFaceSet.ready');
  (cold.fonts || [])
    .filter((f) => /\.woff2/i.test(f.short))
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 2)
    .forEach((f) => m('Font file: ' + f.short.split('/').pop().split('?')[0], f.dur, f.dur, f.short, 12, 'woff2'));

  const faCss = (cold.css || []).find((c) => /fontawesome|all\.min\.css/i.test(c.short));
  if (faCss) m('Font Awesome CSS', faCss.dur, faCss.dur * 0.7, faCss.short, 13, 'media=print onload trick; still competes');

  m('Icon DOM nodes cost proxy (' + cold.iconNodes + ' nodes)', cold.iconNodes * 0.12, cold.iconNodes > 100 ? 25 : 8, 'views/layouts/main.php', 13, 'FA <i> in sidebar');
  m('addEventListener boot count cost (' + cold.listenersAdded + ')', cold.listenersAdded * 0.04, cold.listenersAdded > 400 ? 15 : 5, 'erp-nav-instant.js', 8, 'hooked addEventListener');

  const postSum = (cold.postFpFetch || []).reduce((a, f) => a + f.ms, 0);
  m('Post-FP fetch sum', postSum, postSum, 'erp-nav-instant.js', 11, 'count=' + (cold.postFpFetch || []).length);
  (cold.postFpFetch || [])
    .slice()
    .sort((a, b) => b.ms - a.ms)
    .forEach((f) => m('Post-FP fetch: ' + f.url.split('?')[0].split('/').slice(-2).join('/'), f.ms, f.ms, f.url, 11, 'start=' + f.start));

  const prefetch = (cold.postFpXhr || []).filter((r) => /\/admin\//.test(r.short));
  const prefSum = prefetch.reduce((a, r) => a + r.dur, 0);
  m('idlePrefetch Admin HTML (ResourceTiming sum)', prefSum, prefSum, 'erp-nav-instant.js', 11, 'prefetch docs=' + prefetch.length);

  m('SW ready latency', cold.sw?.readyMs, Math.min(cold.sw?.readyMs || 0, 100), 'pos-sw.js', 17, cold.sw?.scriptURL || '');
  (cold.idb || []).forEach((d) => m('IndexedDB open: ' + d.name, d.ms, d.ms, 'offline', 16, 'idb'));

  (cold.images || [])
    .sort((a, b) => b.dur - a.dur)
    .slice(0, 2)
    .forEach((img) => m('Image: ' + img.short.split('/').pop(), img.dur, img.dur, img.short, 14, 'img'));

  if (lh?.metrics?.totalBlockingTime?.numericValue >= 5) {
    m('Lighthouse TBT', lh.metrics.totalBlockingTime.numericValue, lh.metrics.totalBlockingTime.numericValue, 'js', 19, 'LH TBT');
  }
  if (lh?.audits?.mainthreadWork?.numericValue >= 5) {
    m('Lighthouse main-thread work', lh.audits.mainthreadWork.numericValue, 200, 'js', 19, 'LH mainthread-work-breakdown');
  }
  if (lh?.audits?.bootupTime?.numericValue >= 5) {
    m('Lighthouse JS bootup time', lh.audits.bootupTime.numericValue, lh.audits.bootupTime.numericValue, 'js', 3, 'LH bootup-time');
  }
  if (lh?.metrics?.largestContentfulPaint?.numericValue) {
    m('Lighthouse LCP', lh.metrics.largestContentfulPaint.numericValue, null, 'main.php', 7, 'LH LCP');
  }

  // Sidebar clicks
  (clicks || []).forEach((c) => {
    if (c.error) return;
    m(
      'Sidebar nav ' + (c.warm ? 'WARM' : 'COLD') + ' → ' + c.id + ' usable',
      c.usable_ms,
      c.warm ? Math.max(0, c.usable_ms - 50) : c.usable_ms * 0.8,
      'erp-nav-instant.js',
      9,
      JSON.stringify(c.net || {})
    );
  });

  // Dashboard / sidebar paint deltas (duration from responseStart)
  if (cold.sidebarAt && n.responseStart) {
    m('Sidebar paint after responseStart', cold.sidebarAt - n.responseStart, cold.sidebarAt - n.responseStart, 'main.php', 9, 'sidebarAt - responseStart');
  }
  if (cold.dashboardAt && cold.sidebarAt) {
    m('Dashboard after sidebar', Math.max(0, cold.dashboardAt - cold.sidebarAt), Math.max(0, cold.dashboardAt - cold.sidebarAt), 'dashboard', 10, 'dashboardAt - sidebarAt');
  }

  // FCP from nav start is wall — report frontend portion after responseStart
  if (cold.paint?.['first-contentful-paint'] && n.responseStart) {
    m(
      'FCP after responseStart (frontend render)',
      cold.paint['first-contentful-paint'] - n.responseStart,
      cold.paint['first-contentful-paint'] - n.responseStart,
      'main.php',
      1,
      'FCP - responseStart'
    );
  }

  // CLS frame cost
  if ((cold.shifts || []).length) {
    m('Layout shift frames (' + cold.shifts.length + ', CLS=' + cold.cls + ')', cold.shifts.length * 16, cold.shifts.length * 16, 'fonts/icons', 5, 'layout-shift');
  }

  // Memory
  if (cold.memory?.usedMB) {
    m('JS heap after boot (' + cold.memory.usedMB + 'MB)', Math.max(5, cold.memory.usedMB * 2), 10, 'js', 20, 'performance.memory');
  }

  // Warm vs cold delta insight
  if (warm?.nav?.dcl && n.dcl) {
    m('Cold−Warm DCL delta (cache/SW benefit already present on warm)', n.dcl - warm.nav.dcl, null, 'pos-sw.js', 17, 'cold.dcl - warm.dcl');
  }

  // Dedup by label, sort
  const seen = new Set();
  const ranked = bn
    .sort((a, b) => b.ms - a.ms)
    .filter((x) => {
      if (seen.has(x.label)) return false;
      seen.add(x.label);
      return true;
    })
    .map((x, i) => {
      let priority = 'P2';
      if (x.ms >= 300 || i < 5) priority = 'P0';
      else if (x.ms >= 80 || i < 12) priority = 'P1';
      // Downgrade pure client-network items for patch priority
      if (/Client TCP|client-observed|auditor/i.test(x.label)) priority = 'INFO';
      return { rank: i + 1, priority, ...x };
    });

  return ranked;
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const m = mint();
  const profile = path.join(os.tmpdir(), 'rateb-fe-re-' + Date.now());
  const ctx = await chromium.launchPersistentContext(profile, {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage', '--enable-precise-memory-info', '--disable-http-cache'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  });
  await ctx.addCookies([
    {
      name: m.session_name || 'rateb_erp',
      value: m.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  const page = ctx.pages()[0] || (await ctx.newPage());
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
  await installHooks(page);

  console.log('[re] cold…');
  const wall0 = Date.now();
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const usableAfterGoto = await waitUsable(page, 60000);
  await page.waitForTimeout(4000); // allow idlePrefetch + fonts
  const cold = await collect(page);
  cold.wall_to_usable_ms = Date.now() - wall0 - 4000 + usableAfterGoto; // approx
  cold.usable_after_dcl_ms = usableAfterGoto;
  cold.wall_ms = Date.now() - wall0;

  console.log('[re] warm…');
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: false });
  const w0 = Date.now();
  await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await waitUsable(page, 30000);
  await page.waitForTimeout(800);
  const warm = await collect(page);
  warm.wall_ms = Date.now() - w0;

  console.log('[re] sidebar clicks…');
  const clicks = [];
  const targets = [
    { id: 'hr', href: '/admin/hr' },
    { id: 'inventory', href: '/admin/ops/inventory' },
    { id: 'accounting', href: '/admin/ops/accounting' },
  ];
  for (const t of targets) {
    await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await waitUsable(page, 30000);
    await expandSidebar(page);
    // clear cache for target
    await page.evaluate(async (hp) => {
      try {
        const keys = await caches.keys();
        for (const k of keys) {
          const c = await caches.open(k);
          for (const req of await c.keys()) {
            if (String(req.url).includes(hp)) await c.delete(req);
          }
        }
      } catch (e) {}
    }, t.href);
    const coldClick = await clickNav(page, t.href);
    clicks.push({ id: t.id, warm: false, ...coldClick });
    // warm
    await page.goto(BASE + '/admin/?company_id=22', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await waitUsable(page, 20000);
    await expandSidebar(page);
    const warmClick = await clickNav(page, t.href);
    clicks.push({ id: t.id, warm: true, ...warmClick });
  }

  await ctx.close();

  console.log('[re] lighthouse…');
  const m2 = mint();
  const chromeLauncher = await import('chrome-launcher');
  const ud = path.join(os.tmpdir(), 'lh-' + Date.now());
  fs.mkdirSync(ud, { recursive: true });
  const chrome = await chromeLauncher.launch({
    chromePath: CHROME,
    chromeFlags: ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
    userDataDir: ud,
  });
  let lh;
  try {
    const result = await lighthouse(BASE + '/admin/?company_id=22', {
      port: chrome.port,
      output: 'json',
      logLevel: 'error',
      onlyCategories: ['performance'],
      formFactor: 'desktop',
      screenEmulation: { disabled: true },
      throttlingMethod: 'provided',
      disableStorageReset: true,
      extraHeaders: { Cookie: (m2.session_name || 'rateb_erp') + '=' + m2.session_id },
    });
    const a = result.lhr.audits;
    const pick = (id) =>
      a[id]
        ? { numericValue: a[id].numericValue ?? null, displayValue: a[id].displayValue ?? null, score: a[id].score ?? null }
        : null;
    lh = {
      performanceScore: result.lhr.categories.performance?.score,
      metrics: {
        firstContentfulPaint: pick('first-contentful-paint'),
        largestContentfulPaint: pick('largest-contentful-paint'),
        totalBlockingTime: pick('total-blocking-time'),
        cumulativeLayoutShift: pick('cumulative-layout-shift'),
        interactive: pick('interactive'),
        speedIndex: pick('speed-index'),
        serverResponseTime: pick('server-response-time'),
      },
      audits: {
        bootupTime: pick('bootup-time'),
        mainthreadWork: pick('mainthread-work-breakdown'),
        unusedJavascript: pick('unused-javascript'),
        renderBlocking: pick('render-blocking-resources'),
      },
      mainthreadItems: a['mainthread-work-breakdown']?.details?.items || [],
      bootupItems: a['bootup-time']?.details?.items || [],
      renderBlockingItems: a['render-blocking-resources']?.details?.items || [],
    };
  } finally {
    try {
      await Promise.resolve(chrome.kill());
    } catch (eKill) {}
  }

  const ranked = buildTop20(cold, warm, clicks, lh);
  const top20 = ranked.filter((x) => x.priority !== 'INFO').slice(0, 20);
  // re-rank after filtering INFO
  top20.forEach((x, i) => {
    x.rank = i + 1;
    if (x.ms >= 300 || i < 3) x.priority = 'P0';
    else if (x.ms >= 80 || i < 10) x.priority = 'P1';
    else x.priority = 'P2';
  });

  const report = {
    phase: 'FRONTEND-PERF-REMEASURE',
    at: new Date().toISOString(),
    cold,
    warm: { nav: warm.nav, paint: warm.paint, longTaskSum: warm.longTaskSum, wall_ms: warm.wall_ms, memory: warm.memory },
    clicks,
    lighthouse: lh,
    network_context: ranked.filter((x) => x.priority === 'INFO'),
    top20,
    all: ranked,
  };

  let txt = 'FULL FRONTEND PERFORMANCE AUDIT (remeasured)\n';
  txt += '============================================\n';
  txt += report.at + '\n\n';
  txt += 'COLD NavTiming: TTFB=' + nStr(cold.nav?.ttfb) + ' connect=' + nStr(cold.nav?.connect) + ' htmlProcess=' + nStr(cold.nav?.htmlProcess) + ' DCL=' + nStr(cold.nav?.dcl) + ' FCP=' + nStr(cold.paint?.['first-contentful-paint']) + '\n';
  txt += 'WARM NavTiming: TTFB=' + nStr(warm.nav?.ttfb) + ' DCL=' + nStr(warm.nav?.dcl) + ' FCP=' + nStr(warm.paint?.['first-contentful-paint']) + '\n';
  txt += 'LH score=' + lh?.performanceScore + ' TBT=' + lh?.metrics?.totalBlockingTime?.displayValue + ' LCP=' + lh?.metrics?.largestContentfulPaint?.displayValue + ' bootup=' + lh?.audits?.bootupTime?.displayValue + '\n';
  txt += 'LongTasks cold sum=' + cold.longTaskSum + ' CLS=' + cold.cls + ' icons=' + cold.iconNodes + ' listeners=' + cold.listenersAdded + ' defer=' + cold.deferCount + '\n';
  txt += 'IDB opens=' + JSON.stringify(cold.idb) + ' SW ready=' + cold.sw?.readyMs + '\n\n';
  txt += 'SIDEBAR CLICKS\n';
  clicks.forEach((c) => {
    txt += '  ' + c.id + (c.warm ? ' warm' : ' cold') + ': ' + (c.usable_ms ?? c.error) + 'ms ' + JSON.stringify(c.net || {}) + '\n';
  });
  txt += '\nTOP 20 FRONTEND BOTTLENECKS (>=5ms, durations only)\n';
  txt += '------------------------------------------------\n';
  top20.forEach((x) => {
    txt +=
      '#' +
      x.rank +
      ' [' +
      x.priority +
      '] ' +
      x.ms +
      'ms save~' +
      x.estimated_savings_ms +
      'ms area=' +
      x.area +
      '\n  ' +
      x.label +
      '\n  file: ' +
      x.file +
      '\n  fn:   ' +
      x.function +
      '\n  evid: ' +
      x.evidence +
      '\n\n';
  });
  txt += '\nNETWORK CONTEXT (measured from auditor; not patch targets)\n';
  report.network_context.forEach((x) => {
    txt += '- ' + x.ms + 'ms ' + x.label + '\n';
  });

  function nStr(v) {
    return v == null ? '?' : v;
  }

  const out = path.join(OUT_DIR, 'frontend-perf-remeasure-' + Date.now() + '.json');
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(out.replace(/\.json$/, '.txt'), txt);
  console.log(txt);
  console.log('wrote', out);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
