<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Support\AccountingConfig;

/**
 * Enforces period locks on ledger mutations — advisory soft-reject, never blocks pipeline internally.
 */
final class AccountingLedgerLockManager
{
    public function __construct(
        private readonly IntegrityRepository $repository = new IntegrityRepository(),
    ) {
    }

    public function isEnforcementEnabled(): bool
    {
        return AccountingConfig::ledgerLockEnforcementEnabled();
    }

    /**
     * @param string $operation create|update|void|post
     */
    public function assertMutable(
        int $companyId,
        string $entryDate,
        ?int $branchId = null,
        string $operation = 'create'
    ): AccountingLockVerdict {
        if ($companyId < 1) {
            return new AccountingLockVerdict(AccountingLockVerdict::ALLOWED, 'Invalid company — no lock check');
        }

        $periodFrom = date('Y-m-01', strtotime($entryDate) ?: time());
        $periodTo = date('Y-m-t', strtotime($entryDate) ?: time());

        $closure = $this->repository->fetchPeriodClosure($companyId, $periodFrom, $periodTo, $branchId);
        if ($closure === null) {
            return new AccountingLockVerdict(
                AccountingLockVerdict::ALLOWED,
                'Period open',
                null,
                $periodFrom,
                $periodTo
            );
        }

        $status = (string) $closure['status'];

        if ($status === 'hard_closed') {
            if ($this->isEnforcementEnabled()) {
                return new AccountingLockVerdict(
                    AccountingLockVerdict::HARD_REJECT,
                    "Period {$periodFrom}–{$periodTo} is hard closed — {$operation} rejected",
                    $status,
                    $periodFrom,
                    $periodTo
                );
            }

            return new AccountingLockVerdict(
                AccountingLockVerdict::FLAGGED,
                "Period hard closed — {$operation} flagged (enforcement off)",
                $status,
                $periodFrom,
                $periodTo
            );
        }

        if ($status === 'soft_closed') {
            return new AccountingLockVerdict(
                AccountingLockVerdict::SOFT_REJECT,
                "Period soft closed — {$operation} should be reviewed",
                $status,
                $periodFrom,
                $periodTo
            );
        }

        return new AccountingLockVerdict(
            AccountingLockVerdict::ALLOWED,
            'Period closure recorded but not locking',
            $status,
            $periodFrom,
            $periodTo
        );
    }

    /**
     * Convenience for legacy write paths — returns false when hard-reject under enforcement.
     */
    public function canMutate(int $companyId, string $entryDate, ?int $branchId = null, string $operation = 'create'): bool
    {
        $verdict = $this->assertMutable($companyId, $entryDate, $branchId, $operation);

        if ($verdict->status === AccountingLockVerdict::HARD_REJECT) {
            return false;
        }

        return true;
    }
}
