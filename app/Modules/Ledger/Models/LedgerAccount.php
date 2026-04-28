<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Models/LedgerAccount.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Models/LedgerAccount.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class LedgerAccount extends Model
{
    use SoftDeletes;

    protected $table = 'ledger_accounts';

    protected $fillable = [
        'agency_id',
        'parent_id',
        'code',
        'name',
        'type',
    ];

    protected $casts = [
        'agency_id' => 'integer',
        'parent_id' => 'integer',
    ];

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    public static function types(): array
    {
        return [
            self::TYPE_ASSET,
            self::TYPE_LIABILITY,
            self::TYPE_EQUITY,
            self::TYPE_REVENUE,
            self::TYPE_EXPENSE,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id');
    }
}
