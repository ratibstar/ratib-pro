<?php
declare(strict_types=1);

/**
 * P7-1 WP6 — idempotent backfill: assign main branch to branch-restricted users
 * with zero rateb_user_branches rows.
 *
 * Usage:
 *   php bin/backfill-user-branch-assignments.php --dry-run
 *   php bin/backfill-user-branch-assignments.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

use Rateb\App\Core\Database;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Services\BranchService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
if (in_array('--help', $argv ?? [], true) || in_array('-h', $argv ?? [], true)) {
    fwrite(STDOUT, "Usage: php bin/backfill-user-branch-assignments.php [--dry-run]\n");
    exit(0);
}

$started = microtime(true);
$stats = [
    'dry_run' => $dryRun,
    'companies_processed' => 0,
    'users_scanned' => 0,
    'users_updated' => 0,
    'users_skipped' => 0,
    'errors' => [],
];

try {
    if (!BranchService::userBranchesTableExists()) {
        throw new RuntimeException('rateb_user_branches table is missing.');
    }
    if (!BranchService::branchesTableExists()) {
        throw new RuntimeException('rateb_branches table is missing.');
    }

    $db = Database::connection();
    $branchSvc = new BranchService();
    $roleSlugs = BranchAccessService::BRANCH_RESTRICTED_ROLE_SLUGS;
    $slugParams = [];
    $slugPlaceholders = [];
    foreach (array_values($roleSlugs) as $i => $slug) {
        $key = 'slug_' . $i;
        $slugParams[$key] = $slug;
        $slugPlaceholders[] = ':' . $key;
    }

    $sql = 'SELECT DISTINCT u.id AS user_id, u.company_id
            FROM rateb_users u
            INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
            INNER JOIN rateb_roles r ON r.id = ur.role_id
            WHERE COALESCE(u.is_super_admin, 0) = 0
              AND u.company_id IS NOT NULL AND u.company_id > 0
              AND r.slug IN (' . implode(',', $slugPlaceholders) . ')
              AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = u.company_id)
              AND NOT EXISTS (
                  SELECT 1 FROM rateb_user_branches ub WHERE ub.user_id = u.id LIMIT 1
              )
            ORDER BY u.company_id ASC, u.id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($slugParams);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $stats['users_scanned'] = count($rows);
    $companiesTouched = [];
    $defaultBranchByCompany = [];
    $insert = $db->prepare(
        'INSERT IGNORE INTO rateb_user_branches (user_id, branch_id) VALUES (:uid, :bid)'
    );

    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        $companyId = (int) ($row['company_id'] ?? 0);
        if ($userId < 1 || $companyId < 1) {
            $stats['users_skipped']++;
            continue;
        }

        if (!array_key_exists($companyId, $defaultBranchByCompany)) {
            $defaultBranchByCompany[$companyId] = $branchSvc->defaultBranchId($companyId);
        }
        $branchId = (int) $defaultBranchByCompany[$companyId];
        if ($branchId < 1) {
            $stats['users_skipped']++;
            $stats['errors'][] = "user {$userId}: company {$companyId} has no active default branch";
            continue;
        }

        $companiesTouched[$companyId] = true;

        if ($dryRun) {
            $stats['users_updated']++;
            continue;
        }

        $insert->execute(['uid' => $userId, 'bid' => $branchId]);
        if ($insert->rowCount() > 0) {
            $stats['users_updated']++;
        } else {
            $stats['users_skipped']++;
        }
    }

    $stats['companies_processed'] = count($companiesTouched);
    $stats['elapsed_seconds'] = round(microtime(true) - $started, 3);

    echo "RATEB ERP — backfill user branch assignments\n";
    echo str_repeat('=', 44) . "\n";
    echo 'Mode:               ' . ($dryRun ? 'dry-run' : 'execute') . "\n";
    echo 'Companies processed:' . $stats['companies_processed'] . "\n";
    echo 'Users scanned:      ' . $stats['users_scanned'] . "\n";
    echo 'Users updated:      ' . $stats['users_updated'] . "\n";
    echo 'Users skipped:      ' . $stats['users_skipped'] . "\n";
    echo 'Elapsed (s):        ' . $stats['elapsed_seconds'] . "\n";
    if ($stats['errors'] !== []) {
        echo str_repeat('-', 44) . "\n";
        foreach ($stats['errors'] as $err) {
            echo 'WARN: ' . $err . "\n";
        }
    }

    exit($stats['errors'] !== [] && !$dryRun ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
