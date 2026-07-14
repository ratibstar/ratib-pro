#!/bin/bash
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
cd "$ROOT"

echo "==== Run available offline test classes via simple runners if present ===="
# Find run-*.php under offline/tests
find offline/tests modules/pos/tests -maxdepth 1 -name 'run-*.php' 2>/dev/null | sort

echo "==== OfflineFoundationTest if runnable ===="
# Many offline tests need wrappers; try phpunit-less inline
$PHP -r '
require_once "app/Core/Bootstrap.php";
\Rateb\App\Core\Bootstrap::init(__DIR__);
require_once "offline/tests/OfflineFoundationTest.php";
echo class_exists("OfflineFoundationTest") || true;
' 2>&1 | head -5

echo "==== POS integration/e2e fail extract ===="
grep -n -i 'FAIL\|Error\|Exception\|not found\|assert' /tmp/ag-cert/pos_v2_all.log | head -40

echo "==== CrmDashboardController on disk? ===="
find app -name 'CrmDashboardController.php' 2>/dev/null
find app -name '*Recruitment*Controller.php' 2>/dev/null | head -10

echo "==== recruitment error body ===="
R='--resolve rateb.sa:443:167.233.71.107'
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ag-cert/mint2.json"),true); echo $j["session_name"]."=".$j["session_id"];')
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/admin/recruitment?company_id=22" | grep -oE 'Class [^<]+|code>[^<]+' | head -5

echo "==== Composer autoload classmap check CRM ===="
$PHP -r '
require "vendor/autoload.php";
echo class_exists("Rateb\\App\\Controllers\\Company\\CrmDashboardController") ? "CRM_LOADED\n" : "CRM_MISSING\n";
echo class_exists("Rateb\\App\\Controllers\\Company\\HrDashboardController") ? "HR_LOADED\n" : "HR_MISSING\n";
echo class_exists("Rateb\\App\\Controllers\\Company\\RecruitmentDashboardController") ? "REC_LOADED\n" : "REC_MISSING\n";
'

# Re-run final cert route-only verdict piece into report
cp /tmp/ag-cert/probe2.json $ROOT/tools/boot-bench/reports/phase-final-route-probe.json
