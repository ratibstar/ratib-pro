<?php
declare(strict_types=1);

/**
 * PR financial totals gates for Unified Agent (LineItems backend source of truth).
 * Run: php rateb-erp/tests/procurement/run-unified-agent-pr-financial-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementToolExecutor;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    $userId = (int) $pdo->query(
        "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
    if ($userId < 1) {
        $userId = (int) $pdo->query('SELECT id FROM rateb_users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }
    if ($companyId < 1 || $userId < 1) {
        throw new RuntimeException('no_company_or_user');
    }
} catch (Throwable $e) {
    echo "FAIL: db_bootstrap — {$e->getMessage()}\n";
    exit(1);
}

TenantContext::setCompanyId($companyId);
TenantContext::setSuperAdmin(true);
$_SESSION['rateb_user_id'] = $userId;
$_SESSION['rateb_is_super_admin'] = 1;
$_SESSION['rateb_company_id'] = $companyId;
Auth::bootstrapFromSession();

$ref = new ReflectionClass(ProcurementAgentContext::class);
$ctor = $ref->getConstructor();
$ctor->setAccessible(true);
$makeCtx = static function (array $perms, bool $sa, int $cid, array $modules, string $locale = 'ar') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'fin-tests', $perms, $sa, $modules);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$agent = new ErpAgent(require RATEB_ROOT . '/config/agent.php');
$base = $ctx->conversationScopeKey() . ':fin';

$near = static function (float $a, float $b, float $eps = 0.009): bool {
    return abs($a - $b) <= $eps;
};

$run = static function (string $scope, string $msg) use ($agent, $ctx, $pdo): array {
    ErpActionPlanner::clearPendingState($scope);
    $r1 = $agent->process([
        'message' => $msg,
        'history' => [],
        'request_id' => 'fin-p-' . bin2hex(random_bytes(3)),
        'confirmed_writes' => [],
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $keys = [];
    foreach (($r1['pending_confirmations'] ?? []) as $p) {
        if (is_array($p) && (string) ($p['confirm_key'] ?? '') !== '') {
            $keys[] = (string) $p['confirm_key'];
        }
    }
    $r2 = $agent->process([
        'message' => 'تأكيد',
        'history' => [['role' => 'user', 'content' => $msg]],
        'request_id' => 'fin-c-' . bin2hex(random_bytes(3)),
        'confirmed_writes' => $keys,
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $data = is_array($r2['action']['results'][0]['data'] ?? null) ? $r2['action']['results'][0]['data'] : [];
    $id = (int) ($data['id'] ?? 0);
    $header = null;
    $lines = [];
    if ($id > 0) {
        $header = $pdo->query(
            'SELECT id, total_estimated, currency FROM rateb_purchase_requests WHERE id = ' . (int) $id
        )->fetch(PDO::FETCH_ASSOC) ?: null;
        $st = $pdo->prepare(
            'SELECT item_name, quantity, unit, unit_price, tax_name, tax_rate, excluding_tax, total_price
             FROM rateb_purchase_request_items WHERE purchase_request_id = ? ORDER BY id ASC'
        );
        $st->execute([$id]);
        $lines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $agg = $lines !== [] ? LineItems::aggregateTotals($lines) : ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
    return [
        'propose' => $r1,
        'confirm' => $r2,
        'data' => $data,
        'id' => $id,
        'header' => $header,
        'lines' => $lines,
        'agg' => $agg,
        'args' => is_array($r1['pending_confirmations'][0]['arguments'] ?? null)
            ? $r1['pending_confirmations'][0]['arguments']
            : [],
        'ok' => !empty($r2['action']['results'][0]['success'])
            && !empty($r2['action']['results'][0]['verification']['verified']),
        'response' => (string) ($r2['response'] ?? ''),
    ];
};

// Tax preset resolver
$p15 = LineItems::resolveTaxPreset('ضريبة القيمة المضافة 15%', null);
$p5 = LineItems::resolveTaxPreset('VAT 5%', null);
$p0 = LineItems::resolveTaxPreset('Local Sales 0%', null);
$pex = LineItems::resolveTaxPreset('معفى', null);
(($p15['tax_name'] === 'VAT 15%' && (float) $p15['tax_rate'] === 15.0)
    && ($p5['tax_name'] === 'VAT 5%' && (float) $p5['tax_rate'] === 5.0)
    && ($p0['tax_name'] === 'Local Sales 0%' && (float) $p0['tax_rate'] === 0.0)
    && ($pex['tax_name'] === 'Exempt' && (float) $pex['tax_rate'] === 0.0))
    ? $pass('TAX ID')
    : $fail('TAX ID', json_encode([$p15, $p5, $p0, $pex], JSON_UNESCAPED_UNICODE));

// Backend calculator parity
$lt = LineItems::lineTotals(10, 5, 15, true);
(($near((float) $lt['subtotal'], 50.0) && $near((float) $lt['tax'], 7.5) && $near((float) $lt['total'], 57.5))
    ? $pass('TAX CALCULATION')
    : $fail('TAX CALCULATION', json_encode($lt)));
$inc = LineItems::lineTotals(1, 57.5, 15, false);
(($near((float) $inc['total'], 57.5) && $near((float) $inc['tax'], 7.5) && $near((float) $inc['subtotal'], 50.0))
    ? $pass('INCLUSIVE TAX')
    : $fail('INCLUSIVE TAX', json_encode($inc)));

// A — no price
$a = $run($base . ':a', 'أنشئ طلب شراء 10 وحدات بطاطس بدون سعر بأولوية متوسطة');
$aOk = $a['ok'] && $near((float) ($a['agg']['subtotal'] ?? -1), 0.0)
    && $near((float) ($a['agg']['tax'] ?? -1), 0.0)
    && $near((float) ($a['agg']['total'] ?? -1), 0.0)
    && $near((float) ($a['header']['total_estimated'] ?? -1), 0.0)
    && $near((float) ($a['lines'][0]['unit_price'] ?? -1), 0.0);
$aOk ? $pass('NO PRICE') : $fail('NO PRICE', json_encode(['agg' => $a['agg'], 'hdr' => $a['header'], 'line' => $a['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));

// B — price only (default VAT 15% like PR form)
$b = $run($base . ':b', 'أنشئ طلب شراء 10 وحدات بطاطس بسعر 5 ريال بأولوية متوسطة');
$bOk = $b['ok']
    && $near((float) ($b['lines'][0]['unit_price'] ?? 0), 5.0)
    && $near((float) ($b['agg']['subtotal'] ?? 0), 50.0);
$bOk ? $pass('UNIT PRICE') : $fail('UNIT PRICE', json_encode($b['lines'][0] ?? null, JSON_UNESCAPED_UNICODE));
$bOk ? $pass('SUBTOTAL') : $fail('SUBTOTAL', json_encode($b['agg']));

// C — VAT 15
$c = $run($base . ':c', 'أنشئ طلب شراء 10 وحدات بطاطس بسعر 5 ريال وضريبة القيمة المضافة 15% بأولوية متوسطة');
$cOk = $c['ok']
    && $near((float) ($c['agg']['subtotal'] ?? 0), 50.0)
    && $near((float) ($c['agg']['tax'] ?? 0), 7.5)
    && $near((float) ($c['agg']['total'] ?? 0), 57.5)
    && (string) ($c['lines'][0]['tax_name'] ?? '') === 'VAT 15%'
    && (int) ($c['lines'][0]['excluding_tax'] ?? 0) === 1;
$cOk ? $pass('VAT 15') : $fail('VAT 15', json_encode(['agg' => $c['agg'], 'line' => $c['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));
$cOk ? $pass('TOTAL CALCULATION') : $fail('TOTAL CALCULATION', json_encode($c['agg']));
$cOk && $near((float) ($c['header']['total_estimated'] ?? 0), 57.5)
    ? $pass('HEADER TOTALS')
    : $fail('HEADER TOTALS', json_encode($c['header']));

// D — inclusive tax
$d = $run($base . ':d', 'أنشئ طلب شراء 1 وحدة بطاطس بسعر 57.5 ريال شامل الضريبة وضريبة القيمة المضافة 15% بأولوية متوسطة');
$dOk = $d['ok']
    && (int) ($d['lines'][0]['excluding_tax'] ?? 1) === 0
    && $near((float) ($d['agg']['total'] ?? 0), 57.5)
    && $near((float) ($d['agg']['tax'] ?? 0), 7.5)
    && $near((float) ($d['agg']['subtotal'] ?? 0), 50.0);
$dOk ? $pass('INCLUSIVE TAX FLOW') : $fail('INCLUSIVE TAX FLOW', json_encode(['agg' => $d['agg'], 'line' => $d['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));

// E — VAT 5
$e = $run($base . ':e', 'أنشئ طلب شراء 10 وحدات بطاطس بسعر 5 ريال وضريبة 5% بأولوية متوسطة');
$eOk = $e['ok']
    && (string) ($e['lines'][0]['tax_name'] ?? '') === 'VAT 5%'
    && $near((float) ($e['agg']['subtotal'] ?? 0), 50.0)
    && $near((float) ($e['agg']['tax'] ?? 0), 2.5)
    && $near((float) ($e['agg']['total'] ?? 0), 52.5);
$eOk ? $pass('VAT 5') : $fail('VAT 5', json_encode(['agg' => $e['agg'], 'line' => $e['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));

// F — zero tax
$f = $run($base . ':f', 'أنشئ طلب شراء 10 وحدات بطاطس بسعر 5 ريال بدون ضريبة بأولوية متوسطة');
$fOk = $f['ok']
    && $near((float) ($f['agg']['tax'] ?? -1), 0.0)
    && $near((float) ($f['agg']['total'] ?? 0), 50.0)
    && in_array((string) ($f['lines'][0]['tax_name'] ?? ''), ['Local Sales 0%', 'Exempt'], true);
$fOk ? $pass('ZERO TAX') : $fail('ZERO TAX', json_encode(['agg' => $f['agg'], 'line' => $f['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));

// G — exempt
$g = $run($base . ':g', 'أنشئ طلب شراء 10 وحدات بطاطس بسعر 5 ريال معفى من الضريبة بأولوية متوسطة');
$gOk = $g['ok']
    && (string) ($g['lines'][0]['tax_name'] ?? '') === 'Exempt'
    && $near((float) ($g['agg']['tax'] ?? -1), 0.0)
    && $near((float) ($g['agg']['total'] ?? 0), 50.0);
$gOk ? $pass('EXEMPT') : $fail('EXEMPT', json_encode(['agg' => $g['agg'], 'line' => $g['lines'][0] ?? null], JSON_UNESCAPED_UNICODE));

// H — multi-item
$h = $run($base . ':h', 'أنشئ طلب شراء بطاطس × 10 بسعر 5 وأرز × 5 بسعر 10 وضريبة القيمة المضافة 15% بأولوية متوسطة');
$hNames = array_map(static fn($l) => (string) ($l['item_name'] ?? ''), $h['lines']);
$hBlob = implode('|', $hNames);
$hOk = $h['ok']
    && count($h['lines']) >= 2
    && (stripos($hBlob, 'بطاطس') !== false)
    && (stripos($hBlob, 'أرز') !== false || stripos($hBlob, 'رز') !== false)
    && $near((float) ($h['agg']['subtotal'] ?? 0), 100.0)
    && $near((float) ($h['agg']['tax'] ?? 0), 15.0)
    && $near((float) ($h['agg']['total'] ?? 0), 115.0);
$hOk ? $pass('MULTI-ITEM TOTALS') : $fail('MULTI-ITEM TOTALS', json_encode(['agg' => $h['agg'], 'lines' => $h['lines']], JSON_UNESCAPED_UNICODE));

// DB read-back + edit-screen totals source
$editOk = $cOk
    && !empty($c['data']['totals_verified'])
    && $near((float) ($c['data']['subtotal'] ?? 0), 50.0)
    && $near((float) ($c['data']['tax_amount'] ?? 0), 7.5)
    && $near((float) ($c['data']['total'] ?? 0), 57.5);
$editOk ? $pass('DB READ-BACK') : $fail('DB READ-BACK', json_encode($c['data'], JSON_UNESCAPED_UNICODE));

$formPhp = (string) file_get_contents(RATEB_ROOT . '/views/components/line-items.php');
(str_contains($formPhp, 'aggregateTotals') && str_contains($formPhp, 'data-procurement-subtotal'))
    ? $pass('EDIT SCREEN')
    : $fail('EDIT SCREEN', 'footer not server-rendered from aggregateTotals');

($cOk && stripos($c['response'], 'المبلغ قبل الضريبة') !== false && stripos($c['response'], '57.50') !== false)
    ? $pass('FINANCIAL PAYLOAD')
    : $fail('FINANCIAL PAYLOAD', mb_substr($c['response'], 0, 240));

// Direct executor parity with form collect
$ex = ProcurementToolExecutor::execute('create_draft_purchase_request', [
    'title' => 'fin executor',
    'priority' => 'medium',
    'line_items' => [[
        'item_name' => 'بطاطس',
        'quantity' => 10,
        'unit' => 'each',
        'unit_price' => 5,
        'tax_name' => 'VAT 15%',
        'tax_rate' => 15,
        'excluding_tax' => 1,
    ]],
], $ctx);
$exOk = !empty($ex['success'])
    && $near((float) ($ex['data']['subtotal'] ?? 0), 50.0)
    && $near((float) ($ex['data']['tax_amount'] ?? 0), 7.5)
    && $near((float) ($ex['data']['total'] ?? 0), 57.5)
    && !empty($ex['data']['totals_verified']);
$exOk ? $pass('EXECUTOR FORM PARITY') : $fail('EXECUTOR FORM PARITY', json_encode($ex['data'] ?? $ex, JSON_UNESCAPED_UNICODE));

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "PR FINANCIAL GATES PASS\n";
exit(0);
