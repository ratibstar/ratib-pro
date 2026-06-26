<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/plain; charset=UTF-8');

define('RATEB_ENV_NO_SESSION', true);
define('RATEB_HEALTH_PROBE', true);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => false,
        'fatal' => $err['message'] ?? 'unknown',
        'file' => ($err['file'] ?? '') . ':' . ($err['line'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
});

$ratebRoot = realpath(dirname(__FILE__, 2));
if ($ratebRoot === false) {
    $ratebRoot = dirname(__FILE__, 2);
}
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot));
}

$probe = isset($_GET['probe']) ? (string) $_GET['probe'] : '';
$dispatchRoute = isset($_GET['dispatch']) ? (string) $_GET['dispatch'] : '';

if ($probe === 'ping') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'probe' => 'ping', 'php' => PHP_VERSION, 'ts' => time()], JSON_UNESCAPED_UNICODE);
    exit;
}

$steps = [];
try {
    $steps[] = 'PHP ' . PHP_VERSION;
    $steps[] = 'RATEB_ROOT=' . RATEB_ROOT;

    require_once RATEB_ROOT . '/config/app.php';
    $steps[] = 'app.php OK';

    require_once RATEB_ROOT . '/config/database.php';
    $steps[] = 'database.php OK (DB_HOST=' . (defined('DB_HOST') ? DB_HOST : '?') . ', ERP DB=' . (defined('RATEB_DB_NAME') ? RATEB_DB_NAME : '?') . ')';

    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $steps[] = 'Bootstrap OK';

    $pdo = \Rateb\App\Core\Database::connection();
    $steps[] = 'DB connection OK (' . \Rateb\App\Core\Database::resolvedDatabaseName() . ')';

    if ($probe === 'admin-dash' || $probe === 'schema') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = [
            'ok' => true,
            'php' => PHP_VERSION,
            'db' => \Rateb\App\Core\Database::resolvedDatabaseName(),
            'migrations' => [],
            'columns' => [],
            'classes' => [],
            'dashboard' => [],
        ];
        foreach (['131_financial_branch_isolation.sql', '132_interbranch_gl_consolidation.sql', '133_phase5_api_branch_hq_reports.sql', '134_contracts_branch_catchup.sql', '135_phase6_interbranch_execution.sql', '129_inter_branch_transfers.sql'] as $mf) {
            $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
            $stmt->execute(['f' => $mf]);
            $report['migrations'][$mf] = $stmt->fetch() ? 'applied' : 'missing';
        }
        foreach ([
            ['rateb_journal_lines', 'branch_id'],
            ['rateb_cost_centers', 'branch_id'],
            ['rateb_bank_accounts', 'branch_id'],
            ['rateb_journal_entries', 'branch_id'],
            ['rateb_contracts', 'branch_id'],
            ['rateb_contracts', 'approval_status'],
            ['rateb_contracts', 'barcode'],
            ['rateb_branches', 'is_main'],
        ] as [$table, $col]) {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($col));
            $report['columns'][$table . '.' . $col] = ($stmt !== false && $stmt->fetch()) ? 'yes' : 'no';
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        }
        foreach ([
            'Rateb\\App\\Controllers\\Company\\BranchFinancialReportsController',
            'Rateb\\App\\Services\\BranchFinancialReportingService',
            'Rateb\\App\\Services\\ConsolidationEliminationService',
            'Rateb\\App\\Services\\AccountingService',
        ] as $class) {
            $report['classes'][$class] = class_exists($class);
        }
        try {
            \Rateb\App\Core\TenantContext::setSuperAdmin(true);
            $dash = new \Rateb\App\Services\DashboardService();
            $report['dashboard']['metrics'] = $dash->adminMetrics();
            $report['dashboard']['charts'] = $dash->adminCharts();
        } catch (\Throwable $e) {
            $report['ok'] = false;
            $report['dashboard']['error'] = $e->getMessage();
            $report['dashboard']['file'] = $e->getFile() . ':' . $e->getLine();
        }
        try {
            ob_start();
            \Rateb\App\Core\View::render('admin/dashboard', [
                'title' => 'Dashboard probe',
                'metrics' => $report['dashboard']['metrics'] ?? [],
                'charts' => $report['dashboard']['charts'] ?? [],
                'csrf' => 'probe',
            ], 'main');
            $html = (string) ob_get_clean();
            $report['dashboard']['render_len'] = strlen($html);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $report['ok'] = false;
            $report['dashboard']['render_error'] = $e->getMessage();
            $report['dashboard']['render_file'] = $e->getFile() . ':' . $e->getLine();
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($probe === 'branch-ops') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = ['ok' => true, 'steps' => []];
        $adminRow = $pdo->query('SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = \'active\' ORDER BY id ASC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($adminRow)) {
            echo json_encode(['ok' => false, 'error' => 'no super admin user'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $adminId = (int) ($adminRow['id'] ?? 0);
        \Rateb\App\Core\SessionManager::start();
        $_SESSION['rateb_user_id'] = $adminId;
        $_SESSION['rateb_is_super_admin'] = 1;
        $_SESSION['rateb_company_id'] = null;
        $_GET['company_id'] = (string) max(1, (int) ($_GET['company_id'] ?? 3));
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $report['steps'][] = 'session user_id=' . $adminId . ' company_id=' . $_GET['company_id'];
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        require RATEB_ROOT . '/routes/marketing.php';
        require RATEB_ROOT . '/routes/cms.php';
        require RATEB_ROOT . '/routes/company.php';
        require RATEB_ROOT . '/routes/api.php';
        $report['steps'][] = 'routes loaded';
        foreach ([
            'branch_dashboard' => '/admin/ops/branch-dashboard',
            'branch_financial' => '/admin/ops/branch-financial',
            'branch_compare' => '/admin/ops/branch-dashboard/compare',
            'branch_reports' => '/admin/ops/branch-dashboard/reports',
            'contracts' => '/admin/ops/contracts',
        ] as $key => $path) {
            ob_start();
            try {
                $router->dispatch('GET', $path);
                $body = (string) ob_get_clean();
                $report[$key] = ['ok' => true, 'body_len' => strlen($body)];
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $report['ok'] = false;
                $report[$key] = ['ok' => false, 'error' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()];
            }
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($probe === 'admin-live') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = ['ok' => true, 'steps' => []];
        $adminRow = $pdo->query('SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = \'active\' ORDER BY id ASC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($adminRow)) {
            echo json_encode(['ok' => false, 'error' => 'no super admin user'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $adminId = (int) ($adminRow['id'] ?? 0);
        \Rateb\App\Core\SessionManager::start();
        $_SESSION['rateb_user_id'] = $adminId;
        $_SESSION['rateb_is_super_admin'] = 1;
        $_SESSION['rateb_company_id'] = null;
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $report['steps'][] = 'session user_id=' . $adminId;
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        require RATEB_ROOT . '/routes/marketing.php';
        require RATEB_ROOT . '/routes/cms.php';
        require RATEB_ROOT . '/routes/company.php';
        require RATEB_ROOT . '/routes/api.php';
        $report['steps'][] = 'routes loaded';
        ob_start();
        try {
            $router->dispatch('GET', '/admin');
            $body = (string) ob_get_clean();
            $report['body_len'] = strlen($body);
            $report['has_dashboard'] = stripos($body, 'rateb-widget') !== false;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $report['ok'] = false;
            $report['error'] = $e->getMessage();
            $report['file'] = $e->getFile() . ':' . $e->getLine();
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($probe === 'approvals') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = ['db' => \Rateb\App\Core\Database::resolvedDatabaseName(), 'tables' => [], 'pending_test' => null];
        $tables = [
            'rateb_supplier_evaluations' => ['manager_approval', 'approved_by', 'approved_at'],
            'rateb_contracts' => ['approval_status'],
            'rateb_inventory_audits' => ['manager_approval', 'approved_by', 'approved_at'],
            'rateb_asset_assignments' => ['manager_approval', 'approved_by', 'approved_at'],
        ];
        foreach ($tables as $table => $cols) {
            $entry = ['columns' => []];
            foreach ($cols as $col) {
                $stmt = $pdo->query(
                    'SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($col)
                );
                $row = $stmt !== false ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                if ($stmt instanceof \PDOStatement) {
                    $stmt->closeCursor();
                }
                $entry['columns'][$col] = is_array($row) ? (string) ($row['Type'] ?? 'yes') : false;
            }
            $report['tables'][$table] = $entry;
        }
        $row = $pdo->query(
            "SELECT id, company_id, manager_approval FROM rateb_supplier_evaluations WHERE manager_approval = 'pending' ORDER BY id DESC LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $id = (int) ($row['id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE rateb_supplier_evaluations SET manager_approval = :st WHERE id = :id AND manager_approval = :pending'
                )->execute(['st' => 'approved', 'id' => $id, 'pending' => 'pending']);
                $pdo->rollBack();
                $report['pending_test'] = ['id' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $report['pending_test'] = ['id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($probe === 'routes' || $probe === 'dispatch' || $probe === 'all-routes' || $dispatchRoute !== '') {
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        $steps[] = 'routes/web.php OK';
        if ($probe === 'all-routes') {
            require RATEB_ROOT . '/routes/marketing.php';
            $steps[] = 'routes/marketing.php OK';
            require RATEB_ROOT . '/routes/cms.php';
            $steps[] = 'routes/cms.php OK';
        }
        require RATEB_ROOT . '/routes/company.php';
        $steps[] = 'routes/company.php OK';
        require RATEB_ROOT . '/routes/api.php';
        $steps[] = 'routes/api.php OK';
    }

    if ($probe === 'dispatch' || $dispatchRoute !== '') {
        require_once RATEB_ROOT . '/app/helpers/Request.php';
        $route = $dispatchRoute !== '' ? $dispatchRoute : 'login';
        $_GET['route'] = ltrim($route, '/');
        $path = \Rateb\App\Helpers\Request::resolvePath();
        $steps[] = 'resolved path=' . $path;

        ob_start();
        $router->dispatch('GET', $path);
        $body = (string) ob_get_clean();
        $steps[] = 'dispatch OK body_len=' . strlen($body);
    }

    echo "RATEB ERP health: OK\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "RATEB ERP health: FAIL\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
