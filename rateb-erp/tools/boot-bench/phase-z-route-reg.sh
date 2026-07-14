#!/bin/bash
# Phase Z — route registration analysis (temporary probes, no product code change)
set -eu
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
PUB=$ROOT/public

cleanup() { rm -f "$PUB/__phase_z_"*.php; }
trap cleanup EXIT

# --- Probe: instrument helpers + count routes + match GET /admin ---
cat > "$PUB/__phase_z_reg_profile.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);
Rateb\App\Core\Auth::bootstrapFromSession();

/** @var array{calls:int,ms:float,max_conflict:int} */
$prof = [
    'rateb_app_route' => ['calls' => 0, 'ms' => 0.0, 'max_conflict_len' => 0, 'enabled_true' => 0],
    'rateb_erp_mw' => ['calls' => 0, 'ms' => 0.0],
    'rateb_module_permission' => ['calls' => 0, 'ms' => 0.0],
    'rateb_admin_mw' => ['calls' => 0, 'ms' => 0.0],
    'rateb_platform_oversight_mw' => ['calls' => 0, 'ms' => 0.0],
    'rateb_guest_mw' => ['calls' => 0, 'ms' => 0.0],
    'router_add' => ['calls' => 0, 'ms' => 0.0],
];

// Wrap helpers by redefining via runkit? Not available.
// Instead: shadow via measuring inside duck router + sampling rateb_app_route by monkeypatch file include order.
// We intercept by replacing function... can't. Measure via wrapping $app usage from outside:
// Profile by replacing Router and also wrapping through custom include of measured copies? Forbidden (no route change).
// APPROACH: use uopz if present; else time by overriding via namespace — not possible for global functions.
// Use: tick-based / Debug backtrace sampling during require? Too heavy.
// BEST: copy-on-write instrumented wrappers that call originals — PHP allows function_exists guards;
// originals already defined. We cannot redefine.
// Measure with micro-benchmarks of rateb_app_route alone x N after bootstrap, and time company.php
// with and without patched conflictRoots? Can't patch without changing code.
//
// Alternative: Reflection + rename not available.
// Use APD/xhprof if available.

$hasXhprof = extension_loaded('xhprof') || function_exists('xhprof_enable');
$hasTideways = extension_loaded('tideways_xhprof') || function_exists('tideways_xhprof_enable');

$inner = new Rateb\App\Core\Router();
$routes = [];
$t0 = hrtime(true);
$addMs = 0.0;

$router = new class($inner, $routes, $addMs, $t0) {
    public function __construct(
        private Rateb\App\Core\Router $inner,
        private array &$routes,
        private float &$addMs,
        private float $t0,
    ) {}
    private function track(string $method, string $pattern, $handler, array $mw): void
    {
        $a = hrtime(true);
        $fn = match ($method) {
            'GET' => 'get',
            'POST' => 'post',
            'PUT' => 'put',
            'PATCH' => 'patch',
            'DELETE' => 'delete',
            default => 'get',
        };
        $this->inner->$fn($pattern, $handler, $mw);
        $this->addMs += (hrtime(true) - $a) / 1e6;
        $handlerLabel = 'unknown';
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $cls = is_string($handler[0]) ? $handler[0] : get_class($handler[0]);
            $handlerLabel = $cls . '::' . $handler[1];
        } elseif ($handler instanceof Closure) {
            $handlerLabel = 'Closure';
        }
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handlerLabel,
            'mw_count' => count($mw),
            't_ms' => round((hrtime(true) - $this->t0) / 1e6, 3),
        ];
    }
    public function get(string $p, $h, array $m = []): void { $this->track('GET', $p, $h, $m); }
    public function post(string $p, $h, array $m = []): void { $this->track('POST', $p, $h, $m); }
    public function put(string $p, $h, array $m = []): void { $this->track('PUT', $p, $h, $m); }
    public function patch(string $p, $h, array $m = []): void { $this->track('PATCH', $p, $h, $m); }
    public function delete(string $p, $h, array $m = []): void { $this->track('DELETE', $p, $h, $m); }
};

$fileTimings = [];
$files = [
    'web' => $root . '/routes/web.php',
    'marketing' => $root . '/routes/marketing.php',
    'cms' => $root . '/routes/cms.php',
    'company' => $root . '/routes/company.php',
    'api' => $root . '/routes/api.php',
];
$beforeCount = 0;
foreach ($files as $name => $path) {
    if (!is_file($path)) {
        $fileTimings[$name] = ['ms' => 0, 'routes' => 0, 'missing' => true];
        continue;
    }
    $a = hrtime(true);
    $c0 = count($routes);
    require $path;
    $fileTimings[$name] = [
        'ms' => round((hrtime(true) - $a) / 1e6, 3),
        'routes' => count($routes) - $c0,
        'missing' => false,
    ];
}

$totalRegMs = round((hrtime(true) - $t0) / 1e6, 3);

// Dispatch match simulation (same logic as Router::dispatch path match)
$matchUri = '/admin';
$method = 'GET';
$matched = null;
$matchIndex = null;
$checked = 0;
foreach ($routes as $i => $route) {
    if ($route['method'] !== $method) {
        continue;
    }
    $checked++;
    $regex = '#^' . preg_replace_callback(
        '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(\.\+))?\}#',
        static function (array $m): string {
            if (($m[2] ?? '') === '.+') {
                return '(?P<' . $m[1] . '>.+)';
            }
            return '(?P<' . $m[1] . '>[^/]+)';
        },
        $route['pattern']
    ) . '$#';
    if (preg_match($regex, $matchUri)) {
        $matched = $route;
        $matchIndex = $i;
        break;
    }
}

// Also try /admin/ with rtrim like Router
$matchUri2 = '/admin'; // Router rtrims trailing slash → /admin

// Count by source file (infer from pattern prefixes / ordering)
$byGroup = [
    'auth_login' => 0,
    'admin_dashboard' => 0,
    'admin_oversight' => 0,
    'admin_ops' => 0,
    'admin_hr_crm_projects_etc' => 0,
    'company_legacy' => 0,
    'api' => 0,
    'marketing_cms_other' => 0,
    'closure_handlers' => 0,
];
foreach ($routes as $r) {
    if ($r['handler'] === 'Closure') {
        $byGroup['closure_handlers']++;
    }
    $p = $r['pattern'];
    if (str_starts_with($p, '/company')) {
        $byGroup['company_legacy']++;
    } elseif ($p === '/admin' || $p === '/admin/') {
        $byGroup['admin_dashboard']++;
    } elseif (str_starts_with($p, '/admin/api')) {
        $byGroup['api']++;
    } elseif (str_starts_with($p, '/admin/ops/')) {
        $byGroup['admin_ops']++;
    } elseif (preg_match('#^/admin/(hr|crm|projects|eam|qms|recruitment)/#', $p)) {
        $byGroup['admin_hr_crm_projects_etc']++;
    } elseif (str_starts_with($p, '/admin/')) {
        $byGroup['admin_oversight']++;
    } elseif (in_array($r['method'] . ' ' . $p, ['GET /admin/login', 'POST /admin/login'], true) || str_contains($p, '/login')) {
        $byGroup['auth_login']++;
    } else {
        $byGroup['marketing_cms_other']++;
    }
}

// Microbench: rateb_app_route alone vs rateb_erp_mw vs router add
$bench = [];
$n = 1000;
$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    rateb_app_route('inventory/items');
}
$bench['rateb_app_route_1000_ms'] = round((hrtime(true) - $a) / 1e6, 3);
$bench['rateb_app_route_per_call_ms'] = round($bench['rateb_app_route_1000_ms'] / $n, 4);

$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    rateb_erp_mw('inventory', '', 'inventory');
}
$bench['rateb_erp_mw_1000_ms'] = round((hrtime(true) - $a) / 1e6, 3);
$bench['rateb_erp_mw_per_call_ms'] = round($bench['rateb_erp_mw_1000_ms'] / $n, 4);

$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    rateb_app_route('hr/employees');
}
$bench['rateb_app_route_hr_1000_ms'] = round((hrtime(true) - $a) / 1e6, 3);

// Demonstrate conflictRoots growth cost: call rateb_app_route many times with unique-looking paths
// After company.php already ran thousands of merges in this request — measure current cost
$a = hrtime(true);
for ($i = 0; $i < 200; $i++) {
    rateb_app_route('inventory/x' . $i);
}
$bench['rateb_app_route_200_after_load_ms'] = round((hrtime(true) - $a) / 1e6, 3);

// Fresh insight: count array_merge bug impact by inspecting via reflection of static? can't.
// Approximate: call rateb_company_access_routes_enabled
$bench['company_access_routes_enabled'] = function_exists('rateb_company_access_routes_enabled')
    ? rateb_company_access_routes_enabled()
    : null;
$bench['is_platform_host'] = function_exists('rateb_is_platform_oversight_host')
    ? rateb_is_platform_oversight_host()
    : null;

$neededForAdmin = 1; // matched routes for GET /admin
$total = count($routes);
$unused = $total - $neededForAdmin;
$unusedPct = $total > 0 ? round(100 * $unused / $total, 2) : 0;
// Time attribution: company.php file time is almost entirely unused for GET /admin
$unusedTimeMs = ($fileTimings['company']['ms'] ?? 0)
    + ($fileTimings['api']['ms'] ?? 0) * 0 // api might be needed for charts later but not this request match
    + max(0, ($fileTimings['cms']['ms'] ?? 0))
    + max(0, ($fileTimings['marketing']['ms'] ?? 0));
// More precise: only the matched route's file is needed — web.php. All others unused for THIS request.
$unusedRegTimeMs = $totalRegMs - ($fileTimings['web']['ms'] ?? 0);

echo json_encode([
    'sapi' => PHP_SAPI,
    'total_registered' => $total,
    'router_inner_add_ms' => round($addMs, 3),
    'total_registration_wall_ms' => $totalRegMs,
    'file_timings' => $fileTimings,
    'matched_get_admin' => $matched,
    'match_index' => $matchIndex,
    'patterns_checked_before_match' => $checked,
    'needed_for_get_admin' => $neededForAdmin,
    'unused_routes' => $unused,
    'unused_pct' => $unusedPct,
    'unused_registration_time_ms_approx' => round($unusedRegTimeMs, 3),
    'unused_registration_time_pct' => $totalRegMs > 0 ? round(100 * $unusedRegTimeMs / $totalRegMs, 2) : 0,
    'groups' => $byGroup,
    'bench' => $bench,
    'closures_total' => $byGroup['closure_handlers'],
    'framework_route_cache_present' => false,
    'note_route_cache' => 'Grep of rateb-erp found no RouteCache / compiled routes / serialize routes capability',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

# Isolated microbench of rateb_app_route growth bug (fresh process)
cat > "$PUB/__phase_z_app_route_growth.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
require $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);

// Ensure helpers loaded
require_once $root . '/routes/middleware-helpers.php';

$enabled = rateb_company_access_routes_enabled();
$samples = [];
// Measure batches: first 50, next 50, ... as static array grows due to array_merge each call
$batch = 50;
for ($b = 0; $b < 20; $b++) {
    $a = hrtime(true);
    for ($i = 0; $i < $batch; $i++) {
        rateb_app_route('inventory/item-' . $b . '-' . $i);
    }
    $ms = (hrtime(true) - $a) / 1e6;
    $samples[] = [
        'batch' => $b,
        'calls_so_far' => ($b + 1) * $batch,
        'batch_ms' => round($ms, 3),
        'per_call_ms' => round($ms / $batch, 4),
    ];
}

// Compare: paths that do NOT hit conflictRoots merge path the same — merge runs every call when enabled
$a = hrtime(true);
for ($i = 0; $i < 200; $i++) {
    rateb_erp_mw('inventory', '', 'inventory');
}
$erpMs = (hrtime(true) - $a) / 1e6;

$a = hrtime(true);
$r = new Rateb\App\Core\Router();
for ($i = 0; $i < 200; $i++) {
    $r->get('/x' . $i, [Rateb\App\Controllers\Admin\DashboardController::class, 'index'], []);
}
$addMs = (hrtime(true) - $a) / 1e6;

echo json_encode([
    'company_access_routes_enabled' => $enabled,
    'growth_batches' => $samples,
    'rateb_erp_mw_200_ms' => round($erpMs, 3),
    'router_get_200_ms' => round($addMs, 3),
    'first_batch_per_call' => $samples[0]['per_call_ms'] ?? null,
    'last_batch_per_call' => $samples[count($samples) - 1]['per_call_ms'] ?? null,
    'slowdown_factor' => ($samples[0]['per_call_ms'] ?? 0) > 0
        ? round(($samples[count($samples) - 1]['per_call_ms'] / $samples[0]['per_call_ms']), 2)
        : null,
    'source' => [
        'file' => 'config/app.php',
        'function' => 'rateb_app_route',
        'lines' => '2562-2591',
        'mechanism' => 'static $conflictRoots array_merge on every call when rateb_company_access_routes_enabled(); then in_array over growing list',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

echo "=== FPM full registration profile ==="
curl -sk "https://rateb.sa/rateb-erp/public/__phase_z_reg_profile.php" | tee /tmp/phase-z-reg.json
echo
echo "=== FPM rateb_app_route growth ==="
curl -sk "https://rateb.sa/rateb-erp/public/__phase_z_app_route_growth.php" | tee /tmp/phase-z-growth.json
echo

# Count $app( and rateb_app_route / rateb_erp_mw in company.php
echo "=== static counts in company.php ==="
php -r '
$p="/home/admin/domains/rateb.sa/public_html/rateb-erp/routes/company.php";
$s=file_get_contents($p);
echo json_encode([
  "app_calls"=>preg_match_all("/\\\$app\\s*\\(/",$s),
  "rateb_erp_mw"=>preg_match_all("/rateb_erp_mw\\s*\\(/",$s),
  "rateb_app_route"=>preg_match_all("/rateb_app_route\\s*\\(/",$s),
  "router_get"=>preg_match_all("/\\\$router->get\\s*\\(/",$s),
  "router_post"=>preg_match_all("/\\\$router->post\\s*\\(/",$s),
  "closures_fn"=>preg_match_all("/\\bfn\\s*\\(/",$s),
], JSON_PRETTY_PRINT),"\n";
'

# Confirm no route cache in Router/Bootstrap
echo "=== framework cache grep ==="
grep -RniE "route.?cache|cachedRoutes|compileRoutes|serialize.*routes" \
  /home/admin/domains/rateb.sa/public_html/rateb-erp/app/Core \
  /home/admin/domains/rateb.sa/public_html/rateb-erp/config \
  2>/dev/null | head -20 || echo "(none)"
