<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Models/LedgerEntry.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Models/LedgerEntry.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry. No updates or deletes allowed.
 * Use reversal entries to correct errors.
 */
final class LedgerEntry extends Model
{
    protected $table = 'ledger_entries';

    protected $fillable = [
        'ledger_journal_id',
        'ledger_account_id',
        'debit',
        'credit',
        'currency_code',
        'description',
    ];

    protected $casts = [
        'ledger_journal_id' => 'integer',
        'ledger_account_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public $timestamps = false;

    public $incrementing = true;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('Ledger entries are immutable and cannot be updated.'));
        static::deleting(fn () => throw new \RuntimeException('Ledger entries are immutable and cannot be deleted. Use reversal entries instead.'));
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function isDebit(): bool
    {
        return (float) $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }
}
