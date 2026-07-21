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
assert('SW build id bumped for upgrade path', source.includes('20260721-offline-cache-migrate-v100'));
assert('activate migrates prior ops caches before delete', /Migrate prior ops-page HTML[\s\S]*caches\.delete\(name\)/.test(source));

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
