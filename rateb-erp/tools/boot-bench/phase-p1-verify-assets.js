'use strict';
(async () => {
  const sw = await (await fetch('https://rateb.sa/rateb-erp/public/pos-sw.js?t=' + Date.now(), { cache: 'no-store' })).text();
  const nav = await (await fetch('https://rateb.sa/rateb-erp/public/assets/js/erp-nav-instant.js?t=' + Date.now(), { cache: 'no-store' })).text();
  const m = sw.match(/SW_BUILD_ID\s*=\s*['"]([^'"]+)/);
  console.log(JSON.stringify({
    sw_build: m && m[1],
    has_swr: sw.indexOf('stale-while-revalidate') !== -1 || sw.indexOf('PERF-P1 — SWR') !== -1,
    nav_len: nav.length,
    nav_ok: nav.indexOf('RatebNavInstant') !== -1,
  }, null, 2));
})();
