<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/** Per-request branch access for the active company (multi-company + multi-branch). */
final class BranchContext
{
    private static bool $bootstrapped = false;
    private static int $companyId = 0;
    private static bool $accessAll = true;
    /** @var array<int, int> */
    private static array $allowedIds = [];
    /** HQ user narrowed to one branch via switcher (session rateb_active_branch_filter). */
    private static ?int $activeFilterBranchId = null;

    public static function reset(): void
    {
        self::$bootstrapped = false;
        self::$companyId = 0;
        self::$accessAll = true;
        self::$allowedIds = [];
        self::$activeFilterBranchId = null;
    }

    public static function isBootstrapped(): bool
    {
        return self::$bootstrapped;
    }

    public static function setBootstrapped(int $companyId, bool $accessAll, array $allowedIds): void
    {
        self::$bootstrapped = true;
        self::$companyId = $companyId;
        self::$accessAll = $accessAll;
        self::$allowedIds = array_values(array_unique(array_filter(array_map('intval', $allowedIds), static fn (int $id): bool => $id > 0)));
    }

    public static function setActiveFilterBranchId(?int $branchId): void
    {
        self::$activeFilterBranchId = ($branchId !== null && $branchId > 0) ? $branchId : null;
    }

    public static function activeFilterBranchId(): ?int
    {
        return self::$activeFilterBranchId;
    }

    /** Branch ids that should constrain SQL filters for this request. */
    /** @return array<int, int> */
    public static function effectiveFilterIds(): array
    {
        if (self::$activeFilterBranchId !== null && self::$activeFilterBranchId > 0) {
            return [self::$activeFilterBranchId];
        }
        if (!self::$accessAll) {
            return self::$allowedIds;
        }
        return [];
    }

    public static function shouldApplyBranchFilter(): bool
    {
        return self::effectiveFilterIds() !== [];
    }

    public static function companyId(): int
    {
        return self::$companyId;
    }

    public static function accessAll(): bool
    {
        return self::$accessAll;
    }

    /** @return array<int, int> */
    public static function allowedIds(): array
    {
        return self::$allowedIds;
    }

    public static function canAccess(int $branchId): bool
    {
        if ($branchId < 1) {
            return true;
        }
        if (self::$accessAll) {
            return true;
        }
        return in_array($branchId, self::$allowedIds, true);
    }
}
