<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Phase E — payroll → accounting feature flag (env-based, default OFF).
 *
 * HR_PAYROLL_ACCOUNTING_ENABLED=false|0|off|empty → disabled
 * Only explicit true/1/on/yes enables the adapter.
 */
final class HrPayrollAccountingConfig
{
    public const ENV_ENABLED = 'HR_PAYROLL_ACCOUNTING_ENABLED';
    public const ENV_EXPENSE_CODE = 'HR_PAYROLL_EXPENSE_ACCOUNT_CODE';
    public const ENV_PAYABLE_CODE = 'HR_PAYROLL_PAYABLE_ACCOUNT_CODE';
    public const ENV_DEDUCTION_CODE = 'HR_PAYROLL_DEDUCTION_ACCOUNT_CODE';

    public const DEFAULT_EXPENSE_CODE = '5020101';
    public const DEFAULT_PAYABLE_CODE = '20105';
    public const DEFAULT_DEDUCTION_CODE = '20104';

    public static function isEnabled(): bool
    {
        $raw = self::env(self::ENV_ENABLED);
        if ($raw === null || $raw === '') {
            return false;
        }
        $v = strtolower(trim($raw));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function expenseAccountCode(): string
    {
        $c = trim((string) (self::env(self::ENV_EXPENSE_CODE) ?? ''));
        return $c !== '' ? $c : self::DEFAULT_EXPENSE_CODE;
    }

    public static function payableAccountCode(): string
    {
        $c = trim((string) (self::env(self::ENV_PAYABLE_CODE) ?? ''));
        return $c !== '' ? $c : self::DEFAULT_PAYABLE_CODE;
    }

    public static function deductionAccountCode(): string
    {
        $c = trim((string) (self::env(self::ENV_DEDUCTION_CODE) ?? ''));
        return $c !== '' ? $c : self::DEFAULT_DEDUCTION_CODE;
    }

    private static function env(string $key): ?string
    {
        if (isset($_ENV[$key]) && is_string($_ENV[$key])) {
            return $_ENV[$key];
        }
        $g = getenv($key);
        return $g === false ? null : (string) $g;
    }
}
