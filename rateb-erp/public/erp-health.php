<?php
declare(strict_types=1);

/**
 * Public health — anonymous GET returns {"status":"ok"} only.
 * Administrative probes require X-Rateb-Health-Token (deploy secret); no session impersonation.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=UTF-8');

define('RATEB_ENV_NO_SESSION', true);
define('RATEB_HEALTH_PROBE', true);

$ratebRoot = realpath(dirname(__FILE__, 2));
if ($ratebRoot === false) {
    $ratebRoot = dirname(__FILE__, 2);
}
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot));
}

require_once RATEB_ROOT . '/config/app.php';
require_once RATEB_ROOT . '/app/Core/SecurityHeaders.php';
\Rateb\App\Core\SecurityHeaders::send();

$probe = trim((string) ($_GET['probe'] ?? ''));
$dispatch = trim((string) ($_GET['dispatch'] ?? ''));

/** @var list<string> */
$forbiddenProbes = [
    'branch-ops',
    'admin-live',
    'dispatch',
    'all-routes',
    'routes',
    'approvals',
];

if ($dispatch !== '' || in_array($probe, $forbiddenProbes, true)) {
    http_response_code(404);
    echo json_encode(['status' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($probe === '') {
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once RATEB_ROOT . '/app/Core/HealthProbeAuth.php';
if (!\Rateb\App\Core\HealthProbeAuth::verifyRequest()) {
    http_response_code(403);
    echo json_encode(['status' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once RATEB_ROOT . '/config/database.php';
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$isProduction = function_exists('rateb_is_production') && rateb_is_production();

try {
    if ($probe === 'ping') {
        echo json_encode(['status' => 'ok', 'ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($probe === 'schema' || $probe === 'admin-dash') {
        $pdo = \Rateb\App\Core\Database::connection();
        $migrationFiles = [
            '131_financial_branch_isolation.sql',
            '132_interbranch_gl_consolidation.sql',
            '133_phase5_api_branch_hq_reports.sql',
            '134_contracts_branch_catchup.sql',
            '135_phase6_interbranch_execution.sql',
            '129_inter_branch_transfers.sql',
        ];
        $allApplied = true;
        foreach ($migrationFiles as $mf) {
            $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
            $stmt->execute(['f' => $mf]);
            if (!$stmt->fetch()) {
                $allApplied = false;
                break;
            }
        }

        if ($isProduction) {
            echo json_encode([
                'ok' => $allApplied,
                'status' => $allApplied ? 'ok' : 'degraded',
                'migrations_ready' => $allApplied,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $report = [
            'ok' => $allApplied,
            'status' => $allApplied ? 'ok' : 'degraded',
            'migrations' => [],
            'columns' => [],
        ];
        foreach ($migrationFiles as $mf) {
            $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
            $stmt->execute(['f' => $mf]);
            $report['migrations'][$mf] = $stmt->fetch() ? 'applied' : 'missing';
        }
        foreach ([
            ['rateb_journal_lines', 'branch_id'],
            ['rateb_journal_entries', 'branch_id'],
            ['rateb_branch_transfers', 'status'],
        ] as [$table, $col]) {
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($col)
            );
            $report['columns'][$table . '.' . $col] = ($stmt !== false && $stmt->fetch()) ? 'yes' : 'no';
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(404);
    echo json_encode(['status' => 'not_found'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    if ($isProduction) {
        echo json_encode(['status' => 'error'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
