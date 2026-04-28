<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Models/LedgerJournal.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Models/LedgerJournal.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LedgerJournal extends Model
{
    protected $table = 'ledger_journals';

    protected $fillable = [
        'agency_id',
        'description',
        'reference_type',
        'reference_id',
        'currency_code',
        'posted_at',
    ];

    protected $casts = [
        'agency_id' => 'integer',
        'reference_id' => 'integer',
        'posted_at' => 'datetime',
    ];

    public $timestamps = true;

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_journal_id');
    }
}
