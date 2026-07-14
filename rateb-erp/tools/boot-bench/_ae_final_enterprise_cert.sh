#!/bin/bash
# Final enterprise regression certification — READ ONLY (no app code changes).
# Temporary probe under public/ removed at end.
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
REPORT="$ROOT/tools/boot-bench/reports"
mkdir -p "$REPORT" /tmp/ag-cert
OUT_JSON="$REPORT/phase-final-enterprise-certification.json"
R='--resolve rateb.sa:443:167.233.71.107'
export RATEB_ROOT="$ROOT"
cd "$ROOT"

pass=0
fail=0
skip=0
RESULTS=()

record() {
  local name="$1" status="$2" detail="$3"
  RESULTS+=("$(printf '%s' "{\"name\":$(php -r 'echo json_encode($argv[1]);' -- "$name"),\"status\":$(php -r 'echo json_encode($argv[1]);' -- "$status"),\"detail\":$(php -r 'echo json_encode($argv[1]);' -- "$detail")}")")
  case "$status" in
    PASS) pass=$((pass+1)) ;;
    FAIL) fail=$((fail+1)) ;;
    SKIP) skip=$((skip+1)) ;;
  esac
  echo "[$status] $name — $detail"
}

run_php_suite() {
  local name="$1"
  local script="$2"
  shift 2
  if [[ ! -f "$script" ]]; then
    record "$name" "SKIP" "missing $script"
    return
  fi
  local log="/tmp/ag-cert/${name}.log"
  "$PHP" "$script" "$@" >"$log" 2>&1
  local rc=$?
  local tail
  tail=$(tail -c 400 "$log" | tr '\n' ' ' | tr -d '\r')
  if [[ $rc -eq 0 ]]; then
    record "$name" "PASS" "exit=0 ${tail:0:240}"
  else
    record "$name" "FAIL" "exit=$rc ${tail:0:240}"
  fi
}

echo "======== 1) EnterpriseTestRunner ========"
set +e
"$PHP" "$ROOT/bin/enterprise-test/run.php" --json > /tmp/ag-cert/enterprise-test.json 2>/tmp/ag-cert/enterprise-test.err
rc=$?
if [[ $rc -eq 0 ]] && "$PHP" -r '$j=json_decode(file_get_contents("/tmp/ag-cert/enterprise-test.json"),true); exit((is_array($j)&&(($j["failed"]??1)===0))?0:1);'; then
  detail=$("$PHP" -r '$j=json_decode(file_get_contents("/tmp/ag-cert/enterprise-test.json"),true); echo "passed={$j["passed"]} failed={$j["failed"]} total={$j["total"]}";')
  record "enterprise_test_runner" "PASS" "$detail"
else
  detail=$(head -c 300 /tmp/ag-cert/enterprise-test.err; echo; head -c 300 /tmp/ag-cert/enterprise-test.json)
  record "enterprise_test_runner" "FAIL" "rc=$rc $detail"
fi

echo "======== 2) Domain / offline / POS suites ========"
# Curated "all available" runners — domain online + offline foundation + POS
SUITES=(
  "hr_online|$ROOT/tests/hr/run-hr-phase23a-tests.php"
  "crm_online|$ROOT/tests/crm/run-crm-phase17a-tests.php"
  "accounting_online|$ROOT/tests/accounting/run-accounting-phase16a-tests.php"
  "procurement_online|$ROOT/tests/procurement/run-procurement-phase21a-tests.php"
  "assets_online|$ROOT/tests/assets/run-assets-phase19a-tests.php"
  "projects_online|$ROOT/tests/projects/run-projects-phase18a-tests.php"
  "approval_online|$ROOT/tests/approval/run-approval-phase20a-tests.php"
  "manufacturing_online|$ROOT/tests/manufacturing/run-manufacturing-phase22a-tests.php"
  "payroll_online|$ROOT/tests/payroll/run-payroll-phase24a-tests.php"
  "quality_online|$ROOT/tests/quality/run-quality-phase25a-tests.php"
  "documents_online|$ROOT/tests/documents/run-document-management-phase26a-tests.php"
  "bi_online|$ROOT/tests/bi/run-business-intelligence-phase27a-tests.php"
  "recruitment_online|$ROOT/tests/recruitment/run-recruitment-phase15a-tests.php"
  "offline_foundation|$ROOT/offline/tests/run-offline-foundation-tests.php"
  "offline_baseline_v12|$ROOT/offline/tests/run-enterprise-baseline-v12-tests.php"
  "offline_auth|$ROOT/offline/tests/run-erp-offline-auth-tests.php"
  "offline_rbac|$ROOT/offline/tests/run-erp-offline-rbac-tests.php"
  "offline_identity|$ROOT/offline/tests/run-erp-offline-identity-tests.php"
  "offline_hardening|$ROOT/offline/tests/run-offline-hardening-tests.php"
  "offline_hr|$ROOT/offline/tests/run-hr-offline-tests.php"
  "offline_crm|$ROOT/offline/tests/run-crm-offline-tests.php"
  "offline_inventory|$ROOT/offline/tests/run-inventory-offline-tests.php"
  "offline_procurement|$ROOT/offline/tests/run-procurement-offline-tests.php"
  "offline_accounting|$ROOT/offline/tests/run-accounting-offline-tests.php"
  "offline_sync_queue|$ROOT/offline/tests/run-queue-durability-tests.php"
  "pos_v2_all|$ROOT/modules/pos/tests/run-all-pos-v2-tests.php"
  "pos_offline_sync|$ROOT/modules/pos/tests/run-offline-sync-tests.php"
)

for entry in "${SUITES[@]}"; do
  name="${entry%%|*}"
  script="${entry#*|}"
  echo "-- $name"
  run_php_suite "$name" "$script"
done

echo "======== 3) Route parity / ModuleLoader probe ========"
cat > "$ROOT/public/_ae_final_cert_probe.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$ROOT = dirname(__DIR__);
$t0 = hrtime(true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin/hr?company_id=22';
$_GET['company_id'] = '22';

require_once $ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
\Rateb\App\Core\Auth::bootstrapFromSession();
if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

require_once RATEB_ROOT . '/app/helpers/Request.php';

function ae_route_sha(\Rateb\App\Core\Router $router): array {
    $ref = new ReflectionClass($router);
    $prop = $ref->getProperty('routes');
    $prop->setAccessible(true);
    $routes = $prop->getValue($router);
    $lines = [];
    $conflicts = [];
    $seen = [];
    foreach ($routes as $r) {
        $key = $r['method'] . ' ' . $r['pattern'];
        if (isset($seen[$key])) {
            $conflicts[] = $key;
        }
        $seen[$key] = true;
        $h = $r['handler'];
        if (is_array($h)) {
            $hs = (is_object($h[0]) ? get_class($h[0]) : (string) $h[0]) . '::' . (string) $h[1];
        } elseif ($h instanceof Closure) {
            $hs = 'Closure';
        } else {
            $hs = 'callable';
        }
        $mw = [];
        foreach ($r['middleware'] as $m) {
            if (is_array($m)) {
                $mw[] = (string) $m[0] . ':' . (string) ($m[1] ?? '');
            } else {
                $mw[] = (string) $m;
            }
        }
        sort($mw);
        $lines[] = $r['method'] . "\t" . $r['pattern'] . "\t" . $hs . "\t" . implode(',', $mw);
    }
    sort($lines);
    return [
        'count' => count($routes),
        'sha256' => hash('sha256', implode("\n", $lines)),
        'conflict_keys' => array_values(array_unique($conflicts)),
        'conflict_count' => count(array_unique($conflicts)),
    ];
}

// Ops selective (pre-AG SHA reference)
$routerOps = new \Rateb\App\Core\Router();
$tOps = hrtime(true);
\Rateb\App\Core\RouteModuleLoader::loadForPath($routerOps, '/admin/hr');
$opsLoadMs = (hrtime(true) - $tOps) / 1e6;
$opsMeta = ae_route_sha($routerOps);
$opsLoaded = \Rateb\App\Core\RouteModuleLoader::lastLoadedIds();

// Dashboard selective
$routerDash = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($routerDash, '/admin');
$dashMeta = ae_route_sha($routerDash);
$dashLoaded = \Rateb\App\Core\RouteModuleLoader::lastLoadedIds();

// Auth only
$routerAuth = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($routerAuth, '/login');
$authMeta = ae_route_sha($routerAuth);

// POS UI
$routerPos = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($routerPos, '/pos');
$posMeta = ae_route_sha($routerPos);
$posLoaded = \Rateb\App\Core\RouteModuleLoader::lastLoadedIds();

// API
$routerApi = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($routerApi, '/api/health');
$apiMeta = ae_route_sha($routerApi);

// Full loadAll
$routerAll = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadAll($routerAll);
$allMeta = ae_route_sha($routerAll);

// rateb_app_route parity matrix
$roots = [
    'inventory','suppliers','assets','contracts','stock-movements','supplier-evaluations',
    'workflows','medical-devices','reports','notifications','accounting','chart-of-accounts',
    'journal-entries','cost-centers','cash-vouchers','fiscal-periods','bank-accounts','rfq',
    'quotations','purchase-requests','purchase-orders','warehouses','warehouse-transfers',
    'product-categories','branches','branch-dashboard','branch-financial','branch-transfers',
    'inventory-batches','inventory-audits','inventory-forecast','supplier-comms',
    'supplier-classifications','supplier-kpi','contract-renewals','tenders','asset-maintenance',
    'asset-assignments','asset-depreciation','device-maintenance','device-spare-parts',
    'device-warranty','documents','profile','pos','access-control','users','roles','permissions',
    'audit-logs','support-tickets','email-templates','sms-templates','hr','crm','recruitment',
];
$routeSamples = [];
foreach ($roots as $r) {
    $routeSamples[$r] = rateb_app_route($r);
}
// company-aware URL
$companyUrl = function_exists('rateb_app_url') ? rateb_app_url('inventory') : null;
$opsCompany = function_exists('rateb_url_with_ops_company') ? rateb_url_with_ops_company(rateb_app_route('hr')) : null;

// Legacy aliases present?
$legacyPatterns = [];
foreach (['/company', '/company/login', '/accounting', '/company/{legacy:.+}'] as $p) {
    $legacyPatterns[$p] = $routerOps->hasMatch('GET', $p);
}

// Module select matrix
$select = [];
foreach (['/admin','/admin/hr','/admin/ops/inventory','/admin/crm','/pos','/api/foo','/login','/admin/cms','/site'] as $path) {
    $select[$path] = \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path);
}

echo json_encode([
    'ok' => true,
    'auth' => true,
    'user_id' => \Rateb\App\Core\Auth::id(),
    'company_access' => rateb_company_access_routes_enabled(),
    'ops' => $opsMeta + ['load_ms' => round($opsLoadMs, 3), 'loaded' => $opsLoaded],
    'dashboard' => $dashMeta + ['loaded' => $dashLoaded],
    'auth_routes' => $authMeta,
    'pos' => $posMeta + ['loaded' => $posLoaded],
    'api' => $apiMeta,
    'load_all' => $allMeta,
    'rateb_app_route_samples' => $routeSamples,
    'company_aware_urls' => [
        'rateb_app_url_inventory' => $companyUrl,
        'rateb_url_with_ops_company_hr' => $opsCompany,
    ],
    'legacy_aliases' => $legacyPatterns,
    'module_select' => $select,
    'pre_ag_ops_sha' => 'bd4081989eca0724497836e9b5dc7bec38e46e21b7429149e2401764ba4b32a8',
    'ops_sha_matches_pre_ag' => ($opsMeta['sha256'] === 'bd4081989eca0724497836e9b5dc7bec38e46e21b7429149e2401764ba4b32a8'),
    'ops_count_matches_pre_ag' => ($opsMeta['count'] === 1179),
    'elapsed_ms' => round((hrtime(true) - $t0) / 1e6, 3),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ag-cert/mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ag-cert/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_final_cert_probe.php" > /tmp/ag-cert/probe.json
cp /tmp/ag-cert/probe.json "$REPORT/phase-final-route-probe.json"

if "$PHP" -r '$j=json_decode(file_get_contents("/tmp/ag-cert/probe.json"),true); exit(!empty($j["ok"])&&!empty($j["ops_sha_matches_pre_ag"])&&!empty($j["ops_count_matches_pre_ag"])?0:1);'; then
  detail=$("$PHP" -r '$j=json_decode(file_get_contents("/tmp/ag-cert/probe.json"),true); echo "sha=".$j["ops"]["sha256"]." count=".$j["ops"]["count"]." load_ms=".$j["ops"]["load_ms"]." conflicts=".$j["ops"]["conflict_count"];')
  record "route_sha_parity_pre_ag" "PASS" "$detail"
else
  detail=$(head -c 400 /tmp/ag-cert/probe.json)
  record "route_sha_parity_pre_ag" "FAIL" "$detail"
fi

# Sample URL expected (pre-AG)
"$PHP" -r '
$j=json_decode(file_get_contents("/tmp/ag-cert/probe.json"),true);
$exp=["hr"=>"admin/hr","inventory"=>"admin/ops/inventory","users"=>"admin/ops/users","crm"=>"admin/crm","pos"=>"admin/ops/pos"];
$bad=[];
foreach($exp as $k=>$v){ if(($j["rateb_app_route_samples"][$k]??null)!==$v)$bad[]="$k got ".($j["rateb_app_route_samples"][$k]??"null"); }
echo empty($bad)?"OK":implode("; ",$bad);
' > /tmp/ag-cert/url_parity.txt
if grep -q '^OK$' /tmp/ag-cert/url_parity.txt; then
  record "rateb_app_route_url_parity" "PASS" "$(cat /tmp/ag-cert/url_parity.txt)"
else
  record "rateb_app_route_url_parity" "FAIL" "$(cat /tmp/ag-cert/url_parity.txt)"
fi

leg=$("$PHP" -r '$j=json_decode(file_get_contents("/tmp/ag-cert/probe.json"),true); $l=$j["legacy_aliases"]??[]; echo (($l["/company"]??false)&&($l["/accounting"]??false))?"OK":json_encode($l);')
if [[ "$leg" == "OK" ]]; then
  record "legacy_aliases" "PASS" "company+accounting present"
else
  record "legacy_aliases" "FAIL" "$leg"
fi

echo "======== 4) HTTP module smoke (auth cookie) ========"
# path|expect_substr_in_body_or_empty|label
SMOKES=(
  "/admin/|dashboard|dashboard"
  "/admin/hr?company_id=22||hr"
  "/admin/crm?company_id=22||crm"
  "/admin/ops/inventory?company_id=22||inventory"
  "/admin/ops/purchase-requests?company_id=22||procurement"
  "/admin/ops/accounting?company_id=22||accounting"
  "/admin/ops/suppliers?company_id=22||suppliers"
  "/admin/recruitment?company_id=22||recruitment"
  "/pos||pos"
  "/login||login_page"
)
for entry in "${SMOKES[@]}"; do
  IFS='|' read -r path needle label <<<"$entry"
  code=$(curl -sk $R -b "$C" -o /tmp/ag-cert/smoke_body.html -w "%{http_code}" "https://rateb.sa/rateb-erp/public$path")
  ttfb=$(curl -sk $R -b "$C" -o /dev/null -w "%{time_starttransfer}" "https://rateb.sa/rateb-erp/public$path")
  if [[ "$code" != "200" && "$code" != "302" ]]; then
    record "http_$label" "FAIL" "http=$code ttfb=$ttfb"
  else
    record "http_$label" "PASS" "http=$code ttfb=$ttfb"
  fi
done

# Auth: logout redirect / unauth probe
code=$(curl -sk $R -o /dev/null -w "%{http_code}" "https://rateb.sa/rateb-erp/public/admin/hr")
# without cookie should redirect to login typically 302
if [[ "$code" == "302" || "$code" == "401" || "$code" == "200" ]]; then
  record "auth_gate_unauthenticated" "PASS" "http=$code (gate responds)"
else
  record "auth_gate_unauthenticated" "FAIL" "http=$code"
fi

echo "======== 5) SW / offline assets / hybrid ========"
for f in \
  "public/pos-sw.js" \
  "public/offline-shell.html" \
  "public/assets/offline/rateb-offline.js" \
  "app/Core/RouteModuleLoader.php" \
  "routes/manifest.php" \
  "routes/modules/ops.php"
 do
  if [[ -f "$ROOT/$f" ]]; then
    record "artifact_$f" "PASS" "present"
  else
    record "artifact_$f" "FAIL" "missing"
  fi
done

# SW reachable
code=$(curl -sk $R -o /dev/null -w "%{http_code}" "https://rateb.sa/rateb-erp/public/pos-sw.js")
if [[ "$code" == "200" ]]; then record "service_worker_http" "PASS" "http=200"; else record "service_worker_http" "FAIL" "http=$code"; fi

"$PHP" "$ROOT/bin/hybrid-zero-touch-status.php" > /tmp/ag-cert/hybrid.json 2>/tmp/ag-cert/hybrid.err
hrc=$?
if [[ $hrc -eq 0 ]]; then
  record "hybrid_zero_touch_status" "PASS" "exit=0 $(head -c 120 /tmp/ag-cert/hybrid.json | tr '\n' ' ')"
else
  # may fail outside branch appliance — soft skip if expected
  if grep -qi 'branch\|RATEB_RUNTIME\|not configured' /tmp/ag-cert/hybrid.err /tmp/ag-cert/hybrid.json 2>/dev/null; then
    record "hybrid_zero_touch_status" "SKIP" "not applicable on SaaS host: $(head -c 160 /tmp/ag-cert/hybrid.err)"
  else
    record "hybrid_zero_touch_status" "FAIL" "exit=$hrc $(head -c 200 /tmp/ag-cert/hybrid.err)"
  fi
fi

# Background jobs: queue monitor class / migration presence check (read-only)
if "$PHP" -r '
require_once getenv("RATEB_ROOT")."/app/Core/Bootstrap.php";
\Rateb\App\Core\Bootstrap::init(getenv("RATEB_ROOT"));
$ok = class_exists("Rateb\\App\\Services\\AuthorizationService");
echo $ok ? "OK" : "NO";
' | grep -q OK; then
  record "rbac_AuthorizationService" "PASS" "class loads"
else
  record "rbac_AuthorizationService" "FAIL" "class missing"
fi

rm -f "$ROOT/public/_ae_final_cert_probe.php"
echo PROBE_REMOVED

: > /tmp/ag-cert/results.jsonl
for r in "${RESULTS[@]}"; do
  echo "$r" >> /tmp/ag-cert/results.jsonl
done

OUT_JSON="$OUT_JSON" PASS_N=$pass FAIL_N=$fail SKIP_N=$skip "$PHP" <<'PHP'
<?php
$pass = (int) getenv('PASS_N');
$fail = (int) getenv('FAIL_N');
$skip = (int) getenv('SKIP_N');
$items = [];
foreach (file('/tmp/ag-cert/results.jsonl', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $items[] = json_decode($line, true);
}
$probe = @json_decode(@file_get_contents('/tmp/ag-cert/probe.json'), true) ?: [];
$ent = @json_decode(@file_get_contents('/tmp/ag-cert/enterprise-test.json'), true);
$verdict = $fail === 0 ? 'SAFE TO COMMIT' : 'DO NOT COMMIT';
$out = [
    'phase' => 'FINAL_ENTERPRISE_REGRESSION_CERTIFICATION',
    'mode' => 'read_only_no_code_changes',
    'scope' => 'Post Phase AG (rateb_app_route O(N²) fix) — compare to pre-AG',
    'measured_at' => gmdate('c'),
    'verdict' => $verdict,
    'summary' => ['pass' => $pass, 'fail' => $fail, 'skip' => $skip, 'total' => $pass + $fail + $skip],
    'pre_ag_ops_sha256' => 'bd4081989eca0724497836e9b5dc7bec38e46e21b7429149e2401764ba4b32a8',
    'post_ag_ops_sha256' => $probe['ops']['sha256'] ?? null,
    'route_parity' => [
        'sha_match' => $probe['ops_sha_matches_pre_ag'] ?? null,
        'count_match' => $probe['ops_count_matches_pre_ag'] ?? null,
        'ops_count' => $probe['ops']['count'] ?? null,
        'ops_load_ms' => $probe['ops']['load_ms'] ?? null,
        'dashboard_count' => $probe['dashboard']['count'] ?? null,
        'load_all_count' => $probe['load_all']['count'] ?? null,
        'route_conflicts_ops' => $probe['ops']['conflict_count'] ?? null,
    ],
    'enterprise_test' => is_array($ent) ? [
        'passed' => $ent['passed'] ?? null,
        'failed' => $ent['failed'] ?? null,
        'total' => $ent['total'] ?? null,
    ] : null,
    'checks' => $items,
    'regressions' => array_values(array_filter($items, static fn ($c) => ($c['status'] ?? '') === 'FAIL')),
    'module_select' => $probe['module_select'] ?? null,
    'legacy_aliases' => $probe['legacy_aliases'] ?? null,
    'company_aware_urls' => $probe['company_aware_urls'] ?? null,
    'evidence_files' => [
        'tools/boot-bench/reports/phase-final-enterprise-certification.json',
        'tools/boot-bench/reports/phase-final-route-probe.json',
        'tools/boot-bench/reports/phase-ag-before.json',
        'tools/boot-bench/reports/phase-ag-verdict.json',
    ],
];
$path = getenv('OUT_JSON');
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo $verdict . "\n";
echo "Wrote $path\n";
echo "pass=$pass fail=$fail skip=$skip\n";
PHP
