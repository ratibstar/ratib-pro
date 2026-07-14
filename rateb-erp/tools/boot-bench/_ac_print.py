#!/usr/bin/env python3
import json
j=json.load(open('/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-ac-infra.json'))
print('cookie', j.get('cookie_minted'))
for k,v in j['network'].items():
    if isinstance(v, dict) and 'ttfb_ms' in v:
        print('%s: code=%s dns=%s tcp=%s tls=%s think=%s ttfb=%s total=%s http=%s size=%s' % (
            k, v.get('http_code'), v.get('dns_ms'), v.get('tcp_ms'), v.get('tls_ms'),
            v.get('server_think_ms'), v.get('ttfb_ms'), v.get('total_ms'), v.get('http_version'), v.get('size')))
print('fpm_opcache', j.get('fpm_opcache_probe',{}).get('body'))
print('opcache', json.dumps(j.get('opcache'), indent=2)[:800])
print('compression', j.get('compression'))
print('mysql', j.get('mysql'))
print('cache_ext', j.get('cache_extensions'))
print('proxy_headers:')
print(j.get('proxy_headers','')[:800])
print('SW', list((j.get('service_workers') or {}).keys()))
print('ASSETS')
print((j.get('top_assets_by_bytes') or '')[:900])
print('load', j['system']['loadavg'])
print('mem')
print(j['system']['mem'])
print('swap', j['system']['swap'])
print('disk')
print(j['system']['disk'][:400])
print('POOL')
print((j.get('fpm_pool') or '')[:800])
print('PROCS')
print((j.get('processes') or '')[:900])
# sample headers from admin warm
h = (j['network'].get('loopback_admin_warm') or {}).get('headers','')
print('ADMIN_HDR')
print('\n'.join([ln for ln in h.splitlines() if ln.strip()][:25]))
css_h = (j['network'].get('loopback_main_css') or {}).get('headers','')
print('CSS_HDR')
print('\n'.join([ln for ln in css_h.splitlines() if ln.strip()][:20]))
