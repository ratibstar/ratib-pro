<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

final class BranchesSetupService
{
  private const MIGRATION_FILE = '119_branches.sql';

  /** @return array<string, mixed> */
  public function report(?int $companyId = null): array
  {
    if (function_exists('rateb_bootstrap_ops_tenant')) {
      rateb_bootstrap_ops_tenant();
    }
    $companyId = $companyId ?? (int) (TenantContext::companyId() ?? 0);
    if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
      $companyId = rateb_resolve_ops_company_id();
    }

    $db = Database::connection();
    $tableOk = $this->tableExists($db);
    $migrationApplied = $this->migrationApplied($db);
    $perms = $this->permissionStatus($db);
    $branchCount = 0;
    $branches = [];
    if ($tableOk && $companyId > 0) {
      $branchCount = (new Branch())->count(['company_id' => $companyId]);
      $branches = (new Branch())->all(20, 0, ['company_id' => $companyId]);
    }

    $checks = [
      [
        'id' => 'table',
        'label' => 'branch_check_table',
        'done' => $tableOk,
        'hint' => 'rateb_branches',
      ],
      [
        'id' => 'migration',
        'label' => 'branch_check_migration',
        'done' => $migrationApplied || $tableOk,
        'hint' => self::MIGRATION_FILE,
      ],
      [
        'id' => 'permissions',
        'label' => 'branch_check_permissions',
        'done' => $perms['view'] && $perms['manage'],
        'hint' => 'branches.view / branches.manage',
      ],
      [
        'id' => 'nav',
        'label' => 'branch_check_nav',
        'done' => function_exists('rateb_nav_can') && rateb_nav_can('branches.view'),
        'hint' => __('branches'),
      ],
      [
        'id' => 'crud',
        'label' => 'branch_check_crud',
        'done' => $tableOk && class_exists(\Rateb\App\Controllers\Company\BranchesController::class),
        'hint' => rateb_app_route('branches'),
      ],
      [
        'id' => 'map_url',
        'label' => 'branch_check_map_url',
        'done' => function_exists('rateb_external_url')
            && rateb_external_url('ratib.sa') === 'https://ratib.sa',
        'hint' => 'rateb_external_url()',
      ],
      [
        'id' => 'data',
        'label' => 'branch_check_has_branches',
        'done' => $branchCount > 0,
        'hint' => (string) $branchCount,
      ],
    ];

    $pending = [
      ['label' => 'branch_todo_limit', 'phase' => 1],
      ['label' => 'branch_todo_export', 'phase' => 1],
      ['label' => 'branch_todo_main_auto', 'phase' => 1],
      ['label' => 'branch_todo_accounting', 'phase' => 2],
      ['label' => 'branch_todo_warehouse', 'phase' => 2],
      ['label' => 'branch_todo_users', 'phase' => 2],
    ];

    $doneCount = count(array_filter($checks, static fn (array $c): bool => !empty($c['done'])));

    return [
      'company_id' => $companyId,
      'branch_count' => $branchCount,
      'branches' => $branches,
      'checks' => $checks,
      'pending' => $pending,
      'done_count' => $doneCount,
      'total_checks' => count($checks),
      'permissions' => $perms,
      'links' => [
        'list' => rateb_url(rateb_app_route('branches')),
        'create' => rateb_url(rateb_app_route('branches/create')),
        'admin' => rateb_url('admin'),
      ],
    ];
  }

  private function tableExists(\PDO $db): bool
  {
    try {
      $db->query('SELECT id FROM rateb_branches LIMIT 0');
      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  private function migrationApplied(\PDO $db): bool
  {
    try {
      $stmt = $db->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
      $stmt->execute(['f' => self::MIGRATION_FILE]);
      return $stmt->fetch() !== false;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /** @return array{view:bool,manage:bool} */
  private function permissionStatus(\PDO $db): array
  {
    $out = ['view' => false, 'manage' => false];
    try {
      $stmt = $db->query(
        "SELECT slug FROM rateb_permissions WHERE slug IN ('branches.view','branches.manage')"
      );
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === 'branches.view') {
          $out['view'] = true;
        }
        if ($slug === 'branches.manage') {
          $out['manage'] = true;
        }
      }
    } catch (\Throwable $e) {
    }
    return $out;
  }
}
