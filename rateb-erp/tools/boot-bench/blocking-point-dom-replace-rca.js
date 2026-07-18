/**
 * FINAL BLOCKING POINT RCA — evidence only.
 * What await chain blocks DOM replacement after swapTo()?
 *
 *   node blocking-point-dom-replace-rca.js
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
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const STAMP = Date.now();
const OUT_JSON = path.join(__dirname, 'reports', 'BLOCKING-POINT-DOM-REPLACE-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'BLOCKING-POINT-DOM-REPLACE-RCA.md');
const SRC = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'erp-nav-instant.js');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function r1(n) {
  if (n == null || Number.isNaN(Number(n))) return null;
  return Math.round(Number(n) * 10) / 10;
}

/** Static call graph from source (authoritative). */
function buildStaticGraph() {
  const text = fs.readFileSync(SRC, 'utf8');
  const lines = text.split(/\r?\n/);
  const find = (re) => {
    for (let i = 0; i < lines.length; i++) {
      if (re.test(lines[i])) return i + 1;
    }
    return null;
  };
  return {
    file: 'rateb-erp/public/assets/js/erp-nav-instant.js',
    nodes: [
      {
        id: 'swapTo',
        function: 'swapTo',
        line: find(/function swapTo\(/),
        code: 'return fetchHtml(href).then(function (pack) { ... curMain.innerHTML = ... })',
      },
      {
        id: 'fetchHtml',
        function: 'fetchHtml',
        line: find(/function fetchHtml\(/),
        code: 'return matchCachedHtml(href).then(...)',
      },
      {
        id: 'matchCachedHtml',
        function: 'matchCachedHtml',
        line: find(/function matchCachedHtml\(/),
        code: 'return openOpsCaches().then(... sequential cache.match ...)',
      },
      {
        id: 'openOpsCaches',
        function: 'openOpsCaches',
        line: find(/function openOpsCaches\(/),
        code: 'return caches.keys().then(... caches.open ...)',
      },
      {
        id: 'caches.keys',
        function: 'CacheStorage.keys',
        line: find(/return root\.caches\.keys\(\)/),
        await: true,
      },
      {
        id: 'caches.open',
        function: 'CacheStorage.open',
        line: find(/return root\.caches\.open\(name\)/),
        await: true,
      },
      {
        id: 'cache.match',
        function: 'Cache.match',
        line: find(/return found \|\| cache\.match\(k\)/),
        await: true,
      },
      {
        id: 'cached.text',
        function: 'Response.text (cache hit)',
        line: find(/return cached\.text\(\)\.then/),
        await: true,
      },
      {
        id: 'fetchWithTimeout',
        function: 'fetchWithTimeout',
        line: find(/function fetchWithTimeout\(/),
        code: 'Promise.race([fetch(...), timeout])',
      },
      {
        id: 'fetch',
        function: 'window.fetch',
        line: find(/var network = fetch\(url, opts\)/),
        await: true,
      },
      {
        id: 'res.text',
        function: 'Response.text (network)',
        line: find(/return res\.text\(\)\.then\(function \(html\) \{/),
        await: true,
        note: 'network miss path inside fetchHtml',
      },
      {
        id: 'DOM_REPLACE',
        function: 'swapTo.then (no replaceMainContent — inline)',
        line: find(/curMain\.innerHTML = nextMain\.innerHTML/),
        code: 'curMain.innerHTML = nextMain.innerHTML',
        equivalent: 'replaceMainContent()',
      },
      {
        id: 'loadNewScripts',
        function: 'loadNewScripts',
        line: find(/function loadNewScripts\(/),
        await: true,
        note: 'AFTER DOM replace on network path; blocks afterEnter only',
      },
      {
        id: 'afterEnter',
        function: 'runLifecycle(afterEnter) via afterScripts',
        line: find(/runLifecycle\('afterEnter'/),
      },
    ],
  };
}

function installProbe() {
  if (window.__BLOCK_RCA__ && window.__BLOCK_RCA__.__installed) {
    return true;
  }
  const N = (window.__BLOCK_RCA__ = {
    active: null,
    runs: [],
    __installed: true,
  });
  const now = () => performance.now();

  function span(name, meta) {
    const A = N.active;
    if (!A) return { end() {} };
    const start = now();
    const rec = { name, start, end: null, ms: null, meta: meta || null };
    A.awaits.push(rec);
    return {
      end(extra) {
        rec.end = now();
        rec.ms = rec.end - rec.start;
        if (extra) rec.meta = Object.assign({}, rec.meta || {}, extra);
      },
    };
  }

  // Patch Cache API
  if (window.caches) {
    const oKeys = caches.keys.bind(caches);
    const oOpen = caches.open.bind(caches);
    caches.keys = function () {
      const s = span('await caches.keys', {
        file: 'erp-nav-instant.js',
        function: 'openOpsCaches',
        line: 437,
      });
      return oKeys().then((v) => {
        s.end({ keyCount: (v && v.length) || 0 });
        return v;
      });
    };
    caches.open = function (name) {
      const s = span('await caches.open', {
        file: 'erp-nav-instant.js',
        function: 'openOpsCaches',
        line: 446,
        cacheName: name,
      });
      return oOpen(name).then((c) => {
        s.end();
        if (c && !c.__blockRca) {
          c.__blockRca = true;
          const om = c.match.bind(c);
          c.match = function (req, opts) {
            const s2 = span('await cache.match', {
              file: 'erp-nav-instant.js',
              function: 'matchCachedHtml',
              line: 471,
              key: String(req && req.url ? req.url : req).slice(0, 120),
              ignoreSearch: !!(opts && opts.ignoreSearch),
            });
            return om(req, opts).then((hit) => {
              s2.end({ hit: !!hit });
              return hit;
            });
          };
        }
        return c;
      });
    };
  }

  // Patch fetch + text for nav
  const of = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const headers = (init && init.headers) || {};
    const hv = (k) => {
      try {
        if (headers.get) return headers.get(k);
        return headers[k] || headers[k.toLowerCase()];
      } catch (e) {
        return null;
      }
    };
    const isNav = hv('X-Rateb-Nav-Swap') === '1' || hv('x-rateb-nav-swap') === '1';
    if (!(N.active && isNav)) return of(input, init);
    const s = span('await fetch (fetchWithTimeout/network)', {
      file: 'erp-nav-instant.js',
      function: 'fetchWithTimeout',
      line: 178,
      url: String(typeof input === 'string' ? input : (input && input.url) || '').slice(0, 160),
    });
    return of(input, init).then((res) => {
      s.end({ status: res && res.status });
      const origText = res.text.bind(res);
      res.text = function () {
        const s2 = span('await Response.text (network body)', {
          file: 'erp-nav-instant.js',
          function: 'fetchHtml',
          line: 543,
        });
        return origText().then((html) => {
          s2.end({ bytes: html ? html.length : 0 });
          return html;
        });
      };
      return res;
    });
  };

  // Patch Response.prototype.text for cache hits (cloned responses from cache.match)
  const protoText = Response.prototype.text;
  Response.prototype.text = function () {
    const A = N.active;
    if (!A) return protoText.apply(this, arguments);
    // Only attribute if we're between matchCachedHtml resolve and DOM replace
    const s = span('await Response.text', {
      file: 'erp-nav-instant.js',
      function: 'fetchHtml / cached.text or res.text',
      line: 529,
    });
    return protoText.apply(this, arguments).then((html) => {
      s.end({ bytes: html ? html.length : 0 });
      return html;
    });
  };

  // DOM replace
  const watchMain = () => {
    const main = document.querySelector('#rateb-main-content, main.rateb-content');
    if (!main || main.__blockRca) return;
    main.__blockRca = true;
    const desc = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    Object.defineProperty(main, 'innerHTML', {
      configurable: true,
      enumerable: true,
      get() {
        return desc.get.call(this);
      },
      set(v) {
        const A = N.active;
        if (A) {
          if (typeof A._closeFetchHtmlAtDom === 'function') A._closeFetchHtmlAtDom();
          A.domReplaceAt = now();
          A.awaits.push({
            name: 'DOM_REPLACE curMain.innerHTML = nextMain.innerHTML',
            start: A.domReplaceAt,
            end: A.domReplaceAt,
            ms: 0,
            meta: {
              file: 'erp-nav-instant.js',
              function: 'swapTo',
              line: 571,
              equivalent: 'replaceMainContent()',
              note: 'SYNC — not an await; runs only after fetchHtml Promise resolves',
            },
          });
        }
        desc.set.call(this, v);
        if (A) A.domReplaceDoneAt = now();
      },
    });
  };
  watchMain();
  try {
    new MutationObserver(() => watchMain()).observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) { /* ignore */ }

  document.addEventListener('rateb:nav:afterEnter', () => {
    if (N.active) {
      N.active.afterEnterAt = now();
      N.active.awaits.push({
        name: 'afterEnter (runLifecycle)',
        start: N.active.afterEnterAt,
        end: N.active.afterEnterAt,
        ms: 0,
        meta: { file: 'erp-nav-instant.js', function: 'afterScripts/runLifecycle', line: 583 },
      });
    }
  });

  // Wrap navigate (= swapTo export)
  const patch = () => {
    const api = window.RatebNavInstant;
    if (!api || api.__blockRca) return !!api;
    api.__blockRca = true;
    const orig = api.navigate.bind(api);
    api.navigate = function (href, opts) {
      const run = {
        href: String(href),
        t0: now(),
        awaits: [],
        domReplaceAt: null,
        afterEnterAt: null,
        fromCache: null,
      };
      N.active = run;
      run.awaits.push({
        name: 'swapTo() entered',
        start: run.t0,
        end: run.t0,
        ms: 0,
        meta: { file: 'erp-nav-instant.js', function: 'swapTo', line: 552 },
      });
      // Outer await: fetchHtml — closed when DOM replaces (not when navigate fully settles)
      const outerStart = now();
      const outerRec = {
        name: 'await fetchHtml(href)  [BLOCKS DOM REPLACE]',
        start: outerStart,
        end: null,
        ms: null,
        meta: {
          file: 'erp-nav-instant.js',
          function: 'swapTo',
          line: 561,
          proof: 'DOM replace is inside .then(pack => ...) of this Promise',
        },
      };
      run.awaits.push(outerRec);
      run._closeFetchHtmlAtDom = () => {
        if (outerRec.end == null) {
          outerRec.end = now();
          outerRec.ms = outerRec.end - outerRec.start;
        }
      };
      return Promise.resolve(orig(href, opts)).then((ok) => {
        if (outerRec.end == null) {
          outerRec.end = now();
          outerRec.ms = outerRec.end - outerRec.start;
        }
        if (run.domReplaceAt != null) {
          const before = run.awaits
            .filter(
              (a) =>
                a.end != null &&
                a.end <= run.domReplaceAt + 0.01 &&
                a.name.indexOf('DOM_REPLACE') === -1 &&
                a.name.indexOf('entered') === -1 &&
                a.name.indexOf('BLOCKS DOM') === -1
            )
            .sort((a, b) => b.end - a.end)[0];
          run.awaitImmediatelyBeforeDomReplace = before
            ? { name: before.name, ms: before.ms, meta: before.meta, endedAt_offset: before.end - run.t0 }
            : null;
        }
        run.totalMs = now() - run.t0;
        run.ok = !!ok;
        N.runs.push(run);
        N.active = null;
        return ok;
      });
    };
    return true;
  };
  setInterval(patch, 20);
  patch();

  N.consumeLast = () => N.runs[N.runs.length - 1] || null;
}

async function goDash(page) {
  await page.goto(BASE + '/admin/?company_id=22&_brca=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.evaluate(installProbe);
}

async function runNav(page, label, hrefRe, groupRe) {
  await goDash(page);
  const href = await page.evaluate(
    ({ hrefRe, groupRe }) => {
      const hr = new RegExp(hrefRe.source, hrefRe.flags);
      const gr = new RegExp(groupRe.source, groupRe.flags);
      for (const b of document.querySelectorAll('[data-nav-group-toggle]')) {
        if (gr.test(b.textContent || '')) b.click();
      }
      const a = [...document.querySelectorAll('a[href]')].find((el) => {
        try {
          return hr.test(new URL(el.href).pathname);
        } catch (e) {
          return false;
        }
      });
      return a ? a.href : null;
    },
    {
      hrefRe: { source: hrefRe.source, flags: hrefRe.flags },
      groupRe: { source: groupRe.source, flags: groupRe.flags },
    }
  );
  if (!href) return { label, error: 'link_not_found' };

  const result = await page.evaluate(async (h) => {
    const ok = await window.RatebNavInstant.navigate(h);
    const run = window.__BLOCK_RCA__.consumeLast();
    // fromCache from afterEnter detail if present
    return { ok, run };
  }, href);

  // Collapse Response.text duplicates: keep longest contiguous
  const run = result.run;
  if (run && run.awaits) {
    run.awaits = run.awaits.map((a) => ({
      name: a.name,
      ms: r1(a.ms),
      t_from_swapTo: a.start != null ? r1(a.start - run.t0) : null,
      file: a.meta && a.meta.file,
      function: a.meta && a.meta.function,
      line: a.meta && a.meta.line,
      meta: a.meta,
    }));
    // Blocking await = longest await that ended at or before DOM replace (excluding DOM replace itself)
    const preDom = run.awaits.filter(
      (a) =>
        a.name.indexOf('DOM_REPLACE') === -1 &&
        a.name.indexOf('afterEnter') === -1 &&
        a.name.indexOf('entered') === -1 &&
        a.t_from_swapTo != null &&
        run.domReplaceAt != null &&
        a.t_from_swapTo + (a.ms || 0) <= r1(run.domReplaceAt - run.t0) + 0.5
    );
    // Prefer the outer fetchHtml span
    const outer = run.awaits.find((a) => /BLOCKS DOM REPLACE/.test(a.name));
    const longest = preDom.slice().sort((a, b) => (b.ms || 0) - (a.ms || 0))[0];
    run.blockingAwait = outer || longest;
    run.longestLeafAwait = longest;
    run.domReplace_t = run.domReplaceAt != null ? r1(run.domReplaceAt - run.t0) : null;
    run.afterEnter_t = run.afterEnterAt != null ? r1(run.afterEnterAt - run.t0) : null;
    run.total_ms = r1(run.totalMs);
  }

  return { label, href, ok: result.ok, run };
}

function buildMarkdown(staticGraph, runs) {
  const L = [];
  L.push('# FINAL BLOCKING POINT RCA — DOM Replacement (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('');
  L.push('**Question:** What EXACTLY prevents DOM replacement between `swapTo()` and content swap?');
  L.push('');
  L.push('**Answer:** DOM replacement is **intentionally sequenced after** the `fetchHtml(href)` Promise resolves. There is no `replaceMainContent()` — replacement is inline:');
  L.push('');
  L.push('```571:571:rateb-erp/public/assets/js/erp-nav-instant.js');
  L.push('curMain.innerHTML = nextMain.innerHTML;');
  L.push('```');
  L.push('');
  L.push('That line sits inside `fetchHtml(href).then(function (pack) { ... })` at line 561. Until `fetchHtml` settles, the old page cannot disappear.');
  L.push('');

  L.push('## Call graph (source)');
  L.push('');
  L.push('```');
  L.push('swapTo(href)                          [' + staticGraph.file + ':' + staticGraph.nodes.find((n) => n.id === 'swapTo').line + ']');
  L.push('  │');
  L.push('  ├─ runLifecycle(beforeLeave)        [sync]');
  L.push('  │');
  L.push('  └─ await fetchHtml(href)            ★ BLOCKS DOM REPLACE  [line 561]');
  L.push('        │');
  L.push('        ├─ await matchCachedHtml(href)');
  L.push('        │     ├─ await openOpsCaches()');
  L.push('        │     │     ├─ await caches.keys()           [line 437]');
  L.push('        │     │     └─ await caches.open(name)×N     [line 446]');
  L.push('        │     └─ await cache.match(key) chain        [line 470–474]  (sequential)');
  L.push('        │');
  L.push('        ├─ HIT:  await cached.text()                 [line 529]');
  L.push('        │        (SWR fetch is fire-and-forget — does NOT block DOM)');
  L.push('        │');
  L.push('        └─ MISS: await fetchWithTimeout / fetch      [line 178/536]');
  L.push('                 └─ await res.text()                 [line 543]');
  L.push('  │');
  L.push('  ▼  fetchHtml resolved → pack.html available');
  L.push('  │');
  L.push('  DOM REPLACE: curMain.innerHTML = nextMain.innerHTML   [line 571]  (= replaceMainContent)');
  L.push('  │');
  L.push('  ├─ HIT:  afterEnter() sync, loadNewScripts idle       [line 600–608]');
  L.push('  └─ MISS: await loadNewScripts(doc) then afterEnter()  [line 610]');
  L.push('              └─ afterEnter()                           [line 583]');
  L.push('```');
  L.push('');

  L.push('## Proof: DOM replace delayed until fetch completes');
  L.push('');
  L.push('From source (`swapTo`):');
  L.push('');
  L.push('```552:571:rateb-erp/public/assets/js/erp-nav-instant.js');
  L.push('function swapTo(href, opts) {');
  L.push('    ...');
  L.push('    return fetchHtml(href).then(function (pack) {');
  L.push('        var doc = new DOMParser().parseFromString(pack.html, \'text/html\');');
  L.push('        ...');
  L.push('        curMain.innerHTML = nextMain.innerHTML;');
  L.push('```');
  L.push('');
  L.push('**There is no parallel path that replaces DOM before `fetchHtml` resolves.** The blocking await is the Promise returned by `fetchHtml` (line 561).');
  L.push('');

  for (const run of runs) {
    L.push('## Runtime — ' + run.label);
    L.push('');
    if (run.error) {
      L.push('ERROR: ' + run.error);
      L.push('');
      continue;
    }
    const R = run.run;
    L.push('- href: `' + run.href + '`');
    L.push('- total: **' + (R && R.total_ms) + ' ms**');
    L.push('- DOM replace at: **' + (R && R.domReplace_t) + ' ms** after swapTo');
    L.push('- afterEnter at: **' + (R && R.afterEnter_t) + ' ms** after swapTo');
    L.push('- **Blocking await (outer):** `' + (R && R.blockingAwait && R.blockingAwait.name) + '` = **' + (R && R.blockingAwait && R.blockingAwait.ms) + ' ms**');
    L.push(
      '- **Await finishing immediately before old page disappears:** `' +
        (R && R.awaitImmediatelyBeforeDomReplace && R.awaitImmediatelyBeforeDomReplace.name) +
        '` (ended @ ' +
        (R && R.awaitImmediatelyBeforeDomReplace && r1(R.awaitImmediatelyBeforeDomReplace.endedAt_offset)) +
        ' ms)'
    );
    L.push('- Longest leaf await before DOM: `' + (R && R.longestLeafAwait && R.longestLeafAwait.name) + '` = **' + (R && R.longestLeafAwait && R.longestLeafAwait.ms) + ' ms**');
    L.push('');
    L.push('| # | await / step | file | function | line | duration (ms) | t from swapTo |');
    L.push('|---|--------------|------|----------|------|---------------|---------------|');
    (R.awaits || []).forEach((a, i) => {
      L.push(
        '| ' +
          (i + 1) +
          ' | ' +
          a.name.replace(/\|/g, '/') +
          ' | ' +
          (a.file || '') +
          ' | ' +
          (a.function || '') +
          ' | ' +
          (a.line || '') +
          ' | ' +
          a.ms +
          ' | ' +
          a.t_from_swapTo +
          ' |'
      );
    });
    L.push('');
  }

  L.push('## Highlighted blocking await');
  L.push('');
  L.push('| Item | Value |');
  L.push('|------|-------|');
  L.push('| **Blocking await** | `await fetchHtml(href)` in `swapTo` |');
  L.push('| **File** | `rateb-erp/public/assets/js/erp-nav-instant.js` |');
  L.push('| **Function** | `swapTo` |');
  L.push('| **Line** | **561** |');
  L.push('| **Why old page stays** | `innerHTML` assignment is inside `.then` of this await |');
  L.push('| **Leaf work inside** | Cache API (`matchCachedHtml`) then either `cached.text()` (HIT) or `fetch` + `res.text()` (MISS) |');
  L.push('');
  L.push('No production code was modified.');
  L.push('');
  return L.join('\n');
}

(async () => {
  const staticGraph = buildStaticGraph();

  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'block-rca-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  });
  await ctx.addCookies([
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

  const page = ctx.pages()[0] || (await ctx.newPage());
  const runs = [];

  // Inventory first (likely MISS) then second (HIT)
  runs.push(
    await runNav(page, 'Inventory_first_MISS', /\/admin\/ops\/inventory(\/|$)/i, /المخزون|inventory/i)
  );
  runs.push(
    await runNav(page, 'Inventory_second_HIT', /\/admin\/ops\/inventory(\/|$)/i, /المخزون|inventory/i)
  );
  runs.push(
    await runNav(
      page,
      'Purchasing_first',
      /\/admin\/ops\/purchase-requests(\/|$)/i,
      /المشتريات|procurement|purchas/i
    )
  );
  runs.push(
    await runNav(
      page,
      'Purchasing_second',
      /\/admin\/ops\/purchase-requests(\/|$)/i,
      /المشتريات|procurement|purchas/i
    )
  );

  fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
  fs.writeFileSync(
    OUT_JSON,
    JSON.stringify({ generatedAt: new Date().toISOString(), staticGraph, runs }, null, 2)
  );
  fs.writeFileSync(OUT_MD, buildMarkdown(staticGraph, runs));
  console.log(OUT_JSON);
  console.log(OUT_MD);
  console.log(
    JSON.stringify(
      runs.map((r) => ({
        label: r.label,
        err: r.error || null,
        total: r.run && r.run.total_ms,
        domAt: r.run && r.run.domReplace_t,
        afterEnterAt: r.run && r.run.afterEnter_t,
        blocking: r.run && r.run.blockingAwait && { name: r.run.blockingAwait.name, ms: r.run.blockingAwait.ms },
        beforeDom: r.run && r.run.awaitImmediatelyBeforeDomReplace,
        longestLeaf: r.run && r.run.longestLeafAwait && { name: r.run.longestLeafAwait.name, ms: r.run.longestLeafAwait.ms },
      })),
      null,
      2
    )
  );
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
