#!/usr/bin/env python3
import json
p='/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-ab-e2e-profile.json'
j=json.load(open(p))
print('wall',j['totals']['wall_ms'],'sql_ms',j['totals']['sql_ms'],'sql_n',j['totals']['sql_count'])
print('BOTTLENECK')
print(json.dumps(j['single_biggest_bottleneck'], indent=2))
print('TOP SPANS')
for s in j['top20_spans_by_wall'][:15]:
    print('%8.3f self=%8.3f sql=%3d %s' % (s['dur_ms'], s['self_ms'], s['sql_count'], s['id']))
print('TOP SQL')
for q in j['top20_sql_queries'][:12]:
    sql=q['sql'][:140].replace('|','/')
    print('%7.3f %s::%s L%s  %s' % (q['dur_ms'], q.get('class',''), q.get('function',''), q.get('line'), sql))
print('TOP SQL FN')
for q in j['top20_sql_by_function'][:10]:
    print('%7.3f n=%s %s L%s' % (q['ms'], q['n'], q['key'], q['line']))
print('FLAME')
ft=j['flame_tree']
print(ft['id'], ft['wall_ms'], ft['pct'])
for c in ft['children']:
    print(' ', c['id'], c['wall_ms'], c['pct'], 'self', c['self_ms'])
    for c2 in c.get('children') or []:
        print('   ', c2['id'], c2['wall_ms'], c2['pct'], 'self', c2['self_ms'])
