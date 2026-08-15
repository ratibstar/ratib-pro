#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const swPath = path.join(root, 'rateb-erp/public/pos-sw.js');
const source = fs.readFileSync(swPath, 'utf8');

let passed = 0;
let failed = 0;
const assert = (name, condition) => {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + name);
    condition ? passed++ : failed++;
};

const match = source.match(/function isValidErpOpsHtmlBody\(pageUrl, html\) \{[\s\S]*?\n\}/);
assert('isValidErpOpsHtmlBody is defined in pos-sw.js', !!match);

let isValidErpOpsHtmlBody = null;
if (match) {
    // eslint-disable-next-line no-new-func
    isValidErpOpsHtmlBody = new Function(
        match[0] + '\nreturn isValidErpOpsHtmlBody;'
    )();
}

const companiesUrl = 'https://rateb.sa/rateb-erp/public/admin/companies';
const emptyHtml = '<html><head></head><body></body></html>';
const truncatedHtml = '<!DOCTYPE html><html><head><title>x</title></head><body><div>thin</div></body></html>';
const validHtml = [
    '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>Companies</title></head>',
    '<body><aside class="rateb-sidebar"></aside><main id="rateb-main" class="rateb-main">',
    '<script>window.__RATEB_ERP_SHELL=1;</script>',
    '<h1>لوحة التحكم</h1>',
    'x'.repeat(20000),
    '</main></body></html>'
].join('');

assert('rejects empty HTML', isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, '') === false);
assert('rejects blank document poison', isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, emptyHtml) === false);
assert('rejects truncated HTML without shell markers', isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, truncatedHtml) === false);
assert('accepts valid ERP HTML', isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, validHtml) === true);

assert('ops cache version unchanged', source.includes("var ERP_OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v36'"));
assert('navigateErpCloudWithCacheSafety paints cache-first', /function navigateErpCloudWithCacheSafety[\s\S]*serveCachedFast\(hit/.test(source));
assert('poisoned cache entries are deleted', source.includes('deletePoisonedErpOpsCacheEntries(pageUrl)'));
assert('offline fallback uses neverFailNavigate after poison', /deletePoisonedErpOpsCacheEntries\(pageUrl\)[\s\S]*neverFailNavigate\(request, url\)/.test(source));
assert('putErpOpsHtmlResponse reuses shared validator', /function putErpOpsHtmlResponse[\s\S]*isValidErpOpsHtmlBody\(pageUrl, body\)/.test(source));
assert('SW build id bumped for upgrade path', source.includes('20260722-offline-open-all-v127'));
assert('offline miss paints Admin shell stub', /function adminHtmlWithOfflineStub|X-Rateb-Offline-Stub/.test(source));
assert('online charts never get empty stub', /isCriticalOnlineChartAsset[\s\S]*12000|chartCritical \? 12000/.test(source));
assert('chart asset miss uses bare fetch online', /Last resort: bare fetch without abort[\s\S]*chartCritical/.test(source));
assert('activate migrates prior ops caches before delete', /Migrate prior ops-page HTML[\s\S]*caches\.delete\(name\)/.test(source));
assert('F5 clones response before deferred Cache.put', /Clone NOW[\s\S]*toCache = response\.clone\(\)[\s\S]*setTimeout/.test(source));
assert('ops HTML put deferred past second F5', /setTimeout\(function \(\) \{[\s\S]*putErpOpsHtmlResponse[\s\S]*\}, 800\)/.test(source));
assert('bare /admin offline never uses uncached-first ceiling', /Bare \/admin MUST NEVER show[\s\S]*adminHomeFallback/.test(source));
assert('admin home offline seed on activate', /function seedAdminHomeOfflineFallback[\s\S]*purgeInlineShellFromAdminKeys/.test(source));
assert('offline finish flattens Promise responses', /Resolving a nested Promise made Chrome fail respondWith[\s\S]*Promise\.resolve\(res\)/.test(source));
assert('offline header stamp is sync when body exists', /Sync stamp when body is readable[\s\S]*return new Response\(response\.body/.test(source));
assert('navigate fetch aborts on timeout', /function fetchNavigateNetwork[\s\S]*AbortController[\s\S]*markCloudNetworkDegraded/.test(source));
assert('soft-offline latch from client message', source.includes("data.type === 'RATEB_CLOUD_OFFLINE'"));
assert('client soft-offline latch is long-lived', /reason === 'client'[\s\S]*120000/.test(source));
assert('hard offline helper ignores soft latch', source.includes('function isHardBrowserOffline'));
assert('safe offline admin navigate exists', source.includes('function safeOfflineAdminNavigate'));
assert('admin document navigate always intercepts', source.includes('function adminDocumentNavigate'));
assert('offline admin paints within ceiling', /ceilingMs = bareAdmin \? 1800 : 2000/.test(source));
assert('soft-nav offline miss never returns lean shell', /soft-nav-offline-miss[\s\S]*NEVER return lean inline shell|NEVER return lean inline shell[\s\S]*soft-nav-offline-miss/.test(source));
assert('soft-offline TTL is short', /CLOUD_DEGRADED_TTL_MS = 5000/.test(source));
assert('admin nav never bypasses to Chrome interstitial', /ALWAYS respondWith — never fall through to Chrome/.test(source));
assert('respondWith document gate never rejects', /respondWithDocumentAndReleaseWarmGate[\s\S]{0,800}?\.catch\(function/.test(source));
assert('SW warms agency-updates for offline', /leanOpsCritical[\s\S]*admin\/agency-updates/.test(source));
assert('SW warms companies-approvals for offline', source.includes("'admin/oversight/companies-approvals'"));
assert('SW warms hr-approvals for offline', source.includes("'admin/oversight/hr-approvals'"));
assert('SW HTML warm delayed for online charts', /HTML warm ~8s after shell assets[\s\S]*8000/.test(source));
assert('SW warms hr/holidays for offline', /leanOpsCritical[\s\S]*admin\/hr\/holidays/.test(source));
assert('SW warms accounting hub', /leanOpsCritical[\s\S]*admin\/ops\/accounting/.test(source));
assert('rejects inline offline shell as ops HTML', /Never treat the lean offline menu[\s\S]*return false/.test(source));
assert('purges inline shell from admin keys', source.includes('function purgeInlineShellFromAdminKeys'));

// Simulated cache recovery contract: invalid body must not be re-cached by put path.
assert(
    'put path cannot accept poisoned empty body',
    isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, emptyHtml) === false
);
assert(
    'network recovery still allows valid re-cache',
    isValidErpOpsHtmlBody && isValidErpOpsHtmlBody(companiesUrl, validHtml) === true
);

console.log('');
console.log(passed + '/' + (passed + failed) + ' passed');
process.exit(failed === 0 ? 0 : 1);
