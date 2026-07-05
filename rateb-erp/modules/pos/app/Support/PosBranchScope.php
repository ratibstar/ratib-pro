<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Support;

/** Branch isolation for POS read paths (admin filter + register session). */
final class PosBranchScope
{
    /** @return array{0: string, 1: array<string, mixed>} */
    public static function readFilterSql(string $alias = 'o', string $column = 'branch_id'): array
    {
        if (function_exists('rateb_branch_filter_sql')) {
            return rateb_branch_filter_sql($alias, $column);
        }
        return ['', []];
    }

    /** @param array<string, mixed>|null $row */
    public static function assertOrderReadable(?array $row): void
    {
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }
        $branchId = (int) ($row['branch_id'] ?? 0);
        if ($branchId > 0) {
            PosFkValidator::assertBranchAccess($branchId);
        }
    }

    /** @param array<string, mixed>|null $row */
    public static function assertSnapshotReadable(?array $row): void
    {
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }
        $branchId = (int) ($row['branch_id'] ?? 0);
        if ($branchId > 0) {
            PosFkValidator::assertBranchAccess($branchId);
        }
    }

    public static function registerBranchId(int $branchId): int
    {
        if ($branchId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        PosFkValidator::assertBranchAccess($branchId);
        return $branchId;
    }
}
