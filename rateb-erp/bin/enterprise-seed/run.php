<?php
declare(strict_types=1);

/**
 * RATEB ERP Phase 6 — Enterprise staging seed (NOT for production).
 *
 * Targets: 10 companies, 50 branches, 500 users, 1000 employees, 10000 customers,
 * 50000 invoices, 100000 journal entries, 250000 stock movements, 100 warehouses,
 * 500 assets, 500 contracts.
 *
 * Usage (staging only):
 *   RATEB_ENV=staging RATEB_ENTERPRISE_SEED=1 php bin/enterprise-seed/run.php
 *   RATEB_ENV=staging php bin/enterprise-seed/run.php --only=companies,branches
 */
define('RATEB_ROOT', dirname(__DIR__, 2));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once __DIR__ . '/guard.php';
enterprise_seed_guard();

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/EnterpriseSeeder.php';

$only = [];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_filter(array_map('trim', explode(',', substr($arg, 7))));
    }
}

$seeder = new EnterpriseSeeder();
$steps = $only !== [] ? $only : [
    'companies', 'branches', 'users', 'employees', 'customers',
    'warehouses', 'inventory', 'stock_movements', 'assets', 'contracts',
    'journal_entries', 'invoices',
];

foreach ($steps as $step) {
    $method = 'seed' . str_replace('_', '', ucwords($step, '_'));
    if (!method_exists($seeder, $method)) {
        fwrite(STDERR, "Unknown step: {$step}\n");
        continue;
    }
    echo "==> {$step}\n";
    $seeder->$method();
}

echo "Enterprise seed complete.\n";
