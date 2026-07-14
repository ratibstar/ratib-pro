#!/bin/bash
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
$PHP "$ROOT/tools/boot-bench/remote-auth.php" restore
echo RESTORED

$PHP <<'PHP'
<?php
$acc = json_decode(file_get_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-ae-admin-ops-accounting.json'), true);
$hr = json_decode(file_get_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-ae-admin-hr.json'), true);
$dash = json_decode(file_get_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-ae-admin.json'), true);

function ae_pack($j) {
  $stages = array_slice($j['stages'] ?? [], 0, 15);
  $sqlFn = array_slice($j['top30_sql_by_function'] ?? [], 0, 20);
  $sql = array_slice($j['top30_sql'] ?? [], 0, 20);
  // sort sql by dur
  usort($sql, fn($a,$b) => ($b['dur_ms']??0) <=> ($a['dur_ms']??0));
  $sql = array_slice($sql, 0, 20);
  return [
    'path' => $j['path'] ?? null,
    'wall_ms' => $j['totals']['wall_ms_to_body'] ?? null,
    'sql_ms' => $j['totals']['sql_ms'] ?? null,
    'sql_count' => $j['totals']['sql_count'] ?? null,
    'biggest' => $j['single_biggest_stage'] ?? null,
    'stages_top' => $stages,
    'top20_sql_by_function' => $sqlFn,
    'top20_sql' => $sql,
    'top15_leaf' => array_slice($j['top30_leaf_spans'] ?? [], 0, 15),
  ];
}

$out = [
  'accounting' => ae_pack($acc),
  'hr' => ae_pack($hr),
  'dashboard' => ae_pack($dash),
];
file_put_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/reports/phase-post-ag-php-detail.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode([
  'acc_wall' => $out['accounting']['wall_ms'],
  'acc_sql_ms' => $out['accounting']['sql_ms'],
  'acc_sql_n' => $out['accounting']['sql_count'],
  'acc_biggest' => $out['accounting']['biggest'],
  'acc_top5_fn' => array_slice($out['accounting']['top20_sql_by_function'], 0, 5),
  'acc_top5_sql' => array_slice($out['accounting']['top20_sql'], 0, 5),
  'acc_stages' => array_map(fn($s)=>['id'=>$s['id'],'wall'=>$s['wall_ms'],'self'=>$s['self_ms'],'pct'=>$s['pct']], array_slice($out['accounting']['stages_top'],0,8)),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
PHP
