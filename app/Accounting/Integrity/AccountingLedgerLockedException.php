<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Thrown when ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED blocks a ledger mutation.
 */
final class AccountingLedgerLockedException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $verdictStatus = AccountingLockVerdict::HARD_REJECT,
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
        int $code = 423,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
